<?php
session_start();
include("connexion.php");
require_once __DIR__ . '/includes/ui_prefs.php'; // langue FR/EN + thème clair/sombre

require_once __DIR__ . '/config.php'; // identifiants SMTP

// Charger PHPMailer pour l'envoi d'email
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendOTP($email, $nom, $otp)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom(SMTP_USER, 'BERTIN MOVIE');
        $mail->addReplyTo(SMTP_USER, 'BERTIN MOVIE');
        $mail->addAddress($email, $nom);

        $mail->isHTML(true);
        $mail->Subject = t('mail_subject');
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; background: #f1f5f9; color: #0f172a; padding: 20px; margin: 0;">
            <div style="max-width: 500px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                <h2 style="text-align: center; color: #3b82f6; margin-bottom: 20px;">🎬 BERTIN MOVIE</h2>
                <p style="font-size: 16px;">' . t('mail_hello') . ' <strong>' . htmlspecialchars($nom) . '</strong>,</p>
                <p style="color: #475569;">' . t('mail_reset_intro') . '</p>
                <p style="color: #475569;">' . t('mail_code_label') . '</p>
                <div style="font-size: 36px; font-weight: 700; color: #3b82f6; text-align: center; padding: 20px; background: #f8fafc; border-radius: 8px; margin: 20px 0; letter-spacing: 10px; font-family: monospace;">
                    ' . $otp . '
                </div>
                <p style="color: #94a3b8; font-size: 13px;">' . t('mail_expire') . '</p>
                <p style="color: #94a3b8; font-size: 13px;">' . t('mail_ignore') . '</p>
                <div style="text-align: center; color: #94a3b8; font-size: 12px; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                    &copy; 2026 BERTIN MOVIE - ' . t('mail_rights') . '
                </div>
            </div>
        </body>
        </html>
        ';

        $mail->AltBody = t('mail_alt', $nom, $otp);

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        $_SESSION['mail_error'] = $mail->ErrorInfo;
        return false;
    }
}

if (isset($_POST['envoyer'])) {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $erreur = t('err_enter_email');
    } else {
        $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Anti-spam : 60 secondes minimum entre deux envois pour le même email
        $attente = 60 - (time() - ($_SESSION['reset_otp_time'] ?? 0));

        if ($user && $attente > 0 && ($_SESSION['reset_email'] ?? '') === $email) {
            $erreur = t('err_wait', $attente);
        } elseif ($user) {
            $otp = random_int(100000, 999999);
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_otp'] = (string) $otp;
            $_SESSION['reset_otp_time'] = time();      // pour l'expiration (10 min)
            $_SESSION['reset_tentatives'] = 0;         // essais de saisie du code
            unset($_SESSION['reset_verified'], $_SESSION['reset_verified_time']);

            $nom_complet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
            if ($nom_complet === '') {
                $nom_complet = $user['nom_utilisateur'] ?? 'Utilisateur';
            }

            if (sendOTP($email, $nom_complet, $otp)) {
                header("Location: verification_code.php");
                exit();
            } else {
                unset($_SESSION['reset_otp'], $_SESSION['reset_otp_time']);
                $erreur = t('err_mail_send');
                if (isset($_SESSION['mail_error'])) {
                    $erreur .= "<br><small style='color:#ef4444;'>" . t('err_mail_detail') . "</small>";
                    unset($_SESSION['mail_error']);
                }
            }
        } else {
            $erreur = t('err_email_unknown');
        }
    }
}

// Messages renvoyés par verification_code.php
if (!isset($erreur) && isset($_GET['expire'])) {
    $erreur = t('err_expired_get');
} elseif (!isset($erreur) && isset($_GET['essais'])) {
    $erreur = t('err_tries_get');
}
?>

<!DOCTYPE html>
<html lang="<?= bm_lang() ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>
        <?= t('forgot_title') ?>
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
            flex-direction: column;
            gap: 4px;
            animation: slideDown 0.4s ease;
        }

        .error i {
            font-size: 18px;
            color: #ef4444;
            margin-right: 8px;
        }

        .error small {
            font-size: 12px;
            color: #dc2626;
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
                <i class="fas fa-key"></i>
            </div>
            <h2>BERTIN <span>MOVIE</span></h2>
            <p>
                <?= t('forgot_subtitle') ?>
            </p>
        </div>

        <?php if (isset($erreur)): ?>
            <div class="error">
                <div><i class="fas fa-exclamation-circle"></i>
                    <?= $erreur ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i>
                    <?= t('label_email') ?>
                </label>
                <input type="email" name="email" placeholder="<?= t('forgot_ph_email') ?>"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <button type="submit" name="envoyer" class="btn btn-success">
                <i class="fas fa-paper-plane"></i>
                <?= t('forgot_btn') ?>
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