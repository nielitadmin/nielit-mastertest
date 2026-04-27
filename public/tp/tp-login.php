<?php
session_name('NIELIT_TP_SESSION');
session_start();

// Check if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'tp') {
    header("Location: tp-dashboard.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$error = '';

// ============================================================================
// HANDLE STANDARD MANUAL LOGIN
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['manual_login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'tp'");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // SMART CHECK: Checks standard hash, plain text in hash column, OR plain text in password column
        $is_valid_password = false;
        if ($user) {
            if (password_verify($password, $user['password_hash'] ?? '')) {
                $is_valid_password = true;
            } elseif ($password === ($user['password_hash'] ?? '')) {
                $is_valid_password = true;
            } elseif ($password === ($user['password'] ?? '')) {
                $is_valid_password = true;
            }
        }

        if ($is_valid_password) {
            if ($user['is_active']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                
                header("Location: tp-dashboard.php");
                exit();
            } else {
                $error = "Your account has been deactivated. Please contact the administrator.";
            }
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institute Login - NIELIT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0D9488; /* Teal Theme for TP */
            --primary-hover: #0F766E;
            --primary-light: #14B8A6;
            --primary-bg: #CCFBF1;
            --bg-page: #F8FAFC;
            --surface: rgba(255, 255, 255, 0.85); /* Frosted Glass */
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border: rgba(226, 232, 240, 0.8);
            --shadow-lg: 0 20px 40px -10px rgba(13, 148, 136, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* LOCKED FULLSCREEN BODY */
        body { 
            background: var(--bg-page); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            flex-direction: column; 
            padding: 20px;
            overflow: hidden; 
            position: relative;
        }

        /* 🟢 --- BEAUTIFUL AMBIENT 3D BACKGROUND (Teal Theme) --- */
        .ambient-bg {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1; overflow: hidden; pointer-events: none;
            background: radial-gradient(circle at 50% 0%, #E0F2FE 0%, #CCFBF1 50%, #F8FAFC 100%);
            perspective: 1000px;
        }

        .shape {
            position: absolute;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.6), rgba(13, 148, 136, 0.08));
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 15px 35px rgba(13, 148, 136, 0.1), inset 0 0 20px rgba(255, 255, 255, 0.8);
            animation: float-3d 20s infinite linear;
        }

        .cube { width: 140px; height: 140px; border-radius: 28px; top: 15%; left: 12%; animation-duration: 28s; }
        .ring { width: 220px; height: 220px; border-radius: 50%; border: 35px solid rgba(255,255,255,0.4); top: 55%; right: 8%; animation-duration: 35s; animation-direction: reverse; background: transparent; box-shadow: 0 15px 35px rgba(13, 148, 136, 0.05); }
        .pyramid { width: 90px; height: 90px; border-radius: 16px; bottom: 15%; left: 22%; animation-duration: 22s; }
        .sphere { width: 180px; height: 180px; border-radius: 50%; top: 8%; right: 15%; animation-duration: 40s; }

        @keyframes float-3d {
            0% { transform: translateY(0) rotateX(0deg) rotateY(0deg) rotateZ(0deg); }
            50% { transform: translateY(-50px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); }
            100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); }
        }

        /* --- LOGIN CARD --- */
        .login-container {
            width: 100%; max-width: 420px; 
            background: var(--surface); 
            border-radius: 24px;
            box-shadow: var(--shadow-lg); 
            border: 1px solid rgba(255, 255, 255, 1);
            padding: 40px; 
            position: relative; 
            backdrop-filter: blur(24px); 
            animation: fadeUp 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
            z-index: 1;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-container::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 6px;
            background: linear-gradient(90deg, var(--primary-light), var(--primary));
        }

        .header { text-align: center; margin-bottom: 30px; }
        .logo-box { width: 60px; height: 60px; background: var(--primary-bg); color: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 15px; box-shadow: 0 8px 15px rgba(13, 148, 136, 0.15);}
        .header h1 { font-size: 24px; font-weight: 800; color: var(--text-main); margin-bottom: 5px; }
        .header p { font-size: 14px; color: var(--text-muted); font-weight: 500; }

        .alert-error { background: #FEF2F2; color: #DC2626; padding: 12px 15px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 20px; border: 1px solid #FECACA; text-align: center; }

        /* --- STANDARD FORM --- */
        .form-group { margin-bottom: 18px; }
        
        /* 🆕 Flexbox for Label + Forgot Password Link */
        .label-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .label-flex label { margin-bottom: 0; display: block; font-size: 13px; font-weight: 700; color: var(--text-main); }
        .forgot-link { font-size: 12px; color: var(--primary); text-decoration: none; font-weight: 700; transition: color 0.3s; }
        .forgot-link:hover { color: var(--primary-hover); text-decoration: underline; }

        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; transition: 0.3s;}
        
        .form-control { 
            width: 100%; padding: 14px 16px 14px 45px; 
            border: 1px solid var(--border); border-radius: 12px; 
            font-size: 14px; font-weight: 500; outline: none; transition: 0.3s; 
            background: #F8FAFC; font-family: inherit;
        }
        .form-control:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 3px var(--primary-bg); }
        .input-wrap:focus-within .input-icon { color: var(--primary); }

        /* 🆕 Toggle Password Visibility Icon */
        .toggle-pw { 
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%); 
            background: none; border: none; cursor: pointer; font-size: 14px; 
            color: var(--text-muted); transition: color 0.3s; padding: 0; outline: none; 
        }
        .toggle-pw:hover, .toggle-pw:focus { color: var(--primary); }

        .btn-submit { 
            width: 100%; background: var(--primary); color: white; border: none; 
            padding: 15px; border-radius: 12px; font-size: 15px; font-weight: 800; 
            cursor: pointer; transition: 0.3s; margin-top: 10px; font-family: inherit;
            box-shadow: 0 6px 15px rgba(13, 148, 136, 0.25);
        }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(13, 148, 136, 0.3); }

        /* --- REGISTRATION BANNER --- */
        .register-banner {
            margin-top: 25px;
            padding: 15px;
            background: rgba(204, 251, 241, 0.6); 
            border: 1px dashed #5EEAD4;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .register-text { font-size: 12px; color: #0F766E; font-weight: 500; line-height: 1.4; }
        .register-text strong { display: block; font-size: 13px; font-weight: 800; color: #115E59; margin-bottom: 2px;}
        
        .btn-register {
            flex-shrink: 0; padding: 10px 16px; background: white; border: 1px solid #5EEAD4;
            border-radius: 8px; color: var(--primary); text-decoration: none; font-size: 12px;
            font-weight: 700; transition: all 0.3s; box-shadow: 0 2px 4px rgba(13, 148, 136, 0.1);
        }
        .btn-register:hover { 
            background: var(--primary); color: white; border-color: var(--primary); 
            box-shadow: 0 4px 10px rgba(13, 148, 136, 0.2); 
        }

        .back-link { 
            display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted); 
            text-decoration: none; font-size: 13px; font-weight: 600; margin-top: 25px; 
            transition: 0.2s; position: relative; z-index: 1;
        }
        .back-link:hover { color: var(--primary); }

        @media (max-width: 480px) {
            .login-container { padding: 30px 20px; }
            .register-banner { flex-direction: column; text-align: center; }
            .btn-register { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

    <div class="ambient-bg">
        <div class="shape cube"></div>
        <div class="shape ring"></div>
        <div class="shape pyramid"></div>
        <div class="shape sphere"></div>
    </div>

    <div class="login-container">
        <div class="header">
            <div class="logo-box"><i class="fas fa-chalkboard-teacher"></i></div>
            <h1>Institute Login</h1>
            <p>Access the Training Partner Portal</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off">
            <div class="form-group">
                <div class="label-flex">
                    <label>Assigned Username</label>
                </div>
                <div class="input-wrap">
                    <input type="text" name="username" class="form-control" placeholder="Enter TP username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required autofocus>
                    <i class="fas fa-user input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <div class="label-flex">
                    <label>Password</label>
                    <a href="tp-forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>
                <div class="input-wrap">
                    <input type="password" name="password" id="passwordField" class="form-control" placeholder="Enter password" required>
                    <i class="fas fa-lock input-icon"></i>
                    <button type="button" class="toggle-pw" onclick="togglePassword()" id="toggleBtn" aria-label="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" name="manual_login" class="btn-submit">Secure Login</button>
        </form>

        <div class="register-banner">
            <div class="register-text">
                <strong>New Institute?</strong>
                Apply to become an authorized Training Partner.
            </div>
            <a href="tp-register.php" class="btn-register">Register Here</a>
        </div>
    </div>

    <a href="../index.php" class="back-link"><i class="fas fa-arrow-left"></i> Return to Main Website</a>

    <script>
        function togglePassword() {
            const field = document.getElementById('passwordField');
            const btnIcon = document.querySelector('#toggleBtn i');
            
            if (field.type === 'password') {
                field.type = 'text';
                btnIcon.classList.remove('fa-eye');
                btnIcon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                btnIcon.classList.remove('fa-eye-slash');
                btnIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>