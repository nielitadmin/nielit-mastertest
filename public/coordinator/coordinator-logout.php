<?php
// Make sure this matches the session name used in coordinator-login.php
session_name('NIELIT_COORD_SESSION'); 
session_start();

// 1. Empty the session array completely
$_SESSION = array();

// 2. Destroy the session cookie securely in the browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session file on the server
session_destroy();

// 4. Prevent the browser from caching this redirect (Anti-Back Button)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 5. Redirect to the main index (or coordinator login page)
header("Location: ../index.php");
exit();
?>