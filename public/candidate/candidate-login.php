<?php
session_name('NIELIT_CANDIDATE_SESSION');
session_start();

// IMPORTANT FIX: Check if already logged in properly
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'candidate') {
    header("Location: candidate-dashboard.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$error = '';

// Direct API-key login mode: allows trusted external apps to open this page
// with an API key plus a candidate identifier and create the portal session.
if (isset($_GET['api_key']) && trim((string) $_GET['api_key']) !== '' && !isset($_GET['token'])) {
    $apiKey = trim((string) $_GET['api_key']);
    $identifier = trim((string) ($_GET['username'] ?? $_GET['student_id'] ?? $_GET['email'] ?? ''));
    $directPassword = trim((string) ($_GET['password'] ?? ''));

    if ($identifier === '') {
        $error = 'Username, student_id, or email is required for API-based login.';
    } elseif ($directPassword === '') {
        $error = 'Password is required for API-based login.';
    } elseif (!hash_equals('b426f2591b862bd64bf55c37763af2540419da61f974061e59c0308b4ca66dd5', $apiKey)) {
        $error = 'Invalid API key.';
    } else {
        $stmt = $pdo->prepare(" 
            SELECT u.id, u.username, u.full_name, u.role, u.is_active, c.registration_number
            FROM users u
            LEFT JOIN candidates c ON u.id = c.user_id
            WHERE (u.username = ? OR u.email = ? OR c.registration_number = ?)
              AND u.role = 'candidate'
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $student = false;

            try {
                $studentStmt = $pdo->prepare(" 
                    SELECT student_id, name, email, mobile, course_id, training_center, status, dob
                    FROM students
                    WHERE student_id = ? OR email = ?
                    LIMIT 1
                ");
                $studentStmt->execute([$identifier, $identifier]);
                $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $localStudentError) {
                $student = fetchStudentFromRemoteApi($identifier);
            }

            if ($student && strtolower((string) $student['status']) === 'active') {
                try {
                    $pdo->beginTransaction();

                    $existingUserStmt = $pdo->prepare("SELECT id, username, full_name, role, is_active FROM users WHERE email = ? OR username = ? LIMIT 1");
                    $existingUserStmt->execute([(string) $student['email'], (string) $student['student_id']]);
                    $existingUser = $existingUserStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existingUser) {
                        $user = $existingUser;
                    } else {
                        $generatedUsername = 'cand_' . preg_replace('/[^a-zA-Z0-9]/', '', (string) $student['student_id']);
                        $generatedPassword = bin2hex(random_bytes(8));
                        $passwordHash = password_hash($generatedPassword, PASSWORD_DEFAULT);

                        $createUserStmt = $pdo->prepare(" 
                            INSERT INTO users (username, password_hash, email, full_name, role, is_active, created_at)
                            VALUES (?, ?, ?, ?, 'candidate', 1, NOW())
                        ");
                        $createUserStmt->execute([
                            $generatedUsername,
                            $passwordHash,
                            (string) $student['email'],
                            (string) $student['name']
                        ]);
                        $newUserId = (int) $pdo->lastInsertId();

                        $candidateStmt = $pdo->prepare("SELECT user_id FROM candidates WHERE registration_number = ? LIMIT 1");
                        $candidateStmt->execute([(string) $student['student_id']]);
                        $candidate = $candidateStmt->fetch(PDO::FETCH_ASSOC);

                        if (!$candidate) {
                            $createCandidateStmt = $pdo->prepare(" 
                                INSERT INTO candidates (user_id, registration_number, date_of_birth, mobile)
                                VALUES (?, ?, ?, ?)
                            ");
                            $createCandidateStmt->execute([
                                $newUserId,
                                (string) $student['student_id'],
                                (string) ($student['dob'] ?? null),
                                (string) ($student['mobile'] ?? '')
                            ]);
                        }

                        $user = [
                            'id' => $newUserId,
                            'username' => $generatedUsername,
                            'full_name' => (string) $student['name'],
                            'role' => 'candidate',
                            'is_active' => 1,
                            'registration_number' => (string) $student['student_id'],
                        ];
                    }

                    $pdo->commit();
                } catch (Throwable $provisionError) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Student found, but portal account provisioning failed.';
                }
            }
        }

        if ($user && (int) $user['is_active'] === 1) {
            $passwordHash = password_hash($directPassword, PASSWORD_DEFAULT);
            $syncPasswordStmt = $pdo->prepare('UPDATE users SET password_hash = ?, last_login = NOW() WHERE id = ?');
            $syncPasswordStmt->execute([$passwordHash, (int) $user['id']]);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = (string) $user['username'];
            $_SESSION['user_role'] = (string) $user['role'];
            $_SESSION['full_name'] = (string) $user['full_name'];
            $_SESSION['login_time'] = time();

            $candidateLinkStmt = $pdo->prepare('SELECT user_id FROM candidates WHERE user_id = ? LIMIT 1');
            $candidateLinkStmt->execute([(int) $user['id']]);
            $candidateLink = $candidateLinkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$candidateLink && !empty($user['registration_number'])) {
                $linkCandidateStmt = $pdo->prepare('INSERT INTO candidates (user_id, registration_number) VALUES (?, ?)');
                $linkCandidateStmt->execute([(int) $user['id'], (string) $user['registration_number']]);
            }

            session_write_close();
            header('Location: candidate-dashboard.php');
            exit();
        }

        $error = 'Candidate not found or account disabled.';
    }
}

function fetchStudentFromRemoteApi($identifier)
{
    $apiUrl = 'https://nielitbhubaneswar.in/api/v1/students.php?action=get&student_id=' . urlencode($identifier);
    $headers = [
        'X-API-Key: ' . 'b426f2591b862bd64bf55c37763af2540419da61f974061e59c0308b4ca66dd5',
        'Accept: application/json',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
            ],
        ]);
        $response = @file_get_contents($apiUrl, false, $context);
    }

    if (!$response) {
        return false;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return false;
    }

    $student = $decoded['data']['student'] ?? null;
    if (!is_array($student)) {
        return false;
    }

    return $student;
}

