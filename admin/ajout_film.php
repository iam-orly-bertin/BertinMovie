<?php
// Cette page n'est ni dans /admin/ ni dans /user/ : on indique à session_boot.php qu'elle appartient
// à la zone admin, pour qu'elle utilise le même cookie de session (BM_ADMIN) que les pages admin.
define('BM_SESSION_NAME', 'BM_ADMIN');
require_once __DIR__ . '/../includes/session_boot.php';
include("../connexion.php");
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notification.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/bm_trace.php';

$real = $pdo->query("SELECT * FROM realisateur")->fetchAll();
$lang = $pdo->query("SELECT * FROM langue")->fetchAll();
$cat = $pdo->query("SELECT * FROM categorie")->fetchAll();

if (isset($_POST['save'])) {
    $nom_film = $_POST['nom_film'];
    $titre = $_POST['titre'];
    $duree = $_POST['duree'];
    $date_sortie = $_POST['date_sortie'];
    $id_realisateur = $_POST['id_realisateur'];
    $id_langue = $_POST['id_langue'];
    $id_categorie = $_POST['id_categorie'];

    $sql = "INSERT INTO film (nom_film, titre, duree, date_sortie, id_realisateur, id_langue, id_categorie)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nom_film, $titre, $duree, $date_sortie, $id_realisateur, $id_langue, $id_categorie]);

    $film_id = $pdo->lastInsertId();

    // 🔥 NOTIFICATION D'AJOUT
    addHistoriqueAction($pdo, $_SESSION['user_id'], 'ajout', 'film', $film_id, $nom_film, "Film ajouté : $nom_film");
    showNotif('success', "Le film \"$nom_film\" a été ajouté avec succès");

    // 📧 NOTIFICATION PAR EMAIL à tous les utilisateurs (case cochée par l'admin)
    if (!empty($_POST['notifier'])) {
        $res = envoyerNotificationFilm($pdo, $film_id, $nom_film, $titre);
        bm_trace("NOTIF-FILM-EMAIL", $_SESSION['user_id'] ?? null, null);
        error_log("Notification film #$film_id ({$res['via']}) : {$res['envoyes']} envoyé(s), {$res['echecs']} échec(s)");

        // Message affiché à l'admin après l'ajout (un seul message possible : il remplace le précédent)
        if ($res['envoyes'] > 0) {
            showNotif('success', "Le film \"$nom_film\" a été ajouté. {$res['envoyes']} email(s) de notification envoyé(s)"
                . ($res['echecs'] > 0 ? " ({$res['echecs']} échec(s))" : '') . '.');
        } elseif ($res['echecs'] > 0) {
            showNotif('warning', "Le film \"$nom_film\" a été ajouté, mais aucun email n'a pu être envoyé (méthode : {$res['via']}). Configurez includes/mail_config.php.");
        } else {
            showNotif('warning', "Le film \"$nom_film\" a été ajouté. Aucun utilisateur avec une adresse email à notifier.");
        }
    }

    header("Location: ../admin/film.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter Film</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="content">
        <div class="form-card">
            <h2><i class="fas fa-plus-circle" style="color:#3b82f6;"></i> Ajouter un film</h2>

            <form method="POST">
                <div class="form-group">
                    <label>Nom du film</label>
                    <input type="text" name="nom_film" required placeholder="Ex: Black Panther">
                </div>

                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="titre" required placeholder="Ex: Black Panther: Wakanda Forever">
                </div>

                <div class="form-group">
                    <label>Durée</label>
                    <input type="time" name="duree" required>
                </div>

                <div class="form-group">
                    <label>Date de sortie</label>
                    <input type="date" name="date_sortie" required>
                </div>

                <div class="form-group">
                    <label>Réalisateur</label>
                    <select name="id_realisateur" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($real as $r): ?>
                            <option value="<?= $r['id_realisateur'] ?>">
                                <?= htmlspecialchars($r['realisateur_numero'] . " - " . $r['nom'] . " " . $r['prenom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Langue</label>
                    <select name="id_langue" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($lang as $l): ?>
                            <option value="<?= $l['id_langue'] ?>">
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
                            <option value="<?= $c['id_categorie'] ?>">
                                <?= htmlspecialchars($c['nom_categorie']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="notifier" value="1" checked style="width:auto;">
                        <i class="fas fa-envelope"></i> Notifier les utilisateurs par email
                    </label>
                </div>

                <button class="btn-save" name="save">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>

            <div class="back">
                <a href="../admin/film.php"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </div>
    </div>

</body>

</html>