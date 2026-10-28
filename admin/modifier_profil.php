<?php
require_once __DIR__ . '/../includes/session_boot.php';
include("../connexion.php");
require_once __DIR__ . '/../includes/auth_check.php';

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

$message = '';
$message_type = '';

if (isset($_POST['update'])) {
    $nom_utilisateur = trim($_POST['nom_utilisateur']);
    $email = trim($_POST['email']);

    if (empty($nom_utilisateur)) {
        $message = "Le nom d'utilisateur est requis";
        $message_type = "error";
    } else {
        try {
            // Vérifier si le nom existe déjà (sauf pour l'utilisateur actuel)
            $check = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE nom_utilisateur = ? AND id_utilisateur != ?");
            $check->execute([$nom_utilisateur, $user_id]);
            if ($check->fetch()) {
                $message = "Ce nom d'utilisateur est déjà pris";
                $message_type = "error";
            } else {
                $sql = "UPDATE utilisateur SET nom_utilisateur = ?, email = ? WHERE id_utilisateur = ?";
                $pdo->prepare($sql)->execute([$nom_utilisateur, $email, $user_id]);

                // Mettre à jour la session
                $_SESSION['username'] = $nom_utilisateur;
                $_SESSION['user_nom'] = $nom_utilisateur;

                $message = "Profil mis à jour avec succès.";
                $message_type = "success";

                // Recharger les données
                $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de la mise à jour";
            $message_type = "error";
        }
    }
}

$is_admin = ($user['role'] ?? 'user') === 'admin';
// Valeurs réaffichées dans le formulaire (conserve la saisie en cas d'erreur)
$val_nom = isset($_POST['update']) && $message_type === 'error' ? trim($_POST['nom_utilisateur']) : $user['nom_utilisateur'];
$val_email = isset($_POST['update']) && $message_type === 'error' ? trim($_POST['email']) : ($user['email'] ?? '');
$is_admin_u = ($user['role'] ?? 'user') === 'admin';
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le profil - Bertin Movie</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=3">
    <link rel="stylesheet" href="/film_web_dy/admin/hx-style.css?v=4">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content hx-wrap hx-wide">

        <a href="../admin/profil.php" class="hx-back"><i class="fas fa-arrow-left"></i> Retour au profil</a>

        <div class="hx-head">
            <div class="hx-title">
                <div class="hx-title-ico"><i class="fas fa-user-edit"></i></div>
                <div>
                    <h1>Modifier le profil</h1>
                    <p>Mettez à jour vos informations</p>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="hx-alert <?= $message_type === 'success' ? 'hx-alert--ok' : 'hx-alert--err' ?>">
                <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="hx-formgrid">
            <div class="hx-card">
                <div class="hx-card-head">
                    <h2>Informations personnelles</h2>
                    <p>Modifiez votre nom d'utilisateur et votre e-mail.</p>
                </div>
                <form method="POST" class="hx-form">
                    <div>
                        <span class="hx-lab">Nom d'utilisateur</span>
                        <label class="hx-field">
                            <i class="fas fa-user"></i>
                            <input type="text" name="nom_utilisateur" value="<?= htmlspecialchars($val_nom) ?>" required
                                autocomplete="username">
                        </label>
                    </div>

                    <div>
                        <span class="hx-lab">E-mail</span>
                        <label class="hx-field">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" value="<?= htmlspecialchars($val_email) ?>"
                                placeholder="vous@exemple.com" autocomplete="email">
                        </label>
                    </div>

                    <div>
                        <span class="hx-lab">Rôle</span>
                        <label class="hx-field hx-field--static">
                            <i class="fas fa-user-tag"></i>
                            <input type="text" value="<?= $is_admin ? 'Administrateur' : 'Utilisateur' ?>" disabled>
                        </label>
                        <p class="hx-hint">Le rôle ne peut pas être modifié.</p>
                    </div>

                    <div class="hx-form-actions">
                        <button type="submit" name="update" class="hx-btn hx-btn--primary">
                            <i class="fas fa-save"></i> Mettre à jour
                        </button>
                        <a href="../admin/profil.php" class="hx-btn hx-btn--ghost">Annuler</a>
                    </div>
                </form>
            </div>

            <aside class="hx-aside">
                <?php
                $ini = strtoupper(mb_substr($user['prenom'] ?? '', 0, 1) . mb_substr($user['nom'] ?? '', 0, 1));
                if ($ini === '') {
                    $ini = strtoupper(mb_substr($user['nom_utilisateur'], 0, 2));
                }
                ?>
                <div class="hx-aside-user">
                    <div class="hx-aside-av">
                        <?= htmlspecialchars($ini) ?>
                    </div>
                    <div>
                        <b>
                            <?= htmlspecialchars($user['nom_utilisateur']) ?>
                        </b>
                        <small>
                            <?= $is_admin_u ? 'Administrateur' : 'Utilisateur' ?>
                        </small>
                    </div>
                </div>
                <h3>À savoir</h3>
                <ul>
                    <li><i class="fas fa-user"></i><span>Le nom d'utilisateur doit être unique : deux comptes ne peuvent
                            pas avoir le même.</span></li>
                    <li><i class="fas fa-envelope"></i><span>L'e-mail est facultatif, mais utile pour retrouver votre
                            compte.</span></li>
                    <li><i class="fas fa-user-tag"></i><span>Le rôle est attribué par un administrateur et ne peut pas
                            être changé ici.</span></li>
                </ul>
            </aside>
        </div>

    </div>

</body>

</html>