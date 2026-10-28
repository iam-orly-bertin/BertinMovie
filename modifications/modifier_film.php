<?php
// Cette page n'est ni dans /admin/ ni dans /user/ : on indique à session_boot.php qu'elle appartient
// à la zone admin, pour qu'elle utilise le même cookie de session (BM_ADMIN) que les pages admin.
define('BM_SESSION_NAME', 'BM_ADMIN');
require_once __DIR__ . '/../includes/session_boot.php';
include("../connexion.php");
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notification.php';

/* =========================
   RECUPERATION ID
========================= */
$id = (int) $_GET['id'];

/* =========================
   DONNEES POUR SELECT
========================= */
$real = $pdo->query("SELECT * FROM realisateur ORDER BY nom")->fetchAll();
$lang = $pdo->query("SELECT * FROM langue ORDER BY nom_langue")->fetchAll();
$cat = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();

/* =========================
   RECUPERER FILM ACTUEL
========================= */
$stmt = $pdo->prepare("SELECT * FROM film WHERE id_film = ?");
$stmt->execute([$id]);
$film = $stmt->fetch();

if (!$film) {
    header("Location: ../admin/film.php");
    exit();
}

/* =========================
   UPDATE - AVEC NOTIFICATION
========================= */
if (isset($_POST['update'])) {
    $nom_film = $_POST['nom_film'];
    $titre = $_POST['titre'];
    $duree = $_POST['duree'];
    $date_sortie = $_POST['date_sortie'];
    $id_realisateur = $_POST['id_realisateur'];
    $id_langue = $_POST['id_langue'];
    $id_categorie = $_POST['id_categorie'];
    $film_numero = $_POST['film_numero'];

    $sql = "UPDATE film SET
        nom_film = ?,
        titre = ?,
        duree = ?,
        date_sortie = ?,
        id_realisateur = ?,
        id_langue = ?,
        id_categorie = ?,
        film_numero = ?
        WHERE id_film = ?";

    $pdo->prepare($sql)->execute([
        $nom_film,
        $titre,
        $duree,
        $date_sortie,
        $id_realisateur,
        $id_langue,
        $id_categorie,
        $film_numero,
        $id
    ]);

    // 🔥 NOTIFICATION DE MODIFICATION
    addHistoriqueAction($pdo, $_SESSION['user_id'], 'modification', 'film', $id, $nom_film, "Film modifié : $nom_film");
    showNotif('info', "Le film \"$nom_film\" a été modifié avec succès");

    header("Location: ../admin/film.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Film</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="content">
        <div class="form-card">
            <h2><i class="fas fa-edit" style="color:#f59e0b;"></i> Modifier le film</h2>

            <form method="POST">
                <div class="form-group">
                    <label>Nom du film</label>
                    <input type="text" name="nom_film" value="<?= htmlspecialchars($film['nom_film']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="titre" value="<?= htmlspecialchars($film['titre']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Durée</label>
                    <input type="time" name="duree" value="<?= $film['duree'] ?>" required>
                </div>

                <div class="form-group">
                    <label>Date de sortie</label>
                    <input type="date" name="date_sortie" value="<?= $film['date_sortie'] ?>" required>
                </div>

                <div class="form-group">
                    <label>Réalisateur</label>
                    <select name="id_realisateur" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($real as $r): ?>
                            <option value="<?= $r['id_realisateur'] ?>" <?= $r['id_realisateur'] == $film['id_realisateur'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['nom'] . ' ' . $r['prenom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Langue</label>
                    <select name="id_langue" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($lang as $l): ?>
                            <option value="<?= $l['id_langue'] ?>" <?= $l['id_langue'] == $film['id_langue'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['nom_langue']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Catégorie</label>
                    <select name="id_categorie" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($cat as $c): ?>
                            <option value="<?= $c['id_categorie'] ?>" <?= $c['id_categorie'] == $film['id_categorie'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nom_categorie']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Numéro du film</label>
                    <input type="text" name="film_numero" value="<?= htmlspecialchars($film['film_numero']) ?>">
                </div>

                <button class="btn-save" name="update" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <i class="fas fa-save"></i> Mettre à jour
                </button>
            </form>

            <div class="back">
                <a href="../admin/film.php"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </div>
    </div>

</body>

</html>