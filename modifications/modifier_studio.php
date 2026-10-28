<?php
include("../connexion.php");

$id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM studio WHERE id_studio = ?");
$stmt->execute([$id]);
$studio = $stmt->fetch();

if (!$studio) {
    header("Location: ../admin/studio.php");
    exit();
}

if (isset($_POST['update'])) {
    $sql = "UPDATE studio SET nom_studio = ? WHERE id_studio = ?";
    $pdo->prepare($sql)->execute([$_POST['nom_studio'], $id]);
    header("Location: ../admin/studio.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Studio</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content">
        <div class="form-card">
            <h2><i class="fas fa-edit" style="color:#f59e0b;"></i> Modifier le studio</h2>

            <form method="POST">
                <div class="form-group">
                    <label>Nom du studio</label>
                    <input type="text" name="nom_studio" value="<?= htmlspecialchars($studio['nom_studio']) ?>"
                        required>
                </div>

                <button class="btn-save" name="update" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <i class="fas fa-save"></i> Mettre à jour
                </button>
            </form>

            <div class="back">
                <a href="../admin/studio.php"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </div>
    </div>

</body>

</html>