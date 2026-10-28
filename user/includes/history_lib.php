<?php
/**
 * history_lib.php : historique de visionnage (table historique_vue)
 * Utilisé par watch.php (enregistrer) et historique.php (afficher / effacer)
 */

/** Crée la table si elle n'existe pas (une seule vérification par session). */
function bm_hist_ensure(PDO $pdo): void
{
    if (!empty($_SESSION['bm_hist_ready'])) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS historique_vue (
            id_historique INT AUTO_INCREMENT PRIMARY KEY,
            id_utilisateur INT NOT NULL,
            id_video INT NOT NULL,
            date_vue TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_video (id_utilisateur, id_video),
            INDEX idx_user_date (id_utilisateur, date_vue)
        ) DEFAULT CHARSET=utf8mb4");
        $_SESSION['bm_hist_ready'] = true;
    } catch (PDOException $e) {
        // on ne bloque jamais la lecture de la vidéo à cause de l'historique
    }
}

/** Enregistre (ou remonte en tête) une vidéo dans l'historique de l'utilisateur. */
function bm_hist_record(PDO $pdo, int $user_id, int $id_video): void
{
    try {
        bm_hist_ensure($pdo);
        // 1 seule ligne par utilisateur + vidéo : on supprime l'ancienne puis on réinsère avec la date du jour
        $pdo->prepare("DELETE FROM historique_vue WHERE id_utilisateur = ? AND id_video = ?")
            ->execute([$user_id, $id_video]);
        $pdo->prepare("INSERT INTO historique_vue (id_utilisateur, id_video) VALUES (?, ?)")
            ->execute([$user_id, $id_video]);
    } catch (PDOException $e) {
    }
}