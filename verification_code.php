<?php
session_start();
include("connexion.php");
require_once __DIR__ . '/includes/ui_prefs.php'; // langue FR/EN + thème clair/sombre

// ✅ VÉRIFIER QUE L'EMAIL ET L'OTP SONT DANS LA SESSION
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_otp'])) {
    header("Location: mot_de_passe_oublie.php");
    exit();
}

// Sécurité du code : valable 10 minutes, 5 essais maximum
define('OTP_DUREE', 600);
define('OTP_MAX_ESSAIS', 5);

if (isset($_POST['verifier'])) {
    // Champ caché rempli par le JavaScript ; sinon on reconstruit le code avec otp1..otp6
    $code_input = trim($_POST['otp'] ?? '');
    if ($code_input === '') {
        for ($i = 1; $i <= 6; $i++) {
            $code_input .= trim($_POST['otp' . $i] ?? '');
        }
    }
    $otp_session = (string) $_SESSION['reset_otp'];

    // 1) Code expiré ?
    if ((time() - ($_SESSION['reset_otp_time'] ?? 0)) > OTP_DUREE) {
        unset($_SESSION['reset_otp'], $_SESSION['reset_tentatives']);
        header("Location: mot_de_passe_oublie.php?expire=1");
        exit();
    }

    // 2) Code correct ? (comparaison sûre)
    if (hash_equals($otp_session, $code_input)) {
        unset($_SESSION['reset_otp'], $_SESSION['reset_tentatives']);
        // ✅ Preuve que le code a été vérifié : exigée par nouveau_mot_de_passe.php
        $_SESSION['reset_verified'] = true;
        $_SESSION['reset_verified_time'] = time();
        session_regenerate_id(true);
        header("Location: nouveau_mot_de_passe.php");
        exit();
    }

    // 3) Code faux : on compte l'essai
    $_SESSION['reset_tentatives'] = ($_SESSION['reset_tentatives'] ?? 0) + 1;
    $restants = OTP_MAX_ESSAIS - $_SESSION['reset_tentatives'];

    if ($restants <= 0) {
        unset($_SESSION['reset_otp'], $_SESSION['reset_tentatives']);
        header("Location: mot_de_passe_oublie.php?essais=1");
        exit();
    }

    $erreur = t('err_otp_wrong', $restants);
}
?>

<!DOCTYPE html>
<html lang="<?= bm_lang() ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>
        <?= t('verif_title') ?>
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
           OTP INFO
        ========================================================= */
        .otp-info {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .otp-info strong {
            color: #0f172a;
        }

        /* =========================================================
           OTP CONTAINER
        ========================================================= */
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
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2>BERTIN <span>MOVIE</span></h2>
            <p>
                <?= t('verif_subtitle') ?>
            </p>
        </div>

        <?php if (isset($erreur)): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $erreur ?>
            </div>
        <?php endif; ?>

        <div class="otp-info">
            <?= t('verif_info', htmlspecialchars($_SESSION['reset_email'])) ?>
        </div>

        <form method="POST" id="otpForm">
            <div class="otp-container" id="otpContainer">
                <input type="text" name="otp1" maxlength="1" pattern="[0-9]" required autofocus>
                <input type="text" name="otp2" maxlength="1" pattern="[0-9]" required>
                <input type="text" name="otp3" maxlength="1" pattern="[0-9]" required>
                <input type="text" name="otp4" maxlength="1" pattern="[0-9]" required>
                <input type="text" name="otp5" maxlength="1" pattern="[0-9]" required>
                <input type="text" name="otp6" maxlength="1" pattern="[0-9]" required>
            </div>

            <input type="hidden" name="otp" id="otpHidden">

            <button type="submit" name="verifier" class="btn btn-success">
                <i class="fas fa-check-circle"></i>
                <?= t('verif_btn') ?>
            </button>
        </form>

        <div class="links" style="margin-top:16px;">
            <a href="mot_de_passe_oublie.php"><i class="fas fa-redo"></i>
                <?= t('verif_resend') ?>
            </a>
            <br>
            <a href="login.php"><i class="fas fa-home"></i>
                <?= t('verif_home') ?>
            </a>
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

    </div>

</body>

</html>