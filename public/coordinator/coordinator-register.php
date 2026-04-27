<?php
session_name('NIELIT_COORD_SESSION');
session_start();

// If already logged in, redirect to Coordinator dashboard
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] == 'coordinator') {
    header("Location: coordinator-dashboard.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/mailer.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Basic Validation
    if (empty($fullName) || empty($email) || empty($username) || empty($password) || empty($confirmPassword)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Username or Email is already registered.";
            } else {
                // Hash the password securely
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new coordinator (is_active = 0 so admin has to approve them)
                $insert = $pdo->prepare("INSERT INTO users (username, email, full_name, role, password, password_hash, is_active) VALUES (?, ?, ?, 'coordinator', ?, ?, 0)");
                $insert->execute([$username, $email, $fullName, $password, $hashedPassword]);
                
                $success = true;

                // SEND CONFIRMATION EMAIL TO THE NEW COORDINATOR
                $subject = "Coordinator Application Received - NIELIT";
                $html_body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 25px; border: 1px solid #E2E8F0; border-radius: 12px; background: #ffffff;'>
                        <div style='text-align: center; border-bottom: 2px solid #F5F3FF; padding-bottom: 15px; margin-bottom: 20px;'>
                            <h2 style='color: #7C3AED; margin: 0;'>Application Received</h2>
                            <p style='color: #64748B; font-size: 14px; margin-top: 5px;'>NIELIT Administrator Portal</p>
                        </div>
                        <p style='color: #0F172A; font-size: 15px;'>Dear <strong>{$fullName}</strong>,</p>
                        <p style='color: #475569; font-size: 15px; line-height: 1.6;'>Thank you for registering as a Coordinator on the NIELIT Assessment Portal.</p>
                        <p style='color: #475569; font-size: 15px; line-height: 1.6;'>Your application has been received and is currently <strong>pending approval</strong> by a System Administrator.</p>
                        <div style='background: #F8FAFC; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #E2E8F0;'>
                            <p style='margin: 5px 0; color: #0F172A;'><strong>Requested Username:</strong> {$username}</p>
                            <p style='margin: 5px 0; color: #0F172A;'><strong>Account Status:</strong> <span style='color: #D97706; font-weight: bold;'>Pending Review ⏳</span></p>
                        </div>
                        <p style='color: #475569; font-size: 15px; line-height: 1.6;'>You will receive another email notification as soon as your account has been verified and activated.</p>
                        <hr style='border: none; border-top: 1px solid #E2E8F0; margin: 25px 0;'>
                        <p style='color: #94A3B8; font-size: 12px; margin: 0; text-align: center;'>This is an automated message. Please do not reply to this email.</p>
                    </div>
                ";
                sendNielitEmail($email, $fullName, $subject, $html_body);
            }
        } catch (PDOException $e) {
            $error = "Database error. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinator Registration - NIELIT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* 🟢 FIX 1: Changed alignment to prevent top cut-off on short screens */
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; margin: 0; display: flex; align-items: flex-start; justify-content: center; min-height: 100vh; overflow-x: hidden; position: relative; padding: 80px 20px 40px 20px; box-sizing: border-box;}
        
        .bg-shapes { position: fixed; inset: 0; z-index: -1; overflow: hidden; background: linear-gradient(135deg, #F5F3FF 0%, #EDE9FE 100%); }
        .circle1 { position: absolute; width: 600px; height: 600px; background: rgba(124, 58, 237, 0.08); border-radius: 50%; top: -10%; left: -10%; filter: blur(60px); }
        .circle2 { position: absolute; width: 500px; height: 500px; background: rgba(139, 92, 246, 0.08); border-radius: 50%; bottom: -10%; right: -10%; filter: blur(50px); }
        
        /* 🟢 FIX 2: Increased max-width to 550px and added margin: auto for perfect centering */
        .register-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border: 1px solid white; border-radius: 24px; padding: 40px; width: 100%; max-width: 550px; margin: auto; box-shadow: 0 25px 50px -12px rgba(124, 58, 237, 0.15); animation: fadeUp 0.6s ease-out; z-index: 10; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        .logo-wrap { text-align: center; margin-bottom: 30px; }
        .icon-box { width: 60px; height: 60px; background: #7C3AED; color: white; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 15px; box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3); }
        h1 { font-size: 24px; font-weight: 800; color: #0F172A; margin: 0 0 5px 0; letter-spacing: -0.5px;}
        p { color: #64748B; font-size: 14px; margin: 0; font-weight: 500; }

        .alert { padding: 14px 18px; border-radius: 12px; font-size: 14px; font-weight: 600; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #FEE2E2; color: #DC2626; border: 1px solid #FCA5A5; }

        .form-group { margin-bottom: 20px; }
        .label-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .label-flex label { margin-bottom: 0; display: block; font-size: 12px; font-weight: 800; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .input-wrap { position: relative; }
        .input-wrap i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 14px; transition: 0.3s;}
        .form-control { width: 100%; padding: 14px 16px 14px 42px; border: 1px solid #E2E8F0; border-radius: 12px; font-family: inherit; font-size: 14px; background: #F8FAFC; outline: none; transition: 0.3s; box-sizing: border-box; font-weight: 500;}
        .form-control:focus { border-color: #7C3AED; background: white; box-shadow: 0 0 0 4px #EDE9FE; }
        .input-wrap:focus-within i { color: #7C3AED; }

        .toggle-pw { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 14px; color: #94A3B8; transition: color 0.3s; padding: 0; outline: none; }
        .toggle-pw:hover, .toggle-pw:focus { color: #7C3AED; }

        .btn-submit { width: 100%; padding: 16px; background: #7C3AED; color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 15px; font-family: inherit; cursor: pointer; transition: 0.3s; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25); text-decoration: none;}
        .btn-submit:hover { background: #6D28D9; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(124, 58, 237, 0.35); }

        /* 🟢 FIX 3: Adjusted Back Button so it doesn't overlap on scroll */
        .floating-back-btn { position: absolute; top: 20px; left: 30px; display: inline-flex; align-items: center; gap: 8px; color: #1E293B; text-decoration: none; font-size: 14px; font-weight: 600; background: #FFFFFF; padding: 10px 18px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04); border: 1px solid #E2E8F0; transition: all 0.2s ease; z-index: 100; }
        .floating-back-btn:hover { background: #F8FAFC; border-color: #CBD5E1; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
        .floating-back-btn:hover i { transform: translateX(-4px); }

        .login-banner { margin-top: 25px; padding: 15px; background: rgba(237, 233, 254, 0.6); border: 1px dashed #A78BFA; border-radius: 12px; text-align: center; font-size: 14px; color: #6D28D9; font-weight: 500; }
        .login-banner a { color: #7C3AED; font-weight: 800; text-decoration: none; transition: 0.2s;}
        .login-banner a:hover { color: #4C1D95; text-decoration: underline; }

        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }

        .success-screen { text-align: center; padding: 20px 0; animation: fadeUp 0.6s ease-out; }
        .success-icon { font-size: 65px; color: #10B981; margin-bottom: 20px; animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .success-screen h2 { color: #0F172A; font-size: 26px; font-weight: 800; margin-bottom: 10px; }
        .success-screen p { color: #64748B; font-size: 15px; margin-bottom: 25px; line-height: 1.6;}
        .status-badge { background: #FFFBEB; border: 1px solid #FDE68A; padding: 12px 20px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 25px; font-weight: 700; font-size: 14px; color: #B45309; }
        .email-note { font-size: 13px; background: #F8FAFC; padding: 15px; border-radius: 12px; border: 1px solid #E2E8F0; color: #475569; margin-bottom: 30px;}
        @keyframes scaleIn { 0% { transform: scale(0); } 100% { transform: scale(1); } }

        @media (max-width: 600px) {
            body { padding-top: 70px; } /* Room for back button on mobile */
            .floating-back-btn { top: 15px; left: 15px; padding: 8px 12px; font-size: 13px; }
            .register-card { padding: 30px 20px; }
            .form-row { flex-direction: column; gap: 0; }
        }
    </style>
</head>
<body>

    <div class="bg-shapes">
        <div class="circle1"></div>
        <div class="circle2"></div>
    </div>

    <a href="../index.php" class="floating-back-btn">
        <i class="fas fa-arrow-left"></i> Return Home
    </a>

    <div class="register-card">
        
        <?php if ($success): ?>
            <div class="success-screen">
                <div class="success-icon"><i class="fas fa-check-circle"></i></div>
                <h2>Application Submitted!</h2>
                <p>Thank you, <strong><?php echo htmlspecialchars($fullName); ?></strong>. Your registration details have been received securely.</p>
                
                <div class="status-badge">
                    <i class="fas fa-hourglass-half"></i> Pending Admin Approval
                </div>
                
                <div class="email-note">
                    <i class="fas fa-envelope" style="color: #7C3AED; margin-right: 5px;"></i>
                    We've sent a confirmation email to <strong><?php echo htmlspecialchars($email); ?></strong>. You will be notified once your account is active.
                </div>

                <a href="coordinator-login.php" class="btn-submit">Return to Login <i class="fas fa-arrow-right"></i></a>
            </div>
        <?php else: ?>

            <div class="logo-wrap">
                <div class="icon-box"><i class="fas fa-user-plus"></i></div>
                <h1>Coordinator Application</h1>
                <p>Register for Logistics & Scheduling Access</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <div class="label-flex"><label>Full Name</label></div>
                    <div class="input-wrap">
                        <i class="fas fa-user"></i>
                        <input type="text" name="full_name" class="form-control" placeholder="Enter your full name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="label-flex"><label>Official Email Address</label></div>
                    <div class="input-wrap">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="name@nielit.gov.in" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="label-flex"><label>Desired Username</label></div>
                    <div class="input-wrap">
                        <i class="fas fa-user-tag"></i>
                        <input type="text" name="username" class="form-control" placeholder="Choose a unique username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <div class="label-flex"><label>Password</label></div>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="pw1" class="form-control" placeholder="Min. 8 characters" required>
                            <button type="button" class="toggle-pw" onclick="togglePassword('pw1', this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="label-flex"><label>Confirm Password</label></div>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="confirm_password" id="pw2" class="form-control" placeholder="Repeat password" required>
                            <button type="button" class="toggle-pw" onclick="togglePassword('pw2', this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Submit Application <i class="fas fa-paper-plane"></i></button>
            </form>

            <div class="login-banner">
                Already have an approved account? <a href="coordinator-login.php">Login Here</a>
            </div>
            
        <?php endif; ?>

    </div>

    <script>
        function togglePassword(fieldId, btn) {
            const field = document.getElementById(fieldId);
            const icon = btn.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>