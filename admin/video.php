<?php
require_once __DIR__ . '/../includes/session_boot.php';

/* ✅ Connexion */
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/../includes/archive_lib.php';


require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../includes/auth_check.php';

/* =========================
   GET FILTERS
========================= */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? (int) $_GET['category'] : 0;
$show_all = isset($_GET['show_all']) ? (int) $_GET['show_all'] : 0;

/* =========================
   SQL - Afficher tous les films avec ou sans vidéo
========================= */
$sql = "SELECT f.*, 
               v.id_video, 
               v.url_video, 
               v.thumbnail, 
               v.date_upload,
               v.views,
               c.nom_categorie, 
               c.id_categorie,
               CASE WHEN v.id_video IS NOT NULL THEN 1 ELSE 0 END as has_video
        FROM film f
        LEFT JOIN video_film v ON f.id_film = v.id_film
        LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
        WHERE f.is_deleted = 0 AND f.est_archive = 0";

$params = [];

if (!empty($search)) {
    $sql .= " AND (f.nom_film LIKE :search OR f.titre LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($category > 0) {
    $sql .= " AND f.id_categorie = :category";
    $params[':category'] = $category;
}

// Filtrer pour montrer seulement les films avec vidéo (si show_all = 0)
if ($show_all == 0) {
    $sql .= " AND v.id_video IS NOT NULL";
}

