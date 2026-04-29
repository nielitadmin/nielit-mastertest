<?php
session_name('NIELIT_COORD_SESSION'); 
session_start();

// Check if user is logged in and is a coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'coordinator') {
    header("Location: coordinator-login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage-questions.php");
    exit();
}

$question_id = $_GET['id'];
require_once __DIR__ . '/../../config/database.php';

$error = '';
$success = '';

try {
    // 1. Fetch the Question Data
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$question) {
        header("Location: manage-questions.php");
        exit();
    }

    // 2. Fetch the Options
    $stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY option_order ASC");
    $stmt->execute([$question_id]);
    $existing_options = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Categories & Chapters for the Dropdowns
    $categories = $pdo->query("SELECT id, category_code, category_name, chapter_count FROM exam_categories ORDER BY category_code ASC")->fetchAll(PDO::FETCH_ASSOC);

    $course_groups = [
        'O-Level Modules' => [],
        'A-Level Modules' => [],
        'B/C-Level Modules' => [],
        'Short Term / CCC' => [],
        'Other Modules' => []
    ];

    $parent_category_of_question = '';

    foreach ($categories as $cat) {
        $code = strtoupper($cat['category_code']);
        $name = strtoupper($cat['category_name']);
        
        $group_key = 'Other Modules';
        if (preg_match('/^M[1-4]-R/i', $code) || strpos($name, 'O LEVEL') !== false) {
            $group_key = 'O-Level Modules';
        } elseif (preg_match('/^A[0-9\.]+-R/i', $code) || strpos($name, 'A LEVEL') !== false) {
            $group_key = 'A-Level Modules';
        } elseif (preg_match('/^[BC][1-9]-/i', $code)) {
            $group_key = 'B/C-Level Modules';
        } elseif (strpos($code, 'CCC') !== false || strpos($code, 'BCC') !== false || strpos($name, 'COMPUTER CONCEPTS') !== false) {
            $group_key = 'Short Term / CCC';
        }

        $course_groups[$group_key][] = $cat;

        // Figure out which parent group this question belongs to (for auto-selecting the dropdown)
        if ($cat['id'] == $question['category_id']) {
            $parent_category_of_question = $group_key;
        }
    }
    $course_groups = array_filter($course_groups, function($val) { return count($val) > 0; });

    // 4. Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $category_id = $_POST['category_id'];
        $chapter_number = !empty($_POST['chapter_number']) ? $_POST['chapter_number'] : null;
        $question_text = trim($_POST['question_text']);
        $question_type = $_POST['question_type'];
        $difficulty_level = $_POST['difficulty_level'];
        $marks = floatval($_POST['marks']);
        $explanation = trim($_POST['explanation'] ?? '');
        
        $errors = [];
        if (empty($category_id)) $errors[] = "Please select a specific Module.";
        if (empty($question_text)) $errors[] = "Question text is required.";
        if ($marks <= 0) $errors[] = "Marks must be greater than 0.";
        
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
            
            // Update Question
            $stmt = $pdo->prepare("
                UPDATE questions 
                SET category_id=?, chapter_number=?, question_text=?, question_type=?, difficulty_level=?, marks=?, explanation=?
                WHERE id=?
            ");
            $stmt->execute([$category_id, $chapter_number, $question_text, $question_type, $difficulty_level, $marks, $explanation, $question_id]);
            
            // Delete old options (safest way to handle dynamically removed/added options)
            $pdo->prepare("DELETE FROM question_options WHERE question_id=?")->execute([$question_id]);
            
            // Re-insert options
            if ($question_type == 'mcq' && isset($_POST['options'])) {
                $opt_stmt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct, option_order) VALUES (?, ?, ?, ?)");
                $order = 1;
                foreach ($_POST['options'] as $option) {
                    if (!empty(trim($option['text'] ?? ''))) {
                        $is_correct = (isset($option['is_correct']) && $option['is_correct'] === 'on') ? 1 : 0;
                        $opt_stmt->execute([$question_id, trim($option['text']), $is_correct, $order]);
                        $order++;
                    }
                }
            } elseif ($question_type == 'true_false') {
                $tf_correct = $_POST['tf_correct'] ?? 'true';
                $opt_stmt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct, option_order) VALUES (?, ?, ?, ?)");
                $opt_stmt->execute([$question_id, 'True', ($tf_correct == 'true') ? 1 : 0, 1]);
                $opt_stmt->execute([$question_id, 'False', ($tf_correct == 'false') ? 1 : 0, 2]);
            }
            
            $pdo->commit();
            header("Location: manage-questions.php?msg=success_edit");
            exit();
        } else {
            $error = implode("<br>", $errors);
        }
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $error = "System Database Error. Please try again later. " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Question - NIELIT Coordinator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #7C3AED;        --primary-light: #8B5CF6;  --primary-bg: #EDE9FE;     
            --secondary: #0F172A;
            --success: #059669;        --success-bg: #D1FAE5;
            --danger: #DC2626;         --danger-bg: #FEE2E2;
            --text-dark: #0F172A;      --text-muted: #64748B;
            --bg-body: #F8FAFC;        --surface: #FFFFFF;
            --border: #E2E8F0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 40px -10px rgba(124, 58, 237, 0.1);
            --radius-md: 12px;         --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; overflow-x: hidden; padding-bottom: 60px; }

        .ambient-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; overflow: hidden; pointer-events: none; background: linear-gradient(180deg, #F8FAFC 0%, #E2E8F0 100%); }
        .orb { position: absolute; border-radius: 50%; filter: blur(90px); opacity: 0.6; animation: float-orb 20s infinite alternate; }
        .orb-1 { width: 600px; height: 600px; background: linear-gradient(135deg, #DDD6FE, #A78BFA); top: -10%; left: -10%; }
        @keyframes float-orb { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(100px, 50px) scale(1.1); } }

        .navbar-wrapper { position: sticky; top: 0; z-index: 1000; background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 4px 30px -10px rgba(0,0,0,0.05); }
        .top-nav { padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--bg-body); border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s; }
        .btn-back:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); }
        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--primary); line-height: 1.2; }
        .brand-text span { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .user-info { font-size: 14px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }

        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; position: relative; z-index: 10; animation: fadeUp 0.5s ease-out;}
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .header-text h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); margin-bottom: 5px; display: flex; align-items: center; gap: 10px; }
        .header-text p { color: var(--text-muted); font-size: 14px; font-weight: 500; }

        .alert { padding: 16px 20px; border-radius: var(--radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; border: 1px solid transparent; }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: #FECACA; }

        .form-card { background: rgba(255,255,255,0.9); backdrop-filter: blur(16px); border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-lg); padding: 40px; }
        
        .section-title { font-size: 13px; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--border); display: flex; align-items: center; gap: 8px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group.two-thirds { grid-column: span 2; }

        .form-group label { display: block; font-size: 13px; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 16px; top: 20px; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; pointer-events: none; transition: 0.3s; }
        
        .form-control { width: 100%; padding: 12px 16px 12px 42px; border-radius: 12px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-dark); font-family: inherit; font-size: 14px; font-weight: 500; transition: all 0.3s; outline: none; appearance: none; }
        textarea.form-control { padding: 16px; min-height: 120px; resize: vertical; line-height: 1.5; }
        select.form-control { cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; padding-right: 40px; }
        select:disabled { background-color: #F1F5F9; color: #94A3B8; cursor: not-allowed; }
        
        .form-control:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px var(--primary-bg); }
        .form-control:focus + .input-icon, .input-wrap:focus-within .input-icon { color: var(--primary); }

        .options-section { background: #F8FAFC; border: 1px dashed var(--border); padding: 25px; border-radius: var(--radius-md); margin-bottom: 30px; }
        .options-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .options-header h3 { font-size: 15px; font-weight: 700; color: var(--text-dark); }
        .options-header p { font-size: 12px; color: var(--text-muted); }
        
        .option-row { display: flex; align-items: center; gap: 15px; margin-bottom: 12px; background: white; padding: 12px 15px; border-radius: 12px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: 0.2s; }
        .option-row:focus-within { border-color: var(--primary-light); box-shadow: 0 0 0 3px var(--primary-bg); }
        .option-row input[type="text"] { flex: 1; border: none; outline: none; font-family: inherit; font-size: 14px; font-weight: 500; }
        
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

        .tf-group { display: flex; gap: 20px; }
        .tf-label { flex: 1; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 15px; border: 2px solid var(--border); border-radius: 12px; background: white; cursor: pointer; transition: 0.2s; font-weight: 700; font-size: 14px; }
        .tf-label input { display: none; }
        .tf-label.active-true { border-color: var(--success); background: var(--success-bg); color: var(--success); }
        .tf-label.active-false { border-color: var(--danger); background: var(--danger-bg); color: var(--danger); }

        .action-row { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; border-top: 1px solid var(--border); padding-top: 25px; }
        .btn { padding: 14px 28px; border-radius: 12px; font-weight: 700; font-size: 14px; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; border: none; font-family: inherit; }
        .btn-cancel { background: white; color: var(--text-dark); border: 1px solid var(--border); text-decoration: none; }
        .btn-cancel:hover { background: var(--bg-body); border-color: #94A3B8; }
        .btn-submit { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.2); }
        .btn-submit:hover { background: #6D28D9; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3); }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.two-thirds { grid-column: 1 / -1; }
            .action-row { flex-direction: column-reverse; }
            .btn { width: 100%; justify-content: center; }
            .form-card { padding: 25px; }
        }
    </style>
</head>
<body>

    <div class="ambient-bg">
        <div class="orb orb-1"></div>
    </div>

    <div class="navbar-wrapper">
        <nav class="top-nav">
            <div class="nav-left">
                <a href="manage-questions.php" class="btn-back"><i class="fas fa-arrow-left"></i> Cancel</a>
                <div class="brand-text">
                    <h2>Content Creator</h2>
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
    </div>

    <div class="container">
        <div class="page-header">
            <div class="header-text">
                <h1><i class="fas fa-pen" style="color: var(--primary);"></i> Edit Question #<?php echo $question_id; ?></h1>
                <p>Modify text, update chapters, or fix options.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" id="questionForm" class="form-card">
            
            <div class="section-title"><i class="fas fa-tags"></i> Classification</div>
            <div class="form-grid">
                
                <div class="form-group">
                    <label>Course / Program Category *</label>
                    <div class="input-wrap">
                        <select id="parentCategory" class="form-control" required onchange="updateModules()">
                            <option value="">Select a Course Category...</option>
                            <?php foreach(array_keys($course_groups) as $group_name): ?>
                                <option value="<?php echo htmlspecialchars($group_name); ?>" <?php echo $parent_category_of_question === $group_name ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($group_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-folder input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Specific Module *</label>
                    <div class="input-wrap">
                        <select name="category_id" id="moduleId" class="form-control" required onchange="updateChapters()">
                            <option value="">Select Course Category first...</option>
                        </select>
                        <i class="fas fa-book input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Assign to Chapter (Optional)</label>
                    <div class="input-wrap">
                        <select name="chapter_number" id="chapterId" class="form-control">
                            <option value="">General (No Chapter)</option>
                        </select>
                        <i class="fas fa-bookmark input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Question Format *</label>
                    <div class="input-wrap">
                        <select name="question_type" id="questionType" class="form-control" required onchange="toggleOptions()">
                            <option value="mcq" <?php echo $question['question_type'] == 'mcq' ? 'selected' : ''; ?>>Multiple Choice (MCQ)</option>
                            <option value="true_false" <?php echo $question['question_type'] == 'true_false' ? 'selected' : ''; ?>>True / False</option>
                        </select>
                        <i class="fas fa-list-ul input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Difficulty Rating *</label>
                    <div class="input-wrap">
                        <select name="difficulty_level" class="form-control" required>
                            <option value="easy" <?php echo $question['difficulty_level'] == 'easy' ? 'selected' : ''; ?>>Easy</option>
                            <option value="medium" <?php echo $question['difficulty_level'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="hard" <?php echo $question['difficulty_level'] == 'hard' ? 'selected' : ''; ?>>Hard</option>
                        </select>
                        <i class="fas fa-signal input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Allocated Marks *</label>
                    <div class="input-wrap">
                        <input type="number" name="marks" class="form-control" step="0.5" min="0.5" value="<?php echo htmlspecialchars($question['marks']); ?>" required style="padding-left: 42px;">
                        <i class="fas fa-star input-icon"></i>
                    </div>
                </div>
            </div>

            <div class="section-title"><i class="fas fa-pen-nib"></i> Question Content</div>
            <div class="form-group full-width">
                <label>Question Text *</label>
                <textarea name="question_text" class="form-control" required placeholder="Type the main question here..."><?php echo htmlspecialchars($question['question_text']); ?></textarea>
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
                <?php 
                    $is_true_correct = true; // default
                    if ($question['question_type'] == 'true_false') {
                        foreach ($existing_options as $opt) {
                            if ($opt['option_text'] == 'True' && $opt['is_correct']) $is_true_correct = true;
                            if ($opt['option_text'] == 'False' && $opt['is_correct']) $is_true_correct = false;
                        }
                    }
                ?>
                <div class="tf-group">
                    <label class="tf-label <?php echo $is_true_correct ? 'active-true' : ''; ?>" id="lbl-true">
                        <input type="radio" name="tf_correct" value="true" <?php echo $is_true_correct ? 'checked' : ''; ?> onchange="updateTFToggle()">
                        <i class="fas fa-check-circle"></i> True
                    </label>
                    <label class="tf-label <?php echo !$is_true_correct ? 'active-false' : ''; ?>" id="lbl-false">
                        <input type="radio" name="tf_correct" value="false" <?php echo !$is_true_correct ? 'checked' : ''; ?> onchange="updateTFToggle()">
                        <i class="fas fa-times-circle"></i> False
                    </label>
                </div>
            </div>

            <div class="form-group full-width" style="margin-top: 10px;">
                <label>Solution Explanation (Optional)</label>
                <textarea name="explanation" class="form-control" style="min-height: 80px;" placeholder="Provide a rationale for the correct answer."><?php echo htmlspecialchars($question['explanation']); ?></textarea>
            </div>

            <div class="action-row">
                <a href="manage-questions.php" class="btn btn-cancel"><i class="fas fa-times"></i> Cancel Updates</a>
                <button type="submit" class="btn btn-submit"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>

    <script>
        const categoryData = <?php echo json_encode($course_groups); ?>;
        const currentModuleId = "<?php echo $question['category_id']; ?>";
        const currentChapterId = "<?php echo $question['chapter_number'] ?? ''; ?>";
        
        let optionCount = 0;

        // 🟢 LOAD EXISTING OPTIONS FROM PHP TO JS
        const existingOptions = <?php echo json_encode($existing_options); ?>;

        function updateModules() {
            const parentSelect = document.getElementById('parentCategory');
            const modSelect = document.getElementById('moduleId');
            const selectedCat = parentSelect.value;
            
            modSelect.innerHTML = '<option value="">Select specific module...</option>';
            
            if(selectedCat && categoryData[selectedCat]) {
                categoryData[selectedCat].forEach(mod => {
                    const opt = document.createElement('option');
                    opt.value = mod.id;
                    opt.textContent = mod.category_code + ' - ' + mod.category_name;
                    // Add chapter count as a data attribute
                    opt.dataset.chapters = mod.chapter_count || 1;
                    if(mod.id == currentModuleId) opt.selected = true;
                    modSelect.appendChild(opt);
                });
                modSelect.disabled = false;
                modSelect.style.backgroundColor = "var(--bg-body)";
            } else {
                modSelect.disabled = true;
                modSelect.style.backgroundColor = "#F1F5F9";
            }
            updateChapters();
        }

        // 🟢 NEW: Update Chapter Dropdown based on Module's Chapter Count
        function updateChapters() {
            const modSelect = document.getElementById('moduleId');
            const chapSelect = document.getElementById('chapterId');
            
            chapSelect.innerHTML = '<option value="">General (No Chapter)</option>';
            
            if (modSelect.selectedIndex > 0) {
                const selectedOption = modSelect.options[modSelect.selectedIndex];
                const totalChapters = parseInt(selectedOption.dataset.chapters) || 1;
                
                for (let i = 1; i <= totalChapters; i++) {
                    const opt = document.createElement('option');
                    opt.value = i;
                    opt.textContent = "Chapter " + i;
                    if(i == currentChapterId) opt.selected = true;
                    chapSelect.appendChild(opt);
                }
                chapSelect.disabled = false;
                chapSelect.style.backgroundColor = "var(--bg-body)";
            } else {
                chapSelect.disabled = true;
                chapSelect.style.backgroundColor = "#F1F5F9";
            }
        }

        function toggleOptions() {
            const type = document.getElementById('questionType').value;
            document.getElementById('mcqOptions').style.display = type === 'mcq' ? 'block' : 'none';
            document.getElementById('trueFalseOptions').style.display = type === 'true_false' ? 'block' : 'none';
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

        function addOption(text = '', isCorrect = false) {
            optionCount++;
            const container = document.getElementById('optionsContainer');
            const div = document.createElement('div');
            div.className = 'option-row';
            div.id = `option-${optionCount}`;
            
            div.innerHTML = `
                <div style="display:flex; align-items:center; justify-content:center; width: 24px; font-weight:800; color:var(--border);">${String.fromCharCode(64 + optionCount)}.</div>
                <input type="text" name="options[${optionCount}][text]" placeholder="Enter option text..." value="${text.replace(/"/g, '&quot;')}" required>
                <label class="correct-toggle">
                    <input type="checkbox" name="options[${optionCount}][is_correct]" ${isCorrect ? 'checked' : ''}>
                    <div class="check-indicator"><i class="fas fa-check"></i></div>
                    <span>Correct</span>
                </label>
                <button type="button" class="btn-remove-opt" onclick="removeOption(${optionCount})" title="Remove Option">
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
                
                if (newNum <= 2) removeBtn.disabled = true;
                else removeBtn.disabled = false;
            }
        }

        window.onload = function() {
            // Trigger cascading dropdowns
            if (document.getElementById('parentCategory').value) {
                updateModules();
            }

            toggleOptions();
            
            // Render existing options if it's MCQ
            if (document.getElementById('questionType').value === 'mcq') {
                if (existingOptions.length > 0) {
                    existingOptions.forEach(opt => {
                        addOption(opt.option_text, opt.is_correct == 1 || opt.is_correct === true);
                    });
                } else {
                    for (let i = 0; i < 4; i++) addOption();
                }
            } else {
                // Preload 4 empty if they switch to MCQ later
                for (let i = 0; i < 4; i++) addOption();
            }
        };

        // Form Validation Before Save
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
            }
        });
    </script>
</body>
</html>