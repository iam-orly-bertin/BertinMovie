<?php

// =============================================
// CONNEXION À LA BASE DE DONNÉES
// Version Universelle (Local + InfinityFree)
// =============================================

// Les identifiants sont dans config.php (jamais dans le code)
require_once __DIR__ . '/config.php';

// Détection automatique de l'environnement
$serveur = strtolower($_SERVER['SERVER_NAME'] ?? 'localhost');

// Environnement LOCAL = localhost, 127.0.0.1, une adresse du réseau privé
// (192.168.x.x, 10.x.x.x, 172.16-31.x.x : cas du téléphone sur le Wi-Fi),
// un nom d'ordinateur sans point (ex : DESKTOP-ABC) ou un nom en .local
$ip_privee = filter_var($serveur, FILTER_VALIDATE_IP)
    && !filter_var($serveur, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
$est_local = in_array($serveur, ['localhost', '127.0.0.1', '::1'], true)
    || $ip_privee
    || strpos($serveur, '.') === false
    || substr($serveur, -6) === '.local';

// Configuration selon l'environnement
if ($est_local) {
    // ---------- CONFIGURATION LOCALE (XAMPP/WAMP) ----------
    $host = DB_LOCAL_HOST;
    $dbname = DB_LOCAL_NAME;
    $username = DB_LOCAL_USER;
    $password = DB_LOCAL_PASS;

} else {
    // ---------- CONFIGURATION INFINITYFREE ----------
    $host = DB_PROD_HOST;
    $dbname = DB_PROD_NAME;
    $username = DB_PROD_USER;
    $password = DB_PROD_PASS;
}

// ---------- CONNEXION À LA BASE ----------
try {
    $pdo = new PDO(
        "mysql:host=" . $host . ";dbname=" . $dbname . ";charset=utf8mb4",
        $username,
        $password
    );

    // Mode erreur : exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Mode de récupération : tableau associatif
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Support UTF-8
    $pdo->exec("SET NAMES utf8mb4");

    // Colonnes d'archivage des films : créées automatiquement (une seule fois), aucun SQL à lancer
    require_once __DIR__ . '/includes/archive_lib.php';
    ensure_archive_schema($pdo, $host . '|' . $dbname);

} catch (PDOException $e) {
    // On ne montre jamais le détail technique au visiteur : on l'enregistre seulement dans les logs
    die("Erreur BD : " . $e->getMessage());
}

// ---------- VARIABLES UTILES POUR LE SITE ----------
// Détection de l'environnement (utile pour les chemins)
define('ENV_LOCAL', $est_local);

// URL de base du site
if (ENV_LOCAL) {
    // Adresse utilisée dans le navigateur (localhost ou 192.168.x.x depuis le téléphone)
    define('BASE_URL', 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/film_web_dy/');
} else {
    define('BASE_URL', 'https://bertinmovie.freedev.app/');
}

// Chemin absolu du site
define('ROOT_PATH', __DIR__ . '/');

?>