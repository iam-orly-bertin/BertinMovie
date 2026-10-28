<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../connexion.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    header("Location: utilisateurs.php");
    exit();
}

// Déconnexion
if (isset($_POST['deconnecter'])) {
    $user_id = (int) $_POST['user_id'];

    try {
        $pdo->prepare("DELETE FROM sessions_actives WHERE id_utilisateur = ?")->execute([$user_id]);
        $pdo->prepare("UPDATE utilisateur SET session_id = NULL WHERE id_utilisateur = ?")->execute([$user_id]);
        $pdo->prepare("
            INSERT INTO historique_connexion (id_utilisateur, date_connexion, type) 
            VALUES (?, NOW(), 'deconnexion_auto')
        ")->execute([$user_id]);

        $_SESSION['flash'] = "✅ Utilisateur déconnecté avec succès.";
        header("Location: utilisateur_detail.php?id=" . $user_id);
        exit();

    } catch (PDOException $e) {
        $_SESSION['flash'] = "❌ Erreur lors de la déconnexion.";
        header("Location: utilisateur_detail.php?id=" . $user_id);
        exit();
    }
}

// Infos utilisateur
$stmt = $pdo->prepare("
    SELECT u.*, p.nom_pays
    FROM utilisateur u
    LEFT JOIN pays p ON u.id_pays = p.id_pays
    WHERE u.id_utilisateur = ?
");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: utilisateurs.php");
    exit();
}

