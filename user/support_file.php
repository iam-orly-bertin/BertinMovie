<?php
/** user/support_file.php?id=MESSAGE_ID : téléchargement d'un fichier de SA propre conversation uniquement. */
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/support_lib.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') === 'admin') {
    http_response_code(401);
    exit('Connexion requise');
}
support_download($pdo, (int) ($_GET['id'] ?? 0), (int) $_SESSION['user_id']);