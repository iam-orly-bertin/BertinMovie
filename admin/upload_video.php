<?php
/* =====================================================================
   admin/upload_video.php

   VIDÉO      -> Backblaze B2  (envoi direct navigateur -> B2, lien pré-signé S3)
   MINIATURE  -> ImageKit      (comme avant)

   Aucune bibliothèque à installer (ni composer, ni SDK) : tout est dans ce fichier.
   ===================================================================== */
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../includes/auth_check.php';
include("../connexion.php");

/* =====================================================================
   🔑🔑🔑  COLLEZ VOS CLÉS ICI (remplacez le texte entre les guillemets)  🔑🔑🔑
   (Vous pourrez plus tard les déplacer dans config.php avec define(...) :
    ce fichier utilisera alors ceux de config.php.)
   ===================================================================== */

// --- BACKBLAZE B2 -----------------------------------------------------
// B2 > App Keys : "keyID"
if (!defined('B2_KEY_ID'))
    define('B2_KEY_ID', '003353d3de4329f0000000006');
// B2 > App Keys : "applicationKey" (affichée une seule fois à la création)
if (!defined('B2_APP_KEY'))
    define('B2_APP_KEY', 'K003y4QdYmNnZvQm3IdvUrpU61B6tuU');
// Nom exact du bucket (il doit être PUBLIC)
if (!defined('B2_BUCKET'))
    define('B2_BUCKET', 'reelsofakili');
// OPTIONNEL : laissez vide, l'adresse du serveur Backblaze est détectée automatiquement.
// (Seulement si la détection échoue : copiez "Endpoint" depuis la page du bucket, ex. s3.us-west-004.backblazeb2.com)
if (!defined('B2_ENDPOINT'))
    define('B2_ENDPOINT', '');
if (!defined('B2_AUTH_URL'))
    define('B2_AUTH_URL', 'https://api.backblazeb2.com/b2api/v3/b2_authorize_account');
if (!defined('B2_SCHEME'))
    define('B2_SCHEME', 'https');   // technique : ne pas modifier
// OPTIONNEL : ID du bucket (page du bucket > "Bucket ID"). Sert seulement au bouton « Configurer CORS ».
// Laissé vide, il est retrouvé automatiquement.
if (!defined('B2_BUCKET_ID'))
    define('B2_BUCKET_ID', 'c32523fd636dfe24a302091f');
// OPTIONNEL : domaine personnalisé / CDN devant le bucket (ex. https://cdn.monsite.com). Laisser vide sinon.
if (!defined('B2_PUBLIC_BASE'))
    define('B2_PUBLIC_BASE', '');

// --- IMAGEKIT (miniatures uniquement) ---------------------------------
// ImageKit > Developer options > "Private key"
if (!defined('IK_PRIVATE_KEY'))
    define('IK_PRIVATE_KEY', 'private_Vt5cCWzuI1LrLeOGU59jwe8ao1Q=');

// --- LIMITES ----------------------------------------------------------
if (!defined('UP_MAX_VIDEO_MB'))
    define('UP_MAX_VIDEO_MB', 2048);  // 2 Go
if (!defined('UP_MAX_THUMB_MB'))
    define('UP_MAX_THUMB_MB', 5);

/* ===================================================================== */

const UP_VIDEO_TYPES = [
    'mp4' => 'video/mp4',
    'm4v' => 'video/mp4',
    'mov' => 'video/quicktime',
    'webm' => 'video/webm',
    'mkv' => 'video/x-matroska',
    'avi' => 'video/x-msvideo',
];

/*<helpers>*/

function b2_host()
{
    $e = trim((string) B2_ENDPOINT);
    if ($e !== '' && strpos($e, 'PASTE_') === false) {
        return rtrim(preg_replace('#^https?://#i', '', $e), '/');
    }
    return b2_discover_host();
}

/** Détecte l'adresse S3 du compte Backblaze à partir de keyID + applicationKey (mise en cache en session). */
function b2_discover_host()
{
    static $host = null;
    if ($host !== null) {
        return $host;
    }
    $GLOBALS['b2_disc'] = ['status' => 0, 'detail' => ''];
    $ck = 'b2_host_' . substr(sha1(B2_KEY_ID), 0, 10);
    if (isset($_SESSION[$ck]) && $_SESSION[$ck]['t'] > time() - 43200) {
        return $host = $_SESSION[$ck]['h'];
    }
    $r = up_http('GET', B2_AUTH_URL, ['timeout' => 15, 'userpwd' => B2_KEY_ID . ':' . B2_APP_KEY]);
    $j = json_decode($r['body'], true);
    $url = $j['apiInfo']['storageApi']['s3ApiUrl'] ?? ($j['s3ApiUrl'] ?? '');
    $h = rtrim(preg_replace('#^https?://#i', '', (string) $url), '/');
    if ($r['code'] === 200 && preg_match('/^s3\.[a-z0-9-]+\.backblazeb2\.com$/i', $h)) {
        $_SESSION[$ck] = ['h' => $h, 't' => time()];
        return $host = $h;
    }
    $GLOBALS['b2_disc'] = [
        'status' => $r['code'],
        'detail' => $j['message'] ?? ($r['err'] !== '' ? $r['err'] : 'réponse inattendue'),
    ];
    return $host = '';
}

function b2_region()
{
    return preg_match('/^s3\.([a-z0-9-]+)\.backblazeb2\.com$/i', b2_host(), $m) ? strtolower($m[1]) : '';
}

function b2_enc($key)
{
    return implode('/', array_map('rawurlencode', explode('/', $key)));
}

/** Liste des réglages manquants (vide = tout est prêt). */
function up_cfg_problems()
{
    $bad = [];
    foreach (['B2_KEY_ID', 'B2_APP_KEY', 'B2_BUCKET', 'IK_PRIVATE_KEY'] as $c) {
        $v = trim((string) constant($c));
        if ($v === '' || strpos($v, 'PASTE_') !== false) {
            $bad[] = $c;
        }
    }
    $e = trim((string) B2_ENDPOINT);
    if (
        $e !== '' && strpos($e, 'PASTE_') === false
        && !preg_match('/^s3\.[a-z0-9-]+\.backblazeb2\.com$/i', rtrim(preg_replace('#^https?://#i', '', $e), '/'))
    ) {
        $bad[] = 'B2_ENDPOINT';
    }
    return $bad;
}

/** URL pré-signée AWS Signature V4 (S3). */
function sigv4_presign($method, $host, $path, array $extra, $region, $keyId, $secret, $expires, $ts = null, $scheme = 'https')
{
    $ts = $ts ?: time();
    $amz = gmdate('Ymd\THis\Z', $ts);
    $date = gmdate('Ymd', $ts);
    $scope = "$date/$region/s3/aws4_request";

    $q = $extra + [];
    $q['X-Amz-Algorithm'] = 'AWS4-HMAC-SHA256';
    $q['X-Amz-Credential'] = $keyId . '/' . $scope;
    $q['X-Amz-Date'] = $amz;
    $q['X-Amz-Expires'] = (string) $expires;
    $q['X-Amz-SignedHeaders'] = 'host';
    ksort($q, SORT_STRING);

    $parts = [];
    foreach ($q as $k => $v) {
        $parts[] = rawurlencode($k) . '=' . rawurlencode($v);
    }
    $canonQuery = implode('&', $parts);

    $canon = "$method\n$path\n$canonQuery\nhost:$host\n\nhost\nUNSIGNED-PAYLOAD";
    $sts = "AWS4-HMAC-SHA256\n$amz\n$scope\n" . hash('sha256', $canon);

    $k = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
    $k = hash_hmac('sha256', $region, $k, true);
    $k = hash_hmac('sha256', 's3', $k, true);
    $k = hash_hmac('sha256', 'aws4_request', $k, true);

    return "$scheme://$host$path?$canonQuery&X-Amz-Signature=" . hash_hmac('sha256', $sts, $k);
}

/** URL pré-signée pour le bucket B2 (clé vide = opération sur le bucket). */
function b2_url($method, $key, $expires = 3600, array $extra = [])
{
    $path = '/' . rawurlencode(B2_BUCKET) . ($key !== '' ? '/' . b2_enc($key) : '');
    return sigv4_presign($method, b2_host(), $path, $extra, b2_region(), B2_KEY_ID, B2_APP_KEY, $expires, null, B2_SCHEME);
}

/** URL publique de lecture (celle enregistrée dans video_film.url_video). */
function b2_public_url($key)
{
    $base = trim(B2_PUBLIC_BASE) !== ''
        ? rtrim(trim(B2_PUBLIC_BASE), '/')
        : 'https://' . B2_BUCKET . '.' . b2_host();
    return $base . '/' . b2_enc($key);
}

function up_http($method, $url, array $o = [])
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Extension PHP cURL non disponible sur ce serveur');
    }
    $ch = curl_init($url);
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => $o['timeout'] ?? 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ];
    if ($method === 'HEAD') {
        $opt[CURLOPT_NOBODY] = true;
    }
    if (!empty($o['userpwd'])) {
        $opt[CURLOPT_USERPWD] = $o['userpwd'];
    }
    if (!empty($o['headers'])) {
        $opt[CURLOPT_HTTPHEADER] = $o['headers'];
    }
    if (isset($o['post'])) {
        $opt[CURLOPT_POSTFIELDS] = $o['post'];
    }
    curl_setopt_array($ch, $opt);
    $body = curl_exec($ch);
    $r = [
        'code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'body' => is_string($body) ? $body : '',
        'err' => curl_error($ch),
        'len' => (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD),
    ];
    curl_close($ch);
    return $r;
}

/** Extrait "Code: Message" d'une erreur XML S3. */
function s3_error($body)
{
    $c = preg_match('#<Code>(.*?)</Code>#s', $body, $m1) ? $m1[1] : '';
    $m = preg_match('#<Message>(.*?)</Message>#s', $body, $m2) ? $m2[1] : '';
    return trim($c . ($m !== '' ? ': ' . $m : ''), ': ');
}

