<?php
require_once __DIR__ . '/../includes/session_boot.php';
include("../connexion.php");
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notification.php';
require_once __DIR__ . '/../includes/archive_lib.php';

/* =========================================================
   EXPORT CSV - AVEC NOTIFICATION
========================================================= */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sql = "SELECT f.*,
                   r.nom AS nom_realisateur,
                   r.prenom AS prenom_realisateur,
                   l.nom_langue,
                   c.nom_categorie,
                   v.id_video
            FROM film f
            LEFT JOIN realisateur r ON f.id_realisateur = r.id_realisateur
            LEFT JOIN langue l ON f.id_langue = l.id_langue
            LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
            LEFT JOIN video_film v ON f.id_film = v.id_film
            ORDER BY f.id_film DESC";
    $data = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'films_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, ['Nom', 'Titre', 'Durée', 'Date sortie', 'Réalisateur', 'Langue', 'Catégorie', 'Numéro']);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['nom_film'],
            $row['titre'],
            $row['duree'],
            $row['date_sortie'],
            trim($row['nom_realisateur'] . ' ' . $row['prenom_realisateur']),
            $row['nom_langue'],
            $row['nom_categorie'],
            $row['film_numero']
        ]);
    }

    fclose($output);

    addHistoriqueAction($pdo, $_SESSION['user_id'], 'export', 'film', null, null, "Export CSV des films effectué");
    showNotif('info', "Export CSV des films effectué avec succès");

    exit();
}

/* =========================================================
   SUPPRESSION INLINE - AVEC NOTIFICATION
========================================================= */
if (isset($_GET["supp"])) {
    $id = (int) $_GET["supp"];

    $stmt = $pdo->prepare("SELECT nom_film FROM film WHERE id_film = ?");
    $stmt->execute([$id]);
    $film = $stmt->fetch();
    $nom_film = $film['nom_film'] ?? 'ID ' . $id;

    $stmt = $pdo->prepare("DELETE FROM film WHERE id_film = ?");
    $stmt->execute([$id]);

    addHistoriqueAction($pdo, $_SESSION['user_id'], 'suppression', 'film', $id, $nom_film, "Film supprimé : $nom_film");
    showNotif('success', "Le film \"$nom_film\" a été supprimé avec succès");

    header("Location: film.php");
    exit();
}

/* =========================================================
   ARCHIVER / RESTAURER UN FILM (POST + jeton CSRF)
   Un film archivé disparaît du catalogue mais reste en base.
========================================================= */
if (empty($_SESSION['csrf_film'])) {
    $_SESSION['csrf_film'] = bin2hex(random_bytes(16));
}
$csrf_film = $_SESSION['csrf_film'];

$search = trim($_GET['search'] ?? '');
$sort = film_sort_key($_GET['sort'] ?? '', 'film');
$vue = (($_GET['vue'] ?? '') === 'archives') ? 'archives' : 'actifs';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_action'])) {
    $act = $_POST['archive_action'];
    $id = (int) ($_POST['id_film'] ?? 0);
    $vue_back = (($_POST['vue'] ?? '') === 'archives') ? 'archives' : 'actifs';
    $back = 'film.php?' . http_build_query([
        'search' => trim($_POST['search'] ?? ''),
        'sort' => film_sort_key($_POST['sort'] ?? '', 'film'),
        'vue' => $vue_back,
    ]);

    if (!isset($_POST['csrf']) || !hash_equals($csrf_film, (string) $_POST['csrf'])) {
        showNotif('error', "Action refusée (session expirée). Rechargez la page puis réessayez.");
    } elseif ($id > 0 && in_array($act, ['archiver', 'restaurer'], true)) {
        $stmt = $pdo->prepare("SELECT nom_film FROM film WHERE id_film = ?");
        $stmt->execute([$id]);
        $f = $stmt->fetch();
        if ($f) {
            if ($act === 'archiver') {
                $pdo->prepare("UPDATE film SET est_archive = 1, date_archivage = NOW() WHERE id_film = ?")->execute([$id]);
                addHistoriqueAction($pdo, $_SESSION['user_id'], 'archivage', 'film', $id, $f['nom_film'], "Film archivé : " . $f['nom_film']);
                showNotif('success', "Le film \"" . $f['nom_film'] . "\" a été archivé. Il n'apparaît plus dans le catalogue.");
            } else {
                $pdo->prepare("UPDATE film SET est_archive = 0, date_archivage = NULL WHERE id_film = ?")->execute([$id]);
                addHistoriqueAction($pdo, $_SESSION['user_id'], 'restauration', 'film', $id, $f['nom_film'], "Film restauré depuis les archives : " . $f['nom_film']);
                showNotif('success', "Le film \"" . $f['nom_film'] . "\" a été restauré dans le catalogue.");
            }
        }
    }
    header("Location: " . $back);
    exit();
}

