<?php
require_once __DIR__ . '/../includes/session_boot.php';

if (!isset($_SESSION['role'])) {
    header("Location: ../index.php");
    exit();
}

include("../connexion.php");
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/update_session.php';

// Mettre à jour la session de l'admin lui-même
if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

/* =========================================================
   EXPORT CSV
========================================================= */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("
        SELECT h.*,
               u.nom_utilisateur,
               CONCAT(COALESCE(u.prenom, ''), ' ', COALESCE(u.nom, '')) as nom_complet
        FROM historique_connexion h
        INNER JOIN utilisateur u ON h.id_utilisateur = u.id_utilisateur
        ORDER BY h.id_historique DESC
    ");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'historique_connexions_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, ['Utilisateur', 'Nom complet', 'Date de connexion', 'Type']);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['nom_utilisateur'],
            $row['nom_complet'],
            $row['date_connexion'],
            $row['type'] ?? 'connexion'
        ]);
    }

    fclose($output);
    exit();
}

/* =========================================================
   UTILISATEURS CONNECTÉS EN TEMPS RÉEL
========================================================= */
cleanInactiveSessions($pdo);

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

/* =========================================================
   DONNÉES
========================================================= */
$data = $pdo->query("
    SELECT h.*,
           u.id_utilisateur,
           u.nom_utilisateur,
           u.prenom,
           u.nom,
           u.email,
           u.role,
           CONCAT(COALESCE(u.prenom, ''), ' ', COALESCE(u.nom, '')) as nom_complet
    FROM historique_connexion h
    INNER JOIN utilisateur u ON h.id_utilisateur = u.id_utilisateur
    ORDER BY h.id_historique DESC
    LIMIT 50
")->fetchAll();

$total = count($data);
$show_count = 10;
$has_more = $total > $show_count;
$distinct_users = count(array_unique(array_column($data, 'id_utilisateur')));

// L'admin est toujours considéré comme connecté quand il est sur la page
$admin_id = $_SESSION['user_id'] ?? null;
if ($admin_id && !in_array($admin_id, $connected_users)) {
    $connected_users[] = $admin_id;
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

$last_day = null;
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des connexions - Bertin Movie</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=3">
    <link rel="stylesheet" href="/film_web_dy/admin/hx-style.css?v=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content hx-wrap">

        <!-- ===== EN-TÊTE ===== -->
        <div class="hx-head">
            <div class="hx-title">
                <div class="hx-title-ico"><i class="fas fa-right-to-bracket"></i></div>
                <div>
                    <h1>Historique des connexions</h1>
                    <p>Les <?= $total ?> dernières connexions et déconnexions</p>
                </div>
            </div>
            <div class="hx-actions">
                <a class="hx-btn hx-btn--primary" href="historique.php?export=csv">
                    <i class="fas fa-file-csv"></i> Exporter CSV
                </a>
                <a class="hx-btn hx-btn--violet" href="historique.php">
                    <i class="fas fa-sync-alt"></i> Rafraîchir
                </a>
            </div>
        </div>

        <!-- ===== SYNTHÈSE ===== -->
        <div class="hx-stats" id="hxStats">
            <div class="hx-stat hx-stat--green">
                <div class="hx-stat-ico"><i class="fas fa-signal"></i></div>
                <div><strong><?= count($connected_users) ?></strong><span>En ligne</span></div>
            </div>
            <div class="hx-stat hx-stat--blue">
                <div class="hx-stat-ico"><i class="fas fa-list-check"></i></div>
                <div><strong><?= number_format($total, 0, ',', ' ') ?></strong><span>Connexions</span></div>
            </div>
            <div class="hx-stat hx-stat--violet">
                <div class="hx-stat-ico"><i class="fas fa-users"></i></div>
                <div><strong><?= $distinct_users ?></strong><span>Utilisateurs</span></div>
            </div>
        </div>

        <!-- ===== LISTE ===== -->
        <?php if (empty($data)): ?>
            <div class="hx-empty">
                <div class="hx-empty-ico"><i class="fas fa-history"></i></div>
                <h3>Aucune connexion enregistrée</h3>
                <p>Les connexions des utilisateurs apparaîtront ici.</p>
            </div>
        <?php else: ?>
            <div class="hx-list" id="hxList">
                <?php foreach ($data as $index => $row):
                    $initiales = strtoupper(mb_substr($row['prenom'] ?? '', 0, 1) . mb_substr($row['nom'] ?? '', 0, 1));
                    if ($initiales === '') {
                        $initiales = strtoupper(mb_substr($row['nom_utilisateur'] ?? 'U', 0, 2));
                    }

                    // Type
                    $type = $row['type'] ?? 'connexion';
                    if ($type === 'connexion') {
                        $type_label = 'Connexion';
                        $type_chip = 'hx-chip--ok';
                        $type_icon = 'fa-sign-in-alt';
                    } elseif ($type === 'deconnexion_auto') {
                        $type_label = 'Déconnexion auto';
                        $type_chip = 'hx-chip--off';
                        $type_icon = 'fa-power-off';
                    } else {
                        $type_label = ucfirst($type);
                        $type_chip = 'hx-chip--warn';
                        $type_icon = 'fa-sign-out-alt';
                    }

                    // Statut en temps réel
                    $is_connected = in_array($row['id_utilisateur'], $connected_users);

                    // Rôle
                    $is_admin = ($row['role'] ?? 'user') === 'admin';
                    $ico_bg = $is_admin ? 'linear-gradient(135deg,#8b5cf6,#7c3aed)' : 'linear-gradient(135deg,#3b82f6,#2563eb)';
                    $ico_c = $is_admin ? 'rgba(124,58,237,.55)' : 'rgba(37,99,235,.55)';

                    $ts = strtotime($row['date_connexion']);
                    $day = date('Y-m-d', $ts);
                    $extra = $index >= $show_count ? 'hx-extra' : '';

                    if ($day !== $last_day):
                        $last_day = $day; ?>
                        <div class="hx-day <?= $extra ?>"><?= htmlspecialchars(hx_day_label($day)) ?></div>
                    <?php endif; ?>

                    <a href="utilisateur_detail.php?id=<?= (int) $row['id_utilisateur'] ?>"
                        class="hx-item hx-link <?= $extra ?>" data-user-id="<?= (int) $row['id_utilisateur'] ?>"
                        style="animation-delay: <?= min($index, 12) * 0.03 ?>s;">

                        <div class="hx-ico" style="background:<?= $ico_bg ?>;--c:<?= $ico_c ?>;">
                            <?= htmlspecialchars($initiales) ?>
                        </div>

                        <div class="hx-body">
                            <div class="hx-line">
                                <span
                                    class="hx-label"><?= htmlspecialchars(trim($row['nom_complet']) !== '' ? trim($row['nom_complet']) : $row['nom_utilisateur']) ?></span>
                                <span class="hx-role <?= $is_admin ? 'hx-role--admin' : 'hx-role--user' ?>">
                                    <?= $is_admin ? 'Admin' : 'User' ?>
                                </span>
                            </div>

                            <div class="hx-meta">
                                <span class="hx-chip"><i class="fas fa-at"></i>
                                    <?= htmlspecialchars($row['nom_utilisateur']) ?></span>
                                <span class="hx-chip" title="<?= date('d/m/Y H:i:s', $ts) ?>"><i class="fas fa-clock"></i>
                                    <?= date('H:i:s', $ts) ?></span>
                                <span class="hx-chip <?= $type_chip ?>"><i class="fas <?= $type_icon ?>"></i>
                                    <?= $type_label ?></span>
                                <span class="hx-chip hx-status <?= $is_connected ? 'hx-chip--ok' : 'hx-chip--muted' ?>">
                                    <span class="hx-dot"></span>
                                    <?= $is_connected ? 'En ligne' : 'Hors ligne' ?>
                                </span>
                            </div>
                        </div>

                        <span class="hx-go"><i class="fas fa-chevron-right"></i></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($has_more): ?>
                <button type="button" class="hx-more" id="hxMore" data-hidden="<?= $total - $show_count ?>">
                    <i class="fas fa-chevron-down"></i>
                    <span>Voir plus (<?= $total - $show_count ?> supplémentaires)</span>
                </button>
            <?php endif; ?>

            <div class="hx-live"><i class="fas fa-sync-alt"></i> Statuts mis à jour automatiquement toutes les 30 s</div>
        <?php endif; ?>

    </div>

    <script>
        // ============================================================
        // VOIR PLUS / VOIR MOINS
        // ============================================================
        (function () {
            var btn = document.getElementById('hxMore');
            var list = document.getElementById('hxList');
            if (!btn || !list) return;
            var hidden = btn.getAttribute('data-hidden');
            btn.addEventListener('click', function () {
                var open = list.classList.toggle('expanded');
                btn.querySelector('i').className = open ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
                btn.querySelector('span').textContent = open
                    ? 'Voir moins'
                    : 'Voir plus (' + hidden + ' supplémentaires)';
                if (!open) {
                    list.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        })();

        // ============================================================
        // MISE À JOUR AUTOMATIQUE DES STATUTS (30 s)
        // ============================================================
        setInterval(function () {
            fetch(window.location.href, { credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');

                    var newItems = doc.querySelectorAll('.hx-item');
                    document.querySelectorAll('.hx-item').forEach(function (item, i) {
                        if (!newItems[i]) return;
                        var n = newItems[i].querySelector('.hx-status');
                        var c = item.querySelector('.hx-status');
                        if (n && c) {
                            c.className = n.className;
                            c.innerHTML = n.innerHTML;
                        }
                    });

                    var ns = doc.getElementById('hxStats');
                    var cs = document.getElementById('hxStats');
                    if (ns && cs) cs.innerHTML = ns.innerHTML;
                })
                .catch(function () { });
        }, 30000);
    </script>

</body>

</html>