function up_safe_key($key)
{
    return is_string($key) && preg_match('#^videos/[A-Za-z0-9._-]{1,200}$#', $key) === 1;
}

function up_make_key($filename, $ext)
{
    $base = preg_replace('/[^A-Za-z0-9]+/', '_', pathinfo($filename, PATHINFO_FILENAME));
    $base = trim(substr($base, 0, 60), '_');
    return 'videos/' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . ($base !== '' ? $base : 'video') . '.' . $ext;
}

function ik_upload($tmp, $name, $mime)
{
    $r = up_http('POST', 'https://upload.imagekit.io/api/v1/files/upload', [
        'timeout' => 60,
        'userpwd' => IK_PRIVATE_KEY . ':',
        'post' => [
            'file' => new CURLFile($tmp, $mime, $name),
            'fileName' => $name,
            'folder' => '/thumbnails',
            'useUniqueFileName' => 'false',
        ],
    ]);
    $j = json_decode($r['body'], true);
    if ($r['code'] === 200 && !empty($j['url']) && !empty($j['fileId'])) {
        return ['ok' => true, 'url' => $j['url'], 'fileId' => $j['fileId']];
    }
    return [
        'ok' => false,
        'status' => $r['code'],
        'detail' => $j['message'] ?? ($r['err'] !== '' ? $r['err'] : 'HTTP ' . $r['code']),
    ];
}

/** Règle CORS nécessaire pour envoyer une vidéo depuis le navigateur (API S3 de Backblaze). */
function b2_cors_rules()
{
    return [
        [
            'corsRuleName' => 'allowBrowserUploads',
            'allowedOrigins' => ['*'],
            'allowedOperations' => ['s3_put', 's3_get', 's3_head'],
            'allowedHeaders' => ['*'],
            'exposeHeaders' => ['ETag'],
            'maxAgeSeconds' => 3600,
        ]
    ];
}

function up_origin()
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** Simule la requête de pré-vérification (OPTIONS) que le navigateur envoie avant le PUT. */
function b2_cors_preflight($origin)
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Extension PHP cURL non disponible sur ce serveur');
    }
    $hdr = [];
    $ch = curl_init(b2_url('PUT', 'videos/_cors_check.tmp', 300));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'OPTIONS',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Origin: ' . $origin, 'Access-Control-Request-Method: PUT', 'Access-Control-Request-Headers: content-type'],
        CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$hdr) {
            $p = explode(':', $line, 2);
            if (count($p) === 2) {
                $hdr[strtolower(trim($p[0]))] = trim($p[1]);
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $ok = $code >= 200 && $code < 300 && !empty($hdr['access-control-allow-origin']);
    return [
        'ok' => $ok,
        'status' => $code,
        'detail' => $ok ? '' : (s3_error((string) $body) ?: ($err !== '' ? $err : ($code ? 'aucune règle CORS pour PUT' : 'sans réponse'))),
    ];
}

/** Connexion à l'API native Backblaze (nécessaire seulement pour modifier le bucket). */
function b2_native_auth()
{
    $r = up_http('GET', B2_AUTH_URL, ['timeout' => 15, 'userpwd' => B2_KEY_ID . ':' . B2_APP_KEY]);
    $j = json_decode($r['body'], true);
    $si = $j['apiInfo']['storageApi'] ?? (is_array($j) ? $j : []);
    if ($r['code'] === 200 && !empty($j['authorizationToken']) && !empty($si['apiUrl']) && !empty($j['accountId'])) {
        return ['ok' => true, 'api' => rtrim($si['apiUrl'], '/'), 'token' => $j['authorizationToken'], 'account' => $j['accountId']];
    }
    return ['ok' => false, 'status' => $r['code'], 'detail' => $j['message'] ?? ($r['err'] !== '' ? $r['err'] : 'réponse inattendue')];
}

/** Enregistre la règle CORS sur le bucket (b2_update_bucket). Demande la permission « writeBuckets ». */
function b2_cors_apply()
{
    $a = b2_native_auth();
    if (!$a['ok']) {
        return $a;
    }
    $H = ['Authorization: ' . $a['token'], 'Content-Type: application/json'];
    $bid = trim((string) B2_BUCKET_ID);
    if ($bid === '') {
        $r = up_http('POST', $a['api'] . '/b2api/v3/b2_list_buckets', [
            'timeout' => 20,
            'headers' => $H,
            'post' => json_encode(['accountId' => $a['account'], 'bucketName' => B2_BUCKET]),
        ]);
        $j = json_decode($r['body'], true);
        $bid = $j['buckets'][0]['bucketId'] ?? '';
        if ($bid === '') {
            return ['ok' => false, 'status' => $r['code'], 'detail' => $j['message'] ?? 'bucket introuvable : renseignez B2_BUCKET_ID'];
        }
    }
    $r = up_http('POST', $a['api'] . '/b2api/v3/b2_update_bucket', [
        'timeout' => 20,
        'headers' => $H,
        'post' => json_encode(['accountId' => $a['account'], 'bucketId' => $bid, 'corsRules' => b2_cors_rules()]),
    ]);
    $j = json_decode($r['body'], true);
    if ($r['code'] === 200) {
        return ['ok' => true];
    }
    return ['ok' => false, 'status' => $r['code'], 'detail' => $j['message'] ?? ($r['err'] !== '' ? $r['err'] : 'HTTP ' . $r['code'])];
}

/*</helpers>*/

function up_json($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

if (empty($_SESSION['up_csrf'])) {
    $_SESSION['up_csrf'] = bin2hex(random_bytes(16));
}

/* =====================================================================
   API INTERNE (appelée en AJAX par la page)
   ===================================================================== */
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            up_json(['ok' => false, 'code' => 'server', 'detail' => 'method'], 405);
        }
        $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['up_csrf'], (string) $tok)) {
            up_json(['ok' => false, 'code' => 'csrf'], 403);
        }

        $action = (string) $_GET['action'];
        $missing = up_cfg_problems();

        /* ---------- TEST DES CONNEXIONS ---------- */
        if ($action === 'test') {
            $out = ['ok' => true, 'missing' => $missing, 'b2' => null, 'ik' => null, 'cors' => null];
            if (!array_intersect($missing, ['B2_KEY_ID', 'B2_APP_KEY', 'B2_BUCKET', 'B2_ENDPOINT'])) {
                if (b2_host() === '') {
                    $out['b2'] = ['ok' => false, 'status' => $GLOBALS['b2_disc']['status'], 'detail' => $GLOBALS['b2_disc']['detail']];
                } else {
                    $r = up_http('GET', b2_url('GET', '', 300, ['list-type' => '2', 'max-keys' => '1']), ['timeout' => 20]);
                    $out['b2'] = ['ok' => $r['code'] === 200, 'status' => $r['code'], 'detail' => $r['code'] === 200 ? '' : (s3_error($r['body']) ?: $r['err'])];
                    if ($r['code'] === 200) {
                        $out['cors'] = b2_cors_preflight(up_origin());   // l'envoi depuis le navigateur est-il autorisé ?
                    }
                }
            }
            if (!in_array('IK_PRIVATE_KEY', $missing, true)) {
                $r = up_http('GET', 'https://api.imagekit.io/v1/files?limit=1', ['timeout' => 20, 'userpwd' => IK_PRIVATE_KEY . ':']);
                $j = json_decode($r['body'], true);
                $out['ik'] = ['ok' => $r['code'] === 200, 'status' => $r['code'], 'detail' => $r['code'] === 200 ? '' : ($j['message'] ?? $r['err'])];
            }
            up_json($out);
        }

        if ($missing) {
            up_json(['ok' => false, 'code' => 'config', 'detail' => implode(', ', $missing)]);
        }
        if ($action !== 'thumb' && b2_host() === '') {
            up_json(['ok' => false, 'code' => 'b2_auth', 'status' => $GLOBALS['b2_disc']['status'], 'detail' => $GLOBALS['b2_disc']['detail']]);
        }

        /* ---------- CORS : vérifier / configurer le bucket ---------- */
        if ($action === 'cors_check') {
            up_json(['ok' => true, 'cors' => b2_cors_preflight(up_origin())]);
        }
        if ($action === 'cors_apply') {
            $r = b2_cors_apply();
            if (!$r['ok']) {
                up_json(['ok' => false, 'code' => 'cors_apply', 'status' => $r['status'] ?? 0, 'detail' => $r['detail'] ?? '']);
            }
            up_json(['ok' => true, 'cors' => b2_cors_preflight(up_origin())]);
        }

        /* ---------- 1) PRÉPARATION : contrôles + lien d'envoi pré-signé ---------- */
        if ($action === 'prepare') {
            $id_film = (int) ($_POST['id_film'] ?? 0);
            $filename = (string) ($_POST['filename'] ?? '');
            $size = (int) ($_POST['size'] ?? 0);

            $st = $pdo->prepare("SELECT id_film FROM film WHERE id_film = ? AND is_deleted = 0");
            $st->execute([$id_film]);
            if (!$st->fetch()) {
                up_json(['ok' => false, 'code' => 'film']);
            }

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!isset(UP_VIDEO_TYPES[$ext])) {
                up_json(['ok' => false, 'code' => 'ext']);
            }
            if ($size <= 0 || $size > UP_MAX_VIDEO_MB * 1048576) {
                up_json(['ok' => false, 'code' => 'size', 'max' => UP_MAX_VIDEO_MB]);
            }

            $st = $pdo->prepare("SELECT id_video FROM video_film WHERE id_film = ? LIMIT 1");
            $st->execute([$id_film]);
            if ($st->fetch() && ($_POST['replace'] ?? '') !== '1') {
                up_json(['ok' => false, 'code' => 'exists']);
            }

            $key = up_make_key($filename, $ext);
            up_json([
                'ok' => true,
                'key' => $key,
                'content_type' => UP_VIDEO_TYPES[$ext],
                'put_url' => b2_url('PUT', $key, 21600),
            ]);
        }

        /* ---------- 2) MINIATURE -> IMAGEKIT ---------- */
        if ($action === 'thumb') {
            $f = $_FILES['thumbnail'] ?? null;
            if (!$f) {
                up_json(['ok' => false, 'code' => 'no_thumb']);
            }
            if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
                up_json(['ok' => false, 'code' => 'thumb_php_limit', 'detail' => ini_get('upload_max_filesize')]);
            }
            if ($f['error'] !== UPLOAD_ERR_OK) {
                up_json(['ok' => false, 'code' => 'thumb_upload', 'detail' => 'code ' . $f['error']]);
            }
            if ($f['size'] > UP_MAX_THUMB_MB * 1048576) {
                up_json(['ok' => false, 'code' => 'thumb_size', 'max' => UP_MAX_THUMB_MB]);
            }
            $info = @getimagesize($f['tmp_name']);
            $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
            if (!$info || !isset($types[$info[2]])) {
                up_json(['ok' => false, 'code' => 'thumb_type']);
            }
            $base = trim(substr(preg_replace('/[^A-Za-z0-9]+/', '_', pathinfo($f['name'], PATHINFO_FILENAME)), 0, 50), '_');
            $name = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . ($base !== '' ? $base : 'thumb') . '.' . $types[$info[2]];

            $r = ik_upload($f['tmp_name'], $name, $info['mime']);
            if (!$r['ok']) {
                up_json(['ok' => false, 'code' => 'thumb_ik', 'status' => $r['status'], 'detail' => $r['detail']]);
            }
            up_json(['ok' => true, 'url' => $r['url'], 'fileId' => $r['fileId']]);
        }

        /* ---------- 4) VÉRIFICATION : le fichier est-il bien sur Backblaze ? ---------- */
        if ($action === 'verify') {
            $key = $_POST['key'] ?? '';
            $size = (int) ($_POST['size'] ?? 0);
            if (!up_safe_key($key)) {
                up_json(['ok' => false, 'code' => 'server', 'detail' => 'key']);
            }
            $r = up_http('HEAD', b2_url('HEAD', $key, 300), ['timeout' => 20]);
            if ($r['code'] === 404) {
                up_json(['ok' => false, 'code' => 'b2_missing']);
            }
            if ($r['code'] !== 200) {
                up_json(['ok' => false, 'code' => 'b2_head', 'detail' => $r['code'] ? 'HTTP ' . $r['code'] : $r['err']]);
            }
            if ($r['len'] !== $size) {
                up_json(['ok' => false, 'code' => 'b2_size', 'detail' => $r['len'] . ' / ' . $size . ' octets']);
            }
            up_json(['ok' => true, 'size' => $r['len']]);
        }

        /* ---------- 5) ENREGISTREMENT EN BASE ---------- */
        if ($action === 'save') {
            $id_film = (int) ($_POST['id_film'] ?? 0);
            $key = $_POST['key'] ?? '';
            $thumb = (string) ($_POST['thumb_url'] ?? '');
            $replace = ($_POST['replace'] ?? '') === '1';

            $host = (string) parse_url($thumb, PHP_URL_HOST);
            if (!up_safe_key($key) || !preg_match('/(^|\.)imagekit\.io$/i', $host)) {
                up_json(['ok' => false, 'code' => 'server', 'detail' => 'params']);
            }
            $video_url = b2_public_url($key);

            try {
                $st = $pdo->prepare("SELECT id_video, url_video, public_id FROM video_film WHERE id_film = ? LIMIT 1");
                $st->execute([$id_film]);
                $old = $st->fetch();

                if ($old) {
                    if (!$replace) {
                        up_json(['ok' => false, 'code' => 'exists']);
                    }
                    $pdo->prepare("UPDATE video_film SET url_video = ?, thumbnail = ?, public_id = ?, date_upload = NOW() WHERE id_video = ?")
                        ->execute([$video_url, $thumb, $key, $old['id_video']]);
                    $id_video = (int) $old['id_video'];

                    // Nettoyage best-effort de l'ancienne vidéo si elle était déjà sur notre bucket
                    if (
                        up_safe_key($old['public_id'] ?? '') && $old['public_id'] !== $key
                        && strpos((string) $old['url_video'], B2_BUCKET) !== false
                    ) {
                        try {
                            up_http('DELETE', b2_url('DELETE', $old['public_id'], 300), ['timeout' => 15]);
                        } catch (Throwable $e) {
                            // sans importance : l'enregistrement a réussi
                        }
                    }
                } else {
                    $pdo->prepare("INSERT INTO video_film (id_film, url_video, thumbnail, public_id) VALUES (?, ?, ?, ?)")
                        ->execute([$id_film, $video_url, $thumb, $key]);
                    $id_video = (int) $pdo->lastInsertId();
                }
            } catch (PDOException $e) {
                up_json(['ok' => false, 'code' => 'db', 'detail' => $e->getMessage()]);
            }
            up_json(['ok' => true, 'id_video' => $id_video, 'replaced' => (bool) $old, 'url' => $video_url]);
        }

        /* ---------- NETTOYAGE après échec / annulation ---------- */
        if ($action === 'cleanup') {
            $key = $_POST['key'] ?? '';
            $fileId = $_POST['fileId'] ?? '';
            $errs = [];
            if ($key !== '') {
                if (up_safe_key($key)) {
                    $r = up_http('DELETE', b2_url('DELETE', $key, 300), ['timeout' => 20]);
                    if (!in_array($r['code'], [200, 204, 404], true)) {
                        $errs[] = 'Backblaze (HTTP ' . $r['code'] . ')';
                    }
                }
            }
            if ($fileId !== '' && preg_match('/^[A-Za-z0-9]+$/', $fileId)) {
                $r = up_http('DELETE', 'https://api.imagekit.io/v1/files/' . $fileId, ['timeout' => 20, 'userpwd' => IK_PRIVATE_KEY . ':']);
                if (!in_array($r['code'], [200, 204, 404], true)) {
                    $errs[] = 'ImageKit (HTTP ' . $r['code'] . ')';
                }
            }
            up_json(['ok' => !$errs, 'detail' => implode(', ', $errs)]);
        }

        up_json(['ok' => false, 'code' => 'server', 'detail' => 'action'], 400);

    } catch (Throwable $e) {
        up_json(['ok' => false, 'code' => 'server', 'detail' => $e->getMessage()], 500);
    }
}

