<?php
// includes/auth_check.php
//  1) exige une session connectée
//  2) VERROU D IDENTITÉ : si user_id / role changent autrement que par login.php -> journal + déconnexion
//  3) DÉCONNEXION AUTOMATIQUE après inactivité (timeout) + avertissement avant la coupure
//  4) exige un rôle cohérent avec la zone (/admin/ = admin ; /user/ = non-admin)
require_once __DIR__ . "/bm_trace.php";

/* =========================================================
   RÉGLAGES DE LA DÉCONNEXION AUTOMATIQUE  (modifie ici !)
   ========================================================= */
if (!defined('IDLE_TIMEOUT_MIN'))
    define('IDLE_TIMEOUT_MIN', 15);  // minutes d'inactivité avant déconnexion (mets 1 pour tester)
if (!defined('IDLE_WARNING_SEC'))
    define('IDLE_WARNING_SEC', 60);  // avertissement X secondes avant la coupure

if (empty($_SESSION["user_id"]) || empty($_SESSION["role"])) {
    header("Location: /film_web_dy/index.php");
    exit();
}

$__uid = (int) $_SESSION["user_id"];
$__role = (string) $_SESSION["role"];

if (!isset($_SESSION["_bm_uid"])) {                 // anciennes sessions : on fige l identité vue ici
    $_SESSION["_bm_uid"] = $__uid;
    $_SESSION["_bm_role"] = $__role;
} elseif ($_SESSION["_bm_uid"] !== $__uid || $_SESSION["_bm_role"] !== $__role) {
    bm_trace("IDENTITY-CHANGED", $_SESSION["_bm_uid"], $__uid);
    bm_force_logout();
}

/* =========================================================
   INACTIVITÉ
   - une page ouverte normalement (clic sur un lien) = activité
   - les requêtes automatiques en arrière-plan (fetch du dashboard, rafraîchissement
     des statuts toutes les 30 s...) NE comptent PAS comme activité
   - idle_timeout.js envoie un « keepalive » seulement si la personne bouge / tape / regarde une vidéo
   ========================================================= */
$__file = basename($_SERVER["SCRIPT_NAME"] ?? "");
$__is_keepalive = !empty($_SERVER["HTTP_X_BM_KEEPALIVE"]);
$__is_bg = !$__is_keepalive && (
    !empty($_SERVER["HTTP_X_BM_BACKGROUND"])
    || in_array($__file, ["check_notification.php", "check_notifications.php", "check_session.php"], true)
);
$__wants_json = $__is_keepalive || $__is_bg
    || !empty($_SERVER["HTTP_X_REQUESTED_WITH"])
    || stripos($_SERVER["HTTP_ACCEPT"] ?? "", "application/json") !== false;

$__now = time();
$__last = (int) ($_SESSION["_bm_last_activity"] ?? $__now);

if (($__now - $__last) >= (int) round(IDLE_TIMEOUT_MIN * 60)) {
    // ----- Session expirée : on la détruit -----
    bm_trace("IDLE-TIMEOUT", $__uid, $__uid);
    try {
        if (isset($GLOBALS["pdo"])) {
            $GLOBALS["pdo"]->prepare("DELETE FROM sessions_actives WHERE id_utilisateur = ? AND session_id = ?")
                ->execute([$__uid, session_id()]);
        }
    } catch (Throwable $e) {
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();

    if ($__wants_json) {                       // requête AJAX : pas de redirection HTML
        http_response_code(401);
        header("Content-Type: application/json");
        header("Cache-Control: no-store");
        echo '{"expired":true}';
        exit();
    }
    $__base = defined("BASE_URL") ? (parse_url(BASE_URL, PHP_URL_PATH) ?: "/") : "/film_web_dy/";
    header("Location: " . $__base . "login.php?timeout=1");
    exit();
}

if (!$__is_bg) {
    $_SESSION["_bm_last_activity"] = $__now;   // vraie activité : on remet le compteur à zéro
}

$__script = str_replace("\\", "/", $_SERVER["SCRIPT_NAME"] ?? "");
$__zone = null;
if (strpos($__script, "/admin/") !== false) {
    $__zone = "admin";
} elseif (strpos($__script, "/user/") !== false) {
    $__zone = "user";
}
if (($__zone === "admin" && $__role !== "admin") || ($__zone === "user" && $__role === "admin")) {
    header("Location: /film_web_dy/index.php");
    exit();
}

/* ----- Keepalive envoyé par idle_timeout.js : on répond tout de suite, sans afficher la page ----- */
if ($__is_keepalive) {
    require_once __DIR__ . "/update_session.php";      // garde aussi le statut « en ligne » à jour
    if (isset($GLOBALS["pdo"])) {
        updateUserSession($GLOBALS["pdo"], $__uid);
    }
    header("Content-Type: application/json");
    header("Cache-Control: no-store");
    echo '{"ok":true}';
    exit();
}

/* ----- Injecte le script d'inactivité dans les pages HTML (juste avant </body>) ----- */
if (!$__is_bg && !$__wants_json) {
    $__base = defined("BASE_URL") ? (parse_url(BASE_URL, PHP_URL_PATH) ?: "/") : "/film_web_dy/";
    $__t = max(10, (int) round(IDLE_TIMEOUT_MIN * 60));
    $__w = max(5, min((int) IDLE_WARNING_SEC, (int) floor($__t / 2)));
    $__tag = '<script src="' . htmlspecialchars($__base, ENT_QUOTES) . 'includes/idle_timeout.js" data-timeout="' . $__t . '" data-warn="' . $__w . '"></script>';
    ob_start(function ($html) use ($__tag) {
        $pos = strripos($html, "</body>");
        return $pos === false ? $html : substr($html, 0, $pos) . $__tag . substr($html, $pos);
    });
}

unset($__uid, $__role, $__script, $__zone, $__file, $__is_keepalive, $__is_bg, $__wants_json, $__now, $__last, $__base, $__t, $__w, $__tag);