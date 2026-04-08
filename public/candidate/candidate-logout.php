<?php
session_name('NIELIT_CANDIDATE_SESSION');
session_start();

// 1. Empty the session array
$_SESSION = array();

// 2. Destroy the session cookie in the browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session on the server
session_destroy();

// 4. Prevent the browser from caching this redirect
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 5. Redirect to login
header("Location: candidate-login.php");
exit();
?>