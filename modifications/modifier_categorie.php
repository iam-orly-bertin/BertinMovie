<?php
include("../connexion.php");

$id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM categorie WHERE id_categorie = ?");
$stmt->execute([$id]);
$categorie = $stmt->fetch();

if (!$categorie) {
    header("Location: ../admin/categorie.php");
    exit();
}

if (isset($_POST['update'])) {
    $sql = "UPDATE categorie SET nom_categorie = ? WHERE id_categorie = ?";
    $pdo->prepare($sql)->execute([$_POST['nom_categorie'], $id]);
    header("Location: ../admin/categorie.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Catégorie</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content">
        <div class="form-card">
            <h2><i class="fas fa-edit" style="color:#f59e0b;"></i> Modifier la catégorie</h2>

            <form method="POST">
                <div class="form-group">
                    <label>Nom de la catégorie</label>
                    <input type="text" name="nom_categorie" value="<?= htmlspecialchars($categorie['nom_categorie']) ?>"
                        required>
                </div>

                <button class="btn-save" name="update" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <i class="fas fa-save"></i> Mettre à jour
                </button>
            </form>

            <div class="back">
                <a href="../admin/categorie.php"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </div>
    </div>

</body>

</html>