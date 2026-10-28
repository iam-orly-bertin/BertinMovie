<?php
// includes/update_session.php

function updateUserSession($pdo, $id_utilisateur)
{
    if (!$id_utilisateur)
        return false;

    if (isset($_SESSION["_bm_uid"]) && (int) $_SESSION["_bm_uid"] !== (int) $id_utilisateur)
        return false;   // identité incohérente : ne pas écrire dans sessions_actives

    $session_id = session_id();

    try {
        $check = $pdo->query("SHOW TABLES LIKE 'sessions_actives'");
        if ($check->rowCount() == 0) {
            return true;
        }

        // Session déjà connue : on rafraîchit seulement l'activité (date_debut conservée)
        $upd = $pdo->prepare("UPDATE sessions_actives SET derniere_activite = NOW() WHERE id_utilisateur = ? AND session_id = ?");
        $upd->execute([$id_utilisateur, $session_id]);

        if ($upd->rowCount() === 0) {
            // Nouvelle session : une seule session active par utilisateur
            $pdo->prepare("DELETE FROM sessions_actives WHERE id_utilisateur = ?")->execute([$id_utilisateur]);
            $pdo->prepare("
                INSERT INTO sessions_actives (id_utilisateur, session_id, derniere_activite, date_debut)
                VALUES (?, ?, NOW(), NOW())
            ")->execute([$id_utilisateur, $session_id]);
        }

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function cleanInactiveSessions($pdo)
{
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'sessions_actives'");
        if ($check->rowCount() > 0) {
            $pdo->exec("DELETE FROM sessions_actives WHERE derniere_activite < DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
        }
    } catch (PDOException $e) {
        // Ignorer
    }
}
