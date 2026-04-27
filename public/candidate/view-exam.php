<?php
date_default_timezone_set('Asia/Kolkata');
session_name('NIELIT_COORD_SESSION');
session_start();

// Check if user is logged in and is a coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'coordinator') {
    header("Location: coordinator-login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage-exams.php");
    exit();
}

$exam_id = $_GET['id'];

// ============================================================================
// NEW ARCHITECTURE: Import centralized database connection
// Path assumes this file is in: /public/coordinator/view-exam.php
// ============================================================================
require_once __DIR__ . '/../../config/database.php';

$error = '';
$exam = null;
$roster = [];
$stats = [
    'total_registered' => 0,
    'present' => 0,
    'passed' => 0,
    'failed' => 0,
    'avg_score' => 0
];

try {
    // 1. Fetch Exam Details (Safely handling NULLs for Practice Exams via LEFT JOIN)
    $stmt = $pdo->prepare("
        SELECT es.*, ec.category_name, ec.category_code, ec.duration_minutes,
               c.center_name, c.center_code, c.city, c.address
        FROM exam_sessions es
        LEFT JOIN exam_categories ec ON es.category_id = ec.id
        LEFT JOIN exam_centers c ON es.center_id = c.id
        WHERE es.id = ?
    ");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        header("Location: manage-exams.php");
        exit();
    }

    // Determine Exam Status
    $now_timestamp = time();
    if ($exam['is_practice']) {
        $status = 'practice';
    } else {
        $start_timestamp = strtotime($exam['exam_date'] . ' ' . $exam['start_time']);
        $end_timestamp = strtotime($exam['exam_date'] . ' ' . $exam['end_time']);
        
        if (!$exam['is_active']) { $status = 'inactive'; } 
        elseif ($start_timestamp > $now_timestamp) { $status = 'upcoming'; } 
        elseif ($end_timestamp < $now_timestamp) { $status = 'completed'; } 
        else { $status = 'ongoing'; }
    }

    // 2. Fetch Candidate Roster & Scores
    $stmt = $pdo->prepare("
        SELECT er.id as reg_id, er.registration_status, er.attendance_marked,
               u.full_name, u.email, cand.registration_number,
               res.percentage, res.is_pass
        FROM exam_registrations er
        JOIN users u ON er.candidate_id = u.id
        LEFT JOIN candidates cand ON u.id = cand.user_id
        LEFT JOIN exam_results res ON er.id = res.registration_id
        WHERE er.session_id = ?
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$exam_id]);
    $roster = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Roster Statistics
    $total_score = 0;
    $scored_count = 0;

    foreach ($roster as $candidate) {
        $stats['total_registered']++;
        if ($candidate['attendance_marked']) {
            $stats['present']++;
        }
        if ($candidate['percentage'] !== null) {
            $scored_count++;
            $total_score += $candidate['percentage'];
            if ($candidate['is_pass']) { $stats['passed']++; } 
            else { $stats['failed']++; }
        }
    }
    
    if ($scored_count > 0) {
        $stats['avg_score'] = round($total_score / $scored_count, 1);
    }

} catch (PDOException $e) {
    $error = "System Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Exam Roster - NIELIT Coordinator Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1D4ED8;        --primary-light: #3B82F6;  --primary-bg: #DBEAFE;     
            --secondary: #0F172A;
            --success: #059669;        --success-bg: #D1FAE5;
            --warning: #D97706;        --warning-bg: #FEF3C7;
            --danger: #DC2626;         --danger-bg: #FEE2E2;
            --practice: #8B5CF6;       --practice-bg: #EDE9FE;
            --neutral: #64748B;        --neutral-bg: #F1F5F9;
            --text-dark: #0F172A;      --text-muted: #64748B;
            --bg-body: #F4F7FB;        --surface: #FFFFFF;
            --border: #E2E8F0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            --radius-md: 12px;         --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; overflow-x: hidden; padding-bottom: 60px; }

        /* Ambient BG */
        .ambient-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; pointer-events: none; background: radial-gradient(circle at 50% 0%, #DCE6F1 0%, #E8F0F8 50%, #F4F7FB 100%); }
        
        /* Navbar */
        .navbar-wrapper { position: sticky; top: 0; z-index: 1000; background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 4px 30px -10px rgba(0,0,0,0.05); }
        .top-nav { padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--bg-body); border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s; }
        .btn-back:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); transform: translateX(-3px); }
        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--primary); line-height: 1.2; }
        .brand-text span { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .user-info { font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }

        .container { max-width: 1400px; margin: 30px auto; padding: 0 40px; }

        /* Grid Layout */
        .dashboard-grid { display: grid; grid-template-columns: 350px 1fr; gap: 30px; align-items: start; }

        /* Left Panel - Exam Details */
        .bento-card { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); padding: 30px; position: relative; overflow: hidden; }
        
        .badge { padding: 4px 12px; border-radius: 50px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; margin-bottom: 15px;}
        .status-upcoming { background: var(--warning-bg); color: var(--warning); }
        .status-ongoing { background: var(--success-bg); color: var(--success); }
        .status-completed { background: var(--neutral-bg); color: var(--neutral); }
        .status-practice { background: var(--practice-bg); color: var(--practice); }
        .status-inactive { background: var(--danger-bg); color: var(--danger); }

        .exam-header h1 { font-size: 24px; font-weight: 800; color: var(--text-dark); margin-bottom: 5px; }
        .exam-header p { font-size: 14px; color: var(--text-muted); font-weight: 600; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border); }

        .detail-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .detail-icon { width: 36px; height: 36px; background: var(--neutral-bg); color: var(--neutral); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .detail-text h4 { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .detail-text p { font-size: 14px; font-weight: 600; color: var(--text-dark); }

        /* Mini Stats */
        .mini-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 30px; }
        .m-stat { background: var(--bg-body); padding: 15px; border-radius: var(--radius-md); text-align: center; border: 1px solid var(--border); }
        .m-stat h3 { font-size: 24px; font-weight: 800; color: var(--primary); }
        .m-stat p { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-top: 4px; }

        /* Right Panel - Roster Table */
        .table-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .table-toolbar h2 { font-size: 18px; font-weight: 800; color: var(--text-dark); }
        .search-box { position: relative; width: 300px; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px;}
        .search-box input { width: 100%; padding: 10px 15px 10px 40px; border-radius: 50px; border: 1px solid var(--border); background: var(--bg-body); font-family: inherit; font-size: 13px; font-weight: 500; outline: none; transition: 0.3s; }
        .search-box input:focus { border-color: var(--primary); background: white; }

        .table-responsive { overflow-x: auto; background: white; border-radius: var(--radius-md); border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { background: #F8FAFC; color: var(--text-muted); font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 15px 20px; text-align: left; border-bottom: 2px solid var(--border); }
        td { padding: 15px 20px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 500; vertical-align: middle; transition: background 0.2s;}
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--bg-body); }

        .cand-info { display: flex; flex-direction: column; gap: 3px; }
        .cand-info strong { color: var(--text-dark); font-weight: 700; }
        .cand-info span { font-size: 12px; color: var(--text-muted); }

        .status-pill { padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .pill-green { background: var(--success-bg); color: var(--success); }
        .pill-red { background: var(--danger-bg); color: var(--danger); }
        .pill-grey { background: var(--neutral-bg); color: var(--neutral); }

        .score-box { font-weight: 800; font-size: 15px; }
        .score-pass { color: var(--success); }
        .score-fail { color: var(--danger); }
        .score-pending { color: var(--neutral); font-weight: 600; font-size: 13px; }

        @media (max-width: 1024px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-nav { padding: 15px 20px; }
            .container { padding: 0 20px; }
            .table-toolbar { flex-direction: column; align-items: flex-start; gap: 15px; }
            .search-box { width: 100%; }
        }
    </style>
</head>
<body>

    <div class="ambient-bg"></div>

    <div class="navbar-wrapper">
        <nav class="top-nav">
            <div class="nav-left">
                <a href="manage-exams.php" class="btn-back"><i class="fas fa-arrow-left"></i> Exams</a>
                <div class="brand-text">
                    <h2>Session Viewer</h2>
                    <span class="hide-mobile">NIELIT Coordinator Portal</span>
                </div>
            </div>
            <div class="nav-right">
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?></div>
                    <span class="hide-mobile"><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?></span>
                </div>
            </div>
        </nav>
    </div>

    <div class="container">
        
        <?php if ($error): ?>
            <div style="background: var(--danger-bg); color: var(--danger); padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            
            <div class="bento-card">
                <span class="badge status-<?php echo $status; ?>">
                    <?php 
                        if($status == 'practice') echo '<i class="fas fa-infinity"></i> Always Open Practice';
                        else echo ucfirst($status); 
                    ?>
                </span>
                
                <div class="exam-header">
                    <h1><?php echo htmlspecialchars($exam['exam_code']); ?></h1>
                    <p><?php echo htmlspecialchars($exam['category_name']); ?></p>
                </div>

                <?php if ($exam['is_practice']): ?>
                    <div class="detail-row">
                        <div class="detail-icon" style="background: var(--practice-bg); color: var(--practice);"><i class="fas fa-globe"></i></div>
                        <div class="detail-text">
                            <h4>Location</h4>
                            <p>Online / Virtual Portal</p>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-icon" style="background: var(--practice-bg); color: var(--practice);"><i class="fas fa-calendar-check"></i></div>
                        <div class="detail-text">
                            <h4>Schedule</h4>
                            <p>Flexible (No Time Limit)</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="detail-row">
                        <div class="detail-icon" style="background: var(--primary-bg); color: var(--primary);"><i class="fas fa-building"></i></div>
                        <div class="detail-text">
                            <h4>Exam Center</h4>
                            <p><?php echo htmlspecialchars($exam['center_name']); ?><br><span style="font-size: 12px; color: var(--text-muted); font-weight: 500;"><?php echo htmlspecialchars($exam['city']); ?></span></p>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-icon" style="background: var(--warning-bg); color: var(--warning);"><i class="far fa-calendar-alt"></i></div>
                        <div class="detail-text">
                            <h4>Date & Time</h4>
                            <p><?php echo date('d M Y', strtotime($exam['exam_date'])); ?><br><span style="font-size: 12px; color: var(--text-muted); font-weight: 500;"><?php echo date('h:i A', strtotime($exam['start_time'])) . ' - ' . date('h:i A', strtotime($exam['end_time'])); ?></span></p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mini-stats">
                    <div class="m-stat">
                        <h3><?php echo $stats['total_registered']; ?><?php echo $exam['is_practice'] ? '' : '/' . $exam['total_seats']; ?></h3>
                        <p>Registered</p>
                    </div>
                    <div class="m-stat">
                        <h3 style="color: var(--success);"><?php echo $stats['avg_score']; ?>%</h3>
                        <p>Class Average</p>
                    </div>
                    <div class="m-stat">
                        <h3 style="color: var(--warning);"><?php echo $stats['present']; ?></h3>
                        <p>Present</p>
                    </div>
                    <div class="m-stat">
                        <h3 style="color: var(--danger);"><?php echo $stats['failed']; ?></h3>
                        <p>Failed</p>
                    </div>
                </div>
            </div>

            <div class="bento-card" style="padding: 25px;">
                <div class="table-toolbar">
                    <h2>Candidate Roster</h2>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="rosterSearch" placeholder="Search by Name or Roll No...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="rosterTable">
                        <thead>
                            <tr>
                                <th>Candidate Details</th>
                                <th>Status</th>
                                <th>Attendance</th>
                                <th>Final Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($roster)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        <i class="fas fa-users-slash" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                        No candidates have registered for this session yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($roster as $row): ?>
                                    <tr class="roster-row">
                                        <td>
                                            <div class="cand-info">
                                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                                <span>Roll: <?php echo htmlspecialchars($row['registration_number'] ?? 'N/A'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($row['registration_status'] == 'completed'): ?>
                                                <span class="status-pill pill-green"><i class="fas fa-check"></i> Completed</span>
                                            <?php else: ?>
                                                <span class="status-pill pill-grey"><i class="fas fa-clock"></i> Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['attendance_marked']): ?>
                                                <span class="status-pill pill-green">Present</span>
                                            <?php else: ?>
                                                <span class="status-pill pill-red">Absent</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['percentage'] !== null): ?>
                                                <span class="score-box <?php echo $row['is_pass'] ? 'score-pass' : 'score-fail'; ?>">
                                                    <?php echo round($row['percentage'], 1); ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="score-pending">-</span>
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
    </div>

    <script>
        // Simple Real-time Search Filter for Roster
        document.getElementById('rosterSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('.roster-row');

            rows.forEach(row => {
                // Check against the first column (Candidate Details)
                let text = row.cells[0].textContent.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>