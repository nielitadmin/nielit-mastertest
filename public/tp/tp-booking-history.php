<?php
session_name('NIELIT_TP_SESSION');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'tp') {
    header("Location: tp-login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$tp_id = $_SESSION['user_id'];
$error = '';
$success_msg = '';

try {
    // Handle Deletion Request BEFORE fetching the table
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_booking_id'])) {
        $del_id = $_POST['delete_booking_id'];
        
        // Security: Only allow delete if it belongs to this TP AND status is Pending Payment
        $delStmt = $pdo->prepare("DELETE FROM slot_bookings WHERE id = ? AND tp_id = ? AND status = 'Pending Payment'");
        $delStmt->execute([$del_id, $tp_id]);
        
        if ($delStmt->rowCount() > 0) {
            $success_msg = "Unpaid booking request was successfully deleted.";
        } else {
            $error = "Could not delete booking. It may have already been processed or paid.";
        }
    }

    // Fetch all bookings for this TP, including payment transaction ID if available
    $stmt = $pdo->prepare("
        SELECT 
            b.*, 
            c.category_name, 
            c.category_code,
            p.transaction_id
        FROM slot_bookings b
        JOIN exam_categories c ON b.category_id = c.id
        LEFT JOIN payments p ON p.booking_id = b.id
        WHERE b.tp_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$tp_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History - NIELIT TP Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #059669; --primary-light: #10B981; --primary-bg: #D1FAE5;
            --secondary: #0F172A;
            --text-dark: #0F172A; --text-muted: #64748B;
            --bg-body: #F8FAFC; --surface: #FFFFFF; --border: #E2E8F0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            --radius-md: 12px; --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; padding-bottom: 60px; }

        /* Navbar */
        .top-nav { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--border); }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--bg-body); border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; color: var(--text-dark); text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.3s; }
        .btn-back:hover { background: var(--primary-bg); color: var(--primary); border-color: var(--primary-light); transform: translateX(-3px); }
        .brand-text h2 { font-size: 18px; font-weight: 800; color: var(--secondary); margin-bottom: 2px; }
        .brand-text span { font-size: 11px; color: var(--primary); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }

        .container { max-width: 1400px; margin: 30px auto; padding: 0 40px; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); margin-bottom: 5px; }
        .page-header p { color: var(--text-muted); font-weight: 500; font-size: 15px; }
        .btn-action { background: var(--primary); color: white; padding: 12px 24px; border-radius: var(--radius-md); text-decoration: none; font-weight: 700; font-size: 14px; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.2); }
        .btn-action:hover { background: #047857; transform: translateY(-2px); }

        /* Data Table */
        .table-card { background: white; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; }
        .table-toolbar { padding: 20px 25px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #F8FAFC; }
        .search-box { position: relative; width: 300px; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-box input { width: 100%; padding: 10px 15px 10px 40px; border-radius: 50px; border: 1px solid var(--border); font-family: inherit; font-size: 13px; outline: none; transition: 0.3s; }
        .search-box input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
        
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th { background: white; color: var(--text-muted); font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 15px 25px; text-align: left; border-bottom: 2px solid var(--border); }
        td { padding: 20px 25px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 500; color: var(--text-dark); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--bg-body); }

        .req-id { font-weight: 800; color: var(--text-dark); font-size: 15px; }
        .req-date { font-size: 12px; color: var(--text-muted); margin-top: 4px; display: block; }
        
        .module-info strong { color: var(--primary); font-size: 15px; }
        .module-info span { display: block; font-size: 12px; color: var(--text-muted); margin-top: 2px; }

        .fee-info { font-weight: 800; color: var(--text-dark); font-size: 15px; }
        .txn-info { font-size: 11px; font-weight: 700; color: var(--text-muted); background: var(--bg-body); padding: 4px 8px; border-radius: 6px; border: 1px dashed var(--border); display: inline-block; margin-top: 6px;}

        /* Status Badges */
        .badge { padding: 6px 12px; border-radius: 50px; font-size: 11px; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px; letter-spacing: 0.5px; }
        .badge-pending { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; } /* Pending Payment */
        .badge-verify { background: #DBEAFE; color: #1D4ED8; border: 1px solid #BFDBFE; } /* Verification Pending */
        .badge-approved { background: #EDE9FE; color: #6D28D9; border: 1px solid #DDD6FE; } /* Approved for Scheduling */
        .badge-scheduled { background: #D1FAE5; color: #059669; border: 1px solid #A7F3D0; } /* Scheduled */
        .badge-rejected { background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; } /* Rejected */

        /* Delete Button */
        .btn-delete { background: #FEE2E2; color: #DC2626; border: 1px solid #FCA5A5; padding: 8px 12px; border-radius: 8px; cursor: pointer; transition: 0.2s; font-size: 14px; }
        .btn-delete:hover { background: #DC2626; color: white; border-color: #DC2626; transform: scale(1.05); }

        @media (max-width: 768px) {
            .top-nav { padding: 15px 20px; }
            .container { padding: 0 20px; }
            .table-toolbar { flex-direction: column; align-items: stretch; gap: 15px; }
            .search-box { width: 100%; }
        }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="nav-left">
            <a href="tp-dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div class="brand-text">
                <h2>Institute Portal</h2>
                <span>Training Partner • NIELIT</span>
            </div>
        </div>
        <div style="font-weight: 700; font-size: 14px; color: var(--secondary);" class="hide-mobile">
            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
        </div>
    </nav>

    <div class="container">
        
        <div class="page-header">
            <div>
                <h1>Booking History & Ledger</h1>
                <p>Track your exam slot requests, payments, and scheduling statuses.</p>
            </div>
            <a href="tp-book-exam.php" class="btn-action"><i class="fas fa-plus"></i> New Booking</a>
        </div>

        <?php if ($success_msg): ?>
            <div style="background: #D1FAE5; color: #059669; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #FEE2E2; color: #DC2626; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600;"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="table-card">
            <div class="table-toolbar">
                <div style="font-weight: 800; font-size: 16px;">All Booking Records (<?php echo count($bookings); ?>)</div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by ID, Module, Status..." onkeyup="searchTable()">
                </div>
            </div>
            
            <div class="table-responsive">
                <table id="historyTable">
                    <thead>
                        <tr>
                            <th>Req ID & Date</th>
                            <th>Exam Module</th>
                            <th>Target Schedule</th>
                            <th>Batch Size</th>
                            <th>Payment Info</th>
                            <th>Live Status</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 60px; color: var(--text-muted);">
                                    <i class="fas fa-folder-open" style="font-size: 40px; margin-bottom: 15px; color: #CBD5E1; display: block;"></i>
                                    <h3 style="margin: 0 0 5px 0; color: var(--text-dark);">No Bookings Found</h3>
                                    <p style="margin: 0; font-size: 14px;">You have not initiated any exam slot bookings yet.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $b): 
                                // Determine styling based on status
                                $status = $b['status'];
                                $badge_class = 'badge-pending';
                                $icon = 'fa-clock';
                                
                                if ($status == 'Verification Pending') { $badge_class = 'badge-verify'; $icon = 'fa-file-invoice-dollar'; }
                                elseif ($status == 'Approved for Scheduling') { $badge_class = 'badge-approved'; $icon = 'fa-thumbs-up'; }
                                elseif ($status == 'Scheduled') { $badge_class = 'badge-scheduled'; $icon = 'fa-calendar-check'; }
                                elseif ($status == 'Rejected') { $badge_class = 'badge-rejected'; $icon = 'fa-times-circle'; }
                            ?>
                            <tr>
                                <td>
                                    <span class="req-id">#<?php echo str_pad($b['id'], 5, '0', STR_PAD_LEFT); ?></span>
                                    <span class="req-date">Requested: <?php echo date('d M Y', strtotime($b['created_at'])); ?></span>
                                </td>
                                <td>
                                    <div class="module-info">
                                        <strong><?php echo htmlspecialchars($b['category_code']); ?></strong>
                                        <span><?php echo htmlspecialchars($b['category_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark);">
                                        <i class="far fa-calendar-alt" style="color: var(--primary); margin-right: 4px;"></i> 
                                        <?php echo date('d M Y', strtotime($b['requested_date'])); ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                        <i class="far fa-clock" style="margin-right: 4px;"></i> 
                                        <?php echo date('h:i A', strtotime($b['requested_time'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: var(--bg-body); border: 1px solid var(--border); border-radius: 10px; font-weight: 800; color: var(--text-dark);">
                                        <?php echo $b['estimated_candidates']; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fee-info">₹<?php echo number_format($b['total_fee'], 2); ?></div>
                                    <?php if (!empty($b['transaction_id'])): ?>
                                        <div class="txn-info"><i class="fas fa-receipt"></i> <?php echo htmlspecialchars($b['transaction_id']); ?></div>
                                    <?php else: ?>
                                        <div class="txn-info" style="color: #B45309; background: #FEF3C7; border-color: #FDE68A;">Unpaid</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($status == 'Pending Payment'): ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this unpaid booking request?');" style="margin: 0;">
                                            <input type="hidden" name="delete_booking_id" value="<?php echo $b['id']; ?>">
                                            <button type="submit" class="btn-delete" title="Cancel & Delete Booking">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 12px; font-weight: 700; background: var(--bg-body); padding: 4px 8px; border-radius: 6px;"><i class="fas fa-lock"></i> Locked</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        function searchTable() {
            const filter = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#historyTable tbody tr');
            
            rows.forEach(row => {
                if (row.cells.length === 1) return; // Skip empty state row
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        }
    </script>
</body>
</html>