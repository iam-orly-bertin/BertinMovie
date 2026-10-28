<?php
/**
 * includes/support_lib.php : logique commune du chat support (admin <-> utilisateur)
 * Une conversation = un utilisateur (id_user). Tous les admins partagent la même conversation.
 * Les dates sont stockées en UTC et converties à l'heure du navigateur par le JavaScript.
 */
require_once __DIR__ . '/crypto.php';   // chiffrement AES-256-GCM du texte des messages
define('SUPPORT_MAX_FILE', 8 * 1024 * 1024);   // 8 Mo (InfinityFree limite souvent à ~10 Mo)
define('SUPPORT_MAX_TEXT', 2000);
define('SUPPORT_DIR', dirname(__DIR__) . '/uploads/support/');

function support_ini_bytes($v)
{
    $v = trim((string) $v);
    $n = (float) $v;
    switch (strtolower(substr($v, -1))) {
        case 'g':
            $n *= 1024;
        case 'm':
            $n *= 1024;
        case 'k':
            $n *= 1024;
    }
    return (int) $n;
}

/** À appeler sur un POST : détecte un envoi refusé par PHP (post_max_size dépassé). */
function support_check_post_size()
{
    $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $max = support_ini_bytes(ini_get('post_max_size'));
    if ($len > 0 && empty($_POST) && empty($_FILES)) {
        $lim = $max > 0 ? round($max / 1048576, 1) . ' Mo' : 'inconnue';
        support_json(['error' => "Fichier refusé par le serveur (limite d'envoi : $lim)."], 413);
    }
}

