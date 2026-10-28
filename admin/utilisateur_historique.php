<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/update_session.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Mettre à jour la session de l'admin
if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    header("Location: utilisateurs.php");
    exit();
}

// ============================================================
// DÉCONNEXION AUTOMATIQUE
// ============================================================
if (isset($_POST['auto_logout']) && isset($_POST['user_id'])) {
    $user_id = (int) $_POST['user_id'];
    $duration = isset($_POST['duration']) ? (int) $_POST['duration'] : 30;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO historique_connexion (id_utilisateur, date_connexion, type) 
            VALUES (?, NOW(), 'deconnexion_auto')
        ");
        $stmt->execute([$user_id]);

        try {
            $pdo->prepare("DELETE FROM sessions_actives WHERE id_utilisateur = ?")->execute([$user_id]);
        } catch (Exception $e) {
            // Table peut ne pas exister
        }

        $_SESSION['flash'] = "✅ Utilisateur déconnecté automatiquement après {$duration} secondes.";
        header("Location: utilisateur_detail.php?id=" . $user_id);
        exit();

    } catch (PDOException $e) {
        $_SESSION['flash'] = "❌ Erreur lors de la déconnexion automatique.";
        header("Location: utilisateur_detail.php?id=" . $user_id);
        exit();
    }
}

// ============================================================
// INFOS UTILISATEUR
// ============================================================
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

// ============================================================
// STATUT EN TEMPS RÉEL
// ============================================================
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

