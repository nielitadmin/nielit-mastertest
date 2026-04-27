<?php
session_name('NIELIT_ADMIN_SESSION');
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: admin-login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage-centers.php");
    exit();
}

$center_id = $_GET['id'];

// ============================================================================
// NEW ARCHITECTURE: Import centralized database connection
// Path assumes this file is in: /public/admin/edit-center.php
// ============================================================================
require_once __DIR__ . '/../../config/database.php';

$error = '';
$success = '';

try {
    // Fetch existing center data
    $stmt = $pdo->prepare("SELECT * FROM exam_centers WHERE id = ?");
    $stmt->execute([$center_id]);
    $center = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$center) {
        header("Location: manage-centers.php?msg=Center not found");
        exit();
    }

    // Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $center_code = strtoupper(trim($_POST['center_code']));
        $center_name = trim($_POST['center_name']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $capacity = intval($_POST['capacity']);
        $is_active = isset($_POST['is_active']) ? 'true' : 'false';

        // Validate basic constraints
        if (empty($center_code) || empty($center_name) || empty($city) || empty($capacity)) {
            $error = "Please fill in all required fields.";
        } else {
            // Check if center_code is already in use by ANOTHER center
            $check = $pdo->prepare("SELECT id FROM exam_centers WHERE center_code = ? AND id != ?");
            $check->execute([$center_code, $center_id]);

            if ($check->fetch()) {
                $error = "The Center Code '{$center_code}' is already assigned to another location.";
            } else {
                $update = $pdo->prepare("
                    UPDATE exam_centers 
                    SET center_code = ?, center_name = ?, address = ?, city = ?, capacity = ?, is_active = ?
                    WHERE id = ?
                ");
                $update->execute([$center_code, $center_name, $address, $city, $capacity, $is_active, $center_id]);
                
                $success = "Exam Center details updated successfully!";
                
                // Refresh center data
                $stmt->execute([$center_id]);
                $center = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Exam Center | NIELIT Admin Portal</title>
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
        .form-group input, .form-group textarea { background: #F8FAFC; border: 1px solid var(--border); padding: 14px 16px; border-radius: 10px; font-size: 14px; font-family: inherit; color: var(--text-main); transition: 0.3s; width: 100%; outline: none;}
        .form-group input:focus, .form-group textarea:focus { background: var(--white); border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-bg); }
        .form-group textarea { min-height: 80px; resize: vertical; }

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
            <a href="manage-centers.php" class="btn-back"><i class="fas fa-arrow-left"></i> Centers</a>
            <h1 class="page-title">Edit Exam Center</h1>
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
                    <span>System Record ID</span>
                    <strong>#<?php echo htmlspecialchars($center['id']); ?></strong>
                </div>
            </div>

            <form method="POST" action="">
                
                <h3 class="form-section-title"><i class="fas fa-building"></i> Center Identification</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Assigned Center Code *</label>
                        <input type="text" name="center_code" value="<?php echo htmlspecialchars($center['center_code']); ?>" required placeholder="e.g., BBSR-01">
                    </div>
                    <div class="form-group">
                        <label>Official Center Name *</label>
                        <input type="text" name="center_name" value="<?php echo htmlspecialchars($center['center_name']); ?>" required>
                    </div>
                </div>

                <h3 class="form-section-title"><i class="fas fa-map-marker-alt"></i> Location Details</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Full Physical Address</label>
                        <textarea name="address" placeholder="Enter complete street address..."><?php echo htmlspecialchars($center['address']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>City / District *</label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($center['city']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Maximum Seat Capacity *</label>
                        <input type="number" name="capacity" value="<?php echo htmlspecialchars($center['capacity']); ?>" required min="1">
                    </div>
                </div>

                <h3 class="form-section-title"><i class="fas fa-cogs"></i> System Status</h3>
                <div class="form-grid" style="margin-bottom: 0;">
                    <div class="form-group full-width">
                        <div class="toggle-wrapper">
                            <div class="toggle-label">
                                Operational Status
                                <small>Allow exams to be scheduled here</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="is_active" <?php echo $center['is_active'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="manage-centers.php" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>