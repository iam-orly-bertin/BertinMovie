<?php
// includes/session_boot.php : démarre la session AVEC le bon cookie selon la zone
//   /admin/... -> BM_ADMIN   |   /user/... -> BM_USER   |   ailleurs -> défini via BM_SESSION_NAME
// Ne dépend ni de .htaccess ni de php.ini (compatible hébergement gratuit).
if (session_status() === PHP_SESSION_NONE) {
    $__script = str_replace("\\", "/", $_SERVER["SCRIPT_NAME"] ?? "");
    if (defined("BM_SESSION_NAME")) {
        session_name(BM_SESSION_NAME);
    } elseif (strpos($__script, "/admin/") !== false) {
        session_name("BM_ADMIN");
    } elseif (strpos($__script, "/user/") !== false) {
        session_name("BM_USER");
    }
    $__https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");
    ini_set("session.use_strict_mode", "1");   // refuse les identifiants de session inconnus
    ini_set("session.use_only_cookies", "1");
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $__https,
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    session_start();
    unset($__script, $__https);
}
