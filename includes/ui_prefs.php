<?php
// =============================================
// BOUTONS LANGUE (FR/EN) + THÈME (clair/sombre)
// Utilisé par : login, creation_compte, mot_de_passe_oublie, verification_code, nouveau_mot_de_passe
//   - bm_ui_head()    : à appeler juste avant </head>
//   - bm_ui_toggles() : à appeler juste après <body>
// Les choix sont mémorisés dans les cookies "bm_lang" et "bm_theme" (1 an).
// =============================================
require_once __DIR__ . '/i18n.php';

function bm_ui_head()
{
    ?>
        <script>
            // Appliqué AVANT l'affichage de la page : pas de flash blanc en mode sombre
            (function () {
                var m = document.cookie.match(/(?:^|; )bm_theme=(light|dark)/);
                document.documentElement.setAttribute('data-theme', m ? m[1] : 'light');
            })();
        </script>
        <style>
            /* ---------- Boutons flottants (langue + thème) ---------- */
            .bm-ui {
                position: fixed;
                top: 14px;
                right: 14px;
                z-index: 1000;
                display: flex;
                gap: 8px;
                align-items: center;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            }

            .bm-ui .bm-seg,
            .bm-ui .bm-theme {
                height: 38px;
                border-radius: 999px;
                border: 1px solid rgba(59, 130, 246, 0.22);
                background: rgba(255, 255, 255, 0.92);
                box-shadow: 0 2px 12px rgba(37, 99, 235, 0.10);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
            }

            .bm-ui .bm-seg {
                display: inline-flex;
                padding: 3px;
                gap: 2px;
            }

            .bm-ui .bm-seg button {
                border: none;
                background: transparent;
                color: #475569;
                font: 700 12px 'Inter', sans-serif;
                letter-spacing: 0.5px;
                padding: 0 12px;
                border-radius: 999px;
                cursor: pointer;
                transition: all 0.25s ease;
            }

            .bm-ui .bm-seg button.active {
                background: linear-gradient(135deg, #3b82f6, #2563eb);
                color: #fff;
            }

            .bm-ui .bm-seg button:not(.active):hover {
                background: rgba(59, 130, 246, 0.10);
                color: #2563eb;
            }

            .bm-ui .bm-theme {
                width: 38px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #334155;
                font-size: 15px;
                cursor: pointer;
                padding: 0;
                transition: all 0.25s ease;
            }

            .bm-ui .bm-theme:hover {
                color: #2563eb;
                transform: translateY(-1px);
            }

            .bm-ui .bm-theme .fa-sun {
                display: none;
            }

            html[data-theme="dark"] .bm-ui .bm-theme .fa-sun {
                display: inline;
            }

            html[data-theme="dark"] .bm-ui .bm-theme .fa-moon {
                display: none;
            }

            @media (max-width: 420px) {
                .bm-ui {
                    top: 8px;
                    right: 8px;
                    gap: 6px;
                }

                .bm-ui .bm-seg,
                .bm-ui .bm-theme {
                    height: 34px;
                }

                .bm-ui .bm-theme {
                    width: 34px;
                }

                .bm-ui .bm-seg button {
                    padding: 0 10px;
                    font-size: 11px;
                }
            }

            /* ---------- Thème sombre ---------- */
            html[data-theme="dark"] {
                color-scheme: dark;
            }

            html[data-theme="dark"] body {
                background: #0a1020;
                color: #e2e8f0;
            }

            html[data-theme="dark"] body::before {
                background:
                    radial-gradient(ellipse at 20% 30%, rgba(59, 130, 246, 0.14) 0%, transparent 50%),
                    radial-gradient(ellipse at 80% 70%, rgba(139, 92, 246, 0.10) 0%, transparent 50%);
            }

            html[data-theme="dark"] .container,
            html[data-theme="dark"] .login-wrapper,
            html[data-theme="dark"] .form-right {
                background: #111a2e;
            }

            html[data-theme="dark"] .container {
                border-color: rgba(148, 163, 184, 0.14);
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.55);
            }

            html[data-theme="dark"] .login-wrapper {
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.55);
            }

            html[data-theme="dark"] .logo h2,
            html[data-theme="dark"] .form-right .logo-group .row h1,
            html[data-theme="dark"] .otp-info strong {
                color: #f1f5f9;
            }

            html[data-theme="dark"] .logo p,
            html[data-theme="dark"] .form-right .logo-group .desc,
            html[data-theme="dark"] .otp-info,
            html[data-theme="dark"] .links a,
            html[data-theme="dark"] .footer {
                color: #94a3b8;
            }

            html[data-theme="dark"] .links a.register-link {
                color: #60a5fa;
            }

            html[data-theme="dark"] .links a:hover,
            html[data-theme="dark"] .links a.register-link:hover {
                color: #60a5fa;
                background: rgba(59, 130, 246, 0.14);
            }

            html[data-theme="dark"] .footer {
                border-top-color: #1e293b;
            }

            html[data-theme="dark"] .logo .icon-wrapper {
                background: rgba(59, 130, 246, 0.16);
            }

            html[data-theme="dark"] .form-group label {
                color: #cbd5e1;
            }

            html[data-theme="dark"] .form-group input,
            html[data-theme="dark"] .form-group select,
            html[data-theme="dark"] .input-wrap input,
            html[data-theme="dark"] .otp-container input {
                background: #0b1324;
                border-color: #26334d;
                color: #f1f5f9;
            }

            html[data-theme="dark"] .form-group input:focus,
            html[data-theme="dark"] .form-group select:focus,
            html[data-theme="dark"] .input-wrap input:focus,
            html[data-theme="dark"] .otp-container input:focus {
                background: #0a1020;
                border-color: #3b82f6;
                box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.18);
            }

            html[data-theme="dark"] .otp-container input:not(:placeholder-shown) {
                background: #14223f;
            }

            html[data-theme="dark"] .form-group input::placeholder,
            html[data-theme="dark"] .input-wrap input::placeholder {
                color: #64748b;
            }

            html[data-theme="dark"] .form-group select option {
                background: #0b1324;
                color: #f1f5f9;
            }

            html[data-theme="dark"] .input-wrap .toggle {
                color: #64748b;
            }

            html[data-theme="dark"] .error,
            html[data-theme="dark"] .error-msg {
                background: rgba(220, 38, 38, 0.14);
                border-color: rgba(248, 113, 113, 0.30);
                color: #fca5a5;
            }

            html[data-theme="dark"] .error small {
                color: #fca5a5 !important;
            }

            html[data-theme="dark"] .success-otp {
                background: rgba(59, 130, 246, 0.16);
                border-color: rgba(96, 165, 250, 0.30);
                color: #93c5fd;
            }

            html[data-theme="dark"] .success-final {
                background: rgba(34, 197, 94, 0.14);
                border-color: rgba(74, 222, 128, 0.30);
                color: #86efac;
            }

            html[data-theme="dark"] .bm-ui .bm-seg,
            html[data-theme="dark"] .bm-ui .bm-theme {
                background: rgba(17, 26, 46, 0.92);
                border-color: rgba(148, 163, 184, 0.22);
                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.4);
            }

            html[data-theme="dark"] .bm-ui .bm-seg button {
                color: #cbd5e1;
            }

            html[data-theme="dark"] .bm-ui .bm-seg button.active {
                color: #fff;
            }

            html[data-theme="dark"] .bm-ui .bm-theme {
                color: #fbbf24;
            }
        </style>
        <?php
}

