<?php
/* BERTIN MOVIE - charge les traductions de l'administration (FR/EN).
   Les fichiers JS sont dans /film_web_dy/admin_lang/ (dossier public,
   car includes/ est bloqué par .htaccess). */
if (defined('ADMIN_LANG_LOADED')) { return; }
define('ADMIN_LANG_LOADED', true);

$__dir  = str_replace('\\', '/', dirname(__DIR__)) . '/admin_lang';
$__root = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$__url  = ($__root !== '' && strpos($__dir, $__root) === 0) ? substr($__dir, strlen($__root)) : '/film_web_dy/admin_lang';
$__v1 = @filemtime($__dir . '/translations.js') ?: 1;
$__v2 = @filemtime($__dir . '/admin-lang.js') ?: 1;
?>
<script src="<?= htmlspecialchars($__url) ?>/translations.js?v=<?= $__v1 ?>"></script>
<script src="<?= htmlspecialchars($__url) ?>/admin-lang.js?v=<?= $__v2 ?>"></script>
