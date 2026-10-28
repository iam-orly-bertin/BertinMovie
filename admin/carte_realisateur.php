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

    $stmt = $pdo->prepare("SELECT * FROM realisateur WHERE id_realisateur = ?");
    $stmt->execute([$id]);
    $realisateur = $stmt->fetch();

    if (!$realisateur) {
        die("Réalisateur non trouvé");
    }

    $nationalite_label = $realisateur['nationalite'] ?? 'Non spécifiée';
    $initiales = strtoupper(substr($realisateur['prenom'], 0, 1) . substr($realisateur['nom'], 0, 1));
    $url_qr = "https://" . $_SERVER['HTTP_HOST'] . "/film_web_dy/realisateur_info.php?id=" . $id;

    // Générer le HTML du PDF
    $html_pdf = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Carte Réalisateur</title>
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
            .carte .brand i { font-size: 24px; color: #8b5cf6; }
            .carte .brand span { font-size: 16px; font-weight: 700; color: #fff; }
            .carte .brand span.purple { color: #8b5cf6; }
            .carte .badge {
                background: rgba(139,92,246,0.15);
                border: 1px solid rgba(139,92,246,0.2);
                padding: 4px 14px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 600;
                color: #a78bfa;
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
                background: linear-gradient(135deg, #4c1d95, #8b5cf6);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 48px;
                font-weight: 700;
                color: #fff;
                box-shadow: 0 8px 32px rgba(139,92,246,0.3);
                border: 3px solid rgba(255,255,255,0.1);
                position: relative;
            }
            .carte .avatar .circle .nationalite-badge {
                position: absolute;
                bottom: 4px;
                right: 4px;
                padding: 2px 10px;
                border-radius: 12px;
                background: #0f172a;
                border: 2px solid rgba(255,255,255,0.1);
                font-size: 10px;
                color: #a78bfa;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            .carte .info { text-align: center; margin-bottom: 20px; }
            .carte .info .nom { font-size: 24px; font-weight: 700; color: #fff; margin-bottom: 2px; }
            .carte .info .prenom { font-size: 18px; color: #94a3b8; }
            .carte .info .nationalite {
                display: inline-block;
                margin-top: 6px;
                padding: 2px 16px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                background: rgba(139,92,246,0.15);
                color: #a78bfa;
            }
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
            .carte .numero-section .numero { font-size: 20px; font-weight: 700; color: #8b5cf6; font-family: monospace; letter-spacing: 1px; }
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
                        <i class="fas fa-video"></i>
                        <span>BERTIN <span class="purple">MOVIE</span></span>
                    </div>
                    <div class="badge">🎬 RÉALISATEUR</div>
                </div>
                
                <div class="avatar">
                    <div class="circle">
                        ' . $initiales . '
                        <span class="nationalite-badge">' . htmlspecialchars(substr($realisateur['nationalite'] ?? '', 0, 3)) . '</span>
                    </div>
                </div>
                
                <div class="info">
                    <div class="nom">' . htmlspecialchars($realisateur['nom']) . '</div>
                    <div class="prenom">' . htmlspecialchars($realisateur['prenom']) . '</div>
                    <span class="nationalite">🌍 ' . htmlspecialchars($nationalite_label) . '</span>
                </div>
                
                <div class="numero-section">
                    <span class="label">🎯 Numéro Réalisateur</span>
                    <span class="numero">' . htmlspecialchars($realisateur['realisateur_numero'] ?? 'N/A') . '</span>
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
        <title>PDF Carte Réalisateur</title>
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
            <button onclick="window.print()" style="padding:12px 32px;background:linear-gradient(135deg,#4c1d95,#8b5cf6);color:#fff;border:none;border-radius:12px;font-size:16px;cursor:pointer;font-weight:600;margin:10px;">
                <i class="fas fa-print"></i> Imprimer / Enregistrer en PDF
            </button>
            <br>
            <span style="font-size:13px;color:#64748b;">Cliquez sur "Imprimer" puis choisissez "Enregistrer au format PDF"</span>
        </div>
        <script>
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
$realisateurs = $pdo->query("SELECT id_realisateur, nom, prenom, realisateur_numero, nationalite FROM realisateur ORDER BY nom, prenom")->fetchAll();

$realisateur_selected = null;
$id_realisateur = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id_realisateur > 0) {
    $stmt = $pdo->prepare("SELECT * FROM realisateur WHERE id_realisateur = ?");
    $stmt->execute([$id_realisateur]);
    $realisateur_selected = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Carte Réalisateur Premium</title>
    <link rel="stylesheet" href="/film_web_dy/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== BASE RESET & TYPOGRAPHY ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
        }

        body.dark-mode {
            background: #0f172a;
        }

        /* ===== PAGE LAYOUT ===== */
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
            color: #8b5cf6;
        }

        /* ===== SELECTOR MODERN ===== */
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
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.08);
        }

        .selector-modern .btn-pdf {
            padding: 12px 32px;
            background: linear-gradient(135deg, #4c1d95, #8b5cf6);
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
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.2);
        }

        .selector-modern .btn-pdf:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.3);
        }

        .selector-modern .btn-pdf:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        /* ===== CARD WRAPPER ===== */
        .carte-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 520px;
        }

        /* ===== MODERN CARD ===== */
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
            background: radial-gradient(circle, rgba(139, 92, 246, 0.08), transparent 70%);
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

        /* ===== CARD HEADER ===== */
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
            color: #8b5cf6;
            filter: drop-shadow(0 0 20px rgba(139, 92, 246, 0.15));
        }

        .carte-modern .brand-card span {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.3px;
        }

        .carte-modern .brand-card span.purple {
            color: #8b5cf6;
        }

        .carte-modern .badge-card {
            background: rgba(139, 92, 246, 0.12);
            border: 1px solid rgba(139, 92, 246, 0.15);
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: #a78bfa;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .carte-modern .badge-card i {
            font-size: 10px;
            color: #f59e0b;
        }

        /* ===== AVATAR ===== */
        .carte-modern .avatar-card {
            display: flex;
            justify-content: center;
            margin: 12px 0 20px;
        }

        .carte-modern .avatar-card .circle-card {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4c1d95, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: #ffffff;
            box-shadow: 0 8px 40px rgba(139, 92, 246, 0.25);
            border: 3px solid rgba(255, 255, 255, 0.06);
            position: relative;
        }

        .carte-modern .avatar-card .circle-card .nationalite-badge-card {
            position: absolute;
            bottom: 4px;
            right: 4px;
            padding: 2px 10px;
            border-radius: 12px;
            background: #0f172a;
            border: 2px solid rgba(255, 255, 255, 0.06);
            font-size: 9px;
            color: #a78bfa;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        /* ===== INFO ===== */
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

        .carte-modern .info-card .nationalite-card {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 18px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(139, 92, 246, 0.12);
            color: #a78bfa;
        }

        /* ===== NUMERO ===== */
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
            color: #8b5cf6;
        }

        .carte-modern .numero-card .numero-card-value {
            font-size: 20px;
            font-weight: 700;
            color: #8b5cf6;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }

        /* ===== QR CODE ===== */
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

        .carte-modern .qr-card .qr-placeholder {
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            color: #64748b;
        }

        .carte-modern .qr-card .qr-placeholder i {
            font-size: 40px;
            color: #8b5cf6;
            opacity: 0.5;
        }

        .carte-modern .qr-card .qr-placeholder span {
            font-size: 12px;
            font-weight: 500;
        }

        /* ===== FOOTER ===== */
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
            color: #8b5cf6;
        }

        .carte-modern .footer-card .version-card {
            font-size: 11px;
            color: #64748b;
            opacity: 0.4;
        }

        /* ===== EMPTY STATE ===== */
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

        /* ============================================================
                   📱 RESPONSIVE DESIGN - COMPLETE MOBILE SUPPORT
                   ============================================================ */

        /* Tablets & Small Laptops */
        @media (max-width: 1024px) {
            .page-carte {
                padding: 16px;
            }

            .carte-modern {
                width: 380px;
                padding: 28px;
            }
        }

        /* Mobile Landscape & Small Tablets */
        @media (max-width: 768px) {
            .page-carte {
                padding: 12px;
            }

            .page-carte .header-page {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                margin-bottom: 20px;
            }

            .page-carte .header-page h1 {
                font-size: 22px;
            }

            .page-carte .header-page h1 i {
                font-size: 20px;
            }

            /* Selector - Mobile */
            .selector-modern {
                padding: 16px 18px;
                border-radius: 16px;
                flex-direction: column;
                gap: 12px;
            }

            .selector-modern .select-group {
                min-width: 100%;
                flex-wrap: wrap;
                gap: 10px;
            }

            .selector-modern .select-group label {
                font-size: 13px;
                width: 100%;
            }

            .selector-modern .select-group select {
                width: 100%;
                padding: 10px 14px;
                font-size: 14px;
                background-size: 10px;
                background-position: right 12px center;
                padding-right: 36px;
            }

            .selector-modern .btn-pdf {
                width: 100%;
                justify-content: center;
                padding: 14px 20px;
                font-size: 14px;
            }

            /* Card - Mobile */
            .carte-modern {
                width: 100%;
                max-width: 420px;
                padding: 24px;
                border-radius: 20px;
            }

            .carte-modern .avatar-card .circle-card {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }

            .carte-modern .avatar-card .circle-card .nationalite-badge-card {
                font-size: 8px;
                padding: 1px 8px;
                bottom: 2px;
                right: 2px;
            }

            .carte-modern .info-card .nom-card {
                font-size: 20px;
            }

            .carte-modern .info-card .prenom-card {
                font-size: 16px;
            }

            .carte-modern .info-card .nationalite-card {
                font-size: 11px;
                padding: 2px 14px;
            }

            .carte-modern .numero-card {
                padding: 12px 16px;
                flex-direction: row;
                gap: 8px;
            }

            .carte-modern .numero-card .label-card {
                font-size: 11px;
            }

            .carte-modern .numero-card .numero-card-value {
                font-size: 17px;
            }

            .carte-modern .qr-card {
                padding: 12px;
                min-height: 120px;
            }

            .carte-modern .qr-card img {
                width: 100px;
                height: 100px;
            }

            .carte-modern .footer-card {
                flex-direction: row;
                gap: 8px;
                flex-wrap: wrap;
            }

            .carte-modern .footer-card .date-card {
                font-size: 10px;
            }

            .carte-modern .footer-card .version-card {
                font-size: 10px;
            }

            /* Empty state */
            .empty-modern {
                padding: 50px 16px;
            }

            .empty-modern .empty-icon {
                font-size: 48px;
            }

            .empty-modern h3 {
                font-size: 18px;
            }
        }

        /* Small Mobile Phones */
        @media (max-width: 480px) {
            .page-carte {
                padding: 8px;
            }

            .page-carte .header-page h1 {
                font-size: 18px;
                gap: 8px;
            }

            .page-carte .header-page h1 i {
                font-size: 16px;
            }

            .selector-modern {
                padding: 12px 14px;
                border-radius: 12px;
                margin-bottom: 16px;
            }

            .selector-modern .select-group select {
                padding: 8px 12px;
                font-size: 13px;
                padding-right: 32px;
                border-radius: 10px;
            }

            .selector-modern .btn-pdf {
                padding: 12px 16px;
                font-size: 13px;
                border-radius: 10px;
            }

            .carte-modern {
                padding: 16px 14px;
                border-radius: 16px;
            }

            .carte-modern .card-header {
                margin-bottom: 14px;
                padding-bottom: 12px;
            }

            .carte-modern .brand-card i {
                font-size: 18px;
            }

            .carte-modern .brand-card span {
                font-size: 13px;
            }

            .carte-modern .badge-card {
                font-size: 9px;
                padding: 3px 10px;
            }

            .carte-modern .badge-card i {
                font-size: 8px;
            }

            .carte-modern .avatar-card {
                margin: 8px 0 14px;
            }

            .carte-modern .avatar-card .circle-card {
                width: 80px;
                height: 80px;
                font-size: 32px;
                border-width: 2px;
            }

            .carte-modern .avatar-card .circle-card .nationalite-badge-card {
                font-size: 7px;
                padding: 1px 6px;
                bottom: 1px;
                right: 1px;
                border-width: 1px;
            }

            .carte-modern .info-card {
                margin-bottom: 14px;
            }

            .carte-modern .info-card .nom-card {
                font-size: 18px;
            }

            .carte-modern .info-card .prenom-card {
                font-size: 14px;
            }

            .carte-modern .info-card .nationalite-card {
                font-size: 10px;
                padding: 2px 12px;
                margin-top: 4px;
            }

            .carte-modern .numero-card {
                padding: 10px 14px;
                border-radius: 10px;
                margin-bottom: 14px;
                flex-direction: column;
                gap: 4px;
                text-align: center;
            }

            .carte-modern .numero-card .label-card {
                font-size: 10px;
            }

            .carte-modern .numero-card .numero-card-value {
                font-size: 16px;
            }

            .carte-modern .qr-card {
                padding: 10px;
                min-height: 100px;
                border-radius: 10px;
                margin-bottom: 14px;
            }

            .carte-modern .qr-card img {
                width: 80px;
                height: 80px;
            }

            .carte-modern .qr-card .qr-placeholder i {
                font-size: 32px;
            }

            .carte-modern .qr-card .qr-placeholder span {
                font-size: 10px;
            }

            .carte-modern .footer-card {
                padding-top: 12px;
                flex-direction: column;
                gap: 4px;
                text-align: center;
            }

            .carte-modern .footer-card .date-card {
                font-size: 9px;
            }

            .carte-modern .footer-card .version-card {
                font-size: 9px;
            }

            /* Empty state - mobile */
            .empty-modern {
                padding: 40px 12px;
            }

            .empty-modern .empty-icon {
                font-size: 36px;
                margin-bottom: 12px;
            }

            .empty-modern h3 {
                font-size: 16px;
            }

            .empty-modern p {
                font-size: 13px;
            }
        }

        /* Extra Small Phones */
        @media (max-width: 360px) {
            .carte-modern {
                padding: 12px 10px;
                border-radius: 12px;
            }

            .carte-modern .avatar-card .circle-card {
                width: 70px;
                height: 70px;
                font-size: 26px;
            }

            .carte-modern .info-card .nom-card {
                font-size: 16px;
            }

            .carte-modern .info-card .prenom-card {
                font-size: 12px;
            }

            .carte-modern .numero-card .numero-card-value {
                font-size: 14px;
            }

            .carte-modern .qr-card img {
                width: 70px;
                height: 70px;
            }

            .selector-modern .btn-pdf {
                font-size: 12px;
                padding: 10px 14px;
            }
        }

        /* ===== PRINT STYLES ===== */
        @media print {
            body {
                background: #0f172a !important;
            }

            .no-print {
                display: none !important;
            }

            .carte-modern {
                box-shadow: none !important;
                border: 1px solid #1e293b !important;
                transform: none !important;
                page-break-inside: avoid;
            }

            .carte-modern::before,
            .carte-modern::after {
                display: none !important;
            }

            .carte-modern .qr-card img {
                break-inside: avoid;
            }

            .selector-modern {
                display: none !important;
            }

            .page-carte .header-page {
                display: none !important;
            }

            .carte-wrapper {
                min-height: auto !important;
                padding: 0 !important;
                margin: 0 auto !important;
            }
        }
    </style>
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="page-carte">

        <div class="header-page">
            <h1>
                <i class="fas fa-user-tie"></i>
                Carte Réalisateur Premium
            </h1>
        </div>

        <!-- Sélecteur -->
        <div class="selector-modern">
            <form method="GET" class="select-group">
                <label for="realisateur_id">
                    <i class="fas fa-user" style="color:#8b5cf6;"></i>
                    Choisir un réalisateur
                </label>
                <select name="id" id="realisateur_id" onchange="this.form.submit()">
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($realisateurs as $r): ?>
                        <option value="<?= $r['id_realisateur'] ?>" <?= ($id_realisateur == $r['id_realisateur']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['prenom'] . ' ' . $r['nom']) ?>
                            (
                            <?= htmlspecialchars($r['realisateur_numero'] ?? 'N/A') ?>)
                            <?= !empty($r['nationalite']) ? ' - ' . htmlspecialchars($r['nationalite']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($realisateur_selected): ?>
                <a href="carte_realisateur.php?export=pdf&id=<?= $id_realisateur ?>" class="btn-pdf" target="_blank">
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
            <?php if (!$realisateur_selected): ?>
                <div class="empty-modern">
                    <i class="fas fa-user-plus empty-icon"></i>
                    <h3>Aucun réalisateur sélectionné</h3>
                    <p>Sélectionnez un réalisateur pour générer sa carte professionnelle premium.</p>
                </div>
            <?php else:
                $nationalite_label = $realisateur_selected['nationalite'] ?? 'Non spécifiée';
                $initiales = strtoupper(substr($realisateur_selected['prenom'], 0, 1) . substr($realisateur_selected['nom'], 0, 1));
                $url_qr = "https://" . $_SERVER['HTTP_HOST'] . "/film_web_dy/realisateur_info.php?id=" . $id_realisateur;
                $nationalite_short = substr($realisateur_selected['nationalite'] ?? '', 0, 3);
                ?>
                <div class="carte-modern" id="carteRealisateur">
                    <div class="content-card">

                        <!-- Header -->
                        <div class="card-header">
                            <div class="brand-card">
                                <i class="fas fa-video"></i>
                                <span>BERTIN <span class="purple">MOVIE</span></span>
                            </div>
                            <div class="badge-card">
                                <i class="fas fa-star"></i>
                                RÉALISATEUR
                            </div>
                        </div>

                        <!-- Avatar -->
                        <div class="avatar-card">
                            <div class="circle-card">
                                <?= htmlspecialchars($initiales) ?>
                                <span class="nationalite-badge-card">
                                    <?= htmlspecialchars($nationalite_short) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Informations -->
                        <div class="info-card">
                            <div class="nom-card">
                                <?= htmlspecialchars($realisateur_selected['nom']) ?>
                            </div>
                            <div class="prenom-card">
                                <?= htmlspecialchars($realisateur_selected['prenom']) ?>
                            </div>
                            <span class="nationalite-card">
                                🌍
                                <?= htmlspecialchars($nationalite_label) ?>
                            </span>
                        </div>

                        <!-- Numéro -->
                        <div class="numero-card">
                            <span class="label-card">
                                <i class="fas fa-hashtag"></i>
                                Numéro Réalisateur
                            </span>
                            <span class="numero-card-value">
                                <?= htmlspecialchars($realisateur_selected['realisateur_numero'] ?? 'N/A') ?>
                            </span>
                        </div>

                        <!-- QR Code -->
                        <div class="qr-card">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($url_qr) ?>"
                                alt="QR Code" id="qrImage"
                                onerror="this.style.display='none'; document.getElementById('qrFallback').style.display='flex';">
                            <div class="qr-placeholder" id="qrFallback">
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