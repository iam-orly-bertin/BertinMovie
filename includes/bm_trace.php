<?php
// includes/bm_trace.php : journal des événements d identité (table bm_trace + error_log)
// Lecture : phpMyAdmin -> SELECT * FROM bm_trace ORDER BY id DESC;
function bm_trace($event, $uid_from = null, $uid_to = null)
{
    $sid  = substr(session_id(), 0, 8);
    $line = [
        date("Y-m-d H:i:s"), $event, $sid === "" ? "-" : $sid, session_name(),
        $uid_from, $uid_to, $_SESSION["role"] ?? null,
        substr($_SERVER["SCRIPT_NAME"] ?? "", 0, 120),
        $_SERVER["REMOTE_ADDR"] ?? null,
        substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 120),
    ];
    error_log("[BM-TRACE] " . implode(" | ", array_map("strval", $line)));

    $pdo = $GLOBALS["pdo"] ?? null;
    if (!$pdo) { return; }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bm_trace (
            id INT AUTO_INCREMENT PRIMARY KEY,
            t DATETIME NOT NULL,
            event VARCHAR(30) NOT NULL,
            sid8 VARCHAR(8) NULL,
            sname VARCHAR(20) NULL,
            uid_from INT NULL,
            uid_to INT NULL,
            role VARCHAR(20) NULL,
            script VARCHAR(120) NULL,
            ip VARCHAR(45) NULL,
            ua VARCHAR(120) NULL
        )");
        $pdo->prepare("INSERT INTO bm_trace (t,event,sid8,sname,uid_from,uid_to,role,script,ip,ua)
                       VALUES (?,?,?,?,?,?,?,?,?,?)")->execute($line);
        $pdo->exec("DELETE FROM bm_trace WHERE t < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    } catch (Throwable $e) {
    }
}

function bm_force_logout()
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
    header("Location: /film_web_dy/index.php");
    exit();
}
