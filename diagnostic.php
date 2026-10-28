<?php
// diagnostic.php - À mettre dans le dossier racine, à ouvrir dans le navigateur, puis À SUPPRIMER.
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

function ok($t, $d = '')  { echo "<p>✅ <b>$t</b> $d</p>"; }
function ko($t, $d = '')  { echo "<p style='color:#b91c1c'>❌ <b>$t</b> $d</p>"; }
function info($t)         { echo "<p style='color:#475569'>ℹ️ $t</p>"; }

echo "<body style='font-family:Arial;max-width:800px;margin:30px auto;line-height:1.5'>";
echo "<h2>Diagnostic BERTIN MOVIE</h2>";

// 1) PHP
version_compare(PHP_VERSION, '7.3', '>=') ? ok('Version PHP', PHP_VERSION) : ko('Version PHP trop ancienne', PHP_VERSION . ' (il faut 7.3 ou plus)');
foreach (['pdo_mysql', 'openssl', 'mbstring'] as $ext) {
    extension_loaded($ext) ? ok("Extension $ext") : ko("Extension $ext absente", $ext === 'openssl' ? "(nécessaire pour envoyer les emails : activez extension=openssl dans php.ini)" : '');
}

// 2) Fichiers
$fichiers = ['config.php', 'connexion.php', 'vendor/autoload.php', 'vendor/phpmailer/phpmailer/src/PHPMailer.php'];
foreach ($fichiers as $f) {
    file_exists(__DIR__ . '/' . $f) ? ok("Fichier $f trouvé") : ko("Fichier $f INTROUVABLE", $f === 'vendor/autoload.php' || strpos($f, 'phpmailer') !== false ? "→ PHPMailer n'est pas installé dans ce dossier : c'est probablement la cause de l'erreur 500" : '');
}

// 3) config.php
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    foreach (['DB_LOCAL_HOST', 'DB_LOCAL_NAME', 'DB_LOCAL_USER', 'DB_LOCAL_PASS', 'DB_PROD_HOST', 'DB_PROD_NAME', 'DB_PROD_USER', 'DB_PROD_PASS', 'SMTP_USER', 'SMTP_PASS'] as $c) {
        if (!defined($c)) { ko("Constante $c manquante dans config.php"); }
        elseif (constant($c) === 'A_REMPLIR') { ko("$c vaut encore A_REMPLIR", '→ mettez le vrai mot de passe'); }
    }
    ok('config.php chargé');
}

// 4) PHPMailer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        class_exists('PHPMailer\PHPMailer\PHPMailer') ? ok('Classe PHPMailer chargée') : ko('Classe PHPMailer introuvable malgré autoload.php');
    } catch (Throwable $e) {
        ko('Erreur en chargeant vendor/autoload.php', htmlspecialchars($e->getMessage()));
    }
}

// 5) Base de données (même logique que connexion.php)
if (defined('DB_LOCAL_HOST')) {
    $serveur = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $local = ($serveur === 'localhost' || $serveur === '127.0.0.1');
    info("Environnement détecté : " . ($local ? 'LOCAL' : 'INFINITYFREE') . " ($serveur)");
    $h = $local ? DB_LOCAL_HOST : DB_PROD_HOST;
    $n = $local ? DB_LOCAL_NAME : DB_PROD_NAME;
    $u = $local ? DB_LOCAL_USER : DB_PROD_USER;
    $p = $local ? DB_LOCAL_PASS : DB_PROD_PASS;
    try {
        $pdo = new PDO("mysql:host=$h;dbname=$n;charset=utf8mb4", $u, $p, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        ok('Connexion à la base', "($n)");

        foreach (['utilisateur', 'pending_users', 'pays', 'historique_connexion'] as $t) {
            $r = $pdo->query("SHOW TABLES LIKE '$t'")->rowCount();
            $r ? ok("Table $t") : ko("Table $t ABSENTE", $t === 'pending_users' ? "→ l'inscription ne peut pas fonctionner sans cette table" : '');
        }
        $attendues = ['utilisateur' => ['tentatives_echec', 'verrouille_jusqua'], 'pending_users' => ['otp_code', 'nom_utilisateur', 'prenom', 'nom', 'email', 'sexe', 'date_naissance', 'id_pays', 'mot_de_passe']];
        foreach ($attendues as $table => $cols) {
            if (!$pdo->query("SHOW TABLES LIKE '$table'")->rowCount()) continue;
            $existantes = array_column($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
            foreach ($cols as $c) {
                in_array($c, $existantes) ? ok("Colonne $table.$c") : ko("Colonne $table.$c ABSENTE", $c === 'tentatives_echec' || $c === 'verrouille_jusqua' ? '→ exécutez migration.sql (le site marche quand même, sans verrouillage)' : '');
            }
        }
    } catch (Throwable $e) {
        ko('Connexion à la base impossible', htmlspecialchars($e->getMessage()));
    }
}

// 6) Accès au serveur email
$errno = 0; $errstr = '';
$s = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 6);
if ($s) { ok('Le serveur peut joindre smtp.gmail.com:587'); fclose($s); }
else { ko('Impossible de joindre smtp.gmail.com:587', "($errstr) → pare-feu, pas d'internet, ou hébergeur qui bloque l'envoi d'email (c'est le cas d'InfinityFree gratuit)"); }

echo "<hr><p style='color:#b91c1c'><b>Supprimez ce fichier après le test.</b></p></body>";
