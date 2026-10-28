<?php
session_start();
include("connexion.php");
require_once __DIR__ . '/includes/ui_prefs.php'; // langue FR/EN + thème clair/sombre
require_once __DIR__ . '/includes/countries.php'; // liste mondiale des pays
require_once __DIR__ . '/config.php'; // identifiants SMTP

// Charger PHPMailer
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ==========================
// 📧 ENVOI EMAIL OTP
// ==========================
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
                <p style="color: #475569;">' . t('mail_signup_intro') . '</p>
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

// Liste mondiale des pays (code ISO => nom dans la langue choisie)
$liste_pays = bm_countries_sorted(bm_lang());

// ==========================
// STEP 1 : INSCRIPTION
// ==========================
if (isset($_POST['register'])) {

    $nom_utilisateur = trim($_POST['nom_utilisateur'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sexe = $_POST['sexe'] ?? '';
    $date_naissance = $_POST['date_naissance'] ?? '';
    $pays_code = strtoupper(trim($_POST['pays_code'] ?? ''));
    $password = $_POST['mot_de_passe'] ?? '';
    $confirm = $_POST['confirmation'] ?? '';

    // Validations côté serveur (le HTML seul ne suffit pas)
    $d_naiss = DateTime::createFromFormat('Y-m-d', $date_naissance);
    $date_ok = $d_naiss && $d_naiss->format('Y-m-d') === $date_naissance
        && $d_naiss <= new DateTime('today') && $d_naiss >= new DateTime('1900-01-01');
    $pays_ok = isset($BM_COUNTRIES[$pays_code]);

    // Anti-spam : 60 secondes minimum entre deux envois pour le même email
    $attente = 60 - (time() - ($_SESSION['signup_time'] ?? 0));

    if (empty($nom_utilisateur) || empty($prenom) || empty($nom) || empty($email) || empty($password)) {
        $error = t('err_all_required');
    } elseif ($password != $confirm) {
        $error = t('err_pw_mismatch');
    } elseif (strlen($password) < 6) {
        $error = t('err_pw_short');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('err_email_invalid');
    } elseif (!in_array($sexe, ['M', 'F'], true)) {
        $error = t('err_sex_invalid');
    } elseif (!$date_ok) {
        $error = t('err_date_invalid');
    } elseif (!$pays_ok) {
        $error = t('err_country_invalid');
    } elseif ($attente > 0 && ($_SESSION['signup_email'] ?? '') === $email) {
        $error = t('err_wait', $attente);
    } else {
        $check = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ? OR nom_utilisateur = ?");
        $check->execute([$email, $nom_utilisateur]);
        if ($check->fetch()) {
            $error = t('err_taken');
        } else {
            $pdo->prepare("DELETE FROM pending_users WHERE email = ?")->execute([$email]);

            // Pays : retrouve (ou crée automatiquement) la ligne correspondante dans la table `pays`
            $id_pays = bm_country_id($pdo, $pays_code);

            $otp = random_int(100000, 999999);
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO pending_users (nom_utilisateur, prenom, nom, email, sexe, date_naissance, id_pays, mot_de_passe, otp_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nom_utilisateur, $prenom, $nom, $email, $sexe, $date_naissance, $id_pays, $hash, $otp]);

            if (sendOTP($email, $prenom . ' ' . $nom, $otp)) {
                // On mémorise l'inscription en cours dans la session (expiration + essais)
                $_SESSION['signup_email'] = $email;
                $_SESSION['signup_time'] = time();
                $_SESSION['signup_tentatives'] = 0;
                unset($_SESSION['signup_abandon']);

                $step = "verify";
                $email_for_otp = $email;
                $success_otp = t('reg_otp_sent', htmlspecialchars($email));
            } else {
                $pdo->prepare("DELETE FROM pending_users WHERE email = ?")->execute([$email]);
                $error = t('err_mail_send');
                if (isset($_SESSION['mail_error'])) {
                    $error .= "<br><small style='color:#ef4444;'>" . t('err_mail_detail_reg') . "</small>";
                    unset($_SESSION['mail_error']);
                }
            }
        }
    }
}

// ==========================
// STEP 2 : VÉRIFICATION OTP
// ==========================
if (isset($_POST['verify'])) {

    $email = $_POST['email'] ?? '';
    // Champ caché rempli par le JavaScript ; sinon on reconstruit le code avec otp1..otp6
    $otp_input = trim($_POST['otp'] ?? '');
    if ($otp_input === '') {
        for ($i = 1; $i <= 6; $i++) {
            $otp_input .= trim($_POST['otp' . $i] ?? '');
        }
    }

    if (empty($otp_input) || empty($email)) {
        $error = t('err_otp_empty');
        $step = "verify";
        $email_for_otp = $email;
    } elseif (($_SESSION['signup_email'] ?? '') !== $email) {
        // L'inscription n'a pas été démarrée depuis ce navigateur
        $error = t('err_session_expired');
    } elseif ((time() - ($_SESSION['signup_time'] ?? 0)) > 600) {
        // Code expiré (10 minutes)
        $pdo->prepare("DELETE FROM pending_users WHERE email = ?")->execute([$email]);
        unset($_SESSION['signup_email'], $_SESSION['signup_time'], $_SESSION['signup_tentatives']);
        $error = t('err_code_expired_reg');
    } elseif (($_SESSION['signup_tentatives'] ?? 0) >= 5) {
        // Trop d'essais
        $pdo->prepare("DELETE FROM pending_users WHERE email = ?")->execute([$email]);
        unset($_SESSION['signup_email'], $_SESSION['signup_time'], $_SESSION['signup_tentatives']);
        $error = t('err_too_many_reg');
    } else {
        $stmt = $pdo->prepare("SELECT * FROM pending_users WHERE email = ? AND otp_code = ?");
        $stmt->execute([$email, $otp_input]);
        $user = $stmt->fetch();

        // Dernière vérification : l'email ou le nom d'utilisateur n'a pas été pris entre-temps
        $deja = false;
        if ($user) {
            $dup = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ? OR nom_utilisateur = ?");
            $dup->execute([$user['email'], $user['nom_utilisateur']]);
            $deja = (bool) $dup->fetch();
        }

        if ($user && $deja) {
            $pdo->prepare("DELETE FROM pending_users WHERE email = ?")->execute([$email]);
            unset($_SESSION['signup_email'], $_SESSION['signup_time'], $_SESSION['signup_tentatives']);
            $error = t('err_taken');
        } elseif ($user) {
            $insert = $pdo->prepare("INSERT INTO utilisateur (nom_utilisateur, prenom, nom, email, sexe, date_naissance, id_pays, mot_de_passe, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'user')");
            $insert->execute([
                $user['nom_utilisateur'],
                $user['prenom'],
                $user['nom'],
                $user['email'],
                $user['sexe'],
                $user['date_naissance'],
                $user['id_pays'],
                $user['mot_de_passe']
            ]);

            $del = $pdo->prepare("DELETE FROM pending_users WHERE email = ?");
            $del->execute([$email]);

            unset($_SESSION['signup_email'], $_SESSION['signup_time'], $_SESSION['signup_tentatives']);

            $success = t('reg_done');
            $step = "done";
        } else {
            $_SESSION['signup_tentatives'] = ($_SESSION['signup_tentatives'] ?? 0) + 1;
            $error = t('err_otp_wrong_reg', max(0, 5 - $_SESSION['signup_tentatives']));
            $step = "verify";
            $email_for_otp = $email;
        }
    }
}

// Rechargement en GET (ex : changement de langue) pendant l'étape du code : on reste sur l'étape du code
if (!isset($step) && !isset($success) && !isset($_POST['register']) && !isset($_POST['verify'])) {
    if (isset($_GET['restart'])) {
        // "Renvoyer un nouveau code" : formulaire vide (le délai anti-spam de 60 s reste actif)
        $_SESSION['signup_abandon'] = 1;
    } elseif (empty($_SESSION['signup_abandon']) && !empty($_SESSION['signup_email']) && (time() - ($_SESSION['signup_time'] ?? 0)) <= 600) {
        $step = "verify";
        $email_for_otp = $_SESSION['signup_email'];
    }
}

// Garde ce que la personne a tapé quand il y a une erreur (jamais les mots de passe)
function bm_old($k)
{
    return htmlspecialchars($_POST[$k] ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="<?= bm_lang() ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= t('reg_title') ?></title>
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
        .container {
            max-width: 560px;
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
           FORM ELEMENTS
        ========================================================= */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-group {
            margin-bottom: 16px;
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

        .form-group input,
        .form-group select {
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

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.06);
        }

        .form-group input::placeholder {
            color: #94a3b8;
        }

        .form-group select {
            appearance: auto;
            cursor: pointer;
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

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
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

        .success-otp {
            background: #dbeafe;
            color: #1e40af;
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 18px;
            border: 1px solid #bfdbfe;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            animation: slideDown 0.4s ease;
        }

        .success-otp i {
            color: #3b82f6;
            margin-right: 8px;
        }

        .success-final {
            background: #dcfce7;
            color: #166534;
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 16px;
            border: 1px solid #bbf7d0;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            animation: slideDown 0.4s ease;
        }

        .success-final i {
            color: #22c55e;
            margin-right: 8px;
        }

        /* =========================================================
           LIENS
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
           OTP
        ========================================================= */
        .otp-info {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 16px;
        }

        .otp-info strong {
            color: #0f172a;
        }

        .otp-container {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin: 16px 0 24px;
        }

        .otp-container input {
            width: 52px;
            height: 60px;
            text-align: center;
            font-size: 26px;
            font-weight: 700;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            color: #0f172a;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .otp-container input:focus {
            outline: none;
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.06);
        }

        .otp-container input:not(:placeholder-shown) {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        /* =========================================================
           DONE
        ========================================================= */
        .done {
            text-align: center;
        }

        .done .icon {
            font-size: 64px;
            display: block;
            margin-bottom: 16px;
        }

        .done .links {
            margin-top: 16px;
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

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .logo h2 {
                font-size: 24px;
            }

            .otp-container {
                gap: 8px;
            }

            .otp-container input {
                width: 44px;
                height: 52px;
                font-size: 22px;
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

            .form-group input,
            .form-group select {
                padding: 10px 14px;
                font-size: 13px;
                border-radius: 10px;
            }

            .otp-container {
                gap: 6px;
            }

            .otp-container input {
                width: 38px;
                height: 46px;
                font-size: 18px;
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

        @media (max-width: 350px) {
            .otp-container input {
                width: 32px;
                height: 40px;
                font-size: 16px;
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
                <i class="fas fa-film"></i>
            </div>
            <h2>BERTIN <span>MOVIE</span></h2>
            <p><?= t('reg_subtitle') ?></p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error">
                <div><i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($success_otp)): ?>
            <div class="success-otp">
                <i class="fas fa-check-circle"></i> <?= $success_otp ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="done">
                <span class="icon">✅</span>
                <div class="success-final">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
                <div class="links">
                    <a href="index.php"><i class="fas fa-sign-in-alt"></i> <?= t('reg_signin') ?></a>
                </div>
            </div>
        <?php endif; ?>

        <!-- STEP 1 : FORMULAIRE D'INSCRIPTION -->
        <?php if (!isset($step) && !isset($success)): ?>
            <form method="POST">

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user-circle"></i> <?= t('reg_username') ?></label>
                        <input type="text" name="nom_utilisateur" placeholder="<?= t('reg_ph_username') ?>"
                            value="<?= bm_old('nom_utilisateur') ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> <?= t('label_email') ?></label>
                        <input type="email" name="email" placeholder="<?= t('reg_ph_email') ?>"
                            value="<?= bm_old('email') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> <?= t('reg_firstname') ?></label>
                        <input type="text" name="prenom" placeholder="<?= t('reg_ph_firstname') ?>"
                            value="<?= bm_old('prenom') ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> <?= t('reg_lastname') ?></label>
                        <input type="text" name="nom" placeholder="<?= t('reg_ph_lastname') ?>" value="<?= bm_old('nom') ?>"
                            required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-venus-mars"></i> <?= t('reg_sex') ?></label>
                        <select name="sexe" required>
                            <option value=""><?= t('reg_select') ?></option>
                            <option value="M" <?= (($_POST['sexe'] ?? '') === 'M') ? 'selected' : '' ?>><?= t('reg_male') ?>
                            </option>
                            <option value="F" <?= (($_POST['sexe'] ?? '') === 'F') ? 'selected' : '' ?>><?= t('reg_female') ?>
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> <?= t('reg_birth') ?></label>
                        <input type="date" name="date_naissance" value="<?= bm_old('date_naissance') ?>"
                            max="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-globe"></i> <?= t('reg_nationality') ?></label>
                    <select name="pays_code" required>
                        <option value=""><?= t('reg_select_country') ?></option>
                        <?php foreach ($liste_pays as $code => $nom_pays): ?>
                            <option value="<?= $code ?>" <?= (($_POST['pays_code'] ?? '') === $code) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nom_pays) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> <?= t('label_password') ?></label>
                        <input type="password" name="mot_de_passe" placeholder="<?= t('reg_ph_password') ?>" required
                            minlength="6">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-check-circle"></i> <?= t('reg_confirm') ?></label>
                        <input type="password" name="confirmation" placeholder="<?= t('reg_ph_confirm') ?>" required
                            minlength="6">
                    </div>
                </div>

                <button type="submit" name="register" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> <?= t('reg_btn') ?>
                </button>
            </form>

            <div class="links">
                <a href="login.php"><i class="fas fa-arrow-left"></i> <?= t('link_back_login') ?></a>
            </div>
        <?php endif; ?>

        <!-- STEP 2 : VÉRIFICATION OTP -->
        <?php if (isset($step) && $step == "verify" && !isset($success)): ?>
            <div class="otp-info">
                <?= t('reg_otp_info', htmlspecialchars($email_for_otp)) ?>
            </div>

            <form method="POST" id="otpForm">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email_for_otp) ?>">

                <div class="otp-container" id="otpContainer">
                    <input type="text" name="otp1" maxlength="1" pattern="[0-9]" required autofocus>
                    <input type="text" name="otp2" maxlength="1" pattern="[0-9]" required>
                    <input type="text" name="otp3" maxlength="1" pattern="[0-9]" required>
                    <input type="text" name="otp4" maxlength="1" pattern="[0-9]" required>
                    <input type="text" name="otp5" maxlength="1" pattern="[0-9]" required>
                    <input type="text" name="otp6" maxlength="1" pattern="[0-9]" required>
                </div>

                <input type="hidden" name="otp" id="otpHidden">

                <button type="submit" name="verify" class="btn btn-success">
                    <i class="fas fa-check-circle"></i> <?= t('reg_btn_verify') ?>
                </button>
            </form>

            <div class="links" style="margin-top:16px;">
                <a href="creation_compte.php?restart=1"><i class="fas fa-redo"></i> <?= t('reg_resend') ?></a>
            </div>

            <script>
                const inputs = document.querySelectorAll('.otp-container input');
                const hiddenInput = document.getElementById('otpHidden');

                inputs.forEach((input, index) => {
                    input.addEventListener('input', function (e) {
                        this.value = this.value.replace(/\D/g, '');
                        if (this.value.length === 1 && index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                        updateHiddenInput();
                    });

                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Backspace' && this.value === '' && index > 0) {
                            inputs[index - 1].focus();
                        }
                    });

                    input.addEventListener('paste', function (e) {
                        e.preventDefault();
                        const paste = (e.clipboardData || window.clipboardData).getData('text');
                        const digits = paste.replace(/\D/g, '').slice(0, 6);
                        if (digits.length > 0) {
                            for (let i = 0; i < digits.length && i < inputs.length; i++) {
                                inputs[i].value = digits[i] || '';
                            }
                            const lastIndex = Math.min(digits.length - 1, inputs.length - 1);
                            if (lastIndex < inputs.length - 1) {
                                inputs[lastIndex + 1].focus();
                            }
                            updateHiddenInput();
                        }
                    });
                });

                function updateHiddenInput() {
                    let otp = '';
                    inputs.forEach(input => { otp += input.value; });
                    hiddenInput.value = otp;
                }

                document.getElementById('otpForm').addEventListener('submit', function (e) {
                    updateHiddenInput();
                    if (hiddenInput.value.length !== 6) {
                        e.preventDefault();
                        alert(<?= json_encode(t('js_otp_alert'), JSON_UNESCAPED_UNICODE) ?>);
                    }
                });
            </script>
        <?php endif; ?>

    </div>

</body>

</html>