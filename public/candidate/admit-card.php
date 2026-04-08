<?php
session_name('NIELIT_SESSION');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'candidate') {
    die("Unauthorized Access.");
}

require_once __DIR__ . '/../../config/database.php';

$reg_id = $_GET['reg_id'] ?? 0;
$candidate_id = $_SESSION['user_id'];

try {
    // Fetch all details needed for the Admit Card
    $stmt = $pdo->prepare("
        SELECT 
            er.registration_status,
            c.registration_number, c.date_of_birth,
            u.full_name, u.email,
            es.exam_code, es.exam_date, es.start_time, es.end_time,
            cat.category_name, cat.category_code,
            cen.center_name, cen.address, cen.city, cen.state
        FROM exam_registrations er
        JOIN candidates c ON er.candidate_id = c.user_id
        JOIN users u ON c.user_id = u.id
        JOIN exam_sessions es ON er.session_id = es.id
        JOIN exam_categories cat ON es.category_id = cat.id
        JOIN exam_centers cen ON es.center_id = cen.id
        WHERE er.id = ? AND er.candidate_id = ?
    ");
    $stmt->execute([$reg_id, $candidate_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("Invalid Admit Card Request.");
    }

} catch (PDOException $e) {
    die("Database Error.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admit Card - <?php echo htmlspecialchars($data['registration_number']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background: #e2e8f0; margin: 0; padding: 40px 0; color: #000; }
        
        .admit-card-container {
            width: 800px; margin: 0 auto; background: #fff; padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-top: 8px solid #1E3A8A;
            position: relative;
        }

        /* Watermark */
        .admit-card-container::before {
            content: 'NIELIT'; position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 150px; font-weight: 900; color: rgba(0,0,0,0.03);
            z-index: 0; pointer-events: none;
        }

        .header { display: flex; align-items: center; border-bottom: 2px solid #1E3A8A; padding-bottom: 20px; margin-bottom: 30px; position: relative; z-index: 1;}
        .logo { width: 80px; height: 80px; background: #1E3A8A; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 30px; font-weight: 900; margin-right: 20px;}
        .header-text h1 { margin: 0 0 5px 0; font-size: 24px; font-weight: 900; color: #1E3A8A; text-transform: uppercase;}
        .header-text p { margin: 0; font-size: 13px; font-weight: 500; color: #333; }
        
        .title { text-align: center; font-size: 18px; font-weight: 800; text-decoration: underline; margin-bottom: 30px; text-transform: uppercase; letter-spacing: 1px;}

        .main-content { display: flex; gap: 30px; position: relative; z-index: 1;}
        
        .details-table { flex: 1; border-collapse: collapse; width: 100%; font-size: 14px; }
        .details-table td { padding: 10px; border: 1px solid #ccc; }
        .details-table td:nth-child(odd) { font-weight: 700; background: #f8fafc; width: 35%; color: #333;}
        
        .photo-box {
            width: 140px; height: 160px; border: 2px solid #ccc; 
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-size: 12px; color: #999; text-align: center; background: #f8fafc;
        }

        .section-title { background: #1E3A8A; color: white; padding: 8px 12px; font-size: 14px; font-weight: 700; margin: 30px 0 15px 0; text-transform: uppercase; position: relative; z-index: 1;}

        .instructions { font-size: 12px; line-height: 1.6; color: #333; position: relative; z-index: 1;}
        .instructions ol { margin: 0; padding-left: 20px; }
        .instructions li { margin-bottom: 8px; }

        .signatures { display: flex; justify-content: space-between; margin-top: 50px; position: relative; z-index: 1;}
        .sig-line { text-align: center; font-size: 12px; font-weight: 700; border-top: 1px solid #000; padding-top: 5px; width: 200px; }

        .print-btn {
            display: block; width: 200px; margin: 30px auto; padding: 12px;
            background: #1E3A8A; color: white; text-align: center; text-decoration: none;
            font-weight: 700; border-radius: 8px; cursor: pointer; border: none; font-size: 15px;
        }
        .print-btn:hover { background: #172554; }

        @media print {
            body { background: white; padding: 0; }
            .admit-card-container { box-shadow: none; width: 100%; padding: 20px; border-top: none; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>

    <button class="print-btn" onclick="window.print()">Print Admit Card</button>

    <div class="admit-card-container">
        
        <div class="header">
            <div class="logo">N</div>
            <div class="header-text">
                <h1>National Institute of Electronics & IT</h1>
                <p>Ministry of Electronics & Information Technology, Government of India</p>
                <p>Bhubaneswar Regional Center</p>
            </div>
        </div>

        <div class="title">e-Admit Card for Computer Based Test (CBT)</div>

        <div class="main-content">
            <table class="details-table">
                <tr>
                    <td>Candidate Name</td>
                    <td style="text-transform: uppercase; font-weight: 700; font-size: 16px;"><?php echo htmlspecialchars($data['full_name']); ?></td>
                </tr>
                <tr>
                    <td>Registration Number</td>
                    <td><?php echo htmlspecialchars($data['registration_number']); ?></td>
                </tr>
                <tr>
                    <td>Date of Birth</td>
                    <td><?php echo date('d-m-Y', strtotime($data['date_of_birth'])); ?></td>
                </tr>
                <tr>
                    <td>Exam Module</td>
                    <td><strong><?php echo htmlspecialchars($data['category_code']); ?></strong> (<?php echo htmlspecialchars($data['category_name']); ?>)</td>
                </tr>
            </table>

            <div class="photo-box">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: #e2e8f0; margin-bottom: 10px;"></div>
                Paste Recent<br>Passport Photo
            </div>
        </div>

        <div class="section-title">Examination Details & Venue</div>
        <table class="details-table">
            <tr>
                <td>Exam Date</td>
                <td style="font-weight: 700; font-size: 15px;"><?php echo date('l, d F Y', strtotime($data['exam_date'])); ?></td>
            </tr>
            <tr>
                <td>Reporting Time</td>
                <td><?php echo date('h:i A', strtotime($data['start_time'] . ' -30 minutes')); ?></td>
            </tr>
            <tr>
                <td>Exam Timing</td>
                <td><?php echo date('h:i A', strtotime($data['start_time'])); ?> to <?php echo date('h:i A', strtotime($data['end_time'])); ?></td>
            </tr>
            <tr>
                <td>Exam Center Code</td>
                <td><?php echo htmlspecialchars($data['exam_code']); ?></td>
            </tr>
            <tr>
                <td>Test Venue Address</td>
                <td>
                    <strong><?php echo htmlspecialchars($data['center_name']); ?></strong><br>
                    <?php echo htmlspecialchars($data['address']); ?><br>
                    <?php echo htmlspecialchars($data['city']) . ', ' . htmlspecialchars($data['state']); ?>
                </td>
            </tr>
        </table>

        <div class="section-title">Important Instructions for Candidates</div>
        <div class="instructions">
            <ol>
                <li>Candidates must carry a printed copy of this e-Admit Card to the examination center.</li>
                <li>An original, valid Photo ID (Aadhar Card, PAN Card, Voter ID, or Passport) is strictly mandatory for verification.</li>
                <li>Candidates should report to the examination center at least 30 minutes before the commencement of the exam. Gates will close 15 minutes before the start time.</li>
                <li>Electronic devices, smartwatches, calculators, and mobile phones are strictly prohibited inside the examination hall.</li>
                <li>Any candidate found using unfair means will be immediately disqualified from the examination process.</li>
            </ol>
        </div>

        <div class="signatures">
            <div class="sig-line">Signature of the Candidate<br><span style="font-size: 10px; font-weight: 400;">(To be signed in front of Invigilator)</span></div>
            <div class="sig-line">Controller of Examinations<br><span style="font-size: 10px; font-weight: 400;">NIELIT</span></div>
        </div>

    </div>

</body>
</html>