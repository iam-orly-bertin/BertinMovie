<?php
/**
 * includes/permissions.php : droits par rôle ET par utilisateur (critère 3)
 *
 * Deux tables (créées et remplies automatiquement) :
 *   permissions              (role, permission)                    -> droits de chaque rôle (une ligne = droit accordé)
 *   permissions_utilisateur  (id_utilisateur, permission, autorise) -> exception pour UN utilisateur (1 = autorisé, 0 = interdit)
 *
 * Règle : exception personnelle si elle existe, sinon droit du rôle.
 * Tout se configure dans l'interface : Paramètres -> Droits d'accès (admin/permissions.php).
 *
 * Utilisation dans une page (après auth_check.php) :
 *     require_once __DIR__ . '/../includes/permissions.php';
 *     require_permission('voir_rapports');
 * ou :  if (can('commenter')) { ... }
 */
require_once __DIR__ . '/bm_trace.php';

/** Catalogue : code => [libellé, icône, groupe, rôles concernés]. Sert à l'interface et aux valeurs par défaut. */
function perm_catalog()
{
    return [
        // ----- Côté utilisateur -----
        'voir_films' => ['Regarder les vidéos', 'fa-play-circle', 'Utilisateur', ['admin', 'user']],
        'commenter' => ['Commenter les films', 'fa-comment', 'Utilisateur', ['admin', 'user']],
        'aimer' => ['Aimer (like) un film', 'fa-heart', 'Utilisateur', ['admin', 'user']],
        'gerer_favoris' => ['Gérer ses favoris', 'fa-star', 'Utilisateur', ['admin', 'user']],
        'modifier_profil' => ['Modifier son profil', 'fa-user-pen', 'Utilisateur', ['admin', 'user']],
        'support_ecrire' => ['Écrire au support (chat)', 'fa-headset', 'Utilisateur', ['admin', 'user']],
        // ----- Côté administration -----
        'gerer_utilisateurs' => ['Gérer les utilisateurs', 'fa-users', 'Administration', ['admin']],
        'gerer_commentaires' => ['Modérer les commentaires', 'fa-comments', 'Administration', ['admin']],
        'voir_rapports' => ['Voir les rapports', 'fa-chart-pie', 'Administration', ['admin']],
        'voir_historique' => ['Voir le journal des activités', 'fa-clock-rotate-left', 'Administration', ['admin']],
        'gerer_corbeille' => ['Gérer la corbeille', 'fa-trash-can', 'Administration', ['admin']],
        'modifier_parametres' => ['Modifier les paramètres', 'fa-gear', 'Administration', ['admin']],
        'support_repondre' => ['Répondre au support', 'fa-reply', 'Administration', ['admin']],
    ];
}

/** Par défaut : tout le monde a tout ce qui le concerne. */
function perm_defaults()
{
    $d = ['admin' => [], 'user' => []];
    foreach (perm_catalog() as $code => $c) {
        foreach ($c[3] as $r) {
            $d[$r][] = $code;
        }
    }
    return $d;
}

function perm_ensure_table(PDO $pdo)
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        role VARCHAR(20) NOT NULL,
        permission VARCHAR(50) NOT NULL,
        UNIQUE KEY uq_role_perm (role, permission)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS permissions_utilisateur (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_utilisateur INT NOT NULL,
        permission VARCHAR(50) NOT NULL,
        autorise TINYINT(1) NOT NULL,
        UNIQUE KEY uq_user_perm (id_utilisateur, permission)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Les droits déjà « connus » sont mémorisés (role = '_known') : un droit décoché pour tous les rôles
    // n'est donc JAMAIS remis automatiquement ; seuls les droits vraiment nouveaux du catalogue sont ajoutés.
    $known = $pdo->query("SELECT permission FROM permissions WHERE role = '_known'")->fetchAll(PDO::FETCH_COLUMN);
    $ins = $pdo->prepare("INSERT IGNORE INTO permissions (role, permission) VALUES (?, ?)");
    $knownIns = $pdo->prepare("INSERT IGNORE INTO permissions (role, permission) VALUES ('_known', ?)");
    $isEmpty = (int) $pdo->query("SELECT COUNT(*) FROM permissions WHERE role <> '_known'")->fetchColumn() === 0;
    $hadKnown = count($known) > 0;

    foreach (perm_catalog() as $code => $c) {
        if (in_array($code, $known, true)) {
            continue;
        }
        // Table vide (première fois) ou droit jamais vu : on l'accorde par défaut.
        // Cas d'une ancienne installation (table remplie mais sans mémoire) : on ne touche qu'au droit « aimer » (le seul ajouté depuis).
        if ($isEmpty || $hadKnown || $code === 'aimer') {
            foreach ($c[3] as $r) {
                $ins->execute([$r, $code]);
            }
        }
        $knownIns->execute([$code]);
    }
    $done = true;
}

