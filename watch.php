<?php
// Cette page n'est ni dans /admin/ ni dans /user/ : on indique à session_boot.php qu'elle appartient
// à la zone admin, pour qu'elle utilise le même cookie de session (BM_ADMIN) que les pages admin.
define('BM_SESSION_NAME', 'BM_ADMIN');
require_once __DIR__ . '/includes/session_boot.php';

require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/includes/update_session.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/b2_stream.php';

// 🔥 Vérifier si le fichier notification.php existe avant de l'inclure
$notification_file = __DIR__ . '/../includes/notification.php';
if (file_exists($notification_file)) {
    require_once $notification_file;
} else {
    // Définir des fonctions factices pour éviter les erreurs
    if (!function_exists('addHistoriqueAction')) {
        function addHistoriqueAction($pdo, $id_utilisateur, $type_action, $entite, $entite_id = null, $nom_entite = null, $details = null)
        {
            return true;
        }
    }
    if (!function_exists('showNotif')) {
        function showNotif($type, $message, $title = null)
        {
            $_SESSION['notif'] = ['type' => $type, 'message' => $message, 'title' => $title ?? 'Notification'];
        }
    }
    if (!function_exists('renderNotifications')) {
        function renderNotifications()
        {
            // Affichage simple si le fichier n'existe pas
            if (isset($_SESSION['notif'])) {
                echo '<div class="alert alert-' . $_SESSION['notif']['type'] . '">' . htmlspecialchars($_SESSION['notif']['message']) . '</div>';
                unset($_SESSION['notif']);
            }
        }
    }
}

// Mettre à jour la session
if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

// 🔥 RÉCUPÉRER LE VRAI NOM DE L'UTILISATEUR
$user_id = $_SESSION['user_id'] ?? 0;
$nom_utilisateur = 'Utilisateur';
if ($user_id) {
    try {
        $stmt_user = $pdo->prepare("SELECT prenom, nom, nom_utilisateur FROM utilisateur WHERE id_utilisateur = ?");
        $stmt_user->execute([$user_id]);
        $user_data = $stmt_user->fetch();

        if (!empty($user_data['prenom']) && !empty($user_data['nom'])) {
            $nom_utilisateur = trim($user_data['prenom'] . ' ' . $user_data['nom']);
        } elseif (!empty($user_data['prenom'])) {
            $nom_utilisateur = $user_data['prenom'];
        } elseif (!empty($user_data['nom'])) {
            $nom_utilisateur = $user_data['nom'];
        } else {
            $nom_utilisateur = $user_data['nom_utilisateur'] ?? 'Utilisateur';
        }
        $_SESSION['user_nom'] = $nom_utilisateur;
    } catch (PDOException $e) {
        // Ignorer
    }
}

/* =========================
   VÉRIFICATION DE L'ID VIDÉO
========================= */
if (!isset($_GET['id_video']) || empty($_GET['id_video'])) {
    header('Location: video.php');
    exit;
}

$id_video = (int) $_GET['id_video'];

/* =========================
   RÉCUPÉRATION DES INFORMATIONS DU FILM ET DE LA VIDÉO
========================= */
$sql = "SELECT f.*, 
               v.id_video, 
               v.url_video, 
               v.thumbnail, 
               v.date_upload, 
               v.views,
               c.nom_categorie, 
               c.id_categorie,
               r.nom AS nom_realisateur,
               r.prenom AS prenom_realisateur,
               r.nationalite,
               l.nom_langue,
               GROUP_CONCAT(DISTINCT CONCAT(a.prenom, ' ', a.nom) SEPARATOR ', ') AS acteurs,
               GROUP_CONCAT(DISTINCT s.nom_studio SEPARATOR ', ') AS studios
        FROM film f
        LEFT JOIN video_film v ON f.id_film = v.id_film
        LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
        LEFT JOIN realisateur r ON f.id_realisateur = r.id_realisateur
        LEFT JOIN langue l ON f.id_langue = l.id_langue
        LEFT JOIN film_acteur fa ON f.id_film = fa.id_film
        LEFT JOIN acteur a ON fa.id_acteur = a.id_acteur
        LEFT JOIN film_studio fs ON f.id_film = fs.id_film
        LEFT JOIN studio s ON fs.id_studio = s.id_studio
        WHERE v.id_video = :id_video AND f.is_deleted = 0
        GROUP BY f.id_film";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id_video' => $id_video]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video || empty($video['url_video'])) {
    header('Location: video.php?error=video_not_found');
    exit;
}

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
        $session_id = session_id();
        $pdo->prepare("INSERT INTO video_views (id_video, session_id) VALUES (?, ?)")->execute([$id_video, $session_id]);
    } catch (PDOException $e) {
    }
}

/* =========================
   SYSTÈME DE LIKE - AVEC VÉRIFICATION DE LA TABLE
========================= */
// 🔥 Vérifier si la table video_likes existe et a la bonne structure
try {
    $pdo->query("SELECT 1 FROM video_likes LIMIT 1");
} catch (PDOException $e) {
    // La table n'existe pas, la créer avec id_utilisateur
    $pdo->exec("CREATE TABLE IF NOT EXISTS video_likes (
        id_like INT AUTO_INCREMENT PRIMARY KEY,
        id_video INT NOT NULL,
        id_utilisateur INT NOT NULL,
        date_like TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (id_video, id_utilisateur)
    )");
}

