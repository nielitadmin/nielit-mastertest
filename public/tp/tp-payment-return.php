<?php
session_name('NIELIT_TP_SESSION');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/helpers/PaymentHub.php';

$hub_status = strtolower(trim((string)($_GET['hub_status'] ?? '')));
$hub_token = trim((string)($_GET['hub_token'] ?? ''));
$hub_invoice = trim((string)($_GET['hub_invoice'] ?? ''));

$is_paid = false;
$error_message = '';
$booking = null;
$payment_info = null;

if (!empty($hub_token)) {
    $statusData = PaymentHub::getStatus($hub_token);

    if ($statusData && ($statusData['payment_status'] ?? '') === 'paid') {
        $is_paid = true;
        $payment_info = $statusData;

        // Resolve booking ID from context or reference
        $booking_id = (int)($statusData['context']['booking_id'] ?? 0);
        if ($booking_id <= 0 && preg_match('/TP-BKG-(\d+)/', (string)$statusData['reference'], $matches)) {
            $booking_id = (int)$matches[1];
        }

        if ($booking_id > 0) {
            try {
                $bStmt = $pdo->prepare("SELECT * FROM slot_bookings WHERE id = ?");
                $bStmt->execute([$booking_id]);
                $booking = $bStmt->fetch(PDO::FETCH_ASSOC);

                if ($booking) {
                    $pdo->beginTransaction();

                    // 1. Update booking status if not already approved
                    if ($booking['status'] === 'Pending Payment' || $booking['status'] === 'Verification Pending') {
                        $upd = $pdo->prepare("UPDATE slot_bookings SET status = 'Approved for Scheduling' WHERE id = ?");
                        $upd->execute([$booking_id]);
                    }

                    // 2. Check if already logged in payments table
                    $txn_id = (string)($statusData['razorpay_payment_id'] ?? $statusData['invoice_number'] ?? $hub_token);
                    $chkPay = $pdo->prepare("SELECT id FROM payments WHERE booking_id = ? AND reference_number = ? LIMIT 1");
                    $chkPay->execute([$booking_id, $txn_id]);
                    if (!$chkPay->fetchColumn()) {
                        $paid_amount = ((int)($statusData['amount_paise'] ?? 0)) / 100;
                        if ($paid_amount <= 0) {
                            $paid_amount = (float)$booking['total_fee'];
                        }
                        $tp_id = (int)$booking['tp_id'];

                        // Insert verified payment record
                        $ins = $pdo->prepare("
                            INSERT INTO payments (booking_id, tp_id, amount, payment_mode, reference_number, transaction_id, payment_status, verified_by, verified_at, created_at)
                            VALUES (?, ?, ?, 'Payment Hub', ?, ?, 'Verified', 1, NOW(), NOW())
                        ");
                        try {
                            $ins->execute([$booking_id, $tp_id, $paid_amount, $txn_id, $txn_id]);
                        } catch (PDOException $pe) {
                            // Fallback if some optional columns do not exist
                            $fallback = $pdo->prepare("
                                INSERT INTO payments (booking_id, tp_id, amount, payment_mode, reference_number, payment_status)
                                VALUES (?, ?, ?, 'Payment Hub', ?, 'Verified')
                            ");
                            $fallback->execute([$booking_id, $tp_id, $paid_amount, $txn_id]);
                        }
                    }

                    $pdo->commit();
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("TP Payment return DB error: " . $e->getMessage());
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
    <title><?php echo $is_paid ? 'Payment Successful' : 'Payment Status'; ?> - NIELIT CBT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #0D9488; 
            --primary-hover: #0F766E; 
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --text-main: #1E293B; 
            --text-muted: #64748B; 
            --surface: #FFFFFF; 
            --border: #E2E8F0; 
            --shadow: 0 20px 40px -15px rgba(0,0,0,0.07);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: #F8FAFC; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .result-card { width: 100%; max-width: 500px; background: var(--surface); border-radius: 24px; box-shadow: var(--shadow); border: 1px solid var(--border); padding: 40px; text-align: center; }
        .icon-circle { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 36px; margin: 0 auto 20px; }
        .icon-success { background: var(--success-bg); color: var(--success); }
        .icon-error { background: var(--danger-bg); color: var(--danger); }
        h2 { font-size: 22px; font-weight: 800; color: var(--text-main); margin-bottom: 8px; }
        p.subtitle { color: var(--text-muted); font-size: 14px; margin-bottom: 25px; line-height: 1.5; }
        .info-box { background: #F8FAFC; border: 1px solid var(--border); border-radius: 12px; padding: 20px; text-align: left; margin-bottom: 25px; }
        .info-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; font-size: 13px; }
        .info-row span:first-child { color: var(--text-muted); font-weight: 500; }
        .info-row .val { font-weight: 700; color: var(--text-main); }
        .btn-action { display: block; width: 100%; padding: 14px; border-radius: 12px; font-size: 14px; font-weight: 700; text-decoration: none; margin-bottom: 10px; transition: 0.2s; text-align: center; }
        .btn-primary { background: #0D9488; color: white; }
        .btn-primary:hover { background: #0F766E; }
        .btn-receipt { background: #1a56db; color: white; }
        .btn-receipt:hover { background: #1545b2; }
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
            <h2>Payment Successful!</h2>
            <p class="subtitle">Your exam slot booking has been verified and automatically approved for scheduling.</p>

            <div class="info-box">
                <?php if ($booking): ?>
                    <div class="info-row">
                        <span>Booking ID:</span>
                        <span class="val">#BKG-<?php echo str_pad((string)$booking['id'], 5, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="info-row">
                        <span>Candidates:</span>
                        <span class="val"><?php echo htmlspecialchars((string)$booking['estimated_candidates']); ?> Students</span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($payment_info['amount_paise'])): ?>
                    <div class="info-row">
                        <span>Amount Paid:</span>
                        <span class="val" style="color: #059669; font-size: 15px;">₹<?php echo number_format($payment_info['amount_paise'] / 100, 2); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($payment_info['invoice_number'])): ?>
                    <div class="info-row">
                        <span>Receipt / Invoice:</span>
                        <span class="val font-monospace"><?php echo htmlspecialchars($payment_info['invoice_number']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($payment_info['razorpay_payment_id'])): ?>
                    <div class="info-row">
                        <span>Gateway Payment ID:</span>
                        <span class="val font-monospace"><?php echo htmlspecialchars($payment_info['razorpay_payment_id']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span>Status:</span>
                    <span class="val" style="color: #059669;"><i class="fas fa-check-circle"></i> Confirmed &amp; Paid</span>
                </div>
            </div>

            <?php if (!empty($receipt_url)): ?>
                <a href="<?php echo htmlspecialchars($receipt_url); ?>" target="_blank" class="btn-action btn-receipt">
                    <i class="fas fa-file-invoice"></i> View Official Money Receipt
                </a>
            <?php endif; ?>

            <a href="tp-dashboard.php" class="btn-action btn-primary">
                <i class="fas fa-arrow-right"></i> Continue to Dashboard
            </a>

        <?php else: ?>
            <div class="icon-circle icon-error">
                <i class="fas fa-times"></i>
            </div>
            <h2>Payment Incomplete</h2>
            <p class="subtitle"><?php echo htmlspecialchars($error_message); ?></p>

            <?php if (!empty($booking_id)): ?>
                <a href="tp-payment-gateway.php?booking_id=<?php echo (int)$booking_id; ?>" class="btn-action btn-primary">
                    <i class="fas fa-redo"></i> Retry Payment
                </a>
            <?php endif; ?>

            <a href="tp-booking-history.php" class="btn-action btn-secondary">
                <i class="fas fa-arrow-left"></i> Return to Bookings
            </a>
        <?php endif; ?>
    </div>

</body>
</html>

