<?php
session_name('NIELIT_FINANCE_SESSION');
session_start();

// If already logged in, redirect to Finance dashboard
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] == 'finance') {
    header("Location: finance-dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Connect to database
    require_once __DIR__ . '/../../config/database.php';
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            // ONLY select users with the 'finance' role
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'finance' AND is_active = true");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                
                // Set Finance Session Variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['login_time'] = time();
                
                // Update last login timestamp
                $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update->execute([$user['id']]);
                
                header("Location: finance-dashboard.php");
                exit();

            } else {
                $error = "Invalid credentials or unauthorized access.";
            }
        } catch (PDOException $e) {
            $error = "System Database Offline. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Portal Login - NIELIT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F1F5F9; margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; overflow: hidden; position: relative; }
        
        /* Professional Navy Abstract Background for Finance */
        .bg-shapes { position: absolute; inset: 0; z-index: -1; overflow: hidden; background: linear-gradient(135deg, #E2E8F0 0%, #F8FAFC 100%); }
        .circle1 { position: absolute; width: 600px; height: 600px; background: rgba(30, 64, 175, 0.05); border-radius: 50%; top: -20%; left: -10%; filter: blur(60px); }
        .circle2 { position: absolute; width: 500px; height: 500px; background: rgba(15, 23, 42, 0.05); border-radius: 50%; bottom: -20%; right: -10%; filter: blur(50px); }
        
        .login-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border: 1px solid white; border-radius: 24px; padding: 50px 40px; width: 100%; max-width: 420px; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.15); animation: fadeUp 0.6s ease-out; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        .logo-wrap { text-align: center; margin-bottom: 30px; }
        .icon-box { width: 70px; height: 70px; background: #1E3A8A; color: white; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 30px; margin-bottom: 15px; box-shadow: 0 10px 25px rgba(30, 64, 175, 0.3); }
        h1 { font-size: 24px; font-weight: 800; color: #0F172A; margin: 0 0 5px 0; }
        p { color: #64748B; font-size: 14px; margin: 0; font-weight: 500; }

        .error-msg { background: #FEE2E2; color: #DC2626; padding: 12px 15px; border-radius: 12px; font-size: 13px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; border: 1px solid #FCA5A5; }

        .form-group { margin-bottom: 20px; }
        
        /* 🆕 Flexbox Layout for Label and Forgot Password */
        .label-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .label-flex label { margin-bottom: 0; display: block; font-size: 12px; font-weight: 800; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; }
        .forgot-link { font-size: 12px; color: #1E3A8A; text-decoration: none; font-weight: 700; transition: color 0.3s; }
        .forgot-link:hover { color: #172554; text-decoration: underline; }

        .input-wrap { position: relative; }
        .input-wrap i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 16px; }
        .form-control { width: 100%; padding: 14px 16px 14px 45px; border: 1px solid #E2E8F0; border-radius: 12px; font-family: inherit; font-size: 14px; background: #F8FAFC; outline: none; transition: 0.3s; box-sizing: border-box; }
        .form-control:focus { border-color: #1E3A8A; background: white; box-shadow: 0 0 0 4px #DBEAFE; }

        .btn-submit { width: 100%; padding: 15px; background: #1E3A8A; color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 15px; font-family: inherit; cursor: pointer; transition: 0.3s; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 15px rgba(30, 64, 175, 0.25); }
        .btn-submit:hover { background: #172554; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(30, 64, 175, 0.35); }

        .back-link { position: absolute; top: 30px; left: 40px; display: inline-flex; align-items: center; gap: 8px; background: white; border: 1px solid #E2E8F0; padding: 10px 20px; border-radius: 12px; color: #0F172A; text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.3s; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .back-link:hover { transform: translateX(-3px); border-color: #1E3A8A; color: #1E3A8A; }
    </style>
</head>
<body>

    <div class="bg-shapes">
        <div class="circle1"></div>
        <div class="circle2"></div>
    </div>

    <a href="../index.php" class="back-link"><i class="fas fa-arrow-left"></i> Return Home</a>

    <div class="login-card">
        <div class="logo-wrap">
            <div class="icon-box"><i class="fas fa-file-invoice-dollar"></i></div>
            <h1>Finance Portal</h1>
            <p>Authorized Personnel Only</p>
        </div>

        <?php if ($error): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <div class="label-flex">
                    <label>Finance ID / Username</label>
                </div>
                <div class="input-wrap">
                    <i class="fas fa-user-tie"></i>
                    <input type="text" name="username" class="form-control" placeholder="Enter assigned Finance ID" required>
                </div>
            </div>
            
            <div class="form-group">
                <div class="label-flex">
                    <label>Secure Password</label>
                    <a href="finance-forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>
                <div class="input-wrap">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
            </div>

            <button type="submit" class="btn-submit">Secure Login <i class="fas fa-sign-in-alt"></i></button>
        </form>
    </div>

</body>
</html>