<?php
session_name('NIELIT_COORD_SESSION'); 
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
    // --- Add New Module (Category) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_module'])) {
        $mod_code = strtoupper(trim($_POST['module_code']));
        $mod_name = trim($_POST['module_name']);
        $mod_duration = (int)$_POST['duration_minutes'];
        $mod_marks = (int)$_POST['total_marks']; 
        $mod_chapters = (int)$_POST['chapter_count']; 
        
        if (!empty($mod_code) && !empty($mod_name)) {
            try {
                $check = $pdo->prepare("SELECT id FROM exam_categories WHERE category_code = ?");
                $check->execute([$mod_code]);
                if ($check->fetch()) {
                    $error = "A module with the code '$mod_code' already exists.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO exam_categories (category_code, category_name, duration_minutes, total_marks, chapter_count) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$mod_code, $mod_name, $mod_duration, $mod_marks, $mod_chapters]);
                    header("Location: manage-questions.php?msg=module_created");
                    exit();
                }
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        } else {
            $error = "Module Code and Name are required.";
        }
    }

    // --- 🟢 NEW: Edit Existing Module ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_module'])) {
        $cat_id = (int)$_POST['edit_module_id'];
        $mod_code = strtoupper(trim($_POST['edit_module_code']));
        $mod_name = trim($_POST['edit_module_name']);
        $mod_duration = (int)$_POST['edit_duration_minutes'];
        $mod_marks = (int)$_POST['edit_total_marks']; 
        $mod_chapters = (int)$_POST['edit_chapter_count']; 
        
        if (!empty($mod_code) && !empty($mod_name) && $cat_id > 0) {
            try {
                // Check for duplicates (excluding the current module being edited)
                $check = $pdo->prepare("SELECT id FROM exam_categories WHERE category_code = ? AND id != ?");
                $check->execute([$mod_code, $cat_id]);
                if ($check->fetch()) {
                    $error = "Another module with the code '$mod_code' already exists.";
                } else {
                    $stmt = $pdo->prepare("UPDATE exam_categories SET category_code=?, category_name=?, duration_minutes=?, total_marks=?, chapter_count=? WHERE id=?");
                    $stmt->execute([$mod_code, $mod_name, $mod_duration, $mod_marks, $mod_chapters, $cat_id]);
                    header("Location: manage-questions.php?msg=module_updated");
                    exit();
                }
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        } else {
            $error = "Module Code and Name are required.";
        }
    }

    // --- Delete Entire Module (Folder) ---
    if (isset($_GET['delete_module'])) {
        $cat_id = $_GET['delete_module'];
        try {
            $pdo->beginTransaction();
            if ($cat_id === 'null') {
                $pdo->prepare("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE category_id IS NULL)")->execute();
                $pdo->prepare("DELETE FROM candidate_responses WHERE question_id IN (SELECT id FROM questions WHERE category_id IS NULL)")->execute();
                $pdo->prepare("DELETE FROM questions WHERE category_id IS NULL")->execute();
            } else {
                $pdo->prepare("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE category_id = ?)")->execute([$cat_id]);
                $pdo->prepare("DELETE FROM candidate_responses WHERE question_id IN (SELECT id FROM questions WHERE category_id = ?)")->execute([$cat_id]);
                $pdo->prepare("DELETE FROM questions WHERE category_id = ?")->execute([$cat_id]);
                $pdo->prepare("DELETE FROM candidate_responses WHERE registration_id IN (SELECT id FROM exam_registrations WHERE session_id IN (SELECT id FROM exam_sessions WHERE category_id = ?))")->execute([$cat_id]);
                $pdo->prepare("DELETE FROM exam_results WHERE registration_id IN (SELECT id FROM exam_registrations WHERE session_id IN (SELECT id FROM exam_sessions WHERE category_id = ?))")->execute([$cat_id]);
                $pdo->prepare("DELETE FROM exam_registrations WHERE session_id IN (SELECT id FROM exam_sessions WHERE category_id = ?)")->execute([$cat_id]);
                $pdo->prepare("DELETE FROM exam_sessions WHERE category_id = ?")->execute([$cat_id]);
                $pdo->prepare("DELETE FROM exam_categories WHERE id = ?")->execute([$cat_id]);
            }
            $pdo->commit();
            header("Location: manage-questions.php?msg=module_deleted");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            header("Location: manage-questions.php?msg=module_deactivated&err=" . urlencode($e->getMessage()));
            exit();
        }
    }

    // Handle single question deletion (soft delete)
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        $stmt = $pdo->prepare("UPDATE questions SET is_active = false WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        header("Location: manage-questions.php?msg=deactivated");
        exit();
    }

    // Handle single question activation
    if (isset($_GET['activate']) && is_numeric($_GET['activate'])) {
        $stmt = $pdo->prepare("UPDATE questions SET is_active = true WHERE id = ?");
        $stmt->execute([$_GET['activate']]);
        header("Location: manage-questions.php?msg=activated");
        exit();
    }

    // Handle Messages from Redirects
    $warning = '';
    if (isset($_GET['msg'])) {
        if ($_GET['msg'] === 'deactivated') $message = "Question deactivated successfully.";
        if ($_GET['msg'] === 'activated') $message = "Question activated successfully.";
        if ($_GET['msg'] === 'module_deleted') $message = "✅ Success: Module and all its contents were permanently deleted.";
        if ($_GET['msg'] === 'module_created') $message = "✅ Success: New module with chapters created successfully!";
        if ($_GET['msg'] === 'module_updated') $message = "✅ Success: Module settings updated successfully!";
        if ($_GET['msg'] === 'module_deactivated') {
            $warning = "⚠️ Warning: Database blocked the deletion. Error: " . htmlspecialchars($_GET['err'] ?? 'Unknown constraint');
        }
    }

    // Get filter parameters
    $category_filter = isset($_GET['category']) ? $_GET['category'] : '';
    $difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';

    // Build query with filters
    $query = "
        SELECT 
            q.*,
            ec.category_name,
            ec.category_code,
            (SELECT COUNT(*) FROM question_options WHERE question_id = q.id) as option_count
        FROM questions q
        LEFT JOIN exam_categories ec ON q.category_id = ec.id
        WHERE 1=1
    ";
    
    $params = [];
    if (!empty($category_filter)) {
        $query .= " AND q.category_id = ?";
        $params[] = $category_filter;
    }
    if (!empty($difficulty_filter)) {
        $query .= " AND q.difficulty_level = ?";
        $params[] = $difficulty_filter;
    }
    $query .= " ORDER BY ec.category_code ASC, q.id DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $all_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // --- SMART CATEGORIZATION ENGINE ---
    $course_groups = [
        'O-Level' => ['title' => 'O-Level Modules', 'icon' => 'fa-laptop-code', 'theme' => 'blue', 'modules' => [], 'q_count' => 0],
        'A-Level' => ['title' => 'A-Level Modules', 'icon' => 'fa-microchip', 'theme' => 'indigo', 'modules' => [], 'q_count' => 0],
        'BC-Level' => ['title' => 'B/C-Level Modules', 'icon' => 'fa-server', 'theme' => 'teal', 'modules' => [], 'q_count' => 0],
        'CCC' => ['title' => 'Short Term / CCC', 'icon' => 'fa-certificate', 'theme' => 'warning', 'modules' => [], 'q_count' => 0],
        'Other' => ['title' => 'Other Modules', 'icon' => 'fa-folder-open', 'theme' => 'gray', 'modules' => [], 'q_count' => 0]
    ];

    // 🟢 Fetch duration and marks as well so they can be passed to the edit modal
    $categories = $pdo->query("SELECT id, category_code, category_name, duration_minutes, total_marks, chapter_count FROM exam_categories ORDER BY category_code ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($categories as $cat) {
        $code = strtoupper($cat['category_code'] ?? '');
        $name = strtoupper($cat['category_name'] ?? '');
        
        $parent_key = 'Other';
        if (preg_match('/^M[1-4]-R/i', $code) || strpos($name, 'O LEVEL') !== false) {
            $parent_key = 'O-Level';
        } elseif (preg_match('/^A[0-9\.]+-R/i', $code) || strpos($name, 'A LEVEL') !== false) {
            $parent_key = 'A-Level';
        } elseif (preg_match('/^[BC][1-9]-/i', $code)) {
            $parent_key = 'BC-Level';
        } elseif (strpos($code, 'CCC') !== false || strpos($code, 'BCC') !== false || strpos($name, 'COMPUTER CONCEPTS') !== false) {
            $parent_key = 'CCC';
        }

        $module_name = $cat['category_code'] . ': ' . $cat['category_name'];
        $course_groups[$parent_key]['modules'][$module_name] = [
            'cat_id' => $cat['id'],
            'cat_code' => $cat['category_code'],
            'cat_name' => $cat['category_name'],
            'duration' => $cat['duration_minutes'],
            'marks' => $cat['total_marks'],
            'chapter_count' => $cat['chapter_count'] ?? 1, 
            'chapters' => [],
            'unassigned' => [] 
        ];
    }

    // Now populate the questions into their respective folders and CHAPTERS
    foreach ($all_questions as $q) {
        $code = strtoupper($q['category_code'] ?? '');
        $name = strtoupper($q['category_name'] ?? '');
        
        $parent_key = 'Other';
        if (preg_match('/^M[1-4]-R/i', $code) || strpos($name, 'O LEVEL') !== false) {
            $parent_key = 'O-Level';
        } elseif (preg_match('/^A[0-9\.]+-R/i', $code) || strpos($name, 'A LEVEL') !== false) {
            $parent_key = 'A-Level';
        } elseif (preg_match('/^[BC][1-9]-/i', $code)) {
            $parent_key = 'BC-Level';
        } elseif (strpos($code, 'CCC') !== false || strpos($code, 'BCC') !== false || strpos($name, 'COMPUTER CONCEPTS') !== false) {
            $parent_key = 'CCC';
        }

        $module_name = $q['category_name'] ? ($q['category_code'] . ': ' . $q['category_name']) : 'Unassigned / Global Questions';
        
        if (!isset($course_groups[$parent_key]['modules'][$module_name])) {
            $course_groups[$parent_key]['modules'][$module_name] = [
                'cat_id' => $q['category_id'],
                'cat_code' => $q['category_code'],
                'cat_name' => $q['category_name'],
                'duration' => 120, // defaults if orphaned
                'marks' => 100,
                'chapter_count' => 1,
                'chapters' => [],
                'unassigned' => []
            ];
        }
        
        $chap_num = $q['chapter_number'] ?? null;
        if ($chap_num) {
            $course_groups[$parent_key]['modules'][$module_name]['chapters'][$chap_num][] = $q;
        } else {
            $course_groups[$parent_key]['modules'][$module_name]['unassigned'][] = $q;
        }

        $course_groups[$parent_key]['q_count']++;
    }
    
    $course_groups = array_filter($course_groups, function($group) { return count($group['modules']) > 0; });
    
    // Get statistics
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN is_active THEN 1 END) as active,
            COUNT(CASE WHEN difficulty_level = 'easy' THEN 1 END) as easy,
            COUNT(CASE WHEN difficulty_level = 'medium' THEN 1 END) as medium,
            COUNT(CASE WHEN difficulty_level = 'hard' THEN 1 END) as hard
        FROM questions
    ")->fetch(PDO::FETCH_ASSOC);

    $is_filtered = (!empty($category_filter) || !empty($difficulty_filter));

} catch (PDOException $e) {
    $error = "System Database Error. Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Bank - Coordinator Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #7C3AED;        --primary-light: #8B5CF6;  --primary-bg: #EDE9FE;     
            --secondary: #0F172A;
            --success: #059669;        --success-bg: #D1FAE5;
            --danger: #DC2626;         --danger-bg: #FEE2E2;
            --warning: #D97706;        --warning-bg: #FEF3C7;
            --text-dark: #0F172A;      --text-muted: #64748B;
            --bg-body: #F8FAFC;        --surface: #FFFFFF;
            --border: #E2E8F0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 40px -10px rgba(124, 58, 237, 0.1);
            --radius-md: 12px;         --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; padding-bottom: 60px; position: relative; }

        .ambient-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; overflow: hidden; pointer-events: none; background: linear-gradient(180deg, #F8FAFC 0%, #E2E8F0 100%); perspective: 1000px; }
        
        .navbar-wrapper {
            position: sticky; top: 0; z-index: 1000;
            background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(16px);
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

        .container { max-width: 1440px; margin: 30px auto; padding: 0 40px; position: relative; z-index: 10; animation: fadeUp 0.5s ease-out; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        .header-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        
        .btn-bulk { background: var(--surface); color: var(--primary); border: 1px solid var(--primary-light); padding: 12px 20px; border-radius: var(--radius-md); text-decoration: none; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; box-shadow: var(--shadow-sm); cursor: pointer;}
        .btn-bulk:hover { background: var(--primary-bg); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .btn-add { background: var(--primary); color: white; padding: 12px 24px; border-radius: var(--radius-md); text-decoration: none; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.2); }
        .btn-add:hover { background: #6D28D9; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3); }

        .alert { padding: 16px 20px; border-radius: var(--radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; border: 1px solid transparent;}
        .alert-success { background: var(--success-bg); color: var(--success); border-color: #A7F3D0; }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: #FECACA; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: rgba(255,255,255,0.85); backdrop-filter: blur(10px); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 15px; position: relative; overflow: hidden; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .stat-card::before { content:''; position:absolute; top:0; left:0; width:4px; height:100%; border-radius: 4px 0 0 4px; }
        .stat-card.total::before { background: var(--primary); }
        .stat-card.active::before { background: var(--success); }
        .stat-card.easy::before { background: #10B981; }
        .stat-card.medium::before { background: #F59E0B; }
        .stat-card.hard::before { background: #EF4444; }

        .stat-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .stat-card.total .stat-icon { background: var(--primary-bg); color: var(--primary); }
        .stat-card.active .stat-icon { background: var(--success-bg); color: var(--success); }
        .stat-card.easy .stat-icon { background: #D1FAE5; color: #10B981; }
        .stat-card.medium .stat-icon { background: #FEF3C7; color: #F59E0B; }
        .stat-card.hard .stat-icon { background: #FEE2E2; color: #EF4444; }

        .stat-info h3 { font-size: 24px; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 5px; }
        .stat-info p { font-size: 13px; color: var(--text-muted); font-weight: 600; }

        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; background: rgba(255,255,255,0.9); padding: 15px 20px; border-radius: var(--radius-md); border: 1px solid var(--border); box-shadow: var(--shadow-sm); }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .filter-select { padding: 10px 35px 10px 15px; border-radius: 10px; border: 1px solid var(--border); background-color: var(--bg-body); font-family: inherit; font-size: 13px; font-weight: 600; color: var(--text-dark); cursor: pointer; outline: none; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 15px center; transition: 0.3s; }
        .filter-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
        .btn-apply { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 13px; cursor: pointer; transition: 0.2s; }
        .btn-apply:hover { background: #6D28D9; }
        .btn-reset { background: var(--border); color: var(--text-dark); text-decoration: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 13px; transition: 0.2s; }
        .btn-reset:hover { background: #CBD5E1; }

        .search-box { position: relative; width: 100%; max-width: 320px; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-box input { width: 100%; padding: 10px 15px 10px 40px; border-radius: 50px; border: 1px solid var(--border); background: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; transition: all 0.3s; outline: none; font-weight: 500; }
        .search-box input:focus { background: var(--white); border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }

        .course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; margin-bottom: 40px; }
        .course-card { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); border-radius: var(--radius-lg); border: 1px solid var(--border); padding: 30px 25px; text-align: center; cursor: pointer; box-shadow: var(--shadow-sm); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; align-items: center; gap: 15px; }
        .course-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-lg); border-color: var(--primary-light); }
        .course-icon-wrap { width: 64px; height: 64px; border-radius: 16px; display: flex; justify-content: center; align-items: center; font-size: 28px; }
        .theme-blue { background: var(--primary-bg); color: var(--primary); }
        .theme-indigo { background: #E0E7FF; color: #4F46E5; }
        .theme-teal { background: #CCFBF1; color: #0D9488; }
        .theme-warning { background: var(--warning-bg); color: var(--warning); }
        .theme-gray { background: #F1F5F9; color: #475569; }
        .course-card h3 { font-size: 18px; font-weight: 800; color: var(--text-dark); }
        .course-card p { font-size: 13px; font-weight: 600; color: var(--text-muted); background: var(--bg-body); padding: 6px 12px; border-radius: 50px; }

        #moduleView { display: none; }
        .view-header { display: flex; align-items: center; gap: 20px; margin-bottom: 25px; background: var(--surface); padding: 15px 25px; border-radius: var(--radius-md); border: 1px solid var(--border); }
        .btn-back-cat { background: var(--bg-body); border: 1px solid var(--border); border-radius: 8px; padding: 8px 16px; color: var(--text-dark); font-weight: 700; font-size: 13px; cursor: pointer; transition: 0.2s; }
        .btn-back-cat:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); }
        .view-header h2 { font-size: 20px; font-weight: 800; color: var(--primary); }

        .folder-accordion { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); margin-bottom: 20px; overflow: hidden; transition: 0.3s; }
        .folder-header { padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: 0.3s; background: transparent; }
        .folder-header:hover { background: var(--primary-bg); }
        
        .folder-title-area { display: flex; align-items: center; gap: 15px; }
        .folder-icon { font-size: 24px; color: var(--primary); transition: 0.3s; }
        .folder-title { font-size: 16px; font-weight: 800; color: var(--text-dark); }
        .folder-count { background: var(--primary); color: white; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 50px; margin-left: 10px; }

        .btn-folder-add { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary-light); padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: 0.3s;}
        .btn-folder-add:hover { background: var(--primary); color: white; }
        .btn-folder-delete { background: var(--danger-bg); color: var(--danger); border: 1px solid #FECACA; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: 0.3s; }
        .btn-folder-delete:hover { background: var(--danger); color: white; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.2); }

        .folder-content { display: none; border-top: 1px solid var(--border); background: #F8FAFC; padding: 10px; }
        .folder-content.active { display: block; animation: slideDown 0.3s ease-out; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* CHAPTER STYLES */
        .chapter-box { background: white; border: 1px solid var(--border); border-radius: 12px; margin-bottom: 15px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
        .chapter-header { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; background: #FFFFFF; cursor: pointer; transition: 0.2s;}
        .chapter-header:hover { background: #F1F5F9; }
        .chapter-title { font-size: 14px; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; gap: 10px;}
        .chapter-title i { color: var(--primary-light); }
        .chapter-actions { display: flex; gap: 10px; align-items: center;}
        .chapter-count-badge { font-size: 11px; background: var(--bg-body); padding: 4px 8px; border-radius: 6px; border: 1px solid var(--border); color: var(--text-muted); font-weight: 700;}
        .chapter-content { display: none; border-top: 1px solid var(--border); }

        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { background: #F8FAFC; color: var(--text-muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 15px 20px; text-align: left; border-bottom: 2px solid var(--border); }
        td { padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 500; vertical-align: middle; transition: background 0.2s;}
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--bg-body); }

        .q-text { max-width: 400px; font-weight: 600; color: var(--text-dark); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
        .q-meta { font-size: 12px; color: var(--text-muted); margin-top: 6px; display: flex; gap: 15px; }
        .q-meta i { color: var(--primary); margin-right: 4px; }

        .badge { padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; white-space: nowrap;}
        .b-easy { background: #D1FAE5; color: #059669; }
        .b-medium { background: #FEF3C7; color: #D97706; }
        .b-hard { background: #FEE2E2; color: #DC2626; }
        .b-mcq { background: var(--primary-bg); color: var(--primary); }
        .b-tf { background: #F1F5F9; color: #475569; }
        .b-active { background: var(--success-bg); color: var(--success); }
        .b-inactive { background: var(--danger-bg); color: var(--danger); }

        .actions { display: flex; gap: 8px; justify-content: flex-end;}
        .btn-action { width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s; font-size: 14px; cursor: pointer; }
        .btn-edit { background: var(--warning-bg); color: var(--warning); }
        .btn-edit:hover { background: var(--warning); color: white; transform: translateY(-2px); }
        .btn-deactivate { background: var(--danger-bg); color: var(--danger); }
        .btn-deactivate:hover { background: var(--danger); color: white; transform: translateY(-2px); }
        .btn-activate { background: var(--success-bg); color: var(--success); }
        .btn-activate:hover { background: var(--success); color: white; transform: translateY(-2px); }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; }
        .modal { background: white; padding: 30px; border-radius: var(--radius-lg); width: 100%; max-width: 450px; box-shadow: var(--shadow-lg); animation: slideUpFade 0.3s ease-out; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { font-size: 20px; font-weight: 800; color: var(--text-dark); }
        .btn-close { background: none; border: none; font-size: 20px; color: var(--text-muted); cursor: pointer; transition: 0.2s; }
        .btn-close:hover { color: var(--danger); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 15px; border: 2px solid var(--border); border-radius: var(--radius-md); font-size: 14px; font-family: inherit; transition: 0.3s; outline: none; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; }

        @media (max-width: 768px) {
            .top-nav { padding: 15px 20px; }
            .container { padding: 0 20px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; }
            .header-actions a, .header-actions button { flex: 1; justify-content: center; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .filters { width: 100%; flex-direction: column; }
            .filters select, .filters button, .filters a { width: 100%; text-align: center; }
            .search-box { max-width: 100%; }
            .folder-header { flex-direction: column; align-items: flex-start; gap: 15px; }
        }
    </style>
</head>
<body>

    <div class="ambient-bg">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
    </div>

    <div class="navbar-wrapper">
        <nav class="top-nav">
            <div class="nav-left">
                <a href="coordinator-dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
                <div class="brand-text">
                    <h2>Question Bank</h2>
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
                <h1><i class="fas fa-database" style="color: var(--primary);"></i> Module-Wise Question Bank</h1>
            </div>
            <div class="header-actions">
                <button type="button" onclick="openModal()" class="btn-bulk" style="background: var(--primary-bg); border-color: var(--primary-light); color: var(--primary);"><i class="fas fa-folder-plus"></i> Create Module</button>
                <a href="bulk-upload-questions.php" class="btn-bulk"><i class="fas fa-file-csv"></i> Bulk Import</a>
                <a href="add-question.php" class="btn-add"><i class="fas fa-plus"></i> Add Question</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($warning): ?>
            <div class="alert" style="background: var(--warning-bg); color: var(--warning); border-color: #FDE68A;"><i class="fas fa-exclamation-triangle"></i> <?php echo $warning; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total'] ?? 0); ?></h3>
                    <p>Total Questions</p>
                </div>
            </div>
            <div class="stat-card active">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['active'] ?? 0); ?></h3>
                    <p>Active</p>
                </div>
            </div>
            <div class="stat-card easy">
                <div class="stat-icon"><i class="fas fa-seedling"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['easy'] ?? 0); ?></h3>
                    <p>Easy Level</p>
                </div>
            </div>
            <div class="stat-card medium">
                <div class="stat-icon"><i class="fas fa-balance-scale"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['medium'] ?? 0); ?></h3>
                    <p>Medium Level</p>
                </div>
            </div>
            <div class="stat-card hard">
                <div class="stat-icon"><i class="fas fa-fire"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['hard'] ?? 0); ?></h3>
                    <p>Hard Level</p>
                </div>
            </div>
        </div>

        <div class="toolbar">
            <form method="GET" class="filters">
                <select name="category" class="filter-select">
                    <option value="">All Specific Modules</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_code'] . ' - ' . $cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="difficulty" class="filter-select">
                    <option value="">All Difficulties</option>
                    <option value="easy" <?php echo $difficulty_filter == 'easy' ? 'selected' : ''; ?>>Easy</option>
                    <option value="medium" <?php echo $difficulty_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="hard" <?php echo $difficulty_filter == 'hard' ? 'selected' : ''; ?>>Hard</option>
                </select>
                
                <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply</button>
                <?php if($is_filtered): ?>
                    <a href="manage-questions.php" class="btn-reset">Reset</a>
                <?php endif; ?>
            </form>

            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search across all modules..." onkeyup="searchTable()">
            </div>
        </div>

        <div id="categoryGrid" class="course-grid" style="<?php echo $is_filtered ? 'display:none;' : ''; ?>">
            <?php foreach ($course_groups as $group_key => $group): ?>
                <div class="course-card" onclick="openCategory('<?php echo htmlspecialchars($group_key); ?>')">
                    <div class="course-icon-wrap theme-<?php echo $group['theme']; ?>">
                        <i class="fas <?php echo $group['icon']; ?>"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($group['title']); ?></h3>
                    <p><?php echo count($group['modules']); ?> Modules • <?php echo $group['q_count']; ?> Questions</p>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($course_groups)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="fas fa-box-open" style="font-size: 32px; margin-bottom:10px;"></i>
                    <p>No categories found. Create a module to get started.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="moduleView" style="<?php echo $is_filtered ? 'display:block;' : ''; ?>">
            
            <div class="view-header" id="viewHeader" style="<?php echo $is_filtered ? 'display:none;' : ''; ?>">
                <button class="btn-back-cat" onclick="closeCategory()">
                    <i class="fas fa-arrow-left"></i> Back to Categories
                </button>
                <h2 id="currentCategoryTitle">Category Name</h2>
            </div>

            <div id="foldersContainer">
                <?php if (empty($course_groups) && $is_filtered): ?>
                    <div style="text-align: center; padding: 60px; background: rgba(255,255,255,0.8); border-radius: var(--radius-lg); border: 1px dashed var(--border);">
                        <i class="fas fa-folder-open" style="font-size: 40px; color: var(--primary-light); margin-bottom: 15px; opacity: 0.5;"></i>
                        <h3 style="color: var(--text-dark); margin-bottom: 5px;">No Questions Found</h3>
                        <p style="color: var(--text-muted); font-size: 14px;">Try adjusting your filters.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $folder_index = 0;
                    foreach ($course_groups as $group_key => $group):
                        foreach ($group['modules'] as $folder_name => $folder_data): 
                            $folder_index++;
                            $folder_id = "folder-" . $folder_index;
                            $cat_id = $folder_data['cat_id'] ?? 'null';
                            $total_chapters = $folder_data['chapter_count'];
                    ?>
                        <div class="folder-accordion" data-category="<?php echo htmlspecialchars($group_key); ?>" data-folder="<?php echo $folder_id; ?>" style="<?php echo $is_filtered ? '' : 'display:none;'; ?>">
                            
                            <div class="folder-header" onclick="toggleFolder('<?php echo $folder_id; ?>')">
                                <div class="folder-title-area">
                                    <i class="fas fa-folder folder-icon" id="icon-<?php echo $folder_id; ?>"></i>
                                    <span class="folder-title"><?php echo htmlspecialchars($folder_name); ?></span>
                                    <span class="folder-count"><?php echo $total_chapters; ?> Chapters</span>
                                </div>
                                
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <?php if ($cat_id !== 'null'): ?>
                                        <a href="add-question.php?category_id=<?php echo $cat_id; ?>" class="btn-folder-add" onclick="event.stopPropagation();">
                                            <i class="fas fa-plus"></i> Add Question
                                        </a>
                                        
                                        <button type="button" class="btn-folder-add" onclick="event.stopPropagation(); openEditModal('<?php echo $cat_id; ?>', '<?php echo addslashes($folder_data['cat_code']); ?>', '<?php echo addslashes($folder_data['cat_name']); ?>', '<?php echo $folder_data['duration']; ?>', '<?php echo $folder_data['marks']; ?>', '<?php echo $total_chapters; ?>')" style="background: var(--warning-bg); color: var(--warning); border-color: #FDE68A;">
                                            <i class="fas fa-edit"></i> Edit Module
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="?delete_module=<?php echo $cat_id; ?>" class="btn-folder-delete" onclick="event.stopPropagation(); return confirm('WARNING: This will delete ALL questions inside this module. Are you sure?');">
                                        <i class="fas fa-trash-alt"></i> Delete Module
                                    </a>
                                    
                                    <i class="fas fa-chevron-down" style="color: var(--text-muted); font-size: 14px;"></i>
                                </div>
                            </div>

                            <div class="folder-content" id="<?php echo $folder_id; ?>">
                                
                                <?php 
                                for ($i = 1; $i <= $total_chapters; $i++): 
                                    $chap_id = $folder_id . "-chap-" . $i;
                                    $chap_questions = $folder_data['chapters'][$i] ?? [];
                                ?>
                                    <div class="chapter-box">
                                        <div class="chapter-header" onclick="toggleChapter('<?php echo $chap_id; ?>', this)">
                                            <div class="chapter-title">
                                                <i class="fas fa-bookmark"></i> Chapter <?php echo $i; ?>
                                            </div>
                                            <div class="chapter-actions">
                                                <span class="chapter-count-badge"><?php echo count($chap_questions); ?> Qs</span>
                                                <i class="fas fa-angle-down" style="color: var(--text-muted); font-size: 12px; transition: 0.2s;"></i>
                                            </div>
                                        </div>
                                        
                                        <div class="chapter-content" id="<?php echo $chap_id; ?>">
                                            <?php if (empty($chap_questions)): ?>
                                                <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 13px;">
                                                    No questions assigned to Chapter <?php echo $i; ?> yet.
                                                </div>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table>
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 60px;">ID</th>
                                                                <th>Question Preview</th>
                                                                <th>Type & Difficulty</th>
                                                                <th>Status</th>
                                                                <th style="text-align: right;">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($chap_questions as $q): ?>
                                                            <tr class="q-row">
                                                                <td style="color: var(--text-muted); font-weight: 700;">#<?php echo $q['id']; ?></td>
                                                                <td>
                                                                    <div class="q-text" title="<?php echo htmlspecialchars($q['question_text']); ?>">
                                                                        <?php echo htmlspecialchars($q['question_text']); ?>
                                                                    </div>
                                                                    <div class="q-meta">
                                                                        <span><i class="fas fa-list-ol"></i> <?php echo $q['option_count']; ?> Options</span>
                                                                        <span><i class="fas fa-star"></i> <?php echo $q['marks']; ?> Mark(s)</span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div style="display:flex; flex-direction:column; gap:5px; align-items:flex-start;">
                                                                        <span class="badge <?php echo $q['question_type'] == 'mcq' ? 'b-mcq' : 'b-tf'; ?>">
                                                                            <?php echo $q['question_type'] == 'mcq' ? 'MCQ' : 'True/False'; ?>
                                                                        </span>
                                                                        <span class="badge b-<?php echo $q['difficulty_level']; ?>">
                                                                            <?php echo ucfirst($q['difficulty_level']); ?>
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <span class="badge b-<?php echo $q['is_active'] ? 'active' : 'inactive'; ?>">
                                                                        <?php echo $q['is_active'] ? 'Active' : 'Disabled'; ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div class="actions">
                                                                        <a href="edit-question.php?id=<?php echo $q['id']; ?>" class="btn-action btn-edit" title="Edit Question">
                                                                            <i class="fas fa-pen"></i>
                                                                        </a>
                                                                        <?php if ($q['is_active']): ?>
                                                                            <a href="?delete=<?php echo $q['id']; ?>" class="btn-action btn-deactivate" onclick="return confirm('Deactivate this question?')" title="Deactivate">
                                                                                <i class="fas fa-power-off"></i>
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <a href="?activate=<?php echo $q['id']; ?>" class="btn-action btn-activate" onclick="return confirm('Reactivate this question?')" title="Activate">
                                                                                <i class="fas fa-check"></i>
                                                                            </a>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                                
                                <?php if (!empty($folder_data['unassigned'])): ?>
                                    <div class="chapter-box" style="border-color: #E2E8F0;">
                                        <div class="chapter-header" onclick="toggleChapter('<?php echo $folder_id; ?>-chap-unassigned', this)" style="background: #F8FAFC;">
                                            <div class="chapter-title" style="color: var(--text-muted);">
                                                <i class="fas fa-layer-group"></i> General Questions (Unassigned)
                                            </div>
                                            <div class="chapter-actions">
                                                <span class="chapter-count-badge"><?php echo count($folder_data['unassigned']); ?> Qs</span>
                                                <i class="fas fa-angle-down" style="color: var(--text-muted); font-size: 12px;"></i>
                                            </div>
                                        </div>
                                        <div class="chapter-content" id="<?php echo $folder_id; ?>-chap-unassigned">
                                            <div class="table-responsive">
                                                <table>
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 60px;">ID</th>
                                                            <th>Question Preview</th>
                                                            <th>Type & Difficulty</th>
                                                            <th>Status</th>
                                                            <th style="text-align: right;">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($folder_data['unassigned'] as $q): ?>
                                                        <tr class="q-row">
                                                            <td style="color: var(--text-muted); font-weight: 700;">#<?php echo $q['id']; ?></td>
                                                            <td>
                                                                <div class="q-text" title="<?php echo htmlspecialchars($q['question_text']); ?>">
                                                                    <?php echo htmlspecialchars($q['question_text']); ?>
                                                                </div>
                                                                <div class="q-meta">
                                                                    <span><i class="fas fa-list-ol"></i> <?php echo $q['option_count']; ?> Options</span>
                                                                    <span><i class="fas fa-star"></i> <?php echo $q['marks']; ?> Mark(s)</span>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div style="display:flex; flex-direction:column; gap:5px; align-items:flex-start;">
                                                                    <span class="badge <?php echo $q['question_type'] == 'mcq' ? 'b-mcq' : 'b-tf'; ?>"><?php echo $q['question_type'] == 'mcq' ? 'MCQ' : 'True/False'; ?></span>
                                                                    <span class="badge b-<?php echo $q['difficulty_level']; ?>"><?php echo ucfirst($q['difficulty_level']); ?></span>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge b-<?php echo $q['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $q['is_active'] ? 'Active' : 'Disabled'; ?></span>
                                                            </td>
                                                            <td>
                                                                <div class="actions">
                                                                    <a href="edit-question.php?id=<?php echo $q['id']; ?>" class="btn-action btn-edit" title="Edit Question"><i class="fas fa-pen"></i></a>
                                                                    <?php if ($q['is_active']): ?>
                                                                        <a href="?delete=<?php echo $q['id']; ?>" class="btn-action btn-deactivate" onclick="return confirm('Deactivate this question?')" title="Deactivate"><i class="fas fa-power-off"></i></a>
                                                                    <?php else: ?>
                                                                        <a href="?activate=<?php echo $q['id']; ?>" class="btn-action btn-activate" onclick="return confirm('Reactivate this question?')" title="Activate"><i class="fas fa-check"></i></a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endforeach; endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="moduleModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3>Create New Module</h3>
                <button type="button" class="btn-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Module Code (e.g., M1-R5, DA-M1) <span style="color:red;">*</span></label>
                    <input type="text" name="module_code" class="form-control" required placeholder="Enter unique short code">
                </div>
                <div class="form-group">
                    <label>Module Full Name <span style="color:red;">*</span></label>
                    <input type="text" name="module_name" class="form-control" required placeholder="e.g., IT Tools and Network Basics">
                </div>
                <div class="form-group">
                    <label>Standard Exam Duration (Minutes) <span style="color:red;">*</span></label>
                    <input type="number" name="duration_minutes" class="form-control" required value="120" min="10">
                </div>
                <div class="form-group">
                    <label>Total Marks <span style="color:red;">*</span></label>
                    <input type="number" name="total_marks" class="form-control" required value="100" min="1">
                </div>
                <div class="form-group">
                    <label>Total Chapters in Module <span style="color:red;">*</span></label>
                    <input type="number" name="chapter_count" class="form-control" required value="1" min="1" max="50">
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-back-cat" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="add_module" class="btn-add"><i class="fas fa-save"></i> Save Module</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModuleModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3>Edit Module Settings</h3>
                <button type="button" class="btn-close" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="edit_module_id" id="editModuleId">
                <div class="form-group">
                    <label>Module Code <span style="color:red;">*</span></label>
                    <input type="text" name="edit_module_code" id="editModuleCode" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Module Full Name <span style="color:red;">*</span></label>
                    <input type="text" name="edit_module_name" id="editModuleName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Standard Exam Duration (Minutes) <span style="color:red;">*</span></label>
                    <input type="number" name="edit_duration_minutes" id="editDuration" class="form-control" required min="10">
                </div>
                <div class="form-group">
                    <label>Total Marks <span style="color:red;">*</span></label>
                    <input type="number" name="edit_total_marks" id="editMarks" class="form-control" required min="1">
                </div>
                <div class="form-group">
                    <label>Total Chapters <span style="color:red;">*</span></label>
                    <input type="number" name="edit_chapter_count" id="editChapters" class="form-control" required min="1" max="50">
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-back-cat" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="edit_module" class="btn-add" style="background: var(--warning); border:none;"><i class="fas fa-save"></i> Update Module</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const isFiltered = <?php echo $is_filtered ? 'true' : 'false'; ?>;

        function openModal() { document.getElementById('moduleModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('moduleModal').style.display = 'none'; }

        // 🟢 NEW: Edit Modal Logic
        function openEditModal(id, code, name, duration, marks, chapters) {
            document.getElementById('editModuleId').value = id;
            document.getElementById('editModuleCode').value = code;
            document.getElementById('editModuleName').value = name;
            document.getElementById('editDuration').value = duration;
            document.getElementById('editMarks').value = marks;
            document.getElementById('editChapters').value = chapters;
            document.getElementById('editModuleModal').style.display = 'flex';
        }
        function closeEditModal() { document.getElementById('editModuleModal').style.display = 'none'; }

        function openCategory(categoryKey) {
            document.getElementById('categoryGrid').style.display = 'none';
            document.getElementById('moduleView').style.display = 'block';
            document.getElementById('viewHeader').style.display = 'flex';
            
            const titles = {
                'O-Level': 'O-Level Modules',
                'A-Level': 'A-Level Modules',
                'BC-Level': 'B/C-Level Modules',
                'CCC': 'Short Term / CCC',
                'Other': 'Other Modules'
            };
            document.getElementById('currentCategoryTitle').innerText = titles[categoryKey];

            const accordions = document.querySelectorAll('.folder-accordion');
            accordions.forEach(acc => {
                if(acc.dataset.category === categoryKey) {
                    acc.style.display = 'block';
                } else {
                    acc.style.display = 'none';
                }
            });
        }

        function closeCategory() {
            document.getElementById('categoryGrid').style.display = 'grid';
            document.getElementById('moduleView').style.display = 'none';
            document.getElementById('searchInput').value = '';
            searchTable(); 
        }

        function toggleFolder(folderId) {
            const content = document.getElementById(folderId);
            const icon = document.getElementById('icon-' + folderId);
            
            if (content.classList.contains('active')) {
                content.classList.remove('active');
                icon.classList.replace('fa-folder-open', 'fa-folder');
            } else {
                content.classList.add('active');
                icon.classList.replace('fa-folder', 'fa-folder-open');
            }
        }

        function toggleChapter(chapterId, headerElement) {
            const content = document.getElementById(chapterId);
            const arrow = headerElement.querySelector('.fa-angle-down');
            
            if (content.style.display === 'block') {
                content.style.display = 'none';
                headerElement.style.background = '#FFFFFF';
                if(arrow) arrow.style.transform = 'rotate(0deg)';
            } else {
                content.style.display = 'block';
                headerElement.style.background = '#F8FAFC';
                if(arrow) arrow.style.transform = 'rotate(180deg)';
            }
        }

        function searchTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const accordions = document.querySelectorAll('.folder-accordion');
            
            if(input.length > 0) {
                document.getElementById('categoryGrid').style.display = 'none';
                document.getElementById('moduleView').style.display = 'block';
                document.getElementById('viewHeader').style.display = 'none';
            } else if (!isFiltered) {
                document.getElementById('categoryGrid').style.display = 'grid';
                document.getElementById('moduleView').style.display = 'none';
                return;
            }
            
            accordions.forEach(folder => {
                const rows = folder.querySelectorAll('tbody .q-row');
                let hasVisibleRow = false;
                
                rows.forEach(row => {
                    const textCell = row.cells[1];
                    if (textCell) {
                        const text = textCell.textContent.toLowerCase();
                        if (text.includes(input)) {
                            row.style.display = '';
                            hasVisibleRow = true;
                            const chapterContent = row.closest('.chapter-content');
                            if(chapterContent) {
                                chapterContent.style.display = 'block';
                                const arrow = chapterContent.previousElementSibling.querySelector('.fa-angle-down');
                                if(arrow) arrow.style.transform = 'rotate(180deg)';
                            }
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });

                const content = folder.querySelector('.folder-content');
                const icon = folder.querySelector('.folder-icon');
                
                if (input !== '' && hasVisibleRow) {
                    content.classList.add('active');
                    icon.classList.replace('fa-folder', 'fa-folder-open');
                    folder.style.display = 'block';
                } else if (input !== '' && !hasVisibleRow) {
                    folder.style.display = 'none'; 
                } else if (isFiltered) {
                    folder.style.display = 'block'; 
                }
            });
        }
    </script>
</body>
</html>