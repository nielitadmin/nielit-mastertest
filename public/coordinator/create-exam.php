<?php
// Enforce Strict Indian Standard Time
date_default_timezone_set('Asia/Kolkata');
session_name('NIELIT_COORD_SESSION'); 
session_start();

// Check if user is logged in and is coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'coordinator') {
    header("Location: coordinator-login.php");
    exit();
}

// ============================================================================
// NEW ARCHITECTURE: Import centralized database connection
// ============================================================================
require_once __DIR__ . '/../../config/database.php';

$error = '';

// Get categories and centers for dropdowns
$categories = [];
$centers = [];
$course_groups = [];

try {
    // Fetch data for dropdowns
    $categories = $pdo->query("SELECT id, category_code, category_name, duration_minutes FROM exam_categories ORDER BY category_code ASC")->fetchAll(PDO::FETCH_ASSOC);
    $centers = $pdo->query("SELECT id, center_code, center_name, city, capacity FROM exam_centers WHERE is_active = true ORDER BY center_name")->fetchAll(PDO::FETCH_ASSOC);
    
    // --- SMART CATEGORIZATION ENGINE ---
    $course_groups = [
        'O-Level Modules' => [],
        'A-Level Modules' => [],
        'B/C-Level Modules' => [],
        'Short Term / CCC' => [],
        'Other Modules' => []
    ];

    foreach ($categories as $cat) {
        $code = strtoupper($cat['category_code']);
        $name = strtoupper($cat['category_name']);
        
        if (preg_match('/^M[1-4]-R/i', $code) || strpos($name, 'O LEVEL') !== false || strpos($name, 'O-LEVEL') !== false) {
            $course_groups['O-Level Modules'][] = $cat;
        } elseif (preg_match('/^A[0-9\.]+-R/i', $code) || strpos($name, 'A LEVEL') !== false || strpos($name, 'A-LEVEL') !== false) {
            $course_groups['A-Level Modules'][] = $cat;
        } elseif (preg_match('/^[BC][1-9]-/i', $code)) {
            $course_groups['B/C-Level Modules'][] = $cat;
        } elseif (strpos($code, 'CCC') !== false || strpos($code, 'BCC') !== false || strpos($name, 'COMPUTER CONCEPTS') !== false) {
            $course_groups['Short Term / CCC'][] = $cat;
        } else {
            $course_groups['Other Modules'][] = $cat;
        }
    }
    
    $course_groups = array_filter($course_groups, function($val) { return count($val) > 0; });

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $category_id = $_POST['category_id']; 
        $conductor_name = trim($_POST['conductor_name']);
        
        // 🟢 FIX: Use strict integers for database booleans to prevent insertion errors
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_practice = isset($_POST['is_practice']) ? 1 : 0;
        
        if ($is_practice) {
            $center_id = null; 
            // 🟢 FIX: Assign a far future date to bypass DB constraints so it never expires
            $exam_date = date('Y-m-d', strtotime('+10 years')); 
            $start_time = '00:00:00';
            $end_time = '23:59:59';
            $total_seats = 999999; 
        } else {
            $center_id = $_POST['center_id'];
            $exam_date = $_POST['exam_date'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $total_seats = (int)$_POST['total_seats'];
        }
        
        // Validate
        if (empty($category_id)) {
            $error = "Please select a specific Module.";
        } elseif (empty($conductor_name)) { 
            $error = "Please provide the Exam Conductor's name.";
        } elseif (!$is_practice && (empty($center_id) || empty($exam_date) || empty($start_time) || empty($end_time))) {
            $error = "Please fill in all required fields for a formal exam.";
        } elseif (!$is_practice && $end_time <= $start_time) {
            $error = "End time must be logically after the start time.";
        } else {
            $cat = array_filter($categories, fn($c) => $c['id'] == $category_id);
            $cat = reset($cat);
            
            if ($is_practice) {
                $exam_code = $cat['category_code'] . '-PRACTICE-' . rand(10000, 99999);
            } else {
                $cen = array_filter($centers, fn($c) => $c['id'] == $center_id);
                $cen = reset($cen);
                $base_code = $cat['category_code'] . '-' . $cen['center_code'] . '-' . date('ymd', strtotime($exam_date)) . '-' . date('Hi', strtotime($start_time));
                $exam_code = $base_code;
                
                $check = $pdo->prepare("SELECT id FROM exam_sessions WHERE exam_code = ?");
                $check->execute([$exam_code]);
                if ($check->fetch()) {
                    $exam_code = $base_code . '-' . rand(100, 999);
                }
            }
            
            // Atomic Transaction to prevent race conditions
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO exam_sessions (exam_code, category_id, center_id, exam_date, start_time, end_time, total_seats, is_active, created_by, is_practice, exam_conductor)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$exam_code, $category_id, $center_id, $exam_date, $start_time, $end_time, $total_seats, $is_active, $_SESSION['user_id'], $is_practice, $conductor_name]);
            
            $pdo->commit();
            
            header("Location: manage-exams.php?msg=Exam Session Created Successfully: $exam_code");
            exit();
        }
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    $error = "System Database Error: " . $e->getMessage();
}

