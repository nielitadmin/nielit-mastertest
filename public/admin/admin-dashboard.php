<?php
// Force PHP to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

// Start session with admin-specific name
session_name('NIELIT_ADMIN_SESSION');
session_start();

// --- STRICT ANTI-CACHE HEADERS ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: admin-login.php");
    exit();
}

// Initialize variables
$stats = [
    'total_users' => 0, 'total_candidates' => 0, 'total_admins' => 0,
    'total_exams' => 0, 'upcoming_exams' => 0, 'total_questions' => 0,
    'total_centers' => 0, 'total_registrations' => 0,
    'active_centers_today' => 0, 'completed_today' => 0, 'testing_now' => 0, 'missed_today' => 0
];
$recentUsers = [];
$top_tps = []; // Array for Leaderboard
$error = '';

// ============================================================================
// NEW ARCHITECTURE: Import centralized database connection
// Path assumes this file is in: /public/admin/admin-dashboard.php
// ============================================================================
require_once '../../config/database.php';

try {
    // 1. Fetch High-Level Static Stats
    $statsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM exam_sessions) as total_exams,
            (SELECT COUNT(*) FROM questions WHERE is_active = true) as total_questions,
            (SELECT COUNT(*) FROM exam_registrations) as total_registrations,
            (SELECT COUNT(DISTINCT center_id) FROM exam_sessions WHERE exam_date = CURRENT_DATE) as active_centers_today
    ";
    
    $statsResult = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);
    if ($statsResult) {
        $stats = array_merge($stats, $statsResult);
    }
    
    // 2. SMART LIVE MONITOR LOGIC
    $liveQuery = "
        SELECT 
            es.start_time, 
            es.end_time, 
            es.exam_date,
            es.is_practice, 
            er.attendance_marked, 
            res.registration_id as result_id,
            (SELECT COUNT(*) FROM candidate_responses WHERE registration_id = er.id) as response_count
        FROM exam_registrations er
        JOIN exam_sessions es ON er.session_id = es.id
        LEFT JOIN exam_results res ON er.id = res.registration_id
        WHERE es.exam_date = CURRENT_DATE OR es.is_practice = true
    ";
    $liveData = $pdo->query($liveQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    $now = time();
    foreach($liveData as $row) {
        if ($row['result_id']) {
            $stats['completed_today']++;
            continue;
        }

        if ($row['is_practice']) {
            if ($row['response_count'] > 0) $stats['testing_now']++;
            continue;
        }

        $date_clean = explode(' ', $row['exam_date'])[0];
        $start_clean = explode('+', $row['start_time'])[0];
        $end_clean = explode('+', $row['end_time'])[0];

        $exam_start = strtotime($date_clean . ' ' . $start_clean);
        $exam_end = strtotime($date_clean . ' ' . $end_clean);
        if ($exam_end < $exam_start) $exam_end += 86400; 

        $cutoff_time = $exam_start + (30 * 60); 

        if ($now > $exam_end) {
            if ($row['response_count'] > 0 || $row['attendance_marked']) $stats['completed_today']++;
            else $stats['missed_today']++;
        } elseif ($now > $cutoff_time && $row['response_count'] == 0 && !$row['attendance_marked']) {
            $stats['missed_today']++;
        } elseif ($now >= $exam_start && $now <= $exam_end) {
            $stats['testing_now']++;
        }
    }
    
    // 3. Fetch Recent Users
    $recentUsers = $pdo->query("
        SELECT id, username, full_name, email, role, created_at 
        FROM users 
        ORDER BY id DESC LIMIT 4
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 4. NEW FEATURE: Fetch Top Training Partners Leaderboard
    $tp_rank_query = $pdo->query("
        SELECT 
            u.id, 
            u.full_name as tp_name, 
            COUNT(b.id) as total_bookings,
            COALESCE(SUM(b.estimated_candidates), 0) as total_students,
            COALESCE(SUM(b.total_fee), 0) as generated_revenue
        FROM users u
        LEFT JOIN slot_bookings b ON u.id = b.tp_id AND b.status != 'Rejected'
        WHERE u.role = 'tp'
        GROUP BY u.id, u.full_name
        HAVING COUNT(b.id) > 0
        ORDER BY total_bookings DESC, total_students DESC
        LIMIT 5
    ");
    $top_tps = $tp_rank_query->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
    error_log("Admin dashboard DB error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Workspace - NIELIT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand-blue: #2563EB; --brand-indigo: #4F46E5; --brand-light: #EFF6FF;
            --success: #059669; --success-bg: #D1FAE5;
            --text-dark: #0F172A; --text-gray: #64748B;
            --bg-body: #F8FAFC; --surface: #FFFFFF;
            --surface-glass: rgba(255, 255, 255, 0.7); --border-glass: rgba(255, 255, 255, 0.8);
            --shadow-soft: 0 20px 40px -15px rgba(15, 23, 42, 0.05);
            --shadow-float: 0 30px 60px -20px rgba(37, 99, 235, 0.15);
            --radius-lg: 24px; --radius-xl: 32px; --radius-pill: 999px; --border: #E2E8F0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; position: relative; overflow-x: hidden;}

        /* --- 3D Modern Background --- */
        .ambient-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100vh; z-index: -1;}        
        .orb { position: absolute; border-radius: 50%; filter: blur(60px); opacity: 0.6; animation: float-orb 20s infinite alternate cubic-bezier(0.45, 0.05, 0.55, 0.95); }
        .orb-1 { width: 600px; height: 600px; background: linear-gradient(135deg, #38BDF8, #2563EB); top: -10%; left: -10%; }
        .orb-2 { width: 500px; height: 500px; background: linear-gradient(135deg, #818CF8, #4F46E5); bottom: -20%; right: -5%; animation-delay: -5s; }
        .glass-shape { position: absolute; background: linear-gradient(135deg, rgba(255,255,255,0.4), rgba(255,255,255,0.1)); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.5); box-shadow: inset 0 0 20px rgba(255,255,255,0.5), 0 20px 40px rgba(0,0,0,0.05); animation: float-3d 15s infinite linear; }
        .shape-torus { width: 150px; height: 150px; border-radius: 50%; border: 30px solid rgba(255,255,255,0.2); top: 15%; right: 15%; }
        .shape-cube { width: 100px; height: 100px; border-radius: 20px; bottom: 20%; left: 10%; animation-duration: 25s; animation-direction: reverse; }
        @keyframes float-orb { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(100px, 50px) scale(1.1); } }
        @keyframes float-3d { 0% { transform: translateY(0) rotateX(0) rotateY(0) rotateZ(0); } 50% { transform: translateY(-30px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); } 100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); } }

        /* --- Header & Nav --- */
        .top-banner { background: var(--text-dark); color: white; text-align: center; padding: 8px; font-size: 12px; font-weight: 600; letter-spacing: 0.5px; }
        .navbar-wrapper { position: sticky; top: 0; z-index: 1000; background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-bottom: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 4px 30px -10px rgba(0,0,0,0.05); }
        .navbar { padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; }
        .brand { display: flex; align-items: center; gap: 15px; }
        .logo-mark { width: 45px; height: 45px; background: linear-gradient(135deg, var(--brand-blue), var(--brand-indigo)); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 22px; box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.4); }
        .brand-text h1 { font-size: 20px; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; line-height: 1.2;}
        .brand-text span { font-size: 13px; color: var(--text-gray); font-weight: 500; }
        
        .nav-actions { display: flex; align-items: center; gap: 24px; }
        .user-pill { display: flex; align-items: center; gap: 12px; background: var(--surface); padding: 6px 6px 6px 16px; border-radius: var(--radius-pill); box-shadow: var(--shadow-soft); cursor: pointer; transition: all 0.3s; border: 1px solid transparent; }
        .user-pill:hover { border-color: var(--brand-blue); transform: translateY(-2px); box-shadow: var(--shadow-float); }
        .user-pill span { font-weight: 700; font-size: 14px; color: var(--text-dark); }
        .user-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--brand-light); color: var(--brand-blue); display: flex; align-items: center; justify-content: center; font-weight: 700; }

        /* Profile Menu */
        .profile-dropdown-wrapper { position: relative; }
        .profile-menu { position: absolute; top: calc(100% + 15px); right: 0; background: var(--surface); border: 1px solid var(--border-glass); border-radius: 16px; box-shadow: var(--shadow-float); width: 220px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); z-index: 1000; overflow: hidden; }
        .profile-menu.show { opacity: 1; visibility: visible; transform: translateY(0); }
        .menu-header { padding: 15px 20px; background: var(--bg-body); border-bottom: 1px solid var(--border-glass); display: flex; flex-direction: column; }
        .menu-header strong { font-size: 14px; color: var(--text-dark); font-weight: 800; }
        .menu-header span { font-size: 11px; color: var(--text-gray); font-weight: 700; text-transform: uppercase; margin-top: 2px;}
        .menu-item { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: var(--text-dark); text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.2s; }
        .menu-item i { font-size: 16px; color: var(--brand-blue); width: 16px; text-align: center;}
        .menu-item:hover { background: var(--brand-light); color: var(--brand-blue); }
        .menu-item.text-danger i { color: #EF4444; }
        .menu-item.text-danger:hover { background: #FEE2E2; color: #EF4444; }

        /* --- Bento Box Layout --- */
        .workspace { max-width: 1600px; margin: 30px auto; padding: 0 40px; }
        .bento-grid { display: grid; grid-template-columns: repeat(12, 1fr); grid-auto-rows: minmax(100px, auto); gap: 24px; }
        .bento-card { background: var(--surface-glass); backdrop-filter: blur(20px); border: 1px solid var(--border-glass); border-radius: var(--radius-lg); padding: 30px; box-shadow: var(--shadow-soft); transition: transform 0.3s, box-shadow 0.3s; }
        .bento-card:hover { box-shadow: var(--shadow-float); }
        .span-12 { grid-column: span 12; } .span-8 { grid-column: span 8; } .span-6 { grid-column: span 6; } .span-4 { grid-column: span 4; } .span-3 { grid-column: span 3; }

        /* Welcome Card Elements */
        .welcome-card { background: linear-gradient(135deg, var(--text-dark), #1E293B); color: white; border: none; overflow: hidden; position: relative; display: flex; flex-direction: column; justify-content: center;}
        .welcome-card::after { content: ''; position: absolute; right: -50px; top: -100px; width: 300px; height: 300px; background: radial-gradient(circle, rgba(56, 189, 248, 0.2) 0%, transparent 70%); }
        
        .welcome-content { position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: flex-start; width: 100%; }
        .welcome-title { font-size: 28px; font-weight: 800; margin-bottom: 8px; letter-spacing: -0.5px; }
        .welcome-sub { color: #94A3B8; font-size: 15px; }
        
        .date-badge { text-align: right; background: rgba(255,255,255,0.1); padding: 12px 20px; border-radius: 16px; backdrop-filter: blur(10px); }
        .date-day { font-size: 13px; color: #94A3B8; font-weight: 600; text-transform: uppercase; }
        .date-full { font-size: 18px; font-weight: 800; }

        /* KPI Cards */
        .kpi-card { display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden; }
        .kpi-icon-wrap { width: 48px; height: 48px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 20px; }
        .bg-blue { background: #DBEAFE; color: #2563EB; } .bg-indigo { background: #E0E7FF; color: #4F46E5; } .bg-sky { background: #E0F2FE; color: #0284C7; } .bg-teal { background: #CCFBF1; color: #0D9488; }
        .kpi-value { font-size: 36px; font-weight: 800; letter-spacing: -1px; color: var(--text-dark); line-height: 1; margin-bottom: 8px; }
        .kpi-label { font-size: 14px; font-weight: 600; color: var(--text-gray); }

        /* --- UPGRADED LIVE MONITOR WIDGET --- */
        .health-card-graphical { display: flex; flex-direction: column; justify-content: space-between; padding: 25px; }
        .health-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .health-title { font-size: 16px; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; gap: 8px; }
        .live-pulse { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: var(--success); background: var(--success-bg); padding: 4px 10px; border-radius: 50px; }
        .pulse-dot { width: 6px; height: 6px; background-color: var(--success); border-radius: 50%; box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.7); animation: pulse-anim 1.5s infinite; }
        @keyframes pulse-anim { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(5, 150, 105, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(5, 150, 105, 0); } }

        .live-stat-main { text-align: center; margin: 0 0 10px; padding: 15px 10px; background: linear-gradient(135deg, #1E40AF, #4338CA); border-radius: 16px; color: #FFFFFF; box-shadow: 0 5px 15px rgba(30, 64, 175, 0.2); position: relative; overflow: hidden; }
        .live-stat-main::before { content: '\f108'; font-family: "Font Awesome 6 Free"; font-weight: 900; position: absolute; right: -10px; bottom: -15px; font-size: 60px; color: rgba(255, 255, 255, 0.1); transform: rotate(-15deg); }
        .live-stat-main h4 { font-size: 34px; font-weight: 800; line-height: 1; margin-bottom: 4px; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .live-stat-main span { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #E0E7FF; }

        .live-stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .live-stat-box { background: var(--bg-body); padding: 12px 5px; border-radius: 12px; border: 1px solid var(--border); text-align: center; display: flex; flex-direction: column; justify-content: center; transition: transform 0.3s; }
        .live-stat-box:hover { transform: translateY(-2px); border-color: var(--brand-blue); box-shadow: var(--shadow-soft);}
        .live-stat-box h5 { font-size: 18px; font-weight: 800; color: var(--text-dark); margin-bottom: 2px; line-height: 1;}
        .live-stat-box span { font-size: 9px; font-weight: 800; color: var(--text-gray); text-transform: uppercase; letter-spacing: 0.5px; }
        .box-danger { background: #FEF2F2; border-color: #FECACA; }
        .box-danger:hover { border-color: #EF4444; }
        .box-danger h5, .box-danger span { color: #DC2626; }

        /* Quick Actions */
        .quick-action-title { font-size: 18px; font-weight: 700; color: var(--text-dark); margin-bottom: 15px; padding-left: 5px; }
        .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn-modern { display: flex; align-items: center; gap: 12px; padding: 16px; background: var(--surface); border: 1px solid var(--border-glass); border-radius: 16px; text-decoration: none; color: var(--text-dark); font-weight: 700; font-size: 14px; transition: 0.3s; }
        .btn-modern:hover { background: var(--brand-light); border-color: #BFDBFE; color: var(--brand-blue); transform: scale(1.02); }
        .btn-modern i { font-size: 18px; color: var(--brand-blue); }

        /* --- LEADERBOARD WIDGET --- */
        .rank-list { display: flex; flex-direction: column; gap: 12px; margin-top: 15px;}
        .rank-item { display: flex; align-items: center; gap: 16px; padding: 16px; border-radius: 16px; border: 1px solid var(--border-glass); background: rgba(255,255,255,0.4); transition: 0.3s; }
        .rank-item:hover { background: var(--surface); border-color: var(--brand-light); transform: translateX(4px); box-shadow: var(--shadow-soft); }
        .rank-badge { width: 42px; height: 42px; border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 16px; font-weight: 800; color: white; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); position: relative; overflow: hidden;}
        .rank-1 { background: linear-gradient(135deg, #FBBF24, #D97706); }
        .rank-2 { background: linear-gradient(135deg, #E2E8F0, #94A3B8); color: #0F172A;}
        .rank-3 { background: linear-gradient(135deg, #FDBA74, #B45309); }
        .rank-other { background: var(--bg-body); color: var(--text-gray); border: 1px solid var(--border); box-shadow: none;}
        .rank-badge i { position: absolute; font-size: 30px; opacity: 0.2; right: -5px; bottom: -5px; }

        .tp-details { flex: 1; }
        .tp-details h4 { font-size: 15px; font-weight: 800; color: var(--text-dark); margin-bottom: 4px; }
        .tp-meta { display: flex; gap: 15px; font-size: 12px; font-weight: 600; color: var(--text-gray); }
        .tp-meta i { color: var(--brand-blue); margin-right: 4px;}

        .tp-score { text-align: right; }
        .tp-score .score-val { font-size: 20px; font-weight: 800; color: var(--brand-blue); line-height: 1;}
        .tp-score .score-label { font-size: 10px; font-weight: 700; color: var(--text-gray); text-transform: uppercase;}

        /* Modern Table */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        th { text-align: left; padding: 0 20px 10px; font-size: 12px; text-transform: uppercase; color: var(--text-gray); font-weight: 700; letter-spacing: 0.5px; }
        td { background: var(--surface); padding: 16px 20px; font-size: 14px; font-weight: 500; }
        tr td:first-child { border-radius: 16px 0 0 16px; }
        tr td:last-child { border-radius: 0 16px 16px 0; }
        tr { transition: transform 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        tr:hover { transform: scale(1.01); box-shadow: var(--shadow-soft); z-index: 2; position: relative; }

        .role-badge { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; }
        .badge-admin { background: #F1F5F9; color: #475569; }
        .badge-candidate { background: #DBEAFE; color: #1D4ED8; }

        .edit-btn { color: var(--brand-blue); text-decoration: none; font-weight: 700; background: var(--brand-light); padding: 8px 16px; border-radius: 8px; transition: 0.2s; white-space: nowrap;}
        .edit-btn:hover { background: var(--brand-blue); color: white; }

        /* ========================================= */
        /* RESPONSIVE DESIGN FOR PHONES & TABLETS    */
        /* ========================================= */
        
        @media (max-width: 1200px) { 
            .span-12, .span-8, .span-4 { grid-column: span 12; } 
            .span-3 { grid-column: span 6; } 
        }
        
        @media (max-width: 768px) { 
            /* Top Nav adjustments */
            .top-banner { font-size: 10px; padding: 6px; }
            .navbar-wrapper { position: relative; } /* Un-sticky on small phones to save screen height */
            .navbar { padding: 12px 20px; }
            .brand-text span { display: none; } /* Hide secondary logo text on mobile */
            .logo-mark { width: 36px; height: 36px; font-size: 18px; }
            
            /* Hide user name, show only avatar */
            .user-pill span { display: none; }
            .user-pill { padding: 6px; border-radius: 50%; }

            /* Container and Grid */
            .workspace { padding: 0 20px; margin-top: 15px; }
            .bento-grid { gap: 15px; }
            .bento-card { padding: 20px; }
            .span-3 { grid-column: span 12; } 
            
            /* Welcome Card Mobile Stack */
            .welcome-content { flex-direction: column; gap: 15px; }
            .welcome-title { font-size: 24px; }
            .date-badge { text-align: left; width: 100%; display: flex; align-items: center; justify-content: space-between; }
            
            /* Live Monitor Stack */
            .health-card-graphical { padding: 20px; }
            .live-stat-grid { grid-template-columns: 1fr; gap: 10px; } /* Stack vertically */
            .live-stat-box { padding: 15px; display: flex; flex-direction: row; justify-content: space-between; align-items: center;}
            .live-stat-box h5 { margin: 0; font-size: 20px; }
            .live-stat-box span { font-size: 11px; }
            
            /* Quick Actions Stack */
            .action-grid { grid-template-columns: 1fr; }
            .btn-modern { padding: 14px; }
            
            /* Table adjustments */
            .table-container { padding-bottom: 10px; }
            td { padding: 12px 15px; font-size: 13px; }
        }
    </style>
</head>
<body>
    <div class="ambient-bg">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="glass-shape shape-torus"></div>
        <div class="glass-shape shape-cube"></div>
    </div>

    <div class="top-banner">
        राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान | National Institute of Electronics & Information Technology
    </div>

    <div class="navbar-wrapper">
        <nav class="navbar">
            <div class="brand">
                <div class="logo-mark">N</div>
                <div class="brand-text">
                    <h1>NIELIT</h1>
                    <span>CBT Administration Console</span>
                </div>
            </div>

            <div class="nav-actions">
                <div class="profile-dropdown-wrapper">
                    <div class="user-pill" id="profileDropdownBtn" title="Account Menu">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?></div>
                    </div>
                    
                    <div class="profile-menu" id="profileMenu">
                        <div class="menu-header">
                            <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></strong>
                            <span>System Administrator</span>
                        </div>
                        <a href="edit-user.php?id=<?php echo $_SESSION['user_id']; ?>" class="menu-item">
                            <i class="fas fa-user-edit"></i> Edit Profile
                        </a>
                        <a href="admin-logout.php" class="menu-item text-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    </div>

    <div class="workspace">
        <?php if (!empty($error)): ?>
            <div style="background: white; border-left: 4px solid #EF4444; padding: 15px; border-radius: 12px; margin-bottom: 20px; box-shadow: var(--shadow-soft); font-weight: 600; font-size: 14px;">
                <i class="fas fa-exclamation-circle" style="color: #EF4444; margin-right: 10px;"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="bento-grid">
            
            <div class="bento-card welcome-card span-8">
                <div class="welcome-content">
                    <div>
                        <h2 class="welcome-title">Welcome back, <br><br><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Administrator')[0]); ?> ☺️</h2>
                        <p class="welcome-sub">Here's what's happening in your exam ecosystem today.</p>
                    </div>
                    <div class="date-badge">
                        <div class="date-day"><?php echo date('l'); ?></div>
                        <div class="date-full"><?php echo date('d M Y'); ?></div>
                    </div>
                </div>
            </div>

            <div class="bento-card health-card-graphical span-4">
                <div class="health-header">
                    <h3 class="health-title"><i class="fas fa-satellite-dish" style="color: var(--brand-blue);"></i> Live Monitor</h3>
                    <div class="live-pulse">
                        <span class="pulse-dot"></span> Active Now
                    </div>
                </div>
                
                <div class="live-stat-main">
                    <h4><?php echo number_format($stats['testing_now']); ?></h4>
                    <span>Candidates Testing Now</span>
                </div>
                
                <div class="live-stat-grid">
                    <div class="live-stat-box">
                        <h5><?php echo number_format($stats['completed_today']); ?></h5>
                        <span>Completed</span>
                    </div>
                    <div class="live-stat-box box-danger" title="Late by >30 mins">
                        <h5><?php echo number_format($stats['missed_today']); ?></h5>
                        <span>Missed/Late</span>
                    </div>
                    <div class="live-stat-box">
                        <h5><?php echo number_format($stats['active_centers_today']); ?></h5>
                        <span>Active Centers</span>
                    </div>
                </div>
            </div>

            <div class="bento-card kpi-card span-3">
                <div class="kpi-icon-wrap bg-blue"><i class="fas fa-users"></i></div>
                <div>
                    <div class="kpi-value"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="kpi-label">Registered Profiles</div>
                </div>
            </div>
            <div class="bento-card kpi-card span-3">
                <div class="kpi-icon-wrap bg-indigo"><i class="fas fa-laptop-code"></i></div>
                <div>
                    <div class="kpi-value"><?php echo number_format($stats['total_exams']); ?></div>
                    <div class="kpi-label">Total Exam Sessions</div>
                </div>
            </div>
            <div class="bento-card kpi-card span-3">
                <div class="kpi-icon-wrap bg-teal"><i class="fas fa-clipboard-check"></i></div>
                <div>
                    <div class="kpi-value"><?php echo number_format($stats['total_registrations']); ?></div>
                    <div class="kpi-label">Total Enrollments</div>
                </div>
            </div>
            <div class="bento-card kpi-card span-3">
                <div class="kpi-icon-wrap bg-sky"><i class="fas fa-layer-group"></i></div>
                <div>
                    <div class="kpi-value"><?php echo number_format($stats['total_questions']); ?></div>
                    <div class="kpi-label">Questions in Bank</div>
                </div>
            </div>

            <div class="bento-card span-4" style="background: transparent; border: none; box-shadow: none; padding: 0;">
                <h3 class="quick-action-title">Quick Actions</h3>
                <div class="action-grid">
                    <a href="manage-users.php" class="btn-modern">
                        <i class="fas fa-user-plus"></i> Users
                    </a>
                    <a href="manage-exams.php" class="btn-modern">
                        <i class="fas fa-calendar-plus"></i> Exams
                    </a>
                    <a href="manage-questions.php" class="btn-modern">
                        <i class="fas fa-pen-square"></i> Questions
                    </a>
                    <a href="reports.php" class="btn-modern">
                        <i class="fas fa-chart-pie"></i> Reports
                    </a>
                    <a href="manage-centers.php" class="btn-modern" style="grid-column: 1 / -1; justify-content: center;">
                        <i class="fas fa-map-marked-alt"></i> Manage Test Centers
                    </a>
                </div>
            </div>

            <div class="bento-card span-8">
                <h3 class="quick-action-title"><i class="fas fa-trophy" style="color: #D97706; margin-right: 8px;"></i> Top Training Partners</h3>
                
                <?php if(empty($top_tps)): ?>
                    <div style="text-align: center; padding: 30px; color: var(--text-gray);">
                        <i class="fas fa-medal" style="font-size: 40px; color: #E2E8F0; margin-bottom: 10px;"></i>
                        <p style="font-size: 14px; font-weight: 500;">No active bookings yet.<br>Leaderboard will populate automatically.</p>
                    </div>
                <?php else: ?>
                    <div class="rank-list">
                        <?php 
                        $rank = 1;
                        foreach($top_tps as $tp): 
                            $badgeClass = 'rank-other';
                            if ($rank == 1) $badgeClass = 'rank-1';
                            elseif ($rank == 2) $badgeClass = 'rank-2';
                            elseif ($rank == 3) $badgeClass = 'rank-3';
                        ?>
                        <div class="rank-item">
                            <div class="rank-badge <?php echo $badgeClass; ?>">
                                <?php if($rank <= 3): ?><i class="fas fa-crown"></i><?php endif; ?>
                                <?php echo $rank; ?>
                            </div>
                            
                            <div class="tp-details">
                                <h4><?php echo htmlspecialchars($tp['tp_name']); ?></h4>
                                <div class="tp-meta">
                                    <span><i class="fas fa-users"></i> <?php echo number_format($tp['total_students']); ?> Students</span>
                                    <span class="hide-mobile"><i class="fas fa-rupee-sign"></i> <?php echo number_format($tp['generated_revenue']); ?></span>
                                </div>
                            </div>
                            
                            <div class="tp-score">
                                <div class="score-val"><?php echo number_format($tp['total_bookings']); ?></div>
                                <div class="score-label">Bookings</div>
                            </div>
                        </div>
                        <?php $rank++; endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bento-card span-12">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="font-size: 18px; font-weight: 700; color: var(--text-dark);">Recent Onboarding</h3>
                    <a href="manage-users.php" style="color: var(--brand-blue); font-weight: 700; text-decoration: none; font-size: 13px;">Directory &rarr;</a>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Candidate / User</th>
                                <th>Role</th>
                                <th class="hide-mobile">Registered Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentUsers)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-gray);">No recent registrations.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 800; color: var(--text-dark); margin-bottom: 4px; font-size: 13px;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                        <div style="font-size: 11px; color: var(--text-gray);">@<?php echo htmlspecialchars($user['username']); ?></div>
                                    </td>
                                    <td><span class="role-badge badge-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                    <td class="hide-mobile" style="color: var(--text-gray); font-weight: 600; font-size: 13px;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td><a href="edit-user.php?id=<?php echo $user['id']; ?>" class="edit-btn">Manage</a></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div> 
        <div style="text-align: center; color: var(--text-gray); font-size: 12px; font-weight: 600; padding: 30px 0;">
            NIELIT OS &copy; <?php echo date('Y'); ?>. Secure Console Environment.
        </div>
    </div>

    <script>
        // SECURE AUTO-LOGOUT ON BROWSER BACK BUTTON
        document.addEventListener('DOMContentLoaded', function() {
            if (window.history && window.history.pushState) {
                window.history.pushState('trap', null, window.location.href);
                window.onpopstate = function(event) {
                    window.location.replace('admin-logout.php');
                };
            }
        });

        // Profile Dropdown Toggle Logic
        document.addEventListener('DOMContentLoaded', function() {
            const profileBtn = document.getElementById('profileDropdownBtn');
            const profileMenu = document.getElementById('profileMenu');

            profileBtn.addEventListener('click', function(e) {
                e.stopPropagation(); 
                profileMenu.classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
                    profileMenu.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>