/* =========================================================
   RECHERCHE + TRI (liste fixe de tris autorisés) + ONGLET
========================================================= */
$est_archive = ($vue === 'archives') ? 1 : 0;

$sql = "
SELECT f.*,
       r.nom AS nom_realisateur,
       r.prenom AS prenom_realisateur,
       l.nom_langue,
       c.nom_categorie,
       v.id_video,
       v.url_video,
       v.views
FROM film f
LEFT JOIN realisateur r ON f.id_realisateur = r.id_realisateur
LEFT JOIN langue l ON f.id_langue = l.id_langue
LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
LEFT JOIN video_film v ON f.id_film = v.id_film
WHERE (f.nom_film LIKE ? OR f.titre LIKE ?) AND f.est_archive = ?
ORDER BY " . film_sort_sql($sort, 'film') . "
";

$data = $pdo->prepare($sql);
$data->execute(["%$search%", "%$search%", $est_archive]);
$films = $data->fetchAll();

$nb = $pdo->query("SELECT COALESCE(SUM(est_archive = 0), 0) AS a, COALESCE(SUM(est_archive = 1), 0) AS b FROM film")->fetch();
$nb_actifs = (int) ($nb['a'] ?? 0);
$nb_archives = (int) ($nb['b'] ?? 0);

function film_url($vue, $search, $sort)
{
    return 'film.php?' . http_build_query(['search' => $search, 'sort' => $sort, 'vue' => $vue]);
}

