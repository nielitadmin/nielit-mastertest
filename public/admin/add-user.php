<?php
session_name('NIELIT_ADMIN_SESSION');
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: admin-login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($username) || empty($email) || empty($full_name) || empty($password) || empty($role)) {
            $error = "All core fields (Name, Username, Email, Password, Role) are mandatory.";
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            
            if ($check->fetch()) {
                $error = "The Username or Email is already registered in the system.";
            } else {
                $pdo->beginTransaction();
                
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, email, full_name, role, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$username, $password_hash, $email, $full_name, $role, $is_active]);
                $new_user_id = (int)$pdo->lastInsertId();
                
                if ($role === 'candidate') {
                    $mobile = trim($_POST['mobile'] ?? '');
                    $dob = $_POST['dob'] ?? null;
                    
                    if (empty($mobile) || empty($dob)) {
                        throw new Exception("Mobile number and Date of Birth are required for Candidate profiles.");
                    }

                    $reg_number = 'NIELIT' . date('Y') . str_pad($new_user_id, 5, '0', STR_PAD_LEFT);
                    
                    $c_stmt = $pdo->prepare("
                        INSERT INTO candidates (user_id, registration_number, date_of_birth, mobile)
                        VALUES (?, ?, ?, ?)
                    ");
                    $c_stmt->execute([$new_user_id, $reg_number, $dob, $mobile]);
                }
                
                $pdo->commit();
                header("Location: manage-users.php?msg=added");
                exit();
            }
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "System Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User - NIELIT Admin Console</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            var(--primary): #1D4ED8; var(--primary-light): #3B82F6; var(--primary-bg): #DBEAFE;     
            var(--text-dark): #0F172A; var(--text-muted): #64748B;
            var(--bg-body): #F4F7FB; var(--surface): #FFFFFF; var(--border): #E2E8F0;
            --success: #059669; --danger: #DC2626; --danger-bg: #FEE2E2;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F4F7FB; color: #0F172A; padding-bottom: 60px; }
        
        .top-nav { background: rgba(255, 255, 255, 0.9); padding: 15px 40px; display: flex; justify-content: space-between; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; border: 1px solid #E2E8F0; padding: 8px 16px; border-radius: 10px; color: #0F172A; text-decoration: none; font-weight: 600; font-size: 13px; }
        
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 28px; font-weight: 800; margin-bottom: 5px; }
        
        .form-card { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 40px rgba(29,78,216,0.1); }
        .section-title { font-size: 14px; font-weight: 800; color: #1D4ED8; text-transform: uppercase; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #E2E8F0; display: flex; align-items: center; gap: 10px; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 8px; }
        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #64748B; font-size: 14px; }
        .form-control { width: 100%; padding: 14px 16px 14px 45px; border-radius: 12px; border: 1px solid #E2E8F0; font-family: inherit; font-size: 14px; outline: none; transition: 0.3s; background: #F8FAFC;}
        .form-control:focus { border-color: #1D4ED8; box-shadow: 0 0 0 4px #DBEAFE; background: white;}
        select.form-control { cursor: pointer; appearance: none; }
        
        .candidate-fields { display: none; }
        .candidate-fields.show { display: grid; }
        
        .toggle-wrap { display: flex; align-items: center; justify-content: space-between; background: #F8FAFC; border: 1px solid #E2E8F0; padding: 15px 20px; border-radius: 12px; }
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #CBD5E1; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #059669; }
        input:checked + .slider:before { transform: translateX(24px); }
        
        .action-row { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; border-top: 1px solid #E2E8F0; padding-top: 25px; }
        .btn { padding: 14px 28px; border-radius: 12px; font-weight: 700; font-size: 14px; cursor: pointer; border: none; }
        .btn-cancel { background: white; border: 1px solid #E2E8F0; text-decoration: none; color: #0F172A; }
        .btn-submit { background: #1D4ED8; color: white; }
    </style>
</head>
<body>

    <nav class="top-nav">
        <a href="manage-users.php" class="btn-back"><i class="fas fa-arrow-left"></i> Directory</a>
        <div style="font-weight: 800; color: #1D4ED8;">NIELIT Admin Console</div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Create User Account</h1>
            <p style="color: #64748B;">Provision a new role within the ERP system.</p>
        </div>

        <?php if ($error): ?>
            <div style="background: #FEE2E2; color: #DC2626; padding: 15px; border-radius: 12px; margin-bottom: 20px;"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" class="form-card" autocomplete="off">
            
            <div class="section-title"><i class="fas fa-id-card"></i> Core Identity</div>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Full Legal Name / Institute Name *</label>
                    <div class="input-wrap">
                        <input type="text" name="full_name" class="form-control" required>
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>System Role *</label>
                    <div class="input-wrap">
                        <select name="role" id="roleSelect" class="form-control" required onchange="toggleCandidateFields()">
                            <option value="admin">System Administrator</option>
                            <option value="tp">Training Partner (TP)</option>
                            <option value="finance">Finance Officer</option>
                            <option value="coordinator">Assessment Coordinator</option>
                            <option value="candidate">Exam Candidate</option>
                        </select>
                        <i class="fas fa-user-shield input-icon" id="roleIcon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <div class="input-wrap">
                        <input type="email" name="email" class="form-control" required>
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>
            </div>

            <div id="candidateFields" class="form-grid candidate-fields">
                <div class="form-group">
                    <label>Mobile Number *</label>
                    <div class="input-wrap">
                        <input type="tel" name="mobile" id="mobileInput" class="form-control">
                        <i class="fas fa-mobile-alt input-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label>Date of Birth *</label>
                    <div class="input-wrap">
                        <input type="date" name="dob" id="dobInput" class="form-control" style="padding-left: 45px;">
                        <i class="fas fa-calendar-alt input-icon"></i>
                    </div>
                </div>
            </div>

            <div class="section-title"><i class="fas fa-key"></i> Authentication</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Login Username *</label>
                    <div class="input-wrap">
                        <input type="text" name="username" class="form-control" required>
                        <i class="fas fa-at input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Initial Password *</label>
                    <div class="input-wrap">
                        <input type="password" name="password" class="form-control" required>
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Account Status</label>
                    <div class="toggle-wrap">
                        <div style="font-weight: 700; font-size: 14px;">Active Account <br><small style="color: #64748B; font-size:11px;">User can log in immediately</small></div>
                        <label class="switch"><input type="checkbox" name="is_active" value="1" checked><span class="slider"></span></label>
                    </div>
                </div>
            </div>

            <div class="action-row">
                <a href="manage-users.php" class="btn btn-cancel">Cancel</a>
                <button type="submit" class="btn btn-submit">Provision Account</button>
            </div>
        </form>
    </div>

    <script>
        function toggleCandidateFields() {
            const role = document.getElementById('roleSelect').value;
            const candidateFields = document.getElementById('candidateFields');
            const mobileInput = document.getElementById('mobileInput');
            const dobInput = document.getElementById('dobInput');
            const roleIcon = document.getElementById('roleIcon');

            if (role === 'candidate') {
                candidateFields.classList.add('show');
                mobileInput.required = true; dobInput.required = true;
                roleIcon.className = 'fas fa-user-graduate input-icon';
            } else {
                candidateFields.classList.remove('show');
                mobileInput.required = false; dobInput.required = false;
                
                if(role === 'tp') roleIcon.className = 'fas fa-chalkboard-teacher input-icon';
                else if(role === 'finance') roleIcon.className = 'fas fa-rupee-sign input-icon';
                else if(role === 'coordinator') roleIcon.className = 'fas fa-calendar-alt input-icon';
                else roleIcon.className = 'fas fa-user-shield input-icon';
            }
        }
        window.onload = toggleCandidateFields;
    </script>
</body>
</html>