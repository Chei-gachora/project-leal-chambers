<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time(); // For 5-min auto logout

$user_id = $_SESSION['user_id'];   // ← Get logged-in user

include 'connect.php';

// Fetch SOL statistics - ONLY FOR CURRENT USER
$today = date('Y-m-d');

// Critical, Warning, Safe counts...
$stmtCritical = $conn->prepare("SELECT COUNT(*) as total FROM cases WHERE user_id = ? AND sol_expiry_date IS NOT NULL AND DATEDIFF(sol_expiry_date, ?) <= 30 AND status != 'Closed'");
$stmtCritical->bind_param("is", $user_id, $today);
$stmtCritical->execute();
$critical = $stmtCritical->get_result()->fetch_assoc()['total'] ?? 0;

$stmtWarning = $conn->prepare("SELECT COUNT(*) as total FROM cases WHERE user_id = ? AND sol_expiry_date IS NOT NULL AND DATEDIFF(sol_expiry_date, ?) BETWEEN 31 AND 90 AND status != 'Closed'");
$stmtWarning->bind_param("is", $user_id, $today);
$stmtWarning->execute();
$warning = $stmtWarning->get_result()->fetch_assoc()['total'] ?? 0;

$stmtSafe = $conn->prepare("SELECT COUNT(*) as total FROM cases WHERE user_id = ? AND sol_expiry_date IS NOT NULL AND DATEDIFF(sol_expiry_date, ?) > 90 AND status != 'Closed'");
$stmtSafe->bind_param("is", $user_id, $today);
$stmtSafe->execute();
$safe = $stmtSafe->get_result()->fetch_assoc()['total'] ?? 0;

$totalTracked = $critical + $warning + $safe;

