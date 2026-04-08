<?php
session_name('NIELIT_CANDIDATE_SESSION');
session_start();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 'admin') {
        header("Location: admin-dashboard.php");
    } else {
        header("Location: candidate-dashboard.php");
    }
    exit();
}

// 🟢 NEW ARCHITECTURE: Centralized database connection
require_once __DIR__ . '/../../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // $pdo is securely imported from database.php, persistent connection is already handled there!
        
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $mobile = trim($_POST['mobile']);
        $dob = $_POST['dob'];
        
        if ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters!";
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            
            if ($check->fetch()) {
                $error = "Username or email already exists!";
            } else {
                $pdo->beginTransaction();
                
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, email, full_name, role, is_active) 
                    VALUES (?, ?, ?, ?, 'candidate', true) RETURNING id
                ");
                $stmt->execute([$username, $password_hash, $email, $full_name]);
                $user_id = $stmt->fetchColumn();
                
                $reg_number = 'NIELIT' . date('Y') . str_pad($user_id, 5, '0', STR_PAD_LEFT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO candidates (user_id, registration_number, date_of_birth, mobile) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $reg_number, $dob, $mobile]);
                
                $pdo->commit();
                
                // =========================================================================
                // 📧 FIRE THE WELCOME EMAIL USING OUR BREVO ENGINE
                // =========================================================================
                require_once __DIR__ . '/../../config/mailer.php';
                
                $login_url = "http://localhost:8080/nielit-bbsr-mock/public/candidate/candidate-login.php";
                
                $html_body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #E2E8F0; border-radius: 16px; background: #FFFFFF;'>
                        <div style='text-align: center; margin-bottom: 20px;'>
                            <h2 style='color: #059669; margin: 0;'>Welcome to NIELIT!</h2>
                            <p style='color: #64748B; font-size: 14px; margin-top: 5px;'>Official Candidate Portal</p>
                        </div>
                        
                        <p style='color: #0F172A; font-size: 15px;'>Dear <strong>{$full_name}</strong>,</p>
                        <p style='color: #475569; font-size: 15px; line-height: 1.6;'>Your candidate profile has been successfully registered on the NIELIT Centralized Exam System.</p>
                        
                        <div style='background: #ECFDF5; padding: 20px; border-radius: 12px; border: 1px solid #A7F3D0; margin: 25px 0;'>
                            <p style='margin: 0 0 10px 0; color: #065F46; font-size: 13px; font-weight: bold; text-transform: uppercase;'>Your Secure Registration Details</p>
                            <p style='margin: 0 0 8px 0; color: #0F172A; font-size: 15px;'><strong>Registration ID:</strong> <span style='background: #FFFFFF; padding: 3px 8px; border-radius: 4px; border: 1px solid #A7F3D0; color: #059669; font-weight: bold;'>{$reg_number}</span></p>
                            <p style='margin: 0 0 8px 0; color: #0F172A; font-size: 15px;'><strong>Username:</strong> {$username}</p>
                            <p style='margin: 0; color: #0F172A; font-size: 14px; color: #64748B;'><strong>Password:</strong> <em>(The password you created during registration)</em></p>
                        </div>

                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='{$login_url}' style='background: #059669; color: #FFFFFF; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: bold; font-size: 15px; display: inline-block; box-shadow: 0 4px 6px rgba(5, 150, 105, 0.2);'>Login to Dashboard</a>
                        </div>
                        
                        <hr style='border: none; border-top: 1px solid #E2E8F0; margin: 25px 0;'>
                        <p style='color: #64748B; font-size: 13px; margin: 0; text-align: center;'>National Institute of Electronics & Information Technology, Bhubaneswar<br>Ministry of Electronics & IT, Govt. of India</p>
                    </div>
                ";

                // Trigger the email!
                sendNielitEmail($email, $full_name, "Your NIELIT Registration is Complete ({$reg_number})", $html_body);
                // =========================================================================

                $success = $reg_number;
            }
        }
        
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "System Error. Please try again later. " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Registration — NIELIT CBT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Professional Light Theme Colors */
            --primary: #1D4ED8;        
            --candidate: #059669;      /* Emerald Green Accent */
            --candidate-light: #10B981;
            --candidate-bg: #D1FAE5;
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
            --shadow-lg: 0 20px 40px -10px rgba(5, 150, 105, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
            background-color: var(--bg-body);
            padding: 60px 20px 20px; /* Reduced padding */
        }

        /* --- 3D MOVING BACKGROUND --- */
        .ambient-bg {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1; overflow: hidden; pointer-events: none;
            background: radial-gradient(circle at 50% 0%, #DCE6F1 0%, #E8F0F8 50%, #F4F7FB 100%);
            perspective: 1000px;
        }

        .shape {
            position: absolute;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.7), rgba(5, 150, 105, 0.05));
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 15px 35px rgba(5, 150, 105, 0.08), inset 0 0 20px rgba(255, 255, 255, 0.8);
            animation: float-3d 20s infinite linear;
        }

        .cube { width: 140px; height: 140px; border-radius: 28px; top: 15%; left: 8%; animation-duration: 28s; }
        .ring { width: 220px; height: 220px; border-radius: 50%; border: 35px solid rgba(255,255,255,0.4); top: 55%; right: 5%; animation-duration: 35s; animation-direction: reverse; background: transparent; }
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
            padding: 15px 30px; z-index: 10; display: flex; justify-content: flex-start;
        }
        
        .btn-back-top {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px);
            border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px;
            color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 13px;
            transition: all 0.3s; box-shadow: var(--shadow-sm);
        }
        .btn-back-top:hover {
            background: var(--surface); color: var(--candidate);
            transform: translateX(-3px); box-shadow: var(--shadow-md);
        }

        /* --- CARD --- */
        .register-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 520px; /* Reduced from 600px */
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 1);
            border-radius: 20px; /* Smaller radius */
            backdrop-filter: blur(24px);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            animation: fadeUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .card-accent {
            height: 4px;
            background: linear-gradient(90deg, var(--candidate), var(--candidate-light));
        }

        /* --- HEAD --- */
        .card-head {
            padding: 20px 30px 10px; /* Tighter padding */
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .icon-wrap {
            width: 48px; /* Reduced from 60px */
            height: 48px;
            flex-shrink: 0;
            border-radius: 12px;
            background: var(--candidate-bg);
            color: var(--candidate);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 8px 15px rgba(5, 150, 105, 0.1);
        }

        .head-text .card-title {
            font-size: 18px; /* Slightly smaller */
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 2px;
            letter-spacing: -0.5px;
        }

        .head-text .card-subtitle {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* --- BODY --- */
        .card-body {
            padding: 0 30px 20px; /* Tighter bottom padding */
        }

        .sep {
            height: 1px;
            background: var(--border);
            margin-bottom: 15px; /* Tighter margin */
        }

        /* --- ALERTS --- */
        .error-box {
            background: var(--error-bg);
            border: 1px solid #FCA5A5;
            border-radius: 10px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-5px); }
            40% { transform: translateX(5px); }
            60% { transform: translateX(-3px); }
            80% { transform: translateX(3px); }
        }

        .error-icon { color: var(--error-text); font-size: 16px; }
        .error-box .msg { font-size: 12px; color: var(--error-text); font-weight: 600; line-height: 1.3; }

        /* --- SUCCESS STATE --- */
        .success-state {
            text-align: center;
            padding: 10px 0;
        }

        .success-icon {
            font-size: 50px;
            color: var(--candidate);
            margin-bottom: 10px;
            display: block;
            animation: pop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes pop {
            from { transform: scale(0); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }

        .success-title { font-size: 20px; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; }
        .success-desc { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.5; font-weight: 500; }

        .reg-number-box {
            background: var(--candidate-bg);
            border: 1px dashed var(--candidate-light);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .reg-label { font-size: 11px; font-weight: 700; color: var(--candidate); letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 6px; }
        .reg-number { font-size: 22px; font-weight: 800; color: var(--text-dark); letter-spacing: 1px; }
        .reg-hint { font-size: 11px; color: var(--text-muted); margin-top: 8px; font-weight: 500; }

        .btn-go-login {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 12px; background: var(--candidate); color: white;
            border: none; border-radius: 10px; font-size: 14px; font-weight: 700;
            text-decoration: none; transition: all 0.3s; box-shadow: 0 6px 15px rgba(5, 150, 105, 0.2);
        }

        .btn-go-login:hover { background: #065F46; transform: translateY(-2px); }

        /* --- SECTION LABEL --- */
        .section-label {
            font-size: 10px; /* Reduced */
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px; /* Tighter */
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-label::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }

        /* --- FORM --- */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px; /* Tighter gap between columns */
        }

        .form-group { margin-bottom: 12px; /* Tighter vertical gap */ }

        .form-group label {
            display: block;
            font-size: 12px; /* Reduced from 13px */
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .input-wrap { position: relative; }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            color: var(--text-muted);
            pointer-events: none;
            transition: color 0.3s;
        }

        .form-group input {
            width: 100%;
            padding: 10px 14px 10px 36px; /* Tighter padding */
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 8px; /* Tighter border radius */
            color: var(--text-dark);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px; /* Slightly smaller text */
            font-weight: 500;
            outline: none;
            transition: all 0.3s;
            -webkit-appearance: none;
        }

        .form-group input::placeholder { color: #94A3B8; }

        .form-group input:focus {
            border-color: var(--candidate);
            box-shadow: 0 0 0 3px var(--candidate-bg);
            background: var(--surface);
        }

        .form-group input:focus + .input-icon,
        .input-wrap:focus-within .input-icon {
            color: var(--candidate);
        }

        /* Password toggle */
        .toggle-pw {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-muted);
            transition: color 0.3s;
            padding: 0;
            outline: none;
        }
        .toggle-pw:hover { color: var(--candidate); }

        /* --- SUBMIT --- */
        .btn-submit {
            width: 100%;
            padding: 12px; /* Tighter padding */
            background: var(--candidate);
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 5px;
            transition: all 0.3s;
            box-shadow: 0 6px 15px rgba(5, 150, 105, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: #065F46;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(5, 150, 105, 0.3);
        }

        .btn-submit:active { transform: translateY(0); }

        /* --- FOOTER --- */
        .card-footer {
            padding: 15px 30px; /* Reduced from 20px 40px */
            background: rgba(248, 250, 252, 0.8);
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .login-link {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .login-link a {
            color: var(--candidate);
            text-decoration: none;
            font-weight: 700;
            transition: color 0.3s;
        }
        .login-link a:hover { text-decoration: underline; }

        .secure-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* --- NIELIT TAG --- */
        .nielit-tag {
            position: fixed;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
            white-space: nowrap;
        }

        @media (max-width: 600px) {
            .top-nav { padding: 15px; justify-content: center; }
            .register-card { width: calc(100% - 20px); margin: 0 10px; }
            .card-head, .card-body { padding-left: 20px; padding-right: 20px; }
            .card-footer { padding-left: 20px; padding-right: 20px; flex-direction: column; gap: 8px; text-align: center; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
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
        <a href="candidate-login.php" class="btn-back-top">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>
    </nav>

    <div class="register-card">
        <div class="card-accent"></div>

        <div class="card-head">
            <div class="icon-wrap"><i class="fas fa-user-edit"></i></div>
            <div class="head-text">
                <div class="card-title">Candidate Registration</div>
                <div class="card-subtitle">NIELIT Centralized Exam Portal</div>
            </div>
        </div>

        <div class="card-body">
            <div class="sep"></div>

            <?php if ($error): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-triangle error-icon"></i>
                <span class="msg"><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="success-state">
                <i class="fas fa-check-circle success-icon"></i>
                <div class="success-title">Registration Successful!</div>
                <div class="success-desc">Your account has been securely created. We have also sent your confirmation details to your email address.</div>

                <div class="reg-number-box">
                    <div class="reg-label">Your Registration ID</div>
                    <div class="reg-number"><?php echo htmlspecialchars($success); ?></div>
                    <div class="reg-hint"><i class="fas fa-info-circle"></i> Use this ID as your username</div>
                </div>

                <a href="candidate-login.php" class="btn-go-login">
                    <i class="fas fa-sign-in-alt"></i> Proceed to Login
                </a>
            </div>

            <?php else: ?>
            <form method="POST" action="" autocomplete="off">

                <div class="section-label">Personal Information</div>

                <div class="form-group">
                    <label>Full Legal Name</label>
                    <div class="input-wrap">
                        <input type="text" name="full_name" required placeholder="As per Valid ID Card"
                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Mobile Number</label>
                        <div class="input-wrap">
                            <input type="tel" name="mobile" required placeholder="10-digit number" pattern="[0-9]{10}"
                                   value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>">
                            <i class="fas fa-mobile-alt input-icon"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <div class="input-wrap">
                            <input type="date" name="dob" required
                                   value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>">
                            <i class="fas fa-calendar-alt input-icon"></i>
                        </div>
                    </div>
                </div>

                <div class="section-label">Account Details</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Username</label>
                        <div class="input-wrap">
                            <input type="text" name="username" required placeholder="Choose a unique ID"
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            <i class="fas fa-id-badge input-icon"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-wrap">
                            <input type="email" name="email" required placeholder="name@example.com"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-wrap">
                            <input type="password" name="password" id="pw1" required placeholder="Min. 6 chars">
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="toggle-pw" onclick="togglePw('pw1','tb1')" id="tb1" aria-label="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <div class="input-wrap">
                            <input type="password" name="confirm_password" id="pw2" required placeholder="Re-enter password">
                            <i class="fas fa-check-circle input-icon" id="match-icon"></i>
                            <button type="button" class="toggle-pw" onclick="togglePw('pw2','tb2')" id="tb2" aria-label="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>

            </form>
            <?php endif; ?>
        </div>

        <div class="card-footer">
            <?php if (!$success): ?>
            <span class="login-link">Already registered? <a href="candidate-login.php">Sign in</a></span>
            <?php else: ?>
            <span class="login-link">Check your email for confirmation.</span>
            <?php endif; ?>
            <div class="secure-badge">
                <i class="fas fa-shield-alt"></i> Data Secured
            </div>
        </div>
    </div>

    <div class="nielit-tag">National Institute of Electronics &amp; Information Technology, Bhubaneswar &nbsp;·&nbsp; &copy; <?php echo date('Y'); ?></div>

    <script>
        // Password visibility toggle
        function togglePw(fieldId, btnId) {
            const f = document.getElementById(fieldId);
            const b = document.querySelector('#' + btnId + ' i');
            if (f.type === 'password') { 
                f.type = 'text'; 
                b.classList.remove('fa-eye');
                b.classList.add('fa-eye-slash');
            } else { 
                f.type = 'password'; 
                b.classList.remove('fa-eye-slash');
                b.classList.add('fa-eye');
            }
        }

        // Live password match indicator
        const pw1 = document.getElementById('pw1');
        const pw2 = document.getElementById('pw2');
        const matchIcon = document.getElementById('match-icon');
        
        if (pw2) {
            pw2.addEventListener('input', () => {
                if (pw1.value && pw2.value) {
                    const isMatch = pw1.value === pw2.value;
                    
                    pw2.style.borderColor = isMatch ? 'var(--candidate)' : 'var(--error-text)';
                    pw2.style.boxShadow = isMatch 
                        ? '0 0 0 3px var(--candidate-bg)' 
                        : '0 0 0 3px var(--error-bg)';
                    
                    matchIcon.style.color = isMatch ? 'var(--candidate)' : 'var(--error-text)';
                    matchIcon.className = isMatch ? 'fas fa-check-circle input-icon' : 'fas fa-times-circle input-icon';
                    
                } else {
                    pw2.style.borderColor = '';
                    pw2.style.boxShadow = '';
                    matchIcon.style.color = '';
                    matchIcon.className = 'fas fa-check-circle input-icon';
                }
            });
        }
    </script>

</body>
</html>