<?php
date_default_timezone_set('Asia/Kolkata');
session_name('NIELIT_COORD_SESSION'); // 🆕 Changed Session Name
session_start();

// Check if user is logged in and is Coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'coordinator') {
    header("Location: coordinator-login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$message = '';
$error = '';

try {
    // Handle exam deletion (PRG Pattern)
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        $pdo->beginTransaction();
        $exam_id = $_GET['delete'];
        
        $reg_ids = $pdo->prepare("SELECT id FROM exam_registrations WHERE session_id = ?");
        $reg_ids->execute([$exam_id]);
        $registrations = $reg_ids->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($registrations)) {
            $placeholders = implode(',', array_fill(0, count($registrations), '?'));
            $pdo->prepare("DELETE FROM candidate_responses WHERE registration_id IN ($placeholders)")->execute($registrations);
            $pdo->prepare("DELETE FROM exam_results WHERE registration_id IN ($placeholders)")->execute($registrations);
        }
        
        $pdo->prepare("DELETE FROM exam_registrations WHERE session_id = ?")->execute([$exam_id]);
        $pdo->prepare("DELETE FROM exam_sessions WHERE id = ?")->execute([$exam_id]);
        
        $pdo->commit();
        header("Location: manage-exams.php?msg=deleted");
        exit();
    }

    // Handle status toggle (PRG Pattern)
    if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
        $pdo->query("UPDATE exam_sessions SET is_active = NOT is_active WHERE id = {$_GET['toggle']}");
        header("Location: manage-exams.php?msg=toggled");
        exit();
    }

    // Handle success messages from redirects
    if (isset($_GET['msg'])) {
        if ($_GET['msg'] === 'deleted') $message = "Exam and all related data deleted successfully.";
        if ($_GET['msg'] === 'toggled') $message = "Exam activation status updated successfully.";
    }

    // Fetch all exams (ADDED TP NAME JOIN)
    $exams = $pdo->query("
        SELECT 
            es.*,
            ec.category_name,
            ec.category_code,
            ec.duration_minutes,
            c.center_name,
            c.center_code,
            c.city,
            tp.full_name as tp_name,
            (SELECT COUNT(*) FROM exam_registrations WHERE session_id = es.id) as registered_count
        FROM exam_sessions es
        LEFT JOIN exam_categories ec ON es.category_id = ec.id
        LEFT JOIN exam_centers c ON es.center_id = c.id
        LEFT JOIN slot_bookings b ON es.booking_id = b.id
        LEFT JOIN users tp ON b.tp_id = tp.id
        ORDER BY es.exam_date DESC, es.start_time DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats accurately
    $stats = ['total' => 0, 'upcoming' => 0, 'ongoing' => 0, 'completed' => 0];
    $now_timestamp = time();
    
    foreach ($exams as &$exam) {
        $stats['total']++;
        $start_timestamp = strtotime($exam['exam_date'] . ' ' . $exam['start_time']);
        $end_timestamp = strtotime($exam['exam_date'] . ' ' . $exam['end_time']);
        
        // FIX: Midnight Bug - If end time is mathematically smaller than start time, add 1 day
        if ($end_timestamp <= $start_timestamp) {
            $end_timestamp += 86400; 
        }
        
        if (!$exam['is_active']) {
            $exam['computed_status'] = 'inactive';
        } elseif ($exam['is_practice']) {
            $exam['computed_status'] = 'practice'; 
            $stats['ongoing']++;
        } elseif ($start_timestamp > $now_timestamp) {
            $exam['computed_status'] = 'upcoming';
            $stats['upcoming']++;
        } elseif ($end_timestamp < $now_timestamp) {
            $exam['computed_status'] = 'completed';
            $stats['completed']++;
        } else {
            $exam['computed_status'] = 'ongoing';
            $stats['ongoing']++;
        }
    }
    unset($exam);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = "System Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams - Coordinator Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* 🆕 COORDINATOR PURPLE THEME */
            --primary: #7C3AED;        --primary-light: #8B5CF6;  --primary-bg: #EDE9FE;     
            --success: #059669;        --success-bg: #D1FAE5;
            --warning: #D97706;        --warning-bg: #FEF3C7;
            --danger: #DC2626;         --danger-bg: #FEE2E2;
            --practice: #8B5CF6;       --practice-bg: #EDE9FE;
            --neutral: #64748B;        --neutral-bg: #F1F5F9;
            --text-dark: #0F172A;      --text-muted: #64748B;
            --bg-body: #F8FAFC;        --surface: #FFFFFF;
            --border: #E2E8F0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 40px -10px rgba(124, 58, 237, 0.1);
            --radius-md: 12px;         --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; overflow-x: hidden; padding-bottom: 50px;}

        /* --- 3D MOVING BACKGROUND --- */
        .ambient-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; overflow: hidden; pointer-events: none; background: radial-gradient(circle at 50% 0%, #DCE6F1 0%, #E8F0F8 50%, #F4F7FB 100%); perspective: 1000px; }
        .shape { position: absolute; background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(59,130,246,0.05)); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.9); box-shadow: 0 15px 35px rgba(124,58,237,0.08), inset 0 0 20px rgba(255,255,255,0.5); animation: float-3d 20s infinite linear; }
        .cube { width: 120px; height: 120px; border-radius: 24px; top: 15%; left: 5%; animation-duration: 25s; }
        .ring { width: 200px; height: 200px; border-radius: 50%; border: 35px solid rgba(255,255,255,0.4); top: 50%; right: 5%; animation-duration: 30s; animation-direction: reverse; background: transparent; }
        .pyramid { width: 80px; height: 80px; border-radius: 16px; bottom: 15%; left: 20%; animation-duration: 18s; }

        @keyframes float-3d { 0% { transform: translateY(0) rotateX(0deg) rotateY(0deg); } 50% { transform: translateY(-40px) rotateX(180deg) rotateY(90deg); } 100% { transform: translateY(0) rotateX(360deg) rotateY(180deg); } }

        /* --- TOP NAV --- */
        .top-nav { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid rgba(255,255,255,0.5); }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--bg-body); border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s; }
        .btn-back:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); transform: translateX(-3px); }
        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--primary); line-height: 1.2; }
        .brand-text span { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .user-info { font-size: 14px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }

        /* --- CONTAINER --- */
        .container { max-width: 1440px; margin: 30px auto; padding: 0 40px; position: relative; z-index: 10; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        
        .btn-create { background: var(--primary); color: white; padding: 12px 24px; border-radius: var(--radius-md); text-decoration: none; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.2); }
        .btn-create:hover { background: #6D28D9; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3); }

        .alert { padding: 16px 20px; border-radius: var(--radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; animation: slideIn 0.4s ease; }
        .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid #A7F3D0; }
        .alert-error { background: var(--danger-bg); color: var(--danger); border: 1px solid #FECACA; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- BENTO STATS --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: rgba(255,255,255,0.85); backdrop-filter: blur(10px); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 15px; position: relative; overflow: hidden; }
        .stat-card::before { content:''; position:absolute; top:0; left:0; width:4px; height:100%; border-radius: 4px 0 0 4px; }
        .stat-card:nth-child(1)::before { background: var(--primary); }
        .stat-card:nth-child(2)::before { background: var(--warning); }
        .stat-card:nth-child(3)::before { background: var(--success); }
        .stat-card:nth-child(4)::before { background: var(--neutral); }

        .stat-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .stat-card:nth-child(1) .stat-icon { background: var(--primary-bg); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: var(--warning-bg); color: var(--warning); }
        .stat-card:nth-child(3) .stat-icon { background: var(--success-bg); color: var(--success); }
        .stat-card:nth-child(4) .stat-icon { background: var(--neutral-bg); color: var(--neutral); }
        
        .stat-info h3 { font-size: 24px; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 5px; }
        .stat-info p { font-size: 13px; color: var(--text-muted); font-weight: 600; }

        /* --- FILTERS & SEARCH --- */
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-btn { background: var(--surface); border: 1px solid var(--border); padding: 8px 16px; border-radius: 50px; font-size: 13px; font-weight: 700; color: var(--text-muted); cursor: pointer; transition: 0.2s; }
        .filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 10px rgba(124, 58, 237, 0.2); }
        .filter-btn:hover:not(.active) { background: var(--primary-bg); color: var(--primary); }

        .search-box { position: relative; width: 100%; max-width: 320px; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-box input { width: 100%; padding: 10px 15px 10px 40px; border-radius: 50px; border: 1px solid var(--border); background: var(--surface); font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; transition: all 0.3s; outline: none; font-weight: 500; }
        .search-box input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }

        /* --- UX FRIENDLY HORIZONTAL LIST VIEW --- */
        .exams-list { display: flex; flex-direction: column; gap: 12px; }

        .exam-row { 
            display: flex; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); 
            border: 1px solid var(--border); border-radius: var(--radius-md); transition: 0.2s; 
            overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .exam-row:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(124, 58, 237, 0.1); border-color: var(--primary-light); }
        
        .er-status-bar { width: 6px; flex-shrink: 0; }
        .b-upcoming { background: var(--warning); }
        .b-ongoing { background: var(--success); }
        .b-completed { background: var(--text-muted); }
        .b-inactive { background: var(--danger); }
        .b-practice { background: var(--practice); }

        /* Left Section: Titles & Badges */
        .er-left { width: 280px; padding: 16px 20px; display: flex; flex-direction: column; justify-content: center; gap: 6px; border-right: 1px dashed var(--border); }
        .er-code { font-size: 15px; font-weight: 800; color: var(--text-dark); line-height: 1.2; }
        .er-cat { font-size: 11px; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; }
        
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; width: fit-content;}
        .badge-upcoming { background: var(--warning-bg); color: #B45309; }
        .badge-ongoing { background: var(--success-bg); color: var(--success); }
        .badge-completed { background: var(--neutral-bg); color: var(--neutral); }
        .badge-inactive { background: var(--danger-bg); color: var(--danger); }
        .badge-practice { background: var(--practice-bg); color: var(--practice); }

        /* Middle Section: Details */
        .er-middle { flex: 1; padding: 16px 20px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; align-items: center; }
        .detail-item { display: flex; align-items: flex-start; gap: 8px; }
        .detail-icon { width: 28px; height: 28px; background: var(--bg-body); color: var(--primary); border-radius: 8px; display: flex; justify-content: center; align-items: center; font-size: 12px; flex-shrink: 0; }
        .detail-text { display: flex; flex-direction: column; gap: 2px;}
        .detail-val { font-size: 13px; font-weight: 700; color: var(--text-dark); }
        .detail-sub { font-size: 11px; color: var(--text-muted); font-weight: 500; }
        
        /* Progress Bar (Compact inside grid) */
        .er-progress { background: var(--bg-body); padding: 8px 12px; border-radius: 8px; grid-column: span 1; display: flex; flex-direction: column; justify-content: center;}
        .p-head { display: flex; justify-content: space-between; font-size: 11px; font-weight: 800; color: var(--text-dark); margin-bottom: 6px; }
        .p-track { width: 100%; height: 6px; background: var(--border); border-radius: 10px; overflow: hidden; }
        .p-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--primary-light)); border-radius: 10px; transition: width 0.5s; }
        .fill-full { background: var(--danger); }

        /* Right Section: Actions */
        .er-right { width: 260px; background: #F8FAFC; padding: 16px; border-left: 1px solid var(--border); display: grid; grid-template-columns: 1fr 1fr; gap: 8px; align-content: center; }
        .btn { padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; text-align: center; text-decoration: none; display: flex; justify-content: center; align-items: center; gap: 6px; border: none; cursor: pointer; transition: 0.2s; }
        
        .btn-view { background: var(--primary-bg); color: var(--primary); }
        .btn-view:hover { background: var(--primary); color: white; }
        .btn-edit { background: var(--warning-bg); color: var(--warning); }
        .btn-edit:hover { background: var(--warning); color: white; }
        .btn-toggle { background: var(--neutral-bg); color: var(--neutral); }
        .btn-toggle:hover { background: var(--neutral); color: white; }
        .btn-delete { background: var(--surface); border: 1px solid #FECACA; color: var(--danger); grid-column: 1 / -1;}
        .btn-delete:hover { background: var(--danger); color: white; border-color: var(--danger); }

        /* Empty State */
        .empty-state { grid-column: 1 / -1; text-align: center; padding: 60px 20px; background: rgba(255,255,255,0.6); border: 1px dashed var(--border); border-radius: var(--radius-lg); }
        .empty-state i { font-size: 48px; color: var(--border); margin-bottom: 15px; }
        .empty-state h3 { font-size: 20px; font-weight: 800; color: var(--text-dark); margin-bottom: 5px; }
        .empty-state p { color: var(--text-muted); font-weight: 500; }

        @media (max-width: 1200px) {
            .er-middle { grid-template-columns: 1fr 1fr; }
            .er-progress { grid-column: 1 / -1; }
        }
        @media (max-width: 992px) {
            .exam-row { flex-direction: column; }
            .er-status-bar { width: 100%; height: 6px; }
            .er-left, .er-right { width: 100%; border-right: none; }
            .er-middle { border-left: none; border-top: 1px dashed var(--border); border-bottom: 1px dashed var(--border); }
            .er-right { display: flex; flex-wrap: wrap; border-left: none; }
            .er-right .btn { flex: 1; min-width: 100px; }
        }
        @media (max-width: 768px) {
            .top-nav { padding: 15px 20px; }
            .container { padding: 0 20px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search-box { max-width: 100%; }
            .er-middle { grid-template-columns: 1fr; }
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
            <a href="coordinator-dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div class="brand-text">
                <h2>Exam Management</h2>
                <span class="hide-mobile">NIELIT Coordinator Portal</span>
            </div>
        </div>
        <div class="nav-right">
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'C', 0, 1)); ?></div>
                <span class="hide-mobile"><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?></span>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check" style="color: var(--primary);"></i> Exam Sessions</h1>
            <a href="create-exam.php" class="btn-create"><i class="fas fa-plus"></i> Create Exam</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Sessions</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['upcoming']; ?></h3>
                    <p>Upcoming</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-satellite-dish"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['ongoing']; ?></h3>
                    <p>Ongoing Now</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['completed']; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
        </div>

        <div class="toolbar">
            <div class="filters" id="filterContainer">
                <button class="filter-btn active" data-filter="all">All Exams</button>
                <button class="filter-btn" data-filter="upcoming">Upcoming</button>
                <button class="filter-btn" data-filter="ongoing">Ongoing</button>
                <button class="filter-btn" data-filter="practice">Practice</button>
                <button class="filter-btn" data-filter="completed">Completed</button>
                <button class="filter-btn" data-filter="inactive">Inactive</button>
            </div>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by Exam Code, Center...">
            </div>
        </div>

        <div class="exams-list" id="examsGrid">
            <?php if (empty($exams)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No Exam Sessions Found</h3>
                    <p>Get started by building your first exam schedule.</p>
                </div>
            <?php else: ?>
                <?php foreach ($exams as $exam): 
                    $status = $exam['computed_status'];
                    $reg_count = $exam['registered_count'] ?? 0;
                    $capacity = $exam['total_seats'] ?? 1; // Prevent division by zero
                    $fill_percentage = min(100, round(($reg_count / $capacity) * 100));
                    $is_full = ($reg_count >= $capacity);

                    // Dynamic Duration Calculation 
                    if ($exam['is_practice']) {
                        $actual_duration = $exam['duration_minutes'];
                    } else {
                        $start_ts = strtotime($exam['start_time']);
                        $end_ts = strtotime($exam['end_time']);
                        if ($end_ts < $start_ts) { $end_ts += 86400; } 
                        $actual_duration = round(abs($end_ts - $start_ts) / 60);
                    }

                    // Map Status to Colors
                    $color_class = ''; $badge_class = ''; $icon = '';
                    if ($status === 'upcoming') { $color_class = 'b-upcoming'; $badge_class = 'badge-upcoming'; $icon = 'fa-clock'; }
                    elseif ($status === 'ongoing') { $color_class = 'b-ongoing'; $badge_class = 'badge-ongoing'; $icon = 'fa-play-circle'; }
                    elseif ($status === 'completed') { $color_class = 'b-completed'; $badge_class = 'badge-completed'; $icon = 'fa-check-circle'; }
                    elseif ($status === 'inactive') { $color_class = 'b-inactive'; $badge_class = 'badge-inactive'; $icon = 'fa-eye-slash'; }
                    elseif ($status === 'practice') { $color_class = 'b-practice'; $badge_class = 'badge-practice'; $icon = 'fa-infinity'; }
                ?>
                <div class="exam-row" data-status="<?php echo $status; ?>">
                    
                    <div class="er-status-bar <?php echo $color_class; ?>"></div>

                    <div class="er-left">
                        <span class="er-code"><?php echo htmlspecialchars($exam['exam_code']); ?></span>
                        <span class="er-cat"><?php echo htmlspecialchars($exam['category_name']); ?></span>
                        <div class="badge <?php echo $badge_class; ?>" style="margin-top: 4px;">
                            <?php if($status === 'ongoing'): ?><div class="pulse-dot"></div><?php endif; ?>
                            <i class="fas <?php echo $icon; ?>"></i> 
                            <?php echo ucfirst($status); ?>
                        </div>
                    </div>
                    
                    <div class="er-middle">
                        
                        <?php if ($exam['is_practice']): ?>
                            <div class="detail-item">
                                <div class="detail-icon" style="color: var(--practice);"><i class="fas fa-globe"></i></div>
                                <div class="detail-text">
                                    <span class="detail-val">Online Portal</span>
                                    <span class="detail-sub">Always Open</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-calendar-day"></i></div>
                                <div class="detail-text">
                                    <span class="detail-val"><?php echo date('d M Y', strtotime($exam['exam_date'])); ?></span>
                                    <span class="detail-sub"><?php echo date('h:i A', strtotime($exam['start_time'])); ?> - <?php echo date('h:i A', strtotime($exam['end_time'])); ?></span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="detail-text">
                                    <span class="detail-val"><?php echo htmlspecialchars($exam['center_name']); ?></span>
                                    <span class="detail-sub"><?php echo htmlspecialchars($exam['city']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="detail-item">
                            <div class="detail-icon" style="color: <?php echo $exam['tp_name'] ? '#0D9488' : 'var(--primary)'; ?>; background: <?php echo $exam['tp_name'] ? '#CCFBF1' : 'var(--bg-body)'; ?>;">
                                <i class="fas <?php echo $exam['tp_name'] ? 'fa-chalkboard-teacher' : 'fa-users'; ?>"></i>
                            </div>
                            <div class="detail-text">
                                <span class="detail-val" style="color: <?php echo $exam['tp_name'] ? '#0D9488' : 'var(--text-dark)'; ?>;">
                                    <?php echo htmlspecialchars($exam['tp_name'] ?? 'Public Batch'); ?>
                                </span>
                                <span class="detail-sub"><?php echo $exam['tp_name'] ? 'Institute / TP' : 'Open Enrollment'; ?></span>
                            </div>
                        </div>

                        <div class="er-progress">
                            <div class="p-head">
                                <span>Seat Utilization</span>
                                <span style="color: <?php echo $is_full ? 'var(--danger)' : 'var(--text-dark)'; ?>;">
                                    <?php echo $reg_count; ?> <?php echo $exam['is_practice'] ? 'Enrolled' : '/ ' . $capacity; ?>
                                </span>
                            </div>
                            <?php if (!$exam['is_practice']): ?>
                            <div class="p-track">
                                <div class="p-fill <?php echo $is_full ? 'fill-full' : ''; ?>" style="width: <?php echo $fill_percentage; ?>%;"></div>
                            </div>
                            <?php endif; ?>
                        </div>

                    </div>
                    
                    <div class="er-right">
                        <a href="view-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-view" title="View Roster"><i class="fas fa-eye"></i> View</a>
                        <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-edit" title="Edit Settings"><i class="fas fa-pen"></i> Edit</a>
                        <a href="?toggle=<?php echo $exam['id']; ?>" class="btn btn-toggle" onclick="return confirm('Toggle visibility of this exam?')" title="<?php echo $exam['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                            <i class="fas <?php echo $exam['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i> <?php echo $exam['is_active'] ? 'Hide' : 'Show'; ?>
                        </a>
                        <a href="?delete=<?php echo $exam['id']; ?>" class="btn btn-delete" onclick="return confirm('⚠️ CRITICAL WARNING: This permanently deletes the exam, all candidate registrations, responses, and scorecards associated with it. Type OK to confirm or Cancel.')" title="Delete Exam">
                            <i class="fas fa-trash-alt"></i> Delete
                        </a>
                    </div>

                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Client-Side Searching and Filtering 
        const searchInput = document.getElementById('searchInput');
        const filterBtns = document.querySelectorAll('.filter-btn');
        const examRows = document.querySelectorAll('.exam-row');

        let currentFilter = 'all';

        // Filter by Tabs
        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentFilter = btn.getAttribute('data-filter');
                applyFilters();
            });
        });

        // Filter by Search
        searchInput.addEventListener('keyup', applyFilters);

        function applyFilters() {
            const query = searchInput.value.toLowerCase();
            
            examRows.forEach(row => {
                const status = row.getAttribute('data-status');
                const textContent = row.textContent.toLowerCase();
                
                const matchesFilter = (currentFilter === 'all' || status === currentFilter);
                const matchesSearch = textContent.includes(query);
                
                if (matchesFilter && matchesSearch) {
                    row.style.display = 'flex';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>