<?php
session_name('NIELIT_CANDIDATE_SESSION');
session_start();

// 1. Check if logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    header("Location: candidate-login.php");
    exit();
}

// 2. ANTI-BACK BUTTON CACHE HEADERS
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/../../config/database.php';

$error = '';
$candidate = [];
$active_exams = [];
$available_exams = [];
$recent_results = [];
$stats = ['total' => 0, 'upcoming_count' => 0, 'completed_count' => 0, 'attended_count' => 0, 'missed_count' => 0];

try {
    $userId = $_SESSION['user_id'];

    // LIVE AUTH CHECK
    $authCheck = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
    $authCheck->execute([$userId]);
    $userStatus = $authCheck->fetchColumn();

    if ($userStatus === false || $userStatus == false) {
        echo "<!DOCTYPE html><html><head><title>Access Revoked</title></head><body><script>alert('Account suspended.'); window.location.href = 'candidate-logout.php';</script></body></html>";
        exit();
    }

    // 1. Get candidate details
    $stmt = $pdo->prepare("SELECT u.*, c.registration_number FROM users u LEFT JOIN candidates c ON u.id = c.user_id WHERE u.id = ?");
    $stmt->execute([$userId]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $display_reg_id = !empty($candidate['registration_number']) ? $candidate['registration_number'] : $_SESSION['username'];
    
    // 2. Performance Metrics
    $perfQuery = $pdo->prepare("
        SELECT AVG(percentage) as avg_score, MAX(percentage) as top_score 
        FROM exam_results er
        JOIN exam_registrations reg ON er.registration_id = reg.id
        WHERE reg.candidate_id = ?
    ");
    $perfQuery->execute([$userId]);
    $perf = $perfQuery->fetch(PDO::FETCH_ASSOC);
    $avg_score = $perf['avg_score'] ? round($perf['avg_score'], 1) : 0;
    $top_score = $perf['top_score'] ? round($perf['top_score'], 1) : 0;

    // 3. Get registered exams
    $stmt = $pdo->prepare("
        SELECT es.*, ec.category_name, ec.category_code, c.center_name, c.city, er.registration_status, er.attendance_marked, er.id as registration_id,
        (SELECT COUNT(*) FROM exam_results WHERE registration_id = er.id) as result_exists
        FROM exam_registrations er 
        JOIN exam_sessions es ON er.session_id = es.id 
        JOIN exam_categories ec ON es.category_id = ec.id 
        LEFT JOIN exam_centers c ON es.center_id = c.id
        WHERE er.candidate_id = ? AND es.is_active = true
        ORDER BY es.exam_date ASC, es.start_time ASC
    ");
    $stmt->execute([$userId]);
    $all_registered = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $now = time();
    foreach ($all_registered as $exam) {
        
        $stats['total']++;
        if ($exam['attendance_marked']) $stats['attended_count']++;

        // 🟢 SMART LOGIC FIX: PRACTICE MODE WITH FINANCE PAYMENT GATEWAY
        if ($exam['is_practice']) {
            // Check the payment/registration status
            if ($exam['registration_status'] === 'pending_payment') {
                $exam['live_status'] = 'practice_pay';
            } elseif ($exam['registration_status'] === 'payment_submitted') {
                $exam['live_status'] = 'practice_verify';
            } else {
                // If 'approved' or legacy 'registered', they are allowed to take it
                if ($exam['result_exists'] > 0) {
                    $stats['completed_count']++; 
                    $exam['live_status'] = 'practice_retake'; 
                } else {
                    $exam['live_status'] = 'practice';
                }
            }
            $active_exams[] = $exam;

        } else {
            // STANDARD EXAM LOGIC
            $exam_start = strtotime($exam['exam_date'] . ' ' . $exam['start_time']);
            $exam_end = strtotime($exam['exam_date'] . ' ' . $exam['end_time']);
            
            if ($exam_end <= $exam_start) { $exam_end += 86400; }
            $cutoff_time = $exam_start + (30 * 60); 

            if ($exam['registration_status'] === 'completed' || $exam['result_exists'] > 0) {
                $stats['completed_count']++;
            } elseif ($now > $exam_end) {
                $stats['completed_count']++; 
            } else {
                if ($now > $cutoff_time && !$exam['attendance_marked']) {
                    $exam['live_status'] = 'missed';
                    $stats['missed_count']++;
                } elseif ($now >= $exam_start && $now <= $cutoff_time) {
                    $exam['live_status'] = 'ongoing';
                    $stats['upcoming_count']++;
                } else {
                    $exam['live_status'] = 'upcoming';
                    $stats['upcoming_count']++;
                }
                $active_exams[] = $exam;
            }
        }
    }
    
    // 4. Get available exams
    $stmt = $pdo->prepare("
        SELECT es.*, ec.category_name, c.city, c.capacity,
        (SELECT COUNT(*) FROM exam_registrations WHERE session_id = es.id) as reg_count
        FROM exam_sessions es 
        JOIN exam_categories ec ON es.category_id = ec.id 
        LEFT JOIN exam_centers c ON es.center_id = c.id
        WHERE es.is_active = true AND (es.exam_date >= CURRENT_DATE OR es.is_practice = true)
        AND es.booking_id IS NULL 
        AND es.id NOT IN (SELECT session_id FROM exam_registrations WHERE candidate_id = ?)
        ORDER BY es.is_practice DESC, es.exam_date ASC LIMIT 3
    ");
    $stmt->execute([$userId]);
    $available_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Get Recent Results
    $resStmt = $pdo->prepare("
        SELECT es.exam_code, ec.category_name, er.*, es.id as exam_id
        FROM exam_results er
        JOIN exam_registrations reg ON er.registration_id = reg.id
        JOIN exam_sessions es ON reg.session_id = es.id
        JOIN exam_categories ec ON es.category_id = ec.id
        WHERE reg.candidate_id = ?
        ORDER BY er.registration_id DESC LIMIT 4
    ");
    $resStmt->execute([$userId]);
    $recent_results = $resStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "System connection stable but database is under maintenance.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Workspace | NIELIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #059669; --primary-hover: #047857; --primary-bg: #D1FAE5; 
            --secondary: #0F172A; --accent: #10B981; --text-main: #1E293B; --text-muted: #64748B; 
            --bg-page: transparent; --glass-surface: rgba(255, 255, 255, 0.85); --border: rgba(226, 232, 240, 0.8); 
            --radius-lg: 24px; --radius-md: 16px; 
            --danger: #DC2626; --danger-bg: #FEE2E2; 
            --purple: #8B5CF6; --purple-bg: #EDE9FE; 
            --blue: #2563EB; --blue-bg: #DBEAFE;
            --shadow-sm: 0 4px 6px -1px rgba(5, 150, 105, 0.05); --shadow-glass: 0 20px 40px -10px rgba(5, 150, 105, 0.15);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { color: var(--text-main); overflow-x: hidden; position: relative; min-height: 100vh; background-color: #F8FAFC;}

        .ambient-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; overflow: hidden; pointer-events: none; background: radial-gradient(circle at 50% 0%, #DCE6F1 0%, #E8F0F8 50%, #F4F7FB 100%); perspective: 1000px; }
        .shape { position: absolute; background: linear-gradient(135deg, rgba(255, 255, 255, 0.6), rgba(5, 150, 105, 0.05)); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.7); box-shadow: 0 15px 35px rgba(5, 150, 105, 0.08), inset 0 0 20px rgba(255, 255, 255, 0.8); animation: float-3d 20s infinite linear; }
        .cube { width: 140px; height: 140px; border-radius: 28px; top: 15%; left: 5%; animation-duration: 28s; }
        .ring { width: 220px; height: 220px; border-radius: 50%; border: 35px solid rgba(255,255,255,0.3); top: 55%; right: 2%; animation-duration: 35s; animation-direction: reverse; background: transparent; }
        .pyramid { width: 90px; height: 90px; border-radius: 16px; bottom: 15%; left: 20%; animation-duration: 22s; }
        .sphere { width: 180px; height: 180px; border-radius: 50%; top: 8%; right: 15%; animation-duration: 40s; }
        @keyframes float-3d { 0% { transform: translateY(0) rotateX(0deg) rotateY(0deg) rotateZ(0deg); } 50% { transform: translateY(-50px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); } 100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); } }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-up { animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .delay-1 { animation-delay: 0.1s; } .delay-2 { animation-delay: 0.2s; } .delay-3 { animation-delay: 0.3s; }
        
        .navbar { background: rgba(255,255,255,0.7); backdrop-filter: blur(20px); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 1000; box-shadow: var(--shadow-sm);}
        .nav-brand { display: flex; align-items: center; gap: 12px; }
        .logo-box { background: var(--primary); color: white; width: 40px; height: 40px; border-radius: 10px; display: flex; justify-content: center; align-items: center; font-weight: 800; font-size: 20px; }
        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--secondary); line-height: 1.2;}
        .brand-text span { font-size: 12px; color: var(--text-muted); font-weight: 600; }
        .user-nav { display: flex; align-items: center; gap: 20px; }
        .profile-pill { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.9); padding: 6px 16px 6px 6px; border-radius: 50px; border: 1px solid var(--border); transition: 0.3s; cursor: pointer; text-decoration: none;}
        .profile-pill:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.1); }
        .avatar { width: 32px; height: 32px; background: var(--primary-bg); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; }
        .logout-btn { color: #EF4444; background: #FEE2E2; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.3s; text-decoration: none; }
        .logout-btn:hover { background: #EF4444; color: white; transform: rotate(10deg); }
        
        .dashboard-container { max-width: 1400px; margin: 30px auto; padding: 0 30px; display: grid; grid-template-columns: 2fr 1fr; gap: 30px; position: relative; z-index: 1;}
        .left-col { display: flex; flex-direction: column; gap: 30px; }
        
        .bento-card, .exam-box, .stat-card { background: var(--glass-surface); backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,1); border-radius: var(--radius-lg); box-shadow: var(--shadow-glass); }
        .welcome-card { background: linear-gradient(135deg, var(--secondary), #064E3B); color: white; padding: 40px; border-radius: var(--radius-lg); display: flex; justify-content: space-between; align-items: center; position: relative; overflow: hidden; box-shadow: var(--shadow-glass); }
        .welcome-card::before { content: ''; position: absolute; top: -50px; right: -50px; width: 300px; height: 300px; background: radial-gradient(circle, rgba(16, 185, 129, 0.4) 0%, transparent 70%); }
        .w-text h1 { font-size: 32px; font-weight: 800; margin-bottom: 8px; z-index: 2; position: relative; }
        .w-text p { color: #A7F3D0; font-size: 15px; z-index: 2; position: relative; }
        .reg-badge { background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); padding: 12px 20px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); text-align: right; z-index: 2; position: relative; }
        .reg-badge span { font-size: 11px; text-transform: uppercase; font-weight: 700; color: #A7F3D0; }
        .reg-badge strong { display: block; font-size: 20px; font-weight: 800; letter-spacing: 1px; color: white; }
        
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .stat-card { padding: 20px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 15px; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        .s-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .s-info h3 { font-size: 24px; font-weight: 800; color: var(--text-main); line-height: 1; }
        .s-info p { font-size: 12px; font-weight: 600; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; }
        
        .section-title { font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .section-title a { font-size: 13px; color: var(--primary); text-decoration: none; background: var(--primary-bg); padding: 6px 12px; border-radius: 8px; transition: 0.2s; }
        .section-title a:hover { background: var(--primary); color: white; }
        
        .exam-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .exam-box { padding: 25px; transition: 0.3s; position: relative; overflow: hidden; }
        .exam-box:hover { border-color: var(--primary); transform: translateY(-3px);}
        .eb-head { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .eb-title { font-size: 16px; font-weight: 800; color: var(--text-main); margin-bottom: 4px; }
        .eb-code { font-size: 12px; color: var(--text-muted); font-weight: 600; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; height: fit-content; }
        
        /* 🟢 NEW BADGE COLORS */
        .bg-live { background: #DCFCE7; color: #16A34A; animation: pulse 2s infinite; }
        .bg-wait { background: #FEF08A; color: #A16207; }
        .bg-pay { background: var(--blue-bg); color: var(--blue); }
        .bg-avail { background: var(--primary-bg); color: var(--primary); }
        .bg-missed { background: var(--danger-bg); color: var(--danger); }
        .bg-practice { background: var(--purple-bg); color: var(--purple); }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        
        .eb-meta { display: flex; flex-direction: column; gap: 8px; font-size: 13px; color: var(--text-muted); font-weight: 500; margin-bottom: 20px; }
        .eb-meta i { color: var(--primary); width: 16px; }
        
        .btn-group { display: flex; gap: 10px; }
        .btn { flex: 1; padding: 10px; text-align: center; border-radius: 10px; font-size: 13px; font-weight: 700; text-decoration: none; transition: 0.3s; border: 1px solid transparent; display: flex; justify-content: center; align-items: center; gap: 6px;}
        .btn-fill { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(5, 150, 105, 0.2); }
        .btn-fill:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .btn-out { border-color: rgba(226, 232, 240, 1); color: var(--text-main); background: rgba(255,255,255,0.5); }
        .btn-out:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }
        .btn-danger { background: var(--danger-bg); color: var(--danger); cursor: not-allowed; }
        .btn-purple { background: var(--purple); color: white; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.2);}
        .btn-purple:hover { transform: translateY(-2px); background: #7C3AED; }
        .btn-blue { background: var(--blue); color: white; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);}
        .btn-blue:hover { transform: translateY(-2px); background: #1D4ED8; }
        
        .right-col { display: flex; flex-direction: column; gap: 30px; }
        .bento-card { padding: 30px; }
        .perf-container { display: flex; flex-direction: column; align-items: center; text-align: center; }
        .gauge { position: relative; width: 140px; height: 140px; background: conic-gradient(var(--accent) <?php echo $avg_score; ?>%, #E2E8F0 0); border-radius: 50%; display: flex; justify-content: center; align-items: center; margin-bottom: 15px; }
        .gauge-inner { width: 110px; height: 110px; background: white; border-radius: 50%; display: flex; flex-direction: column; justify-content: center; align-items: center; box-shadow: inset 0 2px 5px rgba(0,0,0,0.05); }
        .gauge-inner h3 { font-size: 28px; font-weight: 800; color: var(--text-main); line-height: 1; }
        .gauge-inner span { font-size: 10px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; }
        .perf-stats { display: flex; width: 100%; justify-content: space-around; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border); }
        .p-stat h4 { font-size: 20px; font-weight: 800; color: var(--primary); }
        .p-stat p { font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }
        
        .score-list { display: flex; flex-direction: column; gap: 15px; margin-top: 15px; }
        .score-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border: 1px solid var(--border); border-radius: 12px; transition: 0.2s; text-decoration: none; background: rgba(255,255,255,0.5);}
        .score-item:hover { border-color: var(--primary); background: white; transform: translateX(5px);}
        .score-info h4 { font-size: 14px; font-weight: 800; color: var(--text-main); margin-bottom: 3px; }
        .score-info p { font-size: 12px; color: var(--text-muted); }
        .score-val { font-size: 18px; font-weight: 800; }
        .score-pass { color: var(--success); }
        .score-fail { color: var(--danger); }
        
        .notice-list { display: flex; flex-direction: column; gap: 15px; margin-top: 20px; }
        .notice-item { display: flex; gap: 15px; padding-bottom: 15px; border-bottom: 1px dashed var(--border); }
        .notice-item:last-child { border: none; padding-bottom: 0; }
        .n-icon { width: 36px; height: 36px; background: #FFF7ED; color: #CA8A04; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; }
        .n-text h4 { font-size: 13px; font-weight: 700; color: var(--text-main); margin-bottom: 3px; line-height: 1.3; }
        .n-text p { font-size: 12px; color: var(--text-muted); }
        
        @media (max-width: 1024px) { .dashboard-container { grid-template-columns: 1fr; } .exam-cards { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .stats-row { grid-template-columns: 1fr; } .welcome-card { flex-direction: column; text-align: center; gap: 20px; padding: 30px 20px; } .reg-badge { text-align: center; width: 100%; } .navbar { padding: 15px 20px; } .dashboard-container { padding: 0 20px; } }
    </style>
</head>
<body>

    <div class="ambient-bg">
        <div class="shape cube"></div>
        <div class="shape ring"></div>
        <div class="shape pyramid"></div>
        <div class="shape sphere"></div>
    </div>

    <nav class="navbar">
        <div class="nav-brand">
            <div class="logo-box">N</div>
            <div class="brand-text">
                <h2>NIELIT Portal</h2>
                <span>Candidate Environment</span>
            </div>
        </div>
        <div class="user-nav">
            <a href="profile.php" class="profile-pill">
                <div class="avatar"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'C', 0, 1))); ?></div>
                <span class="user-name" style="color: var(--text-main);"><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Candidate')[0]); ?></span>
            </a>
            <a href="candidate-logout.php" class="logout-btn" title="Secure Logout"><i class="fas fa-power-off"></i></a>
        </div>
    </nav>

    <div class="dashboard-container">
        
        <div class="left-col animate-up">
            
            <div class="welcome-card">
                <div class="w-text">
                    <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $candidate['full_name'] ?? 'Candidate')[0]); ?>!</h1>
                    <p>Track your academic progress, upcoming exams, and recent scorecards all in one place.</p>
                </div>
                <div class="reg-badge">
                    <span>Registration ID</span>
                    <strong><?php echo htmlspecialchars($display_reg_id); ?></strong>
                </div>
            </div>

            <div class="stats-row animate-up delay-1">
                <div class="stat-card">
                    <div class="s-icon" style="background: var(--primary-bg); color: var(--primary);"><i class="fas fa-folder-open"></i></div>
                    <div class="s-info">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Exams</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="s-icon" style="background: #FEF08A; color: #A16207;"><i class="fas fa-clock"></i></div>
                    <div class="s-info">
                        <h3><?php echo $stats['upcoming_count']; ?></h3>
                        <p>Upcoming</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="s-icon" style="background: var(--accent-bg); color: var(--accent);"><i class="fas fa-check-double"></i></div>
                    <div class="s-info">
                        <h3><?php echo $stats['completed_count']; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
            </div>

            <div class="animate-up delay-2">
                <div class="section-title">
                    <span><i class="fas fa-bolt" style="color: #EAB308; margin-right: 8px;"></i> Active & Scheduled</span>
                    <a href="my-exams.php?filter=upcoming">View Schedule</a>
                </div>

                <?php if (empty($active_exams)): ?>
                    <div class="exam-box" style="text-align: center; padding: 40px;">
                        <i class="far fa-calendar-check" style="font-size: 40px; color: rgba(226, 232, 240, 1); margin-bottom: 15px;"></i>
                        <h4 style="color: var(--text-main); margin-bottom: 5px;">No Scheduled Exams</h4>
                        <p style="color: var(--text-muted); font-size: 13px;">You are completely caught up.</p>
                    </div>
                <?php else: ?>
                    <div class="exam-cards">
                        <?php foreach ($active_exams as $exam): ?>
                            <div class="exam-box" style="<?php echo $exam['live_status'] == 'missed' ? 'opacity: 0.7;' : ''; ?>">
                                <div class="eb-head">
                                    <div>
                                        <div class="eb-title" style="<?php echo $exam['live_status'] == 'missed' ? 'text-decoration: line-through;' : ''; ?>"><?php echo htmlspecialchars($exam['category_name']); ?></div>
                                        <div class="eb-code"><?php echo htmlspecialchars($exam['exam_code']); ?></div>
                                    </div>
                                    <span class="badge <?php 
                                        if($exam['live_status'] == 'ongoing') echo 'bg-live';
                                        elseif($exam['live_status'] == 'missed') echo 'bg-missed';
                                        elseif($exam['live_status'] == 'practice_pay') echo 'bg-pay';
                                        elseif($exam['live_status'] == 'practice_verify') echo 'bg-wait';
                                        elseif($exam['live_status'] == 'practice' || $exam['live_status'] == 'practice_retake') echo 'bg-practice';
                                        else echo 'bg-wait'; 
                                    ?>">
                                        <?php 
                                            if($exam['live_status'] == 'ongoing') echo 'Live Now';
                                            elseif($exam['live_status'] == 'missed') echo 'Missed';
                                            elseif($exam['live_status'] == 'practice_pay') echo 'Payment Required';
                                            elseif($exam['live_status'] == 'practice_verify') echo 'Verifying Payment';
                                            elseif($exam['live_status'] == 'practice' || $exam['live_status'] == 'practice_retake') echo 'Practice';
                                            else echo 'Upcoming'; 
                                        ?>
                                    </span>
                                </div>
                                <div class="eb-meta">
                                    <?php if ($exam['is_practice']): ?>
                                        <div><i class="far fa-calendar-alt"></i> 24/7 Availability</div>
                                        <div><i class="fas fa-money-bill-wave" style="color: var(--blue);"></i> Fee: ₹50.00</div>
                                    <?php else: ?>
                                        <div><i class="far fa-calendar-alt"></i> <?php echo date('d M Y, h:i A', strtotime($exam['exam_date'] . ' ' . $exam['start_time'])); ?></div>
                                        <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($exam['center_name']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group">
                                    <?php if ($exam['live_status'] === 'ongoing'): ?>
                                        <a href="take-exam.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-fill" style="background: #16A34A;"><i class="fas fa-play"></i> Start Exam</a>
                                    <?php elseif ($exam['live_status'] === 'missed'): ?>
                                        <button class="btn btn-danger" disabled><i class="fas fa-times-circle"></i> Late by 30+ Mins</button>
                                    
                                    <?php elseif ($exam['live_status'] === 'practice_pay'): ?>
                                        <a href="practice-payment.php?reg_id=<?php echo $exam['registration_id']; ?>" class="btn btn-blue"><i class="fas fa-rupee-sign"></i> Pay ₹50 to Unlock</a>
                                    
                                    <?php elseif ($exam['live_status'] === 'practice_verify'): ?>
                                        <button class="btn btn-out" disabled><i class="fas fa-spinner fa-spin"></i> Under Review</button>
                                        
                                    <?php elseif ($exam['live_status'] === 'practice_retake'): ?>
                                        <a href="take-exam.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-purple"><i class="fas fa-redo"></i> Retake Practice</a>
                                    
                                    <?php elseif ($exam['live_status'] === 'practice'): ?>
                                        <a href="take-exam.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-purple"><i class="fas fa-play"></i> Start Practice</a>
                                    
                                    <?php else: ?>
                                        <a href="exam-instructions.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-out"><i class="fas fa-info-circle"></i> Guide</a>
                                        <a href="admit-card.php?reg_id=<?php echo $exam['registration_id']; ?>" target="_blank" class="btn btn-fill"><i class="fas fa-file-pdf"></i> Admit Card</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="animate-up delay-3" style="margin-bottom: 40px;">
                <div class="section-title">
                    <span><i class="fas fa-compass" style="color: var(--primary); margin-right: 8px;"></i> Open Enrollments</span>
                    <a href="available-exams.php">Browse All</a>
                </div>

                <?php if (empty($available_exams)): ?>
                    <div class="exam-box" style="text-align: center; padding: 40px;">
                        <p style="color: var(--text-muted); font-size: 13px;">No new exams are currently open for registration.</p>
                    </div>
                <?php else: ?>
                    <div class="exam-cards">
                        <?php foreach ($available_exams as $exam): ?>
                            <div class="exam-box">
                                <div class="eb-head">
                                    <div>
                                        <div class="eb-title"><?php echo htmlspecialchars($exam['category_name']); ?></div>
                                        <div class="eb-code"><?php echo htmlspecialchars($exam['exam_code']); ?></div>
                                    </div>
                                    <span class="badge bg-avail">Open</span>
                                </div>
                                <div class="eb-meta">
                                    <?php if ($exam['is_practice']): ?>
                                        <div><i class="far fa-calendar-alt"></i> Always Open</div>
                                        <div><i class="fas fa-money-bill-wave" style="color: var(--blue);"></i> Fee: ₹50.00</div>
                                    <?php else: ?>
                                        <div><i class="far fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($exam['exam_date'])); ?></div>
                                        <div><i class="fas fa-building"></i> <?php echo htmlspecialchars($exam['city']); ?> Center</div>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group">
                                    <a href="register-exam.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-fill"><i class="fas fa-user-plus"></i> Enroll Now</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <div class="right-col animate-up delay-2">
            
            <div class="bento-card">
                <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 25px;"><i class="fas fa-chart-pie" style="color: var(--primary); margin-right: 8px;"></i> Academic Analytics</h3>
                
                <div class="perf-container">
                    <div class="gauge">
                        <div class="gauge-inner">
                            <h3><?php echo $avg_score; ?>%</h3>
                            <span>Avg Score</span>
                        </div>
                    </div>
                    <p style="font-size: 13px; color: var(--text-muted); font-weight: 500;">Based on <?php echo $stats['completed_count']; ?> completed exams.</p>
                    
                    <div class="perf-stats">
                        <div class="p-stat">
                            <h4><?php echo $top_score; ?>%</h4>
                            <p>Highest Score</p>
                        </div>
                        <div class="p-stat" style="border-left: 1px solid rgba(226, 232, 240, 1); padding-left: 20px;">
                            <h4 style="color: var(--accent);"><?php echo $stats['attended_count']; ?></h4>
                            <p>Attendance</p>
                        </div>
                    </div>
                    
                    <a href="my-exams.php?filter=completed" class="btn btn-out" style="width: 100%; margin-top: 20px;"><i class="fas fa-history"></i> View All Scorecards</a>
                </div>
            </div>

            <div class="bento-card">
                <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 5px;"><i class="fas fa-award" style="color: var(--primary); margin-right: 8px;"></i> Recent Results</h3>
                
                <?php if (empty($recent_results)): ?>
                    <p style="font-size: 13px; color: var(--text-muted); text-align: center; padding: 20px 0;">No results published yet.</p>
                <?php else: ?>
                    <div class="score-list">
                        <?php foreach ($recent_results as $res): 
                            $isPass = false;
                            if (isset($res['result_status'])) {
                                $isPass = (strtolower($res['result_status']) === 'pass');
                            } elseif (isset($res['is_pass'])) {
                                $isPass = ($res['is_pass'] === true || $res['is_pass'] === 't' || $res['is_pass'] == 1);
                            }
                            $scoreVal = $res['percentage'] ?? $res['score'] ?? $res['total_marks_obtained'] ?? 0;
                        ?>
                            <a href="exam-result.php?exam_id=<?php echo $res['exam_id']; ?>" class="score-item">
                                <div class="score-info">
                                    <h4><?php echo htmlspecialchars($res['category_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($res['exam_code']); ?></p>
                                </div>
                                <div class="score-val <?php echo $isPass ? 'score-pass' : 'score-fail'; ?>">
                                    <?php echo round($scoreVal, 1); ?>%
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bento-card">
                <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 5px;"><i class="fas fa-bullhorn" style="color: #EA580C; margin-right: 8px;"></i> Notice Board</h3>
                <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 15px;">Latest updates from the admin desk.</p>
                
                <div class="notice-list">
                    <div class="notice-item">
                        <div class="n-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="n-text">
                            <h4>Strict adherence to timings</h4>
                            <p>Candidates must report 30 mins prior. Late entries are strictly prohibited.</p>
                        </div>
                    </div>
                    <div class="notice-item">
                        <div class="n-icon" style="background: var(--primary-bg); color: var(--primary);"><i class="fas fa-id-card"></i></div>
                        <div class="n-text">
                            <h4>Admit Card Mandatory</h4>
                            <p>Please carry a printed copy of your admit card along with valid Govt ID.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <script>
        window.history.pushState({ page: "dashboard" }, "", window.location.href);
        let backButtonCount = 0;
        window.addEventListener('popstate', function(event) {
            window.history.pushState({ page: "dashboard" }, "", window.location.href);
            backButtonCount++;
            if (backButtonCount === 1) {
                alert("⚠️ SECURITY WARNING: Browser 'Back' navigation is disabled. Please use the on-screen menu or HTML buttons to navigate. Clicking the browser back button again will automatically log you out.");
            } else {
                window.location.replace("candidate-logout.php");
            }
        });
    </script>
</body>
</html>