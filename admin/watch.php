<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once 'includes/connexion.php';


require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Vérification de l'ID vidéo
if (!isset($_GET['id_video']) || empty($_GET['id_video'])) {
    header('Location: video.php');
    exit;
}

$id_video = (int) $_GET['id_video'];

// Récupération des infos de la vidéo
$sql = "SELECT f.*, v.id_video, v.url_video, v.thumbnail, v.date_upload, v.views,
               c.nom_categorie, c.id_categorie
        FROM film f
        JOIN video_film v ON f.id_film = v.id_film
        LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
        WHERE v.id_video = :id_video AND f.is_deleted = 0";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id_video' => $id_video]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    header('Location: video.php');
    exit;
}

// Incrémentation des vues (anti-double via session)
$view_key = 'viewed_' . $id_video;
if (!isset($_SESSION[$view_key])) {
    // Incrémenter dans la base
    $pdo->prepare("UPDATE video_film SET views = views + 1 WHERE id_video = ?")->execute([$id_video]);
    $_SESSION[$view_key] = true;

    // Log dans table video_views (optionnel)
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $pdo->prepare("INSERT INTO video_views (id_video, ip_address) VALUES (?, ?)")->execute([$id_video, $ip]);
    } catch (PDOException $e) {
        // Table peut ne pas exister
    }
}

// Système de likes (toggle via session)
$like_key = 'liked_' . $id_video;
$is_liked = isset($_SESSION[$like_key]) && $_SESSION[$like_key] === true;

if (isset($_POST['toggle_like'])) {
    if ($is_liked) {
        // Unlike
        $pdo->prepare("DELETE FROM video_likes WHERE id_video = ? AND ip_address = ?")->execute([$id_video, $_SERVER['REMOTE_ADDR']]);
        $_SESSION[$like_key] = false;
        $is_liked = false;
    } else {
        // Like
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        try {
            $pdo->prepare("INSERT INTO video_likes (id_video, ip_address) VALUES (?, ?)")->execute([$id_video, $ip]);
            $_SESSION[$like_key] = true;
            $is_liked = true;
        } catch (PDOException $e) {
            // Table peut ne pas exister
        }
    }
    // Recharger la page pour éviter resoumission
    header('Location: watch.php?id_video=' . $id_video);
    exit;
}

// Récupérer le nombre de likes
$like_count = $pdo->prepare("SELECT COUNT(*) FROM video_likes WHERE id_video = ?");
$like_count->execute([$id_video]);
$total_likes = $like_count->fetchColumn();

// Récupérer les vidéos suggérées (même catégorie)
$suggested_sql = "SELECT f.*, v.id_video, v.thumbnail, v.views
                  FROM film f
                  JOIN video_film v ON f.id_film = v.id_film
                  WHERE f.id_categorie = :category 
                  AND v.id_video != :id_video 
                  AND f.is_deleted = 0 AND f.est_archive = 0
                  ORDER BY v.views DESC, v.date_upload DESC
                  LIMIT 6";
