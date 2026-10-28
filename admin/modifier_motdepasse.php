<?php
require_once __DIR__ . '/../includes/session_boot.php';
include("../connexion.php");
require_once __DIR__ . '/../includes/auth_check.php';

if (!isset($_SESSION['role'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id_utilisateur'] ?? null;

if (!$user_id) {
    header("Location: ../index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: ../index.php");
    exit();
}

$message = '';
$message_type = '';

if (isset($_POST['update'])) {
    $ancien_motdepasse = $_POST['ancien_motdepasse'] ?? '';
    $nouveau_motdepasse = $_POST['nouveau_motdepasse'] ?? '';
    $confirmer_motdepasse = $_POST['confirmer_motdepasse'] ?? '';

    if (empty($ancien_motdepasse) || empty($nouveau_motdepasse) || empty($confirmer_motdepasse)) {
        $message = "Tous les champs sont requis";
        $message_type = "error";
    } elseif (!password_verify($ancien_motdepasse, $user['mot_de_passe'])) {
        $message = "L'ancien mot de passe est incorrect";
        $message_type = "error";
    } elseif (strlen($nouveau_motdepasse) < 6) {
        $message = "Le nouveau mot de passe doit contenir au moins 6 caractères";
        $message_type = "error";
    } elseif ($nouveau_motdepasse !== $confirmer_motdepasse) {
        $message = "Les mots de passe ne correspondent pas";
        $message_type = "error";
    } else {
        $hash = password_hash($nouveau_motdepasse, PASSWORD_DEFAULT);
        $sql = "UPDATE utilisateur SET mot_de_passe = ? WHERE id_utilisateur = ?";
        $pdo->prepare($sql)->execute([$hash, $user_id]);

        $message = "Mot de passe modifié avec succès.";
        $message_type = "success";
    }
}
$is_admin_u = ($user['role'] ?? 'user') === 'admin';
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le mot de passe - Bertin Movie</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=3">
    <link rel="stylesheet" href="/film_web_dy/admin/hx-style.css?v=4">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content hx-wrap hx-wide">

        <a href="../admin/profil.php" class="hx-back"><i class="fas fa-arrow-left"></i> Retour au profil</a>

        <div class="hx-head">
            <div class="hx-title">
                <div class="hx-title-ico hx-title-ico--amber"><i class="fas fa-key"></i></div>
                <div>
                    <h1>Mot de passe</h1>
                    <p>Choisissez un mot de passe d'au moins 6 caractères</p>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="hx-alert <?= $message_type === 'success' ? 'hx-alert--ok' : 'hx-alert--err' ?>">
                <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="hx-formgrid">
            <div class="hx-card">
                <div class="hx-card-head">
                    <h2>Changer le mot de passe</h2>
                    <p>Saisissez l'ancien, puis choisissez le nouveau.</p>
                </div>
                <form method="POST" class="hx-form" id="pwdForm" autocomplete="off">
                    <div>
                        <span class="hx-lab">Ancien mot de passe</span>
                        <label class="hx-field">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="ancien_motdepasse" required autocomplete="current-password"
                                placeholder="Votre mot de passe actuel">
                            <button type="button" class="hx-eye" aria-label="Afficher le mot de passe"><i
                                    class="fas fa-eye"></i></button>
                        </label>
                    </div>

                    <div>
                        <span class="hx-lab">Nouveau mot de passe</span>
                        <label class="hx-field">
                            <i class="fas fa-key"></i>
                            <input type="password" name="nouveau_motdepasse" id="newPwd" required minlength="6"
                                autocomplete="new-password" placeholder="Minimum 6 caractères">
                            <button type="button" class="hx-eye" aria-label="Afficher le mot de passe"><i
                                    class="fas fa-eye"></i></button>
                        </label>
                        <div class="hx-meter"><i id="meterBar"></i></div>
                        <p class="hx-hint" id="meterText">Ajoutez des majuscules, des chiffres et des symboles pour plus
                            de sécurité.</p>
                    </div>

                    <div>
                        <span class="hx-lab">Confirmer le nouveau mot de passe</span>
                        <label class="hx-field">
                            <i class="fas fa-check-double"></i>
                            <input type="password" name="confirmer_motdepasse" id="confirmPwd" required
                                autocomplete="new-password" placeholder="Répétez le nouveau mot de passe">
                            <button type="button" class="hx-eye" aria-label="Afficher le mot de passe"><i
                                    class="fas fa-eye"></i></button>
                        </label>
                        <p class="hx-hint" id="matchText"></p>
                    </div>

                    <div class="hx-form-actions">
                        <button type="submit" name="update" class="hx-btn hx-btn--amber">
                            <i class="fas fa-save"></i> Modifier le mot de passe
                        </button>
                        <a href="../admin/profil.php" class="hx-btn hx-btn--ghost">Annuler</a>
                    </div>
                </form>
            </div>

            <aside class="hx-aside">
                <?php
                $ini = strtoupper(mb_substr($user['prenom'] ?? '', 0, 1) . mb_substr($user['nom'] ?? '', 0, 1));
                if ($ini === '') {
                    $ini = strtoupper(mb_substr($user['nom_utilisateur'], 0, 2));
                }
                ?>
                <div class="hx-aside-user">
                    <div class="hx-aside-av">
                        <?= htmlspecialchars($ini) ?>
                    </div>
                    <div>
                        <b>
                            <?= htmlspecialchars($user['nom_utilisateur']) ?>
                        </b>
                        <small>
                            <?= $is_admin_u ? 'Administrateur' : 'Utilisateur' ?>
                        </small>
                    </div>
                </div>
                <h3>Un bon mot de passe</h3>
                <ul>
                    <li><i class="fas fa-ruler-horizontal"></i><span>Au moins 6 caractères, idéalement 10 ou
                            plus.</span></li>
                    <li><i class="fas fa-font"></i><span>Mélangez majuscules, minuscules, chiffres et symboles.</span>
                    </li>
                    <li><i class="fas fa-ban"></i><span>Évitez votre nom, votre date de naissance ou un mot de passe
                            déjà utilisé ailleurs.</span></li>
                </ul>
            </aside>
        </div>

    </div>

    <script>
        (function () {
            // Afficher / masquer le mot de passe
            document.querySelectorAll('.hx-eye').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var input = btn.parentNode.querySelector('input');
                    var show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
                    btn.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
                });
            });

            var pwd = document.getElementById('newPwd');
            var conf = document.getElementById('confirmPwd');
            var bar = document.getElementById('meterBar');
            var text = document.getElementById('meterText');
            var match = document.getElementById('matchText');

            // Force du mot de passe (indicatif)
            pwd.addEventListener('input', function () {
                var v = pwd.value, score = 0;
                if (v.length >= 6) score++;
                if (v.length >= 10) score++;
                if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
                if (/\d/.test(v)) score++;
                if (/[^A-Za-z0-9]/.test(v)) score++;

                var levels = [
                    { w: '0%', c: 'transparent', t: 'Ajoutez des majuscules, des chiffres et des symboles pour plus de sécurité.' },
                    { w: '25%', c: '#ef4444', t: 'Faible' },
                    { w: '50%', c: '#f59e0b', t: 'Moyen' },
                    { w: '75%', c: '#3b82f6', t: 'Bon' },
                    { w: '100%', c: '#22c55e', t: 'Excellent' }
                ];
                var lvl = v.length === 0 ? 0 : Math.min(4, Math.max(1, score - 1));
                bar.style.width = levels[lvl].w;
                bar.style.background = levels[lvl].c;
                text.textContent = levels[lvl].t;
                checkMatch();
            });

            // Correspondance des deux champs
            function checkMatch() {
                if (!conf.value) { match.textContent = ''; match.className = 'hx-hint'; return; }
                var ok = conf.value === pwd.value;
                match.textContent = ok ? 'Les mots de passe correspondent.' : 'Les mots de passe ne correspondent pas.';
                match.className = 'hx-hint ' + (ok ? 'ok' : 'bad');
            }
            conf.addEventListener('input', checkMatch);
        })();
    </script>

</body>

</html>