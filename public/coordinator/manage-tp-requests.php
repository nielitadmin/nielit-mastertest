<?php
session_name('NIELIT_COORD_SESSION');
session_start();

// Ensure user is logged in and is Coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'coordinator') {
    header("Location: coordinator-login.php");
    exit();
}

// Anti-Back Button Cache Headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require_once __DIR__ . '/../../config/database.php';

$error = '';
$success_msg = '';
$pending_batches = [];
$centers = [];

try {
    // ==========================================
    // 1. Handle TP Request: APPROVE & SCHEDULE
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_booking'])) {
        $booking_id = $_POST['booking_id'];
        $center_id = $_POST['center_id'];
        
        $pdo->beginTransaction();
        
        // Fetch the booking details
        $stmt = $pdo->prepare("SELECT * FROM slot_bookings WHERE id = ? AND status IN ('Pending', 'Approved for Scheduling')");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            $exam_code = "EXM-" . date('ym') . "-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $start_time = $booking['requested_time'];
            $end_time = date('H:i:s', strtotime($start_time . ' + 2 hours'));
            $exam_conductor = $_SESSION['full_name'];

            // Create Live Session
            $sessStmt = $pdo->prepare("
                INSERT INTO exam_sessions (exam_code, category_id, center_id, exam_date, start_time, end_time, total_seats, is_active, is_practice, exam_conductor, booking_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, true, false, ?, ?)
            ");
            $sessStmt->execute([
                $exam_code, $booking['category_id'], $center_id, 
                $booking['requested_date'], $start_time, $end_time, 
                $booking['estimated_candidates'] + 5, // Buffer seats
                $exam_conductor, $booking_id
            ]);
            $new_session_id = (int)$pdo->lastInsertId();

            // Enroll TP Students
            $candidates = json_decode($booking['selected_candidates'], true);
            if (is_array($candidates) && count($candidates) > 0) {
                $regStmt = $pdo->prepare("INSERT INTO exam_registrations (session_id, candidate_id, registration_status) VALUES (?, ?, 'approved')");
                foreach ($candidates as $cand_id) {
                    $regStmt->execute([$new_session_id, $cand_id]);
                }
            }

            // Update Booking Status to Scheduled
            $updStmt = $pdo->prepare("UPDATE slot_bookings SET status = 'Scheduled' WHERE id = ?");
            $updStmt->execute([$booking_id]);

            $pdo->commit();
            $success_msg = "TP Request Approved! Session ($exam_code) generated and center allocated.";
        } else {
            $pdo->rollBack();
            $error = "Invalid booking or it has already been processed.";
        }
    }

    // ==========================================
    // 2. Handle TP Request: DECLINE
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['decline_booking'])) {
        $booking_id = $_POST['booking_id'];
        $stmt = $pdo->prepare("UPDATE slot_bookings SET status = 'Declined' WHERE id = ?");
        if($stmt->execute([$booking_id])) {
            $success_msg = "Training Partner exam request has been declined.";
        } else {
            $error = "Failed to decline request.";
        }
    }

    // Fetch Exam Centers for Dropdown
    $centers = $pdo->query("SELECT id, center_name, city FROM exam_centers WHERE is_active = true ORDER BY city")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Pending TP Batches (Waiting for Coordinator Approval/Scheduling)
    $stmt = $pdo->query("
        SELECT b.*, tp.full_name as tp_name, c.category_name, c.category_code
        FROM slot_bookings b
        JOIN users tp ON b.tp_id = tp.id
        JOIN exam_categories c ON b.category_id = c.id
        WHERE b.status IN ('Pending', 'Approved for Scheduling')
        ORDER BY b.requested_date ASC
    ");
    $pending_batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    $error = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage TP Requests - Coordinator</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #7C3AED; --primary-light: #8B5CF6; --primary-bg: #EDE9FE;     
            --secondary: #0F172A; --success: #059669; --success-bg: #D1FAE5;
            --danger: #DC2626; --danger-bg: #FEE2E2; --warning: #D97706; --warning-bg: #FEF3C7;
            --tp-color: #0D9488; --tp-bg: #CCFBF1;
            --text-dark: #0F172A; --text-muted: #64748B; --bg-body: #F8FAFC; --surface: #FFFFFF; --border: #E2E8F0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05); --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            --radius-md: 12px; --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; padding-bottom: 60px; position: relative; }

        /* Animated Background */
        .ambient-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; overflow: hidden; pointer-events: none; background: linear-gradient(180deg, #F8FAFC 0%, #E2E8F0 100%); perspective: 1000px; }
        .orb { position: absolute; border-radius: 50%; filter: blur(90px); opacity: 0.6; animation: float-orb 20s infinite alternate cubic-bezier(0.45, 0.05, 0.55, 0.95); }
        .orb-1 { width: 600px; height: 600px; background: linear-gradient(135deg, #DDD6FE, #A78BFA); top: -10%; left: -10%; }
        .orb-2 { width: 500px; height: 500px; background: linear-gradient(135deg, #C4B5FD, #8B5CF6); bottom: -20%; right: -5%; animation-delay: -5s; }
        @keyframes float-orb { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(100px, 50px) scale(1.1); } }

        /* Navbar */
        .navbar-wrapper { position: sticky; top: 0; z-index: 1000; background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-bottom: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 4px 30px -10px rgba(0,0,0,0.05); }
        .top-nav { padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; }
        .nav-left { display: flex; align-items: center; gap: 15px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--bg-body); border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s; }
        .btn-back:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); transform: translateX(-3px); }
        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--primary); line-height: 1.2; }
        .brand-text span { font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;}
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .user-info { font-size: 14px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }

        .container { max-width: 1440px; margin: 30px auto; padding: 0 40px; position: relative; z-index: 10; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }

        .alert { padding: 16px 20px; border-radius: var(--radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; border: 1px solid transparent;}
        .alert-success { background: var(--success-bg); color: var(--success); border-color: #A7F3D0; }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: #FECACA; }

        /* --- VERIFICATION TABLE --- */
        .table-card { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 40px;}
        .table-header { padding: 25px; border-bottom: 1px solid var(--border); background: transparent; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;}
        .table-header h2 { font-size: 20px; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
        
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th { background: #F8FAFC; color: var(--text-muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 15px 25px; text-align: left; border-bottom: 2px solid var(--border); border-top: 1px solid var(--border); }
        td { padding: 20px 25px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 500; vertical-align: middle; transition: background 0.2s;}
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.5); }

        .tp-info strong { display: block; font-size: 15px; color: var(--text-dark); font-weight: 700; margin-bottom: 3px; }
        .tp-info span { font-size: 12px; color: var(--text-muted); font-weight: 600; }
        
        .date-box { font-weight: 800; color: var(--text-dark); font-size: 14px; }
        .time-box { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-top: 4px; }

        .action-cell { display: flex; flex-direction: column; gap: 10px; }
        .form-select { padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border); font-family: inherit; font-size: 13px; font-weight: 600; outline: none; background: var(--bg-body); width: 100%; color: var(--text-dark); cursor: pointer; transition: 0.3s;}
        .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); background: white;}
        
        .btn-group { display: flex; gap: 8px; }
        .btn-approve { flex: 1; background: var(--success); color: white; border: none; padding: 10px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 12px; display: inline-flex; justify-content: center; align-items: center; gap: 6px;}
        .btn-approve:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(5, 150, 105, 0.3); }
        .btn-decline { background: var(--danger-bg); color: var(--danger); border: 1px solid #FECACA; padding: 10px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 12px; display: inline-flex; justify-content: center; align-items: center;}
        .btn-decline:hover { background: var(--danger); color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(220, 38, 38, 0.2); }

        @media (max-width: 768px) {
            .top-nav { padding: 15px 20px; }
            .container { padding: 0 20px; }
            .table-responsive { overflow-x: auto; }
        }
    </style>
</head>
<body>

    <div class="ambient-bg">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
    </div>

    <div class="navbar-wrapper">
        <nav class="top-nav">
            <div class="nav-left">
                <a href="coordinator-dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
                <div class="brand-text" style="margin-left: 10px;">
                    <h2>TP Requests</h2>
                    <span class="hide-mobile">Coordinator Portal</span>
                </div>
            </div>
            <div class="nav-right">
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'C', 0, 1)); ?></div>
                    <span class="hide-mobile"><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?></span>
                </div>
            </div>
        </nav>
    </div>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-chalkboard-teacher" style="color: var(--primary);"></i> Institute Batch Approvals</h1>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="table-card">
            <div class="table-header">
                <h2><i class="fas fa-clipboard-list" style="color: var(--tp-color);"></i> Pending Action Required</h2>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Req ID</th>
                            <th>Institute (TP)</th>
                            <th>Exam Module</th>
                            <th>Requested Schedule</th>
                            <th style="min-width: 250px;">Coordinator Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_batches)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 80px 20px; color: var(--text-muted);">
                                    <i class="fas fa-check-double" style="font-size: 48px; margin-bottom: 20px; color: var(--success); display: block;"></i>
                                    <h3 style="margin: 0 0 8px 0; color: var(--text-dark); font-size: 20px; font-weight: 800;">Inbox Empty</h3>
                                    <p style="margin: 0; font-size: 14px;">No pending exam requests from Training Partners.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_batches as $batch): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 800; color: var(--text-dark);">#<?php echo str_pad($batch['id'], 5, '0', STR_PAD_LEFT); ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; margin-top: 4px;"><i class="fas fa-users"></i> <?php echo $batch['estimated_candidates']; ?> Students</div>
                                </td>
                                <td>
                                    <div class="tp-info">
                                        <strong><?php echo htmlspecialchars($batch['tp_name']); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 800; color: var(--primary);"><?php echo htmlspecialchars($batch['category_code']); ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted); font-weight: 600; margin-top: 2px;"><?php echo htmlspecialchars($batch['category_name']); ?></div>
                                </td>
                                <td>
                                    <div class="date-box"><i class="far fa-calendar-alt" style="color: var(--text-muted); margin-right: 4px;"></i> <?php echo date('d M Y', strtotime($batch['requested_date'])); ?></div>
                                    <div class="time-box"><i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($batch['requested_time'])); ?></div>
                                </td>
                                <td>
                                    <?php if (empty($centers)): ?>
                                        <span style="color: var(--danger); font-size: 12px; font-weight: 700; background: var(--danger-bg); padding: 8px 12px; border-radius: 8px; display: inline-block;"><i class="fas fa-exclamation-triangle"></i> No Centers Available in DB</span>
                                    <?php else: ?>
                                        <form method="POST" class="action-cell" onsubmit="return confirm('Confirm this action?');">
                                            <input type="hidden" name="booking_id" value="<?php echo $batch['id']; ?>">
                                            
                                            <select name="center_id" class="form-select" required>
                                                <option value="">-- Select Center to Approve --</option>
                                                <?php foreach ($centers as $center): ?>
                                                    <option value="<?php echo $center['id']; ?>">
                                                        <?php echo htmlspecialchars($center['city'] . ' - ' . $center['center_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <div class="btn-group">
                                                <button type="submit" name="approve_booking" class="btn-approve" title="Approve & Assign Center">
                                                    <i class="fas fa-check"></i> Approve & Schedule
                                                </button>
                                                <button type="submit" name="decline_booking" class="btn-decline" title="Decline Request" formnovalidate>
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>