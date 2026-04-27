<?php
session_name('NIELIT_COORD_SESSION');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'coordinator') {
    header("Location: coordinator-login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$success = ''; $error = '';

// Handle the same Scheduling POST request as the dashboard
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['schedule_booking'])) {
    // (You can copy the exact same $pdo->beginTransaction() scheduling logic from your dashboard here)
    // For brevity in viewing, we assume the coordinator uses the dashboard for quick scheduling, 
    // and this page for master viewing.
}

// Fetch all bookings (Not just pending)
$batches = [];
try {
    $stmt = $pdo->query("
        SELECT b.*, tp.full_name as tp_name, c.category_name, c.category_code 
        FROM slot_bookings b 
        JOIN users tp ON b.tp_id = tp.id 
        JOIN exam_categories c ON b.category_id = c.id 
        ORDER BY b.requested_date DESC
    ");
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) { $error = "Failed to load batches."; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Schedule - Coordinator</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #7C3AED; --primary-hover: #6D28D9; --primary-bg: #EDE9FE;
            --text-dark: #0F172A; --text-muted: #64748B; --bg-body: #F8FAFC; 
            --border: #E2E8F0; --success: #059669; --warning: #D97706; --info: #3B82F6;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-body); color: var(--text-dark); margin: 0; padding-bottom: 50px;}
        .top-nav { background: white; padding: 15px 40px; display: flex; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .btn-back { color: var(--text-dark); text-decoration: none; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .btn-back:hover { color: var(--primary); }
        .container { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
        
        .header { margin-bottom: 30px; }
        .header h1 { font-size: 26px; font-weight: 800; color: var(--primary); margin-bottom: 5px;}
        
        .card { background: white; border-radius: 16px; border: 1px solid var(--border); overflow-x: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th { background: #F8FAFC; padding: 15px 20px; text-align: left; font-size: 12px; color: var(--text-muted); text-transform: uppercase; border-bottom: 2px solid var(--border); }
        td { padding: 15px 20px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 500; vertical-align: middle; }
        tr:hover td { background: var(--bg-body); }
        
        .badge { padding: 5px 12px; border-radius: 50px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-pending { background: #FEF3C7; color: var(--warning); }
        .badge-approved { background: #DBEAFE; color: var(--info); }
        .badge-scheduled { background: #D1FAE5; color: var(--success); }
        .badge-rejected { background: #FEE2E2; color: #DC2626; }
    </style>
</head>
<body>
    <nav class="top-nav">
        <a href="coordinator-dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <div style="font-weight: 700; color: var(--text-muted);">Master Scheduling Ledger</div>
    </nav>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-calendar-alt"></i> Institute Batch Requests</h1>
            <p style="color: var(--text-muted); font-weight: 500;">Complete history of all batch slot bookings requested by Training Partners.</p>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Req ID</th>
                        <th>Institute (TP) Name</th>
                        <th>Exam Module</th>
                        <th>Requested Date & Time</th>
                        <th>Candidates</th>
                        <th>Current Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($batches)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">No batch requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach($batches as $b): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad($b['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars($b['tp_name']); ?></td>
                            <td>
                                <strong style="color: var(--primary);"><?php echo $b['category_code']; ?></strong><br>
                                <span style="font-size: 12px; color: var(--text-muted);"><?php echo $b['category_name']; ?></span>
                            </td>
                            <td>
                                <strong><?php echo date('d M Y', strtotime($b['requested_date'])); ?></strong><br>
                                <span style="font-size: 12px; color: var(--text-muted);"><?php echo date('h:i A', strtotime($b['requested_time'])); ?></span>
                            </td>
                            <td><i class="fas fa-users" style="color: var(--text-muted);"></i> <?php echo $b['estimated_candidates']; ?></td>
                            <td>
                                <?php 
                                    $statusClass = 'pending';
                                    if ($b['status'] == 'Approved for Scheduling') $statusClass = 'approved';
                                    if ($b['status'] == 'Scheduled') $statusClass = 'scheduled';
                                    if ($b['status'] == 'Rejected') $statusClass = 'rejected';
                                ?>
                                <span class="badge badge-<?php echo $statusClass; ?>"><?php echo $b['status']; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>