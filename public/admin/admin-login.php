<?php
session_name('NIELIT_ADMIN_SESSION');
session_start();

// Only redirect if already logged in completely
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] == 'admin') {
    header("Location: admin-dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ============================================================================
    // NEW ARCHITECTURE: Import centralized database connection
    // Path assumes this file is in: /public/admin/admin-login.php
    // ============================================================================
    require_once __DIR__ . '/../../config/database.php';
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // --- CAPTCHA VERIFICATION LOGIC ---
    $user_captcha = isset($_POST['captcha_answer']) ? (int)$_POST['captcha_answer'] : 0;
    $real_captcha = isset($_SESSION['captcha_result']) ? (int)$_SESSION['captcha_result'] : -1;

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } elseif ($user_captcha !== $real_captcha) {
        $error = "Incorrect math verification. Please try again.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' AND is_active = true");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                
                // Set Admin Session Variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['login_time'] = time();
                
                // Update last login timestamp in DB
                $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update->execute([$user['id']]);
                
                // DIRECT REDIRECT TO DASHBOARD
                header("Location: admin-dashboard.php");
                exit();

            } else {
                $error = "Invalid admin credentials or account disabled.";
            }
        } catch (PDOException $e) {
            $error = "System Database Offline. Please contact the technical team.";
            error_log("Admin Login DB error: " . $e->getMessage());
        }
    }
}