/** Droits du rôle (liste de codes). */
function perm_role_list($role)
{
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo) {
        return [];
    }
    perm_ensure_table($pdo);
    $st = $pdo->prepare("SELECT permission FROM permissions WHERE role = ?");
    $st->execute([$role]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

/** Exceptions d'un utilisateur : [code => true/false]. */
function perm_user_overrides($uid)
{
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo) {
        return [];
    }
    perm_ensure_table($pdo);
    $st = $pdo->prepare("SELECT permission, autorise FROM permissions_utilisateur WHERE id_utilisateur = ?");
    $st->execute([(int) $uid]);
    $o = [];
    foreach ($st->fetchAll() as $r) {
        $o[$r['permission']] = (bool) $r['autorise'];
    }
    return $o;
}

/** L'utilisateur connecté a-t-il ce droit ? (calculé une fois par requête et par droit) */
function can($permission)
{
    static $cache = [];
    $role = (string) ($_SESSION['role'] ?? '');
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($role === '' || $uid === 0) {
        return false;
    }
    // Garde-fou : un admin ne peut jamais perdre l'accès à l'écran qui sert à gérer les droits
    if ($role === 'admin' && in_array($permission, ['modifier_parametres'], true)) {
        return true;
    }
    if (!isset($cache['u' . $uid])) {
        try {
            $cache['u' . $uid] = ['role' => perm_role_list($role), 'perso' => perm_user_overrides($uid)];
        } catch (Throwable $e) {
            error_log('permissions : ' . $e->getMessage());
            $cache['u' . $uid] = ['role' => [], 'perso' => []];   // en cas de souci : refus par défaut
        }
    }
    $c = $cache['u' . $uid];
    if (array_key_exists($permission, $c['perso'])) {
        return $c['perso'][$permission];
    }
    return in_array($permission, $c['role'], true);
}

