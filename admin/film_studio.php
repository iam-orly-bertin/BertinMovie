<?php
include("../connexion.php");

require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../includes/auth_check.php';

/* =========================
   SUPPRESSION RELATION
========================= */
if (isset($_GET["supp"])) {
    $id = (int) $_GET["supp"];
    $pdo->prepare("DELETE FROM film_studio WHERE id_film_studio = ?")->execute([$id]);
    header("Location: film_studio.php");
    exit();
}

/* =========================
   RECHERCHE
========================= */
$search = $_GET['search'] ?? '';

$sql = "
SELECT fs.*,
       f.nom_film,
       s.nom_studio
FROM film_studio fs
LEFT JOIN film f ON fs.id_film = f.id_film
LEFT JOIN studio s ON fs.id_studio = s.id_studio
WHERE f.nom_film LIKE ?
   OR s.nom_studio LIKE ?
ORDER BY fs.id_film_studio DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(["%$search%", "%$search%"]);
$data = $stmt->fetchAll();

$total = count($data);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Film - Studio</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content">

        <div class="page-header">
            <h1><i class="fas fa-building" style="color:#f59e0b;"></i> Films - Studios <span>(
                    <?= $total ?>)
                </span></h1>
            <a class="btn-add" href="../formulaires/ajout_film_studio.php">
                <i class="fas fa-plus"></i> Associer
            </a>
        </div>

        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Rechercher un film ou un studio..."
                value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fas fa-search"></i> Rechercher</button>
        </form>

        <div class="card">
            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Film</th>
                            <th>Studio</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td>
                                    <?= $row['id_film_studio'] ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['nom_film']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['nom_studio']) ?>
                                </td>
                                <td>
                                    <a class="btn btn-delete" href="#"
                                        onclick="confirmDelete(<?= $row['id_film_studio'] ?>, 'film_studio.php', 'Supprimer cette association film-studio ?')">
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