<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../includes/auth_check.php';
include("../connexion.php");

if (!isset($_SESSION['role'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id_utilisateur'] ?? null;

if (!$user_id) {
    header("Location: ../index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: ../index.php");
    exit();
}

$is_admin = ($user['role'] ?? 'user') === 'admin';

$nom_complet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
if ($nom_complet === '') {
    $nom_complet = $user['nom_utilisateur'];
}

$initiales = strtoupper(mb_substr($user['prenom'] ?? '', 0, 1) . mb_substr($user['nom'] ?? '', 0, 1));
if ($initiales === '') {
    $initiales = strtoupper(mb_substr($user['nom_utilisateur'], 0, 2));
}

$membre_depuis = strtotime($user['date_creation']);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil - Bertin Movie</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=3">
    <link rel="stylesheet" href="/film_web_dy/admin/hx-style.css?v=4">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content hx-wrap hx-wide">

        <div class="hx-head">
            <div class="hx-title">
                <div class="hx-title-ico"><i class="fas fa-user-circle"></i></div>
                <div>
                    <h1>Mon profil</h1>
                    <p>Vos informations de compte</p>
                </div>
            </div>
        </div>

        <!-- ===== CARTE PROFIL ===== -->
        <div class="hx-hero">
            <div class="hx-hero-cover"></div>
            <div class="hx-hero-body">
                <div class="hx-hero-avatar"><?= htmlspecialchars($initiales) ?></div>
                <div class="hx-hero-id">
                    <h2><?= htmlspecialchars($nom_complet) ?></h2>
                    <div class="hx-sub"><i class="fas fa-at"></i> <?= htmlspecialchars($user['nom_utilisateur']) ?>
                    </div>
                    <span class="hx-role <?= $is_admin ? 'hx-role--admin' : 'hx-role--user' ?>">
                        <?= $is_admin ? 'Admin' : 'User' ?>
                    </span>
                </div>

                <div class="hx-hero-actions">
                    <a href="../admin/modifier_profil.php" class="hx-btn hx-btn--primary">
                        <i class="fas fa-user-edit"></i> Modifier le profil
                    </a>
                    <a href="../admin/modifier_motdepasse.php" class="hx-btn hx-btn--amber">
                        <i class="fas fa-key"></i> Mot de passe
                    </a>
                    <a href="../admin/dashboard.php" class="hx-btn hx-btn--ghost">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                </div>
            </div>
        </div>

        <!-- ===== INFOS ===== -->
        <div class="hx-info">
            <div class="hx-info-item">
                <div class="hx-info-ico"><i class="fas fa-user"></i></div>
                <div style="min-width:0;">
                    <small>Nom d'utilisateur</small>
                    <b><?= htmlspecialchars($user['nom_utilisateur']) ?></b>
                </div>
            </div>
            <div class="hx-info-item">
                <div class="hx-info-ico"><i class="fas fa-envelope"></i></div>
                <div style="min-width:0;">
                    <small>E-mail</small>
                    <b><?= htmlspecialchars($user['email'] ?? 'Non renseigné') ?></b>
                </div>
            </div>
            <div class="hx-info-item">
                <div class="hx-info-ico"><i class="fas fa-user-tag"></i></div>
                <div style="min-width:0;">
                    <small>Rôle</small>
                    <b><?= $is_admin ? 'Administrateur' : 'Utilisateur' ?></b>
                </div>
            </div>
            <div class="hx-info-item">
                <div class="hx-info-ico"><i class="fas fa-calendar-alt"></i></div>
                <div style="min-width:0;">
                    <small>Membre depuis</small>
                    <b><?= date('d/m/Y', $membre_depuis) ?> à <?= date('H:i', $membre_depuis) ?></b>
                </div>
            </div>
        </div>

    </div>

</body>

</html>