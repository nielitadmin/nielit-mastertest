<?php
// FIX 1: Enforce Strict Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

session_name('NIELIT_CANDIDATE_SESSION');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    header("Location: candidate-login.php");
    exit();
}

// 🟢 NEW ARCHITECTURE: Centralized database connection
require_once __DIR__ . '/../../config/database.php';

$filter = $_GET['filter'] ?? 'all';
$message = '';
$messageType = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'registration_success') {
        $message = "Exam registration completed successfully!";
        $messageType = 'success';
    } elseif ($_GET['msg'] === 'already_registered') {
        $message = "You are already registered for this exam session.";
        $messageType = 'warning';
    } elseif ($_GET['msg'] === 'result_pending') {
        $message = "Your result is still being processed. Please check back later.";
        $messageType = 'warning';
    }
}

$my_exams = [];
$stats = ['total' => 0, 'upcoming' => 0, 'completed' => 0, 'attended' => 0, 'missed' => 0];
$error = '';

try {
    // $pdo is securely imported from database.php
    
    // FIX 3: Use LEFT JOIN for exam_centers to support Practice Exams
    $query = "
        SELECT 
            es.*, ec.category_name, ec.category_code, ec.duration_minutes, ec.pass_percentage,
            c.center_name, c.center_code, c.city, c.address as center_address,
            er.id as registration_id, er.registration_status, er.registered_at, 
            er.attendance_marked, er.seat_number, er.admit_card_url,
            (SELECT COUNT(*) FROM candidate_responses WHERE registration_id = er.id) as answers_count,
            (SELECT COUNT(*) FROM questions WHERE category_id = es.category_id AND is_active = true) as total_questions,
            (SELECT COUNT(*) FROM exam_results WHERE registration_id = er.id) as result_exists
        FROM exam_registrations er
        JOIN exam_sessions es ON er.session_id = es.id
        JOIN exam_categories ec ON es.category_id = ec.id
        LEFT JOIN exam_centers c ON es.center_id = c.id
        WHERE er.candidate_id = ?
        ORDER BY es.is_practice DESC, es.exam_date ASC, es.start_time ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $all_exams_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $now = time();
    
    foreach ($all_exams_raw as $exam) {
        $stats['total']++;
        if ($exam['attendance_marked']) $stats['attended']++;

        // Practice Exam Logic
        if ($exam['is_practice']) {
            $status = 'practice';
            if ($exam['result_exists'] > 0) {
                $stats['completed']++;
            }
        } 
        // Formal Exam Logic
        else {
            // TIMEZONE FIX: Strip DB offsets
            $date_clean = explode(' ', $exam['exam_date'])[0];
            $start_clean = explode('+', $exam['start_time'])[0];
            $end_clean = explode('+', $exam['end_time'])[0];

            $exam_start = strtotime($date_clean . ' ' . $start_clean);
            $exam_end = strtotime($date_clean . ' ' . $end_clean);
            
            // STRICT 30-MINUTE LATE POLICY
            $cutoff_time = $exam_start + (30 * 60); 
            
            // Determine exact status
            if ($exam['registration_status'] === 'completed' || $exam['result_exists'] > 0) {
                $status = 'completed';
                $stats['completed']++;
            } elseif ($now > $exam_end) {
                $status = 'completed';
                $stats['completed']++; 
            } elseif ($now > $cutoff_time && !$exam['attendance_marked']) {
                $status = 'missed';
                $stats['missed']++;
                $stats['completed']++; // Treat missed as completed so it moves out of active
            } elseif ($now >= $exam_start && $now <= $cutoff_time) {
                $status = 'ongoing';
                $stats['upcoming']++;
            } else {
                $status = 'upcoming';
                $stats['upcoming']++;
            }
        }
        
        $exam['calc_status'] = $status;

        // Apply UI Filter
        if ($filter == 'all' || 
            ($filter == 'upcoming' && in_array($status, ['upcoming', 'ongoing', 'practice'])) || 
            ($filter == 'completed' && in_array($status, ['completed', 'missed']))) {
            $my_exams[] = $exam;
        }
    }
    
    // Custom sort: Ongoing first -> Practice -> Upcoming -> Missed -> Completed
    usort($my_exams, function($a, $b) {
        $weights = ['ongoing' => 0, 'practice' => 1, 'upcoming' => 2, 'missed' => 3, 'completed' => 4];
        $wA = $weights[$a['calc_status']] ?? 5;
        $wB = $weights[$b['calc_status']] ?? 5;
        
        if ($wA === $wB) {
            $timeA = $a['is_practice'] ? 0 : strtotime($a['exam_date'] . ' ' . $a['start_time']);
            $timeB = $b['is_practice'] ? 0 : strtotime($b['exam_date'] . ' ' . $b['start_time']);
            return $timeA <=> $timeB;
        }
        return $wA <=> $wB;
    });
    
} catch (PDOException $e) {
    $error = "System Database Offline. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Exam History | NIELIT Candidate Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1D4ED8;        --primary-light: #3B82F6;  --primary-bg: #DBEAFE; 
            --success: #10B981;        --success-bg: #D1FAE5;
            --warning: #F59E0B;        --warning-bg: #FEF3C7;
            --danger: #EF4444;         --danger-bg: #FEE2E2;
            --purple: #8B5CF6;         --purple-bg: #EDE9FE;
            --text-main: #0F172A;      --text-muted: #64748B;
            --bg-page: #F4F7FB;        --white: #FFFFFF;
            --border: #E2E8F0;         --radius-md: 12px; --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-page); color: var(--text-main); min-height: 100vh; overflow-x: hidden; padding-bottom: 40px; }

        /* --- 3D AMBIENT BACKGROUND --- */
        .ambient-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; overflow: hidden; background: radial-gradient(circle at 50% 0%, #DCE6F1 0%, #E8F0F8 50%, #F4F7FB 100%); }
        .shape { position: absolute; background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(29,78,216,0.05)); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.6); box-shadow: 0 10px 30px rgba(0,0,0,0.03); animation: float 20s infinite linear; }
        .cube { width: 140px; height: 140px; border-radius: 28px; top: 15%; left: 8%; animation-duration: 25s; }
        .sphere { width: 180px; height: 180px; border-radius: 50%; bottom: 15%; right: 10%; animation-duration: 35s; animation-direction: reverse; }
        @keyframes float { 0% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-40px) rotate(180deg); } 100% { transform: translateY(0) rotate(360deg); } }

        /* --- ENTRANCE ANIMATIONS --- */
        @keyframes fadeUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .animate-up { animation: fadeUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .delay-1 { animation-delay: 0.1s; } .delay-2 { animation-delay: 0.2s; } .delay-3 { animation-delay: 0.3s; }

        /* --- NAVBAR --- */
        .navbar { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(15px); padding: 12px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.5); position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--bg-page); border: 1px solid var(--border); padding: 8px 16px; border-radius: 8px; color: var(--text-main); text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.3s; }
        .btn-back:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); transform: translateX(-3px); }
        .brand-text h2 { font-size: 16px; font-weight: 800; color: var(--primary); line-height: 1.2; }
        .user-pill { display: flex; align-items: center; gap: 10px; background: var(--white); padding: 5px 12px 5px 5px; border-radius: 50px; border: 1px solid var(--border); }
        .avatar-circle { width: 28px; height: 28px; background: var(--primary); color: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px;}

        /* --- COMPACT LAYOUT --- */
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        
        .top-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; }
        .page-header h1 { font-size: 22px; font-weight: 800; display: flex; align-items: center; gap: 10px; color: var(--text-main); }
        .page-header h1 i { color: var(--primary); font-size: 20px; }

        /* --- COMPACT STATS GRID --- */
        .stats-grid { display: flex; gap: 12px; margin-bottom: 20px; }
        .stat-card { flex: 1; background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); padding: 12px 16px; border-radius: var(--radius-md); border: 1px solid var(--border); display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .s-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .stat-card:nth-child(1) .s-icon { background: var(--primary-bg); color: var(--primary); }
        .stat-card:nth-child(2) .s-icon { background: var(--warning-bg); color: #D97706; }
        .stat-card:nth-child(3) .s-icon { background: var(--success-bg); color: var(--success); }
        .stat-card:nth-child(4) .s-icon { background: var(--danger-bg); color: var(--danger); }
        .s-info h3 { font-size: 18px; font-weight: 800; color: var(--text-main); line-height: 1; margin-bottom: 2px; }
        .s-info p { font-size: 10px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; }

        /* --- SEGMENTED TABS --- */
        .filter-tabs { display: inline-flex; background: rgba(255,255,255,0.8); backdrop-filter: blur(10px); padding: 4px; border-radius: 50px; border: 1px solid var(--border); }
        .filter-tab { padding: 6px 20px; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 12px; color: var(--text-muted); transition: 0.2s; }
        .filter-tab:hover { color: var(--primary); }
        .filter-tab.active { background: var(--primary); color: var(--white); box-shadow: 0 2px 6px rgba(29, 78, 216, 0.2); }

        /* --- HORIZONTAL LIST VIEW (UX FRIENDLY) --- */
        .exams-list { display: flex; flex-direction: column; gap: 12px; }

        .exam-row { 
            display: flex; background: var(--white); border: 1px solid var(--border); 
            border-radius: var(--radius-md); transition: 0.2s; overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .exam-row:hover { transform: translateY(-2px); box-shadow: 0 8px 15px -3px rgba(29, 78, 216, 0.08); border-color: var(--primary-light); }
        .exam-row.missed { opacity: 0.7; }

        /* Status Color Bar */
        .er-status-bar { width: 6px; flex-shrink: 0; }
        .b-upcoming { background: var(--warning); }
        .b-ongoing { background: var(--success); }
        .b-completed { background: var(--border); }
        .b-missed { background: var(--danger); }
        .b-practice { background: var(--purple); }

        /* Left Content: Title & Badge */
        .er-left { width: 280px; padding: 16px 20px; display: flex; flex-direction: column; justify-content: center; gap: 6px; border-right: 1px dashed var(--border); }
        .er-code { font-size: 11px; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px;}
        .er-title { font-size: 15px; font-weight: 800; color: var(--text-main); line-height: 1.2; }
        .missed .er-title { text-decoration: line-through; }
        .er-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 800; padding: 3px 8px; border-radius: 4px; width: fit-content; text-transform: uppercase; }
        .badge-upcoming { background: var(--warning-bg); color: #B45309; }
        .badge-ongoing { background: var(--success-bg); color: var(--success); }
        .badge-completed { background: var(--bg-page); color: var(--text-muted); }
        .badge-missed { background: var(--danger-bg); color: var(--danger); }
        .badge-practice { background: var(--purple-bg); color: var(--purple); }

        .pulse-dot { width: 6px; height: 6px; background: var(--success); border-radius: 50%; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); } 70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }

        /* Middle Content: Details Grid */
        .er-middle { flex: 1; padding: 16px 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: center; }
        
        .detail-item { display: flex; align-items: flex-start; gap: 8px; }
        .detail-icon { width: 28px; height: 28px; background: var(--bg-page); color: var(--primary); border-radius: 8px; display: flex; justify-content: center; align-items: center; font-size: 12px; flex-shrink: 0; }
        .detail-text { display: flex; flex-direction: column; gap: 2px;}
        .detail-val { font-size: 13px; font-weight: 700; color: var(--text-main); }
        .detail-sub { font-size: 11px; color: var(--text-muted); font-weight: 500; }
        .detail-item.full-width { grid-column: 1 / -1; }

        /* Progress Bar (Compact) */
        .er-progress { background: var(--bg-page); padding: 10px 15px; border-radius: 8px; grid-column: 1 / -1; }
        .p-head { display: flex; justify-content: space-between; font-size: 11px; font-weight: 800; color: var(--text-main); margin-bottom: 6px; }
        .p-track { width: 100%; height: 6px; background: var(--border); border-radius: 10px; overflow: hidden; }
        .p-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--primary-light)); border-radius: 10px; transition: width 1s ease; }

        /* Right Content: Actions */
        .er-right { width: 220px; background: #F8FAFC; padding: 16px; border-left: 1px solid var(--border); display: flex; flex-direction: column; justify-content: center; gap: 8px; }
        
        .btn { padding: 10px; border-radius: 8px; font-size: 12px; font-weight: 800; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; transition: 0.2s; border: 1px solid transparent; cursor: pointer; width: 100%;}
        .btn-fill { background: var(--primary); color: var(--white); box-shadow: 0 2px 6px rgba(29, 78, 216, 0.2); }
        .btn-fill:hover { background: #1E40AF; transform: translateY(-1px); }
        .btn-green { background: var(--success); color: var(--white); box-shadow: 0 2px 6px rgba(16, 185, 129, 0.2); }
        .btn-green:hover { background: #059669; transform: translateY(-1px); }
        .btn-purple { background: var(--purple); color: var(--white); box-shadow: 0 2px 6px rgba(139, 92, 246, 0.2); }
        .btn-purple:hover { background: #7C3AED; transform: translateY(-1px); }
        .btn-out { background: var(--white); border-color: var(--border); color: var(--text-main); }
        .btn-out:hover { background: var(--bg-page); border-color: var(--primary); color: var(--primary); }
        .btn-danger { background: var(--danger-bg); color: var(--danger); cursor: not-allowed; border-color: #FECACA;}
        .btn-danger:hover { transform: none; }

        .empty-state { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 40px 20px; border-radius: var(--radius-md); border: 1px dashed var(--border); text-align: center; }
        .empty-state i { font-size: 40px; color: var(--border); margin-bottom: 15px; }
        .empty-state h3 { font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 5px; }
        .empty-state p { color: var(--text-muted); font-weight: 500; font-size: 13px; margin-bottom: 20px; }

        @media (max-width: 992px) { 
            .stats-grid { flex-wrap: wrap; }
            .stat-card { min-width: 45%; }
            .exam-row { flex-direction: column; }
            .er-status-bar { width: 100%; height: 6px; }
            .er-left, .er-right { width: 100%; border-right: none; }
            .er-middle { border-left: none; border-top: 1px dashed var(--border); border-bottom: 1px dashed var(--border); }
            .er-right { flex-direction: row; border-left: none; }
        }
        @media (max-width: 768px) {
            .navbar { padding: 12px 20px; }
            .container { padding: 0 15px; }
            .top-section { flex-direction: column; align-items: flex-start; gap: 15px; }
            .er-middle { grid-template-columns: 1fr; }
            .er-right { flex-direction: column; }
        }
    </style>
</head>
<body>

    <div class="ambient-canvas">
        <div class="shape cube"></div>
        <div class="shape sphere"></div>
    </div>

    <nav class="navbar">
        <div class="nav-left">
            <a href="candidate-dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> <span class="hide-mobile">Dashboard</span></a>
            <div class="brand-text">
                <h2>History Log</h2>
            </div>
        </div>
        <div class="user-pill">
            <span class="hide-mobile" style="font-weight: 700; font-size: 12px;"><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Candidate')[0]); ?></span>
            <div class="avatar-circle"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'C', 0, 1)); ?></div>
        </div>
    </nav>

    <div class="container">
        
        <div class="top-section animate-up">
            <div class="page-header">
                <h1><i class="fas fa-list-ul"></i> My Examinations</h1>
            </div>
            
            <div class="filter-tabs">
                <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">All</a>
                <a href="?filter=upcoming" class="filter-tab <?php echo $filter == 'upcoming' ? 'active' : ''; ?>">Upcoming/Live</a>
                <a href="?filter=completed" class="filter-tab <?php echo $filter == 'completed' ? 'active' : ''; ?>">Completed</a>
            </div>
        </div>

        <div class="stats-grid animate-up delay-1">
            <div class="stat-card">
                <div class="s-icon"><i class="fas fa-folder-open"></i></div>
                <div class="s-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Exams</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="s-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="s-info">
                    <h3><?php echo $stats['upcoming']; ?></h3>
                    <p>Upcoming</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="s-icon"><i class="fas fa-check-double"></i></div>
                <div class="s-info">
                    <h3><?php echo $stats['completed']; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="s-icon"><i class="fas fa-user-times"></i></div>
                <div class="s-info">
                    <h3><?php echo $stats['missed']; ?></h3>
                    <p>Missed</p>
                </div>
            </div>
        </div>

        <div class="exams-list animate-up delay-2">
            <?php if (empty($my_exams)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Registrations Found</h3>
                    <p>You haven't registered for any exams that match this filter.</p>
                    <a href="available-exams.php" class="btn btn-fill" style="width: auto; display: inline-flex; padding: 10px 25px;"><i class="fas fa-search"></i> Browse Exams</a>
                </div>
            <?php else: ?>
                <?php foreach ($my_exams as $exam): 
                    $status = $exam['calc_status'];
                    
                    // --- DYNAMIC DURATION CALCULATION ---
                    if ($exam['is_practice']) {
                        $actual_duration = $exam['duration_minutes'];
                    } else {
                        $start_ts = strtotime($exam['start_time']);
                        $end_ts = strtotime($exam['end_time']);
                        if ($end_ts < $start_ts) { $end_ts += 86400; } // Handle spanning midnight
                        $actual_duration = round(abs($end_ts - $start_ts) / 60);
                    }

                    // Button Logic
                    if ($status === 'upcoming') {
                        $color_class = 'b-upcoming';
                        $badge_class = 'badge-upcoming';
                        $icon = 'fa-clock';
                        $status_text = 'Upcoming';
                        $btn1 = '<a href="exam-instructions.php?exam_id='.$exam['id'].'" class="btn btn-out"><i class="fas fa-book-reader"></i> Guidelines</a>';
                        $btn2 = '<a href="generate-admit-card.php?exam_id='.$exam['id'].'" class="btn btn-out" style="border-color: var(--primary); color: var(--primary);"><i class="fas fa-download"></i> Admit Card</a>';
                    } elseif ($status === 'ongoing') {
                        $color_class = 'b-ongoing';
                        $badge_class = 'badge-ongoing';
                        $icon = 'fa-play-circle';
                        $status_text = 'Live Now';
                        $btn1 = '<a href="take-exam.php?exam_id='.$exam['id'].'" class="btn btn-green"><i class="fas fa-laptop-code"></i> Launch Terminal</a>';
                        $btn2 = '';
                    } elseif ($status === 'missed') {
                        $color_class = 'b-missed';
                        $badge_class = 'badge-missed';
                        $icon = 'fa-times-circle';
                        $status_text = 'Missed';
                        $btn1 = '<button class="btn btn-danger" disabled><i class="fas fa-ban"></i> Late by 30+ Mins</button>';
                        $btn2 = '';
                    } elseif ($status === 'practice') {
                        $color_class = 'b-practice';
                        $badge_class = 'badge-practice';
                        $icon = 'fa-infinity';
                        $status_text = 'Practice';
                        if ($exam['result_exists'] > 0) {
                            $btn1 = '<a href="exam-result.php?exam_id='.$exam['id'].'" class="btn btn-out"><i class="fas fa-award"></i> View Score</a>';
                            $btn2 = '<a href="start-practice.php?exam_id='.$exam['id'].'" class="btn btn-purple" onclick="return confirm(\'Restarting will erase your previous practice score. Continue?\')"><i class="fas fa-redo"></i> Retake</a>';
                        } else {
                            $btn1 = '<a href="take-exam.php?exam_id='.$exam['id'].'" class="btn btn-purple"><i class="fas fa-play"></i> Start Practice</a>';
                            $btn2 = '';
                        }
                    } else {
                        $color_class = 'b-completed';
                        $badge_class = 'badge-completed';
                        $icon = 'fa-check-circle';
                        $status_text = 'Completed';
                        $btn1 = '<a href="exam-result.php?exam_id='.$exam['id'].'" class="btn btn-fill"><i class="fas fa-award"></i> View Scorecard</a>';
                        $btn2 = '';
                    }
                ?>
                <div class="exam-row <?php echo $status === 'missed' ? 'missed' : ''; ?>">
                    
                    <div class="er-status-bar <?php echo $color_class; ?>"></div>

                    <div class="er-left">
                        <div class="er-code"><?php echo htmlspecialchars($exam['exam_code']); ?></div>
                        <div class="er-title"><?php echo htmlspecialchars($exam['category_name']); ?></div>
                        <div class="er-badge <?php echo $badge_class; ?>">
                            <?php if($status === 'ongoing'): ?><div class="pulse-dot"></div><?php endif; ?>
                            <i class="fas <?php echo $icon; ?>"></i> <?php echo $status_text; ?>
                        </div>
                    </div>

                    <div class="er-middle">
                        
                        <?php if ($exam['is_practice']): ?>
                            <div class="detail-item">
                                <div class="detail-icon" style="color: var(--purple);"><i class="fas fa-globe"></i></div>
                                <div class="detail-text">
                                    <span class="detail-val">Online Portal</span>
                                    <span class="detail-sub">Available 24/7</span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon" style="color: var(--purple);"><i class="fas fa-stopwatch"></i></div>
                                <div class="detail-text">
                                    <span class="detail-val"><?php echo $actual_duration; ?> Mins</span>
                                    <span class="detail-sub">Duration</span>
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
                            <div class="detail-item full-width" style="padding-top: 5px;">
                                <div class="detail-icon" style="height: 20px; width: 28px;"><i class="fas fa-user-tie"></i></div>
                                <div class="detail-text" style="flex-direction: row; align-items: baseline;">
                                    <span class="detail-sub" style="margin: 0;">Conductor:</span>
                                    <span class="detail-val" style="font-size: 11px;"><?php echo htmlspecialchars($exam['exam_conductor'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($status === 'ongoing' || $status === 'completed'): ?>
                            <div class="er-progress">
                                <div class="p-head">
                                    <span>Progress</span>
                                    <span><?php echo $exam['answers_count']; ?>/<?php echo $exam['total_questions']; ?> Ans</span>
                                </div>
                                <?php $percent = $exam['total_questions'] > 0 ? ($exam['answers_count'] / $exam['total_questions']) * 100 : 0; ?>
                                <div class="p-track">
                                    <div class="p-fill" style="width: <?php echo min(100, $percent); ?>%;"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="er-right">
                        <?php echo $btn1; ?>
                        <?php echo $btn2; ?>
                    </div>

                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>