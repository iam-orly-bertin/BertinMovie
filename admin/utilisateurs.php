<?php
require_once __DIR__ . '/../includes/session_boot.php';

require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/update_session.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/permissions.php';
require_permission('gerer_utilisateurs');

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Mettre à jour la session de l'admin
if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

// Filtres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sexe = isset($_GET['sexe']) ? trim($_GET['sexe']) : '';
$role = isset($_GET['role']) ? trim($_GET['role']) : '';
$date_debut = isset($_GET['date_debut']) ? trim($_GET['date_debut']) : '';
$date_fin = isset($_GET['date_fin']) ? trim($_GET['date_fin']) : '';
$tri = isset($_GET['tri']) ? trim($_GET['tri']) : 'nouveaux';

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Construction WHERE
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(u.nom_utilisateur LIKE :search OR u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($sexe)) {
    $where[] = "u.sexe = :sexe";
    $params[':sexe'] = $sexe;
}
if (!empty($role)) {
    $where[] = "u.role = :role";
    $params[':role'] = $role;
}
if (!empty($date_debut)) {
    $where[] = "DATE(u.date_creation) >= :date_debut";
    $params[':date_debut'] = $date_debut;
}
if (!empty($date_fin)) {
    $where[] = "DATE(u.date_creation) <= :date_fin";
    $params[':date_fin'] = $date_fin;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$order_map = [
    'nouveaux' => 'u.date_creation DESC',
    'anciens' => 'u.date_creation ASC',
    'nom_az' => 'u.nom ASC, u.prenom ASC',
    'nom_za' => 'u.nom DESC, u.prenom DESC',
    'plus_actifs' => 'nb_connexions DESC',
    'moins_actifs' => 'nb_connexions ASC',
];
$order = $order_map[$tri] ?? 'u.date_creation DESC';

// Total
$sql_count = "SELECT COUNT(*) FROM utilisateur u $where_clause";
$count_stmt = $pdo->prepare($sql_count);
foreach ($params as $key => $val) {
    $count_stmt->bindValue($key, $val);
}
$count_stmt->execute();
$total = (int) $count_stmt->fetchColumn();
$total_pages = (int) ceil($total / $limit);

// Récupération
$sql = "
    SELECT u.*,
        (SELECT COUNT(*) FROM historique_connexion h WHERE h.id_utilisateur = u.id_utilisateur) as nb_connexions,
        (SELECT MAX(date_connexion) FROM historique_connexion h WHERE h.id_utilisateur = u.id_utilisateur) as derniere_connexion,
        (SELECT COUNT(DISTINCT DATE(date_connexion)) FROM historique_connexion h WHERE h.id_utilisateur = u.id_utilisateur) as jours_actifs
    FROM utilisateur u
    $where_clause
    ORDER BY $order
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$utilisateurs = $stmt->fetchAll();

// Utilisateurs connectés
$connected_users = [];
try {
    $connected_users = $pdo->query("
        SELECT DISTINCT id_utilisateur 
        FROM sessions_actives 
        WHERE derniere_activite >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $connected_users = [];
}

$admin_id = $_SESSION['user_id'] ?? null;
if ($admin_id && !in_array($admin_id, $connected_users)) {
    $connected_users[] = $admin_id;
}

// Statistiques
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN sexe = 'M' THEN 1 ELSE 0 END) as hommes,
        SUM(CASE WHEN sexe = 'F' THEN 1 ELSE 0 END) as femmes,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as users
    FROM utilisateur
")->fetch();

if (!$stats) {
    $stats = ['total' => 0, 'hommes' => 0, 'femmes' => 0, 'admins' => 0, 'users' => 0];
}

$actifs_aujourdhui = $pdo->query("
    SELECT COUNT(DISTINCT id_utilisateur) 
    FROM historique_connexion 
    WHERE DATE(date_connexion) = CURDATE()
")->fetchColumn();

/* =========================================================
   AIDES D'AFFICHAGE (design uniquement)
========================================================= */
function hx_url($p)
{
    return '?' . http_build_query(array_merge($_GET, ['page' => $p]));
}

$active_filters = count(array_filter([$sexe, $role, $date_debut, $date_fin], function ($v) {
    return $v !== '';
})) + ($tri !== 'nouveaux' ? 1 : 0);
$has_filters = $active_filters > 0 || $search !== '';
$from = $total ? $offset + 1 : 0;
$to = min($offset + $limit, $total);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - Bertin Movie</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=3">
    <link rel="stylesheet" href="/film_web_dy/admin/hx-style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content hx-wrap">

        <!-- ===== EN-TÊTE ===== -->
        <div class="hx-head">
            <div class="hx-title">
                <div class="hx-title-ico"><i class="fas fa-users"></i></div>
                <div>
                    <h1>Utilisateurs</h1>
                    <p>Gestion des comptes et suivi de l'activité</p>
                </div>
            </div>
            <div class="hx-actions">
                <a class="hx-btn hx-btn--violet" href="utilisateurs.php">
                    <i class="fas fa-sync-alt"></i> Rafraîchir
                </a>
            </div>
        </div>

        <!-- ===== SYNTHÈSE ===== -->
        <div class="hx-stats">
            <div class="hx-stat hx-stat--blue">
                <div class="hx-stat-ico"><i class="fas fa-users"></i></div>
                <div><strong><?= (int) ($stats['total'] ?? 0) ?></strong><span>Total</span></div>
            </div>
            <div class="hx-stat hx-stat--blue">
                <div class="hx-stat-ico"><i class="fas fa-mars"></i></div>
                <div><strong><?= (int) ($stats['hommes'] ?? 0) ?></strong><span>Hommes</span></div>
            </div>
            <div class="hx-stat hx-stat--pink">
                <div class="hx-stat-ico"><i class="fas fa-venus"></i></div>
                <div><strong><?= (int) ($stats['femmes'] ?? 0) ?></strong><span>Femmes</span></div>
            </div>
            <div class="hx-stat hx-stat--violet">
                <div class="hx-stat-ico"><i class="fas fa-user-shield"></i></div>
                <div><strong><?= (int) ($stats['admins'] ?? 0) ?></strong><span>Admins</span></div>
            </div>
            <div class="hx-stat hx-stat--green">
                <div class="hx-stat-ico"><i class="fas fa-user"></i></div>
                <div><strong><?= (int) ($stats['users'] ?? 0) ?></strong><span>Utilisateurs</span></div>
            </div>
            <div class="hx-stat hx-stat--orange">
                <div class="hx-stat-ico"><i class="fas fa-user-check"></i></div>
                <div><strong><?= (int) $actifs_aujourdhui ?></strong><span>Actifs aujourd'hui</span></div>
            </div>
        </div>

        <!-- ===== FILTRES ===== -->
        <form method="GET" class="hx-filters" id="filterForm">
            <div class="hx-filters-top">
                <label class="hx-field hx-search">
                    <i class="fas fa-search"></i>
                    <input type="search" name="search" id="searchInput" autocomplete="off"
                        placeholder="Rechercher un nom, un e-mail…" value="<?= htmlspecialchars($search) ?>">
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
                    <span class="hx-lab">Sexe</span>
                    <label class="hx-field">
                        <i class="fas fa-venus-mars"></i>
                        <select name="sexe" onchange="this.form.submit()">
                            <option value="">Tous</option>
                            <option value="M" <?= $sexe === 'M' ? 'selected' : '' ?>>Masculin</option>
                            <option value="F" <?= $sexe === 'F' ? 'selected' : '' ?>>Féminin</option>
                        </select>
                    </label>
                </div>

                <div>
                    <span class="hx-lab">Rôle</span>
                    <label class="hx-field">
                        <i class="fas fa-user-tag"></i>
                        <select name="role" onchange="this.form.submit()">
                            <option value="">Tous</option>
                            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Utilisateur</option>
                        </select>
                    </label>
                </div>

                <div>
                    <span class="hx-lab">Inscrit du</span>
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

                <div>
                    <span class="hx-lab">Trier par</span>
                    <label class="hx-field">
                        <i class="fas fa-sort"></i>
                        <select name="tri" onchange="this.form.submit()">
                            <option value="nouveaux" <?= $tri === 'nouveaux' ? 'selected' : '' ?>>Plus récents</option>
                            <option value="anciens" <?= $tri === 'anciens' ? 'selected' : '' ?>>Plus anciens</option>
                            <option value="nom_az" <?= $tri === 'nom_az' ? 'selected' : '' ?>>Nom A-Z</option>
                            <option value="nom_za" <?= $tri === 'nom_za' ? 'selected' : '' ?>>Nom Z-A</option>
                            <option value="plus_actifs" <?= $tri === 'plus_actifs' ? 'selected' : '' ?>>Plus actifs
                            </option>
                            <option value="moins_actifs" <?= $tri === 'moins_actifs' ? 'selected' : '' ?>>Moins actifs
                            </option>
                        </select>
                    </label>
                </div>

                <?php if ($has_filters): ?>
                    <a href="utilisateurs.php" class="hx-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- ===== LISTE ===== -->
        <?php if (empty($utilisateurs)): ?>
            <div class="hx-empty">
                <div class="hx-empty-ico"><i class="fas fa-user-slash"></i></div>
                <h3>Aucun utilisateur trouvé</h3>
                <p>Essayez de modifier vos critères de recherche.</p>
                <?php if ($has_filters): ?>
                    <a href="utilisateurs.php" class="hx-btn hx-btn--primary"><i class="fas fa-undo"></i>
                        Réinitialiser les filtres</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="hx-list" style="margin-top:18px;">
                <?php foreach ($utilisateurs as $i => $user):
                    $initiales = strtoupper(mb_substr($user['prenom'] ?? '', 0, 1) . mb_substr($user['nom'] ?? '', 0, 1));
                    if ($initiales === '') {
                        $initiales = strtoupper(mb_substr($user['nom_utilisateur'] ?? 'U', 0, 2));
                    }

                    $nom_complet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
                    if ($nom_complet === '') {
                        $nom_complet = $user['nom_utilisateur'] ?? 'Utilisateur';
                    }

                    $is_connected = in_array($user['id_utilisateur'], $connected_users);
                    $is_admin = ($user['role'] ?? 'user') === 'admin';
                    $ico_bg = $is_admin ? 'linear-gradient(135deg,#8b5cf6,#7c3aed)' : 'linear-gradient(135deg,#3b82f6,#2563eb)';
                    $ico_c = $is_admin ? 'rgba(124,58,237,.55)' : 'rgba(37,99,235,.55)';
                    ?>
                    <a href="utilisateur_detail.php?id=<?= (int) $user['id_utilisateur'] ?>" class="hx-item hx-link"
                        style="animation-delay: <?= min($i, 12) * 0.03 ?>s;">
                        <div class="hx-ico" style="background:<?= $ico_bg ?>;--c:<?= $ico_c ?>;">
                            <?= htmlspecialchars($initiales) ?>
                        </div>

                        <div class="hx-body">
                            <div class="hx-line">
                                <span class="hx-label"><?= htmlspecialchars($nom_complet) ?></span>
                                <span class="hx-role <?= $is_admin ? 'hx-role--admin' : 'hx-role--user' ?>">
                                    <?= $is_admin ? 'Admin' : 'User' ?>
                                </span>
                            </div>

                            <div class="hx-meta">
                                <span class="hx-chip"><i class="fas fa-at"></i>
                                    <span class="t"><?= htmlspecialchars($user['nom_utilisateur'] ?? '') ?></span></span>
                                <span class="hx-chip"><i class="fas fa-envelope"></i>
                                    <span class="t"><?= htmlspecialchars($user['email'] ?? 'Non renseigné') ?></span></span>
                                <?php if (!empty($user['sexe'])): ?>
                                    <span class="hx-chip"><i class="fas <?= $user['sexe'] === 'M' ? 'fa-mars' : 'fa-venus' ?>"></i>
                                        <?= $user['sexe'] === 'M' ? 'Masculin' : 'Féminin' ?></span>
                                <?php endif; ?>
                                <span class="hx-chip hx-status <?= $is_connected ? 'hx-chip--ok' : 'hx-chip--muted' ?>">
                                    <span class="hx-dot"></span>
                                    <?= $is_connected ? 'En ligne' : 'Hors ligne' ?>
                                </span>
                                <?php if ($user['derniere_connexion']): ?>
                                    <span class="hx-chip hide-sm" title="Dernière connexion"><i class="fas fa-clock"></i>
                                        <?= date('d/m/Y H:i', strtotime($user['derniere_connexion'])) ?></span>
                                    <?php if ($user['jours_actifs'] > 0): ?>
                                        <span class="hx-chip hide-sm" title="Jours actifs"><i class="fas fa-calendar-check"></i>
                                            <?= (int) $user['jours_actifs'] ?> j</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <span class="hx-go"><i class="fas fa-chevron-right"></i></span>
                    </a>
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
        // RECHERCHE AUTOMATIQUE (450 ms après la dernière frappe)
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

        // ============================================================
        // MISE À JOUR AUTOMATIQUE DES STATUTS (30 s)
        // ============================================================
        setInterval(function () {
            fetch(window.location.href, { credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var fresh = doc.querySelectorAll('.hx-item');
                    document.querySelectorAll('.hx-item').forEach(function (item, i) {
                        if (!fresh[i]) return;
                        var n = fresh[i].querySelector('.hx-status');
                        var c = item.querySelector('.hx-status');
                        if (n && c) {
                            c.className = n.className;
                            c.innerHTML = n.innerHTML;
                        }
                    });
                })
                .catch(function () { });
        }, 30000);
    </script>

</body>

</html>