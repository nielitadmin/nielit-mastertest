<?php
$host = 'localhost';
$port = '5432';
$dbname = 'nielit_cbt_mock';
$dbuser = 'nielit_admin';
$dbpass = 'NIELIT@BBSR2024';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $username = 'admin';
    $password = 'Password@123';
    
    // This generates a mathematically perfect hash for your specific server
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Check if the admin account exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        // Update the existing admin account
        $update = $pdo->prepare("UPDATE users SET password_hash = ?, is_active = true WHERE username = ?");
        $update->execute([$hash, $username]);
        echo "<h2 style='color: green;'>✅ Success! The admin password has been forcefully reset to: Password@123</h2>";
    } else {
        // Recreate the admin account if it was somehow deleted
        $insert = $pdo->prepare("
            INSERT INTO users (username, password_hash, email, full_name, role, is_active, created_at) 
            VALUES (?, ?, 'admin@nielit.gov.in', 'System Administrator', 'admin', true, NOW())
        ");
        $insert->execute([$username, $hash]);
        echo "<h2 style='color: green;'>✅ Success! Missing admin account was recreated. Password is: Password@123</h2>";
    }
    
    echo "<br><a href='admin-login.php' style='padding: 10px 20px; background: #1D4ED8; color: white; text-decoration: none; border-radius: 8px;'>Go to Login Page</a>";

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>❌ Database Error: " . $e->getMessage() . "</h2>";
}
?>