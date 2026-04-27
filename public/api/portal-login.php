<?php
/**
 * Token-to-session bridge.
 * Use after successful API authentication to create candidate portal session.
 */

require_once __DIR__ . '/../../config/api_config.php';

authenticateApiRequest();

$token = $_GET['token'] ?? null;
if (!$token) {
    sendApiError('Token is required', 400, 'MISSING_TOKEN');
}

$payload = validateAuthToken($token);
if (!$payload) {
    sendApiError('Invalid or expired token', 401, 'INVALID_TOKEN');
}

global $pdo;
$stmt = $pdo->prepare("SELECT id, username, full_name, role, is_active FROM users WHERE id = ? AND role = 'candidate' LIMIT 1");
$stmt->execute([(int) $payload['uid']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || (int) $user['is_active'] !== 1) {
    sendApiError('Candidate account not found or inactive', 401, 'ACCOUNT_INVALID');
}

session_name('NIELIT_CANDIDATE_SESSION');
session_start();

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['username'] = (string) $user['username'];
$_SESSION['user_role'] = (string) $user['role'];
$_SESSION['full_name'] = (string) $user['full_name'];
$_SESSION['login_time'] = time();

session_write_close();

header('Content-Type: text/html; charset=utf-8');
header('Location: ../candidate/candidate-dashboard.php');
exit;
