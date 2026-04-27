<?php
/**
 * Central API configuration and helpers.
 */

define('NIELIT_API_KEY', 'b426f2591b862bd64bf55c37763af2540419da61f974061e59c0308b4ca66dd5');
define('API_TOKEN_EXPIRY', 1800); // 30 minutes
define('API_TOKEN_SECRET', 'change-this-secret-in-production-2026');
define('API_ALLOWED_ORIGIN', '*');

require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . API_ALLOWED_ORIGIN);
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function sendApiResponse($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function sendApiError($message, $status = 400, $code = null)
{
    $payload = [
        'success' => false,
        'message' => $message,
    ];

    if ($code !== null) {
        $payload['error_code'] = $code;
    }

    sendApiResponse($payload, $status);
}

function getRequestHeaderValue($key)
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $name => $value) {
        if (strtolower($name) === strtolower($key)) {
            return $value;
        }
    }
    return null;
}

function authenticateApiRequest()
{
    $apiKey = getRequestHeaderValue('X-API-Key');
    if (!$apiKey) {
        $apiKey = $_GET['api_key'] ?? null;
    }

    if (!$apiKey || !hash_equals(NIELIT_API_KEY, $apiKey)) {
        sendApiError('Invalid API key', 401, 'INVALID_API_KEY');
    }

    return ['authenticated' => true];
}

function base64UrlEncode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data)
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function generateAuthToken($userId)
{
    $payload = [
        'uid' => (int) $userId,
        'iat' => time(),
        'exp' => time() + API_TOKEN_EXPIRY,
        'rnd' => bin2hex(random_bytes(12)),
    ];

    $payloadEncoded = base64UrlEncode(json_encode($payload));
    $signature = hash_hmac('sha256', $payloadEncoded, API_TOKEN_SECRET, true);

    return $payloadEncoded . '.' . base64UrlEncode($signature);
}

function validateAuthToken($token)
{
    $parts = explode('.', (string) $token);
    if (count($parts) !== 2) {
        return false;
    }

    $payloadEncoded = $parts[0];
    $signatureEncoded = $parts[1];

    $expectedSignature = hash_hmac('sha256', $payloadEncoded, API_TOKEN_SECRET, true);
    $actualSignature = base64UrlDecode($signatureEncoded);

    if (!is_string($actualSignature) || !hash_equals($expectedSignature, $actualSignature)) {
        return false;
    }

    $payloadJson = base64UrlDecode($payloadEncoded);
    $payload = json_decode($payloadJson, true);

    if (!is_array($payload) || empty($payload['uid']) || empty($payload['exp'])) {
        return false;
    }

    if ((int) $payload['exp'] < time()) {
        return false;
    }

    return $payload;
}
