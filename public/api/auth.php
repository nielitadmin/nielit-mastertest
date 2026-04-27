<?php
/**
 * Candidate authentication API.
 * POST: login
 * GET : token validation
 */

require_once __DIR__ . '/../../config/api_config.php';

authenticateApiRequest();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    handleLogin();
}

if ($method === 'GET') {
    handleTokenValidation();
}

sendApiError('Method not allowed', 405, 'METHOD_NOT_ALLOWED');

function handleLogin()
{
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $username = trim((string) ($input['username'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    if ($username === '' || $password === '') {
        sendApiError('Username and password are required', 400, 'MISSING_CREDENTIALS');
    }

    $stmt = $pdo->prepare(
        "SELECT id, username, full_name, email, role, is_active, password_hash, password
         FROM users
         WHERE (username = ? OR email = ?) AND role = 'candidate'
         LIMIT 1"
    );
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendApiError('Candidate not found', 401, 'USER_NOT_FOUND');
    }

    if ((int) $user['is_active'] !== 1) {
        sendApiError('Account is inactive', 401, 'ACCOUNT_INACTIVE');
    }

    $validPassword = false;
    if (!empty($user['password_hash']) && password_verify($password, (string) $user['password_hash'])) {
        $validPassword = true;
    } elseif (isset($user['password_hash']) && hash_equals((string) $user['password_hash'], $password)) {
        $validPassword = true;
    } elseif (isset($user['password']) && hash_equals((string) $user['password'], $password)) {
        $validPassword = true;
    }

    if (!$validPassword) {
        sendApiError('Invalid credentials', 401, 'INVALID_CREDENTIALS');
    }

    $token = generateAuthToken((int) $user['id']);

    $update = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $update->execute([(int) $user['id']]);

    unset($user['password_hash'], $user['password']);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/public/api/auth.php')), '/\\');
    $portalLoginUrl = $scheme . '://' . $host . $basePath . '/api/portal-login.php?token=' . urlencode($token);

    sendApiResponse([
        'success' => true,
        'message' => 'Authentication successful',
        'token' => $token,
        'expires_at' => date('c', time() + API_TOKEN_EXPIRY),
        'portal_login_url' => $portalLoginUrl,
        'user' => $user,
    ]);
}

function handleTokenValidation()
{
    $token = $_GET['token'] ?? null;
    if (!$token) {
        $authHeader = getRequestHeaderValue('Authorization');
        if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
        }
    }

    if (!$token) {
        sendApiError('Token is required', 400, 'MISSING_TOKEN');
    }

    $payload = validateAuthToken($token);
    if (!$payload) {
        sendApiError('Invalid or expired token', 401, 'INVALID_TOKEN');
    }

    sendApiResponse([
        'success' => true,
        'valid' => true,
        'user_id' => (int) $payload['uid'],
        'expires_at' => date('c', (int) $payload['exp']),
    ]);
}
