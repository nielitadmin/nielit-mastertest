<?php
/**
 * PaymentHub Helper Class for NIELIT Mock Test Portal
 * Integrates with https://payment.nielitbhubaneswar.in
 */

class PaymentHub
{
    private static ?array $config = null;

    /**
     * Load payment hub configuration
     */
    public static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $configFile = __DIR__ . '/../../config/payment_hub.php';
        if (file_exists($configFile)) {
            $cfg = require $configFile;
        } else {
            $cfg = [];
        }

        $baseAppUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://')
            . ($_SERVER['HTTP_HOST'] ?? 'test.nielitbhubaneswar.in');

        self::$config = [
            'enabled' => !empty($cfg['enabled']),
            'api_base' => rtrim($cfg['api_base'] ?? 'https://payment.nielitbhubaneswar.in', '/'),
            'source' => trim($cfg['source'] ?? 'mock'),
            'shared_secret' => trim($cfg['shared_secret'] ?? ''),
            'app_url' => rtrim($cfg['app_url'] ?? $baseAppUrl, '/'),
        ];

        return self::$config;
    }

    /**
     * Check if Payment Hub is configured and ready
     */
    public static function isReady(): bool
    {
        $cfg = self::config();
        return !empty($cfg['enabled']) && !empty($cfg['shared_secret']);
    }

    /**
     * Get the base URL of this mock portal
     */
    public static function appUrl(string $path = ''): string
    {
        $cfg = self::config();
        $base = $cfg['app_url'];
        if ($path === '') {
            return $base;
        }
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Create a checkout session on Payment Hub
     *
     * @param array $params [
     *   'reference' => string (unique internal ID, e.g. TP-BKG-12-169000000),
     *   'amount_paise' => int (amount in paise, e.g. 5000 for ₹50.00),
     *   'purpose' => string (e.g. 'Slot Booking', 'Practice Exam Fee'),
     *   'description' => string,
     *   'return_url' => string,
     *   'notify_url' => string (optional),
     *   'payer' => [
     *       'name' => string,
     *       'email' => string,
     *       'phone' => string,
     *       'external_id' => string,
     *   ],
     *   'context' => array (arbitrary key-values saved with checkout)
     * ]
     * @return array ['ok' => bool, 'pay_url' => string, 'checkout_token' => string, 'error' => string]
     */
    public static function createCheckout(array $params): array
    {
        $cfg = self::config();
        if (empty($cfg['shared_secret'])) {
            return ['ok' => false, 'error' => 'Payment Hub shared secret is not configured in config/payment_hub.php.'];
        }

        $payload = [
            'source' => $cfg['source'],
            'reference' => (string) $params['reference'],
            'amount' => (int) $params['amount_paise'],
            'currency' => 'INR',
            'purpose' => (string) ($params['purpose'] ?? 'Mock Test Portal Payment'),
            'description' => (string) ($params['description'] ?? ''),
            'return_url' => (string) $params['return_url'],
            'notify_url' => (string) ($params['notify_url'] ?? self::appUrl('api/payment_hub_notify.php')),
            'payer' => is_array($params['payer'] ?? null) ? $params['payer'] : [],
            'context' => is_array($params['context'] ?? null) ? $params['context'] : [],
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $rawBody, $cfg['shared_secret']);

        $endpoint = $cfg['api_base'] . '/api/v1/checkout.php';

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Payment-Signature: ' . $signature,
            'X-Hub-Signature: ' . $signature,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError) {
            return [
                'ok' => false,
                'error' => 'Cannot connect to Payment Hub (' . ($curlError ?: 'Connection failed') . ')',
            ];
        }

        $json = json_decode($response, true);
        if (!is_array($json)) {
            return [
                'ok' => false,
                'error' => 'Payment Hub returned invalid response (HTTP ' . $httpCode . ')',
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300 && !empty($json['ok'])) {
            return [
                'ok' => true,
                'pay_url' => (string) ($json['pay_url'] ?? ''),
                'checkout_token' => (string) ($json['checkout_token'] ?? ''),
                'expires_at' => (string) ($json['expires_at'] ?? ''),
            ];
        }

        return [
            'ok' => false,
            'error' => (string) ($json['error'] ?? 'Checkout creation failed (HTTP ' . $httpCode . ')'),
        ];
    }

    /**
     * Query payment status from Payment Hub by token
     *
     * @param string $token 64-char checkout token
     * @return array|null Status data or null on failure
     */
    public static function getStatus(string $token): ?array
    {
        $cfg = self::config();
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token));
        if (strlen($token) !== 64) {
            return null;
        }

        $endpoint = $cfg['api_base'] . '/api/v1/payment_status.php?token=' . urlencode($token);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        $json = json_decode($response, true);
        if (is_array($json) && !empty($json['ok']) && isset($json['status'])) {
            return $json['status'];
        }

        return null;
    }

    /**
     * Verify incoming webhook signature
     */
    public static function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (empty($signatureHeader)) {
            return false;
        }
        $cfg = self::config();
        $secret = $cfg['shared_secret'];
        if ($secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, strtolower(trim($signatureHeader)));
    }

    /**
     * Generate money receipt URL on Payment Hub
     */
    public static function getReceiptUrl(int $paymentId): string
    {
        $cfg = self::config();
        return $cfg['api_base'] . '/pay/receipt.php?id=' . $paymentId;
    }
}

