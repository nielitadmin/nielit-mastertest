<?php
session_name('NIELIT_TP_SESSION');
session_start();

require_once __DIR__ . '/../../config/database.php';

// =========================================================================
// AJAX API: LIVE SLOT AVAILABILITY CHECKER
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] == 'check_slots') {
    header('Content-Type: application/json');
    $date = $_GET['date'];
    
    // Define all standard 2-Hour Slots for the entire day
    $slots = [
        '08:00:00' => ['label' => '08:00 AM - 10:00 AM', 'booked' => 0],
        '10:00:00' => ['label' => '10:00 AM - 12:00 PM', 'booked' => 0],
        '12:00:00' => ['label' => '12:00 PM - 02:00 PM', 'booked' => 0],
        '14:00:00' => ['label' => '02:00 PM - 04:00 PM', 'booked' => 0],
        '16:00:00' => ['label' => '04:00 PM - 06:00 PM', 'booked' => 0]
    ];
    
    // Query database to find out how many students are already booked for this date
    $stmt = $pdo->prepare("
        SELECT requested_time, SUM(estimated_candidates) as total_booked 
        FROM slot_bookings 
        WHERE requested_date = ? AND status != 'Rejected'
        GROUP BY requested_time
    ");
    $stmt->execute([$date]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($bookings as $b) {
        $time = $b['requested_time'];
        if (isset($slots[$time])) {
            $slots[$time]['booked'] += $b['total_booked'];
        }
    }
    
    echo json_encode($slots);
    exit();
}
// =========================================================================

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'tp') {
    header("Location: tp-login.php");
    exit();
}

$tp_id = $_SESSION['user_id'];
$error = '';

try {
    // Fetch available Modules and their Fees
    $categories = $pdo->query("SELECT id, category_code, category_name, exam_fee FROM exam_categories ORDER BY category_code")->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch this TP's students
    $stmt = $pdo->prepare("
        SELECT u.id as user_id, u.full_name, c.registration_number 
        FROM candidates c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.tp_id = ?
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tp_id]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process Booking
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['initiate_booking'])) {
        $category_id = $_POST['category_id'];
        $req_date = $_POST['req_date'];
        $req_time = $_POST['req_time'];
        $selected_candidates = $_POST['candidates'] ?? [];
        
        if (empty($category_id) || empty($req_date) || empty($req_time) || empty($selected_candidates)) {
            $error = "Please fill all fields and select at least one student.";
        } else {
            $num_candidates = count($selected_candidates);
            
            // Lookup the fee for the selected category
            $feeStmt = $pdo->prepare("SELECT exam_fee FROM exam_categories WHERE id = ?");
            $feeStmt->execute([$category_id]);
            $base_fee = $feeStmt->fetchColumn() ?: 500.00;
            
            $total_fee = $num_candidates * $base_fee;
            $candidates_json = json_encode($selected_candidates); // Save who they selected
            
            $pdo->beginTransaction();
            $bookStmt = $pdo->prepare("
                INSERT INTO slot_bookings (tp_id, category_id, requested_date, requested_time, estimated_candidates, total_fee, status, selected_candidates)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending Payment', ?) RETURNING id
            ");
            $bookStmt->execute([$tp_id, $category_id, $req_date, $req_time, $num_candidates, $total_fee, $candidates_json]);
            $booking_id = $bookStmt->fetchColumn();
            $pdo->commit();
            
            // Redirect to our Razorpay Dummy Gateway
            header("Location: tp-payment-gateway.php?booking_id=" . $booking_id);
            exit();
        }
    }
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Exam Slot - NIELIT TP Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #059669; --primary-light: #10B981; --primary-bg: #D1FAE5;
            --text-dark: #0F172A; --text-muted: #64748B;
            --bg-body: #F8FAFC; --surface: #FFFFFF; --border: #E2E8F0;
            --radius-md: 12px; --radius-lg: 20px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-body); color: var(--text-dark); padding-bottom: 50px; }
        
        .top-nav { background: rgba(255, 255, 255, 0.9); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--bg-body); border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; color: var(--text-dark); text-decoration: none; font-weight: 700; font-size: 13px; }
        
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); margin-bottom: 5px;}
        .page-header p { color: var(--text-muted); font-weight: 500;}

        .booking-grid { display: grid; grid-template-columns: 1fr 350px; gap: 30px; align-items: start; }
        
        .form-card { background: white; border-radius: var(--radius-lg); padding: 30px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid var(--border); }
        .section-title { font-size: 15px; font-weight: 800; color: var(--primary); border-bottom: 2px solid var(--border); padding-bottom: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;}
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 8px; color: var(--text-dark); }
        .form-control { width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid var(--border); font-family: inherit; font-size: 14px; outline: none; background: #F8FAFC; transition: 0.3s; }
        .form-control:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 3px var(--primary-bg); }
        
        .candidate-list { max-height: 300px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px; background: #F8FAFC; }
        .candidate-item { display: flex; align-items: center; gap: 15px; padding: 12px 15px; border-bottom: 1px solid var(--border); transition: 0.2s; }
        .candidate-item:hover { background: white; }
        .candidate-item:last-child { border-bottom: none; }
        .candidate-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; }
        .candidate-info strong { display: block; font-size: 14px; color: var(--text-dark); }
        .candidate-info span { font-size: 12px; color: var(--text-muted); }

        .summary-card { background: #0F172A; color: white; border-radius: var(--radius-lg); padding: 30px; position: sticky; top: 100px; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2); }
        .summary-card h3 { font-size: 18px; font-weight: 800; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;}
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 14px; color: #CBD5E1; font-weight: 500;}
        .summary-row span.val { color: white; font-weight: 700; }
        .total-row { display: flex; justify-content: space-between; margin-top: 20px; padding-top: 20px; border-top: 1px dashed rgba(255,255,255,0.2); font-size: 20px; font-weight: 800; color: #10B981; }
        
        .btn-pay { width: 100%; background: #10B981; color: white; border: none; padding: 15px; border-radius: 12px; font-size: 15px; font-weight: 800; margin-top: 25px; cursor: pointer; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 10px; font-family: inherit;}
        .btn-pay:hover { background: #059669; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3); }
        .btn-pay:disabled { background: #64748B !important; cursor: not-allowed; transform: none !important; box-shadow: none !important;}

        @media (max-width: 992px) { .booking-grid { grid-template-columns: 1fr; } .summary-card { position: static; } }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="nav-left">
            <a href="tp-dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
            <div style="font-weight: 800; color: var(--primary); font-size: 18px; margin-left: 10px;">Institute Portal</div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Book Exam Slot</h1>
            <p>Select an exam module, choose your students, and secure an available 2-hour time slot.</p>
        </div>

        <?php if ($error): ?>
            <div style="background: #FEE2E2; color: #DC2626; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600;"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" class="booking-grid">
            
            <div class="form-card">
                
                <div class="section-title" style="margin-top: 0px;"><i class="fas fa-users"></i> 1. Select Candidates</div>
                <?php if (empty($candidates)): ?>
                    <div style="padding: 20px; text-align: center; background: #F8FAFC; border: 1px dashed var(--border); border-radius: 10px; color: var(--text-muted);">
                        No students found. Please add students in "Manage Batch" first.
                    </div>
                <?php else: ?>
                    <div class="candidate-list" style="margin-bottom: 25px;">
                        <div class="candidate-item" style="background: white; position: sticky; top: 0; border-bottom: 2px solid var(--border); z-index: 10;">
                            <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
                            <label for="selectAll" style="margin: 0; cursor: pointer; font-weight: 800; color: var(--primary);">Select All Students</label>
                        </div>
                        
                        <?php foreach ($candidates as $c): ?>
                            <label class="candidate-item" style="cursor: pointer;">
                                <input type="checkbox" name="candidates[]" value="<?php echo $c['user_id']; ?>" class="cand-checkbox" onchange="calculateTotal()">
                                <div class="candidate-info">
                                    <strong><?php echo htmlspecialchars($c['full_name']); ?></strong>
                                    <span>Roll: <?php echo htmlspecialchars($c['registration_number']); ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="section-title"><i class="fas fa-book"></i> 2. Exam & Slot Details</div>
                
                <div class="form-group">
                    <label>Select Module / Course</label>
                    <select name="category_id" id="categorySelect" class="form-control" required onchange="calculateTotal()">
                        <option value="" data-fee="0">-- Select a Module --</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" data-fee="<?php echo $cat['exam_fee']; ?>">
                                <?php echo htmlspecialchars($cat['category_code'] . ' - ' . $cat['category_name']); ?> (₹<?php echo $cat['exam_fee']; ?>/student)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Target Date</label>
                        <input type="date" name="req_date" id="req_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>" onchange="checkLiveSlots()">
                    </div>
                    <div class="form-group">
                        <label>Available 2-Hour Slots</label>
                        <select name="req_time" id="req_time" class="form-control" required disabled>
                            <option value="">-- Select Date First --</option>
                        </select>
                    </div>
                </div>

            </div>

            <div class="summary-card">
                <h3>Payment Summary</h3>
                <div class="summary-row">
                    <span>Base Fee per Student</span>
                    <span class="val">₹<span id="sumBaseFee">0</span></span>
                </div>
                <div class="summary-row">
                    <span>Selected Students</span>
                    <span class="val"><span id="sumStudents">0</span></span>
                </div>
                <div class="summary-row">
                    <span>Processing Fee</span>
                    <span class="val">₹0</span>
                </div>
                
                <div class="total-row">
                    <span>Total Payable</span>
                    <span>₹<span id="sumTotal">0</span></span>
                </div>

                <button type="submit" name="initiate_booking" class="btn-pay" id="payBtn" disabled>
                    Pay & Book Slot <i class="fas fa-arrow-right"></i>
                </button>
            </div>

        </form>
    </div>

    <script>
        // Global variable. You can change this to 50, 100, 200, etc. based on your mock center limits
        const GLOBAL_MAX_CAPACITY = 100; 

        function toggleAll(source) {
            const checkboxes = document.querySelectorAll('.cand-checkbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
            calculateTotal();
            checkLiveSlots(); 
        }

        function calculateTotal() {
            const select = document.getElementById('categorySelect');
            const fee = select.options[select.selectedIndex].getAttribute('data-fee');
            const baseFee = parseFloat(fee) || 0;
            
            const selectedCount = document.querySelectorAll('.cand-checkbox:checked').length;
            const total = baseFee * selectedCount;

            document.getElementById('sumBaseFee').innerText = baseFee.toFixed(2);
            document.getElementById('sumStudents').innerText = selectedCount;
            document.getElementById('sumTotal').innerText = total.toFixed(2);

            validateForm();
            checkLiveSlots(); 
        }

        // The Smart API Call
        function checkLiveSlots() {
            const dateInput = document.getElementById('req_date').value;
            const timeSelect = document.getElementById('req_time');
            const selectedCount = document.querySelectorAll('.cand-checkbox:checked').length;

            if (!dateInput) return;

            timeSelect.innerHTML = '<option value="">Checking availability...</option>';
            timeSelect.disabled = true;

            fetch(`tp-book-exam.php?action=check_slots&date=${dateInput}`)
                .then(response => response.json())
                .then(data => {
                    timeSelect.innerHTML = '<option value="">-- Choose a Slot --</option>';
                    timeSelect.disabled = false;

                    let hasAvailableSlot = false;

                    // ONLY display slots that have enough capacity
                    for (const [time, info] of Object.entries(data)) {
                        const seatsAvailable = GLOBAL_MAX_CAPACITY - info.booked;

                        // Only add the option if it has enough seats for the selected students
                        if (seatsAvailable >= selectedCount && seatsAvailable > 0) {
                            const option = document.createElement('option');
                            option.value = time;
                            option.text = `${info.label} (${seatsAvailable} Seats Left)`;
                            timeSelect.appendChild(option);
                            hasAvailableSlot = true;
                        }
                    }

                    // If no slots are appended, show an error message
                    if (!hasAvailableSlot && selectedCount > 0) {
                        timeSelect.innerHTML = '<option value="">No available slots for this batch size</option>';
                        timeSelect.disabled = true;
                    } else if (!hasAvailableSlot && selectedCount === 0) {
                        timeSelect.innerHTML = '<option value="">Please select students first</option>';
                        timeSelect.disabled = true;
                    }
                    
                    validateForm();
                })
                .catch(error => {
                    timeSelect.innerHTML = '<option value="">Error checking slots</option>';
                });
        }

        // Make sure the time dropdown triggers form validation when changed
        document.getElementById('req_time').addEventListener('change', validateForm);

        function validateForm() {
            const selectedCount = document.querySelectorAll('.cand-checkbox:checked').length;
            const baseFee = parseFloat(document.getElementById('categorySelect').options[document.getElementById('categorySelect').selectedIndex].getAttribute('data-fee')) || 0;
            const timeSelected = document.getElementById('req_time').value;

            const payBtn = document.getElementById('payBtn');
            
            // They can only pay if they have students, a module, and a valid slot selected!
            if (selectedCount > 0 && baseFee > 0 && timeSelected !== "") {
                payBtn.disabled = false;
            } else {
                payBtn.disabled = true;
            }
        }
    </script>
</body>
</html>