function support_json($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Crée la table au premier appel (rien à importer à la main). */
function support_ensure_table(PDO $pdo)
{
    if (!empty($_SESSION['_support_table'])) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user INT NOT NULL,
        id_admin INT NULL,
        sender ENUM('user','admin') NOT NULL,
        contenu TEXT NULL,
        fichier_path VARCHAR(64) NULL,
        fichier_nom VARCHAR(255) NULL,
        fichier_mime VARCHAR(100) NULL,
        fichier_taille INT UNSIGNED NULL,
        lu TINYINT(1) NOT NULL DEFAULT 0,
        date_envoi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_conv (id_user, id),
        KEY idx_unread (sender, lu)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $_SESSION['_support_table'] = 1;
}

/* ---------- CSRF ---------- */
function support_csrf()
{
    if (empty($_SESSION['support_csrf'])) {
        $_SESSION['support_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['support_csrf'];
}

function support_csrf_ok()
{
    $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($t) && !empty($_SESSION['support_csrf']) && hash_equals($_SESSION['support_csrf'], $t);
}

/* ---------- Lecture ---------- */
function support_format(array $m)
{
    $o = [
        'id' => (int) $m['id'],
        'from' => $m['sender'],
        'text' => (string) bm_decrypt($m['contenu'] ?? ''),   // déchiffré à la lecture
        't' => $m['date_envoi'],
    ];
    if (!empty($m['fichier_path'])) {
        $o['file'] = [
            'name' => $m['fichier_nom'],
            'size' => (int) $m['fichier_taille'],
            'image' => in_array($m['fichier_mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true),
        ];
    }
    return $o;
}

/** Nouveaux messages d'une conversation. after = 0 -> les 100 derniers. */
function support_fetch(PDO $pdo, $idUser, $after)
{
    if ($after <= 0) {
        $st = $pdo->prepare("SELECT * FROM (SELECT * FROM support_messages WHERE id_user = ? ORDER BY id DESC LIMIT 100) t ORDER BY id ASC");
        $st->execute([$idUser]);
    } else {
        $st = $pdo->prepare("SELECT * FROM support_messages WHERE id_user = ? AND id > ? ORDER BY id ASC LIMIT 100");
        $st->execute([$idUser, $after]);
    }
    return array_map('support_format', $st->fetchAll());
}

/** Marque comme lus les messages envoyés par $sender dans cette conversation. */
function support_mark_read(PDO $pdo, $idUser, $sender)
{
    $pdo->prepare("UPDATE support_messages SET lu = 1 WHERE id_user = ? AND sender = ? AND lu = 0")->execute([$idUser, $sender]);
}

/** Plus grand id de MES messages que l'autre côté a lus (pour afficher les coches « lu »). */
function support_peer_read(PDO $pdo, $idUser, $mySender)
{
    $st = $pdo->prepare("SELECT COALESCE(MAX(id), 0) FROM support_messages WHERE id_user = ? AND sender = ? AND lu = 1");
    $st->execute([$idUser, $mySender]);
    return (int) $st->fetchColumn();
}

function support_unread_for_user(PDO $pdo, $idUser)
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM support_messages WHERE id_user = ? AND sender = 'admin' AND lu = 0");
    $st->execute([$idUser]);
    return (int) $st->fetchColumn();
}

function support_unread_for_admin(PDO $pdo)
{
    return (int) $pdo->query("SELECT COUNT(*) FROM support_messages WHERE sender = 'user' AND lu = 0")->fetchColumn();
}

/* ---------- Envoi ---------- */
function support_rate_ok(PDO $pdo, $idUser, $sender, $max)
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM support_messages WHERE id_user = ? AND sender = ? AND date_envoi > ?");
    $st->execute([$idUser, $sender, gmdate('Y-m-d H:i:s', time() - 60)]);
    return (int) $st->fetchColumn() < $max;
}

/** Valide et enregistre le fichier envoyé (champ « fichier »). Renvoie null s'il n'y en a pas. */
function support_handle_upload()
{
    if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES['fichier'];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > SUPPORT_MAX_FILE) {
        throw new RuntimeException('Fichier trop volumineux (8 Mo maximum, limite serveur : ' . ini_get('upload_max_filesize') . ').');
    }
    if ($f['error'] === UPLOAD_ERR_NO_TMP_DIR || $f['error'] === UPLOAD_ERR_CANT_WRITE) {
        throw new RuntimeException('Le serveur ne peut pas stocker le fichier (dossier temporaire PHP).');
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        throw new RuntimeException("Échec de l'envoi du fichier.");
    }

    $strict = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
    ];
    // Documents, archives, audio, vidéo : extension autorisée suffit (le type détecté varie selon les serveurs)
    $lenient = ['txt', 'zip', 'mp3', 'mp4', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!isset($strict[$ext]) && !in_array($ext, $lenient, true)) {
        throw new RuntimeException('Type de fichier non autorisé (.' . $ext . ').');
    }
    $mime = function_exists('finfo_open') ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
    if (isset($strict[$ext])) {
        if ($mime === '') {
            $mime = $strict[$ext][0];
        }
        if (!in_array($mime, $strict[$ext], true)) {
            throw new RuntimeException('Le contenu du fichier ne correspond pas à son extension.');
        }
    } else {
        if ($mime === '' || strpos($mime, 'image/') === 0) {
            $mime = 'application/octet-stream';
        }
        $mime = substr($mime, 0, 100);
    }
    if (strpos($mime, 'image/') === 0 && !@getimagesize($f['tmp_name'])) {
        throw new RuntimeException('Image invalide.');
    }

    if (!is_dir(SUPPORT_DIR) && !@mkdir(SUPPORT_DIR, 0755, true)) {
        throw new RuntimeException("Dossier d'envoi introuvable (uploads/support).");
    }
    if (!is_file(SUPPORT_DIR . '.htaccess')) {
        @file_put_contents(SUPPORT_DIR . '.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    }
    if (!is_writable(SUPPORT_DIR)) {
        @chmod(SUPPORT_DIR, 0775);
        clearstatcache();
    }
    if (!is_writable(SUPPORT_DIR)) {
        error_log('support: dossier non inscriptible : ' . SUPPORT_DIR);
        throw new RuntimeException("Le dossier uploads/support n'est pas accessible en écriture. Donnez-lui les droits 755 ou 775 (777 en local). Chemin : " . SUPPORT_DIR);
    }
    $stored = bin2hex(random_bytes(16));   // nom aléatoire, sans extension
    if (!move_uploaded_file($f['tmp_name'], SUPPORT_DIR . $stored)) {
        // repli : copie simple (certains hébergeurs bloquent move_uploaded_file)
        if (!@copy($f['tmp_name'], SUPPORT_DIR . $stored)) {
            $err = error_get_last();
            error_log('support: move/copy échoué vers ' . SUPPORT_DIR . ' : ' . ($err['message'] ?? '?'));
            throw new RuntimeException("Impossible d'enregistrer le fichier (" . ($err['message'] ?? 'erreur inconnue') . ').');
        }
    }
    $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '', basename($f['name']));
    $name = mb_substr($name, 0, 120);
    return ['path' => $stored, 'nom' => $name !== '' ? $name : ('fichier.' . $ext), 'mime' => $mime, 'taille' => (int) $f['size']];
}

/** Enregistre un message (texte et/ou fichier déjà validé). Renvoie le message formaté. */
function support_insert(PDO $pdo, $idUser, $sender, $idAdmin, $text, $file)
{
    $st = $pdo->prepare("INSERT INTO support_messages (id_user, id_admin, sender, contenu, fichier_path, fichier_nom, fichier_mime, fichier_taille, lu, date_envoi)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
    $now = gmdate('Y-m-d H:i:s');
    try {
        $st->execute([
            $idUser,
            $idAdmin,
            $sender,
            $text !== '' ? bm_encrypt($text) : null,   // chiffré avant d'aller en base
            $file['path'] ?? null,
            $file['nom'] ?? null,
            $file['mime'] ?? null,
            $file['taille'] ?? null,
            $now,
        ]);
    } catch (Throwable $e) {
        if ($file) {
            @unlink(SUPPORT_DIR . $file['path']);
        }
        throw $e;
    }
    return support_format([
        'id' => (int) $pdo->lastInsertId(),
        'sender' => $sender,
        'contenu' => $text,
        'date_envoi' => $now,
        'fichier_path' => $file['path'] ?? null,
        'fichier_nom' => $file['nom'] ?? null,
        'fichier_mime' => $file['mime'] ?? null,
        'fichier_taille' => $file['taille'] ?? null,
    ]);
}

require_once __DIR__ . '/notif_admin.php';

/** Traite un POST « send » complet pour la conversation $idUser. */
function support_do_send(PDO $pdo, $idUser, $sender, $idAdmin, $maxPerMinute)
{
    $text = trim((string) ($_POST['contenu'] ?? ''));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
    if (mb_strlen($text) > SUPPORT_MAX_TEXT) {
        support_json(['error' => 'Message trop long (' . SUPPORT_MAX_TEXT . ' caractères maximum).'], 422);
    }
    if (!support_rate_ok($pdo, $idUser, $sender, $maxPerMinute)) {
        support_json(['error' => 'Trop de messages, patientez un instant.'], 429);
    }
    try {
        $file = support_handle_upload();
    } catch (RuntimeException $e) {
        support_json(['error' => $e->getMessage()], 422);
    }
    if ($text === '' && !$file) {
        support_json(['error' => 'Écrivez un message ou joignez un fichier.'], 422);
    }
    $msg = support_insert($pdo, $idUser, $sender, $idAdmin, $text, $file);

    // 🔔 Un utilisateur écrit à l'admin -> notification interne pour l'admin
    if ($sender === 'user') {
        addNotifAdminMessage($pdo, $idUser);
    }
    return $msg;
}

/* ---------- Téléchargement sécurisé ---------- */
/** $onlyUser = id de l'utilisateur autorisé, ou null pour un admin (accès à tout). */
function support_download(PDO $pdo, $idMsg, $onlyUser)
{
    $st = $pdo->prepare("SELECT * FROM support_messages WHERE id = ?");
    $st->execute([(int) $idMsg]);
    $m = $st->fetch();
    if (!$m || empty($m['fichier_path']) || !preg_match('/^[a-f0-9]{32}$/', $m['fichier_path'])) {
        http_response_code(404);
        exit('Introuvable');
    }
    if ($onlyUser !== null && (int) $m['id_user'] !== (int) $onlyUser) {
        http_response_code(403);
        exit('Accès refusé');
    }
    $path = SUPPORT_DIR . $m['fichier_path'];
    if (!is_file($path)) {
        http_response_code(404);
        exit('Fichier supprimé');
    }
    $isImg = in_array($m['fichier_mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    $inline = $isImg && !isset($_GET['dl']);
    session_write_close();
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . ($isImg ? $m['fichier_mime'] : 'application/octet-stream'));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($m['fichier_nom']));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}