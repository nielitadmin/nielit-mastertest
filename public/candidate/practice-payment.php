<?php
session_name('NIELIT_CANDIDATE_SESSION');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    header("Location: candidate-login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$error = '';
$success = '';

if (!isset($_GET['reg_id']) || !is_numeric($_GET['reg_id'])) {
    header("Location: candidate-dashboard.php");
    exit();
}

$reg_id = (int)$_GET['reg_id'];

try {
    // 1. Verify this registration belongs to this user and needs payment
    // We use user_id because the candidate_id in exam_registrations is linked to the user_id in this setup
    $stmt = $pdo->prepare("
        SELECT er.*, es.exam_code, ec.category_name 
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

    // 2. Handle the Payment Form Submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_payment'])) {
        $transaction_id = trim($_POST['transaction_id']);

        if (empty($transaction_id) || strlen($transaction_id) < 8) {
            $error = "Please enter a valid UPI UTR or Transaction ID (Min 8 characters).";
        } else {
            // Update the registration to show payment is under review
            $update = $pdo->prepare("
                UPDATE exam_registrations 
                SET registration_status = 'payment_submitted', transaction_id = ? 
                WHERE id = ?
            ");
            $update->execute([$transaction_id, $reg_id]);

            // Send back to dashboard with a success message
            header("Location: candidate-dashboard.php?msg=PaymentUnderReview");
            exit();
        }
    }
} catch (PDOException $e) {
    // If it fails because transaction_id column doesn't exist yet, show a helpful error
    if (strpos($e->getMessage(), 'column "transaction_id" of relation "exam_registrations" does not exist') !== false) {
         $error = "DEVELOPER: You need to add the transaction_id column to your database! Run the SQL command provided in the chat.";
    } else {
         $error = "System Database Error: " . $e->getMessage();
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
            --primary: #2563EB; --primary-hover: #1D4ED8; --primary-bg: #DBEAFE; 
            --secondary: #0F172A; --text-main: #1E293B; --text-muted: #64748B; 
            --surface: rgba(255, 255, 255, 0.85); --border: rgba(226, 232, 240, 0.8); 
            --danger: #DC2626; --shadow-glass: 0 20px 40px -10px rgba(37, 99, 235, 0.15);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: #F8FAFC; display: flex; align-items: center; justify-content: center; min-height: 100vh; flex-direction: column; padding: 20px; overflow-x: hidden; }

        /* 3D Ambient Background (Blue Theme for Payments) */
        .ambient-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; overflow: hidden; pointer-events: none; background: radial-gradient(circle at 50% 0%, #E0F2FE 0%, #DBEAFE 50%, #F8FAFC 100%); perspective: 1000px; }
        .shape { position: absolute; background: linear-gradient(135deg, rgba(255, 255, 255, 0.8), rgba(37, 99, 235, 0.05)); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.9); box-shadow: 0 15px 35px rgba(37, 99, 235, 0.08), inset 0 0 20px rgba(255, 255, 255, 0.8); animation: float-3d 20s infinite linear; }
        .cube { width: 140px; height: 140px; border-radius: 28px; top: 15%; left: 8%; animation-duration: 28s; }
        .sphere { width: 180px; height: 180px; border-radius: 50%; bottom: 10%; right: 10%; animation-duration: 40s; }
        @keyframes float-3d { 0% { transform: translateY(0) rotateX(0deg) rotateY(0deg) rotateZ(0deg); } 50% { transform: translateY(-50px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); } 100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); } }

        .payment-card {
            width: 100%; max-width: 450px; background: var(--surface); border-radius: 24px;
            box-shadow: var(--shadow-glass); border: 1px solid rgba(255, 255, 255, 1);
            padding: 40px; position: relative; backdrop-filter: blur(24px); z-index: 1;
            animation: fadeUp 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        .header { text-align: center; margin-bottom: 25px; }
        .icon-box { width: 60px; height: 60px; background: var(--primary-bg); color: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 15px; box-shadow: 0 8px 15px rgba(37, 99, 235, 0.15);}
        .header h1 { font-size: 22px; font-weight: 800; color: var(--text-main); margin-bottom: 5px; }
        .header p { font-size: 13px; color: var(--text-muted); font-weight: 500; }

        .order-summary { background: rgba(255,255,255,0.6); border: 2px dashed #93C5FD; border-radius: 16px; padding: 20px; margin-bottom: 25px; text-align: center;}
        .os-title { font-size: 14px; font-weight: 800; color: var(--text-main); margin-bottom: 4px; }
        .os-code { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-bottom: 15px;}
        .os-amount { font-size: 32px; font-weight: 800; color: var(--primary); line-height: 1;}

        .qr-section { text-align: center; margin-bottom: 25px; }
        .qr-placeholder { width: 160px; height: 160px; background: white; border: 1px solid var(--border); border-radius: 12px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #CBD5E1; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);}
        .qr-section p { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }
        .upi-id { font-size: 14px; font-weight: 800; color: var(--primary-hover); background: var(--primary-bg); padding: 8px 16px; border-radius: 50px; display: inline-block; margin-top: 10px; border: 1px solid #BFDBFE;}

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
        .form-control { width: 100%; padding: 14px 16px; border: 2px solid var(--border); border-radius: 12px; font-size: 14px; font-weight: 600; outline: none; transition: 0.3s; text-align: center; letter-spacing: 2px; font-family: inherit; background: white;}
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-bg); }

        .btn-submit { width: 100%; background: var(--primary); color: white; border: none; padding: 16px; border-radius: 12px; font-size: 15px; font-weight: 800; cursor: pointer; transition: 0.3s; box-shadow: 0 6px 15px rgba(37, 99, 235, 0.25); display: flex; justify-content: center; align-items: center; gap: 8px; font-family: inherit;}
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(37, 99, 235, 0.35);}

        .alert-error { background: #FEF2F2; color: var(--danger); padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 20px; border: 1px solid #FECACA; text-align: center; }
        .btn-cancel { display: block; text-align: center; margin-top: 20px; color: var(--text-muted); font-size: 13px; font-weight: 700; text-decoration: none; transition: 0.2s;}
        .btn-cancel:hover { color: var(--danger); }
    </style>
</head>
<body>

    <div class="ambient-bg">
        <div class="shape cube"></div>
        <div class="shape sphere"></div>
    </div>

    <div class="payment-card">
        <div class="header">
            <div class="icon-box"><i class="fas fa-shield-check"></i></div>
            <h1>Secure Payment</h1>
            <p>Verify your identity to unlock Practice Mode</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="order-summary">
            <div class="os-title"><?php echo htmlspecialchars($registration['category_name']); ?> (Practice)</div>
            <div class="os-code">REF: <?php echo htmlspecialchars($registration['exam_code']); ?></div>
            <div class="os-amount">₹50.00</div>
        </div>

        <div class="qr-section">
            <div class="qr-placeholder">
                <i class="fas fa-qrcode"></i>
            </div>
            <p>Scan to Pay via any UPI App</p>
            <div class="upi-id">nielit.exams@sbi</div>
        </div>

        <form method="POST" action="" autocomplete="off">
            <div class="form-group">
                <label>Enter UPI UTR / Transaction ID</label>
                <input type="text" name="transaction_id" class="form-control" placeholder="e.g. 301234567890" required autofocus>
            </div>

            <button type="submit" name="submit_payment" class="btn-submit">
                <i class="fas fa-check-circle"></i> Submit for Verification
            </button>
        </form>

        <a href="candidate-dashboard.php" class="btn-cancel">Cancel & Return to Dashboard</a>
    </div>

</body>
</html>