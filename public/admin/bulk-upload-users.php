<?php
session_name('NIELIT_ADMIN_SESSION');
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: admin-login.php");
    exit();
}

// 🟢 NEW ARCHITECTURE: Centralized database connection
require_once __DIR__ . '/../../config/database.php';

// Handle Template Download
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=NIELIT_User_Bulk_Template.csv');
    $output = fopen('php://output', 'w');
    // Header row
    fputcsv($output, ['FullName', 'Email', 'Username', 'Password', 'Role(admin/candidate)', 'Mobile(Required for Candidate)', 'DOB(YYYY-MM-DD)(Required for Candidate)']);
    // Example Candidate
    fputcsv($output, ['John Doe', 'john.doe@example.com', 'johndoe99', 'Pass@123', 'candidate', '9876543210', '2000-05-15']);
    // Example Admin
    fputcsv($output, ['Admin Sarah', 'sarah@nielit.gov.in', 'sarah_admin', 'Secure#2026', 'admin', '', '']);
    fclose($output);
    exit();
}

$error = '';
$success = '';

try {
    // $pdo is now securely imported from database.php

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "Error uploading file. System Code: " . $file['error'];
        } elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
            $error = "Invalid file format. Please upload a standard .CSV file.";
        } else {
            $handle = fopen($file['tmp_name'], "r");
            if ($handle !== FALSE) {
                // Skip header row
                fgetcsv($handle);
                
                $pdo->beginTransaction();
                $rowNum = 1; 
                $successCount = 0;
                
                $checkUserStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $insertUserStmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, full_name, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, true, NOW()) RETURNING id");
                $insertCandidateStmt = $pdo->prepare("INSERT INTO candidates (user_id, registration_number, date_of_birth, mobile) VALUES (?, ?, ?, ?)");

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $rowNum++;
                    
                    // Skip entirely empty rows
                    if (empty(array_filter($data))) continue;
                    
                    // Pad array to prevent undefined index warnings
                    $data = array_pad($data, 7, '');
                    
                    $fullName = trim($data[0]);
                    $email = trim($data[1]);
                    $username = trim($data[2]);
                    $password = trim($data[3]);
                    $role = strtolower(trim($data[4]));
                    $mobile = trim($data[5]);
                    $dob = trim($data[6]);

                    // Basic Validation
                    if (empty($fullName) || empty($email) || empty($username) || empty($password) || empty($role)) {
                        throw new Exception("Row $rowNum: Missing required core fields (Name, Email, Username, Password, Role).");
                    }
                    if (!in_array($role, ['admin', 'candidate'])) {
                        throw new Exception("Row $rowNum: Invalid Role '$role'. Must be 'admin' or 'candidate'.");
                    }
                    if (strlen($password) < 6) {
                        throw new Exception("Row $rowNum: Password must be at least 6 characters.");
                    }

                    // Check for duplicates
                    $checkUserStmt->execute([$username, $email]);
                    if ($checkUserStmt->fetch()) {
                        throw new Exception("Row $rowNum: Username '$username' or Email '$email' is already registered in the system.");
                    }

                    // Insert User
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $insertUserStmt->execute([$username, $passwordHash, $email, $fullName, $role]);
                    $newUserId = $insertUserStmt->fetchColumn();

                    // If Candidate, handle extra table insertion
                    if ($role === 'candidate') {
                        if (empty($mobile) || empty($dob)) {
                            throw new Exception("Row $rowNum: Candidate profiles require both Mobile Number and DOB.");
                        }
                        
                        // Generate official Registration ID
                        $regNumber = 'NIELIT' . date('Y') . str_pad($newUserId, 5, '0', STR_PAD_LEFT);
                        
                        $insertCandidateStmt->execute([$newUserId, $regNumber, $dob, $mobile]);
                    }
                    
                    $successCount++;
                }
                
                fclose($handle);
                $pdo->commit();
                
                if ($successCount > 0) {
                    $success = "Successfully imported $successCount users into the directory!";
                } else {
                    $error = "No valid data found in the uploaded file.";
                }
                
            } else {
                $error = "Failed to open the uploaded file.";
            }
        }
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage() . " The entire batch has been cancelled to prevent partial data corruption.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Upload Users - NIELIT Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1D4ED8;        
            --primary-light: #3B82F6;  
            --primary-bg: #DBEAFE;     
            --secondary: #0F172A;
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --text-dark: #0F172A;
            --text-muted: #64748B;
            --bg-body: #F4F7FB;
            --surface: #FFFFFF;
            --border: #E2E8F0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 40px -10px rgba(29, 78, 216, 0.1);
            --radius-md: 12px;
            --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-dark);
            min-height: 100vh;
            overflow-x: hidden;
            padding-bottom: 60px;
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
            background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(59,130,246,0.05));
            backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 15px 35px rgba(29,78,216,0.08), inset 0 0 20px rgba(255,255,255,0.5);
            animation: float-3d 20s infinite linear;
        }

        .cube { width: 120px; height: 120px; border-radius: 24px; top: 15%; left: 8%; animation-duration: 25s; }
        .ring { width: 200px; height: 200px; border-radius: 50%; border: 35px solid rgba(255,255,255,0.4); top: 50%; right: 5%; animation-duration: 30s; animation-direction: reverse; background: transparent; }
        .pyramid { width: 80px; height: 80px; border-radius: 16px; bottom: 15%; left: 20%; animation-duration: 18s; }

        @keyframes float-3d {
            0% { transform: translateY(0) rotateX(0deg) rotateY(0deg) rotateZ(0deg); }
            50% { transform: translateY(-40px) rotateX(180deg) rotateY(90deg) rotateZ(45deg); }
            100% { transform: translateY(0) rotateX(360deg) rotateY(180deg) rotateZ(90deg); }
        }

        /* --- TOP NAV --- */
        .top-nav {
            background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px);
            padding: 15px 40px; display: flex; justify-content: space-between; align-items: center;
            box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 100;
            border-bottom: 1px solid rgba(255,255,255,0.5);
        }

        .nav-left { display: flex; align-items: center; gap: 20px; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--bg-body); border: 1px solid var(--border);
            padding: 8px 16px; border-radius: 10px; color: var(--text-dark);
            text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s;
        }
        .btn-back:hover { background: #DBEAFE; color: #1D4ED8; border-color: #3B82F6; transform: translateX(-3px); }

        .brand-text h2 { font-size: 18px; font-weight: 800; color: #1D4ED8; line-height: 1.2; }
        .brand-text span { font-size: 12px; color: var(--text-muted); font-weight: 500; }

        .user-info { font-size: 14px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 32px; height: 32px; background: #1D4ED8; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }

        /* --- MAIN CONTAINER --- */
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; position: relative; z-index: 10; }

        .page-header { margin-bottom: 25px; text-align: center; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); margin-bottom: 5px; }
        .page-header p { color: var(--text-muted); font-size: 14px; }

        .alert { padding: 16px 20px; border-radius: var(--radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 25px; animation: slideIn 0.4s ease; line-height: 1.5;}
        .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid #A7F3D0; }
        .alert-error { background: var(--danger-bg); color: var(--danger); border: 1px solid #FECACA; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- BENTO CARD --- */
        .card {
            background: rgba(255,255,255,0.9); backdrop-filter: blur(16px);
            border-radius: var(--radius-lg); border: 1px solid var(--border);
            box-shadow: var(--shadow-lg); padding: 40px;
        }

        /* --- INFO BANNER --- */
        .info-banner {
            background: var(--bg-body); border: 1px solid var(--border); border-radius: var(--radius-md);
            padding: 20px; margin-bottom: 30px; display: flex; gap: 20px; align-items: flex-start;
        }
        .info-icon { font-size: 24px; color: #1D4ED8; }
        .info-content h3 { font-size: 15px; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
        .info-content p { font-size: 13px; color: var(--text-muted); margin-bottom: 12px; line-height: 1.5; }
        .btn-download {
            display: inline-flex; align-items: center; gap: 8px; background: var(--surface);
            color: #1D4ED8; font-size: 13px; font-weight: 700; text-decoration: none;
            padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border); transition: 0.2s;
        }
        .btn-download:hover { background: #DBEAFE; border-color: #3B82F6; }

        /* --- DRAG & DROP ZONE --- */
        .upload-zone {
            border: 2px dashed var(--border); border-radius: var(--radius-md);
            padding: 50px 20px; text-align: center; background: var(--surface);
            transition: all 0.3s ease; cursor: pointer; position: relative; margin-bottom: 30px;
        }
        .upload-zone:hover, .upload-zone.dragover { border-color: #1D4ED8; background: #DBEAFE; }
        
        .upload-zone i { font-size: 40px; color: #3B82F6; margin-bottom: 15px; transition: 0.3s; }
        .upload-zone:hover i { transform: translateY(-5px); color: #1D4ED8; }
        
        .upload-zone h4 { font-size: 16px; font-weight: 700; color: var(--text-dark); margin-bottom: 5px; }
        .upload-zone p { font-size: 13px; color: var(--text-muted); }
        
        .upload-zone input[type="file"] {
            position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10;
        }

        .file-name-display {
            display: none; background: var(--success-bg); color: var(--success); padding: 10px 15px;
            border-radius: 8px; font-size: 13px; font-weight: 700; align-items: center; justify-content: center; gap: 8px;
            margin-top: 15px; border: 1px solid #A7F3D0;
        }

        /* Buttons */
        .action-row { display: flex; justify-content: flex-end; gap: 15px; border-top: 1px solid var(--border); padding-top: 25px; }
        .btn { padding: 14px 28px; border-radius: 12px; font-weight: 700; font-size: 14px; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; border: none; font-family: inherit; }
        .btn-cancel { background: white; color: var(--text-dark); border: 1px solid var(--border); text-decoration: none; }
        .btn-cancel:hover { background: var(--bg-body); border-color: #94A3B8; }
        .btn-submit { background: #1D4ED8; color: white; box-shadow: 0 4px 15px rgba(29, 78, 216, 0.2); pointer-events: none; opacity: 0.5; }
        .btn-submit.active { pointer-events: auto; opacity: 1; }
        .btn-submit.active:hover { background: #1e3a8a; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(29, 78, 216, 0.3); }

        @media (max-width: 768px) {
            .action-row { flex-direction: column-reverse; }
            .btn { width: 100%; justify-content: center; }
            .card { padding: 25px; }
            .top-nav { padding: 15px 20px; }
            .info-banner { flex-direction: column; }
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
            <a href="manage-users.php" class="btn-back"><i class="fas fa-arrow-left"></i> Directory</a>
            <div class="brand-text">
                <h2>Bulk Import</h2>
                <span class="hide-mobile">User Provisioning</span>
            </div>
        </div>
        <div class="nav-right">
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?></div>
                <span class="hide-mobile"><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?></span>
            </div>
        </div>
    </nav>

    <div class="container">
        
        <div class="page-header">
            <h1><i class="fas fa-users-cog" style="color: #1D4ED8;"></i> Bulk Upload Users</h1>
            <p>Upload large batches of candidates and administrators via CSV.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 20px; flex-shrink: 0;"></i> 
                    <span><?php echo $error; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle" style="font-size: 20px;"></i> 
                    <span><?php echo $success; ?></span>
                </div>
                <a href="manage-users.php" style="color:var(--success); text-decoration:underline;">View Directory</a>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="card">
            
            <div class="info-banner">
                <div class="info-icon"><i class="fas fa-info-circle"></i></div>
                <div class="info-content">
                    <h3>Important Instructions</h3>
                    <p>To ensure successful processing, your CSV file must strictly follow our predefined format. Do not alter the header row. Candidate profiles <strong>must</strong> include Mobile and DOB. Leave those blank for Admins.</p>
                    <a href="?download_template=true" class="btn-download">
                        <i class="fas fa-download"></i> Download CSV Template
                    </a>
                </div>
            </div>

            <div class="upload-zone" id="dropZone">
                <i class="fas fa-cloud-upload-alt"></i>
                <h4>Drag & Drop your CSV file here</h4>
                <p>or click to browse from your computer (Max size: 5MB)</p>
                <input type="file" name="csv_file" id="fileInput" accept=".csv" required>
                
                <div class="file-name-display" id="fileDisplay">
                    <i class="fas fa-file-csv"></i> <span id="fileName">filename.csv</span>
                </div>
            </div>

            <div class="action-row">
                <a href="manage-users.php" class="btn btn-cancel">Cancel</a>
                <button type="submit" class="btn btn-submit" id="submitBtn">
                    <i class="fas fa-upload"></i> Process & Import File
                </button>
            </div>

        </form>
    </div>

    <script>
        const fileInput = document.getElementById('fileInput');
        const dropZone = document.getElementById('dropZone');
        const fileDisplay = document.getElementById('fileDisplay');
        const fileNameSpan = document.getElementById('fileName');
        const submitBtn = document.getElementById('submitBtn');

        // Handle File Selection
        fileInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                const file = this.files[0];
                if(file.name.endsWith('.csv')) {
                    fileNameSpan.textContent = file.name;
                    fileDisplay.style.display = 'flex';
                    submitBtn.classList.add('active');
                    dropZone.style.borderColor = 'var(--success)';
                } else {
                    alert("Please upload a valid .csv file.");
                    this.value = '';
                    fileDisplay.style.display = 'none';
                    submitBtn.classList.remove('active');
                }
            }
        });

        // Drag and Drop visual feedback
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
            dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
    </script>
</body>
</html>