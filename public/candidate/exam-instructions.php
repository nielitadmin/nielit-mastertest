<?php
session_name('NIELIT_CANDIDATE_SESSION');
session_start();

// Check if user is logged in and is candidate
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    header("Location: candidate-login.php");
    exit();
}

// Check if exam ID is provided
if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    header("Location: available-exams.php");
    exit();
}

// 🟢 NEW ARCHITECTURE: Centralized database connection
require_once __DIR__ . '/../../config/database.php';

$exam_id = $_GET['exam_id'];

try {
    // $pdo is securely imported from database.php
    
    // Get exam details
    $stmt = $pdo->prepare("
        SELECT 
            es.*,
            ec.category_name,
            ec.category_code,
            ec.duration_minutes,
            ec.pass_percentage,
            c.center_name,
            c.center_code,
            c.city,
            c.address,
            c.capacity,
            (SELECT COUNT(*) FROM exam_registrations WHERE session_id = es.id) as registered_count
        FROM exam_sessions es
        JOIN exam_categories ec ON es.category_id = ec.id
        JOIN exam_centers c ON es.center_id = c.id
        WHERE es.id = ? AND es.is_active = true
    ");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        header("Location: available-exams.php");
        exit();
    }
    
    // Check if already registered
    $check = $pdo->prepare("SELECT id FROM exam_registrations WHERE candidate_id = ? AND session_id = ?");
    $check->execute([$_SESSION['user_id'], $exam_id]);
    $is_registered = $check->fetch() ? true : false;
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Details - NIELIT CBT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }
        .navbar {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .logo-placeholder {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem 1rem;
            background: #f1f5f9;
            border-radius: 40px;
        }
        .user-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        .card {
            background: white;
            padding: 2rem;
            border-radius: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        h1 {
            color: #2563eb;
            font-size: 1.5rem;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .status-available {
            background: #dbeafe;
            color: #2563eb;
        }
        .status-registered {
            background: #10b981;
            color: white;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .info-item {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 16px;
        }
        .info-label {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .info-value {
            font-weight: 700;
            font-size: 1.25rem;
            color: #1e293b;
        }
        .seats-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
        }
        .seats-left {
            font-size: 1.5rem;
            font-weight: 700;
            color: #10b981;
        }
        .seats-total {
            color: #64748b;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        .btn {
            padding: 0.75rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-primary:hover {
            background: #1e40af;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        .btn-secondary:hover {
            background: #475569;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 2rem;
            color: #64748b;
            text-decoration: none;
        }
        .back-link:hover {
            color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo-area">
            <div class="logo-placeholder">N</div>
            <div>
                <h2>NIELIT Bhubaneswar</h2>
                <span>Exam Details</span>
            </div>
        </div>
        <div class="nav-links">
            <div class="user-menu">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'C', 0, 1)); ?>
                </div>
                <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Candidate'); ?></span>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="header">
                <h1><i class="fas fa-file-alt"></i> Exam Details</h1>
                <?php if (isset($is_registered)): ?>
                    <span class="status-badge <?php echo $is_registered ? 'status-registered' : 'status-available'; ?>">
                        <i class="fas <?php echo $is_registered ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                        <?php echo $is_registered ? 'Registered' : 'Available'; ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if (isset($exam)): 
                $seats_left = $exam['capacity'] - $exam['registered_count'];
            ?>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-tag"></i> Exam Code</div>
                        <div class="info-value"><?php echo htmlspecialchars($exam['exam_code']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-layer-group"></i> Category</div>
                        <div class="info-value"><?php echo htmlspecialchars($exam['category_code']); ?> - <?php echo htmlspecialchars($exam['category_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-building"></i> Center</div>
                        <div class="info-value"><?php echo htmlspecialchars($exam['center_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-map-marker-alt"></i> City</div>
                        <div class="info-value"><?php echo htmlspecialchars($exam['city']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-calendar"></i> Date</div>
                        <div class="info-value"><?php echo date('l, d F Y', strtotime($exam['exam_date'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-clock"></i> Time</div>
                        <div class="info-value"><?php echo date('h:i A', strtotime($exam['start_time'])); ?> - <?php echo date('h:i A', strtotime($exam['end_time'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-hourglass-half"></i> Duration</div>
                        <div class="info-value"><?php echo $exam['duration_minutes']; ?> minutes</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-percent"></i> Pass Percentage</div>
                        <div class="info-value"><?php echo $exam['pass_percentage']; ?>%</div>
                    </div>
                </div>
                
                <div class="seats-info">
                    <div>
                        <div class="info-label"><i class="fas fa-chair"></i> Available Seats</div>
                        <span class="seats-left"><?php echo max(0, $seats_left); ?></span> / <span class="seats-total"><?php echo $exam['capacity']; ?></span>
                    </div>
                    <div>
                        <div class="info-label"><i class="fas fa-users"></i> Registered</div>
                        <span class="seats-left" style="color: #2563eb;"><?php echo $exam['registered_count']; ?></span>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <?php if (!$is_registered && $seats_left > 0): ?>
                        <a href="register-exam.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-success">
                            <i class="fas fa-check-circle"></i> Register Now
                        </a>
                    <?php elseif ($is_registered): ?>
                        <a href="my-exams.php" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View My Exams
                        </a>
                    <?php endif; ?>
                    <a href="available-exams.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            <?php endif; ?>
            
            <a href="available-exams.php" class="back-link">← Back to Available Exams</a>
        </div>
    </div>
</body>
</html>