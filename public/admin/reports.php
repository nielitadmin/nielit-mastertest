<?php
// Enforce Strict Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

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

// ============================================================================
// NEW ARCHITECTURE: Import centralized database connection
// Path assumes this file is in: /public/admin/reports.php
// ============================================================================
require_once __DIR__ . '/../../config/database.php';

$error = '';
$results = [];
$categories = [];
$stats = [
    'total_evaluated' => 0,
    'passed' => 0,
    'failed' => 0,
    'avg_score' => 0
];

try {
    // Note: The $pdo connection is already set up and configured 
    // to 'Asia/Kolkata' via your config/database.php file!

    // 1. Fetch Master Results Directory
    $query = "
        SELECT 
            er.registration_id,
            u.full_name,
            c.registration_number,
            ec.category_name,
            ec.category_code,
            es.exam_code,
            es.exam_date,
            es.exam_conductor,
            er.total_marks_obtained,
            er.percentage,
            er.result_status,
            (SELECT SUM(marks) FROM questions WHERE category_id = es.category_id AND is_active = true) as max_marks
        FROM exam_results er
        JOIN exam_registrations reg ON er.registration_id = reg.id
        JOIN users u ON reg.candidate_id = u.id
        LEFT JOIN candidates c ON u.id = c.user_id
        JOIN exam_sessions es ON reg.session_id = es.id
        JOIN exam_categories ec ON es.category_id = ec.id
        ORDER BY es.exam_date DESC, er.percentage DESC
        LIMIT 1000
    ";
    $results = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Dynamic KPIs
    $total_percentage = 0;
    foreach ($results as $res) {
        $stats['total_evaluated']++;
        $total_percentage += $res['percentage'];
        if (strtolower($res['result_status']) === 'pass' || $res['result_status'] == 1 || $res['result_status'] === 't') {
            $stats['passed']++;
        } else {
            $stats['failed']++;
        }
    }
    if ($stats['total_evaluated'] > 0) {
        $stats['avg_score'] = round($total_percentage / $stats['total_evaluated'], 1);
    }

    // Fetch Unique Categories for Filter Dropdown
    $categories = $pdo->query("SELECT DISTINCT category_code, category_name FROM exam_categories ORDER BY category_code")->fetchAll(PDO::FETCH_ASSOC);

    // --- BULK CSV EXPORT FEATURE ---
    if (isset($_GET['export']) && $_GET['export'] == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="NIELIT_Results_Export_' . date('Ymd_Hi') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Roll Number', 'Candidate Name', 'Category', 'Exam Code', 'Exam Date', 'Max Marks', 'Marks Obtained', 'Percentage', 'Status']);
        
        foreach ($results as $r) {
            $statusStr = (strtolower($r['result_status']) === 'pass' || $r['result_status'] == 1 || $r['result_status'] === 't') ? 'PASS' : 'FAIL';
            fputcsv($output, [
                $r['registration_number'] ?? 'N/A',
                $r['full_name'],
                $r['category_code'] . ' - ' . $r['category_name'],
                $r['exam_code'],
                date('d M Y', strtotime($r['exam_date'])),
                $r['max_marks'],
                $r['total_marks_obtained'],
                round($r['percentage'], 1) . '%',
                $statusStr
            ]);
        }
        fclose($output);
        exit();
    }

} catch (PDOException $e) {
    $error = "System Database Offline. Please try again later.";
    error_log("Reports DB error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results Directory - NIELIT Admin Console</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #1D4ED8; --primary-light: #3B82F6; --primary-bg: #DBEAFE;     
            --success: #059669; --success-bg: #D1FAE5;
            --danger: #DC2626; --danger-bg: #FEE2E2;
            --warning: #D97706; --warning-bg: #FEF3C7;
            --text-dark: #0F172A; --text-muted: #64748B;
            --bg-body: #F8FAFC; --surface: #FFFFFF; --border: #E2E8F0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            --radius-md: 12px; --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; overflow-x: hidden; padding-bottom: 60px; }

        /* --- 3D AMBIENT BACKGROUND --- */
        .ambient-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; pointer-events: none; background: radial-gradient(circle at 50% 0%, #DCE6F1 0%, #E8F0F8 50%, #F4F7FB 100%); perspective: 1000px; }
        .shape { position: absolute; background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(59,130,246,0.05)); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.9); box-shadow: 0 15px 35px rgba(29,78,216,0.08), inset 0 0 20px rgba(255,255,255,0.5); animation: float-3d 20s infinite linear; }
        .cube { width: 120px; height: 120px; border-radius: 24px; top: 15%; left: 8%; animation-duration: 25s; }
        .ring { width: 200px; height: 200px; border-radius: 50%; border: 35px solid rgba(255,255,255,0.4); top: 50%; right: 5%; animation-duration: 30s; animation-direction: reverse; background: transparent; }
        @keyframes float-3d { 0% { transform: translateY(0) rotateX(0deg) rotateY(0deg); } 50% { transform: translateY(-40px) rotateX(180deg) rotateY(90deg); } 100% { transform: translateY(0) rotateX(360deg) rotateY(180deg); } }

        /* --- NAVBAR --- */
        .top-nav { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid rgba(255,255,255,0.5); }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--bg-body); border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s; }
        .btn-back:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); transform: translateX(-3px); }
        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--primary); line-height: 1.2; }
        .brand-text span { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .user-info { font-size: 14px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }

        .container { max-width: 1440px; margin: 30px auto; padding: 0 40px; position: relative; z-index: 10; animation: fadeUp 0.5s ease-out;}
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* --- KPI GRID --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); padding: 25px 20px; border-radius: var(--radius-md); border: 1px solid var(--border); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 15px; position: relative; overflow: hidden; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); }
        .stat-card::before { content:''; position:absolute; top:0; left:0; width:5px; height:100%; border-radius: 5px 0 0 5px; }
        .c-primary::before { background: var(--primary); } .c-success::before { background: var(--success); } .c-danger::before { background: var(--danger); } .c-warning::before { background: var(--warning); }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        .c-primary .stat-icon { background: var(--primary-bg); color: var(--primary); } .c-success .stat-icon { background: var(--success-bg); color: var(--success); } .c-danger .stat-icon { background: var(--danger-bg); color: var(--danger); } .c-warning .stat-icon { background: var(--warning-bg); color: var(--warning); }
        .stat-info h3 { font-size: 28px; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 5px; }
        .stat-info p { font-size: 12px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; }

        /* --- TOOLBAR --- */
        .toolbar { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); padding: 20px; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .filters { display: flex; gap: 15px; flex-wrap: wrap; flex: 1; }
        
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-select, .form-input { padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-body); font-family: inherit; font-size: 13px; font-weight: 600; color: var(--text-dark); outline: none; min-width: 200px; transition: 0.3s; }
        .form-select:focus, .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
        
        .btn-export { background: var(--success); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: 0.3s; text-decoration: none; }
        .btn-export:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.2); }

        /* --- DATA TABLE --- */
        .table-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-md); overflow: hidden; }
        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #F8FAFC; color: var(--text-muted); font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 16px 20px; border-bottom: 2px solid var(--border); white-space: nowrap;}
        td { padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600; color: var(--text-dark); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--primary-bg); }

        .cand-info { display: flex; flex-direction: column; gap: 3px; }
        .cand-name { font-weight: 800; color: var(--text-dark); }
        .cand-roll { font-size: 12px; color: var(--text-muted); }
        
        .exam-info { display: flex; flex-direction: column; gap: 3px; }
        .exam-cat { font-weight: 700; color: var(--primary); font-size: 12px; }
        .exam-code { font-size: 12px; color: var(--text-muted); }

        .score-wrap { display: flex; align-items: center; gap: 10px; }
        .score-val { font-size: 16px; font-weight: 800; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .bg-pass { background: var(--success-bg); color: var(--success); }
        .bg-fail { background: var(--danger-bg); color: var(--danger); }

        .btn-print { background: white; border: 1px solid var(--border); color: var(--text-dark); padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-print:hover { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 10px rgba(29, 78, 216, 0.2);}

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 40px; color: var(--border); margin-bottom: 15px; }

        /* ========================================= */
        /* HIDDEN PRINT TEMPLATE (GOVT FORMAT)       */
        /* ========================================= */
        #printTemplate { display: none; }

        @media print {
            @page { size: A4 portrait; margin: 15mm; }
            body { background: white !important; font-family: "Times New Roman", Times, serif !important; color: black !important;}
            
            /* Hide Web UI */
            .ambient-bg, .top-nav, .container { display: none !important; }
            
            /* Show & Style Print Template */
            #printTemplate { 
                display: block !important; 
                border: 4px double #000; 
                padding: 10mm; 
                position: relative; 
                min-height: 250mm;
            }
            #printTemplate::after {
                content: 'OFFICIAL SCORECARD'; position: absolute; top: 50%; left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg); font-size: 60pt; font-weight: 800;
                color: rgba(0, 0, 0, 0.05); z-index: -1; pointer-events: none; white-space: nowrap;
            }
            .pt-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; }
            .pt-header h1 { font-size: 24pt; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 2px;}
            .pt-header p { font-size: 12pt; font-weight: bold; margin-bottom: 15px;}
            .pt-roll { font-size: 14pt; font-weight: bold; border: 1px solid #000; display: inline-block; padding: 5px 15px; }
            
            .pt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; border: 2px solid #000; margin-bottom: 40px; }
            .pt-item { border: 1px solid #000; padding: 12px; }
            .pt-item.full { grid-column: 1 / -1; }
            .pt-label { font-size: 10pt; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; display: block; }
            .pt-val { font-size: 14pt; font-family: "Courier New", Courier, monospace; font-weight: bold;}

            .pt-score-box { border: 3px double #000; padding: 20px; text-align: center; margin-bottom: 40px; }
            .pt-score-val { font-size: 36pt; font-weight: bold; margin-bottom: 10px; font-family: "Courier New", Courier, monospace;}
            .pt-status { font-size: 20pt; font-weight: bold; letter-spacing: 3px; text-transform: uppercase;}

            .pt-footer { margin-top: 60px; text-align: right; font-size: 11pt; font-weight: bold; line-height: 1.8; }
        }
    </style>
</head>
<body>

    <div class="ambient-bg">
        <div class="shape cube"></div>
        <div class="shape ring"></div>
    </div>

    <nav class="top-nav">
        <div class="nav-left">
            <a href="admin-dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div class="brand-text">
                <h2>Results Directory</h2>
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
        
        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card c-primary">
                <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_evaluated']); ?></h3>
                    <p>Total Evaluated</p>
                </div>
            </div>
            <div class="stat-card c-success">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['passed']); ?></h3>
                    <p>Candidates Passed</p>
                </div>
            </div>
            <div class="stat-card c-danger">
                <div class="stat-icon"><i class="fas fa-user-times"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['failed']); ?></h3>
                    <p>Candidates Failed</p>
                </div>
            </div>
            <div class="stat-card c-warning">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['avg_score']; ?>%</h3>
                    <p>Average Score</p>
                </div>
            </div>
        </div>

        <div class="toolbar">
            <div class="filters">
                <div class="filter-group">
                    <label>Search Candidate</label>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="form-input" placeholder="Name, Roll, or Exam Code...">
                    </div>
                </div>
                <div class="filter-group">
                    <label>Filter by Category</label>
                    <select id="catFilter" class="form-select">
                        <option value="all">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category_code']); ?>">
                                <?php echo htmlspecialchars($cat['category_code'] . ' - ' . $cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select id="statusFilter" class="form-select">
                        <option value="all">All Status</option>
                        <option value="pass">Passed Only</option>
                        <option value="fail">Failed Only</option>
                    </select>
                </div>
            </div>
            <div>
                <a href="reports.php?export=csv" class="btn-export"><i class="fas fa-file-csv"></i> Export Data to CSV</a>
            </div>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table id="resultsTable">
                    <thead>
                        <tr>
                            <th>Candidate Details</th>
                            <th>Exam Information</th>
                            <th>Final Score</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="fas fa-clipboard-check"></i>
                                        <h3>No Results Published</h3>
                                        <p>Candidate results will appear here automatically once exams are submitted.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($results as $res): 
                                $isPass = (strtolower($res['result_status']) === 'pass' || $res['result_status'] == 1 || $res['result_status'] === 't');
                                $statusClass = $isPass ? 'bg-pass' : 'bg-fail';
                                $statusText = $isPass ? 'PASS' : 'FAIL';
                                $catCode = htmlspecialchars($res['category_code']);
                                $percent = round($res['percentage'], 1);
                                
                                // Data attributes used for JS Printing
                                $printData = htmlspecialchars(json_encode([
                                    'name' => $res['full_name'],
                                    'roll' => $res['registration_number'] ?? 'N/A',
                                    'cat' => $res['category_code'] . ' - ' . $res['category_name'],
                                    'code' => $res['exam_code'],
                                    'date' => date('d M Y', strtotime($res['exam_date'])),
                                    'cond' => $res['exam_conductor'] ?? 'N/A',
                                    'max' => $res['max_marks'],
                                    'obt' => $res['total_marks_obtained'],
                                    'perc' => $percent,
                                    'stat' => $statusText
                                ]));
                            ?>
                            <tr class="res-row" data-cat="<?php echo $catCode; ?>" data-status="<?php echo strtolower($statusText); ?>">
                                <td>
                                    <div class="cand-info">
                                        <span class="cand-name"><?php echo htmlspecialchars($res['full_name']); ?></span>
                                        <span class="cand-roll">Roll: <?php echo htmlspecialchars($res['registration_number'] ?? 'N/A'); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="exam-info">
                                        <span class="exam-cat"><?php echo htmlspecialchars($res['category_name']); ?></span>
                                        <span class="exam-code"><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($res['exam_code']); ?> &nbsp;|&nbsp; <i class="far fa-calendar"></i> <?php echo date('d M Y', strtotime($res['exam_date'])); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="score-wrap">
                                        <span class="score-val" style="color: <?php echo $isPass ? 'var(--success)' : 'var(--danger)'; ?>;"><?php echo $percent; ?>%</span>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <button class="btn-print" onclick="triggerPrint(this)" data-payload='<?php echo $printData; ?>'>
                                        <i class="fas fa-print"></i> Print Card
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="printTemplate">
        <div class="pt-header">
            <h1>Official Examination Scorecard</h1>
            <p>National Institute of Electronics & Information Technology</p>
            <div class="pt-roll">ENROLMENT NO: <span id="pt-roll"></span></div>
        </div>

        <div class="pt-grid">
            <div class="pt-item full">
                <span class="pt-label">Candidate Name</span>
                <span class="pt-val" id="pt-name"></span>
            </div>
            <div class="pt-item full">
                <span class="pt-label">Examination Category</span>
                <span class="pt-val" id="pt-cat"></span>
            </div>
            <div class="pt-item">
                <span class="pt-label">Session Code</span>
                <span class="pt-val" id="pt-code"></span>
            </div>
            <div class="pt-item">
                <span class="pt-label">Examination Date</span>
                <span class="pt-val" id="pt-date"></span>
            </div>
            <div class="pt-item">
                <span class="pt-label">Maximum Marks</span>
                <span class="pt-val" id="pt-max"></span>
            </div>
            <div class="pt-item">
                <span class="pt-label">Marks Obtained</span>
                <span class="pt-val" id="pt-obt"></span>
            </div>
            <div class="pt-item full">
                <span class="pt-label">Invigilator / Conductor</span>
                <span class="pt-val" id="pt-cond"></span>
            </div>
        </div>

        <div class="pt-score-box">
            <div style="font-size: 14pt; font-weight: bold; margin-bottom: 5px;">FINAL SCORE PERCENTAGE</div>
            <div class="pt-score-val" id="pt-perc"></div>
            <div class="pt-status" id="pt-stat"></div>
        </div>

        <div class="pt-footer">
            THIS IS A SYSTEM GENERATED SECURE DOCUMENT.<br>
            VALID WITHOUT PHYSICAL SIGNATURE.<br><br><br><br>
            __________________________________<br>
            AUTHORIZED SIGNATORY / ISSUING AUTHORITY<br>
            NIELIT EXAMINATION BOARD
        </div>
    </div>

    <script>
        // --- FILTERING LOGIC ---
        const searchInput = document.getElementById('searchInput');
        const catFilter = document.getElementById('catFilter');
        const statusFilter = document.getElementById('statusFilter');
        const rows = document.querySelectorAll('.res-row');

        function applyFilters() {
            const query = searchInput.value.toLowerCase();
            const cat = catFilter.value;
            const stat = statusFilter.value;

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                const rowCat = row.getAttribute('data-cat');
                const rowStat = row.getAttribute('data-status');

                const matchesSearch = text.includes(query);
                const matchesCat = (cat === 'all' || rowCat === cat);
                const matchesStat = (stat === 'all' || rowStat === stat);

                if (matchesSearch && matchesCat && matchesStat) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('keyup', applyFilters);
        catFilter.addEventListener('change', applyFilters);
        statusFilter.addEventListener('change', applyFilters);

        // --- PRINT LOGIC ---
        function triggerPrint(btnElement) {
            // 1. Get the data payload from the button
            const data = JSON.parse(btnElement.getAttribute('data-payload'));

            // 2. Populate the hidden print template
            document.getElementById('pt-roll').innerText = data.roll;
            document.getElementById('pt-name').innerText = data.name;
            document.getElementById('pt-cat').innerText = data.cat;
            document.getElementById('pt-code').innerText = data.code;
            document.getElementById('pt-date').innerText = data.date;
            document.getElementById('pt-cond').innerText = data.cond;
            document.getElementById('pt-max').innerText = data.max;
            document.getElementById('pt-obt').innerText = data.obt;
            document.getElementById('pt-perc').innerText = data.perc + '%';
            
            const statEl = document.getElementById('pt-stat');
            statEl.innerText = data.stat;
            statEl.style.color = data.stat === 'PASS' ? 'black' : 'black'; // Keep black for official print

            // 3. Trigger Browser Print
            // The @media print CSS handles hiding the main UI and showing ONLY the template!
            window.print();
        }
    </script>
</body>
</html>