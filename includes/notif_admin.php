<?php
// =========================================================
// includes/notif_admin.php - Notifications internes pour l'ADMIN
// Quand un utilisateur aime / commente un film, on enregistre la notification
// dans la base de données (table notifications_admin). L'admin la lit ensuite
// depuis SA propre session (avant : elle était stockée dans la session de
// l'utilisateur, donc l'admin ne la voyait jamais).
// =========================================================

function verifierTableNotifAdmin($pdo)
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications_admin (
            id_notif INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(20) NOT NULL,
            id_utilisateur INT NOT NULL,
            nom_utilisateur VARCHAR(150) NOT NULL,
            id_video INT NULL,
            nom_film VARCHAR(255) NULL,
            message VARCHAR(500) NOT NULL,
            lu TINYINT(1) NOT NULL DEFAULT 0,
            date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_lu (lu),
            INDEX idx_date (date_creation)
        )");
    } catch (PDOException $e) {
        error_log("notifications_admin (création) : " . $e->getMessage());
    }
}

/**
 * Enregistre une notification pour l'admin ($type = 'like' ou 'commentaire').
 * Ne lève jamais d'exception : un échec ne doit pas bloquer le like/commentaire.
 */
function addNotifAdmin($pdo, $type, $id_utilisateur, $nom_utilisateur, $id_video, $nom_film, $message)
{
    try {
        verifierTableNotifAdmin($pdo);
        $stmt = $pdo->prepare(
            "INSERT INTO notifications_admin (type, id_utilisateur, nom_utilisateur, id_video, nom_film, message)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$type, $id_utilisateur, $nom_utilisateur, $id_video, $nom_film, mb_substr($message, 0, 500)]);
        return true;
    } catch (PDOException $e) {
        error_log("addNotifAdmin : " . $e->getMessage());
        return false;
    }
}

/** Nombre de notifications non lues (pour un badge éventuel). */
function countNotifAdminNonLues($pdo)
{
    try {
        verifierTableNotifAdmin($pdo);
        return (int) $pdo->query("SELECT COUNT(*) FROM notifications_admin WHERE lu = 0")->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Notification "nouveau message" (chat support) pour l'admin.
 * Le contenu du message est chiffré en base : on ne met que le nom de l'expéditeur.
 * Anti-spam : si une notification "message" de ce même utilisateur n'a pas encore
 * été affichée à l'admin, on n'en ajoute pas une deuxième.
 */
function addNotifAdminMessage($pdo, $id_utilisateur)
{
    try {
        verifierTableNotifAdmin($pdo);

        $dej = $pdo->prepare("SELECT 1 FROM notifications_admin WHERE type = 'message' AND id_utilisateur = ? AND lu = 0 LIMIT 1");
        $dej->execute([$id_utilisateur]);
        if ($dej->fetchColumn()) {
            return true;
        }

        $u = $pdo->prepare("SELECT prenom, nom, nom_utilisateur FROM utilisateur WHERE id_utilisateur = ?");
        $u->execute([$id_utilisateur]);
        $d = $u->fetch() ?: [];
        $nom = trim(($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? ''));
        if ($nom === '') {
            $nom = $d['nom_utilisateur'] ?? 'Un utilisateur';
        }

        return addNotifAdmin($pdo, 'message', $id_utilisateur, $nom, null, null, $nom . ' vous a envoyé un message');
    } catch (PDOException $e) {
        error_log("addNotifAdminMessage : " . $e->getMessage());
        return false;
    }
}