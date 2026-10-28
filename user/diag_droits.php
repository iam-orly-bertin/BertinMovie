<?php
/**
 * installer_droits.php : branche le système de droits dans VOS fichiers actuels (sans écraser votre travail).
 *
 *   1) Placez ce fichier à la racine du site (à côté de login.php), avec includes/permissions.php et admin/permissions.php
 *   2) Ouvrez  http://localhost/film_web_dy/installer_droits.php   (ou : php installer_droits.php)
 *   3) Lisez le rapport, testez le site, puis SUPPRIMEZ ce fichier.
 *
 * Il ajoute seulement quelques lignes (une copie de sauvegarde « .bak_droits » est créée à côté de chaque fichier modifié).
 * Relancer le script est sans danger : ce qui est déjà en place est ignoré.
 */
if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Réservé à une exécution en local.');
}
$root = __DIR__;
$report = [];
$report[] = ['DOSSIER', $root, 'les fichiers de CE dossier sont modifiés : c\'est bien celui de votre site ?'];

/** Après la ligne « require_once ... auth_check.php » : charge permissions.php et exige le droit. */
function after_auth($file, $perm)
{
    return [
        'file' => $file,
        'marker' => "require_permission('$perm')",
        'desc' => "droit « $perm »",
        'regex' => '~require_once\s+__DIR__\s*\.\s*[\'"]/\.\./includes/auth_check\.php[\'"]\s*;~',
        'with' => function ($m) use ($perm) {
            return $m[0] . "\nrequire_once __DIR__ . '/../includes/permissions.php';\nrequire_permission('$perm');";
        },
    ];
}

function plain($file, $marker, $desc, $from, $to)
{
    return ['file' => $file, 'marker' => $marker, 'desc' => $desc, 'plain' => [$from, $to]];
}

$patches = [
    // ----- Administration -----
    after_auth('admin/rapports.php', 'voir_rapports'),
    after_auth('admin/utilisateurs.php', 'gerer_utilisateurs'),
    after_auth('admin/parametres.php', 'modifier_parametres'),
    after_auth('admin/commentaires.php', 'gerer_commentaires'),
    after_auth('admin/corbeille.php', 'gerer_corbeille'),
    after_auth('admin/historique_actions.php', 'voir_historique'),
    [
        'file' => 'admin/parametres.php',
        'marker' => 'href="permissions.php"',
        'desc' => "lien « Droits d'accès » dans les onglets des Paramètres",
        'regex' => '~(<i class="fas fa-code-branch"></i> Versions <b><\?= count\(\$filmsV\) \?></b>\s*</button>)~',
        'with' => function ($m) {
            return $m[1] . "\n            <a class=\"hx-tab\" href=\"permissions.php\" style=\"--c:#ef4444;text-decoration:none;\" onclick=\"event.stopPropagation()\">\n                <i class=\"fas fa-user-lock\"></i> Droits d'accès\n            </a>";
        },
    ],
    // ----- Côté utilisateur -----
    after_auth('user/watch.php', 'voir_films'),
    after_auth('user/modifier_profil.php', 'modifier_profil'),
    plain(
        'user/watch.php',
        "&& can('aimer')",
        'droit « aimer »',
        "if (isset(\$_POST['toggle_like'])) {",
        "if (isset(\$_POST['toggle_like']) && can('aimer')) {"
    ),
    plain(
        'user/watch.php',
        "&& can('commenter')) {",
        'droit « commenter » (enregistrement)',
        "if (isset(\$_POST['ajouter_feedback']) && !empty(\$_POST['commentaire'])) {",
        "if (isset(\$_POST['ajouter_feedback']) && !empty(\$_POST['commentaire']) && can('commenter')) {"
    ),
    plain(
        'user/watch.php',
        'commentaires sont désactivés',
        'droit « commenter » (message + formulaire masqué)',
        '<form method="POST" class="watch-fb-form">',
        "<?php if (!can('commenter')): ?>\n                    <div class=\"fb-error\"><i class=\"fas fa-ban\"></i> Les commentaires sont désactivés pour votre compte.</div>\n                <?php endif; ?>\n                <form method=\"POST\" class=\"watch-fb-form\"<?= can('commenter') ? '' : ' style=\"display:none\"' ?>>"
    ),
    plain(
        'user/ajax_favori.php',
        "can('gerer_favoris')",
        'droit « gérer ses favoris »',
        "\$user_id = \$_SESSION['user_id'];",
        "\$user_id = \$_SESSION['user_id'];\n\nrequire_once __DIR__ . '/../includes/permissions.php';\nif (!can('gerer_favoris')) {\n    echo json_encode(['success' => false, 'message' => \"Action non autorisée sur votre compte\"]);\n    exit;\n}"
    ),
    plain(
        'user/support_api.php',
        "can('support_ecrire')",
        'droit « écrire au support »',
        "        support_json(['message' => support_do_send(\$pdo, \$uid, 'user', null, 8)]);",
        "        require_once __DIR__ . '/../includes/permissions.php';\n        if (!can('support_ecrire')) {\n            support_json(['error' => \"L'envoi de messages est désactivé pour votre compte.\"], 403);\n        }\n        support_json(['message' => support_do_send(\$pdo, \$uid, 'user', null, 8)]);"
    ),
];

