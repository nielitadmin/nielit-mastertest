<?php
// Combined Login Page - Both Admin and Candidate can login here
session_name('NIELIT_COMBINED_SESSION');
session_start();

// If already logged in, redirect to respective dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] == 'admin') {
        header("Location: admin-dashboard.php");
        exit();
    } elseif ($_SESSION['user_role'] == 'candidate') {
        header("Location: candidate-dashboard.php");
        exit();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Database connection
    $host = 'localhost';
    $port = '5432';
    $dbname = 'nielit_cbt_mock';
    $dbuser = 'nielit_admin';
    $dbpass = 'NIELIT@BBSR2024';
    
    try {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $dbuser, $dbpass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $login_type = $_POST['login_type'] ?? 'auto'; // auto, admin, or candidate
        
        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password";
        } else {
            // Build query based on login type
            if ($login_type == 'admin') {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' AND is_active = true");
            } elseif ($login_type == 'candidate') {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'candidate' AND is_active = true");
            } else {
                // Auto-detect - try admin first, then candidate
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = true");
            }
            
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Check if login type matches role (if specified)
                if ($login_type == 'admin' && $user['role'] != 'admin') {
                    $error = "This user is not an admin. Please select correct login type.";
                } elseif ($login_type == 'candidate' && $user['role'] != 'candidate') {
                    $error = "This user is not a candidate. Please select correct login type.";
                } else {
                    // Set session based on role
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $update->execute([$user['id']]);
                    
                    session_write_close();
                    
                    // Redirect based on role
                    if ($user['role'] == 'admin') {
                        header("Location: admin-dashboard.php");
                    } else {
                        header("Location: candidate-dashboard.php");
                    }
                    exit();
                }
            } else {
                $error = "Invalid username or password!";
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIELIT Bhubaneswar - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding: 20px;
        }

        /* Animated Background */
        .bg-bubbles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .bg-bubbles li {
            position: absolute;
            list-style: none;
            display: block;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.15);
            bottom: -160px;
            animation: square 25s infinite;
            transition-timing-function: linear;
            border-radius: 50%;
        }

        .bg-bubbles li:nth-child(1) { left: 10%; width: 80px; height: 80px; animation-delay: 0s; }
        .bg-bubbles li:nth-child(2) { left: 20%; width: 120px; height: 120px; animation-delay: 2s; animation-duration: 17s; }
        .bg-bubbles li:nth-child(3) { left: 25%; width: 60px; height: 60px; animation-delay: 4s; }
        .bg-bubbles li:nth-child(4) { left: 40%; width: 100px; height: 100px; animation-delay: 0s; animation-duration: 22s; }
        .bg-bubbles li:nth-child(5) { left: 70%; width: 70px; height: 70px; animation-delay: 0s; }
        .bg-bubbles li:nth-child(6) { left: 80%; width: 90px; height: 90px; animation-delay: 3s; animation-duration: 18s; }

        @keyframes square {
            0% { transform: translateY(0); opacity: 0.5; }
            100% { transform: translateY(-1200px) rotate(600deg); opacity: 0; }
        }

        .login-container {
            width: 450px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            overflow: hidden;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #0047ab 0%, #667eea 100%);
            padding: 30px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .logo {
            width: 80px;
            height: 80px;
            margin-bottom: 10px;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.2));
            animation: float 3s ease-in-out infinite;
            border-radius: 50%;
            background: white;
            padding: 8px;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
            100% { transform: translateY(0px); }
        }

        .login-header h1 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 3px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .login-header p {
            color: rgba(255,255,255,0.9);
            font-size: 15px;
        }

        .login-body {
            padding: 30px 25px;
        }

        /* Login Type Tabs */
        .login-tabs {
            display: flex;
            margin-bottom: 25px;
            border-bottom: 2px solid #e0e0e0;
        }
        .tab {
            flex: 1;
            text-align: center;
            padding: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            color: #666;
            border-bottom: 3px solid transparent;
        }
        .tab:hover {
            color: #0047ab;
        }
        .tab.active {
            color: #0047ab;
            border-bottom-color: #0047ab;
        }

        .error {
            background: #fee;
            color: #c33;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 13px;
            border: 1px solid #fcc;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 13px;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: #0047ab;
        }

        .form-group input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: #0047ab;
            box-shadow: 0 0 0 3px rgba(0,71,171,0.1);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #0047ab 0%, #667eea 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .btn-login:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        .btn-login:hover:after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(100, 100);
                opacity: 0;
            }
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,71,171,0.4);
        }

        .info-box {
            background: #e8f0fe;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 13px;
            text-align: center;
            border-left: 4px solid #0047ab;
        }

        .info-box strong {
            color: #0047ab;
        }

        .demo-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
            border: 2px dashed #0047ab;
        }

        .demo-box h4 {
            color: #0047ab;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .demo-credentials {
            display: flex;
            justify-content: center;
            gap: 20px;
            font-size: 13px;
            flex-wrap: wrap;
        }

        .demo-item {
            text-align: left;
        }

        .demo-item .role {
            font-weight: 600;
            color: #0047ab;
        }

        .register-link {
            text-align: center;
            margin-top: 15px;
        }

        .register-link a {
            color: #28a745;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            color: rgba(255,255,255,0.7);
            font-size: 11px;
        }
    </style>
