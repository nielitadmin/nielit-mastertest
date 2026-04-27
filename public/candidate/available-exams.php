<?php
session_start();

// Check if user is logged in and is candidate
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    header("Location: candidate-login.php");
    exit();
}

// 🟢 NEW ARCHITECTURE: Centralized database connection
require_once __DIR__ . '/../../config/database.php';

// Get filter parameters
$category = $_GET['category'] ?? '';
$center = $_GET['center'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

try {
    // $pdo is securely imported from database.php
    
    // Get categories for filter
    $categories = $pdo->query("SELECT id, category_code, category_name FROM exam_categories ORDER BY category_code")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get centers for filter
    $centers = $pdo->query("SELECT id, center_code, center_name, city FROM exam_centers WHERE is_active = true ORDER BY center_code")->fetchAll(PDO::FETCH_ASSOC);
    
    // Build query for available exams
    $query = "
        SELECT 
            es.*,
            ec.category_name,
            ec.category_code,
            ec.duration_minutes,
            c.center_name,
            c.center_code,
            c.city,
            c.address,
            c.capacity,
            (SELECT COUNT(*) FROM exam_registrations WHERE session_id = es.id) as registered_count
        FROM exam_sessions es
        JOIN exam_categories ec ON es.category_id = ec.id
        JOIN exam_centers c ON es.center_id = c.id
        WHERE es.is_active = true 
        AND es.exam_date >= CURRENT_DATE
        AND es.id NOT IN (
            SELECT session_id FROM exam_registrations WHERE candidate_id = ?
        )
    ";
    
    $params = [$_SESSION['user_id']];
    
    if (!empty($category)) {
        $query .= " AND es.category_id = ?";
        $params[] = $category;
    }
    
    if (!empty($center)) {
        $query .= " AND es.center_id = ?";
        $params[] = $center;
    }
    
    if (!empty($date_from)) {
        $query .= " AND es.exam_date >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $query .= " AND es.exam_date <= ?";
        $params[] = $date_to;
    }
    
    $query .= " ORDER BY es.exam_date ASC, es.start_time ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Exams - NIELIT CBT</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
        }
        .navbar {
            background: #0047ab;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h2 {
            font-size: 20px;
        }
        .navbar .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
            margin-left: 10px;
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #0047ab;
            margin-bottom: 10px;
        }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            font-size: 12px;
            color: #666;
            margin-bottom: 3px;
        }
        .filter-group select, .filter-group input {
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        .btn-filter {
            background: #0047ab;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-reset {
            background: #6c757d;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
        }
        .exams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .exam-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .exam-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .exam-header {
            background: #0047ab;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .exam-code {
            font-size: 18px;
            font-weight: bold;
        }
        .seats-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #28a745;
            color: white;
        }
        .seats-badge.low {
            background: #ffc107;
            color: #333;
        }
        .seats-badge.full {
            background: #dc3545;
        }
        .exam-body {
            padding: 15px;
        }
        .info-row {
            display: flex;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .info-label {
            width: 90px;
            color: #666;
        }
        .info-value {
            flex: 1;
            font-weight: 500;
        }
        .exam-footer {
            padding: 15px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            flex: 1;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #0047ab;
            color: white;
        }
        .btn-primary:hover {
            background: #003380;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            cursor: not-allowed;
        }
        .empty-state {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 10px;
            grid-column: 1 / -1;
        }
        .empty-state h3 {
            color: #666;
            margin-bottom: 15px;
        }
        .pagination {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .page-link {
            padding: 8px 12px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }
        .page-link.active {
            background: #0047ab;
            color: white;
            border-color: #0047ab;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>NIELIT Bhubaneswar - Available Exams</h2>
        <div class="nav-links">
            <a href="candidate-dashboard.php">Dashboard</a>
            <a href="my-exams.php">My Exams</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="header">
            <h1>Available Exams for Registration</h1>
            <p>Browse and register for upcoming NIELIT exams</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="GET" class="filters">
            <div class="filter-group">
                <label>Category</label>
                <select name="category">
                    <option value="">All Categories</option>
                    <?php if(isset($categories)) foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo $cat['category_code']; ?> - <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Center</label>
                <select name="center">
                    <option value="">All Centers</option>
                    <?php if(isset($centers)) foreach ($centers as $cen): ?>
                        <option value="<?php echo $cen['id']; ?>" <?php echo $center == $cen['id'] ? 'selected' : ''; ?>>
                            <?php echo $cen['center_code']; ?> - <?php echo htmlspecialchars($cen['center_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-filter">Apply Filters</button>
                <a href="available-exams.php" class="btn-reset">Reset</a>
            </div>
        </form>
        
        <div class="exams-grid">
            <?php if (empty($exams)): ?>
                <div class="empty-state">
                    <h3>No exams available</h3>
                    <p>There are no exams matching your criteria at the moment.</p>
                    <p style="margin-top: 15px;">Check back later or <a href="available-exams.php">clear filters</a>.</p>
                </div>
            <?php else: ?>
                <?php foreach ($exams as $exam): 
                    $seats_left = $exam['capacity'] - $exam['registered_count'];
                    $seats_class = 'seats-badge';
                    if ($seats_left <= 0) {
                        $seats_class .= ' full';
                    } elseif ($seats_left < 10) {
                        $seats_class .= ' low';
                    }
                ?>
                <div class="exam-card">
                    <div class="exam-header">
                        <span class="exam-code"><?php echo htmlspecialchars($exam['exam_code']); ?></span>
                        <span class="<?php echo $seats_class; ?>">
                            <?php 
                            if ($seats_left <= 0) echo 'Full';
                            else echo $seats_left . ' seats left';
                            ?>
                        </span>
                    </div>
                    <div class="exam-body">
                        <div class="info-row">
                            <span class="info-label">Category:</span>
                            <span class="info-value"><?php echo htmlspecialchars($exam['category_code']); ?> - <?php echo htmlspecialchars($exam['category_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Center:</span>
                            <span class="info-value"><?php echo htmlspecialchars($exam['center_name']); ?>, <?php echo htmlspecialchars($exam['city']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date:</span>
                            <span class="info-value"><?php echo date('l, d F Y', strtotime($exam['exam_date'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Time:</span>
                            <span class="info-value"><?php echo date('h:i A', strtotime($exam['start_time'])); ?> - <?php echo date('h:i A', strtotime($exam['end_time'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Duration:</span>
                            <span class="info-value"><?php echo $exam['duration_minutes']; ?> minutes</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Registered:</span>
                            <span class="info-value"><?php echo $exam['registered_count']; ?>/<?php echo $exam['capacity']; ?> candidates</span>
                        </div>
                    </div>
                    <div class="exam-footer">
                        <?php if ($seats_left > 0): ?>
                            <a href="register-exam.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-success">Register Now</a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>No Seats</button>
                        <?php endif; ?>
                        <a href="exam-details.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-primary">Details</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($exams) && count($exams) > 12): ?>
        <div class="pagination">
            <a href="#" class="page-link">« Previous</a>
            <a href="#" class="page-link active">1</a>
            <a href="#" class="page-link">2</a>
            <a href="#" class="page-link">3</a>
            <a href="#" class="page-link">Next »</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>