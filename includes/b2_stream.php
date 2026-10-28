<?php
/* =====================================================================
   includes/b2_stream.php

   Transforme l'URL enregistrée en base (url_video) en une URL TEMPORAIRE
   signée (AWS Signature V4, API S3 de Backblaze B2).

   Pourquoi ? Le bucket "reelsofakili" n'est pas public : ouvrir
   directement  https://reelsofakili.s3.eu-central-003.backblazeb2.com/videos/xxx.mp4
   donne "UnauthorizedAccess: bucket is not authorized".
   Avec une URL signée, le navigateur peut lire la vidéo SANS rendre le
   bucket public. Le lien expire tout seul (6 h par défaut).

   Utilisation :
       require_once __DIR__ . '/../includes/b2_stream.php';
       $play_url = b2_signed_video_url($video['url_video']);
   ===================================================================== */

// Clés : on les prend dans b2_keys.php (même dossier) si elles ne sont pas déjà définies.
if (!defined('B2_KEY_ID') || !defined('B2_APP_KEY')) {
    $__b2k = __DIR__ . '/b2_keys.php';
    if (is_file($__b2k)) {
        require_once $__b2k;
    }
}

/** URL pré-signée S3 (SigV4) – lecture seule (GET). */
function b2s_presign_get($host, $region, $bucket, $key, $expires = 21600)
{
    $keyId  = defined('B2_KEY_ID') ? B2_KEY_ID : '';
    $secret = defined('B2_APP_KEY') ? B2_APP_KEY : '';
    if ($keyId === '' || $secret === '') {
        return '';
    }

    $ts    = time();
    $amz   = gmdate('Ymd\THis\Z', $ts);
    $date  = gmdate('Ymd', $ts);
    $scope = "$date/$region/s3/aws4_request";

    // chemin "path-style" : /bucket/clé  (chaque segment encodé)
    $path = '/' . rawurlencode($bucket) . '/'
        . implode('/', array_map('rawurlencode', explode('/', $key)));

    $q = [
        'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential'    => $keyId . '/' . $scope,
        'X-Amz-Date'          => $amz,
        'X-Amz-Expires'       => (string) $expires,
        'X-Amz-SignedHeaders' => 'host',
    ];
    ksort($q, SORT_STRING);

    $parts = [];
    foreach ($q as $k => $v) {
        $parts[] = rawurlencode($k) . '=' . rawurlencode($v);
    }
    $canonQuery = implode('&', $parts);

    $canon = "GET\n$path\n$canonQuery\nhost:$host\n\nhost\nUNSIGNED-PAYLOAD";
    $sts   = "AWS4-HMAC-SHA256\n$amz\n$scope\n" . hash('sha256', $canon);

    $k = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
    $k = hash_hmac('sha256', $region, $k, true);
    $k = hash_hmac('sha256', 's3', $k, true);
    $k = hash_hmac('sha256', 'aws4_request', $k, true);

    return "https://$host$path?$canonQuery&X-Amz-Signature=" . hash_hmac('sha256', $sts, $k);
}

/**
 * Reçoit l'URL stockée en base et renvoie l'URL à mettre dans <video>.
 *  - URL Backblaze (…s3.<région>.backblazeb2.com/…)  -> URL signée temporaire
 *  - toute autre URL (anciennes vidéos ImageKit, etc.) -> renvoyée telle quelle
 */
function b2_signed_video_url($stored_url, $expires = 21600)
{
    $stored_url = trim((string) $stored_url);
    if ($stored_url === '') {
        return '';
    }

    $host = (string) parse_url($stored_url, PHP_URL_HOST);
    $path = (string) parse_url($stored_url, PHP_URL_PATH);

    // 1) bucket.s3.<région>.backblazeb2.com/clé   (celle enregistrée par upload_video.php)
    if (preg_match('/^([a-z0-9][a-z0-9.-]*?)\.(s3\.([a-z0-9-]+)\.backblazeb2\.com)$/i', $host, $m)) {
        $bucket = $m[1];
        $s3host = strtolower($m[2]);
        $region = strtolower($m[3]);
        $key    = rawurldecode(ltrim($path, '/'));
    }
    // 2) s3.<région>.backblazeb2.com/bucket/clé   (au cas où)
    elseif (preg_match('/^(s3\.([a-z0-9-]+)\.backblazeb2\.com)$/i', $host, $m)) {
        $s3host = strtolower($m[1]);
        $region = strtolower($m[2]);
        $parts  = explode('/', ltrim(rawurldecode($path), '/'), 2);
        $bucket = $parts[0] ?? '';
        $key    = $parts[1] ?? '';
    } else {
        return $stored_url; // pas Backblaze : on ne touche pas
    }

    if ($bucket === '' || $key === '') {
        return $stored_url;
    }

    $signed = b2s_presign_get($s3host, $region, $bucket, $key, $expires);
    return $signed !== '' ? $signed : $stored_url;
}

/** Type MIME pour la balise <source> (vide = le navigateur détecte seul). */
function b2_video_mime($stored_url)
{
    $ext = strtolower(pathinfo((string) parse_url((string) $stored_url, PHP_URL_PATH), PATHINFO_EXTENSION));
    switch ($ext) {
        case 'mp4':
        case 'm4v':
        case 'mov':
            return 'video/mp4';   // .mov en H.264 se lit dans les navigateurs avec ce type
        case 'webm':
            return 'video/webm';
        default:
            return '';            // mkv, avi… : on laisse le navigateur essayer
    }
}
