<?php
/**
 * includes/archive_lib.php : tri des films + archivage électronique
 *  - ensure_archive_schema() ajoute tout seul les colonnes est_archive / date_archivage à la table film
 *  - film_sort_options() : liste FIXE des tris autorisés (aucun SQL libre venant du navigateur)
 */

/** Crée les colonnes d'archivage si elles n'existent pas (appelée par connexion.php, 1 seule fois grâce à un fichier témoin). */
function ensure_archive_schema(PDO $pdo, $key = '')
{
    $flag = __DIR__ . '/.schema_archive_' . md5((string) $key) . '.flag';
    if (is_file($flag)) {
        return;
    }
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM film")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('est_archive', $cols, true)) {
            $pdo->exec("ALTER TABLE film ADD COLUMN est_archive TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('date_archivage', $cols, true)) {
            $pdo->exec("ALTER TABLE film ADD COLUMN date_archivage DATETIME NULL");
        }
        @file_put_contents($flag, date('c'));
    } catch (Throwable $e) {
        error_log('ensure_archive_schema : ' . $e->getMessage());
    }
}

/**
 * Tris autorisés. $ctx = 'film' (liste admin, ordre d'ajout = id_film)
 *                     ou 'video' (bibliothèque vidéo, ordre = date d'upload).
 * Chaque entrée : [libellé affiché, fragment ORDER BY sûr]. Alias attendus : f = film, v = video_film.
 */
function film_sort_options($ctx = 'film')
{
    $recent = $ctx === 'video' ? 'v.date_upload DESC, f.nom_film ASC' : 'f.id_film DESC';
    $ancien = $ctx === 'video' ? '(v.date_upload IS NULL) ASC, v.date_upload ASC, f.nom_film ASC' : 'f.id_film ASC';
    return [
        'recent' => ['Plus récents', $recent],
        'ancien' => ['Plus anciens', $ancien],
        'az'     => ['Nom A → Z', 'f.nom_film ASC, f.id_film DESC'],
        'za'     => ['Nom Z → A', 'f.nom_film DESC, f.id_film DESC'],
        'vus'    => ['Plus vus', 'COALESCE(v.views, 0) DESC, f.nom_film ASC'],
    ];
}

/** Renvoie une clé de tri valide (par défaut « recent »). */
function film_sort_key($value, $ctx = 'film')
{
    $opts = film_sort_options($ctx);
    return (is_string($value) && isset($opts[$value])) ? $value : 'recent';
}

/** Fragment ORDER BY correspondant à une clé validée. */
function film_sort_sql($key, $ctx = 'film')
{
    $opts = film_sort_options($ctx);
    return $opts[film_sort_key($key, $ctx)][1];
}

/** <option> du menu « Trier par » (HTML déjà échappé). */
function film_sort_select_options($current, $ctx = 'film')
{
    $html = '';
    foreach (film_sort_options($ctx) as $k => $o) {
        $html .= '<option value="' . $k . '"' . ($k === $current ? ' selected' : '') . '>' . htmlspecialchars($o[0]) . '</option>';
    }
    return $html;
}