// API bridge mode: allow external app to open candidate login URL with token + api_key.
if (isset($_GET['token'])) {
    $token = trim((string) ($_GET['token'] ?? ''));
    $apiKey = trim((string) ($_GET['api_key'] ?? ''));

    if ($token !== '' && $apiKey !== '') {
        $bridgeUrl = '../api/portal-login.php?token=' . urlencode($token) . '&api_key=' . urlencode($apiKey);
        header('Location: ' . $bridgeUrl);
        exit();
    }

    $error = 'Missing token or API key for API-based portal login.';
}

// ============================================================================
// GOOGLE OAuth 2.0 CONFIGURATION
// ============================================================================
// Google OAuth credentials — set these in app/config/google-config.php
// Copy app/config/google-config.example.php to google-config.php and fill in your values.
$_googleConfigFile = __DIR__ . '/../../app/config/google-config.php';
$googleConfig         = file_exists($_googleConfigFile) ? require $_googleConfigFile : [];
$google_client_id     = $googleConfig['client_id']     ?? '';
$google_client_secret = $googleConfig['client_secret'] ?? '';
$google_redirect_url  = $googleConfig['redirect_url']  ?? '';
$google_oauth_url     = '';

if ($google_client_id !== '') {
    $google_oauth_url = 'https://accounts.google.com/o/oauth2/v2/auth?scope='
        . urlencode('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email')
        . '&redirect_uri=' . urlencode($google_redirect_url)
        . '&response_type=code&client_id=' . $google_client_id
        . '&access_type=online&prompt=select_account';
}