// Fetch cases for dropdown - ONLY FOR CURRENT USER
$stmtCases = $conn->prepare("
    SELECT id, case_no, client_name
    FROM cases
    WHERE user_id = ?
    ORDER BY case_no ASC
");
$stmtCases->bind_param("i", $user_id);
$stmtCases->execute();
$allCases = $stmtCases->get_result();

// Fetch cases for table - ONLY FOR CURRENT USER
$stmtTable = $conn->prepare("
    SELECT id, case_no, client_name, case_type,
           DATE_FORMAT(created_at, '%d %b %Y') as accrual_date_formatted,
           sol_expiry_date,
           DATEDIFF(sol_expiry_date, ?) as days_remaining,
           status
    FROM cases
    WHERE user_id = ? AND sol_expiry_date IS NOT NULL
    ORDER BY sol_expiry_date ASC
");
$stmtTable->bind_param("si", $today, $user_id);
$stmtTable->execute();
$resultCases = $stmtTable->get_result();


// --- AUTOMATED CRITICAL SOL EMAIL DISPATCH ENGINE ---
if ($critical > 0) {
    try {
        // 1. Query all critical case records details specifically to compile email text
        $stmtAlerts = $conn->prepare("
            SELECT id, case_no, client_name, sol_expiry_date, DATEDIFF(sol_expiry_date, ?) as days_left 
            FROM cases 
            WHERE user_id = ? AND sol_expiry_date IS NOT NULL AND DATEDIFF(sol_expiry_date, ?) <= 30 AND status != 'Closed'
        ");
        $stmtAlerts->bind_param("sis", $today, $user_id, $today);
        $stmtAlerts->execute();
        $criticalCasesResult = $stmtAlerts->get_result();

        // 2. Read previously emailed case IDs to avoid spamming the user on page loads
        $log_file = __DIR__ . '/sol_critical_emailed.txt';
        $emailed_case_ids = file_exists($log_file) ? explode(',', file_get_contents($log_file)) : [];
        $emailed_case_ids = array_map('intval', array_filter($emailed_case_ids));

        $new_critical_cases = [];
        while ($case_row = $criticalCasesResult->fetch_assoc()) {
            if (!in_array((int)$case_row['id'], $emailed_case_ids)) {
                $new_critical_cases[] = $case_row;
            }
        }

        // 3. If there are new critical cases that haven't been emailed yet, fire PHPMailer
        if (!empty($new_critical_cases)) {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                require_once __DIR__ . '/PHPMailer/src/Exception.php';
                require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
                require_once __DIR__ . '/PHPMailer/src/SMTP.php';
            }

            // Fetch active user details using your standard database profile columns
            $user_stmt = $conn->prepare("SELECT email, firstname, lastname FROM users WHERE id = ?");
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_data = $user_stmt->get_result()->fetch_assoc();

            if ($user_data && !empty($user_data['email'])) {
                $display_name = trim(($user_data['firstname'] ?? '') . ' ' . ($user_data['lastname'] ?? ''));
                if (empty($display_name)) $display_name = "User";

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->SMTPDebug = 0; 
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'chegeofficial02@gmail.com';
                $mail->Password   = 'cyjykxbwdaqofpmx'; 
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];

                $mail->setFrom('chegeofficial02@gmail.com', 'LegalPro Alerts');
                $mail->addAddress($user_data['email'], $display_name);

                $mail->isHTML(true);
                $mail->Subject = '⚠️ CRITICAL ACTION REQUIRED: SOL Expiry Approaching - LegalPro';

                $body = "<h3>Hello " . htmlspecialchars($display_name) . ",</h3>";
                $body .= "<p>The following case(s) have entered the <strong>CRITICAL SOL EXPIRY ZONE</strong> ($\le 30$ days remaining). Immediate legal action is required to secure deadlines:</p>";
                $body .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%; max-width: 600px; border-color: #dc3545;'>";
                $body .= "<tr style='background-color: #dc3545; color: white;'><th>Case No</th><th>Client Name</th><th>Expiry Date</th><th>Days Left</th></tr>";

                foreach ($new_critical_cases as $c) {
                    $body .= "<tr>";
                    $body .= "<td><strong>" . htmlspecialchars($c['case_no']) . "</strong></td>";
                    $body .= "<td>" . htmlspecialchars($c['client_name']) . "</td>";
                    $body .= "<td style='color: #dc3545; font-weight: bold;'>" . htmlspecialchars($c['sol_expiry_date']) . "</td>";
                    $body .= "<td style='background-color: #f8d7da; color: #721c24; text-align: center; font-weight: bold;'>" . $c['days_left'] . " days</td>";
                    $body .= "</tr>";
                    
                    // Add this specific case ID to our emailed checklist tracking file array
                    $emailed_case_ids[] = (int)$c['id'];
                }
                
                $body .= "</table>";
                $body .= "<p><a href='http://localhost/wakili/dashboard.php' style='display:inline-block; padding:10px 20px; color:#fff; background-color:#dc3545; text-decoration:none; border-radius:4px; font-weight:bold;'>Review Critical Cases on Dashboard</a></p>";

                $mail->Body = $body;
                if ($mail->send()) {
                    // Update log file with newly checked IDs to prevent duplication loops
                    file_put_contents($log_file, implode(',', $emailed_case_ids));
                    file_put_contents(__DIR__ . '/mail_debug.txt', "[" . date('Y-m-d H:i:s') . "] Success (SOL Alert): Sent critical status email update to " . $user_data['email'] . "\n", FILE_APPEND);
                }
            }
        }
    } catch (Throwable $t) {
        file_put_contents(__DIR__ . '/mail_debug.txt', "[" . date('Y-m-d H:i:s') . "] Error (SOL Alert): " . $t->getMessage() . "\n", FILE_APPEND);
    }
}
// -----------------------------------------------------

// Rewind internal data array pointer for your data display table below loop variables
$resultCases->data_seek(0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOL Tracker | LegalPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #0f172a; color: #e2e8f0; }
        .sidebar { background-color: rgba(30, 41, 55, 0.95); backdrop-filter: blur(12px); min-height: 100vh; }
        .glass { background: rgba(30, 41, 55, 0.65) !important; backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; }
        .top-bar { background: rgba(30, 41, 55, 0.85); backdrop-filter: blur(12px); }
        .nav-link.active { background-color: #3b82f6 !important; color: white !important; }
        .stat-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar p-4">
            <h4 class="mb-4"><i class="fas fa-gavel"></i> LegalPro</h4>
            <ul class="nav flex-column">
                <li class="nav-item"><a href="dashboard.php" class="nav-link text-light"><i class="fas fa-home me-2"></i> Dashboard</a></li>
                <li class="nav-item"><a href="calender.php" class="nav-link text-light"><i class="fas fa-calendar-alt me-2"></i> Calendar</a></li>
                <li class="nav-item"><a href="casess.php" class="nav-link text-light"><i class="fas fa-folder-open me-2"></i> Cases</a></li>
                <li class="nav-item"><a href="soltracker.php" class="nav-link active"><i class="fas fa-clock me-2"></i> SOL Tracker</a></li>
                <li class="nav-item"><a href="team.php" class="nav-link text-light"><i class="fas fa-users me-2"></i> Team</a></li>
                <li class="nav-item"><a href="documents.php" class="nav-link text-light"><i class="fas fa-file-alt me-2"></i> Documents</a></li>
                <li class="nav-item"><a href="Reportts.php" class="nav-link text-light"><i class="fas fa-chart-bar me-2"></i> Reports</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 p-0">
            <div class="top-bar p-3 d-flex justify-content-between align-items-center border-bottom">
                <h5>Statute of Limitations Tracker</h5>
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#solCalculatorModal">
                        <i class="fas fa-calculator me-2"></i> Calculate SOL
                    </button>
                </div>
            </div>

            <div class="p-4">
                <!-- Summary Cards (unchanged) -->
                <div class="row g-4 mb-5">
                    <div class="col-md-3">
                        <div class="glass stat-card p-4 border border-danger border-2">
                            <h6 class="text-danger">Critical (≤ 30 days)</h6>
                            <h2 class="mb-1 text-danger"><?= $critical ?></h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="glass stat-card p-4 border border-warning border-2">
                            <h6 class="text-warning">Warning (31-90 days)</h6>
                            <h2 class="mb-1 text-warning"><?= $warning ?></h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="glass stat-card p-4 border border-success border-2">
                            <h6 class="text-success">Safe (> 90 days)</h6>
                            <h2 class="mb-1 text-success"><?= $safe ?></h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="glass stat-card p-4">
                            <h6 class="text-muted">Total Cases Tracked</h6>
                            <h2 class="mb-1"><?= $totalTracked ?></h2>
                        </div>
                    </div>
                </div>

                <!-- SOL Overview Table (same as before) -->
                <div class="glass mb-4">
                    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Statute of Limitations Overview</h6>
                        <input type="text" id="searchInput" class="form-control w-25" placeholder="Search cases...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="solTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Case Number</th>
                                    <th>Client</th>
                                    <th>Case Type</th>
                                    <th>Accrual Date</th>
                                    <th>Expiry Date</th>
                                    <th>Days Remaining</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $resultCases->fetch_assoc()):
                                    $days = (int)$row['days_remaining'];
                                    if ($days <= 0) {
                                        $statusBadge = '<span class="badge bg-danger">Expired</span>';
                                        $daysText = '<strong class="text-danger">Expired</strong>';
                                    } elseif ($days <= 30) {
                                        $statusBadge = '<span class="badge bg-danger">Critical</span>';
                                        $daysText = '<strong class="text-danger">' . $days . ' days</strong>';
                                    } elseif ($days <= 90) {
                                        $statusBadge = '<span class="badge bg-warning text-dark">Warning</span>';
                                        $daysText = '<strong class="text-warning">' . $days . ' days</strong>';
                                    } else {
                                        $statusBadge = '<span class="badge bg-success">Safe</span>';
                                        $daysText = $days . ' days';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['case_no']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                                    <td><?= htmlspecialchars($row['case_type']) ?></td>
                                    <td><?= htmlspecialchars($row['accrual_date_formatted']) ?></td>
                                    <td><?= htmlspecialchars($row['sol_expiry_date']) ?></td>
                                    <td><?= $daysText ?></td>
                                    <td><?= $statusBadge ?></td>
                                    <td>
                                
                                        <a href="view_case.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">View Details</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic SOL Calculator Modal -->
<div class="modal fade" id="solCalculatorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass">
            <div class="modal-header">
                <h5 class="modal-title">Calculate Statute of Limitations</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select Case</label>
                    <select class="form-select" id="calcCase">
                        <option value="">-- Select a Case --</option>
                        <?php while ($c = $allCases->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['case_no']) ?> - <?= htmlspecialchars($c['client_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Limitation Period (Years)</label>
                    <input type="number" id="limYears" class="form-control" value="6" min="1">
                </div>

                <div class="mb-3">
                    <label class="form-label">Accrual Date</label>
                    <input type="date" id="accrualDate" class="form-control">
                </div>

                <button class="btn btn-primary w-100" onclick="calculateSOL()">Calculate Expiry Date</button>

                <div id="calcResult" class="mt-4 alert alert-info" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function calculateSOL() {
        const caseId = document.getElementById('calcCase').value;
        const years = parseInt(document.getElementById('limYears').value);
        const accrual = document.getElementById('accrualDate').value;

        if (!accrual || !years) {
            alert("Please fill all fields");
            return;
        }

        const expiry = new Date(accrual);
        expiry.setFullYear(expiry.getFullYear() + years);

        const resultDiv = document.getElementById('calcResult');
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = `
            <strong>Case:</strong> ${document.getElementById('calcCase').options[document.getElementById('calcCase').selectedIndex].text}<br>
            <strong>Accrual Date:</strong> ${accrual}<br>
            <strong>Limitation Period:</strong> ${years} years<br>
            <strong>Expiry Date:</strong> <span class="fw-bold">${expiry.toLocaleDateString('en-GB')}</span>
        `;
    }

    // Live Search
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        document.querySelectorAll('#solTable tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    });
</script>
</body>
</html>