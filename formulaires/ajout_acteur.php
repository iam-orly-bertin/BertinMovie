<?php
// Cette page n'est ni dans /admin/ ni dans /user/ : on indique à session_boot.php qu'elle appartient
// à la zone admin, pour qu'elle utilise le même cookie de session (BM_ADMIN) que les pages admin.
define('BM_SESSION_NAME', 'BM_ADMIN');
require_once __DIR__ . '/../includes/session_boot.php';
include("../connexion.php");
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notification.php';

if (isset($_POST['save'])) {
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $sexe = $_POST['sexe'];

    $sql = "INSERT INTO acteur (nom, prenom, sexe) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nom, $prenom, $sexe]);

    $acteur_id = $pdo->lastInsertId();
    $nom_complet = $prenom . ' ' . $nom;

    // 🔥 NOTIFICATION AJOUT
    addHistoriqueAction($pdo, $_SESSION['user_id'], 'ajout', 'acteur', $acteur_id, $nom_complet, "Acteur ajouté : $nom_complet");
    showNotif('success', "L'acteur \"$nom_complet\" a été ajouté avec succès");

    header("Location: ../admin/acteurs.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter Acteur</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="content">
        <div class="form-card">
            <h2><i class="fas fa-user-plus" style="color:#8b5cf6;"></i> Ajouter un acteur</h2>

            <form method="POST">
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" name="nom" required placeholder="Ex: Bertin">
                </div>

                <div class="form-group">
                    <label>Prénom</label>
                    <input type="text" name="prenom" required placeholder="Ex: Ciza">
                </div>

                <div class="form-group">
                    <label>Sexe</label>
                    <select name="sexe" required>
                        <option value="M">Masculin</option>
                        <option value="F">Féminin</option>
                    </select>
                </div>

                <button class="btn-save" name="save">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>

            <div class="back">
                <a href="../admin/acteurs.php"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </div>
    </div>

</body>

</html>