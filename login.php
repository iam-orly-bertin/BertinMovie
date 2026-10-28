<?php
session_start();   // session par défaut (avant authentification)
include("connexion.php");
require_once __DIR__ . '/includes/ui_prefs.php'; // langue FR/EN + thème clair/sombre
require_once __DIR__ . "/includes/bm_trace.php";

/* =========================================================
   RÉGLAGES DU VERROUILLAGE AUTOMATIQUE  (modifie ici !)
   ========================================================= */
define('LOCK_MAX_ATTEMPTS', 2);    // nombre d'échecs avant blocage (3, 5, 10...)
define('LOCK_DURATION_MIN', 1);   // durée du blocage en minutes
define('LOCK_RESET_ON_SUCCESS', true); // remet le compteur à 0 après une connexion réussie

/* Crée les colonnes si elles n'existent pas encore (sans erreur si déjà là) */
function ensureLockColumns(PDO $pdo)
{
    try {
        if ($pdo->query("SHOW COLUMNS FROM utilisateur LIKE 'tentatives_echec'")->rowCount() === 0) {
            $pdo->exec("ALTER TABLE utilisateur ADD COLUMN tentatives_echec INT NOT NULL DEFAULT 0");
        }
        if ($pdo->query("SHOW COLUMNS FROM utilisateur LIKE 'verrouille_jusqua'")->rowCount() === 0) {
            $pdo->exec("ALTER TABLE utilisateur ADD COLUMN verrouille_jusqua DATETIME NULL DEFAULT NULL");
        }
    } catch (PDOException $e) {
        error_log("ensureLockColumns : " . $e->getMessage());
    }
}
ensureLockColumns($pdo);

// Redirigé ici par la déconnexion automatique (inactivité)
if (isset($_GET["timeout"])) {
    $erreur = t('err_timeout');
}

