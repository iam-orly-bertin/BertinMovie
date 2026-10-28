<?php
/**
 * admin/documents.php : recherche documentaire (critère 24)
 * Liste tous les fichiers échangés dans le chat support (support_messages) avec recherche, filtres et tri.
 * Rien à créer en base : on lit les colonnes fichier_nom / fichier_mime / fichier_taille déjà présentes.
 */
require_once __DIR__ . '/../includes/session_boot.php';
include("../connexion.php");
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notification.php';
require_once __DIR__ . '/../includes/support_lib.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit();
}
support_ensure_table($pdo);

/* ---------- Types de fichiers (clé => [libellé, extensions]) ---------- */
$TYPES = [
    'image' => ['Images', ['jpg', 'jpeg', 'png', 'gif', 'webp']],
    'pdf' => ['PDF', ['pdf']],
    'document' => ['Documents', ['doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx']],
    'video' => ['Vidéos', ['mp4']],
    'audio' => ['Audio', ['mp3']],
    'archive' => ['Archives', ['zip']],
];
$TRIS = [
    'date_desc' => ['Plus récents', 'm.date_envoi DESC, m.id DESC'],
    'date_asc' => ['Plus anciens', 'm.date_envoi ASC, m.id ASC'],
    'taille_desc' => ['Plus lourds', 'm.fichier_taille DESC, m.id DESC'],
    'taille_asc' => ['Plus légers', 'm.fichier_taille ASC, m.id DESC'],
    'nom_asc' => ['Nom A → Z', 'm.fichier_nom ASC, m.id DESC'],
    'nom_desc' => ['Nom Z → A', 'm.fichier_nom DESC, m.id DESC'],
];

function dc_e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function dc_ext($nom)
{
    return strtolower(pathinfo((string) $nom, PATHINFO_EXTENSION));
}
function dc_type_of($nom, $TYPES)
{
    $e = dc_ext($nom);
    foreach ($TYPES as $k => $t) {
        if (in_array($e, $t[1], true)) {
            return $k;
        }
    }
    return 'autre';
}
function dc_icon($nom)
{
    $e = dc_ext($nom);
    $map = [
        'jpg' => ['fa-file-image', '#22c55e'],
        'jpeg' => ['fa-file-image', '#22c55e'],
        'png' => ['fa-file-image', '#22c55e'],
        'gif' => ['fa-file-image', '#22c55e'],
        'webp' => ['fa-file-image', '#22c55e'],
        'pdf' => ['fa-file-pdf', '#ef4444'],
        'doc' => ['fa-file-word', '#2563eb'],
        'docx' => ['fa-file-word', '#2563eb'],
        'xls' => ['fa-file-excel', '#16a34a'],
        'xlsx' => ['fa-file-excel', '#16a34a'],
        'ppt' => ['fa-file-powerpoint', '#ea580c'],
        'pptx' => ['fa-file-powerpoint', '#ea580c'],
        'txt' => ['fa-file-lines', '#64748b'],
        'mp4' => ['fa-file-video', '#8b5cf6'],
        'mp3' => ['fa-file-audio', '#f59e0b'],
        'zip' => ['fa-file-zipper', '#64748b'],
    ];
    return $map[$e] ?? ['fa-file', '#64748b'];
}
function dc_size($b)
{
    $b = (float) $b;
    if ($b >= 1048576) {
        return number_format($b / 1048576, 1, ',', ' ') . ' Mo';
    }
    if ($b >= 1024) {
        return number_format($b / 1024, 0, ',', ' ') . ' Ko';
    }
    return (int) $b . ' o';
}
/** Surligne la recherche dans le nom (échappe d'abord, donc sans risque XSS). */
function dc_hl($nom, $q)
{
    if ($q === '') {
        return dc_e($nom);
    }
    $parts = preg_split('/(' . preg_quote($q, '/') . ')/iu', (string) $nom, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return dc_e($nom);
    }
    $out = '';
    foreach ($parts as $i => $p) {
        $out .= ($i % 2 === 1) ? '<mark>' . dc_e($p) . '</mark>' : dc_e($p);
    }
    return $out;
}

