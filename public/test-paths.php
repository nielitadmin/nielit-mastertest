<?php
echo "<h1>🔍 Path Testing Tool</h1>";

$base_url = 'http://localhost/nielit-bbsr-mock/public';
$pages = [
    'Home' => 'index.php',
    'Admin Login' => 'admin-login.php',
    'Candidate Login' => 'candidate-login.php',
    'Admin Dashboard' => 'admin-dashboard.php',
    'Candidate Dashboard' => 'candidate-dashboard.php',
    'Register' => 'register.php',
    '404 Page' => '404.php'
];

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Page</th><th>URL</th><th>Status</th></tr>";

foreach ($pages as $name => $file) {
    $url = "$base_url/$file";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $status = $http_code == 200 ? "✅ OK ($http_code)" : "❌ ERROR ($http_code)";
    $color = $http_code == 200 ? "green" : "red";
    
    echo "<tr>";
    echo "<td><strong>$name</strong></td>";
    echo "<td><a href='$url' target='_blank'>$url</a></td>";
    echo "<td style='color: $color;'>$status</td>";
    echo "</tr>";
}

echo "</table>";
?>