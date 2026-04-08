<?php
session_name('NIELIT_FINANCE_SESSION');
session_start();

// 1. Unset all session variables in the array
$_SESSION = array();

// 2. Completely destroy the session cookie in the user's browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session on the server
session_destroy();

// 4. Prevent browser caching (stops the "Back" button from showing secured pages)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 5. Redirect back to login
header("Location: finance-login.php");
exit();
?>