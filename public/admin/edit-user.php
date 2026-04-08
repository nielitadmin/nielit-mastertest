<?php
session_name('NIELIT_ADMIN_SESSION');
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: admin-login.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage-users.php");
    exit();
}

$user_id = $_GET['id'];

require_once __DIR__ . '/../../config/database.php';
// Include our central mailer engine!
require_once __DIR__ . '/../../config/mailer.php';

$error = '';
$success = '';

try {
    // Get user data
    $stmt = $pdo->prepare("
        SELECT u.*, c.registration_number, c.mobile
        FROM users u
        LEFT JOIN candidates c ON u.id = c.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: manage-users.php");
        exit();
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $is_active = isset($_POST['is_active']) ? true : false;
        $mobile = trim($_POST['mobile']);
        $new_password = $_POST['new_password'];
        
        // Track changes for email notifications
        $status_changed = ($is_active != $user['is_active']);
        $password_changed = !empty($new_password);
        
        // Check if the updated username is already taken by ANOTHER user
        $checkUsername = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $checkUsername->execute([$username, $user_id]);
        
        if ($checkUsername->fetch()) {
            $error = "The username '{$username}' is already taken by another account. Please choose a different one.";
        } else {
            $pdo->beginTransaction();
            
            // Update users table (Syncing both password fields for legacy support)
            if ($password_changed) {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, full_name = ?, email = ?, role = ?, is_active = ?, password_hash = ?, password = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$username, $full_name, $email, $role, $is_active, $password_hash, $new_password, $user_id]);
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, full_name = ?, email = ?, role = ?, is_active = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$username, $full_name, $email, $role, $is_active, $user_id]);
            }
            
            // Update candidates table if role is candidate
            if ($role == 'candidate') {
                $check = $pdo->prepare("SELECT user_id FROM candidates WHERE user_id = ?");
                $check->execute([$user_id]);
                
                if ($check->fetch()) {
                    $candStmt = $pdo->prepare("UPDATE candidates SET mobile = ? WHERE user_id = ?");
                    $candStmt->execute([$mobile ?: null, $user_id]);
                } else {
                    $reg_number = 'NIELIT' . date('Y') . str_pad($user_id, 5, '0', STR_PAD_LEFT);
                    $candStmt = $pdo->prepare("INSERT INTO candidates (user_id, registration_number, mobile) VALUES (?, ?, ?)");
                    $candStmt->execute([$user_id, $reg_number, $mobile ?: null]);
                }
            }
            
            $pdo->commit();
            $success = "User profile updated successfully!";

            // ====================================================================
            // 📧 ADMIN ACTION EMAIL NOTIFICATIONS
            // ====================================================================
            if ($password_changed || $status_changed) {
                $html_body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #E2E8F0; border-radius: 16px; background: #FFFFFF;'>
                        <h2 style='color: #1D4ED8; margin: 0 0 20px 0; text-align: center;'>Account Update Notice</h2>
                        <p style='color: #0F172A; font-size: 15px;'>Dear <strong>{$full_name}</strong>,</p>
                        <p style='color: #475569; font-size: 15px; line-height: 1.6;'>This is an automated notification to inform you that changes were made to your NIELIT portal account by a System Administrator.</p>
                ";

                if ($password_changed) {
                    $html_body .= "
                        <div style='background: #EFF6FF; padding: 15px; border-radius: 8px; border-left: 4px solid #1D4ED8; margin: 15px 0;'>
                            <strong>Password Reset:</strong> Your password has been changed. Your new temporary password is: <span style='background: #FFF; padding: 2px 6px; border: 1px solid #BFDBFE; border-radius: 4px; font-family: monospace;'>{$new_password}</span>
                        </div>
                    ";
                }

                if ($status_changed) {
                    $status_text = $is_active ? "<span style='color: #059669;'>Activated</span>" : "<span style='color: #DC2626;'>Suspended</span>";
                    $html_body .= "
                        <div style='background: #F8FAFC; padding: 15px; border-radius: 8px; border-left: 4px solid #64748B; margin: 15px 0;'>
                            <strong>Account Status:</strong> Your account has been <b>{$status_text}</b>.
                        </div>
                    ";
                }

                $html_body .= "
                        <p style='color: #64748B; font-size: 13px; margin-top: 30px; text-align: center;'>If you have questions regarding this update, please contact NIELIT Administration.</p>
                    </div>
                ";

                sendNielitEmail($email, $full_name, "NIELIT Security Alert: Account Updated", $html_body);
            }
            // ====================================================================
            
            // Refresh the data safely to reflect on the page
            $refreshStmt = $pdo->prepare("
                SELECT u.*, c.registration_number, c.mobile
                FROM users u
                LEFT JOIN candidates c ON u.id = c.user_id
                WHERE u.id = ?
            ");
            $refreshStmt->execute([$user_id]);
            $user = $refreshStmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | NIELIT Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Professional Admin White-Blue Theme */
            --primary: #1D4ED8;        --primary-hover: #1E40AF;
            --primary-bg: #DBEAFE;     --secondary: #0F172A;
            --success: #10B981;        --success-bg: #D1FAE5;
            --danger: #EF4444;         --danger-bg: #FEE2E2;
            --text-main: #0F172A;      --text-muted: #64748B;
            --bg-page: #F8FAFC;        --white: #FFFFFF;
            --border: #E2E8F0;         --radius-md: 12px; --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-page); color: var(--text-main); min-height: 100vh; overflow-x: hidden; padding-bottom: 50px; }

        /* --- 3D AMBIENT BACKGROUND --- */
        .ambient-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; overflow: hidden; background: radial-gradient(circle at 50% 0%, #DCE6F1 0%, #E8F0F8 50%, #F8FAFC 100%); perspective: 1000px; }
        .shape { position: absolute; background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(29,78,216,0.05)); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.6); box-shadow: 0 15px 35px rgba(29,78,216,0.08); animation: float-3d 25s infinite linear; }
        .cube { width: 140px; height: 140px; border-radius: 28px; top: 15%; left: 8%; }
        .sphere { width: 180px; height: 180px; border-radius: 50%; bottom: 15%; right: 10%; animation-duration: 35s; animation-direction: reverse; }
        @keyframes float-3d { 0% { transform: translateY(0) rotateX(0deg) rotateY(0deg) rotateZ(0deg); } 50% { transform: translateY(-40px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); } 100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); } }

        /* --- NAVBAR --- */
        .navbar { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(15px); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .nav-brand { display: flex; align-items: center; gap: 12px; }
        .logo-box { background: var(--secondary); color: white; width: 40px; height: 40px; border-radius: 10px; display: flex; justify-content: center; align-items: center; font-weight: 800; font-size: 20px; }
        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--secondary); line-height: 1.2; }
        .brand-text span { font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 1px;}

        /* --- CONTAINER & LAYOUT --- */
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; position: relative; z-index: 10; animation: fadeUp 0.5s ease-out forwards; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .header-actions { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--white); border: 1px solid var(--border); padding: 10px 20px; border-radius: 10px; color: var(--text-main); text-decoration: none; font-weight: 700; font-size: 14px; transition: 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .btn-back:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); transform: translateX(-3px); }
        .page-title { font-size: 28px; font-weight: 800; color: var(--text-main); }

        /* --- ALERTS --- */
        .alert { padding: 16px 20px; border-radius: var(--radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; border: 1px solid transparent; }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: #A7F3D0; }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: #FECACA; }

        /* --- FORM CARD --- */
        .form-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: var(--radius-lg); padding: 40px; box-shadow: 0 15px 35px rgba(29, 78, 216, 0.08); border: 1px solid var(--border); }
        
        .info-ribbon { background: var(--primary-bg); border-left: 4px solid var(--primary); padding: 15px 20px; border-radius: 0 8px 8px 0; margin-bottom: 30px; display: flex; gap: 30px; }
        .info-ribbon div { display: flex; flex-direction: column; }
        .info-ribbon span { font-size: 11px; font-weight: 700; color: var(--primary); text-transform: uppercase; }
        .info-ribbon strong { font-size: 16px; color: var(--text-main); font-weight: 800; }

        .form-section-title { font-size: 15px; font-weight: 800; color: var(--text-main); margin-bottom: 15px; padding-bottom: 8px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
        .form-section-title i { color: var(--primary); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .full-width { grid-column: 1 / -1; }
        
        .form-group label { font-size: 13px; font-weight: 700; color: var(--text-main); }
        .form-group input, .form-group select { background: #F8FAFC; border: 1px solid var(--border); padding: 14px 16px; border-radius: 10px; font-size: 14px; font-family: inherit; color: var(--text-main); transition: 0.3s; width: 100%; outline: none;}
        .form-group input:focus, .form-group select:focus { background: var(--white); border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-bg); }

        /* Modern Toggle Switch */
        .toggle-wrapper { display: flex; align-items: center; justify-content: space-between; padding: 15px; background: #F8FAFC; border: 1px solid var(--border); border-radius: 10px; }
        .toggle-label { font-size: 14px; font-weight: 700; color: var(--text-main); display: flex; flex-direction: column; }
        .toggle-label small { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        
        .switch { position: relative; display: inline-block; width: 50px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #CBD5E1; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .slider { background-color: var(--success); }
        input:checked + .slider:before { transform: translateX(22px); }

        .form-actions { display: flex; gap: 15px; margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border); }
        .btn-save { flex: 2; background: var(--primary); color: white; padding: 14px; border: none; border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(29, 78, 216, 0.2); }
        .btn-save:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(29, 78, 216, 0.3); }
        .btn-cancel { flex: 1; background: var(--white); color: var(--text-main); padding: 14px; text-decoration: none; border-radius: 12px; font-weight: 700; text-align: center; border: 1px solid var(--border); transition: 0.3s; }
        .btn-cancel:hover { background: #F1F5F9; border-color: #CBD5E1; }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .header-actions { flex-direction: column; align-items: flex-start; }
            .form-actions { flex-direction: column; }
            .form-card { padding: 25px; }
        }
    </style>
</head>
<body>

    <div class="ambient-canvas">
        <div class="shape cube"></div>
        <div class="shape sphere"></div>
    </div>

    <nav class="navbar">
        <div class="nav-brand">
            <div class="logo-box"><i class="fas fa-shield-alt"></i></div>
            <div class="brand-text">
                <h2>NIELIT Admin</h2>
                <span>Central Management</span>
            </div>
        </div>
    </nav>

    <div class="container">
        
        <div class="header-actions">
            <a href="manage-users.php" class="btn-back"><i class="fas fa-arrow-left"></i> Directory</a>
            <h1 class="page-title">Edit User Profile</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="form-card">
            
            <div class="info-ribbon">
                <div>
                    <span>System User ID</span>
                    <strong>#<?php echo htmlspecialchars($user['id']); ?></strong>
                </div>
                <div>
                    <span>Registration Hash</span>
                    <strong><?php echo htmlspecialchars($user['registration_number'] ?: 'PENDING_GENERATION'); ?></strong>
                </div>
            </div>

            <form method="POST" action="">
                
                <h3 class="form-section-title"><i class="fas fa-user-circle"></i> Account Identification</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Username</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required placeholder="Create a unique username">
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>

                <h3 class="form-section-title"><i class="fas fa-address-card"></i> Contact & Security</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Mobile Number</label>
                        <input type="text" name="mobile" value="<?php echo htmlspecialchars($user['mobile'] ?: ''); ?>" placeholder="+91">
                    </div>
                    <div class="form-group">
                        <label>Update Password</label>
                        <input type="password" name="new_password" placeholder="Leave blank to keep current">
                    </div>
                </div>

                <h3 class="form-section-title"><i class="fas fa-cogs"></i> System Privileges</h3>
                <div class="form-grid" style="margin-bottom: 0;">
                    <div class="form-group">
                        <label>Access Role</label>
                        <select name="role">
                            <option value="candidate" <?php echo $user['role'] == 'candidate' ? 'selected' : ''; ?>>Candidate / Student</option>
                            <option value="tp" <?php echo $user['role'] == 'tp' ? 'selected' : ''; ?>>Training Partner (Institute)</option>
                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>System Administrator</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="opacity:0; user-select:none;">Status</label> <div class="toggle-wrapper">
                            <div class="toggle-label">
                                Account Active
                                <small>Allow user to log in</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="is_active" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="manage-users.php" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>