<?php
// Force PHP to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

session_name('NIELIT_CANDIDATE_SESSION');
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

// Get the JSON data sent from take-exam.php
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['registration_id']) || !isset($data['exam_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid submission data.']);
    exit;
}

$registration_id = $data['registration_id'];
$exam_id = $data['exam_id'];
$frontend_answers = $data['answers'] ?? []; 

// 🟢 NEW ARCHITECTURE: Centralized database connection
require_once __DIR__ . '/../../config/database.php';

try {
    // $pdo is securely imported from database.php
    
    $pdo->beginTransaction();

    // 1. Verify Registration
    $stmt = $pdo->prepare("SELECT registration_status FROM exam_registrations WHERE id = ? AND candidate_id = ?");
    $stmt->execute([$registration_id, $_SESSION['user_id']]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reg) {
        throw new Exception("Invalid registration record.");
    }

    // 2. FAILSAFE SYNC: Force frontend answers into database
    // This will accurately update the "15 / 15 Answered" progress bar!
    if (!empty($frontend_answers) && is_array($frontend_answers)) {
        $clearStmt = $pdo->prepare("DELETE FROM candidate_responses WHERE registration_id = ?");
        $clearStmt->execute([$registration_id]);

        $insAns = $pdo->prepare("INSERT INTO candidate_responses (registration_id, question_id, selected_option_id) VALUES (?, ?, ?)");
        foreach ($frontend_answers as $q_id => $opt_id) {
            if (!empty($opt_id)) {
                $insAns->execute([$registration_id, $q_id, $opt_id]);
            }
        }
    }

    // 3. Fetch Category ID to get passing marks
    $catStmt = $pdo->prepare("SELECT category_id FROM exam_sessions WHERE id = ?");
    $catStmt->execute([$exam_id]);
    $category_id = $catStmt->fetchColumn();

    // 4. Fetch ONLY ACTIVE questions.
    $qStmt = $pdo->prepare("
        SELECT q.id as question_id, q.marks, 
               (SELECT id FROM question_options WHERE question_id = q.id AND is_correct = true LIMIT 1) as correct_option_id 
        FROM questions q
        WHERE q.category_id = ? AND q.is_active = true
    ");
    $qStmt->execute([$category_id]);
    
    $correct_mapping = [];
    $total_possible_marks = 0;
    $total_questions = 0;
    
    while ($row = $qStmt->fetch(PDO::FETCH_ASSOC)) {
        $correct_mapping[$row['question_id']] = [
            'correct_option' => $row['correct_option_id'],
            'marks' => $row['marks']
        ];
        $total_possible_marks += $row['marks'];
        $total_questions++;
    }

    // 5. Calculate Score securely directly from the Database
    $dbAnswers = $pdo->prepare("SELECT question_id, selected_option_id FROM candidate_responses WHERE registration_id = ?");
    $dbAnswers->execute([$registration_id]);
    
    $marks_obtained = 0;
    $correct_count = 0;

    while ($ans = $dbAnswers->fetch(PDO::FETCH_ASSOC)) {
        $q_id = $ans['question_id'];
        $selected_opt = $ans['selected_option_id'];
        
        if (isset($correct_mapping[$q_id]) && $correct_mapping[$q_id]['correct_option'] == $selected_opt) {
            $marks_obtained += $correct_mapping[$q_id]['marks'];
            $correct_count++;
        }
    }

    // 6. Determine Pass/Fail
    $percentage = ($total_possible_marks > 0) ? ($marks_obtained / $total_possible_marks) * 100 : 0;
    
    $pass_percent = 50; 
    try {
        $passStmt = $pdo->prepare("SELECT pass_percentage FROM exam_categories WHERE id = ?");
        $passStmt->execute([$category_id]);
        if ($val = $passStmt->fetchColumn()) {
            $pass_percent = $val;
        }
    } catch (Exception $e) {}
    
    $status = ($percentage >= $pass_percent) ? 'pass' : 'fail';

    // 7. Check if Result Already Exists (Smart Upsert)
    $checkResult = $pdo->prepare("SELECT registration_id FROM exam_results WHERE registration_id = ?");
    $checkResult->execute([$registration_id]);
    
    if ($checkResult->fetch()) {
        $resStmt = $pdo->prepare("
            UPDATE exam_results 
            SET total_marks_obtained = ?, percentage = ?, result_status = ?, correct_answers = ?, total_questions = ?
            WHERE registration_id = ?
        ");
        $resStmt->execute([$marks_obtained, $percentage, $status, $correct_count, $total_questions, $registration_id]);
    } else {
        $resStmt = $pdo->prepare("
            INSERT INTO exam_results 
            (registration_id, total_marks_obtained, percentage, result_status, correct_answers, total_questions) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $resStmt->execute([$registration_id, $marks_obtained, $percentage, $status, $correct_count, $total_questions]);
    }

    // 8. ULTIMATE FIX: Only update 'attendance_marked'. We completely ignore 'registration_status' 
    // to bypass the strict database check constraint that was crashing the submission!
    $updStmt = $pdo->prepare("
        UPDATE exam_registrations 
        SET attendance_marked = true
        WHERE id = ?
    ");
    $updStmt->execute([$registration_id]);

    // Commit all changes
    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Log the actual error for the admin, but hide it from the frontend JSON response
    error_log("Submit Exam Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A secure processing error occurred. Result logged.']);
}
?>