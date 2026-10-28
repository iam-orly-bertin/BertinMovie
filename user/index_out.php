<?php
require_once __DIR__ . '/../includes/session_boot.php';

// Inclusion des fichiers nécessaires
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/update_session.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Mettre à jour la session
if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

$is_logged_in = isset($_SESSION['user_id']);
$user_nom = $_SESSION['user_nom'] ?? '';

// =========================================================
// RÉCUPÉRATION DES DONNÉES
// =========================================================

// 1. Films récents (pour la hero section)
$hero_film = $pdo->query("
    SELECT f.*, 
           v.id_video, 
           v.thumbnail, 
           v.views,
           c.nom_categorie,
           r.nom AS nom_realisateur,
           r.prenom AS prenom_realisateur
    FROM film f
    INNER JOIN video_film v ON f.id_film = v.id_film
    LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
    LEFT JOIN realisateur r ON f.id_realisateur = r.id_realisateur
    WHERE f.is_deleted = 0 AND f.est_archive = 0
    ORDER BY v.views DESC
    LIMIT 1
")->fetch();

// 2. Films populaires (pour la section tendances)
$popular_films = $pdo->query("
    SELECT f.*, 
           v.id_video, 
           v.thumbnail, 
           v.views,
           c.nom_categorie
    FROM film f
    INNER JOIN video_film v ON f.id_film = v.id_film
    LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
    WHERE f.is_deleted = 0 AND f.est_archive = 0
    ORDER BY v.views DESC
    LIMIT 8
")->fetchAll();

// 3. Nouveautés
$new_films = $pdo->query("
    SELECT f.*, 
           v.id_video, 
           v.thumbnail, 
           v.views,
           c.nom_categorie
    FROM film f
    INNER JOIN video_film v ON f.id_film = v.id_film
    LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
    WHERE f.is_deleted = 0 AND f.est_archive = 0
    ORDER BY v.date_upload DESC
    LIMIT 8
")->fetchAll();

// 4. Catégories
$categories = $pdo->query("
    SELECT c.*, COUNT(f.id_film) as total_films
    FROM categorie c
    LEFT JOIN film f ON c.id_categorie = f.id_categorie AND f.is_deleted = 0 AND f.est_archive = 0
    GROUP BY c.id_categorie
    ORDER BY total_films DESC
    LIMIT 6
")->fetchAll();

// 5. Favoris (si connecté)
$favoris_ids = [];
if ($is_logged_in) {
    try {
        $stmt = $pdo->prepare("SELECT id_video FROM user_favoris WHERE id_utilisateur = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $favoris_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
    }
}

// 6. Continuer à regarder (si connecté)
$continue_watching = [];
if ($is_logged_in) {
    try {
        $stmt = $pdo->prepare("
            SELECT f.*, v.id_video, v.thumbnail, v.views, h.date_vue
            FROM historique_vue h
            INNER JOIN video_film v ON h.id_video = v.id_video
            INNER JOIN film f ON v.id_film = f.id_film
            WHERE h.id_utilisateur = ? AND f.is_deleted = 0 AND f.est_archive = 0
            ORDER BY h.date_vue DESC
            LIMIT 6
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $continue_watching = $stmt->fetchAll();
    } catch (PDOException $e) {
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title data-lang-fr="BERTIN MOVIE - Découvrez les meilleurs films"
        data-lang-en="BERTIN MOVIE - Discover the best movies">BERTIN MOVIE - Découvrez les meilleurs films</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/film_web_dy/user/css/style-user.css">

    <style>
        /* =========================================================
           RESET & VARIABLES
           ========================================================= */
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #60a5fa;
            --secondary: #8b5cf6;
            --bg-dark: #0f0f1a;
            --bg-card: #1a1a2e;
            --bg-card-hover: #252540;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: #2a2a40;
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-hover: 0 12px 48px rgba(59, 130, 246, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --radius: 16px;
            --radius-lg: 24px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg-dark);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* =========================================================
           SCROLLBAR PERSONNALISÉE
           ========================================================= */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-dark);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* =========================================================
           PAGE CONTENT
           ========================================================= */
        .page-content {
            padding: 0;
            max-width: 100%;
            margin: 0;
        }

        /* =========================================================
           HEADER - NAVIGATION
           ========================================================= */
        .main-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 12px 24px;
            transition: all 0.4s ease;
            background: transparent;
        }

        .main-header.scrolled {
            background: rgba(15, 15, 26, 0.92);
            backdrop-filter: blur(12px);
            box-shadow: 0 2px 30px rgba(0, 0, 0, 0.3);
        }

        .main-header .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1400px;
            margin: 0 auto;
        }

        .main-header .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.5px;
        }

        .main-header .logo i {
            color: var(--primary);
            font-size: 28px;
        }

        .main-header .logo span {
            color: var(--primary);
        }

        .main-header .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
            list-style: none;
        }

        .main-header .nav-links li a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 10px;
            transition: var(--transition);
        }

        .main-header .nav-links li a:hover,
        .main-header .nav-links li a.active {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.06);
        }

        .main-header .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-login {
            padding: 8px 20px;
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-login:hover {
            border-color: var(--text-primary);
            color: var(--text-primary);
        }

        .btn-register {
            padding: 8px 24px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 10px;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
        }

        /* =========================================================
           HERO SECTION
           ========================================================= */
        .hero-section {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 80px 24px 60px;
            overflow: hidden;
        }

        .hero-section .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: linear-gradient(135deg, rgba(15, 15, 26, 0.4) 0%, rgba(15, 15, 26, 0.9) 100%);
        }

        .hero-section .hero-bg img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.3;
        }

        .hero-section .hero-content {
            position: relative;
            z-index: 1;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .hero-section .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            color: var(--primary-light);
            margin-bottom: 20px;
        }

        .hero-section .hero-badge i {
            font-size: 10px;
        }

        .hero-section .hero-title {
            font-size: 56px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 16px;
            letter-spacing: -1px;
        }

        .hero-section .hero-title span {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-section .hero-desc {
            font-size: 18px;
            color: var(--text-secondary);
            max-width: 560px;
            line-height: 1.7;
            margin-bottom: 24px;
        }

        .hero-section .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
        }

        .hero-section .hero-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .hero-section .hero-meta span i {
            color: var(--primary);
        }

        .hero-section .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .hero-section .hero-actions .btn-hero-primary {
            padding: 14px 32px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 12px;
            color: #fff;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .hero-section .hero-actions .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(59, 130, 246, 0.35);
        }

        .hero-section .hero-actions .btn-hero-secondary {
            padding: 14px 32px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .hero-section .hero-actions .btn-hero-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--text-primary);
        }

        /* =========================================================
           SECTION TITLE
           ========================================================= */
        .section-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: var(--primary);
        }

        .section-title .see-all {
            margin-left: auto;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .section-title .see-all:hover {
            color: var(--primary);
        }

        /* =========================================================
           SECTIONS
           ========================================================= */
        .content-section {
            padding: 40px 24px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* =========================================================
           CATEGORIES GRID
           ========================================================= */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
        }

        .category-card {
            position: relative;
            border-radius: var(--radius);
            overflow: hidden;
            aspect-ratio: 16/9;
            background: var(--bg-card);
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
            text-decoration: none;
        }

        .category-card:hover {
            transform: translateY(-6px) scale(1.02);
            border-color: var(--primary);
            box-shadow: var(--shadow-hover);
        }

        .category-card .cat-icon {
            font-size: 32px;
            margin-bottom: 8px;
            opacity: 0.6;
            transition: var(--transition);
        }

        .category-card:hover .cat-icon {
            opacity: 1;
            transform: scale(1.1);
        }

        .category-card .cat-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .category-card .cat-count {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .category-card .cat-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(0deg, rgba(15, 15, 26, 0.7) 0%, transparent 60%);
        }

        .category-card .cat-content {
            position: relative;
            z-index: 1;
        }

        .category-card .cat-icon-action {
            color: #f59e0b;
        }

        .category-card .cat-icon-comedy {
            color: #22c55e;
        }

        .category-card .cat-icon-drama {
            color: #8b5cf6;
        }

        .category-card .cat-icon-horror {
            color: #ef4444;
        }

        .category-card .cat-icon-romance {
            color: #ec4899;
        }

        .category-card .cat-icon-sci-fi {
            color: #06b6d4;
        }

        /* =========================================================
           MOVIES GRID
           ========================================================= */
        .movies-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .movie-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            cursor: pointer;
            text-decoration: none;
            display: block;
        }

        .movie-card:hover {
            transform: translateY(-8px);
            border-color: var(--primary);
            box-shadow: var(--shadow-hover);
        }

        .movie-card .thumb {
            position: relative;
            padding-top: 56.25%;
            background: var(--bg-dark);
            overflow: hidden;
        }

        .movie-card .thumb img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .movie-card:hover .thumb img {
            transform: scale(1.08);
        }

        .movie-card .thumb .overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(0deg, rgba(15, 15, 26, 0.8) 0%, transparent 50%);
            opacity: 0;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .movie-card:hover .thumb .overlay {
            opacity: 1;
        }

        .movie-card .thumb .overlay .play-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #fff;
            transform: scale(0.8);
            transition: var(--transition);
            backdrop-filter: blur(4px);
        }

        .movie-card:hover .thumb .overlay .play-icon {
            transform: scale(1);
        }

        .movie-card .thumb .duration {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.8);
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #fff;
            backdrop-filter: blur(4px);
        }

        .movie-card .thumb .badge-top {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(59, 130, 246, 0.9);
            color: #fff;
        }

        .movie-card .info {
            padding: 16px;
        }

        .movie-card .info h4 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .movie-card .info .meta {
            display: flex;
            gap: 12px;
            font-size: 12px;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }

        .movie-card .info .meta i {
            color: var(--primary);
            margin-right: 2px;
        }

        /* =========================================================
           FAVORITE BUTTON
           ========================================================= */
        .favorite-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.6);
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            cursor: pointer;
            color: #fff;
            font-size: 16px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            backdrop-filter: blur(4px);
        }

        .favorite-btn:hover {
            transform: scale(1.1);
        }

        .favorite-btn.active {
            background: #3b82f6 !important;
            box-shadow: 0 2px 12px rgba(59, 130, 246, 0.4);
        }

        .favorite-btn.active i {
            color: #fff !important;
        }

        /* =========================================================
           CONTINUE WATCHING
           ========================================================= */
        .continue-card {
            position: relative;
        }

        .continue-card .progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: rgba(255, 255, 255, 0.1);
        }

        .continue-card .progress-bar .progress {
            height: 100%;
            background: var(--primary);
            border-radius: 0 2px 2px 0;
            width: 0%;
        }

        /* =========================================================
           CTA SECTION
           ========================================================= */
        .cta-section {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-dark));
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 60px 40px;
            text-align: center;
            margin-top: 20px;
        }

        .cta-section h2 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .cta-section p {
            color: var(--text-secondary);
            font-size: 18px;
            max-width: 560px;
            margin: 0 auto 28px;
            line-height: 1.7;
        }

        .cta-section .cta-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
        }

        .cta-section .cta-buttons a {
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: var(--transition);
        }

        .cta-section .cta-buttons .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.2);
        }

        .cta-section .cta-buttons .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 32px rgba(59, 130, 246, 0.35);
        }

        .cta-section .cta-buttons .btn-secondary {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .cta-section .cta-buttons .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* =========================================================
           MODAL CONNEXION
           ========================================================= */
        .auth-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .auth-modal-overlay.active {
            display: flex;
        }

        .auth-modal {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 40px;
            max-width: 480px;
            width: 92%;
            border: 1px solid var(--border-color);
            animation: modalSlideUp 0.4s ease;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes modalSlideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .auth-modal .modal-close {
            float: right;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
            transition: var(--transition);
        }

        .auth-modal .modal-close:hover {
            color: #ef4444;
            transform: rotate(90deg);
        }

        .auth-modal .modal-icon {
            text-align: center;
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 16px;
        }

        .auth-modal h2 {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .auth-modal p {
            text-align: center;
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .auth-modal .modal-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .auth-modal .modal-buttons a {
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            text-align: center;
            transition: var(--transition);
        }

        .auth-modal .modal-buttons .btn-login-modal {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .auth-modal .modal-buttons .btn-login-modal:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .auth-modal .modal-buttons .btn-register-modal {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
        }

        .auth-modal .modal-buttons .btn-register-modal:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
        }

        .auth-modal .modal-divider {
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
            margin: 16px 0;
            position: relative;
        }

        .auth-modal .modal-divider::before,
        .auth-modal .modal-divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30%;
            height: 1px;
            background: var(--border-color);
        }

        .auth-modal .modal-divider::before {
            left: 0;
        }

        .auth-modal .modal-divider::after {
            right: 0;
        }

        /* =========================================================
           RESPONSIVE
           ========================================================= */
        @media (max-width: 1024px) {
            .movies-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .categories-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .hero-section .hero-title {
                font-size: 36px;
            }

            .hero-section .hero-desc {
                font-size: 16px;
            }

            .movies-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .categories-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .cta-section {
                padding: 40px 20px;
            }

            .cta-section h2 {
                font-size: 24px;
            }

            .main-header .nav-links {
                display: none;
            }

            .auth-modal {
                padding: 28px 20px;
            }

            .main-header {
                padding: 10px 16px;
            }

            .main-header .logo {
                font-size: 20px;
            }

            .main-header .logo i {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .movies-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .categories-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .hero-section .hero-title {
                font-size: 28px;
            }

            .hero-section .hero-actions {
                flex-direction: column;
            }

            .hero-section .hero-actions .btn-hero-primary,
            .hero-section .hero-actions .btn-hero-secondary {
                justify-content: center;
            }

            .content-section {
                padding: 24px 12px;
            }

            .hero-section {
                padding: 60px 12px 40px;
            }

            .main-header .nav-actions .btn-login span,
            .main-header .nav-actions .btn-register span {
                display: none;
            }

            .main-header .nav-actions .btn-login,
            .main-header .nav-actions .btn-register {
                padding: 8px 12px;
            }

            .main-header .nav-actions .btn-login i,
            .main-header .nav-actions .btn-register i {
                font-size: 16px;
            }

            .cta-section {
                padding: 30px 16px;
            }

            .cta-section h2 {
                font-size: 20px;
            }

            .cta-section p {
                font-size: 15px;
            }

            .auth-modal h2 {
                font-size: 20px;
            }

            .section-title {
                font-size: 20px;
            }

            .movie-card .info h4 {
                font-size: 13px;
            }

            .movie-card .info .meta {
                font-size: 10px;
                gap: 8px;
            }

            .category-card .cat-name {
                font-size: 12px;
            }

            .category-card .cat-icon {
                font-size: 24px;
            }
        }

        @media (max-width: 380px) {
            .movies-grid {
                gap: 8px;
            }

            .movie-card .info {
                padding: 10px;
            }

            .movie-card .info h4 {
                font-size: 12px;
            }

            .hero-section .hero-title {
                font-size: 24px;
            }
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

        .animate-on-load {
            animation: fadeInUp 0.6s ease forwards;
        }

        .animate-on-load:nth-child(2) {
            animation-delay: 0.1s;
        }

        .animate-on-load:nth-child(3) {
            animation-delay: 0.2s;
        }

        .animate-on-load:nth-child(4) {
            animation-delay: 0.3s;
        }

        .animate-on-load:nth-child(5) {
            animation-delay: 0.4s;
        }

        .animate-on-load:nth-child(6) {
            animation-delay: 0.5s;
        }

        .animate-on-load:nth-child(7) {
            animation-delay: 0.6s;
        }

        .animate-on-load:nth-child(8) {
            animation-delay: 0.7s;
        }
    </style>
</head>

<body>

    <!-- =========================================================
    HEADER - NAVIGATION
    ========================================================= -->
    <header class="main-header" id="mainHeader">
        <div class="header-inner">
            <a href="index.php" class="logo">
                <i class="fas fa-film"></i>
                BERTIN<span>MOVIE</span>
            </a>

            <ul class="nav-links">
                <li><a href="index.php" class="active" data-lang-fr="Accueil" data-lang-en="Home">Accueil</a></li>
                <li><a href="video.php" data-lang-fr="Films" data-lang-en="Movies">Films</a></li>
                <li><a href="#categories" data-lang-fr="Catégories" data-lang-en="Categories">Catégories</a></li>
                <?php if ($is_logged_in): ?>
                    <li><a href="favoris.php" data-lang-fr="Favoris" data-lang-en="Favorites"><i class="fas fa-heart"></i>
                            Favoris</a></li>
                    <li><a href="historique.php" data-lang-fr="Historique" data-lang-en="History"><i
                                class="fas fa-history"></i> Historique</a></li>
                <?php endif; ?>
            </ul>

            <div class="nav-actions">
                <?php if ($is_logged_in): ?>
                    <a href="profil.php" class="btn-login" style="border-color:var(--primary);color:var(--primary);">
                        <i class="fas fa-user"></i> <span><?= htmlspecialchars($user_nom) ?></span>
                    </a>
                    <a href="/film_web_dy/index.php" class="btn-register"
                        style="background:linear-gradient(135deg,#ef4444,#dc2626);">
                        <i class="fas fa-sign-out-alt"></i> <span data-lang-fr="Déconnexion"
                            data-lang-en="Logout">Déconnexion</span>
                    </a>
                <?php else: ?>
                    <a href="javascript:void(0)" class="btn-login" onclick="openAuthModal('login')">
                        <i class="fas fa-sign-in-alt"></i> <span data-lang-fr="Se connecter" data-lang-en="Login">Se
                            connecter</span>
                    </a>
                    <a href="javascript:void(0)" class="btn-register" onclick="openAuthModal('register')">
                        <i class="fas fa-user-plus"></i> <span data-lang-fr="Créer un compte" data-lang-en="Sign up">Créer
                            un compte</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- =========================================================
    AUTH MODAL
    ========================================================= -->
    <div class="auth-modal-overlay" id="authModal">
        <div class="auth-modal">
            <button class="modal-close" onclick="closeAuthModal()">&times;</button>
            <div class="modal-icon">
                <i class="fas fa-film"></i>
            </div>
            <h2 id="authModalTitle" data-lang-fr="Connectez-vous pour continuer" data-lang-en="Login to continue">
                Connectez-vous pour continuer</h2>
            <p id="authModalDesc"
                data-lang-fr="Créez votre compte gratuitement pour regarder ce film et profiter de toutes les fonctionnalités."
                data-lang-en="Create your free account to watch this movie and enjoy all features.">
                Créez votre compte gratuitement pour regarder ce film et profiter de toutes les fonctionnalités.
            </p>
            <div class="modal-divider"></div>
            <div class="modal-buttons">
                <a href="/film_web_dy/login.php" class="btn-login-modal" data-lang-fr="Se connecter"
                    data-lang-en="Login">Se connecter</a>
                <a href="/film_web_dy/register.php" class="btn-register-modal" data-lang-fr="Créer un compte"
                    data-lang-en="Create account">Créer un compte</a>
            </div>
        </div>
    </div>

    <!-- =========================================================
    PAGE CONTENT
    ========================================================= -->
    <div class="page-content">

        <!-- =========================================================
        HERO SECTION
        ========================================================= -->
        <section class="hero-section" style="background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 100%);">
            <div class="hero-bg">
                <?php if (!empty($hero_film['thumbnail'])): ?>
                    <img src="<?= htmlspecialchars($hero_film['thumbnail']) ?>"
                        alt="<?= htmlspecialchars($hero_film['nom_film']) ?>">
                <?php endif; ?>
            </div>
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-star"></i>
                    <span data-lang-fr="Film à l'affiche" data-lang-en="Featured movie">Film à l'affiche</span>
                </div>
                <h1 class="hero-title">
                    <?php if (!empty($hero_film['nom_film'])): ?>
                        <?= htmlspecialchars($hero_film['nom_film']) ?>
                    <?php else: ?>
                        Découvrez les <span>meilleurs films</span>
                    <?php endif; ?>
                </h1>
                <p class="hero-desc">
                    <?php if (!empty($hero_film['titre'])): ?>
                        <?= htmlspecialchars($hero_film['titre']) ?>
                    <?php else: ?>
                        <span
                            data-lang-fr="Plongez dans un univers cinématographique unique. Des milliers de films à portée de clic."
                            data-lang-en="Dive into a unique cinematic universe. Thousands of movies at your fingertips.">
                            Plongez dans un univers cinématographique unique. Des milliers de films à portée de clic.
                        </span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($hero_film['nom_categorie'])): ?>
                    <div class="hero-meta">
                        <span><i class="fas fa-tag"></i> <?= htmlspecialchars($hero_film['nom_categorie']) ?></span>
                        <?php if (!empty($hero_film['date_sortie'])): ?>
                            <span><i class="fas fa-calendar"></i> <?= date('Y', strtotime($hero_film['date_sortie'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($hero_film['duree'])): ?>
                            <span><i class="fas fa-clock"></i> <?= htmlspecialchars($hero_film['duree']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="hero-actions">
                    <?php if (!empty($hero_film['id_video'])): ?>
                        <a href="javascript:void(0)" onclick="handleWatchClick(<?= $hero_film['id_video'] ?>)"
                            class="btn-hero-primary">
                            <i class="fas fa-play"></i> <span data-lang-fr="Regarder" data-lang-en="Watch">Regarder</span>
                        </a>
                    <?php else: ?>
                        <a href="video.php" class="btn-hero-primary">
                            <i class="fas fa-compass"></i> <span data-lang-fr="Explorer"
                                data-lang-en="Explore">Explorer</span>
                        </a>
                    <?php endif; ?>
                    <a href="#categories" class="btn-hero-secondary">
                        <i class="fas fa-th-large"></i> <span data-lang-fr="Découvrir"
                            data-lang-en="Discover">Découvrir</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- =========================================================
        CATÉGORIES
        ========================================================= -->
        <section class="content-section" id="categories">
            <div class="section-title">
                <i class="fas fa-th-large"></i>
                <span data-lang-fr="Catégories" data-lang-en="Categories">Catégories</span>
                <a href="video.php" class="see-all">
                    <span data-lang-fr="Voir tout" data-lang-en="See all">Voir tout</span> <i
                        class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="categories-grid">
                <?php
                $cat_icons = [
                    'Action' => 'fa-bolt cat-icon-action',
                    'Comédie' => 'fa-laugh cat-icon-comedy',
                    'Comedy' => 'fa-laugh cat-icon-comedy',
                    'Drame' => 'fa-mask cat-icon-drama',
                    'Horreur' => 'fa-skull cat-icon-horror',
                    'Romance' => 'fa-heart cat-icon-romance',
                    'Sci-Fi' => 'fa-rocket cat-icon-sci-fi',
                    'Animation' => 'fa-cube cat-icon-action',
                    'Thriller' => 'fa-brain cat-icon-drama',
                ];
                foreach ($categories as $cat):
                    $icon_class = $cat_icons[$cat['nom_categorie']] ?? 'fa-tag';
                    ?>
                    <a href="video.php?categorie=<?= $cat['id_categorie'] ?>" class="category-card animate-on-load">
                        <div class="cat-overlay"></div>
                        <div class="cat-content">
                            <div class="cat-icon <?= $icon_class ?>">
                                <i class="fas <?= explode(' ', $icon_class)[0] ?>"></i>
                            </div>
                            <div class="cat-name"><?= htmlspecialchars($cat['nom_categorie']) ?></div>
                            <div class="cat-count"><?= $cat['total_films'] ?? 0 ?> films</div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- =========================================================
        FILMS POPULAIRES
        ========================================================= -->
        <?php if (count($popular_films) > 0): ?>
            <section class="content-section">
                <div class="section-title">
                    <i class="fas fa-fire"></i>
                    <span data-lang-fr="Tendances" data-lang-en="Trending">Tendances</span>
                    <a href="video.php" class="see-all">
                        <span data-lang-fr="Voir tout" data-lang-en="See all">Voir tout</span> <i
                            class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="movies-grid">
                    <?php foreach ($popular_films as $index => $film): ?>
                        <a href="javascript:void(0)" onclick="handleWatchClick(<?= $film['id_video'] ?>)"
                            class="movie-card animate-on-load" style="animation-delay: <?= $index * 0.05 ?>s;">
                            <div class="thumb">
                                <img src="<?= htmlspecialchars($film['thumbnail'] ?? 'assets/default-thumbnail.jpg') ?>"
                                    alt="<?= htmlspecialchars($film['nom_film']) ?>" loading="lazy">
                                <div class="overlay">
                                    <div class="play-icon"><i class="fas fa-play"></i></div>
                                </div>
                                <?php if (!empty($film['duree'])): ?>
                                    <span class="duration"><?= htmlspecialchars($film['duree']) ?></span>
                                <?php endif; ?>
                                <?php if ($is_logged_in): ?>
                                    <button class="favorite-btn <?= in_array($film['id_video'], $favoris_ids) ? 'active' : '' ?>"
                                        onclick="event.stopPropagation(); toggleFavori(<?= $film['id_video'] ?>, this)">
                                        <i class="<?= in_array($film['id_video'], $favoris_ids) ? 'fas' : 'far' ?> fa-heart"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="info">
                                <h4><?= htmlspecialchars($film['nom_film']) ?></h4>
                                <div class="meta">
                                    <span><i class="fas fa-eye"></i>
                                        <?= number_format($film['views'] ?? 0, 0, ',', ' ') ?></span>
                                    <span><i class="fas fa-tag"></i>
                                        <?= htmlspecialchars($film['nom_categorie'] ?? 'Non catégorisé') ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- =========================================================
        NOUVEAUTÉS
        ========================================================= -->
        <?php if (count($new_films) > 0): ?>
            <section class="content-section">
                <div class="section-title">
                    <i class="fas fa-star"></i>
                    <span data-lang-fr="Nouveautés" data-lang-en="New releases">Nouveautés</span>
                    <a href="video.php" class="see-all">
                        <span data-lang-fr="Voir tout" data-lang-en="See all">Voir tout</span> <i
                            class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="movies-grid">
                    <?php foreach ($new_films as $index => $film): ?>
                        <a href="javascript:void(0)" onclick="handleWatchClick(<?= $film['id_video'] ?>)"
                            class="movie-card animate-on-load" style="animation-delay: <?= $index * 0.05 ?>s;">
                            <div class="thumb">
                                <img src="<?= htmlspecialchars($film['thumbnail'] ?? 'assets/default-thumbnail.jpg') ?>"
                                    alt="<?= htmlspecialchars($film['nom_film']) ?>" loading="lazy">
                                <div class="overlay">
                                    <div class="play-icon"><i class="fas fa-play"></i></div>
                                </div>
                                <span class="badge-top" data-lang-fr="Nouveau" data-lang-en="New">Nouveau</span>
                                <?php if (!empty($film['duree'])): ?>
                                    <span class="duration"><?= htmlspecialchars($film['duree']) ?></span>
                                <?php endif; ?>
                                <?php if ($is_logged_in): ?>
                                    <button class="favorite-btn <?= in_array($film['id_video'], $favoris_ids) ? 'active' : '' ?>"
                                        onclick="event.stopPropagation(); toggleFavori(<?= $film['id_video'] ?>, this)">
                                        <i class="<?= in_array($film['id_video'], $favoris_ids) ? 'fas' : 'far' ?> fa-heart"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="info">
                                <h4><?= htmlspecialchars($film['nom_film']) ?></h4>
                                <div class="meta">
                                    <span><i class="fas fa-eye"></i>
                                        <?= number_format($film['views'] ?? 0, 0, ',', ' ') ?></span>
                                    <span><i class="fas fa-tag"></i>
                                        <?= htmlspecialchars($film['nom_categorie'] ?? 'Non catégorisé') ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- =========================================================
        CONTINUER À REGARDER (si connecté)
        ========================================================= -->
        <?php if ($is_logged_in && count($continue_watching) > 0): ?>
            <section class="content-section">
                <div class="section-title">
                    <i class="fas fa-play-circle"></i>
                    <span data-lang-fr="Continuer à regarder" data-lang-en="Continue watching">Continuer à regarder</span>
                </div>
                <div class="movies-grid">
                    <?php foreach ($continue_watching as $index => $film): ?>
                        <a href="watch.php?id_video=<?= $film['id_video'] ?>" class="movie-card continue-card animate-on-load"
                            style="animation-delay: <?= $index * 0.05 ?>s;">
                            <div class="thumb">
                                <img src="<?= htmlspecialchars($film['thumbnail'] ?? 'assets/default-thumbnail.jpg') ?>"
                                    alt="<?= htmlspecialchars($film['nom_film']) ?>" loading="lazy">
                                <div class="overlay">
                                    <div class="play-icon"><i class="fas fa-play"></i></div>
                                </div>
                                <?php if (!empty($film['duree'])): ?>
                                    <span class="duration"><?= htmlspecialchars($film['duree']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="info">
                                <h4><?= htmlspecialchars($film['nom_film']) ?></h4>
                                <div class="meta">
                                    <span><i class="fas fa-clock"></i> <?= date('d/m/Y', strtotime($film['date_vue'])) ?></span>
                                </div>
                            </div>
                            <div class="progress-bar">
                                <div class="progress" style="width: 0%;"></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- =========================================================
        CTA SECTION (si non connecté)
        ========================================================= -->
        <?php if (!$is_logged_in): ?>
            <section class="content-section">
                <div class="cta-section">
                    <h2 data-lang-fr="Prêt à découvrir l'univers du cinéma ?"
                        data-lang-en="Ready to discover the world of cinema?">
                        Prêt à découvrir l'univers du cinéma ?
                    </h2>
                    <p data-lang-fr="Rejoignez notre communauté et accédez à des milliers de films en illimité. Créez votre compte gratuitement en quelques secondes."
                        data-lang-en="Join our community and access thousands of movies unlimited. Create your free account in seconds.">
                        Rejoignez notre communauté et accédez à des milliers de films en illimité. Créez votre compte
                        gratuitement en quelques secondes.
                    </p>
                    <div class="cta-buttons">
                        <a href="/film_web_dy/register.php" class="btn-primary" data-lang-fr="Créer un compte gratuit"
                            data-lang-en="Create free account">Créer un compte gratuit</a>
                        <a href="/film_web_dy/login.php" class="btn-secondary" data-lang-fr="Se connecter"
                            data-lang-en="Login">Se connecter</a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

    </div>

    <!-- =========================================================
    JAVASCRIPT
    ========================================================= -->
    <script>
        // ============================================================
        // HEADER SCROLL
        // ============================================================
        document.addEventListener('DOMContentLoaded', function () {
            const header = document.getElementById('mainHeader');
            window.addEventListener('scroll', function () {
                if (window.scrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });
        });

        // ============================================================
        // AUTH MODAL
        // ============================================================
        function openAuthModal(type) {
            const modal = document.getElementById('authModal');
            const title = document.getElementById('authModalTitle');
            const desc = document.getElementById('authModalDesc');

            if (type === 'login') {
                title.textContent = document.documentElement.lang === 'en' ? 'Login to continue' : 'Connectez-vous pour continuer';
                desc.textContent = document.documentElement.lang === 'en' ? 'Login to your account to watch this movie and enjoy all features.' : 'Connectez-vous à votre compte pour regarder ce film et profiter de toutes les fonctionnalités.';
            } else {
                title.textContent = document.documentElement.lang === 'en' ? 'Create your free account' : 'Créez votre compte gratuitement';
                desc.textContent = document.documentElement.lang === 'en' ? 'Join our community and access thousands of movies unlimited.' : 'Rejoignez notre communauté et accédez à des milliers de films en illimité.';
            }

            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAuthModal() {
            document.getElementById('authModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        document.getElementById('authModal').addEventListener('click', function (e) {
            if (e.target === this) closeAuthModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAuthModal();
        });

        // ============================================================
        // HANDLE WATCH CLICK - VÉRIFICATION CONNEXION
        // ============================================================
        function handleWatchClick(idVideo) {
            <?php if ($is_logged_in): ?>
                window.location.href = 'watch.php?id_video=' + idVideo;
            <?php else: ?>
                openAuthModal('login');
            <?php endif; ?>
        }

        // ============================================================
        // FAVORIS
        // ============================================================
        function toggleFavori(idVideo, btn) {
            <?php if (!$is_logged_in): ?>
                openAuthModal('login');
                return;
            <?php endif; ?>

            const icon = btn.querySelector('i');
            const isActive = btn.classList.contains('active');

            fetch('/film_web_dy/user/ajax_favori.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_video=' + idVideo + '&action=' + (isActive ? 'remove' : 'add')
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (isActive) {
                            btn.classList.remove('active');
                            icon.className = 'far fa-heart';
                        } else {
                            btn.classList.add('active');
                            icon.className = 'fas fa-heart';
                        }
                    }
                })
                .catch(error => console.error('Erreur:', error));
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