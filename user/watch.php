<?php
require_once __DIR__ . '/../includes/session_boot.php';

require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/update_session.php';
require_once __DIR__ . '/../includes/notification.php';
require_once __DIR__ . '/../includes/b2_stream.php';
require_once __DIR__ . '/includes/history_lib.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Mettre à jour la session
if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

if (!isset($_GET['id_video']) || empty($_GET['id_video'])) {
    header('Location: video.php');
    exit;
}

$id_video = (int) $_GET['id_video'];
$user_id = $_SESSION['user_id'];

// 🔥 RÉCUPÉRER LE VRAI NOM DE L'UTILISATEUR DEPUIS LA BASE DE DONNÉES
$stmt_user = $pdo->prepare("SELECT prenom, nom, nom_utilisateur FROM utilisateur WHERE id_utilisateur = ?");
$stmt_user->execute([$user_id]);
$user_data = $stmt_user->fetch();

// 🔥 CONSTRUIRE LE NOM COMPLET CORRECTEMENT
$nom_utilisateur = '';
if (!empty($user_data['prenom']) && !empty($user_data['nom'])) {
    $nom_utilisateur = trim($user_data['prenom'] . ' ' . $user_data['nom']);
} elseif (!empty($user_data['prenom'])) {
    $nom_utilisateur = $user_data['prenom'];
} elseif (!empty($user_data['nom'])) {
    $nom_utilisateur = $user_data['nom'];
} else {
    $nom_utilisateur = $user_data['nom_utilisateur'] ?? 'Un utilisateur';
}

// 🔥 METTRE À JOUR LA SESSION
$_SESSION['user_nom'] = $nom_utilisateur;

error_log("User ID: $user_id, Nom: $nom_utilisateur, Data: " . print_r($user_data, true));

/* =========================
   RÉCUPÉRATION DES INFOS
========================= */
$sql = "SELECT f.*, 
               v.id_video, 
               v.url_video, 
               v.thumbnail, 
               v.views, 
               v.date_upload,
               c.nom_categorie,
               r.nom AS nom_realisateur,
               r.prenom AS prenom_realisateur,
               l.nom_langue,
               GROUP_CONCAT(DISTINCT s.nom_studio SEPARATOR ', ') AS studios
        FROM film f
        INNER JOIN video_film v ON f.id_film = v.id_film
        LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
        LEFT JOIN realisateur r ON f.id_realisateur = r.id_realisateur
        LEFT JOIN langue l ON f.id_langue = l.id_langue
        LEFT JOIN film_studio fs ON f.id_film = fs.id_film
        LEFT JOIN studio s ON fs.id_studio = s.id_studio
        WHERE v.id_video = :id_video AND f.is_deleted = 0 AND f.est_archive = 0
        GROUP BY f.id_film";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id_video' => $id_video]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video || empty($video['url_video'])) {
    header('Location: video.php?error=video_not_found');
    exit;
}

// 🔥 MAINTENANT ON A LE NOM DU FILM !
$nom_film = $video['nom_film'];

// 🎬 Lien de lecture temporaire (le bucket Backblaze est privé)
$play_url = b2_signed_video_url($video['url_video']);
$play_mime = b2_video_mime($video['url_video']);

/* =========================
   INCRÉMENTER LES VUES
========================= */
$view_key = 'viewed_' . $id_video;
if (!isset($_SESSION[$view_key])) {
    try {
        $pdo->prepare("UPDATE video_film SET views = views + 1 WHERE id_video = ?")->execute([$id_video]);
        $_SESSION[$view_key] = true;
    } catch (PDOException $e) {
    }
}

/* =========================
   HISTORIQUE DE VISIONNAGE
   (à chaque ouverture, la vidéo remonte en tête de l'historique)
========================= */
bm_hist_record($pdo, (int) $user_id, (int) $id_video);

/* =========================
   SYSTÈME DE LIKE
========================= */
try {
    $pdo->query("SELECT 1 FROM video_likes LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS video_likes (
        id_like INT AUTO_INCREMENT PRIMARY KEY,
        id_video INT NOT NULL,
        id_utilisateur INT NOT NULL,
        date_like TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (id_video, id_utilisateur)
    )");
}

$is_liked = false;
try {
    $stmt = $pdo->prepare("SELECT 1 FROM video_likes WHERE id_video = ? AND id_utilisateur = ?");
    $stmt->execute([$id_video, $user_id]);
    $is_liked = $stmt->fetchColumn() > 0;
} catch (PDOException $e) {
}

