<?php
// /film_web_dy/check_session.php (appelé toutes les 10 s par user/index.php) -> zone utilisateur
define("BM_SESSION_NAME", "BM_USER");
require_once __DIR__ . "/includes/session_boot.php";
header("Content-Type: application/json");

if (empty($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") === "admin") {
    echo json_encode(["valid" => false]);
    exit();
}

require_once __DIR__ . "/connexion.php";
require_once __DIR__ . "/includes/bm_trace.php";

try {
    $stmt = $pdo->prepare("SELECT id FROM sessions_actives WHERE id_utilisateur = ? AND session_id = ? LIMIT 1");
    $stmt->execute([$_SESSION["user_id"], session_id()]);
    $row = $stmt->fetch();

    if ($row) {
        $pdo->prepare("UPDATE sessions_actives SET derniere_activite = NOW() WHERE id = ?")->execute([$row["id"]]);
        echo json_encode(["valid" => true]);
    } else {
        bm_trace("SESSION-ENDED", $_SESSION["user_id"], null);   // ligne supprimée / connexion ailleurs
        session_unset();
        session_destroy();
        echo json_encode(["valid" => false]);
    }
} catch (PDOException $e) {
    echo json_encode(["valid" => true]);
}
