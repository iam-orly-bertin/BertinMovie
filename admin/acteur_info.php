<?php
// acteur_info.php - Page affichée quand on scanne le QR Code
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/connexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM acteur WHERE id_acteur = ?");
    $stmt->execute([$id]);
    $acteur = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acteur Info</title>
    <link rel="stylesheet" href="/film_web_dy/style-mobile.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #0f172a;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .card-info {
            background: linear-gradient(145deg, #0f172a, #1e293b);
            border-radius: 24px;
            padding: 40px;
            max-width: 400px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .card-info .icon {
            font-size: 60px;
            color: #3b82f6;
            margin-bottom: 16px;
        }

        .card-info h1 {
            font-size: 28px;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .card-info .prenom {
            font-size: 20px;
            color: #94a3b8;
            margin-bottom: 16px;
        }

        .card-info .numero {
            display: inline-block;
            background: rgba(59, 130, 246, 0.1);
            padding: 8px 24px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            color: #3b82f6;
            font-family: monospace;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }

        .card-info .sexe {
            font-size: 14px;
            color: #94a3b8;
        }

        .card-info .footer-info {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>

<body>
    <?php if (isset($acteur) && $acteur): ?>
        <div class="card-info">
            <div class="icon">🎭</div>
            <h1>
                <?= htmlspecialchars($acteur['nom']) ?>
            </h1>
            <div class="prenom">
                <?= htmlspecialchars($acteur['prenom']) ?>
            </div>
            <div class="numero">
                <?= htmlspecialchars($acteur['acteur_numero'] ?? 'N/A') ?>
            </div>
            <div class="sexe">
                <?= $acteur['sexe'] === 'M' ? '♂ Masculin' : '♀ Féminin' ?>
            </div>
            <div class="footer-info">
                <i class="fas fa-film" style="color:#3b82f6;"></i>
                Bertin Movie - Carte Acteur
            </div>
        </div>
    <?php else: ?>
        <div class="card-info">
            <div class="icon">❓</div>
            <h1>Acteur non trouvé</h1>
            <div class="prenom">Veuillez scanner un code valide</div>
        </div>
    <?php endif; ?>
</body>

</html>