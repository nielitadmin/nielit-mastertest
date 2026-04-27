<?php
// Debug Admin Login
session_name('NIELIT_ADMIN_SESSION');
session_start();

// Clear any existing session for clean test
$_SESSION = array();

$error = '';
$debug_info = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $host = 'localhost';
    $port = '5432';
    $dbname = 'nielit_cbt_mock';
    $dbuser = 'nielit_admin';
    $dbpass = 'NIELIT@BBSR2024';
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $debug_info .= "<h3>Login Attempt:</h3>";
    $debug_info .= "<p>Username: <strong>$username</strong></p>";
    $debug_info .= "<p>Password: <strong>" . str_repeat('*', strlen($password)) . "</strong></p>";
    
    try {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $dbuser, $dbpass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $debug_info .= "<h3>Database Result:</h3>";
        if ($user) {
            $debug_info .= "<p style='color:green'>✅ User found in database</p>";
            $debug_info .= "<p>User ID: {$user['id']}</p>";
            $debug_info .= "<p>Username: {$user['username']}</p>";
            $debug_info .= "<p>Role: {$user['role']}</p>";
            $debug_info .= "<p>Is Active: " . ($user['is_active'] ? 'Yes' : 'No') . "</p>";
            $debug_info .= "<p>Password Hash: " . substr($user['password_hash'], 0, 20) . "...</p>";
            
            // Check if role is admin
            if ($user['role'] != 'admin') {
                $debug_info .= "<p style='color:red'>❌ User is not an admin (role: {$user['role']})</p>";
            }
            
            // Check if active
            if (!$user['is_active']) {
                $debug_info .= "<p style='color:red'>❌ User account is inactive</p>";
            }
            
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                $debug_info .= "<p style='color:green'>✅ Password is correct</p>";
                
                // Set session data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['login_time'] = time();
                $_SESSION['login_type'] = 'admin';
                
                $debug_info .= "<p style='color:green'>✅ Session set successfully</p>";
                $debug_info .= "<p>Redirecting to: <strong>admin-dashboard.php</strong></p>";
                
                // Show redirect link
                $debug_info .= "<p><a href='admin-dashboard.php' style='background: #0047ab; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Click here to go to Admin Dashboard</a></p>";
                
            } else {
                $debug_info .= "<p style='color:red'>❌ Password verification failed</p>";
            }
        } else {
            $debug_info .= "<p style='color:red'>❌ User not found</p>";
        }
        
    } catch (PDOException $e) {
        $debug_info .= "<p style='color:red'>❌ Database error: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Login Debug</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f0f2f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #0047ab; }
        .debug-box { background: #e8f0fe; padding: 20px; border-radius: 5px; margin: 20px 0; font-family: monospace; border-left: 4px solid #0047ab; }
        .form-group { margin-bottom: 15px; }
        .form-group input { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px; }
        .btn { background: #0047ab; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Admin Login Debug Tool</h1>
        
        <form method="POST">
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" value="admin" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" value="admin123" required>
            </div>
            <button type="submit" class="btn">Test Login</button>
        </form>
        
        <?php if ($debug_info): ?>
            <div class="debug-box">
                <?php echo $debug_info; ?>
            </div>
        <?php endif; ?>
        
        <p><a href="admin-login.php">← Back to normal login</a></p>
    </div>
</body>
</html>