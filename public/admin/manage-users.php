<?php
session_name('NIELIT_ADMIN_SESSION');
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: admin-login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/mailer.php';

$message = '';
$error = '';

try {
    // ====================================================================
    // 1. HANDLE BULK DELETION (NEW)
    // ====================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete') {
        if (!empty($_POST['selected_users']) && is_array($_POST['selected_users'])) {
            
            $ids_to_delete = [];
            // Sanitize IDs and prevent the admin from deleting themselves
            foreach ($_POST['selected_users'] as $id) {
                if (is_numeric($id) && $id != $_SESSION['user_id']) {
                    $ids_to_delete[] = (int)$id;
                }
            }

            if (!empty($ids_to_delete)) {
                try {
                    $pdo->beginTransaction();
                    
                    // Create an IN clause e.g., (?, ?, ?) based on count
                    $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));

                    // Fetch user details FIRST so we can email them
                    $getUserStmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id IN ($placeholders)");
                    $getUserStmt->execute($ids_to_delete);
                    $usersToDelete = $getUserStmt->fetchAll(PDO::FETCH_ASSOC);

                    // CASCADE DELETE PROCESS
                    $stmt1 = $pdo->prepare("DELETE FROM candidate_responses WHERE registration_id IN (SELECT id FROM exam_registrations WHERE candidate_id IN ($placeholders))");
                    $stmt1->execute($ids_to_delete);
                    
                    $stmt2 = $pdo->prepare("DELETE FROM exam_results WHERE registration_id IN (SELECT id FROM exam_registrations WHERE candidate_id IN ($placeholders))");
                    $stmt2->execute($ids_to_delete);
                    
                    $stmt3 = $pdo->prepare("DELETE FROM exam_registrations WHERE candidate_id IN ($placeholders)");
                    $stmt3->execute($ids_to_delete);
                    
                    $stmt4 = $pdo->prepare("DELETE FROM candidates WHERE user_id IN ($placeholders)");
                    $stmt4->execute($ids_to_delete);
                    
                    $stmt5 = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
                    $stmt5->execute($ids_to_delete);

                    $pdo->commit();

                    // FIRE EMAILS TO EVERY DELETED USER
                    foreach ($usersToDelete as $u) {
                        if (!empty($u['email'])) {
                            $html_body = "
                                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #E2E8F0; border-radius: 16px; background: #FFFFFF;'>
                                    <div style='text-align: center; margin-bottom: 20px;'>
                                        <h2 style='color: #DC2626; margin: 0;'>Account Deletion Notice</h2>
                                        <p style='color: #64748B; font-size: 14px; margin-top: 5px;'>NIELIT Administration</p>
                                    </div>
                                    <p style='color: #0F172A; font-size: 15px;'>Dear <strong>{$u['full_name']}</strong>,</p>
                                    <p style='color: #475569; font-size: 15px; line-height: 1.6;'>This is an official notification that your account on the NIELIT Centralized Exam Portal has been permanently deleted by a System Administrator.</p>
                                    <div style='background: #FEF2F2; padding: 15px; border-radius: 8px; border-left: 4px solid #DC2626; margin: 15px 0;'>
                                        <p style='margin: 0; color: #991B1B; font-size: 14px;'>All associated data, including exam registrations, historical responses, and scorecards, have been permanently erased from our active systems.</p>
                                    </div>
                                    <hr style='border: none; border-top: 1px solid #E2E8F0; margin: 20px 0;'>
                                    <p style='color: #64748B; font-size: 11px; margin: 0; text-align: center;'>Automated security message. Do not reply.</p>
                                </div>
                            ";
                            sendNielitEmail($u['email'], $u['full_name'], "Notice of Account Deletion - NIELIT", $html_body);
                        }
                    }

                    header("Location: manage-users.php?msg=bulk_deleted&count=" . count($ids_to_delete));
                    exit();
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    header("Location: manage-users.php?msg=delete_failed&err=" . urlencode($e->getMessage()));
                    exit();
                }
            } else {
                $error = "No valid users selected for deletion (You cannot delete yourself).";
            }
        } else {
            $error = "Please select at least one user to delete.";
        }
    }

    // ====================================================================
    // 2. HANDLE SINGLE ACTIONS (Deactivate/Activate)
    // ====================================================================
    if (isset($_GET['deactivate']) && is_numeric($_GET['deactivate'])) {
        if ($_GET['deactivate'] != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("UPDATE users SET is_active = false WHERE id = ?");
            $stmt->execute([$_GET['deactivate']]);
            header("Location: manage-users.php?msg=deactivated");
            exit();
        } else {
            $error = "You cannot deactivate your own administrative account.";
        }
    }

    if (isset($_GET['activate']) && is_numeric($_GET['activate'])) {
        $stmt = $pdo->prepare("UPDATE users SET is_active = true WHERE id = ?");
        $stmt->execute([$_GET['activate']]);
        header("Location: manage-users.php?msg=activated");
        exit();
    }

    // Handle single hard delete via GET (Kept for the individual trash can icon)
    if (isset($_GET['hard_delete']) && is_numeric($_GET['hard_delete'])) {
        // We can just utilize the bulk delete logic via an array simulation
        $_POST['bulk_action'] = 'delete';
        $_POST['selected_users'] = [$_GET['hard_delete']];
        // The page will refresh anyway
    }

    // Handle success messages from redirects
    if (isset($_GET['msg'])) {
        if ($_GET['msg'] === 'deactivated') $message = "User account successfully suspended.";
        if ($_GET['msg'] === 'activated') $message = "User account successfully activated.";
        if ($_GET['msg'] === 'bulk_deleted') $message = htmlspecialchars($_GET['count']) . " user(s) permanently deleted and notified.";
        if ($_GET['msg'] === 'delete_failed') {
            $err_msg = isset($_GET['err']) ? htmlspecialchars($_GET['err']) : "Unknown error";
            $error = "Deletion failed due to database constraint: " . $err_msg;
        }
    }

    // Get all users
    $users = $pdo->query("
        SELECT 
            u.id, u.username, u.email, u.full_name, u.role, u.is_active, u.created_at, u.last_login,
            c.registration_number, c.mobile
        FROM users u
        LEFT JOIN candidates c ON u.id = c.user_id
        ORDER BY u.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Stats
    $totalUsers = count($users);
    $adminCount = 0;
    $candidateCount = 0;
    $activeCount = 0;

    foreach ($users as $u) {
        if ($u['role'] === 'admin') $adminCount++;
        if ($u['role'] === 'candidate') $candidateCount++;
        if ($u['is_active']) $activeCount++;
    }

} catch (PDOException $e) {
    $error = "Database connection error. Please contact IT support.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - NIELIT Admin Console</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1D4ED8;        
            --primary-light: #3B82F6;  
            --primary-bg: #DBEAFE;     
            --secondary: #0F172A;
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --text-dark: #0F172A;
            --text-muted: #64748B;
            --bg-body: #F4F7FB;
            --surface: #FFFFFF;
            --border: #E2E8F0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 40px -10px rgba(29, 78, 216, 0.1);
            --radius-md: 12px;
            --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; overflow-x: hidden; padding-bottom: 50px; }
        
        .ambient-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; background: radial-gradient(circle at 50% 0%, #DCE6F1 0%, #E8F0F8 50%, #F4F7FB 100%); }
        .shape { position: absolute; background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(59,130,246,0.05)); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.9); box-shadow: 0 15px 35px rgba(29,78,216,0.08), inset 0 0 20px rgba(255,255,255,0.5); animation: float-3d 20s infinite linear; }
        .cube { width: 120px; height: 120px; border-radius: 24px; top: 15%; left: 8%; animation-duration: 25s; }
        .ring { width: 200px; height: 200px; border-radius: 50%; border: 35px solid rgba(255,255,255,0.4); top: 50%; right: 5%; animation-duration: 30s; animation-direction: reverse; background: transparent; }
        @keyframes float-3d { 0% { transform: translateY(0) rotateX(0deg) rotateY(0deg) rotateZ(0deg); } 50% { transform: translateY(-40px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); } 100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); } }

        .top-nav { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid rgba(255,255,255,0.5); }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--bg-body); border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s; }
        .btn-back:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); transform: translateX(-3px); }
        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--primary); line-height: 1.2; }
        .brand-text span { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .user-info { font-size: 14px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }
        
        .container { max-width: 1440px; margin: 30px auto; padding: 0 40px; position: relative; z-index: 10; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        
        .header-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-add { background: var(--primary); color: white; padding: 12px 24px; border-radius: var(--radius-md); text-decoration: none; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; box-shadow: 0 4px 15px rgba(29, 78, 216, 0.2); }
        .btn-add:hover { background: #1e3a8a; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(29, 78, 216, 0.3); }

        .alert { padding: 16px 20px; border-radius: var(--radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; animation: slideIn 0.4s ease; border: 1px solid transparent; }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: #A7F3D0; }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: #FECACA; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: rgba(255,255,255,0.85); backdrop-filter: blur(10px); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 15px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .stat-card:nth-child(1) .stat-icon { background: var(--primary-bg); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: #E0E7FF; color: #4F46E5; }
        .stat-card:nth-child(3) .stat-icon { background: var(--success-bg); color: var(--success); }
        .stat-card:nth-child(4) .stat-icon { background: var(--warning-bg); color: var(--warning); }
        .stat-info h3 { font-size: 24px; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 5px; }
        .stat-info p { font-size: 13px; color: var(--text-muted); font-weight: 600; }

        .table-wrapper { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-md); padding: 25px; margin-bottom: 40px; }

        /* 🆕 Advanced Filtering UI */
        .table-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; background: #F8FAFC; padding: 15px; border-radius: 12px; border: 1px solid var(--border);}
        
        .filter-group { display: flex; gap: 15px; flex-wrap: wrap; flex: 1;}
        
        .search-box { position: relative; width: 100%; max-width: 300px; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-box input { width: 100%; padding: 10px 15px 10px 40px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface); font-family: inherit; font-size: 13px; transition: 0.3s; outline: none; font-weight: 500; }
        .search-box input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }

        .filter-select { padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface); font-family: inherit; font-size: 13px; color: var(--text-dark); outline: none; cursor: pointer; min-width: 150px; font-weight: 600;}
        .filter-select:focus { border-color: var(--primary); }

        /* 🆕 Bulk Delete Button */
        .btn-bulk-delete { background: var(--danger); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; display: none; align-items: center; gap: 8px; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.2); transition: 0.3s;}
        .btn-bulk-delete:hover { background: #B91C1C; transform: translateY(-2px);}

        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 900px; }
        
        /* 🆕 Checkbox Column Styling */
        th { background: #F1F5F9; color: var(--text-muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 15px; text-align: left; border-bottom: 2px solid var(--border); border-top: 1px solid var(--border); }
        th:first-child { border-left: 1px solid var(--border); border-top-left-radius: 10px; border-bottom-left-radius: 10px; width: 40px; text-align: center;}
        th:last-child { border-right: 1px solid var(--border); border-top-right-radius: 10px; border-bottom-right-radius: 10px;}
        
        td { padding: 15px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 500; vertical-align: middle; transition: background 0.2s;}
        td:first-child { text-align: center; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--bg-body); }

        /* Custom Checkbox */
        .custom-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary);}

        .user-main-info { display: flex; flex-direction: column; }
        .user-main-info strong { color: var(--text-dark); font-weight: 700; }
        .user-main-info span { color: var(--text-muted); font-size: 12px; }

        .badge { padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 4px; }
        .badge-admin { background: var(--primary-bg); color: var(--primary); }
        .badge-candidate { background: #E0E7FF; color: #4F46E5; }
        .badge-tp { background: #F3E8FF; color: #7C3AED; }
        .badge-active { background: var(--success-bg); color: var(--success); }
        .badge-inactive { background: var(--danger-bg); color: var(--danger); }

        .actions { display: flex; gap: 8px; justify-content: flex-end; }
        .btn-action { width: 34px; height: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s; font-size: 14px; border: 1px solid transparent; cursor: pointer; }
        .btn-edit { background: var(--warning-bg); color: var(--warning); }
        .btn-edit:hover { background: var(--warning); color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(217, 119, 6, 0.2); }
        .btn-deactivate { background: #FEE2E2; color: #DC2626; }
        .btn-deactivate:hover { background: #DC2626; color: white; transform: translateY(-2px); }
        .btn-activate { background: var(--success-bg); color: var(--success); }
        .btn-activate:hover { background: var(--success); color: white; transform: translateY(-2px); }
        .btn-delete { background: #FEE2E2; color: #DC2626; }
        .btn-delete:hover { background: #B91C1C; color: white; transform: translateY(-2px); }
        .btn-disabled { background: #F1F5F9; color: #CBD5E1; cursor: not-allowed; }

        @media (max-width: 768px) {
            .table-controls { flex-direction: column; align-items: stretch; }
            .search-box { max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="ambient-bg">
        <div class="shape cube"></div>
        <div class="shape ring"></div>
        <div class="shape pyramid"></div>
    </div>

    <nav class="top-nav">
        <div class="nav-left">
            <a href="admin-dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div class="brand-text">
                <h2>User Management</h2>
                <span class="hide-mobile">NIELIT Admin Console</span>
            </div>
        </div>
        <div class="nav-right">
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?></div>
                <span class="hide-mobile"><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?></span>
            </div>
        </div>
    </nav>

    <div class="container">
        
        <div class="page-header">
            <h1><i class="fas fa-users-cog" style="color: var(--primary);"></i> Directory</h1>
            <div class="header-actions">
                <a href="add-user.php" class="btn-add">
                    <i class="fas fa-user-plus"></i> Add New User
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($totalUsers); ?></h3>
                    <p>Total Profiles</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($adminCount); ?></h3>
                    <p>Administrators</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($candidateCount); ?></h3>
                    <p>Candidates</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($activeCount); ?></h3>
                    <p>Active Accounts</p>
                </div>
            </div>
        </div>

        <div class="table-wrapper">
            <form method="POST" id="bulkForm" action="manage-users.php">
                <input type="hidden" name="bulk_action" value="delete">

                <div class="table-controls">
                    <div class="filter-group">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Search name, ID, email..." onkeyup="filterTable()">
                        </div>
                        
                        <select id="roleFilter" class="filter-select" onchange="filterTable()">
                            <option value="all">All Roles</option>
                            <option value="admin">Administrators</option>
                            <option value="candidate">Candidates</option>
                            <option value="tp">Training Partners</option>
                        </select>

                        <select id="statusFilter" class="filter-select" onchange="filterTable()">
                            <option value="all">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>

                    <button type="submit" id="bulkDeleteBtn" class="btn-bulk-delete" onclick="return confirm('⚠️ DANGER: You are about to permanently delete multiple users and all their data. This will send deletion emails to all selected users. Proceed?')">
                        <i class="fas fa-trash-alt"></i> Delete Selected (<span id="selectedCount">0</span>)
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll" class="custom-checkbox"></th>
                                <th>User Profile</th>
                                <th>Role & Status</th>
                                <th>Contact / Reg ID</th>
                                <th>Join Date</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        <i class="fas fa-folder-open" style="font-size: 32px; margin-bottom: 10px;"></i><br>
                                        No users found in the system.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                <tr data-role="<?php echo $user['role']; ?>" data-status="<?php echo $user['is_active'] ? 'active' : 'suspended'; ?>">
                                    <td>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" class="custom-checkbox row-checkbox">
                                        <?php else: ?>
                                            <input type="checkbox" disabled title="Cannot delete yourself">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="user-main-info">
                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                            <span>@<?php echo htmlspecialchars($user['username']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-direction: column; align-items: flex-start;">
                                            <span class="badge badge-<?php echo $user['role']; ?>">
                                                <?php echo $user['role'] === 'admin' ? '<i class="fas fa-shield-alt"></i>' : '<i class="fas fa-user"></i>'; ?>
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                            <span class="badge badge-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $user['is_active'] ? 'Active' : 'Suspended'; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-main-info">
                                            <span><i class="far fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                                            <?php if($user['role'] === 'candidate'): ?>
                                                <span><i class="fas fa-id-card"></i> <?php echo $user['registration_number'] ?: 'N/A'; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-main-info">
                                            <strong><?php echo date('d M Y', strtotime($user['created_at'])); ?></strong>
                                            <span>Last Login: <?php echo $user['last_login'] ? date('d M Y', strtotime($user['last_login'])) : 'Never'; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn-action btn-edit" title="Edit Profile">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            
                                            <?php if ($user['is_active']): ?>
                                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                    <span class="btn-action btn-disabled" title="Cannot deactivate yourself"><i class="fas fa-ban"></i></span>
                                                <?php else: ?>
                                                    <a href="?deactivate=<?php echo $user['id']; ?>" class="btn-action btn-deactivate" onclick="return confirm('Are you sure you want to suspend this user?')" title="Suspend User">
                                                        <i class="fas fa-power-off"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="?activate=<?php echo $user['id']; ?>" class="btn-action btn-activate" onclick="return confirm('Reactivate this user account?')" title="Activate User">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="?hard_delete=<?php echo $user['id']; ?>" class="btn-action btn-delete" onclick="return confirm('⚠️ WARNING: This will permanently wipe this user and all their data. Are you sure?')" title="Delete User">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // ---------------------------------------------------------
        // 1. COMBINED FILTERING LOGIC (Search + Dropdowns)
        // ---------------------------------------------------------
        function filterTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            
            const tbody = document.querySelector('#usersTable tbody');
            const rows = tbody.getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                // Skip the "no users found" placeholder row if it exists
                if (rows[i].cells.length === 1) continue;
                
                const textContent = rows[i].textContent.toLowerCase();
                const rowRole = rows[i].getAttribute('data-role');
                const rowStatus = rows[i].getAttribute('data-status');
                
                // Determine if row matches all criteria
                const matchesSearch = textContent.includes(searchInput);
                const matchesRole = (roleFilter === 'all' || rowRole === roleFilter);
                const matchesStatus = (statusFilter === 'all' || rowStatus === statusFilter);
                
                if (matchesSearch && matchesRole && matchesStatus) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                    // Uncheck hidden rows so they aren't accidentally deleted
                    const cb = rows[i].querySelector('.row-checkbox');
                    if(cb) cb.checked = false; 
                }
            }
            updateBulkDeleteButton();
        }

        // ---------------------------------------------------------
        // 2. BULK CHECKBOX LOGIC
        // ---------------------------------------------------------
        const selectAllCheckbox = document.getElementById('selectAll');
        const rowCheckboxes = document.querySelectorAll('.row-checkbox');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        const selectedCountSpan = document.getElementById('selectedCount');

        // "Select All" toggle
        if(selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                rowCheckboxes.forEach(cb => {
                    // Only check rows that are currently visible (not filtered out)
                    const row = cb.closest('tr');
                    if (row.style.display !== 'none') {
                        cb.checked = selectAllCheckbox.checked;
                    }
                });
                updateBulkDeleteButton();
            });
        }

        // Individual row toggles
        rowCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateBulkDeleteButton);
        });

        // Update Button UI
        function updateBulkDeleteButton() {
            const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
            
            if (checkedCount > 0) {
                bulkDeleteBtn.style.display = 'inline-flex';
                selectedCountSpan.textContent = checkedCount;
            } else {
                bulkDeleteBtn.style.display = 'none';
                if(selectAllCheckbox) selectAllCheckbox.checked = false;
            }
        }
    </script>
</body>
</html>