// ============================================================================
// 1. HANDLE GOOGLE LOGIN & AUTO-REGISTRATION
// ============================================================================
if (isset($_GET['code'])) {
    
    // Step A: Exchange the auth code for an Access Token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $_GET['code'],
        'client_id' => $google_client_id,
        'client_secret' => $google_client_secret,
        'redirect_uri' => $google_redirect_url,
        'grant_type' => 'authorization_code'
    ]));
    $token_response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($token_response['access_token'])) {
        $access_token = $token_response['access_token'];

        // Step B: Fetch the User's Profile Data from Google
        $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
        $google_profile = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($google_profile['email'])) {
            $google_email = $google_profile['email'];
            $google_name = $google_profile['name'] ?? 'New Candidate';

            // Step C: Check if this email exists in our database for Candidates
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'candidate'");
            $stmt->execute([$google_email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // EXISTING CANDIDATE - Log them in
                if ($user['is_active']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['login_time'] = time();

                    // Update last login
                    $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $update->execute([$user['id']]);

                    header("Location: candidate-dashboard.php");
                    exit();
                } else {
                    $error = "Your account has been deactivated. Please contact support.";
                }
            } else {
                // NEW CANDIDATE - AUTO REGISTER THEM INSTANTLY!
                try {
                    // Create a unique username for the candidate
                    $email_prefix = explode('@', $google_email)[0];
                    $new_username = 'cand_' . preg_replace('/[^a-zA-Z0-9]/', '', $email_prefix) . '_' . rand(100,999);
                    
                    $pdo->beginTransaction();
                    $insertStmt = $pdo->prepare("
                        INSERT INTO users (username, password, password_hash, full_name, email, role, is_active) 
                        VALUES (?, 'GOOGLE_SSO_USER', 'GOOGLE_SSO_USER', ?, ?, 'candidate', true)
                    ");
                    $insertStmt->execute([$new_username, $google_name, $google_email]);
                    $new_user_id = (int)$pdo->lastInsertId();
                    $pdo->commit();

                    // 🟢 =======================================================
                    // SEND WELCOME EMAIL VIA BREVO SMTP
                    // =======================================================
                    
                    require_once __DIR__ . '/../../config/mailer.php'; 
                    
                    $subject = "Welcome to the NIELIT Assessment Portal!";
                    $htmlBody = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden;'>
                        <div style='background: #059669; padding: 20px; text-align: center; color: white;'>
                            <h2 style='margin: 0;'>Candidate Registration Successful</h2>
                        </div>
                        <div style='padding: 30px; background: #FAFAFA; color: #333;'>
                            <p style='font-size: 16px;'>Hello <strong>{$google_name}</strong>,</p>
                            <p style='font-size: 15px; line-height: 1.6;'>Your account has been successfully created via Google Sign-In. You can now access your dashboard to view upcoming exams, download admit cards, and launch your computer-based tests.</p>
                            
                            <div style='background: #FFFFFF; border-left: 4px solid #059669; padding: 15px; margin: 25px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.05);'>
                                <p style='margin: 0 0 5px 0; font-size: 13px; color: #64748B; text-transform: uppercase;'>Your System Assigned Username:</p>
                                <p style='margin: 0; font-size: 18px; font-weight: bold; color: #0F172A;'>{$new_username}</p>
                            </div>
                            
                            <p style='font-size: 14px; color: #64748B;'><em>Note: Since you registered with Google, you do not need a password. Simply click 'Sign in with Google' whenever you return.</em></p>
                        </div>
                        <div style='background: #F1F5F9; padding: 15px; text-align: center; font-size: 12px; color: #64748B;'>
                            &copy; " . date('Y') . " National Institute of Electronics & Information Technology.
                        </div>
                    </div>";

                    // Call the custom mailer function defined in your config/mailer.php
                    try {
                        sendNielitEmail($google_email, $google_name, $subject, $htmlBody);
                    } catch (Exception $mailEx) {
                        error_log("Failed to send Welcome email to Candidate: " . $mailEx->getMessage());
                    }
                    
                    // =======================================================

                    // Instantly log in the brand new candidate
                    $_SESSION['user_id'] = $new_user_id;
                    $_SESSION['username'] = $new_username;
                    $_SESSION['user_role'] = 'candidate';
                    $_SESSION['full_name'] = $google_name;
                    $_SESSION['login_time'] = time();
                    
                    header("Location: candidate-dashboard.php");
                    exit();

                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Failed to auto-register Google account. Error: " . $e->getMessage();
                }
            }
        } else {
            $error = "Failed to retrieve email from Google. Please try again.";
        }
    } else {
        $error = "Google Authentication failed. Please try again.";
    }
}

