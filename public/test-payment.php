<?php
/**
 * Test Payment & Hub Connection Diagnostic Tool
 * NIELIT Mock Test Portal (test.nielitbhubaneswar.in)
 */

require_once __DIR__ . '/../config/payment_hub.php';
require_once __DIR__ . '/../app/helpers/PaymentHub.php';

$cfgFile = __DIR__ . '/../config/payment_hub.php';
$msg = '';
$msgType = 'info';
$pingResult = null;
$returnStatus = null;

// Handle Save Secret
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_secret') {
    $newSecret = trim((string)($_POST['shared_secret'] ?? ''));
    if ($newSecret === '') {
        $msg = 'Please enter a shared secret.';
        $msgType = 'danger';
    } else {
        $fileContent = "<?php\n/**\n * NIELIT Mock Test Portal — Central Payment Portal Configuration\n * Connects to https://payment.nielitbhubaneswar.in\n */\n\nreturn [\n    'enabled' => true,\n    'api_base' => getenv('PAYMENT_HUB_API_BASE') ?: 'https://payment.nielitbhubaneswar.in',\n    'source' => 'mock',\n    'shared_secret' => getenv('PAYMENT_HUB_SHARED_SECRET') ?: '" . addslashes($newSecret) . "',\n    'app_url' => getenv('APP_URL') ?: (\n        (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://')\n        . (\$_SERVER['HTTP_HOST'] ?? 'test.nielitbhubaneswar.in')\n    ),\n];\n";
        if (file_put_contents($cfgFile, $fileContent)) {
            $msg = 'Shared secret saved successfully!';
            $msgType = 'success';
        } else {
            $msg = 'Could not write to config/payment_hub.php. Check folder permissions.';
            $msgType = 'danger';
        }
    }
}

// Refresh config
$cfg = PaymentHub::config();

// Handle Ping Hub Test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ping_hub') {
    $testPayload = [
        'reference' => 'PING-' . time() . '-' . rand(100, 999),
        'amount_paise' => 100, // ₹1.00
        'purpose' => 'Connection Test',
        'description' => 'Verifying signature and connection between test.nielitbhubaneswar.in and payment hub',
        'return_url' => PaymentHub::appUrl('test-payment.php'),
        'payer' => [
            'name' => 'NIELIT Admin Tester',
            'email' => 'admin@nielitbhubaneswar.in',
            'phone' => '9876543210'
        ],
        'context' => [
            'type' => 'diagnostic_ping'
        ]
    ];
    $pingResult = PaymentHub::createCheckout($testPayload);
}

// Handle Start Real Test Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start_test_payment') {
    $amountRupees = max(1, (int)($_POST['test_amount'] ?? 1));
    $payerName = trim((string)($_POST['payer_name'] ?? 'Test Candidate'));
    $payerEmail = trim((string)($_POST['payer_email'] ?? 'candidate@nielitbhubaneswar.in'));
    $payerPhone = trim((string)($_POST['payer_phone'] ?? '9876543210'));

    $payload = [
        'reference' => 'TEST-MOCK-' . time(),
        'amount_paise' => $amountRupees * 100,
        'purpose' => 'Test Payment (Mock CBT Portal)',
        'description' => 'End-to-end integration test from test.nielitbhubaneswar.in',
        'return_url' => PaymentHub::appUrl('test-payment.php?returned=1'),
        'notify_url' => PaymentHub::appUrl('api/payment_hub_notify.php'),
        'payer' => [
            'name' => $payerName,
            'email' => $payerEmail,
            'phone' => $payerPhone
        ],
        'context' => [
            'type' => 'test_checkout',
            'initiated_at' => date('c')
        ]
    ];

    $res = PaymentHub::createCheckout($payload);
    if (!empty($res['ok']) && !empty($res['pay_url'])) {
        header('Location: ' . $res['pay_url']);
        exit;
    } else {
        $msg = 'Failed to create checkout: ' . ($res['error'] ?? 'Unknown error');
        $msgType = 'danger';
    }
}

// Handle Return from Payment Hub
if (isset($_GET['returned']) && !empty($_GET['hub_token'])) {
    $token = trim((string)$_GET['hub_token']);
    $returnStatus = PaymentHub::getStatus($token);
}

