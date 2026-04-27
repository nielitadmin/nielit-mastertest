<?php
session_name('NIELIT_ADMIN_SESSION');
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: admin-login.php");
    exit();
}

// ============================================================================
// NEW ARCHITECTURE: Import centralized database connection
// Path assumes this file is in: /public/admin/export-users.php
// ============================================================================
require_once __DIR__ . '/../../config/database.php';

try {
    // Set headers to force download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=NIELIT_User_Directory_' . date('Y-m-d') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open PHP output stream
    $output = fopen('php://output', 'w');

    // Write the CSV column headers
    fputcsv($output, [
        'System ID', 
        'Username', 
        'Full Name', 
        'Email Address', 
        'Account Role', 
        'Status', 
        'Registration ID (Candidate)', 
        'Mobile Number', 
        'Date of Birth', 
        'Account Created', 
        'Last Login'
    ]);

    // Fetch all user data, joined with candidate data
    $stmt = $pdo->query("
        SELECT 
            u.id, 
            u.username, 
            u.full_name, 
            u.email, 
            UPPER(u.role) as role, 
            CASE WHEN u.is_active THEN 'Active' ELSE 'Suspended' END as status,
            c.registration_number, 
            c.mobile, 
            c.date_of_birth,
            u.created_at, 
            u.last_login
        FROM users u
        LEFT JOIN candidates c ON u.id = c.user_id
        ORDER BY u.id DESC
    ");

    // Loop through the database results and write them as CSV rows
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    die("Database error during export: " . $e->getMessage());
}
?>