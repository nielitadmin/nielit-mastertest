<?php
session_name('NIELIT_ADMIN_SESSION');
session_start();

echo "<h1>Session Debug</h1>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<p><a href='admin-dashboard.php'>Go to Admin Dashboard</a></p>";
echo "<p><a href='admin-login.php'>Back to Login</a></p>";
?>