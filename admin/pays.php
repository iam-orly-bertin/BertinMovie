<?php
require_once __DIR__ . '/../includes/session_boot.php';
include("../connexion.php");
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notification.php';

/* =========================================================
   SUPPRESSION INLINE - AVEC NOTIFICATION
========================================================= */
if (isset($_GET["supp"])) {
    $id = (int) $_GET["supp"];

    // Récupérer le nom du pays
    $stmt = $pdo->prepare("SELECT nom_pays FROM pays WHERE id_pays = ?");
    $stmt->execute([$id]);
    $pays = $stmt->fetch();
    $nom_pays = $pays['nom_pays'] ?? 'ID ' . $id;

    // Suppression
    $stmt = $pdo->prepare("DELETE FROM pays WHERE id_pays = ?");
    $stmt->execute([$id]);

    // Enregistrement dans l'historique
    addHistoriqueAction($pdo, $_SESSION['user_id'], 'suppression', 'pays', $id, $nom_pays, "Pays supprimé : $nom_pays");

    // Notification
    showNotif('success', "Le pays \"$nom_pays\" a été supprimé avec succès");

    header("Location: pays.php");
    exit();
}

/* =========================================================
   RECHERCHE
========================================================= */
$search = $_GET['search'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM pays WHERE nom_pays LIKE ? ORDER BY id_pays DESC");
$stmt->execute(["%$search%"]);
$data = $stmt->fetchAll();

$total = count($data);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pays</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="content">

        <div class="page-header">
            <h1><i class="fas fa-globe" style="color:#8b5cf6;"></i> Gestion des Pays <span>(<?= $total ?>)</span></h1>
            <a class="btn-add" href="../formulaires/ajout_pays.php">
                <i class="fas fa-plus"></i> Ajouter
            </a>
        </div>

        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Rechercher un pays..."
                value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fas fa-search"></i> Rechercher</button>
        </form>

        <div class="card">
            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom du pays</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td><?= $row['id_pays'] ?></td>
                                <td><?= htmlspecialchars($row['nom_pays']) ?></td>
                                <td>
                                    <a class="btn btn-edit"
                                        href="../modifications/modifier_pays.php?id=<?= $row['id_pays'] ?>">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a class="btn btn-delete" href="#"
                                        onclick="confirmDelete(<?= $row['id_pays'] ?>, 'pays.php', 'Supprimer ce pays ?')">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>

</html>