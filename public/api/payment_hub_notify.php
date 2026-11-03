<?php
/**
 * Server-to-server webhook endpoint for NIELIT Central Payment Hub
 * Receives payment status notifications from https://payment.nielitbhubaneswar.in
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/helpers/PaymentHub.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit();
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || trim($rawBody) === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Empty request body']);
    exit();
}

$sigHeader = $_SERVER['HTTP_X_PAYMENT_SIGNATURE'] 
    ?? $_SERVER['HTTP_X_HUB_SIGNATURE'] 
    ?? '';

if (!PaymentHub::verifyWebhookSignature($rawBody, $sigHeader)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid signature']);
    exit();
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit();
}

$event = strtolower(trim((string)($data['event'] ?? $data['status'] ?? '')));
$reference = trim((string)($data['reference'] ?? ''));
$token = trim((string)($data['hub_checkout_token'] ?? ''));
$paymentId = (int)($data['payment_id'] ?? 0);
$invoiceNumber = trim((string)($data['invoice_number'] ?? ''));
$razorpayPaymentId = trim((string)($data['razorpay_payment_id'] ?? ''));
$amountPaise = (int)($data['amount'] ?? 0);

// Only handle successful paid events
if ($event !== 'paid') {
    echo json_encode(['ok' => true, 'message' => 'Ignored event: ' . $event]);
    exit();
}

try {
    // 1. Check if it's a Training Partner Slot Booking
    if (preg_match('/^TP-BKG-(\d+)/', $reference, $m)) {
        $booking_id = (int)$m[1];
        $bStmt = $pdo->prepare("SELECT * FROM slot_bookings WHERE id = ?");
        $bStmt->execute([$booking_id]);
        $booking = $bStmt->fetch(PDO::FETCH_ASSOC);

        if ($booking) {
            $pdo->beginTransaction();

            // Auto-approve the booking
            if ($booking['status'] === 'Pending Payment' || $booking['status'] === 'Verification Pending') {
                $upd = $pdo->prepare("UPDATE slot_bookings SET status = 'Approved for Scheduling' WHERE id = ?");
                $upd->execute([$booking_id]);
            }

            // Insert payment log if not present
            $txn_id = $razorpayPaymentId !== '' ? $razorpayPaymentId : ($invoiceNumber !== '' ? $invoiceNumber : $token);
            $chk = $pdo->prepare("SELECT id FROM payments WHERE booking_id = ? AND reference_number = ? LIMIT 1");
            $chk->execute([$booking_id, $txn_id]);
            if (!$chk->fetchColumn()) {
                $amount = $amountPaise > 0 ? ($amountPaise / 100) : (float)$booking['total_fee'];
                $tp_id = (int)$booking['tp_id'];

                try {
                    $ins = $pdo->prepare("
                        INSERT INTO payments (booking_id, tp_id, amount, payment_mode, reference_number, transaction_id, payment_status, verified_by, verified_at, created_at)
                        VALUES (?, ?, ?, 'Payment Hub', ?, ?, 'Verified', 1, NOW(), NOW())
                    ");
                    $ins->execute([$booking_id, $tp_id, $amount, $txn_id, $txn_id]);
                } catch (PDOException $pe) {
                    $fallback = $pdo->prepare("
                        INSERT INTO payments (booking_id, tp_id, amount, payment_mode, reference_number, payment_status)
                        VALUES (?, ?, ?, 'Payment Hub', ?, 'Verified')
                    ");
                    $fallback->execute([$booking_id, $tp_id, $amount, $txn_id]);
                }
            }

            $pdo->commit();
            echo json_encode(['ok' => true, 'type' => 'tp_booking', 'booking_id' => $booking_id]);
            exit();
        }
    }

    // 2. Check if it's a Candidate Practice Exam Registration
    if (preg_match('/^PRACTICE-REG-(\d+)/', $reference, $m)) {
        $reg_id = (int)$m[1];
        $rStmt = $pdo->prepare("SELECT * FROM exam_registrations WHERE id = ?");
        $rStmt->execute([$reg_id]);
        $registration = $rStmt->fetch(PDO::FETCH_ASSOC);

        if ($registration) {
            $txn_id = $razorpayPaymentId !== '' ? $razorpayPaymentId : ($invoiceNumber !== '' ? $invoiceNumber : $token);
            $upd = $pdo->prepare("
                UPDATE exam_registrations 
                SET registration_status = 'approved', transaction_id = ? 
                WHERE id = ?
            ");
            $upd->execute([$txn_id, $reg_id]);

            echo json_encode(['ok' => true, 'type' => 'candidate_practice', 'registration_id' => $reg_id]);
            exit();
        }
    }

    echo json_encode(['ok' => true, 'message' => 'Processed reference: ' . $reference]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