function bm_ui_toggles()
{
    $lang = bm_lang();
    ?>
        <div class="bm-ui" role="group" aria-label="<?= htmlspecialchars(t('ui_lang')) ?>">
            <div class="bm-seg">
                <button type="button" class="<?= $lang === 'fr' ? 'active' : '' ?>" onclick="bmSetLang('fr')"
                    aria-pressed="<?= $lang === 'fr' ? 'true' : 'false' ?>" title="Français">FR</button>
                <button type="button" class="<?= $lang === 'en' ? 'active' : '' ?>" onclick="bmSetLang('en')"
                    aria-pressed="<?= $lang === 'en' ? 'true' : 'false' ?>" title="English">EN</button>
            </div>
            <button type="button" class="bm-theme" onclick="bmToggleTheme()" title="<?= htmlspecialchars(t('ui_theme')) ?>"
                aria-label="<?= htmlspecialchars(t('ui_theme')) ?>">
                <i class="fas fa-moon"></i><i class="fas fa-sun"></i>
            </button>
        </div>
        <script>
            function bmCookie(name, value) {
                document.cookie = name + '=' + value + ';path=/;max-age=31536000;SameSite=Lax';
            }

            function bmToggleTheme() {
                var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                bmCookie('bm_theme', next);
            }

            // Changer de langue : on garde ce que la personne a déjà tapé (sauf les mots de passe et les codes OTP)
            function bmSetLang(lang) {
                try {
                    var data = {};
                    document.querySelectorAll('form input[name], form select[name]').forEach(function (el) {
                        if (el.type === 'password' || el.type === 'hidden' || el.type === 'submit' || el.name.indexOf('otp') === 0) return;
                        data[el.name] = el.value;
                    });
                    sessionStorage.setItem('bm_form:' + location.pathname, JSON.stringify(data));
                } catch (e) { }
                bmCookie('bm_lang', lang);
                // replace() = nouveau chargement en GET : ne renvoie jamais un formulaire déjà envoyé
                location.replace(location.pathname + location.search);
            }

            document.addEventListener('DOMContentLoaded', function () {
                try {
                    var key = 'bm_form:' + location.pathname;
                    var saved = sessionStorage.getItem(key);
                    if (!saved) return;
                    sessionStorage.removeItem(key);
                    var data = JSON.parse(saved);
                    Object.keys(data).forEach(function (name) {
                        var el = document.querySelector('form [name="' + name + '"]');
                        if (el && el.value === '') el.value = data[name];
                    });
                } catch (e) { }
            });
        </script>
        <?php
}