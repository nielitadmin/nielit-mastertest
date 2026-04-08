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
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_request'])) {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            // Check if this email belongs to an active Training Partner
            $stmt = $pdo->prepare("SELECT id, full_name, username FROM users WHERE email = ? AND role = 'tp'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Generate a secure, temporary reset token and store in session
                $reset_token = bin2hex(random_bytes(16));
                $_SESSION['pwd_reset_email'] = $email;
                $_SESSION['pwd_reset_token'] = $reset_token;
                $_SESSION['pwd_reset_time'] = time();

                // 🟢 =======================================================
                // SEND RESET EMAIL VIA BREVO SMTP
                // =======================================================
                require_once __DIR__ . '/../../config/mailer.php'; 
                
                $reset_link = "http://localhost:8080/nielit-bbsr-mock/public/tp/tp-reset-password.php?token=" . $reset_token;
                
                $subject = "Password Reset Request - NIELIT TP Portal";
                $htmlBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden;'>
                    <div style='background: #0D9488; padding: 20px; text-align: center; color: white;'>
                        <h2 style='margin: 0;'>Password Reset Request</h2>
                    </div>
                    <div style='padding: 30px; background: #FAFAFA; color: #333;'>
                        <p style='font-size: 16px;'>Hello <strong>{$user['full_name']}</strong>,</p>
                        <p style='font-size: 15px; line-height: 1.6;'>We received a request to reset the password for your Training Partner account (Username: <strong>{$user['username']}</strong>).</p>
                        
                        <div style='margin: 35px 0; text-align: center;'>
                            <a href='{$reset_link}' style='background: #0D9488; color: white; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; box-shadow: 0 4px 6px rgba(13, 148, 136, 0.2);'>Reset My Password</a>
                        </div>
                        
                        <p style='font-size: 13px; color: #64748B;'><em>If you did not request this, please ignore this email. This link will expire in 15 minutes.</em></p>
                    </div>
                    <div style='background: #F1F5F9; padding: 15px; text-align: center; font-size: 12px; color: #64748B;'>
                        &copy; " . date('Y') . " National Institute of Electronics & Information Technology.
                    </div>
                </div>";

                try {
                    if (function_exists('sendNielitEmail')) {
                        sendNielitEmail($email, $user['full_name'], $subject, $htmlBody);
                    } else {
                        $headers = "MIME-Version: 1.0" . "\r\n";
                        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                        $headers .= "From: NIELIT TP Portal <noreply@yourdomain.com>" . "\r\n";
                        @mail($email, $subject, $htmlBody, $headers);
                    }
                } catch (Exception $mailEx) {
                    error_log("Failed to send Password Reset email to TP: " . $mailEx->getMessage());
                }
                // =======================================================

                $success = "A password reset link has been sent to your email address.";
            } else {
                // To prevent email enumeration hackers, you can make this generic. 
                // But for development, showing the explicit error is helpful.
                $error = "No Training Partner account found with that email address.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Institute Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0D9488;
            --primary-hover: #0F766E;
            --primary-light: #14B8A6;
            --primary-bg: #CCFBF1;
            --bg-page: #F8FAFC;
            --surface: rgba(255, 255, 255, 0.85);
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border: rgba(226, 232, 240, 0.8);
            --shadow-lg: 0 20px 40px -10px rgba(13, 148, 136, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        body { 
            background: var(--bg-page); display: flex; align-items: center; 
            justify-content: center; height: 100vh; flex-direction: column; 
            padding: 20px; overflow: hidden; position: relative;
        }

        /* 🟢 BEAUTIFUL AMBIENT 3D BACKGROUND (Teal Theme) */
        .ambient-bg {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1; overflow: hidden; pointer-events: none;
            background: radial-gradient(circle at 50% 0%, #E0F2FE 0%, #CCFBF1 50%, #F8FAFC 100%);
            perspective: 1000px;
        }

        .shape {
            position: absolute;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.6), rgba(13, 148, 136, 0.08));
            backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.7);
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
            width: 100%; max-width: 420px; background: var(--surface); 
            border-radius: 24px; box-shadow: var(--shadow-lg); 
            border: 1px solid rgba(255, 255, 255, 1); padding: 40px; 
            position: relative; backdrop-filter: blur(24px); 
            animation: fadeUp 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both; z-index: 1;
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
        .logo-box { width: 60px; height: 60px; background: var(--primary-bg); color: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 15px; box-shadow: 0 8px 15px rgba(13, 148, 136, 0.15);}
        .header h1 { font-size: 24px; font-weight: 800; color: var(--text-main); margin-bottom: 5px; }
        .header p { font-size: 14px; color: var(--text-muted); font-weight: 500; line-height: 1.4; }

        .alert-error { background: #FEF2F2; color: #DC2626; padding: 12px 15px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 20px; border: 1px solid #FECACA; text-align: center; }
        .alert-success { background: #F0FDFA; color: #0F766E; padding: 15px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 20px; border: 1px solid #99F6E4; text-align: center; line-height: 1.4; }

        /* --- STANDARD FORM --- */
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
        
        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; transition: 0.3s;}
        
        .form-control { 
            width: 100%; padding: 14px 16px 14px 45px; border: 1px solid var(--border); 
            border-radius: 12px; font-size: 14px; font-weight: 500; outline: none; 
            transition: 0.3s; background: #F8FAFC; font-family: inherit;
        }
        .form-control:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 3px var(--primary-bg); }
        .input-wrap:focus-within .input-icon { color: var(--primary); }

        .btn-submit { 
            width: 100%; background: var(--primary); color: white; border: none; 
            padding: 15px; border-radius: 12px; font-size: 15px; font-weight: 800; 
            cursor: pointer; transition: 0.3s; margin-top: 10px; font-family: inherit;
            box-shadow: 0 6px 15px rgba(13, 148, 136, 0.25); display: flex; justify-content: center; align-items: center; gap: 8px;
        }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(13, 148, 136, 0.3); }

        .back-link { 
            display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted); 
            text-decoration: none; font-size: 13px; font-weight: 600; margin-top: 25px; 
            transition: 0.2s; position: relative; z-index: 1;
        }
        .back-link:hover { color: var(--primary); }

        @media (max-width: 480px) {
            .login-container { padding: 30px 20px; }
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
            <div class="logo-box"><i class="fas fa-unlock-alt"></i></div>
            <h1>Reset Password</h1>
            <p>Enter your registered official email address to receive a secure reset link.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-success">
                <i class="fas fa-envelope-open-text" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                <?php echo $success; ?>
            </div>
        <?php else: ?>
            <form method="POST" action="" autocomplete="off">
                <div class="form-group">
                    <label>Official Email Address</label>
                    <div class="input-wrap">
                        <input type="email" name="email" class="form-control" placeholder="contact@institute.com" required autofocus>
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>

                <button type="submit" name="reset_request" class="btn-submit"><i class="fas fa-paper-plane"></i> Send Reset Link</button>
            </form>
        <?php endif; ?>
    </div>

    <a href="tp-login.php" class="back-link"><i class="fas fa-arrow-left"></i> Return to Login Portal</a>

</body>
</html>