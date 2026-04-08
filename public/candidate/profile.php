<?php
session_start();

// Check if user is logged in and is candidate
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    header("Location: candidate-login.php");
    exit();
}

// 🟢 NEW ARCHITECTURE: Centralized database connection
require_once __DIR__ . '/../../config/database.php';

$error = '';
$success = '';

try {
    // $pdo is securely imported from database.php
    
    // Get current user data
    $stmt = $pdo->prepare("
        SELECT u.*, c.registration_number, c.date_of_birth, c.mobile, c.address, c.photo_url
        FROM users u
        LEFT JOIN candidates c ON u.id = c.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $mobile = trim($_POST['mobile']);
        $address = trim($_POST['address']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = [];
        
        // Update users table
        $updateUser = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
        $updateUser->execute([$full_name, $email, $_SESSION['user_id']]);
        
        // Update candidates table
        $check = $pdo->prepare("SELECT user_id FROM candidates WHERE user_id = ?");
        $check->execute([$_SESSION['user_id']]);
        
        $mobile_value = !empty($mobile) ? $mobile : null;
        $address_value = !empty($address) ? $address : null;
        
        if ($check->fetch()) {
            $updateCandidate = $pdo->prepare("UPDATE candidates SET mobile = ?, address = ? WHERE user_id = ?");
            $updateCandidate->execute([$mobile_value, $address_value, $_SESSION['user_id']]);
        }
        
        // Change password if requested
        if (!empty($current_password) && !empty($new_password)) {
            // Verify current password
            $passCheck = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $passCheck->execute([$_SESSION['user_id']]);
            $current_hash = $passCheck->fetchColumn();
            
            if (password_verify($current_password, $current_hash)) {
                if ($new_password == $confirm_password) {
                    if (strlen($new_password) >= 6) {
                        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $updatePass = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $updatePass->execute([$new_hash, $_SESSION['user_id']]);
                        $success = "Password changed successfully!";
                    } else {
                        $error = "New password must be at least 6 characters";
                    }
                } else {
                    $error = "New passwords do not match";
                }
            } else {
                $error = "Current password is incorrect";
            }
        } else {
            $success = "Profile updated successfully!";
        }
        
        // Refresh user data
        $stmt = $pdo->prepare("
            SELECT u.*, c.registration_number, c.date_of_birth, c.mobile, c.address, c.photo_url
            FROM users u
            LEFT JOIN candidates c ON u.id = c.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - NIELIT CBT</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
        }
        .navbar {
            background: #0047ab;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h2 {
            font-size: 20px;
        }
        .navbar .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
            margin-left: 10px;
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .profile-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .profile-header {
            background: #0047ab;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .profile-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .reg-number {
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 10px;
            font-size: 14px;
        }
        .profile-body {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #0047ab;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }
        .section h3 {
            color: #0047ab;
            margin-bottom: 20px;
        }
        .btn-update {
            background: #28a745;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .btn-update:hover {
            background: #218838;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .readonly-field {
            background: #f5f5f5;
            padding: 12px;
            border-radius: 5px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>🎓 NIELIT Bhubaneswar - My Profile</h2>
        <div class="nav-links">
            <a href="candidate-dashboard.php">Dashboard</a>
            <a href="my-exams.php">My Exams</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="profile-card">
            <div class="profile-header">
                <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                <div class="reg-number">Registration: <?php echo htmlspecialchars($user['registration_number'] ?? 'Pending'); ?></div>
            </div>
            
            <div class="profile-body">
                <?php if ($error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Mobile Number</label>
                            <input type="text" name="mobile" value="<?php echo htmlspecialchars($user['mobile'] ?: ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <div class="readonly-field"><?php echo !empty($user['date_of_birth']) ? date('d-m-Y', strtotime($user['date_of_birth'])) : 'Not provided'; ?></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?: ''); ?></textarea>
                    </div>
                    
                    <div class="section">
                        <h3>Change Password (Optional)</h3>
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" placeholder="Enter current password">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" placeholder="Min 6 characters">
                            </div>
                            
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" placeholder="Re-enter new password">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-update">Update Profile</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>