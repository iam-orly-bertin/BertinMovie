<?php
// Cette page n'est ni dans /admin/ ni dans /user/ : on indique à session_boot.php qu'elle appartient
// à la zone admin, pour qu'elle utilise le même cookie de session (BM_ADMIN) que les pages admin.
define('BM_SESSION_NAME', 'BM_ADMIN');
require_once __DIR__ . '/includes/session_boot.php';

/* ✅ Connexion */
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/includes/archive_lib.php';

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
        LEFT JOIN video_film v 
               ON f.id_film = v.id_film
        LEFT JOIN categorie c 
               ON f.id_categorie = c.id_categorie
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
$total_views = 0;
foreach ($films as $f) {
    $total_views += (int) ($f['views'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - FilmWeb</title>
    <link rel="stylesheet" href="/film_web_dy/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* =========================================================
   RESET & BASE
========================================================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            color: #0f172a;
            padding-top: 72px;
            min-height: 100vh;
        }

        /* =========================================================
   HEADER
========================================================= */
        .header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            padding: 20px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.3);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 50%, rgba(255, 255, 255, 0.05) 0%, transparent 60%);
            pointer-events: none;
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
            position: relative;
            z-index: 1;
        }

        .header h1 {
            color: #ffffff;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .header h1 span {
            background: linear-gradient(135deg, #60a5fa, #93c5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header h1 i {
            color: #3b82f6;
            margin-right: 8px;
        }

        .header-stats {
            color: #94a3b8;
            font-size: 14px;
        }

        .header-stats strong {
            color: #ffffff;
            font-weight: 700;
        }

        .header-stats a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .header-stats a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: #60a5fa;
            transition: width 0.3s ease;
        }

        .header-stats a:hover::after {
            width: 100%;
        }

        .header-stats a:hover {
            color: #93c5fd;
        }

        /* =========================================================
   CONTAINER
========================================================= */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 40px;
        }

        /* =========================================================
   BOUTON UPLOAD - EN HAUT À DROITE
========================================================= */
        .upload-top-right {
            position: fixed;
            top: 80px;
            right: 24px;
            z-index: 999;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.4);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            letter-spacing: 0.3px;
        }

        .upload-top-right .icon {
            font-size: 18px;
        }

        .upload-top-right::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
            border-radius: 12px;
        }

        .upload-top-right:hover::before {
            left: 100%;
        }

        .upload-top-right:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.5);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
        }

        .upload-top-right:active {
            transform: scale(0.97);
        }

        /* =========================================================
   FILTRES
========================================================= */
        .filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            background: #ffffff;
            padding: 16px 20px;
            border-radius: 14px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .filters:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1), 0 2px 10px rgba(15, 23, 42, 0.06);
        }

        .filters input[type="text"] {
            flex: 1;
            min-width: 200px;
            padding: 10px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8fafc;
            color: #0f172a;
        }

        .filters input[type="text"]:focus {
            outline: none;
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.08);
        }

        .filters input[type="text"]::placeholder {
            color: #94a3b8;
        }

        .filters select {
            padding: 10px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 160px;
            color: #0f172a;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
        }

        .filters select:focus {
            outline: none;
            border-color: #3b82f6;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.08);
        }

        .filters .btn-filter {
            padding: 10px 28px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .filters .btn-filter::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .filters .btn-filter:hover::before {
            left: 100%;
        }

        .filters .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4);
        }

        .filters .btn-filter:active {
            transform: scale(0.97);
        }

        .filters .reset-btn {
            background: #f1f5f9;
            color: #475569;
            padding: 10px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
            font-size: 14px;
        }

        .filters .reset-btn:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        /* =========================================================
   GRILLE VIDÉOS
========================================================= */
        .video-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
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
                text-align: center;
            }

            .filters {
                flex-direction: column;
                padding: 16px;
            }

            .filters input[type="text"],
            .filters select,
            .filters .btn-filter,
            .filters .reset-btn {
                width: 100%;
            }

            .upload-top-right {
                top: 70px;
                right: 10px;
                padding: 10px 16px;
                font-size: 12px;
                border-radius: 10px;
            }

            .upload-top-right .icon {
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .upload-top-right {
                top: 64px;
                right: 8px;
                padding: 8px 14px;
                font-size: 11px;
                border-radius: 8px;
            }

            .upload-top-right .icon {
                font-size: 14px;
            }
        }

        /* =========================================================
   CARTE VIDÉO
========================================================= */
        .video-card {
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
            transition: all 0.3s ease;
            border: 1px solid #f1f5f9;
            position: relative;
        }

        .video-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.6s ease;
            z-index: 2;
            pointer-events: none;
        }

        .video-card:hover::before {
            left: 100%;
        }

        .video-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12);
            border-color: #3b82f6;
        }

        .video-card.no-video {
            opacity: 0.7;
        }

        .video-card.no-video:hover {
            opacity: 1;
        }

        /* =========================================================
   THUMBNAIL
========================================================= */
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
            transition: transform 0.5s ease;
        }

        .video-card:hover .thumbnail-wrapper img {
            transform: scale(1.05);
        }

        .thumbnail-wrapper .no-video-placeholder {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: #94a3b8;
            font-size: 14px;
            gap: 8px;
        }

        .thumbnail-wrapper .no-video-placeholder .icon {
            font-size: 48px;
            color: #cbd5e1;
        }

        .thumbnail-wrapper .no-video-placeholder div:last-child {
            font-size: 12px;
            color: #94a3b8;
            max-width: 80%;
            text-align: center;
        }

        .video-duration {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(15, 23, 42, 0.85);
            color: #ffffff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            backdrop-filter: blur(4px);
            letter-spacing: 0.5px;
        }

        /* =========================================================
   INFO CARTE
========================================================= */
        .video-info {
            padding: 16px;
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
            transition: color 0.3s ease;
        }

        .video-card:hover .video-info h3 {
            color: #1e3a8a;
        }

        .video-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 4px;
        }

        .video-category {
            background: #eff6ff;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: #3b82f6;
            font-weight: 500;
            border: 1px solid #bfdbfe;
        }

        /* =========================================================
   BOUTONS D'ACTION
========================================================= */
        .watch-btn {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            width: 100%;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .watch-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .watch-btn:hover::before {
            left: 100%;
        }

        .watch-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }

        .watch-btn:active {
            transform: scale(0.97);
        }

        .watch-btn.upload-btn {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }

        .watch-btn.upload-btn:hover {
            box-shadow: 0 4px 16px rgba(34, 197, 94, 0.4);
            background: linear-gradient(135deg, #16a34a, #15803d);
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

        .watch-btn.disabled::before {
            display: none;
        }

        /* =========================================================
   AUCUN RÉSULTAT
========================================================= */
        .no-videos {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
            background: #ffffff;
            border-radius: 14px;
            border: 2px dashed #e2e8f0;
        }

        .no-videos h2 {
            font-size: 26px;
            margin-bottom: 12px;
            color: #0f172a;
            font-weight: 700;
        }

        .no-videos p {
            font-size: 16px;
            margin-bottom: 20px;
            color: #94a3b8;
        }

        .admin-upload-link {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #ffffff;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .admin-upload-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .admin-upload-link:hover::before {
            left: 100%;
        }

        .admin-upload-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.4);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }

        /* =========================================================
   SCROLLBAR PERSONNALISÉE
========================================================= */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #2563eb;
        }

        /* =========================================================
   ANIMATIONS
========================================================= */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .video-card {
            animation: fadeInUp 0.5s ease forwards;
        }

        .video-card:nth-child(1) {
            animation-delay: 0.05s;
        }

        .video-card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .video-card:nth-child(3) {
            animation-delay: 0.15s;
        }

        .video-card:nth-child(4) {
            animation-delay: 0.2s;
        }

        .video-card:nth-child(5) {
            animation-delay: 0.25s;
        }

        .video-card:nth-child(6) {
            animation-delay: 0.3s;
        }

        .video-card:nth-child(7) {
            animation-delay: 0.35s;
        }

        .video-card:nth-child(8) {
            animation-delay: 0.4s;
        }

        .video-card:nth-child(9) {
            animation-delay: 0.45s;
        }

        /* =========================================================
   RESPONSIVE - Ajustements supplémentaires
========================================================= */
        @media (max-width: 768px) {
            body {
                padding-top: 64px;
            }

            .header h1 {
                font-size: 20px;
            }

            .header-stats {
                font-size: 13px;
            }

            .video-info h3 {
                font-size: 15px;
            }

            .filters {
                padding: 14px;
                gap: 10px;
            }

            .filters input[type="text"],
            .filters select {
                padding: 8px 14px;
                font-size: 13px;
            }

            .filters .btn-filter,
            .filters .reset-btn {
                padding: 8px 20px;
                font-size: 13px;
            }

            .upload-top-right {
                top: 72px;
                right: 12px;
                padding: 10px 18px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 18px;
            }

            .header-stats {
                font-size: 12px;
            }

            .video-info {
                padding: 12px;
            }

            .video-info h3 {
                font-size: 14px;
            }

            .video-meta {
                font-size: 12px;
            }

            .watch-btn {
                font-size: 13px;
                padding: 8px 16px;
            }

            .filters {
                padding: 12px;
                gap: 8px;
                border-radius: 12px;
            }

            .filters input[type="text"],
            .filters select {
                padding: 8px 12px;
                font-size: 12px;
                border-radius: 8px;
            }

            .filters .btn-filter,
            .filters .reset-btn {
                padding: 8px 16px;
                font-size: 12px;
                border-radius: 8px;
            }
        }

        /* =========================================================
           HERO (en-tête au-dessus des vidéos)
        ========================================================= */
        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            margin-bottom: 28px;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.25);
        }

        .hero-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px 20px 24px;
        }

        .hero-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .hero-title h1 {
            color: #fff;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.8px;
        }

        .hero-title h1 i {
            color: #60a5fa;
            margin-right: 8px;
        }

        .hero-title h1 span {
            background: linear-gradient(135deg, #60a5fa, #bfdbfe);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-title p {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 6px;
        }

        .hero-upload {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 12px 22px;
            background: #fff;
            color: #1e3a8a;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            transition: all .25s ease;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .18);
        }

        .hero-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, .25);
        }

        .hero-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 22px;
            align-items: center;
        }

        .stat-chip {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 14px;
            backdrop-filter: blur(6px);
        }

        .stat-chip>i {
            font-size: 18px;
            color: #60a5fa;
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            background: rgba(96, 165, 250, .15);
            border-radius: 10px;
        }

        .stat-chip.warn>i {
            color: #fbbf24;
            background: rgba(251, 191, 36, .15);
        }

        .stat-chip strong {
            display: block;
            color: #fff;
            font-size: 20px;
            line-height: 1.1;
        }

        .stat-chip small {
            color: #94a3b8;
            font-size: 12px;
        }

        .hero-toggle {
            margin-left: auto;
            color: #bfdbfe;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            padding: 9px 16px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 999px;
            transition: .25s;
        }

        .hero-toggle:hover {
            background: rgba(255, 255, 255, .12);
            color: #fff;
        }

        @media (max-width: 600px) {
            .hero-inner {
                padding: 20px 14px;
            }

            .hero-title h1 {
                font-size: 24px;
            }

            .hero-upload {
                width: 100%;
                justify-content: center;
            }

            .stat-chip {
                flex: 1 1 calc(50% - 12px);
                padding: 10px 12px;
            }

            .hero-toggle {
                margin-left: 0;
                width: 100%;
                text-align: center;
            }
        }

        /* =========================================================
           MINIATURE CLIQUABLE + BOUTON PLAY
        ========================================================= */
        .thumb-play {
            position: absolute;
            inset: 0;
            display: block;
            cursor: pointer;
        }

        .play-overlay {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            background: linear-gradient(180deg, rgba(15, 23, 42, 0) 40%, rgba(15, 23, 42, .55) 100%);
            opacity: 0;
            transition: opacity .25s ease;
        }

        .play-overlay i {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            padding-left: 4px;
            background: rgba(37, 99, 235, .92);
            color: #fff;
            font-size: 22px;
            border-radius: 50%;
            box-shadow: 0 8px 24px rgba(37, 99, 235, .5);
            transform: scale(.8);
            transition: transform .25s ease;
        }

        .thumb-play:hover .play-overlay,
        .thumb-play:focus-visible .play-overlay {
            opacity: 1;
        }

        .thumb-play:hover .play-overlay i {
            transform: scale(1);
        }

        @media (hover: none) {
            .play-overlay {
                opacity: 1;
                background: none;
            }

            .play-overlay i {
                width: 46px;
                height: 46px;
                font-size: 18px;
                transform: scale(1);
            }
        }
    </style>
