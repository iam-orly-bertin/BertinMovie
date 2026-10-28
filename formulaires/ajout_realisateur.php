<?php
include("../connexion.php");

if (isset($_POST['save'])) {
    $sql = "INSERT INTO realisateur (nom, prenom, nationalite) VALUES (?, ?, ?)";
    $pdo->prepare($sql)->execute([
        $_POST['nom'],
        $_POST['prenom'],
        $_POST['nationalite']
    ]);
    header("Location: ../admin/realisateur.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter Réalisateur</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content">
        <div class="form-card">
            <h2><i class="fas fa-user-plus" style="color:#06b6d4;"></i> Ajouter un réalisateur</h2>

            <form method="POST">
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" name="nom" required placeholder="Ex: Ciza">
                </div>

                <div class="form-group">
                    <label>Prénom</label>
                    <input type="text" name="prenom" required placeholder="Ex: orly">
                </div>

                <div class="form-group">
                    <label>Nationalité</label>
                    <input type="text" name="nationalite" placeholder="Ex: Américaine">
                </div>

                <button class="btn-save" name="save">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>

            <div class="back">
                <a href="../admin/realisateur.php"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </div>
    </div>

</body>

</html>