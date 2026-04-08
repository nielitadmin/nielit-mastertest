<?php
// File: config/mailer.php

function sendNielitEmail($to_email, $to_name, $subject, $html_content) {
    // 🔑 YOUR BREVO API KEY (REST API)
    $api_key = 'YOUR_BREVO_API_KEY'; 
    
    // The official NIELIT sender details (Must be verified in Brevo)
    $sender_email = 'ritwiksonam1@gmail.com'; 
    $sender_name = 'NIELIT CBT System';

    // Build the data payload exactly how Brevo expects it
    $data = [
        'sender' => ['name' => $sender_name, 'email' => $sender_email],
        'to' => [['email' => $to_email, 'name' => $to_name]],
        'subject' => $subject,
        'htmlContent' => $html_content
    ];

    // Initialize PHP cURL
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'api-key: ' . $api_key,
        'content-type: application/json'
    ]);

    // =========================================================
    // 🛠️ FIX FOR LOCALHOST (XAMPP/WAMP) SSL ISSUES
    // =========================================================
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    // =========================================================

    // Execute the request and grab the response
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Optional: Uncomment this line to debug what Brevo is saying if it fails
    // error_log("Brevo Response: " . $response . " | HTTP Code: " . $http_code);
    
    curl_close($ch);

    // Return true if successful (HTTP 200, 201, or 202)
    return in_array($http_code, [200, 201, 202]);
}
?>