// Mask secret for UI
$secretLen = strlen($cfg['shared_secret']);
$maskedSecret = $secretLen > 8 
    ? substr($cfg['shared_secret'], 0, 4) . str_repeat('•', max(4, $secretLen - 8)) . substr($cfg['shared_secret'], -4)
    : ($secretLen > 0 ? '••••••••' : '(Not configured)');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Hub Integration Tester — NIELIT Mock Test Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .card { border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card-header { background-color: #ffffff; border-bottom: 1px solid #e2e8f0; font-weight: 600; }
        .badge-status { font-size: 0.85rem; padding: 0.35rem 0.65rem; }
        .code-box { background: #0f172a; color: #38bdf8; padding: 0.75rem 1rem; border-radius: 8px; font-family: monospace; font-size: 0.85rem; word-break: break-all; }
    </style>
</head>
<body class="py-4">
<div class="container" style="max-width: 900px;">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <span class="fs-2 text-primary"><i class="bi bi-credit-card-2-front"></i></span>
            <div>
                <h1 class="h4 mb-0 fw-bold">Payment Hub Connection & Test Tool</h1>
                <p class="text-muted small mb-0">Mock Test Portal (<code>test.nielitbhubaneswar.in</code>) &harr; Central Payment Hub (<code>payment.nielitbhubaneswar.in</code>)</p>
            </div>
        </div>
        <div>
            <a href="/admin/admin-dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Admin Dashboard</a>
        </div>
    </div>

    <?php if ($msg !== ''): ?>
        <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Returned Payment Result Banner -->
    <?php if ($returnStatus !== null): ?>
        <?php $paid = ($returnStatus['payment_status'] ?? '') === 'paid'; ?>
        <div class="card mb-4 border-<?php echo $paid ? 'success' : 'warning'; ?>">
            <div class="card-header bg-<?php echo $paid ? 'success text-white' : 'warning-subtle'; ?> d-flex justify-content-between align-items-center">
                <span><i class="bi bi-<?php echo $paid ? 'check-circle-fill' : 'exclamation-triangle'; ?> me-2"></i> Payment Return Status: <strong><?php echo strtoupper($returnStatus['payment_status'] ?? 'UNKNOWN'); ?></strong></span>
                <span class="badge bg-white text-dark">Invoice: <?php echo htmlspecialchars($returnStatus['invoice_number'] ?? 'N/A'); ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="text-muted small">Amount Paid</label>
                        <div class="h5 mb-0 fw-bold text-success">₹<?php echo number_format(((int)($returnStatus['amount_paise'] ?? 0)) / 100, 2); ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Razorpay Payment ID</label>
                        <div class="fw-semibold text-break"><?php echo htmlspecialchars($returnStatus['razorpay_payment_id'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Reference</label>
                        <div class="fw-semibold text-break"><?php echo htmlspecialchars($returnStatus['reference'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Receipt</label>
                        <div>
                            <?php if (!empty($returnStatus['hub_payment_id'])): ?>
                                <a href="<?php echo htmlspecialchars(PaymentHub::getReceiptUrl((int)$returnStatus['hub_payment_id'])); ?>" target="_blank" class="btn btn-sm btn-primary">
                                    <i class="bi bi-receipt me-1"></i> View Official Receipt
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Connection Status & Config -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-sliders me-2"></i> Current Configuration</span>
                    <?php if (PaymentHub::isReady()): ?>
                        <span class="badge bg-success badge-status"><i class="bi bi-check2"></i> Ready</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark badge-status"><i class="bi bi-exclamation"></i> Secret Needed</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-3">
                        <tr>
                            <th class="text-muted" style="width: 35%;">API Base:</th>
                            <td><code><?php echo htmlspecialchars($cfg['api_base']); ?></code></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Source Code:</th>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($cfg['source']); ?></span></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Shared Secret:</th>
                            <td><code><?php echo htmlspecialchars($maskedSecret); ?></code></td>
                        </tr>
                        <tr>
                            <th class="text-muted">App Base URL:</th>
                            <td><code><?php echo htmlspecialchars($cfg['app_url']); ?></code></td>
                        </tr>
                    </table>

                    <hr>

                    <!-- Update Secret Form -->
                    <form method="post">
                        <input type="hidden" name="action" value="save_secret">
                        <label class="form-label fw-semibold small mb-1">
                            Set / Update Shared Secret
                        </label>
                        <p class="text-muted small mb-2">
                            Copy this secret from: <br>
                            <code>https://payment.nielitbhubaneswar.in/admin/sources.php</code> &rarr; <strong>Mock test site (mock)</strong>
                        </p>
                        <div class="input-group input-group-sm mb-2">
                            <input type="password" name="shared_secret" class="form-control" placeholder="Paste 48-char hex secret from Payment Hub" required>
                            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Save Secret</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Connection Test / Ping -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-activity me-2"></i> 1. Ping Payment Hub
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <p class="text-muted small">
                            Sends an HMAC-signed test checkout request to Payment Hub. Verifies SSL connectivity, endpoint routing, and cryptographic secret matching.
                        </p>
                        
                        <?php if ($pingResult !== null): ?>
                            <?php if (!empty($pingResult['ok'])): ?>
                                <div class="alert alert-success py-2 mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-check-circle-fill fs-5"></i>
                                        <div>
                                            <strong>Connection & Signature Valid!</strong><br>
                                            <small class="text-muted">Payment Hub accepted our secret and generated checkout token.</small>
                                        </div>
                                    </div>
                                    <div class="mt-2 small text-break">
                                        Token: <code><?php echo htmlspecialchars(substr($pingResult['checkout_token'] ?? '', 0, 24)); ?>...</code>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger py-2 mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-x-circle-fill fs-5"></i>
                                        <div>
                                            <strong>Ping Failed:</strong> <?php echo htmlspecialchars($pingResult['error'] ?? 'Unknown'); ?>
                                        </div>
                                    </div>
                                    <?php if (strpos(($pingResult['error'] ?? ''), 'invalid signature') !== false): ?>
                                        <div class="mt-2 small">
                                            &rarr; The secret in <code>config/payment_hub.php</code> does not match the secret in Payment Hub's <code>hub_sources</code> table for <code>mock</code>. Update it on the left.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <form method="post" class="mt-2">
                        <input type="hidden" name="action" value="ping_hub">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-broadcast me-1"></i> Ping Payment Hub API
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Live Checkout Test -->
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <i class="bi bi-credit-card me-2"></i> 2. Make a Live Test Payment
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        This creates a live checkout on <code>payment.nielitbhubaneswar.in</code> and opens the Razorpay payment gateway. You can pay ₹1.00 via UPI (Google Pay, PhonePe, Paytm, BHIM) to test the complete end-to-end flow.
                    </p>

                    <form method="post" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="start_test_payment">
                        
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">Amount (₹)</label>
                            <input type="number" class="form-control" name="test_amount" value="1" min="1" max="100" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Payer Name</label>
                            <input type="text" class="form-control" name="payer_name" value="NIELIT Test Student" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Email</label>
                            <input type="email" class="form-control" name="payer_email" value="student@nielitbhubaneswar.in" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">Phone</label>
                            <input type="tel" class="form-control" name="payer_phone" value="9876543210" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100 fw-semibold" <?php echo !PaymentHub::isReady() ? 'disabled' : ''; ?>>
                                <i class="bi bi-arrow-right-circle me-1"></i> Pay Now
                            </button>
                        </div>
                    </form>

                    <?php if (!PaymentHub::isReady()): ?>
                        <div class="text-danger small mt-2">
                            <i class="bi bi-exclamation-triangle me-1"></i> Please configure the shared secret first before initiating a test payment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Integration Help Card -->
        <div class="col-12">
            <div class="card bg-light border-0">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1"></i> How Live Portals in Mock Test are Connected:</h6>
                    <div class="row g-3 small text-secondary">
                        <div class="col-md-6">
                            <strong>1. TP Slot Bookings (Training Partner):</strong><br>
                            When a TP creates a slot booking on <code>/tp/tp-slot-booking.php</code>, they can click <strong>"Pay Now"</strong> on <code>/tp/tp-booking-history.php</code>, which routes them to Payment Hub and upon return auto-approves their booking and logs the payment.
                        </div>
                        <div class="col-md-6">
                            <strong>2. Candidate Practice Exams:</strong><br>
                            Candidates who register for practice exams on <code>/candidate/practice-payment.php</code> can click <strong>"Pay Online via NIELIT Payment Portal"</strong> (₹50) and return with immediate exam unlock.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
