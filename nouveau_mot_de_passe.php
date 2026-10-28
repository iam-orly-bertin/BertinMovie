<?php
session_start();
include("connexion.php");
require_once __DIR__ . '/includes/ui_prefs.php'; // langue FR/EN + thème clair/sombre

// ✅ VÉRIFIER QUE L'EMAIL EST DANS LA SESSION
// ✅ ET QUE LE CODE OTP A ÉTÉ VÉRIFIÉ (empêche d'ouvrir cette page directement)
if (
    !isset($_SESSION['reset_email'])
    || empty($_SESSION['reset_verified'])
    || (time() - ($_SESSION['reset_verified_time'] ?? 0)) > 600
) {
    unset($_SESSION['reset_verified'], $_SESSION['reset_verified_time']);
    header("Location: mot_de_passe_oublie.php");
    exit();
}

if (isset($_POST['save'])) {
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];

    if (empty($pass1) || empty($pass2)) {
        $erreur = t('err_all_required');
    } elseif ($pass1 != $pass2) {
        $erreur = t('err_pw_diff');
    } elseif (strlen($pass1) < 6) {
        $erreur = t('err_pw_short');
    } else {
        $hash = password_hash($pass1, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE email = ?")->execute([$hash, $_SESSION['reset_email']]);

        // Débloquer le compte (verrouillage automatique) et fermer les anciennes sessions
        try {
            $pdo->prepare("UPDATE utilisateur SET tentatives_echec = 0, verrouille_jusqua = NULL WHERE email = ?")->execute([$_SESSION['reset_email']]);
        } catch (PDOException $e) {
            // colonnes absentes : on ignore
        }
        try {
            $pdo->prepare("DELETE FROM sessions_actives WHERE id_utilisateur = (SELECT id_utilisateur FROM utilisateur WHERE email = ?)")->execute([$_SESSION['reset_email']]);
        } catch (PDOException $e) {
            // table absente : on ignore
        }

        unset(
            $_SESSION['reset_email'],
            $_SESSION['reset_otp'],
            $_SESSION['reset_otp_time'],
            $_SESSION['reset_tentatives'],
            $_SESSION['reset_verified'],
            $_SESSION['reset_verified_time']
        );

        header("Location: login.php?success=password_updated");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="<?= bm_lang() ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>
        <?= t('newpw_title') ?>
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
           CONTAINER
        ========================================================= */
        .container {
            max-width: 460px;
            width: 100%;
            background: #ffffff;
            padding: 44px 40px;
            border-radius: 24px;
            border: 1px solid rgba(59, 130, 246, 0.05);
            box-shadow: 0 10px 40px rgba(37, 99, 235, 0.06), 0 2px 10px rgba(0, 0, 0, 0.02);
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }

        .container:hover {
            box-shadow: 0 15px 60px rgba(37, 99, 235, 0.08), 0 2px 10px rgba(0, 0, 0, 0.02);
        }

        /* =========================================================
           LOGO
        ========================================================= */
        .logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.08);
            margin-bottom: 12px;
        }

        .logo .icon-wrapper i {
            font-size: 28px;
            color: #3b82f6;
        }

        .logo h2 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .logo h2 span {
            color: #3b82f6;
        }

        .logo p {
            color: #64748b;
            font-size: 14px;
            margin-top: 4px;
            font-weight: 400;
        }

        /* =========================================================
           FORM
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

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            background: #f8fafc;
            color: #0f172a;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.06);
        }

        .form-group input::placeholder {
            color: #94a3b8;
        }

        /* =========================================================
           BUTTONS
        ========================================================= */
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #ffffff;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 4px;
        }

        .btn-success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            box-shadow: 0 4px 16px rgba(34, 197, 94, 0.2);
        }

        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(34, 197, 94, 0.30);
            background: linear-gradient(135deg, #16a34a, #15803d);
        }

        .btn-success:active {
            transform: translateY(0);
        }

        /* =========================================================
           MESSAGES
        ========================================================= */
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 18px;
            border: 1px solid #fecaca;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease;
        }

        .error i {
            font-size: 18px;
            color: #ef4444;
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

        /* =========================================================
           LINKS
        ========================================================= */
        .links {
            text-align: center;
            margin-top: 20px;
        }

        .links a {
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 8px;
        }

        .links a:hover {
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.04);
        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */
        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .container {
                padding: 28px 20px;
                border-radius: 20px;
            }

            .logo h2 {
                font-size: 24px;
            }

            .btn {
                font-size: 15px;
                padding: 12px;
            }
        }

        @media (max-width: 400px) {
            .container {
                padding: 20px 14px;
                border-radius: 16px;
            }

            .logo .icon-wrapper {
                width: 48px;
                height: 48px;
            }

            .logo .icon-wrapper i {
                font-size: 20px;
            }

            .logo h2 {
                font-size: 20px;
            }

            .logo p {
                font-size: 12px;
            }

            .form-group label {
                font-size: 12px;
            }

            .form-group input {
                padding: 10px 14px;
                font-size: 13px;
                border-radius: 10px;
            }

            .btn {
                font-size: 14px;
                padding: 10px;
                border-radius: 10px;
            }

            .links a {
                font-size: 12px;
            }
        }
    </style>
    <?php bm_ui_head(); ?>
</head>

<body>
    <?php bm_ui_toggles(); ?>

    <div class="container">

        <div class="logo">
            <div class="icon-wrapper">
                <i class="fas fa-lock"></i>
            </div>
            <h2>BERTIN <span>MOVIE</span></h2>
            <p>
                <?= t('newpw_subtitle') ?>
            </p>
        </div>

        <?php if (isset($erreur)): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $erreur ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-lock"></i>
                    <?= t('newpw_label') ?>
                </label>
                <input type="password" name="pass1" placeholder="<?= t('newpw_ph') ?>" required minlength="6">
            </div>
            <div class="form-group">
                <label><i class="fas fa-check-circle"></i>
                    <?= t('newpw_confirm') ?>
                </label>
                <input type="password" name="pass2" placeholder="<?= t('newpw_ph_confirm') ?>" required minlength="6">
            </div>
            <button type="submit" name="save" class="btn btn-success">
                <i class="fas fa-save"></i>
                <?= t('newpw_btn') ?>
            </button>
        </form>

        <div class="links">
            <a href="login.php"><i class="fas fa-arrow-left"></i>
                <?= t('link_back_login') ?>
            </a>
        </div>

    </div>

</body>

</html>