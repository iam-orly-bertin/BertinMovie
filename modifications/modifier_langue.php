<?php
include("../connexion.php");

$id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM langue WHERE id_langue = ?");
$stmt->execute([$id]);
$langue = $stmt->fetch();

if (!$langue) {
    header("Location: ../admin/langue.php");
    exit();
}

if (isset($_POST['update'])) {
    $sql = "UPDATE langue SET nom_langue = ? WHERE id_langue = ?";
    $pdo->prepare($sql)->execute([$_POST['nom_langue'], $id]);
    header("Location: ../admin/langue.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Langue</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content">
        <div class="form-card">
            <h2><i class="fas fa-edit" style="color:#f59e0b;"></i> Modifier la langue</h2>

            <form method="POST">
                <div class="form-group">
                    <label>Nom de la langue</label>
                    <input type="text" name="nom_langue" value="<?= htmlspecialchars($langue['nom_langue']) ?>"
                        required>
                </div>

                <button class="btn-save" name="update" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <i class="fas fa-save"></i> Mettre à jour
                </button>
            </form>

            <div class="back">
                <a href="../admin/langue.php"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </div>
    </div>

</body>

</html>