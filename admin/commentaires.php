<?php
require_once __DIR__ . '/../includes/session_boot.php';
// connexion.php AVANT permissions.php : require_permission() a besoin de $pdo pour lire les droits
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/permissions.php';
require_permission('gerer_commentaires');
require_once __DIR__ . '/../includes/notification.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Filtres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$video_filter = isset($_GET['video']) ? trim($_GET['video']) : '';
$date_debut = isset($_GET['date_debut']) ? trim($_GET['date_debut']) : '';
$date_fin = isset($_GET['date_fin']) ? trim($_GET['date_fin']) : '';

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Construction WHERE
$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = "(f.commentaire LIKE :search OR f.nom_utilisateur LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($video_filter)) {
    $where[] = "f.id_video = :video";
    $params[':video'] = $video_filter;
}
if (!empty($date_debut)) {
    $where[] = "DATE(f.date_creation) >= :date_debut";
    $params[':date_debut'] = $date_debut;
}
if (!empty($date_fin)) {
    $where[] = "DATE(f.date_creation) <= :date_fin";
    $params[':date_fin'] = $date_fin;
}

$where_clause = implode(" AND ", $where);

// Total
$count_sql = "
    SELECT COUNT(*) 
    FROM feedback f
    WHERE $where_clause
";
$count_stmt = $pdo->prepare($count_sql);
foreach ($params as $key => $val) {
    $count_stmt->bindValue($key, $val);
}
$count_stmt->execute();
$total = (int) $count_stmt->fetchColumn();
$total_pages = (int) ceil($total / $limit);

// Récupération des commentaires avec infos vidéo
$sql = "
    SELECT f.*, 
           v.id_video,
           fi.nom_film,
           fi.titre
    FROM feedback f
    LEFT JOIN video_film v ON f.id_video = v.id_video
    LEFT JOIN film fi ON v.id_film = fi.id_film
    WHERE $where_clause
    ORDER BY f.date_creation DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$commentaires = $stmt->fetchAll();

