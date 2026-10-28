<?php
/**
 * admin/archives.php : archivage électronique
 *  - crée une archive ZIP datée : export CSV de toutes les tables + fichier d'empreintes SHA-256
 *  - enregistre l'empreinte du ZIP dans la base (registre) pour prouver qu'il n'a pas été modifié
 *  - vérifie l'intégrité d'une archive (stockée sur le serveur ou envoyée depuis l'ordinateur)
 * Aucun script SQL à lancer : la table du registre se crée toute seule.
 */
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notification.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit();
}

define('ARC_DIR', dirname(__DIR__) . '/uploads/archives/');
define('ARC_WARN_SIZE', 9.5 * 1024 * 1024);   // InfinityFree : ~10 Mo maximum
const ARC_EXCLUDE_TABLES = ['sessions_actives', 'pending_users', 'archives_log'];   // sessions, codes OTP, registre lui-même
const ARC_SECRET_COLS = '/(mot_de_passe|password|passwd|otp|token|secret)/i';          // jamais exportées

$pdo->exec("CREATE TABLE IF NOT EXISTS archives_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom_fichier VARCHAR(120) NOT NULL,
    taille BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    nb_tables INT NOT NULL,
    nb_lignes INT NOT NULL,
    id_utilisateur INT NULL,
    nom_admin VARCHAR(100) NULL,
    date_creation DATETIME NOT NULL,
    UNIQUE KEY uk_nom (nom_fichier),
    KEY idx_sha (sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ---------- fonctions ---------- */
function arc_q($id)
{
    return '`' . str_replace('`', '``', $id) . '`';
}

function arc_tables(PDO $pdo)
{
    $out = [];
    foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $r) {
        if (!in_array($r[0], ARC_EXCLUDE_TABLES, true)) {
            $out[] = $r[0];
        }
    }
    sort($out);
    return $out;
}

/** Écrit une table dans un CSV (UTF-8 avec BOM) sans les colonnes secrètes. Renvoie [nb lignes, colonnes exclues]. */
function arc_export_table(PDO $pdo, $table, $file)
{
    $cols = [];
    $excluded = [];
    foreach ($pdo->query("SHOW COLUMNS FROM " . arc_q($table))->fetchAll(PDO::FETCH_ASSOC) as $c) {
        if (preg_match(ARC_SECRET_COLS, $c['Field'])) {
            $excluded[] = $c['Field'];
        } else {
            $cols[] = $c['Field'];
        }
    }
    $fh = fopen($file, 'w');
    fwrite($fh, "\xEF\xBB\xBF");
    $n = 0;
    if ($cols) {
        fputcsv($fh, $cols, ',', '"', '\\');
        $st = $pdo->query("SELECT " . implode(',', array_map('arc_q', $cols)) . " FROM " . arc_q($table));
        while ($r = $st->fetch(PDO::FETCH_NUM)) {
            fputcsv($fh, $r, ',', '"', '\\');
            $n++;
        }
        $st->closeCursor();
    }
    fclose($fh);
    return [$n, $excluded];
}

function arc_prepare_dir()
{
    if (!is_dir(ARC_DIR) && !@mkdir(ARC_DIR, 0755, true)) {
        throw new RuntimeException("Impossible de créer le dossier uploads/archives (droits d'écriture ?).");
    }
    if (!is_writable(ARC_DIR)) {
        @chmod(ARC_DIR, 0775);
        clearstatcache();
        if (!is_writable(ARC_DIR)) {
            throw new RuntimeException("Le dossier uploads/archives n'est pas accessible en écriture (droits 755 ou 775).");
        }
    }
    if (!is_file(ARC_DIR . '.htaccess')) {
        @file_put_contents(ARC_DIR . '.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    }
}

/** Crée l'archive ZIP et l'enregistre dans le registre. Renvoie la ligne du registre. */
function arc_create(PDO $pdo, $adminId, $adminName)
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException("L'extension PHP « zip » n'est pas activée sur ce serveur.");
    }
    arc_prepare_dir();
    @set_time_limit(180);

    $name = 'archive_bertin_movie_' . date('Y-m-d_His') . '.zip';
    $path = ARC_DIR . $name;
    $tmp = [];
    $entries = [];      // 'csv/table.csv' => ['file' => tmp, 'sha' => hash]
    $summary = [];
    $total = 0;

    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);   // lecture ligne par ligne : peu de mémoire
    try {
        foreach (arc_tables($pdo) as $t) {
            $f = tempnam(ARC_DIR, 'tmp');
            if ($f === false) {
                throw new RuntimeException("Impossible de créer un fichier temporaire.");
            }
            $tmp[] = $f;
            list($n, $excluded) = arc_export_table($pdo, $t, $f);
            $entries['csv/' . $t . '.csv'] = ['file' => $f, 'sha' => hash_file('sha256', $f)];
            $summary[] = [$t, $n, $excluded];
            $total += $n;
        }
    } catch (Throwable $e) {
        foreach ($tmp as $f) {
            @unlink($f);
        }
        throw $e;
    } finally {
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    }

    $readme = "ARCHIVE ÉLECTRONIQUE - BERTIN MOVIE\r\n";
    $readme .= "Créée le : " . date('d/m/Y à H:i:s') . "\r\n";
    $readme .= "Créée par : " . $adminName . "\r\n";
    $readme .= "Contenu : " . count($summary) . " tables, " . $total . " lignes (une table = un fichier CSV, UTF-8)\r\n\r\n";
    $readme .= "Table | lignes | colonnes exclues volontairement (secrets)\r\n";
    foreach ($summary as $s) {
        $readme .= $s[0] . " | " . $s[1] . " | " . ($s[2] ? implode(', ', $s[2]) : '-') . "\r\n";
    }
    $readme .= "\r\nTables non exportées : " . implode(', ', ARC_EXCLUDE_TABLES) . " (sessions, codes de vérification, registre).\r\n\r\n";
    $readme .= "VÉRIFIER L'INTÉGRITÉ\r\n";
    $readme .= "Le fichier empreintes_sha256.txt contient l'empreinte SHA-256 de chaque fichier de l'archive.\r\n";
    $readme .= "Linux / macOS : sha256sum -c empreintes_sha256.txt\r\n";
    $readme .= "Windows (PowerShell) : Get-FileHash .\\csv\\film.csv -Algorithm SHA256\r\n";
    $readme .= "L'empreinte du ZIP lui-même est conservée dans la base de données (page Archives > Vérifier).\r\n";

    $manifest = '';
    ksort($entries);
    foreach ($entries as $e => $info) {
        $manifest .= $info['sha'] . '  ' . $e . "\n";
    }
    $manifest .= hash('sha256', $readme) . "  LISEZMOI.txt\n";

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        foreach ($tmp as $f) {
            @unlink($f);
        }
        throw new RuntimeException("Impossible de créer le fichier ZIP.");
    }
    foreach ($entries as $e => $info) {
        $zip->addFile($info['file'], $e);
    }
    $zip->addFromString('LISEZMOI.txt', $readme);
    $zip->addFromString('empreintes_sha256.txt', $manifest);
    $closed = $zip->close();
    foreach ($tmp as $f) {
        @unlink($f);
    }
    if (!$closed || !is_file($path)) {
        throw new RuntimeException("L'écriture du ZIP a échoué (espace disque ou droits).");
    }

    $row = [
        'nom_fichier' => $name,
        'taille' => filesize($path),
        'sha256' => hash_file('sha256', $path),
        'nb_tables' => count($summary),
        'nb_lignes' => $total,
        'id_utilisateur' => $adminId,
        'nom_admin' => $adminName,
        'date_creation' => date('Y-m-d H:i:s'),
    ];
    $pdo->prepare("INSERT INTO archives_log (nom_fichier, taille, sha256, nb_tables, nb_lignes, id_utilisateur, nom_admin, date_creation)
                   VALUES (:nom_fichier, :taille, :sha256, :nb_tables, :nb_lignes, :id_utilisateur, :nom_admin, :date_creation)")->execute($row);
    return $row;
}