</head>

<body>
    <!-- Menu -->
    <?php include_once __DIR__ . '/includes/menu.php'; ?>

    <!-- Hero -->
    <section class="hero">
        <div class="hero-inner">
            <div class="hero-top">
                <div class="hero-title">
                    <h1><i class="fas fa-film"></i> Bertin<span>Movie</span></h1>
                    <p>Bibliothèque vidéo · cliquez sur une miniature pour lancer la lecture.</p>
                </div>
                <a href="admin/upload_video.php" class="hero-upload"><i class="fas fa-cloud-arrow-up"></i> Uploader une
                    vidéo</a>
            </div>
            <div class="hero-stats">
                <div class="stat-chip"><i class="fas fa-video"></i>
                    <div><strong><?php echo $total_with_video; ?></strong><small>vidéo<?php echo $total_with_video > 1 ? 's' : ''; ?>
                            disponible<?php echo $total_with_video > 1 ? 's' : ''; ?></small></div>
                </div>
                <div class="stat-chip"><i class="fas fa-eye"></i>
                    <div><strong><?php echo number_format($total_views, 0, ',', ' '); ?></strong><small>vues au
                            total</small></div>
                </div>
                <div class="stat-chip warn"><i class="fas fa-circle-exclamation"></i>
                    <div><strong><?php echo $total_without_video; ?></strong><small>sans vidéo</small></div>
                </div>
                <?php if ($show_all == 0 && $total_without_video > 0): ?>
                    <a class="hero-toggle"
                        href="?show_all=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category > 0 ? '&category=' . $category : ''; ?>&sort=<?php echo urlencode($sort); ?>"><i
                            class="fas fa-eye"></i> Voir aussi les films sans vidéo</a>
                <?php elseif ($show_all == 1): ?>
                    <a class="hero-toggle"
                        href="?<?php echo !empty($search) ? 'search=' . urlencode($search) . '&' : ''; ?><?php echo $category > 0 ? 'category=' . $category . '&' : ''; ?>sort=<?php echo urlencode($sort); ?>&show_all=0"><i
                            class="fas fa-video"></i> Voir seulement les vidéos</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

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

            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="video.php<?php echo $show_all == 1 ? '?show_all=1' : ''; ?>" class="reset-btn"><i
                    class="fas fa-undo"></i> Réinitialiser</a>
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
                                <a class="thumb-play" href="watch.php?id_video=<?php echo $id_video; ?>&autoplay=1"
                                    aria-label="Lire <?php echo htmlspecialchars($film['nom_film']); ?>">
                                    <img src="<?php echo htmlspecialchars($thumbnail); ?>"
                                        alt="<?php echo htmlspecialchars($film['nom_film']); ?>" loading="lazy">
                                    <span class="play-overlay"><i class="fas fa-play"></i></span>
                                    <span class="video-duration">
                                        <i class="fas fa-clock"></i>
                                        <?php echo htmlspecialchars($duration); ?>
                                    </span>
                                </a>
                            <?php else: ?>
                                <div class="no-video-placeholder">
                                    <div class="icon"><i class="fas fa-film"></i></div>
                                    <div>Aucune vidéo</div>
                                    <div style="font-size: 12px; margin-top: 5px; color: #cbd5e1;">
                                        <?php echo htmlspecialchars($film['nom_film']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="video-info">
                            <h3>
                                <?php echo htmlspecialchars($film['nom_film']); ?>
                            </h3>
                            <div class="video-meta">
                                <span>
                                    <i class="far fa-calendar-alt"></i>
                                    <?php echo $has_video ? date('d/m/Y', strtotime($film['date_upload'] ?? 'now')) : 'Non uploadé'; ?>
                                </span>
                                <span class="video-category">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($film['nom_categorie'] ?? 'Non catégorisé'); ?>
                                </span>
                            </div>
                            <?php if ($has_video): ?>
                                <a href="watch.php?id_video=<?php echo $id_video; ?>&autoplay=1" class="watch-btn"><i
                                        class="fas fa-play"></i>
                                    Regarder</a>
                            <?php else: ?>
                                <a href="admin/upload_video.php?film_id=<?php echo $film['id_film']; ?>"
                                    class="watch-btn upload-btn"><i class="fas fa-cloud-upload-alt"></i> Uploader</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-videos">
                <h2><i class="fas fa-film" style="color: #3b82f6;"></i> Aucun film trouvé</h2>
                <p>Essayez de modifier vos filtres ou ajoutez des films.</p>
                <a href="admin/upload_video.php" class="admin-upload-link"><i class="fas fa-cloud-upload-alt"></i> Aller à
                    l'upload</a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>