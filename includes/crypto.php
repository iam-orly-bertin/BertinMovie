<?php
/**
 * includes/crypto.php : chiffrement d'un champ sensible (critère 41)
 *
 * Algorithme : AES-256-GCM (chiffrement + authentification : toute modification du texte chiffré est détectée).
 * Format stocké en base : enc:v1:<base64( iv(12) + tag(16) + texte_chiffré )>
 * Compatibilité : une valeur SANS le préfixe « enc:v1: » est considérée comme un ancien texte en clair
 *                 (les anciens messages restent lisibles ; ils seront chiffrés si on les réécrit).
 *
 * Clé : on utilise BM_ENC_KEY si elle est définie dans config.php, par exemple :
 *           define('BM_ENC_KEY', 'colle-ici-une-longue-phrase-secrete-aleatoire');
 *       sinon une clé aléatoire est créée automatiquement dans includes/.bm_secret.key
 *       (dossier protégé par .htaccess). SAUVEGARDEZ cette clé : sans elle, les données chiffrées sont perdues.
 * 
 * test SQL pour vérifier le chiffrement :
 *    SELECT id, contenu FROM support_messages ORDER BY id DESC LIMIT 1;
 */

const BM_ENC_PREFIX = 'enc:v1:';

function bm_enc_key()
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $secret = defined('BM_ENC_KEY') ? (string) BM_ENC_KEY : '';

    if ($secret === '') {
        $file = __DIR__ . '/.bm_secret.key';
        if (!is_file($file)) {
            @file_put_contents($file, bin2hex(random_bytes(32)), LOCK_EX);
            @chmod($file, 0600);
            // interdit l'accès web direct au dossier de la clé
            if (!is_file(__DIR__ . '/.htaccess')) {
                @file_put_contents(__DIR__ . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
            }
        }
        $secret = is_file($file) ? trim((string) file_get_contents($file)) : '';
    }
    if ($secret === '') {
        throw new RuntimeException("Clé de chiffrement introuvable (définir BM_ENC_KEY dans config.php).");
    }
    return $key = hash('sha256', $secret, true);   // 32 octets = AES-256
}

/** Chiffre un texte. null / '' restent tels quels. */
function bm_encrypt($plain)
{
    if ($plain === null || $plain === '') {
        return $plain;
    }
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException("Extension PHP openssl requise pour le chiffrement.");
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt((string) $plain, 'aes-256-gcm', bm_enc_key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cipher === false) {
        throw new RuntimeException("Échec du chiffrement.");
    }
    return BM_ENC_PREFIX . base64_encode($iv . $tag . $cipher);
}

/** Déchiffre un texte. Un ancien texte en clair est renvoyé tel quel. */
function bm_decrypt($stored)
{
    if ($stored === null || $stored === '' || strncmp((string) $stored, BM_ENC_PREFIX, strlen(BM_ENC_PREFIX)) !== 0) {
        return $stored;   // vide ou ancien message non chiffré
    }
    $raw = base64_decode(substr((string) $stored, strlen(BM_ENC_PREFIX)), true);
    if ($raw === false || strlen($raw) < 29) {
        return '[message illisible]';
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', bm_enc_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '[message illisible]' : $plain;
}