// Statut en temps réel
try {
    $pdo->exec("DELETE FROM sessions_actives WHERE derniere_activite < DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
} catch (Exception $e) {
}

$connected_users = [];
try {
    $connected_users = $pdo->query("
        SELECT DISTINCT id_utilisateur FROM sessions_actives
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $connected_users = [];
}

$is_user_connected = in_array($id, $connected_users);

// Statistiques
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        MAX(date_connexion) as derniere,
        MIN(date_connexion) as premiere,
        COUNT(DISTINCT DATE(date_connexion)) as jours
    FROM historique_connexion
    WHERE id_utilisateur = ?
");
$stats->execute([$id]);
$stats_user = $stats->fetch();

if (!$stats_user) {
    $stats_user = ['total' => 0, 'derniere' => null, 'premiere' => null, 'jours' => 0];
}

// Dernières connexions
$connexions = $pdo->prepare("
    SELECT * FROM historique_connexion
    WHERE id_utilisateur = ?
    ORDER BY date_connexion DESC
    LIMIT 10
");
$connexions->execute([$id]);
$dernieres = $connexions->fetchAll();
$total_connexions = count($dernieres);

$age = null;
if (!empty($user['date_naissance'])) {
    $age = date_diff(date_create($user['date_naissance']), date_create('today'))->y;
}

$nom_complet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
if (empty($nom_complet)) {
    $nom_complet = $user['nom_utilisateur'];
}

$initiales = strtoupper(mb_substr($user['prenom'] ?? '', 0, 1) . mb_substr($user['nom'] ?? '', 0, 1));
if (empty($initiales)) {
    $initiales = strtoupper(mb_substr($user['nom_utilisateur'] ?? 'U', 0, 2));
}

$is_admin = ($user['role'] ?? 'user') === 'admin';
$flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);

$show_count = 5;
$total_items = count($dernieres);
$has_more = $total_items > $show_count;
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - <?= htmlspecialchars($nom_complet) ?></title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=3">
    <link rel="stylesheet" href="/film_web_dy/admin/hx-style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content hx-wrap">

        <a href="utilisateurs.php" class="hx-back"><i class="fas fa-arrow-left"></i> Retour à la liste</a>

        <?php if (!empty($flash)):
            $is_success = strpos($flash, '✅') !== false; ?>
            <div class="hx-alert <?= $is_success ? 'hx-alert--ok' : 'hx-alert--err' ?>">
                <i class="fas <?= $is_success ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars(trim(str_replace(['✅', '❌'], '', $flash))) ?>
            </div>
        <?php endif; ?>

        <!-- ===== PROFIL ===== -->
        <div class="hx-card">
            <div class="hx-profile">
                <div class="hx-profile-main">
                    <div class="hx-avatar"
                        style="background:<?= $is_admin ? 'linear-gradient(135deg,#8b5cf6,#7c3aed)' : 'linear-gradient(135deg,#3b82f6,#2563eb)' ?>;box-shadow:0 12px 26px -10px <?= $is_admin ? 'rgba(124,58,237,.6)' : 'rgba(37,99,235,.6)' ?>;">
                        <?= htmlspecialchars($initiales) ?>
                    </div>

                    <div style="min-width:0;">
                        <h2><?= htmlspecialchars($nom_complet) ?></h2>
                        <div class="hx-sub"><i class="fas fa-at"></i>
                            <?= htmlspecialchars($user['nom_utilisateur'] ?? '') ?>
                        </div>

                        <div class="hx-meta" id="hxBadges">
                            <span class="hx-role <?= $is_admin ? 'hx-role--admin' : 'hx-role--user' ?>"
                                style="align-self:center;">
                                <?= $is_admin ? 'Admin' : 'User' ?>
                            </span>
                            <span
                                class="hx-chip hx-status <?= $is_user_connected ? 'hx-chip--ok' : 'hx-chip--muted' ?>">
                                <span class="hx-dot"></span>
                                <?= $is_user_connected ? 'En ligne' : 'Hors ligne' ?>
                            </span>
                        </div>

                        <div class="hx-meta">
                            <span class="hx-chip"><i class="fas fa-envelope"></i>
                                <span class="t"><?= htmlspecialchars($user['email'] ?? 'Non renseigné') ?></span></span>
                            <?php if (!empty($user['sexe'])): ?>
                                <span class="hx-chip"><i
                                        class="fas <?= $user['sexe'] === 'M' ? 'fa-mars' : 'fa-venus' ?>"></i>
                                    <?= $user['sexe'] === 'M' ? 'Masculin' : 'Féminin' ?></span>
                            <?php endif; ?>
                            <?php if ($age): ?>
                                <span class="hx-chip"><i class="fas fa-birthday-cake"></i> <?= $age ?> ans</span>
                            <?php endif; ?>
                            <?php if (!empty($user['nom_pays'])): ?>
                                <span class="hx-chip"><i class="fas fa-globe"></i>
                                    <?= htmlspecialchars($user['nom_pays']) ?></span>
                            <?php endif; ?>
                            <span class="hx-chip"><i class="fas fa-calendar-alt"></i> Inscrit le
                                <?= date('d/m/Y', strtotime($user['date_creation'])) ?></span>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="user_id" value="<?= $id ?>">
                    <button type="submit" name="deconnecter" class="hx-ibtn hx-del"
                        onclick="return confirm('Déconnecter cet utilisateur ?')">
                        <i class="fas fa-power-off"></i> Déconnecter
                    </button>
                </form>
            </div>
        </div>

        <!-- ===== SYNTHÈSE ===== -->
        <div class="hx-stats hx-stats--4">
            <div class="hx-stat hx-stat--blue">
                <div class="hx-stat-ico"><i class="fas fa-sign-in-alt"></i></div>
                <div><strong><?= (int) ($stats_user['total'] ?? 0) ?></strong><span>Connexions</span></div>
            </div>
            <div class="hx-stat hx-stat--green">
                <div class="hx-stat-ico"><i class="fas fa-calendar-check"></i></div>
                <div><strong><?= (int) ($stats_user['jours'] ?? 0) ?></strong><span>Jours actifs</span></div>
            </div>
            <div class="hx-stat hx-stat--orange">
                <div class="hx-stat-ico"><i class="fas fa-hourglass-start"></i></div>
                <div>
                    <strong
                        class="hx-sm"><?= $stats_user['premiere'] ? date('d/m/Y', strtotime($stats_user['premiere'])) : 'Jamais' ?></strong>
                    <span>Première connexion</span>
                </div>
            </div>
            <div class="hx-stat hx-stat--violet">
                <div class="hx-stat-ico"><i class="fas fa-hourglass-end"></i></div>
                <div>
                    <strong
                        class="hx-sm"><?= $stats_user['derniere'] ? date('d/m/Y H:i', strtotime($stats_user['derniere'])) : 'Jamais' ?></strong>
                    <span>Dernière connexion</span>
                </div>
            </div>
        </div>

        <!-- ===== HISTORIQUE ===== -->
        <div class="hx-sec">Historique des connexions (<?= $total_connexions ?> dernières)</div>

        <?php if (empty($dernieres)): ?>
            <div class="hx-empty">
                <div class="hx-empty-ico"><i class="fas fa-history"></i></div>
                <h3>Aucune connexion enregistrée</h3>
                <p>Les connexions de cet utilisateur apparaîtront ici.</p>
            </div>
        <?php else: ?>
            <div class="hx-list" id="hxList">
                <?php foreach ($dernieres as $index => $connexion):
                    $is_auto = (isset($connexion['type']) && $connexion['type'] === 'deconnexion_auto');
                    $ts = strtotime($connexion['date_connexion']);
                    $color = $is_auto ? '#ef4444' : '#22c55e';
                    ?>
                    <div class="hx-item <?= $index >= $show_count ? 'hx-extra' : '' ?>"
                        style="animation-delay: <?= min($index, 12) * 0.03 ?>s;">
                        <div class="hx-ico" style="background:<?= $color ?>;--c:<?= $color ?>;">
                            <i class="fas <?= $is_auto ? 'fa-power-off' : 'fa-sign-in-alt' ?>"></i>
                        </div>
                        <div class="hx-body">
                            <div class="hx-line">
                                <span class="hx-label"><?= $is_auto ? 'Déconnexion auto' : 'Connecté' ?></span>
                            </div>
                            <div class="hx-meta">
                                <span class="hx-chip"><i class="fas fa-calendar-alt"></i>
                                    <?= date('d/m/Y', $ts) ?></span>
                                <span class="hx-chip"><i class="fas fa-clock"></i>
                                    <?= date('H:i:s', $ts) ?></span>
                            </div>
                        </div>
                        <span></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($has_more): ?>
                <button type="button" class="hx-more" id="hxMore" data-hidden="<?= $total_items - $show_count ?>">
                    <i class="fas fa-chevron-down"></i>
                    <span>Voir plus (<?= $total_items - $show_count ?> supplémentaires)</span>
                </button>
            <?php endif; ?>
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
            });
        })();

        // ============================================================
        // MISE À JOUR AUTOMATIQUE DU STATUT (30 s)
        // ============================================================
        setInterval(function () {
            fetch(window.location.href, { credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var n = doc.querySelector('.hx-status');
                    var c = document.querySelector('.hx-status');
                    if (n && c) {
                        c.className = n.className;
                        c.innerHTML = n.innerHTML;
                    }
                })
                .catch(function () { });
        }, 30000);
    </script>

</body>

</html>