/** Contrôle le contenu d'un ZIP à partir de son fichier d'empreintes interne. */
function arc_check_zip($path)
{
    $res = ['ok' => false, 'checked' => 0, 'total' => 0, 'problems' => []];
    if (!class_exists('ZipArchive')) {
        $res['problems'][] = "Extension PHP « zip » absente.";
        return $res;
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        $res['problems'][] = "Archive illisible : ZIP invalide ou corrompu.";
        return $res;
    }
    $man = $zip->getFromName('empreintes_sha256.txt');
    if ($man === false) {
        $zip->close();
        $res['problems'][] = "Le fichier empreintes_sha256.txt est absent de l'archive.";
        return $res;
    }
    $expected = [];
    foreach (preg_split('/\R/', trim($man)) as $line) {
        if (preg_match('/^([a-f0-9]{64})\s+\*?(.+)$/i', trim($line), $m)) {
            $expected[trim($m[2])] = strtolower($m[1]);
        }
    }
    $res['total'] = count($expected);
    foreach ($expected as $entry => $sha) {
        $s = $zip->getStream($entry);
        if (!$s) {
            $res['problems'][] = "Fichier manquant : " . $entry;
            continue;
        }
        $ctx = hash_init('sha256');
        while (!feof($s)) {
            hash_update($ctx, fread($s, 65536));
        }
        fclose($s);
        if (hash_equals($sha, hash_final($ctx))) {
            $res['checked']++;
        } else {
            $res['problems'][] = "Contenu modifié : " . $entry;
        }
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = $zip->getNameIndex($i);
        if ($n !== 'empreintes_sha256.txt' && substr($n, -1) !== '/' && !isset($expected[$n])) {
            $res['problems'][] = "Fichier ajouté (non listé dans les empreintes) : " . $n;
        }
    }
    $zip->close();
    $res['ok'] = $res['total'] > 0 && !$res['problems'];
    return $res;
}

