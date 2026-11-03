<?php
session_name('NIELIT_CANDIDATE_SESSION');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    header("Location: candidate-login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/helpers/PaymentHub.php';

$error = '';
$success = '';

if (!isset($_GET['reg_id']) || !is_numeric($_GET['reg_id'])) {
    header("Location: candidate-dashboard.php");
    exit();
}

$reg_id = (int)$_GET['reg_id'];

try {
    // 1. Verify this registration belongs to this user and needs payment
    $stmt = $pdo->prepare("
        SELECT er.*, es.exam_code, es.chapter_number, ec.category_name, ec.category_code
        FROM exam_registrations er
        JOIN exam_sessions es ON er.session_id = es.id
        JOIN exam_categories ec ON es.category_id = ec.id
        WHERE er.id = ? AND er.candidate_id = ?
    ");
    $stmt->execute([$reg_id, $_SESSION['user_id']]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    // If they aren't in pending_payment status, boot them back to the dashboard
    if (!$registration || $registration['registration_status'] !== 'pending_payment') {
        header("Location: candidate-dashboard.php");
        exit();
    }

    // Fetch candidate user details
    $candStmt = $pdo->prepare("
        SELECT u.*, c.registration_number 
        FROM users u 
        LEFT JOIN candidates c ON u.id = c.user_id 
        WHERE u.id = ?
    ");
    $candStmt->execute([$_SESSION['user_id']]);
    $candidate = $candStmt->fetch(PDO::FETCH_ASSOC);

    // 2. Handle Online Payment Initiation (via Central Payment Hub)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_online'])) {
        $amount_paise = 5000; // ₹50.00 in paise
        $reference = 'PRACTICE-REG-' . $reg_id . '-' . time();
        $chapterText = !empty($registration['chapter_number']) ? ' (Chapter ' . $registration['chapter_number'] . ')' : '';

        $checkoutResult = PaymentHub::createCheckout([
            'reference' => $reference,
            'amount_paise' => $amount_paise,
            'purpose' => 'Practice Exam Fee',
            'description' => 'Practice Exam: ' . $registration['category_name'] . $chapterText,
            'return_url' => PaymentHub::appUrl('candidate/practice-payment-return.php'),
            'notify_url' => PaymentHub::appUrl('api/payment_hub_notify.php'),
            'payer' => [
                'name' => !empty($candidate['full_name']) ? $candidate['full_name'] : 'Candidate',
                'email' => !empty($candidate['email']) ? $candidate['email'] : 'candidate@nielitbhubaneswar.in',
                'phone' => !empty($candidate['phone']) ? $candidate['phone'] : '',
                'external_id' => !empty($candidate['registration_number']) ? $candidate['registration_number'] : ('CAND-' . $_SESSION['user_id']),
            ],
            'context' => [
                'registration_id' => $reg_id,
                'candidate_id' => (int)$_SESSION['user_id'],
                'session_id' => (int)$registration['session_id'],
                'type' => 'candidate_practice',
                'exam_code' => $registration['exam_code'],
            ],
        ]);

        if (!empty($checkoutResult['ok']) && !empty($checkoutResult['pay_url'])) {
            header("Location: " . $checkoutResult['pay_url']);
            exit();
        } else {
            $error = $checkoutResult['error'] ?? 'Could not start online payment session.';
        }
    }

    // 3. Fallback: Handle Manual UPI Transaction ID Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
        $transaction_id = trim($_POST['transaction_id']);

        if (empty($transaction_id) || strlen($transaction_id) < 8) {
            $error = "Please enter a valid UPI UTR or Transaction ID (Min 8 characters).";
        } else {
            $update = $pdo->prepare("
                UPDATE exam_registrations 
                SET registration_status = 'payment_submitted', transaction_id = ? 
                WHERE id = ?
            ");
            $update->execute([$transaction_id, $reg_id]);

            header("Location: candidate-dashboard.php?msg=PaymentUnderReview");
            exit();
        }
    }
} catch (PDOException $e) {
    $error = "System Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unlock Practice Exam - NIELIT CBT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #2563EB; 
            --primary-hover: #1D4ED8; 
            --primary-bg: #DBEAFE; 
            --secondary: #0F172A; 
            --text-main: #1E293B; 
            --text-muted: #64748B; 
            --surface: rgba(255, 255, 255, 0.95); 
            --border: rgba(226, 232, 240, 0.9); 
            --danger: #DC2626; 
            --shadow-glass: 0 20px 40px -10px rgba(37, 99, 235, 0.15);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: #F8FAFC; display: flex; align-items: center; justify-content: center; min-height: 100vh; flex-direction: column; padding: 20px; overflow-x: hidden; }

        .ambient-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; overflow: hidden; pointer-events: none; background: radial-gradient(circle at 50% 0%, #E0F2FE 0%, #DBEAFE 50%, #F8FAFC 100%); }
        .payment-card {
            width: 100%; max-width: 460px; background: var(--surface); border-radius: 24px;
            box-shadow: var(--shadow-glass); border: 1px solid rgba(255, 255, 255, 1);
            padding: 35px 30px; position: relative; z-index: 1;
        }
        .header { text-align: center; margin-bottom: 20px; }
        .icon-box { width: 56px; height: 56px; background: var(--primary-bg); color: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin: 0 auto 12px; }
        .header h1 { font-size: 20px; font-weight: 800; color: var(--text-main); margin-bottom: 4px; }
        .header p { font-size: 13px; color: var(--text-muted); font-weight: 500; }

        .order-summary { background: #F0F7FF; border: 2px dashed #93C5FD; border-radius: 16px; padding: 18px; margin-bottom: 20px; text-align: center; }
        .os-title { font-size: 14px; font-weight: 800; color: var(--text-main); margin-bottom: 3px; }
        .os-code { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-bottom: 10px; }
        .os-amount { font-size: 30px; font-weight: 800; color: var(--primary); }

        .btn-online-pay {
            width: 100%; background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); color: white; border: none; padding: 16px; border-radius: 14px;
            font-size: 15px; font-weight: 800; cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 10px;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25); text-decoration: none;
        }
        .btn-online-pay:hover { background: #1e40af; transform: translateY(-1px); }

        .instant-badge { display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 12px; color: #059669; font-weight: 700; margin-top: 10px; }
        .instant-badge i { font-size: 13px; }

        .divider { display: flex; align-items: center; text-align: center; margin: 24px 0 18px; color: var(--text-muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid var(--border); }
        .divider span { padding: 0 10px; }

        .manual-box { background: #F8FAFC; border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin-bottom: 16px; font-size: 12px; color: var(--text-muted); }
        .manual-box strong { color: var(--text-main); }
        .upi-badge { background: #E2E8F0; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-size: 12px; color: #0F172A; display: inline-block; margin-top: 4px; }

        .form-control { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 10px; font-size: 13px; font-weight: 600; outline: none; margin-bottom: 10px; text-align: center; font-family: inherit; }
        .form-control:focus { border-color: var(--primary); }

        .btn-manual-submit { width: 100%; background: #0F172A; color: white; border: none; padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-manual-submit:hover { background: #1E293B; }

        .alert-error { background: #FEF2F2; color: var(--danger); padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 16px; border: 1px solid #FECACA; text-align: center; }
        .btn-cancel { display: block; text-align: center; margin-top: 16px; color: var(--text-muted); font-size: 13px; font-weight: 600; text-decoration: none; }
        .btn-cancel:hover { color: var(--danger); }
    </style>
</head>
<body>

    <div class="ambient-bg"></div>

    <div class="payment-card">
        <div class="header">
            <div class="icon-box"><i class="fas fa-bolt"></i></div>
            <h1>Unlock Practice Exam</h1>
            <p>Pay fee to immediately access this practice module</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="order-summary">
            <div class="os-title"><?php echo htmlspecialchars($registration['category_name']); ?></div>
            <div class="os-code">
                REF: <?php echo htmlspecialchars($registration['exam_code']); ?>
                <?php if (!empty($registration['chapter_number'])): ?>
                    · Chapter <?php echo htmlspecialchars((string)$registration['chapter_number']); ?>
                <?php endif; ?>
            </div>
            <div class="os-amount">₹50.00</div>
        </div>

        <!-- 🟢 Recommended: Online Direct Checkout via Central Payment Hub -->
        <form method="POST" action="">
            <input type="hidden" name="pay_online" value="1">
            <button type="submit" class="btn-online-pay">
                <i class="fas fa-shield-alt"></i> Pay ₹50 Online (Instant Unlock)
            </button>
            <div class="instant-badge">
                <i class="fas fa-check-circle"></i> Instant Activation via UPI, QR, Cards, or Netbanking
            </div>
        </form>

        <!-- Optional Fallback: Manual UPI Reference Submission -->
        <div class="divider"><span>Or Pay Manually</span></div>

        <div class="manual-box">
            <span>Scan SBI QR or send to UPI:</span><br>
            <span class="upi-badge">nielit.exams@sbi</span>
            <p style="margin-top: 6px; font-size: 11px;">Manual verification requires up to 24 hrs approval by Finance.</p>
        </div>

        <form method="POST" action="" autocomplete="off">
            <input type="text" name="transaction_id" class="form-control" placeholder="Enter UPI UTR / Transaction ID" required>
            <button type="submit" name="submit_payment" class="btn-manual-submit">
                Submit UTR for Manual Review
            </button>
        </form>

        <a href="candidate-dashboard.php" class="btn-cancel">Cancel & Return to Dashboard</a>
    </div>

</body>
</html>