</head>
<body>
    <ul class="bg-bubbles">
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
    </ul>

    <div class="login-container">
        <div class="login-header">
            <img src="assets/images/nielit-logo.png" 
                 alt="NIELIT Logo" 
                 class="logo"
                 onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MCIgaGVpZ2h0PSI4MCIgdmlld0JveD0iMCAwIDgwIDgwIj48cmVjdCB3aWR0aD0iODAiIGhlaWdodD0iODAiIGZpbGw9IndoaXRlIi8+PHRleHQgeD0iNDAiIHk9IjQwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IiMwMDQ3YWIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5OSUVMSVQ8L3RleHQ+PHRleHQgeD0iNDAiIHk9IjU1IiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iOCIgZmlsbD0iIzAwNDdhYiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkJIVUJBTkVTV0FSPC90ZXh0Pjwvc3ZnPg=='">
            <h1>NIELIT</h1>
            <p>BHUBANESWAR</p>
        </div>

        <div class="login-body">
            <?php if ($error): ?>
                <div class="error">
                    <strong>❌</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Login Type Tabs -->
            <div class="login-tabs" id="loginTabs">
                <div class="tab active" onclick="setLoginType('auto')" data-type="auto">🔍 Auto Detect</div>
                <div class="tab" onclick="setLoginType('admin')" data-type="admin">👨‍💼 Admin</div>
                <div class="tab" onclick="setLoginType('candidate')" data-type="candidate">👨‍🎓 Candidate</div>
            </div>

            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="login_type" id="loginType" value="auto">
                
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-group">
                        <span class="input-icon">👤</span>
                        <input 
                            type="text" 
                            name="username" 
                            required 
                            placeholder="Enter your username"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input 
                            type="password" 
                            name="password" 
                            required 
                            placeholder="Enter your password"
                        >
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginButton">🔐 Login</button>
            </form>

            <div class="register-link">
                <a href="register.php">📝 New Candidate? Register Here</a>
            </div>

            <div class="info-box">
                <strong>ℹ️ Auto Detect:</strong> Automatically detects if you're admin or candidate. If you have both accounts with same username, specify the type.
            </div>

            <div class="demo-box">
                <h4>🔑 Demo Credentials</h4>
                <div class="demo-credentials">
                    <div class="demo-item">
                        <div class="role">👨‍💼 Admin</div>
                        <div><strong>Username:</strong> admin</div>
                        <div><strong>Password:</strong> admin123</div>
                    </div>
                    <div class="demo-item">
                        <div class="role">👨‍🎓 Candidate</div>
                        <div><strong>Username:</strong> candidate1</div>
                        <div><strong>Password:</strong> password123</div>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 15px;">
                <a href="admin-login.php" style="color: #666; font-size: 12px; margin: 0 5px;">Admin Login</a> |
                <a href="candidate-login.php" style="color: #666; font-size: 12px; margin: 0 5px;">Candidate Login</a>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>National Institute of Electronics & Information Technology</p>
        <p>Ministry of Electronics & IT, Government of India</p>
    </div>

    <script>
        function setLoginType(type) {
            document.getElementById('loginType').value = type;
            
            // Update active tab
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                if (tab.getAttribute('data-type') === type) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
            
            // Update button text
            const button = document.getElementById('loginButton');
            if (type === 'admin') {
                button.innerHTML = '🔐 Login as Admin';
            } else if (type === 'candidate') {
                button.innerHTML = '🎓 Login as Candidate';
            } else {
                button.innerHTML = '🔐 Login (Auto Detect)';
            }
        }
    </script>
</body>
</html>