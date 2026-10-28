<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/update_session.php';
require_once __DIR__ . '/../includes/notification.php';

if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo->prepare("DELETE FROM historique_vue WHERE id_utilisateur = ?")->execute([$user_id]);
    showNotif('success', "✅ Historique effacé avec succès !");
} catch (PDOException $e) {
    showNotif('error', "❌ Erreur lors de l'effacement de l'historique");
}

header("Location: historique.php");
exit();
?>