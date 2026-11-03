<?php
session_name('NIELIT_TP_SESSION');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/helpers/PaymentHub.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'tp') {
    header("Location: tp-login.php");
    exit();
}

$tp_id = (int)$_SESSION['user_id'];
$error = '';
$booking = null;

if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    header("Location: tp-dashboard.php");
    exit();
}

$booking_id = (int)$_GET['booking_id'];

// Fetch booking details
try {
    $stmt = $pdo->prepare("
        SELECT b.*, ec.category_name, ec.category_code 
        FROM slot_bookings b
        JOIN exam_categories ec ON b.category_id = ec.id
        WHERE b.id = ? AND b.tp_id = ? AND b.status = 'Pending Payment'
    ");
    $stmt->execute([$booking_id, $tp_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $error = "Invalid booking reference or payment already processed.";
    }
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}

// Fetch TP details for payer info
$tp_user = null;
if ($booking) {
    $tpStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $tpStmt->execute([$tp_id]);
    $tp_user = $tpStmt->fetch(PDO::FETCH_ASSOC);
}

// Handle "Proceed to Pay" action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initiate_payment']) && $booking) {
    $amount_in_paise = (int) round($booking['total_fee'] * 100);
    $reference = 'TP-BKG-' . $booking['id'] . '-' . time();

    $checkoutResult = PaymentHub::createCheckout([
        'reference' => $reference,
        'amount_paise' => $amount_in_paise,
        'purpose' => 'Slot Booking Fee',
        'description' => 'Exam slot booking #' . $booking['id'] . ' for ' . $booking['estimated_candidates'] . ' candidate(s)',
        'return_url' => PaymentHub::appUrl('tp/tp-payment-return.php'),
        'notify_url' => PaymentHub::appUrl('api/payment_hub_notify.php'),
        'payer' => [
            'name' => !empty($tp_user['full_name']) ? $tp_user['full_name'] : 'Training Partner',
            'email' => !empty($tp_user['email']) ? $tp_user['email'] : 'tp@nielitbhubaneswar.in',
            'phone' => !empty($tp_user['phone']) ? $tp_user['phone'] : '',
            'external_id' => 'TP-' . $tp_id,
        ],
        'context' => [
            'booking_id' => $booking['id'],
            'tp_id' => $tp_id,
            'type' => 'tp_booking',
            'candidates' => $booking['estimated_candidates'],
            'category_code' => $booking['category_code'] ?? '',
        ],
    ]);

    if (!empty($checkoutResult['ok']) && !empty($checkoutResult['pay_url'])) {
        header("Location: " . $checkoutResult['pay_url']);
        exit();
    } else {
        $error = $checkoutResult['error'] ?? 'Could not initiate checkout on Central Payment Portal.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment - NIELIT CBT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #0D9488; 
            --primary-hover: #0F766E; 
            --primary-bg: #CCFBF1; 
            --secondary: #0F172A; 
            --text-main: #1E293B; 
            --text-muted: #64748B; 
            --surface: #FFFFFF; 
            --border: #E2E8F0; 
            --danger: #DC2626; 
            --shadow: 0 20px 40px -15px rgba(0,0,0,0.07);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: #F8FAFC; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .checkout-card { width: 100%; max-width: 480px; background: var(--surface); border-radius: 20px; box-shadow: var(--shadow); border: 1px solid var(--border); overflow: hidden; }
        .checkout-header { background: linear-gradient(135deg, #0c2340 0%, #123a66 100%); color: white; padding: 30px; text-align: center; }
        .checkout-header h2 { font-size: 20px; font-weight: 800; margin-bottom: 6px; }
        .checkout-header p { font-size: 13px; color: #94a3b8; }
        .checkout-body { padding: 30px; }
        .detail-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed var(--border); font-size: 14px; }
        .detail-row span:first-child { color: var(--text-muted); font-weight: 500; }
        .detail-row .val { font-weight: 700; color: var(--text-main); }
        .total-box { background: var(--primary-bg); border: 1px solid #99F6E4; border-radius: 12px; padding: 20px; text-align: center; margin: 25px 0; }
        .total-box p { font-size: 12px; font-weight: 700; color: var(--primary-hover); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .total-box h3 { font-size: 32px; font-weight: 800; color: #0F766E; }
        .btn-pay { width: 100%; background: #1a56db; color: white; border: none; padding: 16px; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; text-decoration: none; }
        .btn-pay:hover { background: #1545b2; transform: translateY(-1px); }
        .btn-return { display: block; text-align: center; margin-top: 15px; color: var(--text-muted); font-size: 13px; font-weight: 600; text-decoration: none; }
        .btn-return:hover { color: var(--text-main); }
        .alert-error { background: #FEF2F2; color: var(--danger); padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 20px; border: 1px solid #FECACA; text-align: center; }
        .gateway-badge { display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 12px; color: #64748b; margin-top: 16px; }
        .gateway-badge i { color: #16a34a; }
    </style>
</head>
<body>

    <div class="checkout-card">
        <div class="checkout-header">
            <div style="font-size: 32px; margin-bottom: 12px;"><i class="fas fa-wallet"></i></div>
            <h2>Complete Your Booking Payment</h2>
            <p>NIELIT Central Payment Portal</p>
        </div>

        <div class="checkout-body">
            <?php if ($error): ?>
                <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
                <a href="tp-booking-history.php" class="btn-return">Return to Booking History</a>
            <?php elseif ($booking): ?>
                
                <div class="detail-row">
                    <span>Booking Reference</span>
                    <span class="val">#BKG-<?php echo str_pad((string)$booking['id'], 5, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="detail-row">
                    <span>Examination Subject</span>
                    <span class="val"><?php echo htmlspecialchars($booking['category_name']); ?> (<?php echo htmlspecialchars($booking['category_code']); ?>)</span>
                </div>
                <div class="detail-row">
                    <span>Preferred Date & Shift</span>
                    <span class="val"><?php echo date('d M Y', strtotime($booking['preferred_date'])); ?> · Shift <?php echo htmlspecialchars((string)($booking['shift_slot'] ?? '1')); ?></span>
                </div>
                <div class="detail-row">
                    <span>Candidates Enrolled</span>
                    <span class="val"><?php echo htmlspecialchars((string)$booking['estimated_candidates']); ?> Students</span>
                </div>

                <div class="total-box">
                    <p>Total Payable Amount</p>
                    <h3>₹<?php echo number_format($booking['total_fee'], 2); ?></h3>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="initiate_payment" value="1">
                    <button type="submit" class="btn-pay">
                        <i class="fas fa-shield-alt"></i> Pay via Payment Hub (UPI / Cards / Netbanking)
                    </button>
                </form>

                <div class="gateway-badge">
                    <i class="fas fa-lock"></i> Secured by payment.nielitbhubaneswar.in
                </div>

                <a href="tp-booking-history.php" class="btn-return">Cancel & Return to Bookings</a>

            <?php endif; ?>
        </div>
    </div>

</body>
</html>
