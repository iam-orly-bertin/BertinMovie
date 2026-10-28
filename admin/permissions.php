<?php
// admin/permissions.php : interface de gestion des droits (critère 3)
//   Onglet 1 : droits de chaque rôle (admin / utilisateur)  = « pour tous les utilisateurs »
//   Onglet 2 : droits d'UN utilisateur précis (exceptions : autoriser / interdire / selon le rôle)
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/update_session.php';
require_once __DIR__ . '/../includes/notification.php';
require_once __DIR__ . '/../includes/permissions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
require_permission('modifier_parametres');

$uid = (int) $_SESSION['user_id'];
updateUserSession($pdo, $uid);
perm_ensure_table($pdo);

if (empty($_SESSION['bm_csrf'])) {
    $_SESSION['bm_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['bm_csrf'];

function pr_h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function pr_flash($type, $msg, $url)
{
    $_SESSION['pr_flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: ' . $url);
    exit();
}

function pr_log($pdo, $uid, $details)
{
    try {
        if (function_exists('addHistoriqueAction')) {
            addHistoriqueAction($pdo, $uid, 'modification', 'permissions', null, 'Droits d\'accès', $details);
        }
    } catch (Throwable $e) {
    }
}

$CAT = perm_catalog();
$roles = ['admin' => 'Administrateur', 'user' => 'Utilisateur'];

/* =========================================================
   ACTIONS
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        pr_flash('err', 'Le formulaire a expiré. Réessayez.', 'permissions.php');
    }
    $act = (string) ($_POST['action'] ?? '');

    /* ----- droits des rôles ----- */
    if ($act === 'save_roles') {
        $posted = $_POST['r'] ?? [];
        try {
            $pdo->beginTransaction();
            foreach (array_keys($roles) as $role) {
                $pdo->prepare("DELETE FROM permissions WHERE role = ?")->execute([$role]);
                $ins = $pdo->prepare("INSERT INTO permissions (role, permission) VALUES (?, ?)");
                foreach ($CAT as $code => $c) {
                    if (!in_array($role, $c[3], true)) {
                        continue;
                    }
                    $on = !empty($posted[$role][$code]);
                    if ($role === 'admin' && $code === 'modifier_parametres') {
                        $on = true;   // garde-fou anti-verrouillage
                    }
                    if ($on) {
                        $ins->execute([$role, $code]);
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('permissions save_roles : ' . $e->getMessage());
            pr_flash('err', 'Enregistrement impossible.', 'permissions.php?tab=roles');
        }
        pr_log($pdo, $uid, 'Droits des rôles modifiés');
        pr_flash('ok', 'Droits des rôles enregistrés.', 'permissions.php?tab=roles');
    }

    /* ----- droits d'un utilisateur ----- */
    if ($act === 'save_user' || $act === 'reset_user') {
        $target = (int) ($_POST['id_utilisateur'] ?? 0);
        $st = $pdo->prepare("SELECT id_utilisateur, nom_utilisateur FROM utilisateur WHERE id_utilisateur = ? AND role <> 'admin'");
        $st->execute([$target]);
        $tu = $st->fetch();
        if (!$tu) {
            pr_flash('err', 'Utilisateur introuvable.', 'permissions.php?tab=utilisateur');
        }
        $back = 'permissions.php?tab=utilisateur&u=' . $target;
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM permissions_utilisateur WHERE id_utilisateur = ?")->execute([$target]);
            if ($act === 'save_user') {
                $ins = $pdo->prepare("INSERT INTO permissions_utilisateur (id_utilisateur, permission, autorise) VALUES (?, ?, ?)");
                foreach ($CAT as $code => $c) {
                    if (!in_array('user', $c[3], true)) {
                        continue;
                    }
                    $v = $_POST['p'][$code] ?? 'inherit';
                    if ($v === 'allow' || $v === 'deny') {
                        $ins->execute([$target, $code, $v === 'allow' ? 1 : 0]);
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('permissions save_user : ' . $e->getMessage());
            pr_flash('err', 'Enregistrement impossible.', $back);
        }
        pr_log($pdo, $uid, ($act === 'save_user' ? 'Droits modifiés pour ' : 'Droits réinitialisés pour ') . $tu['nom_utilisateur']);
        pr_flash('ok', $act === 'save_user' ? 'Droits de « ' . $tu['nom_utilisateur'] . ' » enregistrés.' : 'Droits de « ' . $tu['nom_utilisateur'] . ' » réinitialisés (selon son rôle).', $back);
    }
    pr_flash('err', 'Action inconnue.', 'permissions.php');
}

/* =========================================================
   DONNÉES D'AFFICHAGE
========================================================= */
$flash = $_SESSION['pr_flash'] ?? null;
unset($_SESSION['pr_flash']);

$tab = ($_GET['tab'] ?? 'roles') === 'utilisateur' ? 'utilisateur' : 'roles';

$roleRights = ['admin' => perm_role_list('admin'), 'user' => perm_role_list('user')];

$users = $pdo->query("SELECT id_utilisateur, nom_utilisateur, prenom, nom, email FROM utilisateur WHERE role <> 'admin' ORDER BY nom_utilisateur")->fetchAll();

$selId = (int) ($_GET['u'] ?? 0);
$sel = null;
foreach ($users as $x) {
    if ((int) $x['id_utilisateur'] === $selId) {
        $sel = $x;
    }
}
$over = $sel ? perm_user_overrides($selId) : [];

// Utilisateurs ayant des exceptions
$withOver = $pdo->query("SELECT u.id_utilisateur, u.nom_utilisateur,
                                SUM(p.autorise = 0) AS nb_deny, SUM(p.autorise = 1) AS nb_allow
                         FROM permissions_utilisateur p JOIN utilisateur u ON u.id_utilisateur = p.id_utilisateur
                         GROUP BY u.id_utilisateur, u.nom_utilisateur ORDER BY u.nom_utilisateur")->fetchAll();

$groups = [];
foreach ($CAT as $code => $c) {
    $groups[$c[2]][$code] = $c;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Droits d'accès - Bertin Movie</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=3">
    <link rel="stylesheet" href="/film_web_dy/admin/hx-style.css?v=4">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hx-tab { text-decoration: none; padding-right: 14px; }
        .pe-card { margin-bottom: 18px; padding: 22px; background: var(--hx-bg); border: 1px solid var(--hx-line); border-radius: 20px; box-shadow: var(--hx-sh); min-width: 0; }
        .pe-card h3 { display: flex; align-items: center; gap: 12px; margin: 0 0 6px; font-size: 16px; font-weight: 800; color: var(--hx-text); }
        .pe-card h3 i { width: 36px; height: 36px; display: grid; place-items: center; border-radius: 11px; background: #dbeafe; color: #3b82f6; font-size: 14px; }
        body.dark-mode .pe-card h3 i { background: #1e3a8a; color: #93bbfc; }
        .pe-sub { margin: 0 0 16px; font-size: 13px; color: var(--hx-muted); line-height: 1.6; }
        .pe-wrap { overflow-x: auto; border: 1px solid var(--hx-line); border-radius: 14px; }
        .pe-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
        .pe-table th { padding: 13px 18px; background: var(--hx-bg-2); border-bottom: 1px solid var(--hx-line); font-size: 11.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; text-align: left; color: var(--hx-muted); white-space: nowrap; }
        .pe-table td { padding: 12px 18px; border-bottom: 1px solid var(--hx-line); color: var(--hx-text); background: var(--hx-bg); vertical-align: middle; }
        .pe-table tr:last-child td { border-bottom: 0; }
        .pe-table td.c, .pe-table th.c { text-align: center; }
        .pe-grp td { background: var(--hx-bg-2) !important; font-size: 11.5px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--hx-muted); padding: 9px 18px; }
        .pe-lab { display: flex; align-items: center; gap: 12px; font-weight: 600; }
        .pe-lab i { width: 18px; text-align: center; color: var(--hx-blue); }
        .pe-na { color: var(--hx-muted-2, #94a3b8); }

        /* interrupteur */
        .pe-sw { position: relative; display: inline-block; width: 44px; height: 24px; }
        .pe-sw input { position: absolute; inset: 0; opacity: 0; cursor: pointer; margin: 0; z-index: 2; }
        .pe-sw span { position: absolute; inset: 0; border-radius: 12px; background: #cbd5e1; transition: background .2s; }
        .pe-sw span::after { content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; transition: transform .2s; box-shadow: 0 1px 3px rgba(0,0,0,.3); }
        .pe-sw input:checked + span { background: #22c55e; }
        .pe-sw input:checked + span::after { transform: translateX(20px); }
        .pe-sw input:disabled { cursor: not-allowed; }
        .pe-sw input:disabled + span { opacity: .55; }

        /* choix 3 états */
        .pe-seg { display: inline-flex; gap: 4px; padding: 4px; background: var(--hx-bg-2); border: 1px solid var(--hx-line); border-radius: 12px; }
        .pe-seg label { position: relative; margin: 0; }
        .pe-seg input { position: absolute; opacity: 0; inset: 0; cursor: pointer; margin: 0; }
        .pe-seg span { display: block; padding: 7px 14px; border-radius: 9px; font-size: 12.5px; font-weight: 700; color: var(--hx-muted); white-space: nowrap; }
        .pe-seg input:checked + span.i { background: var(--hx-bg); color: var(--hx-text); box-shadow: 0 1px 4px rgba(0,0,0,.15); }
        .pe-seg input:checked + span.a { background: #22c55e; color: #fff; }
        .pe-seg input:checked + span.d { background: #ef4444; color: #fff; }

        .pe-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 11px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .pe-chip.on { background: #dcfce7; color: #16a34a; }
        .pe-chip.off { background: #fee2e2; color: #dc2626; }
        body.dark-mode .pe-chip.on { background: rgba(34,197,94,.18); color: #86efac; }
        body.dark-mode .pe-chip.off { background: rgba(239,68,68,.18); color: #fca5a5; }

        .pe-bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; margin-bottom: 18px; }
        .pe-bar .grow { flex: 1 1 260px; min-width: 0; }
        .pe-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; align-items: center; }
        .pe-quick { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
        .pe-quick button { cursor: pointer; border: 1px solid var(--hx-line); background: var(--hx-bg-2); color: var(--hx-text); border-radius: 10px; padding: 7px 12px; font-size: 12.5px; font-weight: 600; }
        .pe-quick button:hover { border-color: var(--hx-blue); }
        .pe-links a { color: var(--hx-blue); font-weight: 600; text-decoration: none; margin-right: 6px; }
        @media (max-width: 640px) {
            .pe-card { padding: 16px; border-radius: 16px; }
            .pe-table th, .pe-table td { padding: 10px 12px; }
            .pe-seg span { padding: 7px 9px; font-size: 12px; }
        }
    </style>
</head>

<body>
    <?php include(__DIR__ . "/../includes/menu.php"); ?>

    <div class="content hx-wrap">
        <div class="hx-head">
            <div class="hx-title">
                <div class="hx-title-ico"><i class="fas fa-user-lock"></i></div>
                <div>
                    <h1>Droits d'accès</h1>
                    <p>Ce que chaque rôle ou chaque utilisateur peut faire</p>
                </div>
            </div>
            <div class="hx-actions">
                <a class="hx-btn hx-btn--ghost" href="parametres.php"><i class="fas fa-arrow-left"></i> Paramètres</a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="hx-alert <?= $flash['type'] === 'ok' ? 'hx-alert--ok' : 'hx-alert--err' ?>">
                <i class="fas <?= $flash['type'] === 'ok' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <div><?= pr_h($flash['msg']) ?></div>
            </div>
        <?php endif; ?>

        <div class="hx-tabs">
            <a class="hx-tab <?= $tab === 'roles' ? 'active' : '' ?>" href="?tab=roles"><i class="fas fa-users-gear"></i> Tous les utilisateurs (par rôle)</a>
            <a class="hx-tab <?= $tab === 'utilisateur' ? 'active' : '' ?>" href="?tab=utilisateur" style="--c:#8b5cf6;"><i class="fas fa-user-gear"></i> Un utilisateur précis <b><?= count($withOver) ?></b></a>
        </div>

        <?php if ($tab === 'roles'): ?>
            <form method="post" class="pe-card">
                <input type="hidden" name="csrf" value="<?= pr_h($csrf) ?>">
                <input type="hidden" name="action" value="save_roles">
                <h3><i class="fas fa-users-gear"></i> Droits par rôle</h3>
                <p class="pe-sub">Ces droits s'appliquent à <strong>tous</strong> les comptes du rôle. Exemple : décochez « Commenter » pour la colonne Utilisateur et plus personne ne pourra commenter. Les exceptions individuelles se règlent dans l'autre onglet.</p>
                <div class="pe-wrap">
                    <table class="pe-table">
                        <thead>
                            <tr>
                                <th>Droit</th>
                                <?php foreach ($roles as $r => $lab): ?>
                                    <th class="c"><?= pr_h($lab) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $g => $items): ?>
                                <tr class="pe-grp"><td colspan="3"><?= pr_h($g) ?></td></tr>
                                <?php foreach ($items as $code => $c): ?>
                                    <tr>
                                        <td><div class="pe-lab"><i class="fas <?= pr_h($c[1]) ?>"></i><?= pr_h($c[0]) ?></div></td>
                                        <?php foreach ($roles as $r => $lab): ?>
                                            <td class="c">
                                                <?php if (in_array($r, $c[3], true)): ?>
                                                    <?php $lock = ($r === 'admin' && $code === 'modifier_parametres'); ?>
                                                    <label class="pe-sw" <?= $lock ? 'title="Toujours actif pour l\'administrateur"' : '' ?>>
                                                        <input type="checkbox" name="r[<?= $r ?>][<?= pr_h($code) ?>]" value="1"
                                                            <?= ($lock || in_array($code, $roleRights[$r], true)) ? 'checked' : '' ?> <?= $lock ? 'disabled' : '' ?>>
                                                        <span></span>
                                                    </label>
                                                <?php else: ?>
                                                    <span class="pe-na">—</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pe-actions">
                    <button class="hx-btn hx-btn--primary"><i class="fas fa-floppy-disk"></i> Enregistrer</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($tab === 'utilisateur'): ?>
            <div class="pe-card">
                <h3><i class="fas fa-user-gear"></i> Droits d'un utilisateur</h3>
                <p class="pe-sub">Choisissez un utilisateur puis, pour chaque droit : <strong>Selon le rôle</strong> (aucune exception), <strong>Autoriser</strong> ou <strong>Interdire</strong>. Un changement est pris en compte dès la prochaine page qu'il ouvre.</p>

                <form method="get" class="pe-bar">
                    <input type="hidden" name="tab" value="utilisateur">
                    <div class="grow">
                        <span class="hx-lab">Utilisateur</span>
                        <label class="hx-field"><i class="fas fa-user"></i>
                            <select name="u" onchange="this.form.submit()">
                                <option value="0">— Choisir un utilisateur —</option>
                                <?php foreach ($users as $x): ?>
                                    <option value="<?= (int) $x['id_utilisateur'] ?>" <?= $selId === (int) $x['id_utilisateur'] ? 'selected' : '' ?>>
                                        <?= pr_h($x['nom_utilisateur'] . ' — ' . trim($x['prenom'] . ' ' . $x['nom']) . ' (' . $x['email'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </form>

                <?php if ($sel): ?>
                    <form method="post" id="userForm">
                        <input type="hidden" name="csrf" value="<?= pr_h($csrf) ?>">
                        <input type="hidden" name="id_utilisateur" value="<?= $selId ?>">
                        <div class="pe-quick">
                            <button type="button" data-all="deny"><i class="fas fa-ban"></i> Tout interdire</button>
                            <button type="button" data-all="allow"><i class="fas fa-check"></i> Tout autoriser</button>
                            <button type="button" data-all="inherit"><i class="fas fa-rotate-left"></i> Tout « selon le rôle »</button>
                        </div>
                        <div class="pe-wrap">
                            <table class="pe-table">
                                <thead>
                                    <tr><th>Droit</th><th class="c">Rôle</th><th class="c">Exception pour <?= pr_h($sel['nom_utilisateur']) ?></th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($CAT as $code => $c):
                                        if (!in_array('user', $c[3], true)) {
                                            continue;
                                        }
                                        $roleOn = in_array($code, $roleRights['user'], true);
                                        $cur = array_key_exists($code, $over) ? ($over[$code] ? 'allow' : 'deny') : 'inherit'; ?>
                                        <tr>
                                            <td><div class="pe-lab"><i class="fas <?= pr_h($c[1]) ?>"></i><?= pr_h($c[0]) ?></div></td>
                                            <td class="c"><span class="pe-chip <?= $roleOn ? 'on' : 'off' ?>"><?= $roleOn ? 'Oui' : 'Non' ?></span></td>
                                            <td class="c">
                                                <div class="pe-seg">
                                                    <label><input type="radio" name="p[<?= pr_h($code) ?>]" value="inherit" <?= $cur === 'inherit' ? 'checked' : '' ?>><span class="i">Selon le rôle</span></label>
                                                    <label><input type="radio" name="p[<?= pr_h($code) ?>]" value="allow" <?= $cur === 'allow' ? 'checked' : '' ?>><span class="a">Autoriser</span></label>
                                                    <label><input type="radio" name="p[<?= pr_h($code) ?>]" value="deny" <?= $cur === 'deny' ? 'checked' : '' ?>><span class="d">Interdire</span></label>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="pe-actions">
                            <button class="hx-btn hx-btn--primary" name="action" value="save_user"><i class="fas fa-floppy-disk"></i> Enregistrer</button>
                            <button class="hx-btn hx-btn--ghost" name="action" value="reset_user" onclick="return confirm('Supprimer toutes les exceptions de cet utilisateur ?')"><i class="fas fa-rotate-left"></i> Réinitialiser</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="hx-empty">
                        <div class="hx-empty-ico"><i class="fas fa-user-gear"></i></div>
                        <h3>Aucun utilisateur sélectionné</h3>
                        <p>Choisissez un utilisateur dans la liste ci-dessus.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($withOver): ?>
                <div class="pe-card">
                    <h3><i class="fas fa-list-check"></i> Utilisateurs avec des exceptions</h3>
                    <p class="pe-sub pe-links">
                        <?php foreach ($withOver as $w): ?>
                            <a href="?tab=utilisateur&u=<?= (int) $w['id_utilisateur'] ?>"><?= pr_h($w['nom_utilisateur']) ?></a>
                            <span class="pe-chip off"><?= (int) $w['nb_deny'] ?> interdit(s)</span>
                            <span class="pe-chip on"><?= (int) $w['nb_allow'] ?> autorisé(s)</span>&nbsp;&nbsp;
                        <?php endforeach; ?>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        (function () {
            var f = document.getElementById('userForm');
            if (!f) { return; }
            f.querySelectorAll('.pe-quick button').forEach(function (b) {
                b.addEventListener('click', function () {
                    var v = b.getAttribute('data-all');
                    f.querySelectorAll('input[type=radio][value=' + v + ']').forEach(function (r) { r.checked = true; });
                });
            });
        })();
    </script>
</body>

</html>
