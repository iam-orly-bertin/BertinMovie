<?php
require_once __DIR__ . '/../includes/session_boot.php';
include("../connexion.php");
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/permissions.php';
require_permission('gerer_corbeille');

if (!isset($_SESSION['role'])) {
    header("Location: ../index.php");
    exit();
}

/* =========================================================
   RESTAURER UN ÉLÉMENT
========================================================= */
if (isset($_GET['restaurer']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM corbeille WHERE id_trash = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();

        if ($item) {
            $table_name = $item['table_name'];
            $deleted_data = $item['deleted_data'];
            $restaure = false;

            if ($table_name == 'film') {
                $deleted_data = str_replace('FILM SUPPRIME:', '', $deleted_data);
                $parts = array_map('trim', explode('|', $deleted_data));

                $nom_film = isset($parts[0]) ? trim($parts[0]) : '';
                $titre = isset($parts[1]) ? trim($parts[1]) : '';
                $date_sortie = isset($parts[2]) ? trim($parts[2]) : '2024-01-01';

                $duree = '00:00:00';
                $id_realisateur = null;
                $id_langue = null;
                $id_categorie = null;
                $film_numero = null;

                if (!empty($nom_film) && !empty($titre)) {
                    $insert = $pdo->prepare("
                        INSERT INTO film (nom_film, titre, duree, date_sortie, id_realisateur, id_langue, id_categorie, film_numero) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insert->execute([
                        $nom_film,
                        $titre,
                        $duree,
                        $date_sortie,
                        $id_realisateur,
                        $id_langue,
                        $id_categorie,
                        $film_numero
                    ]);
                    $restaure = true;
                }

            } elseif ($table_name == 'acteur') {
                $parts = array_map('trim', explode('|', $deleted_data));

                $nom_complet = isset($parts[0]) ? trim($parts[0]) : '';
                $sexe = isset($parts[1]) ? trim(str_replace('sexe:', '', $parts[1])) : 'M';
                $numero = isset($parts[2]) ? trim(str_replace('numero:', '', $parts[2])) : null;

                if (!empty($nom_complet)) {
                    $nom_parts = explode(' ', $nom_complet);
                    $prenom = array_shift($nom_parts);
                    $nom = implode(' ', $nom_parts);

                    $insert = $pdo->prepare("INSERT INTO acteur (nom, prenom, sexe, acteur_numero) VALUES (?, ?, ?, ?)");
                    $insert->execute([$nom, $prenom, $sexe, $numero]);
                    $restaure = true;
                }

            } elseif ($table_name == 'realisateur') {
                $parts = array_map('trim', explode('|', $deleted_data));

                $nom_complet = isset($parts[0]) ? trim($parts[0]) : '';
                $nationalite = isset($parts[1]) ? trim(str_replace('nationalite:', '', $parts[1])) : null;
                $numero = isset($parts[2]) ? trim(str_replace('numero:', '', $parts[2])) : null;

                if (!empty($nom_complet)) {
                    $nom_parts = explode(' ', $nom_complet);
                    $prenom = array_shift($nom_parts);
                    $nom = implode(' ', $nom_parts);

                    $insert = $pdo->prepare("INSERT INTO realisateur (nom, prenom, nationalite, realisateur_numero) VALUES (?, ?, ?, ?)");
                    $insert->execute([$nom, $prenom, $nationalite, $numero]);
                    $restaure = true;
                }

            } elseif ($table_name == 'langue') {
                $nom_langue = trim($deleted_data);
                if (!empty($nom_langue)) {
                    $insert = $pdo->prepare("INSERT INTO langue (nom_langue) VALUES (?)");
                    $insert->execute([$nom_langue]);
                    $restaure = true;
                }

            } elseif ($table_name == 'categorie') {
                $nom_categorie = trim($deleted_data);
                if (!empty($nom_categorie)) {
                    $insert = $pdo->prepare("INSERT INTO categorie (nom_categorie) VALUES (?)");
                    $insert->execute([$nom_categorie]);
                    $restaure = true;
                }

            } elseif ($table_name == 'pays') {
                $nom_pays = trim($deleted_data);
                if (!empty($nom_pays)) {
                    $insert = $pdo->prepare("INSERT INTO pays (nom_pays) VALUES (?)");
                    $insert->execute([$nom_pays]);
                    $restaure = true;
                }

            } elseif ($table_name == 'studio') {
                $nom_studio = trim($deleted_data);
                if (!empty($nom_studio)) {
                    $insert = $pdo->prepare("INSERT INTO studio (nom_studio) VALUES (?)");
                    $insert->execute([$nom_studio]);
                    $restaure = true;
                }
            }

            if ($restaure) {
                $pdo->prepare("DELETE FROM corbeille WHERE id_trash = ?")->execute([$id]);
                header("Location: corbeille.php?action=restaure");
                exit();
            } else {
                header("Location: corbeille.php?action=erreur");
                exit();
            }
        }
    } catch (Exception $e) {
        header("Location: corbeille.php?action=erreur");
        exit();
    }
}

/* =========================================================
   SUPPRIMER DÉFINITIVEMENT
========================================================= */
if (isset($_GET['supprimer_def']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $pdo->prepare("DELETE FROM corbeille WHERE id_trash = ?")->execute([$id]);
    header("Location: corbeille.php?action=supprime");
    exit();
}

/* =========================================================
   VIDER LA CORBEILLE
========================================================= */
if (isset($_GET['vider'])) {
    $pdo->query("DELETE FROM corbeille");
    header("Location: corbeille.php?action=vider");
    exit();
}

/* =========================================================
   RÉCUPÉRATION DES DONNÉES
========================================================= */
$data = $pdo->query("SELECT * FROM corbeille ORDER BY delete_date DESC")->fetchAll();
$total = count($data);

// Compteurs par table (une seule requête)
$counts = [];
foreach ($pdo->query("SELECT table_name, COUNT(*) AS c FROM corbeille GROUP BY table_name")->fetchAll() as $r) {
    $counts[$r['table_name']] = (int) $r['c'];
}

// [singulier, pluriel, icône, couleur]
$types = [
    'film' => ['Film', 'Films', 'fa-film', '#3b82f6'],
    'acteur' => ['Acteur', 'Acteurs', 'fa-user', '#8b5cf6'],
    'realisateur' => ['Réalisateur', 'Réalisateurs', 'fa-user-tie', '#06b6d4'],
    'langue' => ['Langue', 'Langues', 'fa-language', '#10b981'],
    'categorie' => ['Catégorie', 'Catégories', 'fa-tag', '#f59e0b'],
    'pays' => ['Pays', 'Pays', 'fa-globe', '#ec4899'],
    'studio' => ['Studio', 'Studios', 'fa-building', '#f472b6'],
];

$nb_types = count(array_filter($counts));
$derniere = $total ? strtotime($data[0]['delete_date']) : null;

$action = $_GET['action'] ?? '';

function hx_day_label($ymd)
{
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    if ($ymd === date('Y-m-d')) {
        return "Aujourd'hui";
    }
    if ($ymd === date('Y-m-d', strtotime('-1 day'))) {
        return 'Hier';
    }
    $t = strtotime($ymd);
    $label = ucfirst($jours[(int) date('w', $t)]) . ' ' . (int) date('j', $t) . ' ' . $mois[(int) date('n', $t) - 1];
    if (date('Y', $t) !== date('Y')) {
        $label .= ' ' . date('Y', $t);
    }
    return $label;
}

$last_day = null;
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corbeille - Bertin Movie</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=3">
    <link rel="stylesheet" href="/film_web_dy/admin/hx-style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include("../includes/menu.php"); ?>

    <div class="content hx-wrap">

        <!-- ===== EN-TÊTE ===== -->
        <div class="hx-head">
            <div class="hx-title">
                <div class="hx-title-ico hx-title-ico--red"><i class="fas fa-trash-alt"></i></div>
                <div>
                    <h1>Corbeille</h1>
                    <p>Éléments supprimés, à restaurer ou à effacer définitivement</p>
                </div>
            </div>
            <?php if ($total > 0): ?>
                <div class="hx-actions">
                    <button type="button" class="hx-btn hx-btn--danger" onclick="confirmEmptyTrash()">
                        <i class="fas fa-trash"></i> Vider la corbeille
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== MESSAGES ===== -->
        <?php if ($action == 'restaure'): ?>
            <div class="hx-alert hx-alert--ok"><i class="fas fa-check-circle"></i> Élément restauré avec succès.</div>
        <?php elseif ($action == 'supprime'): ?>
            <div class="hx-alert hx-alert--info"><i class="fas fa-info-circle"></i> Élément supprimé définitivement.</div>
        <?php elseif ($action == 'vider'): ?>
            <div class="hx-alert hx-alert--info"><i class="fas fa-info-circle"></i> La corbeille a été vidée.</div>
        <?php elseif ($action == 'erreur'): ?>
            <div class="hx-alert hx-alert--err"><i class="fas fa-exclamation-circle"></i> Erreur lors de la restauration.
            </div>
        <?php endif; ?>

        <!-- ===== SYNTHÈSE ===== -->
        <div class="hx-stats">
            <div class="hx-stat hx-stat--red">
                <div class="hx-stat-ico"><i class="fas fa-trash-can"></i></div>
                <div><strong><?= number_format($total, 0, ',', ' ') ?></strong><span>Éléments</span></div>
            </div>
            <div class="hx-stat hx-stat--violet">
                <div class="hx-stat-ico"><i class="fas fa-layer-group"></i></div>
                <div><strong><?= $nb_types ?></strong><span>Types</span></div>
            </div>
            <div class="hx-stat hx-stat--blue">
                <div class="hx-stat-ico"><i class="fas fa-clock-rotate-left"></i></div>
                <div><strong class="hx-sm"><?= $derniere ? date('d/m/Y', $derniere) : '—' ?></strong><span>Dernière
                        suppression</span></div>
            </div>
        </div>

        <?php if ($total > 0): ?>

            <!-- ===== ONGLETS PAR TYPE ===== -->
            <div class="hx-tabs" id="hxTabs">
                <button type="button" class="hx-tab active" data-filter="all">
                    <i class="fas fa-border-all"></i> Tout <b><?= $total ?></b>
                </button>
                <?php foreach ($types as $key => $t): ?>
                    <?php if (!empty($counts[$key])): ?>
                        <button type="button" class="hx-tab" data-filter="<?= $key ?>" style="--c:<?= $t[3] ?>;">
                            <i class="fas <?= $t[2] ?>"></i> <?= $t[1] ?> <b><?= $counts[$key] ?></b>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- ===== LISTE ===== -->
            <div class="hx-list" id="hxList">
                <?php foreach ($data as $i => $row):
                    $key = $row['table_name'];
                    $t = $types[$key] ?? [ucfirst($key), ucfirst($key), 'fa-file', '#64748b'];
                    $ts = strtotime($row['delete_date']);
                    $day = date('Y-m-d', $ts);
                    if ($day !== $last_day):
                        $last_day = $day; ?>
                        <div class="hx-day"><?= htmlspecialchars(hx_day_label($day)) ?></div>
                    <?php endif; ?>

                    <article class="hx-item" data-table="<?= htmlspecialchars($key) ?>"
                        style="animation-delay: <?= min($i, 12) * 0.03 ?>s;">
                        <div class="hx-ico" style="background:<?= $t[3] ?>;--c:<?= $t[3] ?>;">
                            <i class="fas <?= $t[2] ?>"></i>
                        </div>

                        <div class="hx-body">
                            <div class="hx-line">
                                <span class="hx-label"><?= htmlspecialchars($t[0]) ?></span>
                                <span class="hx-on">supprimé</span>
                            </div>
                            <div class="hx-msg"><?= htmlspecialchars($row['deleted_data']) ?></div>
                            <div class="hx-meta">
                                <span class="hx-chip" title="<?= date('d/m/Y H:i:s', $ts) ?>"><i class="fas fa-clock"></i>
                                    <?= date('H:i', $ts) ?></span>
                            </div>
                        </div>

                        <div class="hx-act">
                            <a class="hx-ibtn hx-ok" href="#"
                                onclick="confirmRestore(<?= (int) $row['id_trash'] ?>); return false;">
                                <i class="fas fa-undo"></i> Restaurer
                            </a>
                            <a class="hx-ibtn hx-del" href="#"
                                onclick="confirmDelete(<?= (int) $row['id_trash'] ?>); return false;">
                                <i class="fas fa-trash"></i> Supprimer
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="hx-empty">
                <div class="hx-empty-ico"><i class="fas fa-trash-alt"></i></div>
                <h3>La corbeille est vide</h3>
                <p>Aucun élément supprimé pour le moment.</p>
            </div>
        <?php endif; ?>

    </div>

    <script>
        // ============================================
        // FILTRE PAR TYPE (onglets)
        // ============================================
        (function () {
            var tabs = document.getElementById('hxTabs');
            var list = document.getElementById('hxList');
            if (!tabs || !list) return;

            function apply(filter) {
                var dayEl = null, dayHas = false;
                Array.prototype.forEach.call(list.children, function (el) {
                    if (el.classList.contains('hx-day')) {
                        if (dayEl) dayEl.hidden = !dayHas;
                        dayEl = el;
                        dayHas = false;
                    } else {
                        var show = filter === 'all' || el.getAttribute('data-table') === filter;
                        el.hidden = !show;
                        if (show) dayHas = true;
                    }
                });
                if (dayEl) dayEl.hidden = !dayHas;
            }

            tabs.addEventListener('click', function (e) {
                var btn = e.target.closest('.hx-tab');
                if (!btn) return;
                tabs.querySelectorAll('.hx-tab').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                apply(btn.getAttribute('data-filter'));
            });
        })();

        // ============================================
        // CONFIRMATIONS
        // ============================================
        function hxConfirm(opts, url) {
            if (typeof confirmModal !== 'undefined') {
                opts.callback = function () { window.location.href = url; };
                confirmModal.open(opts);
            } else if (confirm(opts.message)) {
                window.location.href = url;
            }
        }

        function confirmRestore(id) {
            var fr = (localStorage.getItem('lang') || 'fr') === 'fr';
            hxConfirm({
                type: 'info',
                title: fr ? 'Restauration' : 'Restore',
                message: fr ? 'Restaurer cet élément ?' : 'Restore this item?',
                confirmText: fr ? 'Restaurer' : 'Restore',
                cancelText: fr ? 'Annuler' : 'Cancel'
            }, 'corbeille.php?restaurer=1&id=' + id);
        }

        function confirmDelete(id) {
            var fr = (localStorage.getItem('lang') || 'fr') === 'fr';
            hxConfirm({
                type: 'danger',
                title: fr ? 'Suppression définitive' : 'Permanent delete',
                message: fr ? 'Supprimer définitivement cet élément ? Cette action est irréversible.' : 'Delete permanently? This action is irreversible.',
                confirmText: fr ? 'Supprimer' : 'Delete',
                cancelText: fr ? 'Annuler' : 'Cancel'
            }, 'corbeille.php?supprimer_def=1&id=' + id);
        }

        function confirmEmptyTrash() {
            var fr = (localStorage.getItem('lang') || 'fr') === 'fr';
            hxConfirm({
                type: 'danger',
                title: fr ? 'Vider la corbeille' : 'Empty trash',
                message: fr ? 'Vider la corbeille définitivement ? Cette action est irréversible.' : 'Empty trash permanently? This action is irreversible.',
                confirmText: fr ? 'Vider' : 'Empty',
                cancelText: fr ? 'Annuler' : 'Cancel'
            }, 'corbeille.php?vider=1');
        }
    </script>

</body>

</html>