<?php
/**
 * NIELIT Mock Test Portal — Central Payment Portal Configuration
 * Connects to https://payment.nielitbhubaneswar.in
 */

return [
    // Enable or disable online payments through the central payment hub
    'enabled' => true,

    // Central Payment Portal Base URL
    'api_base' => getenv('PAYMENT_HUB_API_BASE') ?: 'https://payment.nielitbhubaneswar.in',

    // Registered source identifier in Payment Hub
    'source' => 'mock',

    // Shared secret from Payment Hub Admin -> Sources (for "mock" source)
    // You can also set this via environment variable or update here.
    'shared_secret' => getenv('PAYMENT_HUB_SHARED_SECRET') ?: 'mock_nielit_bbsr_shared_secret_2026',

    // Base URL of this CBT / Mock Test Portal
    'app_url' => getenv('APP_URL') ?: (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://')
        . ($_SERVER['HTTP_HOST'] ?? 'test.nielitbhubaneswar.in')
    ),
];

