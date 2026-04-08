<?php
// Enforce Strict Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

session_name('NIELIT_CANDIDATE_SESSION');
session_start();

// Check if user is logged in and is candidate
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    header("Location: candidate-login.php");
    exit();
}

// Check if exam ID is provided
if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    header("Location: available-exams.php");
    exit();
}

$exam_id = $_GET['exam_id'];

// ============================================================================
// Import centralized database connection
// ============================================================================
require_once __DIR__ . '/../../config/database.php';

$error = '';
$success = '';
$is_late = false; // Flag for the 30-minute late rule

try {
    // Get exam details (Using LEFT JOIN to safely handle Practice Exams with NULL centers)
    $stmt = $pdo->prepare("
        SELECT 
            es.*,
            ec.category_name,
            ec.category_code,
            ec.duration_minutes,
            c.center_name,
            c.center_code,
            c.city,
            c.address,
            (SELECT COUNT(*) FROM exam_registrations WHERE session_id = es.id) as registered_count
        FROM exam_sessions es
        JOIN exam_categories ec ON es.category_id = ec.id
        LEFT JOIN exam_centers c ON es.center_id = c.id
        WHERE es.id = ? AND es.is_active = true
    ");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        header("Location: available-exams.php");
        exit();
    }
    
    // 🟢 FOREIGN KEY FIX: Check if the candidate profile exists (Using user_id instead of id)
    $candStmt = $pdo->prepare("SELECT user_id FROM candidates WHERE user_id = ?");
    $candStmt->execute([$_SESSION['user_id']]);
    $profile_exists = $candStmt->fetchColumn();

    // Check if already registered
    if ($profile_exists) {
        $check = $pdo->prepare("SELECT id FROM exam_registrations WHERE candidate_id = ? AND session_id = ?");
        $check->execute([$_SESSION['user_id'], $exam_id]);
        
        if ($check->fetch()) {
            header("Location: candidate-dashboard.php?msg=AlreadyEnrolled");
            exit();
        }
    }
    
    // --- STRICT 30 MINUTE LATE POLICY CHECK ---
    if (!$exam['is_practice']) {
        $date_clean = explode(' ', $exam['exam_date'])[0];
        $start_clean = explode('+', $exam['start_time'])[0];
        $exam_start = strtotime($date_clean . ' ' . $start_clean);
        
        $cutoff_time = $exam_start + (30 * 60); 
        $now = time();
        
        if ($now > $cutoff_time) {
            $is_late = true;
            $error = "Registration blocked. The strict 30-minute late entry window has already passed.";
        }
    }
    
    // Check seat availability
    $seats_left = $exam['total_seats'] - $exam['registered_count'];
    if (!$exam['is_practice'] && !$is_late && $seats_left <= 0) {
        $error = "Registration closed. No seats available for this exam session.";
    }
    
    // Handle registration confirmation
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm'])) {
        if ($is_late) {
            $error = "Cannot complete registration. The entry window is permanently closed.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // CONCURRENCY CHECK: Re-verify session seats inside the transaction
                $verifyStmt = $pdo->prepare("
                    SELECT total_seats, is_practice, (SELECT COUNT(*) FROM exam_registrations WHERE session_id = es.id) as reg_count 
                    FROM exam_sessions es WHERE es.id = ? FOR UPDATE
                ");
                $verifyStmt->execute([$exam_id]);
                $verifyData = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$verifyData['is_practice'] && ($verifyData['total_seats'] - $verifyData['reg_count']) <= 0) {
                    throw new Exception("Sorry, the last seat was just taken by another candidate.");
                }

                // 🟢 THE FIX: If profile is missing, quietly create it so the Foreign Key is happy!
                if (!$profile_exists) {
                    $createProfile = $pdo->prepare("INSERT INTO candidates (user_id, registration_number) VALUES (?, ?)");
                    $createProfile->execute([$_SESSION['user_id'], $_SESSION['username']]);
                }

                // Set initial status based on exam type
                $initial_status = $exam['is_practice'] ? 'pending_payment' : 'registered';

                // Insert registration securely using the user_id as the candidate_id
                $insertStmt = $pdo->prepare("
                    INSERT INTO exam_registrations (candidate_id, session_id, registration_status, attendance_marked)
                    VALUES (?, ?, ?, false) RETURNING id
                ");
                $insertStmt->execute([$_SESSION['user_id'], $exam_id, $initial_status]);
                $registration_id = $insertStmt->fetchColumn();
                
                $pdo->commit();
                
                // Route the user to the correct next step
                if ($exam['is_practice']) {
                    header("Location: practice-payment.php?reg_id=" . $registration_id);
                } else {
                    header("Location: candidate-dashboard.php?msg=EnrollmentSuccess");
                }
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
                $seats_left = 0; 
            }
        }
    }
    
} catch (PDOException $e) {
    // Show the actual error during development so we don't fly blind
    $error = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Registration - NIELIT Exam Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Emerald Candidate Theme */
            --primary: #059669; 
            --primary-hover: #047857; 
            --primary-bg: #D1FAE5; 
            --secondary: #0F172A; 
            --accent: #10B981; 
            --text-main: #1E293B; 
            --text-muted: #64748B; 
            --bg-page: transparent; 
            --glass-surface: rgba(255, 255, 255, 0.85);
            --border: rgba(226, 232, 240, 0.8); 
            --radius-lg: 24px; 
            --radius-md: 16px; 
            --danger: #DC2626; 
            --danger-bg: #FEE2E2; 
            --practice: #2563EB; /* Changed to Blue for Payment Context */
            --practice-bg: #DBEAFE; 
            --shadow-sm: 0 4px 6px -1px rgba(5, 150, 105, 0.05);
            --shadow-glass: 0 20px 40px -10px rgba(5, 150, 105, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F8FAFC;
            color: var(--text-dark);
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        /* --- 3D MOVING BACKGROUND --- */
        .ambient-bg {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1; overflow: hidden; pointer-events: none;
            background: radial-gradient(circle at 50% 0%, #DCE6F1 0%, #E8F0F8 50%, #F4F7FB 100%);
            perspective: 1000px;
        }
        .shape {
            position: absolute; background: linear-gradient(135deg, rgba(255, 255, 255, 0.6), rgba(5, 150, 105, 0.05));
            backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 15px 35px rgba(5, 150, 105, 0.08), inset 0 0 20px rgba(255, 255, 255, 0.8);
            animation: float-3d 20s infinite linear;
        }
        .cube { width: 140px; height: 140px; border-radius: 28px; top: 15%; left: 5%; animation-duration: 28s; }
        .ring { width: 220px; height: 220px; border-radius: 50%; border: 35px solid rgba(255,255,255,0.3); top: 55%; right: 2%; animation-duration: 35s; animation-direction: reverse; background: transparent; }
        .pyramid { width: 90px; height: 90px; border-radius: 16px; bottom: 15%; left: 20%; animation-duration: 22s; }
        .sphere { width: 180px; height: 180px; border-radius: 50%; top: 8%; right: 15%; animation-duration: 40s; }

        @keyframes float-3d {
            0% { transform: translateY(0) rotateX(0deg) rotateY(0deg) rotateZ(0deg); }
            50% { transform: translateY(-50px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); }
            100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); }
        }

        /* --- TOP NAV --- */
        .top-nav { background: rgba(255,255,255,0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 1000; box-shadow: var(--shadow-sm);}
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.9); border: 1px solid var(--border);
            padding: 8px 16px; border-radius: 10px; color: var(--text-dark);
            text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s;
        }
        .btn-back:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); transform: translateX(-3px); }

        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--primary); line-height: 1.2; }
        .brand-text span { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .user-info { font-size: 14px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }

        /* --- MAIN CONTAINER --- */
        .container { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 20px; position: relative; z-index: 10; }

        .card {
            background: var(--glass-surface); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
            padding: 40px; border-radius: var(--radius-lg); border: 1px solid rgba(255,255,255,1);
            box-shadow: var(--shadow-glass); width: 100%; max-width: 650px;
        }

        .card-header { text-align: center; margin-bottom: 30px; }
        .card-header h1 { font-size: 24px; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .card-header p { color: var(--text-muted); font-size: 14px; margin-top: 5px; font-weight: 500;}

        /* Alert Boxes */
        .alert { padding: 15px 20px; border-radius: var(--radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; margin-bottom: 25px; line-height: 1.5; word-wrap: break-word;}
        .alert-error { background: var(--danger-bg); color: var(--danger); border: 1px solid #FECACA; }
        
        .seat-badge {
            text-align: center; padding: 12px; border-radius: var(--radius-md); margin-bottom: 25px;
            font-size: 15px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid transparent;
        }
        .seat-high { background: #D1FAE5; color: var(--success); border-color: #A7F3D0; }
        .seat-low { background: #FEF3C7; color: #D97706; border-color: #FDE68A; }
        .seat-none { background: var(--danger-bg); color: var(--danger); border-color: #FECACA; }
        .seat-practice { background: var(--practice-bg); color: var(--practice); border-color: #BFDBFE; }

        /* Grid Data */
        .details-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 15px;
            background: rgba(255,255,255,0.6); padding: 25px; border-radius: var(--radius-md);
            border: 1px solid var(--border); margin-bottom: 25px;
        }
        .detail-item { display: flex; flex-direction: column; gap: 5px; }
        .detail-item.full-width { grid-column: 1 / -1; }
        .detail-label { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-value { font-size: 15px; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 8px;}
        .detail-value i { color: var(--primary); font-size: 14px; }
        .val-practice i { color: var(--practice); }

        /* Info Box */
        .info-box {
            background: var(--primary-bg); border: 1px solid var(--primary);
            padding: 20px; border-radius: var(--radius-md); margin-bottom: 30px;
        }
        .info-box.practice-info { background: var(--practice-bg); border-color: #93C5FD; }
        .info-box h3 { color: var(--primary); font-size: 14px; font-weight: 800; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .info-box.practice-info h3 { color: var(--practice); }
        .info-box ul { margin-left: 25px; color: #064E3B; font-size: 13px; font-weight: 500; line-height: 1.6; }
        .info-box.practice-info ul { color: #1E3A8A; }
        .info-box li { margin-bottom: 5px; }

        /* Action Buttons */
        .form-actions { display: flex; gap: 15px; }
        .btn {
            flex: 1; padding: 14px 20px; border-radius: 12px; font-weight: 700; font-size: 15px;
            cursor: pointer; transition: all 0.3s; display: inline-flex; justify-content: center; align-items: center; gap: 8px;
            border: none; text-decoration: none; font-family: inherit;
        }
        .btn-cancel { background: white; color: var(--text-dark); border: 1px solid var(--border); }
        .btn-cancel:hover { background: var(--primary-bg); border-color: var(--primary); color: var(--primary);}
        .btn-submit { background: var(--success); color: white; box-shadow: 0 4px 15px rgba(5, 150, 105, 0.2); }
        .btn-submit:hover:not(:disabled) { background: #047857; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3); }
        .btn-submit:disabled { background: #94A3B8; cursor: not-allowed; box-shadow: none; opacity: 0.7; }
        
        .btn-practice-start { background: var(--practice); color: white; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2); }
        .btn-practice-start:hover { background: #1D4ED8; transform: translateY(-2px); }

        @media (max-width: 768px) {
            .details-grid { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column-reverse; }
            .top-nav { padding: 15px 20px; }
            .card { padding: 25px; }
        }
    </style>
</head>
<body>

    <div class="ambient-bg">
        <div class="shape cube"></div>
        <div class="shape ring"></div>
        <div class="shape pyramid"></div>
    </div>

    <nav class="top-nav">
        <div class="nav-left">
            <a href="candidate-dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div class="brand-text">
                <h2>Exam Registration</h2>
                <span class="hide-mobile">NIELIT Candidate Portal</span>
            </div>
        </div>
        <div class="nav-right">
            <div class="user-info">
                <span class="hide-mobile"><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Candidate')[0]); ?></span>
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'C', 0, 1)); ?></div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            
            <div class="card-header">
                <h1><i class="fas fa-clipboard-check" style="color: <?php echo $exam['is_practice'] ? 'var(--practice)' : 'var(--primary)'; ?>;"></i> Confirm Enrollment</h1>
                <p>Please review the session details carefully before confirming.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle" style="font-size: 20px; flex-shrink: 0;"></i> 
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($exam)): ?>
                
                <?php 
                    // Seat Display Logic
                    if ($exam['is_practice']) {
                        $seat_class = 'seat-practice';
                        $icon = 'fa-infinity';
                    } else {
                        if ($is_late) {
                            $seat_class = 'seat-none';
                            $icon = 'fa-ban';
                        } else {
                            $seat_class = 'seat-high';
                            $icon = 'fa-chair';
                            if ($seats_left <= 0) {
                                $seat_class = 'seat-none';
                                $icon = 'fa-times-circle';
                            } elseif ($seats_left <= 10) {
                                $seat_class = 'seat-low';
                                $icon = 'fa-exclamation-circle';
                            }
                        }
                    }

                    // --- DYNAMIC DURATION CALCULATION ---
                    if ($exam['is_practice']) {
                        $actual_duration = $exam['duration_minutes'];
                    } else {
                        $start_ts = strtotime($exam['start_time']);
                        $end_ts = strtotime($exam['end_time']);
                        if ($end_ts < $start_ts) { $end_ts += 86400; } 
                        $actual_duration = round(abs($end_ts - $start_ts) / 60);
                    }
                ?>

                <div class="seat-badge <?php echo $seat_class; ?>">
                    <i class="fas <?php echo $icon; ?>"></i>
                    <?php if ($exam['is_practice']): ?>
                        Premium Practice Module - ₹50.00
                    <?php elseif ($is_late): ?>
                        Registration Closed (Late Entry)
                    <?php elseif ($seats_left > 0): ?>
                        Only <?php echo $seats_left; ?> seat(s) remaining for this session!
                    <?php else: ?>
                        Session Fully Booked
                    <?php endif; ?>
                </div>

                <div class="details-grid">
                    <div class="detail-item full-width">
                        <span class="detail-label">Examination Category</span>
                        <span class="detail-value val-<?php echo $exam['is_practice'] ? 'practice' : 'primary'; ?>"><i class="fas fa-book-open"></i> <?php echo htmlspecialchars($exam['category_code'] . ' - ' . $exam['category_name']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Assigned Session Code</span>
                        <span class="detail-value val-<?php echo $exam['is_practice'] ? 'practice' : 'primary'; ?>"><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($exam['exam_code']); ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Total Duration</span>
                        <span class="detail-value val-<?php echo $exam['is_practice'] ? 'practice' : 'primary'; ?>"><i class="fas fa-stopwatch"></i> <?php echo $actual_duration; ?> Minutes</span>
                    </div>

                    <div class="detail-item full-width" style="border-top: 1px solid var(--border); padding-top: 15px; margin-top: 5px;">
                        <span class="detail-label">Exam Conductor</span>
                        <span class="detail-value val-<?php echo $exam['is_practice'] ? 'practice' : 'primary'; ?>">
                            <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($exam['exam_conductor'] ?? 'Not Assigned'); ?>
                        </span>
                    </div>

                    <?php if ($exam['is_practice']): ?>
                        <div class="detail-item full-width" style="border-top: 1px solid var(--border); padding-top: 15px; margin-top: 5px;">
                            <span class="detail-label">Location & Schedule</span>
                            <span class="detail-value val-practice" style="align-items: flex-start;">
                                <i class="fas fa-globe" style="margin-top: 3px;"></i> 
                                <span>Online Portal<br><span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">Available 24/7. Verified via Finance.</span></span>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="detail-item" style="border-top: 1px solid var(--border); padding-top: 15px; margin-top: 5px;">
                            <span class="detail-label">Scheduled Date</span>
                            <span class="detail-value"><i class="fas fa-calendar-alt"></i> <?php echo date('l, d M Y', strtotime($exam['exam_date'])); ?></span>
                        </div>

                        <div class="detail-item" style="border-top: 1px solid var(--border); padding-top: 15px; margin-top: 5px;">
                            <span class="detail-label">Session Timing</span>
                            <span class="detail-value"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($exam['start_time'])); ?> - <?php echo date('h:i A', strtotime($exam['end_time'])); ?></span>
                        </div>

                        <div class="detail-item full-width" style="border-top: 1px solid var(--border); padding-top: 15px; margin-top: 5px;">
                            <span class="detail-label">Test Center Location</span>
                            <span class="detail-value" style="align-items: flex-start;">
                                <i class="fas fa-map-marker-alt" style="margin-top: 3px;"></i> 
                                <span>
                                    <?php echo htmlspecialchars($exam['center_name']); ?><br>
                                    <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;"><?php echo htmlspecialchars($exam['address'] . ', ' . $exam['city']); ?></span>
                                </span>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($exam['is_practice']): ?>
                    <div class="info-box practice-info">
                        <h3><i class="fas fa-info-circle"></i> Payment Guidelines</h3>
                        <ul>
                            <li>Practice exams require a one-time fee of ₹50.00 to unlock.</li>
                            <li>You will be redirected to the secure payment gateway to scan a UPI QR code.</li>
                            <li>Once payment is verified by the Finance team, the exam will be permanently unlocked.</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="info-box">
                        <h3><i class="fas fa-info-circle"></i> Examination Guidelines</h3>
                        <ul>
                            <li>Candidates must report to the center <strong>30 minutes</strong> prior to the start time.</li>
                            <li>A valid Government issued ID proof is mandatory for entry.</li>
                            <li>Mobile phones and electronic gadgets are strictly prohibited inside the lab.</li>
                            <li>Your official Admit Card will be generated immediately after successful registration.</li>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-actions">
                        <a href="candidate-dashboard.php" class="btn btn-cancel">Cancel</a>
                        
                        <?php if ($exam['is_practice']): ?>
                            <button type="submit" name="confirm" class="btn btn-practice-start">
                                <i class="fas fa-arrow-right"></i> Proceed to Payment
                            </button>
                        <?php else: ?>
                            <button type="submit" name="confirm" class="btn btn-submit" <?php echo ($seats_left <= 0 || $is_late) ? 'disabled' : ''; ?>>
                                <i class="fas fa-check-circle"></i> Complete Registration
                            </button>
                        <?php endif; ?>
                        
                    </div>
                </form>

            <?php endif; ?>
        </div>
    </div>

</body>
</html>