/* =====================================================================
   AFFICHAGE DE LA PAGE
   ===================================================================== */
try {
    $films = $pdo->query("
        SELECT f.id_film, f.nom_film,
               (SELECT COUNT(*) FROM video_film v WHERE v.id_film = f.id_film) AS nb_video
        FROM film f
        WHERE f.is_deleted = 0
        ORDER BY f.nom_film
    ")->fetchAll();
} catch (PDOException $e) {
    $films = $pdo->query("SELECT id_film, nom_film, 0 AS nb_video FROM film WHERE is_deleted = 0 ORDER BY nom_film")->fetchAll();
}
$cfg_missing = up_cfg_problems();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Video</title>
    <link rel="stylesheet" href="/film_web_dy/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ================================================================
           BERTIN MOVIE — UPLOAD STUDIO  ·  PRO BLUE DESIGN
           Visuel uniquement : le PHP et le JavaScript ne sont pas modifiés.
           Palette : marine #0f172a · bleu profond #1e3a8a · bleu #2563eb
           ================================================================ */
        :root {
            --bm-bg: #f0f4f8;
            --bm-surface: #ffffff;
            --bm-soft: #f8fbff;
            --bm-text: #0f172a;
            --bm-muted: #64748b;
            --bm-border: #e2e8f0;
            --bm-primary: #2563eb;
            --bm-primary-2: #1d4ed8;
            --bm-primary-soft: #eff6ff;
            --bm-primary-line: #bfdbfe;
            --bm-navy: #0f172a;
            --bm-deep: #1e3a8a;
            --bm-success: #16a34a;
            --bm-success-bg: #f0fdf4;
            --bm-danger: #dc2626;
            --bm-danger-bg: #fef2f2;
            --bm-warning: #b45309;
            --bm-warning-bg: #fffbeb;
            --bm-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        }

        body.dark-mode {
            --bm-bg: #0b1120;
            --bm-surface: #111a2e;
            --bm-soft: #0f1829;
            --bm-text: #e2e8f0;
            --bm-muted: #94a3b8;
            --bm-border: #1e2b44;
            --bm-primary: #3b82f6;
            --bm-primary-2: #2563eb;
            --bm-primary-soft: #14213d;
            --bm-primary-line: #27407a;
            --bm-success: #4ade80;
            --bm-success-bg: rgba(22, 163, 74, .12);
            --bm-danger: #f87171;
            --bm-danger-bg: rgba(220, 38, 38, .12);
            --bm-warning: #fbbf24;
            --bm-warning-bg: rgba(217, 119, 6, .12);
            --bm-shadow: 0 12px 36px rgba(0, 0, 0, .35);
        }

        body {
            background: var(--bm-bg);
            color: var(--bm-text);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .up-wrap {
            width: min(1040px, calc(100% - 32px));
            margin: 84px auto 56px;
            padding: 0;
            color: var(--bm-text);
        }

        .up-wrap * {
            box-sizing: border-box;
        }

        .route {
            display: none !important;
        }

        /* ---------- Hero ---------- */
        .up-head {
            position: relative;
            overflow: hidden;
            margin-bottom: 24px;
            padding: 32px 34px;
            text-align: left;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--bm-navy) 0%, var(--bm-deep) 100%);
            box-shadow: 0 14px 34px rgba(30, 58, 138, .28);
        }

        .up-head::before {
            content: "";
            position: absolute;
            top: -60%;
            right: -10%;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(96, 165, 250, .28) 0%, transparent 65%);
            pointer-events: none;
        }

        .up-head::after {
            content: "";
            position: absolute;
            bottom: -70%;
            left: 10%;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, .08) 0%, transparent 65%);
            pointer-events: none;
        }

        .up-head h1 {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 0 0 10px;
            color: #fff;
            font-size: clamp(25px, 3.6vw, 34px);
            line-height: 1.1;
            letter-spacing: -1px;
            font-weight: 800;
        }

        .up-head h1 i {
            flex: 0 0 auto;
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            border-radius: 15px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .18);
            color: #93c5fd;
            font-size: 21px;
            backdrop-filter: blur(6px);
        }

        .up-head p {
            position: relative;
            z-index: 1;
            margin: 0;
            max-width: 560px;
            color: #bfdbfe;
            font-size: 14px;
            line-height: 1.6;
        }

        /* ---------- Cards ---------- */
        .card {
            position: relative;
            overflow: hidden;
            margin-bottom: 18px;
            padding: 28px;
            background: var(--bm-surface);
            border: 1px solid var(--bm-border);
            border-radius: 18px;
            box-shadow: var(--bm-shadow);
        }

        .card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--bm-primary), #60a5fa);
        }

        .card h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 18px;
            font-size: 14px;
            font-weight: 800;
            color: var(--bm-text);
        }

        .card h2 i {
            color: var(--bm-primary);
        }

        /* ---------- Banner ---------- */
        .banner {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 13px;
            font-size: 13px;
            line-height: 1.5;
            border: 1px solid var(--bm-border);
        }

        .banner.warn {
            background: var(--bm-warning-bg);
            color: var(--bm-warning);
            border-color: rgba(180, 83, 9, .22);
        }

        .banner i {
            margin-top: 2px;
            font-size: 16px;
        }

        /* ---------- Movie selector ---------- */
        .field {
            margin-bottom: 24px;
        }

        .field>label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: var(--bm-text);
            font-size: 13px;
            font-weight: 700;
        }

        .field>label i {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: var(--bm-primary-soft);
            color: var(--bm-primary);
            font-size: 12px;
        }

        .up-wrap select {
            appearance: none;
            -webkit-appearance: none;
            width: 100%;
            min-height: 52px;
            padding: 0 46px 0 16px;
            border-radius: 12px;
            border: 2px solid var(--bm-border);
            background:
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2364748b' stroke-width='1.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") right 16px center / 12px 8px no-repeat,
                var(--bm-soft);
            color: var(--bm-text);
            font: inherit;
            font-size: 14px;
            cursor: pointer;
            transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
        }

        .up-wrap select:hover {
            border-color: var(--bm-primary-line);
        }

        .up-wrap select:focus {
            outline: none;
            border-color: var(--bm-primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
        }

        .replace-box {
            display: none;
            margin-top: 10px;
            padding: 12px 14px;
            border-radius: 11px;
            background: var(--bm-warning-bg);
            color: var(--bm-warning);
            border: 1px solid rgba(180, 83, 9, .2);
            font-size: 12.5px;
        }

        .replace-box label {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            cursor: pointer;
            font-weight: 600;
        }

        .replace-box input {
            margin-top: 3px;
            accent-color: var(--bm-primary);
        }

        /* ---------- Drop zones ---------- */
        .dz-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(0, .9fr);
            gap: 16px;
            margin-bottom: 22px;
        }

        .dz {
            position: relative;
            min-height: 260px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 46px 22px 24px;
            border: 2px dashed var(--bm-primary-line);
            border-radius: 16px;
            background: var(--bm-soft);
            cursor: pointer;
            transition: border-color .2s ease, background .2s ease, transform .2s ease, box-shadow .2s ease;
        }

        .dz:hover,
        .dz.drag {
            border-color: var(--bm-primary);
            background: var(--bm-primary-soft);
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(37, 99, 235, .12);
        }

        .dz.has {
            border-style: solid;
            border-color: var(--bm-success);
            background: var(--bm-success-bg);
        }

        .dz input {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .dz .ico {
            width: 66px;
            height: 66px;
            display: grid;
            place-items: center;
            margin-bottom: 14px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--bm-primary-soft), var(--bm-primary-line));
            color: var(--bm-primary);
            font-size: 25px;
            transition: transform .2s ease;
        }

        .dz:hover .ico {
            transform: translateY(-3px) scale(1.04);
        }

        .dz.has .ico {
            background: rgba(22, 163, 74, .14);
            color: var(--bm-success);
        }

        .dz .ttl {
            margin-bottom: 6px;
            color: var(--bm-text);
            font-size: 15px;
            font-weight: 800;
        }

        .dz .hint {
            max-width: 300px;
            color: var(--bm-muted);
            font-size: 12.5px;
            line-height: 1.55;
        }

        .dz .meta {
            margin-top: 8px;
            color: var(--bm-muted);
            font-size: 11.5px;
            font-weight: 600;
        }

        .dz .name {
            max-width: 95%;
            margin-top: 8px;
            color: var(--bm-text);
            font-size: 12.5px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .dz .dest {
            position: absolute;
            top: 14px;
            left: 14px;
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: var(--bm-primary);
            color: #fff;
            font-size: 12px;
            box-shadow: 0 6px 14px rgba(37, 99, 235, .3);
        }

        .dz.has .dest {
            background: var(--bm-success);
            box-shadow: 0 6px 14px rgba(22, 163, 74, .3);
        }

        .dz .warnline {
            max-width: 90%;
            margin-top: 8px;
            color: var(--bm-warning);
            font-size: 11.5px;
            font-weight: 600;
        }

        .dz img {
            max-width: 160px;
            max-height: 110px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .18);
        }

        /* ---------- Buttons ---------- */
        .btn {
            width: 100%;
            min-height: 54px;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 0;
            border-radius: 13px;
            background: linear-gradient(135deg, var(--bm-primary), var(--bm-primary-2));
            color: #fff;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .2px;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(37, 99, 235, .28);
            transition: transform .2s ease, box-shadow .2s ease, opacity .2s ease;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(37, 99, 235, .38);
        }

        .btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: .45;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn.ghost,
        .btn.danger {
            width: auto;
            min-height: 40px;
            padding: 0 16px;
            border-radius: 10px;
            font-size: 12px;
            box-shadow: none;
        }

        .btn.ghost {
            background: var(--bm-primary-soft);
            color: var(--bm-primary);
            border: 1px solid var(--bm-primary-line);
        }

        .btn.ghost:hover:not(:disabled) {
            background: var(--bm-primary);
            color: #fff;
            border-color: var(--bm-primary);
            box-shadow: 0 8px 18px rgba(37, 99, 235, .25);
        }

        .btn.danger {
            background: var(--bm-danger-bg);
            color: var(--bm-danger);
            border: 1px solid rgba(220, 38, 38, .22);
        }

        .btn.danger:hover:not(:disabled) {
            background: var(--bm-danger);
            color: #fff;
            box-shadow: 0 8px 18px rgba(220, 38, 38, .25);
        }

        /* ---------- Progress (une seule étape visible à la fois) ---------- */
        .progress-card {
            text-align: center;
        }

        .progress-card h2 {
            justify-content: center;
            margin-bottom: 24px;
            font-size: 16px;
        }

        .steps {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .progress-card .steps {
            max-width: 680px;
            margin: 0 auto;
        }

        .step {
            position: relative;
            display: flex;
            gap: 14px;
            padding: 12px 0;
            animation: stepIn .25s ease both;
        }

        @keyframes stepIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .st-ico {
            flex: 0 0 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--bm-primary-soft);
            border: 1px solid var(--bm-border);
            color: var(--bm-muted);
            font-size: 12px;
            font-weight: 700;
        }

        .st-body {
            flex: 1;
            min-width: 0;
            padding-top: 1px;
        }

        .st-title {
            color: var(--bm-text);
            font-size: 13px;
            font-weight: 800;
        }

        .st-text {
            margin-top: 4px;
            color: var(--bm-muted);
            font-size: 12px;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .step.ok .st-ico {
            background: var(--bm-success);
            border-color: var(--bm-success);
            color: #fff;
        }

        .step.err .st-ico {
            background: var(--bm-danger);
            border-color: var(--bm-danger);
            color: #fff;
        }

        .step.err .st-title {
            color: var(--bm-danger);
        }

        .step.err .st-text small {
            display: block;
            margin-top: 6px;
            opacity: .85;
        }

        .bar {
            height: 8px;
            margin-top: 14px;
            overflow: hidden;
            border-radius: 999px;
            background: var(--bm-primary-soft);
            display: none;
        }

        .step.run .bar,
        .step.ok .bar {
            display: block;
        }

        .bar span {
            display: block;
            width: 0;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--bm-primary), #60a5fa);
            box-shadow: 0 0 14px rgba(37, 99, 235, .35);
            transition: width .25s ease;
        }

        .bar span::after {
            content: "";
            display: block;
            height: 100%;
            width: 35%;
            margin-left: -35%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .7), transparent);
            animation: uploadShine 1.2s linear infinite;
        }

        .step.ok .bar span::after {
            display: none;
        }

        @keyframes uploadShine {
            to {
                margin-left: 100%;
            }
        }

        .progress-card .step.ok,
        .progress-card .step:not(.run):not(.err) {
            display: none !important;
        }

        .progress-card .step.run {
            display: flex;
            align-items: center;
            text-align: left;
            padding: 22px;
            border: 1px solid var(--bm-primary-line);
            border-radius: 16px;
            background: var(--bm-primary-soft);
        }

        .progress-card .step.run .st-ico {
            flex-basis: 46px;
            width: 46px;
            height: 46px;
            border: 0;
            background: var(--bm-surface);
            color: var(--bm-primary);
            font-size: 16px;
            box-shadow: 0 6px 16px rgba(37, 99, 235, .18);
        }

        .progress-card .step.run .st-title {
            color: var(--bm-primary);
            font-size: 14px;
        }

        .progress-card .step.err {
            display: flex;
            text-align: left;
            padding: 18px;
            border-radius: 14px;
            background: var(--bm-danger-bg);
            border: 1px solid rgba(220, 38, 38, .22);
        }

        .progress-card .step.err .st-text {
            color: var(--bm-danger);
            font-weight: 600;
        }

        .steps-foot {
            display: flex;
            justify-content: flex-end;
            margin: 14px auto 0;
            max-width: 680px;
            padding-top: 14px;
            border-top: 1px solid var(--bm-border);
        }

        /* ---------- Result ---------- */
        .result {
            display: none;
            margin: 16px auto 0;
            max-width: 680px;
            padding: 22px;
            text-align: left;
            border-radius: 15px;
            border: 1px solid transparent;
            animation: resultIn .3s ease both;
        }

        @keyframes resultIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .result.ok {
            background: var(--bm-success-bg);
            color: var(--bm-success);
            border-color: rgba(22, 163, 74, .25);
        }

        .result.err {
            background: var(--bm-danger-bg);
            color: var(--bm-danger);
            border-color: rgba(220, 38, 38, .25);
        }

        .result h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 8px;
            font-size: 17px;
            font-weight: 800;
        }

        .result p {
            margin: 4px 0;
            font-size: 13px;
            line-height: 1.55;
        }

        .result .acts {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
        }

        .result .acts a,
        .result .acts button {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .result.ok .btn.ghost {
            background: var(--bm-surface);
        }

        /* ---------- Hidden developer sections ---------- */
        .diagnostics-card,
        .guide-card {
            display: none !important;
        }

        .diag-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 0;
            font-size: 13px;
        }

        .diag-row .ok {
            color: var(--bm-success);
        }

        .diag-row .err {
            color: var(--bm-danger);
        }

        .diag-row .na {
            color: var(--bm-muted);
        }

        /* ---------- Back link ---------- */
        .back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
            padding: 10px 18px;
            color: var(--bm-muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            background: var(--bm-surface);
            border: 1px solid var(--bm-border);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
            transition: all .25s ease;
        }

        .back:hover {
            color: var(--bm-primary);
            border-color: var(--bm-primary);
            transform: translateX(-3px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, .12);
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 720px) {
            .up-wrap {
                width: min(100% - 22px, 1040px);
                margin-top: 70px;
            }

            .up-head {
                padding: 24px 20px;
                border-radius: 16px;
            }

            .up-head h1 i {
                width: 44px;
                height: 44px;
                font-size: 18px;
            }

            .card {
                padding: 22px 16px;
                border-radius: 15px;
            }

            .dz-grid {
                grid-template-columns: 1fr;
            }

            .dz {
                min-height: 210px;
            }

            .progress-card .step.run {
                padding: 16px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .up-wrap *,
            .up-wrap *::before,
            .up-wrap *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="up-wrap" id="app">

        <div class="up-head">
            <h1><i class="fas fa-cloud-arrow-up"></i> <span data-t="title"></span></h1>
            <p data-t="subtitle"></p>
        </div>

        <div class="banner warn" id="cfgBanner" style="display:none">
            <i class="fas fa-cloud-arrow-up"></i>
            <div id="cfgText"></div>
        </div>

        <form class="card" id="form" autocomplete="off" novalidate>

            <div class="field">
                <label for="id_film">
                    <i class="fas fa-film"></i>
                    <span data-t="lbl_film"></span>
                </label>

                <select id="id_film" name="id_film" required>
                    <option value="" data-t="opt_choose"></option>

                    <?php foreach ($films as $f): ?>
                        <option value="<?php echo (int) $f['id_film']; ?>"
                            data-has="<?php echo ((int) $f['nb_video'] > 0) ? 1 : 0; ?>">
                            <?php echo htmlspecialchars($f['nom_film']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="replace-box" id="replaceBox">
                    <label>
                        <input type="checkbox" id="replace">
                        <span data-t="replace_lbl"></span>
                    </label>
                </div>
            </div>
            <div class="dz-grid">
                <label class="dz" id="dzVideo">
                    <span class="dest b2"><i class="fas fa-video"></i></span>
                    <input type="file" id="video" accept="video/*,.mkv,.avi">
                    <i class="fas fa-film ico"></i>
                    <span class="ttl" data-t="dz_video_t"></span>
                    <span class="hint" data-t="dz_video_h"></span>
                    <span class="name" id="videoName"></span>
                    <span class="meta" id="videoMeta"></span>
                    <span class="warnline" id="videoWarn"></span>
                </label>

                <label class="dz" id="dzThumb">
                    <span class="dest ik"><i class="fas fa-image"></i></span>
                    <input type="file" id="thumbnail" accept="image/jpeg,image/png,image/webp">
                    <i class="fas fa-image ico" id="thumbIco"></i>
                    <img id="thumbPrev" alt="" style="display:none">
                    <span class="ttl" data-t="dz_thumb_t"></span>
                    <span class="hint" data-t="dz_thumb_h"></span>
                    <span class="name" id="thumbName"></span>
                    <span class="meta" id="thumbMeta"></span>
                </label>
            </div>

            <button type="submit" class="btn" id="submitBtn"><i class="fas fa-cloud-arrow-up"></i> <span
                    data-t="btn_upload"></span></button>
        </form>

        <div class="card progress-card" id="stepsCard" style="display:none" aria-live="polite">
            <h2><i class="fas fa-list-check"></i> <span data-t="progress"></span></h2>
            <ol class="steps" id="steps"></ol>
            <div class="steps-foot"><button type="button" class="btn danger" id="cancelBtn"><i class="fas fa-ban"></i>
                    <span data-t="btn_cancel"></span></button></div>
            <div class="result" id="result"></div>
        </div>

        <div class="card diagnostics-card" style="display:none">
            <h2><i class="fas fa-plug-circle-check"></i> <span data-t="diag_title"></span></h2>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button type="button" class="btn ghost" id="testBtn"><i class="fas fa-vial"></i> <span
                        data-t="btn_test"></span></button>
                <button type="button" class="btn ghost" id="corsBtn"><i class="fas fa-shield-halved"></i> <span
                        data-t="btn_cors"></span></button>
            </div>
            <div id="diag" style="margin-top:10px"></div>
            <details class="guide" style="margin-top:12px">
                <summary data-t="cors_manual"></summary>
                <pre id="corsJson"
                    style="margin:12px 0 0;padding:12px;border-radius:10px;background:var(--soft);border:1px solid var(--line);font-size:12px;overflow:auto;color:var(--text)"><?= htmlspecialchars(json_encode(b2_cors_rules(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
            </details>
        </div>

        <div class="card guide-card" style="display:none">
            <details class="guide">
                <summary><i class="fas fa-circle-question" style="color:var(--primary);margin-right:8px"></i><span
                        data-t="guide_title"></span></summary>
                <ol id="guideList"></ol>
            </details>
        </div>

        <a href="../admin/dashboard.php" class="back"><i class="fas fa-arrow-left"></i> <span data-t="back"></span></a>
    </div>

    <script>
        (function () {
            'use strict';

            const CSRF = <?= json_encode($_SESSION['up_csrf']) ?>;
            const MAX_MB = <?= (int) UP_MAX_VIDEO_MB ?>;
            const MAX_THUMB_MB = <?= (int) UP_MAX_THUMB_MB ?>;
            const ALLOWED = ['mp4', 'm4v', 'mov', 'webm', 'mkv', 'avi'];
            const $ = (s) => document.querySelector(s);

            /* ============================ TEXTES (FR / EN) ============================ */
            const LANG = localStorage.getItem('lang') === 'en' ? 'en' : 'fr';
            const I18N = {
                fr: {
                    title: 'Ajouter une vidéo', subtitle: 'Choisissez votre film, ajoutez la vidéo et sa miniature.',
                    chip_video: 'Vidéo', chip_thumb: 'Miniature',
                    lbl_film: 'Choisir un film', opt_choose: '-- Sélectionner un film --', has_video: '✓ a déjà une vidéo',
                    replace_lbl: 'Ce film a déjà une vidéo. Cochez pour la REMPLACER (vues et commentaires conservés).',
                    dz_video_t: 'Vidéo du film', dz_video_h: 'Choisissez un fichier vidéo ou faites-le glisser ici',
                    dz_thumb_t: 'Image de couverture', dz_thumb_h: 'Ajoutez une image ou faites-la glisser ici',
                    warn_format: 'Ce format peut être moins compatible. Le format MP4 est recommandé.',
                    btn_upload: 'Publier la vidéo', btn_cancel: 'Annuler l’envoi', btn_retry: 'Réessayer', btn_again: 'Ajouter une autre vidéo',
                    progress: 'Envoi en cours', wait: 'En attente…',
                    st1: 'Vérification', st2: 'Préparation', st3: 'Envoi de la miniature',
                    st4: 'Envoi de la vidéo', st5: 'Vérification de l’envoi', st6: 'Finalisation',
                    s1_run: 'Vérification de vos fichiers…', s1_ok: 'Vos fichiers sont prêts.',
                    s2_run: 'Préparation de l’envoi…', s2_ok: 'Tout est prêt.',
                    s3_run: 'Envoi de l’image de couverture…', s3_ok: 'Image de couverture envoyée.',
                    s4_run: 'Envoi de la vidéo… {pct} % · {done} / {total} · {speed}/s · reste {eta}', s4_ok: 'Vidéo envoyée sur Backblaze en {time}.',
                    s5_run: 'Vérification de l’envoi…', s5_ok: 'Vidéo reçue correctement ({size}).',
                    s6_run: 'Finalisation…', s6_ok: 'Vidéo ajoutée avec succès.', s6_ok_rep: 'Vidéo remplacée avec succès.',
                    ok_title: 'Vidéo publiée avec succès', ok_text: '« {film} » est maintenant disponible ({size}, en {time}).',
                    ok_watch: 'Voir la vidéo', ok_list: 'Liste des vidéos',
                    fail_title: 'Échec à l\'étape « {step} »',
                    clean_none: 'Rien n\'a été enregistré.',
                    clean_ok: 'Les fichiers déjà envoyés ont été supprimés. Rien n\'a été enregistré.',
                    clean_ko: 'Rien n\'a été enregistré, mais ce nettoyage a échoué : {detail}. Supprimez ces fichiers à la main.',
                    e_no_film: 'Choisissez d\'abord un film.', e_no_video: 'Choisissez un fichier vidéo.', e_no_thumb: 'Choisissez une miniature.',
                    e_ext: 'Format vidéo non accepté (MP4, M4V, MOV, WEBM, MKV ou AVI).',
                    e_size: 'La vidéo dépasse la taille maximale ({max} Mo).',
                    e_empty: 'Le fichier vidéo est vide.',
                    e_thumb_type: 'La miniature doit être une image JPG, PNG ou WEBP.',
                    e_thumb_size: 'La miniature dépasse {max} Mo.',
                    e_thumb_php_limit: 'Le serveur refuse cette image : limite d\'upload PHP ({detail}).',
                    e_thumb_upload: 'Le serveur n\'a pas reçu la miniature correctement ({detail}).',
                    e_thumb_ik: 'ImageKit a refusé la miniature : {detail}',
                    e_exists: 'Ce film a déjà une vidéo : cochez « Remplacer » pour continuer.',
                    e_film: 'Film introuvable (supprimé ?).',
                    e_csrf: 'Le formulaire a expiré. Rechargez la page puis recommencez.',
                    e_session: 'Votre session administrateur a expiré. Reconnectez-vous.',
                    e_config: 'Le service d’envoi est temporairement indisponible. Réessayez plus tard.',
                    e_net: 'Impossible de contacter le serveur. Vérifiez votre connexion puis réessayez.',
                    e_b2_put: 'La vidéo n’a pas pu être envoyée. Vérifiez votre connexion puis réessayez.',
                    e_b2_auth: 'Le service d’envoi est temporairement indisponible. Réessayez plus tard.',
                    e_b2_network: 'La connexion a été interrompue. Vérifiez votre réseau puis réessayez.',
                    e_aborted: 'Envoi annulé.',
                    e_b2_missing: 'La vidéo n’a pas été reçue correctement. Réessayez.',
                    e_b2_size: 'L’envoi semble incomplet. Vérifiez votre connexion puis réessayez.',
                    e_b2_head: 'Impossible de vérifier l’envoi. Réessayez dans un instant.',
                    e_db: 'La vidéo a été envoyée mais la finalisation a échoué. Réessayez.',
                    e_server: 'Une erreur est survenue. Réessayez dans un instant.',
                    h_b2_auth: 'Réessayez plus tard ou contactez l’administrateur si le problème persiste.',
                    h_cors: 'Cliquez sur « Configurer CORS automatiquement » dans la carte Connexions, plus bas.',
                    btn_cors: 'Configurer CORS automatiquement', cors_run: 'Configuration de Backblaze…',
                    t_cors_ok: 'CORS : l\'envoi depuis le navigateur est autorisé.',
                    t_cors_ko: 'CORS : envoi depuis le navigateur BLOQUÉ (HTTP {status}) {detail}',
                    t_cors_fix: 'Cliquez sur « Configurer CORS automatiquement ».',
                    cors_applied: 'Règle CORS enregistrée sur le bucket.',
                    cors_wait: 'Elle peut mettre quelques secondes à s\'activer : relancez « Tester les connexions ».',
                    e_cors_apply: 'Backblaze refuse de modifier le bucket (HTTP {status}) : {detail}',
                    h_cors_apply: 'Votre clé n\'a sans doute pas la permission « writeBuckets ». Utilisez la règle manuelle ci-dessous, dans la console Backblaze (bucket → CORS Rules).',
                    e_cors_missing: 'Le bucket Backblaze bloque l\'envoi depuis le navigateur : la règle CORS est absente.',
                    cors_manual: 'Règle CORS à saisir à la main (si le bouton échoue)',
                    h_ik: 'Vérifiez IK_PRIVATE_KEY (clé privée ImageKit).',
                    diag_title: 'Connexions', btn_test: 'Tester les connexions', testing: 'Test en cours…',
                    t_b2_ok: 'Backblaze : connexion OK (bucket « {bucket} »).', t_ik_ok: 'ImageKit : clé valide.',
                    t_b2_ko: 'Backblaze : échec (HTTP {status}) {detail}', t_ik_ko: 'ImageKit : échec (HTTP {status}) {detail}',
                    t_na: '{name} : non testé (clés manquantes).',
                    cfg_missing: 'L’envoi de vidéos est temporairement indisponible. Veuillez réessayer plus tard.',
                    guide_title: 'Comment configurer Backblaze ?',
                    guide: [
                        'Sur backblaze.com → <b>B2 Cloud Storage</b> → <b>Create a Bucket</b> : choisissez <b>Public</b>.',
                        '<b>App Keys</b> → <b>Add a New Application Key</b> (accès à ce bucket, lecture + écriture) : copiez <code>keyID</code> et <code>applicationKey</code>.',
                        'L\'adresse du serveur Backblaze (Endpoint) est détectée toute seule. Seulement si le test échoue : copiez-la depuis la page du bucket dans <code>B2_ENDPOINT</code>.',
                        'Dans la carte « Connexions » ci-dessous, cliquez sur <b>Configurer CORS automatiquement</b> (sinon, saisissez la règle manuelle dans Bucket → CORS Rules).',
                        'Collez ces valeurs en haut de <code>admin/upload_video.php</code>, puis cliquez sur « Tester les connexions ».'
                    ],
                    back: 'Retour au tableau de bord',
                    leave: 'Un envoi est en cours : quitter la page l\'annulera.'
                },
                en: {
                    title: 'Add a video', subtitle: 'Choose a movie, add its video and cover image.',
                    chip_video: 'Video', chip_thumb: 'Cover image',
                    lbl_film: 'Choose a movie', opt_choose: '-- Select a movie --', has_video: '✓ already has a video',
                    replace_lbl: 'This movie already has a video. Tick to REPLACE it (views and comments are kept).',
                    dz_video_t: 'Movie video', dz_video_h: 'Choose a video file or drop it here',
                    dz_thumb_t: 'Cover image', dz_thumb_h: 'Add an image or drop it here',
                    warn_format: 'This format may be less compatible. MP4 is recommended.',
                    btn_upload: 'Publish video', btn_cancel: 'Cancel upload', btn_retry: 'Try again', btn_again: 'Add another video',
                    progress: 'Upload', wait: 'Waiting…',
                    st1: 'Checking', st2: 'Preparing', st3: 'Uploading cover image',
                    st4: 'Uploading video', st5: 'Checking upload', st6: 'Finishing',
                    s1_run: 'Checking your files…', s1_ok: 'Your files are ready.',
                    s2_run: 'Preparing the upload…', s2_ok: 'Everything is ready.',
                    s3_run: 'Uploading the cover image…', s3_ok: 'Cover image uploaded.',
                    s4_run: 'Uploading video… {pct}% · {done} / {total} · {speed}/s · {eta} left', s4_ok: 'Video uploaded in {time}.',
                    s5_run: 'Checking the upload…', s5_ok: 'Video received correctly ({size}).',
                    s6_run: 'Saving to the database…', s6_ok: 'Video added to the catalog.', s6_ok_rep: 'Video replaced in the catalog.',
                    ok_title: 'Video published successfully', ok_text: '“{film}” is live ({size}, uploaded in {time}).',
                    ok_watch: 'Watch the video', ok_list: 'Video list',
                    fail_title: 'Failed at step “{step}”',
                    clean_none: 'Nothing was saved.',
                    clean_ok: 'Files already uploaded were deleted. Nothing was saved.',
                    clean_ko: 'Nothing was saved, but cleanup failed: {detail}. Please delete those files manually.',
                    e_no_film: 'Choose a movie first.', e_no_video: 'Choose a video file.', e_no_thumb: 'Choose a thumbnail.',
                    e_ext: 'Video format not accepted (MP4, M4V, MOV, WEBM, MKV or AVI).',
                    e_size: 'The video exceeds the maximum size ({max} MB).',
                    e_empty: 'The video file is empty.',
                    e_thumb_type: 'The thumbnail must be a JPG, PNG or WEBP image.',
                    e_thumb_size: 'The thumbnail exceeds {max} MB.',
                    e_thumb_php_limit: 'The server rejects this image: PHP upload limit ({detail}).',
                    e_thumb_upload: 'The server did not receive the thumbnail correctly ({detail}).',
                    e_thumb_ik: 'ImageKit rejected the thumbnail: {detail}',
                    e_exists: 'This movie already has a video: tick “Replace” to continue.',
                    e_film: 'Movie not found (deleted?).',
                    e_csrf: 'The form expired. Reload the page and try again.',
                    e_session: 'Your admin session expired. Please log in again.',
                    e_config: 'Video uploads are temporarily unavailable. Please try again later.',
                    e_net: 'The server could not be reached. Check your connection and try again.',
                    e_b2_put: 'The video could not be uploaded. Check your connection and try again.',
                    e_b2_auth: 'The upload service is temporarily unavailable. Please try again later.',
                    e_b2_network: 'The connection was interrupted. Check your network and try again.',
                    e_aborted: 'Upload cancelled.',
                    e_b2_missing: 'The video was not received correctly. Please try again.',
                    e_b2_size: 'The upload appears incomplete. Check your connection and try again.',
                    e_b2_head: 'The upload could not be verified. Please try again.',
                    e_db: 'The video was uploaded but finishing failed. Please try again.',
                    e_server: 'Something went wrong. Please try again in a moment.',
                    h_b2_auth: 'Try again later or contact the administrator if the problem continues.',
                    h_cors: 'Click “Configure CORS automatically” in the Connections card below.',
                    btn_cors: 'Configure CORS automatically', cors_run: 'Configuring Backblaze…',
                    t_cors_ok: 'CORS: uploads from the browser are allowed.',
                    t_cors_ko: 'CORS: uploads from the browser are BLOCKED (HTTP {status}) {detail}',
                    t_cors_fix: 'Click “Configure CORS automatically”.',
                    cors_applied: 'CORS rule saved on the bucket.',
                    cors_wait: 'It can take a few seconds to activate: run “Test connections” again.',
                    e_cors_apply: 'Backblaze refuses to modify the bucket (HTTP {status}): {detail}',
                    h_cors_apply: 'Your key probably lacks the “writeBuckets” permission. Use the manual rule below in the Backblaze console (bucket → CORS Rules).',
                    e_cors_missing: 'The Backblaze bucket blocks uploads from the browser: the CORS rule is missing.',
                    cors_manual: 'CORS rule to enter by hand (if the button fails)',
                    h_ik: 'Check IK_PRIVATE_KEY (your ImageKit private key).',
                    diag_title: 'Connections', btn_test: 'Test connections', testing: 'Testing…',
                    t_b2_ok: 'Backblaze: connection OK (bucket “{bucket}”).', t_ik_ok: 'ImageKit: key is valid.',
                    t_b2_ko: 'Backblaze: failed (HTTP {status}) {detail}', t_ik_ko: 'ImageKit: failed (HTTP {status}) {detail}',
                    t_na: '{name}: not tested (missing keys).',
                    cfg_missing: 'Video uploads are temporarily unavailable. Please try again later.',
                    guide_title: 'How to set up Backblaze?',
                    guide: [
                        'On backblaze.com → <b>B2 Cloud Storage</b> → <b>Create a Bucket</b>: choose <b>Public</b>.',
                        '<b>App Keys</b> → <b>Add a New Application Key</b> (access to this bucket, read + write): copy <code>keyID</code> and <code>applicationKey</code>.',
                        'Backblaze\'s server address (Endpoint) is detected automatically. Only if the test fails: copy it from the bucket page into <code>B2_ENDPOINT</code>.',
                        'In the “Connections” card below, click <b>Configure CORS automatically</b> (otherwise enter the manual rule in Bucket → CORS Rules).',
                        'Paste these values at the top of <code>admin/upload_video.php</code>, then click “Test connections”.'
                    ],
                    back: 'Back to dashboard',
                    leave: 'An upload is in progress: leaving the page will cancel it.'
                }
            };
            const t = (k, v) => String((I18N[LANG][k] ?? I18N.fr[k] ?? k)).replace(/\{(\w+)\}/g, (_, x) => (v && v[x] != null ? v[x] : ''));

            document.querySelectorAll('[data-t]').forEach(el => {
                el.textContent = t(el.dataset.t, { max: el.dataset.t === 'dz_video_h' ? (MAX_MB / 1024) : MAX_THUMB_MB });
            });
            $('#guideList').innerHTML = I18N[LANG].guide.map(x => '<li>' + x + '</li>').join('');
            document.querySelectorAll('#id_film option[data-has="1"]').forEach(o => { o.textContent += '  —  ' + t('has_video'); });

            /* ============================ OUTILS ============================ */
            const U = LANG === 'en' ? ['KB', 'MB', 'GB'] : ['Ko', 'Mo', 'Go'];
            const fmtSize = (b) => b >= 1073741824 ? (b / 1073741824).toFixed(2) + ' ' + U[2]
                : b >= 1048576 ? (b / 1048576).toFixed(1) + ' ' + U[1]
                    : Math.max(1, Math.round(b / 1024)) + ' ' + U[0];
            const fmtTime = (s) => { s = Math.max(0, Math.round(s)); const m = Math.floor(s / 60); return m ? m + ' min ' + (s % 60) + ' s' : s + ' s'; };
            const esc = (s) => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

            /* ============================ CONFIG / ZONES ============================ */
            const cfgBanner = $('#cfgBanner');
            const missing = JSON.parse(cfgBanner.dataset.missing || '[]');
            if (missing.length) {
                cfgBanner.style.display = 'flex';
                $('#cfgText').textContent = t('cfg_missing');
                $('#submitBtn').disabled = true;
                $('.guide').open = true;
            }

            const selFilm = $('#id_film'), replaceBox = $('#replaceBox'), replaceCk = $('#replace');
            const hasVideo = () => selFilm.selectedOptions[0] && selFilm.selectedOptions[0].dataset.has === '1';
            selFilm.addEventListener('change', () => { replaceBox.style.display = hasVideo() ? 'block' : 'none'; replaceCk.checked = false; });

            function bindZone(zone, input, onFile) {
                input.addEventListener('change', () => onFile(input.files[0]));
                ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('drag'); }));
                ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('drag'); }));
                zone.addEventListener('drop', e => {
                    if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; onFile(input.files[0]); }
                });
            }
            bindZone($('#dzVideo'), $('#video'), (f) => {
                $('#dzVideo').classList.toggle('has', !!f);
                $('#videoName').textContent = f ? f.name : '';
                $('#videoMeta').textContent = f ? fmtSize(f.size) : '';
                const ext = f ? f.name.split('.').pop().toLowerCase() : '';
                $('#videoWarn').textContent = f && !['mp4', 'm4v', 'webm'].includes(ext) ? t('warn_format') : '';
            });
            bindZone($('#dzThumb'), $('#thumbnail'), (f) => {
                $('#dzThumb').classList.toggle('has', !!f);
                $('#thumbName').textContent = f ? f.name : '';
                $('#thumbMeta').textContent = f ? fmtSize(f.size) : '';
                const img = $('#thumbPrev');
                if (f) { img.src = URL.createObjectURL(f); img.style.display = 'block'; $('#thumbIco').style.display = 'none'; }
                else { img.style.display = 'none'; $('#thumbIco').style.display = ''; }
            });

            /* ============================ ÉTAPES ============================ */
            const stepsEl = $('#steps');
            let cur = 0;
            function buildSteps() {
                stepsEl.innerHTML = '';
                for (let i = 1; i <= 6; i++) {
                    const li = document.createElement('li');
                    li.className = 'step'; li.id = 'step' + i;
                    li.innerHTML = '<div class="st-ico">' + i + '</div><div class="st-body"><div class="st-title">' + esc(t('st' + i)) +
                        '</div><div class="st-text">' + esc(t('wait')) + '</div>' + (i === 4 ? '<div class="bar"><span id="barFill"></span></div>' : '') + '</div>';
                    stepsEl.appendChild(li);
                }
            }
            function setStep(i, state, text, html) {
                const li = $('#step' + i); if (!li) return;
                li.className = 'step ' + state;
                const ico = li.querySelector('.st-ico');
                ico.innerHTML = state === 'run' ? '<i class="fas fa-spinner fa-spin"></i>' : state === 'ok' ? '<i class="fas fa-check"></i>' : state === 'err' ? '<i class="fas fa-xmark"></i>' : i;
                const tx = li.querySelector('.st-text');
                if (html != null) tx.innerHTML = html; else tx.textContent = text;
            }
            function errMessage(err) {
                const code = err.code || 'server';
                let html = esc(t('e_' + code, err) === 'e_' + code ? t('e_server', { detail: err.detail || err.message || code }) : t('e_' + code, err));
                let hint = '';
                if ((code === 'b2_put' && [400, 401, 403].includes(err.status)) || code === 'b2_auth') hint = 'h_b2_auth';
                if (code === 'b2_network' || code === 'cors_missing') hint = 'h_cors';
                if (code === 'cors_apply' && [401, 403].includes(err.status)) hint = 'h_cors_apply';
                if (code === 'thumb_ik' && [401, 403].includes(err.status)) hint = 'h_ik';
                if (hint) html += '<small>' + esc(t(hint)) + '</small>';
                return html;
            }

            /* ============================ API ============================ */
            async function api(action, fd) {
                let res;
                try {
                    res = await fetch('?action=' + action, { method: 'POST', body: fd || new FormData(), headers: { 'X-CSRF-Token': CSRF }, credentials: 'same-origin' });
                } catch (e) { throw { code: 'net', detail: e.message }; }
                let data;
                try { data = await res.json(); } catch (e) { throw { code: 'session' }; }
                if (!data.ok) throw data;
                return data;
            }

            let xhr = null;
            function putVideo(url, file, ctype, onProg) {
                return new Promise((resolve, reject) => {
                    const x = new XMLHttpRequest(); xhr = x;
                    x.open('PUT', url, true);
                    x.setRequestHeader('Content-Type', ctype);
                    x.upload.onprogress = ev => { if (ev.lengthComputable) onProg(ev.loaded, ev.total); };
                    x.onload = () => {
                        if (x.status >= 200 && x.status < 300) return resolve();
                        const m = /<Code>(.*?)<\/Code>[\s\S]*?<Message>(.*?)<\/Message>/.exec(x.responseText || '');
                        reject({ code: 'b2_put', status: x.status, detail: m ? m[1] + ': ' + m[2] : (x.statusText || '') });
                    };
                    x.onerror = () => reject({ code: 'b2_network' });
                    x.onabort = () => reject({ code: 'aborted' });
                    x.send(file);
                });
            }

            /* ============================ ENVOI ============================ */
            let running = false, cancelled = false, created = {};
            const form = $('#form'), submitBtn = $('#submitBtn');

            function lock(on) {
                running = on;
                submitBtn.disabled = on || missing.length > 0;
                [...form.elements].forEach(el => { if (el !== submitBtn) el.disabled = on; });
                $('#cancelBtn').style.display = on ? '' : 'none';
            }
            window.addEventListener('beforeunload', e => { if (running) { e.preventDefault(); e.returnValue = t('leave'); } });

            function validate() {
                const film = selFilm.value;
                if (!film) throw { code: 'no_film' };
                const v = $('#video').files[0]; if (!v) throw { code: 'no_video' };
                const ext = v.name.split('.').pop().toLowerCase();
                if (!ALLOWED.includes(ext)) throw { code: 'ext' };
                if (v.size === 0) throw { code: 'empty' };
                if (v.size > MAX_MB * 1048576) throw { code: 'size', max: MAX_MB };
                const th = $('#thumbnail').files[0]; if (!th) throw { code: 'no_thumb' };
                if (!/^image\/(jpeg|png|webp)$/.test(th.type)) throw { code: 'thumb_type' };
                if (th.size > MAX_THUMB_MB * 1048576) throw { code: 'thumb_size', max: MAX_THUMB_MB };
                if (hasVideo() && !replaceCk.checked) throw { code: 'exists' };
                return { film, v, th, filmName: selFilm.selectedOptions[0].textContent.split('  —  ')[0].trim() };
            }
            const check = () => { if (cancelled) throw { code: 'aborted' }; };

            async function run() {
                if (running) return;
                const result = $('#result');
                result.style.display = 'none'; result.className = 'result';
                $('#stepsCard').style.display = 'block';
                buildSteps(); created = {}; cancelled = false; cur = 1;
                lock(true);
                $('#stepsCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
                const t0 = Date.now();

                try {
                    // 1 — contrôles côté navigateur
                    setStep(1, 'run', t('s1_run'));
                    const d = validate();
                    setStep(1, 'ok', t('s1_ok', { v: d.v.name, vs: fmtSize(d.v.size), th: d.th.name }));
                    check();

                    // 2 — lien d'envoi sécurisé
                    cur = 2; setStep(2, 'run', t('s2_run'));
                    const fp = new FormData();
                    fp.append('id_film', d.film); fp.append('filename', d.v.name); fp.append('size', d.v.size);
                    if (replaceCk.checked) fp.append('replace', '1');
                    const prep = await api('prepare', fp);
                    setStep(2, 'ok', t('s2_ok'));
                    check();

                    // 3 — miniature -> ImageKit
                    cur = 3; setStep(3, 'run', t('s3_run'));
                    const ft = new FormData(); ft.append('thumbnail', d.th);
                    const th = await api('thumb', ft);
                    created.fileId = th.fileId;
                    setStep(3, 'ok', t('s3_ok'));
                    check();

                    // 4 — vidéo -> Backblaze (direct)
                    cur = 4; created.key = prep.key;
                    const tv = Date.now(); let last = { t: tv, b: 0 }, speed = 0;
                    setStep(4, 'run', t('s4_run', { pct: 0, done: '0', total: fmtSize(d.v.size), speed: '…', eta: '…' }));
                    await putVideo(prep.put_url, d.v, prep.content_type, (loaded, total) => {
                        const now = Date.now();
                        if (now - last.t >= 500) {
                            const inst = (loaded - last.b) / ((now - last.t) / 1000);
                            speed = speed ? speed * .7 + inst * .3 : inst; last = { t: now, b: loaded };
                        }
                        const pct = Math.floor(loaded / total * 100);
                        $('#barFill').style.width = pct + '%';
                        setStep(4, 'run', t('s4_run', {
                            pct, done: fmtSize(loaded), total: fmtSize(total),
                            speed: speed ? fmtSize(speed) : '…', eta: speed ? fmtTime((total - loaded) / speed) : '…'
                        }));
                    });
                    xhr = null;
                    $('#barFill').style.width = '100%';
                    setStep(4, 'ok', t('s4_ok', { time: fmtTime((Date.now() - tv) / 1000) }));
                    check();

                    // 5 — le fichier est-il vraiment arrivé ?
                    cur = 5; setStep(5, 'run', t('s5_run'));
                    const fv = new FormData(); fv.append('key', prep.key); fv.append('size', d.v.size);
                    const vr = await api('verify', fv);
                    setStep(5, 'ok', t('s5_ok', { size: fmtSize(vr.size) }));
                    check();

                    // 6 — base de données
                    cur = 6; setStep(6, 'run', t('s6_run'));
                    const fs = new FormData();
                    fs.append('id_film', d.film); fs.append('key', prep.key); fs.append('thumb_url', th.url);
                    if (replaceCk.checked) fs.append('replace', '1');
                    const sv = await api('save', fs);
                    setStep(6, 'ok', t(sv.replaced ? 's6_ok_rep' : 's6_ok'));

                    // ✅ terminé
                    result.className = 'result ok'; result.style.display = 'block';
                    result.innerHTML = '<h3><i class="fas fa-circle-check"></i> ' + esc(t('ok_title')) + '</h3><p>' +
                        esc(t('ok_text', { film: d.filmName, size: fmtSize(d.v.size), time: fmtTime((Date.now() - t0) / 1000) })) + '</p>' +
                        '<div class="acts"><a class="btn ghost" href="../watch.php?id_video=' + sv.id_video + '"><i class="fas fa-play"></i> ' + esc(t('ok_watch')) + '</a>' +
                        '<a class="btn ghost" href="../video.php"><i class="fas fa-list"></i> ' + esc(t('ok_list')) + '</a>' +
                        '<button type="button" class="btn ghost" id="againBtn"><i class="fas fa-plus"></i> ' + esc(t('btn_again')) + '</button></div>';
                    $('#cancelBtn').style.display = 'none';
                    created = {};
                    running = false;
                    $('#againBtn').onclick = resetAll;
                    result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                } catch (err) {
                    await fail(err);
                }
            }

            async function fail(err) {
                if (err.code === 'b2_network') {            // réseau ? ou règle CORS absente ? on vérifie
                    try {
                        const r = await api('cors_check');
                        if (r.cors && !r.cors.ok) err = { code: 'cors_missing', status: r.cors.status, detail: r.cors.detail };
                    } catch (e) { /* on garde le message réseau */ }
                }
                setStep(cur || 1, 'err', null, errMessage(err));
                xhr = null;

                let cleanMsg = t('clean_none');
                if (created.key || created.fileId) {
                    const fc = new FormData();
                    if (created.key) fc.append('key', created.key);
                    if (created.fileId) fc.append('fileId', created.fileId);
                    try {
                        await api('cleanup', fc);
                        cleanMsg = t('clean_ok');
                    } catch (e) {
                        cleanMsg = t('clean_ko', { detail: e.detail || e.code || '?' });
                    }
                }
                created = {};

                const result = $('#result');
                result.className = 'result err'; result.style.display = 'block';
                result.innerHTML = '<h3><i class="fas fa-circle-xmark"></i> ' + esc(t('fail_title', { step: t('st' + (cur || 1)) })) + '</h3>' +
                    '<p>' + errMessage(err) + '</p><p>' + esc(cleanMsg) + '</p>' +
                    '<div class="acts"><button type="button" class="btn ghost" id="retryBtn"><i class="fas fa-rotate-right"></i> ' + esc(t('btn_retry')) + '</button></div>';
                $('#cancelBtn').style.display = 'none';
                lock(false);
                $('#retryBtn').onclick = () => { lock(false); run(); };
                result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            function resetAll() {
                form.reset();
                ['#dzVideo', '#dzThumb'].forEach(s => $(s).classList.remove('has'));
                ['#videoName', '#videoMeta', '#videoWarn', '#thumbName', '#thumbMeta'].forEach(s => $(s).textContent = '');
                $('#thumbPrev').style.display = 'none'; $('#thumbIco').style.display = '';
                replaceBox.style.display = 'none';
                $('#stepsCard').style.display = 'none';
                lock(false);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            form.addEventListener('submit', e => { e.preventDefault(); run(); });
            $('#cancelBtn').addEventListener('click', () => { cancelled = true; if (xhr) xhr.abort(); });
            $('#cancelBtn').style.display = 'none';

            /* ============================ TEST DES CONNEXIONS ============================ */
            const row = (ok, txt) => '<div class="diag-row"><i class="fas ' + (ok === null ? 'fa-circle-minus na' : ok ? 'fa-circle-check ok' : 'fa-circle-xmark err') + '"></i><span>' + esc(txt) + '</span></div>';
            const spin = (txt) => '<div class="diag-row"><i class="fas fa-spinner fa-spin na"></i> ' + esc(txt) + '</div>';
            function errPlain(e) { const m = t('e_' + e.code, e); return m === 'e_' + e.code ? t('e_server', { detail: e.detail || e.code }) : m; }

            $('#testBtn').addEventListener('click', async () => {
                const box = $('#diag'), btn = $('#testBtn');
                btn.disabled = true; box.innerHTML = spin(t('testing'));
                try {
                    const r = await api('test');
                    let h = '';
                    h += r.b2 ? (r.b2.ok ? row(true, t('t_b2_ok', { bucket: <?= json_encode(B2_BUCKET) ?> })) : row(false, t('t_b2_ko', r.b2))) : row(null, t('t_na', { name: 'Backblaze' }));
                    h += r.cors ? (r.cors.ok ? row(true, t('t_cors_ok')) : row(false, t('t_cors_ko', r.cors) + ' → ' + t('t_cors_fix'))) : row(null, t('t_na', { name: 'CORS' }));
                    h += r.ik ? (r.ik.ok ? row(true, t('t_ik_ok')) : row(false, t('t_ik_ko', r.ik))) : row(null, t('t_na', { name: 'ImageKit' }));
                    box.innerHTML = h;
                } catch (e) {
                    box.innerHTML = row(false, e.code === 'session' ? t('e_session') : errPlain(e));
                }
                btn.disabled = false;
            });

            $('#corsBtn').addEventListener('click', async () => {
                const box = $('#diag'), btn = $('#corsBtn');
                btn.disabled = true; box.innerHTML = spin(t('cors_run'));
                try {
                    const r = await api('cors_apply');
                    box.innerHTML = row(true, t('cors_applied')) + (r.cors && r.cors.ok ? row(true, t('t_cors_ok')) : row(null, t('cors_wait')));
                } catch (e) {
                    let h = row(false, e.code === 'session' ? t('e_session') : errPlain(e));
                    if (e.code === 'cors_apply' && [401, 403].includes(e.status)) h += row(null, t('h_cors_apply'));
                    box.innerHTML = h;
                }
                btn.disabled = false;
            });
        })();
    </script>

</body>

</html>