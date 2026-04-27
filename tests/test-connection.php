<?php
// Database configuration
$host = 'localhost';
$port = '5432';
$dbname = 'nielit_cbt_mock';
$user = 'nielit_admin';
$password = 'NIELIT@BBSR2024';

echo "<h1 style='color: #0047ab;'>NIELIT Bhubaneswar CBT System</h1>";
echo "<h2>Database Connection Test</h2>";

try {
    // Create connection
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS! Connected to PostgreSQL database!</p>";
    
    // Get PostgreSQL version
    $version = $pdo->query("SELECT version()")->fetchColumn();
    echo "<p><strong>PostgreSQL Version:</strong> " . $version . "</p>";
    
    // List all tables
    $tables = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public'
        ORDER BY table_name
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Tables in database:</h3>";
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<li><strong>$table</strong> - $count records</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No tables found. You need to create tables first.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ ERROR: " . $e->getMessage() . "</p>";
    
    // Troubleshooting tips
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Make sure PostgreSQL is running (check Services)</li>";
    echo "<li>Verify database 'nielit_cbt_mock' exists</li>";
    echo "<li>Check username/password: nielit_admin / NIELIT@BBSR2024</li>";
    echo "<li>Ensure PostgreSQL is on port 5432</li>";
    echo "</ul>";
}
?>