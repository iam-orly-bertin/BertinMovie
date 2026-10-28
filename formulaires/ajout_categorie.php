<?php
include("../connexion.php");

if (isset($_POST['save'])) {
    $sql = "INSERT INTO categorie (nom_categorie) VALUES (?)";
    $pdo->prepare($sql)->execute([$_POST['nom_categorie']]);
    header("Location: ../admin/categorie.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter Catégorie</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content">
        <div class="form-card">
            <h2><i class="fas fa-tag" style="color:#f59e0b;"></i> Ajouter une catégorie</h2>

            <form method="POST">
                <div class="form-group">
                    <label>Nom de la catégorie</label>
                    <input type="text" name="nom_categorie" required placeholder="Ex: Action, Comédie, Drame...">
                </div>

                <button class="btn-save" name="save">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>

            <div class="back">
                <a href="../admin/categorie.php"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </div>
    </div>

</body>

</html>