// ============================================================
// 🔥 TRAITEMENT DU LIKE - AVEC LE VRAI NOM
// ============================================================
if (isset($_POST['toggle_like'])) {
    try {
        if ($is_liked) {
            // RETIRER LE LIKE
            $pdo->prepare("DELETE FROM video_likes WHERE id_video = ? AND id_utilisateur = ?")
                ->execute([$id_video, $user_id]);
            $is_liked = false;

            // Historique
            addHistoriqueAction($pdo, $user_id, 'suppression', 'like', $id_video, $nom_film, "Retrait de like sur \"$nom_film\" par $nom_utilisateur");
            showNotif('info', "Vous avez retiré votre like sur \"$nom_film\"");

        } else {
            // AJOUTER LE LIKE
            $pdo->prepare("INSERT INTO video_likes (id_video, id_utilisateur) VALUES (?, ?)")
                ->execute([$id_video, $user_id]);
            $is_liked = true;

            // Historique
            addHistoriqueAction($pdo, $user_id, 'ajout', 'like', $id_video, $nom_film, "Like sur \"$nom_film\" par $nom_utilisateur");
            showNotif('success', "Vous avez aimé \"$nom_film\"");

            // 🔥 NOTIFICATION ADMIN AVEC LE VRAI NOM (CORRIGÉE)
            $_SESSION['admin_notification'] = [
                'type' => 'like',
                'message' => "❤️ " . $nom_utilisateur . " a aimé \"" . $nom_film . "\"",
                'user' => $nom_utilisateur,
                'film' => $nom_film,
                'time' => date('H:i:s')
            ];
        }
    } catch (PDOException $e) {
        // Erreur silencieuse
    }
    header('Location: watch.php?id_video=' . $id_video);
    exit;
}

// Compter les likes
$total_likes = 0;
try {
    $like_count = $pdo->prepare("SELECT COUNT(*) FROM video_likes WHERE id_video = ?");
    $like_count->execute([$id_video]);
    $total_likes = $like_count->fetchColumn();
} catch (PDOException $e) {
}

