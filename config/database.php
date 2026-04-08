<?php
// Force PHP to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

// Database Credentials
$host   = 'localhost';
$port   = '5432';
$dbname = 'nielit_cbt_mock';
$dbuser = 'nielit_admin';
$dbpass = 'NIELIT@BBSR2024';

try {
    // Advanced PDO Options for High Performance
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Strict error throwing
        PDO::ATTR_PERSISTENT         => true,                   // Keep connections open for heavy traffic
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Always fetch as associative array
        PDO::ATTR_EMULATE_PREPARES   => false                   // Native prepared statements for security
    ];

    // Create the connection
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $dbuser, $dbpass, $options);
    
    // Sync PostgreSQL timezone with PHP
    $pdo->exec("SET TIME ZONE 'Asia/Kolkata'");
    
} catch (PDOException $e) {
    // If the database goes offline, gracefully stop the script instead of leaking credentials
    error_log("Fatal DB Error: " . $e->getMessage());
    die("<h1>System Offline</h1><p>The database is currently undergoing maintenance. Please try again in a few minutes.</p>");
}
?>