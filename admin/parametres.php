<?php
/**
 * admin/parametres.php : PARAMÈTRES DU SYSTÈME (un seul fichier)
 *
 *   Onglet « Réglages »     -> point 44 : paramétrage du système (sécurité, maintenance, thème...)
 *   Onglet « Sauvegardes »  -> points 39 + 40 : sauvegarde (manuelle + automatique) et restauration de la base
 *   Onglet « Versions »     -> point 22 : historique des versions de chaque film + retour à une version
 *
 * Ce fichier sert aussi de « bibliothèque » : auth_check.php et login.php le chargent avec
 * define('BM_PARAM_LIB', 1) pour lire les réglages et lancer la sauvegarde automatique.
 */

/* =========================================================
   1. BIBLIOTHÈQUE (fonctions)
========================================================= */
if (!function_exists('bm_param_defaults')) {

    /* ---------- RÉGLAGES ---------- */
    function bm_param_defaults()
    {
        return [
            'maintenance'     => '0',
            'maintenance_msg' => "Le site est en maintenance. Merci de revenir un peu plus tard.",
            'theme_defaut'    => 'light',
            'lock_max'        => '3',     // tentatives avant verrouillage
            'lock_minutes'    => '15',    // durée du verrouillage
            'idle_minutes'    => '15',    // inactivité avant déconnexion
            'idle_warn'       => '60',    // avertissement avant la coupure (secondes)
            'backup_auto'     => 'off',   // off | daily | weekly | monthly
            'backup_keep'     => '7',     // sauvegardes automatiques conservées
            'version_keep'    => '10',    // versions gardées par film (0 = illimité)
        ];
    }

    function bm_params_table(PDO $pdo)
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS parametres (
                cle VARCHAR(60) NOT NULL PRIMARY KEY,
                valeur TEXT NULL,
                maj TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            error_log('bm_params_table : ' . $e->getMessage());
        }
    }

    function bm_params_all($pdo, $refresh = false)
    {
        static $cache = null;
        if ($cache === null || $refresh) {
            $cache = bm_param_defaults();
            try {
                foreach ($pdo->query("SELECT cle, valeur FROM parametres") as $r) {
                    $cache[$r['cle']] = (string) $r['valeur'];
                }
            } catch (Throwable $e) {
                // table pas encore créée : valeurs par défaut
            }
        }
        return $cache;
    }

    function bm_param($pdo, $key, $default = null)
    {
        $all = bm_params_all($pdo);
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    function bm_param_save(PDO $pdo, $key, $value)
    {
        bm_params_table($pdo);
        $pdo->prepare("INSERT INTO parametres (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")
            ->execute([$key, (string) $value]);
        bm_params_all($pdo, true);
    }

    /* ---------- SAUVEGARDES : fichiers ---------- */
    function bm_backup_dir()
    {
        $dir = dirname(__DIR__) . '/sauvegardes/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }
        if (!file_exists($dir . '.htaccess')) {
            @file_put_contents($dir . '.htaccess',
                "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
        }
        if (!file_exists($dir . 'index.html')) {
            @file_put_contents($dir . 'index.html', '');
        }
        return $dir;
    }

    function bm_backup_valid_name($name)
    {
        return (bool) preg_match('/^bm_\d{8}_\d{6}_(manuel|auto|avant-restauration|import)_[a-f0-9]{8}\.sql$/', (string) $name);
    }

    function bm_backup_list()
    {
        $dir = bm_backup_dir();
        $out = [];
        if (!$dir) {
            return $out;
        }
        foreach (glob($dir . 'bm_*.sql') ?: [] as $f) {
            $n = basename($f);
            if (!preg_match('/^bm_(\d{8})_(\d{6})_(manuel|auto|avant-restauration|import)_[a-f0-9]{8}\.sql$/', $n, $m)) {
                continue;
            }
            $ts = strtotime(substr($m[1], 0, 4) . '-' . substr($m[1], 4, 2) . '-' . substr($m[1], 6, 2) . ' '
                . substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2) . ':' . substr($m[2], 4, 2));
            $out[] = ['name' => $n, 'type' => $m[3], 'ts' => $ts ?: filemtime($f), 'size' => (int) filesize($f)];
        }
        usort($out, function ($a, $b) {
            return $b['ts'] <=> $a['ts'];
        });
        return $out;
    }

    function bm_backup_prune($keep)
    {
        $keep = max(1, (int) $keep);
        $dir = bm_backup_dir();
        if (!$dir) {
            return 0;
        }
        $n = 0;
        $autos = array_values(array_filter(bm_backup_list(), function ($b) {
            return $b['type'] === 'auto';
        }));
        foreach (array_slice($autos, $keep) as $b) {
            if (@unlink($dir . $b['name'])) {
                $n++;
            }
        }
        return $n;
    }

    /** Crée un fichier .sql complet de la base. Retourne ['ok'=>bool, 'file', 'size', 'tables', 'rows', 'error'] */
    function bm_backup_create(PDO $pdo, $type = 'manuel')
    {
        @set_time_limit(300);
        $dir = bm_backup_dir();
        if (!$dir) {
            return ['ok' => false, 'error' => "Impossible de créer ou d'écrire dans le dossier « sauvegardes » (droits d'écriture)."];
        }
        if (!in_array($type, ['manuel', 'auto', 'avant-restauration', 'import'], true)) {
            $type = 'manuel';
        }
        $name = 'bm_' . date('Ymd_His') . '_' . $type . '_' . bin2hex(random_bytes(4)) . '.sql';
        $tmp = $dir . $name . '.part';
        $fh = @fopen($tmp, 'wb');
        if (!$fh) {
            return ['ok' => false, 'error' => "Impossible d'écrire le fichier de sauvegarde."];
        }
        $tables = 0;
        $rows = 0;
        try {
            fwrite($fh, "-- BERTIN MOVIE : sauvegarde de la base de données\n-- Date : " . date('Y-m-d H:i:s') . "\n-- Type : $type\n\n"
                . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $list = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
            foreach ($list as $r) {
                $t = $r[0];
                $q = '`' . str_replace('`', '``', $t) . '`';
                $create = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM);
                fwrite($fh, "-- Table $t\nDROP TABLE IF EXISTS $q;\n" . $create[1] . ";\n\n");
                $tables++;
                if ($t === 'sessions_actives') {
                    continue;   // données temporaires : inutile de les sauvegarder
                }
                $pk = [];
                foreach ($pdo->query("SHOW KEYS FROM $q WHERE Key_name = 'PRIMARY'")->fetchAll() as $k) {
                    $pk[(int) $k['Seq_in_index']] = '`' . str_replace('`', '``', $k['Column_name']) . '`';
                }
                ksort($pk);
                $order = $pk ? ' ORDER BY ' . implode(',', $pk) : '';
                $off = 0;
                $cols = null;
                while (true) {
                    $data = $pdo->query("SELECT * FROM $q$order LIMIT 500 OFFSET $off")->fetchAll(PDO::FETCH_ASSOC);
                    if (!$data) {
                        break;
                    }
                    if ($cols === null) {
                        $cols = '(' . implode(',', array_map(function ($c) {
                            return '`' . str_replace('`', '``', $c) . '`';
                        }, array_keys($data[0]))) . ')';
                    }
                    $vals = [];
                    foreach ($data as $row) {
                        $one = [];
                        foreach ($row as $v) {
                            if ($v === null) {
                                $one[] = 'NULL';
                            } elseif (is_int($v) || is_float($v)) {
                                $one[] = (string) $v;
                            } else {
                                $one[] = $pdo->quote($v);
                            }
                        }
                        $vals[] = '(' . implode(',', $one) . ')';
                    }
                    fwrite($fh, "INSERT INTO $q $cols VALUES\n" . implode(",\n", $vals) . ";\n");
                    $rows += count($data);
                    $off += 500;
                    if (count($data) < 500) {
                        break;
                    }
                }
                fwrite($fh, "\n");
            }
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fh);
            if (!@rename($tmp, $dir . $name)) {
                throw new RuntimeException("Impossible de finaliser le fichier.");
            }
        } catch (Throwable $e) {
            @fclose($fh);
            @unlink($tmp);
            error_log('bm_backup_create : ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Échec de la sauvegarde : ' . $e->getMessage()];
        }
        return ['ok' => true, 'file' => $name, 'size' => (int) filesize($dir . $name), 'tables' => $tables, 'rows' => $rows];
    }

    /** Sauvegarde automatique « sans cron » : appelée par auth_check.php quand un admin navigue. */
    function bm_backup_auto_maybe(PDO $pdo)
    {
        $mode = bm_param($pdo, 'backup_auto', 'off');
        $every = ['daily' => 86400, 'weekly' => 604800, 'monthly' => 2592000][$mode] ?? 0;
        if ($every === 0) {
            return false;
        }
        $lastTry = (int) bm_param($pdo, 'backup_last_try', 0);
        if (time() - $lastTry < 3600) {
            return false;   // pas plus d'un essai par heure (évite de boucler en cas d'erreur)
        }
        $lastAuto = 0;
        foreach (bm_backup_list() as $b) {
            if ($b['type'] === 'auto') {
                $lastAuto = $b['ts'];
                break;
            }
        }
        if (time() - $lastAuto < $every) {
            return false;
        }
        bm_param_save($pdo, 'backup_last_try', time());
        $r = bm_backup_create($pdo, 'auto');
        if ($r['ok']) {
            bm_backup_prune(bm_param($pdo, 'backup_keep', 7));
            bm_trace_safe('BACKUP-AUTO');
        }
        return $r['ok'];
    }

    function bm_trace_safe($event)
    {
        if (function_exists('bm_trace')) {
            try {
                bm_trace($event);
            } catch (Throwable $e) {
            }
        }
    }

    /* ---------- RESTAURATION : lecture sécurisée d'un fichier .sql ---------- */
    function bm_sql_allowed($stmt)
    {
        $s = preg_replace('/^(?:\s+|--[^\n]*\n|#[^\n]*\n|\/\*(?!!).*?\*\/)+/s', '', $stmt . "\n");
        if (preg_match('/^\/\*!\d*\s*(.*)$/s', $s, $m)) {
            $s = $m[1];
        }
        if (!preg_match('/^([A-Za-z]+)\s*([A-Za-z@]+)?/', $s, $m)) {
            return false;
        }
        $a = strtoupper($m[1]);
        $b = strtoupper($m[2] ?? '');
        switch ($a) {
            case 'INSERT':
            case 'REPLACE':
            case 'LOCK':
            case 'UNLOCK':
            case 'START':
            case 'BEGIN':
            case 'COMMIT':
                return true;
            case 'DROP':
            case 'CREATE':
            case 'ALTER':
                return $b === 'TABLE';
            case 'SET':
                return !preg_match('/^SET\s+(GLOBAL|PERSIST|PERSIST_ONLY)\b|@@(global|persist)/i', $s);
        }
        return false;
    }

    function bm_sql_run(PDO $pdo, $stmt, $n, &$error)
    {
        if (!bm_sql_allowed($stmt)) {
            $error = "Instruction non autorisée (n° $n) : « " . mb_substr(preg_replace('/\s+/', ' ', $stmt), 0, 70) . " »";
            return false;
        }
        try {
            $pdo->exec($stmt);
        } catch (Throwable $e) {
            $error = "Erreur à l'instruction n° $n : " . $e->getMessage();
            return false;
        }
        return true;
    }

    /** Exécute un fichier .sql instruction par instruction (gère les guillemets et les « ; » dans le texte). */
    function bm_sql_restore(PDO $pdo, $path, &$count, &$error)
    {
        @set_time_limit(600);
        $count = 0;
        $error = '';
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            $error = 'Fichier illisible.';
            return false;
        }
        $buf = '';
        $inS = $inD = $inB = false;
        $esc = false;
        $n = 0;
        while (($line = fgets($fh)) !== false) {
            if (!$inS && !$inD && !$inB && trim($buf) === '') {
                $t = ltrim($line);
                if ($t === '' || substr($t, 0, 2) === '--' || $t[0] === '#') {
                    continue;
                }
            }
            $len = strlen($line);
            $start = 0;
            for ($i = 0; $i < $len; $i++) {
                $c = $line[$i];
                if ($inS || $inD) {
                    if ($esc) {
                        $esc = false;
                        continue;
                    }
                    if ($c === '\\') {
                        $esc = true;
                        continue;
                    }
                    if ($inS && $c === "'") {
                        $inS = false;
                    } elseif ($inD && $c === '"') {
                        $inD = false;
                    }
                    continue;
                }
                if ($inB) {
                    if ($c === '`') {
                        $inB = false;
                    }
                    continue;
                }
                if ($c === "'") {
                    $inS = true;
                } elseif ($c === '"') {
                    $inD = true;
                } elseif ($c === '`') {
                    $inB = true;
                } elseif ($c === ';') {
                    $stmt = trim($buf . substr($line, $start, $i - $start));
                    $buf = '';
                    $start = $i + 1;
                    if ($stmt !== '') {
                        $n++;
                        if (!bm_sql_run($pdo, $stmt, $n, $error)) {
                            fclose($fh);
                            return false;
                        }
                        $count++;
                    }
                }
            }
            $buf .= substr($line, $start);
        }
        fclose($fh);
        $rest = trim($buf);
        if ($rest !== '' && !preg_match('/^(--[^\n]*\n?)+$/', $rest)) {
            $n++;
            if (!bm_sql_run($pdo, $rest, $n, $error)) {
                return false;
            }
            $count++;
        }
        return true;
    }

    /* ---------- VERSIONS DES FILMS ---------- */
    function bm_versions_table(PDO $pdo)
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS film_versions (
                id_version INT AUTO_INCREMENT PRIMARY KEY,
                id_film INT NOT NULL,
                numero INT NOT NULL,
                donnees LONGTEXT NOT NULL,
                empreinte CHAR(40) NOT NULL,
                source VARCHAR(30) NOT NULL DEFAULT 'detection',
                id_utilisateur INT NULL,
                date_version DATETIME NOT NULL,
                UNIQUE KEY uq_film_num (id_film, numero),
                KEY idx_film (id_film)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            error_log('bm_versions_table : ' . $e->getMessage());
        }
    }

    function bm_version_fields()
    {
        return ['nom_film', 'titre', 'duree', 'date_sortie', 'id_realisateur', 'id_langue', 'id_categorie', 'film_numero'];
    }

    /** Photo « utile » d'un film : champs modifiables + lien vidéo (rien de volatil comme les vues). */
    function bm_film_snapshot(array $film, $video_url = null, $video_thumb = null)
    {
        $s = [];
        foreach (bm_version_fields() as $k) {
            $s[$k] = isset($film[$k]) ? (string) $film[$k] : '';
        }
        $s['video_url'] = (string) $video_url;
        $s['video_thumb'] = (string) $video_thumb;
        return $s;
    }

    function bm_snapshot_hash(array $snap)
    {
        ksort($snap);
        return sha1(json_encode($snap, JSON_UNESCAPED_UNICODE));
    }

    /** Enregistre une nouvelle version si le film a changé. Retourne le numéro créé ou null. */
    function bm_version_add(PDO $pdo, $id_film, array $snap, $source, $uid = null)
    {
        $hash = bm_snapshot_hash($snap);
        $st = $pdo->prepare("SELECT numero, empreinte FROM film_versions WHERE id_film = ? ORDER BY numero DESC LIMIT 1");
        $st->execute([$id_film]);
        $last = $st->fetch();
        if ($last && $last['empreinte'] === $hash) {
            return null;
        }
        $num = $last ? ((int) $last['numero'] + 1) : 1;
        if ($num === 1 && $source === 'detection') {
            $source = 'initiale';
        }
        $pdo->prepare("INSERT INTO film_versions (id_film, numero, donnees, empreinte, source, id_utilisateur, date_version)
                       VALUES (?, ?, ?, ?, ?, ?, NOW())")
            ->execute([$id_film, $num, json_encode($snap, JSON_UNESCAPED_UNICODE), $hash, $source, $uid ?: null]);

        $keep = (int) bm_param($pdo, 'version_keep', 10);
        if ($keep > 0 && $num > $keep) {
            $pdo->prepare("DELETE FROM film_versions WHERE id_film = ? AND numero <= ?")->execute([$id_film, $num - $keep]);
        }
        return $num;
    }

    /** Parcourt tous les films et crée une version pour ceux qui ont changé depuis la dernière. */
    function bm_versions_sync(PDO $pdo, $uid = null, $source = 'detection')
    {
        bm_versions_table($pdo);
        $created = 0;
        try {
            $rows = $pdo->query("SELECT f.*, v.url_video AS _vurl, v.thumbnail AS _vthumb
                                 FROM film f
                                 LEFT JOIN video_film v ON v.id_video = (SELECT MIN(id_video) FROM video_film WHERE id_film = f.id_film)
                                 WHERE f.is_deleted = 0 OR f.is_deleted IS NULL")->fetchAll();
        } catch (Throwable $e) {
            error_log('bm_versions_sync : ' . $e->getMessage());
            return 0;
        }
        $latest = [];
        foreach ($pdo->query("SELECT fv.id_film, fv.empreinte FROM film_versions fv
                              JOIN (SELECT id_film, MAX(numero) m FROM film_versions GROUP BY id_film) x
                                ON x.id_film = fv.id_film AND x.m = fv.numero") as $r) {
            $latest[(int) $r['id_film']] = $r['empreinte'];
        }
        foreach ($rows as $f) {
            $snap = bm_film_snapshot($f, $f['_vurl'] ?? '', $f['_vthumb'] ?? '');
            if (($latest[(int) $f['id_film']] ?? null) === bm_snapshot_hash($snap)) {
                continue;
            }
            if (bm_version_add($pdo, (int) $f['id_film'], $snap, $source, $uid) !== null) {
                $created++;
            }
        }
        return $created;
    }

    /** Remet un film dans l'état d'une ancienne version (nom, titre, durée, réalisateur...). */
    function bm_version_restore(PDO $pdo, $id_version, $uid)
    {
        $st = $pdo->prepare("SELECT * FROM film_versions WHERE id_version = ?");
        $st->execute([$id_version]);
        $ver = $st->fetch();
        if (!$ver) {
            return [false, 'Version introuvable.'];
        }
        $snap = json_decode($ver['donnees'], true) ?: [];
        $id = (int) $ver['id_film'];

        $st = $pdo->prepare("SELECT f.*, v.url_video AS _vurl, v.thumbnail AS _vthumb FROM film f
                             LEFT JOIN video_film v ON v.id_video = (SELECT MIN(id_video) FROM video_film WHERE id_film = f.id_film)
                             WHERE f.id_film = ?");
        $st->execute([$id]);
        $film = $st->fetch();
        if (!$film) {
            return [false, "Le film n'existe plus (il est peut-être dans la corbeille)."];
        }

        // 1) on garde l'état actuel comme version (pour pouvoir annuler la restauration)
        bm_version_add($pdo, $id, bm_film_snapshot($film, $film['_vurl'] ?? '', $film['_vthumb'] ?? ''), 'avant-restauration', $uid);

        // 2) on remet les champs de la version choisie
        $set = [];
        $vals = [];
        foreach (bm_version_fields() as $k) {
            if (array_key_exists($k, $snap)) {
                $set[] = "`$k` = ?";
                $vals[] = ($snap[$k] === '') ? null : $snap[$k];
            }
        }
        if (!$set) {
            return [false, 'Cette version ne contient aucune donnée à restaurer.'];
        }
        $vals[] = $id;
        try {
            $pdo->prepare("UPDATE film SET " . implode(', ', $set) . " WHERE id_film = ?")->execute($vals);
        } catch (Throwable $e) {
            return [false, 'Erreur : ' . $e->getMessage()];
        }

        // 3) la restauration devient elle-même une version
        $st = $pdo->prepare("SELECT f.*, v.url_video AS _vurl, v.thumbnail AS _vthumb FROM film f
                             LEFT JOIN video_film v ON v.id_video = (SELECT MIN(id_video) FROM video_film WHERE id_film = f.id_film)
                             WHERE f.id_film = ?");
        $st->execute([$id]);
        $after = $st->fetch();
        bm_version_add($pdo, $id, bm_film_snapshot($after, $after['_vurl'] ?? '', $after['_vthumb'] ?? ''), 'restauration', $uid);

        return [true, (string) ($snap['nom_film'] ?? ''), (int) $ver['numero'], $id];
    }
}

if (defined('BM_PARAM_LIB')) {
    return;   // chargé comme bibliothèque (auth_check.php / login.php) : on s'arrête ici
}

/* =========================================================
   2. PAGE : accès, actions (POST), données
========================================================= */
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/permissions.php';
require_permission('modifier_parametres');
require_once __DIR__ . '/../includes/update_session.php';
require_once __DIR__ . '/../includes/notification.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$uid = (int) $_SESSION['user_id'];
updateUserSession($pdo, $uid);
bm_params_table($pdo);
bm_versions_table($pdo);

if (empty($_SESSION['bm_csrf'])) {
    $_SESSION['bm_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['bm_csrf'];

function pm_h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function pm_flash($type, $msg, $tab)
{
    $_SESSION['pm_flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: parametres.php?tab=' . urlencode($tab));
    exit();
}

function pm_size($b)
{
    if ($b >= 1048576) {
        return number_format($b / 1048576, 1, ',', ' ') . ' Mo';
    }
    return number_format(max(1, $b) / 1024, 0, ',', ' ') . ' Ko';
}

function pm_clamp($v, $min, $max, $def)
{
    return is_numeric($v) ? max($min, min($max, (int) $v)) : $def;
}

/* ---------- Téléchargement d'une sauvegarde ---------- */
if (isset($_GET['dl'])) {
    $f = (string) $_GET['dl'];
    $dir = bm_backup_dir();
    if ($dir && bm_backup_valid_name($f) && is_file($dir . $f)) {
        addHistoriqueAction($pdo, $uid, 'export', 'sauvegarde', null, $f, 'Sauvegarde téléchargée : ' . $f);
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $f . '"');
        header('Content-Length: ' . filesize($dir . $f));
        header('Cache-Control: no-store');
        readfile($dir . $f);
        exit();
    }
    pm_flash('err', 'Fichier de sauvegarde introuvable.', 'sauvegardes');
}

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        pm_flash('err', 'Le formulaire a expiré. Réessayez.', 'reglages');
    }
    $act = (string) ($_POST['action'] ?? '');
    $dir = bm_backup_dir();

    switch ($act) {

        /* ----- 44 : enregistrer les réglages ----- */
        case 'save_settings':
            $idle = pm_clamp($_POST['idle_minutes'] ?? '', 1, 120, 15);
            $warn = pm_clamp($_POST['idle_warn'] ?? '', 10, 300, 60);
            $warn = min($warn, max(10, (int) floor($idle * 60 / 2)));
            $auto = in_array($_POST['backup_auto'] ?? '', ['off', 'daily', 'weekly', 'monthly'], true) ? $_POST['backup_auto'] : 'off';
            $new = [
                'maintenance'     => !empty($_POST['maintenance']) ? '1' : '0',
                'maintenance_msg' => mb_substr(trim((string) ($_POST['maintenance_msg'] ?? '')), 0, 300),
                'theme_defaut'    => (($_POST['theme_defaut'] ?? '') === 'dark') ? 'dark' : 'light',
                'lock_max'        => (string) pm_clamp($_POST['lock_max'] ?? '', 1, 20, 3),
                'lock_minutes'    => (string) pm_clamp($_POST['lock_minutes'] ?? '', 1, 1440, 15),
                'idle_minutes'    => (string) $idle,
                'idle_warn'       => (string) $warn,
                'backup_auto'     => $auto,
                'backup_keep'     => (string) pm_clamp($_POST['backup_keep'] ?? '', 1, 60, 7),
                'version_keep'    => (string) pm_clamp($_POST['version_keep'] ?? '', 0, 100, 10),
            ];
            if ($new['maintenance_msg'] === '') {
                $new['maintenance_msg'] = bm_param_defaults()['maintenance_msg'];
            }
            foreach ($new as $k => $v) {
                bm_param_save($pdo, $k, $v);
            }
            addHistoriqueAction($pdo, $uid, 'modification', 'parametres', null, 'Paramètres', 'Paramètres du système modifiés');
            pm_flash('ok', 'Réglages enregistrés. Ils sont appliqués immédiatement.', 'reglages');
            break;

        case 'reset_settings':
            $pdo->exec("DELETE FROM parametres WHERE cle NOT IN ('backup_last_try')");
            bm_params_all($pdo, true);
            addHistoriqueAction($pdo, $uid, 'modification', 'parametres', null, 'Paramètres', 'Paramètres remis par défaut');
            pm_flash('info', 'Les valeurs par défaut ont été rétablies.', 'reglages');
            break;

        /* ----- 39 : sauvegarde manuelle ----- */
        case 'backup_now':
            $r = bm_backup_create($pdo, 'manuel');
            if ($r['ok']) {
                addHistoriqueAction($pdo, $uid, 'creation', 'sauvegarde', null, $r['file'], 'Sauvegarde manuelle créée');
                pm_flash('ok', 'Sauvegarde créée : ' . $r['tables'] . ' tables, ' . number_format($r['rows'], 0, ',', ' ') . ' lignes (' . pm_size($r['size']) . ').', 'sauvegardes');
            }
            pm_flash('err', $r['error'], 'sauvegardes');
            break;

        case 'backup_delete':
            $name = (string) ($_POST['file'] ?? '');
            if ($dir && bm_backup_valid_name($name) && is_file($dir . $name) && @unlink($dir . $name)) {
                addHistoriqueAction($pdo, $uid, 'suppression', 'sauvegarde', null, $name, 'Sauvegarde supprimée');
                pm_flash('info', 'Sauvegarde supprimée.', 'sauvegardes');
            }
            pm_flash('err', 'Suppression impossible.', 'sauvegardes');
            break;

        case 'backup_import':
            $up = $_FILES['sqlfile'] ?? null;
            if (!$dir || !$up || $up['error'] !== UPLOAD_ERR_OK) {
                pm_flash('err', "Le fichier n'a pas pu être envoyé (trop volumineux pour le serveur ?).", 'sauvegardes');
            }
            if (strtolower(pathinfo($up['name'], PATHINFO_EXTENSION)) !== 'sql' || $up['size'] > 100 * 1048576) {
                pm_flash('err', 'Choisissez un fichier .sql de moins de 100 Mo.', 'sauvegardes');
            }
            $head = (string) @file_get_contents($up['tmp_name'], false, null, 0, 4096);
            if (stripos($head, '<?php') !== false) {
                pm_flash('err', 'Fichier refusé.', 'sauvegardes');
            }
            $name = 'bm_' . date('Ymd_His') . '_import_' . bin2hex(random_bytes(4)) . '.sql';
            if (!move_uploaded_file($up['tmp_name'], $dir . $name)) {
                pm_flash('err', "Impossible d'enregistrer le fichier.", 'sauvegardes');
            }
            addHistoriqueAction($pdo, $uid, 'creation', 'sauvegarde', null, $name, 'Fichier de sauvegarde importé');
            pm_flash('ok', 'Fichier importé. Cliquez sur « Restaurer » pour l\'utiliser.', 'sauvegardes');
            break;

        /* ----- 40 : restauration ----- */
        case 'backup_restore':
            $name = (string) ($_POST['file'] ?? '');
            if (!$dir || !bm_backup_valid_name($name) || !is_file($dir . $name)) {
                pm_flash('err', 'Sauvegarde introuvable.', 'sauvegardes');
            }
            $st = $pdo->prepare("SELECT mot_de_passe FROM utilisateur WHERE id_utilisateur = ?");
            $st->execute([$uid]);
            $me = $st->fetch();
            if (!$me || !password_verify((string) ($_POST['password'] ?? ''), $me['mot_de_passe'])) {
                pm_flash('err', 'Mot de passe incorrect : restauration annulée.', 'sauvegardes');
            }
            $safe = bm_backup_create($pdo, 'avant-restauration');   // filet de sécurité
            if (!$safe['ok']) {
                pm_flash('err', 'Restauration annulée : la sauvegarde de sécurité a échoué (' . $safe['error'] . ')', 'sauvegardes');
            }
            $count = 0;
            $err = '';
            $ok = bm_sql_restore($pdo, $dir . $name, $count, $err);
            bm_params_all($pdo, true);
            if ($ok) {
                bm_trace_safe('DB-RESTORE');
                @addHistoriqueAction($pdo, $uid, 'restauration', 'sauvegarde', null, $name, 'Base restaurée depuis ' . $name);
                pm_flash('ok', 'Base restaurée (' . $count . ' instructions). Une sauvegarde de sécurité a été créée avant : « Avant restauration ».', 'sauvegardes');
            }
            pm_flash('err', 'La restauration a échoué. ' . $err . ' (la sauvegarde « Avant restauration » permet de revenir en arrière).', 'sauvegardes');
            break;

        /* ----- 22 : versions ----- */
        case 'versions_sync':
            $n = bm_versions_sync($pdo, $uid, 'manuel');
            pm_flash($n ? 'ok' : 'info', $n ? ($n . ' nouvelle(s) version(s) enregistrée(s).') : 'Tous les films sont déjà à jour : aucun changement détecté.', 'versions');
            break;

        case 'version_restore':
            $res = bm_version_restore($pdo, (int) ($_POST['id_version'] ?? 0), $uid);
            if ($res[0]) {
                addHistoriqueAction($pdo, $uid, 'restauration', 'film', $res[3], $res[1], 'Film « ' . $res[1] . ' » remis à la version ' . $res[2]);
                pm_flash('ok', 'Le film « ' . $res[1] . ' » est revenu à la version ' . $res[2] . '.', 'versions');
            }
            pm_flash('err', $res[1], 'versions');
            break;
    }
    pm_flash('err', 'Action inconnue.', 'reglages');
}

/* ---------- Données à afficher ---------- */
$tab = in_array($_GET['tab'] ?? '', ['reglages', 'sauvegardes', 'versions'], true) ? $_GET['tab'] : 'reglages';
$flash = $_SESSION['pm_flash'] ?? null;
unset($_SESSION['pm_flash']);

bm_versions_sync($pdo, $uid, 'detection');     // détecte les films modifiés depuis la dernière visite

$P = bm_params_all($pdo, true);
$backups = bm_backup_list();
$nb_b = count($backups);
$last_b = $nb_b ? $backups[0]['ts'] : null;
$size_b = array_sum(array_column($backups, 'size'));

$btypes = [
    'manuel'             => ['Manuelle', 'fa-hand-pointer', '#3b82f6'],
    'auto'               => ['Automatique', 'fa-clock-rotate-left', '#22c55e'],
    'avant-restauration' => ['Avant restauration', 'fa-shield-halved', '#f59e0b'],
    'import'             => ['Importée', 'fa-file-import', '#8b5cf6'],
];

$nbVersions = (int) $pdo->query("SELECT COUNT(*) FROM film_versions")->fetchColumn();
$nbFilmsSuivis = (int) $pdo->query("SELECT COUNT(DISTINCT id_film) FROM film_versions")->fetchColumn();

$filmsV = $pdo->query("SELECT f.id_film, f.nom_film, f.titre,
        (SELECT COUNT(*) FROM film_versions x WHERE x.id_film = f.id_film) AS nb,
        (SELECT MAX(date_version) FROM film_versions x WHERE x.id_film = f.id_film) AS derniere
    FROM film f
    WHERE EXISTS (SELECT 1 FROM film_versions x WHERE x.id_film = f.id_film)
    ORDER BY derniere DESC, f.nom_film
    LIMIT 300")->fetchAll();

$versByFilm = [];
foreach ($pdo->query("SELECT fv.id_version, fv.id_film, fv.numero, fv.donnees, fv.source, fv.date_version, u.nom_utilisateur
        FROM film_versions fv LEFT JOIN utilisateur u ON u.id_utilisateur = fv.id_utilisateur
        ORDER BY fv.id_film, fv.numero DESC") as $r) {
    $versByFilm[(int) $r['id_film']][] = $r;
}

// Noms lisibles pour les clés étrangères (réalisateur, langue, catégorie)
$maps = ['id_realisateur' => [], 'id_langue' => [], 'id_categorie' => []];
try {
    foreach ($pdo->query("SELECT id_realisateur AS i, nom, prenom FROM realisateur") as $r) {
        $maps['id_realisateur'][$r['i']] = trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? ''));
    }
    foreach ($pdo->query("SELECT id_langue AS i, nom_langue AS n FROM langue") as $r) {
        $maps['id_langue'][$r['i']] = $r['n'];
    }
    foreach ($pdo->query("SELECT id_categorie AS i, nom_categorie AS n FROM categorie") as $r) {
        $maps['id_categorie'][$r['i']] = $r['n'];
    }
} catch (Throwable $e) {
}

$vlabels = [
    'nom_film' => 'Nom', 'titre' => 'Titre', 'duree' => 'Durée', 'date_sortie' => 'Sortie',
    'id_realisateur' => 'Réalisateur', 'id_langue' => 'Langue', 'id_categorie' => 'Catégorie',
    'film_numero' => 'N° du film', 'video_url' => 'Fichier vidéo', 'video_thumb' => 'Miniature',
];
$vsources = [
    'initiale' => 'Version initiale', 'detection' => 'Modification détectée', 'modification' => 'Modification',
    'manuel' => 'Modification détectée', 'restauration' => 'Restauration', 'avant-restauration' => 'Avant restauration',
];

function pm_vval($k, $v, $maps)
{
    if ($v === '' || $v === null) {
        return '—';
    }
    if ($k === 'video_url' || $k === 'video_thumb') {
        return 'nouveau fichier';
    }
    if (isset($maps[$k])) {
        return $maps[$k][$v] ?? ('#' . $v);
    }
    return $v;
}

function pm_diff(array $new, ?array $old, $vlabels, $maps)
{
    $out = [];
    if ($old === null) {
        return $out;
    }
    foreach ($vlabels as $k => $lab) {
        $a = (string) ($old[$k] ?? '');
        $b = (string) ($new[$k] ?? '');
        if ($a !== $b) {
            if ($k === 'video_url' || $k === 'video_thumb') {
                $out[] = [$lab, null, 'modifié'];
            } else {
                $out[] = [$lab, pm_vval($k, $a, $maps), pm_vval($k, $b, $maps)];
            }
        }
    }
    return $out;
}

$autoLabels = ['off' => 'Désactivée', 'daily' => 'Tous les jours', 'weekly' => 'Toutes les semaines', 'monthly' => 'Tous les mois'];
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Bertin Movie</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=3">
    <link rel="stylesheet" href="/film_web_dy/admin/hx-style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Compléments propres à la page Paramètres (mêmes variables que hx-style.css) */
        .pm-panel[hidden] { display: none !important; }
        .pm-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px 18px; }
        .pm-grid .pm-full { grid-column: 1 / -1; }
        @media (max-width: 700px) { .pm-grid { grid-template-columns: 1fr; } }
        .pm-card { margin-bottom: 14px; }
        .pm-field input[type=number], .pm-field input[type=text], .pm-field select { min-height: 50px; width: 100%; }
        .pm-switch { display: flex; align-items: center; gap: 12px; cursor: pointer; font-weight: 600; font-size: 14px; color: var(--hx-text); }
        .pm-switch input { position: absolute; opacity: 0; pointer-events: none; }
        .pm-sw-track { position: relative; flex: 0 0 auto; width: 48px; height: 28px; border-radius: 999px; background: var(--hx-line); transition: background .2s ease; }
        .pm-sw-track::after { content: ""; position: absolute; top: 3px; left: 3px; width: 22px; height: 22px; border-radius: 50%; background: #fff; box-shadow: 0 2px 6px rgba(15, 23, 42, .3); transition: transform .2s ease; }
        .pm-switch input:checked + .pm-sw-track { background: #f59e0b; }
        .pm-switch input:checked + .pm-sw-track::after { transform: translateX(20px); }
        .pm-switch input:focus-visible + .pm-sw-track { outline: 3px solid rgba(59, 130, 246, .4); }
        .pm-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 16px; }
        .pm-toolbar .hx-field { flex: 1 1 240px; min-height: 46px; }
        .pm-inline { display: inline; margin: 0; }
        .pm-note { margin: 0 0 16px; padding: 14px 18px; border-radius: 14px; background: var(--hx-bg); border: 1px dashed var(--hx-line); font-size: 13.5px; line-height: 1.55; color: var(--hx-muted); }
        .pm-note b { color: var(--hx-text); }
        .pm-list { display: grid; gap: 12px; }
        .hx-item.pm-film { grid-template-columns: auto minmax(0, 1fr) auto; }
        .pm-vpanel { grid-column: 1 / -1; display: grid; gap: 10px; padding-top: 14px; margin-top: 4px; border-top: 1px dashed var(--hx-line); }
        .pm-vpanel[hidden] { display: none; }
        .pm-ver { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 14px; align-items: start; padding: 12px 14px; border-radius: 14px; background: var(--hx-bg-2); border: 1px solid var(--hx-line); }
        .pm-ver.current { border-color: #3b82f6; }
        .pm-ver-n { min-width: 44px; height: 44px; display: grid; place-items: center; border-radius: 12px; font-weight: 800; font-size: 14px; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
        .pm-ver.current .pm-ver-n { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .pm-ver-t { font-size: 13.5px; color: var(--hx-text); line-height: 1.5; }
        .pm-ver-t span { color: var(--hx-muted); }
        .pm-diff { margin: 6px 0 0; padding: 0; list-style: none; display: grid; gap: 3px; font-size: 13px; color: var(--hx-muted); }
        .pm-diff b { color: var(--hx-text); }
        .pm-diff del { color: #dc2626; text-decoration: line-through; }
        .pm-diff ins { color: #16a34a; text-decoration: none; font-weight: 600; }
        @media (max-width: 640px) {
            .hx-item.pm-film { grid-template-columns: auto minmax(0, 1fr); }
            .hx-item.pm-film .hx-act { grid-column: 1 / -1; }
            .pm-ver { grid-template-columns: auto minmax(0, 1fr); }
            .pm-ver form, .pm-ver > .hx-chip { grid-column: 1 / -1; }
        }
        .pm-modal { position: fixed; inset: 0; z-index: 9999; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 23, 42, .6); }
        .pm-modal.open { display: flex; }
        .pm-modal-box { width: 100%; max-width: 440px; padding: 26px; border-radius: 20px; background: var(--hx-bg); border: 1px solid var(--hx-line); box-shadow: var(--hx-sh-lg); color: var(--hx-text); }
        .pm-modal-box h3 { margin: 0 0 6px; font-size: 19px; font-weight: 800; }
        .pm-modal-box p { margin: 0 0 14px; font-size: 14px; line-height: 1.55; color: var(--hx-muted); }
        .pm-modal-file { display: block; margin-bottom: 14px; padding: 8px 12px; border-radius: 10px; background: var(--hx-bg-2); border: 1px solid var(--hx-line); font-size: 12px; word-break: break-all; color: var(--hx-muted); }
        .pm-modal-actions { display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap; }
        .pm-modal-actions .hx-btn { flex: 1 1 140px; }
    </style>
</head>

<body>

    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="content hx-wrap">

        <!-- ===== EN-TÊTE ===== -->
        <div class="hx-head">
            <div class="hx-title">
                <div class="hx-title-ico"><i class="fas fa-sliders"></i></div>
                <div>
                    <h1>Paramètres du système</h1>
                    <p>Réglages, sauvegardes de la base et versions des films</p>
                </div>
            </div>
        </div>

        <!-- ===== MESSAGE ===== -->
        <?php if ($flash): ?>
            <div class="hx-alert hx-alert--<?= $flash['type'] === 'ok' ? 'ok' : ($flash['type'] === 'err' ? 'err' : 'info') ?>">
                <i class="fas <?= $flash['type'] === 'ok' ? 'fa-check-circle' : ($flash['type'] === 'err' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>"></i>
                <?= pm_h($flash['msg']) ?>
            </div>
        <?php endif; ?>

        <!-- ===== SYNTHÈSE ===== -->
        <div class="hx-stats hx-stats--4">
            <div class="hx-stat hx-stat--blue">
                <div class="hx-stat-ico"><i class="fas fa-database"></i></div>
                <div><strong><?= $nb_b ?></strong><span>Sauvegardes</span></div>
            </div>
            <div class="hx-stat hx-stat--green">
                <div class="hx-stat-ico"><i class="fas fa-clock-rotate-left"></i></div>
                <div><strong class="hx-sm"><?= $last_b ? date('d/m/Y', $last_b) : '—' ?></strong><span>Dernière sauvegarde</span></div>
            </div>
            <div class="hx-stat hx-stat--violet">
                <div class="hx-stat-ico"><i class="fas fa-code-branch"></i></div>
                <div><strong><?= $nbVersions ?></strong><span>Versions (<?= $nbFilmsSuivis ?> films)</span></div>
            </div>
            <div class="hx-stat <?= $P['maintenance'] === '1' ? 'hx-stat--orange' : 'hx-stat--red' ?>">
                <div class="hx-stat-ico"><i class="fas <?= $P['maintenance'] === '1' ? 'fa-screwdriver-wrench' : 'fa-shield-halved' ?>"></i></div>
                <div><strong class="hx-sm"><?= $P['maintenance'] === '1' ? 'Maintenance' : 'En ligne' ?></strong><span>Site</span></div>
            </div>
        </div>

        <!-- ===== ONGLETS ===== -->
        <div class="hx-tabs" id="pmTabs">
            <button type="button" class="hx-tab <?= $tab === 'reglages' ? 'active' : '' ?>" data-tab="reglages">
                <i class="fas fa-sliders"></i> Réglages
            </button>
            <button type="button" class="hx-tab <?= $tab === 'sauvegardes' ? 'active' : '' ?>" data-tab="sauvegardes" style="--c:#22c55e;">
                <i class="fas fa-database"></i> Sauvegardes <b><?= $nb_b ?></b>
            </button>
            <button type="button" class="hx-tab <?= $tab === 'versions' ? 'active' : '' ?>" data-tab="versions" style="--c:#8b5cf6;">
                <i class="fas fa-code-branch"></i> Versions <b><?= count($filmsV) ?></b>
            </button>
            <a class="hx-tab" href="permissions.php" style="--c:#ef4444;text-decoration:none;" onclick="event.stopPropagation()">
                <i class="fas fa-user-lock"></i> Droits d'accès
            </a>
        </div>

        <!-- =====================================================
             ONGLET 1 : RÉGLAGES (point 44)
        ====================================================== -->
        <section class="pm-panel" id="tab-reglages" <?= $tab === 'reglages' ? '' : 'hidden' ?>>
            <form method="POST" class="hx-form" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= pm_h($csrf) ?>">
                <input type="hidden" name="action" value="save_settings">

                <div class="hx-sec"><span>Général</span></div>
                <div class="hx-card pm-card">
                    <div class="pm-grid">
                        <div class="pm-full">
                            <label class="pm-switch">
                                <input type="checkbox" name="maintenance" value="1" <?= $P['maintenance'] === '1' ? 'checked' : '' ?>>
                                <span class="pm-sw-track"></span>
                                <span>Mode maintenance</span>
                            </label>
                            <p class="hx-hint">Les utilisateurs sont déconnectés et ne peuvent plus se connecter. Les administrateurs gardent l'accès.</p>
                        </div>
                        <div class="pm-full">
                            <span class="hx-lab">Message affiché pendant la maintenance</span>
                            <label class="hx-field pm-field"><i class="fas fa-message"></i>
                                <input type="text" name="maintenance_msg" maxlength="300" value="<?= pm_h($P['maintenance_msg']) ?>">
                            </label>
                        </div>
                        <div>
                            <span class="hx-lab">Thème par défaut</span>
                            <label class="hx-field pm-field"><i class="fas fa-palette"></i>
                                <select name="theme_defaut">
                                    <option value="light" <?= $P['theme_defaut'] === 'light' ? 'selected' : '' ?>>Clair</option>
                                    <option value="dark" <?= $P['theme_defaut'] === 'dark' ? 'selected' : '' ?>>Sombre</option>
                                </select>
                            </label>
                            <p class="hx-hint">Pour les visiteurs qui n'ont pas encore choisi leur thème.</p>
                        </div>
                    </div>
                </div>

                <div class="hx-sec"><span>Sécurité des comptes</span></div>
                <div class="hx-card pm-card">
                    <div class="pm-grid">
                        <div>
                            <span class="hx-lab">Tentatives avant verrouillage</span>
                            <label class="hx-field pm-field"><i class="fas fa-lock"></i>
                                <input type="number" name="lock_max" min="1" max="20" value="<?= pm_h($P['lock_max']) ?>">
                            </label>
                            <p class="hx-hint">Mots de passe faux autorisés (1 à 20).</p>
                        </div>
                        <div>
                            <span class="hx-lab">Durée du verrouillage (minutes)</span>
                            <label class="hx-field pm-field"><i class="fas fa-hourglass-half"></i>
                                <input type="number" name="lock_minutes" min="1" max="1440" value="<?= pm_h($P['lock_minutes']) ?>">
                            </label>
                        </div>
                        <div>
                            <span class="hx-lab">Déconnexion après inactivité (minutes)</span>
                            <label class="hx-field pm-field"><i class="fas fa-user-clock"></i>
                                <input type="number" name="idle_minutes" min="1" max="120" value="<?= pm_h($P['idle_minutes']) ?>">
                            </label>
                            <p class="hx-hint">Entre 1 et 120. Mets 1 pour une démonstration.</p>
                        </div>
                        <div>
                            <span class="hx-lab">Avertissement avant la coupure (secondes)</span>
                            <label class="hx-field pm-field"><i class="fas fa-bell"></i>
                                <input type="number" name="idle_warn" min="10" max="300" value="<?= pm_h($P['idle_warn']) ?>">
                            </label>
                        </div>
                    </div>
                </div>

                <div class="hx-sec"><span>Sauvegarde automatique</span></div>
                <div class="hx-card pm-card">
                    <div class="pm-grid">
                        <div>
                            <span class="hx-lab">Fréquence</span>
                            <label class="hx-field pm-field"><i class="fas fa-calendar-check"></i>
                                <select name="backup_auto">
                                    <?php foreach ($autoLabels as $k => $lab): ?>
                                        <option value="<?= $k ?>" <?= $P['backup_auto'] === $k ? 'selected' : '' ?>><?= $lab ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div>
                            <span class="hx-lab">Sauvegardes automatiques à conserver</span>
                            <label class="hx-field pm-field"><i class="fas fa-box-archive"></i>
                                <input type="number" name="backup_keep" min="1" max="60" value="<?= pm_h($P['backup_keep']) ?>">
                            </label>
                        </div>
                        <p class="hx-hint pm-full">Sans tâche planifiée : la sauvegarde se déclenche toute seule quand un administrateur navigue sur le site et que la dernière date de plus que la fréquence choisie. Les plus anciennes sont supprimées automatiquement.</p>
                    </div>
                </div>

                <div class="hx-sec"><span>Versions des films</span></div>
                <div class="hx-card pm-card">
                    <div class="pm-grid">
                        <div>
                            <span class="hx-lab">Versions gardées par film</span>
                            <label class="hx-field pm-field"><i class="fas fa-code-branch"></i>
                                <input type="number" name="version_keep" min="0" max="100" value="<?= pm_h($P['version_keep']) ?>">
                            </label>
                            <p class="hx-hint">0 = illimité. Les plus anciennes sont supprimées au-delà.</p>
                        </div>
                    </div>
                </div>

                <div class="hx-form-actions">
                    <button type="submit" class="hx-btn hx-btn--primary"><i class="fas fa-save"></i> Enregistrer les réglages</button>
                </div>
            </form>

            <form method="POST" class="pm-inline" data-confirm="Remettre tous les réglages à leur valeur par défaut ?" data-type="info" data-title="Valeurs par défaut" data-ok="Rétablir">
                <input type="hidden" name="csrf" value="<?= pm_h($csrf) ?>">
                <input type="hidden" name="action" value="reset_settings">
                <p style="margin-top:14px;"><button type="submit" class="hx-btn hx-btn--ghost"><i class="fas fa-rotate-left"></i> Rétablir les valeurs par défaut</button></p>
            </form>
        </section>

        <!-- =====================================================
             ONGLET 2 : SAUVEGARDES (points 39 + 40)
        ====================================================== -->
        <section class="pm-panel" id="tab-sauvegardes" <?= $tab === 'sauvegardes' ? '' : 'hidden' ?>>

            <div class="pm-toolbar">
                <form method="POST" class="pm-inline">
                    <input type="hidden" name="csrf" value="<?= pm_h($csrf) ?>">
                    <input type="hidden" name="action" value="backup_now">
                    <button type="submit" class="hx-btn hx-btn--primary"><i class="fas fa-database"></i> Créer une sauvegarde maintenant</button>
                </form>
                <form method="POST" enctype="multipart/form-data" class="pm-inline">
                    <input type="hidden" name="csrf" value="<?= pm_h($csrf) ?>">
                    <input type="hidden" name="action" value="backup_import">
                    <label class="hx-btn hx-btn--ghost">
                        <i class="fas fa-upload"></i> Importer un fichier .sql
                        <input type="file" name="sqlfile" accept=".sql" hidden onchange="this.form.submit()">
                    </label>
                </form>
            </div>

            <p class="pm-note">
                <b>Sauvegarde automatique :</b> <?= pm_h($autoLabels[$P['backup_auto']] ?? 'Désactivée') ?>
                <?php if ($P['backup_auto'] !== 'off'): ?>(<?= (int) $P['backup_keep'] ?> conservées)<?php endif; ?>
                · Les fichiers sont dans le dossier protégé <b>/sauvegardes</b> (accès direct interdit) · Espace utilisé : <b><?= pm_size($size_b) ?></b>.
                Avant chaque restauration, une sauvegarde de sécurité est créée automatiquement.
            </p>

            <?php if ($nb_b > 0): ?>
                <div class="pm-list">
                    <?php foreach ($backups as $i => $b):
                        $t = $btypes[$b['type']]; ?>
                        <article class="hx-item" style="animation-delay: <?= min($i, 12) * 0.03 ?>s;">
                            <div class="hx-ico" style="background:<?= $t[2] ?>;--c:<?= $t[2] ?>;"><i class="fas <?= $t[1] ?>"></i></div>
                            <div class="hx-body">
                                <div class="hx-line">
                                    <span class="hx-label"><?= pm_h($t[0]) ?></span>
                                    <span class="hx-on"><?= date('d/m/Y à H:i', $b['ts']) ?></span>
                                </div>
                                <div class="hx-msg"><?= pm_h($b['name']) ?></div>
                                <div class="hx-meta">
                                    <span class="hx-chip"><i class="fas fa-weight-hanging"></i> <?= pm_size($b['size']) ?></span>
                                </div>
                            </div>
                            <div class="hx-act">
                                <a class="hx-ibtn hx-ok" href="parametres.php?dl=<?= urlencode($b['name']) ?>"><i class="fas fa-download"></i> Télécharger</a>
                                <button type="button" class="hx-ibtn hx-ok" onclick="pmRestore('<?= pm_h($b['name']) ?>')"><i class="fas fa-undo"></i> Restaurer</button>
                                <form method="POST" class="pm-inline" data-confirm="Supprimer cette sauvegarde ? Cette action est irréversible." data-type="danger" data-title="Supprimer la sauvegarde" data-ok="Supprimer">
                                    <input type="hidden" name="csrf" value="<?= pm_h($csrf) ?>">
                                    <input type="hidden" name="action" value="backup_delete">
                                    <input type="hidden" name="file" value="<?= pm_h($b['name']) ?>">
                                    <button type="submit" class="hx-ibtn hx-del"><i class="fas fa-trash"></i> Supprimer</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="hx-empty">
                    <div class="hx-empty-ico"><i class="fas fa-database"></i></div>
                    <h3>Aucune sauvegarde</h3>
                    <p>Créez votre première sauvegarde, ou activez la sauvegarde automatique dans « Réglages ».</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- =====================================================
             ONGLET 3 : VERSIONS (point 22)
        ====================================================== -->
        <section class="pm-panel" id="tab-versions" <?= $tab === 'versions' ? '' : 'hidden' ?>>

            <div class="pm-toolbar">
                <label class="hx-field"><i class="fas fa-search"></i>
                    <input type="search" id="pmSearch" placeholder="Rechercher un film…">
                </label>
                <form method="POST" class="pm-inline">
                    <input type="hidden" name="csrf" value="<?= pm_h($csrf) ?>">
                    <input type="hidden" name="action" value="versions_sync">
                    <button type="submit" class="hx-btn hx-btn--violet"><i class="fas fa-camera"></i> Enregistrer l'état actuel</button>
                </form>
            </div>

            <p class="pm-note">
                Chaque fois qu'un film est <b>modifié</b> (informations ou fichier vidéo), son ancien état est conservé comme une <b>version</b>.
                Cliquez sur « Historique » pour voir ce qui a changé et <b>revenir à une version précédente</b> (nom, titre, durée, réalisateur…).
                Le fichier vidéo lui-même n'est pas restauré.
            </p>

            <?php if ($filmsV): ?>
                <div class="pm-list" id="pmFilms">
                    <?php foreach ($filmsV as $i => $f):
                        $vs = $versByFilm[(int) $f['id_film']] ?? []; ?>
                        <article class="hx-item pm-film" data-name="<?= pm_h(mb_strtolower($f['nom_film'] . ' ' . $f['titre'])) ?>"
                            style="animation-delay: <?= min($i, 12) * 0.03 ?>s;">
                            <div class="hx-ico" style="background:#8b5cf6;--c:#8b5cf6;"><i class="fas fa-film"></i></div>
                            <div class="hx-body">
                                <div class="hx-line">
                                    <span class="hx-label"><?= pm_h($f['nom_film']) ?></span>
                                    <span class="hx-on">v<?= $vs ? (int) $vs[0]['numero'] : 1 ?></span>
                                </div>
                                <?php if (!empty($f['titre'])): ?><div class="hx-msg"><?= pm_h($f['titre']) ?></div><?php endif; ?>
                                <div class="hx-meta">
                                    <span class="hx-chip"><i class="fas fa-code-branch"></i> <?= (int) $f['nb'] ?> version<?= $f['nb'] > 1 ? 's' : '' ?></span>
                                    <span class="hx-chip"><i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($f['derniere'])) ?></span>
                                </div>
                            </div>
                            <div class="hx-act">
                                <button type="button" class="hx-ibtn hx-ok pm-toggle"><i class="fas fa-clock-rotate-left"></i> Historique</button>
                            </div>

                            <div class="pm-vpanel" hidden>
                                <?php foreach ($vs as $k => $v):
                                    $snap = json_decode($v['donnees'], true) ?: [];
                                    $prev = isset($vs[$k + 1]) ? (json_decode($vs[$k + 1]['donnees'], true) ?: []) : null;
                                    $diff = pm_diff($snap, $prev, $vlabels, $maps);
                                    $isCur = ($k === 0); ?>
                                    <div class="pm-ver <?= $isCur ? 'current' : '' ?>">
                                        <div class="pm-ver-n">v<?= (int) $v['numero'] ?></div>
                                        <div>
                                            <div class="pm-ver-t">
                                                <b><?= pm_h($vsources[$v['source']] ?? ucfirst($v['source'])) ?></b>
                                                <span>· <?= date('d/m/Y H:i', strtotime($v['date_version'])) ?><?= $v['nom_utilisateur'] ? ' · par ' . pm_h($v['nom_utilisateur']) : '' ?></span>
                                            </div>
                                            <?php if ($diff): ?>
                                                <ul class="pm-diff">
                                                    <?php foreach ($diff as $d): ?>
                                                        <li><b><?= pm_h($d[0]) ?></b> :
                                                            <?php if ($d[1] === null): ?><ins><?= pm_h($d[2]) ?></ins>
                                                            <?php else: ?><del><?= pm_h($d[1]) ?></del> → <ins><?= pm_h($d[2]) ?></ins><?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php elseif ($prev === null): ?>
                                                <ul class="pm-diff"><li><?= pm_h(($snap['nom_film'] ?? '') . (!empty($snap['duree']) ? ' · ' . $snap['duree'] : '')) ?></li></ul>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($isCur): ?>
                                            <span class="hx-chip hx-chip--ok"><i class="fas fa-check"></i> Actuelle</span>
                                        <?php else: ?>
                                            <form method="POST" class="pm-inline" data-confirm="Remettre le film « <?= pm_h($f['nom_film']) ?> » à la version <?= (int) $v['numero'] ?> ? L'état actuel sera gardé comme version." data-type="info" data-title="Restaurer la version" data-ok="Restaurer">
                                                <input type="hidden" name="csrf" value="<?= pm_h($csrf) ?>">
                                                <input type="hidden" name="action" value="version_restore">
                                                <input type="hidden" name="id_version" value="<?= (int) $v['id_version'] ?>">
                                                <button type="submit" class="hx-ibtn hx-ok"><i class="fas fa-undo"></i> Restaurer</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="hx-empty" id="pmNoFilm" hidden>
                    <div class="hx-empty-ico"><i class="fas fa-search"></i></div>
                    <h3>Aucun film trouvé</h3>
                    <p>Essayez un autre mot.</p>
                </div>
            <?php else: ?>
                <div class="hx-empty">
                    <div class="hx-empty-ico"><i class="fas fa-code-branch"></i></div>
                    <h3>Aucune version pour le moment</h3>
                    <p>Ajoutez des films, puis cliquez sur « Enregistrer l'état actuel ».</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- ===== FENÊTRE : confirmation de restauration (mot de passe) ===== -->
        <div class="pm-modal" id="pmModal" role="dialog" aria-modal="true">
            <form method="POST" class="pm-modal-box" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= pm_h($csrf) ?>">
                <input type="hidden" name="action" value="backup_restore">
                <input type="hidden" name="file" id="pmRestoreFile" value="">
                <h3><i class="fas fa-triangle-exclamation" style="color:#f59e0b"></i> Restaurer la base</h3>
                <p>Toutes les données actuelles seront <b>remplacées</b> par celles de cette sauvegarde. Une sauvegarde de sécurité est créée juste avant. Les utilisateurs devront peut-être se reconnecter.</p>
                <span class="pm-modal-file" id="pmRestoreName"></span>
                <span class="hx-lab">Votre mot de passe administrateur</span>
                <label class="hx-field pm-field"><i class="fas fa-lock"></i>
                    <input type="password" name="password" id="pmRestorePwd" required autocomplete="current-password" placeholder="Pour confirmer">
                </label>
                <div class="pm-modal-actions">
                    <button type="submit" class="hx-btn hx-btn--danger"><i class="fas fa-undo"></i> Restaurer</button>
                    <button type="button" class="hx-btn hx-btn--ghost" onclick="pmClose()">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            /* ---------- Onglets ---------- */
            var tabs = document.getElementById('pmTabs');
            function showTab(name) {
                tabs.querySelectorAll('.hx-tab').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-tab') === name); });
                document.querySelectorAll('.pm-panel').forEach(function (p) { p.hidden = (p.id !== 'tab-' + name); });
                if (window.history && history.replaceState) { history.replaceState(null, '', 'parametres.php?tab=' + name); }
            }
            tabs.addEventListener('click', function (e) {
                var b = e.target.closest('.hx-tab');
                if (b) { showTab(b.getAttribute('data-tab')); }
            });

            /* ---------- Historique d'un film (ouvrir / fermer) ---------- */
            document.addEventListener('click', function (e) {
                var t = e.target.closest('.pm-toggle');
                if (!t) { return; }
                var panel = t.closest('.pm-film').querySelector('.pm-vpanel');
                panel.hidden = !panel.hidden;
            });

            /* ---------- Recherche d'un film ---------- */
            var s = document.getElementById('pmSearch');
            if (s) {
                s.addEventListener('input', function () {
                    var q = s.value.trim().toLowerCase(), shown = 0;
                    document.querySelectorAll('#pmFilms .pm-film').forEach(function (el) {
                        var ok = el.getAttribute('data-name').indexOf(q) !== -1;
                        el.hidden = !ok;
                        if (ok) { shown++; }
                    });
                    var none = document.getElementById('pmNoFilm');
                    if (none) { none.hidden = shown !== 0; }
                });
            }

            /* ---------- Confirmations (même fenêtre que le reste du site) ---------- */
            document.addEventListener('submit', function (e) {
                var f = e.target;
                var msg = f.getAttribute && f.getAttribute('data-confirm');
                if (!msg || f.__ok) { return; }
                e.preventDefault();
                if (typeof confirmModal !== 'undefined') {
                    confirmModal.open({
                        type: f.getAttribute('data-type') || 'info',
                        title: f.getAttribute('data-title') || 'Confirmation',
                        message: msg,
                        confirmText: f.getAttribute('data-ok') || 'Confirmer',
                        cancelText: 'Annuler',
                        callback: function () { f.__ok = true; f.submit(); }
                    });
                } else if (confirm(msg)) {
                    f.__ok = true; f.submit();
                }
            }, true);

            /* ---------- Fenêtre de restauration ---------- */
            window.pmRestore = function (name) {
                document.getElementById('pmRestoreFile').value = name;
                document.getElementById('pmRestoreName').textContent = name;
                document.getElementById('pmModal').classList.add('open');
                setTimeout(function () { document.getElementById('pmRestorePwd').focus(); }, 50);
            };
            window.pmClose = function () {
                document.getElementById('pmModal').classList.remove('open');
                document.getElementById('pmRestorePwd').value = '';
            };
            document.getElementById('pmModal').addEventListener('click', function (e) { if (e.target === this) { pmClose(); } });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { pmClose(); } });

            /* ---------- Thème sombre (comme les autres pages) ---------- */
            if (localStorage.getItem('theme') === 'dark') { document.body.classList.add('dark-mode'); }
        })();
    </script>

</body>

</html>