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

    // Récupérer le nom de la catégorie
    $stmt = $pdo->prepare("SELECT nom_categorie FROM categorie WHERE id_categorie = ?");
    $stmt->execute([$id]);
    $categorie = $stmt->fetch();
    $nom_categorie = $categorie['nom_categorie'] ?? 'ID ' . $id;

    // Suppression
    $stmt = $pdo->prepare("DELETE FROM categorie WHERE id_categorie = ?");
    $stmt->execute([$id]);

    // Enregistrement dans l'historique
    addHistoriqueAction($pdo, $_SESSION['user_id'], 'suppression', 'categorie', $id, $nom_categorie, "Catégorie supprimée : $nom_categorie");

    // Notification
    showNotif('success', "La catégorie \"$nom_categorie\" a été supprimée avec succès");

    header("Location: categorie.php");
    exit();
}

/* =========================================================
   RECHERCHE
========================================================= */
$search = $_GET['search'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM categorie WHERE nom_categorie LIKE ? ORDER BY id_categorie DESC");
$stmt->execute(["%$search%"]);
$data = $stmt->fetchAll();

$total = count($data);


?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catégories</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="content">

        <div class="page-header">
            <h1><i class="fas fa-tags" style="color:#f59e0b;"></i> Gestion des Catégories <span>(<?= $total ?>)</span>
            </h1>
            <a class="btn-add" href="../formulaires/ajout_categorie.php">
                <i class="fas fa-plus"></i> Ajouter
            </a>
        </div>

        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Rechercher une catégorie..."
                value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fas fa-search"></i> Rechercher</button>
        </form>

        <div class="card">
            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom de la catégorie</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td><?= $row['id_categorie'] ?></td>
                                <td><?= htmlspecialchars($row['nom_categorie']) ?></td>
                                <td>
                                    <a class="btn btn-edit"
                                        href="../modifications/modifier_categorie.php?id=<?= $row['id_categorie'] ?>">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a class="btn btn-delete" href="#"
                                        onclick="confirmDelete(<?= $row['id_categorie'] ?>, 'categorie.php', 'Supprimer cette catégorie ?')">
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