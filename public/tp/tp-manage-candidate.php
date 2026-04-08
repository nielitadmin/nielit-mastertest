<?php
session_name('NIELIT_TP_SESSION');
session_start();

// Ensure user is logged in and is a Training Partner
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'tp') {
    header("Location: tp-login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$tp_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle Candidate Addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_candidate'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);
    $dob = $_POST['dob'];
    
    // Auto-generate a unique username based on their email prefix + random numbers
    $email_parts = explode('@', $email);
    $base_username = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($email_parts[0]));
    $username = $base_username . rand(1000, 9999);
    
    // Standard default password for TP batches
    $default_password = 'Nielit@123'; 
    
    try {
        $pdo->beginTransaction();
        
        // 1. Check if email already exists in the entire system
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if($check->fetch()) {
            throw new Exception("The email address '{$email}' is already registered in the system.");
        }

        // 2. Create User Account
        $hash = password_hash($default_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password_hash, email, full_name, role, is_active, created_at) 
            VALUES (?, ?, ?, ?, 'candidate', true, NOW()) RETURNING id
        ");
        $stmt->execute([$username, $hash, $email, $full_name]);
        $new_user_id = $stmt->fetchColumn();
        
        // 3. Generate Official Registration Number
        $reg_number = 'NIELIT' . date('Y') . str_pad($new_user_id, 5, '0', STR_PAD_LEFT);
        
        // 4. Create Candidate Profile and LINK to this TP
        $stmt2 = $pdo->prepare("
            INSERT INTO candidates (user_id, registration_number, date_of_birth, mobile, tp_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt2->execute([$new_user_id, $reg_number, $dob, $mobile, $tp_id]);
        
        $pdo->commit();
        $message = "Candidate added successfully! Username: <strong>{$username}</strong> | Password: <strong>{$default_password}</strong>";
        
    } catch(Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Fetch all candidates belonging to this specific TP
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.full_name, u.email, u.username, 
            c.registration_number, c.mobile, c.date_of_birth, u.created_at
        FROM users u
        JOIN candidates c ON u.id = c.user_id
        WHERE c.tp_id = ?
        ORDER BY u.id DESC
    ");
    $stmt->execute([$tp_id]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "System Database Offline. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Batch - NIELIT TP Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #059669; /* TP Green */
            --primary-light: #10B981; --primary-bg: #D1FAE5;
            --secondary: #0F172A;
            --text-dark: #0F172A; --text-muted: #64748B;
            --bg-body: #F8FAFC; --surface: #FFFFFF; --border: #E2E8F0;
            --danger: #DC2626; --danger-bg: #FEE2E2;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            --radius-md: 12px; --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; padding-bottom: 60px; }

        /* Navbar */
        .top-nav { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--border); }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--bg-body); border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; color: var(--text-dark); text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.3s; }
        .btn-back:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); transform: translateX(-3px); }
        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--secondary); margin-bottom: 2px; }
        .brand-text span { font-size: 11px; color: var(--primary); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }

        .container { max-width: 1400px; margin: 30px auto; padding: 0 40px; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); }
        .btn-add { background: var(--primary); color: white; padding: 12px 24px; border: none; border-radius: var(--radius-md); font-weight: 700; font-size: 14px; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.2); font-family: inherit;}
        .btn-add:hover { background: #047857; transform: translateY(-2px); }

        .alert { padding: 15px 20px; border-radius: var(--radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; border: 1px solid transparent; }
        .alert-success { background: var(--primary-bg); color: var(--primary); border-color: #A7F3D0; }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: #FECACA; }

        /* Hidden Add Form */
        .form-container { background: white; padding: 30px; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-md); margin-bottom: 30px; display: none; }
        .form-container.show { display: block; animation: slideDown 0.3s ease-out; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        .form-title { font-size: 18px; font-weight: 800; color: var(--primary); margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 10px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
        .input-wrap { position: relative; }
        .input-wrap i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; }
        .form-control { width: 100%; padding: 12px 16px 12px 42px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-body); font-family: inherit; font-size: 14px; transition: 0.3s; outline: none; }
        .form-control:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 3px var(--primary-bg); }
        
        .action-row { display: flex; justify-content: flex-end; gap: 15px; margin-top: 10px; }
        .btn-cancel { background: white; color: var(--text-dark); border: 1px solid var(--border); padding: 10px 20px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-cancel:hover { background: var(--bg-body); }
        .btn-save { background: var(--primary); color: white; border: none; padding: 10px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 10px rgba(5,150,105,0.2); }
        .btn-save:hover { background: #047857; transform: translateY(-2px); }

        /* Data Table */
        .table-card { background: white; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; }
        .table-toolbar { padding: 20px 25px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #F8FAFC; }
        .search-box { position: relative; width: 300px; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-box input { width: 100%; padding: 10px 15px 10px 40px; border-radius: 50px; border: 1px solid var(--border); font-family: inherit; font-size: 13px; outline: none; transition: 0.3s; }
        .search-box input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
        
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { background: white; color: var(--text-muted); font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 15px 25px; text-align: left; border-bottom: 2px solid var(--border); }
        td { padding: 16px 25px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 500; color: var(--text-dark); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--bg-body); }

        .cand-name { font-weight: 800; color: var(--text-dark); display: block; margin-bottom: 3px; }
        .cand-roll { font-size: 12px; color: var(--primary); font-weight: 700; background: var(--primary-bg); padding: 3px 8px; border-radius: 4px; display: inline-block;}
        .meta-text { font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; margin-top: 4px;}

        @media (max-width: 768px) {
            .top-nav { padding: 15px 20px; }
            .container { padding: 0 20px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .btn-add { width: 100%; justify-content: center; }
            .form-grid { grid-template-columns: 1fr; }
            .table-toolbar { flex-direction: column; align-items: stretch; gap: 15px; }
            .search-box { width: 100%; }
        }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="nav-left">
            <a href="tp-dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div class="brand-text">
                <h2>Institute Portal</h2>
                <span>Training Partner • NIELIT</span>
            </div>
        </div>
        <div style="font-weight: 700; font-size: 14px; color: var(--secondary);" class="hide-mobile">
            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
        </div>
    </nav>

    <div class="container">
        
        <div class="page-header">
            <h1>Candidate Batch Management</h1>
            <button class="btn-add" onclick="toggleForm()"><i class="fas fa-user-plus"></i> Register New Student</button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?php echo $message; ?></span></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-container" id="addForm" <?php if(isset($_POST['add_candidate'])) echo 'style="display:block;"'; ?>>
            <div class="form-title">Student Registration Form</div>
            <form method="POST" autocomplete="off">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Student Full Name *</label>
                        <div class="input-wrap">
                            <input type="text" name="full_name" class="form-control" placeholder="As per official documents" required>
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email Address *</label>
                        <div class="input-wrap">
                            <input type="email" name="email" class="form-control" placeholder="student@example.com" required>
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Mobile Number *</label>
                        <div class="input-wrap">
                            <input type="tel" name="mobile" class="form-control" placeholder="10-digit mobile number" required>
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth *</label>
                        <div class="input-wrap">
                            <input type="date" name="dob" class="form-control" required>
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                </div>
                
                <div style="background: var(--bg-body); padding: 15px; border-radius: 10px; border: 1px dashed var(--border); font-size: 12px; color: var(--text-muted); margin-bottom: 15px;">
                    <i class="fas fa-info-circle" style="color: var(--primary);"></i> The system will automatically generate an Official Registration Number, a unique login Username, and a default password (<strong>Nielit@123</strong>) upon submission.
                </div>

                <div class="action-row">
                    <button type="button" onclick="toggleForm()" class="btn-cancel">Cancel</button>
                    <button type="submit" name="add_candidate" class="btn-save"><i class="fas fa-user-check"></i> Register Student</button>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="table-toolbar">
                <div style="font-weight: 800; font-size: 16px;">Enrolled Students (<?php echo count($candidates); ?>)</div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by name, roll, email..." onkeyup="searchTable()">
                </div>
            </div>
            
            <div class="table-responsive">
                <table id="candTable">
                    <thead>
                        <tr>
                            <th>Student Details</th>
                            <th>Contact Info</th>
                            <th>System Credentials</th>
                            <th>Enrollment Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($candidates)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fas fa-users-slash" style="font-size: 32px; margin-bottom: 10px; color: #CBD5E1;"></i><br>
                                    Your batch is currently empty. Add students to begin.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($candidates as $c): ?>
                            <tr>
                                <td>
                                    <span class="cand-name"><?php echo htmlspecialchars($c['full_name']); ?></span>
                                    <span class="cand-roll">Reg: <?php echo htmlspecialchars($c['registration_number']); ?></span>
                                </td>
                                <td>
                                    <div class="meta-text"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($c['email']); ?></div>
                                    <div class="meta-text"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($c['mobile']); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark);">
                                        <i class="fas fa-at" style="color: var(--text-muted); font-size: 11px;"></i> <?php echo htmlspecialchars($c['username']); ?>
                                    </div>
                                    <div class="meta-text">DOB: <?php echo date('d M Y', strtotime($c['date_of_birth'])); ?></div>
                                </td>
                                <td style="color: var(--text-muted); font-weight: 600; font-size: 13px;">
                                    <?php echo date('d M Y, h:i A', strtotime($c['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        function toggleForm() {
            const form = document.getElementById('addForm');
            if (form.classList.contains('show')) {
                form.classList.remove('show');
            } else {
                form.classList.add('show');
            }
        }

        function searchTable() {
            const filter = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#candTable tbody tr');
            
            rows.forEach(row => {
                if (row.cells.length === 1) return; // Skip empty state row
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        }
    </script>
</body>
</html>