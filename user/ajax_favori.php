<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$user_id = $_SESSION['user_id'];

require_once __DIR__ . '/../includes/permissions.php';
if (!can('gerer_favoris')) {
    echo json_encode(['success' => false, 'message' => "Action non autorisée sur votre compte"]);
    exit;
}
$id_video = isset($_POST['id_video']) ? (int) $_POST['id_video'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($id_video <= 0 || !in_array($action, ['add', 'remove'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit;
}

try {
    // Vérifier si la table existe, sinon la créer
    $pdo->query("SELECT 1 FROM user_favoris LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_favoris (
            id_favori INT AUTO_INCREMENT PRIMARY KEY,
            id_utilisateur INT NOT NULL,
            id_video INT NOT NULL,
            date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE,
            FOREIGN KEY (id_video) REFERENCES video_film(id_video) ON DELETE CASCADE,
            UNIQUE KEY unique_favori (id_utilisateur, id_video)
        )
    ");
}

if ($action === 'add') {
    // Ajouter aux favoris
    $stmt = $pdo->prepare("INSERT IGNORE INTO user_favoris (id_utilisateur, id_video) VALUES (?, ?)");
    $stmt->execute([$user_id, $id_video]);
    echo json_encode(['success' => true, 'action' => 'add']);
} else {
    // Retirer des favoris
    $stmt = $pdo->prepare("DELETE FROM user_favoris WHERE id_utilisateur = ? AND id_video = ?");
    $stmt->execute([$user_id, $id_video]);
    echo json_encode(['success' => true, 'action' => 'remove']);
}
?>