// ============================================================================
// 2. HANDLE STANDARD MANUAL LOGIN (Student ID + Password via Remote API)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['manual_login'])) {

    $studentId = trim($_POST['username']);   // field is labelled Student ID
    $password  = $_POST['password'];

    // --- CAPTCHA VERIFICATION LOGIC ---
    $user_captcha = isset($_POST['captcha_answer']) ? (int)$_POST['captcha_answer'] : 0;
    $real_captcha = isset($_SESSION['captcha_result']) ? (int)$_SESSION['captcha_result'] : -1;

    if (empty($studentId) || empty($password)) {
        $error = "Please enter both Student ID and password.";
    } elseif ($user_captcha !== $real_captcha) {
        $error = "Incorrect math verification. Please try again.";
    } else {
        try {
            // ----------------------------------------------------------------
            // Step 1: Check if a local portal account already exists for this
            //         Student ID (stored as registration_number in candidates).
            // ----------------------------------------------------------------
            $localStmt = $pdo->prepare("
                SELECT u.id, u.username, u.full_name, u.email, u.role, u.is_active,
                       u.password_hash, c.registration_number
                FROM users u
                LEFT JOIN candidates c ON u.id = c.user_id
                WHERE (c.registration_number = ? OR u.username = ?)
                  AND u.role = 'candidate'
                LIMIT 1
            ");
            $localStmt->execute([$studentId, $studentId]);
            $localUser = $localStmt->fetch(PDO::FETCH_ASSOC);

            $apiStudent   = null;   // data from remote API (if fetched)
            $passwordValid = false;

            if ($localUser) {
                // ----------------------------------------------------------------
                // Local account found — verify password against stored hash first,
                // then fall back to the remote API hash for freshness.
                // ----------------------------------------------------------------
                if ((int)$localUser['is_active'] !== 1) {
                    $error = "You have not yet been approved by the Course Coordinator or Admin. Please contact your training center.";
                } else {
                    if (password_verify($password, $localUser['password_hash'] ?? '')) {
                        $passwordValid = true;
                    } else {
                        // Hash may be stale — re-check against the live API password hash
                        $apiStudent = fetchStudentFromRemoteApi($studentId);
                        if ($apiStudent && isset($apiStudent['password'])) {
                            if (password_verify($password, $apiStudent['password'])) {
                                $passwordValid = true;
                                // Sync the fresh hash locally
                                $syncStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                                $syncStmt->execute([password_hash($password, PASSWORD_DEFAULT), (int)$localUser['id']]);
                            }
                        }
                    }

                    if ($passwordValid) {
                        $_SESSION['user_id']    = (int)$localUser['id'];
                        $_SESSION['username']   = (string)$localUser['username'];
                        $_SESSION['user_role']  = 'candidate';
                        $_SESSION['full_name']  = (string)$localUser['full_name'];
                        $_SESSION['student_id'] = $studentId;
                        $_SESSION['login_time'] = time();

                        // Sync latest API profile data into local DB on every login
                        if ($apiStudent) {
                            $fullAddress = implode(', ', array_filter([
                                $apiStudent['address'] ?? '',
                                $apiStudent['city']    ?? '',
                                $apiStudent['state']   ?? '',
                                $apiStudent['pincode'] ?? '',
                            ]));
                            $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?")
                                ->execute([(string)$apiStudent['name'], (int)$localUser['id']]);
                            $pdo->prepare("UPDATE candidates SET mobile = ?, date_of_birth = ?, address = ? WHERE user_id = ?")
                                ->execute([
                                    (string)($apiStudent['mobile'] ?? ''),
                                    (string)($apiStudent['dob']    ?? ''),
                                    $fullAddress,
                                    (int)$localUser['id'],
                                ]);
                            $_SESSION['full_name']       = (string)$apiStudent['name'];
                            $_SESSION['email']           = (string)($apiStudent['email']           ?? '');
                            $_SESSION['mobile']          = (string)($apiStudent['mobile']          ?? '');
                            $_SESSION['course_id']       = (int)($apiStudent['course_id']          ?? 0);
                            $_SESSION['course_name']     = (string)($apiStudent['course_name']     ?? '');
                            $_SESSION['training_center'] = (string)($apiStudent['training_center'] ?? '');
                            $_SESSION['dob']             = (string)($apiStudent['dob']             ?? '');
                            $_SESSION['gender']          = (string)($apiStudent['gender']          ?? '');
                        }

                        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([(int)$localUser['id']]);
                        session_write_close();
                        header("Location: candidate-dashboard.php");
                        exit();
                    } else {
                        $error = "Invalid Student ID or password.";
                    }
                }
            } else {
                // ----------------------------------------------------------------
                // No local account — fetch student from the remote NIELIT API and
                // verify the password against the API-returned bcrypt hash.
                // ----------------------------------------------------------------
                $apiStudent = fetchStudentFromRemoteApi($studentId);

                if (!$apiStudent) {
                    $error = "Student ID not found. Please check your credentials.";
                } elseif (strtolower((string)($apiStudent['status'] ?? '')) !== 'active') {
                    $error = "You have not yet been approved by the Course Coordinator or Admin. Please contact your training center.";
                } elseif (!isset($apiStudent['password']) || !password_verify($password, $apiStudent['password'])) {
                    $error = "Invalid Student ID or password.";
                } else {
                    // Password verified against API hash — provision a local portal account.
                    $passwordValid = true;

                    try {
                        $pdo->beginTransaction();

                        // Build a full address string from API fields
                        $fullAddress = implode(', ', array_filter([
                            $apiStudent['address'] ?? '',
                            $apiStudent['city']    ?? '',
                            $apiStudent['state']   ?? '',
                            $apiStudent['pincode'] ?? '',
                        ]));

                        // Check if a user row already exists by email (edge case)
                        $existingStmt = $pdo->prepare("SELECT id, username, full_name, role, is_active FROM users WHERE email = ? LIMIT 1");
                        $existingStmt->execute([(string)$apiStudent['email']]);
                        $existingUser = $existingStmt->fetch(PDO::FETCH_ASSOC);

                        if ($existingUser) {
                            $newUserId         = (int)$existingUser['id'];
                            $generatedUsername = (string)$existingUser['username'];
                            $fullName          = (string)$apiStudent['name'];

                            // Sync latest profile data into users row
                            $pdo->prepare("UPDATE users SET full_name = ?, last_login = NOW() WHERE id = ?")
                                ->execute([$fullName, $newUserId]);
                        } else {
                            $generatedUsername = (string)$apiStudent['student_id'];
                            $fullName          = (string)$apiStudent['name'];
                            $localHash         = password_hash($password, PASSWORD_DEFAULT);

                            $createStmt = $pdo->prepare("
                                INSERT INTO users (username, password_hash, email, full_name, role, is_active, created_at)
                                VALUES (?, ?, ?, ?, 'candidate', 1, NOW())
                            ");
                            $createStmt->execute([
                                $generatedUsername,
                                $localHash,
                                (string)$apiStudent['email'],
                                $fullName,
                            ]);
                            $newUserId = (int)$pdo->lastInsertId();
                        }

                        // Check candidates table by registration_number first (has unique index)
                        $candCheckStmt = $pdo->prepare("SELECT user_id FROM candidates WHERE registration_number = ? LIMIT 1");
                        $candCheckStmt->execute([(string)$apiStudent['student_id']]);
                        $existingCand = $candCheckStmt->fetch();

                        if ($existingCand) {
                            // registration_number already exists — use that user_id as the canonical one
                            $newUserId = (int)$existingCand['user_id'];
                            $pdo->prepare("
                                UPDATE candidates SET date_of_birth = ?, mobile = ?, address = ?
                                WHERE user_id = ?
                            ")->execute([
                                (string)($apiStudent['dob']    ?? ''),
                                (string)($apiStudent['mobile'] ?? ''),
                                $fullAddress,
                                $newUserId,
                            ]);
                        } else {
                            // Safe to upsert — no row with this registration_number exists
                            $pdo->prepare("
                                INSERT INTO candidates (user_id, registration_number, date_of_birth, mobile, address)
                                VALUES (?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE
                                    date_of_birth = VALUES(date_of_birth),
                                    mobile        = VALUES(mobile),
                                    address       = VALUES(address)
                            ")->execute([
                                $newUserId,
                                (string)$apiStudent['student_id'],
                                (string)($apiStudent['dob']    ?? ''),
                                (string)($apiStudent['mobile'] ?? ''),
                                $fullAddress,
                            ]);
                        }

                        $pdo->commit();

                        // Store rich session data for use across the portal
                        $_SESSION['user_id']          = $newUserId;
                        $_SESSION['username']         = $generatedUsername;
                        $_SESSION['user_role']        = 'candidate';
                        $_SESSION['full_name']        = $fullName;
                        $_SESSION['student_id']       = $studentId;
                        $_SESSION['email']            = (string)($apiStudent['email']           ?? '');
                        $_SESSION['mobile']           = (string)($apiStudent['mobile']          ?? '');
                        $_SESSION['course_id']        = (int)($apiStudent['course_id']          ?? 0);
                        $_SESSION['course_name']      = (string)($apiStudent['course_name']     ?? '');
                        $_SESSION['training_center']  = (string)($apiStudent['training_center'] ?? '');
                        $_SESSION['dob']              = (string)($apiStudent['dob']             ?? '');
                        $_SESSION['gender']           = (string)($apiStudent['gender']          ?? '');
                        $_SESSION['login_time']       = time();

                        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$newUserId]);
                        session_write_close();
                        header("Location: candidate-dashboard.php");
                        exit();

                    } catch (Throwable $provisionErr) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        error_log("Candidate provision error: " . $provisionErr->getMessage());
                        $error = "Login verified but portal account setup failed: " . $provisionErr->getMessage();
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "System Database Offline. Please contact the technical team.";
            error_log("Candidate Login DB error: " . $e->getMessage());
        }
    }
}

