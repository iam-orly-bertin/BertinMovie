<?php
// admin/logout.php : déconnexion propre de la zone admin
require_once __DIR__ . "/../includes/session_boot.php";
require_once __DIR__ . "/../connexion.php";

if (!empty($_SESSION["user_id"])) {
    try {
        $pdo->prepare("DELETE FROM sessions_actives WHERE id_utilisateur = ? AND session_id = ?")
            ->execute([$_SESSION["user_id"], session_id()]);
    } catch (Exception $e) {
    }
}
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}
session_destroy();
header("Location: /film_web_dy/index.php");
exit();
