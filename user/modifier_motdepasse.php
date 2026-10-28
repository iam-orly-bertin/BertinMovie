<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/update_session.php';

if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$message = '';
$message_type = '';

if (isset($_POST['update'])) {
    $ancien = $_POST['ancien_motdepasse'];
    $nouveau = $_POST['nouveau_motdepasse'];
    $confirmer = $_POST['confirmer_motdepasse'];

    if (empty($ancien) || empty($nouveau) || empty($confirmer)) {
        $message = "Tous les champs sont requis";
        $message_type = 'error';
    } elseif (!password_verify($ancien, $user['mot_de_passe'])) {
        $message = "L'ancien mot de passe est incorrect";
        $message_type = 'error';
    } elseif (strlen($nouveau) < 6) {
        $message = "Le nouveau mot de passe doit contenir au moins 6 caractères";
        $message_type = 'error';
    } elseif ($nouveau !== $confirmer) {
        $message = "Les mots de passe ne correspondent pas";
        $message_type = 'error';
    } else {
        $hash = password_hash($nouveau, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE id_utilisateur = ?")->execute([$hash, $user_id]);
        $message = "✅ Mot de passe modifié avec succès !";
        $message_type = 'success';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title data-lang-fr="Modifier mot de passe" data-lang-en="Change password">Modifier mot de passe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/film_web_dy/user/css/style-user.css">
    <style>
        /* =========================================================
           MODIFIER MOT DE PASSE - DESIGN MODERNE
           ========================================================= */

        .password-container {
            max-width: 580px;
            width: 100%;
            margin: 0 auto;
            padding: 10px 16px 100px;
        }

        /* =========================================================
           BOUTON RETOUR
           ========================================================= */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary, #64748b);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 16px;
            padding: 10px 18px;
            background: var(--bg-card, #ffffff);
            border-radius: 12px;
            border: 1px solid var(--border-color, #e2e8f0);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        body.dark-mode .btn-back {
            background: #1e293b;
            border-color: #334155;
            color: #94a3b8;
        }

        .btn-back:hover {
            color: #3b82f6;
            transform: translateX(-4px);
            border-color: #3b82f6;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.12);
        }

        body.dark-mode .btn-back:hover {
            color: #60a5fa;
            border-color: #3b82f6;
        }

        /* =========================================================
           CARTE PRINCIPALE
           ========================================================= */
        .password-card {
            background: var(--bg-card, #ffffff);
            border-radius: 20px;
            padding: 32px 32px 34px;
            border: 1px solid var(--border-color, #e2e8f0);
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.3s ease;
        }

        body.dark-mode .password-card {
            background: #1e293b;
            border-color: #334155;
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.15);
        }

        .password-card:hover {
            box-shadow: 0 4px 30px rgba(59, 130, 246, 0.08);
        }

        body.dark-mode .password-card:hover {
            box-shadow: 0 4px 30px rgba(59, 130, 246, 0.12);
        }

        .password-card h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary, #0f172a);
            text-align: center;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        body.dark-mode .password-card h2 {
            color: #e2e8f0;
        }

        .password-card h2 i {
            color: #3b82f6;
            font-size: 26px;
        }

        /* =========================================================
           MESSAGES
           ========================================================= */
        .msg {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .msg.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        body.dark-mode .msg.success {
            background: #052e16;
            color: #86efac;
            border-color: #166534;
        }

        .msg.error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        body.dark-mode .msg.error {
            background: #450a0a;
            color: #fca5a5;
            border-color: #7f1d1d;
        }

        .msg i {
            font-size: 20px;
            flex-shrink: 0;
        }

        .msg.success i {
            color: #22c55e;
        }

        .msg.error i {
            color: #ef4444;
        }

        /* =========================================================
           FORMULAIRE
           ========================================================= */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-secondary, #475569);
        }

        body.dark-mode .form-group label {
            color: #94a3b8;
        }

        .form-group label i {
            margin-right: 6px;
            color: #3b82f6;
            width: 16px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            padding: 12px 48px 12px 16px;
            border: 2px solid var(--border-color, #e2e8f0);
            border-radius: 12px;
            font-size: 14px;
            background: var(--bg-input, #f8fafc);
            transition: all 0.3s ease;
            font-family: inherit;
            color: var(--text-primary, #0f172a);
        }

        body.dark-mode .input-wrapper input {
            background: #0f172a;
            border-color: #334155;
            color: #e2e8f0;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: #3b82f6;
            background: var(--bg-card, #ffffff);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.08);
        }

        body.dark-mode .input-wrapper input:focus {
            background: #1e293b;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
        }

        .input-wrapper input::placeholder {
            color: var(--text-secondary, #94a3b8);
        }

        body.dark-mode .input-wrapper input::placeholder {
            color: #64748b;
        }

        /* =========================================================
           BOUTON AFFICHER/MASQUER
           ========================================================= */
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary, #94a3b8);
            cursor: pointer;
            transition: all 0.2s ease;
            background: none;
            border: none;
            font-size: 16px;
            padding: 8px;
            border-radius: 8px;
        }

        .toggle-password:hover {
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.08);
        }

        body.dark-mode .toggle-password:hover {
            background: rgba(59, 130, 246, 0.12);
        }

        /* =========================================================
           INDICATEUR DE FORCE
           ========================================================= */
        .password-strength {
            margin-top: 8px;
            height: 5px;
            background: var(--border-color, #e2e8f0);
            border-radius: 4px;
            overflow: hidden;
        }

        body.dark-mode .password-strength {
            background: #334155;
        }

        .password-strength .bar {
            height: 100%;
            width: 0%;
            border-radius: 4px;
            transition: width 0.4s ease, background 0.4s ease;
        }

        .password-strength .bar.weak {
            width: 25%;
            background: #ef4444;
        }

        .password-strength .bar.medium {
            width: 50%;
            background: #f59e0b;
        }

        .password-strength .bar.strong {
            width: 75%;
            background: #22c55e;
        }

        .password-strength .bar.very-strong {
            width: 100%;
            background: #22c55e;
        }

        .password-strength-text {
            font-size: 12px;
            color: var(--text-secondary, #6b7280);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        body.dark-mode .password-strength-text {
            color: #94a3b8;
        }

        .password-strength-text i {
            color: var(--text-secondary, #94a3b8);
            font-size: 12px;
        }

        /* =========================================================
           BOUTON MODIFIER
           ========================================================= */
        .btn-update {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 2px 12px rgba(34, 197, 94, 0.25);
        }

        .btn-update:hover {
            background: linear-gradient(135deg, #16a34a, #15803d);
            transform: translateY(-3px);
            box-shadow: 0 6px 24px rgba(34, 197, 94, 0.35);
        }

        .btn-update:active {
            transform: scale(0.97);
        }

        /* =========================================================
           ANIMATION
           ========================================================= */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .password-card {
            animation: slideUp 0.4s ease-out;
        }

        .btn-back {
            animation: slideUp 0.4s ease-out 0.08s both;
        }

        /* =========================================================
           RESPONSIVE - MOBILE
           ========================================================= */
        @media (max-width: 768px) {
            .password-container {
                padding: 8px 12px 90px;
            }

            .password-card {
                padding: 24px 20px 26px;
            }

            .password-card h2 {
                font-size: 20px;
            }

            .btn-update {
                font-size: 15px;
                padding: 14px;
            }

            .btn-back {
                font-size: 13px;
                padding: 8px 16px;
            }

            .form-group {
                margin-bottom: 16px;
            }

            .input-wrapper input {
                padding: 11px 44px 11px 14px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .password-container {
                padding: 6px 8px 80px;
            }

            .password-card {
                padding: 18px 14px 22px;
                border-radius: 16px;
            }

            .password-card h2 {
                font-size: 18px;
            }

            .password-card h2 i {
                font-size: 20px;
            }

            .form-group {
                margin-bottom: 14px;
            }

            .form-group label {
                font-size: 12px;
            }

            .input-wrapper input {
                padding: 10px 40px 10px 12px;
                font-size: 13px;
                border-radius: 10px;
            }

            .toggle-password {
                right: 10px;
                font-size: 14px;
                padding: 6px;
            }

            .btn-update {
                font-size: 14px;
                padding: 12px;
                border-radius: 10px;
            }

            .btn-update i {
                font-size: 14px;
            }

            .btn-back {
                font-size: 12px;
                padding: 6px 14px;
                border-radius: 10px;
            }

            .btn-back i {
                font-size: 12px;
            }

            .password-strength {
                height: 4px;
                margin-top: 6px;
            }

            .password-strength-text {
                font-size: 11px;
            }

            .msg {
                padding: 10px 14px;
                font-size: 13px;
                border-radius: 10px;
            }

            .msg i {
                font-size: 16px;
            }
        }

        @media (max-width: 380px) {
            .password-container {
                padding: 4px 4px 70px;
            }

            .password-card {
                padding: 14px 10px 18px;
                border-radius: 14px;
            }

            .password-card h2 {
                font-size: 16px;
            }

            .form-group label {
                font-size: 11px;
            }

            .input-wrapper input {
                padding: 8px 36px 8px 10px;
                font-size: 12px;
                border-radius: 8px;
            }

            .toggle-password {
                right: 8px;
                font-size: 12px;
                padding: 4px;
            }

            .btn-update {
                font-size: 13px;
                padding: 10px;
                border-radius: 8px;
            }

            .btn-update i {
                font-size: 12px;
            }

            .btn-back {
                font-size: 11px;
                padding: 5px 12px;
                border-radius: 8px;
            }

            .btn-back i {
                font-size: 10px;
            }

            .password-strength-text {
                font-size: 10px;
            }

            .msg {
                padding: 8px 12px;
                font-size: 12px;
                border-radius: 8px;
            }

            .msg i {
                font-size: 14px;
            }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/menu-user.php'; ?>

    <div class="password-container">

        <a href="profil.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            <span data-lang-fr="Retour au profil" data-lang-en="Back to profile">Retour au profil</span>
        </a>

        <div class="password-card">
            <h2>
                <i class="fas fa-key"></i>
                <span data-lang-fr="Modifier mot de passe" data-lang-en="Change password">Modifier mot de passe</span>
            </h2>

            <?php if ($message): ?>
                <div class="msg <?= $message_type ?>">
                    <i class="fas <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <!-- Ancien mot de passe -->
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> <span data-lang-fr="Ancien mot de passe"
                            data-lang-en="Old password">Ancien mot de passe</span></label>
                    <div class="input-wrapper">
                        <input type="password" name="ancien_motdepasse" id="oldPassword" required
                            placeholder="Entrez votre mot de passe actuel"
                            data-lang-fr-placeholder="Entrez votre mot de passe actuel"
                            data-lang-en-placeholder="Enter your current password">
                        <button type="button" class="toggle-password" onclick="toggleVisibility('oldPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Nouveau mot de passe -->
                <div class="form-group">
                    <label><i class="fas fa-shield-alt"></i> <span data-lang-fr="Nouveau mot de passe"
                            data-lang-en="New password">Nouveau mot de passe</span></label>
                    <div class="input-wrapper">
                        <input type="password" name="nouveau_motdepasse" id="newPassword" required minlength="6"
                            placeholder="Minimum 6 caractères" data-lang-fr-placeholder="Minimum 6 caractères"
                            data-lang-en-placeholder="Minimum 6 characters" oninput="checkPasswordStrength(this.value)">
                        <button type="button" class="toggle-password" onclick="toggleVisibility('newPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="bar" id="strengthBar"></div>
                    </div>
                    <div class="password-strength-text">
                        <i class="fas fa-info-circle"></i>
                        <span id="strengthText" data-lang-fr="Minimum 6 caractères"
                            data-lang-en="Minimum 6 characters">Minimum 6 caractères</span>
                    </div>
                </div>

                <!-- Confirmation -->
                <div class="form-group">
                    <label><i class="fas fa-check-circle"></i> <span data-lang-fr="Confirmer le mot de passe"
                            data-lang-en="Confirm password">Confirmer le mot de passe</span></label>
                    <div class="input-wrapper">
                        <input type="password" name="confirmer_motdepasse" id="confirmPassword" required minlength="6"
                            placeholder="Répétez le nouveau mot de passe"
                            data-lang-fr-placeholder="Répétez le nouveau mot de passe"
                            data-lang-en-placeholder="Repeat the new password">
                        <button type="button" class="toggle-password"
                            onclick="toggleVisibility('confirmPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="update" class="btn-update">
                    <i class="fas fa-save"></i>
                    <span data-lang-fr="Modifier le mot de passe" data-lang-en="Change password">Modifier le mot de
                        passe</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        // =========================================================
        // INDICATEUR DE FORCE DU MOT DE PASSE
        // =========================================================
        function checkPasswordStrength(password) {
            const bar = document.getElementById('strengthBar');
            const text = document.getElementById('strengthText');
            const lang = localStorage.getItem('lang') || 'fr';

            let strength = 0;

            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;

            const levels = {
                fr: ['', 'Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'],
                en: ['', 'Very weak', 'Weak', 'Medium', 'Strong', 'Very strong']
            };

            const classes = ['', 'weak', 'weak', 'medium', 'strong', 'very-strong'];

            if (password.length === 0) {
                bar.className = 'bar';
                const defaultText = lang === 'fr' ? 'Minimum 6 caractères' : 'Minimum 6 characters';
                text.textContent = defaultText;
                return;
            }

            bar.className = 'bar ' + classes[strength];
            const prefix = lang === 'fr' ? 'Force : ' : 'Strength : ';
            text.textContent = prefix + levels[lang][strength] || levels['fr'][strength];
        }

        // =========================================================
        // AFFICHER/MASQUER LE MOT DE PASSE
        // =========================================================
        function toggleVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // =========================================================
        // TRADUCTION DES PLACEHOLDERS
        // =========================================================
        function applyLanguageForPlaceholders(lang) {
            document.querySelectorAll('[data-lang-fr-placeholder][data-lang-en-placeholder]').forEach(el => {
                const placeholder = el.getAttribute('data-lang-' + lang + '-placeholder');
                if (placeholder !== null) {
                    el.placeholder = placeholder;
                }
            });
        }

        // Surcharger la fonction applyLanguage pour inclure les placeholders
        const originalApplyLanguage = window.applyLanguage;
        window.applyLanguage = function (lang) {
            if (typeof originalApplyLanguage === 'function') {
                originalApplyLanguage(lang);
            }
            applyLanguageForPlaceholders(lang);

            // Mettre à jour le texte de force si un mot de passe est déjà saisi
            const newPassword = document.getElementById('newPassword');
            if (newPassword && newPassword.value.length > 0) {
                checkPasswordStrength(newPassword.value);
            }
        };

        // Initialiser au chargement
        document.addEventListener('DOMContentLoaded', function () {
            const currentLang = localStorage.getItem('lang') || 'fr';
            applyLanguageForPlaceholders(currentLang);
        });
    </script>

</body>

</html>