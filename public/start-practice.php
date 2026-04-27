<?php
session_name('NIELIT_CANDIDATE_SESSION');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate' || !isset($_GET['exam_id'])) {
    header("Location: candidate-dashboard.php");
    exit();
}

$exam_id = $_GET['exam_id'];
$candidate_id = $_SESSION['user_id'];

$host = 'localhost';
$port = '5432';
$dbname = 'nielit_cbt_mock';
$dbuser = 'nielit_admin';
$dbpass = 'NIELIT@BBSR2024';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 1. Check if this is ACTUALLY a practice exam (Security Check)
    $stmt = $pdo->prepare("SELECT is_practice FROM exam_sessions WHERE id = ?");
    $stmt->execute([$exam_id]);
    $is_practice = $stmt->fetchColumn();

    if (!$is_practice) {
        die("Error: This is a formal exam. You cannot use the practice launch tool for this session.");
    }

    // 2. Check if candidate is already registered for this practice exam
    $stmt = $pdo->prepare("SELECT id FROM exam_registrations WHERE candidate_id = ? AND session_id = ?");
    $stmt->execute([$candidate_id, $exam_id]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reg) {
        // --- THEY ARE ALREADY REGISTERED (THIS IS A RETAKE) ---
        $reg_id = $reg['id'];
        
        $pdo->beginTransaction();
        // Wipe old answers
        $pdo->prepare("DELETE FROM candidate_responses WHERE registration_id = ?")->execute([$reg_id]);
        // Wipe old scorecard
        $pdo->prepare("DELETE FROM exam_results WHERE registration_id = ?")->execute([$reg_id]);
        // Reset status
        $pdo->prepare("UPDATE exam_registrations SET registration_status = 'registered' WHERE id = ?")->execute([$reg_id]);
        $pdo->commit();
        
        // Clear their old timer
        unset($_SESSION['exam_end_' . $reg_id]);

    } else {
        // --- THEY ARE NOT REGISTERED (AUTO-ENROLL THEM INSTANTLY) ---
        $stmt = $pdo->prepare("
            INSERT INTO exam_registrations (candidate_id, session_id, registration_status) 
            VALUES (?, ?, 'registered')
        ");
        $stmt->execute([$candidate_id, $exam_id]);
    }

    // 3. Launch the exam!
    header("Location: take-exam.php?exam_id=" . $exam_id);
    exit();

} catch (PDOException $e) {
    die("System Error while launching practice mode. Please contact support.");
}
?>