// 🔥 Vérifier si l'utilisateur a déjà liké
$is_liked = false;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM video_likes WHERE id_video = ? AND id_utilisateur = ?");
        $stmt->execute([$id_video, $_SESSION['user_id']]);
        $is_liked = $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        $is_liked = false;
    }
}

// 🔥 TRAITEMENT DU LIKE
if (isset($_POST['toggle_like'])) {
    $nom_film = $video['nom_film'];
    $user_id = $_SESSION['user_id'] ?? 0;

    try {
        if ($is_liked) {
            // RETIRER LE LIKE
            $pdo->prepare("DELETE FROM video_likes WHERE id_video = ? AND id_utilisateur = ?")
                ->execute([$id_video, $user_id]);
            $is_liked = false;

            // Historique (si la fonction existe)
            if (function_exists('addHistoriqueAction')) {
                addHistoriqueAction($pdo, $user_id, 'suppression', 'like', $id_video, $nom_film, "Retrait de like sur \"$nom_film\" par $nom_utilisateur");
                showNotif('info', "Vous avez retiré votre like sur \"$nom_film\"");
            }

        } else {
            // AJOUTER LE LIKE
            $pdo->prepare("INSERT INTO video_likes (id_video, id_utilisateur) VALUES (?, ?)")
                ->execute([$id_video, $user_id]);
            $is_liked = true;

            // Historique (si la fonction existe)
            if (function_exists('addHistoriqueAction')) {
                addHistoriqueAction($pdo, $user_id, 'ajout', 'like', $id_video, $nom_film, "Like sur \"$nom_film\" par $nom_utilisateur");
                showNotif('success', "Vous avez aimé \"$nom_film\"");
            }

            // 🔥 NOTIFICATION ADMIN EN TEMPS RÉEL
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

/* =========================
   NOMBRE DE LIKES
========================= */
$total_likes = 0;
try {
    $like_count = $pdo->prepare("SELECT COUNT(*) FROM video_likes WHERE id_video = ?");
    $like_count->execute([$id_video]);
    $total_likes = $like_count->fetchColumn();
} catch (PDOException $e) {
}

/* =========================
   SYSTÈME DE FEEDBACK
========================= */
if (isset($_POST['ajouter_feedback']) && !empty($_POST['commentaire'])) {
    $commentaire = trim($_POST['commentaire']);
    $utilisateur_id = $_SESSION['user_id'] ?? null;
    $nom_utilisateur_fb = $_SESSION['user_nom'] ?? $_SESSION['username'] ?? 'Anonyme';
    $nom_film = $video['nom_film'];

    if ($utilisateur_id) {
        try {
            // Vérifier si la table feedback existe
            $pdo->query("SELECT 1 FROM feedback LIMIT 1");
        } catch (PDOException $e) {
            // Créer la table feedback
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS feedback (
                    id_feedback INT AUTO_INCREMENT PRIMARY KEY,
                    id_video INT NOT NULL,
                    id_utilisateur INT NOT NULL,
                    nom_utilisateur VARCHAR(100) NOT NULL,
                    commentaire TEXT NOT NULL,
                    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO feedback (id_video, id_utilisateur, nom_utilisateur, commentaire) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_video, $utilisateur_id, $nom_utilisateur_fb, $commentaire]);

            if (function_exists('addHistoriqueAction')) {
                addHistoriqueAction($pdo, $utilisateur_id, 'ajout', 'commentaire', $id_video, $nom_film, $nom_utilisateur_fb . " a commenté \"" . $nom_film . "\": " . $commentaire);
                showNotif('success', "Votre commentaire a été ajouté avec succès");
            }

            // 🔥 NOTIFICATION ADMIN EN TEMPS RÉEL
            $_SESSION['admin_notification'] = [
                'type' => 'commentaire',
                'message' => "💬 " . $nom_utilisateur_fb . " a commenté \"" . $nom_film . "\"",
                'user' => $nom_utilisateur_fb,
                'film' => $nom_film,
                'commentaire' => $commentaire,
                'time' => date('H:i:s')
            ];

            header('Location: watch.php?id_video=' . $id_video . '&feedback=success');
            exit;
        } catch (PDOException $e) {
            $error_feedback = "Erreur lors de l'ajout du commentaire";
        }
    } else {
        $error_feedback = "Vous devez être connecté pour commenter";
    }
}

/* =========================
   RÉCUPÉRATION DES FEEDBACKS
========================= */
$feedbacks = [];
try {
    $pdo->query("SELECT 1 FROM feedback LIMIT 1");
    $stmt = $pdo->prepare("
        SELECT f.*, u.nom_utilisateur, u.prenom, u.nom 
        FROM feedback f
        LEFT JOIN utilisateur u ON f.id_utilisateur = u.id_utilisateur
        WHERE f.id_video = ? 
        ORDER BY f.date_creation DESC 
        LIMIT 50
    ");
    $stmt->execute([$id_video]);
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

/* =========================
   VIDÉOS SUGGÉRÉES
========================= */
$suggested_sql = "SELECT f.*, v.id_video, v.thumbnail, v.views
                  FROM film f
                  LEFT JOIN video_film v ON f.id_film = v.id_film
                  WHERE f.id_categorie = :category 
                  AND v.id_video != :id_video 
                  AND f.is_deleted = 0 AND f.est_archive = 0
                  AND v.id_video IS NOT NULL
                  AND v.url_video IS NOT NULL
                  ORDER BY v.views DESC, v.date_upload DESC
                  LIMIT 6";

$suggested_stmt = $pdo->prepare($suggested_sql);
$suggested_stmt->execute([
    ':category' => $video['id_categorie'] ?? 0,
    ':id_video' => $id_video
]);
$suggested_videos = $suggested_stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($suggested_videos) == 0) {
    $suggested_sql = "SELECT f.*, v.id_video, v.thumbnail, v.views
                      FROM film f
                      LEFT JOIN video_film v ON f.id_film = v.id_film
                      WHERE v.id_video != :id_video 
                      AND f.is_deleted = 0 AND f.est_archive = 0
                      AND v.id_video IS NOT NULL
                      AND v.url_video IS NOT NULL
                      ORDER BY v.date_upload DESC
                      LIMIT 6";
    $suggested_stmt = $pdo->prepare($suggested_sql);
    $suggested_stmt->execute([':id_video' => $id_video]);
    $suggested_videos = $suggested_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$feedback_success = isset($_GET['feedback']) && $_GET['feedback'] === 'success';
$video_url = "https://" . $_SERVER['HTTP_HOST'] . "/film_web_dy/user/watch.php?id_video=" . $id_video;
$video_title = urlencode($video['nom_film']);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo htmlspecialchars($video['nom_film']); ?> - Bertin Movie
    </title>
    <link rel="stylesheet" href="/film_web_dy/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .watch-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 8px 20px 20px;
            margin-top: 76px;
        }

        .watch-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: 0.3s;
        }

        .watch-back:hover {
            transform: translateX(-3px);
        }

        .watch-player {
            background: #000;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .watch-player video {
            width: 100%;
            display: block;
            max-height: 500px;
            background: #000;
        }

        .watch-card {
            background: #ffffff;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .watch-card .watch-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #0f172a;
        }

        .watch-info-line {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 8px;
        }

        .watch-info-line .badge {
            background: #dbeafe;
            color: #3b82f6;
            padding: 2px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
        }

        .watch-info-line .item {
            color: #64748b;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .watch-info-line .item i {
            font-size: 13px;
            color: #94a3b8;
        }

        .watch-btn-details {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 12px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            transition: 0.3s;
            font-family: inherit;
            white-space: nowrap;
        }

        .watch-btn-details:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .watch-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .watch-like-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: transparent;
            border: 2px solid #3b82f6;
            border-radius: 25px;
            font-size: 14px;
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
            font-size: 14px;
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
            font-size: 14px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .watch-like-count i {
            color: #3b82f6;
            font-size: 14px;
        }

        .watch-like-count .count-number {
            font-weight: 600;
            color: #0f172a;
        }

        body.dark-mode .watch-like-count .count-number {
            color: #e2e8f0;
        }

        .watch-feedback {
            background: #ffffff;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .watch-feedback .fb-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .watch-feedback .fb-title span {
            font-weight: 400;
            color: #64748b;
            font-size: 14px;
        }

        .fb-success {
            background: #dcfce7;
            color: #166534;
            padding: 8px 14px;
            border-radius: 6px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .fb-error {
            background: #fee2e2;
            color: #dc2626;
            padding: 8px 14px;
            border-radius: 6px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .watch-fb-form {
            display: flex;
            gap: 12px;
            margin-bottom: 14px;
            align-items: stretch;
        }

        .watch-fb-form textarea {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
            max-height: 160px;
            transition: 0.3s;
            background: #f8fafc;
            line-height: 1.5;
        }

        .watch-fb-form textarea:focus {
            outline: none;
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .watch-fb-form textarea::placeholder {
            color: #94a3b8;
            font-size: 14px;
        }

        .watch-fb-form button {
            padding: 12px 20px;
            background: #3b82f6;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            font-size: 14px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
            min-height: 80px;
        }

        .watch-fb-form button:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .watch-fb-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .watch-fb-item {
            padding: 10px 14px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .watch-fb-item.hidden-fb {
            display: none;
        }

        .watch-fb-item .fb-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
            flex-wrap: wrap;
            gap: 4px;
        }

        .watch-fb-item .fb-user {
            font-weight: 600;
            font-size: 13px;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: 0.3s;
        }

        .watch-fb-item .fb-user:hover {
            color: #3b82f6;
        }

        .watch-fb-item .fb-user i {
            color: #3b82f6;
        }

        .watch-fb-item .fb-date {
            font-size: 11px;
            color: #94a3b8;
        }

        .watch-fb-item .fb-text {
            font-size: 14px;
            color: #0f172a;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .watch-fb-empty {
            text-align: center;
            padding: 20px 16px;
            color: #94a3b8;
        }

        .watch-fb-empty i {
            font-size: 32px;
            display: block;
            margin-bottom: 8px;
            color: #e2e8f0;
        }

        .fb-show-more-btn {
            display: block;
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #3b82f6;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: 0.3s;
            text-align: center;
            font-family: inherit;
        }

        .fb-show-more-btn:hover {
            background: #dbeafe;
            border-color: #3b82f6;
        }

        .fb-show-more-btn i {
            margin-right: 6px;
            transition: 0.3s;
        }

        .fb-show-more-btn:hover i {
            transform: translateY(2px);
        }

        body.dark-mode .fb-show-more-btn {
            background: #1e293b;
            border-color: #334155;
            color: #60a5fa;
        }

        body.dark-mode .fb-show-more-btn:hover {
            background: #0f172a;
            border-color: #3b82f6;
        }

        .watch-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .watch-modal-overlay.active {
            display: flex;
        }

        .watch-modal {
            background: #ffffff;
            border-radius: 12px;
            max-width: 480px;
            width: 92%;
            max-height: 80vh;
            overflow-y: auto;
            padding: 24px 28px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: watchModalIn 0.25s ease;
        }

        @keyframes watchModalIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .watch-modal .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }

        .watch-modal .modal-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .watch-modal .modal-header .close {
            background: none;
            border: none;
            font-size: 26px;
            color: #94a3b8;
            cursor: pointer;
            transition: 0.3s;
            line-height: 1;
            padding: 0 4px;
        }

        .watch-modal .modal-header .close:hover {
            color: #ef4444;
            transform: rotate(90deg);
        }

        .watch-modal .modal-body {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .watch-modal .modal-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .watch-modal .modal-row .label {
            font-weight: 600;
            color: #64748b;
            width: 100px;
            flex-shrink: 0;
            font-size: 13px;
        }

        .watch-modal .modal-row .value {
            color: #0f172a;
            flex: 1;
            font-size: 13px;
        }

        .watch-modal .modal-row .value .empty {
            color: #94a3b8;
            font-style: italic;
        }

        .watch-modal .modal-row .value .actor-tag {
            display: inline-block;
            background: #dbeafe;
            color: #3b82f6;
            padding: 1px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 500;
            margin: 2px 3px 2px 0;
        }

        .watch-modal .modal-row:last-child {
            border-bottom: none;
        }

        .watch-suggestions {
            margin-top: 12px;
        }

        .watch-suggestions .suggested-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .watch-suggestions .suggested-header h3 {
            font-size: 17px;
            font-weight: 700;
        }

        .watch-suggestions .suggested-header .view-all {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .watch-suggestions .suggested-header .view-all:hover {
            color: #2563eb;
            transform: translateX(3px);
        }

        .watch-suggestions .suggested-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .watch-suggestions .suggested-card {
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .watch-suggestions .suggested-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border-color: #3b82f6;
        }

        .watch-suggestions .suggested-card .thumb {
            position: relative;
            background: #f1f5f9;
            aspect-ratio: 16/9;
            overflow: hidden;
        }

        .watch-suggestions .suggested-card .thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .watch-suggestions .suggested-card .thumb .dur {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 1px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .watch-suggestions .suggested-card .info {
            padding: 10px 12px;
        }

        .watch-suggestions .suggested-card .info h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 3px;
            color: #0f172a;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.3;
        }

        .watch-suggestions .suggested-card .info .views {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .watch-no-video {
            padding: 40px 20px;
            text-align: center;
            color: #94a3b8;
        }

        .watch-no-video .icon {
            font-size: 48px;
            display: block;
            margin-bottom: 12px;
        }

        .watch-no-video h3 {
            color: #ffffff;
            margin-bottom: 6px;
            font-size: 20px;
        }

        .watch-no-video .btn-upload {
            display: inline-block;
            margin-top: 12px;
            padding: 8px 24px;
            background: #f59e0b;
            color: #ffffff;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            font-size: 14px;
        }

        .watch-no-video .btn-upload:hover {
            background: #d97706;
        }

        body.dark-mode .watch-card {
            background: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }

        body.dark-mode .watch-card .watch-title {
            color: #e2e8f0;
        }

        body.dark-mode .watch-feedback {
            background: #1e293b;
            border-color: #334155;
        }

        body.dark-mode .watch-fb-item {
            background: #0f172a;
            border-color: #334155;
        }

        body.dark-mode .watch-fb-item .fb-text {
            color: #e2e8f0;
        }

        body.dark-mode .watch-fb-item .fb-user {
            color: #e2e8f0;
        }

        body.dark-mode .watch-fb-item .fb-user:hover {
            color: #60a5fa;
        }

        body.dark-mode .watch-suggestions .suggested-card {
            background: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }

        body.dark-mode .watch-suggestions .suggested-card .info h4 {
            color: #e2e8f0;
        }

        body.dark-mode .watch-modal {
            background: #1e293b;
            color: #e2e8f0;
        }

        body.dark-mode .watch-modal .modal-header {
            border-color: #334155;
        }

        body.dark-mode .watch-modal .modal-header h3 {
            color: #e2e8f0;
        }

        body.dark-mode .watch-modal .modal-row {
            border-color: #334155;
        }

        body.dark-mode .watch-modal .modal-row .label {
            color: #94a3b8;
        }

        body.dark-mode .watch-modal .modal-row .value {
            color: #e2e8f0;
        }

        body.dark-mode .watch-fb-form textarea {
            background: #0f172a;
            border-color: #334155;
            color: #e2e8f0;
        }

        body.dark-mode .watch-fb-form textarea::placeholder {
            color: #64748b;
        }

        body.dark-mode .watch-btn-details {
            background: #1e293b;
            border-color: #334155;
            color: #94a3b8;
        }

        body.dark-mode .watch-btn-details:hover {
            background: #334155;
            color: #e2e8f0;
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

        body.dark-mode .watch-info-line {
            border-color: #334155;
        }

        body.dark-mode .watch-info-line .item {
            color: #94a3b8;
        }

        @media (max-width: 768px) {
            .watch-container {
                padding: 6px 12px 16px;
                margin-top: 68px;
            }

            .watch-suggestions .suggested-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .watch-card .watch-title {
                font-size: 19px;
            }

            .watch-like-btn {
                padding: 6px 16px;
                font-size: 13px;
            }

            .watch-like-btn i {
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .watch-container {
                padding: 4px 10px 14px;
                margin-top: 62px;
            }

            .watch-card {
                padding: 12px 14px;
            }

            .watch-card .watch-title {
                font-size: 17px;
            }

            .watch-info-line {
                gap: 4px 8px;
                padding-bottom: 6px;
            }

            .watch-info-line .badge {
                font-size: 11px;
                padding: 2px 10px;
            }

            .watch-info-line .item {
                font-size: 12px;
            }

            .watch-btn-details {
                font-size: 11px;
                padding: 2px 10px;
            }

            .watch-like-btn {
                padding: 5px 14px;
                font-size: 12px;
                border-width: 2px;
            }

            .watch-like-btn i {
                font-size: 15px;
            }

            .watch-like-btn .like-text {
                font-size: 12px;
            }

            .watch-feedback {
                padding: 12px 14px;
            }

            .watch-feedback .fb-title {
                font-size: 14px;
            }

            .watch-fb-form {
                flex-direction: column;
                gap: 8px;
            }

            .watch-fb-form textarea {
                min-height: 60px;
                font-size: 13px;
                padding: 10px 14px;
            }

            .watch-fb-form button {
                min-height: 40px;
                justify-content: center;
                padding: 8px 16px;
                font-size: 13px;
            }

            .watch-suggestions .suggested-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .watch-modal {
                padding: 16px 18px;
                max-width: 95%;
            }

            .watch-modal .modal-row {
                flex-direction: column;
                gap: 2px;
                padding: 4px 0;
            }

            .watch-modal .modal-row .label {
                width: 100%;
                font-size: 12px;
            }

            .watch-modal .modal-row .value {
                font-size: 13px;
            }
        }

        /* =========================================================
           DESIGN IDENTIQUE À LA PAGE UTILISATEUR
        ========================================================= */
        .watch-container {
            padding: 8px 20px 30px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all .3s ease;
            margin-bottom: 16px;
            padding: 10px 18px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
        }

        .btn-back:hover {
            color: #3b82f6;
            transform: translateX(-4px);
            border-color: #3b82f6;
            box-shadow: 0 4px 16px rgba(59, 130, 246, .12);
        }

        .btn-back i {
            font-size: 16px;
            transition: transform .3s ease;
        }

        .btn-back:hover i {
            transform: translateX(-4px);
        }

        body.dark-mode .btn-back {
            background: #1e293b;
            border-color: #334155;
            color: #94a3b8;
        }

        body.dark-mode .btn-back:hover {
            color: #60a5fa;
            border-color: #3b82f6;
        }

        .watch-player {
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: none;
        }

        .watch-card,
        .watch-feedback {
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: none;
        }

        .watch-card .watch-title {
            font-size: 24px;
            margin-bottom: 12px;
        }

        .watch-info-line {
            gap: 10px 16px;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }

        .watch-info-line .badge {
            background: rgba(59, 130, 246, .15);
            padding: 3px 12px;
            font-size: 13px;
        }

        .watch-btn-details {
            padding: 3px 12px;
        }

        .watch-actions {
            gap: 16px;
            margin-top: 8px;
        }

        .watch-like-btn {
            padding: 10px 24px;
            border-radius: 50px;
            font-size: 15px;
        }

        .watch-like-btn .like-text {
            font-size: 15px;
        }

        .watch-like-count {
            font-size: 15px;
            gap: 6px;
        }

        .watch-feedback .fb-title {
            font-size: 18px;
            margin-bottom: 16px;
            gap: 10px;
        }

        .watch-fb-form textarea {
            padding: 14px 18px;
            font-size: 15px;
        }

        .watch-fb-form button {
            padding: 12px 28px;
        }

        .watch-suggestions {
            margin-top: 24px;
        }

        .watch-suggestions .suggested-header h3 {
            font-size: 20px;
        }

        .watch-suggestions .suggested-grid {
            gap: 20px;
        }

        /* Partager */
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
            border: 2px solid #e2e8f0;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all .3s ease;
            color: #0f172a;
            font-family: inherit;
        }

        .share-btn-main:hover {
            background: #e2e8f0;
        }

        body.dark-mode .share-btn-main {
            border-color: #334155;
            color: #e2e8f0;
        }

        body.dark-mode .share-btn-main:hover {
            background: #334155;
        }

        .share-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 8px;
            min-width: 220px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .12);
            z-index: 100;
            animation: shareDown .2s ease;
        }

        body.dark-mode .share-dropdown {
            background: #1e293b;
            border-color: #334155;
        }

        .share-dropdown.show {
            display: block;
        }

        @keyframes shareDown {
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
            transition: all .2s ease;
            text-decoration: none;
            color: #0f172a;
            font-size: 14px;
            font-weight: 500;
            border: none;
            background: transparent;
            width: 100%;
            font-family: inherit;
        }

        body.dark-mode .share-dropdown .share-item {
            color: #e2e8f0;
        }

        .share-dropdown .share-item:hover {
            background: #f1f5f9;
        }

        body.dark-mode .share-dropdown .share-item:hover {
            background: #0f172a;
        }

        .share-dropdown .share-item i {
            width: 20px;
            font-size: 16px;
            text-align: center;
        }

        .share-dropdown .icon-fb {
            color: #1877f2;
        }

        .share-dropdown .icon-twitter {
            color: #000;
        }

        body.dark-mode .share-dropdown .icon-twitter {
            color: #fff;
        }

        .share-dropdown .icon-wa {
            color: #25d366;
        }

        .share-dropdown .icon-copy {
            color: #64748b;
        }

        .share-dropdown .icon-copy.copied {
            color: #22c55e;
        }

        .share-dropdown .divider {
            height: 1px;
            background: #e2e8f0;
            margin: 4px 8px;
        }

        body.dark-mode .share-dropdown .divider {
            background: #334155;
        }

        @media (max-width: 768px) {

            .watch-card,
            .watch-feedback {
                padding: 18px;
            }

            .watch-card .watch-title {
                font-size: 20px;
            }

            .watch-like-btn,
            .share-btn-main {
                padding: 8px 18px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {

            .watch-card,
            .watch-feedback {
                padding: 14px;
            }

            .watch-card .watch-title {
                font-size: 17px;
            }

            .watch-like-btn,
            .share-btn-main {
                padding: 6px 14px;
                font-size: 13px;
            }

            .share-dropdown {
                right: -40px;
                min-width: 200px;
            }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/menu.php'; ?>
    <?php renderNotifications(); ?>

    <div class="watch-container">

        <a href="/film_web_dy/video.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            <span>Retour aux vidéos</span>
        </a>

        <div class="watch-player">
            <video id="mainVideo" controls playsinline preload="metadata" <?php echo !empty($video['url_video']) ? '' : 'style="display:none;"'; ?> <?php if (!empty($video['thumbnail'])): ?>poster="
                <?php echo htmlspecialchars($video['thumbnail']); ?>" <?php endif; ?>>
                <source src="<?php echo htmlspecialchars($play_url); ?>" <?php echo $play_mime !== '' ? 'type="' . $play_mime . '"' : ''; ?>>
                Votre navigateur ne supporte pas la lecture vidéo.
            </video>
            <?php if (empty($video['url_video'])): ?>
                <div class="watch-no-video">
                    <span class="icon">🎬</span>
                    <h3>Vidéo non disponible</h3>
                    <p>Cette vidéo n'a pas encore été uploadée.</p>
                    <a href="/film_web_dy/admin/upload_video.php?film_id=<?php echo $video['id_film']; ?>"
                        class="btn-upload">
                        <i class="fas fa-upload"></i> Uploader
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="watch-card">
            <h1 class="watch-title">
                <?php echo htmlspecialchars($video['nom_film']); ?>
            </h1>

            <div class="watch-info-line">
                <span class="badge">
                    <?php echo htmlspecialchars($video['nom_categorie'] ?? 'Non catégorisé'); ?>
                </span>
                <span class="item"><i class="fas fa-eye"></i>
                    <?php echo number_format($video['views'] ?? 0, 0, ',', ' '); ?>
                </span>
                <span class="item"><i class="fas fa-clock"></i>
                    <?php echo htmlspecialchars($video['duree'] ?? '00:00:00'); ?>
                </span>
                <button class="watch-btn-details" onclick="openModal()">
                    <i class="fas fa-info-circle"></i> Détails
                </button>
            </div>

            <div class="watch-actions">
                <form method="POST" style="display:inline;">
                    <button type="submit" name="toggle_like"
                        class="watch-like-btn <?php echo $is_liked ? 'liked' : ''; ?>">
                        <i class="<?php echo $is_liked ? 'fas fa-heart' : 'far fa-heart'; ?>"></i>
                        <span class="like-text">
                            <?php echo $is_liked ? 'Aimé' : 'J\'aime'; ?>
                        </span>
                    </button>
                </form>
                <span class="watch-like-count">
                    <i class="fas fa-heart"></i>
                    <span class="count-number">
                        <?php echo number_format($total_likes ?? 0, 0, ',', ' '); ?>
                    </span>
                </span>

                <div class="share-wrapper">
                    <button type="button" class="share-btn-main" onclick="toggleShare()">
                        <i class="fas fa-share-alt"></i> <span>Partager</span>
                    </button>
                    <div class="share-dropdown" id="shareDropdown">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($video_url); ?>"
                            target="_blank" rel="noopener" class="share-item">
                            <i class="fab fa-facebook-f icon-fb"></i> Facebook
                        </a>
                        <a href="https://x.com/intent/post?text=<?php echo $video_title; ?>&url=<?php echo urlencode($video_url); ?>"
                            target="_blank" rel="noopener" class="share-item">
                            <i class="fa-brands fa-x-twitter icon-twitter"></i> X (Twitter)
                        </a>
                        <a href="https://wa.me/?text=<?php echo urlencode(urldecode($video_title) . ' - ' . $video_url); ?>"
                            target="_blank" rel="noopener" class="share-item">
                            <i class="fab fa-whatsapp icon-wa"></i> WhatsApp
                        </a>
                        <div class="divider"></div>
                        <button type="button" class="share-item" onclick="copyLink()">
                            <i class="fas fa-link icon-copy" id="copyIcon"></i> <span>Copier le lien</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="watch-modal-overlay" id="modalDetails" onclick="closeModalOutside(event)">
            <div class="watch-modal">
                <div class="modal-header">
                    <h3><i class="fas fa-film" style="color:#3b82f6;"></i> Détails</h3>
                    <button class="close" onclick="closeModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="modal-row"><span class="label">Titre</span><span class="value">
                            <?php echo htmlspecialchars($video['nom_film']); ?>
                        </span></div>
                    <div class="modal-row"><span class="label">Catégorie</span><span class="value">
                            <?php echo htmlspecialchars($video['nom_categorie'] ?? 'Non renseignée'); ?>
                        </span>
                    </div>
                    <div class="modal-row"><span class="label">Durée</span><span class="value">
                            <?php echo htmlspecialchars($video['duree'] ?? 'Non renseignée'); ?>
                        </span>
                    </div>
                    <div class="modal-row"><span class="label">Date sortie</span><span class="value">
                            <?php if (!empty($video['date_sortie'])) {
                                echo date('d/m/Y', strtotime($video['date_sortie']));
                            } else {
                                echo '<span class="empty">Non renseignée</span>';
                            } ?>
                        </span>
                    </div>
                    <div class="modal-row"><span class="label">Réalisateur</span><span class="value">
                            <?php if (!empty($video['nom_realisateur']) && !empty($video['prenom_realisateur'])) {
                                echo htmlspecialchars($video['prenom_realisateur'] . ' ' . $video['nom_realisateur']);
                                if (!empty($video['nationalite'])) {
                                    echo ' <span style="font-size:11px;color:#94a3b8;">(' . htmlspecialchars($video['nationalite']) . ')</span>';
                                }
                            } else {
                                echo '<span class="empty">Non renseigné</span>';
                            } ?>
                        </span>
                    </div>
                    <div class="modal-row"><span class="label">Langue</span><span class="value">
                            <?php if (!empty($video['nom_langue'])) {
                                echo htmlspecialchars($video['nom_langue']);
                            } else {
                                echo '<span class="empty">Non renseignée</span>';
                            } ?>
                        </span>
                    </div>
                    <div class="modal-row"><span class="label">Studio(s)</span><span class="value">
                            <?php if (!empty($video['studios'])) {
                                echo htmlspecialchars($video['studios']);
                            } else {
                                echo '<span class="empty">Non renseigné</span>';
                            } ?>
                        </span>
                    </div>
                    <div class="modal-row" style="border-bottom:none;"><span class="label">Acteurs</span><span
                            class="value">
                            <?php if (!empty($video['acteurs'])) {
                                $acteurs = explode(', ', $video['acteurs']);
                                foreach ($acteurs as $acteur) {
                                    echo '<span class="actor-tag">' . htmlspecialchars($acteur) . '</span>';
                                }
                            } else {
                                echo '<span class="empty">Non renseigné</span>';
                            } ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="watch-feedback">
            <div class="fb-title">
                <i class="fas fa-comment" style="color:#3b82f6;"></i>
                Feedback <span>(
                    <?php echo count($feedbacks); ?>)
                </span>
            </div>

            <?php if ($feedback_success): ?>
                <div class="fb-success"><i class="fas fa-check-circle"></i> Feedback ajouté !</div>
            <?php endif; ?>
            <?php if (isset($error_feedback)): ?>
                <div class="fb-error"><i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_feedback); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="watch-fb-form">
                <textarea name="commentaire" placeholder="Écrire un feedback..." required maxlength="500"></textarea>
                <button type="submit" name="ajouter_feedback"><i class="fas fa-paper-plane"></i> Envoyer</button>
            </form>

            <div class="watch-fb-list">
                <?php if (count($feedbacks) > 0): ?>
                    <?php
                    $show_count = 2;
                    $total_fb = count($feedbacks);
                    $has_more = $total_fb > $show_count;
                    ?>
                    <?php foreach ($feedbacks as $index => $fb):
                        if (!empty($fb['prenom']) || !empty($fb['nom'])) {
                            $display_name = trim(($fb['prenom'] ?? '') . ' ' . ($fb['nom'] ?? ''));
                        } else {
                            $display_name = $fb['nom_utilisateur'] ?? 'Anonyme';
                        }
                        $user_id = $fb['id_utilisateur'] ?? 0;
                        ?>
                        <div class="watch-fb-item <?php echo $index >= $show_count ? 'hidden-fb' : ''; ?>"
                            data-index="<?php echo $index; ?>">
                            <div class="fb-head">
                                <?php if ($user_id > 0): ?>
                                    <a href="/film_web_dy/admin/utilisateur_detail.php?id=<?php echo $user_id; ?>" class="fb-user"
                                        target="_blank">
                                        <i class="fas fa-user-circle"></i>
                                        <?php echo htmlspecialchars($display_name); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="fb-user"><i class="fas fa-user-circle"></i>
                                        <?php echo htmlspecialchars($display_name); ?>
                                    </span>
                                <?php endif; ?>
                                <span class="fb-date">
                                    <?php $date = new DateTime($fb['date_creation']);
                                    echo $date->format('d/m/Y H:i'); ?>
                                </span>
                            </div>
                            <div class="fb-text">
                                <?php echo nl2br(htmlspecialchars($fb['commentaire'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($has_more): ?>
                        <button class="fb-show-more-btn" id="showMoreFbBtn" onclick="toggleFeedbacks()">
                            <i class="fas fa-chevron-down"></i> Voir plus (
                            <?php echo $total_fb - $show_count; ?>
                            supplémentaires)
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="watch-fb-empty">
                        <i class="fas fa-comment-slash"></i>
                        <p>Aucun feedback.<br>Soyez le premier !</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (count($suggested_videos) > 0): ?>
            <div class="watch-suggestions">
                <div class="suggested-header">
                    <h3><i class="fas fa-film" style="color:#3b82f6;"></i> Suggestions</h3>
                    <a href="/film_web_dy/video.php" class="view-all">Voir tout <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="suggested-grid">
                    <?php foreach ($suggested_videos as $sug): ?>
                        <a href="watch.php?id_video=<?php echo $sug['id_video']; ?>" class="suggested-card">
                            <div class="thumb">
                                <img src="<?php echo htmlspecialchars($sug['thumbnail'] ?? 'assets/default-thumbnail.jpg'); ?>"
                                    alt="<?php echo htmlspecialchars($sug['nom_film']); ?>" loading="lazy">
                                <?php if (!empty($sug['duree'])): ?>
                                    <span class="dur">
                                        <?php echo htmlspecialchars($sug['duree']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="info">
                                <h4>
                                    <?php echo htmlspecialchars($sug['nom_film']); ?>
                                </h4>
                                <span class="views"><i class="fas fa-eye"></i>
                                    <?php echo number_format($sug['views'] ?? 0, 0, ',', ' '); ?> vues
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleFeedbacks() {
            var hiddenItems = document.querySelectorAll('.watch-fb-item.hidden-fb');
            var btn = document.getElementById('showMoreFbBtn');
            if (hiddenItems.length > 0) {
                hiddenItems.forEach(function (item) {
                    item.classList.remove('hidden-fb');
                });
                btn.innerHTML = '<i class="fas fa-chevron-up"></i> Voir moins';
            } else {
                var allItems = document.querySelectorAll('.watch-fb-item');
                var showCount = 2;
                var hidden = 0;
                allItems.forEach(function (item, index) {
                    if (index >= showCount) {
                        item.classList.add('hidden-fb');
                        hidden++;
                    }
                });
                btn.innerHTML = '<i class="fas fa-chevron-down"></i> Voir plus (' + hidden + ' supplémentaires)';
            }
        }

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

        <?php if ($feedback_success): ?>
            document.querySelector('.watch-feedback').scrollIntoView({ behavior: 'smooth', block: 'start' });
        <?php endif; ?>

        document.querySelector('.watch-fb-form textarea').addEventListener('input', function () {
            if (this.value.length > 500) this.value = this.value.substring(0, 500);
        });

        document.querySelectorAll('.watch-like-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                var ripple = document.createElement('span');
                ripple.classList.add('like-ripple');
                var rect = this.getBoundingClientRect();
                var size = Math.max(rect.width, rect.height);
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
                ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
                this.appendChild(ripple);
                setTimeout(function () {
                    ripple.remove();
                }, 600);
            });
        });

        // Partage
        function toggleShare() {
            document.getElementById('shareDropdown').classList.toggle('show');
        }
        document.addEventListener('click', function (e) {
            var wrapper = document.querySelector('.share-wrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                document.getElementById('shareDropdown').classList.remove('show');
            }
        });
        function copyLink() {
            var url = <?php echo json_encode($video_url); ?>;
            var icon = document.getElementById('copyIcon');
            function done() {
                icon.className = 'fas fa-check icon-copy copied';
                setTimeout(function () { icon.className = 'fas fa-link icon-copy'; }, 2000);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(done).catch(fallback);
            } else { fallback(); }
            function fallback() {
                var input = document.createElement('input');
                input.value = url;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                done();
            }
            document.getElementById('shareDropdown').classList.remove('show');
        }
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

        // Lecture automatique quand on arrive depuis une miniature
        <?php if (!empty($_GET['autoplay'])): ?>
                (function () {
                    var v = document.getElementById('mainVideo');
                    if (!v) return;
                    var p = v.play();
                    if (p && p.catch) {
                        p.catch(function () {          // son bloqué par le navigateur : on lance sans le son
                            v.muted = true;
                            v.play().catch(function () { });
                        });
                    }
                })();
        <?php endif; ?>

        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
        }
    </script>

</body>

</html>