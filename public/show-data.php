<?php
echo "<h1 style='color: #0047ab;'>NIELIT CBT System - Database Status</h1>";

$host = 'localhost';
$port = '5432';
$dbname = 'nielit_cbt_mock';
$user = 'nielit_admin';
$password = 'NIELIT@BBSR2024';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Connected to database</p>";
    
    // Get all users
    $users = $pdo->query("SELECT id, username, email, full_name, role, created_at FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>📋 Users Table</h2>";
    if (count($users) > 0) {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr style='background: #0047ab; color: white;'><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Created At</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['full_name']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>{$user['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found.</p>";
    }
    
    // Get exam categories
    $categories = $pdo->query("SELECT * FROM exam_categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>📋 Exam Categories</h2>";
    if (count($categories) > 0) {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr style='background: #0047ab; color: white;'><th>ID</th><th>Code</th><th>Name</th><th>Duration</th><th>Total Marks</th><th>Pass %</th></tr>";
        foreach ($categories as $cat) {
            echo "<tr>";
            echo "<td>{$cat['id']}</td>";
            echo "<td>{$cat['category_code']}</td>";
            echo "<td>{$cat['category_name']}</td>";
            echo "<td>{$cat['duration_minutes']} min</td>";
            echo "<td>{$cat['total_marks']}</td>";
            echo "<td>{$cat['pass_percentage']}%</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No categories found.</p>";
    }
    
    // Get exam centers
    $centers = $pdo->query("SELECT * FROM exam_centers ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>📋 Exam Centers</h2>";
    if (count($centers) > 0) {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr style='background: #0047ab; color: white;'><th>ID</th><th>Code</th><th>Name</th><th>City</th><th>Capacity</th></tr>";
        foreach ($centers as $center) {
            echo "<tr>";
            echo "<td>{$center['id']}</td>";
            echo "<td>{$center['center_code']}</td>";
            echo "<td>{$center['center_name']}</td>";
            echo "<td>{$center['city']}</td>";
            echo "<td>{$center['capacity']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No centers found.</p>";
    }
    
    // Database summary
    echo "<h2>📊 Database Summary</h2>";
    $tables = ['users', 'candidates', 'exam_categories', 'questions', 'question_options', 
               'exam_centers', 'exam_sessions', 'exam_registrations', 'candidate_responses', 'exam_results'];
    
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 50%;'>";
    echo "<tr style='background: #0047ab; color: white;'><th>Table Name</th><th>Record Count</th></tr>";
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "<tr>";
        echo "<td>$table</td>";
        echo "<td style='text-align: center;'>$count</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>