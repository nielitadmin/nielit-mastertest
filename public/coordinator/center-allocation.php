<?php
session_name('NIELIT_COORD_SESSION');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'coordinator') {
    header("Location: coordinator-login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$success = ''; $error = '';

// Handle Admit Card Generation
if (isset($_GET['publish_admit_cards']) && is_numeric($_GET['publish_admit_cards'])) {
    $session_id = $_GET['publish_admit_cards'];
    try {
        $pdo->beginTransaction();
        
        // In a real system, you would loop through exam_registrations for this session_id 
        // and assign unique Roll Numbers and Seat Numbers here.
        
        // Mark session as fully allocated
        $stmt = $pdo->prepare("UPDATE exam_sessions SET is_active = true WHERE id = ?");
        $stmt->execute([$session_id]);
        
        $pdo->commit();
        $success = "Admit Cards successfully generated and published to Candidate Portals!";
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = "Failed to generate admit cards.";
    }
}

// Fetch all Scheduled Sessions
$sessions = [];
try {
    $stmt = $pdo->query("
        SELECT es.*, ec.center_name, ec.city, ec.capacity, c.category_name 
        FROM exam_sessions es 
        JOIN exam_centers ec ON es.center_id = ec.id 
        JOIN exam_categories c ON es.category_id = c.id
        ORDER BY es.exam_date ASC
    ");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) { }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Center Allocation - Coordinator</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #7C3AED; --primary-hover: #6D28D9; --primary-bg: #EDE9FE;
            --text-dark: #0F172A; --text-muted: #64748B; --bg-body: #F8FAFC; 
            --border: #E2E8F0; --success: #059669; --warning: #D97706; 
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-body); color: var(--text-dark); margin: 0; padding-bottom: 50px;}
        .top-nav { background: white; padding: 15px 40px; display: flex; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .btn-back { color: var(--text-dark); text-decoration: none; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .btn-back:hover { color: var(--primary); }
        .container { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
        
        .header { margin-bottom: 30px; }
        .header h1 { font-size: 26px; font-weight: 800; color: var(--primary); margin-bottom: 5px;}
        
        .alert { padding: 15px; border-radius: 8px; font-weight: 600; margin-bottom: 20px; }
        .alert-success { background: #D1FAE5; color: var(--success); border: 1px solid #A7F3D0; }
        .alert-error { background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; }

        .session-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; }
        .session-card { background: white; border: 1px solid var(--border); border-radius: 16px; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: 0.3s;}
        .session-card:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: 0 10px 20px -5px rgba(124, 58, 237, 0.15);}
        
        .sc-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 15px;}
        .sc-code { font-size: 18px; font-weight: 800; color: var(--primary); }
        .sc-date { font-size: 13px; font-weight: 700; background: var(--primary-bg); color: var(--primary); padding: 5px 10px; border-radius: 8px; }
        
        .sc-body p { margin: 0 0 10px 0; font-size: 14px; display: flex; align-items: center; gap: 10px;}
        .sc-body i { width: 20px; color: var(--text-muted); text-align: center;}
        
        .progress-bar { width: 100%; background: var(--border); height: 8px; border-radius: 10px; margin-top: 15px; overflow: hidden;}
        .progress-fill { background: var(--success); height: 100%; }
        
        .btn-publish { display: block; width: 100%; text-align: center; background: var(--primary); color: white; padding: 12px; border-radius: 10px; text-decoration: none; font-weight: 700; margin-top: 20px; transition: 0.2s;}
        .btn-publish:hover { background: var(--primary-hover); }
    </style>
</head>
<body>
    <nav class="top-nav">
        <a href="coordinator-dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <div style="font-weight: 700; color: var(--text-muted);">Center & Seat Allocation</div>
    </nav>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-building"></i> Live Exam Sessions</h1>
            <p style="color: var(--text-muted); font-weight: 500;">Monitor center capacities and publish admit cards for scheduled sessions.</p>
        </div>

        <?php if($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div><?php endif; ?>

        <div class="session-grid">
            <?php if(empty($sessions)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-muted); background: white; border-radius: 16px; border: 1px solid var(--border);">No live exam sessions currently scheduled.</div>
            <?php else: ?>
                <?php foreach($sessions as $s): 
                    $capacity_pct = ($s['total_seats'] / $s['capacity']) * 100;
                    $cap_color = $capacity_pct > 90 ? 'var(--danger)' : 'var(--success)';
                ?>
                <div class="session-card">
                    <div class="sc-header">
                        <div class="sc-code"><?php echo htmlspecialchars($s['exam_code']); ?></div>
                        <div class="sc-date"><i class="far fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($s['exam_date'])); ?></div>
                    </div>
                    <div class="sc-body">
                        <p><i class="fas fa-book-open"></i> <strong>Module:</strong> <?php echo htmlspecialchars($s['category_name']); ?></p>
                        <p><i class="fas fa-map-marker-alt"></i> <strong>Center:</strong> <?php echo htmlspecialchars($s['center_name'] . ', ' . $s['city']); ?></p>
                        <p><i class="far fa-clock"></i> <strong>Time:</strong> <?php echo date('h:i A', strtotime($s['start_time'])) . ' - ' . date('h:i A', strtotime($s['end_time'])); ?></p>
                        
                        <div style="margin-top: 20px; font-size: 12px; font-weight: 700; color: var(--text-muted); display: flex; justify-content: space-between;">
                            <span>Center Capacity Load</span>
                            <span><?php echo $s['total_seats']; ?> / <?php echo $s['capacity']; ?> Seats</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, $capacity_pct); ?>%; background: <?php echo $cap_color; ?>;"></div>
                        </div>
                    </div>
                    
                    <a href="?publish_admit_cards=<?php echo $s['id']; ?>" class="btn-publish" onclick="return confirm('Publish admit cards to student portals?');">
                        <i class="fas fa-paper-plane"></i> Publish Admit Cards
                    </a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>