// Generate new Captcha for page load
$num1 = rand(1, 10);
$num2 = rand(1, 10);
$_SESSION['captcha_result'] = $num1 + $num2;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — NIELIT CBT System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            /* Professional Light Theme Colors */
            --primary: #1D4ED8;        /* Deep Blue */
            --primary-light: #3B82F6;  /* Bright Blue */
            --primary-bg: #DBEAFE;     /* Soft Blue */
            --text-dark: #0F172A;
            --text-muted: #475569;
            --bg-body: #F4F7FB;
            --surface: #FFFFFF;
            --border: #CBD5E1;
            --input-bg: #F8FAFC;
            --input-border: #E2E8F0;
            --error-bg: #FEE2E2;
            --error-text: #DC2626;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 40px -10px rgba(29, 78, 216, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            background-color: var(--bg-body);
        }

        /* --- 3D MOVING BACKGROUND (BLUE TINTED) --- */
        .ambient-bg {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1; overflow: hidden; pointer-events: none;
            background: radial-gradient(circle at 50% 0%, #DCE6F1 0%, #E8F0F8 50%, #F4F7FB 100%);
            perspective: 1000px;
        }

        .shape {
            position: absolute;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.6), rgba(59, 130, 246, 0.05));
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 15px 35px rgba(29, 78, 216, 0.08), inset 0 0 20px rgba(255, 255, 255, 0.8);
            animation: float-3d 20s infinite linear;
        }

        .cube { width: 140px; height: 140px; border-radius: 28px; top: 15%; left: 10%; animation-duration: 28s; }
        .ring { width: 220px; height: 220px; border-radius: 50%; border: 35px solid rgba(255,255,255,0.3); top: 55%; right: 5%; animation-duration: 35s; animation-direction: reverse; background: transparent; box-shadow: 0 15px 35px rgba(29, 78, 216, 0.05); }
        .pyramid { width: 90px; height: 90px; border-radius: 16px; bottom: 15%; left: 20%; animation-duration: 22s; }
        .sphere { width: 180px; height: 180px; border-radius: 50%; top: 8%; right: 12%; animation-duration: 40s; }

        @keyframes float-3d {
            0% { transform: translateY(0) rotateX(0deg) rotateY(0deg) rotateZ(0deg); }
            50% { transform: translateY(-50px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); }
            100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); }
        }

        /* --- TOP NAV / BACK BUTTON --- */
        .top-nav {
            position: absolute; top: 0; left: 0; width: 100%;
            padding: 20px 40px; z-index: 10; display: flex; justify-content: flex-start;
        }
        
        .btn-back-top {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px);
            border: 1px solid var(--border); padding: 10px 20px; border-radius: 12px;
            color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 14px;
            transition: all 0.3s; box-shadow: var(--shadow-sm);
        }
        .btn-back-top:hover {
            background: var(--surface); color: var(--primary);
            transform: translateX(-3px); box-shadow: var(--shadow-md);
        }

        /* --- LOGIN CARD --- */
        .login-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(255, 255, 255, 1);
            border-radius: 24px;
            backdrop-filter: blur(24px);
            overflow: hidden;
            animation: fadeUp 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
            box-shadow: var(--shadow-lg);
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Top accent line */
        .card-accent {
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
        }

        /* --- HEADER --- */
        .card-head {
            padding: 40px 40px 20px;
            text-align: center;
        }

        .icon-wrap {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(29, 78, 216, 0.1);
        }

        .card-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .card-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* --- BODY --- */
        .card-body {
            padding: 0 40px 30px;
        }

        .sep {
            height: 1px;
            background: var(--border);
            margin-bottom: 25px;
        }

        /* --- ERROR BOX --- */
        .error-box {
            background: var(--error-bg);
            border: 1px solid #FCA5A5;
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%      { transform: translateX(-5px); }
            40%      { transform: translateX(5px); }
            60%      { transform: translateX(-3px); }
            80%      { transform: translateX(3px); }
        }

        .error-icon { color: var(--error-text); font-size: 18px; }
        .error-text { font-size: 13px; color: var(--error-text); font-weight: 600; line-height: 1.4; }

        /* --- FORM --- */
        .form-group {
            margin-bottom: 20px;
        }

        /* 🆕 Flexbox for Label + Forgot Password Link */
        .label-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .label-flex label {
            margin-bottom: 0; /* Override default margin */
        }

        .forgot-link {
            font-size: 12px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            transition: color 0.3s;
        }

        .forgot-link:hover {
            color: var(--primary-light);
            text-decoration: underline;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: var(--text-muted);
            pointer-events: none;
            transition: color 0.3s;
        }

        .form-group input:not([type="number"]) {
            width: 100%;
            padding: 14px 16px 14px 44px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            color: var(--text-dark);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            outline: none;
            transition: all 0.3s;
        }

        .form-group input::placeholder {
            color: #94A3B8;
        }

        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-bg);
            background: var(--surface);
        }

        .form-group input:focus + .input-icon,
        .input-wrap:focus-within .input-icon {
            color: var(--primary);
        }

        /* Toggle password */
        .toggle-pw {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: var(--text-muted);
            transition: color 0.3s;
            padding: 0;
            outline: none;
        }

        .toggle-pw:hover, .toggle-pw:focus { color: var(--primary); }

        /* --- CAPTCHA STYLE --- */
        .captcha-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: var(--input-bg);
            border: 1px dashed var(--border);
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .captcha-wrap:focus-within {
            border-color: var(--primary);
            background: var(--surface);
        }

        .captcha-text {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-dark);
        }

        .captcha-wrap input {
            width: 60px;
            padding: 10px;
            text-align: center;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            outline: none;
            transition: all 0.3s;
        }

        .captcha-wrap input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-bg);
        }

        /* --- SUBMIT BUTTON --- */
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s;
            box-shadow: 0 8px 20px rgba(29, 78, 216, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            background: #1e3a8a;
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(29, 78, 216, 0.35);
        }

        .btn-submit:active { transform: translateY(0); }

        /* --- FOOTER --- */
        .card-footer {
            padding: 20px 40px;
            background: rgba(248, 250, 252, 0.8);
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .secure-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .secure-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #10B981;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
            animation: blink 2s ease-in-out infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* --- NIELIT TAG --- */
        .nielit-tag {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
        }

        @media (max-width: 480px) {
            .top-nav { padding: 15px; justify-content: center; }
            .login-card { width: calc(100% - 32px); margin: 80px 16px 16px; }
            .card-head, .card-body { padding-left: 24px; padding-right: 24px; }
            .card-footer { padding-left: 24px; padding-right: 24px; flex-direction: column; gap: 10px; text-align: center; }
            .nielit-tag { display: none; }
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

    <nav class="top-nav">
        <a href="/index.php" class="btn-back-top">
            <i class="fas fa-arrow-left"></i> Return to Portals
        </a>
    </nav>

    <div class="login-card">
        <div class="card-accent"></div>

        <div class="card-head">
            <div class="icon-wrap"><i class="fas fa-user-shield"></i></div>
            <div class="card-title">Admin Console</div>
            <div class="card-subtitle">Secure Access Required</div>
        </div>

        <div class="card-body">
            <div class="sep"></div>

            <?php if ($error): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle error-icon"></i>
                <span class="error-text"><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label>Admin Username</label>
                    <div class="input-wrap">
                        <input type="text" name="username"
                               placeholder="Enter your assigned username"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               required autofocus>
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <div class="label-flex">
                        <label>Password</label>
                        <a href="admin-forgot-password.php" class="forgot-link">Forgot Password?</a>
                    </div>
                    <div class="input-wrap">
                        <input type="password" name="password" id="passwordField"
                               placeholder="Enter your password"
                               required>
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="toggle-pw" onclick="togglePassword()" id="toggleBtn" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Verification</label>
                    <div class="captcha-wrap">
                        <div class="captcha-text">
                            <i class="fas fa-robot" style="color: var(--text-muted); font-size: 16px;"></i>
                            <span>Solve: <strong><?php echo $num1; ?> + <?php echo $num2; ?> = ?</strong></span>
                        </div>
                        <input type="number" name="captcha_answer" placeholder="?" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> Secure Login
                </button>
            </form>
        </div>

        <div class="card-footer">
            <div class="secure-badge">
                <i class="fas fa-shield-alt" style="color: var(--text-muted);"></i> SSL Encrypted
            </div>
            <div class="secure-badge">
                <span class="secure-dot"></span> System Online
            </div>
        </div>
    </div>

    <div class="nielit-tag">
        NIELIT, Bhubaneswar &nbsp;·&nbsp; &copy; <?php echo date('Y'); ?>
    </div>

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