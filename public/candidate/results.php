<?php
session_name('NIELIT_CANDIDATE_SESSION');
session_start();

// Check if user is logged in and is candidate
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    header("Location: candidate-login.php");
    exit();
}

// 🟢 NEW ARCHITECTURE: Centralized database connection
require_once __DIR__ . '/../../config/database.php';

try {
    // $pdo is securely imported from database.php
    
    // Get all results for this candidate
    $results = $pdo->prepare("
        SELECT 
            er.*,
            es.id as exam_id,
            es.exam_code,
            ec.category_name,
            ec.category_code,
            es.exam_date,
            u.full_name,
            c.registration_number
        FROM exam_results er
        JOIN exam_registrations reg ON er.registration_id = reg.id
        JOIN exam_sessions es ON reg.session_id = es.id
        JOIN exam_categories ec ON es.category_id = ec.id
        JOIN users u ON reg.candidate_id = u.id
        JOIN candidates c ON u.id = c.user_id
        WHERE reg.candidate_id = ?
        ORDER BY er.published_at DESC
    ");
    $results->execute([$_SESSION['user_id']]);
    $my_results = $results->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results - NIELIT CBT</title>
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
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        .header {
            background: white;
            padding: 2rem;
            border-radius: 24px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #2563eb;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .result-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .result-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
        }
        .result-code {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .result-category {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        .result-body {
            padding: 1.5rem;
        }
        .score-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
        }
        .score-pass {
            background: #10b981;
            color: white;
        }
        .score-fail {
            background: #ef4444;
            color: white;
        }
        .result-detail {
            text-align: center;
            margin-bottom: 1rem;
        }
        .result-marks {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }
        .result-percentage {
            font-size: 1rem;
            color: #64748b;
        }
        .result-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .result-date {
            font-size: 0.875rem;
            color: #64748b;
        }
        .btn-view {
            padding: 0.5rem 1rem;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .btn-view:hover {
            background: #1e40af;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 20px;
            grid-column: 1 / -1;
        }
        .empty-state i {
            font-size: 3rem;
            color: #94a3b8;
            margin-bottom: 1rem;
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
                <span>My Results</span>
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
        <div class="header">
            <h1><i class="fas fa-chart-bar"></i> My Exam Results</h1>
        </div>
        
        <?php if (isset($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="results-grid">
            <?php if (empty($my_results)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Results Found</h3>
                    <p>You haven't taken any exams yet or results are not published.</p>
                    <a href="available-exams.php" style="color: #2563eb; text-decoration: none;">Browse Available Exams →</a>
                </div>
            <?php else: ?>
                <?php foreach ($my_results as $result): ?>
                    <div class="result-card">
                        <div class="result-header">
                            <div class="result-code"><?php echo htmlspecialchars($result['exam_code']); ?></div>
                            <div class="result-category"><?php echo htmlspecialchars($result['category_code']); ?> - <?php echo htmlspecialchars($result['category_name']); ?></div>
                        </div>
                        <div class="result-body">
                            <div class="score-circle score-<?php echo htmlspecialchars($result['result_status']); ?>">
                                <?php echo round($result['percentage']); ?>%
                            </div>
                            <div class="result-detail">
                                <div class="result-marks"><?php echo $result['total_marks_obtained']; ?> / <?php echo $result['total_marks']; ?></div>
                                <div class="result-percentage"><?php echo round($result['percentage'], 2); ?>%</div>
                            </div>
                        </div>
                        <div class="result-footer">
                            <span class="result-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d M Y', strtotime($result['published_at'])); ?>
                            </span>
                            <a href="exam-result.php?exam_id=<?php echo $result['exam_id']; ?>" class="btn-view">
                                View Details <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <a href="candidate-dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</body>
</html>