<?php
include("../connexion.php");

/* =========================
   RÉCUPÉRATION DES FILMS ET STUDIOS
========================= */
$films = $pdo->query("SELECT id_film, nom_film FROM film ORDER BY nom_film")->fetchAll();
$studios = $pdo->query("SELECT id_studio, nom_studio FROM studio ORDER BY nom_studio")->fetchAll();

/* =========================
   AJOUT
========================= */
if (isset($_POST['save'])) {
    $sql = "INSERT INTO film_studio (id_film, id_studio) VALUES (?, ?)";
    $pdo->prepare($sql)->execute([
        $_POST['id_film'],
        $_POST['id_studio']
    ]);
    header("Location: ../admin/film_studio.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Associer Film - Studio</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content">
        <div class="form-card">
            <h2><i class="fas fa-link" style="color:#f59e0b;"></i> Associer un film à un studio</h2>

            <form method="POST">
                <div class="form-group">
                    <label>Film</label>
                    <select name="id_film" required>
                        <option value="">-- Sélectionner un film --</option>
                        <?php foreach ($films as $f): ?>
                            <option value="<?= $f['id_film'] ?>">
                                <?= htmlspecialchars($f['nom_film']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Studio</label>
                    <select name="id_studio" required>
                        <option value="">-- Sélectionner un studio --</option>
                        <?php foreach ($studios as $s): ?>
                            <option value="<?= $s['id_studio'] ?>">
                                <?= htmlspecialchars($s['nom_studio']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button class="btn-save" name="save">
                    <i class="fas fa-save"></i> Associer
                </button>
            </form>

            <div class="back">
                <a href="../admin/film_studio.php"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </div>
    </div>

</body>

</html>