/** Page « Accès refusé » (design BERTIN MOVIE, clair / sombre selon l'appareil). */
function perm_denied_page($label)
{
    $base = defined('BASE_URL') ? (parse_url(BASE_URL, PHP_URL_PATH) ?: '/') : '/film_web_dy/';
    $isAdmin = (($_SESSION['role'] ?? '') === 'admin');
    $home = $base . ($isAdmin ? 'admin/dashboard.php' : 'user/index.php');
    $h = function ($x) {
        return htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
    };
    ?>
    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Accès refusé - BERTIN MOVIE</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            :root {
                --bg: #f0f4ff;
                --card: #ffffff;
                --text: #0f172a;
                --muted: #64748b;
                --line: #e2e8f0;
                --blue: #3b82f6;
                --red: #ef4444;
                --red-soft: #fee2e2;
                --chip: #f1f5f9
            }

            @media (prefers-color-scheme:dark) {
                :root {
                    --bg: #0b1120;
                    --card: #111a2e;
                    --text: #f1f5f9;
                    --muted: #94a3b8;
                    --line: #1f2b45;
                    --red-soft: rgba(239, 68, 68, .16);
                    --chip: #18233c
                }
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box
            }

            html,
            body {
                height: 100%
            }

            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
                background: var(--bg);
                color: var(--text);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                position: relative;
                overflow-x: hidden
            }

            body::before {
                content: '';
                position: fixed;
                inset: -30%;
                background: radial-gradient(ellipse at 20% 25%, rgba(59, 130, 246, .14), transparent 55%), radial-gradient(ellipse at 80% 75%, rgba(239, 68, 68, .10), transparent 55%);
                z-index: 0
            }

            .card {
                position: relative;
                z-index: 1;
                width: 100%;
                max-width: 470px;
                background: var(--card);
                border: 1px solid var(--line);
                border-radius: 26px;
                padding: 44px 38px 34px;
                text-align: center;
                box-shadow: 0 20px 60px rgba(15, 23, 42, .12);
                animation: rise .5s ease both
            }

            .brand {
                font-size: 13px;
                font-weight: 800;
                letter-spacing: .18em;
                color: var(--muted);
                margin-bottom: 26px
            }

            .brand span {
                color: var(--blue)
            }

            .shield {
                position: relative;
                width: 96px;
                height: 96px;
                margin: 0 auto 24px;
                border-radius: 50%;
                display: grid;
                place-items: center;
                background: var(--red-soft);
                color: var(--red);
                font-size: 40px;
                animation: shake .6s .35s ease both
            }

            .shield::after {
                content: '';
                position: absolute;
                inset: -9px;
                border-radius: 50%;
                border: 2px dashed rgba(239, 68, 68, .35);
                animation: spin 22s linear infinite
            }

            h1 {
                font-size: 28px;
                font-weight: 800;
                letter-spacing: -.02em;
                margin-bottom: 10px
            }

            .lead {
                color: var(--muted);
                font-size: 15px;
                line-height: 1.65;
                margin-bottom: 20px
            }

            .perm {
                display: inline-flex;
                align-items: center;
                gap: 9px;
                background: var(--chip);
                border: 1px solid var(--line);
                border-radius: 999px;
                padding: 9px 18px;
                font-size: 14px;
                font-weight: 700;
                margin-bottom: 22px
            }

            .perm i {
                color: var(--red)
            }

            .hint {
                display: flex;
                gap: 12px;
                align-items: flex-start;
                text-align: left;
                background: var(--chip);
                border-radius: 14px;
                padding: 14px 16px;
                font-size: 13px;
                line-height: 1.6;
                color: var(--muted);
                margin-bottom: 26px
            }

            .hint i {
                color: var(--blue);
                margin-top: 3px
            }

            .actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap
            }

            .btn {
                flex: 1 1 150px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                padding: 13px 18px;
                border-radius: 13px;
                font-family: inherit;
                font-size: 15px;
                font-weight: 700;
                text-decoration: none;
                cursor: pointer;
                border: 2px solid transparent;
                transition: transform .2s, box-shadow .2s, background .2s
            }

            .btn-primary {
                color: #fff;
                background: linear-gradient(135deg, #3b82f6, #2563eb);
                box-shadow: 0 8px 22px rgba(37, 99, 235, .28)
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 30px rgba(37, 99, 235, .38)
            }

            .btn-ghost {
                color: var(--text);
                background: transparent;
                border-color: var(--line)
            }

            .btn-ghost:hover {
                border-color: var(--blue);
                color: var(--blue);
                transform: translateY(-2px)
            }

            .code {
                margin-top: 22px;
                font-size: 12px;
                color: var(--muted);
                letter-spacing: .08em
            }

            @keyframes rise {
                from {
                    opacity: 0;
                    transform: translateY(18px)
                }

                to {
                    opacity: 1;
                    transform: none
                }
            }

            @keyframes shake {

                0%,
                100% {
                    transform: translateX(0)
                }

                20% {
                    transform: translateX(-7px)
                }

                40% {
                    transform: translateX(6px)
                }

                60% {
                    transform: translateX(-4px)
                }

                80% {
                    transform: translateX(3px)
                }
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg)
                }
            }

            @media (prefers-reduced-motion:reduce) {
                * {
                    animation: none !important
                }
            }

            @media (max-width:480px) {
                .card {
                    padding: 34px 22px 26px;
                    border-radius: 22px
                }

                h1 {
                    font-size: 24px
                }

                .shield {
                    width: 82px;
                    height: 82px;
                    font-size: 34px
                }
            }
        </style>
    </head>

    <body>
        <main class="card" role="alert">
            <div class="brand">BERTIN <span>MOVIE</span></div>
            <div class="shield"><i class="fas fa-lock"></i></div>
            <h1>Accès refusé</h1>
            <p class="lead">Vous n'avez pas l'autorisation d'utiliser cette fonctionnalité avec votre compte.</p>
            <div class="perm"><i class="fas fa-ban"></i> <?= $h($label) ?></div>
            <div class="hint"><i class="fas fa-circle-info"></i><span>Ce droit est géré par un administrateur. S'il s'agit
                    d'une erreur, contactez-le pour qu'il vous le réactive.</span></div>
            <div class="actions">
                <a class="btn btn-ghost" href="javascript:history.back()"><i class="fas fa-arrow-left"></i> Retour</a>
                <a class="btn btn-primary" href="<?= $h($home) ?>"><i class="fas fa-house"></i> Accueil</a>
            </div>
            <div class="code">ERREUR 403</div>
        </main>
    </body>

    </html>
    <?php
}

/** Bloque la page (403) si le droit manque et le note dans bm_trace. */
function require_permission($permission)
{
    if (can($permission)) {
        return;
    }
    bm_trace('PERMISSION-DENIED:' . substr($permission, 0, 11), $_SESSION['user_id'] ?? null, $_SESSION['user_id'] ?? null);
    http_response_code(403);
    $ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Accès refusé : droit manquant.', 'permission' => $permission]);
    } else {
        while (ob_get_level() > 0) {
            @ob_end_clean();   // supprime un éventuel début de page déjà préparé
        }
        perm_denied_page(perm_catalog()[$permission][0] ?? $permission);
    }
    exit();
}