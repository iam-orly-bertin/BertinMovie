<?php
require_once __DIR__ . '/../includes/session_boot.php';
// connexion.php AVANT permissions.php : require_permission() a besoin de $pdo pour lire les droits
require_once __DIR__ . '/../connexion.php';

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/permissions.php';
require_permission('voir_historique');
require_once __DIR__ . '/../includes/update_session.php';
require_once __DIR__ . '/../includes/notification.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

// Vérifier et créer la table
try {
    $table_exists = $pdo->query("SHOW TABLES LIKE 'historique_actions'")->rowCount() > 0;
    if (!$table_exists) {
        $pdo->exec("
            CREATE TABLE historique_actions (
                id_action INT PRIMARY KEY AUTO_INCREMENT,
                id_utilisateur INT NOT NULL,
                type_action VARCHAR(50) NOT NULL,
                entite VARCHAR(50) NOT NULL,
                entite_id INT NULL,
                nom_entite VARCHAR(255) NULL,
                details TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                lu TINYINT(1) DEFAULT 0,
                date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_utilisateur (id_utilisateur),
                INDEX idx_type (type_action),
                INDEX idx_entite (entite),
                INDEX idx_date (date_action),
                INDEX idx_lu (lu)
            )
        ");
    } else {
        $columns = $pdo->query("SHOW COLUMNS FROM historique_actions LIKE 'lu'")->fetchAll();
        if (empty($columns)) {
            $pdo->exec("ALTER TABLE historique_actions ADD COLUMN lu TINYINT(1) DEFAULT 0");
            $pdo->exec("ALTER TABLE historique_actions ADD INDEX idx_lu (lu)");
        }
    }
} catch (PDOException $e) {
}

// Actions
if (isset($_GET['mark_all_read']) && isset($_SESSION['user_id'])) {
    if (function_exists('marquerToutesNotificationsLues')) {
        marquerToutesNotificationsLues($pdo, $_SESSION['user_id']);
        showNotif('success', "Toutes les notifications ont été marquées comme lues");
    }
    header("Location: historique_actions.php");
    exit();
}

if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $id_action = (int) $_GET['id'];
    if (function_exists('marquerNotificationLue')) {
        marquerNotificationLue($pdo, $id_action);
        showNotif('success', "Notification marquée comme lue");
    }
    header("Location: historique_actions.php");
    exit();
}

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id_action = (int) $_GET['id'];
    try {
        $pdo->prepare("DELETE FROM historique_actions WHERE id_action = ?")->execute([$id_action]);
        showNotif('success', "Notification supprimée avec succès");
    } catch (PDOException $e) {
        showNotif('error', "Erreur lors de la suppression de la notification");
    }
    header("Location: historique_actions.php");
    exit();
}

