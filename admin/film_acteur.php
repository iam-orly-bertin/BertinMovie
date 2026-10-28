<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../includes/auth_check.php';
include("../connexion.php");



/* =========================
   SUPPRESSION RELATION
========================= */
if (isset($_GET["supp"])) {
    $id = (int) $_GET["supp"];
    $pdo->prepare("DELETE FROM film_acteur WHERE id_film_acteur = ?")->execute([$id]);
    header("Location: film_acteur.php");
    exit();
}

/* =========================
   RECHERCHE
========================= */
$search = $_GET['search'] ?? '';

$sql = "
SELECT fa.*,
       f.nom_film,
       a.nom AS nom_acteur,
       a.prenom AS prenom_acteur
FROM film_acteur fa
LEFT JOIN film f ON fa.id_film = f.id_film
LEFT JOIN acteur a ON fa.id_acteur = a.id_acteur
WHERE f.nom_film LIKE ? 
   OR a.nom LIKE ? 
   OR a.prenom LIKE ?
ORDER BY fa.id_film_acteur DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(["%$search%", "%$search%", "%$search%"]);
$data = $stmt->fetchAll();

$total = count($data);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Film - Acteurs</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content">

        <div class="page-header">
            <h1><i class="fas fa-users" style="color:#8b5cf6;"></i> Films - Acteurs <span>(
                    <?= $total ?>)
                </span></h1>
            <a class="btn-add" href="../formulaires/ajout_film_acteur.php">
                <i class="fas fa-plus"></i> Associer
            </a>
        </div>

        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Rechercher un film ou un acteur..."
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
                            <th>Acteur</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td>
                                    <?= $row['id_film_acteur'] ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['nom_film']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['prenom_acteur'] . ' ' . $row['nom_acteur']) ?>
                                </td>
                                <td>
                                    <a class="btn btn-delete" href="#"
                                        onclick="confirmDelete(<?= $row['id_film_acteur'] ?>, 'film_acteur.php', 'Supprimer cette association film-acteur ?')">
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