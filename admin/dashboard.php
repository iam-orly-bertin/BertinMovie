<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/update_session.php';
require_once __DIR__ . '/../includes/notification.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ===== RECHERCHE UNIFIÉE =====
if (!empty($search)) {
    $stmt = $pdo->prepare("
        SELECT 'film' as type, id_film as id, nom_film as nom, '' as details 
        FROM film 
        WHERE (nom_film LIKE ? OR titre LIKE ?) AND (is_deleted = 0 OR is_deleted IS NULL)
        UNION ALL
        SELECT 'acteur', id_acteur, CONCAT(prenom, ' ', nom), sexe 
        FROM acteur 
        WHERE nom LIKE ? OR prenom LIKE ?
        UNION ALL
        SELECT 'realisateur', id_realisateur, CONCAT(prenom, ' ', nom), nationalite 
        FROM realisateur 
        WHERE nom LIKE ? OR prenom LIKE ?
        UNION ALL
        SELECT 'categorie', id_categorie, nom_categorie, '' 
        FROM categorie 
        WHERE nom_categorie LIKE ?
        UNION ALL
        SELECT 'langue', id_langue, nom_langue, '' 
        FROM langue 
        WHERE nom_langue LIKE ?
        UNION ALL
        SELECT 'studio', id_studio, nom_studio, '' 
        FROM studio 
        WHERE nom_studio LIKE ?
        UNION ALL
        SELECT 'pays', id_pays, nom_pays, '' 
        FROM pays 
        WHERE nom_pays LIKE ?
        LIMIT 10
    ");

    $stmt->execute([
        "%$search%",
        "%$search%",
        "%$search%",
        "%$search%",
        "%$search%",
        "%$search%",
        "%$search%",
        "%$search%",
        "%$search%",
        "%$search%"
    ]);

    $results = $stmt->fetchAll();
    $total_results = count($results);
} else {
    $results = [];
    $total_results = 0;
}
// ===== STATS =====
$nbFilms = $pdo->query("SELECT COUNT(*) FROM film WHERE is_deleted = 0 OR is_deleted IS NULL")->fetchColumn();
$nbActeurs = $pdo->query("SELECT COUNT(*) FROM acteur")->fetchColumn();
$nbReal = $pdo->query("SELECT COUNT(*) FROM realisateur")->fetchColumn();
$nbLang = $pdo->query("SELECT COUNT(*) FROM langue")->fetchColumn();
$nbCat = $pdo->query("SELECT COUNT(*) FROM categorie")->fetchColumn();
$nbPays = $pdo->query("SELECT COUNT(*) FROM pays")->fetchColumn();
$nbStudios = $pdo->query("SELECT COUNT(*) FROM studio")->fetchColumn();
$nbUtilisateurs = $pdo->query("SELECT COUNT(*) FROM utilisateur")->fetchColumn();

// ===== DERNIERS ÉLÉMENTS =====
$films = $pdo->query("
    SELECT f.*, CONCAT(r.prenom, ' ', r.nom) as realisateur_complet,
           l.nom_langue, c.nom_categorie
    FROM film f
    LEFT JOIN realisateur r ON f.id_realisateur = r.id_realisateur
    LEFT JOIN langue l ON f.id_langue = l.id_langue
    LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
    WHERE f.is_deleted = 0 OR f.is_deleted IS NULL
    ORDER BY f.id_film DESC LIMIT 5
")->fetchAll();

$acteurs = $pdo->query("
    SELECT a.*, CONCAT(a.prenom, ' ', a.nom) as acteur_complet
    FROM acteur a ORDER BY a.id_acteur DESC LIMIT 5
")->fetchAll();

$realisateurs = $pdo->query("
    SELECT r.*, CONCAT(r.prenom, ' ', r.nom) as realisateur_complet
    FROM realisateur r ORDER BY r.id_realisateur DESC LIMIT 5
")->fetchAll();

// ===== FILMS PAR ACTEUR / RÉALISATEUR =====
$acteurFilms = [];
foreach ($acteurs as $a) {
    $stmt = $pdo->prepare("
        SELECT f.nom_film, f.titre 
        FROM film f
        JOIN film_acteur fa ON f.id_film = fa.id_film
        WHERE fa.id_acteur = ? AND (f.is_deleted = 0 OR f.is_deleted IS NULL)
        ORDER BY f.nom_film
    ");
    $stmt->execute([$a['id_acteur']]);
    $acteurFilms[$a['id_acteur']] = $stmt->fetchAll();
}

$realisateurFilms = [];
foreach ($realisateurs as $r) {
    $stmt = $pdo->prepare("
        SELECT nom_film, titre 
        FROM film 
        WHERE id_realisateur = ? AND (is_deleted = 0 OR is_deleted IS NULL)
        ORDER BY nom_film
    ");
    $stmt->execute([$r['id_realisateur']]);
    $realisateurFilms[$r['id_realisateur']] = $stmt->fetchAll();
}

$admin_notification = null; // ancien système (session) supprimé : les notifications viennent maintenant de la base
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Admin - Bertin Movie</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* =========================================================
           DESIGN MODERNE - INTÉGRÉ DIRECTEMENT
           ========================================================= */

        /* ===== VARIABLES ===== */
        :root {
            --bg-primary: #0f172a;
            --bg-card: #1e293b;
            --bg-card-hover: #2a3a5e;
            --border-color: #334155;
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-blue: #3b82f6;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            --radius: 14px;
            --transition: all 0.3s cubic-bezier(0.22, 1, 0.36, 1);
        }

        body.light-mode {
            --bg-primary: #f1f5f9;
            --bg-card: #ffffff;
            --bg-card-hover: #f8fafc;
            --border-color: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Segoe UI', system-ui, sans-serif;
            transition: var(--transition);
            padding-bottom: 70px;
        }

        .content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 24px;
        }

        /* ===== WELCOME ===== */
        .dashboard-welcome {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: var(--radius);
            padding: 28px 32px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }

        body.light-mode .dashboard-welcome {
            background: linear-gradient(135deg, #e2e8f0 0%, #f1f5f9 100%);
        }

        .dashboard-welcome h1 {
            font-size: 26px;
            font-weight: 700;
        }

        .dashboard-welcome h1 i {
            color: var(--accent-blue);
            margin-right: 10px;
        }

        .dashboard-welcome p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .welcome-date {
            margin-top: 8px;
            font-size: 13px;
            color: var(--text-muted);
        }

        /* ===== STATS ===== */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 28px;
        }

        .stats .box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 16px 12px;
            text-align: center;
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stats .box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent-blue);
            opacity: 0.6;
        }

        .stats .box:nth-child(2)::before {
            background: #8b5cf6;
        }

        .stats .box:nth-child(3)::before {
            background: #06b6d4;
        }

        .stats .box:nth-child(4)::before {
            background: #f59e0b;
        }

        .stats .box:nth-child(5)::before {
            background: #10b981;
        }

        .stats .box:nth-child(6)::before {
            background: #ef4444;
        }

        .stats .box:nth-child(7)::before {
            background: #f472b6;
        }

        .stats .box:nth-child(8)::before {
            background: #8b5cf6;
        }

        .stats .box:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
            background: var(--bg-card-hover);
        }

        .stats .box h2 {
            font-size: 24px;
            font-weight: 800;
        }

        .stats .box p {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .stats .box p i {
            margin-right: 4px;
        }

        /* ===== SEARCH ===== */
        .search-modern {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 18px 20px;
            margin-bottom: 24px;
        }

        .search-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .search-label i {
            color: var(--accent-blue);
            margin-right: 8px;
        }

        .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 0 14px;
            transition: var(--transition);
        }

        .search-wrapper:focus-within {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }

        .search-wrapper input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 12px 10px;
            font-size: 14px;
            color: var(--text-primary);
            outline: none;
            min-width: 0;
        }

        .search-wrapper input::placeholder {
            color: var(--text-muted);
        }

        .btn-search-modern {
            background: var(--accent-blue);
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-search-modern:hover {
            background: #2563eb;
        }

        /* ===== CARDS ===== */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 18px 20px;
            margin-bottom: 18px;
            overflow: hidden;
        }

        .section-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }

        .section-header-actions h2 {
            font-size: 17px;
            font-weight: 700;
        }

        .section-header-actions h2 i {
            margin-right: 8px;
        }

        .btn-sm {
            background: var(--border-color);
            color: var(--text-primary);
            padding: 5px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-sm:hover {
            background: var(--accent-blue);
            color: #fff;
        }

        /* ===== TABLES ===== */
        .table-box {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 600px;
        }

        thead {
            background: var(--bg-primary);
            border-bottom: 2px solid var(--border-color);
        }

        thead th {
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
            cursor: pointer;
        }

        tbody tr:hover {
            background: var(--bg-card-hover);
        }

        tbody td {
            padding: 10px 14px;
            vertical-align: middle;
            font-size: 13px;
        }

        table tbody td:first-child {
            min-width: 120px;
            font-weight: 600;
        }

        table tbody td:nth-child(2) {
            min-width: 120px;
        }

        table tbody td:nth-child(3) {
            min-width: 140px;
        }

        table tbody td:nth-child(4) {
            min-width: 100px;
        }

        table tbody td:nth-child(5) {
            min-width: 110px;
        }

        table tbody td:last-child {
            min-width: 40px;
            text-align: right;
        }

        .clickable-row .row-indicator {
            opacity: 0;
            transition: var(--transition);
            font-size: 12px;
            color: var(--text-muted);
        }

        .clickable-row:hover .row-indicator {
            opacity: 1;
        }

        /* =========================================================
           FOOTER
           ========================================================= */
        .footer {
            background: var(--bg-card);
            border-top: 1px solid var(--border-color);
            padding: 30px 24px 20px;
            margin-top: 30px;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 30px;
        }

        .footer-brand .logo-footer {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .footer-brand .logo-footer span {
            color: var(--accent-blue);
        }

        .footer-brand p {
            color: var(--text-secondary);
            font-size: 13px;
            line-height: 1.6;
            max-width: 320px;
        }

        .social-links {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .social-links a {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: var(--transition);
            text-decoration: none;
        }

        .social-links a:hover {
            background: var(--accent-blue);
            color: #fff;
            border-color: var(--accent-blue);
            transform: translateY(-2px);
        }

        .social-links .fa-github:hover {
            background: #333;
            border-color: #333;
        }

        .social-links .fa-facebook-f:hover {
            background: #1877f2;
            border-color: #1877f2;
        }

        .social-links .fa-youtube:hover {
            background: #ff0000;
            border-color: #ff0000;
        }

        .social-links .fa-instagram:hover {
            background: #e4405f;
            border-color: #e4405f;
        }

        .social-links .fa-twitter:hover {
            background: #1da1f2;
            border-color: #1da1f2;
        }

        .footer-col h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .footer-col ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .footer-col ul li a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .footer-col ul li a i {
            font-size: 10px;
            color: var(--text-muted);
        }

        .footer-col ul li a:hover {
            color: var(--accent-blue);
        }

        .footer-bottom {
            max-width: 1400px;
            margin: 20px auto 0;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .footer-links-bottom {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .footer-links-bottom a {
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links-bottom a:hover {
            color: var(--accent-blue);
        }

        /* =========================================================
           NOTIFICATIONS
           ========================================================= */
        .admin-notif-container {
            position: fixed;
            top: 80px;
            right: 16px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-width: 360px;
            width: 100%;
            pointer-events: none;
        }

        /* Toast très visible : fond coloré vif + texte blanc (lisible en mode clair ET sombre) */
        .admin-notif-item {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            border-radius: var(--radius);
            padding: 14px 18px;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.45), 0 0 0 2px rgba(255, 255, 255, 0.85);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideInRight 0.4s cubic-bezier(0.22, 1, 0.36, 1);
            pointer-events: auto;
            border: none;
            max-width: 340px;
        }

        .admin-notif-item.like {
            background: linear-gradient(135deg, #ec4899, #be185d);
        }

        .admin-notif-item.commentaire {
            background: linear-gradient(135deg, #0891b2, #0e7490);
        }

        .admin-notif-item.message {
            background: linear-gradient(135deg, #7c3aed, #5b21b6);
        }

        .admin-notif-item .notif-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #fff;
            background: rgba(255, 255, 255, 0.25);
            flex-shrink: 0;
        }

        .admin-notif-item .notif-content {
            flex: 1;
            min-width: 0;
        }

        .admin-notif-item .notif-title {
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
        }

        .admin-notif-item .notif-message {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.95);
            line-height: 1.4;
        }

        .admin-notif-item .notif-time {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.75);
            margin-top: 2px;
        }

        .admin-notif-item .notif-close-btn {
            background: none;
            border: none;
            color: #ffffff;
            cursor: pointer;
            font-size: 12px;
            padding: 2px;
            transition: var(--transition);
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-notif-item .notif-close-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(60px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }

        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0) scale(1);
            }

            to {
                opacity: 0;
                transform: translateX(60px) scale(0.95);
            }
        }

        .admin-notif-item.removing {
            animation: slideOutRight 0.3s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        @media (max-width: 768px) {
            .admin-notif-container {
                top: 70px;
                right: 10px;
                max-width: calc(100% - 20px);
            }

            .admin-notif-item {
                max-width: 100%;
                padding: 12px 14px;
            }
        }

        /* =========================================================
           MODAL
           ========================================================= */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(6px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.25s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.96);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-container {
            background: #ffffff;
            border-radius: 18px;
            max-width: 650px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 28px 32px;
            animation: slideUp 0.3s cubic-bezier(0.22, 1, 0.36, 1);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
            position: relative;
            border: 1px solid #e2e8f0;
        }

        body.dark-mode .modal-container {
            background: #1e293b;
            border-color: #334155;
        }

        .modal-container::-webkit-scrollbar {
            width: 6px;
        }

        .modal-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        .modal-container::-webkit-scrollbar-thumb {
            background: #3b82f6;
            border-radius: 10px;
        }

        .modal-close {
            position: absolute;
            top: 14px;
            right: 16px;
            background: #f1f5f9;
            border: none;
            color: #64748b;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background: #fee2e2;
            color: #ef4444;
            transform: rotate(90deg);
        }

        .modal-title {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
            padding-right: 44px;
        }

        body.dark-mode .modal-title {
            color: #e2e8f0;
        }

        .modal-title i {
            color: #3b82f6;
            margin-right: 10px;
        }

        .modal-subtitle {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid #e2e8f0;
        }

        body.dark-mode .modal-subtitle {
            color: #94a3b8;
            border-color: #334155;
        }

        .modal-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
            margin: 12px 0 18px 0;
        }

        .modal-detail-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .modal-detail-item .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            font-weight: 700;
        }

        body.dark-mode .modal-detail-item .label {
            color: #64748b;
        }

        .modal-detail-item .value {
            font-size: 15px;
            font-weight: 500;
            color: #0f172a;
        }

        body.dark-mode .modal-detail-item .value {
            color: #e2e8f0;
        }

        .modal-detail-item .value i {
            margin-right: 6px;
            color: #3b82f6;
        }

        .modal-section-title {
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
            margin: 20px 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        body.dark-mode .modal-section-title {
            color: #e2e8f0;
            border-color: #334155;
        }

        .modal-section-title i {
            color: #3b82f6;
        }

        .modal-section-title span {
            background: #3b82f6;
            color: #fff;
            font-size: 11px;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .modal-film-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .modal-film-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        body.dark-mode .modal-film-item {
            background: #0f172a;
            border-color: #334155;
        }

        .modal-film-item:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        body.dark-mode .modal-film-item:hover {
            background: #1e293b;
            border-color: #60a5fa;
        }

        .modal-film-item .film-icon {
            color: #3b82f6;
            font-size: 16px;
            width: 32px;
            height: 32px;
            background: #dbeafe;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        body.dark-mode .modal-film-item .film-icon {
            background: #1e3a8a;
            color: #93bbfc;
        }

        .modal-film-item .film-name {
            font-weight: 600;
            color: #0f172a;
            flex: 1;
        }

        body.dark-mode .modal-film-item .film-name {
            color: #e2e8f0;
        }

        .modal-film-item .film-detail {
            font-size: 12px;
            color: #64748b;
        }

        body.dark-mode .modal-film-item .film-detail {
            color: #94a3b8;
        }

        .modal-empty {
            color: #94a3b8;
            font-style: italic;
            padding: 12px 0;
            text-align: center;
        }

        body.dark-mode .modal-empty {
            color: #64748b;
        }

        body.modal-open {
            overflow: hidden;
            position: fixed;
            width: 100%;
        }

        /* =========================================================
           RESPONSIVE MOBILE - DESIGN MODERNE
           ========================================================= */
        @media (max-width: 768px) {

            /* ===== BODY ===== */
            body {
                padding-top: 0 !important;
                padding-bottom: 70px !important;
                font-size: 15px !important;
                background: #f1f5f9 !important;
            }

            body.dark-mode {
                background: #0f172a !important;
            }

            .content {
                padding: 12px 10px 80px !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
            }

            /* ===== WELCOME ===== */
            .dashboard-welcome {
                padding: 18px 16px !important;
                border-radius: 14px !important;
                margin-bottom: 16px !important;
            }

            .dashboard-welcome h1 {
                font-size: 20px !important;
            }

            .dashboard-welcome h1 i {
                font-size: 22px !important;
            }

            .dashboard-welcome p {
                font-size: 13px !important;
            }

            .welcome-date {
                font-size: 12px !important;
                margin-top: 6px !important;
            }

            /* ===== STATS ===== */
            .stats {
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 8px !important;
                margin-bottom: 16px !important;
            }

            .stats .box {
                padding: 12px 6px !important;
                border-radius: 12px !important;
                min-height: 56px !important;
            }

            .stats .box h2 {
                font-size: 18px !important;
            }

            .stats .box p {
                font-size: 9px !important;
            }

            /* ===== SEARCH ===== */
            .search-modern {
                padding: 14px 14px !important;
                border-radius: 12px !important;
                margin-bottom: 16px !important;
            }

            .search-wrapper {
                flex-wrap: wrap !important;
                padding: 6px 10px !important;
                border-radius: 10px !important;
                gap: 6px !important;
            }

            .search-wrapper input {
                padding: 10px 6px !important;
                font-size: 14px !important;
                width: 100% !important;
                order: 1 !important;
                min-height: 44px !important;
                flex: 1 1 100% !important;
            }

            .search-wrapper i {
                order: 0 !important;
            }

            .search-wrapper .btn-search-modern {
                width: 100% !important;
                justify-content: center !important;
                padding: 10px !important;
                margin-top: 6px !important;
                order: 2 !important;
                font-size: 13px !important;
                min-height: 42px !important;
                border-radius: 10px !important;
            }

            /* ===== CARDS ===== */
            .card {
                padding: 14px 12px !important;
                border-radius: 12px !important;
                margin-bottom: 14px !important;
            }

            .section-header-actions {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 8px !important;
                margin-bottom: 10px !important;
            }

            .section-header-actions h2 {
                font-size: 16px !important;
                text-align: center !important;
                justify-content: center !important;
            }

            .section-header-actions .btn-sm {
                align-self: center !important;
                padding: 6px 16px !important;
                font-size: 13px !important;
                border-radius: 10px !important;
            }

            /* ===== TABLES ===== */
            .table-box {
                font-size: 12px !important;
            }

            thead th,
            tbody td {
                padding: 8px 10px !important;
            }

            table {
                min-width: 520px !important;
                font-size: 14px !important;
                border-radius: 12px !important;
                overflow: hidden !important;
            }

            table thead th {
                padding: 12px 12px !important;
                font-size: 12px !important;
                background: linear-gradient(135deg, #f1f5f9, #e9edf2) !important;
                color: #475569 !important;
                border-bottom: 2px solid #e2e8f0 !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                white-space: nowrap !important;
            }

            body.dark-mode table thead th {
                background: linear-gradient(135deg, #1e293b, #263340) !important;
                color: #94a3b8 !important;
                border-color: #334155 !important;
            }

            table tbody td {
                padding: 12px 12px !important;
                font-size: 14px !important;
                color: #0f172a !important;
                border-bottom: 1px solid #f1f5f9 !important;
                line-height: 1.5 !important;
                background: #ffffff !important;
            }

            body.dark-mode table tbody td {
                border-color: #334155 !important;
                color: #e2e8f0 !important;
                background: #1e293b !important;
            }

            table tbody tr:hover td {
                background: #f8fafc !important;
            }

            body.dark-mode table tbody tr:hover td {
                background: #0f172a !important;
            }

            table tbody tr:last-child td {
                border-bottom: none !important;
            }

            table tbody td:first-child {
                min-width: 100px !important;
                font-weight: 600 !important;
            }

            table tbody td:nth-child(3) {
                min-width: 110px !important;
            }

            table tbody td:last-child {
                min-width: 40px !important;
                text-align: right !important;
            }

            /* ===== ACTIONS TABLE ===== */
            table tbody td:last-child {
                display: flex !important;
                flex-wrap: nowrap !important;
                gap: 6px !important;
                padding: 10px 12px !important;
                min-width: 180px !important;
                align-items: center !important;
                justify-content: flex-start !important;
                white-space: nowrap !important;
            }

            table tbody td:last-child .btn,
            table tbody td:last-child .btn-view-row {
                font-size: 12px !important;
                padding: 5px 12px !important;
                border-radius: 8px !important;
                white-space: nowrap !important;
                flex: 0 0 auto !important;
                min-height: 34px !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 5px !important;
                font-weight: 600 !important;
                text-decoration: none !important;
                border: none !important;
                cursor: pointer !important;
                transition: all 0.3s ease !important;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
                color: #ffffff !important;
            }

            table tbody td:last-child .btn i,
            table tbody td:last-child .btn-view-row i {
                font-size: 12px !important;
            }

            table tbody td:last-child .btn-edit {
                background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            }

            table tbody td:last-child .btn-delete {
                background: linear-gradient(135deg, #ef4444, #dc2626) !important;
            }

            table tbody td:last-child .btn-view-row {
                background: linear-gradient(135deg, #06b6d4, #0891b2) !important;
            }

            table tbody td:last-child .btn-carte {
                background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important;
            }

            table tbody td .btn-view {
                background: linear-gradient(135deg, #10b981, #059669) !important;
                color: #ffffff !important;
                padding: 4px 12px !important;
                border-radius: 6px !important;
                font-size: 12px !important;
                font-weight: 600 !important;
                text-decoration: none !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 4px !important;
                border: none !important;
                cursor: pointer !important;
                transition: all 0.3s ease !important;
                white-space: nowrap !important;
            }

            table tbody td .badge {
                font-size: 12px !important;
                padding: 3px 12px !important;
                border-radius: 14px !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 4px !important;
                font-weight: 600 !important;
                white-space: nowrap !important;
            }

            table tbody td .badge-danger {
                background: #fee2e2 !important;
                color: #dc2626 !important;
                border: 1px solid #fecaca !important;
            }

            body.dark-mode table tbody td .badge-danger {
                background: #7f1d1d !important;
                color: #fca5a5 !important;
                border-color: #dc2626 !important;
            }

            /* ===== MODAL ===== */
            .modal-container {
                padding: 18px 16px !important;
                margin: 8px !important;
                border-radius: 14px !important;
            }

            .modal-title {
                font-size: 18px !important;
                padding-right: 40px !important;
            }

            .modal-detail-grid {
                grid-template-columns: 1fr !important;
                gap: 8px !important;
            }

            .modal-film-item {
                padding: 8px 10px !important;
                font-size: 12px !important;
                flex-wrap: wrap !important;
            }

            .modal-film-item .film-detail {
                width: 100% !important;
                padding-left: 44px !important;
                font-size: 11px !important;
            }

            /* ===== FOOTER ===== */
            .footer {
                padding: 20px 14px 80px !important;
                margin-top: 20px !important;
            }

            .footer .footer-content {
                grid-template-columns: 1fr !important;
                gap: 16px !important;
            }

            .footer .footer-brand p {
                max-width: 100% !important;
                font-size: 12px !important;
            }

            .footer .footer-col {
                border-bottom: 1px solid var(--border-color) !important;
                padding-bottom: 12px !important;
            }

            .footer .footer-col:last-child {
                border-bottom: none !important;
                padding-bottom: 0 !important;
            }

            .footer .footer-col h4 {
                font-size: 13px !important;
                margin-bottom: 8px !important;
            }

            .footer .footer-col ul li a {
                font-size: 12px !important;
                padding: 2px 0 !important;
            }

            .footer .footer-bottom {
                flex-direction: column !important;
                text-align: center !important;
                gap: 6px !important;
                font-size: 11px !important;
                padding-top: 12px !important;
                margin-top: 12px !important;
            }

            .footer-links-bottom {
                justify-content: center !important;
                gap: 12px !important;
            }

            .footer-links-bottom a {
                font-size: 11px !important;
            }

            .social-links {
                justify-content: center !important;
            }

            /* ===== CLICKABLE ROWS ===== */
            .clickable-row:active {
                background: rgba(59, 130, 246, 0.06) !important;
            }

            body.dark-mode .clickable-row:active {
                background: rgba(59, 130, 246, 0.12) !important;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 8px 6px 70px !important;
            }

            .dashboard-welcome {
                padding: 14px 12px !important;
            }

            .dashboard-welcome h1 {
                font-size: 17px !important;
            }

            .stats {
                gap: 5px !important;
            }

            .stats .box {
                padding: 8px 4px !important;
                border-radius: 10px !important;
                min-height: 44px !important;
            }

            .stats .box h2 {
                font-size: 15px !important;
            }

            .stats .box p {
                font-size: 8px !important;
            }

            table {
                font-size: 12px !important;
                min-width: 400px !important;
            }

            thead th,
            tbody td {
                padding: 6px 8px !important;
            }

            table tbody td:first-child {
                min-width: 80px !important;
            }

            table tbody td:nth-child(3) {
                min-width: 90px !important;
            }

            table tbody td:last-child {
                min-width: 140px !important;
                padding: 6px 8px !important;
                gap: 4px !important;
            }

            table tbody td:last-child .btn,
            table tbody td:last-child .btn-view-row {
                font-size: 10px !important;
                padding: 4px 8px !important;
                min-height: 28px !important;
            }

            table tbody td:last-child .btn i,
            table tbody td:last-child .btn-view-row i {
                font-size: 10px !important;
            }

            table tbody td .badge {
                font-size: 10px !important;
                padding: 2px 8px !important;
            }

            table tbody td .btn-view {
                font-size: 10px !important;
                padding: 3px 8px !important;
            }

            .modal-container {
                padding: 14px 12px !important;
                margin: 6px !important;
                border-radius: 12px !important;
            }

            .modal-title {
                font-size: 16px !important;
                padding-right: 36px !important;
            }

            .modal-detail-item .value {
                font-size: 13px !important;
            }

            .modal-close {
                top: 8px !important;
                right: 10px !important;
                width: 30px !important;
                height: 30px !important;
                font-size: 15px !important;
            }

            .modal-film-item {
                padding: 6px 8px !important;
                font-size: 11px !important;
            }

            .modal-film-item .film-icon {
                width: 24px !important;
                height: 24px !important;
                font-size: 12px !important;
            }

            .modal-film-item .film-detail {
                padding-left: 36px !important;
                font-size: 10px !important;
            }

            .modal-section-title {
                font-size: 13px !important;
            }

            .modal-section-title span {
                width: 18px !important;
                height: 18px !important;
                font-size: 9px !important;
            }

            .footer {
                padding: 16px 10px 75px !important;
            }

            .footer .footer-brand .logo-footer {
                font-size: 16px !important;
            }

            .footer .footer-brand p {
                font-size: 11px !important;
            }

            .footer .footer-col h4 {
                font-size: 12px !important;
            }

            .footer .footer-col ul li a {
                font-size: 11px !important;
            }

            .footer .footer-bottom p {
                font-size: 10px !important;
            }

            .footer .footer-bottom .footer-links a {
                font-size: 10px !important;
            }
        }
    </style>
</head>

<body>

    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="admin-notif-container" id="adminNotifContainer"></div>

    <!-- ===== CONTENU PRINCIPAL ===== -->
    <div class="content">

        <!-- WELCOME -->
        <div class="dashboard-welcome">
            <div>
                <h1><i class="fas fa-rocket"></i> Dashboard Admin</h1>
                <p>Bienvenue sur votre interface d'administration</p>
                <div class="welcome-date">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('l d F Y'); ?> ·
                    <?php echo date('H:i'); ?>
                </div>
            </div>
        </div>

        <!-- SEARCH -->
        <div class="search-modern">
            <div class="search-label">
                <i class="fas fa-search"></i> Recherche globale
                <span style="font-weight:400;color:var(--text-muted);font-size:11px;">(films, acteurs,
                    réalisateurs...)</span>
            </div>
            <form method="GET">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Rechercher..."
                        value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                    <button type="submit" class="btn-search-modern">
                        <i class="fas fa-arrow-right"></i> Rechercher
                    </button>
                </div>
            </form>

            <?php if (!empty($search)): ?>
                <div style="margin-top:14px;">
                    <div
                        style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                        <span style="font-size:13px;color:var(--text-secondary);">
                            <i class="fas fa-list"></i>
                            <?php echo $total_results; ?> résultat
                            <?php echo $total_results > 1 ? 's' : ''; ?>
                            pour "<strong style="color:var(--text-primary);">
                                <?php echo htmlspecialchars($search); ?>
                            </strong>"
                        </span>
                        <a href="dashboard.php"
                            style="color:var(--text-muted);text-decoration:none;font-size:16px;padding:2px 6px;border-radius:4px;">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>

                    <?php if ($total_results > 0): ?>
                        <div
                            style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:8px;margin-top:4px;">
                            <?php foreach ($results as $r):
                                $link = '../admin/';
                                $badge_color = '';
                                $badge_text = '';
                                $icon_class = '';

                                switch ($r['type']) {
                                    case 'film':
                                        $link .= 'film.php';
                                        $badge_color = '#3b82f6';
                                        $badge_text = 'Film';
                                        $icon_class = 'fa-film';
                                        break;
                                    case 'acteur':
                                        $link .= 'acteurs.php';
                                        $badge_color = '#8b5cf6';
                                        $badge_text = 'Acteur';
                                        $icon_class = 'fa-user';
                                        break;
                                    case 'realisateur':
                                        $link .= 'realisateur.php';
                                        $badge_color = '#06b6d4';
                                        $badge_text = 'Réalisateur';
                                        $icon_class = 'fa-user-tie';
                                        break;
                                    case 'categorie':
                                        $link .= 'categorie.php';
                                        $badge_color = '#f59e0b';
                                        $badge_text = 'Catégorie';
                                        $icon_class = 'fa-tag';
                                        break;
                                    case 'langue':
                                        $link .= 'langue.php';
                                        $badge_color = '#10b981';
                                        $badge_text = 'Langue';
                                        $icon_class = 'fa-language';
                                        break;
                                    case 'studio':
                                        $link .= 'studio.php';
                                        $badge_color = '#f472b6';
                                        $badge_text = 'Studio';
                                        $icon_class = 'fa-building';
                                        break;
                                    case 'pays':
                                        $link .= 'pays.php';
                                        $badge_color = '#ef4444';
                                        $badge_text = 'Pays';
                                        $icon_class = 'fa-globe';
                                        break;
                                    default:
                                        $link = '#';
                                        $badge_color = '#64748b';
                                        $badge_text = ucfirst($r['type']);
                                        $icon_class = 'fa-circle';
                                }
                                ?>
                                <a href="<?php echo $link; ?>"
                                    style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg-card);border-radius:10px;border:1px solid var(--border-color);text-decoration:none;color:var(--text-primary);transition:all 0.3s cubic-bezier(0.22, 1, 0.36, 1);">
                                    <span
                                        style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:<?php echo $badge_color; ?>;color:#fff;flex-shrink:0;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,0.15);">
                                        <i class="fas <?php echo $icon_class; ?>"></i>
                                    </span>
                                    <span style="font-weight:500;flex:1;font-size:14px;color:var(--text-primary);">
                                        <?php echo htmlspecialchars($r['nom']); ?>
                                    </span>
                                    <span
                                        style="font-size:10px;font-weight:700;padding:2px 10px;border-radius:12px;background:<?php echo $badge_color; ?>20;color:<?php echo $badge_color; ?>;border:1px solid <?php echo $badge_color; ?>40;white-space:nowrap;">
                                        <?php echo $badge_text; ?>
                                    </span>
                                    <?php if (!empty($r['details'])): ?>
                                        <span style="font-size:11px;color:var(--text-muted);margin-left:2px;">
                                            (
                                            <?php echo htmlspecialchars($r['details']); ?>)
                                        </span>
                                    <?php endif; ?>
                                    <span style="color:var(--text-muted);font-size:12px;margin-left:2px;">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center;padding:30px 0;color:var(--text-secondary);">
                            <i class="fas fa-search"
                                style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:10px;opacity:0.5;"></i>
                            <p style="font-size:15px;">Aucun résultat pour
                                "<strong style="color:var(--text-primary);">
                                    <?php echo htmlspecialchars($search); ?>
                                </strong>"</p>
                            <p style="font-size:13px;color:var(--text-muted);margin-top:4px;">Essayez avec d'autres mots-clés
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== STATS ===== -->
        <div class="stats">
            <a href="../admin/film.php" class="box">
                <h2>
                    <?php echo $nbFilms; ?>
                </h2>
                <p><i class="fas fa-film"></i> Films</p>
            </a>
            <a href="../admin/acteurs.php" class="box">
                <h2>
                    <?php echo $nbActeurs; ?>
                </h2>
                <p><i class="fas fa-user"></i> Acteurs</p>
            </a>
            <a href="../admin/realisateur.php" class="box">
                <h2>
                    <?php echo $nbReal; ?>
                </h2>
                <p><i class="fas fa-user-tie"></i> Réalis.</p>
            </a>
            <a href="../admin/langue.php" class="box">
                <h2>
                    <?php echo $nbLang; ?>
                </h2>
                <p><i class="fas fa-language"></i> Langues</p>
            </a>
            <a href="../admin/categorie.php" class="box">
                <h2>
                    <?php echo $nbCat; ?>
                </h2>
                <p><i class="fas fa-tags"></i> Catég.</p>
            </a>
            <a href="../admin/pays.php" class="box">
                <h2>
                    <?php echo $nbPays; ?>
                </h2>
                <p><i class="fas fa-globe"></i> Pays</p>
            </a>
            <a href="../admin/studio.php" class="box">
                <h2>
                    <?php echo $nbStudios; ?>
                </h2>
                <p><i class="fas fa-building"></i> Studios</p>
            </a>
            <a href="../admin/utilisateurs.php" class="box">
                <h2>
                    <?php echo $nbUtilisateurs; ?>
                </h2>
                <p><i class="fas fa-users"></i> Utilisateurs</p>
            </a>
        </div>

        <!-- TABLEAUX -->
        <!-- Films -->
        <div class="card">
            <div class="section-header-actions">
                <h2><i class="fas fa-film" style="color:#3b82f6;"></i> Derniers films</h2>
                <a href="../admin/film.php" class="btn-sm"><i class="fas fa-arrow-right"></i> Voir tous</a>
            </div>
            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>Film</th>
                            <th>Titre</th>
                            <th>Réalisateur</th>
                            <th>Langue</th>
                            <th>Catégorie</th>
                            <th style="width:30px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($films as $f): ?>
                            <tr class="clickable-row" data-type="film" data-id="<?php echo $f['id_film']; ?>"
                                data-nom="<?php echo htmlspecialchars($f['nom_film']); ?>"
                                data-titre="<?php echo htmlspecialchars($f['titre']); ?>"
                                data-realisateur="<?php echo htmlspecialchars($f['realisateur_complet']); ?>"
                                data-langue="<?php echo htmlspecialchars($f['nom_langue']); ?>"
                                data-categorie="<?php echo htmlspecialchars($f['nom_categorie']); ?>"
                                data-numero="<?php echo htmlspecialchars($f['film_numero']); ?>"
                                data-duree="<?php echo htmlspecialchars($f['duree'] ?? ''); ?>"
                                data-sortie="<?php echo htmlspecialchars($f['date_sortie'] ?? ''); ?>">
                                <td>
                                    <?php echo htmlspecialchars($f['nom_film']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($f['titre']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($f['realisateur_complet']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($f['nom_langue']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($f['nom_categorie']); ?>
                                </td>
                                <td><span class="row-indicator"><i class="fas fa-chevron-right"></i></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Acteurs -->
        <div class="card">
            <div class="section-header-actions">
                <h2><i class="fas fa-user" style="color:#8b5cf6;"></i> Derniers acteurs</h2>
                <a href="../admin/acteurs.php" class="btn-sm"><i class="fas fa-arrow-right"></i> Voir tous</a>
            </div>
            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Sexe</th>
                            <th style="width:30px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($acteurs as $a): ?>
                            <tr class="clickable-row" data-type="acteur" data-id="<?php echo $a['id_acteur']; ?>"
                                data-nom="<?php echo htmlspecialchars($a['acteur_complet']); ?>"
                                data-sexe="<?php echo htmlspecialchars($a['sexe']); ?>"
                                data-numero="<?php echo htmlspecialchars($a['acteur_numero']); ?>">
                                <td>
                                    <?php echo htmlspecialchars($a['acteur_complet']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($a['sexe']); ?>
                                </td>
                                <td><span class="row-indicator"><i class="fas fa-chevron-right"></i></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Réalisateurs -->
        <div class="card">
            <div class="section-header-actions">
                <h2><i class="fas fa-user-tie" style="color:#06b6d4;"></i> Derniers réalisateurs</h2>
                <a href="../admin/realisateur.php" class="btn-sm"><i class="fas fa-arrow-right"></i> Voir tous</a>
            </div>
            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Nationalité</th>
                            <th style="width:30px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($realisateurs as $r): ?>
                            <tr class="clickable-row" data-type="realisateur" data-id="<?php echo $r['id_realisateur']; ?>"
                                data-nom="<?php echo htmlspecialchars($r['realisateur_complet']); ?>"
                                data-nationalite="<?php echo htmlspecialchars($r['nationalite']); ?>"
                                data-numero="<?php echo htmlspecialchars($r['realisateur_numero']); ?>">
                                <td>
                                    <?php echo htmlspecialchars($r['realisateur_complet']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($r['nationalite']); ?>
                                </td>
                                <td><span class="row-indicator"><i class="fas fa-chevron-right"></i></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-brand">
                <div class="logo-footer">
                    <i class="fas fa-film"></i> BERTIN <span>MOVIE</span>
                </div>
                <p>Plateforme de gestion complète pour l'administration de votre bibliothèque de films. Gérez facilement
                    vos films, acteurs, réalisateurs et bien plus.</p>
                <div class="social-links">
                    <a href="https://github.com/iam-orly-bertin/" target="_blank" title="GitHub">
                        <i class="fab fa-github"></i>
                    </a>
                    <a href="https://www.facebook.com/profile.php?id=100084999655738" target="_blank" title="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://youtube.com/@bertintales?si=r2sxG8PzPQWP5Q-v" target="_blank" title="YouTube">
                        <i class="fab fa-youtube"></i>
                    </a>
                    <a href="https://www.instagram.com/i_am_orly_berthin/" target="_blank" title="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" title="Twitter (à venir)">
                        <i class="fab fa-twitter"></i>
                    </a>
                </div>
            </div>
            <div class="footer-col">
                <h4>📌 Navigation</h4>
                <ul>
                    <li><a href="/film_web_dy/admin/dashboard.php"><i class="fas fa-chevron-right"></i> Accueil</a></li>
                    <li><a href="/film_web_dy/admin/film.php"><i class="fas fa-chevron-right"></i> Films</a></li>
                    <li><a href="/film_web_dy/video.php"><i class="fas fa-chevron-right"></i> Vidéo</a></li>
                    <li><a href="/film_web_dy/admin/acteurs.php"><i class="fas fa-chevron-right"></i> Acteurs</a></li>
                    <li><a href="/film_web_dy/admin/realisateur.php"><i class="fas fa-chevron-right"></i>
                            Réalisateurs</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>⚙️ Administration</h4>
                <ul>
                    <li><a href="/film_web_dy/admin/categorie.php"><i class="fas fa-chevron-right"></i> Catégories</a>
                    </li>
                    <li><a href="/film_web_dy/admin/pays.php"><i class="fas fa-chevron-right"></i> Pays</a></li>
                    <li><a href="/film_web_dy/admin/langue.php"><i class="fas fa-chevron-right"></i> Langues</a></li>
                    <li><a href="/film_web_dy/admin/studio.php"><i class="fas fa-chevron-right"></i> Studios</a></li>
                    <li><a href="/film_web_dy/admin/utilisateurs.php"><i class="fas fa-chevron-right"></i>
                            Utilisateurs</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>👤 Profil</h4>
                <ul>
                    <li><a href="/film_web_dy/admin/profil.php"><i class="fas fa-chevron-right"></i> Voir profil</a>
                    </li>
                    <li><a href="/film_web_dy/admin/modifier_profil.php"><i class="fas fa-chevron-right"></i> Modifier
                            profil</a></li>
                    <li><a href="/film_web_dy/admin/modifier_motdepasse.php"><i class="fas fa-chevron-right"></i> Mot de
                            passe</a></li>
                    <li><a href="/film_web_dy/admin/historique.php"><i class="fas fa-chevron-right"></i> Historique</a>
                    </li>
                    <li><a href="/film_web_dy/index.php"><i class="fas fa-chevron-right"></i> Déconnexion</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy;
                <?php echo date('Y'); ?> <strong>BERTIN MOVIE</strong> - Tous droits réservés. Version 1.0
            </p>
            <div class="footer-links-bottom">
                <a href="#">Politique de confidentialité</a>
                <a href="#">Conditions d'utilisation</a>
                <a href="#">Contact</a>
                <a href="#">À propos</a>
            </div>
        </div>
    </footer>

    <!-- ===== MODAL ===== -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal-container">
            <button class="modal-close" id="modalClose"><i class="fas fa-times"></i></button>
            <div class="modal-title" id="modalTitle"><i class="fas fa-info-circle"></i> <span
                    id="modalTitleText">Titre</span></div>
            <div class="modal-subtitle" id="modalSubtitle">Détails</div>
            <div class="modal-detail-grid" id="modalDetails"></div>
            <div id="modalFilmsSection" style="display:none;">
                <div class="modal-section-title">
                    <i class="fas fa-film"></i> Films associés <span id="filmCount">0</span>
                </div>
                <div class="modal-film-list" id="modalFilmList"></div>
            </div>
        </div>
    </div>

    <script>
        // ============================================================
        // DONNÉES PRÉ-CHARGÉES
        // ============================================================
        const acteurFilmsData = <?php echo json_encode($acteurFilms); ?>;
        const realisateurFilmsData = <?php echo json_encode($realisateurFilms); ?>;

        // ============================================================
        // MODAL
        // ============================================================
        const modalOverlay = document.getElementById('detailModal');
        const modalClose = document.getElementById('modalClose');
        const modalTitleText = document.getElementById('modalTitleText');
        const modalSubtitle = document.getElementById('modalSubtitle');
        const modalDetails = document.getElementById('modalDetails');
        const modalFilmsSection = document.getElementById('modalFilmsSection');
        const modalFilmList = document.getElementById('modalFilmList');
        const filmCount = document.getElementById('filmCount');

        function closeModal() {
            modalOverlay.classList.remove('active');
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
        }

        modalClose.addEventListener('click', closeModal);
        modalOverlay.addEventListener('click', function (e) {
            if (e.target === modalOverlay) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });

        document.querySelectorAll('.clickable-row').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a')) return;
                const type = this.dataset.type;
                const id = this.dataset.id;
                const nom = this.dataset.nom || 'N/A';

                modalTitleText.textContent = nom;
                modalSubtitle.textContent = type === 'film' ? '📽️ Détails du film' :
                    type === 'acteur' ? '🎭 Détails de l\'acteur' : '🎬 Détails du réalisateur';

                let detailsHTML = '';
                if (type === 'film') {
                    detailsHTML = `
                        <div class="modal-detail-item"><span class="label">🎬 Titre</span><span class="value">${this.dataset.titre || 'N/A'}</span></div>
                        <div class="modal-detail-item"><span class="label">👨‍🎨 Réalisateur</span><span class="value">${this.dataset.realisateur || 'N/A'}</span></div>
                        <div class="modal-detail-item"><span class="label">🌐 Langue</span><span class="value">${this.dataset.langue || 'N/A'}</span></div>
                        <div class="modal-detail-item"><span class="label">🏷️ Catégorie</span><span class="value">${this.dataset.categorie || 'N/A'}</span></div>
                        <div class="modal-detail-item"><span class="label">🔢 Numéro</span><span class="value">${this.dataset.numero || 'N/A'}</span></div>
                        <div class="modal-detail-item"><span class="label">⏱️ Durée</span><span class="value">${this.dataset.duree || 'N/A'}</span></div>
                        <div class="modal-detail-item"><span class="label">📅 Sortie</span><span class="value">${this.dataset.sortie || 'N/A'}</span></div>
                    `;
                    modalFilmsSection.style.display = 'none';
                } else {
                    const extra = type === 'acteur' ? this.dataset.sexe : this.dataset.nationalite;
                    const label = type === 'acteur' ? '⚤ Sexe' : '🌍 Nationalité';
                    detailsHTML = `
                        <div class="modal-detail-item"><span class="label">${label}</span><span class="value">${extra || 'N/A'}</span></div>
                        <div class="modal-detail-item"><span class="label">🔢 Numéro</span><span class="value">${this.dataset.numero || 'N/A'}</span></div>
                    `;
                    const films = (type === 'acteur' ? acteurFilmsData : realisateurFilmsData)[id] || [];
                    modalFilmsSection.style.display = 'block';
                    if (films.length === 0) {
                        modalFilmList.innerHTML = '<div class="modal-empty">🎬 Aucun film associé</div>';
                        filmCount.textContent = '0';
                    } else {
                        let html = '';
                        films.forEach(function (f) {
                            html += `
                                <div class="modal-film-item">
                                    <span class="film-icon"><i class="fas fa-film"></i></span>
                                    <span class="film-name">${f.nom_film}</span>
                                    <span class="film-detail">${f.titre || ''}</span>
                                </div>
                            `;
                        });
                        modalFilmList.innerHTML = html;
                        filmCount.textContent = films.length;
                    }
                }
                modalDetails.innerHTML = detailsHTML;
                modalOverlay.classList.add('active');
                document.body.classList.add('modal-open');
                document.body.style.overflow = 'hidden';
            });
        });

        // ============================================================
        // NOTIFICATIONS
        // ============================================================
        class AdminNotificationSystem {
            constructor() {
                this.container = document.getElementById('adminNotifContainer');
                this.count = 0;
            }
            show(message, title, type, duration, href) {
                type = type || 'info';
                duration = duration || 6000;
                var id = 'admin-notif-' + Date.now() + '-' + (this.count++);
                var icons = { like: 'fa-heart', commentaire: 'fa-comment', message: 'fa-envelope', info: 'fa-info-circle' };
                var icon = icons[type] || icons.info;
                var notif = document.createElement('div');
                notif.className = 'admin-notif-item ' + type;
                if (href) {
                    notif.style.cursor = 'pointer';
                    notif.addEventListener('click', function (e) {
                        if (!e.target.closest('.notif-close-btn')) { window.location.href = href; }
                    });
                }
                notif.id = id;
                var time = new Date();
                var timeStr = time.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                notif.innerHTML = `
                    <div class="notif-icon ${type}"><i class="fas ${icon}"></i></div>
                    <div class="notif-content">
                        <div class="notif-title">${title}</div>
                        <div class="notif-message">${message}</div>
                        <div class="notif-time"><i class="fas fa-clock"></i> ${timeStr}</div>
                    </div>
                    <button class="notif-close-btn" onclick="window.adminNotifSystem.close('${id}')"><i class="fas fa-times"></i></button>
                `;
                this.container.appendChild(notif);
                if (duration > 0) setTimeout(function () { window.adminNotifSystem.close(id); }, duration);
                return id;
            }
            close(id) {
                var notif = document.getElementById(id);
                if (notif) {
                    notif.classList.add('removing');
                    setTimeout(function () { if (notif.parentNode) notif.parentNode.removeChild(notif); }, 400);
                }
            }
            esc(t) {
                var d = document.createElement('div');
                d.textContent = (t === null || t === undefined) ? '' : String(t);
                return d.innerHTML;
            }
            like(user, film, title) {
                title = title || '❤️ Nouveau like';
                return this.show(this.esc(user) + ' a aimé "' + this.esc(film) + '"', title, 'like', 6000);
            }
            message(user, title) {
                title = title || '✉️ Nouveau message';
                return this.show(this.esc(user) + ' vous a envoyé un message', title, 'message', 8000, '/film_web_dy/admin/messages.php');
            }
            commentaire(user, film, title) {
                title = title || '💬 Nouveau commentaire';
                return this.show(this.esc(user) + ' a commenté "' + this.esc(film) + '"', title, 'commentaire', 8000);
            }
        }

        if (typeof window.adminNotifSystem === 'undefined') {
            window.adminNotifSystem = new AdminNotificationSystem();
        }



        // 🔔 Notifications likes / commentaires : lues dans la base (table notifications_admin)
        function verifierNotificationsAdmin() {
            if (document.hidden) { return; }
            fetch('/film_web_dy/admin/check_notification.php', { credentials: 'same-origin', cache: 'no-store', headers: { 'X-BM-Background': '1' } })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) {
                    if (!data || !data.notifications) { return; }
                    data.notifications.forEach(function (n, i) {
                        setTimeout(function () {
                            if (n.type === 'like') window.adminNotifSystem.like(n.user, n.film);
                            else if (n.type === 'commentaire') window.adminNotifSystem.commentaire(n.user, n.film);
                            else if (n.type === 'message') window.adminNotifSystem.message(n.user);
                            else window.adminNotifSystem.show(window.adminNotifSystem.esc(n.message), n.title || 'Nouvelle activité');
                        }, i * 600);
                    });
                }).catch(function () { });
        }
        setTimeout(verifierNotificationsAdmin, 1000);   // à l'ouverture de la page
        setInterval(verifierNotificationsAdmin, 8000);  // puis toutes les 8 s

        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
        }
    </script>

</body>

</html>