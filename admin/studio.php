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

    // Récupérer le nom du studio
    $stmt = $pdo->prepare("SELECT nom_studio FROM studio WHERE id_studio = ?");
    $stmt->execute([$id]);
    $studio = $stmt->fetch();
    $nom_studio = $studio['nom_studio'] ?? 'ID ' . $id;

    // Suppression
    $stmt = $pdo->prepare("DELETE FROM studio WHERE id_studio = ?");
    $stmt->execute([$id]);

    // Enregistrement dans l'historique
    addHistoriqueAction($pdo, $_SESSION['user_id'], 'suppression', 'studio', $id, $nom_studio, "Studio supprimé : $nom_studio");

    // Notification
    showNotif('success', "Le studio \"$nom_studio\" a été supprimé avec succès");

    header("Location: studio.php");
    exit();
}

/* =========================================================
   RECHERCHE
========================================================= */
$search = $_GET['search'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM studio WHERE nom_studio LIKE ? ORDER BY id_studio DESC");
$stmt->execute(["%$search%"]);
$data = $stmt->fetchAll();

$total = count($data);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studios</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="content">

        <div class="page-header">
            <h1><i class="fas fa-building" style="color:#f59e0b;"></i> Gestion des Studios <span>(<?= $total ?>)</span>
            </h1>
            <a class="btn-add" href="../formulaires/ajout_studio.php">
                <i class="fas fa-plus"></i> Ajouter
            </a>
        </div>

        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Rechercher un studio..."
                value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fas fa-search"></i> Rechercher</button>
        </form>

        <div class="card">
            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom du studio</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td><?= $row['id_studio'] ?></td>
                                <td><?= htmlspecialchars($row['nom_studio']) ?></td>
                                <td>
                                    <a class="btn btn-edit"
                                        href="../modifications/modifier_studio.php?id=<?= $row['id_studio'] ?>">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a class="btn btn-delete" href="#"
                                        onclick="confirmDelete(<?= $row['id_studio'] ?>, 'studio.php', 'Supprimer ce studio ?')">
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