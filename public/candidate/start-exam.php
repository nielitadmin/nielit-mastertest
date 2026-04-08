<?php
session_name('NIELIT_CANDIDATE_SESSION'); // 🟢 FIX: Keep session name consistent
session_start();

// Check if user is logged in and is candidate
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    header("Location: candidate-login.php");
    exit();
}

// Check if exam ID is provided
if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    header("Location: my-exams.php");
    exit();
}

// 🟢 NEW ARCHITECTURE: Centralized database connection
require_once __DIR__ . '/../../config/database.php';

$exam_id = $_GET['exam_id'];

try {
    // $pdo is securely imported from database.php
    
    // Verify registration and get dynamic counts
    $stmt = $pdo->prepare("
        SELECT er.*, es.exam_date, es.start_time, es.end_time, es.is_practice,
               ec.duration_minutes, ec.category_name, ec.category_code, ec.total_marks,
               es.exam_code,
               (SELECT COUNT(*) FROM questions WHERE category_id = ec.id AND is_active = true) as total_questions
        FROM exam_registrations er
        JOIN exam_sessions es ON er.session_id = es.id
        JOIN exam_categories ec ON es.category_id = ec.id
        WHERE er.candidate_id = ? AND es.id = ? AND er.registration_status IN ('registered', 'approved')
    ");
    $stmt->execute([$_SESSION['user_id'], $exam_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        $_SESSION['error'] = "You are not registered for this exam or it is already completed.";
        header("Location: my-exams.php");
        exit();
    }
    
    // Check if formal exam date is today or future (Skip check if Practice Exam)
    if (!$registration['is_practice']) {
        $exam_datetime = $registration['exam_date'] . ' ' . $registration['start_time'];
        if (strtotime($exam_datetime) > time()) {
            $_SESSION['error'] = "This exam hasn't started yet. Please check the scheduled time.";
            header("Location: my-exams.php");
            exit();
        }
    }
    
    // Check if already answered (get question count)
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM candidate_responses cr
        JOIN exam_registrations er ON cr.registration_id = er.id
        WHERE er.candidate_id = ? AND er.session_id = ?
    ");
    $check->execute([$_SESSION['user_id'], $exam_id]);
    $answered_count = $check->fetchColumn();
    
    if ($answered_count > 0) {
        // Already started - resume
        header("Location: take-exam.php?exam_id=$exam_id");
        exit();
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start Exam - NIELIT CBT</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .start-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            width: 600px;
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #0047ab;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
        }
        .exam-info {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .info-row {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-label {
            width: 140px;
            font-weight: 600;
            color: #555;
        }
        .info-value {
            flex: 1;
            color: #0047ab;
            font-weight: 500;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .warning-box h3 {
            color: #856404;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .warning-box ul {
            margin-left: 20px;
            color: #856404;
        }
        .warning-box li {
            margin-bottom: 8px;
        }
        .checkbox-container {
            margin-bottom: 25px;
        }
        .checkbox-container label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 16px;
        }
        .checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        .btn-start {
            width: 100%;
            padding: 15px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-start:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }
        .btn-start:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .btn-back {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="start-container">
        <div class="header">
            <h1>NIELIT Bhubaneswar</h1>
            <p>Computer Based Test (CBT) System</p>
        </div>
        
        <h2 style="margin-bottom: 20px; color: #0047ab;">Exam: <?php echo htmlspecialchars($registration['exam_code']); ?></h2>
        
        <div class="exam-info">
            <div class="info-row">
                <span class="info-label">Category:</span>
                <span class="info-value"><?php echo htmlspecialchars($registration['category_code']); ?> - <?php echo htmlspecialchars($registration['category_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Duration:</span>
                <span class="info-value"><?php echo $registration['duration_minutes']; ?> Minutes</span>
            </div>
            <div class="info-row">
                <span class="info-label">Total Questions:</span>
                <span class="info-value"><?php echo $registration['total_questions'] ?? 50; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Maximum Marks:</span>
                <span class="info-value"><?php echo $registration['total_marks'] ?? 100; ?></span>
            </div>
        </div>
        
        <div class="warning-box">
            <h3>⚠️ Important Instructions</h3>
            <ul>
                <li>The exam will be in <strong>full-screen mode</strong>. Do not attempt to exit full screen.</li>
                <li>Do not switch tabs or windows during the exam. This may be considered malpractice.</li>
                <li>You can navigate between questions using the <strong>Question Palette</strong>.</li>
                <li>Answers are <strong>auto-saved</strong> every few seconds.</li>
                <li>The timer will be displayed on the top right corner.</li>
                <li>The exam will <strong>auto-submit</strong> when time expires.</li>
                <li>Once submitted, you cannot change your answers.</li>
            </ul>
        </div>
        
        <div class="checkbox-container">
            <label>
                <input type="checkbox" id="agreeCheck" onchange="toggleStartButton()">
                <span>I have read and understood all the instructions and agree to abide by the exam rules.</span>
            </label>
        </div>
        
        <button class="btn-start" id="startBtn" disabled onclick="startExam()">Start Exam</button>
        
        <a href="my-exams.php" class="btn-back">← Back to My Exams</a>
    </div>
    
    <script>
        function toggleStartButton() {
            const agreeCheck = document.getElementById('agreeCheck');
            const startBtn = document.getElementById('startBtn');
            startBtn.disabled = !agreeCheck.checked;
        }
        
        function startExam() {
            // Request full screen
            if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen();
            }
            
            // Redirect to exam interface
            window.location.href = 'take-exam.php?exam_id=<?php echo $exam_id; ?>';
        }
    </script>
</body>
</html>