// Generate new Captcha for page load
$num1 = rand(1, 10);
$num2 = rand(1, 10);
$_SESSION['captcha_result'] = $num1 + $num2;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Login — NIELIT CBT System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            /* Professional Light Theme Colors */
            --primary: #1D4ED8;        
            --primary-bg: #DBEAFE;     
            --candidate: #059669;      
            --candidate-light: #10B981;
            --candidate-bg: #D1FAE5;
            --text-dark: #0F172A;
            --text-muted: #475569;
            --bg-body: #F4F7FB;
            --surface: #FFFFFF;
            --border: #CBD5E1;
            --input-bg: #F8FAFC;
            --input-border: #E2E8F0;
            --error-bg: #FEE2E2;
            --error-text: #DC2626;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 40px -10px rgba(5, 150, 105, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        /* 🆕 FIX: STRICT 100VH TO KILL SCROLLING completely */
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-dark);
            height: 100vh; /* Locked height */
            overflow: hidden; /* Absolutely no scroll */
            display: flex;
            align-items: center; /* Perfect vertical centering */
            justify-content: center; /* Perfect horizontal centering */
            position: relative;
            background-color: var(--bg-body);
            padding: 0 20px;
        }

        /* --- 3D MOVING BACKGROUND --- */
        .ambient-bg {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1; overflow: hidden; pointer-events: none;
            background: radial-gradient(circle at 50% 0%, #DCE6F1 0%, #E8F0F8 50%, #F4F7FB 100%);
            perspective: 1000px;
        }

        .shape {
            position: absolute;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.6), rgba(5, 150, 105, 0.05));
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 15px 35px rgba(5, 150, 105, 0.08), inset 0 0 20px rgba(255, 255, 255, 0.8);
            animation: float-3d 20s infinite linear;
        }

        .cube { width: 140px; height: 140px; border-radius: 28px; top: 15%; left: 10%; animation-duration: 28s; }
        .ring { width: 220px; height: 220px; border-radius: 50%; border: 35px solid rgba(255,255,255,0.3); top: 55%; right: 5%; animation-duration: 35s; animation-direction: reverse; background: transparent; box-shadow: 0 15px 35px rgba(5, 150, 105, 0.05); }
        .pyramid { width: 90px; height: 90px; border-radius: 16px; bottom: 15%; left: 20%; animation-duration: 22s; }
        .sphere { width: 180px; height: 180px; border-radius: 50%; top: 8%; right: 12%; animation-duration: 40s; }

        @keyframes float-3d {
            0% { transform: translateY(0) rotateX(0deg) rotateY(0deg) rotateZ(0deg); }
            50% { transform: translateY(-50px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); }
            100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); }
        }

        /* --- TOP NAV --- */
        .top-nav {
            position: absolute; top: 20px; left: 40px; z-index: 10;
        }
        
        .btn-back-top {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px);
            border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px;
            color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 13px;
            transition: all 0.3s; box-shadow: var(--shadow-sm);
        }
        .btn-back-top:hover {
            background: var(--surface); color: var(--candidate);
            transform: translateX(-3px); box-shadow: var(--shadow-md);
        }

        /* --- COMPACT LOGIN CARD --- */
        .login-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 400px;
            max-height: 95vh; 
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(255, 255, 255, 1);
            border-radius: 20px;
            backdrop-filter: blur(24px);
            animation: fadeUp 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
            box-shadow: var(--shadow-lg);
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .card-accent {
            height: 4px; flex-shrink: 0;
            background: linear-gradient(90deg, var(--candidate), var(--candidate-light));
        }

        /* --- HEADER (TIGHTENED) --- */
        .card-head {
            padding: 20px 25px 10px;
            text-align: center;
            flex-shrink: 0;
        }

        .icon-wrap {
            width: 45px; 
            height: 45px;
            border-radius: 14px;
            background: var(--candidate-bg);
            color: var(--candidate);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; margin: 0 auto 10px;
            box-shadow: 0 8px 15px rgba(5, 150, 105, 0.1);
        }

        .card-title { font-size: 20px; font-weight: 800; color: var(--text-dark); margin-bottom: 2px; }
        .card-subtitle { font-size: 12px; color: var(--text-muted); font-weight: 500; }

        /* --- BODY (SCROLLABLE IF ON TINY PHONES, OTHERWISE STATIC) --- */
        .card-body {
            padding: 0 25px 10px;
            overflow-y: auto; 
            scrollbar-width: none; 
        }
        .card-body::-webkit-scrollbar { display: none; } 

        .sep { height: 1px; background: var(--border); margin-bottom: 15px; }

        /* --- ERROR BOX --- */
        .error-box {
            background: var(--error-bg); border: 1px solid #FCA5A5; border-radius: 8px;
            padding: 8px 12px; display: flex; align-items: center; gap: 8px;
            margin-bottom: 12px; animation: shake 0.4s ease;
        }
        .error-icon { color: var(--error-text); font-size: 14px; }
        .error-text { font-size: 11px; color: var(--error-text); font-weight: 600; }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* --- GOOGLE BUTTON (TIGHTENED) --- */
        .btn-google {
            display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%;
            padding: 10px; background: white; border: 1px solid var(--border); border-radius: 8px;
            color: var(--text-dark); font-weight: 700; font-size: 13px; cursor: pointer;
            transition: all 0.3s ease; text-decoration: none; box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            margin-bottom: 12px;
        }
        .btn-google:hover { background: #F8FAFC; border-color: #CBD5E1; transform: translateY(-2px); }
        .btn-google img { width: 16px; height: 16px; }

        .divider {
            display: flex; align-items: center; text-align: center; color: var(--text-muted);
            font-size: 10px; font-weight: 700; text-transform: uppercase; margin: 12px 0;
        }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid var(--border); }
        .divider:not(:empty)::before { margin-right: .5em; }
        .divider:not(:empty)::after { margin-left: .5em; }

        /* --- FORM (TIGHTENED) --- */
        .form-group { margin-bottom: 12px; }

        .label-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .label-flex label { margin-bottom: 0; }
        .forgot-link { font-size: 11px; color: var(--primary); text-decoration: none; font-weight: 700; transition: color 0.3s; }
        .forgot-link:hover { color: var(--candidate); text-decoration: underline; }

        .form-group label { display: block; font-size: 11px; font-weight: 700; color: var(--text-dark); margin-bottom: 4px; }

        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 13px; color: var(--text-muted); pointer-events: none; transition: color 0.3s; }

        .form-group input:not([type="number"]) {
            width: 100%; padding: 10px 12px 10px 36px; background: var(--input-bg);
            border: 1px solid var(--input-border); border-radius: 8px;
            color: var(--text-dark); font-family: inherit; font-size: 13px; font-weight: 500;
            outline: none; transition: all 0.3s;
        }
        .form-group input:focus { border-color: var(--candidate); box-shadow: 0 0 0 3px var(--candidate-bg); background: var(--surface); }
        .form-group input:focus + .input-icon, .input-wrap:focus-within .input-icon { color: var(--candidate); }

        .toggle-pw { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 13px; color: var(--text-muted); outline: none; }
        .toggle-pw:hover, .toggle-pw:focus { color: var(--candidate); }

        /* --- CAPTCHA --- */
        .captcha-wrap {
            display: flex; align-items: center; justify-content: space-between;
            padding: 8px 12px; background: var(--input-bg); border: 1px dashed var(--border);
            border-radius: 8px; transition: all 0.3s;
        }
        .captcha-wrap:focus-within { border-color: var(--candidate); background: var(--surface); }
        .captcha-text { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-dark); }
        .captcha-wrap input {
            width: 45px; padding: 6px; text-align: center; border: 1px solid var(--input-border);
            border-radius: 6px; font-size: 12px; font-weight: 700; font-family: inherit; outline: none;
        }
        .captcha-wrap input:focus { border-color: var(--candidate); box-shadow: 0 0 0 3px var(--candidate-bg); }

        /* --- SUBMIT BUTTON --- */
        .btn-submit {
            width: 100%; padding: 12px; background: var(--candidate); color: white;
            border: none; border-radius: 8px; font-family: inherit; font-size: 13px; font-weight: 700;
            cursor: pointer; margin-top: 5px; transition: all 0.3s; box-shadow: 0 6px 15px rgba(5, 150, 105, 0.25);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover { background: #065F46; transform: translateY(-2px); }

        /* --- REGISTRATION BANNER --- */
        .register-banner {
            margin: 0 25px 15px; padding: 10px 14px; background: var(--candidate-bg);
            border: 1px dashed var(--candidate-light); border-radius: 10px;
            display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-shrink: 0;
        }
        .register-text { font-size: 11px; color: #064E3B; font-weight: 500; line-height: 1.3; }
        .register-text strong { display: block; font-size: 12px; font-weight: 700; }
        .btn-register {
            flex-shrink: 0; padding: 6px 12px; background: white; border: 1px solid var(--candidate-light);
            border-radius: 6px; color: var(--candidate); text-decoration: none; font-size: 11px;
            font-weight: 700; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.1); transition: all 0.3s;
        }
        .btn-register:hover { background: var(--candidate); color: white; border-color: var(--candidate); }

        /* --- FOOTER --- */
        .card-footer {
            padding: 12px 25px; background: rgba(248, 250, 252, 0.8); border-top: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
        }
        .secure-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 10px; color: var(--text-muted); font-weight: 600; }
        .secure-dot { width: 6px; height: 6px; border-radius: 50%; background: #10B981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.6); animation: blink 2s ease-in-out infinite; }

        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

        /* --- NIELIT TAG (Absolutely Positional) --- */
        .nielit-tag {
            position: absolute; bottom: 15px; left: 50%; transform: translateX(-50%);
            z-index: 2; font-size: 11px; color: var(--text-muted); font-weight: 600;
        }

        @media (max-width: 480px) {
            .top-nav { top: 15px; left: 15px; }
            .login-card { max-width: 100%; border-radius: 16px; }
            .card-head, .card-body, .register-banner, .card-footer { padding-left: 20px; padding-right: 20px; }
            .register-banner { margin-left: 20px; margin-right: 20px; flex-direction: column; text-align: center; }
            .nielit-tag { display: none; }
        }
    </style>
</head>
<body>

    <div class="ambient-bg">
        <div class="shape cube"></div>
        <div class="shape ring"></div>
        <div class="shape pyramid"></div>
        <div class="shape sphere"></div>
    </div>

    <nav class="top-nav">
        <a href="../index.php" class="btn-back-top">
            <i class="fas fa-arrow-left"></i> Return to Portals
        </a>
    </nav>

    <div class="login-card">
        <div class="card-accent"></div>

        <div class="card-head">
            <div class="icon-wrap"><i class="fas fa-user-graduate"></i></div>
            <div class="card-title">Candidate Portal</div>
            <div class="card-subtitle">NIELIT Centralized CBT System</div>
        </div>

        <div class="card-body">
            <div class="sep"></div>

            <?php if ($error): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle error-icon"></i>
                <span class="error-text"><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($google_oauth_url !== ''): ?>
            <a href="<?php echo $google_oauth_url; ?>" class="btn-google">
                <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google">
                Sign in with Google
            </a>

            <div class="divider">Or manual login</div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label>Student ID</label>
                    <div class="input-wrap">
                        <input type="text" name="username"
                               placeholder="e.g. NIELIT/2025/DBC13/0079"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               required autofocus>
                        <i class="fas fa-id-badge input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <div class="label-flex">
                        <label>Password</label>
                        <a href="candidate-forgot-password.php" class="forgot-link">Forgot Password?</a>
                    </div>
                    <div class="input-wrap">
                        <input type="password" name="password" id="passwordField"
                               placeholder="Enter your password"
                               required>
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="toggle-pw" onclick="togglePassword()" id="toggleBtn" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Security Verification</label>
                    <div class="captcha-wrap">
                        <div class="captcha-text">
                            <i class="fas fa-robot" style="color: var(--text-muted); font-size: 14px;"></i>
                            <span>Solve: <strong><?php echo $num1; ?> + <?php echo $num2; ?> = ?</strong></span>
                        </div>
                        <input type="number" name="captcha_answer" placeholder="?" required>
                    </div>
                </div>

                <button type="submit" name="manual_login" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> Login to Dashboard
                </button>
            </form>
        </div>

        <div class="register-banner">
            <div class="register-text">
                <strong>First Time User?</strong>
                Create a profile to enroll.
            </div>
            <a href="register.php" class="btn-register">Register Now</a>
        </div>

        <div class="card-footer">
            <div class="secure-badge">
                <i class="fas fa-shield-alt" style="color: var(--text-muted);"></i> SSL Encrypted
            </div>
            <div class="secure-badge">
                <span class="secure-dot"></span> System Online
            </div>
        </div>
    </div>

    <div class="nielit-tag">
        National Institute of Electronics &amp; Information Technology, Bhubaneswar &nbsp;·&nbsp; &copy; <?php echo date('Y'); ?>
    </div>

    <script>
        function togglePassword() {
            const field = document.getElementById('passwordField');
            const btnIcon = document.querySelector('#toggleBtn i');
            
            if (field.type === 'password') {
                field.type = 'text';
                btnIcon.classList.remove('fa-eye');
                btnIcon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                btnIcon.classList.remove('fa-eye-slash');
                btnIcon.classList.add('fa-eye');
            }
        }
    </script>

</body>
</html>