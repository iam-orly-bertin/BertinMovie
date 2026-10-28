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

    // Récupérer le nom de la langue
    $stmt = $pdo->prepare("SELECT nom_langue FROM langue WHERE id_langue = ?");
    $stmt->execute([$id]);
    $langue = $stmt->fetch();
    $nom_langue = $langue['nom_langue'] ?? 'ID ' . $id;

    // Suppression
    $stmt = $pdo->prepare("DELETE FROM langue WHERE id_langue = ?");
    $stmt->execute([$id]);

    // Enregistrement dans l'historique
    addHistoriqueAction($pdo, $_SESSION['user_id'], 'suppression', 'langue', $id, $nom_langue, "Langue supprimée : $nom_langue");

    // Notification
    showNotif('success', "La langue \"$nom_langue\" a été supprimée avec succès");

    header("Location: langue.php");
    exit();
}

/* =========================================================
   RECHERCHE
========================================================= */
$search = $_GET['search'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM langue WHERE nom_langue LIKE ? ORDER BY id_langue DESC");
$stmt->execute(["%$search%"]);
$data = $stmt->fetchAll();

$total = count($data);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Langues</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="content">

        <div class="page-header">
            <h1><i class="fas fa-language" style="color:#10b981;"></i> Gestion des Langues <span>(<?= $total ?>)</span>
            </h1>
            <a class="btn-add" href="../formulaires/ajout_langue.php">
                <i class="fas fa-plus"></i> Ajouter
            </a>
        </div>

        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Rechercher une langue..."
                value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fas fa-search"></i> Rechercher</button>
        </form>

        <div class="card">
            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom de la langue</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td><?= $row['id_langue'] ?></td>
                                <td><?= htmlspecialchars($row['nom_langue']) ?></td>
                                <td>
                                    <a class="btn btn-edit"
                                        href="../modifications/modifier_langue.php?id=<?= $row['id_langue'] ?>">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a class="btn btn-delete" href="#"
                                        onclick="confirmDelete(<?= $row['id_langue'] ?>, 'langue.php', 'Supprimer cette langue ?')">
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