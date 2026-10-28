<?php
// Cette page n'est ni dans /admin/ ni dans /user/ : on indique à session_boot.php qu'elle appartient
// à la zone admin, pour qu'elle utilise le même cookie de session (BM_ADMIN) que les pages admin.
define('BM_SESSION_NAME', 'BM_ADMIN');
require_once __DIR__ . '/../includes/session_boot.php';
include("../connexion.php");
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notification.php';

$id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM acteur WHERE id_acteur = ?");
$stmt->execute([$id]);
$acteur = $stmt->fetch();

if (!$acteur) {
    header("Location: ../admin/acteurs.php");
    exit();
}

if (isset($_POST['update'])) {
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $sexe = $_POST['sexe'];

    // Anciennes valeurs pour l'historique
    $old_nom = $acteur['nom'];
    $old_prenom = $acteur['prenom'];

    $sql = "UPDATE acteur SET nom = ?, prenom = ?, sexe = ? WHERE id_acteur = ?";
    $pdo->prepare($sql)->execute([$nom, $prenom, $sexe, $id]);

    $nom_complet = $prenom . ' ' . $nom;
    $details = "Acteur modifié";
    if ($old_nom != $nom)
        $details .= ", Nom: $old_nom → $nom";
    if ($old_prenom != $prenom)
        $details .= ", Prénom: $old_prenom → $prenom";

    // 🔥 NOTIFICATION MODIFICATION
    addHistoriqueAction($pdo, $_SESSION['user_id'], 'modification', 'acteur', $id, $nom_complet, $details);
    showNotif('info', "L'acteur \"$nom_complet\" a été modifié avec succès");

    header("Location: ../admin/acteurs.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Acteur</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="content">
        <div class="form-card">
            <h2><i class="fas fa-user-edit" style="color:#f59e0b;"></i> Modifier l'acteur</h2>

            <form method="POST">
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($acteur['nom']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Prénom</label>
                    <input type="text" name="prenom" value="<?= htmlspecialchars($acteur['prenom']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Sexe</label>
                    <select name="sexe" required>
                        <option value="M" <?= $acteur['sexe'] == 'M' ? 'selected' : '' ?>>Masculin</option>
                        <option value="F" <?= $acteur['sexe'] == 'F' ? 'selected' : '' ?>>Féminin</option>
                    </select>
                </div>

                <button class="btn-save" name="update" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <i class="fas fa-save"></i> Mettre à jour
                </button>
            </form>

            <div class="back">
                <a href="../admin/acteurs.php"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </div>
    </div>

</body>

</html>