// ============================================================
// 🔥 TRAITEMENT DU COMMENTAIRE - AVEC LE VRAI NOM
// ============================================================
// Créer la table feedback si elle n'existe pas
try {
    $pdo->query("SELECT 1 FROM feedback LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
        id_feedback INT AUTO_INCREMENT PRIMARY KEY,
        id_video INT NOT NULL,
        id_utilisateur INT NOT NULL,
        nom_utilisateur VARCHAR(100) NOT NULL,
        commentaire TEXT NOT NULL,
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

if (isset($_POST['ajouter_feedback']) && !empty($_POST['commentaire'])) {
    $commentaire = trim($_POST['commentaire']);

    try {
        $stmt = $pdo->prepare("INSERT INTO feedback (id_video, id_utilisateur, nom_utilisateur, commentaire) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_video, $user_id, $nom_utilisateur, $commentaire]);

        // Historique
        addHistoriqueAction($pdo, $user_id, 'ajout', 'commentaire', $id_video, $nom_film, $nom_utilisateur . " a commenté \"" . $nom_film . "\": " . $commentaire);
        showNotif('success', "Votre commentaire a été ajouté avec succès");

        // 🔥 NOTIFICATION ADMIN AVEC LE VRAI NOM (CORRIGÉE)
        $_SESSION['admin_notification'] = [
            'type' => 'commentaire',
            'message' => "💬 " . $nom_utilisateur . " a commenté \"" . $nom_film . "\"",
            'user' => $nom_utilisateur,
            'film' => $nom_film,
            'commentaire' => $commentaire,
            'time' => date('H:i:s')
        ];

        header('Location: watch.php?id_video=' . $id_video . '&feedback=success');
        exit;
    } catch (PDOException $e) {
        $error_feedback = "Erreur lors de l'ajout du commentaire";
    }
}

$feedbacks = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM feedback WHERE id_video = ? ORDER BY date_creation DESC");
    $stmt->execute([$id_video]);
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

$first_feedbacks = array_slice($feedbacks, 0, 2);
$remaining_feedbacks = array_slice($feedbacks, 2);
$has_more = count($feedbacks) > 2;

/* =========================
   SUGGESTIONS
========================= */
$suggested = $pdo->prepare("
    SELECT f.*, v.id_video, v.thumbnail, v.views
    FROM film f
    INNER JOIN video_film v ON f.id_film = v.id_film
    WHERE f.id_categorie = ? AND v.id_video != ? 
    AND f.is_deleted = 0 AND f.est_archive = 0
    ORDER BY v.views DESC LIMIT 6
");
$suggested->execute([$video['id_categorie'] ?? 0, $id_video]);
$suggested_videos = $suggested->fetchAll();

$feedback_success = isset($_GET['feedback']) && $_GET['feedback'] === 'success';
$video_url = "https://" . $_SERVER['HTTP_HOST'] . "/film_web_dy/user/watch.php?id_video=" . $id_video;
$video_title = urlencode($video['nom_film']);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-lang-fr="<?= htmlspecialchars($video['nom_film']) ?> - BERTIN MOVIE"
        data-lang-en="<?= htmlspecialchars($video['nom_film']) ?> - BERTIN MOVIE">
        <?= htmlspecialchars($video['nom_film']) ?> - BERTIN MOVIE
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/film_web_dy/user/css/style-user.css">
    <style>
        /* =========================================================
           BOUTON RETOUR STYLISÉ
           ========================================================= */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary, #64748b);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 16px;
            padding: 10px 18px;
            background: var(--bg-card, #ffffff);
            border-radius: 12px;
            border: 1px solid var(--border-color, #e2e8f0);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        body.dark-mode .btn-back {
            background: #1e293b;
            border-color: #334155;
            color: #94a3b8;
        }

        .btn-back:hover {
            color: #3b82f6;
            transform: translateX(-4px);
            border-color: #3b82f6;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.12);
        }

        body.dark-mode .btn-back:hover {
            color: #60a5fa;
            border-color: #3b82f6;
        }

        .btn-back i {
            font-size: 16px;
            transition: transform 0.3s ease;
        }

        .btn-back:hover i {
            transform: translateX(-4px);
        }

        /* =========================================================
           LIKE BLEU - STYLE INSTAGRAM EN BLEU
        ========================================================= */
        .watch-like-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: transparent;
            border: 2px solid #3b82f6;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: #3b82f6;
            font-family: inherit;
            position: relative;
            overflow: hidden;
        }

        .watch-like-btn:hover {
            transform: scale(1.05);
            background: rgba(59, 130, 246, 0.05);
        }

        .watch-like-btn:active {
            transform: scale(0.92);
        }

        .watch-like-btn.liked {
            background: #2563eb;
            border-color: #2563eb;
            color: #ffffff;
            animation: likePop 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .watch-like-btn.liked:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            transform: scale(1.05);
        }

        .watch-like-btn.liked:active {
            transform: scale(0.92);
        }

        .watch-like-btn.liked i {
            animation: heartBeat 0.5s ease;
        }

        @keyframes likePop {
            0% {
                transform: scale(1);
            }

            30% {
                transform: scale(1.25);
            }

            60% {
                transform: scale(0.9);
            }

            100% {
                transform: scale(1);
            }
        }

        @keyframes heartBeat {
            0% {
                transform: scale(1);
            }

            25% {
                transform: scale(1.4);
            }

            50% {
                transform: scale(0.9);
            }

            75% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
            }
        }

        .watch-like-btn i {
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .watch-like-btn .like-text {
            font-size: 15px;
        }

        .watch-like-btn .like-ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.3);
            transform: scale(0);
            animation: ripple 0.6s ease-out;
            pointer-events: none;
        }

        .watch-like-btn.liked .like-ripple {
            background: rgba(255, 255, 255, 0.3);
        }

        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        .watch-like-count {
            font-size: 15px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .watch-like-count i {
            color: #3b82f6;
        }

        .watch-like-count .count-number {
            font-weight: 600;
            color: var(--text-primary);
        }

        body.dark-mode .watch-like-btn {
            border-color: #60a5fa;
            color: #60a5fa;
        }

        body.dark-mode .watch-like-btn:hover {
            background: rgba(96, 165, 250, 0.1);
        }

        body.dark-mode .watch-like-btn.liked {
            background: #3b82f6;
            border-color: #3b82f6;
            color: #ffffff;
        }

        body.dark-mode .watch-like-btn.liked:hover {
            background: #2563eb;
            border-color: #2563eb;
        }

        body.dark-mode .watch-like-count i {
            color: #60a5fa;
        }

        /* =========================================================
           AUTRES STYLES
        ========================================================= */
        .watch-container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .watch-player {
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .watch-player video {
            width: 100%;
            display: block;
            max-height: 500px;
            background: #000;
        }

        .watch-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .watch-card .watch-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .watch-info-line {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 12px;
        }

        .watch-info-line .badge {
            background: rgba(59, 130, 246, 0.15);
            color: var(--primary-light);
            padding: 3px 12px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 600;
        }

        .watch-info-line .item {
            color: var(--text-secondary);
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .watch-btn-details {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }

        .watch-btn-details:hover {
            background: var(--primary-light);
            color: #fff;
            border-color: var(--primary-light);
        }

        .watch-actions {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .share-wrapper {
            position: relative;
            display: inline-block;
        }

        .share-btn-main {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: transparent;
            border: 2px solid var(--border-color);
            border-radius: 50px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-primary);
            font-family: inherit;
        }

        .share-btn-main:hover {
            background: var(--border-color);
        }

        .share-btn-main i {
            font-size: 16px;
        }

        .share-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 8px;
            min-width: 220px;
            box-shadow: var(--shadow-lg);
            z-index: 100;
            animation: slideDown 0.2s ease;
        }

        .share-dropdown.show {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .share-dropdown .share-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            border: none;
            background: transparent;
            width: 100%;
            font-family: inherit;
        }

        .share-dropdown .share-item:hover {
            background: var(--bg-body);
        }

        .share-dropdown .share-item i {
            width: 20px;
            font-size: 16px;
            text-align: center;
        }

        .share-dropdown .share-item .icon-fb {
            color: #1877f2;
        }

        .share-dropdown .share-item .icon-twitter {
            color: #000000;
        }

        body.dark-mode .share-dropdown .share-item .icon-twitter {
            color: #ffffff;
        }

        .share-dropdown .share-item .icon-wa {
            color: #25d366;
        }

        .share-dropdown .share-item .icon-insta {
            color: #d6249f;
        }

        .share-dropdown .share-item .icon-copy {
            color: var(--text-secondary);
        }

        .share-dropdown .share-item .icon-copy.copied {
            color: #22c55e;
        }

        .share-dropdown .divider {
            height: 1px;
            background: var(--border-color);
            margin: 4px 8px;
        }

        /* =========================================================
           FEEDBACK
        ========================================================= */
        .watch-feedback {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .watch-feedback .fb-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
        }

        .watch-feedback .fb-title i {
            color: var(--primary-light);
        }

        .watch-feedback .fb-title span {
            font-weight: 400;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .fb-success {
            background: #dcfce7;
            color: #166534;
            padding: 10px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .fb-error {
            background: #fee2e2;
            color: #dc2626;
            padding: 10px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .watch-fb-form {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            align-items: stretch;
        }

        .watch-fb-form textarea {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 15px;
            resize: vertical;
            min-height: 80px;
            transition: var(--transition);
            background: var(--bg-input);
            color: var(--text-primary);
        }

        .watch-fb-form textarea:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .watch-fb-form textarea::placeholder {
            color: #94a3b8;
            font-size: 14px;
        }

        .watch-fb-form button {
            padding: 12px 28px;
            background: var(--primary-light);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 14px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 80px;
        }

        .watch-fb-form button:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .watch-fb-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .watch-fb-item {
            padding: 14px 16px;
            background: var(--bg-body);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .watch-fb-item .fb-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            flex-wrap: wrap;
            gap: 4px;
        }

        .watch-fb-item .fb-user {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .watch-fb-item .fb-user i {
            color: var(--primary-light);
        }

        .watch-fb-item .fb-date {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .watch-fb-item .fb-text {
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.6;
            word-wrap: break-word;
        }

        .watch-fb-empty {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-secondary);
        }

        .watch-fb-empty i {
            font-size: 40px;
            display: block;
            margin-bottom: 12px;
            color: var(--border-color);
        }

        .see-more-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: transparent;
            color: var(--primary-light);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 14px;
            font-family: inherit;
            margin-top: 12px;
        }

        .see-more-btn:hover {
            background: var(--primary-light);
            color: #fff;
            border-color: var(--primary-light);
        }

        .hidden-feedbacks {
            display: none;
        }

        .hidden-feedbacks.show {
            display: block;
        }

        /* =========================================================
           SUGGESTIONS & MODAL
        ========================================================= */
        .watch-suggestions {
            margin-top: 24px;
        }

        .watch-suggestions .suggested-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .watch-suggestions .suggested-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .watch-suggestions .suggested-header h3 i {
            color: var(--primary-light);
        }

        .watch-suggestions .suggested-header .view-all {
            color: var(--primary-light);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .watch-suggestions .suggested-header .view-all:hover {
            color: #2563eb;
            transform: translateX(3px);
        }

        .suggested-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: var(--bg-card);
            border-radius: 12px;
            max-width: 480px;
            width: 92%;
            max-height: 80vh;
            overflow-y: auto;
            padding: 28px 32px;
            box-shadow: var(--shadow);
            animation: modalIn 0.25s ease;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .modal .modal-header h3 i {
            color: var(--primary-light);
        }

        .modal .modal-header .close {
            background: none;
            border: none;
            font-size: 28px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            line-height: 1;
            padding: 0 4px;
        }

        .modal .modal-header .close:hover {
            color: #ef4444;
            transform: rotate(90deg);
        }

        .modal .modal-body {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .modal .modal-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .modal .modal-row .label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 100px;
            flex-shrink: 0;
            font-size: 13px;
        }

        .modal .modal-row .value {
            color: var(--text-primary);
            flex: 1;
            font-size: 13px;
        }

        .modal .modal-row .value .empty {
            color: var(--text-secondary);
            font-style: italic;
        }

        .modal .modal-row .value .actor-tag {
            display: inline-block;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-light);
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin: 2px 4px 2px 0;
        }

        .modal .modal-row:last-child {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .suggested-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 14px;
            }

            .watch-card {
                padding: 18px;
            }

            .watch-card .watch-title {
                font-size: 20px;
            }

            .watch-like-btn {
                padding: 8px 18px;
                font-size: 14px;
            }

            .share-btn-main {
                padding: 8px 18px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .suggested-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .watch-card {
                padding: 14px;
            }

            .watch-card .watch-title {
                font-size: 17px;
            }

            .watch-fb-form {
                flex-direction: column;
            }

            .watch-fb-form textarea {
                min-height: 60px;
            }

            .watch-fb-form button {
                min-height: 44px;
                justify-content: center;
            }

            .modal {
                padding: 18px 20px;
            }

            .modal .modal-row {
                flex-direction: column;
                gap: 2px;
            }

            .modal .modal-row .label {
                width: 100%;
            }

            .share-dropdown {
                right: -40px;
                min-width: 200px;
            }

            .watch-like-btn {
                padding: 6px 14px;
                font-size: 13px;
            }

            .share-btn-main {
                padding: 6px 14px;
                font-size: 13px;
            }

            .watch-like-btn i {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/menu-user.php'; ?>
    <?php renderNotifications(); ?>

    <div class="page-content">
        <div class="watch-container">

            <a href="video.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                <span data-lang-fr="Retour aux vidéos" data-lang-en="Back to videos">Retour aux vidéos</span>
            </a>

            <div class="watch-player">
                <video controls playsinline preload="metadata" <?php if (!empty($video['thumbnail'])): ?>poster="<?= htmlspecialchars($video['thumbnail']) ?>" <?php endif; ?>>
                    <source src="<?= htmlspecialchars($play_url) ?>" <?= $play_mime !== '' ? 'type="' . $play_mime . '"' : '' ?>>
                    <span data-lang-fr="Votre navigateur ne supporte pas la lecture vidéo."
                        data-lang-en="Your browser does not support video playback.">
                        Votre navigateur ne supporte pas la lecture vidéo.
                    </span>
                </video>
            </div>

            <div class="watch-card">
                <h1 class="watch-title">
                    <?= htmlspecialchars($video['nom_film']) ?>
                </h1>

                <div class="watch-info-line">
                    <span class="badge">
                        <?= htmlspecialchars($video['nom_categorie'] ?? 'Non catégorisé') ?>
                    </span>
                    <span class="item"><i class="fas fa-eye"></i>
                        <?= number_format($video['views'] ?? 0, 0, ',', ' ') ?>
                    </span>
                    <span class="item"><i class="fas fa-clock"></i>
                        <?= htmlspecialchars($video['duree'] ?? '00:00:00') ?>
                    </span>
                    <button class="watch-btn-details" onclick="openModal()">
                        <i class="fas fa-info-circle"></i> <span data-lang-fr="Détails"
                            data-lang-en="Details">Détails</span>
                    </button>
                </div>

                <div class="watch-actions">
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="toggle_like" class="watch-like-btn <?= $is_liked ? 'liked' : '' ?>"
                            id="likeBtn">
                            <i class="<?= $is_liked ? 'fas fa-heart' : 'far fa-heart' ?>"></i>
                            <span class="like-text">
                                <?php if ($is_liked): ?>
                                    <span data-lang-fr="Aimé" data-lang-en="Liked">Aimé</span>
                                <?php else: ?>
                                    <span data-lang-fr="J'aime" data-lang-en="Like">J'aime</span>
                                <?php endif; ?>
                            </span>
                        </button>
                    </form>
                    <span class="watch-like-count">
                        <i class="fas fa-heart"></i>
                        <span class="count-number">
                            <?= number_format($total_likes ?? 0, 0, ',', ' ') ?>
                        </span>
                    </span>

                    <div class="share-wrapper">
                        <button class="share-btn-main" onclick="toggleShare()">
                            <i class="fas fa-share-alt"></i> <span data-lang-fr="Partager"
                                data-lang-en="Share">Partager</span>
                        </button>
                        <div class="share-dropdown" id="shareDropdown">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($video_url) ?>"
                                target="_blank" class="share-item">
                                <i class="fab fa-facebook-f icon-fb"></i> Facebook
                            </a>
                            <!-- X (Twitter) avec lien réel -->
                            <a href="https://x.com/intent/post?text=<?= $video_title ?>&url=<?= urlencode($video_url) ?>"
                                target="_blank" class="share-item">
                                <i class="fa-brands fa-x-twitter icon-twitter"></i> X (Twitter)
                            </a>
                            <a href="https://wa.me/?text=<?= urlencode($video_title . ' - ' . $video_url) ?>"
                                target="_blank" class="share-item">
                                <i class="fab fa-whatsapp icon-wa"></i> WhatsApp
                            </a>
                            <a href="https://www.instagram.com/" target="_blank" class="share-item">
                                <i class="fab fa-instagram icon-insta"></i> Instagram
                            </a>
                            <div class="divider"></div>
                            <button class="share-item" onclick="copyLink()">
                                <i class="fas fa-link icon-copy" id="copyIcon"></i> <span data-lang-fr="Copier le lien"
                                    data-lang-en="Copy link">Copier le lien</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL DETAILS -->
            <div class="modal-overlay" id="modalDetails" onclick="closeModalOutside(event)">
                <div class="modal">
                    <div class="modal-header">
                        <h3><i class="fas fa-film"></i> <span data-lang-fr="Détails du film"
                                data-lang-en="Movie details">Détails du film</span></h3>
                        <button class="close" onclick="closeModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-row"><span class="label"><span data-lang-fr="Titre"
                                    data-lang-en="Title">Titre</span></span><span class="value">
                                <?= htmlspecialchars($video['nom_film']) ?>
                            </span></div>
                        <div class="modal-row"><span class="label"><span data-lang-fr="Catégorie"
                                    data-lang-en="Category">Catégorie</span></span><span class="value">
                                <?= htmlspecialchars($video['nom_categorie'] ?? 'Non renseignée') ?>
                            </span></div>
                        <div class="modal-row"><span class="label"><span data-lang-fr="Durée"
                                    data-lang-en="Duration">Durée</span></span><span class="value">
                                <?= htmlspecialchars($video['duree'] ?? 'Non renseignée') ?>
                            </span></div>
                        <div class="modal-row"><span class="label"><span data-lang-fr="Date sortie"
                                    data-lang-en="Release date">Date sortie</span></span><span class="value">
                                <?= !empty($video['date_sortie']) ? date('d/m/Y', strtotime($video['date_sortie'])) : '<span class="empty">Non renseignée</span>' ?>
                            </span></div>
                        <div class="modal-row"><span class="label"><span data-lang-fr="Réalisateur"
                                    data-lang-en="Director">Réalisateur</span></span><span class="value">
                                <?= (!empty($video['nom_realisateur']) && !empty($video['prenom_realisateur'])) ? htmlspecialchars($video['prenom_realisateur'] . ' ' . $video['nom_realisateur']) : '<span class="empty">Non renseigné</span>' ?>
                            </span></div>
                        <div class="modal-row"><span class="label"><span data-lang-fr="Langue"
                                    data-lang-en="Language">Langue</span></span><span class="value">
                                <?= !empty($video['nom_langue']) ? htmlspecialchars($video['nom_langue']) : '<span class="empty">Non renseignée</span>' ?>
                            </span></div>
                        <div class="modal-row"><span class="label"><span data-lang-fr="Studio(s)"
                                    data-lang-en="Studio(s)">Studio(s)</span></span><span class="value">
                                <?= !empty($video['studios']) ? htmlspecialchars($video['studios']) : '<span class="empty">Non renseigné</span>' ?>
                            </span></div>
                        <div class="modal-row" style="border-bottom:none;"><span class="label"><span
                                    data-lang-fr="Acteurs" data-lang-en="Actors">Acteurs</span></span><span
                                class="value"><span class="empty">Non renseigné</span></span></div>
                    </div>
                </div>
            </div>

            <!-- FEEDBACK -->
            <div class="watch-feedback">
                <div class="fb-title">
                    <i class="fas fa-comment"></i> <span data-lang-fr="Feedback" data-lang-en="Feedback">Feedback</span>
                    <span>(
                        <?= count($feedbacks) ?>)
                    </span>
                </div>

                <?php if ($feedback_success): ?>
                    <div class="fb-success"><i class="fas fa-check-circle"></i> <span data-lang-fr="Feedback ajouté !"
                            data-lang-en="Feedback added!">Feedback ajouté !</span></div>
                <?php endif; ?>
                <?php if (isset($error_feedback)): ?>
                    <div class="fb-error"><i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error_feedback) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="watch-fb-form">
                    <textarea name="commentaire" placeholder="Partagez votre avis sur ce film..." required
                        maxlength="500" data-lang-fr-placeholder="Partagez votre avis sur ce film..."
                        data-lang-en-placeholder="Share your opinion about this movie..."></textarea>
                    <button type="submit" name="ajouter_feedback"><i class="fas fa-paper-plane"></i> <span
                            data-lang-fr="Envoyer" data-lang-en="Send">Envoyer</span></button>
                </form>

                <div class="watch-fb-list">
                    <?php foreach ($first_feedbacks as $fb): ?>
                        <div class="watch-fb-item">
                            <div class="fb-head">
                                <span class="fb-user"><i class="fas fa-user-circle"></i>
                                    <?= htmlspecialchars($fb['nom_utilisateur']) ?>
                                </span>
                                <span class="fb-date">
                                    <?php $date = new DateTime($fb['date_creation']);
                                    echo $date->format('d/m/Y H:i'); ?>
                                </span>
                            </div>
                            <div class="fb-text">
                                <?= nl2br(htmlspecialchars($fb['commentaire'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($has_more): ?>
                        <div class="hidden-feedbacks" id="moreFeedbacks">
                            <?php foreach ($remaining_feedbacks as $fb): ?>
                                <div class="watch-fb-item" style="margin-top:10px;">
                                    <div class="fb-head">
                                        <span class="fb-user"><i class="fas fa-user-circle"></i>
                                            <?= htmlspecialchars($fb['nom_utilisateur']) ?>
                                        </span>
                                        <span class="fb-date">
                                            <?php $date = new DateTime($fb['date_creation']);
                                            echo $date->format('d/m/Y H:i'); ?>
                                        </span>
                                    </div>
                                    <div class="fb-text">
                                        <?= nl2br(htmlspecialchars($fb['commentaire'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="see-more-btn" onclick="toggleFeedbacks()" id="seeMoreBtn">
                            <i class="fas fa-chevron-down"></i> <span data-lang-fr="Voir plus" data-lang-en="See more">Voir
                                plus</span> (<span id="remainingCount">
                                <?= count($remaining_feedbacks) ?>
                            </span> <span data-lang-fr="commentaires" data-lang-en="comments">commentaires</span>)
                        </button>
                    <?php endif; ?>

                    <?php if (count($feedbacks) == 0): ?>
                        <div class="watch-fb-empty">
                            <i class="fas fa-comment-slash"></i>
                            <p><span data-lang-fr="Aucun feedback." data-lang-en="No feedback.">Aucun feedback.</span><br>
                                <span data-lang-fr="Soyez le premier à donner votre avis !"
                                    data-lang-en="Be the first to share your opinion!">Soyez le premier à donner votre avis
                                    !</span>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SUGGESTIONS -->
            <?php if (count($suggested_videos) > 0): ?>
                <div class="watch-suggestions">
                    <div class="suggested-header">
                        <h3><i class="fas fa-film"></i> <span data-lang-fr="Vidéos suggérées"
                                data-lang-en="Suggested videos">Vidéos suggérées</span></h3>
                        <a href="video.php" class="view-all"><span data-lang-fr="Voir tout" data-lang-en="See all">Voir
                                tout</span> <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="suggested-grid">
                        <?php foreach ($suggested_videos as $sug): ?>
                            <a href="watch.php?id_video=<?= $sug['id_video'] ?>" class="movie-card">
                                <div class="thumb">
                                    <img src="<?= htmlspecialchars($sug['thumbnail'] ?? 'assets/default-thumbnail.jpg') ?>"
                                        alt="<?= htmlspecialchars($sug['nom_film']) ?>">
                                </div>
                                <div class="info">
                                    <h4>
                                        <?= htmlspecialchars($sug['nom_film']) ?>
                                    </h4>
                                    <div class="meta">
                                        <span><i class="fas fa-eye"></i>
                                            <?= number_format($sug['views'] ?? 0, 0, ',', ' ') ?> <span data-lang-fr="vues"
                                                data-lang-en="views">vues</span>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        // ============================================================
        // LIKE - EFFET RIPPLE
        // ============================================================
        document.getElementById('likeBtn').addEventListener('click', function (e) {
            var ripple = document.createElement('span');
            ripple.classList.add('like-ripple');
            var rect = this.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            this.appendChild(ripple);
            setTimeout(function () { ripple.remove(); }, 600);
        });

        // ============================================================
        // MODAL DETAILS
        // ============================================================
        function openModal() {
            document.getElementById('modalDetails').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            document.getElementById('modalDetails').classList.remove('active');
            document.body.style.overflow = '';
        }
        function closeModalOutside(e) {
            if (e.target === e.currentTarget) closeModal();
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });

        // ============================================================
        // FEEDBACK SCROLL
        // ============================================================
        <?php if ($feedback_success): ?>
            document.querySelector('.watch-feedback').scrollIntoView({ behavior: 'smooth', block: 'start' });
        <?php endif; ?>

        // ============================================================
        // TOGGLE FEEDBACKS (VOIR PLUS)
        // ============================================================
        function toggleFeedbacks() {
            var more = document.getElementById('moreFeedbacks');
            var btn = document.getElementById('seeMoreBtn');
            if (more.classList.contains('show')) {
                more.classList.remove('show');
                btn.innerHTML = '<i class="fas fa-chevron-down"></i> Voir plus (<span id="remainingCount"><?= count($remaining_feedbacks) ?></span> commentaires)';
            } else {
                more.classList.add('show');
                btn.innerHTML = '<i class="fas fa-chevron-up"></i> Voir moins';
            }
        }

        // ============================================================
        // TOGGLE SHARE DROPDOWN
        // ============================================================
        function toggleShare() {
            var dropdown = document.getElementById('shareDropdown');
            dropdown.classList.toggle('show');
        }
        document.addEventListener('click', function (e) {
            var wrapper = document.querySelector('.share-wrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                document.getElementById('shareDropdown').classList.remove('show');
            }
        });

        // ============================================================
        // COPY LINK
        // ============================================================
        function copyLink() {
            var url = '<?= $video_url ?>';
            var icon = document.getElementById('copyIcon');
            navigator.clipboard.writeText(url).then(function () {
                icon.className = 'fas fa-check icon-copy copied';
                setTimeout(function () { icon.className = 'fas fa-link icon-copy'; }, 2000);
            }).catch(function () {
                var input = document.createElement('input');
                input.value = url;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                icon.className = 'fas fa-check icon-copy copied';
                setTimeout(function () { icon.className = 'fas fa-link icon-copy'; }, 2000);
            });
            document.getElementById('shareDropdown').classList.remove('show');
        }

        // ============================================================
        // DARK MODE
        // ============================================================
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
        }

        // ============================================================
        // TRADUCTION - GESTION DES PLACEHOLDERS
        // ============================================================
        function applyLanguageForPlaceholders(lang) {
            document.querySelectorAll('[data-lang-fr-placeholder][data-lang-en-placeholder]').forEach(el => {
                const placeholder = el.getAttribute('data-lang-' + lang + '-placeholder');
                if (placeholder !== null) {
                    el.placeholder = placeholder;
                }
            });
        }

        // Surcharger la fonction applyLanguage pour inclure les placeholders
        const originalApplyLanguage = window.applyLanguage;
        window.applyLanguage = function (lang) {
            if (typeof originalApplyLanguage === 'function') {
                originalApplyLanguage(lang);
            }
            applyLanguageForPlaceholders(lang);
        };

        // Initialiser les placeholders au chargement
        document.addEventListener('DOMContentLoaded', function () {
            const currentLang = localStorage.getItem('lang') || 'fr';
            applyLanguageForPlaceholders(currentLang);
        });
    </script>

</body>

</html>