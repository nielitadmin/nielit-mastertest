<?php
session_name('NIELIT_CANDIDATE_SESSION');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/helpers/PaymentHub.php';

$hub_status = strtolower(trim((string)($_GET['hub_status'] ?? '')));
$hub_token = trim((string)($_GET['hub_token'] ?? ''));
$hub_invoice = trim((string)($_GET['hub_invoice'] ?? ''));

$is_paid = false;
$error_message = '';
$reg_id = 0;
$payment_info = null;
$exam_info = null;

if (!empty($hub_token)) {
    $statusData = PaymentHub::getStatus($hub_token);

    if ($statusData && ($statusData['payment_status'] ?? '') === 'paid') {
        $is_paid = true;
        $payment_info = $statusData;

        // Resolve registration ID from context or reference
        $reg_id = (int)($statusData['context']['registration_id'] ?? 0);
        if ($reg_id <= 0 && preg_match('/PRACTICE-REG-(\d+)/', (string)$statusData['reference'], $matches)) {
            $reg_id = (int)$matches[1];
        }

        if ($reg_id > 0) {
            try {
                $rStmt = $pdo->prepare("
                    SELECT er.*, es.exam_code, ec.category_name 
                    FROM exam_registrations er
                    JOIN exam_sessions es ON er.session_id = es.id
                    JOIN exam_categories ec ON es.category_id = ec.id
                    WHERE er.id = ?
                ");
                $rStmt->execute([$reg_id]);
                $exam_info = $rStmt->fetch(PDO::FETCH_ASSOC);

                if ($exam_info) {
                    $txn_id = (string)($statusData['razorpay_payment_id'] ?? $statusData['invoice_number'] ?? $hub_token);
                    
                    // Unlock the exam immediately: update registration_status to 'approved'
                    if ($exam_info['registration_status'] !== 'approved') {
                        $upd = $pdo->prepare("
                            UPDATE exam_registrations 
                            SET registration_status = 'approved', transaction_id = ? 
                            WHERE id = ?
                        ");
                        $upd->execute([$txn_id, $reg_id]);
                    }
                }
            } catch (Exception $e) {
                error_log("Candidate Practice Payment return DB error: " . $e->getMessage());
            }
        }
    } else {
        $error_message = ($hub_status === 'failed') 
            ? 'The payment was cancelled or failed at the payment gateway.' 
            : 'Payment could not be verified by the Central Payment Portal.';
    }
} else {
    $error_message = 'Missing payment reference token.';
}

$receipt_url = '';
if ($is_paid && !empty($payment_info['hub_payment_id'])) {
    $receipt_url = PaymentHub::getReceiptUrl((int)$payment_info['hub_payment_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_paid ? 'Practice Mode Unlocked' : 'Payment Status'; ?> - NIELIT CBT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #2563EB; 
            --primary-hover: #1D4ED8; 
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --text-main: #1E293B; 
            --text-muted: #64748B; 
            --surface: #FFFFFF; 
            --border: #E2E8F0; 
            --shadow: 0 20px 40px -15px rgba(37, 99, 235, 0.12);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: #F8FAFC; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .result-card { width: 100%; max-width: 480px; background: var(--surface); border-radius: 24px; box-shadow: var(--shadow); border: 1px solid var(--border); padding: 40px 30px; text-align: center; }
        .icon-circle { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 36px; margin: 0 auto 20px; }
        .icon-success { background: var(--success-bg); color: var(--success); }
        .icon-error { background: var(--danger-bg); color: var(--danger); }
        h2 { font-size: 22px; font-weight: 800; color: var(--text-main); margin-bottom: 8px; }
        p.subtitle { color: var(--text-muted); font-size: 14px; margin-bottom: 25px; line-height: 1.5; }
        .info-box { background: #F8FAFC; border: 1px solid var(--border); border-radius: 14px; padding: 18px; text-align: left; margin-bottom: 25px; font-size: 13px; }
        .info-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; }
        .info-row span:first-child { color: var(--text-muted); font-weight: 500; }
        .info-row .val { font-weight: 700; color: var(--text-main); }
        .btn-action { display: block; width: 100%; padding: 14px; border-radius: 12px; font-size: 14px; font-weight: 700; text-decoration: none; margin-bottom: 10px; transition: 0.2s; text-align: center; }
        .btn-primary { background: #2563EB; color: white; }
        .btn-primary:hover { background: #1D4ED8; }
        .btn-receipt { background: #0D9488; color: white; }
        .btn-receipt:hover { background: #0F766E; }
        .btn-secondary { background: #F1F5F9; color: var(--text-main); }
        .btn-secondary:hover { background: #E2E8F0; }
    </style>
</head>
<body>

    <div class="result-card">
        <?php if ($is_paid): ?>
            <div class="icon-circle icon-success">
                <i class="fas fa-check"></i>
            </div>
            <h2>Practice Exam Unlocked!</h2>
            <p class="subtitle">Your payment has been received and verified. You can now access your practice exam right away.</p>

            <div class="info-box">
                <?php if ($exam_info): ?>
                    <div class="info-row">
                        <span>Subject:</span>
                        <span class="val"><?php echo htmlspecialchars($exam_info['category_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span>Exam Code:</span>
                        <span class="val"><?php echo htmlspecialchars($exam_info['exam_code']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span>Fee Paid:</span>
                    <span class="val" style="color: #059669;">₹50.00</span>
                </div>
                <?php if (!empty($payment_info['invoice_number'])): ?>
                    <div class="info-row">
                        <span>Receipt No:</span>
                        <span class="val font-monospace"><?php echo htmlspecialchars($payment_info['invoice_number']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($payment_info['razorpay_payment_id'])): ?>
                    <div class="info-row">
                        <span>Payment ID:</span>
                        <span class="val font-monospace"><?php echo htmlspecialchars($payment_info['razorpay_payment_id']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span>Access Status:</span>
                    <span class="val" style="color: #059669;"><i class="fas fa-lock-open"></i> Unlocked</span>
                </div>
            </div>

            <a href="candidate-dashboard.php" class="btn-action btn-primary">
                <i class="fas fa-play-circle"></i> Go to Dashboard &amp; Start Exam
            </a>

            <?php if (!empty($receipt_url)): ?>
                <a href="<?php echo htmlspecialchars($receipt_url); ?>" target="_blank" class="btn-action btn-receipt">
                    <i class="fas fa-file-invoice"></i> View Official Money Receipt
                </a>
            <?php endif; ?>

        <?php else: ?>
            <div class="icon-circle icon-error">
                <i class="fas fa-times"></i>
            </div>
            <h2>Payment Incomplete</h2>
            <p class="subtitle"><?php echo htmlspecialchars($error_message); ?></p>

            <?php if (!empty($reg_id)): ?>
                <a href="practice-payment.php?reg_id=<?php echo (int)$reg_id; ?>" class="btn-action btn-primary">
                    <i class="fas fa-redo"></i> Retry Payment
                </a>
            <?php endif; ?>

            <a href="candidate-dashboard.php" class="btn-action btn-secondary">
                <i class="fas fa-arrow-left"></i> Return to Dashboard
            </a>
        <?php endif; ?>
    </div>

</body>
</html>

