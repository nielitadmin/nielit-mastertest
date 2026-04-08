<?php
session_name('NIELIT_TP_SESSION');
session_start();

require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'tp') {
    header("Location: tp-login.php");
    exit();
}

$tp_id = $_SESSION['user_id'];
$error = '';
$success = '';

// ============================================================================
// RAZORPAY CONFIGURATION - ENTER YOUR KEYS HERE
// ============================================================================
// Get these from your Razorpay Dashboard -> Settings -> API Keys
$razorpay_key_id = 'rzp_test_YOUR_KEY_ID_HERE'; 
$razorpay_key_secret = 'YOUR_KEY_SECRET_HERE';
// ============================================================================

// ----------------------------------------------------------------------------
// PHASE 1: HANDLE SUCCESSFUL PAYMENT CALLBACK FROM RAZORPAY
// ----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['razorpay_payment_id'])) {
    $razorpay_payment_id = $_POST['razorpay_payment_id'];
    $razorpay_order_id = $_POST['razorpay_order_id'];
    $razorpay_signature = $_POST['razorpay_signature'];
    $booking_id = $_POST['booking_id'];
    $amount_paid = $_POST['amount'] / 100; // Convert from paise back to Rupees

    // Verify the cryptographic signature to prevent tampering
    $generated_signature = hash_hmac('sha256', $razorpay_order_id . "|" . $razorpay_payment_id, $razorpay_key_secret);

    if ($generated_signature === $razorpay_signature) {
        try {
            $pdo->beginTransaction();

            // 1. Log the verified payment
            $payStmt = $pdo->prepare("
                INSERT INTO payments (booking_id, tp_id, amount, payment_mode, reference_number, payment_status) 
                VALUES (?, ?, ?, 'Razorpay', ?, 'Verified')
            ");
            $payStmt->execute([$booking_id, $tp_id, $amount_paid, $razorpay_payment_id]);

            // 2. Auto-Approve the booking since Razorpay verified the funds
            $updateStmt = $pdo->prepare("UPDATE slot_bookings SET status = 'Approved for Scheduling' WHERE id = ?");
            $updateStmt->execute([$booking_id]);

            $pdo->commit();
            $success = "Payment Successful! Reference ID: " . $razorpay_payment_id;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database Error updating payment: " . $e->getMessage();
        }
    } else {
        $error = "Payment Verification Failed. Invalid Signature.";
    }
}

// ----------------------------------------------------------------------------
// PHASE 2: GENERATE RAZORPAY ORDER FOR A NEW BOOKING
// ----------------------------------------------------------------------------
$booking = null;
$razorpay_order_id = null;
$amount_in_paise = 0;

