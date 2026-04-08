<?php
session_name('NIELIT_CANDIDATE_SESSION'); // Ensure session name matches the rest of the portal
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit();
}

// 🟢 NEW ARCHITECTURE: Centralized database connection
require_once __DIR__ . '/../../config/database.php';

try {
    // $pdo is securely imported from database.php
    
    // Verify the registration belongs to this candidate
    $stmt = $pdo->prepare("
        SELECT id FROM exam_registrations 
        WHERE id = ? AND candidate_id = ?
    ");
    $stmt->execute([$data['registration_id'], $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid registration']);
        exit();
    }
    
    // Save or update answer
    $stmt = $pdo->prepare("
        INSERT INTO candidate_responses (registration_id, question_id, selected_option_id, saved_at)
        VALUES (?, ?, ?, NOW())
        ON CONFLICT (registration_id, question_id) 
        DO UPDATE SET selected_option_id = EXCLUDED.selected_option_id, saved_at = NOW()
    ");
    $stmt->execute([$data['registration_id'], $data['question_id'], $data['option_id']]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    // Log the actual error for the admin, but hide it from the frontend JSON response for security
    error_log("Save Answer DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'A database error occurred while saving your answer.']);
}
?>