// Filtres
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$entite_filter = isset($_GET['entite']) ? trim($_GET['entite']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_debut = isset($_GET['date_debut']) ? trim($_GET['date_debut']) : '';
$date_fin = isset($_GET['date_fin']) ? trim($_GET['date_fin']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where = ['1=1'];
$params = [];

if (!empty($type_filter)) {
    $where[] = "ha.type_action = :type";
    $params[':type'] = $type_filter;
}
if (!empty($entite_filter)) {
    $where[] = "ha.entite = :entite";
    $params[':entite'] = $entite_filter;
}
if (!empty($search)) {
    $where[] = "(ha.nom_entite LIKE :search OR u.nom_utilisateur LIKE :search OR ha.details LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($date_debut)) {
    $where[] = "DATE(ha.date_action) >= :date_debut";
    $params[':date_debut'] = $date_debut;
}
if (!empty($date_fin)) {
    $where[] = "DATE(ha.date_action) <= :date_fin";
    $params[':date_fin'] = $date_fin;
}
if (!empty($status_filter)) {
    if ($status_filter === 'lu') {
        $where[] = "ha.lu = 1";
    } elseif ($status_filter === 'non_lu') {
        $where[] = "ha.lu = 0";
    }
}

$where_clause = implode(" AND ", $where);

// Total
$count_sql = "
    SELECT COUNT(*) 
    FROM historique_actions ha
    INNER JOIN utilisateur u ON ha.id_utilisateur = u.id_utilisateur
    WHERE $where_clause
";
$count_stmt = $pdo->prepare($count_sql);
foreach ($params as $key => $val) {
    $count_stmt->bindValue($key, $val);
}
$count_stmt->execute();
$total = (int) $count_stmt->fetchColumn();
$total_pages = (int) ceil($total / $limit);

// Récupération
$sql = "
    SELECT ha.*, u.nom_utilisateur, u.prenom, u.nom
    FROM historique_actions ha
    INNER JOIN utilisateur u ON ha.id_utilisateur = u.id_utilisateur
    WHERE $where_clause
    ORDER BY ha.date_action DESC, ha.lu ASC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll();

// Non lues
$non_lues = 0;
if (isset($_SESSION['user_id']) && function_exists('getNotificationsNonLues')) {
    try {
        $non_lues = getNotificationsNonLues($pdo, $_SESSION['user_id']);
    } catch (Exception $e) {
    }
}

try {
    $types = $pdo->query("SELECT DISTINCT type_action FROM historique_actions ORDER BY type_action")->fetchAll(PDO::FETCH_COLUMN);
    $entites = $pdo->query("SELECT DISTINCT entite FROM historique_actions ORDER BY entite")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $types = [];
    $entites = [];
}

/* =========================================================
   AIDES D'AFFICHAGE (design uniquement)
========================================================= */
function hx_day_label($ymd)
{
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    if ($ymd === date('Y-m-d')) {
        return "Aujourd'hui";
    }
    if ($ymd === date('Y-m-d', strtotime('-1 day'))) {
        return 'Hier';
    }
    $t = strtotime($ymd);
    $label = ucfirst($jours[(int) date('w', $t)]) . ' ' . (int) date('j', $t) . ' ' . $mois[(int) date('n', $t) - 1];
    if (date('Y', $t) !== date('Y')) {
        $label .= ' ' . date('Y', $t);
    }
    return $label;
}

function hx_url($p)
{
    return '?' . http_build_query(array_merge($_GET, ['page' => $p]));
}

$active_filters = count(array_filter([$type_filter, $entite_filter, $status_filter, $date_debut, $date_fin], function ($v) {
    return $v !== '';
}));
$has_filters = $active_filters > 0 || $search !== '';
$from = $total ? $offset + 1 : 0;
$to = min($offset + $limit, $total);
$last_day = null;
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des actions - Bertin Movie</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* =========================================================
           HISTORIQUE DES ACTIONS — DESIGN PRO (mêmes couleurs)
           Classes préfixées "hx-" : aucun conflit avec style.css
        ========================================================= */
        .hx-wrap {
            --hx-bg: #ffffff;
            --hx-bg-2: #f8fafc;
            --hx-line: #e2e8f0;
            --hx-text: #0f172a;
            --hx-muted: #64748b;
            --hx-muted-2: #94a3b8;
            --hx-blue: #3b82f6;
            --hx-blue-d: #2563eb;
            --hx-sh: 0 1px 2px rgba(15, 23, 42, .05), 0 1px 3px rgba(15, 23, 42, .06);
            --hx-sh-lg: 0 10px 28px rgba(15, 23, 42, .10);
            max-width: 1080px;
        }

        body.dark-mode .hx-wrap {
            --hx-bg: #1e293b;
            --hx-bg-2: #0f172a;
            --hx-line: #334155;
            --hx-text: #e2e8f0;
            --hx-muted: #94a3b8;
            --hx-muted-2: #64748b;
            --hx-sh: 0 1px 2px rgba(0, 0, 0, .2);
            --hx-sh-lg: 0 12px 30px rgba(0, 0, 0, .35);
            color-scheme: dark;
        }

        /* ---------- En-tête ---------- */
        .hx-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin: 10px 0 22px;
        }

        .hx-title {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .hx-title-ico {
            flex: 0 0 auto;
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            font-size: 21px;
            box-shadow: 0 10px 24px rgba(59, 130, 246, .35);
        }

        .hx-title h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -.025em;
            line-height: 1.15;
            color: var(--hx-text);
        }

        .hx-title p {
            margin: 4px 0 0;
            font-size: 13.5px;
            color: var(--hx-muted);
        }

        .hx-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .hx-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 0 20px;
            border: 0;
            border-radius: 12px;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            text-decoration: none;
            cursor: pointer;
            transition: transform .25s cubic-bezier(.22, 1, .36, 1), box-shadow .25s ease;
        }

        .hx-btn:hover {
            transform: translateY(-2px);
        }

        .hx-btn:active {
            transform: scale(.97);
        }

        .hx-btn--primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 8px 20px rgba(59, 130, 246, .3);
        }

        .hx-btn--primary:hover {
            box-shadow: 0 12px 26px rgba(59, 130, 246, .42);
        }

        .hx-btn--violet {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            box-shadow: 0 8px 20px rgba(139, 92, 246, .28);
        }

        .hx-btn--violet:hover {
            box-shadow: 0 12px 26px rgba(139, 92, 246, .4);
        }

        /* ---------- Tuiles de synthèse ---------- */
        .hx-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .hx-stat {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            background: var(--hx-bg);
            border: 1px solid var(--hx-line);
            border-radius: 16px;
            box-shadow: var(--hx-sh);
        }

        .hx-stat-ico {
            flex: 0 0 auto;
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            font-size: 17px;
        }

        .hx-stat--blue .hx-stat-ico {
            background: #dbeafe;
            color: #3b82f6;
        }

        .hx-stat--red .hx-stat-ico {
            background: #fee2e2;
            color: #ef4444;
        }

        .hx-stat--violet .hx-stat-ico {
            background: #ede9fe;
            color: #8b5cf6;
        }

        body.dark-mode .hx-stat--blue .hx-stat-ico {
            background: #1e3a8a;
            color: #93bbfc;
        }

        body.dark-mode .hx-stat--red .hx-stat-ico {
            background: #7f1d1d;
            color: #fca5a5;
        }

        body.dark-mode .hx-stat--violet .hx-stat-ico {
            background: rgba(139, 92, 246, .22);
            color: #c4b5fd;
        }

        .hx-stat strong {
            display: block;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -.03em;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
            color: var(--hx-text);
        }

        .hx-stat span {
            display: block;
            margin-top: 3px;
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--hx-muted);
        }

        /* ---------- Filtres ---------- */
        .hx-filters {
            margin-bottom: 8px;
            padding: 16px;
            background: var(--hx-bg);
            border: 1px solid var(--hx-line);
            border-radius: 18px;
            box-shadow: var(--hx-sh);
        }

        .hx-filters-top {
            display: flex;
            gap: 10px;
        }

        .hx-field {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            padding: 0 14px;
            background: var(--hx-bg-2);
            border: 2px solid var(--hx-line);
            border-radius: 12px;
            transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
        }

        .hx-field:hover {
            border-color: #bfdbfe;
        }

        body.dark-mode .hx-field:hover {
            border-color: #475569;
        }

        .hx-field:focus-within {
            border-color: var(--hx-blue);
            background: var(--hx-bg);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, .16);
        }

        .hx-field>i {
            flex: 0 0 auto;
            font-size: 14px;
            color: var(--hx-muted-2);
        }

        .hx-field:focus-within>i {
            color: var(--hx-blue);
        }

        .hx-field input,
        .hx-field select {
            width: 100%;
            min-width: 0;
            min-height: 46px;
            padding: 0;
            border: 0;
            outline: 0;
            background: transparent;
            font: inherit;
            font-size: 14px;
            color: var(--hx-text);
        }

        .hx-field input::placeholder {
            color: var(--hx-muted-2);
        }

        .hx-field select {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 22px;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2364748b' stroke-width='1.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0 center;
            background-size: 11px 7px;
        }

        body.dark-mode .hx-field select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2394a3b8' stroke-width='1.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        }

        .hx-field select option {
            background: var(--hx-bg);
            color: var(--hx-text);
        }

        .hx-search {
            flex: 1 1 auto;
        }

        .hx-toggle {
            display: none;
            align-items: center;
            gap: 8px;
            min-height: 50px;
            padding: 0 16px;
            border: 2px solid var(--hx-line);
            border-radius: 12px;
            background: var(--hx-bg-2);
            color: var(--hx-text);
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s ease;
        }

        .hx-toggle[aria-expanded="true"] {
            border-color: var(--hx-blue);
            color: var(--hx-blue);
        }

        .hx-count {
            min-width: 20px;
            height: 20px;
            display: inline-grid;
            place-items: center;
            padding: 0 6px;
            border-radius: 10px;
            background: var(--hx-blue);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
        }

        .hx-filters-more {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed var(--hx-line);
        }

        .hx-lab {
            display: block;
            margin: 0 0 6px 2px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--hx-muted);
        }

        .hx-reset {
            grid-column: 1 / -1;
            justify-self: end;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 40px;
            padding: 0 16px;
            border: 1px solid var(--hx-line);
            border-radius: 10px;
            background: var(--hx-bg-2);
            color: var(--hx-muted);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all .2s ease;
        }

        .hx-reset:hover {
            background: var(--hx-blue);
            border-color: var(--hx-blue);
            color: #fff;
        }

        /* ---------- Liste groupée par jour ---------- */
        .hx-day {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 4px 10px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: var(--hx-muted);
        }

        .hx-day::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--hx-line);
        }

        .hx-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .hx-item {
            position: relative;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: 16px;
            padding: 16px 18px;
            background: var(--hx-bg);
            border: 1px solid var(--hx-line);
            border-radius: 16px;
            box-shadow: var(--hx-sh);
            transition: transform .3s cubic-bezier(.22, 1, .36, 1), box-shadow .3s ease, border-color .2s ease;
            animation: hxIn .4s cubic-bezier(.22, 1, .36, 1) both;
        }

        .hx-item:hover {
            transform: translateY(-2px);
            border-color: var(--hx-blue);
            box-shadow: var(--hx-sh-lg);
        }

        .hx-item.unread {
            background: linear-gradient(90deg, rgba(59, 130, 246, .07), rgba(59, 130, 246, 0) 55%), var(--hx-bg);
        }

        .hx-item.unread::before {
            content: "";
            position: absolute;
            left: 0;
            top: 14px;
            bottom: 14px;
            width: 4px;
            border-radius: 0 4px 4px 0;
            background: var(--hx-blue);
        }

        @keyframes hxIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hx-ico {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            color: #fff;
            font-size: 18px;
            box-shadow: 0 8px 18px -8px var(--c, rgba(15, 23, 42, .4));
        }

        .hx-body {
            min-width: 0;
        }

        .hx-line {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px 8px;
            font-size: 15px;
            color: var(--hx-text);
        }

        .hx-label {
            font-weight: 700;
            letter-spacing: -.01em;
        }

        .hx-on {
            color: var(--hx-muted);
            font-size: 14px;
        }

        .hx-on b {
            color: var(--hx-text);
            font-weight: 600;
            word-break: break-word;
        }

        .hx-new {
            padding: 2px 10px;
            border-radius: 20px;
            background: #3b82f6;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .hx-msg {
            margin-top: 4px;
            font-size: 14px;
            line-height: 1.55;
            color: var(--hx-muted);
            word-break: break-word;
        }

        .hx-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }

        .hx-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            background: var(--hx-bg-2);
            border: 1px solid var(--hx-line);
            font-size: 12px;
            font-weight: 500;
            color: var(--hx-muted);
        }

        .hx-chip i {
            font-size: 11px;
            color: var(--hx-blue);
        }

        .hx-act {
            display: flex;
            gap: 8px;
        }

        .hx-ibtn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 38px;
            padding: 0 14px;
            border: 0;
            border-radius: 10px;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
            transition: all .2s ease;
        }

        .hx-ibtn i {
            font-size: 12px;
        }

        .hx-ibtn:active {
            transform: scale(.96);
        }

        .hx-ok {
            background: #dbeafe;
            color: #3b82f6;
        }

        .hx-ok:hover {
            background: #3b82f6;
            color: #fff;
        }

        .hx-del {
            background: #fee2e2;
            color: #ef4444;
        }

        .hx-del:hover {
            background: #ef4444;
            color: #fff;
        }

        body.dark-mode .hx-ok {
            background: #1e3a8a;
            color: #93bbfc;
        }

        body.dark-mode .hx-ok:hover {
            background: #3b82f6;
            color: #fff;
        }

        body.dark-mode .hx-del {
            background: #7f1d1d;
            color: #fca5a5;
        }

        body.dark-mode .hx-del:hover {
            background: #ef4444;
            color: #fff;
        }

        /* ---------- Pagination ---------- */
        .hx-pager-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 26px;
        }

        .hx-pager-info {
            font-size: 13px;
            color: var(--hx-muted);
        }

        .hx-pager-info b {
            color: var(--hx-text);
        }

        .hx-pager {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .hx-pager a,
        .hx-pager span {
            min-width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            border: 1px solid var(--hx-line);
            border-radius: 10px;
            background: var(--hx-bg);
            color: var(--hx-text);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all .2s ease;
        }

        .hx-pager a:hover {
            background: var(--hx-blue);
            border-color: var(--hx-blue);
            color: #fff;
            transform: translateY(-2px);
        }

        .hx-pager a.active {
            background: var(--hx-blue);
            border-color: var(--hx-blue);
            color: #fff;
            box-shadow: 0 6px 14px rgba(59, 130, 246, .3);
        }

        .hx-pager span.disabled {
            opacity: .4;
        }

        /* ---------- État vide ---------- */
        .hx-empty {
            margin-top: 16px;
            padding: 56px 20px;
            text-align: center;
            background: var(--hx-bg);
            border: 1px dashed var(--hx-line);
            border-radius: 20px;
        }

        .hx-empty-ico {
            width: 76px;
            height: 76px;
            display: grid;
            place-items: center;
            margin: 0 auto 16px;
            border-radius: 24px;
            background: #dbeafe;
            color: #3b82f6;
            font-size: 30px;
        }

        body.dark-mode .hx-empty-ico {
            background: #1e3a8a;
            color: #93bbfc;
        }

        .hx-empty h3 {
            margin: 0 0 6px;
            font-size: 19px;
            font-weight: 800;
            color: var(--hx-text);
        }

        .hx-empty p {
            margin: 0 0 18px;
            font-size: 14px;
            color: var(--hx-muted);
        }

        /* =========================================================
           TABLETTE
        ========================================================= */
        @media (max-width: 860px) {
            .hx-toggle {
                display: inline-flex;
            }

            .hx-filters-more {
                display: none;
                grid-template-columns: 1fr 1fr;
            }

            .hx-filters-more.open {
                display: grid;
                animation: hxIn .3s ease both;
            }
        }

        /* =========================================================
           MOBILE
        ========================================================= */
        @media (max-width: 640px) {
            .hx-head {
                margin: 4px 0 16px;
            }

            .hx-title-ico {
                width: 46px;
                height: 46px;
                font-size: 18px;
                border-radius: 14px;
            }

            .hx-title h1 {
                font-size: 21px;
            }

            .hx-title p {
                font-size: 12.5px;
            }

            .hx-actions {
                width: 100%;
            }

            .hx-btn {
                flex: 1 1 0;
                min-height: 46px;
                padding: 0 12px;
                font-size: 13.5px;
            }

            .hx-stats {
                gap: 8px;
            }

            .hx-stat {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                padding: 12px;
                border-radius: 14px;
            }

            .hx-stat-ico {
                width: 34px;
                height: 34px;
                font-size: 14px;
                border-radius: 10px;
            }

            .hx-stat strong {
                font-size: 20px;
            }

            .hx-stat span {
                font-size: 10px;
                letter-spacing: .04em;
            }

            .hx-filters {
                padding: 12px;
                border-radius: 16px;
            }

            .hx-field input,
            .hx-field select {
                font-size: 16px;
            }

            .hx-filters-more {
                grid-template-columns: 1fr;
            }

            .hx-day {
                margin: 20px 2px 8px;
                font-size: 11.5px;
            }

            .hx-item {
                grid-template-columns: auto minmax(0, 1fr);
                align-items: start;
                gap: 12px 12px;
                padding: 14px;
                border-radius: 14px;
            }

            .hx-ico {
                width: 40px;
                height: 40px;
                font-size: 16px;
                border-radius: 12px;
            }

            .hx-line {
                font-size: 14.5px;
            }

            .hx-msg {
                font-size: 13.5px;
            }

            .hx-chip {
                font-size: 11.5px;
                padding: 3px 9px;
            }

            .hx-chip.hide-sm {
                display: none;
            }

            .hx-act {
                grid-column: 1 / -1;
                padding-top: 12px;
                border-top: 1px solid var(--hx-line);
            }

            .hx-ibtn {
                flex: 1 1 0;
                min-height: 44px;
                font-size: 13.5px;
                border-radius: 12px;
            }

            .hx-pager-wrap {
                flex-direction: column-reverse;
                align-items: center;
            }

            .hx-pager {
                justify-content: center;
            }

            .hx-pager a,
            .hx-pager span {
                min-width: 42px;
                height: 42px;
            }

            .hx-empty {
                padding: 40px 16px;
            }
        }

        @media (max-width: 380px) {
            .hx-stat-ico {
                display: none;
            }

            .hx-title h1 {
                font-size: 19px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .hx-item,
            .hx-filters-more.open {
                animation: none;
            }
        }
    </style>
</head>

<body>

    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="content hx-wrap">

        <!-- ===== EN-TÊTE ===== -->
        <div class="hx-head">
            <div class="hx-title">
                <div class="hx-title-ico"><i class="fas fa-history"></i></div>
                <div>
                    <h1>Historique des actions</h1>
                    <p>Suivi de toutes les actions effectuées sur la plateforme</p>
                </div>
            </div>
            <div class="hx-actions">
                <?php if ($non_lues > 0 && function_exists('marquerToutesNotificationsLues')): ?>
                    <a class="hx-btn hx-btn--primary" href="?mark_all_read=1"
                        onclick="return confirmDelete('Marquer toutes les notifications comme lues ?')">
                        <i class="fas fa-check-double"></i> Tout lire
                    </a>
                <?php endif; ?>
                <a class="hx-btn hx-btn--violet" href="historique_actions.php">
                    <i class="fas fa-sync-alt"></i> Rafraîchir
                </a>
            </div>
        </div>

        <!-- ===== SYNTHÈSE ===== -->
        <div class="hx-stats">
            <div class="hx-stat hx-stat--blue">
                <div class="hx-stat-ico"><i class="fas fa-list-check"></i></div>
                <div><strong><?= number_format($total, 0, ',', ' ') ?></strong><span>Résultats</span></div>
            </div>
            <div class="hx-stat hx-stat--red">
                <div class="hx-stat-ico"><i class="fas fa-bell"></i></div>
                <div><strong><?= (int) $non_lues ?></strong><span>Non lues</span></div>
            </div>
            <div class="hx-stat hx-stat--violet">
                <div class="hx-stat-ico"><i class="fas fa-file-lines"></i></div>
                <div><strong><?= $page ?><small style="font-size:.6em;font-weight:600;color:var(--hx-muted);"> /
                            <?= max(1, $total_pages) ?></small></strong><span>Page</span></div>
            </div>
        </div>

        <!-- ===== FILTRES ===== -->
        <form method="GET" class="hx-filters" id="filterForm">
            <div class="hx-filters-top">
                <label class="hx-field hx-search">
                    <i class="fas fa-search"></i>
                    <input type="search" name="search" id="searchInput" autocomplete="off"
                        placeholder="Rechercher une action, un utilisateur…" value="<?= htmlspecialchars($search) ?>">
                </label>
                <button type="button" class="hx-toggle" id="filterToggle"
                    aria-expanded="<?= $active_filters > 0 ? 'true' : 'false' ?>" aria-controls="filtersMore">
                    <i class="fas fa-sliders"></i> Filtres
                    <?php if ($active_filters > 0): ?><span
                            class="hx-count"><?= $active_filters ?></span><?php endif; ?>
                </button>
            </div>

            <div class="hx-filters-more <?= $active_filters > 0 ? 'open' : '' ?>" id="filtersMore">
                <div>
                    <span class="hx-lab">Type</span>
                    <label class="hx-field">
                        <i class="fas fa-tag"></i>
                        <select name="type" onchange="this.form.submit()">
                            <option value="">Tous les types</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= $type_filter == $t ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(function_exists('getLabelForAction') ? getLabelForAction($t) : ucfirst($t)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div>
                    <span class="hx-lab">Entité</span>
                    <label class="hx-field">
                        <i class="fas fa-layer-group"></i>
                        <select name="entite" onchange="this.form.submit()">
                            <option value="">Toutes les entités</option>
                            <?php foreach ($entites as $e): ?>
                                <option value="<?= htmlspecialchars($e) ?>" <?= $entite_filter == $e ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($e)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div>
                    <span class="hx-lab">Statut</span>
                    <label class="hx-field">
                        <i class="fas fa-circle-dot"></i>
                        <select name="status" onchange="this.form.submit()">
                            <option value="">Tous</option>
                            <option value="non_lu" <?= $status_filter === 'non_lu' ? 'selected' : '' ?>>Non lues</option>
                            <option value="lu" <?= $status_filter === 'lu' ? 'selected' : '' ?>>Lues</option>
                        </select>
                    </label>
                </div>

                <div>
                    <span class="hx-lab">Du</span>
                    <label class="hx-field">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>"
                            onchange="this.form.submit()">
                    </label>
                </div>

                <div>
                    <span class="hx-lab">Au</span>
                    <label class="hx-field">
                        <i class="fas fa-calendar-check"></i>
                        <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>"
                            onchange="this.form.submit()">
                    </label>
                </div>

                <?php if ($has_filters): ?>
                    <a href="historique_actions.php" class="hx-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- ===== LISTE ===== -->
        <?php if (empty($notifications)): ?>
            <div class="hx-empty">
                <div class="hx-empty-ico">
                    <i class="fas <?= $has_filters ? 'fa-magnifying-glass' : 'fa-bell-slash' ?>"></i>
                </div>
                <h3><?= $has_filters ? 'Aucun résultat' : 'Aucune notification' ?></h3>
                <p>
                    <?= $has_filters
                        ? 'Aucune action ne correspond à vos filtres. Essayez de les modifier.'
                        : "Vous n'avez aucune notification pour le moment." ?>
                </p>
                <?php if ($has_filters): ?>
                    <a href="historique_actions.php" class="hx-btn hx-btn--primary"><i class="fas fa-undo"></i>
                        Réinitialiser les filtres</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="hx-list">
                <?php foreach ($notifications as $i => $notif):
                    $icon = function_exists('getIconForAction') ? getIconForAction($notif['type_action']) : 'fa-circle';
                    $color = function_exists('getColorForAction') ? getColorForAction($notif['type_action']) : '#3b82f6';
                    $label = function_exists('getLabelForAction') ? getLabelForAction($notif['type_action']) : ucfirst($notif['type_action']);
                    $entite_icon = function_exists('getEntiteIcon') ? getEntiteIcon($notif['entite']) : 'fa-circle';
                    $is_lu = $notif['lu'] ?? 0;

                    $user_display = $notif['nom_utilisateur'] ?? 'Utilisateur';
                    if (!empty($notif['prenom']) || !empty($notif['nom'])) {
                        $user_display = trim(($notif['prenom'] ?? '') . ' ' . ($notif['nom'] ?? ''));
                    }

                    $ts = strtotime($notif['date_action']);
                    $day = date('Y-m-d', $ts);
                    if ($day !== $last_day):
                        $last_day = $day; ?>
                        <div class="hx-day"><?= htmlspecialchars(hx_day_label($day)) ?></div>
                    <?php endif; ?>

                    <article class="hx-item <?= $is_lu ? '' : 'unread' ?>" style="animation-delay: <?= min($i, 12) * 0.03 ?>s;">
                        <div class="hx-ico"
                            style="background:<?= htmlspecialchars($color) ?>;--c:<?= htmlspecialchars($color) ?>;">
                            <i class="fas <?= htmlspecialchars($icon) ?>"></i>
                        </div>

                        <div class="hx-body">
                            <div class="hx-line">
                                <span class="hx-label"><?= htmlspecialchars($label) ?></span>
                                <?php if ($notif['nom_entite']): ?>
                                    <span class="hx-on">sur <b><?= htmlspecialchars($notif['nom_entite']) ?></b></span>
                                <?php endif; ?>
                                <?php if (!$is_lu): ?>
                                    <span class="hx-new">Nouveau</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($notif['details']): ?>
                                <div class="hx-msg"><?= htmlspecialchars($notif['details']) ?></div>
                            <?php endif; ?>

                            <div class="hx-meta">
                                <span class="hx-chip"><i class="fas fa-user-circle"></i>
                                    <?= htmlspecialchars($user_display) ?></span>
                                <span class="hx-chip"><i class="fas <?= htmlspecialchars($entite_icon) ?>"></i>
                                    <?= htmlspecialchars(ucfirst($notif['entite'])) ?></span>
                                <span class="hx-chip" title="<?= date('d/m/Y H:i:s', $ts) ?>"><i class="fas fa-clock"></i>
                                    <?= date('H:i', $ts) ?></span>
                                <?php if ($notif['ip_address']): ?>
                                    <span class="hx-chip hide-sm"><i class="fas fa-network-wired"></i>
                                        <?= htmlspecialchars($notif['ip_address']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="hx-act">
                            <?php if (!$is_lu && function_exists('marquerNotificationLue')): ?>
                                <a class="hx-ibtn hx-ok" href="?mark_read=1&id=<?= (int) $notif['id_action'] ?>">
                                    <i class="fas fa-check"></i> Marquer lu
                                </a>
                            <?php endif; ?>
                            <a class="hx-ibtn hx-del" href="#"
                                onclick="confirmDelete(<?= (int) $notif['id_action'] ?>, 'historique_actions.php', 'Supprimer cette notification ?'); return false;">
                                <i class="fas fa-trash"></i> Supprimer
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- ===== PAGINATION ===== -->
            <?php if ($total_pages > 1): ?>
                <div class="hx-pager-wrap">
                    <div class="hx-pager-info">Affichage de <b><?= $from ?>–<?= $to ?></b> sur
                        <b><?= number_format($total, 0, ',', ' ') ?></b>
                    </div>

                    <div class="hx-pager">
                        <?php if ($page > 1): ?>
                            <a href="<?= hx_url($page - 1) ?>" aria-label="Précédent"><i class="fas fa-chevron-left"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        if ($start > 1): ?>
                            <a href="<?= hx_url(1) ?>">1</a>
                            <?php if ($start > 2): ?><span>…</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($p = $start; $p <= $end; $p++): ?>
                            <a href="<?= hx_url($p) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endfor; ?>

                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?><span>…</span><?php endif; ?>
                            <a href="<?= hx_url($total_pages) ?>"><?= $total_pages ?></a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?= hx_url($page + 1) ?>" aria-label="Suivant"><i class="fas fa-chevron-right"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>

    <script>
        // ============================================================
        // FILTRES (mobile) : ouvrir / fermer le panneau
        // ============================================================
        (function () {
            var btn = document.getElementById('filterToggle');
            var panel = document.getElementById('filtersMore');
            if (!btn || !panel) return;
            btn.addEventListener('click', function () {
                var open = panel.classList.toggle('open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        })();

        // ============================================================
        // RECHERCHE AUTOMATIQUE (envoi 450 ms après la dernière frappe)
        // Le curseur revient dans le champ après le rechargement.
        // ============================================================
        (function () {
            var input = document.getElementById('searchInput');
            if (!input) return;
            var timer;

            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    try { sessionStorage.setItem('hxFocusSearch', '1'); } catch (e) { }
                    input.form.submit();
                }, 450);
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    clearTimeout(timer);
                }
            });

            try {
                if (sessionStorage.getItem('hxFocusSearch') === '1') {
                    sessionStorage.removeItem('hxFocusSearch');
                    input.focus();
                    var len = input.value.length;
                    input.setSelectionRange(len, len);
                }
            } catch (e) { }
        })();
    </script>

</body>

</html>