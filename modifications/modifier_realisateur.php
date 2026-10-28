<?php
include("../connexion.php");

$id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM realisateur WHERE id_realisateur = ?");
$stmt->execute([$id]);
$realisateur = $stmt->fetch();

if (!$realisateur) {
    header("Location: ../admin/realisateur.php");
    exit();
}

if (isset($_POST['update'])) {
    $sql = "UPDATE realisateur SET nom = ?, prenom = ?, nationalite = ? WHERE id_realisateur = ?";
    $pdo->prepare($sql)->execute([
        $_POST['nom'],
        $_POST['prenom'],
        $_POST['nationalite'],
        $id
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
    <title>Modifier Réalisateur</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content">
        <div class="form-card">
            <h2><i class="fas fa-user-edit" style="color:#f59e0b;"></i> Modifier le réalisateur</h2>

            <form method="POST">
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($realisateur['nom']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Prénom</label>
                    <input type="text" name="prenom" value="<?= htmlspecialchars($realisateur['prenom']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Nationalité</label>
                    <input type="text" name="nationalite" value="<?= htmlspecialchars($realisateur['nationalite']) ?>">
                </div>

                <button class="btn-save" name="update" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <i class="fas fa-save"></i> Mettre à jour
                </button>
            </form>

            <div class="back">
                <a href="../admin/realisateur.php"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </div>
    </div>

</body>

</html>