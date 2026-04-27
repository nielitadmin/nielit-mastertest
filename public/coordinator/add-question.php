<?php
// Use the correct Coordinator session name
session_name('NIELIT_COORD_SESSION'); 
session_start();

// Check if user is logged in and is a coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'coordinator') {
    header("Location: coordinator-login.php");
    exit();
}

// ============================================================================
// NEW ARCHITECTURE: Import centralized database connection
// ============================================================================
require_once __DIR__ . '/../../config/database.php';

$error = '';
$success = '';

try {
    // Fetch all categories from DB
    $categories = $pdo->query("SELECT id, category_code, category_name FROM exam_categories ORDER BY category_code ASC")->fetchAll(PDO::FETCH_ASSOC);

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
    
    // Remove completely empty groups
    $course_groups = array_filter($course_groups, function($val) { return count($val) > 0; });

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $category_id = $_POST['category_id'];
        $question_text = trim($_POST['question_text']);
        $question_type = $_POST['question_type'];
        $difficulty_level = $_POST['difficulty_level'];
        $marks = floatval($_POST['marks']);
        $explanation = trim($_POST['explanation'] ?? '');
        
        // Validate
        $errors = [];
        if (empty($category_id)) $errors[] = "Please select a specific Module.";
        if (empty($question_text)) $errors[] = "Question text is required.";
        if ($marks <= 0) $errors[] = "Marks must be greater than 0.";
        
        // Validate options for MCQ
        if ($question_type == 'mcq') {
            $has_options = false;
            $has_correct = false;
            
            if (isset($_POST['options']) && is_array($_POST['options'])) {
                foreach ($_POST['options'] as $option) {
                    if (!empty(trim($option['text'] ?? ''))) {
                        $has_options = true;
                        if (isset($option['is_correct']) && $option['is_correct'] === 'on') {
                            $has_correct = true;
                        }
                    }
                }
            }
            
            if (!$has_options) $errors[] = "At least one option is required.";
            if (!$has_correct) $errors[] = "Please mark at least one option as correct.";
        }
        
        if (empty($errors)) {
            $pdo->beginTransaction();
            
            // 🟢 FIX 1: MySQL uses lastInsertId() instead of RETURNING id
            $stmt = $pdo->prepare("
                INSERT INTO questions (category_id, question_text, question_type, difficulty_level, marks, explanation, created_by, created_at, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)
            ");
            $stmt->execute([$category_id, $question_text, $question_type, $difficulty_level, $marks, $explanation, $_SESSION['user_id']]);
            
            // Grab the newly created question ID
            $question_id = $pdo->lastInsertId();
            
            // 🟢 FIX 2: Removed PostgreSQL ::boolean cast. MySQL uses 1 and 0.
            if ($question_type == 'mcq' && isset($_POST['options'])) {
                $opt_stmt = $pdo->prepare("
                    INSERT INTO question_options (question_id, option_text, is_correct, option_order)
                    VALUES (?, ?, ?, ?)
                ");
                
                $order = 1;
                foreach ($_POST['options'] as $option) {
                    if (!empty(trim($option['text'] ?? ''))) {
                        // Use 1 for true, 0 for false in MySQL
                        $is_correct = (isset($option['is_correct']) && $option['is_correct'] === 'on') ? 1 : 0;
                        $opt_stmt->execute([$question_id, trim($option['text']), $is_correct, $order]);
                        $order++;
                    }
                }
            } elseif ($question_type == 'true_false') {
                $tf_correct = $_POST['tf_correct'] ?? 'true';
                $opt_stmt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct, option_order) VALUES (?, ?, ?, ?)");
                
                // Use 1 for true, 0 for false in MySQL
                $opt_stmt->execute([$question_id, 'True', ($tf_correct == 'true') ? 1 : 0, 1]);
                $opt_stmt->execute([$question_id, 'False', ($tf_correct == 'false') ? 1 : 0, 2]);
            }
            
            $pdo->commit();
            header("Location: add-question.php?msg=success");
            exit();
        } else {
            $error = implode("<br>", $errors);
        }
    }

    if (isset($_GET['msg']) && $_GET['msg'] === 'success') {
        $success = "Question successfully added to the module repository!";
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $error = "System Database Error. Please try again later.";
    // Uncomment the line below to see the exact database error in your Hostinger logs if it ever fails again
    // error_log("Add Question DB error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Question - NIELIT Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1D4ED8;        --primary-light: #3B82F6;  --primary-bg: #DBEAFE;     
            --secondary: #0F172A;
            --success: #059669;        --success-bg: #D1FAE5;
            --danger: #DC2626;         --danger-bg: #FEE2E2;
            --text-dark: #0F172A;      --text-muted: #64748B;
            --bg-body: #F4F7FB;        --surface: #FFFFFF;
            --border: #E2E8F0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 40px -10px rgba(29, 78, 216, 0.1);
            --radius-md: 12px;         --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body); color: var(--text-dark);
            min-height: 100vh; overflow-x: hidden; padding-bottom: 60px; position: relative;
        }

        /* --- 3D MOVING BACKGROUND --- */
        .ambient-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100vh; z-index: -1; overflow: hidden; pointer-events: none; background: linear-gradient(180deg, #F8FAFC 0%, #E2E8F0 100%); perspective: 1000px; }
        .orb { position: absolute; border-radius: 50%; filter: blur(90px); opacity: 0.6; animation: float-orb 20s infinite alternate cubic-bezier(0.45, 0.05, 0.55, 0.95); }
        .orb-1 { width: 600px; height: 600px; background: linear-gradient(135deg, #BAE6FD, #38BDF8); top: -10%; left: -10%; }
        .orb-2 { width: 500px; height: 500px; background: linear-gradient(135deg, #7DD3FC, #0284C7); bottom: -20%; right: -5%; animation-delay: -5s; }
        .shape { position: absolute; background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(59,130,246,0.05)); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.9); box-shadow: 0 15px 35px rgba(29,78,216,0.08), inset 0 0 20px rgba(255,255,255,0.5); animation: float-3d 20s infinite linear; }
        .cube { width: 120px; height: 120px; border-radius: 24px; top: 15%; left: 5%; animation-duration: 25s; }
        .ring { width: 200px; height: 200px; border-radius: 50%; border: 35px solid rgba(255,255,255,0.4); top: 50%; right: 5%; animation-duration: 30s; animation-direction: reverse; background: transparent; }
        .pyramid { width: 80px; height: 80px; border-radius: 16px; bottom: 15%; left: 20%; animation-duration: 18s; }

        @keyframes float-orb { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(100px, 50px) scale(1.1); } }
        @keyframes float-3d { 0% { transform: translateY(0) rotateX(0deg) rotateY(0deg) rotateZ(0deg); } 50% { transform: translateY(-40px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); } 100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); } }

        /* --- STICKY GLASSMORPHISM NAVBAR --- */
        .navbar-wrapper {
            position: sticky; top: 0; z-index: 1000;
            background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 4px 30px -10px rgba(0,0,0,0.05);
        }
        .top-nav { padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--bg-body); border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s; }
        .btn-back:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); transform: translateX(-3px); }
        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--primary); line-height: 1.2; }
        .brand-text span { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .user-info { font-size: 14px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }

        /* --- MAIN CONTAINER --- */
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; position: relative; z-index: 10; animation: fadeUp 0.5s ease-out;}
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .header-text h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); margin-bottom: 5px; display: flex; align-items: center; gap: 10px; }
        .header-text p { color: var(--text-muted); font-size: 14px; font-weight: 500; }
        
        .btn-bulk { background: var(--surface); color: var(--primary); border: 1px solid var(--primary-light); padding: 12px 20px; border-radius: var(--radius-md); text-decoration: none; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; box-shadow: var(--shadow-sm); }
        .btn-bulk:hover { background: var(--primary-bg); transform: translateY(-2px); box-shadow: var(--shadow-md); }

        .alert { padding: 16px 20px; border-radius: var(--radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 25px; border: 1px solid transparent; }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: #A7F3D0; }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: #FECACA; }

        /* --- FORM CARD (BENTO STYLE) --- */
        .form-card { background: rgba(255,255,255,0.9); backdrop-filter: blur(16px); border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-lg); padding: 40px; }

        .info-box { background: var(--primary-bg); color: var(--primary); padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: 30px; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 12px; border: 1px solid #BFDBFE; }
        .info-box i { font-size: 18px; }

        .section-title { font-size: 13px; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--border); display: flex; align-items: center; gap: 8px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .form-group.full-width { grid-column: 1 / -1; }

        .form-group label { display: block; font-size: 13px; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 16px; top: 20px; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; pointer-events: none; transition: 0.3s; }
        
        .form-control { width: 100%; padding: 12px 16px 12px 42px; border-radius: 12px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-dark); font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 500; transition: all 0.3s; outline: none; appearance: none; }
        textarea.form-control { padding: 16px; min-height: 120px; resize: vertical; line-height: 1.5; }
        select.form-control { cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; padding-right: 40px; }
        select:disabled { background-color: #F1F5F9; color: #94A3B8; cursor: not-allowed; }
        
        .form-control:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px var(--primary-bg); }
        .form-control:focus + .input-icon, .input-wrap:focus-within .input-icon { color: var(--primary); }
        .form-control::placeholder { color: #94A3B8; }

        /* --- OPTIONS BUILDER SECTION --- */
        .options-section { background: #F8FAFC; border: 1px dashed var(--border); padding: 25px; border-radius: var(--radius-md); margin-bottom: 30px; }
        .options-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .options-header h3 { font-size: 15px; font-weight: 700; color: var(--text-dark); }
        .options-header p { font-size: 12px; color: var(--text-muted); }
        
        .option-row { display: flex; align-items: center; gap: 15px; margin-bottom: 12px; background: white; padding: 12px 15px; border-radius: 12px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: 0.2s; }
        .option-row:focus-within { border-color: var(--primary-light); box-shadow: 0 0 0 3px var(--primary-bg); }
        .option-row input[type="text"] { flex: 1; border: none; outline: none; font-family: inherit; font-size: 14px; font-weight: 500; }
        
        /* Custom Checkbox */
        .correct-toggle { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 700; color: var(--text-muted); user-select: none; padding-right: 10px; border-right: 1px solid var(--border); }
        .correct-toggle input { display: none; }
        .check-indicator { width: 22px; height: 22px; border-radius: 6px; border: 2px solid var(--border); background: var(--bg-body); display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .check-indicator i { opacity: 0; color: white; font-size: 12px; transition: 0.2s; }
        .correct-toggle input:checked + .check-indicator { background: var(--success); border-color: var(--success); box-shadow: 0 2px 8px rgba(5,150,105,0.3); }
        .correct-toggle input:checked + .check-indicator i { opacity: 1; }
        .correct-toggle input:checked ~ span { color: var(--success); }

        .btn-remove-opt { background: transparent; color: var(--text-muted); border: none; font-size: 18px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; }
        .btn-remove-opt:hover:not(:disabled) { background: var(--danger-bg); color: var(--danger); }
        .btn-remove-opt:disabled { opacity: 0.3; cursor: not-allowed; }

        .btn-add-option { background: var(--bg-body); color: var(--primary); border: 1px dashed var(--primary-light); padding: 10px 20px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; margin-top: 10px; }
        .btn-add-option:hover { background: var(--primary-bg); border-style: solid; }

        /* True/False Radio */
        .tf-group { display: flex; gap: 20px; }
        .tf-label { flex: 1; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 15px; border: 2px solid var(--border); border-radius: 12px; background: white; cursor: pointer; transition: 0.2s; font-weight: 700; font-size: 14px; }
        .tf-label input { display: none; }
        .tf-label.active-true { border-color: var(--success); background: var(--success-bg); color: var(--success); }
        .tf-label.active-false { border-color: var(--danger); background: var(--danger-bg); color: var(--danger); }

        /* Buttons */
        .action-row { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; border-top: 1px solid var(--border); padding-top: 25px; }
        .btn { padding: 14px 28px; border-radius: 12px; font-weight: 700; font-size: 14px; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; border: none; font-family: inherit; }
        .btn-cancel { background: white; color: var(--text-dark); border: 1px solid var(--border); text-decoration: none; }
        .btn-cancel:hover { background: var(--bg-body); border-color: #94A3B8; }
        .btn-submit { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(29, 78, 216, 0.2); }
        .btn-submit:hover { background: #1e3a8a; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(29, 78, 216, 0.3); }

        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .btn-bulk { width: 100%; justify-content: center; }
            .form-grid { grid-template-columns: 1fr; gap: 15px; }
            .action-row { flex-direction: column-reverse; }
            .btn { width: 100%; justify-content: center; }
            .form-card { padding: 25px; }
            .top-nav { padding: 15px 20px; }
            .option-row { flex-wrap: wrap; }
            .correct-toggle { border-right: none; flex: 1; }
        }
    </style>
</head>
<body>

    <div class="ambient-bg">
        <div class="shape cube"></div>
        <div class="shape ring"></div>
        <div class="shape pyramid"></div>
    </div>

    <div class="navbar-wrapper">
        <nav class="top-nav">
            <div class="nav-left">
                <a href="manage-questions.php" class="btn-back"><i class="fas fa-arrow-left"></i> Question Bank</a>
                <div class="brand-text">
                    <h2>Content Creator</h2>
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
    </div>

    <div class="container">
        <div class="page-header">
            <div class="header-text">
                <h1><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Add New Question</h1>
                <p>Create a single question to add to the module repository.</p>
            </div>
            <a href="bulk-upload-questions.php" class="btn-bulk">
                <i class="fas fa-file-csv"></i> Bulk Import (CSV)
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <div style="display:flex; align-items:center; gap: 10px;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <div style="display:flex; gap: 15px; font-size: 13px;">
                    <a href="add-question.php" style="color:var(--success); text-decoration:underline;">Add Another</a>
                    <a href="manage-questions.php" style="color:var(--success); text-decoration:underline;">View Bank</a>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" id="questionForm" class="form-card">
            
            <div class="info-box">
                <i class="fas fa-lightbulb"></i>
                For MCQ type, ensure you check the box next to the correct answer(s). Multiple correct answers are supported.
            </div>

            <div class="section-title"><i class="fas fa-tags"></i> Classification</div>
            <div class="form-grid">
                
                <div class="form-group">
                    <label>Course / Program Category *</label>
                    <div class="input-wrap">
                        <select id="parentCategory" class="form-control" required onchange="updateModules()">
                            <option value="">Select a Course Category...</option>
                            <?php foreach(array_keys($course_groups) as $group_name): ?>
                                <option value="<?php echo htmlspecialchars($group_name); ?>"><?php echo htmlspecialchars($group_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-folder input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Specific Module *</label>
                    <div class="input-wrap">
                        <select name="category_id" id="moduleId" class="form-control" required disabled>
                            <option value="">Select Course Category first...</option>
                        </select>
                        <i class="fas fa-book input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Question Format *</label>
                    <div class="input-wrap">
                        <select name="question_type" id="questionType" class="form-control" required onchange="toggleOptions()">
                            <option value="mcq">Multiple Choice (MCQ)</option>
                            <option value="true_false">True / False</option>
                        </select>
                        <i class="fas fa-list-ul input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Difficulty Rating *</label>
                    <div class="input-wrap">
                        <select name="difficulty_level" class="form-control" required>
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                        <i class="fas fa-signal input-icon"></i>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Allocated Marks *</label>
                    <div class="input-wrap">
                        <input type="number" name="marks" class="form-control" step="0.5" min="0.5" value="1" required style="padding-left: 42px;">
                        <i class="fas fa-star input-icon"></i>
                    </div>
                </div>
            </div>

            <div class="section-title"><i class="fas fa-pen-nib"></i> Question Content</div>
            <div class="form-group full-width">
                <label>Question Text *</label>
                <textarea name="question_text" class="form-control" required placeholder="Type the main question here..."><?php echo isset($_POST['question_text']) ? htmlspecialchars($_POST['question_text']) : ''; ?></textarea>
            </div>

            <div id="mcqOptions" class="options-section">
                <div class="options-header">
                    <div>
                        <h3>Answer Choices</h3>
                        <p>Provide options and flag the correct one(s).</p>
                    </div>
                </div>
                <div id="optionsContainer"></div>
                <button type="button" class="btn-add-option" onclick="addOption()">
                    <i class="fas fa-plus"></i> Add Another Option
                </button>
            </div>

            <div id="trueFalseOptions" class="options-section" style="display: none;">
                <div class="options-header">
                    <div>
                        <h3>Logical Answer</h3>
                        <p>Select the correct boolean value.</p>
                    </div>
                </div>
                <div class="tf-group">
                    <label class="tf-label active-true" id="lbl-true">
                        <input type="radio" name="tf_correct" value="true" checked onchange="updateTFToggle()">
                        <i class="fas fa-check-circle"></i> True
                    </label>
                    <label class="tf-label" id="lbl-false">
                        <input type="radio" name="tf_correct" value="false" onchange="updateTFToggle()">
                        <i class="fas fa-times-circle"></i> False
                    </label>
                </div>
            </div>

            <div class="form-group full-width" style="margin-top: 10px;">
                <label>Solution Explanation (Optional)</label>
                <textarea name="explanation" class="form-control" style="min-height: 80px;" placeholder="Provide a rationale for the correct answer. This can be shown to candidates after the exam."></textarea>
            </div>

            <div class="action-row">
                <a href="manage-questions.php" class="btn btn-cancel"><i class="fas fa-times"></i> Discard</a>
                <button type="submit" class="btn btn-submit"><i class="fas fa-save"></i> Save Question to Module</button>
            </div>
        </form>
    </div>

    <script>
        const categoryData = <?php echo json_encode($course_groups); ?>;
        let optionCount = 0;

        // --- NEW: Handle Dynamic Two-Step Dropdown ---
        function updateModules() {
            const parentSelect = document.getElementById('parentCategory');
            const modSelect = document.getElementById('moduleId');
            const selectedCat = parentSelect.value;
            
            // Clear existing
            modSelect.innerHTML = '<option value="">Select specific module...</option>';
            
            if(selectedCat && categoryData[selectedCat]) {
                categoryData[selectedCat].forEach(mod => {
                    const opt = document.createElement('option');
                    opt.value = mod.id;
                    opt.textContent = mod.category_code + ' - ' + mod.category_name;
                    modSelect.appendChild(opt);
                });
                modSelect.disabled = false;
                modSelect.style.backgroundColor = "var(--bg-body)";
            } else {
                modSelect.disabled = true;
                modSelect.style.backgroundColor = "#F1F5F9";
            }
        }

        // --- Existing Question Logic ---
        function toggleOptions() {
            const type = document.getElementById('questionType').value;
            document.getElementById('mcqOptions').style.display = type === 'mcq' ? 'block' : 'none';
            document.getElementById('trueFalseOptions').style.display = type === 'true_false' ? 'block' : 'none';
            
            if (type === 'mcq' && optionCount === 0) {
                for (let i = 0; i < 4; i++) addOption();
            }
        }

        function updateTFToggle() {
            const isTrue = document.querySelector('input[name="tf_correct"][value="true"]').checked;
            const lblTrue = document.getElementById('lbl-true');
            const lblFalse = document.getElementById('lbl-false');

            if(isTrue) {
                lblTrue.className = 'tf-label active-true';
                lblFalse.className = 'tf-label';
            } else {
                lblTrue.className = 'tf-label';
                lblFalse.className = 'tf-label active-false';
            }
        }

        function addOption() {
            optionCount++;
            const container = document.getElementById('optionsContainer');
            const div = document.createElement('div');
            div.className = 'option-row';
            div.id = `option-${optionCount}`;
            
            div.innerHTML = `
                <div style="display:flex; align-items:center; justify-content:center; width: 24px; font-weight:800; color:var(--border);">${String.fromCharCode(64 + optionCount)}.</div>
                <input type="text" name="options[${optionCount}][text]" placeholder="Enter option text..." required>
                <label class="correct-toggle">
                    <input type="checkbox" name="options[${optionCount}][is_correct]">
                    <div class="check-indicator"><i class="fas fa-check"></i></div>
                    <span>Correct</span>
                </label>
                <button type="button" class="btn-remove-opt" onclick="removeOption(${optionCount})" title="Remove Option" ${optionCount <= 2 ? 'disabled' : ''}>
                    <i class="fas fa-trash-alt"></i>
                </button>
            `;
            container.appendChild(div);
            renumberOptions();
        }

        function removeOption(id) {
            if (optionCount > 2) {
                const element = document.getElementById(`option-${id}`);
                if (element) {
                    element.remove();
                    optionCount--;
                    renumberOptions();
                }
            } else {
                alert('A multiple choice question requires at least 2 options.');
            }
        }

        function renumberOptions() {
            const container = document.getElementById('optionsContainer');
            const rows = container.children;
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const newNum = i + 1;
                const letter = String.fromCharCode(64 + newNum);
                
                row.firstElementChild.textContent = letter + '.';
                
                const input = row.querySelector('input[type="text"]');
                input.name = `options[${newNum}][text]`;
                
                const checkbox = row.querySelector('input[type="checkbox"]');
                checkbox.name = `options[${newNum}][is_correct]`;
                
                const removeBtn = row.querySelector('.btn-remove-opt');
                removeBtn.setAttribute('onclick', `removeOption(${newNum})`);
                row.id = `option-${newNum}`;
                
                if (newNum <= 2) {
                    removeBtn.disabled = true;
                } else {
                    removeBtn.disabled = false;
                }
            }
        }

        window.onload = function() {
            toggleOptions();
            
            // Check if we need to auto-restore selection after a failed form submission
            const preselectedModuleId = "<?php echo isset($_POST['category_id']) ? $_POST['category_id'] : ''; ?>";
            if(preselectedModuleId) {
                // Find parent category
                for(const [catName, modules] of Object.entries(categoryData)) {
                    if(modules.some(m => m.id == preselectedModuleId)) {
                        document.getElementById('parentCategory').value = catName;
                        updateModules();
                        document.getElementById('moduleId').value = preselectedModuleId;
                        break;
                    }
                }
            }
        };

        // Validation
        document.getElementById('questionForm')?.addEventListener('submit', function(e) {
            const type = document.getElementById('questionType').value;
            
            if (type === 'mcq') {
                const checkboxes = document.querySelectorAll('#optionsContainer input[type="checkbox"]');
                let hasCorrect = false;
                checkboxes.forEach(cb => { if (cb.checked) hasCorrect = true; });
                
                if (!hasCorrect) {
                    e.preventDefault();
                    alert('Please select at least one correct answer by checking the "Correct" box next to an option.');
                    return;
                }
                
                const textInputs = document.querySelectorAll('#optionsContainer input[type="text"]');
                let allFilled = true;
                textInputs.forEach(input => { if (input.value.trim() === '') allFilled = false; });
                
                if (!allFilled) {
                    e.preventDefault();
                    alert('Please fill in the text for all provided options.');
                }
            }
        });
    </script>
</body>
</html>