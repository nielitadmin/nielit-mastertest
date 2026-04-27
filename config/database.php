<?php
// Force PHP to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

// Load DB settings — use app/config/database-config.php if it exists,
// otherwise fall back to inline defaults (for shared hosting deployments).
$_dbConfigFile = __DIR__ . '/../app/config/database-config.php';
$dbConfig = file_exists($_dbConfigFile) ? require $_dbConfigFile : [];

$driver  = $dbConfig['driver']   ?? 'mysql';
$host    = $dbConfig['host']     ?? 'localhost';
$port    = $dbConfig['port']     ?? '3306';
$dbname  = $dbConfig['dbname']   ?? 'nielit_cbt_mock';
$dbuser  = $dbConfig['username'] ?? 'root';
$dbpass  = $dbConfig['password'] ?? '';
$charset = $dbConfig['charset']  ?? 'utf8mb4';

try {
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_PERSISTENT         => true,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if ($driver === 'pgsql') {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $dbuser, $dbpass, $options);
        $pdo->exec("SET TIME ZONE 'Asia/Kolkata'");
    } else {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=$charset", $dbuser, $dbpass, $options);
        $pdo->exec("SET time_zone = '+05:30'");
    }

} catch (PDOException $e) {
    error_log("Fatal DB Error: " . $e->getMessage());
    die("<h1>System Offline</h1><p>The database is currently undergoing maintenance. Please try again in a few minutes.</p>");
}
