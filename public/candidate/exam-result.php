<?php
session_name('NIELIT_CANDIDATE_SESSION');
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: candidate-login.php");
    exit();
}

// ============================================================================
// NEW ARCHITECTURE: Import centralized database connection
// Path assumes this file is in: /public/candidate/exam-result.php
// ============================================================================
require_once __DIR__ . '/../../config/database.php';

$exam_id = $_GET['exam_id'] ?? 0;

$error = '';
$result = null;
$grade = 'F';

try {
    if ($_SESSION['user_role'] == 'candidate') {
        $stmt = $pdo->prepare("
            SELECT 
                reg.id as registration_id,
                es.exam_code,
                es.is_practice,
                es.exam_conductor,
                ec.category_name,
                ec.category_code,
                ec.pass_percentage,
                u.full_name,
                c.registration_number,
                es.exam_date,
                es.start_time,
                es.end_time,
                c2.center_name,
                c2.city,
                es.category_id,
                er.total_marks_obtained,
                er.percentage,
                er.result_status,
                (SELECT COUNT(*) FROM questions WHERE category_id = es.category_id AND is_active = true) as total_questions_count,
                (SELECT SUM(marks) FROM questions WHERE category_id = es.category_id AND is_active = true) as total_marks_sum
            FROM exam_registrations reg
            JOIN exam_sessions es ON reg.session_id = es.id
            JOIN exam_categories ec ON es.category_id = ec.id
            JOIN users u ON reg.candidate_id = u.id
            LEFT JOIN candidates c ON u.id = c.user_id
            LEFT JOIN exam_centers c2 ON es.center_id = c2.id
            LEFT JOIN exam_results er ON reg.id = er.registration_id
            WHERE reg.candidate_id = ? AND es.id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $exam_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$result) {
        $error = "Fatal Error: Could not find your exam registration. Did you enroll?";
    } elseif ($result['total_marks_obtained'] === null) {
        $error = "Submission Error: Your answers were received, but the auto-grader failed to calculate your score. Please inform the administrator.";
    } else {
        $total_marks = $result['total_marks_sum'] ?? 0;
        $obtained_marks = $result['total_marks_obtained'] ?? 0;
        $percentage = $result['percentage'] ?? 0;
        
        $status = (strtolower($result['result_status']) === 'pass') ? 'pass' : 'fail';
        
        $center_display = $result['is_practice'] ? 'Online Practice Portal' : htmlspecialchars($result['center_name']);
        $date_display = $result['is_practice'] ? date('d M Y') : date('d M Y', strtotime($result['exam_date']));

        // --- NIELIT GRADE CALCULATION ---
        if ($percentage >= 85) { $grade = 'S'; } 
        elseif ($percentage >= 75) { $grade = 'A'; } 
        elseif ($percentage >= 65) { $grade = 'B'; } 
        elseif ($percentage >= 55) { $grade = 'C'; } 
        elseif ($percentage >= 50) { $grade = 'D'; } 
        else { $grade = 'F'; }
    }
    
} catch (PDOException $e) {
    $error = "System Database Offline: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Result - NIELIT Candidate Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563EB; --primary-light: #60A5FA; --primary-bg: #EFF6FF;
            --success: #059669; --success-bg: #D1FAE5;
            --danger: #DC2626; --danger-bg: #FEE2E2;
            --text-dark: #0F172A; --text-muted: #64748B;
            --bg-page: #F8FAFC; --card-bg: rgba(255, 255, 255, 0.95);
            --border: rgba(226, 232, 240, 0.9);
            --shadow-sm: 0 4px 6px -1px rgba(37, 99, 235, 0.05);
            --shadow-float: 0 15px 35px -10px rgba(37, 99, 235, 0.15);
            --radius-md: 14px; --radius-lg: 24px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-page); color: var(--text-dark); height: 100vh; overflow: hidden; display: flex; flex-direction: column; }

        /* Ambient Background */
        .ambient-wrapper { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; pointer-events: none; background: linear-gradient(135deg, #F8FAFC 0%, #E2E8F0 100%); }
        .ambient-wrapper::after { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 50% 0%, rgba(59, 130, 246, 0.08) 0%, transparent 70%); }
        .shape3d { position: absolute; background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.4)); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.8); box-shadow: 0 25px 50px -12px rgba(37, 99, 235, 0.15), inset 0 0 20px rgba(255,255,255,0.6); animation: float-complex 25s infinite cubic-bezier(0.4, 0, 0.2, 1); }
        .s-cube { width: 150px; height: 150px; border-radius: 30px; top: 15%; left: 10%; animation-duration: 28s; }
        .s-pill { width: 250px; height: 80px; border-radius: 50px; top: 65%; right: -5%; animation-duration: 32s; animation-direction: reverse; }
        @keyframes float-complex { 0% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-30px) rotate(10deg); } 100% { transform: translateY(0) rotate(0deg); } }

        /* Navbar */
        .navbar-wrapper { position: relative; z-index: 1000; background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255, 255, 255, 0.6); box-shadow: var(--shadow-sm); }
        .top-nav { padding: 12px 30px; display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .btn-back-nav { display: inline-flex; align-items: center; gap: 8px; background: white; border: 1px solid var(--border); padding: 8px 16px; border-radius: 50px; color: var(--text-dark); text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.3s; }
        .btn-back-nav:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); transform: translateX(-2px); }
        .brand-text h2 { font-size: 16px; font-weight: 800; background: linear-gradient(135deg, #2563EB 0%, #3B82F6 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 2px;}
        .brand-text span { font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }
        .user-info { display: flex; align-items: center; gap: 10px; background: white; padding: 4px 12px 4px 4px; border-radius: 50px; border: 1px solid var(--border); }
        .user-info span { font-size: 13px; font-weight: 700; color: var(--text-dark); }
        .user-avatar { width: 28px; height: 28px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px; }

        /* Main Container */
        .container { flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px; overflow-y: auto; z-index: 10; }
        
        /* Compact Card */
        .result-card { background: var(--card-bg); backdrop-filter: blur(20px); border-radius: var(--radius-lg); border: 1px solid rgba(255,255,255,0.8); box-shadow: var(--shadow-float); width: 100%; max-width: 780px; overflow: hidden; animation: slideUp 0.5s ease-out; display: flex; flex-direction: column; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }

        /* Compact Header */
        .result-header { background: linear-gradient(135deg, #1D4ED8, #3B82F6); color: white; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; }
        .rh-titles h1 { font-size: 22px; font-weight: 800; margin-bottom: 2px; }
        .rh-titles p { font-size: 13px; font-weight: 500; opacity: 0.9; }
        .header-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 50px; font-weight: 700; font-size: 12px; border: 1px solid rgba(255,255,255,0.3); }

        /* Body Split Layout */
        .result-body { display: flex; gap: 20px; padding: 25px; background: white; }

        /* Left Side: Score */
        .score-side { flex: 0 0 240px; display: flex; flex-direction: column; align-items: center; background: var(--bg-page); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border); }
        .gauge-wrapper { position: relative; width: 130px; height: 130px; margin-bottom: 15px;}
        .gauge-circle { width: 100%; height: 100%; border-radius: 50%; background: conic-gradient(var(--status-color) var(--percent), #E2E8F0 0); display: flex; align-items: center; justify-content: center; box-shadow: inset 0 2px 10px rgba(0,0,0,0.05); animation: fillGauge 1.5s ease-out forwards; }
        @keyframes fillGauge { from { background: conic-gradient(var(--status-color) 0%, #E2E8F0 0); } }
        .gauge-inner { width: 100px; height: 100px; background: white; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        .gauge-score { font-size: 28px; font-weight: 800; color: var(--text-dark); line-height: 1; letter-spacing: -0.5px;}
        
        .status-banner { width: 100%; padding: 10px; border-radius: 8px; text-align: center; font-weight: 800; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 6px; border: 1px solid transparent; margin-bottom: 15px;}
        .status-pass { background: var(--success-bg); color: var(--success); border-color: #A7F3D0; }
        .status-fail { background: var(--danger-bg); color: var(--danger); border-color: #FECACA; }

        .mini-stats { width: 100%; text-align: center; }
        .mini-stats div { padding: 8px 0; border-bottom: 1px dashed var(--border); font-size: 12px; font-weight: 700; color: var(--text-muted); display: flex; justify-content: space-between; }
        .mini-stats div:last-child { border: none; padding-bottom: 0; }
        .mini-stats span { color: var(--text-dark); font-weight: 800; font-size: 14px; }

        /* Right Side: Details */
        .details-side { flex: 1; display: grid; grid-template-columns: 1fr; gap: 10px; align-content: flex-start;}
        .data-item { display: flex; align-items: center; gap: 15px; background: var(--bg-page); padding: 12px 15px; border-radius: 12px; border: 1px solid var(--border); transition: 0.2s; }
        .data-item:hover { background: white; border-color: var(--primary-light); box-shadow: var(--shadow-sm); transform: translateX(2px);}
        .di-icon { width: 36px; height: 36px; background: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 16px; border: 1px solid var(--border); flex-shrink: 0;}
        .di-content { flex: 1; }
        .data-label { font-size: 11px; color: var(--text-muted); font-weight: 800; text-transform: uppercase; margin-bottom: 2px; display: block; }
        .data-value { font-size: 14px; font-weight: 700; color: var(--text-dark); line-height: 1.3;}
        .data-value small { display: block; font-size: 12px; color: var(--text-muted); font-weight: 500; margin-top: 2px;}

        /* Footer Actions */
        .result-footer { padding: 20px 30px; background: #F8FAFC; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .footer-note { font-size: 12px; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .actions-row { display: flex; gap: 12px; }
        .btn { padding: 10px 20px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; border: none; text-decoration: none; }
        .btn-dash { background: white; color: var(--text-dark); border: 1px solid var(--border); }
        .btn-dash:hover { border-color: var(--primary); color: var(--primary); }
        .btn-print { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2); }
        .btn-print:hover { background: #1E40AF; transform: translateY(-2px); }

        @media (max-width: 768px) {
            body { overflow-y: auto; }
            .result-header { flex-direction: column; text-align: center; gap: 15px; padding: 25px 20px;}
            .result-body { flex-direction: column; padding: 20px; }
            .score-side { flex: auto; width: 100%; }
            .result-footer { flex-direction: column; gap: 15px; }
            .actions-row { width: 100%; display: flex; flex-direction: column; }
            .btn { flex: 1; justify-content: center; }
        }
    </style>
</head>
<body>

    <div class="ambient-wrapper">
        <div class="shape3d s-cube"></div>
        <div class="shape3d s-pill"></div>
    </div>

    <div class="navbar-wrapper">
        <nav class="top-nav">
            <div class="nav-left">
                <a href="candidate-dashboard.php" class="btn-back-nav">
                    <i class="fas fa-arrow-left"></i> <span class="hide-mobile">Dashboard</span>
                </a>
                <div class="brand-text">
                    <h2>Exam Result</h2>
                    <span class="hide-mobile">NIELIT Candidate Portal</span>
                </div>
            </div>
            <div class="nav-right">
                <div class="user-info">
                    <span class="hide-mobile"><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Candidate')[0]); ?></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'C', 0, 1)); ?></div>
                </div>
            </div>
        </nav>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div style="text-align:center; padding: 50px 30px; color: var(--danger); font-weight: 800; background: white; border-radius: var(--radius-lg); border: 2px dashed #FECACA; max-width: 500px; box-shadow: var(--shadow-float); width: 100%;">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px; color: var(--danger);"></i><br>
                <span style="font-size: 15px; line-height: 1.5; display: block; margin-bottom: 25px;"><?php echo $error; ?></span>
                <a href="candidate-dashboard.php" class="btn btn-dash" style="width: 100%; justify-content: center;"><i class="fas fa-home"></i> Return to Dashboard</a>
            </div>
        <?php elseif ($result): ?>
            
            <?php 
                $status_color = ($status == 'pass') ? 'var(--success)' : 'var(--danger)';
                $status_icon = ($status == 'pass') ? 'fa-award' : 'fa-times-circle';
                $status_text = ($status == 'pass') ? 'QUALIFIED' : 'NOT QUALIFIED';
                $percent_display = round($percentage, 1);
            ?>

            <div class="result-card">
                
                <div class="result-header">
                    <div class="rh-titles">
                        <h1>Online Score Report</h1>
                        <p>Module: <strong><?php echo htmlspecialchars($result['exam_code']); ?></strong></p>
                    </div>
                    <div class="header-badge">
                        <i class="fas fa-id-badge"></i> Roll No: <?php echo htmlspecialchars($result['registration_number'] ?? 'N/A'); ?>
                    </div>
                </div>

                <div class="result-body">
                    
                    <div class="score-side">
                        <div class="gauge-wrapper" style="--percent: <?php echo $percent_display; ?>%; --status-color: <?php echo $status_color; ?>;">
                            <div class="gauge-circle">
                                <div class="gauge-inner">
                                    <span class="gauge-score"><?php echo $percent_display; ?>%</span>
                                </div>
                            </div>
                        </div>

                        <div class="status-banner status-<?php echo $status; ?>">
                            <i class="fas <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                        </div>

                        <div class="mini-stats">
                            <div>Obtained Marks <span><?php echo number_format($obtained_marks, 1); ?></span></div>
                            <div>Maximum Marks <span><?php echo number_format($total_marks, 1); ?></span></div>
                            <div>Final Grade <span style="color: var(--primary);"><?php echo $grade; ?></span></div>
                        </div>
                    </div>

                    <div class="details-side">
                        
                        <div class="data-item">
                            <div class="di-icon"><i class="fas fa-user"></i></div>
                            <div class="di-content">
                                <span class="data-label">Candidate Name</span>
                                <span class="data-value"><?php echo htmlspecialchars($result['full_name']); ?></span>
                            </div>
                        </div>

                        <div class="data-item">
                            <div class="di-icon"><i class="fas fa-book"></i></div>
                            <div class="di-content">
                                <span class="data-label">Examination Subject</span>
                                <span class="data-value">
                                    <?php echo htmlspecialchars($result['category_name']); ?> 
                                    <span style="color: var(--primary);">[<?php echo htmlspecialchars($result['category_code']); ?>]</span>
                                </span>
                            </div>
                        </div>

                        <div class="data-item">
                            <div class="di-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div class="di-content">
                                <span class="data-label">Test Center & Date</span>
                                <span class="data-value">
                                    <?php echo $center_display; ?>
                                    <small><i class="far fa-calendar-alt" style="margin-right: 4px;"></i> <?php echo $date_display; ?></small>
                                </span>
                            </div>
                        </div>

                        <?php if(!empty($result['exam_conductor'])): ?>
                        <div class="data-item">
                            <div class="di-icon"><i class="fas fa-user-tie"></i></div>
                            <div class="di-content">
                                <span class="data-label">Exam Conductor / Invigilator</span>
                                <span class="data-value"><?php echo htmlspecialchars($result['exam_conductor']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>

                <div class="result-footer">
                    <div class="footer-note">
                        <i class="fas fa-shield-check" style="color: var(--success); font-size: 16px;"></i> 
                        System Generated • Validated
                    </div>
                    <div class="actions-row">
                        <a href="candidate-dashboard.php" class="btn btn-dash"><i class="fas fa-home"></i> Dashboard</a>
                        
                        <a href="scorecard.php?exam_id=<?php echo $exam_id; ?>" target="_blank" class="btn btn-print">
                            <i class="fas fa-certificate"></i> View Official Scorecard
                        </a>
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </div>

</body>
</html>