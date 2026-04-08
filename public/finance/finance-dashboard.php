<?php
session_name('NIELIT_FINANCE_SESSION');
session_start();

// 🟢 SECURITY FIX: PHP Headers to prevent disk caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Ensure user is logged in and is Finance Officer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'finance') {
    header("Location: finance-login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$finance_id = $_SESSION['user_id'];
$error = '';
$success_msg = '';

try {
    // 1. Handle Approval / Rejection Logic
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        
        $pdo->beginTransaction();
        
        // --- A. Handle Training Partner Bulk Payments ---
        if (isset($_POST['payment_type']) && $_POST['payment_type'] == 'tp') {
            $b_id = $_POST['booking_id'];
            $p_id = $_POST['payment_id'];
            
            if ($_POST['action'] == 'approve') {
                $pdo->prepare("UPDATE payments SET verified_by = ?, verified_at = NOW(), payment_status = 'Verified' WHERE id = ?")->execute([$finance_id, $p_id]);
                $pdo->prepare("UPDATE slot_bookings SET status = 'Approved for Scheduling' WHERE id = ?")->execute([$b_id]);
                $success_msg = "TP Payment verified successfully. Batch is now ready for Exam Scheduling.";
            } elseif ($_POST['action'] == 'reject') {
                $pdo->prepare("UPDATE payments SET verified_by = ?, verified_at = NOW(), payment_status = 'Rejected' WHERE id = ?")->execute([$finance_id, $p_id]);
                $pdo->prepare("UPDATE slot_bookings SET status = 'Rejected' WHERE id = ?")->execute([$b_id]);
                $error = "TP Payment rejected. The Training Partner has been flagged.";
            }
        }
        
        // --- B. Handle Candidate Practice Payments (₹50) ---
        elseif (isset($_POST['payment_type']) && $_POST['payment_type'] == 'candidate') {
            $reg_id = $_POST['reg_id'];
            
            if ($_POST['action'] == 'approve') {
                // Change status from 'payment_submitted' to 'approved' to unlock the exam
                $pdo->prepare("UPDATE exam_registrations SET registration_status = 'approved' WHERE id = ?")->execute([$reg_id]);
                $success_msg = "Candidate payment verified! Practice mode unlocked.";
            } elseif ($_POST['action'] == 'reject') {
                // Kick it back to 'pending_payment' and wipe the bad transaction ID so they can try again
                $pdo->prepare("UPDATE exam_registrations SET registration_status = 'pending_payment', transaction_id = NULL WHERE id = ?")->execute([$reg_id]);
                $error = "Candidate payment rejected. They must submit a new transaction ID.";
            }
        }
        
        $pdo->commit();
    }

    // 2. Fetch Stats
    $stats = [
        'tp_pending' => $pdo->query("SELECT COUNT(*) FROM slot_bookings WHERE status = 'Verification Pending'")->fetchColumn(),
        'tp_approved' => $pdo->query("SELECT COUNT(*) FROM payments WHERE verified_by = $finance_id AND payment_status = 'Verified'")->fetchColumn(),
        'tp_revenue' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'Verified'")->fetchColumn(),
        
        // New Candidate Stats
        'cand_pending' => $pdo->query("SELECT COUNT(*) FROM exam_registrations WHERE registration_status = 'payment_submitted'")->fetchColumn(),
        'cand_approved' => $pdo->query("SELECT COUNT(*) FROM exam_registrations er JOIN exam_sessions es ON er.session_id = es.id WHERE er.registration_status = 'approved' AND es.is_practice = true")->fetchColumn(),
    ];

    // 3. Fetch Pending TP Verifications
    $stmt_tp = $pdo->query("
        SELECT 
            p.id as payment_id, p.transaction_id, p.amount, p.created_at as pay_date, p.payment_proof,
            b.id as booking_id, b.estimated_candidates,
            tp.full_name as tp_name, 
            c.category_code
        FROM payments p
        JOIN slot_bookings b ON p.booking_id = b.id
        JOIN users tp ON b.tp_id = tp.id
        JOIN exam_categories c ON b.category_id = c.id
        WHERE b.status = 'Verification Pending' AND p.verified_by IS NULL
        ORDER BY p.created_at ASC
    ");
    $pending_tp_payments = $stmt_tp->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Fetch Pending Candidate Practice Verifications
    $stmt_cand = $pdo->query("
        SELECT 
            er.id as reg_id, er.transaction_id, er.registered_at, 
            es.exam_code, ec.category_name, 
            u.full_name as candidate_name, u.username as candidate_username
        FROM exam_registrations er
        JOIN exam_sessions es ON er.session_id = es.id
        JOIN exam_categories ec ON es.category_id = ec.id
        JOIN users u ON er.candidate_id = u.id
        WHERE er.registration_status = 'payment_submitted' AND es.is_practice = true
        ORDER BY er.registered_at ASC
    ");
    $pending_cand_payments = $stmt_cand->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $error = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard - NIELIT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1E3A8A; /* Navy Blue Theme */
            --primary-light: #3B82F6; --primary-bg: #DBEAFE;
            --success: #059669; --success-bg: #D1FAE5;
            --warning: #D97706; --warning-bg: #FEF3C7;
            --danger: #DC2626; --danger-bg: #FEE2E2;
            --practice: #8B5CF6; --practice-bg: #EDE9FE;
            --text-dark: #0F172A; --text-muted: #64748B;
            --bg-body: #F8FAFC; --border: #E2E8F0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; padding-bottom: 50px; }

        .top-nav { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--border); }
        .nav-left { display: flex; align-items: center; gap: 15px; }
        .logo-box { width: 40px; height: 40px; background: var(--primary); color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--primary); margin-bottom: 2px; }
        .brand-text span { font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .btn-logout { background: var(--danger-bg); color: var(--danger); padding: 8px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.3s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-logout:hover { background: var(--danger); color: white; }

        .container { max-width: 1400px; margin: 30px auto; padding: 0 40px; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 28px; font-weight: 800; margin-bottom: 5px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px 25px; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 20px; border-left: 5px solid var(--primary);}
        .stat-icon { width: 55px; height: 55px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; background: var(--primary-bg); color: var(--primary);}
        .stat-info h3 { font-size: 24px; font-weight: 800; margin-bottom: 4px; line-height: 1; }
        .stat-info p { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;}

        .table-card { background: white; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 40px;}
        .table-header { padding: 20px 25px; border-bottom: 1px solid var(--border); background: #F8FAFC; display: flex; align-items: center; justify-content: space-between;}
        .table-header h2 { font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 10px;}
        
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th { background: white; color: var(--text-muted); font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 15px 25px; text-align: left; border-bottom: 2px solid var(--border); }
        td { padding: 20px 25px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 500; vertical-align: middle; }
        tr:hover td { background: var(--bg-body); }

        .txn-box { font-family: monospace; font-size: 15px; font-weight: 800; color: var(--primary); background: var(--primary-bg); padding: 6px 10px; border-radius: 8px; display: inline-block; border: 1px dashed var(--primary-light);}
        .fee-box { font-size: 18px; font-weight: 800; color: var(--success); }
        .practice-fee-box { font-size: 18px; font-weight: 800; color: var(--practice); }

        .tp-info strong { display: block; font-size: 15px; color: var(--text-dark); margin-bottom: 3px; }
        .tp-info span { font-size: 12px; color: var(--text-muted); background: var(--border); padding: 3px 8px; border-radius: 5px; font-weight: 700; }

        .action-btns { display: flex; gap: 10px; align-items: center;}
        .btn-approve { background: var(--success); color: white; border: none; padding: 10px 15px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 13px; }
        .btn-approve:hover { background: #047857; transform: scale(1.05); }
        .btn-reject { background: white; color: var(--danger); border: 1px solid var(--danger); padding: 10px; border-radius: 8px; cursor: pointer; transition: 0.2s; }
        .btn-reject:hover { background: var(--danger-bg); }
        
        .btn-view-receipt { background: #F8FAFC; color: var(--primary); border: 1px solid #CBD5E1; padding: 8px 12px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
        .btn-view-receipt:hover { border-color: var(--primary); background: var(--primary-bg); }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="nav-left">
            <div class="logo-box"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="brand-text">
                <h2>Finance Portal</h2>
                <span>Revenue Verification • NIELIT</span>
            </div>
        </div>
        <div class="nav-right">
            <a href="finance-logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        
        <div class="page-header">
            <h1>Payment Verification Queue</h1>
            <p>Review Training Partner UTR receipts and Candidate Practice Fees.</p>
        </div>

        <?php if ($success_msg): ?>
            <div style="background: var(--success-bg); color: var(--success); padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; border: 1px solid #A7F3D0;"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: var(--danger-bg); color: var(--danger); padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; border: 1px solid #FECACA;"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card" style="border-left-color: var(--warning);">
                <div class="stat-icon" style="background: var(--warning-bg); color: var(--warning);"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['tp_pending'] + $stats['cand_pending']); ?></h3>
                    <p>Total Pending Actions</p>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: var(--primary);">
                <div class="stat-icon" style="background: var(--primary-bg); color: var(--primary);"><i class="fas fa-building"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['tp_approved']); ?></h3>
                    <p>TP Batches Verified</p>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: var(--practice);">
                <div class="stat-icon" style="background: var(--practice-bg); color: var(--practice);"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['cand_approved']); ?></h3>
                    <p>Practice Unlocks</p>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: var(--success);">
                <div class="stat-icon" style="background: var(--success-bg); color: var(--success);"><i class="fas fa-rupee-sign"></i></div>
                <div class="stat-info">
                    <h3>₹<?php echo number_format($stats['tp_revenue'] + ($stats['cand_approved'] * 50)); ?></h3>
                    <p>Total Verified Revenue</p>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h2><span style="color:var(--practice);"><i class="fas fa-circle"></i></span> Candidate Practice Fees (₹50)</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Transaction / Date</th>
                            <th>Candidate Details</th>
                            <th>Practice Module</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_cand_payments)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fas fa-check-double" style="font-size: 30px; margin-bottom: 10px; color: var(--success); display: block;"></i>
                                    <h3 style="margin: 0; color: var(--text-dark); font-size: 16px;">All Practice Fees Verified!</h3>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_cand_payments as $pay): ?>
                            <tr>
                                <td>
                                    <div class="txn-box" style="color: var(--practice); background: var(--practice-bg); border-color: #C4B5FD;"><?php echo htmlspecialchars($pay['transaction_id']); ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">
                                        <i class="far fa-clock"></i> Submitted: <?php echo date('d M, h:i A', strtotime($pay['registered_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="tp-info">
                                        <strong><?php echo htmlspecialchars($pay['candidate_name']); ?></strong>
                                        <span><?php echo htmlspecialchars($pay['candidate_username']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark); font-size: 13px;"><?php echo htmlspecialchars($pay['category_name']); ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($pay['exam_code']); ?></div>
                                </td>
                                <td>
                                    <div class="practice-fee-box">₹50.00</div>
                                </td>
                                <td>
                                    <form method="POST" class="action-btns" onsubmit="return confirm('Confirm verification for this candidate?');">
                                        <input type="hidden" name="payment_type" value="candidate">
                                        <input type="hidden" name="reg_id" value="<?php echo $pay['reg_id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn-approve"><i class="fas fa-check"></i> Verify & Unlock</button>
                                        <button type="submit" name="action" value="reject" class="btn-reject" title="Reject Payment"><i class="fas fa-times"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h2><span style="color:var(--warning);"><i class="fas fa-circle"></i></span> Training Partner Bulk Booking Fees</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Transaction / Date</th>
                            <th>Institute (Training Partner)</th>
                            <th>Amount</th>
                            <th>Screenshot / Receipt</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_tp_payments)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fas fa-check-double" style="font-size: 30px; margin-bottom: 10px; color: var(--success); display: block;"></i>
                                    <h3 style="margin: 0; color: var(--text-dark); font-size: 16px;">All TP Batches Verified!</h3>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_tp_payments as $pay): ?>
                            <tr>
                                <td>
                                    <div class="txn-box"><?php echo htmlspecialchars($pay['transaction_id']); ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">
                                        <i class="far fa-clock"></i> Paid on <?php echo date('d M Y, h:i A', strtotime($pay['pay_date'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="tp-info">
                                        <strong><?php echo htmlspecialchars($pay['tp_name']); ?></strong>
                                        <span>Req ID: #<?php echo str_pad($pay['booking_id'], 5, '0', STR_PAD_LEFT); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fee-box">₹<?php echo number_format($pay['amount'], 2); ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($pay['payment_proof'])): ?>
                                        <a href="../<?php echo htmlspecialchars($pay['payment_proof']); ?>" target="_blank" class="btn-view-receipt">
                                            <i class="fas fa-image" style="color: #3B82F6;"></i> View Screenshot
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 12px; font-weight: 600;">No File Attached</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="action-btns" onsubmit="return confirm('Confirm TP payment verification?');">
                                        <input type="hidden" name="payment_type" value="tp">
                                        <input type="hidden" name="payment_id" value="<?php echo $pay['payment_id']; ?>">
                                        <input type="hidden" name="booking_id" value="<?php echo $pay['booking_id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn-approve"><i class="fas fa-check"></i> Verify</button>
                                        <button type="submit" name="action" value="reject" class="btn-reject" title="Reject Payment"><i class="fas fa-times"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        window.addEventListener('pageshow', function(event) {
            // If the page was loaded from the browser's memory cache
            if (event.persisted) {
                // Force a hard reload from the server. 
                // Since the PHP session is destroyed, this will instantly kick them to login.
                window.location.reload();
            }
        });
    </script>

</body>
</html>