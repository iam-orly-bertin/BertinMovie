<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/update_session.php';
require_once __DIR__ . '/../includes/notification.php';
require_once __DIR__ . '/includes/history_lib.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

updateUserSession($pdo, $_SESSION['user_id']);
$user_id = (int) $_SESSION['user_id'];

bm_hist_ensure($pdo);

// Jeton anti-CSRF (les suppressions se font en POST, plus par simple lien)
if (empty($_SESSION['hist_csrf'])) {
    $_SESSION['hist_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['hist_csrf'];

// =========================================================
// ACTIONS (POST) : retirer une vidéo / tout effacer
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok_token = hash_equals($csrf, (string) ($_POST['csrf'] ?? ''));
    $action = $_POST['action'] ?? '';
    $is_ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

    if ($action === 'remove' && $is_ajax) {
        header('Content-Type: application/json');
        if (!$ok_token) {
            echo json_encode(['ok' => false]);
            exit;
        }
        try {
            $pdo->prepare("DELETE FROM historique_vue WHERE id_utilisateur = ? AND id_video = ?")
                ->execute([$user_id, (int) ($_POST['id_video'] ?? 0)]);
            echo json_encode(['ok' => true]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false]);
        }
        exit;
    }

    if ($action === 'clear' && $ok_token) {
        try {
            $pdo->prepare("DELETE FROM historique_vue WHERE id_utilisateur = ?")->execute([$user_id]);
            showNotif('success', "✅ Historique effacé avec succès !");
        } catch (PDOException $e) {
            showNotif('error', "❌ Erreur lors de l'effacement de l'historique");
        }
    }
    header('Location: historique.php');
    exit;
}

