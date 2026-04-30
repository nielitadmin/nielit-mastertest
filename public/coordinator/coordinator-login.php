<?php
session_name('NIELIT_COORD_SESSION');
session_start();

// If already fully logged in, redirect to Coordinator dashboard
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] == 'coordinator') {
    header("Location: coordinator-dashboard.php");
    exit();
}

$error = '';

require_once __DIR__ . '/../../config/database.php';

// ==========================================
// INITIAL LOGIN (USERNAME/PASSWORD)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login_step'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'coordinator' AND is_active = true");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                
                // Login successful. 
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['login_time'] = time();
                
                // Update last login timestamp
                $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update->execute([$user['id']]);
                
                header("Location: coordinator-dashboard.php");
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
    <title>Coordinator Portal Login - NIELIT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; overflow: hidden; position: relative; }
        
        .bg-shapes { position: absolute; inset: 0; z-index: -1; overflow: hidden; background: linear-gradient(135deg, #F5F3FF 0%, #EDE9FE 100%); }
        .circle1 { position: absolute; width: 600px; height: 600px; background: rgba(124, 58, 237, 0.08); border-radius: 50%; top: -20%; left: -10%; filter: blur(60px); }
        .circle2 { position: absolute; width: 500px; height: 500px; background: rgba(139, 92, 246, 0.08); border-radius: 50%; bottom: -20%; right: -10%; filter: blur(50px); }
        
        .login-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border: 1px solid white; border-radius: 24px; padding: 50px 40px; width: 100%; max-width: 420px; box-shadow: 0 25px 50px -12px rgba(124, 58, 237, 0.15); animation: fadeUp 0.6s ease-out; z-index: 10; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        .logo-wrap { text-align: center; margin-bottom: 30px; }
        .icon-box { width: 70px; height: 70px; background: #7C3AED; color: white; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 30px; margin-bottom: 15px; box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3); }
        h1 { font-size: 24px; font-weight: 800; color: #0F172A; margin: 0 0 5px 0; }
        p { color: #64748B; font-size: 14px; margin: 0; font-weight: 500; }

        .alert-box { padding: 12px 15px; border-radius: 12px; font-size: 13px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .error-msg { background: #FEE2E2; color: #DC2626; border: 1px solid #FCA5A5; }
        
        .form-group { margin-bottom: 20px; }
        .label-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .label-flex label { margin-bottom: 0; display: block; font-size: 12px; font-weight: 800; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; }
        .forgot-link { font-size: 12px; color: #7C3AED; text-decoration: none; font-weight: 700; transition: color 0.3s; }
        .forgot-link:hover { color: #6D28D9; text-decoration: underline; }

        .input-wrap { position: relative; }
        .input-wrap i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 16px; }
        .form-control { width: 100%; padding: 14px 16px 14px 45px; border: 1px solid #E2E8F0; border-radius: 12px; font-family: inherit; font-size: 14px; background: #F8FAFC; outline: none; transition: 0.3s; box-sizing: border-box; }
        .form-control:focus { border-color: #7C3AED; background: white; box-shadow: 0 0 0 4px #EDE9FE; }

        .btn-submit { width: 100%; padding: 15px; background: #7C3AED; color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 15px; font-family: inherit; cursor: pointer; transition: 0.3s; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25); }
        .btn-submit:hover { background: #6D28D9; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(124, 58, 237, 0.35); }

        .back-link { position: absolute; top: 30px; left: 40px; display: inline-flex; align-items: center; gap: 8px; background: white; border: 1px solid #E2E8F0; padding: 10px 20px; border-radius: 12px; color: #0F172A; text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.3s; box-shadow: 0 2px 5px rgba(0,0,0,0.02); z-index: 20; }
        .back-link:hover { transform: translateX(-3px); border-color: #7C3AED; color: #7C3AED; }

        .register-banner { margin-top: 25px; padding: 15px; background: rgba(237, 233, 254, 0.6); border: 1px dashed #A78BFA; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .register-text { font-size: 12px; color: #6D28D9; font-weight: 500; line-height: 1.4; text-align: left; }
        .register-text strong { display: block; font-size: 13px; font-weight: 800; color: #4C1D95; margin-bottom: 2px;}
        .btn-register { flex-shrink: 0; padding: 10px 16px; background: white; border: 1px solid #A78BFA; border-radius: 8px; color: #7C3AED; text-decoration: none; font-size: 12px; font-weight: 700; transition: all 0.3s; box-shadow: 0 2px 4px rgba(124, 58, 237, 0.1); }
        .btn-register:hover { background: #7C3AED; color: white; border-color: #7C3AED; box-shadow: 0 4px 10px rgba(124, 58, 237, 0.2); }

        @media (max-width: 480px) {
            .back-link { top: 15px; left: 15px; padding: 8px 12px; font-size: 12px; }
            .login-card { padding: 30px 20px; }
            .register-banner { flex-direction: column; text-align: center; }
            .btn-register { width: 100%; box-sizing: border-box; text-align: center; }
        }
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
            <div class="icon-box"><i class="fas fa-calendar-check"></i></div>
            <h1>Coordinator Portal</h1>
            <p>Exam Logistics & Scheduling</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-box error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="login_step" value="1">
            <div class="form-group">
                <div class="label-flex">
                    <label>Coordinator ID / Username</label>
                </div>
                <div class="input-wrap">
                    <i class="fas fa-user-tag"></i>
                    <input type="text" name="username" class="form-control" placeholder="Enter assigned ID" required>
                </div>
            </div>
            
            <div class="form-group">
                <div class="label-flex">
                    <label>Secure Password</label>
                    <a href="coordinator-forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>
                <div class="input-wrap">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
            </div>

            <button type="submit" class="btn-submit">Login <i class="fas fa-sign-in-alt"></i></button>
        </form>

        <div class="register-banner">
            <div class="register-text">
                <strong>New Coordinator?</strong>
                Apply for administrative portal access.
            </div>
            <a href="coordinator-register.php" class="btn-register">Register Here</a>
        </div>
        
    </div>

</body>
</html>