$admin_id = $_SESSION['user_id'] ?? null;
if ($admin_id && !in_array($admin_id, $connected_users)) {
    $connected_users[] = $admin_id;
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
if ($user['date_naissance']) {
    $age = date_diff(date_create($user['date_naissance']), date_create('today'))->y;
}

$est_actif = false;
if ($stats_user['derniere']) {
    $est_actif = strtotime($stats_user['derniere']) > strtotime('-7 days');
}

$nom_complet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
if (empty($nom_complet)) {
    $nom_complet = $user['nom_utilisateur'];
}

$initiales = strtoupper(substr($user['prenom'] ?? '', 0, 1) . substr($user['nom'] ?? '', 0, 1));
if (empty($initiales)) {
    $initiales = strtoupper(substr($user['nom_utilisateur'] ?? 'U', 0, 2));
}

// Message flash
$flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil -
        <?= htmlspecialchars($nom_complet) ?>
    </title>
    <link rel="stylesheet" href="/film_web_dy/style.css">
    <link rel="stylesheet" href="/film_web_dy/admin/users-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .usr-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .usr-status-badge .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .usr-status-badge .status-dot.online {
            background: #22c55e;
            animation: pulse-dot 1.5s ease-in-out infinite;
        }

        .usr-status-badge .status-dot.offline {
            background: #94a3b8;
        }

        @keyframes pulse-dot {
            0% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.3);
                opacity: 0.6;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .usr-status-badge.online {
            background: #dcfce7;
            color: #16a34a;
        }

        .usr-status-badge.offline {
            background: #f1f5f9;
            color: #94a3b8;
        }

        body.dark-mode .usr-status-badge.online {
            background: #064e3b;
            color: #86efac;
        }

        body.dark-mode .usr-status-badge.offline {
            background: #1e293b;
            color: #64748b;
        }

        body.dark-mode .usr-status-badge .status-dot.offline {
            background: #64748b;
        }

        /* MODAL */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #ffffff;
            border-radius: 16px;
            padding: 32px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.3s ease;
        }

        body.dark-mode .modal-box {
            background: #1e293b;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-box .modal-icon {
            font-size: 48px;
            display: block;
            text-align: center;
            margin-bottom: 12px;
        }

        .modal-box .modal-icon.warning {
            color: #f59e0b;
        }

        .modal-box h3 {
            font-size: 20px;
            font-weight: 700;
            text-align: center;
            color: #0f172a;
            margin-bottom: 8px;
        }

        body.dark-mode .modal-box h3 {
            color: #e2e8f0;
        }

        .modal-box p {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        body.dark-mode .modal-box p {
            color: #94a3b8;
        }

        .modal-box .countdown-display {
            text-align: center;
            font-size: 36px;
            font-weight: 800;
            color: #dc2626;
            margin: 12px 0 20px;
            font-family: monospace;
        }

        .modal-box .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .modal-box .modal-actions button {
            padding: 10px 28px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 100px;
        }

        .modal-box .modal-actions .btn-confirm {
            background: #ef4444;
            color: #fff;
        }

        .modal-box .modal-actions .btn-confirm:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .modal-box .modal-actions .btn-cancel {
            background: #e2e8f0;
            color: #475569;
        }

        .modal-box .modal-actions .btn-cancel:hover {
            background: #cbd5e1;
            transform: translateY(-2px);
        }

        body.dark-mode .modal-box .modal-actions .btn-cancel {
            background: #334155;
            color: #94a3b8;
        }

        body.dark-mode .modal-box .modal-actions .btn-cancel:hover {
            background: #475569;
            color: #e2e8f0;
        }

        .modal-box .modal-actions .btn-confirm:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Notification interne */
        .internal-notification {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9998;
            background: #ffffff;
            border-radius: 12px;
            padding: 16px 20px;
            border-left: 4px solid #3b82f6;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            max-width: 350px;
            display: none;
            animation: slideInRight 0.4s ease;
        }

        body.dark-mode .internal-notification {
            background: #1e293b;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(40px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .internal-notification.show {
            display: block;
        }

        .internal-notification .notif-title {
            font-weight: 600;
            font-size: 14px;
            color: #0f172a;
        }

        body.dark-mode .internal-notification .notif-title {
            color: #e2e8f0;
        }

        .internal-notification .notif-body {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }

        body.dark-mode .internal-notification .notif-body {
            color: #94a3b8;
        }

        .internal-notification .notif-close {
            float: right;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 16px;
            padding: 0 4px;
        }

        .internal-notification .notif-close:hover {
            color: #ef4444;
        }
    </style>
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <!-- NOTIFICATION INTERNE -->
    <div class="internal-notification" id="internalNotif">
        <button class="notif-close" onclick="closeNotification()">✕</button>
        <div class="notif-title" id="notifTitle">⏳ Déconnexion programmée</div>
        <div class="notif-body" id="notifBody">L'utilisateur sera déconnecté dans <strong>30</strong> secondes</div>
    </div>

    <!-- MODAL DE CONFIRMATION -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-box">
            <span class="modal-icon warning">⚠️</span>
            <h3>Confirmer la déconnexion</h3>
            <p>L'utilisateur <strong>
                    <?= htmlspecialchars($nom_complet) ?>
                </strong> sera déconnecté automatiquement.</p>
            <div class="countdown-display" id="modalCountdown">30s</div>
            <p style="font-size:12px;color:#94a3b8;margin-top:-10px;">Cliquez sur "Annuler" pour arrêter le processus
            </p>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="cancelAutoLogout()">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button class="btn-confirm" id="confirmBtn" onclick="confirmAutoLogout()">
                    <i class="fas fa-power-off"></i> Déconnecter
                </button>
            </div>
        </div>
    </div>

    <div class="usr-wrapper usr-profile">

        <a href="utilisateurs.php" class="usr-back">
            <i class="fas fa-arrow-left"></i> Retour à la liste
        </a>

        <!-- FLASH MESSAGE - CORRIGÉ -->
        <?php if (!empty($flash)): ?>
            <?php
            $is_success = strpos($flash, '✅') !== false;
            $alert_class = $is_success ? 'success' : 'error';
            $icon_class = $is_success ? 'fa-check-circle' : 'fa-exclamation-circle';
            ?>
            <div class="usr-flash usr-flash-<?= $alert_class ?>">
                <i class="fas <?= $icon_class ?>"></i>
                <?= htmlspecialchars($flash) ?>
            </div>
        <?php endif; ?>

        <!-- PROFIL CARD -->
        <div class="usr-profile-card">
            <div class="card-header">
                <div class="top">
                    <div class="avatar-lg">
                        <?= $initiales ?>
                    </div>
                    <div class="info">
                        <div class="name">
                            <?= htmlspecialchars($nom_complet) ?>
                        </div>
                        <div class="username">
                            <i class="fas fa-at"></i>
                            <?= htmlspecialchars($user['nom_utilisateur'] ?? '') ?>
                        </div>
                        <div class="meta">
                            <span class="item">
                                <i class="fas fa-envelope"></i>
                                <span class="val">
                                    <?= htmlspecialchars($user['email'] ?? 'Non renseigné') ?>
                                </span>
                            </span>
                            <?php if ($user['sexe']): ?>
                                <span class="item">
                                    <i class="fas <?= $user['sexe'] === 'M' ? 'fa-mars' : 'fa-venus' ?>"></i>
                                    <span class="val">
                                        <?= $user['sexe'] === 'M' ? 'Masculin' : 'Féminin' ?>
                                    </span>
                                </span>
                            <?php endif; ?>
                            <?php if ($age): ?>
                                <span class="item">
                                    <i class="fas fa-birthday-cake"></i>
                                    <span class="val">
                                        <?= $age ?> ans
                                    </span>
                                </span>
                            <?php endif; ?>
                            <?php if ($user['nom_pays']): ?>
                                <span class="item">
                                    <i class="fas fa-globe"></i>
                                    <span class="val">
                                        <?= htmlspecialchars($user['nom_pays']) ?>
                                    </span>
                                </span>
                            <?php endif; ?>
                            <span class="item">
                                <i class="fas fa-calendar-alt"></i>
                                <span class="val">Inscrit le
                                    <?= date('d/m/Y', strtotime($user['date_creation'])) ?>
                                </span>
                            </span>
                        </div>
                        <div class="badges-group">
                            <span
                                class="usr-badge <?= ($user['role'] ?? 'user') === 'admin' ? 'usr-badge-admin' : 'usr-badge-user' ?>">
                                <i
                                    class="fas <?= ($user['role'] ?? 'user') === 'admin' ? 'fa-user-shield' : 'fa-user' ?>"></i>
                                <?= ucfirst($user['role'] ?? 'user') ?>
                            </span>

                            <span class="usr-status-badge <?= $is_user_connected ? 'online' : 'offline' ?>">
                                <span class="status-dot <?= $is_user_connected ? 'online' : 'offline' ?>"></span>
                                <?= $is_user_connected ? 'En ligne' : 'Hors ligne' ?>
                            </span>

                            <?php if ($stats_user['derniere']): ?>
                                <span class="usr-badge" style="background:#dbeafe;color:#1e3a8a;">
                                    <i class="fas fa-clock"></i>
                                    Dernière :
                                    <?= date('d/m/Y H:i', strtotime($stats_user['derniere'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- BOUTON DÉCONNEXION -->
                <form method="POST" id="autoLogoutForm" style="flex-shrink:0;">
                    <input type="hidden" name="user_id" value="<?= $id ?>">
                    <input type="hidden" name="duration" id="logoutDuration" value="30">
                    <button type="button" class="usr-logout-btn" id="autoLogoutBtn">
                        <i class="fas fa-power-off"></i>
                        <span>Déconnexion auto</span>
                        <span class="countdown-text" id="btnCountdown">30s</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="usr-stats-grid">
            <div class="usr-stat-box blue">
                <span class="ico"><i class="fas fa-sign-in-alt"></i></span>
                <span class="num">
                    <?= $stats_user['total'] ?? 0 ?>
                </span>
                <span class="lbl">Connexions totales</span>
            </div>
            <div class="usr-stat-box green">
                <span class="ico"><i class="fas fa-calendar-check"></i></span>
                <span class="num">
                    <?= $stats_user['jours'] ?? 0 ?>
                </span>
                <span class="lbl">Jours actifs</span>
            </div>
            <div class="usr-stat-box orange">
                <span class="ico"><i class="fas fa-hourglass-start"></i></span>
                <span class="num">
                    <?= $stats_user['premiere'] ? date('d/m/Y', strtotime($stats_user['premiere'])) : 'Jamais' ?>
                </span>
                <span class="lbl">Première connexion</span>
            </div>
            <div class="usr-stat-box purple">
                <span class="ico"><i class="fas fa-hourglass-end"></i></span>
                <span class="num">
                    <?= $stats_user['derniere'] ? date('d/m/Y H:i', strtotime($stats_user['derniere'])) : 'Jamais' ?>
                </span>
                <span class="lbl">Dernière connexion</span>
            </div>
        </div>

        <!-- Connexions -->
        <div class="usr-connexions">
            <div class="head">
                <span class="left">
                    <i class="fas fa-history"></i> Historique des connexions
                    <span class="count">(
                        <?= $total_connexions ?> dernières)
                    </span>
                </span>
            </div>

            <?php if (empty($dernieres)): ?>
                <div style="text-align:center;padding:40px 20px;color:#64748b;">
                    <i class="fas fa-info-circle"
                        style="font-size:32px;display:block;margin-bottom:10px;color:#e2e8f0;"></i>
                    Aucune connexion enregistrée
                </div>
            <?php else: ?>
                <?php
                $show_count = 5;
                $total_items = count($dernieres);
                $has_more = $total_items > $show_count;
                ?>

                <?php foreach ($dernieres as $index => $connexion): ?>
                    <div class="item <?= $index >= $show_count ? 'hidden-connexion' : '' ?>" data-index="<?= $index ?>">
                        <div class="date">
                            <i class="fas fa-calendar-alt"></i>
                            <?= date('d/m/Y H:i:s', strtotime($connexion['date_connexion'])) ?>
                        </div>
                        <div class="status">
                            <span class="dot"></span>
                            <?= (isset($connexion['type']) && $connexion['type'] === 'deconnexion_auto') ? 'Déconnexion auto' : 'Connecté' ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($has_more): ?>
                    <button class="show-more-btn" id="showMoreBtn" onclick="toggleConnexions()">
                        <i class="fas fa-chevron-down"></i> Voir plus (
                        <?= $total_items - $show_count ?> supplémentaires)
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>

    <script>
        // ============================================================
        // DÉCONNEXION AUTOMATIQUE
        // ============================================================
        let countdownInterval = null;
        let remainingSeconds = 30;
        let isProcessing = false;
        let timerStarted = false;

        const btn = document.getElementById('autoLogoutBtn');
        const modal = document.getElementById('confirmModal');
        const modalCountdown = document.getElementById('modalCountdown');
        const btnCountdown = document.getElementById('btnCountdown');
        const confirmBtn = document.getElementById('confirmBtn');
        const notif = document.getElementById('internalNotif');
        const notifTitle = document.getElementById('notifTitle');
        const notifBody = document.getElementById('notifBody');
        const form = document.getElementById('autoLogoutForm');

        function showNotification(message, title) {
            title = title || '⏳ Déconnexion programmée';
            notifTitle.textContent = title;
            notifBody.innerHTML = message;
            notif.classList.add('show');
            setTimeout(function () {
                notif.classList.remove('show');
            }, 10000);
        }

        function closeNotification() {
            notif.classList.remove('show');
        }

        function startCountdown() {
            if (timerStarted) return;
            timerStarted = true;
            isProcessing = true;

            remainingSeconds = 30;
            modal.classList.add('active');
            updateCountdownDisplay();

            var userName = '<?= htmlspecialchars($nom_complet) ?>';
            showNotification(
                'L\'utilisateur <strong>' + userName + '</strong> sera déconnecté dans <strong>30</strong> secondes.',
                '⏳ Déconnexion programmée'
            );

            countdownInterval = setInterval(function () {
                remainingSeconds--;
                updateCountdownDisplay();

                if (remainingSeconds <= 0) {
                    clearInterval(countdownInterval);
                    modalCountdown.textContent = '0s';
                    btnCountdown.textContent = '0s';
                    confirmBtn.disabled = true;
                    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Déconnexion...';

                    setTimeout(function () {
                        form.submit();
                    }, 500);
                }
            }, 1000);
        }

        function updateCountdownDisplay() {
            modalCountdown.textContent = remainingSeconds + 's';
            btnCountdown.textContent = remainingSeconds + 's';
        }

        function cancelAutoLogout() {
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
            timerStarted = false;
            isProcessing = false;
            modal.classList.remove('active');
            btnCountdown.textContent = '30s';
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-power-off"></i> Déconnecter';
            closeNotification();

            var userName = '<?= htmlspecialchars($nom_complet) ?>';
            showNotification(
                'Déconnexion automatique annulée pour <strong>' + userName + '</strong>.',
                'ℹ️ Annulation'
            );
        }

        function confirmAutoLogout() {
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Déconnexion...';
            modalCountdown.textContent = '0s';

            setTimeout(function () {
                form.submit();
            }, 500);
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (isProcessing) return;
            startCountdown();
        });

        // ============================================================
        // VOIR PLUS / VOIR MOINS
        // ============================================================
        function toggleConnexions() {
            var hiddenItems = document.querySelectorAll('.usr-connexions .item.hidden-connexion');
            var btn = document.getElementById('showMoreBtn');

            if (hiddenItems.length > 0) {
                hiddenItems.forEach(function (item) {
                    item.classList.remove('hidden-connexion');
                });
                btn.innerHTML = '<i class="fas fa-chevron-up"></i> Voir moins';
            } else {
                var allItems = document.querySelectorAll('.usr-connexions .item');
                allItems.forEach(function (item, index) {
                    if (index >= 5) {
                        item.classList.add('hidden-connexion');
                    }
                });
                var total = allItems.length;
                btn.innerHTML = '<i class="fas fa-chevron-down"></i> Voir plus (' + (total - 5) + ' supplémentaires)';
            }
        }

        // Fermer la modal avec Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                cancelAutoLogout();
            }
        });

        // Fermer en cliquant sur l'overlay
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                cancelAutoLogout();
            }
        });
    </script>

</body>

</html>