if (isset($_POST["login"])) {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $sql = "SELECT * FROM utilisateur WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // --- Compte actuellement verrouillé ? (heure calculée par MySQL pour éviter les décalages PHP/MySQL)
    $minutes_restantes = 0;
    if ($user) {
        $chk = $pdo->prepare("SELECT GREATEST(0, CEIL(TIMESTAMPDIFF(SECOND, NOW(), verrouille_jusqua) / 60)) AS mins
                              FROM utilisateur WHERE id_utilisateur = ? AND verrouille_jusqua IS NOT NULL AND verrouille_jusqua > NOW()");
        $chk->execute([$user["id_utilisateur"]]);
        $row = $chk->fetch();
        $minutes_restantes = $row ? (int) $row["mins"] : 0;
    }

    if ($user && $minutes_restantes > 0) {
        // Même un bon mot de passe est refusé pendant le blocage
        bm_trace("LOGIN-LOCKED", null, $user["id_utilisateur"]);
        $erreur = t('err_locked', $minutes_restantes . ' ' . t($minutes_restantes > 1 ? 'minute_pl' : 'minute_sg'));

    } elseif ($user && password_verify($password, $user["mot_de_passe"])) {
        // --- Succès : on remet le compteur à zéro
        if (LOCK_RESET_ON_SUCCESS) {
            $pdo->prepare("UPDATE utilisateur SET tentatives_echec = 0, verrouille_jusqua = NULL WHERE id_utilisateur = ?")
                ->execute([$user["id_utilisateur"]]);
        }

        $pdo->prepare("INSERT INTO historique_connexion(id_utilisateur) VALUES(?)")->execute([$user["id_utilisateur"]]);

        // Une session SÉPARÉE par rôle (cookie BM_ADMIN ou BM_USER)
        $isAdmin = ($user["role"] === "admin");
        session_write_close();
        define("BM_SESSION_NAME", $isAdmin ? "BM_ADMIN" : "BM_USER");
        require_once __DIR__ . "/includes/session_boot.php";
        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["id_utilisateur"];
        $_SESSION["user_nom"] = $user["nom_utilisateur"];
        $_SESSION["user_role"] = $user["role"];
        $_SESSION["id_utilisateur"] = $user["id_utilisateur"];
        $_SESSION["username"] = $user["nom_utilisateur"];
        $_SESSION["role"] = $user["role"];
        $_SESSION["_bm_uid"] = (int) $user["id_utilisateur"];   // identité verrouillée
        $_SESSION["_bm_role"] = (string) $user["role"];
        bm_trace("LOGIN-OK", null, $user["id_utilisateur"]);

        if ($isAdmin) {
            header("Location: admin/dashboard.php");
            exit();
        } else {
            header("Location: user/index.php");
            exit();
        }

    } else {
        // --- Échec
        $erreur = t('err_bad_login');

        if ($user) {
            // Un ancien blocage est expiré : on repart de zéro (sinon le 1er échec re-bloquerait aussitôt)
            $pdo->prepare("UPDATE utilisateur SET tentatives_echec = 0, verrouille_jusqua = NULL
                           WHERE id_utilisateur = ? AND verrouille_jusqua IS NOT NULL AND verrouille_jusqua <= NOW()")
                ->execute([$user["id_utilisateur"]]);

            // +1 échec ; si le maximum est atteint (ou dépassé après un ancien blocage expiré) -> verrouillage
            $pdo->prepare("UPDATE utilisateur
                           SET tentatives_echec = tentatives_echec + 1,
                               verrouille_jusqua = IF(tentatives_echec + 1 >= :max,
                                                      DATE_ADD(NOW(), INTERVAL :min MINUTE),
                                                      NULL)
                           WHERE id_utilisateur = :id")
                ->execute([":max" => LOCK_MAX_ATTEMPTS, ":min" => LOCK_DURATION_MIN, ":id" => $user["id_utilisateur"]]);

            $s = $pdo->prepare("SELECT tentatives_echec FROM utilisateur WHERE id_utilisateur = ?");
            $s->execute([$user["id_utilisateur"]]);
            $n = (int) $s->fetchColumn();
            bm_trace("LOGIN-FAIL", null, $user["id_utilisateur"]);

            if ($n >= LOCK_MAX_ATTEMPTS) {
                $erreur = t('err_locked_now', LOCK_DURATION_MIN);
            } else {
                $reste = LOCK_MAX_ATTEMPTS - $n;
                $erreur = t('err_bad_login_left', $reste . ' ' . t($reste > 1 ? 'attempt_pl' : 'attempt_sg'));
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= bm_lang() ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>
        <?= t('login_title') ?>
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <style>
        /* =========================================================
           RESET & BASE
        ========================================================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background: #f0f4ff;
            position: relative;
        }

        /* Background décoratif */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(ellipse at 20% 30%, rgba(59, 130, 246, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 70%, rgba(139, 92, 246, 0.04) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(96, 165, 250, 0.03) 0%, transparent 60%);
            z-index: 0;
            animation: bgFloat 30s ease-in-out infinite;
        }

        @keyframes bgFloat {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            33% {
                transform: translate(2%, -2%) scale(1.02);
            }

            66% {
                transform: translate(-2%, 2%) scale(0.98);
            }
        }

        /* =========================================================
           CONTAINER PRINCIPAL
        ========================================================= */
        .login-wrapper {
            display: flex;
            max-width: 1000px;
            width: 100%;
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 10px 40px rgba(37, 99, 235, 0.08), 0 2px 10px rgba(0, 0, 0, 0.02);
            overflow: hidden;
            border: 1px solid rgba(59, 130, 246, 0.05);
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }

        .login-wrapper:hover {
            box-shadow: 0 15px 60px rgba(37, 99, 235, 0.10), 0 2px 10px rgba(0, 0, 0, 0.02);
        }

        /* =========================================================
           LEFT - BRANDING
        ========================================================= */
        .brand-left {
            flex: 0 0 400px;
            background: linear-gradient(160deg, #1a3a7a 0%, #2563eb 50%, #3b82f6 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 44px;
            position: relative;
            overflow: hidden;
            min-height: 520px;
        }

        .brand-left::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -40%;
            width: 80%;
            height: 80%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.08), transparent 70%);
            animation: glowPulse 8s ease-in-out infinite;
        }

        .brand-left::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -30%;
            width: 60%;
            height: 60%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.05), transparent 70%);
            animation: glowPulse 10s ease-in-out infinite reverse;
        }

        @keyframes glowPulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.5;
            }

            50% {
                transform: scale(1.15);
                opacity: 1;
            }
        }

        .brand-left .brand-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .brand-left .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 24px;
            box-shadow: 0 0 40px rgba(255, 255, 255, 0.05);
            transition: all 0.4s ease;
        }

        .brand-left .icon-wrapper:hover {
            transform: scale(1.05);
            box-shadow: 0 0 60px rgba(255, 255, 255, 0.08);
        }

        .brand-left .icon-wrapper i {
            font-size: 44px;
            color: #ffffff;
            filter: drop-shadow(0 0 20px rgba(255, 255, 255, 0.15));
        }

        .brand-left h2 {
            color: #ffffff;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        .brand-left h2 span {
            color: rgba(255, 255, 255, 0.7);
        }

        .brand-left .subtitle {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            font-weight: 400;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .brand-left .divider {
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0.1));
            border-radius: 2px;
            margin: 20px auto 24px;
        }

        .brand-left .features {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
            max-width: 280px;
        }

        .brand-left .features .item {
            display: flex;
            align-items: center;
            gap: 14px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 13px;
            font-weight: 500;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.3s ease;
        }

        .brand-left .features .item:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.12);
            transform: translateX(4px);
        }

        .brand-left .features .item i {
            color: rgba(255, 255, 255, 0.7);
            font-size: 15px;
            width: 22px;
            text-align: center;
        }

        /* =========================================================
           RIGHT - FORM
        ========================================================= */
        .form-right {
            flex: 1;
            padding: 52px 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }

        .form-right .logo-group {
            margin-bottom: 28px;
        }

        .form-right .logo-group .row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 4px;
        }

        .form-right .logo-group .row i {
            font-size: 28px;
            color: #3b82f6;
        }

        .form-right .logo-group .row h1 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .form-right .logo-group .row h1 span {
            color: #3b82f6;
        }

        .form-right .logo-group .desc {
            color: #64748b;
            font-size: 14px;
            margin-left: 40px;
            font-weight: 400;
        }

        /* =========================================================
           FORM ELEMENTS
        ========================================================= */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 13px;
            color: #334155;
            letter-spacing: 0.3px;
        }

        .form-group label i {
            margin-right: 8px;
            color: #3b82f6;
            width: 16px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap input {
            width: 100%;
            padding: 14px 18px;
            padding-right: 48px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 15px;
            background: #f8fafc;
            color: #0f172a;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .input-wrap input:focus {
            outline: none;
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.06);
        }

        .input-wrap input::placeholder {
            color: #94a3b8;
        }

        .input-wrap .toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 18px;
            padding: 6px;
            transition: all 0.3s ease;
            border-radius: 8px;
        }

        .input-wrap .toggle:hover {
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.05);
        }

        /* =========================================================
           BUTTONS & LINKS
        ========================================================= */
        .btn-primary {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 6px;
            font-family: inherit;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.30);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary i {
            font-size: 18px;
        }

        .links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .links a {
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 8px;
        }

        .links a:hover {
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.04);
        }

        .links a i {
            font-size: 14px;
        }

        .links a.register-link {
            color: #3b82f6;
        }

        .links a.register-link:hover {
            color: #2563eb;
            background: rgba(59, 130, 246, 0.08);
        }

        .footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #eef2f6;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 400;
            letter-spacing: 0.5px;
        }

        .footer i {
            color: #3b82f6;
            margin-right: 6px;
        }

        /* =========================================================
           ERROR MESSAGE
        ========================================================= */
        .error-msg {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            font-weight: 500;
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-msg i {
            font-size: 18px;
            color: #ef4444;
        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */
        @media (max-width: 1024px) {
            .brand-left {
                flex: 0 0 340px;
                padding: 40px 32px;
                min-height: 460px;
            }
        }

        @media (max-width: 900px) {
            .login-wrapper {
                flex-direction: column;
                max-width: 460px;
                border-radius: 24px;
            }

            .brand-left {
                flex: unset;
                min-height: 200px;
                padding: 28px 24px;
                border-radius: 24px 24px 0 0;
            }

            .brand-left .icon-wrapper {
                width: 72px;
                height: 72px;
                margin-bottom: 16px;
            }

            .brand-left .icon-wrapper i {
                font-size: 32px;
            }

            .brand-left h2 {
                font-size: 22px;
            }

            .brand-left .subtitle {
                font-size: 12px;
            }

            .brand-left .features {
                display: none;
            }

            .brand-left .divider {
                width: 40px;
                margin: 12px auto 16px;
            }

            .form-right {
                padding: 32px 28px;
            }

            .form-right .logo-group .row h1 {
                font-size: 24px;
            }

            .form-right .logo-group .row i {
                font-size: 22px;
            }

            .form-right .logo-group .desc {
                font-size: 13px;
                margin-left: 34px;
            }

            .input-wrap input {
                padding: 12px 16px;
                padding-right: 44px;
                font-size: 14px;
            }

            .btn-primary {
                padding: 14px;
                font-size: 15px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 12px;
            }

            .login-wrapper {
                border-radius: 20px;
            }

            .brand-left {
                min-height: 160px;
                padding: 20px 16px;
                border-radius: 20px 20px 0 0;
            }

            .brand-left .icon-wrapper {
                width: 60px;
                height: 60px;
                margin-bottom: 12px;
            }

            .brand-left .icon-wrapper i {
                font-size: 26px;
            }

            .brand-left h2 {
                font-size: 18px;
            }

            .brand-left .subtitle {
                font-size: 11px;
            }

            .brand-left .divider {
                width: 30px;
                margin: 10px auto 12px;
            }

            .form-right {
                padding: 24px 18px;
            }

            .form-right .logo-group {
                margin-bottom: 20px;
            }

            .form-right .logo-group .row h1 {
                font-size: 20px;
            }

            .form-right .logo-group .row i {
                font-size: 18px;
            }

            .form-right .logo-group .desc {
                font-size: 12px;
                margin-left: 30px;
            }

            .form-group {
                margin-bottom: 14px;
            }

            .form-group label {
                font-size: 11px;
            }

            .input-wrap input {
                padding: 10px 14px;
                padding-right: 40px;
                font-size: 13px;
                border-radius: 12px;
            }

            .input-wrap .toggle {
                font-size: 15px;
                right: 12px;
            }

            .btn-primary {
                padding: 12px;
                font-size: 14px;
                border-radius: 12px;
            }

            .btn-primary i {
                font-size: 16px;
            }

            .links {
                flex-direction: column;
                align-items: center;
                gap: 6px;
                margin-top: 16px;
            }

            .links a {
                font-size: 12px;
            }

            .footer {
                font-size: 11px;
                margin-top: 20px;
                padding-top: 16px;
            }

            .error-msg {
                font-size: 12px;
                padding: 10px 14px;
                border-radius: 10px;
            }

            .error-msg i {
                font-size: 16px;
            }
        }

        @media (max-width: 380px) {
            .brand-left {
                min-height: 130px;
                padding: 16px 12px;
            }

            .brand-left .icon-wrapper {
                width: 48px;
                height: 48px;
                margin-bottom: 8px;
            }

            .brand-left .icon-wrapper i {
                font-size: 20px;
            }

            .brand-left h2 {
                font-size: 16px;
            }

            .form-right {
                padding: 16px 12px;
            }

            .form-right .logo-group .row h1 {
                font-size: 18px;
            }

            .form-right .logo-group .row i {
                font-size: 16px;
            }

            .form-right .logo-group .desc {
                font-size: 11px;
                margin-left: 26px;
            }

            .input-wrap input {
                padding: 8px 12px;
                padding-right: 36px;
                font-size: 12px;
                border-radius: 10px;
            }

            .btn-primary {
                padding: 10px;
                font-size: 13px;
                border-radius: 10px;
            }

            .links a {
                font-size: 11px;
            }
        }
    </style>
    <?php bm_ui_head(); ?>
</head>

<body>
    <?php bm_ui_toggles(); ?>

    <div class="login-wrapper">

        <!-- LEFT - BRANDING -->
        <div class="brand-left">
            <div class="brand-content">
                <div class="icon-wrapper">
                    <i class="fas fa-film"></i>
                </div>
                <h2>BERTIN <span>MOVIE</span></h2>
                <p class="subtitle">
                    <?= t('login_subtitle') ?>
                </p>
                <div class="divider"></div>
                <div class="features">
                    <div class="item"><i class="fas fa-play-circle"></i><span>
                            <?= t('login_feat1') ?>
                        </span></div>
                    <div class="item"><i class="fas fa-tv"></i><span>
                            <?= t('login_feat2') ?>
                        </span></div>
                    <div class="item"><i class="fas fa-heart"></i><span>
                            <?= t('login_feat3') ?>
                        </span></div>
                </div>
            </div>
        </div>

        <!-- RIGHT - FORM -->
        <div class="form-right">

            <div class="logo-group">
                <div class="row">
                    <i class="fas fa-sign-in-alt"></i>
                    <h1>
                        <?= t('login_h1') ?>
                    </h1>
                </div>
                <p class="desc">
                    <?= t('login_desc') ?>
                </p>
            </div>

            <?php if (isset($erreur)): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($erreur) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i>
                        <?= t('label_email') ?>
                    </label>
                    <div class="input-wrap">
                        <input type="email" id="email" name="email" placeholder="<?= t('login_ph_email') ?>" required
                            autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i>
                        <?= t('label_password') ?>
                    </label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password" placeholder="<?= t('login_ph_password') ?>"
                            required>
                        <button type="button" class="toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="login" class="btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    <?= t('login_btn') ?>
                </button>
            </form>

            <div class="links">
                <a href="creation_compte.php" class="register-link"><i class="fas fa-user-plus"></i>
                    <?= t('login_register') ?>
                </a>
                <a href="mot_de_passe_oublie.php"><i class="fas fa-key"></i>
                    <?= t('login_forgot') ?>
                </a>
            </div>

            <div class="footer">
                <i class="fas fa-film"></i> BERTIN MOVIE &copy; 2026
            </div>

        </div>

    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
    </script>

</body>

</html>