// =========================================================
// LECTURE : 1 ligne par vidéo (la vue la plus récente)
// Les durées sont calculées côté MySQL pour éviter tout décalage de fuseau horaire
// =========================================================
$videos = [];
try {
    $st = $pdo->prepare("
        SELECT f.nom_film, f.duree, v.id_video, v.thumbnail, v.views, c.nom_categorie,
               h.date_vue,
               TIMESTAMPDIFF(MINUTE, h.date_vue, NOW()) AS minutes_ago,
               DATEDIFF(CURDATE(), DATE(h.date_vue))   AS days_ago
        FROM (
            SELECT id_video, MAX(date_vue) AS date_vue
            FROM historique_vue
            WHERE id_utilisateur = ?
            GROUP BY id_video
        ) h
        INNER JOIN video_film v ON h.id_video = v.id_video
        INNER JOIN film f ON v.id_film = f.id_film
        LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
        WHERE f.is_deleted = 0 AND f.est_archive = 0
        ORDER BY h.date_vue DESC
        LIMIT 200
    ");
    $st->execute([$user_id]);
    $videos = $st->fetchAll();
} catch (PDOException $e) {
    $videos = [];
}

// Regroupement par période
$groups = [
    'today' => ['fr' => "Aujourd'hui", 'en' => 'Today', 'icon' => 'fa-sun', 'items' => []],
    'yesterday' => ['fr' => 'Hier', 'en' => 'Yesterday', 'icon' => 'fa-clock-rotate-left', 'items' => []],
    'week' => ['fr' => 'Cette semaine', 'en' => 'This week', 'icon' => 'fa-calendar-week', 'items' => []],
    'month' => ['fr' => 'Ce mois-ci', 'en' => 'This month', 'icon' => 'fa-calendar-days', 'items' => []],
    'older' => ['fr' => 'Plus ancien', 'en' => 'Older', 'icon' => 'fa-box-archive', 'items' => []],
];
$count_today = 0;
$count_week = 0;
foreach ($videos as $v) {
    $d = (int) $v['days_ago'];
    if ($d <= 0) {
        $k = 'today';
    } elseif ($d === 1) {
        $k = 'yesterday';
    } elseif ($d <= 7) {
        $k = 'week';
    } elseif ($d <= 30) {
        $k = 'month';
    } else {
        $k = 'older';
    }
    $groups[$k]['items'][] = $v;
    if ($d <= 0) {
        $count_today++;
    }
    if ($d <= 7) {
        $count_week++;
    }
}

function bm_ago(int $min): array
{
    if ($min < 1) {
        return ["À l'instant", 'Just now'];
    }
    if ($min < 60) {
        return ["Il y a $min min", "$min min ago"];
    }
    $h = intdiv($min, 60);
    if ($h < 24) {
        return ["Il y a $h h", "{$h}h ago"];
    }
    $d = intdiv($h, 24);
    if ($d < 30) {
        return ["Il y a $d j", "{$d}d ago"];
    }
    $m = intdiv($d, 30);
    return ["Il y a $m mois", "{$m}mo ago"];
}

$total = count($videos);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon historique - BERTIN MOVIE</title>
    <style>
        .hx-wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 14px 18px 100px;
        }

        /* ---------- En-tête ---------- */
        .hx-hero {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 26px 28px;
            margin-bottom: 22px;
            background:
                radial-gradient(600px 220px at 0% 0%, rgba(59, 130, 246, .22), transparent 70%),
                radial-gradient(500px 200px at 100% 100%, rgba(139, 92, 246, .14), transparent 70%),
                var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 22px;
            box-shadow: var(--shadow);
        }

        .hx-title {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .hx-title .ico {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: var(--primary-gradient);
            color: #fff;
            font-size: 22px;
            box-shadow: 0 8px 24px rgba(59, 130, 246, .35);
        }

        .hx-title h1 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -.3px;
            color: var(--text-primary);
        }

        .hx-title p {
            margin-top: 2px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .hx-stats {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .hx-stat {
            min-width: 92px;
            padding: 10px 16px;
            text-align: center;
            border-radius: 14px;
            background: rgba(59, 130, 246, .08);
            border: 1px solid rgba(59, 130, 246, .18);
        }

        .hx-stat b {
            display: block;
            font-size: 22px;
            font-weight: 800;
            color: var(--primary-light);
            line-height: 1.1;
        }

        .hx-stat small {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--text-secondary);
            font-weight: 600;
        }

        /* ---------- Barre d'outils ---------- */
        .hx-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 26px;
        }

        .hx-search {
            position: relative;
            flex: 1;
            min-width: 220px;
        }

        .hx-search i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
        }

        .hx-search input {
            width: 100%;
            padding: 13px 40px 13px 42px;
            border-radius: 14px;
            font-size: 14px;
            font-family: inherit;
            background: var(--bg-input);
            color: var(--text-primary);
            border: 1.5px solid var(--border-color);
            transition: var(--transition);
        }

        .hx-search input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, .12);
        }

        .hx-search .x {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 28px;
            height: 28px;
            border: 0;
            border-radius: 50%;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            display: none;
        }

        .hx-search .x:hover {
            background: rgba(148, 163, 184, .18);
            color: var(--text-primary);
        }

        .hx-clear {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 13px 20px;
            border-radius: 14px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            font-family: inherit;
            color: #ef4444;
            background: rgba(239, 68, 68, .08);
            border: 1.5px solid rgba(239, 68, 68, .35);
            transition: var(--transition);
        }

        .hx-clear:hover {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(239, 68, 68, .35);
        }

        /* ---------- Groupes ---------- */
        .hx-group {
            margin-bottom: 34px;
        }

        .hx-group-head {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .hx-group-head i {
            color: var(--primary-light);
            font-size: 15px;
        }

        .hx-group-head h2 {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .hx-group-head .n {
            font-size: 12px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            background: rgba(59, 130, 246, .12);
            color: var(--primary-light);
        }

        .hx-group-head::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .hx-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 20px;
        }

        /* ---------- Carte ---------- */
        .hx-card {
            position: relative;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            overflow: hidden;
            transition: transform .3s ease, box-shadow .3s ease, border-color .3s ease, opacity .3s ease;
            animation: hxUp .45s ease-out both;
        }

        .hx-card:hover {
            transform: translateY(-6px);
            border-color: var(--primary-light);
            box-shadow: var(--shadow-hover), var(--shadow-glow);
        }

        .hx-card.removing {
            opacity: 0;
            transform: scale(.92);
        }

        @keyframes hxUp {
            from {
                opacity: 0;
                transform: translateY(18px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .hx-thumb {
            position: relative;
            display: block;
            padding-top: 56.25%;
            background: var(--bg-body);
            overflow: hidden;
        }

        .hx-thumb img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .4s ease;
        }

        .hx-card:hover .hx-thumb img {
            transform: scale(1.07);
        }

        .hx-noimg {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            color: var(--primary-light);
            opacity: .55;
            font-size: 38px;
        }

        .hx-over {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            opacity: 0;
            transition: opacity .3s ease;
            background: linear-gradient(to top, rgba(2, 6, 23, .7), rgba(2, 6, 23, .15));
        }

        .hx-card:hover .hx-over {
            opacity: 1;
        }

        .hx-play {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 20px;
            background: var(--primary-gradient);
            box-shadow: 0 8px 28px rgba(59, 130, 246, .55);
            padding-left: 3px;
            transform: scale(.8);
            transition: transform .3s ease;
        }

        .hx-card:hover .hx-play {
            transform: scale(1);
        }

        .hx-dur {
            position: absolute;
            right: 10px;
            bottom: 10px;
            padding: 3px 10px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
            color: #fff;
            background: rgba(0, 0, 0, .75);
            backdrop-filter: blur(4px);
        }

        .hx-when {
            position: absolute;
            left: 10px;
            top: 10px;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
            background: rgba(15, 23, 42, .72);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .hx-when i {
            color: #60a5fa;
            font-size: 10px;
        }

        .hx-del {
            position: absolute;
            right: 10px;
            top: 10px;
            z-index: 3;
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 50%;
            display: grid;
            place-items: center;
            cursor: pointer;
            font-size: 14px;
            color: #fff;
            background: rgba(15, 23, 42, .72);
            backdrop-filter: blur(6px);
            opacity: 0;
            transform: scale(.85);
            transition: all .25s ease;
        }

        .hx-card:hover .hx-del,
        .hx-del:focus-visible {
            opacity: 1;
            transform: scale(1);
        }

        .hx-del:hover {
            background: #ef4444;
        }

        @media (hover: none) {
            .hx-del {
                opacity: 1;
                transform: none;
            }
        }

        .hx-info {
            padding: 14px 16px 16px;
        }

        .hx-info h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .hx-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 14px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .hx-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .hx-meta i {
            color: var(--primary-light);
            font-size: 11px;
        }

        .hx-again {
            margin-top: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            background: var(--primary-gradient);
            transition: var(--transition);
        }

        .hx-again:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(59, 130, 246, .4);
        }

        /* ---------- États vides ---------- */
        .hx-empty,
        .hx-noresult {
            text-align: center;
            padding: 64px 20px;
            background: var(--bg-card);
            border: 1px dashed var(--border-color);
            border-radius: 22px;
        }

        .hx-noresult {
            display: none;
        }

        .hx-empty .big {
            width: 92px;
            height: 92px;
            margin: 0 auto 18px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 38px;
            color: var(--primary-light);
            background: rgba(59, 130, 246, .1);
        }

        .hx-empty h3,
        .hx-noresult h3 {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .hx-empty p,
        .hx-noresult p {
            max-width: 420px;
            margin: 0 auto 22px;
            font-size: 15px;
            color: var(--text-secondary);
        }

        .hx-noresult p {
            margin-bottom: 0;
        }

        .hx-cta {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 13px 30px;
            border-radius: 14px;
            font-weight: 700;
            color: #fff;
            background: var(--primary-gradient);
            box-shadow: 0 8px 24px rgba(59, 130, 246, .35);
            transition: var(--transition);
        }

        .hx-cta:hover {
            transform: translateY(-3px);
        }

        /* ---------- Fenêtre de confirmation ---------- */
        .hx-modal {
            position: fixed;
            inset: 0;
            z-index: 3000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(2, 6, 23, .65);
            backdrop-filter: blur(6px);
        }

        .hx-modal.open {
            display: flex;
            animation: hxFade .2s ease;
        }

        .hx-box {
            width: 100%;
            max-width: 420px;
            padding: 32px 28px 26px;
            text-align: center;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .5);
            animation: hxPop .28s cubic-bezier(.2, 1.3, .4, 1);
        }

        .hx-box .warn {
            width: 72px;
            height: 72px;
            margin: 0 auto 16px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 28px;
            color: #ef4444;
            background: rgba(239, 68, 68, .12);
        }

        .hx-box h3 {
            font-size: 21px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .hx-box p {
            font-size: 14px;
            line-height: 1.55;
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .hx-box .row {
            display: flex;
            gap: 10px;
        }

        .hx-box button {
            flex: 1;
            padding: 13px;
            border-radius: 13px;
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: var(--transition);
        }

        .hx-box .no {
            background: transparent;
            color: var(--text-primary);
            border: 1.5px solid var(--border-color);
        }

        .hx-box .no:hover {
            background: rgba(148, 163, 184, .12);
        }

        .hx-box .yes {
            border: 0;
            color: #fff;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 6px 20px rgba(239, 68, 68, .35);
        }

        .hx-box .yes:hover {
            transform: translateY(-2px);
        }

        @keyframes hxFade {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes hxPop {
            from {
                opacity: 0;
                transform: scale(.88);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        /* ---------- Petit message (toast) ---------- */
        .hx-toast {
            position: fixed;
            left: 50%;
            bottom: 28px;
            z-index: 3100;
            padding: 12px 22px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            background: #0f172a;
            border: 1px solid #334155;
            box-shadow: 0 12px 40px rgba(0, 0, 0, .45);
            transform: translate(-50%, 30px);
            opacity: 0;
            pointer-events: none;
            transition: all .3s ease;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .hx-toast.show {
            transform: translate(-50%, 0);
            opacity: 1;
        }

        .hx-toast i {
            color: #22c55e;
        }

        /* ---------- Mobile ---------- */
        @media (max-width: 768px) {
            .hx-wrap {
                padding: 8px 12px 90px;
            }

            .hx-hero {
                padding: 20px 18px;
                border-radius: 18px;
            }

            .hx-title h1 {
                font-size: 21px;
            }

            .hx-title .ico {
                width: 46px;
                height: 46px;
                font-size: 18px;
            }

            .hx-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 14px;
            }
        }

        @media (max-width: 520px) {
            .hx-stats {
                width: 100%;
            }

            .hx-stat {
                flex: 1;
                min-width: 0;
                padding: 8px 6px;
            }

            .hx-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .hx-clear {
                justify-content: center;
            }

            .hx-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .hx-info {
                padding: 10px 10px 12px;
            }

            .hx-info h3 {
                font-size: 13px;
            }

            .hx-meta {
                font-size: 10.5px;
                gap: 3px 10px;
            }

            .hx-again {
                margin-top: 10px;
                padding: 8px;
                font-size: 12px;
            }

            .hx-when {
                font-size: 9.5px;
                padding: 3px 8px;
            }

            .hx-dur {
                font-size: 10px;
                padding: 2px 7px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .hx-card,
            .hx-box,
            .hx-modal.open {
                animation: none;
            }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/menu-user.php'; ?>
    <?php renderNotifications(); ?>

    <div class="hx-wrap">

        <!-- ===== EN-TÊTE ===== -->
        <div class="hx-hero">
            <div class="hx-title">
                <div class="ico"><i class="fas fa-clock-rotate-left"></i></div>
                <div>
                    <h1 data-lang-fr="Mon historique" data-lang-en="My history">Mon historique</h1>
                    <p data-lang-fr="Tout ce que vous avez regardé, du plus récent au plus ancien"
                        data-lang-en="Everything you watched, newest first">Tout ce que vous avez regardé, du plus
                        récent au plus ancien</p>
                </div>
            </div>
            <div class="hx-stats">
                <div class="hx-stat"><b id="statTotal"><?= $total ?></b><small data-lang-fr="Films"
                        data-lang-en="Movies">Films</small></div>
                <div class="hx-stat"><b><?= $count_today ?></b><small data-lang-fr="Aujourd'hui"
                        data-lang-en="Today">Aujourd'hui</small></div>
                <div class="hx-stat"><b><?= $count_week ?></b><small data-lang-fr="7 jours" data-lang-en="7 days">7
                        jours</small></div>
            </div>
        </div>

        <?php if ($total > 0): ?>
            <!-- ===== RECHERCHE + EFFACER ===== -->
            <div class="hx-bar">
                <div class="hx-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="hxSearch" autocomplete="off" placeholder="Rechercher dans l'historique…">
                    <button type="button" class="x" id="hxSearchX" aria-label="Clear"><i class="fas fa-times"></i></button>
                </div>
                <button type="button" class="hx-clear" onclick="hxOpen()">
                    <i class="fas fa-trash-alt"></i>
                    <span data-lang-fr="Tout effacer" data-lang-en="Clear all">Tout effacer</span>
                </button>
            </div>

            <!-- ===== GROUPES ===== -->
            <?php foreach ($groups as $key => $g):
                if (!$g['items']) {
                    continue;
                } ?>
                <section class="hx-group" data-group>
                    <div class="hx-group-head">
                        <i class="fas <?= $g['icon'] ?>"></i>
                        <h2 data-lang-fr="<?= htmlspecialchars($g['fr'], ENT_QUOTES) ?>"
                            data-lang-en="<?= htmlspecialchars($g['en'], ENT_QUOTES) ?>"><?= htmlspecialchars($g['fr']) ?></h2>
                        <span class="n" data-count><?= count($g['items']) ?></span>
                    </div>
                    <div class="hx-grid">
                        <?php foreach ($g['items'] as $i => $film):
                            $ago = bm_ago((int) $film['minutes_ago']);
                            $titre = $film['nom_film'];
                            ?>
                            <article class="hx-card"
                                data-title="<?= htmlspecialchars(mb_strtolower($titre . ' ' . ($film['nom_categorie'] ?? '')), ENT_QUOTES) ?>"
                                style="animation-delay: <?= min($i, 12) * 0.04 ?>s">
                                <button type="button" class="hx-del" title="Retirer de l'historique"
                                    onclick="hxRemove(this, <?= (int) $film['id_video'] ?>)" aria-label="Remove">
                                    <i class="fas fa-times"></i>
                                </button>
                                <a class="hx-thumb" href="/film_web_dy/user/watch.php?id_video=<?= (int) $film['id_video'] ?>">
                                    <?php if (!empty($film['thumbnail'])): ?>
                                        <img src="<?= htmlspecialchars($film['thumbnail']) ?>" alt="<?= htmlspecialchars($titre) ?>"
                                            loading="lazy"
                                            onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'hx-noimg',innerHTML:'<i class=&quot;fas fa-film&quot;></i>'}))">
                                    <?php else: ?>
                                        <div class="hx-noimg"><i class="fas fa-film"></i></div>
                                    <?php endif; ?>
                                    <div class="hx-over">
                                        <div class="hx-play"><i class="fas fa-play"></i></div>
                                    </div>
                                    <?php if (!empty($film['duree'])): ?>
                                        <span class="hx-dur"><?= htmlspecialchars($film['duree']) ?></span>
                                    <?php endif; ?>
                                    <span class="hx-when"><i class="fas fa-circle-check"></i>
                                        <span data-lang-fr="<?= htmlspecialchars($ago[0], ENT_QUOTES) ?>"
                                            data-lang-en="<?= htmlspecialchars($ago[1], ENT_QUOTES) ?>"><?= htmlspecialchars($ago[0]) ?></span>
                                    </span>
                                </a>
                                <div class="hx-info">
                                    <h3 title="<?= htmlspecialchars($titre) ?>"><?= htmlspecialchars($titre) ?></h3>
                                    <div class="hx-meta">
                                        <span><i class="fas fa-tag"></i>
                                            <?= htmlspecialchars($film['nom_categorie'] ?? 'Non catégorisé') ?></span>
                                        <span><i class="fas fa-eye"></i>
                                            <?= number_format((int) ($film['views'] ?? 0), 0, ',', ' ') ?></span>
                                        <span><i class="fas fa-calendar"></i>
                                            <?= date('d/m/Y H:i', strtotime($film['date_vue'])) ?></span>
                                    </div>
                                    <a class="hx-again" href="/film_web_dy/user/watch.php?id_video=<?= (int) $film['id_video'] ?>">
                                        <i class="fas fa-rotate-right"></i>
                                        <span data-lang-fr="Revoir" data-lang-en="Watch again">Revoir</span>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <!-- Aucun résultat de recherche -->
            <div class="hx-noresult" id="hxNoResult">
                <div class="big"
                    style="width:72px;height:72px;margin:0 auto 14px;border-radius:50%;display:grid;place-items:center;font-size:30px;color:var(--primary-light);background:rgba(59,130,246,.1)">
                    <i class="fas fa-magnifying-glass"></i></div>
                <h3 data-lang-fr="Aucun résultat" data-lang-en="No results">Aucun résultat</h3>
                <p data-lang-fr="Aucun film de votre historique ne correspond à cette recherche."
                    data-lang-en="No movie in your history matches this search.">Aucun film de votre historique ne
                    correspond à cette recherche.</p>
            </div>
        <?php endif; ?>

        <!-- État vide (visible si historique vide, ou si on retire le dernier film) -->
        <div class="hx-empty" id="hxEmpty" style="<?= $total > 0 ? 'display:none' : '' ?>">
            <div class="big"><i class="fas fa-clock-rotate-left"></i></div>
            <h3 data-lang-fr="Aucun historique" data-lang-en="No history">Aucun historique</h3>
            <p data-lang-fr="Les films que vous regardez apparaîtront ici automatiquement."
                data-lang-en="The movies you watch will automatically appear here.">Les films que vous regardez
                apparaîtront ici automatiquement.</p>
            <a href="/film_web_dy/user/video.php" class="hx-cta">
                <i class="fas fa-film"></i>
                <span data-lang-fr="Découvrir des vidéos" data-lang-en="Discover videos">Découvrir des vidéos</span>
            </a>
        </div>
    </div>

    <!-- ===== CONFIRMATION « TOUT EFFACER » ===== -->
    <div class="hx-modal" id="hxModal" onclick="if(event.target===this)hxClose()">
        <form class="hx-box" method="POST" action="historique.php">
            <input type="hidden" name="action" value="clear">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <div class="warn"><i class="fas fa-trash-can"></i></div>
            <h3 data-lang-fr="Effacer tout l'historique ?" data-lang-en="Clear your entire history?">Effacer tout
                l'historique ?</h3>
            <p data-lang-fr="Tous les films de votre historique seront retirés. Cette action est définitive."
                data-lang-en="Every movie will be removed from your history. This cannot be undone.">Tous les films de
                votre historique seront retirés. Cette action est définitive.</p>
            <div class="row">
                <button type="button" class="no" onclick="hxClose()"><span data-lang-fr="Annuler"
                        data-lang-en="Cancel">Annuler</span></button>
                <button type="submit" class="yes"><span data-lang-fr="Oui, tout effacer"
                        data-lang-en="Yes, clear all">Oui, tout effacer</span></button>
            </div>
        </form>
    </div>

    <div class="hx-toast" id="hxToast"><i class="fas fa-circle-check"></i><span id="hxToastTxt"></span></div>

    <script>
        (function () {
            var CSRF = <?= json_encode($csrf) ?>;
            var TXT = {
                fr: { removed: 'Retiré de l\'historique', error: 'Erreur, réessayez', search: 'Rechercher dans l\'historique…', del: 'Retirer de l\'historique' },
                en: { removed: 'Removed from history', error: 'Error, please retry', search: 'Search your history…', del: 'Remove from history' }
            };
            function lang() { return localStorage.getItem('lang') === 'en' ? 'en' : 'fr'; }
            function T(k) { return TXT[lang()][k]; }

            // Placeholder + infobulles (le moteur du menu ne gère pas ces attributs)
            function applyAttrs() {
                var s = document.getElementById('hxSearch');
                if (s) { s.placeholder = T('search'); }
                document.querySelectorAll('.hx-del').forEach(function (b) { b.title = T('del'); });
            }
            applyAttrs();
            if (typeof window.applyLanguage === 'function') {
                var orig = window.applyLanguage;
                window.applyLanguage = function (l) { orig(l); applyAttrs(); };
            }

            // ----- Toast -----
            var toastT;
            function toast(msg, ok) {
                var t = document.getElementById('hxToast');
                document.getElementById('hxToastTxt').textContent = msg;
                t.querySelector('i').style.color = ok === false ? '#ef4444' : '#22c55e';
                t.classList.add('show');
                clearTimeout(toastT);
                toastT = setTimeout(function () { t.classList.remove('show'); }, 2400);
            }

            // ----- Modal -----
            window.hxOpen = function () { document.getElementById('hxModal').classList.add('open'); };
            window.hxClose = function () { document.getElementById('hxModal').classList.remove('open'); };
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { hxClose(); } });

            // ----- Compteurs / états vides -----
            function refresh() {
                var total = 0, anyVisible = false;
                document.querySelectorAll('[data-group]').forEach(function (g) {
                    var all = g.querySelectorAll('.hx-card').length;
                    var vis = g.querySelectorAll('.hx-card:not([hidden])').length;
                    total += all;
                    g.querySelector('[data-count]').textContent = all;
                    g.hidden = vis === 0;
                    if (vis > 0) { anyVisible = true; }
                });
                var st = document.getElementById('statTotal');
                if (st) { st.textContent = total; }
                var bar = document.querySelector('.hx-bar');
                var empty = document.getElementById('hxEmpty');
                var nores = document.getElementById('hxNoResult');
                if (total === 0) {
                    if (bar) { bar.style.display = 'none'; }
                    if (nores) { nores.style.display = 'none'; }
                    empty.style.display = 'block';
                } else if (nores) {
                    nores.style.display = anyVisible ? 'none' : 'block';
                }
            }

            // ----- Retirer un film -----
            window.hxRemove = function (btn, idVideo) {
                var card = btn.closest('.hx-card');
                btn.disabled = true;
                var body = new URLSearchParams({ action: 'remove', id_video: idVideo, csrf: CSRF });
                fetch('historique.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'fetch', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (d) {
                    if (!d.ok) { throw new Error(); }
                    card.classList.add('removing');
                    setTimeout(function () { card.remove(); refresh(); }, 280);
                    toast(T('removed'));
                }).catch(function () {
                    btn.disabled = false;
                    toast(T('error'), false);
                });
            };

            // ----- Recherche -----
            var input = document.getElementById('hxSearch'), x = document.getElementById('hxSearchX');
            if (input) {
                input.addEventListener('input', function () {
                    var q = input.value.trim().toLowerCase();
                    x.style.display = q ? 'block' : 'none';
                    document.querySelectorAll('.hx-card').forEach(function (c) {
                        c.hidden = q !== '' && c.getAttribute('data-title').indexOf(q) === -1;
                    });
                    refresh();
                });
                x.addEventListener('click', function () { input.value = ''; input.dispatchEvent(new Event('input')); input.focus(); });
            }
        })();
    </script>

</body>

</html>