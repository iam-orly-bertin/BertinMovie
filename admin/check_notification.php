<?php
// admin/check_notification.php (appelé en arrière-plan par le dashboard admin)
// Renvoie les notifications NON LUES (likes / commentaires des utilisateurs) puis les marque comme lues.
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notif_admin.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['error' => 'Unauthorized', 'notifications' => []]);
    exit();
}

$response = ['notification' => false, 'notifications' => [], 'non_lues' => 0];

try {
    verifierTableNotifAdmin($pdo);

    $rows = $pdo->query(
        "SELECT id_notif, type, nom_utilisateur, nom_film, message
         FROM notifications_admin
         WHERE lu = 0
         ORDER BY id_notif ASC
         LIMIT 10"
    )->fetchAll();

    if ($rows) {
        $ids = array_column($rows, 'id_notif');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE notifications_admin SET lu = 1 WHERE id_notif IN ($in)")->execute($ids);

        foreach ($rows as $r) {
            $response['notifications'][] = [
                'type' => $r['type'],
                'user' => $r['nom_utilisateur'],
                'film' => $r['nom_film'] ?? 'un film',
                'message' => $r['message'],
                'title' => ['like' => '❤️ Nouveau like', 'commentaire' => '💬 Nouveau commentaire', 'message' => '✉️ Nouveau message'][$r['type']] ?? 'Nouvelle activité',
            ];
        }
        $response['notification'] = true;
    }
    $response['non_lues'] = countNotifAdminNonLues($pdo);
} catch (PDOException $e) {
    error_log("check_notification : " . $e->getMessage());
}

echo json_encode($response);