<?php
session_name('NIELIT_ADMIN_SESSION');
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: admin-login.php");
    exit();
}

// ============================================================================
// NEW ARCHITECTURE: Import centralized database connection
// Path assumes this file is in: /public/admin/manage-centers.php
// ============================================================================
require_once __DIR__ . '/../../config/database.php';

$message = '';
$error = '';

// FIX: Initialize variables with default values so the UI never breaks on DB error
$centers = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'capacity' => 0
];

try {
    // Handle delete (PRG Pattern)
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM exam_centers WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        header("Location: manage-centers.php?msg=deleted");
        exit();
    }

    // Handle status toggle (PRG Pattern)
    if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
        $pdo->query("UPDATE exam_centers SET is_active = NOT is_active WHERE id = {$_GET['toggle']}");
        header("Location: manage-centers.php?msg=toggled");
        exit();
    }

    // Handle add form (PRG Pattern)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_center'])) {
        $center_code = strtoupper(trim($_POST['center_code']));
        $center_name = trim($_POST['center_name']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $capacity = intval($_POST['capacity']);
        
        $stmt = $pdo->prepare("
            INSERT INTO exam_centers (center_code, center_name, address, city, capacity, is_active)
            VALUES (?, ?, ?, ?, ?, true)
        ");
        $stmt->execute([$center_code, $center_name, $address, $city, $capacity]);
        
        header("Location: manage-centers.php?msg=added");
        exit();
    }

    // Handle success messages from redirects
    if (isset($_GET['msg'])) {
        if ($_GET['msg'] === 'deleted') $message = "Exam center removed successfully.";
        if ($_GET['msg'] === 'toggled') $message = "Exam center operational status updated.";
        if ($_GET['msg'] === 'added') $message = "New exam center registered successfully.";
    }

    // Get all centers
    $centers = $pdo->query("SELECT * FROM exam_centers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Statistics
    $stats['total'] = count($centers);
    foreach ($centers as $c) {
        if ($c['is_active']) $stats['active']++;
        $stats['capacity'] += $c['capacity'];
    }

} catch (PDOException $e) {
    // FIX: Show the ACTUAL database error to the admin so they know why it failed
    $error = "Database Error: " . $e->getMessage();
    error_log("Manage Centers DB error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exam Centers - NIELIT Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Professional Light Theme Colors */
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

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-dark);
            min-height: 100vh;
            overflow-x: hidden;
            padding-bottom: 60px;
        }

        /* --- 3D MOVING BACKGROUND --- */
        .ambient-bg {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1; overflow: hidden; pointer-events: none;
            background: radial-gradient(circle at 50% 0%, #DCE6F1 0%, #E8F0F8 50%, #F4F7FB 100%);
            perspective: 1000px;
        }

        .shape {
            position: absolute;
            background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(59,130,246,0.05));
            backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 15px 35px rgba(29,78,216,0.08), inset 0 0 20px rgba(255,255,255,0.5);
            animation: float-3d 20s infinite linear;
        }

        .cube { width: 120px; height: 120px; border-radius: 24px; top: 15%; left: 5%; animation-duration: 25s; }
        .ring { width: 200px; height: 200px; border-radius: 50%; border: 35px solid rgba(255,255,255,0.4); top: 50%; right: 5%; animation-duration: 30s; animation-direction: reverse; background: transparent; }
        .pyramid { width: 80px; height: 80px; border-radius: 16px; bottom: 15%; left: 20%; animation-duration: 18s; }

        @keyframes float-3d {
            0% { transform: translateY(0) rotateX(0deg) rotateY(0deg) rotateZ(0deg); }
            50% { transform: translateY(-40px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); }
            100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); }
        }

        /* --- TOP NAV --- */
        .top-nav {
            background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px);
            padding: 15px 40px; display: flex; justify-content: space-between; align-items: center;
            box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 100;
            border-bottom: 1px solid rgba(255,255,255,0.5);
        }

        .nav-left { display: flex; align-items: center; gap: 20px; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--bg-body); border: 1px solid var(--border);
            padding: 8px 16px; border-radius: 10px; color: var(--text-dark);
            text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s;
        }
        .btn-back:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); transform: translateX(-3px); }

        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--primary); line-height: 1.2; }
        .brand-text span { font-size: 12px; color: var(--text-muted); font-weight: 500; }

        .nav-right { display: flex; align-items: center; gap: 20px; }
        .user-info { font-size: 14px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }

        /* --- CONTAINER --- */
        .container { max-width: 1440px; margin: 30px auto; padding: 0 40px; position: relative; z-index: 10; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        
        .btn-add {
            background: var(--primary); color: white; padding: 12px 24px; border: none; cursor: pointer;
            border-radius: var(--radius-md); font-weight: 700; font-size: 14px; display: inline-flex; 
            align-items: center; gap: 8px; transition: 0.3s; box-shadow: 0 4px 15px rgba(29, 78, 216, 0.2); font-family: inherit;
        }
        .btn-add:hover { background: #1e3a8a; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(29, 78, 216, 0.3); }

        .alert { padding: 16px 20px; border-radius: var(--radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; animation: slideIn 0.4s ease; }
        .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid #A7F3D0; }
        .alert-error { background: var(--danger-bg); color: var(--danger); border: 1px solid #FECACA; word-wrap: break-word; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- BENTO STATS --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: rgba(255,255,255,0.85); backdrop-filter: blur(10px);
            padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border);
            box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 15px; position: relative; overflow: hidden;
        }
        .stat-card::before { content:''; position:absolute; top:0; left:0; width:4px; height:100%; border-radius: 4px 0 0 4px; }
        .stat-card:nth-child(1)::before { background: var(--primary); }
        .stat-card:nth-child(2)::before { background: var(--success); }
        .stat-card:nth-child(3)::before { background: var(--warning); }

        .stat-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .stat-card:nth-child(1) .stat-icon { background: var(--primary-bg); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: var(--success-bg); color: var(--success); }
        .stat-card:nth-child(3) .stat-icon { background: var(--warning-bg); color: var(--warning); }

        .stat-info h3 { font-size: 24px; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 5px; }
        .stat-info p { font-size: 13px; color: var(--text-muted); font-weight: 600; }

        /* --- HIDDEN ADD FORM --- */
        .form-container {
            background: rgba(255,255,255,0.95); backdrop-filter: blur(16px);
            padding: 30px; border-radius: var(--radius-lg); border: 1px solid var(--border);
            box-shadow: var(--shadow-lg); margin-bottom: 30px;
            display: none; opacity: 0; transform: translateY(-10px); transition: all 0.3s ease;
        }
        .form-container.show { display: block; opacity: 1; transform: translateY(0); }
        
        .form-container h3 { font-size: 18px; font-weight: 800; color: var(--primary); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; border-bottom: 2px solid var(--border); padding-bottom: 10px;}
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 16px; top: 20px; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; pointer-events: none; transition: 0.3s; }
        
        .form-control {
            width: 100%; padding: 12px 16px 12px 42px; border-radius: 12px;
            border: 1px solid var(--border); background: var(--bg-body);
            color: var(--text-dark); font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px; font-weight: 500; transition: all 0.3s; outline: none;
        }
        textarea.form-control { padding: 16px; min-height: 80px; resize: vertical; }
        .form-control:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 3px var(--primary-bg); }
        .form-control:focus + .input-icon, .input-wrap:focus-within .input-icon { color: var(--primary); }
        
        .action-row { display: flex; justify-content: flex-end; gap: 15px; margin-top: 10px; }
        .btn-cancel { background: white; color: var(--text-dark); border: 1px solid var(--border); padding: 10px 20px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-cancel:hover { background: var(--bg-body); border-color: #94A3B8; }
        .btn-save { background: var(--success); color: white; border: none; padding: 10px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 10px rgba(5,150,105,0.2); }
        .btn-save:hover { background: #065F46; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(5,150,105,0.3); }

        /* --- TABLE & TOOLBAR --- */
        .table-wrapper {
            background: rgba(255,255,255,0.9); backdrop-filter: blur(10px);
            border-radius: var(--radius-lg); border: 1px solid var(--border);
            box-shadow: var(--shadow-md); padding: 25px;
        }

        .toolbar { display: flex; justify-content: flex-end; align-items: center; margin-bottom: 20px; }
        .search-box { position: relative; width: 100%; max-width: 320px; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-box input {
            width: 100%; padding: 10px 15px 10px 40px; border-radius: 50px; border: 1px solid var(--border);
            background: var(--surface); font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px;
            transition: all 0.3s; outline: none; font-weight: 500;
        }
        .search-box input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }

        /* --- MODERN TABLE --- */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 900px; }
        th {
            background: #F8FAFC; color: var(--text-muted); font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px; padding: 15px; text-align: left;
            border-bottom: 2px solid var(--border); border-top: 1px solid var(--border);
        }
        th:first-child { border-left: 1px solid var(--border); border-top-left-radius: 10px; border-bottom-left-radius: 10px;}
        th:last-child { border-right: 1px solid var(--border); border-top-right-radius: 10px; border-bottom-right-radius: 10px;}
        
        td { padding: 16px 15px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 500; vertical-align: middle; transition: background 0.2s;}
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--bg-body); }

        .center-main-info { display: flex; flex-direction: column; gap: 4px; }
        .center-main-info strong { color: var(--text-dark); font-weight: 800; font-size: 15px;}
        .center-main-info span { color: var(--text-muted); font-size: 12px; display: flex; align-items: center; gap: 5px;}

        /* Badges */
        .badge { padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 4px; }
        .badge-active { background: var(--success-bg); color: var(--success); }
        .badge-inactive { background: var(--danger-bg); color: var(--danger); }
        .badge-cap { background: var(--primary-bg); color: var(--primary); }

        /* Action Buttons */
        .actions { display: flex; gap: 8px; justify-content: flex-end; }
        .btn-action {
            width: 32px; height: 32px; border-radius: 8px; display: inline-flex;
            align-items: center; justify-content: center; text-decoration: none;
            transition: all 0.2s; font-size: 14px; border: 1px solid transparent; cursor: pointer;
        }
        .btn-edit { background: var(--warning-bg); color: var(--warning); }
        .btn-edit:hover { background: var(--warning); color: white; transform: translateY(-2px); }
        
        .btn-toggle { background: var(--neutral-bg); color: var(--text-muted); }
        .btn-toggle:hover { background: var(--text-muted); color: white; transform: translateY(-2px); }
        
        .btn-delete { background: var(--danger-bg); color: var(--danger); }
        .btn-delete:hover { background: var(--danger); color: white; transform: translateY(-2px); }

        @media (max-width: 768px) {
            .top-nav { padding: 15px 20px; }
            .container { padding: 0 20px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .btn-add { width: 100%; justify-content: center; }
            .form-grid { grid-template-columns: 1fr; }
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
                <h2>Center Allocation</h2>
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
            <h1><i class="fas fa-building" style="color: var(--primary);"></i> Manage Exam Centers</h1>
            <button class="btn-add" onclick="toggleForm()"><i class="fas fa-plus"></i> Register New Center</button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> 
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon"><i class="fas fa-map-marked-alt"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total']); ?></h3>
                    <p>Total Registered Centers</p>
                </div>
            </div>
            <div class="stat-card active">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['active']); ?></h3>
                    <p>Active Centers</p>
                </div>
            </div>
            <div class="stat-card capacity">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['capacity']); ?></h3>
                    <p>Total Seat Capacity</p>
                </div>
            </div>
        </div>

        <div class="form-container" id="addForm" <?php if(isset($_POST['add_center'])) echo 'style="display:block; opacity:1; transform:none;"'; ?>>
            <h3><i class="fas fa-plus-circle"></i> Center Registration</h3>
            <form method="POST" autocomplete="off">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Assigned Center Code *</label>
                        <div class="input-wrap">
                            <input type="text" name="center_code" class="form-control" placeholder="e.g., BBSR-01" required>
                            <i class="fas fa-hashtag input-icon"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Official Center Name *</label>
                        <div class="input-wrap">
                            <input type="text" name="center_name" class="form-control" placeholder="e.g., NIELIT Main Campus" required>
                            <i class="fas fa-building input-icon"></i>
                        </div>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Full Physical Address</label>
                    <div class="input-wrap">
                        <textarea name="address" class="form-control" placeholder="Enter complete street address..."></textarea>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>City / District *</label>
                        <div class="input-wrap">
                            <input type="text" name="city" class="form-control" placeholder="e.g., Bhubaneswar" required>
                            <i class="fas fa-city input-icon"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Maximum Seat Capacity *</label>
                        <div class="input-wrap">
                            <input type="number" name="capacity" class="form-control" placeholder="Total available seats" min="1" required>
                            <i class="fas fa-chair input-icon"></i>
                        </div>
                    </div>
                </div>

                <div class="action-row">
                    <button type="button" onclick="toggleForm()" class="btn-cancel">Cancel</button>
                    <button type="submit" name="add_center" class="btn-save"><i class="fas fa-save"></i> Save Center</button>
                </div>
            </form>
        </div>

        <div class="table-wrapper">
            <div class="toolbar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search centers by code, name, or city..." onkeyup="searchTable()">
                </div>
            </div>

            <div class="table-responsive">
                <table id="centersTable">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Code</th>
                            <th>Center Information</th>
                            <th>Location Details</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($centers)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fas fa-building" style="font-size: 32px; margin-bottom: 10px;"></i><br>
                                    No centers registered yet. Click "Register New Center" to add one.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($centers as $c): ?>
                            <tr>
                                <td>
                                    <strong style="color: var(--text-dark);"><?php echo htmlspecialchars($c['center_code']); ?></strong>
                                </td>
                                <td>
                                    <div class="center-main-info">
                                        <strong><?php echo htmlspecialchars($c['center_name']); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <div class="center-main-info">
                                        <span style="color: var(--text-dark); font-weight: 600;"><i class="fas fa-map-marker-alt" style="color: var(--primary);"></i> <?php echo htmlspecialchars($c['city']); ?></span>
                                        <span title="<?php echo htmlspecialchars($c['address']); ?>"><?php echo htmlspecialchars(substr($c['address'] ?? 'No address provided', 0, 35)) . (strlen($c['address']) > 35 ? '...' : ''); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-cap"><i class="fas fa-chair"></i> <?php echo $c['capacity']; ?> Seats</span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $c['is_active'] ? 'active' : 'inactive'; ?>">
                                        <i class="fas <?php echo $c['is_active'] ? 'fa-check' : 'fa-times'; ?>"></i>
                                        <?php echo $c['is_active'] ? 'Operational' : 'Disabled'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="edit-center.php?id=<?php echo $c['id']; ?>" class="btn-action btn-edit" title="Edit Center">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <a href="?toggle=<?php echo $c['id']; ?>" class="btn-action btn-toggle" onclick="return confirm('Change operational status of this center?')" title="<?php echo $c['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas <?php echo $c['is_active'] ? 'fa-power-off' : 'fa-play'; ?>"></i>
                                        </a>
                                        <a href="?delete=<?php echo $c['id']; ?>" class="btn-action btn-delete" onclick="return confirm('⚠️ WARNING: Delete this center permanently?')" title="Delete Center">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
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
        // Smooth form toggle
        function toggleForm() {
            const form = document.getElementById('addForm');
            if (form.classList.contains('show')) {
                form.classList.remove('show');
            } else {
                form.classList.add('show');
                // Scroll to form
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        // Real-time client-side table filtering
        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const tbody = document.querySelector('#centersTable tbody');
            const rows = tbody.getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                // Skip the "No centers found" row
                if (rows[i].cells.length === 1) continue;
                
                let textContent = rows[i].textContent.toLowerCase();
                if (textContent.includes(filter)) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        }
    </script>
</body>
</html>