if (isset($_GET['booking_id']) && empty($success)) {
    $booking_id = $_GET['booking_id'];

    // Fetch booking details
    $stmt = $pdo->prepare("SELECT * FROM slot_bookings WHERE id = ? AND tp_id = ? AND status = 'Pending Payment'");
    $stmt->execute([$booking_id, $tp_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($booking) {
        // Razorpay expects the amount in the smallest currency unit (Paise)
        $amount_in_paise = round($booking['total_fee'] * 100); 

        // Generate an Order via Razorpay API using cURL
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        $order_data = json_encode([
            'amount' => $amount_in_paise,
            'currency' => 'INR',
            'receipt' => 'rcpt_' . $booking_id . '_' . time(),
            'payment_capture' => 1 // Auto-capture payment
        ]);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $order_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $razorpay_key_id . ":" . $razorpay_key_secret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($order_data)
        ]);

        $response = curl_exec($ch);
        curl_close($ch);
        
        $order_result = json_decode($response, true);

        if (isset($order_result['id'])) {
            $razorpay_order_id = $order_result['id'];
        } else {
            $error = "Failed to generate Razorpay Order. Check your API Keys. Details: " . ($order_result['error']['description'] ?? 'Unknown');
        }
    } else {
        $error = "Invalid booking reference or payment already processed.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment - NIELIT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0D9488; --bg-body: #F8FAFC; --surface: #FFFFFF;
            --text-dark: #0F172A; --text-muted: #64748B; --border: #E2E8F0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-body); color: var(--text-dark); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px;}
        
        .checkout-card {
            background: var(--surface); width: 100%; max-width: 450px;
            border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.05);
            border: 1px solid var(--border); overflow: hidden;
        }
        
        .checkout-header { background: #0F172A; padding: 30px; text-align: center; color: white; }
        .checkout-header img { height: 40px; margin-bottom: 15px; filter: brightness(0) invert(1); opacity: 0.9;}
        .checkout-header h2 { font-size: 20px; font-weight: 800; }
        .checkout-header p { color: #94A3B8; font-size: 13px; font-weight: 500; margin-top: 5px;}

        .checkout-body { padding: 30px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 14px; color: var(--text-muted); font-weight: 600;}
        .detail-row span.val { color: var(--text-dark); font-weight: 800; }
        
        .total-box {
            background: #F0FDFA; border: 1px solid #CCFBF1; border-radius: 12px;
            padding: 20px; margin: 20px 0; text-align: center;
        }
        .total-box p { font-size: 12px; color: var(--primary); font-weight: 800; text-transform: uppercase; margin-bottom: 5px;}
        .total-box h3 { font-size: 32px; font-weight: 800; color: #0F766E; line-height: 1;}

        .btn-pay {
            width: 100%; background: #3388FF; color: white; border: none; padding: 16px;
            border-radius: 12px; font-size: 16px; font-weight: 800; cursor: pointer;
            transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-pay:hover { background: #1D4ED8; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(51, 136, 255, 0.3); }

        .btn-return {
            display: block; text-align: center; margin-top: 20px; color: var(--text-muted);
            text-decoration: none; font-size: 13px; font-weight: 700; transition: 0.2s;
        }
        .btn-return:hover { color: var(--primary); }

        .alert-error { background: #FEF2F2; color: #DC2626; padding: 15px; border-radius: 12px; font-size: 13px; font-weight: 600; margin-bottom: 20px; text-align: center; border: 1px solid #FECACA;}
        
        .success-box { text-align: center; padding: 40px 30px; }
        .success-icon { width: 80px; height: 80px; background: #D1FAE5; color: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 36px; margin: 0 auto 20px; animation: pop 0.5s ease;}
        .success-box h2 { font-size: 24px; font-weight: 800; margin-bottom: 10px; }
        .success-box p { color: var(--text-muted); font-size: 14px; margin-bottom: 25px; line-height: 1.5;}
        
        @keyframes pop { 0% { transform: scale(0); } 80% { transform: scale(1.1); } 100% { transform: scale(1); } }
    </style>
</head>
<body>

    <div class="checkout-card">
        
        <?php if (!empty($success)): ?>
            <div class="success-box">
                <div class="success-icon"><i class="fas fa-check"></i></div>
                <h2>Payment Verified!</h2>
                <p>Your payment of <strong>₹<?php echo number_format($amount_paid ?? 0, 2); ?></strong> has been received and verified by Razorpay.<br>Your batch is now approved for scheduling.</p>
                <a href="tp-dashboard.php" class="btn-pay" style="background: var(--primary);">Go to Dashboard <i class="fas fa-arrow-right"></i></a>
            </div>
        <?php else: ?>
            
            <div class="checkout-header">
                <h2>Complete Your Payment</h2>
                <p>Secure Checkout via Razorpay</p>
            </div>

            <div class="checkout-body">
                <?php if ($error): ?>
                    <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
                    <a href="tp-dashboard.php" class="btn-return">Return to Dashboard</a>
                <?php elseif ($booking && $razorpay_order_id): ?>
                    
                    <div class="detail-row">
                        <span>Booking Reference</span>
                        <span class="val">#BKG-<?php echo str_pad($booking['id'], 5, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Candidates Enrolled</span>
                        <span class="val"><?php echo htmlspecialchars($booking['estimated_candidates']); ?> Students</span>
                    </div>

                    <div class="total-box">
                        <p>Total Payable Amount</p>
                        <h3>₹<?php echo number_format($booking['total_fee'], 2); ?></h3>
                    </div>

                    <form action="tp-payment-gateway.php" method="POST" id="razorpay-form">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                        <input type="hidden" name="amount" value="<?php echo $amount_in_paise; ?>">
                        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
                        <input type="hidden" name="razorpay_order_id" id="razorpay_order_id">
                        <input type="hidden" name="razorpay_signature" id="razorpay_signature">
                        
                        <button type="button" id="rzp-button1" class="btn-pay">
                            Pay via Razorpay <i class="fas fa-shield-alt"></i>
                        </button>
                    </form>

                    <a href="tp-book-exam.php" class="btn-return">Cancel & Go Back</a>

                    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                    <script>
                        var options = {
                            "key": "<?php echo $razorpay_key_id; ?>", 
                            "amount": "<?php echo $amount_in_paise; ?>", 
                            "currency": "INR",
                            "name": "NIELIT Bhubaneswar",
                            "description": "Exam Slot Booking Fee",
                            "image": "https://nielit.gov.in/sites/all/themes/nielit/images/logo.png",
                            "order_id": "<?php echo $razorpay_order_id; ?>", 
                            "handler": function (response){
                                // When payment succeeds, populate hidden fields and submit the form to PHP
                                document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                                document.getElementById('razorpay_order_id').value = response.razorpay_order_id;
                                document.getElementById('razorpay_signature').value = response.razorpay_signature;
                                document.getElementById('razorpay-form').submit();
                            },
                            "prefill": {
                                "name": "<?php echo htmlspecialchars($_SESSION['full_name']); ?>",
                                "email": "tp@institute.com"
                            },
                            "theme": {
                                "color": "#0F172A"
                            }
                        };
                        var rzp1 = new Razorpay(options);
                        
                        rzp1.on('payment.failed', function (response){
                            alert("Payment Failed: " + response.error.description);
                        });
                        
                        document.getElementById('rzp-button1').onclick = function(e){
                            rzp1.open();
                            e.preventDefault();
                        }
                    </script>

                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>