$cenData = [];
foreach($centers as $c) { $cenData[$c['id']] = $c['capacity']; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Exam Session - Coordinator Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* COORDINATOR PURPLE THEME */
            --primary: #7C3AED;
            --primary-light: #8B5CF6;
            --primary-dark: #5B21B6;
            --primary-bg: #EDE9FE;
            --gradient-main: linear-gradient(135deg, #7C3AED 0%, #4F46E5 100%);
            
            --success: #059669;
            --success-bg: #D1FAE5;
            --warning: #D97706;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            
            --text-dark: #0F172A;
            --text-muted: #64748B;
            --bg-page: #F8FAFC;
            --card-bg: rgba(255, 255, 255, 0.85);
            --border: rgba(226, 232, 240, 0.9);
            
            --shadow-sm: 0 4px 6px -1px rgba(124, 58, 237, 0.05);
            --shadow-md: 0 10px 25px -5px rgba(124, 58, 237, 0.1);
            --shadow-float: 0 20px 40px -10px rgba(124, 58, 237, 0.15);
            
            --radius-md: 14px;
            --radius-lg: 24px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg-page); 
            color: var(--text-dark); 
            min-height: 100vh; 
            overflow-x: hidden; 
            padding-bottom: 60px; 
        }

        /* --- ADVANCED 3D AMBIENT BACKGROUND (Purple Version) --- */
        .ambient-wrapper {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1; overflow: hidden; pointer-events: none;
            background: linear-gradient(135deg, #F8FAFC 0%, #E2E8F0 100%);
            perspective: 1200px;
        }
        
        .ambient-wrapper::after {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(circle at 50% 0%, rgba(124, 58, 237, 0.1) 0%, transparent 70%);
        }

        .shape3d {
            position: absolute;
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.4));
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.8);
            box-shadow: 0 25px 50px -12px rgba(124, 58, 237, 0.15), inset 0 0 20px rgba(255,255,255,0.6);
            transform-style: preserve-3d;
            animation: float-complex 25s infinite cubic-bezier(0.4, 0, 0.2, 1);
        }

        .s-cube { width: 150px; height: 150px; border-radius: 30px; top: 10%; left: 5%; animation-duration: 28s; }
        .s-pill { width: 250px; height: 80px; border-radius: 50px; top: 60%; right: -5%; animation-duration: 32s; animation-direction: reverse; }
        .s-circle { width: 180px; height: 180px; border-radius: 50%; bottom: 5%; left: 15%; animation-duration: 22s; }

        @keyframes float-complex {
            0% { transform: translateY(0) translateZ(0) rotateX(0deg) rotateY(0deg); }
            33% { transform: translateY(-30px) translateZ(50px) rotateX(15deg) rotateY(20deg); }
            66% { transform: translateY(20px) translateZ(-30px) rotateX(-10deg) rotateY(-15deg); }
            100% { transform: translateY(0) translateZ(0) rotateX(0deg) rotateY(0deg); }
        }

        /* --- STICKY GLASS NAVBAR --- */
        .navbar-wrapper { 
            position: sticky; top: 0; z-index: 1000; 
            background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.6); 
            box-shadow: var(--shadow-sm); 
        }
        .top-nav { padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; }
        
        .nav-left { display: flex; align-items: center; gap: 24px; }
        
        .btn-back { 
            display: inline-flex; align-items: center; gap: 8px; 
            background: white; border: 1px solid var(--border); 
            padding: 10px 20px; border-radius: 50px; color: var(--text-dark); 
            text-decoration: none; font-weight: 700; font-size: 13px; 
            transition: all 0.3s ease; box-shadow: var(--shadow-sm);
        }
        .btn-back:hover { 
            background: var(--primary); color: white; border-color: var(--primary);
            transform: translateX(-4px); box-shadow: 0 4px 12px rgba(124, 58, 237, 0.2); 
        }

        .brand-text h2 { font-size: 20px; font-weight: 800; background: var(--gradient-main); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1.2; }
        .brand-text span { font-size: 12px; color: var(--text-muted); font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;}

        .user-info { display: flex; align-items: center; gap: 12px; background: white; padding: 6px 16px 6px 6px; border-radius: 50px; border: 1px solid var(--border); box-shadow: var(--shadow-sm);}
        .user-info span { font-size: 14px; font-weight: 700; color: var(--text-dark); }
        .user-avatar { width: 34px; height: 34px; background: var(--gradient-main); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px;}

        /* --- MAIN FORM CONTAINER --- */
        .container { max-width: 900px; margin: 50px auto; padding: 0 20px; position: relative; z-index: 10; animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes slideUpFade { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        .page-header { text-align: center; margin-bottom: 35px; }
        .page-header h1 { font-size: 32px; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; letter-spacing: -0.5px;}
        .page-header p { color: var(--text-muted); font-size: 15px; font-weight: 500;}

        .alert-error { background: white; color: var(--danger); border-left: 4px solid var(--danger); box-shadow: var(--shadow-md); padding: 18px 24px; border-radius: 8px; font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 12px; margin-bottom: 25px; }

        /* Form Card */
        .form-card { 
            background: var(--card-bg); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
            border-radius: var(--radius-lg); border: 1px solid rgba(255,255,255,0.6); 
            box-shadow: var(--shadow-float); padding: 45px; 
        }

        .section-title { 
            font-size: 13px; font-weight: 800; color: var(--primary); 
            text-transform: uppercase; letter-spacing: 1.5px; 
            margin-bottom: 25px; display: flex; align-items: center; gap: 10px; 
        }
        .section-title::after { content: ''; flex: 1; height: 2px; background: linear-gradient(90deg, var(--primary-bg), transparent); border-radius: 2px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 35px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; font-size: 13px; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; }
        
        .input-wrap { position: relative; transition: all 0.3s; }
        .input-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 15px; pointer-events: none; transition: 0.3s; }
        
        .form-control { 
            width: 100%; padding: 15px 18px 15px 50px; border-radius: var(--radius-md); 
            border: 2px solid var(--border); background: white; 
            font-size: 14px; font-weight: 600; color: var(--text-dark);
            outline: none; transition: all 0.3s ease; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
            font-family: inherit;
        }
        .form-control:focus { border-color: var(--primary-light); box-shadow: 0 0 0 4px var(--primary-bg); }
        .input-wrap:focus-within .input-icon { color: var(--primary); }
        
        select.form-control { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 18px center; }
        select:disabled { background-color: #F8FAFC; color: #94A3B8; cursor: not-allowed; border-color: #E2E8F0; }

        input[type="date"].form-control, input[type="time"].form-control { padding-left: 50px; }
        input[type="date"]::-webkit-calendar-picker-indicator, input[type="time"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.5; transition: 0.2s;}
        input[type="date"]:focus::-webkit-calendar-picker-indicator, input[type="time"]:focus::-webkit-calendar-picker-indicator { opacity: 1; }

        /* Practice Banner Styling */
        .practice-banner { 
            background: linear-gradient(to right, #FFFBEB, white); border: 1px solid #FEF3C7; 
            padding: 24px; border-radius: var(--radius-md); margin-bottom: 35px; 
            display: flex; align-items: center; justify-content: space-between; 
            box-shadow: var(--shadow-sm);
        }
        .practice-banner h3 { color: var(--warning); font-size: 16px; margin-bottom: 4px; font-weight: 800; display: flex; align-items: center; gap: 8px;}
        .practice-banner p { color: #92400E; font-size: 13px; font-weight: 600; margin: 0;}

        /* Modern Toggle Switch */
        .switch { position: relative; display: inline-block; width: 54px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #E2E8F0; transition: .4s; border-radius: 34px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);}
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s cubic-bezier(0.68, -0.55, 0.265, 1.55); border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        input:checked + .slider { background-color: var(--success); }
        input:checked + .slider:before { transform: translateX(26px); }
        input:checked + .slider.warning { background-color: var(--warning); }

        /* Formal Settings Wrapper */
        #formal-settings { transition: opacity 0.3s ease, height 0.3s ease; }

        /* Actions */
        .action-row { display: flex; justify-content: flex-end; gap: 15px; margin-top: 40px; padding-top: 25px; border-top: 2px dashed var(--border); }
        .btn { padding: 15px 30px; border-radius: var(--radius-md); font-weight: 800; font-size: 14px; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 10px; border: none; text-decoration: none; font-family: inherit; }
        .btn-cancel { background: white; color: var(--text-dark); border: 2px solid var(--border); }
        .btn-cancel:hover { background: var(--bg-page); border-color: #CBD5E1; transform: translateY(-2px); }
        .btn-submit { background: var(--gradient-main); color: white; box-shadow: 0 10px 20px -5px rgba(124, 58, 237, 0.4); }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 15px 25px -5px rgba(124, 58, 237, 0.5); }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; gap: 20px; }
            .action-row { flex-direction: column-reverse; }
            .btn { width: 100%; justify-content: center; }
            .form-card { padding: 25px; }
            .top-nav { padding: 15px 20px; }
            .practice-banner { flex-direction: column; align-items: flex-start; gap: 20px;}
        }
    </style>
</head>
<body>

    <div class="ambient-wrapper">
        <div class="shape3d s-cube"></div>
        <div class="shape3d s-pill"></div>
        <div class="shape3d s-circle"></div>
    </div>

    <div class="navbar-wrapper">
        <nav class="top-nav">
            <div class="nav-left">
                <a href="manage-exams.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> <span>Back to Exams</span>
                </a>
                <div class="brand-text">
                    <h2>Schedule Builder</h2>
                    <span class="hide-mobile">Coordinator Portal</span>
                </div>
            </div>
            <div class="nav-right">
                <div class="user-info hide-mobile">
                    <span><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'C', 0, 1)); ?></div>
                </div>
            </div>
        </nav>
    </div>

    <div class="container">
        
        <div class="page-header">
            <h1>Create Exam Session</h1>
            <p>Configure a new computer-based test session and assign capacities.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i> 
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="form-card" autocomplete="off">
            
            <div class="practice-banner">
                <div>
                    <h3><i class="fas fa-infinity"></i> Practice Mode Configuration</h3>
                    <p>Enable unlimited seats and flexible 24/7 scheduling. Scores are unrecorded.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="is_practice" id="practiceToggle" value="1" onchange="togglePracticeMode()" <?php echo (isset($_POST['is_practice']) && $_POST['is_practice'] == '1') ? 'checked' : ''; ?>>
                    <span class="slider" style="background-color: var(--warning);"></span>
                </label>
            </div>

            <div class="section-title"><i class="fas fa-layer-group"></i> Exam Classification</div>
            
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Exam Conductor Name <span style="color:var(--danger);">*</span></label>
                    <div class="input-wrap">
                        <input type="text" name="conductor_name" class="form-control" placeholder="E.g. Mr. Rajesh Kumar / NIELIT BBSR Team" required value="<?php echo isset($_POST['conductor_name']) ? htmlspecialchars($_POST['conductor_name']) : ''; ?>">
                        <i class="fas fa-user-tie input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Course / Program Category <span style="color:var(--danger);">*</span></label>
                    <div class="input-wrap">
                        <select id="parentCategory" class="form-control" required onchange="updateModules()">
                            <option value="">Select Category...</option>
                            <?php foreach(array_keys($course_groups) as $group_name): ?>
                                <option value="<?php echo htmlspecialchars($group_name); ?>"><?php echo htmlspecialchars($group_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-folder-open input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Specific Module <span style="color:var(--danger);">*</span></label>
                    <div class="input-wrap">
                        <select name="category_id" id="category_id" class="form-control" required disabled>
                            <option value="">Select Course first...</option>
                        </select>
                        <i class="fas fa-book input-icon"></i>
                    </div>
                </div>
            </div>

            <div id="formal-settings">
                <div class="section-title"><i class="fas fa-map-marker-alt"></i> Location & Timing</div>
                
                <div class="form-group full-width" style="margin-bottom: 24px;">
                    <label>Designated Exam Center <span style="color:var(--danger);">*</span></label>
                    <div class="input-wrap">
                        <select name="center_id" id="center_id" class="form-control req-formal" required onchange="updateCapacityHint()">
                            <option value="">Select Physical Location...</option>
                            <?php foreach ($centers as $cen): ?>
                                <option value="<?php echo $cen['id']; ?>" <?php echo (isset($_POST['center_id']) && $_POST['center_id'] == $cen['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cen['center_name']) . ' - ' . htmlspecialchars($cen['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-building input-icon"></i>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Scheduled Date <span style="color:var(--danger);">*</span></label>
                        <div class="input-wrap">
                            <input type="date" name="exam_date" class="form-control req-formal" min="<?php echo date('Y-m-d'); ?>" value="<?php echo isset($_POST['exam_date']) ? $_POST['exam_date'] : ''; ?>" required>
                            <i class="fas fa-calendar-alt input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Start Time <span style="color:var(--danger);">*</span></label>
                        <div class="input-wrap">
                            <input type="time" name="start_time" id="start_time" class="form-control req-formal" required value="<?php echo isset($_POST['start_time']) ? $_POST['start_time'] : ''; ?>">
                            <i class="fas fa-hourglass-start input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>End Time <span style="color:var(--danger);">*</span></label>
                        <div class="input-wrap">
                            <input type="time" name="end_time" id="end_time" class="form-control req-formal" required value="<?php echo isset($_POST['end_time']) ? $_POST['end_time'] : ''; ?>">
                            <i class="fas fa-hourglass-end input-icon"></i>
                        </div>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Total Available Seats <span style="color:var(--danger);">*</span></label>
                    <div class="input-wrap">
                        <input type="number" name="total_seats" id="total_seats" class="form-control req-formal" min="1" placeholder="Enter maximum seat limit..." required value="<?php echo isset($_POST['total_seats']) ? $_POST['total_seats'] : ''; ?>">
                        <i class="fas fa-chair input-icon"></i>
                    </div>
                    <span style="display: block; font-size: 12px; color: var(--primary); margin-top: 8px; font-weight: 600;" id="seat_hint"></span>
                </div>
            </div>

            <div class="section-title" style="margin-top: 35px;"><i class="fas fa-toggle-on"></i> Visibility</div>
            <div class="form-group full-width">
                <div style="display: flex; align-items: center; justify-content: space-between; background: white; border: 2px solid var(--border); padding: 18px 24px; border-radius: var(--radius-md);">
                    <div>
                        <span style="font-size: 14px; font-weight: 800; color: var(--text-dark); display: block;">Active Session</span>
                        <span style="font-size: 12px; color: var(--text-muted); font-weight: 600; margin-top: 4px; display: block;">Allow candidates to view and enroll immediately.</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="is_active" value="1" <?php echo (!isset($_POST) || isset($_POST['is_active'])) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="action-row">
                <a href="manage-exams.php" class="btn btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                <button type="submit" class="btn btn-submit"><i class="fas fa-check-circle"></i> Create & Publish Exam</button>
            </div>
            
        </form>
    </div>

    <script>
        const categoryData = <?php echo json_encode($course_groups); ?>;
        const centerCapacities = <?php echo json_encode($cenData); ?>;

        // Toggle Practice Mode Visibility & Requirements smoothly
        function togglePracticeMode() {
            const isPractice = document.getElementById('practiceToggle').checked;
            const formalSettings = document.getElementById('formal-settings');
            const formalInputs = document.querySelectorAll('.req-formal');
            
            if (isPractice) {
                formalSettings.style.opacity = '0';
                setTimeout(() => { formalSettings.style.display = 'none'; }, 300);
                formalInputs.forEach(input => input.removeAttribute('required'));
            } else {
                formalSettings.style.display = 'block';
                setTimeout(() => { formalSettings.style.opacity = '1'; }, 10);
                formalInputs.forEach(input => input.setAttribute('required', 'required'));
            }
        }

        function updateModules() {
            const parentVal = document.getElementById('parentCategory').value;
            const modSelect = document.getElementById('category_id');
            
            modSelect.innerHTML = '<option value="">Select specific module...</option>';
            
            if(parentVal && categoryData[parentVal]) {
                categoryData[parentVal].forEach(mod => {
                    const opt = document.createElement('option');
                    opt.value = mod.id;
                    opt.textContent = mod.category_code + ' - ' + mod.category_name;
                    modSelect.appendChild(opt);
                });
                modSelect.disabled = false;
            } else { 
                modSelect.disabled = true; 
            }
        }

        function updateCapacityHint() {
            const cenId = document.getElementById('center_id').value;
            const seatHint = document.getElementById('seat_hint');
            const totalSeats = document.getElementById('total_seats');
            
            if (cenId && centerCapacities[cenId]) {
                const maxCap = centerCapacities[cenId];
                seatHint.innerHTML = `<i class="fas fa-info-circle"></i> Max capacity for the selected center is ${maxCap}.`;
                totalSeats.max = maxCap;
            } else {
                seatHint.innerHTML = '';
                totalSeats.max = '';
            }
        }

        // Initialize state on load
        window.onload = function() {
            togglePracticeMode();
            
            // Re-populate dropdowns if there was a validation error on POST
            const preselectedModuleId = "<?php echo isset($_POST['category_id']) ? $_POST['category_id'] : ''; ?>";
            if(preselectedModuleId) {
                for(const [catName, modules] of Object.entries(categoryData)) {
                    if(modules.some(m => m.id == preselectedModuleId)) {
                        document.getElementById('parentCategory').value = catName;
                        updateModules();
                        document.getElementById('category_id').value = preselectedModuleId;
                        break;
                    }
                }
            }
            updateCapacityHint();
        };
    </script>
</body>
</html>