function arc_size($n)
{
    return $n >= 1048576 ? number_format($n / 1048576, 2, ',', ' ') . ' Mo' : number_format(max(1, $n) / 1024, 0, ',', ' ') . ' Ko';
}

/* ---------- jeton CSRF ---------- */
if (empty($_SESSION['csrf_archives'])) {
    $_SESSION['csrf_archives'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_archives'];
$adminId = (int) ($_SESSION['user_id'] ?? 0);
$adminName = (string) ($_SESSION['user_nom'] ?? $_SESSION['username'] ?? ('admin #' . $adminId));

/* ---------- téléchargement (GET) ---------- */
if (isset($_GET['dl'])) {
    $st = $pdo->prepare("SELECT nom_fichier FROM archives_log WHERE id = ?");
    $st->execute([(int) $_GET['dl']]);
    $r = $st->fetch();
    $file = $r ? ARC_DIR . basename($r['nom_fichier']) : '';
    if (!$r || !is_file($file)) {
        http_response_code(404);
        exit('Archive introuvable');
    }
    session_write_close();
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($r['nom_fichier']) . '"');
    header('Content-Length: ' . filesize($file));
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}

/* ---------- actions (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        showNotif('error', "Fichier refusé par le serveur : il dépasse la taille maximale d'envoi (post_max_size).");
        header("Location: archives.php");
        exit();
    }
    if (!isset($_POST['csrf']) || !hash_equals($csrf, (string) $_POST['csrf'])) {
        showNotif('error', "Action refusée (session expirée). Rechargez la page puis réessayez.");
        header("Location: archives.php");
        exit();
    }
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'creer') {
            $row = arc_create($pdo, $adminId, $adminName);
            addHistoriqueAction($pdo, $adminId, 'archivage', 'archive', null, $row['nom_fichier'], "Archive ZIP créée : " . $row['nom_fichier'] . " (" . $row['nb_tables'] . " tables, " . $row['nb_lignes'] . " lignes)");
            if ($row['taille'] > ARC_WARN_SIZE) {
                showNotif('warning', "Archive créée (" . arc_size($row['taille']) . "), mais elle dépasse ~10 Mo : sur InfinityFree le téléchargement ou l'envoi peut échouer.");
            } else {
                showNotif('success', "Archive créée : " . $row['nom_fichier'] . " (" . arc_size($row['taille']) . ").");
            }
        } elseif ($action === 'supprimer') {
            $st = $pdo->prepare("SELECT * FROM archives_log WHERE id = ?");
            $st->execute([(int) ($_POST['id'] ?? 0)]);
            if ($r = $st->fetch()) {
                @unlink(ARC_DIR . basename($r['nom_fichier']));
                $pdo->prepare("DELETE FROM archives_log WHERE id = ?")->execute([$r['id']]);
                addHistoriqueAction($pdo, $adminId, 'suppression', 'archive', null, $r['nom_fichier'], "Archive ZIP supprimée : " . $r['nom_fichier']);
                showNotif('success', "Archive supprimée : " . $r['nom_fichier']);
            }
        } elseif ($action === 'verifier') {
            $st = $pdo->prepare("SELECT * FROM archives_log WHERE id = ?");
            $st->execute([(int) ($_POST['id'] ?? 0)]);
            if ($r = $st->fetch()) {
                $file = ARC_DIR . basename($r['nom_fichier']);
                if (!is_file($file)) {
                    $_SESSION['arc_verif'] = ['titre' => $r['nom_fichier'], 'registre' => false, 'registre_msg' => "Le fichier n'existe plus sur le serveur.", 'check' => ['ok' => false, 'checked' => 0, 'total' => 0, 'problems' => []]];
                } else {
                    $same = hash_equals(strtolower($r['sha256']), hash_file('sha256', $file));
                    $_SESSION['arc_verif'] = [
                        'titre' => $r['nom_fichier'],
                        'registre' => $same,
                        'registre_msg' => $same ? "L'empreinte SHA-256 du ZIP correspond à celle enregistrée le " . date('d/m/Y à H:i', strtotime($r['date_creation'])) . "."
                            : "L'empreinte du ZIP ne correspond PAS à celle enregistrée à sa création : le fichier a été modifié.",
                        'check' => arc_check_zip($file),
                    ];
                }
            }
        } elseif ($action === 'verifier_fichier') {
            $f = $_FILES['zipfile'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                showNotif('error', "Choisissez un fichier ZIP à vérifier (10 Mo maximum).");
            } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'zip') {
                showNotif('error', "Le fichier doit être une archive .zip.");
            } else {
                $sha = hash_file('sha256', $f['tmp_name']);
                $st = $pdo->prepare("SELECT * FROM archives_log WHERE sha256 = ? LIMIT 1");
                $st->execute([$sha]);
                $r = $st->fetch();
                $_SESSION['arc_verif'] = [
                    'titre' => $f['name'],
                    'registre' => (bool) $r,
                    'registre_msg' => $r ? "Ce fichier est identique à l'archive « " . $r['nom_fichier'] . " » créée le " . date('d/m/Y à H:i', strtotime($r['date_creation'])) . " (même empreinte SHA-256)."
                        : "Ce fichier ne correspond à aucune archive enregistrée sur ce serveur : il a été modifié ou provient d'ailleurs.",
                    'sha' => $sha,
                    'check' => arc_check_zip($f['tmp_name']),
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('archives.php : ' . $e->getMessage());
        showNotif('error', "Erreur : " . $e->getMessage());
    }
    header("Location: archives.php");
    exit();
}

/* ---------- données de la page ---------- */
$verif = $_SESSION['arc_verif'] ?? null;
unset($_SESSION['arc_verif']);

$archives = $pdo->query("SELECT * FROM archives_log ORDER BY id DESC LIMIT 100")->fetchAll();
$nb_films_arch = (int) $pdo->query("SELECT COUNT(*) FROM film WHERE est_archive = 1")->fetchColumn();
$zip_ok = class_exists('ZipArchive');
$tables_inc = arc_tables($pdo);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archives - BERTIN MOVIE</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=3">
    <link rel="stylesheet" href="/film_web_dy/admin/hx-style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .ar-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .ar-card {
            padding: 22px;
            border-radius: 18px;
            background: var(--hx-bg);
            color: var(--hx-text);
            border: 1px solid var(--hx-line);
            box-shadow: var(--hx-sh);
        }

        .ar-card h2 {
            margin: 0 0 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 17px;
            font-weight: 700;
        }

        .ar-card h2 i {
            color: var(--hx-blue);
        }

        .ar-card p {
            margin: 0 0 14px;
            font-size: 13.5px;
            line-height: 1.55;
            color: var(--hx-muted);
        }

        .ar-list {
            margin: 0 0 16px;
            padding-left: 18px;
            font-size: 13px;
            line-height: 1.7;
            color: var(--hx-muted);
        }

        .ar-list b {
            color: var(--hx-text);
        }

        .ar-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 0 20px;
            border: 0;
            border-radius: 12px;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            text-decoration: none;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 8px 20px rgba(59, 130, 246, .3);
            transition: transform .2s, box-shadow .2s;
        }

        .ar-btn:hover {
            transform: translateY(-2px);
        }

        .ar-btn:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none;
        }

        .ar-btn.amber {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 8px 20px rgba(245, 158, 11, .3);
        }

        .ar-sm {
            min-height: 34px;
            padding: 0 12px;
            font-size: 12.5px;
            border-radius: 9px;
            box-shadow: none;
        }

        .ar-sm.green {
            background: #10b981;
        }

        .ar-sm.red {
            background: #ef4444;
        }

        .ar-file {
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 12px;
            padding: 10px;
            border-radius: 12px;
            border: 2px dashed var(--hx-line);
            background: var(--hx-bg-2);
            color: var(--hx-text);
            font: inherit;
            font-size: 13.5px;
        }

        .ar-res {
            margin-bottom: 18px;
            padding: 18px 20px;
            border-radius: 16px;
            border: 1px solid;
        }

        .ar-res.ok {
            background: rgba(34, 197, 94, .1);
            border-color: rgba(34, 197, 94, .4);
        }

        .ar-res.ko {
            background: rgba(239, 68, 68, .1);
            border-color: rgba(239, 68, 68, .4);
        }

        .ar-res h3 {
            margin: 0 0 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            color: var(--hx-text);
        }

        .ar-res.ok h3 i {
            color: #16a34a;
        }

        .ar-res.ko h3 i {
            color: #dc2626;
        }

        .ar-res p,
        .ar-res li {
            margin: 4px 0;
            font-size: 13.5px;
            color: var(--hx-text);
            word-break: break-word;
        }

        .ar-res ul {
            margin: 6px 0 0;
            padding-left: 20px;
        }

        .ar-res code {
            font-size: 12px;
            word-break: break-all;
        }

        .ar-table-card {
            padding: 0;
            overflow: hidden;
        }

        .ar-table-card h2 {
            padding: 20px 22px 0;
        }

        .ar-table-card>p {
            padding: 0 22px;
        }

        .ar-wrap {
            overflow-x: auto;
        }

        .ar-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }

        .ar-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--hx-muted-2);
            background: var(--hx-bg-2);
            border-top: 1px solid var(--hx-line);
            border-bottom: 1px solid var(--hx-line);
            white-space: nowrap;
        }

        .ar-table td {
            padding: 13px 16px;
            vertical-align: middle;
            border-bottom: 1px solid var(--hx-line);
            color: var(--hx-text);
        }

        .ar-table tr:last-child td {
            border-bottom: 0;
        }

        .ar-name {
            font-weight: 600;
            word-break: break-all;
        }

        .ar-sha {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font: 12px/1.2 ui-monospace, Consolas, monospace;
            color: var(--hx-muted);
            cursor: pointer;
            border: 0;
            background: transparent;
            padding: 3px 6px;
            border-radius: 6px;
        }

        .ar-sha:hover {
            background: var(--hx-bg-2);
            color: var(--hx-blue);
        }

        .ar-acts {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .ar-acts form {
            margin: 0;
        }

        .ar-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--hx-muted);
            font-size: 14px;
        }

        .ar-empty i {
            display: block;
            margin-bottom: 10px;
            font-size: 32px;
            opacity: .5;
        }

        .ar-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            color: #d97706;
            background: rgba(245, 158, 11, .12);
            border: 1px solid rgba(245, 158, 11, .35);
        }

        @media (max-width: 900px) {
            .ar-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .ar-card {
                padding: 18px 16px;
                border-radius: 16px;
            }

            .ar-btn {
                width: 100%;
            }

            .ar-sm {
                width: auto;
                flex: 1;
            }

            .ar-table thead {
                display: none;
            }

            .ar-table,
            .ar-table tbody,
            .ar-table tr,
            .ar-table td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }

            .ar-table tr {
                padding: 12px 16px;
                border-bottom: 1px solid var(--hx-line);
            }

            .ar-table tr:last-child {
                border-bottom: 0;
            }

            .ar-table td {
                padding: 4px 0;
                border: 0;
                display: flex;
                justify-content: space-between;
                gap: 12px;
                text-align: right;
            }

            .ar-table td::before {
                content: attr(data-l);
                flex: none;
                font-size: 11.5px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .05em;
                color: var(--hx-muted-2);
                text-align: left;
            }

            .ar-table td.ar-nm {
                display: block;
                text-align: left;
                padding-bottom: 8px;
            }

            .ar-table td.ar-nm::before {
                display: none;
            }

            .ar-table td.ar-ac {
                display: block;
                padding-top: 10px;
            }

            .ar-table td.ar-ac::before {
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../includes/menu.php'; ?>
    <?php renderNotifications(); ?>

    <div class="content hx-wrap">

        <div class="hx-head">
            <div class="hx-title">
                <div class="hx-title-ico"
                    style="background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 10px 24px rgba(245,158,11,.35);">
                    <i class="fas fa-box-archive"></i></div>
                <div>
                    <h1>Archives</h1>
                    <p>Archivage électronique avec preuve d'intégrité (SHA-256)</p>
                </div>
            </div>
            <a class="ar-pill" href="film.php?vue=archives"><i class="fas fa-film"></i> <?= $nb_films_arch ?>
                film<?= $nb_films_arch > 1 ? 's' : '' ?> archivé<?= $nb_films_arch > 1 ? 's' : '' ?> dans le
                catalogue</a>
        </div>

        <?php if ($verif):
            $c = $verif['check'];
            $good = $verif['registre'] && $c['ok']; ?>
            <div class="ar-res <?= $good ? 'ok' : 'ko' ?>">
                <h3><i class="fas <?= $good ? 'fa-shield-check' : 'fa-triangle-exclamation' ?>"></i>
                    <?= $good ? 'Archive intègre : aucune modification détectée' : 'Problème détecté sur cette archive' ?>
                </h3>
                <p><strong><?= htmlspecialchars($verif['titre']) ?></strong></p>
                <p><i class="fas <?= $verif['registre'] ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                    <?= htmlspecialchars($verif['registre_msg']) ?></p>
                <?php if (!empty($verif['sha'])): ?>
                    <p>SHA-256 : <code><?= htmlspecialchars($verif['sha']) ?></code></p><?php endif; ?>
                <p><i class="fas <?= $c['ok'] ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                    Contenu : <?= (int) $c['checked'] ?> fichier(s) conforme(s) sur <?= (int) $c['total'] ?> listé(s) dans
                    empreintes_sha256.txt.</p>
                <?php if ($c['problems']): ?>
                    <ul><?php foreach ($c['problems'] as $pb): ?>
                            <li><?= htmlspecialchars($pb) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="ar-grid">
            <div class="ar-card">
                <h2><i class="fas fa-file-zipper"></i> Créer une archive</h2>
                <p>Génère un ZIP daté contenant l'export CSV de la base de données et un fichier d'empreintes SHA-256.
                    L'empreinte du ZIP est aussi enregistrée en base.</p>
                <ul class="ar-list">
                    <li><b><?= count($tables_inc) ?> tables</b> exportées en CSV (un fichier par table)</li>
                    <li><b>empreintes_sha256.txt</b> : preuve que les fichiers n'ont pas été modifiés</li>
                    <li>Mots de passe, codes de vérification et jetons <b>jamais exportés</b></li>
                </ul>
                <?php if (!$zip_ok): ?>
                    <p style="color:#dc2626;"><i class="fas fa-triangle-exclamation"></i> L'extension PHP « zip » n'est pas
                        activée sur ce serveur.</p>
                <?php endif; ?>
                <form method="POST"
                    onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerHTML = '<i class=&quot;fas fa-circle-notch fa-spin&quot;></i> Création en cours…';">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="creer">
                    <button type="submit" class="ar-btn amber" <?= $zip_ok ? '' : 'disabled' ?>><i
                            class="fas fa-box-archive"></i> Créer une archive maintenant</button>
                </form>
            </div>

            <div class="ar-card">
                <h2><i class="fas fa-shield-halved"></i> Vérifier un fichier</h2>
                <p>Envoyez un ZIP : le système compare son empreinte SHA-256 au registre et contrôle chaque fichier à
                    l'intérieur. Toute modification, même d'une virgule, est détectée.</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="verifier_fichier">
                    <input class="ar-file" type="file" name="zipfile" accept=".zip,application/zip" required>
                    <button type="submit" class="ar-btn"><i class="fas fa-magnifying-glass"></i> Vérifier
                        l'intégrité</button>
                </form>
            </div>
        </div>

        <div class="ar-card ar-table-card">
            <h2><i class="fas fa-clock-rotate-left"></i> Archives créées</h2>
            <p>Conservées dans un dossier protégé (accès uniquement via cette page).</p>
            <?php if (!$archives): ?>
                <div class="ar-empty"><i class="fas fa-box-open"></i>Aucune archive pour l'instant.</div>
            <?php else: ?>
                <div class="ar-wrap">
                    <table class="ar-table">
                        <thead>
                            <tr>
                                <th>Archive</th>
                                <th>Date</th>
                                <th>Taille</th>
                                <th>Contenu</th>
                                <th>SHA-256</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($archives as $a):
                                $exists = is_file(ARC_DIR . basename($a['nom_fichier'])); ?>
                                <tr>
                                    <td class="ar-nm" data-l="Archive"><span
                                            class="ar-name"><?= htmlspecialchars($a['nom_fichier']) ?></span>
                                        <?php if (!$exists): ?><br><small style="color:#dc2626;">Fichier absent du
                                                serveur</small><?php endif; ?></td>
                                    <td data-l="Date"><?= date('d/m/Y H:i', strtotime($a['date_creation'])) ?></td>
                                    <td data-l="Taille"><?= arc_size((int) $a['taille']) ?></td>
                                    <td data-l="Contenu"><?= (int) $a['nb_tables'] ?> tables ·
                                        <?= number_format((int) $a['nb_lignes'], 0, ',', ' ') ?> lignes</td>
                                    <td data-l="SHA-256">
                                        <button type="button" class="ar-sha" title="Copier l'empreinte complète"
                                            data-sha="<?= htmlspecialchars($a['sha256']) ?>">
                                            <?= htmlspecialchars(substr($a['sha256'], 0, 12)) ?>… <i class="far fa-copy"></i>
                                        </button>
                                    </td>
                                    <td class="ar-ac">
                                        <div class="ar-acts">
                                            <?php if ($exists): ?>
                                                <a class="ar-btn ar-sm" href="archives.php?dl=<?= (int) $a['id'] ?>"><i
                                                        class="fas fa-download"></i> Télécharger</a>
                                            <?php endif; ?>
                                            <form method="POST">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                                                <input type="hidden" name="action" value="verifier">
                                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                                <button type="submit" class="ar-btn ar-sm green"><i
                                                        class="fas fa-shield-halved"></i> Vérifier</button>
                                            </form>
                                            <form method="POST"
                                                onsubmit="return confirm('Supprimer définitivement cette archive ?');">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                                                <input type="hidden" name="action" value="supprimer">
                                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                                <button type="submit" class="ar-btn ar-sm red"><i
                                                        class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.querySelectorAll('.ar-sha').forEach(function (b) {
            b.addEventListener('click', function () {
                var t = b.dataset.sha, old = b.innerHTML;
                function done() { b.innerHTML = '<i class="fas fa-check"></i> Copié'; setTimeout(function () { b.innerHTML = old; }, 1400); }
                if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(done); }
                else { var x = document.createElement('textarea'); x.value = t; document.body.appendChild(x); x.select(); try { document.execCommand('copy'); done(); } catch (e) { prompt('SHA-256', t); } x.remove(); }
            });
        });
    </script>
</body>

</html>