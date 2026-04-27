<?php
// Force PHP to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

// Load canonical DB settings from app config.
$dbConfig = require __DIR__ . '/../app/config/database-config.php';

// Database Credentials
$driver = $dbConfig['driver'] ?? 'mysql';
$host   = $dbConfig['host'] ?? 'localhost';
$port   = $dbConfig['port'] ?? '3306';
$dbname = $dbConfig['dbname'] ?? 'nielit_cbt_mock';
$dbuser = $dbConfig['username'] ?? '';
$dbpass = $dbConfig['password'] ?? '';
$charset = $dbConfig['charset'] ?? 'utf8mb4';

try {
    // Advanced PDO Options for High Performance
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Strict error throwing
        PDO::ATTR_PERSISTENT         => true,                   // Keep connections open for heavy traffic
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Always fetch as associative array
        PDO::ATTR_EMULATE_PREPARES   => false                   // Native prepared statements for security
    ];

    if ($driver === 'pgsql') {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $dbuser, $dbpass, $options);
        $pdo->exec("SET TIME ZONE 'Asia/Kolkata'");
    } else {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=$charset", $dbuser, $dbpass, $options);
        $pdo->exec("SET time_zone = '+05:30'");
    }
    
} catch (PDOException $e) {
    // If the database goes offline, gracefully stop the script instead of leaking credentials
    error_log("Fatal DB Error: " . $e->getMessage());
    die("<h1>System Offline</h1><p>The database is currently undergoing maintenance. Please try again in a few minutes.</p>");
}
?>