// Vidéos pour le filtre
$videos = $pdo->query("
    SELECT v.id_video, f.nom_film 
    FROM video_film v 
    INNER JOIN film f ON v.id_film = f.id_film 
    ORDER BY f.nom_film
")->fetchAll();

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

$active_filters = count(array_filter([$video_filter, $date_debut, $date_fin], function ($v) {
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
    <title>Commentaires utilisateurs - Bertin Movie</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=3">
    <link rel="stylesheet" href="/film_web_dy/admin/hx-style.css?v=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="content hx-wrap">

        <!-- ===== EN-TÊTE ===== -->
        <div class="hx-head">
            <div class="hx-title">
                <div class="hx-title-ico"><i class="fas fa-comments"></i></div>
                <div>
                    <h1>Commentaires utilisateurs</h1>
                    <p>Avis laissés par les utilisateurs sur les vidéos</p>
                </div>
            </div>
            <div class="hx-actions">
                <a class="hx-btn hx-btn--violet" href="commentaires.php">
                    <i class="fas fa-sync-alt"></i> Rafraîchir
                </a>
            </div>
        </div>

        <!-- ===== SYNTHÈSE ===== -->
        <div class="hx-stats">
            <div class="hx-stat hx-stat--blue">
                <div class="hx-stat-ico"><i class="fas fa-comment-dots"></i></div>
                <div><strong>
                        <?= number_format($total, 0, ',', ' ') ?>
                    </strong><span>Commentaires</span></div>
            </div>
            <div class="hx-stat hx-stat--green">
                <div class="hx-stat-ico"><i class="fas fa-list-check"></i></div>
                <div><strong>
                        <?= count($commentaires) ?>
                    </strong><span>Affichés</span></div>
            </div>
            <div class="hx-stat hx-stat--violet">
                <div class="hx-stat-ico"><i class="fas fa-file-lines"></i></div>
                <div><strong>
                        <?= $page ?><small> /
                            <?= max(1, $total_pages) ?>
                        </small>
                    </strong><span>Page</span></div>
            </div>
        </div>

        <!-- ===== FILTRES ===== -->
        <form method="GET" class="hx-filters" id="filterForm">
            <div class="hx-filters-top">
                <label class="hx-field hx-search">
                    <i class="fas fa-search"></i>
                    <input type="search" name="search" id="searchInput" autocomplete="off"
                        placeholder="Rechercher un commentaire ou un utilisateur…"
                        value="<?= htmlspecialchars($search) ?>">
                </label>
                <button type="button" class="hx-toggle" id="filterToggle"
                    aria-expanded="<?= $active_filters > 0 ? 'true' : 'false' ?>" aria-controls="filtersMore">
                    <i class="fas fa-sliders"></i> Filtres
                    <?php if ($active_filters > 0): ?><span class="hx-count">
                            <?= $active_filters ?>
                        </span>
                    <?php endif; ?>
                </button>
            </div>

            <div class="hx-filters-more <?= $active_filters > 0 ? 'open' : '' ?>" id="filtersMore">
                <div>
                    <span class="hx-lab">Vidéo</span>
                    <label class="hx-field">
                        <i class="fas fa-film"></i>
                        <select name="video" onchange="this.form.submit()">
                            <option value="">Toutes les vidéos</option>
                            <?php foreach ($videos as $v): ?>
                                <option value="<?= (int) $v['id_video'] ?>" <?= $video_filter == $v['id_video'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['nom_film']) ?>
                                </option>
                            <?php endforeach; ?>
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
                    <a href="commentaires.php" class="hx-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- ===== LISTE ===== -->
        <?php if (empty($commentaires)): ?>
            <div class="hx-empty">
                <div class="hx-empty-ico">
                    <i class="fas <?= $has_filters ? 'fa-magnifying-glass' : 'fa-comment-slash' ?>"></i>
                </div>
                <h3>
                    <?= $has_filters ? 'Aucun résultat' : 'Aucun commentaire' ?>
                </h3>
                <p>
                    <?= $has_filters
                        ? 'Aucun commentaire ne correspond à vos filtres. Essayez de les modifier.'
                        : "Les utilisateurs n'ont laissé aucun commentaire pour le moment." ?>
                </p>
                <?php if ($has_filters): ?>
                    <a href="commentaires.php" class="hx-btn hx-btn--primary"><i class="fas fa-undo"></i>
                        Réinitialiser les filtres</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="hx-list">
                <?php foreach ($commentaires as $i => $comment):
                    $nom = trim($comment['nom_utilisateur'] ?? '');
                    $initiales = '';
                    if ($nom !== '') {
                        foreach (array_slice(preg_split('/\s+/', $nom), 0, 2) as $part) {
                            $initiales .= mb_substr($part, 0, 1);
                        }
                        $initiales = mb_strtoupper($initiales);
                    } else {
                        $initiales = '?';
                    }

                    $ts = strtotime($comment['date_creation']);
                    $day = date('Y-m-d', $ts);
                    $film = $comment['nom_film'] ?? ('Vidéo #' . $comment['id_video']);

                    if ($day !== $last_day):
                        $last_day = $day; ?>
                        <div class="hx-day">
                            <?= htmlspecialchars(hx_day_label($day)) ?>
                        </div>
                    <?php endif; ?>

                    <article class="hx-item" style="animation-delay: <?= min($i, 12) * 0.03 ?>s;">
                        <div class="hx-ico" style="background:linear-gradient(135deg,#06b6d4,#0891b2);--c:rgba(8,145,178,.55);">
                            <?= htmlspecialchars($initiales) ?>
                        </div>

                        <div class="hx-body">
                            <div class="hx-line">
                                <span class="hx-label">
                                    <?= htmlspecialchars($nom !== '' ? $nom : 'Anonyme') ?>
                                </span>
                                <span class="hx-on">sur
                                    <a href="/film_web_dy/user/watch.php?id_video=<?= (int) $comment['id_video'] ?>"
                                        target="_blank" rel="noopener">
                                        <?= htmlspecialchars($film) ?>
                                    </a>
                                </span>
                            </div>

                            <div class="hx-quote">
                                <?= nl2br(htmlspecialchars($comment['commentaire'])) ?>
                            </div>

                            <div class="hx-meta">
                                <span class="hx-chip"><i class="fas fa-user-circle"></i>
                                    <?= htmlspecialchars($nom !== '' ? $nom : 'Anonyme') ?>
                                </span>
                                <span class="hx-chip" title="<?= date('d/m/Y H:i:s', $ts) ?>"><i class="fas fa-clock"></i>
                                    <?= date('H:i', $ts) ?>
                                </span>
                                <span class="hx-chip hide-sm"><i class="fas fa-film"></i>
                                    <?= htmlspecialchars($film) ?>
                                </span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- ===== PAGINATION ===== -->
            <?php if ($total_pages > 1): ?>
                <div class="hx-pager-wrap">
                    <div class="hx-pager-info">Affichage de <b>
                            <?= $from ?>–
                            <?= $to ?>
                        </b> sur
                        <b>
                            <?= number_format($total, 0, ',', ' ') ?>
                        </b>
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
                            <?php if ($start > 2): ?><span>…</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($p = $start; $p <= $end; $p++): ?>
                            <a href="<?= hx_url($p) ?>" class="<?= $p === $page ? 'active' : '' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?><span>…</span>
                            <?php endif; ?>
                            <a href="<?= hx_url($total_pages) ?>">
                                <?= $total_pages ?>
                            </a>
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
        // RECHERCHE AUTOMATIQUE (450 ms après la dernière frappe)
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
                if (e.key === 'Enter') clearTimeout(timer);
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