/* ---------- Paramètres (tout est validé) ---------- */
function dc_get($k)
{
    return (isset($_GET[$k]) && is_string($_GET[$k])) ? $_GET[$k] : '';   // ignore ?q[]=... et autres tableaux
}
$q = mb_substr(trim(dc_get('q')), 0, 100);
$type = isset($TYPES[dc_get('type')]) ? dc_get('type') : '';
$uid = (int) dc_get('user');
$sens = in_array(dc_get('sens'), ['user', 'admin'], true) ? dc_get('sens') : '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', dc_get('from')) ? dc_get('from') : '';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', dc_get('to')) ? dc_get('to') : '';
$tri = isset($TRIS[dc_get('tri')]) ? dc_get('tri') : 'date_desc';
$page = max(1, (int) dc_get('p'));
$perPage = 15;

$where = ["m.fichier_path IS NOT NULL", "m.fichier_path <> ''"];
$params = [];
if ($q !== '') {
    $where[] = "m.fichier_nom LIKE ?";
    $params[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
}
if ($type !== '') {
    $or = [];
    foreach ($TYPES[$type][1] as $e) {
        $or[] = "LOWER(m.fichier_nom) LIKE ?";
        $params[] = '%.' . $e;
    }
    $where[] = '(' . implode(' OR ', $or) . ')';
}
if ($uid > 0) {
    $where[] = "m.id_user = ?";
    $params[] = $uid;
}
if ($sens !== '') {
    $where[] = "m.sender = ?";
    $params[] = $sens;
}
if ($from !== '') {
    $where[] = "m.date_envoi >= ?";
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = "m.date_envoi < DATE_ADD(?, INTERVAL 1 DAY)";
    $params[] = $to . ' 00:00:00';
}
$W = implode(' AND ', $where);

$st = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(m.fichier_taille), 0) AS taille FROM support_messages m WHERE $W");
$st->execute($params);
$agg = $st->fetch();
$total = (int) $agg['n'];
$taille = (int) $agg['taille'];
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$st = $pdo->prepare("SELECT m.id, m.id_user, m.sender, m.fichier_path, m.fichier_nom, m.fichier_mime, m.fichier_taille, m.date_envoi,
                            u.nom_utilisateur, u.prenom, u.nom
                     FROM support_messages m
                     LEFT JOIN utilisateur u ON u.id_utilisateur = m.id_user
                     WHERE $W
                     ORDER BY " . $TRIS[$tri][1] . "
                     LIMIT " . (int) $perPage . " OFFSET " . (int) $offset);
$st->execute($params);
$rows = $st->fetchAll();

$all = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(fichier_taille), 0) AS taille, MAX(date_envoi) AS dernier
                    FROM support_messages WHERE fichier_path IS NOT NULL AND fichier_path <> ''")->fetch();