$sort = film_sort_key($_GET['sort'] ?? '', 'video');   // liste fixe de tris autorisés
$sql .= " ORDER BY " . film_sort_sql($sort, 'video');

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$films = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   CATEGORIES
========================= */
$categories = $pdo->query("SELECT id_categorie, nom_categorie FROM categorie ORDER BY nom_categorie")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   STATS
========================= */
$total_films = count($films);
$total_with_video = 0;
foreach ($films as $f) {
    if ($f['has_video'])
        $total_with_video++;
}
$total_without_video = $total_films - $total_with_video;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - FilmWeb</title>
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

        /* Header */
        .header {
            background: #0f172a;
            padding: 20px 0;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
        }

        .header h1 span {
            color: #60a5fa;
        }

        .header-stats {
            color: #94a3b8;
            font-size: 14px;
        }

        .header-stats strong {
            color: #ffffff;
        }

        /* Filters */
        .filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            background: #ffffff;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
        }

        .filters input[type="text"] {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background: #ffffff;
        }

        .filters input[type="text"]:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .filters select {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: #ffffff;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 150px;
        }

        .filters select:focus {
            outline: none;
            border-color: #1e3a8a;
        }

        .filters button {
            padding: 10px 25px;
            background: #1e3a8a;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filters button:hover {
            background: #0f172a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
        }

        .filters .reset-btn {
            background: #e2e8f0;
            color: #0f172a;
        }

        .filters .reset-btn:hover {
            background: #cbd5e1;
            box-shadow: none;
        }

        .toggle-all-btn {
            background: #f1f5f9;
            color: #0f172a;
            padding: 10px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .toggle-all-btn:hover {
            background: #e2e8f0;
        }

        .toggle-all-btn.active {
            background: #1e3a8a;
            color: #ffffff;
            border-color: #1e3a8a;
        }

        /* Grid */
        .video-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        @media (max-width: 992px) {
            .video-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .video-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                align-items: stretch;
            }

            .filters {
                flex-direction: column;
            }

            .filters input[type="text"],
            .filters select,
            .filters button,
            .toggle-all-btn {
                width: 100%;
            }
        }

        /* Video Card */
        .video-card {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #f1f5f9;
        }

        .video-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.12);
        }

        .video-card.no-video {
            opacity: 0.7;
        }

        .video-card.no-video:hover {
            opacity: 1;
        }

        .thumbnail-wrapper {
            position: relative;
            background: #f1f5f9;
            overflow: hidden;
            aspect-ratio: 16/9;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .thumbnail-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .thumbnail-wrapper .no-video-placeholder {
            background: #f1f5f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: #94a3b8;
            font-size: 14px;
        }

        .thumbnail-wrapper .no-video-placeholder .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .video-card:hover .thumbnail-wrapper img {
            transform: scale(1.02);
        }

        .video-duration {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(15, 23, 42, 0.85);
            color: #ffffff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            backdrop-filter: blur(4px);
        }

        .video-status {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .video-status.available {
            background: rgba(34, 197, 94, 0.9);
            color: #ffffff;
        }

        .video-status.missing {
            background: rgba(239, 68, 68, 0.9);
            color: #ffffff;
        }

        .video-info {
            padding: 15px;
        }

        .video-info h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #0f172a;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .video-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 12px;
        }

        .video-category {
            background: #f1f5f9;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: #1e3a8a;
            font-weight: 500;
        }

        .watch-btn {
            display: inline-block;
            padding: 8px 20px;
            background: #1e3a8a;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            width: 100%;
            text-align: center;
        }

        .watch-btn:hover {
            background: #0f172a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
        }

        .watch-btn.disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .watch-btn.disabled:hover {
            transform: none;
            box-shadow: none;
        }

        /* No videos */
        .no-videos {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .no-videos h2 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #0f172a;
        }

        .no-videos p {
            font-size: 16px;
        }

        /* Admin Upload Link */
        .admin-upload-link {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 25px;
            background: #0f172a;
            color: #ffffff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .admin-upload-link:hover {
            background: #1e3a8a;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <!-- Menu -->
    <?php include_once __DIR__ . '/includes/menu.php'; ?>

    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <h1>🎬 Film<span>Web</span></h1>
            <div class="header-stats">
                <?php if ($show_all == 0): ?>
                    📺 <strong>
                        <?php echo $total_with_video; ?>
                    </strong>
                    vidéo
                    <?php echo $total_with_video > 1 ? 's' : ''; ?>
                    disponible
                    <?php echo $total_with_video > 1 ? 's' : ''; ?>
                    <?php if ($total_without_video > 0): ?>
                        · <a href="?show_all=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category > 0 ? '&category=' . $category : ''; ?>&sort=<?php echo urlencode($sort); ?>"
                            style="color: #60a5fa; text-decoration: none;">Voir aussi
                            <?php echo $total_without_video; ?>
                            film
                            <?php echo $total_without_video > 1 ? 's' : ''; ?> sans vidéo
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    🎬 <strong>
                        <?php echo $total_films; ?>
                    </strong> film
                    <?php echo $total_films > 1 ? 's' : ''; ?> au total
                    · <a href="?<?php echo !empty($search) ? 'search=' . urlencode($search) . '&' : ''; ?><?php echo $category > 0 ? 'category=' . $category . '&' : ''; ?>sort=<?php echo urlencode($sort); ?>&show_all=0"
                        style="color: #60a5fa; text-decoration: none;">Voir seulement les vidéos</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Filters -->
        <form method="GET" class="filters">
            <input type="hidden" name="show_all" value="<?php echo $show_all; ?>">
            <input type="text" name="search" placeholder="🔍 Rechercher un film..."
                value="<?php echo htmlspecialchars($search); ?>">

            <select name="category">
                <option value="0">Toutes les catégories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id_categorie']; ?>" <?php echo $category == $cat['id_categorie'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['nom_categorie']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="sort" aria-label="Trier par" title="Trier par" onchange="this.form.submit()">
                <?php echo film_sort_select_options($sort, 'video'); ?>
            </select>

            <button type="submit">Filtrer</button>
            <a href="video.php<?php echo $show_all == 1 ? '?show_all=1' : ''; ?>" class="reset-btn"
                style="padding: 10px 25px; background: #e2e8f0; color: #0f172a; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s;">Réinitialiser</a>
        </form>

        <!-- Video Grid -->
        <?php if (count($films) > 0): ?>
            <div class="video-grid">
                <?php foreach ($films as $film):
                    $has_video = $film['has_video'] == 1;
                    $thumbnail = !empty($film['thumbnail']) ? $film['thumbnail'] : 'assets/default-thumbnail.jpg';
                    $duration = $film['duree'] ?? '00:00';
                    $id_video = $film['id_video'] ?? 0;
                    ?>
                    <div class="video-card <?php echo !$has_video ? 'no-video' : ''; ?>">
                        <div class="thumbnail-wrapper">
                            <?php if ($has_video): ?>
                                <img src="<?php echo htmlspecialchars($thumbnail); ?>"
                                    alt="<?php echo htmlspecialchars($film['nom_film']); ?>" loading="lazy">
                                <span class="video-duration">
                                    <?php echo htmlspecialchars($duration); ?>
                                </span>

                            <?php else: ?>
                                <div class="no-video-placeholder">
                                    <div class="icon">🎬</div>
                                    <div>Aucune vidéo</div>
                                    <div style="font-size: 12px; margin-top: 5px; color: #cbd5e1;">
                                        <?php echo htmlspecialchars($film['nom_film']); ?>
                                    </div>
                                </div>
                                <span class="video-status missing">✗ Manquant</span>
                            <?php endif; ?>
                        </div>
                        <div class="video-info">
                            <h3>
                                <?php echo htmlspecialchars($film['nom_film']); ?>
                            </h3>
                            <div class="video-meta">
                                <span>
                                    <?php echo $has_video ? date('d/m/Y', strtotime($film['date_upload'] ?? 'now')) : 'Non uploadé'; ?>
                                </span>
                                <span class="video-category">
                                    <?php echo htmlspecialchars($film['nom_categorie'] ?? 'Non catégorisé'); ?>
                                </span>
                            </div>
                            <?php if ($has_video): ?>
                                <a href="watch.php?id_video=<?php echo $id_video; ?>" class="watch-btn">▶ Regarder</a>
                            <?php else: ?>
                                <a href="admin/upload_video.php?film_id=<?php echo $film['id_film']; ?>" class="watch-btn"
                                    style="background: #f59e0b;">📤 Uploader</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-videos">
                <h2>🎥 Aucun film trouvé</h2>
                <p>Essayez de modifier vos filtres ou ajoutez des films.</p>
                <a href="admin/upload_video.php" class="admin-upload-link">📤 Aller à l'upload</a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>