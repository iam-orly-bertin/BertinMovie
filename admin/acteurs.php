<?php
require_once __DIR__ . '/../includes/session_boot.php';
include("../connexion.php");
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notification.php';

/* =========================================================
   EXPORT CSV - AVEC NOTIFICATION
========================================================= */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("SELECT * FROM acteur ORDER BY id_acteur DESC");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'acteurs_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, ['ID', 'Nom', 'Prénom', 'Sexe', 'Numéro']);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['id_acteur'],
            $row['nom'],
            $row['prenom'],
            $row['sexe'] == 'M' ? 'Masculin' : 'Féminin',
            $row['acteur_numero']
        ]);
    }

    fclose($output);

    addHistoriqueAction($pdo, $_SESSION['user_id'], 'export', 'acteur', null, null, "Export CSV des acteurs effectué");
    showNotif('info', "Export CSV des acteurs effectué avec succès");

    exit();
}

/* =========================================================
   SUPPRESSION INLINE - AVEC NOTIFICATION
========================================================= */
if (isset($_GET["supp"])) {
    $id = (int) $_GET["supp"];

    $stmt = $pdo->prepare("SELECT nom, prenom FROM acteur WHERE id_acteur = ?");
    $stmt->execute([$id]);
    $acteur = $stmt->fetch();
    $nom_acteur = ($acteur['prenom'] ?? '') . ' ' . ($acteur['nom'] ?? '');
    if (empty(trim($nom_acteur))) {
        $nom_acteur = 'ID ' . $id;
    }

    $stmt = $pdo->prepare("DELETE FROM acteur WHERE id_acteur = ?");
    $stmt->execute([$id]);

    addHistoriqueAction($pdo, $_SESSION['user_id'], 'suppression', 'acteur', $id, $nom_acteur, "Acteur supprimé : $nom_acteur");
    showNotif('succes', "L'acteur \"$nom_acteur\" a été supprimé avec succès");

    header("Location: acteurs.php");
    exit();
}

/* =========================================================
   RECHERCHE
========================================================= */
$search = $_GET['search'] ?? '';

/* TRI : liste fixe de tris autorisés (aucun SQL ne vient de l'URL) */
$tris_acteur = [
    'recent' => ['Plus récents', 'a.id_acteur DESC'],
    'ancien' => ['Plus anciens', 'a.id_acteur ASC'],
    'nom_asc' => ['Nom A → Z', 'a.nom ASC, a.prenom ASC, a.id_acteur DESC'],
    'nom_desc' => ['Nom Z → A', 'a.nom DESC, a.prenom DESC, a.id_acteur DESC'],
    'prenom_asc' => ['Prénom A → Z', 'a.prenom ASC, a.nom ASC, a.id_acteur DESC'],
    'prenom_desc' => ['Prénom Z → A', 'a.prenom DESC, a.nom DESC, a.id_acteur DESC'],
    'films_desc' => ['Plus de films', 'nb_films DESC, a.nom ASC, a.id_acteur DESC'],
];
$sort = (isset($_GET['sort']) && is_string($_GET['sort']) && isset($tris_acteur[$_GET['sort']])) ? $_GET['sort'] : 'recent';

function acteur_tri_url($search, $sort)
{
    return 'acteurs.php?' . http_build_query(['search' => $search, 'sort' => $sort]);
}

$sql = "SELECT a.*,
               (SELECT COUNT(*) FROM film_acteur fa2
                  JOIN film f2 ON f2.id_film = fa2.id_film
                 WHERE fa2.id_acteur = a.id_acteur AND (f2.is_deleted = 0 OR f2.is_deleted IS NULL)) AS nb_films
        FROM acteur a
        WHERE a.nom LIKE ? OR a.prenom LIKE ?
        ORDER BY " . $tris_acteur[$sort][1];
$stmt = $pdo->prepare($sql);
$stmt->execute(["%$search%", "%$search%"]);
$data = $stmt->fetchAll();

