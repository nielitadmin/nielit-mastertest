<?php
session_name('NIELIT_FINANCE_SESSION');
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'finance') {
    header("Location: finance-dashboard.php");
    exit();
}

require_once __DIR__ . '/../../config/mailer.php';
// 🟢 NEW ARCHITECTURE: Centralized database connection
require_once __DIR__ . '/../../config/database.php';

$error = '';
$success = '';
$step = 1; // Default state: Ask for email

// Security check: If trying to access step 2 or 3 without an active reset session, kick back to step 1
if (isset($_POST['step']) && $_POST['step'] > 1 && !isset($_SESSION['fin_reset_email'])) {
    $_POST['step'] = 1;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step'])) {
    
    try {
        // $pdo is securely imported from database.php

        // ==========================================
        // STEP 1: VERIFY EMAIL & SEND OTP
        // ==========================================
        if ($_POST['step'] == 1) {
            $email = trim($_POST['email']);
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
                $step = 1;
            } else {
                // STRICT CHECK: Must be a finance account
                $stmt = $pdo->prepare("SELECT id, full_name, is_active FROM users WHERE email = ? AND role = 'finance'");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    if (!$user['is_active']) {
                        $error = "This finance account is disabled. Contact system administration.";
                        $step = 1;
                    } else {
                        // Generate 6-digit OTP
                        $otp = rand(100000, 999999);
                        $_SESSION['fin_reset_email'] = $email;
                        $_SESSION['fin_reset_otp'] = $otp;
                        $_SESSION['fin_reset_name'] = $user['full_name'];
                        
                        // Send OTP via Brevo Mailer (Navy Blue Theme)
                        $html_body = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #E2E8F0; border-radius: 16px; background: #FFFFFF;'>
                                <div style='text-align: center; margin-bottom: 20px;'>
                                    <h2 style='color: #1E3A8A; margin: 0;'>Finance Password Reset</h2>
                                    <p style='color: #64748B; font-size: 14px; margin-top: 5px;'>NIELIT Financial Department Portal</p>
                                </div>
                                <p style='color: #0F172A; font-size: 15px;'>Dear <strong>{$user['full_name']}</strong>,</p>
                                <p style='color: #475569; font-size: 15px; line-height: 1.6;'>A password reset was requested for your Finance Officer account. Here is your secure One-Time Password (OTP):</p>
                                
                                <div style='background: #EFF6FF; padding: 20px; border-radius: 12px; border: 1px dashed #1E3A8A; margin: 25px 0; text-align: center;'>
                                    <h1 style='margin: 0; color: #1E3A8A; font-size: 36px; letter-spacing: 5px;'>{$otp}</h1>
                                </div>

                                <p style='color: #DC2626; font-size: 13px; line-height: 1.5; text-align: center;'>
                                    <em>* Do not share this OTP with anyone. It is valid for this session only.</em>
                                </p>
                                <hr style='border: none; border-top: 1px solid #E2E8F0; margin: 25px 0;'>
                                <p style='color: #64748B; font-size: 13px; margin: 0; text-align: center;'>National Institute of Electronics & Information Technology, Bhubaneswar</p>
                            </div>
                        ";

                        if (sendNielitEmail($email, $user['full_name'], "NIELIT Finance: Password Reset OTP", $html_body)) {
                            $success = "A secure OTP has been sent to your registered email.";
                            $step = 2; // Move to OTP input
                        } else {
                            $error = "Failed to dispatch OTP email. Please verify mailer configuration.";
                            $step = 1;
                        }
                    }
                } else {
                    // Generic error to prevent email enumeration
                    $error = "If this email is registered to the Finance Dept, an OTP has been sent.";
                    $step = 1;
                }
            }
        }
        
        // ==========================================
        // STEP 2: VERIFY THE OTP
        // ==========================================
        elseif ($_POST['step'] == 2) {
            $entered_otp = trim($_POST['otp']);
            
            if ($entered_otp == $_SESSION['fin_reset_otp']) {
                $success = "OTP Verified! Please configure your new secure password.";
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
                $error = "Finance passwords must be at least 8 characters long for security.";
                $step = 3;
            } elseif ($new_password !== $confirm_password) {
                $error = "Passwords do not match.";
                $step = 3;
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $email_to_update = $_SESSION['fin_reset_email'];
                
                $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ?, password = ? WHERE email = ? AND role = 'finance'");
                $updateStmt->execute([$hashed_password, $new_password, $email_to_update]);
                
                // Clear reset session data
                unset($_SESSION['fin_reset_email']);
                unset($_SESSION['fin_reset_otp']);
                unset($_SESSION['fin_reset_name']);
                
                $success = "Finance password successfully updated! You may now securely log in.";
                $step = 4; // Final Success Screen
            }
        }

    } catch (PDOException $e) {
        $error = "System Database Offline. Please contact technical support.";
        error_log("Finance Password Reset DB error: " . $e->getMessage());
        $step = 1;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Password Reset - NIELIT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #1E3A8A;
            --primary-hover: #172554;
            --primary-bg: #DBEAFE;
            --text-dark: #0F172A;
            --text-muted: #64748B;
            --error-bg: #FEE2E2;
            --error-text: #DC2626;
            --success-bg: #ECFDF5;
            --success-text: #059669;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F1F5F9; margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; overflow: hidden; position: relative; }
        
        /* Professional Navy Abstract Background */
        .bg-shapes { position: absolute; inset: 0; z-index: -1; overflow: hidden; background: linear-gradient(135deg, #E2E8F0 0%, #F8FAFC 100%); }
        .circle1 { position: absolute; width: 600px; height: 600px; background: rgba(30, 64, 175, 0.05); border-radius: 50%; top: -20%; left: -10%; filter: blur(60px); }
        .circle2 { position: absolute; width: 500px; height: 500px; background: rgba(15, 23, 42, 0.05); border-radius: 50%; bottom: -20%; right: -10%; filter: blur(50px); }
        
        .login-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border: 1px solid white; border-radius: 24px; padding: 40px; width: 100%; max-width: 420px; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.15); animation: fadeUp 0.6s ease-out; position: relative; z-index: 1;}
        @keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        .logo-wrap { text-align: center; margin-bottom: 25px; }
        .icon-box { width: 64px; height: 64px; background: var(--primary); color: white; border-radius: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 15px; box-shadow: 0 10px 25px rgba(30, 64, 175, 0.3); }
        h1 { font-size: 22px; font-weight: 800; color: var(--text-dark); margin: 0 0 5px 0; letter-spacing: -0.5px;}
        p { color: var(--text-muted); font-size: 13px; margin: 0; font-weight: 500; line-height: 1.4;}

        .alert-box { border-radius: 12px; padding: 12px 14px; display: flex; align-items: flex-start; gap: 10px; margin-bottom: 20px; animation: popIn 0.4s ease; border: 1px solid transparent; }
        .alert-box.error { background: var(--error-bg); border-color: #FCA5A5; color: var(--error-text); }
        .alert-box.success { background: var(--success-bg); border-color: #A7F3D0; color: var(--success-text); }
        .alert-box i { font-size: 16px; margin-top: 2px; }
        .alert-text { font-size: 13px; font-weight: 600; line-height: 1.4; }
        @keyframes popIn { 0% { transform: scale(0.95); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 12px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
        .input-wrap { position: relative; }
        .input-wrap i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 16px; }
        
        .form-control { width: 100%; padding: 14px 16px 14px 45px; border: 1px solid #E2E8F0; border-radius: 12px; font-family: inherit; font-size: 14px; font-weight: 600; background: #F8FAFC; outline: none; transition: 0.3s; box-sizing: border-box; color: var(--text-dark);}
        .form-control:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px var(--primary-bg); }

        /* Specific style for OTP input */
        .otp-input { text-align: center; font-size: 24px !important; letter-spacing: 12px; padding-left: 16px !important; font-weight: 800 !important; color: var(--primary) !important; }

        .btn-submit { width: 100%; padding: 15px; background: var(--primary); color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 15px; font-family: inherit; cursor: pointer; transition: 0.3s; margin-top: 5px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 15px rgba(30, 64, 175, 0.25); }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(30, 64, 175, 0.35); }

        .back-link { position: absolute; top: 30px; left: 40px; display: inline-flex; align-items: center; gap: 8px; background: white; border: 1px solid #E2E8F0; padding: 10px 20px; border-radius: 12px; color: #0F172A; text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.3s; box-shadow: 0 2px 5px rgba(0,0,0,0.02); z-index: 10;}
        .back-link:hover { transform: translateX(-3px); border-color: var(--primary); color: var(--primary); }
        
        .success-state { text-align: center; padding: 10px 0; }
        .success-state i { font-size: 64px; color: var(--primary); margin-bottom: 20px; display: block; animation: popIn 0.5s ease; }
        
        @media (max-width: 480px) {
            .login-card { width: calc(100% - 32px); margin: 80px 16px 16px; padding: 30px 20px; }
            .back-link { top: 20px; left: 50%; transform: translateX(-50%); }
            .back-link:hover { transform: translate(-50%, -2px); }
        }
    </style>
</head>
<body>

    <div class="bg-shapes">
        <div class="circle1"></div>
        <div class="circle2"></div>
    </div>

    <a href="finance-login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>

    <div class="login-card">
        <div class="logo-wrap">
            <div class="icon-box"><i class="fas fa-key"></i></div>
            <h1>Password Recovery</h1>
            <p>
                <?php 
                    if ($step == 1) echo "Enter your registered finance email address.";
                    elseif ($step == 2) echo "We sent a secure 6-digit OTP to <br><b style='color: var(--text-dark); margin-top: 5px; display: block;'>" . htmlspecialchars($_SESSION['fin_reset_email']) . "</b>";
                    elseif ($step == 3) echo "Create a strong new finance password.";
                ?>
            </p>
        </div>

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
                <label>Registered Email Address</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" class="form-control" placeholder="finance@nielit.gov.in" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required autofocus>
                </div>
            </div>
            <button type="submit" class="btn-submit">Dispatch Secure OTP <i class="fas fa-paper-plane"></i></button>
        </form>
        
        <?php elseif ($step == 2): ?>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="step" value="2">
            <div class="form-group">
                <label style="text-align: center;">Enter 6-Digit Authorization Code</label>
                <div class="input-wrap">
                    <input type="text" name="otp" class="form-control otp-input" placeholder="------" maxlength="6" pattern="\d{6}" required autofocus autocomplete="one-time-code">
                </div>
            </div>
            <button type="submit" class="btn-submit">Verify Authorization <i class="fas fa-shield-check"></i></button>
        </form>

        <?php elseif ($step == 3): ?>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="step" value="3">
            <div class="form-group">
                <label>New Secure Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters" required autofocus>
                </div>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <div class="input-wrap">
                    <i class="fas fa-check-double"></i>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Retype password" required>
                </div>
            </div>
            <button type="submit" class="btn-submit">Save Credentials <i class="fas fa-save"></i></button>
        </form>

        <?php elseif ($step == 4): ?>
        <div class="success-state">
            <i class="fas fa-check-circle"></i>
            <h3 style="margin-bottom: 10px; color: var(--text-dark); font-size: 24px; font-weight: 800;">Access Restored!</h3>
            <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 30px; line-height: 1.5;"><?php echo htmlspecialchars($success); ?></p>
            <a href="finance-login.php" class="btn-submit" style="text-decoration: none;">
                Return to Login <i class="fas fa-sign-in-alt"></i>
            </a>
        </div>
        <?php endif; ?>
        
    </div>

</body>
</html>