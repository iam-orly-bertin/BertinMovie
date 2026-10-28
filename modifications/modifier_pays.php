<?php
include("../connexion.php");

$id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM pays WHERE id_pays = ?");
$stmt->execute([$id]);
$pays = $stmt->fetch();

if (!$pays) {
    header("Location: ../admin/pays.php");
    exit();
}

if (isset($_POST['update'])) {
    $sql = "UPDATE pays SET nom_pays = ? WHERE id_pays = ?";
    $pdo->prepare($sql)->execute([$_POST['nom_pays'], $id]);
    header("Location: ../admin/pays.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Pays</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content">
        <div class="form-card">
            <h2><i class="fas fa-edit" style="color:#f59e0b;"></i> Modifier le pays</h2>

            <form method="POST">
                <div class="form-group">
                    <label>Nom du pays</label>
                    <input type="text" name="nom_pays" value="<?= htmlspecialchars($pays['nom_pays']) ?>" required>
                </div>

                <button class="btn-save" name="update" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <i class="fas fa-save"></i> Mettre à jour
                </button>
            </form>

            <div class="back">
                <a href="../admin/pays.php"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </div>
    </div>

</body>

</html>