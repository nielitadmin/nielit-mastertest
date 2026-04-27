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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $institute_name = trim($_POST['institute_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Basic Validation
    if (empty($institute_name) || empty($email) || empty($username) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = "This Username or Email is already registered.";
            } else {
                // Securely hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert the new Training Partner into the database
                // Note: We set is_active = true so you can immediately log in and test it!
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (username, password, password_hash, full_name, email, role, is_active) 
                    VALUES (?, ?, ?, ?, ?, 'tp', true)
                ");
                
                if ($insertStmt->execute([$username, $password, $hashed_password, $institute_name, $email])) {
                    
                    // 🟢 =======================================================
                    // SEND WELCOME EMAIL VIA BREVO SMTP
                    // =======================================================
                    require_once __DIR__ . '/../../config/mailer.php'; 
                    
                    $subject = "Welcome to the NIELIT Training Partner Network!";
                    $htmlBody = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden;'>
                        <div style='background: #0D9488; padding: 20px; text-align: center; color: white;'>
                            <h2 style='margin: 0;'>Institute Registration Successful</h2>
                        </div>
                        <div style='padding: 30px; background: #FAFAFA; color: #333;'>
                            <p style='font-size: 16px;'>Hello <strong>{$institute_name}</strong>,</p>
                            <p style='font-size: 15px; line-height: 1.6;'>Your application to become an authorized NIELIT Training Partner has been received and your account is now active. You can log in to the portal to manage your batches, schedule exams, and track candidate progress.</p>
                            
                            <div style='background: #FFFFFF; border-left: 4px solid #0D9488; padding: 15px; margin: 25px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.05);'>
                                <p style='margin: 0 0 5px 0; font-size: 13px; color: #64748B; text-transform: uppercase;'>Your Official TP Username:</p>
                                <p style='margin: 0; font-size: 18px; font-weight: bold; color: #0F172A;'>{$username}</p>
                            </div>
                            
                            <p style='font-size: 14px; color: #64748B;'><em>Please keep your credentials secure. If you ever forget your password, you can reset it from the login page.</em></p>
                            
                            <div style='margin-top: 30px; text-align: center;'>
                                <a href='https://test.nielitbhubaneswar.in/tp/tp-login.php' style='background: #0D9488; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>Access TP Portal</a>
                            </div>
                        </div>
                        <div style='background: #F1F5F9; padding: 15px; text-align: center; font-size: 12px; color: #64748B;'>
                            &copy; " . date('Y') . " National Institute of Electronics & Information Technology.
                        </div>
                    </div>";

                    try {
                        if (function_exists('sendNielitEmail')) {
                            sendNielitEmail($email, $institute_name, $subject, $htmlBody);
                        } else {
                            $headers = "MIME-Version: 1.0" . "\r\n";
                            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                            $headers .= "From: NIELIT TP Portal <noreply@yourdomain.com>" . "\r\n";
                            @mail($email, $subject, $htmlBody, $headers);
                        }
                    } catch (Exception $mailEx) {
                        error_log("Failed to send Welcome email to TP: " . $mailEx->getMessage());
                    }
                    // =======================================================

                    $success = "Institute Registration Successful! Please check your email inbox.";
                    // Clear POST data so form is empty on success
                    $_POST = [];
                } else {
                    $error = "Registration failed due to a system error.";
                }
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
    <title>Institute Registration - NIELIT</title>
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
        
        body { 
            background: var(--bg-page); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            flex-direction: column; 
            padding: 40px 20px;
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

        /* --- REGISTER CARD --- */
        .register-container {
            width: 100%; max-width: 600px; /* Wider for 2 columns */
            background: var(--surface); 
            border-radius: 24px;
            box-shadow: var(--shadow-lg); 
            border: 1px solid rgba(255, 255, 255, 1);
            padding: 40px; 
            position: relative; 
            backdrop-filter: blur(24px); /* Glass blur */
            animation: fadeUp 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
            z-index: 1;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .register-container::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 6px;
            background: linear-gradient(90deg, var(--primary-light), var(--primary));
        }

        .header { text-align: center; margin-bottom: 30px; }
        .logo-box { width: 60px; height: 60px; background: var(--primary-bg); color: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 15px; box-shadow: 0 8px 15px rgba(13, 148, 136, 0.15);}
        .header h1 { font-size: 24px; font-weight: 800; color: var(--text-main); margin-bottom: 5px; }
        .header p { font-size: 14px; color: var(--text-muted); font-weight: 500; }

        .alert-error { background: #FEF2F2; color: #DC2626; padding: 15px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 20px; border: 1px solid #FECACA; display: flex; align-items: center; gap: 10px;}
        .alert-success { background: #F0FDFA; color: #0F766E; padding: 15px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 20px; border: 1px solid #99F6E4; display: flex; align-items: center; justify-content: space-between; gap: 10px; }

        /* --- FORM GRID --- */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group { margin-bottom: 18px; }
        .form-group.full-width { grid-column: 1 / -1; }
        
        .form-group label { display: block; font-size: 13px; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
        
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

        .btn-submit { 
            grid-column: 1 / -1;
            width: 100%; background: var(--primary); color: white; border: none; 
            padding: 16px; border-radius: 12px; font-size: 15px; font-weight: 800; 
            cursor: pointer; transition: 0.3s; margin-top: 10px; font-family: inherit;
            box-shadow: 0 6px 15px rgba(13, 148, 136, 0.25);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(13, 148, 136, 0.3); }

        .back-link { 
            display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted); 
            text-decoration: none; font-size: 13px; font-weight: 600; margin-top: 25px; 
            transition: 0.2s; position: relative; z-index: 1;
        }
        .back-link:hover { color: var(--primary); }

        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; gap: 0; }
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

    <div class="register-container">
        <div class="header">
            <div class="logo-box"><i class="fas fa-building"></i></div>
            <h1>Institute Registration</h1>
            <p>Apply to become an authorized NIELIT Training Partner.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert-success">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle" style="font-size: 20px;"></i> 
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <a href="tp-login.php" style="background: var(--primary); color: white; padding: 8px 16px; border-radius: 8px; font-weight: 800; text-decoration: none; font-size: 12px; box-shadow: 0 2px 4px rgba(13, 148, 136, 0.2);">Login &rarr;</a>
            </div>
        <?php else: ?>

            <form method="POST" action="" autocomplete="off" class="form-grid">
                
                <div class="form-group full-width">
                    <label>Registered Institute Name</label>
                    <div class="input-wrap">
                        <input type="text" name="institute_name" class="form-control" placeholder="e.g. Apex Tech Academy" value="<?php echo isset($_POST['institute_name']) ? htmlspecialchars($_POST['institute_name']) : ''; ?>" required autofocus>
                        <i class="fas fa-university input-icon"></i>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Official Email Address</label>
                    <div class="input-wrap">
                        <input type="email" name="email" class="form-control" placeholder="contact@institute.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Desired TP Username</label>
                    <div class="input-wrap">
                        <input type="text" name="username" class="form-control" placeholder="Create a unique username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        <i class="fas fa-user-tag input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Create Password</label>
                    <div class="input-wrap">
                        <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="input-wrap">
                        <input type="password" name="confirm_password" class="form-control" placeholder="Retype password" required>
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>

                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Submit Application</button>
            </form>

        <?php endif; ?>
    </div>

    <a href="tp-login.php" class="back-link"><i class="fas fa-arrow-left"></i> Return to Login Portal</a>

</body>
</html>