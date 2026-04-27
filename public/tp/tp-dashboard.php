<?php
session_name('NIELIT_TP_SESSION');
session_start();

// Ensure user is logged in and is a Training Partner
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'tp') {
    header("Location: tp-login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$tp_id = $_SESSION['user_id'];
$error = '';
$full_name = $_SESSION['full_name'] ?? 'Training Partner';

// Fetch TP specific stats
$stats = [
    'total_candidates' => 0,
    'pending_bookings' => 0,
    'approved_bookings' => 0,
    'total_paid' => 0
];

try {
    // Get total candidates registered by this TP
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE tp_id = ?");
    $stmt->execute([$tp_id]);
    $stats['total_candidates'] = $stmt->fetchColumn();

    // Get booking stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'Pending Payment' OR status = 'Verification Pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'Approved for Scheduling' OR status = 'Scheduled' THEN 1 END) as approved,
            COALESCE(SUM(CASE WHEN status != 'Pending Payment' AND status != 'Rejected' THEN total_fee ELSE 0 END), 0) as paid
        FROM slot_bookings 
        WHERE tp_id = ?
    ");
    $stmt->execute([$tp_id]);
    $booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['pending_bookings'] = $booking_stats['pending'] ?? 0;
    $stats['approved_bookings'] = $booking_stats['approved'] ?? 0;
    $stats['total_paid'] = $booking_stats['paid'] ?? 0;

    // Fetch recent bookings for the table
    $stmt = $pdo->prepare("
        SELECT b.*, c.category_name, c.category_code 
        FROM slot_bookings b
        JOIN exam_categories c ON b.category_id = c.id
        WHERE b.tp_id = ?
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$tp_id]);
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TP Dashboard - NIELIT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #059669; /* Green theme for TP */
            --primary-light: #10B981;
            --primary-bg: #D1FAE5;
            --secondary: #0F172A;
            --warning: #D97706; --warning-bg: #FEF3C7;
            --info: #2563EB; --info-bg: #DBEAFE;
            --text-dark: #0F172A; --text-muted: #64748B;
            --bg-body: #F8FAFC; --surface: #FFFFFF; --border: #E2E8F0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            --radius-md: 12px; --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; }

        /* Navbar */
        .top-nav { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--border); }
        .nav-left { display: flex; align-items: center; gap: 15px; }
        .logo-box { width: 40px; height: 40px; background: var(--primary); color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--secondary); margin-bottom: 2px; }
        .brand-text span { font-size: 11px; color: var(--primary); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .btn-logout { background: #FEE2E2; color: #DC2626; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.3s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-logout:hover { background: #DC2626; color: white; }

        .container { max-width: 1400px; margin: 30px auto; padding: 0 40px; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .greeting h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); margin-bottom: 5px; }
        .greeting p { color: var(--text-muted); font-weight: 500; font-size: 15px; }
        
        .quick-actions { display: flex; gap: 15px; }
        .btn-action { background: var(--primary); color: white; padding: 12px 24px; border-radius: var(--radius-md); text-decoration: none; font-weight: 700; font-size: 14px; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.2); }
        .btn-action:hover { background: #047857; transform: translateY(-2px); }
        .btn-secondary { background: white; color: var(--text-dark); border: 1px solid var(--border); box-shadow: var(--shadow-sm); }
        .btn-secondary:hover { background: var(--bg-body); border-color: #CBD5E1; color: var(--primary); }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 20px; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); }
        .stat-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .icon-blue { background: var(--info-bg); color: var(--info); }
        .icon-yellow { background: var(--warning-bg); color: var(--warning); }
        .icon-green { background: var(--primary-bg); color: var(--primary); }
        .icon-purple { background: #EDE9FE; color: #8B5CF6; }
        .stat-info h3 { font-size: 28px; font-weight: 800; color: var(--text-dark); margin-bottom: 4px; line-height: 1; }
        .stat-info p { font-size: 13px; font-weight: 600; color: var(--text-muted); }

        /* Table Section */
        .table-card { background: white; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 50px;}
        .table-header { padding: 20px 25px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .table-header h2 { font-size: 18px; font-weight: 800; color: var(--text-dark); }
        
        table { width: 100%; border-collapse: collapse; }
        th { background: #F8FAFC; color: var(--text-muted); font-size: 12px; font-weight: 700; text-transform: uppercase; padding: 15px 25px; text-align: left; }
        td { padding: 18px 25px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600; color: var(--text-dark); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--bg-body); }
        
        .badge { padding: 6px 12px; border-radius: 50px; font-size: 11px; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; }
        .badge-pending { background: #FEF3C7; color: #B45309; }
        .badge-verify { background: #DBEAFE; color: #1D4ED8; }
        .badge-approved { background: #EDE9FE; color: #6D28D9; }
        .badge-scheduled { background: var(--primary-bg); color: var(--primary); }
        .badge-rejected { background: #FEE2E2; color: #DC2626; }

        .btn-track { background: white; border: 1px solid var(--border); padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: 0.2s; color: var(--secondary); display: inline-flex; align-items: center; gap: 5px; margin-left: 10px;}
        .btn-track:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }

        /* --- TRACKING MODAL --- */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1000; display: none; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; }
        .modal-overlay.active { display: flex; opacity: 1; }
        
        .modal-content { background: white; width: 100%; max-width: 500px; border-radius: 20px; padding: 30px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); transform: translateY(20px); transition: 0.3s; }
        .modal-overlay.active .modal-content { transform: translateY(0); }
        
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid var(--border);}
        .modal-header h3 { font-size: 18px; font-weight: 800; color: var(--secondary); margin: 0; }
        .btn-close { background: none; border: none; font-size: 20px; color: var(--text-muted); cursor: pointer; transition: 0.2s;}
        .btn-close:hover { color: var(--danger); }

        /* Timeline Stepper */
        .timeline { position: relative; padding-left: 30px; margin-top: 20px; }
        .timeline::before { content: ''; position: absolute; left: 11px; top: 10px; bottom: 10px; width: 2px; background: #E2E8F0; z-index: 1; }
        
        .step { position: relative; padding-bottom: 30px; }
        .step:last-child { padding-bottom: 0; }
        
        .step-marker { position: absolute; left: -30px; top: 0; width: 24px; height: 24px; border-radius: 50%; background: white; border: 2px solid #CBD5E1; z-index: 2; display: flex; align-items: center; justify-content: center; font-size: 10px; color: transparent; transition: 0.3s; }
        
        .step.completed .step-marker { background: var(--primary); border-color: var(--primary); color: white; }
        .step.active .step-marker { border-color: var(--info); border-width: 4px; }
        .step.rejected .step-marker { background: var(--danger); border-color: var(--danger); color: white; }

        .step-content h4 { font-size: 15px; font-weight: 800; color: var(--text-dark); margin: 0 0 4px 0; }
        .step-content p { font-size: 13px; color: var(--text-muted); margin: 0; font-weight: 500;}
        
        /* Colored lines for completed steps */
        .step.completed::after { content: ''; position: absolute; left: -19px; top: 24px; bottom: -10px; width: 2px; background: var(--primary); z-index: 1; }
        .step:last-child::after { display: none; }

        @media (max-width: 768px) {
            .top-nav { padding: 15px 20px; }
            .container { padding: 0 20px; }
            .quick-actions { width: 100%; flex-direction: column; }
            .btn-action, .btn-secondary { width: 100%; justify-content: center; }
            .status-cell { display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }
            .btn-track { margin-left: 0; }
        }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="nav-left">
            <div class="logo-box"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="brand-text">
                <h2>Institute Portal</h2>
                <span>Training Partner • NIELIT</span>
            </div>
        </div>
        <div class="nav-right">
            <div style="font-weight: 700; font-size: 14px; margin-right: 10px;" class="hide-mobile">
                <?php echo htmlspecialchars($full_name); ?>
            </div>
            <a href="tp-logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        
        <?php if ($error): ?>
            <div style="background: #FEE2E2; color: #DC2626; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600;"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['payment']) && $_GET['payment'] == 'success'): ?>
            <div style="background: #D1FAE5; color: #059669; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-check-circle" style="font-size: 20px;"></i> 
                Payment Successful & Verified! Your batch is now automatically approved for scheduling.
            </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="greeting">
                <h1>Welcome, <?php $parts = explode(' ', $full_name); echo htmlspecialchars($parts[0] ?? 'User'); ?></h1>
                <p>Manage your students and schedule official examinations.</p>
            </div>
            <div class="quick-actions">
                <a href="tp-manage-candidate.php" class="btn-action btn-secondary"><i class="fas fa-users"></i> Manage Batch</a>
                <a href="tp-book-exam.php" class="btn-action"><i class="fas fa-calendar-plus"></i> Book Exam Slot</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_candidates']); ?></h3>
                    <p>Total Registered Students</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['pending_bookings']); ?></h3>
                    <p>Pending Slot Verifications</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['approved_bookings']); ?></h3>
                    <p>Approved / Scheduled Exams</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-purple"><i class="fas fa-rupee-sign"></i></div>
                <div class="stat-info">
                    <h3>₹<?php echo number_format($stats['total_paid']); ?></h3>
                    <p>Total Examination Fees Paid</p>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h2>Recent Exam Slot Requests</h2>
                <a href="tp-booking-history.php" style="color: var(--primary); text-decoration: none; font-size: 13px; font-weight: 700;">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Req ID</th>
                            <th>Module</th>
                            <th>Requested Schedule</th>
                            <th>Batch Size</th>
                            <th>Total Fee</th>
                            <th>Live Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_bookings)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fas fa-folder-open" style="font-size: 32px; margin-bottom: 10px; color: #CBD5E1;"></i><br>
                                    You have not submitted any exam slot requests yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_bookings as $b): 
                                $status = $b['status'];
                                $badge_class = 'badge-pending';
                                $icon = 'fa-clock';
                                
                                if ($status == 'Verification Pending') { $badge_class = 'badge-verify'; $icon = 'fa-file-invoice-dollar'; }
                                elseif ($status == 'Approved for Scheduling') { $badge_class = 'badge-approved'; $icon = 'fa-thumbs-up'; }
                                elseif ($status == 'Scheduled') { $badge_class = 'badge-scheduled'; $icon = 'fa-calendar-check'; }
                                elseif ($status == 'Rejected') { $badge_class = 'badge-rejected'; $icon = 'fa-times-circle'; }
                            ?>
                            <tr>
                                <td style="color: var(--text-muted);">#<?php echo $b['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($b['category_code']); ?></strong><br>
                                    <span style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($b['category_name']); ?></span>
                                </td>
                                <td>
                                    <i class="far fa-calendar-alt" style="color: var(--primary); margin-right: 5px;"></i> <?php echo date('d M Y', strtotime($b['requested_date'])); ?><br>
                                    <span style="font-size: 12px; color: var(--text-muted);"><i class="far fa-clock" style="margin-right: 5px;"></i> <?php echo date('h:i A', strtotime($b['requested_time'])); ?></span>
                                </td>
                                <td><i class="fas fa-users" style="color: var(--text-muted);"></i> <?php echo $b['estimated_candidates']; ?> Students</td>
                                <td>₹<?php echo number_format($b['total_fee'], 2); ?></td>
                                <td class="status-cell">
                                    <span class="badge <?php echo $badge_class; ?>"><i class="fas <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($status); ?></span>
                                    <button class="btn-track" onclick="openTracker('<?php echo $b['id']; ?>', '<?php echo $status; ?>', '<?php echo $b['category_code']; ?>')">
                                        <i class="fas fa-map-marker-alt"></i> Track
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="modal-overlay" id="trackingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Application Tracker</h3>
                <button class="btn-close" onclick="closeTracker()"><i class="fas fa-times"></i></button>
            </div>
            
            <div style="background: #F8FAFC; padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 700; margin-bottom: 20px; border: 1px dashed var(--border);">
                Req ID: <span id="modalReqId" style="color: var(--primary);">#---</span> | Module: <span id="modalModule">---</span>
            </div>

            <div class="timeline">
                
                <div class="step" id="step1">
                    <div class="step-marker"><i class="fas fa-check"></i></div>
                    <div class="step-content">
                        <h4>Application Submitted</h4>
                        <p id="desc1">Slot requested and pending fee payment.</p>
                    </div>
                </div>

                <div class="step" id="step2">
                    <div class="step-marker"><i class="fas fa-check"></i></div>
                    <div class="step-content">
                        <h4>Finance Verification</h4>
                        <p id="desc2">Payment processed securely via Razorpay gateway.</p>
                    </div>
                </div>

                <div class="step" id="step3">
                    <div class="step-marker"><i class="fas fa-check"></i></div>
                    <div class="step-content">
                        <h4>Approved for Scheduling</h4>
                        <p id="desc3">Payment cleared. Awaiting test center allocation.</p>
                    </div>
                </div>

                <div class="step" id="step4">
                    <div class="step-marker"><i class="fas fa-check"></i></div>
                    <div class="step-content">
                        <h4>Exam Scheduled</h4>
                        <p id="desc4">Center assigned. Admit cards generated for students.</p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function openTracker(reqId, status, module) {
            document.getElementById('modalReqId').innerText = '#' + reqId;
            document.getElementById('modalModule').innerText = module;
            
            // Reset all steps
            for(let i=1; i<=4; i++) {
                document.getElementById('step'+i).className = 'step';
            }

            // Logic to color the steps based on the database status
            if (status === 'Pending Payment') {
                document.getElementById('step1').classList.add('active');
                
            } else if (status === 'Verification Pending') {
                document.getElementById('step1').classList.add('completed');
                document.getElementById('step2').classList.add('active');
                document.getElementById('desc1').innerText = 'Fee payment successfully submitted.';
                
            } else if (status === 'Approved for Scheduling') {
                document.getElementById('step1').classList.add('completed');
                document.getElementById('step2').classList.add('completed');
                document.getElementById('step3').classList.add('active');
                document.getElementById('desc1').innerText = 'Fee payment successfully submitted.';
                document.getElementById('desc2').innerText = 'Instant Verification via Razorpay.';
                
            } else if (status === 'Scheduled') {
                document.getElementById('step1').classList.add('completed');
                document.getElementById('step2').classList.add('completed');
                document.getElementById('step3').classList.add('completed');
                document.getElementById('step4').classList.add('completed');
                document.getElementById('desc1').innerText = 'Fee payment successfully submitted.';
                document.getElementById('desc2').innerText = 'Instant Verification via Razorpay.';
                document.getElementById('desc3').innerText = 'Cleared for center allocation.';
                document.getElementById('desc4').innerText = 'Live exam generated successfully.';
                
            } else if (status === 'Rejected') {
                document.getElementById('step1').classList.add('completed');
                document.getElementById('step2').classList.add('rejected');
                document.getElementById('step2').querySelector('.step-marker').innerHTML = '<i class="fas fa-times"></i>';
                document.getElementById('desc2').innerText = 'Payment rejected or failed.';
                document.getElementById('desc2').style.color = '#DC2626';
            }

            document.getElementById('trackingModal').classList.add('active');
        }

        function closeTracker() {
            document.getElementById('trackingModal').classList.remove('active');
            
            // Revert rejection text styles on close so it resets cleanly
            document.getElementById('desc2').style.color = '';
            document.getElementById('step2').querySelector('.step-marker').innerHTML = '<i class="fas fa-check"></i>';
        }
    </script>
</body>
</html>