// Prérequis
foreach (['includes/permissions.php', 'admin/permissions.php'] as $need) {
    if (!is_file("$root/$need")) {
        $report[] = ['ERREUR', $need, 'fichier manquant : copiez-le d\'abord'];
    }
}

// On regroupe par fichier pour ne lire / écrire qu'une fois
$byFile = [];
foreach ($patches as $p) {
    $byFile[$p['file']][] = $p;
}

foreach ($byFile as $file => $list) {
    $path = "$root/$file";
    if (!is_file($path)) {
        foreach ($list as $p) {
            $report[] = ['IGNORÉ', $file, $p['desc'] . ' : fichier introuvable'];
        }
        continue;
    }
    $src = file_get_contents($path);
    $new = $src;
    $added = [];   // [index du rapport, marqueur] des ajouts de ce fichier
    foreach ($list as $p) {
        if (strpos($new, $p['marker']) !== false) {
            $report[] = ['DÉJÀ FAIT', $file, $p['desc']];
            continue;
        }
        if (isset($p['regex'])) {
            $n = preg_match_all($p['regex'], $new);
            if ($n !== 1) {
                $report[] = ['À FAIRE À LA MAIN', $file, $p['desc'] . ' : repère trouvé ' . (int) $n . ' fois (attendu : 1)'];
                continue;
            }
            $new = preg_replace_callback($p['regex'], $p['with'], $new, 1);
        } else {
            [$from, $to] = $p['plain'];
            if (substr_count($new, $from) !== 1) {
                $report[] = ['À FAIRE À LA MAIN', $file, $p['desc'] . ' : repère trouvé ' . substr_count($new, $from) . ' fois (attendu : 1)'];
                continue;
            }
            $new = str_replace($from, $to, $new);
        }
        $report[] = ['AJOUTÉ', $file, $p['desc']];
        $added[] = [count($report) - 1, $p['marker']];
    }
    if ($new !== $src) {
        if (!is_file($path . '.bak_droits')) {
            @copy($path, $path . '.bak_droits');
        }
        $w = @file_put_contents($path, $new);
        clearstatcache();
        $relu = is_file($path) ? (string) file_get_contents($path) : '';
        foreach ($added as [$idx, $marker]) {
            if ($w === false || strpos($relu, $marker) === false) {
                $report[$idx][0] = 'ÉCHEC ÉCRITURE';
                $report[$idx][2] .= ' : le fichier n\'a PAS pu être modifié (' . (is_writable($path) ? 'écriture refusée' : 'fichier en lecture seule / verrouillé') . ')';
            }
        }
    }
}

// ----- Affichage -----
if (PHP_SAPI === 'cli') {
    foreach ($report as $r) {
        echo str_pad($r[0], 18) . str_pad($r[1], 32) . $r[2] . "\n";
    }
    exit;
}
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><meta charset="utf-8"><title>Installation des droits</title><body style="font-family:Arial;max-width:900px;margin:30px auto;padding:0 16px">';
echo '<h2>Installation du système de droits</h2><table cellpadding="8" style="border-collapse:collapse;width:100%">';
$col = ['AJOUTÉ' => '#16a34a', 'DÉJÀ FAIT' => '#64748b', 'IGNORÉ' => '#d97706', 'ERREUR' => '#dc2626', 'ÉCHEC ÉCRITURE' => '#dc2626', 'DOSSIER' => '#2563eb', 'À FAIRE À LA MAIN' => '#dc2626'];
foreach ($report as $r) {
    echo '<tr style="border-bottom:1px solid #e2e8f0"><td style="color:' . ($col[$r[0]] ?? '#000') . ';font-weight:700;white-space:nowrap">' . htmlspecialchars($r[0]) . '</td><td><code>' . htmlspecialchars($r[1]) . '</code></td><td>' . htmlspecialchars($r[2]) . '</td></tr>';
}
echo '</table><p><b>Étape suivante :</b> ouvrez <a href="admin/permissions.php">admin/permissions.php</a> (ou Paramètres → Droits d\'accès), puis <b>supprimez installer_droits.php</b>.</p></body>';