// Récupérer les films pour chaque acteur
$acteurFilms = [];
foreach ($data as $acteur) {
    $stmtFilms = $pdo->prepare("
        SELECT f.nom_film, f.titre 
        FROM film f
        JOIN film_acteur fa ON f.id_film = fa.id_film
        WHERE fa.id_acteur = ? AND (f.is_deleted = 0 OR f.is_deleted IS NULL)
        ORDER BY f.nom_film
    ");
    $stmtFilms->execute([$acteur['id_acteur']]);
    $acteurFilms[$acteur['id_acteur']] = $stmtFilms->fetchAll();
}

$total = count($data);
$counter = 0;
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Acteurs</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* =========================================================
           DESIGN MODERNE - MOBILE
           ========================================================= */

        @media (max-width: 768px) {

            /* ===== CONTENT ===== */
            .content {
                padding: 10px 6px 80px !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
            }

            /* =========================================================
               PAGE HEADER - DESIGN MODERNE
               ========================================================= */
            .page-header {
                flex-direction: column !important;
                gap: 10px !important;
                margin-bottom: 16px !important;
            }

            .page-header h1 {
                font-size: 18px !important;
                text-align: center !important;
                margin: 0 !important;
                font-weight: 700 !important;
                color: #0f172a !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 8px !important;
            }

            body.dark-mode .page-header h1 {
                color: #e2e8f0 !important;
            }

            .page-header h1 i {
                color: #8b5cf6 !important;
                font-size: 20px !important;
            }

            .page-header h1 span {
                font-size: 14px !important;
                color: #64748b !important;
                font-weight: 400 !important;
            }

            body.dark-mode .page-header h1 span {
                color: #94a3b8 !important;
            }

            .page-header>div {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 8px !important;
                width: 100% !important;
            }

            .page-header>div:has(.btn-add:nth-child(3)) {
                grid-template-columns: 1fr 1fr !important;
            }

            .page-header>div:has(.btn-add:nth-child(3)) .btn-add:nth-child(3) {
                grid-column: 1 / -1 !important;
            }

            .page-header .btn-add {
                padding: 12px 10px !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                border-radius: 12px !important;
                justify-content: center !important;
                min-height: 46px !important;
                width: 100% !important;
                text-align: center !important;
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                color: #ffffff !important;
                border: none !important;
                cursor: pointer !important;
                transition: all 0.3s ease !important;
                box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1) !important;
                text-decoration: none !important;
            }

            .page-header .btn-add i {
                font-size: 14px !important;
            }

            .page-header .btn-add:hover {
                transform: translateY(-2px) !important;
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15) !important;
            }

            .page-header .btn-add:active {
                transform: scale(0.97) !important;
            }

            /* ===== RECHERCHE ===== */
            .search-form {
                flex-direction: column !important;
                gap: 10px !important;
                max-width: 100% !important;
                width: 100% !important;
                margin-bottom: 14px !important;
            }

            .search-form input[type="text"] {
                width: 100% !important;
                padding: 14px 16px !important;
                font-size: 15px !important;
                border-radius: 12px !important;
                min-height: 48px !important;
                border: 2px solid #e2e8f0 !important;
                background: #ffffff !important;
                color: #0f172a !important;
                transition: all 0.3s ease !important;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04) !important;
            }

            body.dark-mode .search-form input[type="text"] {
                border-color: #334155 !important;
                background: #1e293b !important;
                color: #e2e8f0 !important;
            }

            .search-form input[type="text"]:focus {
                outline: none !important;
                border-color: #8b5cf6 !important;
                box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.12) !important;
            }

            .search-form input[type="text"]::placeholder {
                color: #94a3b8 !important;
            }

            .search-form button {
                width: 100% !important;
                padding: 14px !important;
                font-size: 15px !important;
                font-weight: 600 !important;
                border-radius: 12px !important;
                justify-content: center !important;
                min-height: 48px !important;
                background: #8b5cf6 !important;
                color: #ffffff !important;
                border: none !important;
                cursor: pointer !important;
                transition: all 0.3s ease !important;
                box-shadow: 0 4px 14px rgba(139, 92, 246, 0.3) !important;
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
            }

            .search-form button:hover {
                transform: translateY(-2px) !important;
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4) !important;
            }

            /* ===== CARD ===== */
            .card {
                padding: 12px 4px !important;
                border-radius: 14px !important;
                margin-bottom: 14px !important;
                overflow: hidden !important;
                background: #ffffff !important;
                border: 1px solid #e2e8f0 !important;
                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05) !important;
            }

            body.dark-mode .card {
                background: #1e293b !important;
                border-color: #334155 !important;
                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15) !important;
            }

            /* =========================================================
               TABLES - DESIGN MODERNE
               ========================================================= */
            .table-box {
                margin: 0 !important;
                border-radius: 12px !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                padding: 0 !important;
            }

            table {
                font-size: 14px !important;
                min-width: 580px !important;
                width: 100% !important;
                border-collapse: separate !important;
                border-spacing: 0 !important;
                border-radius: 12px !important;
                overflow: hidden !important;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important;
            }

            body.dark-mode table {
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2) !important;
            }

            table thead th {
                padding: 14px 12px !important;
                font-size: 12px !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                background: linear-gradient(135deg, #f1f5f9, #e9edf2) !important;
                color: #475569 !important;
                border-bottom: 2px solid #e2e8f0 !important;
                text-align: left !important;
                white-space: nowrap !important;
                position: sticky !important;
                top: 0 !important;
                z-index: 10 !important;
            }

            body.dark-mode table thead th {
                background: linear-gradient(135deg, #1e293b, #263340) !important;
                color: #94a3b8 !important;
                border-color: #334155 !important;
            }

            table tbody td {
                padding: 14px 12px !important;
                font-size: 14px !important;
                vertical-align: middle !important;
                word-break: break-word !important;
                border-bottom: 1px solid #f1f5f9 !important;
                color: #0f172a !important;
                line-height: 1.5 !important;
                background: #ffffff !important;
                transition: background 0.2s ease !important;
            }

            body.dark-mode table tbody td {
                border-color: #334155 !important;
                color: #e2e8f0 !important;
                background: #1e293b !important;
            }

            table tbody tr:last-child td {
                border-bottom: none !important;
            }

            table tbody tr:hover td {
                background: #f8fafc !important;
            }

            body.dark-mode table tbody tr:hover td {
                background: #0f172a !important;
            }

            /* ===== COLONNES ===== */
            table tbody td:first-child {
                min-width: 48px !important;
                max-width: 65px !important;
                text-align: center !important;
                white-space: nowrap !important;
                font-weight: 700 !important;
                color: #8b5cf6 !important;
            }

            table tbody td:nth-child(2) {
                min-width: 110px !important;
                max-width: 160px !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                font-weight: 500 !important;
            }

            table tbody td:nth-child(3) {
                min-width: 110px !important;
                max-width: 160px !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                font-weight: 500 !important;
            }

            table tbody td:nth-child(4) {
                min-width: 85px !important;
                max-width: 110px !important;
                white-space: nowrap !important;
            }

            table tbody td:nth-child(5) {
                min-width: 90px !important;
                max-width: 120px !important;
                white-space: nowrap !important;
                text-align: center !important;
                font-weight: 500 !important;
            }

            /* =========================================================
               ACTIONS - 3 BOUTONS MODERNES EN LIGNE
               ========================================================= */
            table tbody td:last-child {
                min-width: 260px !important;
                max-width: 330px !important;
                padding: 8px 12px !important;
                display: flex !important;
                flex-wrap: nowrap !important;
                gap: 6px !important;
                align-items: center !important;
                justify-content: flex-start !important;
                white-space: nowrap !important;
                background: #ffffff !important;
            }

            body.dark-mode table tbody td:last-child {
                background: #1e293b !important;
            }

            table tbody td:last-child .btn {
                font-size: 12px !important;
                padding: 6px 12px !important;
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

            table tbody td:last-child .btn i {
                font-size: 12px !important;
            }

            table tbody td:last-child .btn:hover {
                transform: translateY(-2px) !important;
                box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15) !important;
            }

            /* === BOUTON MODIFIER === */
            table tbody td:last-child .btn-edit {
                background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            }

            /* === BOUTON SUPPRIMER === */
            table tbody td:last-child .btn-delete {
                background: linear-gradient(135deg, #ef4444, #dc2626) !important;
            }

            /* === BOUTON CARTE === */
            table tbody td:last-child .btn-carte {
                background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important;
            }

            /* =========================================================
               MODALS - DESIGN MODERNE
               ========================================================= */
            .modal-overlay {
                padding: 10px !important;
            }

            .modal-container {
                padding: 20px 18px !important;
                margin: 8px !important;
                max-height: 92vh !important;
                border-radius: 16px !important;
                max-width: 100% !important;
                width: 100% !important;
                background: #ffffff !important;
                border: 1px solid #e2e8f0 !important;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
            }

            body.dark-mode .modal-container {
                background: #1e293b !important;
                border-color: #334155 !important;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5) !important;
            }

            .modal-title {
                font-size: 18px !important;
                font-weight: 700 !important;
                color: #0f172a !important;
                padding-right: 38px !important;
                display: flex !important;
                align-items: center !important;
                gap: 10px !important;
            }

            body.dark-mode .modal-title {
                color: #e2e8f0 !important;
            }

            .modal-title i {
                color: #8b5cf6 !important;
                font-size: 20px !important;
            }

            .modal-subtitle {
                font-size: 13px !important;
                color: #64748b !important;
                padding-bottom: 12px !important;
                margin-bottom: 14px !important;
                border-bottom: 2px solid #e2e8f0 !important;
            }

            body.dark-mode .modal-subtitle {
                color: #94a3b8 !important;
                border-color: #334155 !important;
            }

            .modal-detail-grid {
                grid-template-columns: 1fr !important;
                gap: 8px !important;
            }

            .modal-detail-item .label {
                font-size: 10px !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                color: #94a3b8 !important;
                font-weight: 700 !important;
            }

            body.dark-mode .modal-detail-item .label {
                color: #64748b !important;
            }

            .modal-detail-item .value {
                font-size: 15px !important;
                font-weight: 500 !important;
                color: #0f172a !important;
            }

            body.dark-mode .modal-detail-item .value {
                color: #e2e8f0 !important;
            }

            .modal-film-item {
                padding: 8px 12px !important;
                font-size: 13px !important;
                flex-wrap: wrap !important;
                gap: 6px !important;
                background: #f8fafc !important;
                border-radius: 10px !important;
                border: 1px solid #e2e8f0 !important;
                display: flex !important;
                align-items: center !important;
                transition: all 0.3s ease !important;
            }

            body.dark-mode .modal-film-item {
                background: #0f172a !important;
                border-color: #334155 !important;
            }

            .modal-film-item .film-icon {
                width: 30px !important;
                height: 30px !important;
                font-size: 13px !important;
                background: #ede9fe !important;
                border-radius: 50% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                flex-shrink: 0 !important;
                color: #8b5cf6 !important;
            }

            body.dark-mode .modal-film-item .film-icon {
                background: #1e293b !important;
                color: #a78bfa !important;
            }

            .modal-film-item .film-name {
                font-weight: 600 !important;
                color: #0f172a !important;
                flex: 1 !important;
                font-size: 14px !important;
            }

            body.dark-mode .modal-film-item .film-name {
                color: #e2e8f0 !important;
            }

            .modal-film-item .film-detail {
                font-size: 12px !important;
                color: #64748b !important;
                width: 100% !important;
                padding-left: 38px !important;
            }

            body.dark-mode .modal-film-item .film-detail {
                color: #94a3b8 !important;
            }

            .modal-close {
                top: 10px !important;
                right: 12px !important;
                width: 36px !important;
                height: 36px !important;
                font-size: 17px !important;
                border-radius: 50% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                background: #f1f5f9 !important;
                border: none !important;
                color: #64748b !important;
                cursor: pointer !important;
                transition: all 0.3s ease !important;
                position: absolute !important;
                z-index: 10 !important;
            }

            body.dark-mode .modal-close {
                background: #334155 !important;
                color: #94a3b8 !important;
            }

            .modal-close:hover {
                background: #fee2e2 !important;
                color: #ef4444 !important;
                transform: rotate(90deg) !important;
            }

            .modal-section-title {
                font-size: 15px !important;
                font-weight: 600 !important;
                color: #0f172a !important;
                margin-top: 16px !important;
                padding-bottom: 8px !important;
                border-bottom: 2px solid #e2e8f0 !important;
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
            }

            body.dark-mode .modal-section-title {
                color: #e2e8f0 !important;
                border-color: #334155 !important;
            }

            .modal-section-title i {
                color: #8b5cf6 !important;
            }

            .modal-section-title span {
                background: #8b5cf6 !important;
                color: #ffffff !important;
                font-size: 11px !important;
                border-radius: 50% !important;
                width: 22px !important;
                height: 22px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-weight: 700 !important;
            }

            .modal-empty {
                color: #94a3b8 !important;
                font-style: italic !important;
                padding: 12px 0 !important;
                text-align: center !important;
                font-size: 13px !important;
            }

            body.dark-mode .modal-empty {
                color: #64748b !important;
            }

            /* ===== CLICKABLE ROWS ===== */
            .clickable-row {
                cursor: pointer !important;
                -webkit-tap-highlight-color: transparent !important;
                transition: all 0.15s ease !important;
            }

            .clickable-row:active {
                background: rgba(139, 92, 246, 0.06) !important;
            }

            body.dark-mode .clickable-row:active {
                background: rgba(139, 92, 246, 0.10) !important;
            }

            body.modal-open {
                overflow: hidden !important;
                position: fixed !important;
                width: 100% !important;
            }
        }

        @media (max-width: 480px) {

            .content {
                padding: 6px 4px 70px !important;
            }

            .page-header h1 {
                font-size: 16px !important;
            }

            .page-header h1 span {
                font-size: 12px !important;
            }

            .page-header>div {
                gap: 5px !important;
            }

            .page-header .btn-add {
                padding: 8px 6px !important;
                font-size: 12px !important;
                min-height: 38px !important;
                border-radius: 10px !important;
            }

            .page-header .btn-add i {
                font-size: 12px !important;
            }

            table {
                font-size: 13px !important;
                min-width: 480px !important;
            }

            table thead th {
                padding: 10px 8px !important;
                font-size: 10px !important;
            }

            table tbody td {
                padding: 10px 8px !important;
                font-size: 13px !important;
            }

            table tbody td:first-child {
                min-width: 40px !important;
                max-width: 55px !important;
            }

            table tbody td:nth-child(2) {
                min-width: 90px !important;
                max-width: 130px !important;
            }

            table tbody td:nth-child(3) {
                min-width: 90px !important;
                max-width: 130px !important;
            }

            table tbody td:nth-child(4) {
                min-width: 70px !important;
                max-width: 95px !important;
            }

            table tbody td:nth-child(5) {
                min-width: 75px !important;
                max-width: 100px !important;
            }

            table tbody td:last-child {
                min-width: 220px !important;
                max-width: 280px !important;
                padding: 6px 8px !important;
                gap: 4px !important;
            }

            table tbody td:last-child .btn {
                font-size: 10px !important;
                padding: 4px 8px !important;
                min-height: 28px !important;
            }

            table tbody td:last-child .btn i {
                font-size: 10px !important;
            }

            .modal-container {
                padding: 14px 12px !important;
                margin: 6px !important;
                border-radius: 14px !important;
            }

            .modal-title {
                font-size: 16px !important;
                padding-right: 34px !important;
            }

            .modal-subtitle {
                font-size: 12px !important;
            }

            .modal-detail-item .value {
                font-size: 14px !important;
            }

            .modal-close {
                top: 8px !important;
                right: 10px !important;
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .modal-film-item {
                padding: 6px 10px !important;
                font-size: 12px !important;
            }

            .modal-film-item .film-icon {
                width: 26px !important;
                height: 26px !important;
                font-size: 12px !important;
            }

            .modal-film-item .film-name {
                font-size: 13px !important;
            }

            .modal-film-item .film-detail {
                font-size: 11px !important;
                padding-left: 34px !important;
            }

            .modal-section-title {
                font-size: 14px !important;
            }

            .modal-section-title span {
                width: 20px !important;
                height: 20px !important;
                font-size: 10px !important;
            }

            .search-form input[type="text"] {
                padding: 10px 12px !important;
                font-size: 14px !important;
                min-height: 42px !important;
            }

            .search-form button {
                padding: 10px !important;
                font-size: 14px !important;
                min-height: 42px !important;
            }
        }

        /* =========================================================
           TRI (ajout) : menu « Trier par » + en-têtes cliquables
        ========================================================= */
        .search-form .ft-sort {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .search-form .ft-sort select {
            height: 44px;
            padding: 0 14px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            background: #fff;
            color: #0f172a;
            font-size: 14px;
            font-family: inherit;
            cursor: pointer;
            outline: none;
        }

        .search-form .ft-sort select:focus {
            border-color: #8b5cf6;
        }

        body.dark-mode .search-form .ft-sort select {
            background: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }

        .th-sort {
            color: inherit;
            text-decoration: none;
            white-space: nowrap;
        }

        .th-sort i {
            margin-left: 4px;
            font-size: 11px;
            opacity: .55;
        }

        .th-sort:hover i {
            opacity: 1;
            color: #8b5cf6;
        }

        .tri-nbf {
            display: inline-block;
            margin-left: 8px;
            padding: 1px 9px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            color: #8b5cf6;
            background: rgba(139, 92, 246, .12);
            vertical-align: middle;
        }

        @media (max-width: 768px) {
            .search-form {
                flex-wrap: wrap !important;
            }

            .search-form .ft-sort {
                width: 100%;
            }

            .search-form .ft-sort select {
                flex: 1;
                width: 100%;
                font-size: 16px;
            }
        }
    </style>

</head>

<body>

    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="content">

        <!-- ===== HEADER ===== -->
        <div class="page-header">
            <h1>
                <i class="fas fa-users"></i>
                Gestion des Acteurs
                <span>(<?= $total ?>)</span>
            </h1>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a class="btn-add" href="../formulaires/ajout_acteur.php"
                    style="background:linear-gradient(135deg, #8b5cf6, #7c3aed);">
                    <i class="fas fa-plus"></i> Ajouter
                </a>
                <a class="btn-add" href="acteurs.php?export=csv"
                    style="background:linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-file-excel"></i> Exporter CSV
                </a>
                <a class="btn-add" href="../admin/carte_acteur.php"
                    style="background:linear-gradient(135deg, #7c3aed, #6d28d9);">
                    <i class="fas fa-id-card"></i> Carte
                </a>
            </div>
        </div>

        <!-- ===== RECHERCHE ===== -->
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="🔍 Rechercher un acteur..."
                value="<?= htmlspecialchars($search) ?>">
            <label class="ft-sort">
                <i class="fas fa-arrow-down-wide-short" style="color:#8b5cf6;"></i>
                <select name="sort" aria-label="Trier par" onchange="this.form.submit()">
                    <?php foreach ($tris_acteur as $k => $t): ?>
                        <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= htmlspecialchars($t[0]) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit"><i class="fas fa-search"></i> Rechercher</button>
        </form>

        <!-- ===== TABLEAU ===== -->
        <div class="card">
            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><a class="th-sort"
                                    href="<?= htmlspecialchars(acteur_tri_url($search, $sort === 'nom_asc' ? 'nom_desc' : 'nom_asc')) ?>">Nom
                                    <i
                                        class="fas <?= $sort === 'nom_asc' ? 'fa-sort-up' : ($sort === 'nom_desc' ? 'fa-sort-down' : 'fa-sort') ?>"></i></a>
                            </th>
                            <th><a class="th-sort"
                                    href="<?= htmlspecialchars(acteur_tri_url($search, $sort === 'prenom_asc' ? 'prenom_desc' : 'prenom_asc')) ?>">Prénom
                                    <i
                                        class="fas <?= $sort === 'prenom_asc' ? 'fa-sort-up' : ($sort === 'prenom_desc' ? 'fa-sort-down' : 'fa-sort') ?>"></i></a>
                            </th>
                            <th>Sexe</th>
                            <th>Numéro</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <?php $counter++; ?>
                            <tr class="clickable-row" data-id="<?= $row['id_acteur'] ?>"
                                data-nom="<?= htmlspecialchars($row['nom']) ?>"
                                data-prenom="<?= htmlspecialchars($row['prenom']) ?>"
                                data-complet="<?= htmlspecialchars($row['prenom'] . ' ' . $row['nom']) ?>"
                                data-sexe="<?= $row['sexe'] == 'M' ? 'Masculin' : 'Féminin' ?>"
                                data-numero="<?= htmlspecialchars($row['acteur_numero']) ?>">
                                <td><?= $counter ?></td>
                                <td><?= htmlspecialchars($row['nom']) ?><?php if ($sort === 'films_desc'): ?><span
                                            class="tri-nbf"><?= (int) $row['nb_films'] ?>
                                            film<?= (int) $row['nb_films'] > 1 ? 's' : '' ?></span><?php endif; ?></td>
                                <td><?= htmlspecialchars($row['prenom']) ?></td>
                                <td><?= $row['sexe'] == 'M' ? 'Masculin' : 'Féminin' ?></td>
                                <td><?= htmlspecialchars($row['acteur_numero']) ?></td>
                                <td>
                                    <a class="btn btn-edit"
                                        href="../modifications/modifier_acteur.php?id=<?= $row['id_acteur'] ?>">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a class="btn btn-delete" href="#"
                                        onclick="confirmDelete(<?= $row['id_acteur'] ?>, 'acteurs.php', 'Supprimer cet acteur ?')">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </a>
                                    <a class="btn btn-carte" href="../admin/carte_acteur.php?id=<?= $row['id_acteur'] ?>">
                                        <i class="fas fa-id-card"></i> Carte
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ===== MODAL ACTEUR ===== -->
    <div class="modal-overlay" id="modalActeur">
        <div class="modal-container">
            <button class="modal-close" data-modal="modalActeur"><i class="fas fa-times"></i></button>
            <div class="modal-title"><i class="fas fa-user"></i> <span class="modal-title-text">Détails de
                    l'acteur</span></div>
            <div class="modal-subtitle">🎭 Détails de l'acteur</div>
            <div class="modal-detail-grid" id="modalDetails"></div>
            <div id="modalFilmsSection">
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
        var acteurFilmsData = <?php echo json_encode($acteurFilms); ?>;

        // ============================================================
        // GESTION DES MODALS - ACTEUR
        // ============================================================
        (function () {
            'use strict';

            function closeModal(modalId) {
                var modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.remove('active');
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                }
            }

            function closeAllModals() {
                document.querySelectorAll('.modal-overlay.active').forEach(function (modal) {
                    modal.classList.remove('active');
                });
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
            }

            document.querySelectorAll('.modal-close').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var modalId = this.dataset.modal;
                    closeModal(modalId);
                });
            });

            document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
                overlay.addEventListener('click', function (e) {
                    if (e.target === this) {
                        closeModal(this.id);
                    }
                });
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeAllModals();
                }
            });

            document.addEventListener('click', function (e) {
                var row = e.target.closest('.clickable-row');
                if (!row) return;
                if (e.target.closest('a') || e.target.closest('button')) return;

                var modal = document.getElementById('modalActeur');
                if (!modal) return;

                var data = row.dataset;
                var id = data.id;

                var detailsContainer = modal.querySelector('#modalDetails');
                if (detailsContainer) {
                    detailsContainer.innerHTML =
                        '<div class="modal-detail-item"><span class="label">👤 Nom complet</span><span class="value">' + (data.complet || 'N/A') + '</span></div>' +
                        '<div class="modal-detail-item"><span class="label">⚤ Sexe</span><span class="value">' + (data.sexe || 'N/A') + '</span></div>' +
                        '<div class="modal-detail-item"><span class="label">🔢 Numéro</span><span class="value">' + (data.numero || 'N/A') + '</span></div>';
                }

                var filmList = modal.querySelector('#modalFilmList');
                var filmCount = modal.querySelector('#filmCount');
                var films = acteurFilmsData[id] || [];

                if (filmList && filmCount) {
                    if (films.length === 0) {
                        filmList.innerHTML = '<div class="modal-empty">🎬 Aucun film associé</div>';
                        filmCount.textContent = '0';
                    } else {
                        var html = '';
                        films.forEach(function (f) {
                            html +=
                                '<div class="modal-film-item">' +
                                '<span class="film-icon"><i class="fas fa-film"></i></span>' +
                                '<span class="film-name">' + f.nom_film + '</span>' +
                                '<span class="film-detail">' + (f.titre || '') + '</span>' +
                                '</div>';
                        });
                        filmList.innerHTML = html;
                        filmCount.textContent = films.length;
                    }
                }

                var titleSpan = modal.querySelector('.modal-title-text');
                if (titleSpan) {
                    titleSpan.textContent = data.complet || data.nom || 'Détails de l\'acteur';
                }

                modal.classList.add('active');
                document.body.classList.add('modal-open');
                document.body.style.overflow = 'hidden';
            });

        })();
    </script>

</body>

</html>