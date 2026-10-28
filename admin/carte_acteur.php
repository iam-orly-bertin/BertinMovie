<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../connexion.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// ============================================================
// GÉNÉRATION PDF - Version avec HTML + CSS (sans dompdf)
// ============================================================
if (isset($_GET['export']) && $_GET['export'] === 'pdf' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    $stmt = $pdo->prepare("SELECT * FROM acteur WHERE id_acteur = ?");
    $stmt->execute([$id]);
    $acteur = $stmt->fetch();

    if (!$acteur) {
        die("Acteur non trouvé");
    }

    $sexe_label = $acteur['sexe'] === 'M' ? 'Masculin' : 'Féminin';
    $sexe_icon = $acteur['sexe'] === 'M' ? '♂' : '♀';
    $initiales = strtoupper(substr($acteur['prenom'], 0, 1) . substr($acteur['nom'], 0, 1));
    $url_qr = "https://" . $_SERVER['HTTP_HOST'] . "/film_web_dy/acteur_info.php?id=" . $id;

    // Générer le HTML du PDF
    $html_pdf = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Carte Acteur</title>
        <link rel="stylesheet" href="/film_web_dy/style-mobile.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: "Segoe UI", Arial, sans-serif;
                background: #0f172a;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                padding: 20px;
            }
            .carte {
                width: 420px;
                background: linear-gradient(145deg, #0f172a, #1e293b);
                border-radius: 24px;
                padding: 32px;
                border: 1px solid rgba(255,255,255,0.06);
                box-shadow: 0 20px 60px rgba(0,0,0,0.5);
                position: relative;
                overflow: hidden;
            }
            .carte .content { position: relative; z-index: 1; }
            .carte .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 16px;
                border-bottom: 1px solid rgba(255,255,255,0.06);
            }
            .carte .brand { display: flex; align-items: center; gap: 10px; }
            .carte .brand i { font-size: 24px; color: #3b82f6; }
            .carte .brand span { font-size: 16px; font-weight: 700; color: #fff; }
            .carte .brand span.blue { color: #3b82f6; }
            .carte .badge {
                background: rgba(59,130,246,0.15);
                border: 1px solid rgba(59,130,246,0.2);
                padding: 4px 14px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 600;
                color: #60a5fa;
            }
            .carte .avatar {
                display: flex;
                justify-content: center;
                margin: 10px 0 18px;
            }
            .carte .avatar .circle {
                width: 120px;
                height: 120px;
                border-radius: 50%;
                background: linear-gradient(135deg, #1e3a8a, #3b82f6);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 48px;
                font-weight: 700;
                color: #fff;
                box-shadow: 0 8px 32px rgba(59,130,246,0.3);
                border: 3px solid rgba(255,255,255,0.1);
                position: relative;
            }
            .carte .avatar .circle .sexe-badge {
                position: absolute;
                bottom: 4px;
                right: 4px;
                width: 28px;
                height: 28px;
                border-radius: 50%;
                background: #0f172a;
                border: 2px solid rgba(255,255,255,0.1);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 14px;
                color: #60a5fa;
            }
            .carte .info { text-align: center; margin-bottom: 20px; }
            .carte .info .nom { font-size: 24px; font-weight: 700; color: #fff; margin-bottom: 2px; }
            .carte .info .prenom { font-size: 18px; color: #94a3b8; }
            .carte .info .sexe {
                display: inline-block;
                margin-top: 6px;
                padding: 2px 16px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
            }
            .carte .info .sexe.male { background: rgba(59,130,246,0.15); color: #60a5fa; }
            .carte .info .sexe.female { background: rgba(236,64,122,0.15); color: #f06292; }
            .carte .numero-section {
                background: rgba(255,255,255,0.03);
                border-radius: 12px;
                padding: 14px 20px;
                margin-bottom: 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border: 1px solid rgba(255,255,255,0.04);
            }
            .carte .numero-section .label { font-size: 12px; font-weight: 500; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
            .carte .numero-section .numero { font-size: 20px; font-weight: 700; color: #3b82f6; font-family: monospace; letter-spacing: 1px; }
            .carte .qr-section {
                display: flex;
                justify-content: center;
                margin-bottom: 20px;
                padding: 16px;
                background: rgba(255,255,255,0.03);
                border-radius: 12px;
                border: 1px solid rgba(255,255,255,0.04);
            }
            .carte .qr-section img { width: 120px; height: 120px; border-radius: 8px; }
            .carte .footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding-top: 16px;
                border-top: 1px solid rgba(255,255,255,0.06);
            }
            .carte .footer .date { font-size: 11px; color: #64748b; }
            .carte .footer .version { font-size: 11px; color: #64748b; opacity: 0.5; }
        </style>
    </head>
    <body>
        <div class="carte">
            <div class="content">
                <div class="header">
                    <div class="brand">
                        <i class="fas fa-film"></i>
                        <span>BERTIN <span class="blue">MOVIE</span></span>
                    </div>
                    <div class="badge">⭐ ACTEUR</div>
                </div>
                
                <div class="avatar">
                    <div class="circle">
                        ' . $initiales . '
                        <span class="sexe-badge">' . $sexe_icon . '</span>
                    </div>
                </div>
                
                <div class="info">
                    <div class="nom">' . htmlspecialchars($acteur['nom']) . '</div>
                    <div class="prenom">' . htmlspecialchars($acteur['prenom']) . '</div>
                    <span class="sexe ' . ($acteur['sexe'] === 'M' ? 'male' : 'female') . '">' . $sexe_icon . ' ' . $sexe_label . '</span>
                </div>
                
                <div class="numero-section">
                    <span class="label">📋 Numéro d\'acteur</span>
                    <span class="numero">' . htmlspecialchars($acteur['acteur_numero'] ?? 'N/A') . '</span>
                </div>
                
                <div class="qr-section">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($url_qr) . '" alt="QR Code" />
                </div>
                
                <div class="footer">
                    <span class="date">📅 Émis le ' . date('d/m/Y') . '</span>
                    <span class="version">v2.0</span>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';

    // 🔥 Utiliser la fonction d'impression du navigateur pour générer le PDF
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>PDF Carte Acteur</title>
        <style>
            body { margin: 0; padding: 0; background: #0f172a; }
            @media print {
                body { background: #0f172a; }
                .carte { box-shadow: none; border: 1px solid #1e293b; }
                .no-print { display: none !important; }
            }
        </style>
    </head>
    <body>
        ' . $html_pdf . '
        <div class="no-print" style="text-align:center;padding:20px;color:#94a3b8;">
            <button onclick="window.print()" style="padding:12px 32px;background:linear-gradient(135deg,#1e3a8a,#3b82f6);color:#fff;border:none;border-radius:12px;font-size:16px;cursor:pointer;font-weight:600;margin:10px;">
                <i class="fas fa-print"></i> Imprimer / Enregistrer en PDF
            </button>
            <br>
            <span style="font-size:13px;color:#64748b;">Cliquez sur "Imprimer" puis choisissez "Enregistrer au format PDF"</span>
        </div>
        <script>
            // Auto-imprimer après 1 seconde
            setTimeout(function() {
                window.print();
            }, 1000);
        </script>
    </body>
    </html>';
    exit();
}

// ============================================================
// RÉCUPÉRATION DES DONNÉES
// ============================================================
$acteurs = $pdo->query("SELECT id_acteur, nom, prenom, acteur_numero, sexe FROM acteur ORDER BY nom, prenom")->fetchAll();

$acteur_selected = null;
$id_acteur = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id_acteur > 0) {
    $stmt = $pdo->prepare("SELECT * FROM acteur WHERE id_acteur = ?");
    $stmt->execute([$id_acteur]);
    $acteur_selected = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte Acteur Premium</title>
    <link rel="stylesheet" href="/film_web_dy/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-carte {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-carte .header-page {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
        }

        .page-carte .header-page h1 {
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #0f172a;
        }

        body.dark-mode .page-carte .header-page h1 {
            color: #e2e8f0;
        }

        .page-carte .header-page h1 i {
            color: #3b82f6;
        }

        .selector-modern {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px 28px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            margin-bottom: 32px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
        }

        body.dark-mode .selector-modern {
            background: #1e293b;
            border-color: #334155;
        }

        .selector-modern .select-group {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            min-width: 250px;
        }

        .selector-modern .select-group label {
            font-weight: 600;
            font-size: 14px;
            color: #475569;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        body.dark-mode .selector-modern .select-group label {
            color: #94a3b8;
        }

        .selector-modern .select-group select {
            flex: 1;
            padding: 12px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            background: #f8fafc;
            color: #0f172a;
            transition: all 0.3s ease;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23475669' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
            font-family: inherit;
        }

        body.dark-mode .selector-modern .select-group select {
            background: #0f172a;
            border-color: #334155;
            color: #e2e8f0;
        }

        .selector-modern .select-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.08);
        }

        .selector-modern .btn-pdf {
            padding: 12px 32px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
        }

        .selector-modern .btn-pdf:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.3);
        }

        .selector-modern .btn-pdf:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .carte-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 520px;
        }

        .carte-modern {
            width: 420px;
            background: linear-gradient(145deg, #0f172a, #1e293b);
            border-radius: 28px;
            padding: 32px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .carte-modern:hover {
            transform: translateY(-4px);
        }

        .carte-modern::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.08), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            animation: pulseGlow 4s ease-in-out infinite;
        }

        .carte-modern::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -20%;
            width: 280px;
            height: 280px;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.05), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            animation: pulseGlow 5s ease-in-out infinite reverse;
        }

        @keyframes pulseGlow {

            0%,
            100% {
                opacity: 0.6;
                transform: scale(1);
            }

            50% {
                opacity: 1;
                transform: scale(1.05);
            }
        }

        .carte-modern .content-card {
            position: relative;
            z-index: 1;
        }

        .carte-modern .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .carte-modern .brand-card {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .carte-modern .brand-card i {
            font-size: 24px;
            color: #3b82f6;
            filter: drop-shadow(0 0 20px rgba(59, 130, 246, 0.15));
        }

        .carte-modern .brand-card span {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.3px;
        }

        .carte-modern .brand-card span.blue {
            color: #3b82f6;
        }

        .carte-modern .badge-card {
            background: rgba(59, 130, 246, 0.12);
            border: 1px solid rgba(59, 130, 246, 0.15);
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: #60a5fa;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .carte-modern .badge-card i {
            font-size: 10px;
            color: #f59e0b;
        }

        .carte-modern .avatar-card {
            display: flex;
            justify-content: center;
            margin: 12px 0 20px;
        }

        .carte-modern .avatar-card .circle-card {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: #ffffff;
            box-shadow: 0 8px 40px rgba(59, 130, 246, 0.25);
            border: 3px solid rgba(255, 255, 255, 0.06);
            position: relative;
        }

        .carte-modern .avatar-card .circle-card .sexe-badge-card {
            position: absolute;
            bottom: 4px;
            right: 4px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #0f172a;
            border: 2px solid rgba(255, 255, 255, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #60a5fa;
        }

        .carte-modern .info-card {
            text-align: center;
            margin-bottom: 20px;
        }

        .carte-modern .info-card .nom-card {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 2px;
            letter-spacing: -0.3px;
        }

        .carte-modern .info-card .prenom-card {
            font-size: 18px;
            font-weight: 400;
            color: #94a3b8;
        }

        .carte-modern .info-card .sexe-card {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 18px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .carte-modern .info-card .sexe-card.male {
            background: rgba(59, 130, 246, 0.12);
            color: #60a5fa;
        }

        .carte-modern .info-card .sexe-card.female {
            background: rgba(236, 64, 122, 0.12);
            color: #f06292;
        }

        .carte-modern .numero-card {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 14px;
            padding: 14px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.04);
        }

        .carte-modern .numero-card .label-card {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .carte-modern .numero-card .label-card i {
            color: #3b82f6;
        }

        .carte-modern .numero-card .numero-card-value {
            font-size: 20px;
            font-weight: 700;
            color: #3b82f6;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }

        .carte-modern .qr-card {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.04);
            min-height: 140px;
            align-items: center;
        }

        .carte-modern .qr-card img {
            width: 120px;
            height: 120px;
            border-radius: 8px;
        }

        .carte-modern .footer-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.04);
        }

        .carte-modern .footer-card .date-card {
            font-size: 11px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .carte-modern .footer-card .date-card i {
            color: #3b82f6;
        }

        .carte-modern .footer-card .version-card {
            font-size: 11px;
            color: #64748b;
            opacity: 0.4;
        }

        .empty-modern {
            text-align: center;
            padding: 80px 20px;
            color: #64748b;
        }

        .empty-modern .empty-icon {
            font-size: 64px;
            color: #e2e8f0;
            display: block;
            margin-bottom: 20px;
        }

        body.dark-mode .empty-modern .empty-icon {
            color: #334155;
        }

        .empty-modern h3 {
            font-size: 22px;
            color: #0f172a;
            margin-bottom: 8px;
        }

        body.dark-mode .empty-modern h3 {
            color: #e2e8f0;
        }

        @media (max-width: 768px) {
            .page-carte {
                padding: 12px;
            }

            .page-carte .header-page {
                flex-direction: column;
                align-items: flex-start;
            }

            .page-carte .header-page h1 {
                font-size: 22px;
            }

            .selector-modern {
                padding: 16px 18px;
                border-radius: 16px;
                flex-direction: column;
            }

            .selector-modern .select-group {
                min-width: 100%;
                flex-wrap: wrap;
            }

            .selector-modern .select-group label {
                font-size: 13px;
            }

            .selector-modern .btn-pdf {
                width: 100%;
                justify-content: center;
                padding: 14px 20px;
            }

            .carte-modern {
                width: 100%;
                padding: 24px;
                border-radius: 20px;
            }

            .carte-modern .avatar-card .circle-card {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }

            .carte-modern .avatar-card .circle-card .sexe-badge-card {
                width: 26px;
                height: 26px;
                font-size: 12px;
            }

            .carte-modern .info-card .nom-card {
                font-size: 20px;
            }

            .carte-modern .info-card .prenom-card {
                font-size: 16px;
            }

            .carte-modern .numero-card .numero-card-value {
                font-size: 16px;
            }

            .carte-modern .qr-card img {
                width: 100px;
                height: 100px;
            }
        }

        @media (max-width: 480px) {
            .carte-modern {
                padding: 18px;
                border-radius: 16px;
            }

            .carte-modern .avatar-card .circle-card {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }

            .carte-modern .avatar-card .circle-card .sexe-badge-card {
                width: 22px;
                height: 22px;
                font-size: 10px;
                bottom: 2px;
                right: 2px;
            }

            .carte-modern .info-card .nom-card {
                font-size: 18px;
            }

            .carte-modern .info-card .prenom-card {
                font-size: 14px;
            }

            .carte-modern .numero-card {
                flex-direction: column;
                gap: 4px;
                text-align: center;
            }

            .carte-modern .numero-card .numero-card-value {
                font-size: 18px;
            }

            .carte-modern .footer-card {
                flex-direction: column;
                gap: 6px;
                text-align: center;
            }

            .carte-modern .qr-card img {
                width: 90px;
                height: 90px;
            }
        }
    </style>
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="page-carte">

        <div class="header-page">
            <h1>
                <i class="fas fa-id-card"></i>
                Carte Acteur Premium
            </h1>
        </div>

        <!-- Sélecteur -->
        <div class="selector-modern">
            <form method="GET" class="select-group">
                <label for="acteur_id">
                    <i class="fas fa-user" style="color:#3b82f6;"></i>
                    Choisir un acteur
                </label>
                <select name="id" id="acteur_id" onchange="this.form.submit()">
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($acteurs as $a): ?>
                        <option value="<?= $a['id_acteur'] ?>" <?= ($id_acteur == $a['id_acteur']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?>
                            (
                            <?= htmlspecialchars($a['acteur_numero'] ?? 'N/A') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($acteur_selected): ?>
                <a href="carte_acteur.php?export=pdf&id=<?= $id_acteur ?>" class="btn-pdf" target="_blank">
                    <i class="fas fa-file-pdf"></i>
                    Générer PDF
                </a>
            <?php else: ?>
                <button class="btn-pdf" disabled>
                    <i class="fas fa-file-pdf"></i>
                    Générer PDF
                </button>
            <?php endif; ?>
        </div>

        <!-- Carte -->
        <div class="carte-wrapper">
            <?php if (!$acteur_selected): ?>
                <div class="empty-modern">
                    <i class="fas fa-user-plus empty-icon"></i>
                    <h3>Aucun acteur sélectionné</h3>
                    <p>Sélectionnez un acteur pour générer sa carte professionnelle premium.</p>
                </div>
            <?php else:
                $sexe_label = $acteur_selected['sexe'] === 'M' ? 'Masculin' : 'Féminin';
                $sexe_class = $acteur_selected['sexe'] === 'M' ? 'male' : 'female';
                $sexe_icon = $acteur_selected['sexe'] === 'M' ? 'fa-mars' : 'fa-venus';
                $initiales = strtoupper(substr($acteur_selected['prenom'], 0, 1) . substr($acteur_selected['nom'], 0, 1));
                $url_qr = "https://" . $_SERVER['HTTP_HOST'] . "/film_web_dy/acteur_info.php?id=" . $id_acteur;
                ?>
                <div class="carte-modern" id="carteActeur">
                    <div class="content-card">

                        <!-- Header -->
                        <div class="card-header">
                            <div class="brand-card">
                                <i class="fas fa-film"></i>
                                <span>BERTIN <span class="blue">MOVIE</span></span>
                            </div>
                            <div class="badge-card">
                                <i class="fas fa-star"></i>
                                ACTEUR
                            </div>
                        </div>

                        <!-- Avatar -->
                        <div class="avatar-card">
                            <div class="circle-card">
                                <?= $initiales ?>
                                <span class="sexe-badge-card">
                                    <i class="fas <?= $sexe_icon ?>"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Informations -->
                        <div class="info-card">
                            <div class="nom-card">
                                <?= htmlspecialchars($acteur_selected['nom']) ?>
                            </div>
                            <div class="prenom-card">
                                <?= htmlspecialchars($acteur_selected['prenom']) ?>
                            </div>
                            <span class="sexe-card <?= $sexe_class ?>">
                                <i class="fas <?= $sexe_icon ?>"></i>
                                <?= $sexe_label ?>
                            </span>
                        </div>

                        <!-- Numéro -->
                        <div class="numero-card">
                            <span class="label-card">
                                <i class="fas fa-hashtag"></i>
                                Numéro d'acteur
                            </span>
                            <span class="numero-card-value">
                                <?= htmlspecialchars($acteur_selected['acteur_numero'] ?? 'N/A') ?>
                            </span>
                        </div>

                        <!-- QR Code -->
                        <div class="qr-card">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($url_qr) ?>"
                                alt="QR Code" id="qrImage"
                                onerror="this.style.display='none'; document.getElementById('qrFallback').style.display='flex';">
                            <div class="qr-placeholder" id="qrFallback" style="display:none;">
                                <i class="fas fa-qrcode"></i>
                                <span>QR Code</span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="footer-card">
                            <span class="date-card">
                                <i class="fas fa-calendar-alt"></i>
                                Émis le
                                <?= date('d/m/Y') ?>
                            </span>
                            <span class="version-card">v2.0 Premium</span>
                        </div>

                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>

</body>

</html>