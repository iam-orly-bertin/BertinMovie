<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/update_session.php';

if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT u.*, p.nom_pays 
    FROM utilisateur u 
    LEFT JOIN pays p ON u.id_pays = p.id_pays 
    WHERE u.id_utilisateur = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$nb_favoris = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_favoris WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    $nb_favoris = $stmt->fetchColumn();
} catch (PDOException $e) {
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title data-lang-fr="Mon profil" data-lang-en="My profile">Mon profil</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/film_web_dy/user/css/style-user.css">
    <style>
        /* =========================================================
           PROFIL.PHP - DESIGN MODERNE
           ========================================================= */

        .profile-container {
            max-width: 680px;
            width: 100%;
            margin: 0 auto;
            padding: 10px 16px 100px;
        }

        /* =========================================================
           CARTE PRINCIPALE
           ========================================================= */
        .profile-card {
            background: var(--bg-card, #ffffff);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border-color, #e2e8f0);
            transition: box-shadow 0.3s ease;
        }

        .profile-card:hover {
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.10);
        }

        body.dark-mode .profile-card {
            background: #1e293b;
            border-color: #334155;
        }

        /* =========================================================
           BANNIÈRE
           ========================================================= */
        .profile-cover {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 50%, #60a5fa 100%);
            padding: 32px 32px 20px 32px;
            position: relative;
        }

        /* =========================================================
           SECTION AVATAR + NOM
           ========================================================= */
        .profile-top {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .avatar-wrapper {
            flex-shrink: 0;
        }

        .avatar {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 700;
            color: #2563eb;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            border: 4px solid rgba(255, 255, 255, 0.5);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.20);
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-info h1 {
            color: #ffffff;
            font-size: 26px;
            font-weight: 700;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.10);
            margin: 0 0 2px 0;
            word-break: break-word;
        }

        .user-info .handle {
            color: rgba(255, 255, 255, 0.85);
            font-size: 15px;
            font-weight: 400;
        }

        .user-info .role-tag {
            display: inline-block;
            margin-top: 6px;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.20);
            color: #ffffff;
            backdrop-filter: blur(4px);
        }

        .role-tag.admin {
            background: rgba(251, 191, 36, 0.35);
        }

        .member-since {
            margin-top: 12px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.80);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .member-since i {
            font-size: 14px;
        }

        /* =========================================================
           CORPS DE LA CARTE
           ========================================================= */
        .profile-body {
            padding: 28px 32px 32px;
        }

        /* =========================================================
           GRILLE D'INFORMATIONS
           ========================================================= */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }

        .info-item {
            background: var(--bg-input, #f8fafc);
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid var(--border-color, #e2e8f0);
            transition: all 0.2s ease;
        }

        body.dark-mode .info-item {
            background: #0f172a;
            border-color: #334155;
        }

        .info-item:hover {
            background: var(--bg-hover, #f0f4ff);
            border-color: #3b82f6;
        }

        body.dark-mode .info-item:hover {
            background: #1e293b;
            border-color: #3b82f6;
        }

        .info-item .label {
            font-size: 11px;
            color: var(--text-secondary, #64748b);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        body.dark-mode .info-item .label {
            color: #94a3b8;
        }

        .info-item .label i {
            margin-right: 6px;
            color: #3b82f6;
            width: 14px;
        }

        .info-item .value {
            font-size: 15px;
            font-weight: 500;
            color: var(--text-primary, #0f172a);
        }

        body.dark-mode .info-item .value {
            color: #e2e8f0;
        }

        .info-item .value .empty {
            color: var(--text-secondary, #94a3b8);
            font-weight: 400;
            font-style: italic;
            font-size: 13px;
        }

        /* =========================================================
           STATISTIQUES
           ========================================================= */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: var(--bg-input, #f8fafc);
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border-color, #e2e8f0);
            transition: all 0.3s ease;
        }

        body.dark-mode .stat-box {
            background: #0f172a;
            border-color: #334155;
        }

        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.08);
            border-color: #3b82f6;
        }

        body.dark-mode .stat-box:hover {
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.12);
            border-color: #3b82f6;
        }

        .stat-box .stat-icon {
            font-size: 20px;
            margin-bottom: 4px;
            display: block;
        }

        .stat-box .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary, #0f172a);
            line-height: 1.2;
        }

        body.dark-mode .stat-box .stat-number {
            color: #e2e8f0;
        }

        .stat-box .stat-number.fav {
            color: #3b82f6;
        }

        .stat-box .stat-number.movies {
            color: #8b5cf6;
        }

        .stat-box .stat-number.views {
            color: #06b6d4;
        }

        .stat-box .stat-label {
            font-size: 12px;
            color: var(--text-secondary, #64748b);
            font-weight: 500;
            margin-top: 2px;
        }

        body.dark-mode .stat-box .stat-label {
            color: #94a3b8;
        }

        /* =========================================================
           BOUTONS D'ACTION
           ========================================================= */
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .btn {
            padding: 12px 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #ffffff;
        }

        .btn i {
            font-size: 15px;
        }

        .btn-edit {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 2px 12px rgba(59, 130, 246, 0.25);
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-3px);
            box-shadow: 0 6px 24px rgba(59, 130, 246, 0.35);
        }

        .btn-password {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            box-shadow: 0 2px 12px rgba(34, 197, 94, 0.25);
        }

        .btn-password:hover {
            background: linear-gradient(135deg, #16a34a, #15803d);
            transform: translateY(-3px);
            box-shadow: 0 6px 24px rgba(34, 197, 94, 0.35);
        }

        .btn-logout {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 2px 12px rgba(239, 68, 68, 0.25);
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-3px);
            box-shadow: 0 6px 24px rgba(239, 68, 68, 0.35);
        }

        .btn:active {
            transform: scale(0.97);
        }

        /* =========================================================
           ANIMATION
           ========================================================= */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-card {
            animation: slideUp 0.5s ease-out;
        }

        /* =========================================================
           RESPONSIVE - MOBILE
           ========================================================= */
        @media (max-width: 768px) {
            .profile-container {
                padding: 8px 12px 90px;
            }

            .profile-cover {
                padding: 24px 20px 16px 20px;
            }

            .profile-top {
                gap: 16px;
            }

            .avatar {
                width: 76px;
                height: 76px;
                font-size: 32px;
            }

            .user-info h1 {
                font-size: 22px;
            }

            .user-info .handle {
                font-size: 14px;
            }

            .profile-body {
                padding: 20px 20px 24px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .stats-row {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }

            .stat-box .stat-number {
                font-size: 20px;
            }

            .action-buttons {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .btn {
                padding: 14px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .profile-container {
                padding: 6px 8px 80px;
            }

            .profile-cover {
                padding: 18px 16px 14px 16px;
            }

            .profile-top {
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }

            .avatar {
                width: 72px;
                height: 72px;
                font-size: 30px;
            }

            .user-info h1 {
                font-size: 20px;
            }

            .user-info .handle {
                font-size: 13px;
            }

            .user-info .role-tag {
                font-size: 11px;
                padding: 3px 14px;
            }

            .member-since {
                justify-content: center;
                font-size: 12px;
            }

            .profile-body {
                padding: 16px 14px 20px;
            }

            .info-grid {
                gap: 8px;
            }

            .info-item {
                padding: 10px 14px;
            }

            .info-item .label {
                font-size: 10px;
            }

            .info-item .value {
                font-size: 14px;
            }

            .stats-row {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }

            .stat-box {
                padding: 12px 8px;
            }

            .stat-box .stat-number {
                font-size: 18px;
            }

            .stat-box .stat-icon {
                font-size: 16px;
            }

            .stat-box .stat-label {
                font-size: 10px;
            }

            .action-buttons {
                gap: 6px;
            }

            .btn {
                padding: 12px;
                font-size: 13px;
                border-radius: 10px;
            }

            .btn i {
                font-size: 14px;
            }
        }

        @media (max-width: 380px) {
            .profile-container {
                padding: 4px 4px 70px;
            }

            .profile-cover {
                padding: 14px 12px 12px 12px;
            }

            .avatar {
                width: 60px;
                height: 60px;
                font-size: 26px;
            }

            .user-info h1 {
                font-size: 17px;
            }

            .user-info .handle {
                font-size: 12px;
            }

            .profile-body {
                padding: 12px 10px 16px;
            }

            .info-item {
                padding: 8px 10px;
            }

            .info-item .label {
                font-size: 9px;
            }

            .info-item .value {
                font-size: 13px;
            }

            .stats-row {
                gap: 6px;
            }

            .stat-box {
                padding: 10px 6px;
            }

            .stat-box .stat-number {
                font-size: 16px;
            }

            .stat-box .stat-icon {
                font-size: 14px;
            }

            .stat-box .stat-label {
                font-size: 9px;
            }

            .btn {
                padding: 10px;
                font-size: 12px;
                border-radius: 8px;
            }

            .btn i {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/menu-user.php'; ?>

    <div class="profile-container">
        <div class="profile-card">

            <!-- ===== BANNIÈRE ===== -->
            <div class="profile-cover">
                <div class="profile-top">
                    <div class="avatar-wrapper">
                        <div class="avatar">
                            <?= strtoupper(substr($user['nom_utilisateur'] ?? 'U', 0, 1)) ?>
                        </div>
                    </div>
                    <div class="user-info">
                        <h1>
                            <?= htmlspecialchars($user['prenom'] ?? '') ?>
                            <?= htmlspecialchars($user['nom'] ?? '') ?>
                        </h1>
                        <div class="handle">@<?= htmlspecialchars($user['nom_utilisateur']) ?></div>
                        <span class="role-tag <?= $user['role'] ?>">
                            <?php if ($user['role'] == 'admin'): ?>
                                ⭐ <span data-lang-fr="Admin" data-lang-en="Admin">Admin</span>
                            <?php else: ?>
                                👤 <span data-lang-fr="Membre" data-lang-en="Member">Membre</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div class="member-since">
                    <i class="far fa-calendar-alt"></i>
                    <span data-lang-fr="Membre depuis" data-lang-en="Member since">Membre depuis</span>
                    <?= date('M Y', strtotime($user['date_creation'] ?? 'now')) ?>
                </div>
            </div>

            <!-- ===== CORPS ===== -->
            <div class="profile-body">

                <!-- ===== INFOS ===== -->
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label"><i class="fas fa-envelope"></i> <span data-lang-fr="Email" data-lang-en="Email">Email</span></div>
                        <div class="value"><?= htmlspecialchars($user['email'] ?? 'Non renseigné') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label"><i class="fas fa-venus-mars"></i> <span data-lang-fr="Sexe" data-lang-en="Gender">Sexe</span></div>
                        <div class="value">
                            <?php if ($user['sexe'] == 'M'): ?>
                                <span data-lang-fr="Masculin" data-lang-en="Male">Masculin</span>
                            <?php elseif ($user['sexe'] == 'F'): ?>
                                <span data-lang-fr="Féminin" data-lang-en="Female">Féminin</span>
                            <?php else: ?>
                                <span class="empty" data-lang-fr="Non renseigné" data-lang-en="Not specified">Non renseigné</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="label"><i class="fas fa-calendar-alt"></i> <span data-lang-fr="Naissance" data-lang-en="Birth date">Naissance</span></div>
                        <div class="value">
                            <?= !empty($user['date_naissance']) ? date('d/m/Y', strtotime($user['date_naissance'])) : '<span class="empty" data-lang-fr="Non renseignée" data-lang-en="Not specified">Non renseignée</span>' ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="label"><i class="fas fa-globe"></i> <span data-lang-fr="Nationalité" data-lang-en="Nationality">Nationalité</span></div>
                        <div class="value">
                            <?= htmlspecialchars($user['nom_pays'] ?? '<span class="empty" data-lang-fr="Non renseignée" data-lang-en="Not specified">Non renseignée</span>') ?>
                        </div>
                    </div>
                </div>

                <!-- ===== STATS ===== -->
                <div class="stats-row">
                    <div class="stat-box">
                        <span class="stat-icon">❤️</span>
                        <div class="stat-number fav"><?= $nb_favoris ?></div>
                        <div class="stat-label" data-lang-fr="Favoris" data-lang-en="Favorites">Favoris</div>
                    </div>
                    <div class="stat-box">
                        <span class="stat-icon">🎬</span>
                        <div class="stat-number movies">0</div>
                        <div class="stat-label" data-lang-fr="Films vus" data-lang-en="Movies watched">Films vus</div>
                    </div>
                    <div class="stat-box">
                        <span class="stat-icon">👀</span>
                        <div class="stat-number views">0</div>
                        <div class="stat-label" data-lang-fr="Vues" data-lang-en="Views">Vues</div>
                    </div>
                </div>

                <!-- ===== ACTIONS ===== -->
                <div class="action-buttons">
                    <a href="modifier_profil.php" class="btn btn-edit">
                        <i class="fas fa-user-edit"></i> <span data-lang-fr="Modifier" data-lang-en="Edit">Modifier</span>
                    </a>
                    <a href="modifier_motdepasse.php" class="btn btn-password">
                        <i class="fas fa-lock"></i> <span data-lang-fr="Sécurité" data-lang-en="Security">Sécurité</span>
                    </a>
                    <a href="#" class="btn btn-logout" onclick="confirmLogout(event)">
                        <i class="fas fa-sign-out-alt"></i> <span data-lang-fr="Quitter" data-lang-en="Leave">Quitter</span>
                    </a>
                </div>

            </div>
        </div>
    </div>

</body>

</html>