<?php
session_name('NIELIT_ADMIN_SESSION');
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') {
    header("Location: admin-dashboard.php");
    exit();
}

require_once __DIR__ . '/../../config/mailer.php';
// 🟢 NEW ARCHITECTURE: Centralized database connection
require_once __DIR__ . '/../../config/database.php';

$error = '';
$success = '';
$step = 1; // Default state: Ask for email

// Security check: If trying to access step 2 or 3 without an active reset session, kick back to step 1
if (isset($_POST['step']) && $_POST['step'] > 1 && !isset($_SESSION['admin_reset_email'])) {
    $_POST['step'] = 1;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step'])) {
    
    try {
        // $pdo is securely imported from database.php, no hardcoded credentials needed!

        // ==========================================
        // STEP 1: VERIFY EMAIL & SEND OTP
        // ==========================================
        if ($_POST['step'] == 1) {
            $email = trim($_POST['email']);
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
                $step = 1;
            } else {
                // STRICT CHECK: Must be an admin account
                $stmt = $pdo->prepare("SELECT id, full_name, is_active FROM users WHERE email = ? AND role = 'admin'");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    if (!$user['is_active']) {
                        $error = "This administrator account is disabled. Contact superadmin.";
                        $step = 1;
                    } else {
                        // Generate 6-digit OTP
                        $otp = rand(100000, 999999);
                        $_SESSION['admin_reset_email'] = $email;
                        $_SESSION['admin_reset_otp'] = $otp;
                        $_SESSION['admin_reset_name'] = $user['full_name'];
                        
                        // Send OTP via Brevo Mailer
                        $html_body = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #E2E8F0; border-radius: 16px; background: #FFFFFF;'>
                                <div style='text-align: center; margin-bottom: 20px;'>
                                    <h2 style='color: #1D4ED8; margin: 0;'>Admin Password Reset</h2>
                                    <p style='color: #64748B; font-size: 14px; margin-top: 5px;'>NIELIT Central Management Console</p>
                                </div>
                                <p style='color: #0F172A; font-size: 15px;'>Dear <strong>{$user['full_name']}</strong>,</p>
                                <p style='color: #475569; font-size: 15px; line-height: 1.6;'>A password reset was requested for your Administrator account. Here is your highly secure One-Time Password (OTP):</p>
                                
                                <div style='background: #EFF6FF; padding: 20px; border-radius: 12px; border: 1px dashed #3B82F6; margin: 25px 0; text-align: center;'>
                                    <h1 style='margin: 0; color: #1D4ED8; font-size: 36px; letter-spacing: 5px;'>{$otp}</h1>
                                </div>

                                <p style='color: #DC2626; font-size: 13px; line-height: 1.5; text-align: center;'>
                                    <em>* Do not share this OTP with anyone. It is valid for this session only.</em>
                                </p>
                                <hr style='border: none; border-top: 1px solid #E2E8F0; margin: 25px 0;'>
                                <p style='color: #64748B; font-size: 13px; margin: 0; text-align: center;'>National Institute of Electronics & Information Technology, Bhubaneswar</p>
                            </div>
                        ";

                        if (sendNielitEmail($email, $user['full_name'], "NIELIT Admin: Password Reset OTP", $html_body)) {
                            $success = "A secure OTP has been sent to your administrator email.";
                            $step = 2; // Move to OTP input
                        } else {
                            $error = "Failed to dispatch OTP email. Please verify mailer configuration.";
                            $step = 1;
                        }
                    }
                } else {
                    // Generic error to prevent email enumeration
                    $error = "If this email is registered as an Admin, an OTP has been sent.";
                    $step = 1;
                }
            }
        }
        
        // ==========================================
        // STEP 2: VERIFY THE OTP
        // ==========================================
        elseif ($_POST['step'] == 2) {
            $entered_otp = trim($_POST['otp']);
            
            if ($entered_otp == $_SESSION['admin_reset_otp']) {
                $success = "OTP Verified! Please configure your new administrator password.";
                $step = 3; // Move to New Password input
            } else {
                $error = "Invalid OTP. Please verify the code sent to your email.";
                $step = 2; // Stay on OTP step
            }
        }
        
        // ==========================================
        // STEP 3: SAVE NEW PASSWORD
        // ==========================================
        elseif ($_POST['step'] == 3) {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (strlen($new_password) < 8) {
                $error = "Admin passwords must be at least 8 characters long for security.";
                $step = 3;
            } elseif ($new_password !== $confirm_password) {
                $error = "Passwords do not match.";
                $step = 3;
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $email_to_update = $_SESSION['admin_reset_email'];
                
                // Update both hash and raw password (if you still need legacy support)
                $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ?, password = ? WHERE email = ? AND role = 'admin'");
                $updateStmt->execute([$hashed_password, $new_password, $email_to_update]);
                
                // Clear reset session data
                unset($_SESSION['admin_reset_email']);
                unset($_SESSION['admin_reset_otp']);
                unset($_SESSION['admin_reset_name']);
                
                $success = "Administrator password successfully updated! You may now securely log in.";
                $step = 4; // Final Success Screen
            }
        }

    } catch (PDOException $e) {
        $error = "System Database Offline. Please contact technical support.";
        error_log("Admin Password Reset DB error: " . $e->getMessage());
        $step = 1;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Password Reset — NIELIT CBT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            /* Blue Admin Theme */
            --primary: #1D4ED8;        
            --primary-light: #3B82F6;  
            --primary-bg: #DBEAFE;     
            --text-dark: #0F172A;
            --text-muted: #475569;
            --bg-body: #F4F7FB;
            --surface: #FFFFFF;
            --border: #CBD5E1;
            --input-bg: #F8FAFC;
            --input-border: #E2E8F0;
            --error-bg: #FEE2E2;
            --error-text: #DC2626;
            --success-bg: #ECFDF5;
            --success-text: #059669;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 40px -10px rgba(29, 78, 216, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-dark); min-height: 100vh; display: flex; align-items: center; justify-content: center; overflow: hidden; background-color: var(--bg-body); }
        
        /* 3D Blue Ambient Background */
        .ambient-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; background: radial-gradient(circle at 50% 0%, #DCE6F1 0%, #E8F0F8 50%, #F4F7FB 100%); }
        .shape { position: absolute; background: linear-gradient(135deg, rgba(255, 255, 255, 0.6), rgba(59, 130, 246, 0.05)); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.7); box-shadow: 0 15px 35px rgba(29, 78, 216, 0.08); animation: float-3d 20s infinite linear; }
        .cube { width: 140px; height: 140px; border-radius: 28px; top: 15%; left: 10%; animation-duration: 28s; }
        .ring { width: 220px; height: 220px; border-radius: 50%; border: 35px solid rgba(255,255,255,0.3); top: 55%; right: 5%; animation-duration: 35s; animation-direction: reverse; background: transparent; }
        @keyframes float-3d { 0% { transform: translateY(0) rotateX(0deg) rotateY(0deg) rotateZ(0deg); } 50% { transform: translateY(-50px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); } 100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); } }
        
        .top-nav { position: absolute; top: 0; left: 0; width: 100%; padding: 20px 40px; z-index: 10; display: flex; justify-content: flex-start; }
        .btn-back-top { display: inline-flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.3s; box-shadow: var(--shadow-sm); }
        .btn-back-top:hover { background: var(--surface); color: var(--primary); transform: translateX(-3px); }

        .login-card { position: relative; z-index: 1; width: 100%; max-width: 420px; background: rgba(255, 255, 255, 0.85); border: 1px solid rgba(255, 255, 255, 1); border-radius: 24px; backdrop-filter: blur(24px); overflow: hidden; animation: fadeUp 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both; box-shadow: var(--shadow-lg); }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(30px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        
        .card-accent { height: 4px; background: linear-gradient(90deg, var(--primary), var(--primary-light)); }
        .card-head { padding: 35px 35px 15px; text-align: center; }
        .icon-wrap { width: 64px; height: 64px; border-radius: 18px; background: var(--primary-bg); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 15px; box-shadow: 0 8px 15px rgba(29, 78, 216, 0.1); }
        .card-title { font-size: 22px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; }
        .card-subtitle { font-size: 13px; color: var(--text-muted); font-weight: 500; padding: 0 10px; line-height: 1.4;}
        .card-body { padding: 0 35px 30px; }
        .sep { height: 1px; background: var(--border); margin-bottom: 20px; }

        .alert-box { border-radius: 10px; padding: 12px 14px; display: flex; align-items: flex-start; gap: 10px; margin-bottom: 20px; animation: popIn 0.4s ease; }
        .alert-box.error { background: var(--error-bg); border: 1px solid #FCA5A5; color: var(--error-text); }
        .alert-box.success { background: var(--success-bg); border: 1px solid #A7F3D0; color: var(--success-text); }
        .alert-box i { font-size: 16px; margin-top: 2px; }
        .alert-text { font-size: 13px; font-weight: 600; line-height: 1.4; }
        @keyframes popIn { 0% { transform: scale(0.95); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 8px; }
        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 14px; color: var(--text-muted); transition: color 0.3s; }
        .form-group input { width: 100%; padding: 14px 16px 14px 44px; background: var(--input-bg); border: 1px solid var(--input-border); border-radius: 10px; color: var(--text-dark); font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 600; outline: none; transition: all 0.3s; }
        .form-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-bg); background: var(--surface); }
        .form-group input:focus + .input-icon { color: var(--primary); }

        /* Specific style for OTP input */
        .otp-input { text-align: center; font-size: 24px !important; letter-spacing: 12px; padding-left: 16px !important; font-weight: 800 !important; color: var(--primary) !important; }

        .btn-submit { width: 100%; padding: 16px; background: var(--primary); color: white; border: none; border-radius: 12px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.3s; box-shadow: 0 8px 20px rgba(29, 78, 216, 0.25); display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-submit:hover { background: #1e3a8a; transform: translateY(-2px); box-shadow: 0 12px 25px rgba(29, 78, 216, 0.35); }
        
        .success-state { text-align: center; padding: 10px 0; }
        .success-state i { font-size: 64px; color: var(--primary); margin-bottom: 20px; display: block; animation: popIn 0.5s ease; }
    </style>
</head>
<body>

    <div class="ambient-bg">
        <div class="shape cube"></div>
        <div class="shape ring"></div>
    </div>

    <nav class="top-nav">
        <a href="admin-login.php" class="btn-back-top">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>
    </nav>

    <div class="login-card">
        <div class="card-accent"></div>

        <div class="card-head">
            <div class="icon-wrap"><i class="fas fa-user-shield"></i></div>
            <div class="card-title">Admin Password Recovery</div>
            <div class="card-subtitle">
                <?php 
                    if ($step == 1) echo "Enter your registered administrative email address.";
                    elseif ($step == 2) echo "We sent a secure 6-digit OTP to <br><b style='color: var(--text-dark); margin-top: 5px; display: block;'>" . htmlspecialchars($_SESSION['admin_reset_email']) . "</b>";
                    elseif ($step == 3) echo "Create a strong new administrator password.";
                ?>
            </div>
        </div>

        <div class="card-body">
            <div class="sep"></div>

            <?php if ($error): ?>
                <div class="alert-box error"><i class="fas fa-exclamation-circle"></i><span class="alert-text"><?php echo htmlspecialchars($error); ?></span></div>
            <?php endif; ?>
            <?php if ($success && $step != 4): ?>
                <div class="alert-box success"><i class="fas fa-check-circle"></i><span class="alert-text"><?php echo htmlspecialchars($success); ?></span></div>
            <?php endif; ?>

            <?php if ($step == 1): ?>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="step" value="1">
                <div class="form-group">
                    <label>Registered Admin Email</label>
                    <div class="input-wrap">
                        <input type="email" name="email" placeholder="Enter your Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required autofocus>
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Dispatch Secure OTP</button>
            </form>
            
            <?php elseif ($step == 2): ?>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="step" value="2">
                <div class="form-group">
                    <label style="text-align: center;">Enter 6-Digit Authorization Code</label>
                    <div class="input-wrap">
                        <input type="text" name="otp" class="otp-input" placeholder="------" maxlength="6" pattern="\d{6}" required autofocus autocomplete="one-time-code">
                    </div>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-shield-alt"></i> Verify Authorization</button>
            </form>

            <?php elseif ($step == 3): ?>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="step" value="3">
                <div class="form-group">
                    <label>New Secure Password</label>
                    <div class="input-wrap">
                        <input type="password" name="new_password" placeholder="Minimum 8 characters" required autofocus>
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="input-wrap">
                        <input type="password" name="confirm_password" placeholder="Retype password" required>
                        <i class="fas fa-check-double input-icon"></i>
                    </div>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save Credentials</button>
            </form>

            <?php elseif ($step == 4): ?>
            <div class="success-state">
                <i class="fas fa-check-circle"></i>
                <h3 style="margin-bottom: 10px; color: var(--text-dark); font-size: 24px; font-weight: 800;">Access Restored!</h3>
                <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 30px; line-height: 1.5;"><?php echo htmlspecialchars($success); ?></p>
                <a href="admin-login.php" class="btn-submit" style="text-decoration: none;">
                    <i class="fas fa-sign-in-alt"></i> Return to Console
                </a>
            </div>
            <?php endif; ?>
            
        </div>
    </div>

</body>
</html>