$users = $pdo->query("SELECT DISTINCT u.id_utilisateur AS id, u.nom_utilisateur, u.prenom, u.nom
                      FROM support_messages m JOIN utilisateur u ON u.id_utilisateur = m.id_user
                      WHERE m.fichier_path IS NOT NULL AND m.fichier_path <> ''
                      ORDER BY u.nom_utilisateur")->fetchAll();

$filtresActifs = count(array_filter([$q, $type, $uid ?: '', $sens, $from, $to], function ($v) {
    return $v !== '' && $v !== 0;
}));
function dc_url($over = [])
{
    $keep = array_filter(array_merge($_GET, $over), function ($v) {
        return $v !== '' && $v !== null;
    });
    return 'documents.php' . ($keep ? '?' . http_build_query($keep) : '');
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents - BERTIN MOVIE</title>
    <link rel="stylesheet" href="<?= dc_e(BASE_URL) ?>style.css?v=2">
    <link rel="stylesheet" href="hx-style.css?v=4">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dc {
            max-width: 1180px;
            color: var(--hx-text);
        }

        .dc h1 {
            font-size: 24px;
            margin: 0 0 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dc .sub {
            color: var(--hx-muted);
            margin: 0 0 20px;
            font-size: 14px;
        }

        .dc-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .dc-stat {
            background: var(--hx-bg);
            border: 1px solid var(--hx-line);
            border-radius: 14px;
            padding: 14px 18px;
            box-shadow: var(--hx-sh);
        }

        .dc-stat b {
            display: block;
            font-size: 22px;
        }

        .dc-stat span {
            color: var(--hx-muted);
            font-size: 13px;
        }

        .dc-filters {
            background: var(--hx-bg);
            border: 1px solid var(--hx-line);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 18px;
            box-shadow: var(--hx-sh);
        }

        .dc-search {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
        }

        .dc-search input {
            flex: 1;
            min-width: 0;
        }

        .dc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
        }

        .dc-filters label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--hx-muted);
            margin-bottom: 4px;
        }

        .dc-filters input,
        .dc-filters select {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid var(--hx-line);
            border-radius: 10px;
            background: var(--hx-bg-2);
            color: var(--hx-text);
            font: inherit;
            font-size: 14px;
            box-sizing: border-box;
        }

        .dc-btn {
            border: 0;
            border-radius: 10px;
            padding: 9px 18px;
            font-weight: 700;
            cursor: pointer;
            background: var(--hx-blue);
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            white-space: nowrap;
        }

        .dc-btn:hover {
            background: var(--hx-blue-d);
        }

        .dc-btn.light {
            background: var(--hx-bg-2);
            color: var(--hx-text);
            border: 1.5px solid var(--hx-line);
        }

        .dc-btn.off {
            opacity: .45;
            pointer-events: none;
        }

        .dc-foot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            color: var(--hx-muted);
            font-size: 13px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .dc-card {
            background: var(--hx-bg);
            border: 1px solid var(--hx-line);
            border-radius: 16px;
            box-shadow: var(--hx-sh);
            overflow: hidden;
        }

        .dc-scroll {
            overflow-x: auto;
        }

        .dc-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        .dc-table th {
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--hx-muted);
            background: var(--hx-bg-2);
            padding: 12px 14px;
            white-space: nowrap;
        }

        .dc-table td {
            padding: 12px 14px;
            border-top: 1px solid var(--hx-line);
            vertical-align: middle;
            font-size: 14px;
        }

        .dc-file {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .dc-thumb {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            flex: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: var(--hx-bg-2);
            overflow: hidden;
        }

        .dc-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dc-name {
            font-weight: 600;
            word-break: break-word;
        }

        .dc-name mark {
            background: #fde68a;
            color: #0f172a;
            border-radius: 3px;
            padding: 0 2px;
        }

        .dc-tag {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: var(--hx-bg-2);
            border: 1px solid var(--hx-line);
        }

        .dc-who a {
            color: inherit;
            font-weight: 600;
            text-decoration: none;
        }

        .dc-who a:hover {
            color: var(--hx-blue);
        }

        .dc-who small {
            display: block;
            color: var(--hx-muted);
            font-size: 12px;
        }

        .dc-miss {
            color: var(--hx-muted);
            font-size: 12px;
        }

        .dc-empty {
            text-align: center;
            padding: 50px 20px;
            color: var(--hx-muted);
        }

        .dc-empty i {
            font-size: 38px;
            margin-bottom: 10px;
            display: block;
            opacity: .5;
        }

        .dc-pages {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .dc-pages a,
        .dc-pages span {
            padding: 7px 12px;
            border-radius: 9px;
            border: 1.5px solid var(--hx-line);
            background: var(--hx-bg);
            color: var(--hx-text);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .dc-pages span.cur {
            background: var(--hx-blue);
            border-color: var(--hx-blue);
            color: #fff;
        }

        @media (max-width: 640px) {
            .dc-search {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <?php include("../includes/menu.php"); ?>
    <?php renderNotifications(); ?>

    <div class="content">
        <div class="hx-wrap dc">
            <h1><i class="fas fa-folder-open" style="color:#3b82f6;"></i> Documents</h1>
            <p class="sub">Tous les fichiers échangés dans le chat support : recherchez, filtrez et téléchargez.</p>

            <div class="dc-stats">
                <div class="dc-stat"><b><?= (int) $all['n'] ?></b><span>fichier(s) au total</span></div>
                <div class="dc-stat"><b><?= dc_e(dc_size($all['taille'])) ?></b><span>espace utilisé</span></div>
                <div class="dc-stat">
                    <b><?= $all['dernier'] ? '<time class="dc-t" datetime="' . dc_e(str_replace(' ', 'T', $all['dernier'])) . 'Z">' . dc_e($all['dernier']) . ' UTC</time>' : '—' ?></b><span>dernier
                        envoi</span></div>
            </div>

            <form method="GET" class="dc-filters" id="dcForm">
                <div class="dc-search">
                    <input type="search" name="q" value="<?= dc_e($q) ?>"
                        placeholder="Rechercher un fichier par nom (ex. facture, .pdf, photo)…" maxlength="100"
                        style="padding:10px 14px;border:1.5px solid var(--hx-line);border-radius:10px;background:var(--hx-bg-2);color:var(--hx-text);font:inherit;">
                    <button class="dc-btn" type="submit"><i class="fas fa-search"></i> Rechercher</button>
                    <?php if ($filtresActifs): ?><a class="dc-btn light" href="documents.php"><i
                                class="fas fa-rotate-left"></i> Réinitialiser</a><?php endif; ?>
                </div>
                <div class="dc-grid">
                    <div><label>Type</label>
                        <select name="type" onchange="this.form.submit()">
                            <option value="">Tous les types</option>
                            <?php foreach ($TYPES as $k => $t): ?>
                                <option value="<?= dc_e($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= dc_e($t[0]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>Utilisateur</label>
                        <select name="user" onchange="this.form.submit()">
                            <option value="0">Tous les utilisateurs</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int) $u['id'] ?>" <?= $uid === (int) $u['id'] ? 'selected' : '' ?>>
                                    <?= dc_e(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) ?: $u['nom_utilisateur']) ?>
                                    (<?= dc_e($u['nom_utilisateur']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>Envoyé par</label>
                        <select name="sens" onchange="this.form.submit()">
                            <option value="">Utilisateur ou admin</option>
                            <option value="user" <?= $sens === 'user' ? 'selected' : '' ?>>L'utilisateur</option>
                            <option value="admin" <?= $sens === 'admin' ? 'selected' : '' ?>>L'administrateur</option>
                        </select>
                    </div>
                    <div><label>Du</label><input type="date" name="from" value="<?= dc_e($from) ?>"
                            onchange="this.form.submit()"></div>
                    <div><label>Au</label><input type="date" name="to" value="<?= dc_e($to) ?>"
                            onchange="this.form.submit()"></div>
                    <div><label>Trier par</label>
                        <select name="tri" onchange="this.form.submit()">
                            <?php foreach ($TRIS as $k => $t): ?>
                                <option value="<?= dc_e($k) ?>" <?= $tri === $k ? 'selected' : '' ?>><?= dc_e($t[0]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="dc-foot">
                    <span><strong><?= $total ?></strong> résultat(s) ·
                        <?= dc_e(dc_size($taille)) ?><?= $filtresActifs ? ' · ' . $filtresActifs . ' filtre(s) actif(s)' : '' ?></span>
                </div>
            </form>

            <div class="dc-card">
                <?php if (!$rows): ?>
                    <div class="dc-empty"><i class="fas fa-folder-open"></i>
                        <?= $filtresActifs ? 'Aucun fichier ne correspond à votre recherche.' : "Aucun fichier n'a encore été échangé dans le chat." ?>
                    </div>
                <?php else: ?>
                    <div class="dc-scroll">
                        <table class="dc-table">
                            <thead>
                                <tr>
                                    <th>Fichier</th>
                                    <th>Type</th>
                                    <th>Expéditeur</th>
                                    <th>Date</th>
                                    <th>Taille</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r):
                                    $existe = preg_match('/^[a-f0-9]{32}$/', (string) $r['fichier_path']) && is_file(SUPPORT_DIR . $r['fichier_path']);
                                    $k = dc_type_of($r['fichier_nom'], $TYPES);
                                    [$ico, $col] = dc_icon($r['fichier_nom']);
                                    $estImage = $k === 'image' && $existe;
                                    $nomUser = trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? '')) ?: ($r['nom_utilisateur'] ?? 'Utilisateur supprimé');
                                    $expediteur = $r['sender'] === 'admin' ? 'Administrateur' : $nomUser;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="dc-file">
                                                <div class="dc-thumb" style="color:<?= dc_e($col) ?>">
                                                    <?php if ($estImage): ?><img src="support_file.php?id=<?= (int) $r['id'] ?>"
                                                            alt="" loading="lazy">
                                                    <?php else: ?><i class="fas <?= dc_e($ico) ?>"></i><?php endif; ?>
                                                </div>
                                                <div class="dc-name"><?= dc_hl($r['fichier_nom'], $q) ?></div>
                                            </div>
                                        </td>
                                        <td><span class="dc-tag"><?= dc_e($k === 'autre' ? 'Autre' : $TYPES[$k][0]) ?></span>
                                        </td>
                                        <td class="dc-who">
                                            <?= dc_e($expediteur) ?>
                                            <small>Conversation de <a
                                                    href="messages.php?user=<?= (int) $r['id_user'] ?>"><?= dc_e($nomUser) ?></a></small>
                                        </td>
                                        <td><time class="dc-t"
                                                datetime="<?= dc_e(str_replace(' ', 'T', $r['date_envoi'])) ?>Z"><?= dc_e($r['date_envoi']) ?>
                                                UTC</time></td>
                                        <td><?= dc_e(dc_size($r['fichier_taille'])) ?></td>
                                        <td>
                                            <?php if ($existe): ?>
                                                <a class="dc-btn" href="support_file.php?id=<?= (int) $r['id'] ?>&dl=1"><i
                                                        class="fas fa-download"></i> Télécharger</a>
                                            <?php else: ?>
                                                <span class="dc-miss"><i class="fas fa-triangle-exclamation"></i> Fichier
                                                    supprimé</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($pages > 1): ?>
                <div class="dc-foot">
                    <span>Page <?= $page ?> sur <?= $pages ?></span>
                    <div class="dc-pages">
                        <?php if ($page > 1): ?><a href="<?= dc_e(dc_url(['p' => $page - 1])) ?>">‹
                                Précédent</a><?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                            <?= $i === $page ? '<span class="cur">' . $i . '</span>' : '<a href="' . dc_e(dc_url(['p' => $i])) . '">' . $i . '</a>' ?>
                        <?php endfor; ?>
                        <?php if ($page < $pages): ?><a href="<?= dc_e(dc_url(['p' => $page + 1])) ?>">Suivant
                                ›</a><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Les dates sont stockées en UTC : on les affiche à l'heure du navigateur (comme dans le chat)
        document.querySelectorAll('time.dc-t').forEach(function (t) {
            var d = new Date(t.getAttribute('datetime'));
            if (!isNaN(d)) { t.textContent = d.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }); }
        });
    </script>
</body>

</html>