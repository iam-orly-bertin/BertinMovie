<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$message = '';
$message_type = '';

// Récupérer les films sans vidéo
$films = $pdo->query("SELECT f.*, c.nom_categorie 
                      FROM film f
                      LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
                      LEFT JOIN video_film v ON f.id_film = v.id_film
                      WHERE f.is_deleted = 0 AND v.id_video IS NULL
                      ORDER BY f.nom_film")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $film_id = (int) $_POST['film_id'];
    $url_video = trim($_POST['url_video']);
    $thumbnail = trim($_POST['thumbnail']);

    if (empty($film_id) || empty($url_video)) {
        $message = "❌ Veuillez sélectionner un film et entrer une URL.";
        $message_type = 'error';
    } else {
        try {
            $insert = $pdo->prepare("INSERT INTO video_film (id_film, url_video, thumbnail, date_upload) VALUES (?, ?, ?, NOW())");
            $insert->execute([$film_id, $url_video, $thumbnail]);

            $message = "✅ Vidéo ajoutée avec succès !";
            $message_type = 'success';

            // Rediriger vers la vidéo
            $id_video = $pdo->lastInsertId();
            header('Location: watch.php?id_video=' . $id_video . '&uploaded=1');
            exit;
        } catch (PDOException $e) {
            $message = "❌ Erreur : " . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>

<?php include_once __DIR__ . '/../includes/menu_user.php'; ?>

<div class="container">
    <div class="card">
        <h1 style="margin-bottom: 20px;">📤 Uploader une vidéo</h1>

        <a href="mes_videos.php" style="display: inline-block; margin-bottom: 20px;">← Mes vidéos</a>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="film_id">Sélectionner un film *</label>
                <select name="film_id" id="film_id" required>
                    <option value="">-- Choisissez un film --</option>
                    <?php foreach ($films as $film): ?>
                        <option value="<?php echo $film['id_film']; ?>">
                            <?php echo htmlspecialchars($film['nom_film']); ?>
                            <?php if (!empty($film['nom_categorie'])): ?>
                                (
                                <?php echo htmlspecialchars($film['nom_categorie']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (count($films) == 0): ?>
                    <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                        ⚠️ Tous les films ont déjà une vidéo.
                    </small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="url_video">URL de la vidéo *</label>
                <input type="url" name="url_video" id="url_video" required placeholder="https://example.com/video.mp4">
                <small style="color: var(--text-secondary);">Lien direct vers le fichier vidéo (MP4, WebM, etc.)</small>
            </div>

            <div class="form-group">
                <label for="thumbnail">URL du thumbnail</label>
                <input type="url" name="thumbnail" id="thumbnail" placeholder="https://example.com/thumbnail.jpg">
                <small style="color: var(--text-secondary);">Image de couverture (optionnel)</small>
            </div>

            <div style="display: flex; gap: 15px;">
                <button type="submit" class="btn btn-success" <?php echo count($films) == 0 ? 'disabled' : ''; ?>>
                    📤 Uploader
                </button>
                <a href="mes_videos.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

</body>

</html>