$suggested_stmt = $pdo->prepare($suggested_sql);
$suggested_stmt->execute([':category' => $video['id_categorie'], ':id_video' => $id_video]);
$suggested_videos = $suggested_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo htmlspecialchars($video['nom_film']); ?> - FilmWeb
    </title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Video Player */
        .player-wrapper {
            background: #0f172a;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.15);
        }

        .player-wrapper video {
            width: 100%;
            display: block;
            max-height: 600px;
            background: #000;
        }

        /* Video Info */
        .video-info-section {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
        }

        .video-info-section h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #0f172a;
        }

        .video-details {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 15px;
        }

        .video-details .badge {
            background: #f1f5f9;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 13px;
            color: #1e3a8a;
            font-weight: 500;
        }

        .video-details .meta-item {
            color: #64748b;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Like Button */
        .like-section {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 10px;
        }

        .like-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 25px;
            background: #f1f5f9;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            color: #0f172a;
        }

        .like-btn:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        .like-btn.liked {
            background: #1e3a8a;
            color: #ffffff;
        }

        .like-btn.liked:hover {
            background: #0f172a;
        }

        .like-count {
            font-size: 15px;
            color: #64748b;
            font-weight: 500;
        }

        /* Suggested Videos */
        .suggested-section {
            margin-top: 30px;
        }

        .suggested-section h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #0f172a;
        }

        .suggested-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        @media (max-width: 768px) {
            .suggested-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .suggested-grid {
                grid-template-columns: 1fr;
            }

            .video-info-section h1 {
                font-size: 20px;
            }

            .video-details {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        .suggested-card {
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .suggested-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.1);
        }

        .suggested-card img {
            width: 100%;
            aspect-ratio: 16/9;
            object-fit: cover;
        }

        .suggested-card .info {
            padding: 12px;
        }

        .suggested-card .info h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #0f172a;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .suggested-card .info .views {
            font-size: 12px;
            color: #94a3b8;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #1e3a8a;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }

        .back-link:hover {
            color: #0f172a;
            transform: translateX(-3px);
        }
    </style>
</head>

<body>
    <!-- Menu -->
    <?php include_once 'includes/menu.php'; ?>

    <div class="container">
        <a href="video.php" class="back-link">← Retour à l'accueil</a>

        <!-- Player -->
        <div class="player-wrapper">
            <video controls>
                <source src="<?php echo htmlspecialchars($video['url_video']); ?>" type="video/mp4">
                Votre navigateur ne supporte pas la lecture vidéo.
            </video>
        </div>

        <!-- Video Info -->
        <div class="video-info-section">
            <h1>
                <?php echo htmlspecialchars($video['nom_film']); ?>
            </h1>

            <div class="video-details">
                <span class="badge">
                    <?php echo htmlspecialchars($video['nom_categorie'] ?? 'Non catégorisé'); ?>
                </span>
                <span class="meta-item">📅
                    <?php echo date('d/m/Y', strtotime($video['date_upload'])); ?>
                </span>
                <span class="meta-item">👁️
                    <?php echo number_format($video['views'] ?? 0, 0, ',', ' '); ?> vues
                </span>
                <span class="meta-item">⏱️
                    <?php echo htmlspecialchars($video['duree'] ?? '00:00'); ?>
                </span>
            </div>

            <!-- Like System -->
            <div class="like-section">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="toggle_like" class="like-btn <?php echo $is_liked ? 'liked' : ''; ?>">
                        <?php echo $is_liked ? '❤️' : '🤍'; ?>
                        <?php echo $is_liked ? 'J\'aime' : 'Aimer'; ?>
                    </button>
                </form>
                <span class="like-count">❤️
                    <?php echo number_format($total_likes ?? 0, 0, ',', ' '); ?> personne
                    <?php echo ($total_likes ?? 0) > 1 ? 's' : ''; ?>
                </span>
            </div>
        </div>

        <!-- Suggested Videos -->
        <?php if (count($suggested_videos) > 0): ?>
            <div class="suggested-section">
                <h2>📺 Vidéos suggérées</h2>
                <div class="suggested-grid">
                    <?php foreach ($suggested_videos as $suggested): ?>
                        <a href="watch.php?id_video=<?php echo $suggested['id_video']; ?>" class="suggested-card">
                            <img src="<?php echo htmlspecialchars($suggested['thumbnail'] ?? 'assets/default-thumbnail.jpg'); ?>"
                                alt="<?php echo htmlspecialchars($suggested['nom_film']); ?>" loading="lazy">
                            <div class="info">
                                <h4>
                                    <?php echo htmlspecialchars($suggested['nom_film']); ?>
                                </h4>
                                <span class="views">👁️
                                    <?php echo number_format($suggested['views'] ?? 0, 0, ',', ' '); ?> vues
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>