$total = count($films);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Films</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* =========================================================
           MOBILE UNIQUEMENT - film.php
           ========================================================= */

        @media (max-width: 768px) {

            /* ===== CONTENT ===== */
            .content {
                padding: 8px 6px 80px !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
            }

            /* ===== PAGE HEADER ===== */
            .page-header {
                flex-direction: column !important;
                gap: 10px !important;
                margin-bottom: 12px !important;
            }

            .page-header h1 {
                font-size: 17px !important;
                text-align: center !important;
                margin: 0 !important;
            }

            .page-header h1 span {
                font-size: 13px !important;
            }

            .page-header>div {
                display: flex !important;
                flex-wrap: wrap !important;
                justify-content: center !important;
                gap: 8px !important;
                width: 100% !important;
            }

            .page-header .btn-add {
                padding: 10px 16px !important;
                font-size: 13px !important;
                border-radius: 10px !important;
                justify-content: center !important;
                min-height: 42px !important;
                flex: 1 !important;
                min-width: 100px !important;
                max-width: 200px !important;
                text-align: center !important;
                display: flex !important;
                align-items: center !important;
                gap: 6px !important;
            }

            .page-header .btn-add i {
                font-size: 14px !important;
            }

            /* ===== RECHERCHE ===== */
            .search-form {
                flex-direction: column !important;
                gap: 8px !important;
                max-width: 100% !important;
                width: 100% !important;
                margin-bottom: 12px !important;
            }

            .search-form input[type="text"] {
                width: 100% !important;
                padding: 11px 14px !important;
                font-size: 14px !important;
                border-radius: 10px !important;
                min-height: 44px !important;
            }

            .search-form button {
                width: 100% !important;
                padding: 11px !important;
                font-size: 14px !important;
                border-radius: 10px !important;
                justify-content: center !important;
                min-height: 44px !important;
            }

            /* ===== CARD ===== */
            .card {
                padding: 10px 4px !important;
                border-radius: 10px !important;
                margin-bottom: 10px !important;
                overflow: hidden !important;
            }

            /* =========================================================
               TABLES - TITRE SUR PLUSIEURS LIGNES, NUMÉROS ET BOUTONS SUR UNE LIGNE
               ========================================================= */
            .table-box {
                margin: 0 !important;
                border-radius: 8px !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                padding: 0 !important;
            }

            table {
                font-size: 14px !important;
                min-width: 720px !important;
                width: 100% !important;
                border-collapse: collapse !important;
            }

            table thead th {
                padding: 14px 12px !important;
                font-size: 12px !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                background: #f1f5f9 !important;
                color: #475569 !important;
                border-bottom: 2px solid #e2e8f0 !important;
                text-align: left !important;
                white-space: nowrap !important;
            }

            body.dark-mode table thead th {
                background: #1e293b !important;
                color: #94a3b8 !important;
                border-color: #334155 !important;
            }

            table tbody td {
                padding: 14px 12px !important;
                font-size: 14px !important;
                vertical-align: middle !important;
                border-bottom: 1px solid #f1f5f9 !important;
                color: #0f172a !important;
                line-height: 1.5 !important;
            }

            body.dark-mode table tbody td {
                border-color: #334155 !important;
                color: #e2e8f0 !important;
            }

            /* ===== COLONNES ===== */
            /* NOM - sur une ligne */
            table tbody td:first-child {
                min-width: 130px !important;
                max-width: 170px !important;
                font-weight: 600 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }

            /* TITRE - peut s'étendre sur plusieurs lignes */
            table tbody td:nth-child(2) {
                min-width: 140px !important;
                max-width: 200px !important;
                word-wrap: break-word !important;
                word-break: break-word !important;
                white-space: normal !important;
                line-height: 1.3 !important;
            }

            /* DURÉE - sur une ligne */
            table tbody td:nth-child(3) {
                min-width: 80px !important;
                max-width: 100px !important;
                text-align: center !important;
                white-space: nowrap !important;
            }

            /* DATE - sur une ligne */
            table tbody td:nth-child(4) {
                min-width: 100px !important;
                max-width: 120px !important;
                white-space: nowrap !important;
            }

            /* RÉALISATEUR - sur une ligne */
            table tbody td:nth-child(5) {
                min-width: 130px !important;
                max-width: 180px !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }

            /* LANGUE - sur une ligne */
            table tbody td:nth-child(6) {
                min-width: 90px !important;
                max-width: 120px !important;
                white-space: nowrap !important;
            }

            /* CATÉGORIE - sur une ligne */
            table tbody td:nth-child(7) {
                min-width: 100px !important;
                max-width: 130px !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }

            /* NUMÉRO - sur une ligne */
            table tbody td:nth-child(8) {
                min-width: 80px !important;
                max-width: 100px !important;
                text-align: center !important;
                white-space: nowrap !important;
            }

            /* VIDÉO - sur une ligne */
            table tbody td:nth-child(9) {
                min-width: 110px !important;
                max-width: 140px !important;
                text-align: center !important;
                white-space: nowrap !important;
            }

            /* =========================================================
               ACTIONS - BOUTONS EN LIGNE
               ========================================================= */
            table tbody td:last-child {
                min-width: 220px !important;
                max-width: 280px !important;
                padding: 10px 12px !important;
                display: flex !important;
                flex-wrap: nowrap !important;
                gap: 6px !important;
                align-items: center !important;
                justify-content: flex-start !important;
                white-space: nowrap !important;
            }

            table tbody td:last-child .btn,
            table tbody td:last-child .btn-view-row {
                font-size: 12px !important;
                padding: 5px 12px !important;
                border-radius: 6px !important;
                white-space: nowrap !important;
                flex: 0 0 auto !important;
                min-height: 34px !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 4px !important;
                font-weight: 600 !important;
                text-decoration: none !important;
                border: none !important;
                cursor: pointer !important;
                transition: all 0.3s ease !important;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08) !important;
            }

            table tbody td:last-child .btn i,
            table tbody td:last-child .btn-view-row i {
                font-size: 12px !important;
            }

            table tbody td:last-child .btn-edit {
                background: #3b82f6 !important;
                color: #ffffff !important;
            }

            table tbody td:last-child .btn-edit:hover {
                background: #2563eb !important;
                transform: translateY(-2px) !important;
            }

            table tbody td:last-child .btn-delete {
                background: #ef4444 !important;
                color: #ffffff !important;
            }

            table tbody td:last-child .btn-delete:hover {
                background: #dc2626 !important;
                transform: translateY(-2px) !important;
            }

            table tbody td .btn-view {
                background: #10b981 !important;
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

            table tbody td .btn-view:hover {
                background: #059669 !important;
                transform: translateY(-2px) !important;
            }

            /* ===== BADGES - sur une ligne ===== */
            table tbody td .badge {
                font-size: 12px !important;
                padding: 3px 12px !important;
                border-radius: 12px !important;
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

            /* =========================================================
               MODALS
               ========================================================= */
            .modal-overlay {
                padding: 8px !important;
            }

            .modal-container {
                padding: 16px 14px !important;
                margin: 8px !important;
                max-height: 92vh !important;
                border-radius: 14px !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            .modal-title {
                font-size: 17px !important;
                padding-right: 36px !important;
            }

            .modal-subtitle {
                font-size: 12px !important;
                padding-bottom: 10px !important;
                margin-bottom: 12px !important;
            }

            .modal-detail-grid {
                grid-template-columns: 1fr !important;
                gap: 6px !important;
            }

            .modal-detail-item .label {
                font-size: 9px !important;
            }

            .modal-detail-item .value {
                font-size: 14px !important;
            }

            .modal-film-item {
                padding: 6px 10px !important;
                font-size: 12px !important;
                flex-wrap: wrap !important;
                gap: 4px !important;
            }

            .modal-film-item .film-icon {
                width: 28px !important;
                height: 28px !important;
                font-size: 12px !important;
            }

            .modal-film-item .film-detail {
                width: 100% !important;
                padding-left: 38px !important;
                font-size: 11px !important;
            }

            .modal-close {
                top: 8px !important;
                right: 10px !important;
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .modal-section-title {
                font-size: 14px !important;
                margin-top: 12px !important;
            }

            .modal-section-title span {
                width: 20px !important;
                height: 20px !important;
                font-size: 10px !important;
            }

            #modalVideoContent .btn-view-row {
                font-size: 13px !important;
                padding: 8px 14px !important;
            }

            #modalVideoContent .no-video {
                padding: 12px !important;
                font-size: 12px !important;
            }

            #modalVideoContent .no-video i {
                font-size: 20px !important;
            }

            /* ===== CLICKABLE ROWS ===== */
            .clickable-row {
                cursor: pointer !important;
                -webkit-tap-highlight-color: transparent !important;
            }

            .clickable-row:active {
                background: rgba(59, 130, 246, 0.06) !important;
            }

            body.dark-mode .clickable-row:active {
                background: rgba(59, 130, 246, 0.12) !important;
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
                font-size: 15px !important;
            }

            .page-header h1 span {
                font-size: 12px !important;
            }

            .page-header>div {
                gap: 5px !important;
            }

            .page-header .btn-add {
                padding: 8px 12px !important;
                font-size: 12px !important;
                min-height: 36px !important;
                border-radius: 8px !important;
                min-width: 80px !important;
            }

            .page-header .btn-add i {
                font-size: 12px !important;
            }

            table {
                font-size: 13px !important;
                min-width: 620px !important;
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
                min-width: 100px !important;
                max-width: 130px !important;
            }

            table tbody td:nth-child(2) {
                min-width: 120px !important;
                max-width: 170px !important;
            }

            table tbody td:nth-child(5) {
                min-width: 100px !important;
                max-width: 130px !important;
            }

            table tbody td:nth-child(9) {
                min-width: 90px !important;
                max-width: 120px !important;
            }

            table tbody td:last-child {
                min-width: 180px !important;
                max-width: 220px !important;
                padding: 8px 8px !important;
                gap: 4px !important;
            }

            table tbody td:last-child .btn,
            table tbody td:last-child .btn-view-row {
                font-size: 10px !important;
                padding: 3px 8px !important;
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
                padding: 12px 10px !important;
                margin: 4px !important;
                border-radius: 10px !important;
            }

            .modal-title {
                font-size: 15px !important;
                padding-right: 32px !important;
            }

            .modal-subtitle {
                font-size: 11px !important;
            }

            .modal-detail-item .value {
                font-size: 13px !important;
            }

            .modal-close {
                top: 6px !important;
                right: 8px !important;
                width: 28px !important;
                height: 28px !important;
                font-size: 14px !important;
            }

            .modal-film-item {
                padding: 4px 6px !important;
                font-size: 11px !important;
            }

            .modal-film-item .film-icon {
                width: 22px !important;
                height: 22px !important;
                font-size: 11px !important;
            }

            .modal-film-item .film-detail {
                padding-left: 32px !important;
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

            #modalVideoContent .btn-view-row {
                font-size: 12px !important;
                padding: 6px 12px !important;
            }

            .search-form input[type="text"] {
                padding: 9px 12px !important;
                font-size: 13px !important;
                min-height: 38px !important;
            }

            .search-form button {
                padding: 9px !important;
                font-size: 13px !important;
                min-height: 38px !important;
            }
        }

        /* =========================================================
           TRI + ARCHIVAGE (ajout)
        ========================================================= */
        .ft-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 0 0 14px;
        }

        .ft-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            color: #64748b;
            background: #fff;
            border: 1px solid #e2e8f0;
            transition: all .2s;
        }

        .ft-tab:hover {
            color: #3b82f6;
            border-color: #3b82f6;
        }

        .ft-tab.on {
            color: #fff;
            border-color: transparent;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 6px 16px rgba(59, 130, 246, .3);
        }

        .ft-tab.on.arch {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 6px 16px rgba(245, 158, 11, .3);
        }

        .ft-tab .n {
            min-width: 22px;
            padding: 0 7px;
            border-radius: 11px;
            font-size: 12px;
            line-height: 20px;
            text-align: center;
            background: rgba(100, 116, 139, .15);
        }

        .ft-tab.on .n {
            background: rgba(255, 255, 255, .25);
        }

        body.dark-mode .ft-tab {
            background: #1e293b;
            border-color: #334155;
            color: #94a3b8;
        }

        body.dark-mode .ft-tab.on {
            color: #fff;
        }

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
            border-color: #3b82f6;
        }

        body.dark-mode .search-form .ft-sort select {
            background: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }

        .ft-inline {
            display: inline;
            margin: 0;
        }

        .btn.btn-archive,
        .btn.btn-restore {
            border: none;
            cursor: pointer;
            font-family: inherit;
            color: #fff;
        }

        .btn.btn-archive {
            background: #f59e0b;
        }

        .btn.btn-archive:hover {
            background: #d97706;
        }

        .btn.btn-restore {
            background: #10b981;
        }

        .btn.btn-restore:hover {
            background: #059669;
        }

        table tbody td:last-child .btn-archive,
        table tbody td:last-child .btn-restore {
            color: #fff !important;
        }

        .ft-arch-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(245, 158, 11, .15);
            color: #d97706;
        }

        .ft-empty {
            padding: 40px 16px;
            text-align: center;
            color: #64748b;
            font-size: 15px;
        }

        @media (max-width: 768px) {
            .ft-tab {
                flex: 1;
                justify-content: center;
                padding: 9px 10px;
                font-size: 13px;
            }

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

        <div class="page-header">
            <h1><i class="fas fa-film" style="color:#3b82f6;"></i> Gestion des Films <span>(
                    <?= $total ?>)
                </span></h1>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a class="btn-add" href="../formulaires/ajout_film.php">
                    <i class="fas fa-plus"></i> Ajouter
                </a>
                <a class="btn-add" href="archives.php" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <i class="fas fa-box-archive"></i> Archives ZIP
                </a>
                <a class="btn-add" href="film.php?export=csv"
                    style="background:linear-gradient(135deg,#10b981,#059669);">
                    <i class="fas fa-file-excel"></i> Exporter CSV
                </a>
            </div>
        </div>

        <div class="ft-tabs">
            <a class="ft-tab <?= $vue === 'actifs' ? 'on' : '' ?>"
                href="<?= htmlspecialchars(film_url('actifs', $search, $sort)) ?>">
                <i class="fas fa-film"></i> Catalogue <span class="n"><?= $nb_actifs ?></span>
            </a>
            <a class="ft-tab arch <?= $vue === 'archives' ? 'on' : '' ?>"
                href="<?= htmlspecialchars(film_url('archives', $search, $sort)) ?>">
                <i class="fas fa-box-archive"></i> Archivés <span class="n"><?= $nb_archives ?></span>
            </a>
        </div>

        <form method="GET" class="search-form">
            <input type="hidden" name="vue" value="<?= htmlspecialchars($vue) ?>">
            <input type="text" name="search" placeholder="Rechercher un film..."
                value="<?= htmlspecialchars($search) ?>">
            <label class="ft-sort">
                <i class="fas fa-arrow-down-wide-short" style="color:#3b82f6;"></i>
                <select name="sort" aria-label="Trier par" onchange="this.form.submit()">
                    <?= film_sort_select_options($sort, 'film') ?>
                </select>
            </label>
            <button type="submit"><i class="fas fa-search"></i> Rechercher</button>
        </form>

        <div class="card">
            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Titre</th>
                            <th>Durée</th>
                            <th>Date</th>
                            <th>Réalisateur</th>
                            <th>Langue</th>
                            <th>Catégorie</th>
                            <th>N°</th>
                            <th>Vidéo</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($films as $row): ?>
                            <tr class="clickable-row" data-id="<?= $row['id_film'] ?>"
                                data-nom="<?= htmlspecialchars($row['nom_film']) ?>"
                                data-titre="<?= htmlspecialchars($row['titre']) ?>" data-duree="<?= $row['duree'] ?>"
                                data-sortie="<?= date('d/m/Y', strtotime($row['date_sortie'])) ?>"
                                data-realisateur="<?= htmlspecialchars(trim($row['nom_realisateur'] . ' ' . $row['prenom_realisateur'])) ?>"
                                data-langue="<?= htmlspecialchars($row['nom_langue']) ?>"
                                data-categorie="<?= htmlspecialchars($row['nom_categorie']) ?>"
                                data-numero="<?= htmlspecialchars($row['film_numero']) ?>"
                                data-id-video="<?= $row['id_video'] ?? '' ?>"
                                data-url-video="<?= htmlspecialchars($row['url_video'] ?? '') ?>">
                                <td>
                                    <?= htmlspecialchars($row['nom_film']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['titre']) ?>
                                </td>
                                <td>
                                    <?= $row['duree'] ?>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($row['date_sortie'])) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(trim($row['nom_realisateur'] . ' ' . $row['prenom_realisateur'])) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['nom_langue']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['nom_categorie']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['film_numero']) ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['id_video']) && !empty($row['url_video'])): ?>
                                        <a href="../watch.php?id_video=<?= $row['id_video'] ?>" target="_blank"
                                            class="btn btn-view">
                                            <i class="fas fa-play"></i> Voir
                                        </a>
                                    <?php else: ?>
                                        <span class="badge badge-danger">
                                            <i class="fas fa-video-slash"></i> Aucune
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="btn btn-edit"
                                        href="../modifications/modifier_film.php?id=<?= $row['id_film'] ?>">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <form method="POST" class="ft-inline">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_film) ?>">
                                        <input type="hidden" name="id_film" value="<?= (int) $row['id_film'] ?>">
                                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                                        <input type="hidden" name="vue" value="<?= htmlspecialchars($vue) ?>">
                                        <?php if ($vue === 'archives'): ?>
                                            <button type="submit" name="archive_action" value="restaurer"
                                                class="btn btn-restore" title="Remettre ce film dans le catalogue">
                                                <i class="fas fa-rotate-left"></i> Restaurer
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="archive_action" value="archiver" class="btn btn-archive"
                                                title="Retirer du catalogue sans supprimer"
                                                onclick="return confirm('Archiver ce film ? Il disparaîtra du catalogue mais pourra être restauré.');">
                                                <i class="fas fa-box-archive"></i> Archiver
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <a class="btn btn-delete" href="#"
                                        onclick="confirmDelete(<?= $row['id_film'] ?>, 'film.php', 'Supprimer ce film ?')">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$films): ?>
                    <div class="ft-empty">
                        <?= $vue === 'archives' ? "Aucun film archivé." : "Aucun film trouvé." ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ===== MODAL FILM ===== -->
    <div class="modal-overlay" id="modalFilm">
        <div class="modal-container">
            <button class="modal-close" data-modal="modalFilm"><i class="fas fa-times"></i></button>
            <div class="modal-title"><i class="fas fa-info-circle"></i> <span class="modal-title-text">Détails du
                    film</span></div>
            <div class="modal-subtitle">📽️ Détails du film</div>
            <div class="modal-detail-grid" id="modalDetails"></div>
            <div id="modalVideoSection">
                <div class="modal-section-title">
                    <i class="fas fa-video"></i> Vidéo
                </div>
                <div id="modalVideoContent"></div>
            </div>
        </div>
    </div>

    <script>
        // ============================================================
        // GESTION DES MODALS - FILM
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

                var modal = document.getElementById('modalFilm');
                if (!modal) return;

                var data = row.dataset;

                var detailsContainer = modal.querySelector('#modalDetails');
                if (detailsContainer) {
                    detailsContainer.innerHTML =
                        '<div class="modal-detail-item"><span class="label">🎬 Nom</span><span class="value">' + (data.nom || 'N/A') + '</span></div>' +
                        '<div class="modal-detail-item"><span class="label">📝 Titre</span><span class="value">' + (data.titre || 'N/A') + '</span></div>' +
                        '<div class="modal-detail-item"><span class="label">👨‍🎨 Réalisateur</span><span class="value">' + (data.realisateur || 'N/A') + '</span></div>' +
                        '<div class="modal-detail-item"><span class="label">🌐 Langue</span><span class="value">' + (data.langue || 'N/A') + '</span></div>' +
                        '<div class="modal-detail-item"><span class="label">🏷️ Catégorie</span><span class="value">' + (data.categorie || 'N/A') + '</span></div>' +
                        '<div class="modal-detail-item"><span class="label">🔢 Numéro</span><span class="value">' + (data.numero || 'N/A') + '</span></div>' +
                        '<div class="modal-detail-item"><span class="label">⏱️ Durée</span><span class="value">' + (data.duree || 'N/A') + '</span></div>' +
                        '<div class="modal-detail-item"><span class="label">📅 Sortie</span><span class="value">' + (data.sortie || 'N/A') + '</span></div>';
                }

                var videoContent = modal.querySelector('#modalVideoContent');
                var idVideo = data.idVideo;
                var urlVideo = data.urlVideo;

                if (videoContent) {
                    if (idVideo && urlVideo) {
                        videoContent.innerHTML =
                            '<div style="display:flex;flex-direction:column;gap:10px;padding:4px 0;">' +
                            '<a href="../watch.php?id_video=' + idVideo + '" target="_blank" class="btn-view-row">' +
                            '<i class="fas fa-play" style="font-size:18px;"></i> Voir la vidéo' +
                            '</a>' +
                            '<span style="font-size:12px;color:#64748b;text-align:center;">' +
                            '<i class="fas fa-external-link-alt"></i> S\'ouvre dans un nouvel onglet' +
                            '</span>' +
                            '</div>';
                    } else {
                        videoContent.innerHTML =
                            '<div class="no-video">' +
                            '<i class="fas fa-video-slash"></i>' +
                            '<span style="font-weight:500;">Aucune vidéo associée à ce film</span>' +
                            '</div>';
                    }
                }

                var titleSpan = modal.querySelector('.modal-title-text');
                if (titleSpan) {
                    titleSpan.textContent = data.nom || 'Détails du film';
                }

                modal.classList.add('active');
                document.body.classList.add('modal-open');
                document.body.style.overflow = 'hidden';
            });

        })();
    </script>

</body>

</html>