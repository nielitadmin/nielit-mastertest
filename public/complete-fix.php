<?php
// Complete fix for all redirect issues
echo "<h1>🔧 NIELIT CBT System - Complete Fix</h1>";
echo "<style>
    body { font-family: Arial; padding: 20px; background: #f0f2f5; }
    .success { color: green; padding: 5px; }
    .warning { color: orange; padding: 5px; }
    .error { color: red; padding: 5px; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 10px; }
    h2 { color: #0047ab; }
</style>";

$fixed_count = 0;
$error_count = 0;

// List of all PHP files in public folder
$all_files = [
    '404.php',
    'add-question.php',
    'add-user.php',
    'admin-dashboard.php',
    'admin-login-debug.php',
    'admin-login.php',
    'admin-logout.php',
    'available-exams.php',
    'candidate-dashboard.php',
    'candidate-login.php',
    'candidate-logout.php',
    'check-files.php',
    'check-schema.php',
    'create-exam.php',
    'db-test.php',
    'edit-user.php',
    'exam-instructions.php',
    'index.php',
    'login-combined.php',
    'logout.php',
    'manage-centers.php',
    'manage-exams.php',
    'manage-questions.php',
    'manage-users.php',
    'my-exams.php',
    'phpinfo.php',
    'profile.php',
    'quick-fix.php',
    'register-exam.php',
    'register.php',
    'save-answer.php',
    'show-data.php',
    'start-exam.php',
    'submit-exam.php',
    'take-exam.php',
    'test-link.php',
    'test-paths.php',
    'test-redirect.php',
    'test.php',
    'view-exam.php'
];

echo "<div class='section'>";
echo "<h2>📁 Processing " . count($all_files) . " files...</h2>";

foreach ($all_files as $file) {
    $filepath = __DIR__ . '/' . $file;
    
    if (!file_exists($filepath)) {
        echo "<p class='warning'>⚠️ File not found: $file</p>";
        continue;
    }
    
    $content = file_get_contents($filepath);
    $original = $content;
    
    // FIX 1: Redirect headers - Admin files
    if (strpos($file, 'admin') !== false || in_array($file, ['add-question.php', 'add-user.php', 'manage-users.php', 'manage-questions.php', 'manage-exams.php', 'manage-centers.php', 'edit-user.php', 'create-exam.php', 'view-exam.php'])) {
        $content = str_replace(
            ['header("Location: login.php")', "header('Location: login.php')"],
            ['header("Location: admin-login.php")', "header('Location: admin-login.php')"],
            $content
        );
    }
    
    // FIX 2: Redirect headers - Candidate files
    if (strpos($file, 'candidate') !== false || in_array($file, ['my-exams.php', 'available-exams.php', 'exam-instructions.php', 'profile.php', 'register-exam.php', 'start-exam.php', 'take-exam.php'])) {
        $content = str_replace(
            ['header("Location: login.php")', "header('Location: login.php')"],
            ['header("Location: candidate-login.php")', "header('Location: candidate-login.php')"],
            $content
        );
    }
    
    // FIX 3: Special case for candidate-dashboard.php with type parameter
    $content = str_replace(
        ['header("Location: login.php?type=candidate")', "header('Location: login.php?type=candidate')"],
        ['header("Location: candidate-login.php")', "header('Location: candidate-login.php')"],
        $content
    );
    
    // FIX 4: Special case for admin-dashboard.php
    $content = str_replace(
        'header("Location: login.php")',
        'header("Location: admin-login.php")',
        $content
    );
    
    // FIX 5: HTML links in all files
    $content = str_replace(
        ['href="login.php"', "href='login.php'"],
        ['href="admin-login.php"', "href='admin-login.php'"],
        $content
    );
    
    // FIX 6: Form actions
    $content = str_replace(
        ['action="login.php"', "action='login.php'"],
        ['action="admin-login.php"', "action='admin-login.php'"],
        $content
    );
    
    // FIX 7: Text links
    $content = str_replace(
        '>login.php<',
        '>admin-login.php<',
        $content
    );
    
    // FIX 8: Register.php special handling
    if ($file == 'register.php') {
        // Fix login link in register.php
        $content = str_replace(
            '<a href="login.php">Login here</a>',
            '<a href="candidate-login.php">Login here</a>',
            $content
        );
        
        // Fix redirect in register.php
        $content = str_replace(
            'header("Location: login.php")',
            'if ($_SESSION[\'user_role\'] == \'admin\') { header("Location: admin-dashboard.php"); } else { header("Location: candidate-dashboard.php"); }',
            $content
        );
    }
    
    // FIX 9: Logout.php - make it redirect to index
    if ($file == 'logout.php') {
        $content = "<?php\nsession_start();\nsession_destroy();\nheader(\"Location: index.php\");\nexit();\n?>";
    }
    
    // FIX 10: Index.php - make it a clean landing page
    if ($file == 'index.php') {
        $content = '<?php
// Simple landing page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIELIT Bhubaneswar - CBT System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            text-align: center;
            color: white;
            padding: 20px;
        }
        h1 { font-size: 48px; margin-bottom: 10px; }
        .subtitle { font-size: 24px; margin-bottom: 40px; opacity: 0.9; }
        .buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 15px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 18px;
            transition: all 0.3s;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(0,0,0,0.3); }
        .btn-admin { background: #0047ab; color: white; }
        .btn-candidate { background: #28a745; color: white; }
        .footer {
            margin-top: 50px;
            color: rgba(255,255,255,0.8);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>NIELIT Bhubaneswar</h1>
        <div class="subtitle">Computer Based Test System</div>
        <div class="buttons">
            <a href="admin-login.php" class="btn btn-admin">👨‍💼 Admin Login</a>
            <a href="candidate-login.php" class="btn btn-candidate">👨‍🎓 Candidate Login</a>
        </div>
        <div class="footer">
            <p>National Institute of Electronics & Information Technology</p>
            <p>Ministry of Electronics & IT, Government of India</p>
        </div>
    </div>
</body>
</html>';
    }
    
    // Save if changed
    if ($content !== $original) {
        file_put_contents($filepath, $content);
        echo "<p class='success'>✅ Fixed: $file</p>";
        $fixed_count++;
    } else {
        echo "<p class='warning'>ℹ️ No changes needed: $file</p>";
    }
}

echo "</div>";

// Create a new combined login page if needed
$combined_path = __DIR__ . '/login-combined.php';
if (!file_exists($combined_path)) {
    $combined_content = '<?php
session_name("NIELIT_COMBINED_SESSION");
session_start();

if (isset($_SESSION["user_id"])) {
    if ($_SESSION["user_role"] == "admin") {
        header("Location: admin-dashboard.php");
    } else {
        header("Location: candidate-dashboard.php");
    }
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $host = "localhost";
    $port = "5432";
    $dbname = "nielit_cbt_mock";
    $dbuser = "nielit_admin";
    $dbpass = "NIELIT@BBSR2024";
    
    try {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $dbuser, $dbpass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $username = $_POST["username"];
        $password = $_POST["password"];
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = true");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user["password_hash"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["user_role"] = $user["role"];
            $_SESSION["full_name"] = $user["full_name"];
            
            if ($user["role"] == "admin") {
                header("Location: admin-dashboard.php");
            } else {
                header("Location: candidate-dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid username or password!";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - NIELIT Bhubaneswar</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .login-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 350px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        h2 { color: #0047ab; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #333; }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #0047ab;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover { background: #003380; }
        .error {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .links {
            text-align: center;
            margin-top: 15px;
        }
        .links a {
            color: #666;
            text-decoration: none;
            margin: 0 10px;
            font-size: 13px;
        }
        .links a:hover { color: #0047ab; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>NIELIT CBT System</h2>
        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <div class="links">
            <a href="admin-login.php">Admin Login</a> |
            <a href="candidate-login.php">Candidate Login</a> |
            <a href="register.php">Register</a>
        </div>
    </div>
</body>
</html>';
    file_put_contents($combined_path, $combined_content);
    echo "<p class='success'>✅ Created: login-combined.php</p>";
}

echo "<div class='section'>";
echo "<h2>📊 Summary</h2>";
echo "<p><strong>Files fixed:</strong> $fixed_count</p>";
echo "<p><strong>Total files processed:</strong> " . count($all_files) . "</p>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>🔗 Quick Links</h2>";
echo "<ul>";
echo "<li><a href='index.php' target='_blank'>Homepage</a></li>";
echo "<li><a href='admin-login.php' target='_blank'>Admin Login</a></li>";
echo "<li><a href='candidate-login.php' target='_blank'>Candidate Login</a></li>";
echo "<li><a href='login-combined.php' target='_blank'>Combined Login</a></li>";
echo "<li><a href='admin-dashboard.php' target='_blank'>Admin Dashboard</a></li>";
echo "<li><a href='candidate-dashboard.php' target='_blank'>Candidate Dashboard</a></li>";
echo "</ul>";
echo "</div>";

echo "<p><strong>✅ Fix complete! Please clear your browser cache and test.</strong></p>";
?>