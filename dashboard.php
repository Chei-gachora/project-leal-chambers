<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Consistent PDO and mysqli Connection
require_once 'connect.php';

// Get logged-in user ID
$user_id = $_SESSION['user_id'] ?? 0;

// 2. Fetch the 5 most recent records for THIS USER
$sql = "SELECT case_no, client_name, status, next_hearing_date, sol_expiry_date 
        FROM cases 
        WHERE user_id = ? 
        ORDER BY id DESC LIMIT 5";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Fallback if column doesn't exist yet
    $result = $conn->query("SELECT case_no, client_name, status, next_hearing_date, sol_expiry_date FROM cases ORDER BY id DESC LIMIT 5");
}

$recent_cases = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $recent_cases[] = $row;
    }
}
?>
<?php
// 1. Establish database connection link
require_once 'connect.php';

// Set working calendar variables
$today          = date('Y-m-d');
$oneWeekFromNow = date('Y-m-d', strtotime('+7 days'));
$currentYear    = date('Y');
$currentMonth   = date('m');
// Get logged-in user ID
$user_id = $_SESSION['user_id'] ?? 0;
// =========================================================================
// METRIC CARDS DATA QUERIES - NOW USER SPECIFIC
// =========================================================================

// Card: Total Cases
$queryTotalCases = "SELECT COUNT(*) as total FROM cases WHERE user_id = ?";
$stmtTotalCases = $conn->prepare($queryTotalCases);
$stmtTotalCases->bind_param("i", $user_id);
$stmtTotalCases->execute();
$totalCasesCount = $stmtTotalCases->get_result()->fetch_assoc()['total'];

// Card: Upcoming Deadlines
$queryTotal = "SELECT COUNT(*) as total FROM cases WHERE user_id = ? AND next_hearing_date >= ? AND status != 'Closed'";
$stmtTotal = $conn->prepare($queryTotal);
$stmtTotal->bind_param("is", $user_id, $today);
$stmtTotal->execute();
$totalDeadlines = $stmtTotal->get_result()->fetch_assoc()['total'];

$queryWeek = "SELECT COUNT(*) as total FROM cases WHERE user_id = ? AND next_hearing_date BETWEEN ? AND ? AND status != 'Closed'";
$stmtWeek = $conn->prepare($queryWeek);
$stmtWeek->bind_param("iss", $user_id, $today, $oneWeekFromNow);
$stmtWeek->execute();
$dueThisWeek = $stmtWeek->get_result()->fetch_assoc()['total'];

// Card: Active Matters
$queryActive = "SELECT COUNT(*) as total FROM cases WHERE user_id = ? AND status = 'Active'";
$stmtActive = $conn->prepare($queryActive);
$stmtActive->bind_param("i", $user_id);
$stmtActive->execute();
$activeMattersCount = $stmtActive->get_result()->fetch_assoc()['total'];

$querySol = "SELECT COUNT(*) as total FROM cases WHERE user_id = ? AND status = 'Active' AND (sol_expiry_date >= ? OR sol_expiry_date IS NULL)";
$stmtSol = $conn->prepare($querySol);
$stmtSol->bind_param("is", $user_id, $today);
$stmtSol->execute();
$activeWithinSolCount = $stmtSol->get_result()->fetch_assoc()['total'];

if ($activeMattersCount === $activeWithinSolCount && $activeMattersCount > 0) {
    $solSubtext = "All within SOL";
    $solColor = "#198754";
} else {
    $expiredCount = $activeMattersCount - $activeWithinSolCount;
    $solSubtext = $expiredCount . " action required (SOL expired)";
    $solColor = "#dc3545";
}

// Card: This Month's Hearings
$queryMonthlyCount = "SELECT COUNT(*) as total FROM cases WHERE user_id = ? AND YEAR(next_hearing_date) = ? AND MONTH(next_hearing_date) = ? AND status != 'Closed'";
$stmtMonth = $conn->prepare($queryMonthlyCount);
$stmtMonth->bind_param("iss", $user_id, $currentYear, $currentMonth);
$stmtMonth->execute();
$monthHearingsCount = $stmtMonth->get_result()->fetch_assoc()['total'];

$queryNextHearing = "SELECT next_hearing_date FROM cases WHERE user_id = ? AND next_hearing_date >= ? AND YEAR(next_hearing_date) = ? AND MONTH(next_hearing_date) = ? AND status != 'Closed' ORDER BY next_hearing_date ASC LIMIT 1";
$stmtNext = $conn->prepare($queryNextHearing);
$stmtNext->bind_param("isss", $user_id, $today, $currentYear, $currentMonth);
$stmtNext->execute();
$resultNext = $stmtNext->get_result()->fetch_assoc();
$nextHearingFormatted = ($resultNext && !empty($resultNext['next_hearing_date'])) ? "Next: " . date('j M', strtotime($resultNext['next_hearing_date'])) : "No more hearings this month";

// =========================================================================
// TIMELINE STREAM LOGIC (Upcoming Events Feed) - USER SPECIFIC
// =========================================================================
$upcomingEvents = [];

// Hearings
$queryHearings = "SELECT id, title, court, next_hearing_date FROM cases WHERE user_id = ? AND next_hearing_date >= ? AND status != 'Closed' ORDER BY next_hearing_date ASC LIMIT 5";
$stmtHearings = $conn->prepare($queryHearings);
$stmtHearings->bind_param("is", $user_id, $today);
$stmtHearings->execute();
$resHearings = $stmtHearings->get_result();
while ($row = $resHearings->fetch_assoc()) {
    $upcomingEvents[] = [
        'type' => 'Hearing',
        'title' => 'Hearing - ' . $row['title'],
        'meta' => $row['court'] ? htmlspecialchars($row['court']) : 'Court unassigned',
        'date_string' => date('j F Y, 9:00 AM', strtotime($row['next_hearing_date'])),
        'timestamp' => strtotime($row['next_hearing_date'])
    ];
}

// SOL Warnings
$querySolWarnings = "SELECT id, title, case_no, sol_expiry_date FROM cases WHERE user_id = ? AND sol_expiry_date >= ? AND status = 'Active' ORDER BY sol_expiry_date ASC LIMIT 3";
$stmtSolFeed = $conn->prepare($querySolWarnings);
$stmtSolFeed->bind_param("is", $user_id, $today);
$stmtSolFeed->execute();
$resSolFeed = $stmtSolFeed->get_result();
while ($row = $resSolFeed->fetch_assoc()) {
    $diffDays = (int)ceil((strtotime($row['sol_expiry_date']) - strtotime($today)) / 86400);
    $upcomingEvents[] = [
        'type' => 'SOL_Warning',
        'title' => "Keep in Mind: Statute of Limitation Warning",
        'meta' => htmlspecialchars($row['title']) . " - Case " . htmlspecialchars($row['case_no']),
        'date_string' => "Expires in " . $diffDays . " days",
        'timestamp' => strtotime($row['sol_expiry_date']) - 1
    ];
}

usort($upcomingEvents, function($a, $b) {
    return $a['timestamp'] <=> $b['timestamp'];
});
?>
<?php


// dashboard.php
if (session_status() === PHP_SESSION_NONE) 
    session_start();




// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's first and last name
$stmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$full_name = $user ? htmlspecialchars(trim($user['firstname'] . ' ' . $user['lastname'])) : 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Legal Practice Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
  <div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar p-4">
            <h4 class="mb-4">
                <i class="fas fa-gavel"></i> LegalPro
            </h4>
            <ul class="nav flex-column">
                <li class="nav-item"><a href="#" class="nav-link active"><i class="fas fa-home me-2"></i> Dashboard</a></li>
                <li class="nav-item"><a href="calender.php" class="nav-link"><i class="fas fa-calendar-alt me-2"></i> Calendar</a></li>
                <li class="nav-item"><a href="casess.php" class="nav-link"><i class="fas fa-folder-open me-2"></i> Cases</a></li>
                <li class="nav-item"><a href="soltracker.php" class="nav-link"><i class="fas fa-clock me-2"></i> SOL Tracker</a></li>
                <li class="nav-item"><a href="team.php" class="nav-link"><i class="fas fa-users me-2"></i> Team</a></li>
                <li class="nav-item"><a href="documents.php" class="nav-link"><i class="fas fa-file-alt me-2"></i> Documents</a></li>
                <li class="nav-item"><a href="Reportts.html" class="nav-link"><i class="fas fa-chart-bar me-2"></i> Reports</a></li>
            </ul>
        </div>

        <!-- Main Content Area -->
        <div class="col-md-10 p-0">
            <!-- Top Navbar -->
            <div class="top-bar p-3 d-flex justify-content-between align-items-center">
                <h1 class="welcome">Welcome back, <?= $full_name ?></h1>
                
                <div class="d-flex align-items-center gap-3">
                    
<!-- Notification Bell -->
<li class="nav-item dropdown position-relative">
    <a class="nav-link" href="#" id="notificationBell" onclick="toggleNotifications()">
        <i class="fas fa-bell fa-lg"></i>
        <span class="badge bg-danger position-absolute top-0 start-100 translate-middle" id="notifCount" style="font-size: 0.7rem;">0</span>
    </a>
    
    <div class="dropdown-menu dropdown-menu-end shadow-lg" id="notificationDropdown" style="width: 420px; max-height: 520px; overflow-y: auto; display: none;">
        <div class="dropdown-header border-bottom">
            <strong>Notifications</strong>
           <!-- Mark as Read Button -->
<a href="#" id="markAsReadBtn" class="text-danger small fw-bold">Mark all as read</a>
        </div>
        <div id="notificationsList" class="p-2"></div>
    </div>
</li>
                    <!-- Profile -->
                    <a href="profile.php" style="text-decoration: none; color: inherit; display: inline-flex; align-items-center;">
                        <img src="imglogin.jpg" alt="Profile" id="profileAvatar" style="width: 32px; height: 32px; border-radius: 50%;">
                        <span style="margin-left: 8px;">Profile</span>
                    </a>

                    <!-- Logout -->
                    <a href="logout.php" class="nav-link text-light">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>

                </div>
            </div>

            <!-- Dashboard Stats Grid -->
            <!-- Your stats cards go here -->

        
                <!-- Dashboard Stats Grid -->
                <div class="p-4">
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                           <?php
// Get logged-in user ID
$user_id = $_SESSION['user_id'] ?? 0;

try {

    // Total Cases for THIS user only
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cases WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $totalCases = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Monthly Cases for THIS user
    $stmtMonth = $pdo->prepare("
        SELECT COUNT(*) as this_month 
        FROM cases 
        WHERE user_id = ? 
          AND MONTH(created_at) = MONTH(CURRENT_DATE())
          AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $stmtMonth->execute([$user_id]);
    $thisMonth = $stmtMonth->fetch(PDO::FETCH_ASSOC)['this_month'] ?? 0;

} catch (PDOException $e) {
    $totalCases = 0;
    $thisMonth = 0;
    error_log("Dashboard query error: " . $e->getMessage());
}
?>
                            
                            <div class="card stat-card p-4">
                                <div class="d-flex justify-content-between w-100">
                                    <div class="card-content">
                                        <h6>Total Cases</h6>
                                        <div class="number"><?php echo $totalCases; ?></div>
                                        <?php if ($thisMonth > 0): ?>
                                            <div class="trend up text-success"><i class="fas fa-arrow-up"></i> <?php echo $thisMonth; ?> this month</div>
                                        <?php else: ?>
                                            <div class="trend text-muted">0 new this month</div>
                                        <?php endif; ?>
                                    </div>
                                    <i class="fas fa-briefcase fs-1 text-primary opacity-75"></i>
                                </div>
                            </div>
                        </div>
<div class="metric-card" style="background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); min-width: 220px; flex: 1; font-family: sans-serif;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <p style="color: #6c757d; font-size: 14px; margin: 0 0 10px 0; font-weight: 500;">
                Upcoming Deadlines
            </p>
            
            <h2 style="font-size: 36px; margin: 0; font-weight: 700; color: #dc3545;">
                <?= htmlspecialchars($totalDeadlines); ?>
            </h2>
        </div>
        
        <div style="background: #fff0f1; padding: 8px; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </div>
    </div>
    
    <div style="margin-top: 15px;">
        <span style="color: #dc3545; font-size: 13px; font-weight: 600;">
            <?= htmlspecialchars($dueThisWeek); ?> due this week
        </span>
    </div>
</div>

                        <div class="metric-card" style="background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); min-width: 220px; flex: 1; font-family: sans-serif;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <p style="color: #6c757d; font-size: 14px; margin: 0 0 10px 0; font-weight: 500;">
                Active Matters
            </p>
            
            <h2 style="font-size: 36px; margin: 0; font-weight: 700; color: #212529;">
                <?= htmlspecialchars($activeMattersCount); ?>
            </h2>
        </div>
        
        <div style="background: #e8f5e9; padding: 8px; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#198754" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            </svg>
        </div>
    </div>
    
    <div style="margin-top: 15px;">
        <span style="color: <?= $solColor; ?>; font-size: 13px; font-weight: 600;">
            <?= htmlspecialchars($solSubtext); ?>
        </span>
    </div>
</div>

                                  <!-- This Month's Hearings Card -->
<div class="metric-card" style="background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); min-width: 220px; flex: 1; font-family: sans-serif;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <!-- Card Label Header -->
            <p style="color: #6c757d; font-size: 14px; margin: 0 0 10px 0; font-weight: 500;">
                This Month's Hearings
            </p>
            
            <!-- Dynamic Count of All Hearings for Current Month -->
            <h2 style="font-size: 36px; margin: 0; font-weight: 700; color: #212529;">
                <?= htmlspecialchars($monthHearingsCount); ?>
            </h2>
        </div>
        
        <!-- UI Blue Calendar Checked Box Icon -->
        <div style="background: #e1f5fe; padding: 8px; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0288d1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
                <polyline points="9 16 11 18 15 14"></polyline>
            </svg>
        </div>
    </div>
    
    <!-- Dynamic Next Hearing Date Status Footer -->
    <div style="margin-top: 15px;">
        <span style="color: #0288d1; font-size: 13px; font-weight: 600;">
            <?= htmlspecialchars($nextHearingFormatted); ?>
        </span>
    </div>
</div>
<div class="events-card" style="background: #ffffff; padding: 24px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; border: 1px solid #f0f2f5;">
    
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#495057" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #343a40;">Upcoming Events</h3>
    </div>

    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php if (!empty($upcomingEvents)): ?>
            <?php foreach ($upcomingEvents as $event): ?>
                
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; background: #fafbfc; border-radius: 6px; border: 1px solid #f1f3f5; gap: 15px;">
                    
                    <div style="flex: 0 0 120px; min-width: 120px;">
                        <?php if ($event['type'] === 'Hearing'): ?>
                            <span style="display: inline-block; background: #e1f5fe; color: #0288d1; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;">
                                Hearing
                            </span>
                        <?php else: ?>
                            <span style="display: inline-block; background: #fff3e0; color: #f57c00; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;">
                                SOL Expiry
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div style="flex: 2; min-width: 200px;">
                        <h4 style="margin: 0 0 3px 0; font-size: 14px; font-weight: 600; color: #212529;">
                            <?= htmlspecialchars($event['title']); ?>
                        </h4>
                        <p style="margin: 0; font-size: 12px; color: #6c757d; font-weight: 400; line-height: 1.4;">
                            <?= $event['meta']; ?>
                        </p>
                    </div>
                    
                    <div style="flex: 1; text-align: right; min-width: 150px;">
                        <span style="font-size: 13px; font-weight: 600; color: <?= ($event['type'] === 'Hearing') ? '#495057' : '#e65100'; ?>;">
                            <?= htmlspecialchars($event['date_string']); ?>
                        </span>
                    </div>

                </div>

            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 30px 0; color: #adb5bd; font-size: 13px;">
                No scheduled hearings or deadlines on record.
            </div>
        <?php endif; ?>
    </div>
</div>

                        <div class="col-lg-7 mb-4">
                            <?php
                            try {
                                $pdoTable = $pdo;

                                // Get logged-in user ID
                                $user_id = $_SESSION['user_id'] ?? 0;

                                $query = "SELECT case_no, client, status, next_date, sol_status FROM cases 
                                          WHERE user_id = ? ORDER BY id DESC LIMIT 3";
                                $stmtTable = $pdoTable->prepare($query);
                                $stmtTable->execute([$user_id]);
                                $recent_cases = $stmtTable->fetchAll();

                            } catch (PDOException $e) {
                                $recent_cases = [];
                                error_log("Database error: " . $e->getMessage());
                            }
                            ?>
                        <!-- Recent Cases Card -->
<div class="metric-card">
    <?php
    try {
        $user_id = $_SESSION['user_id'] ?? 0;
        $stmt = $pdo->prepare("
            SELECT 
                case_no,
                title,
                client_name,
                status,
                next_hearing_date,
                sol_expiry_date
            FROM cases 
            WHERE user_id = ?
            ORDER BY id DESC 
            LIMIT 3
        ");
        $stmt->execute([$user_id]);
        $recent_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $recent_cases = [];
        error_log("Recent Cases Error: " . $e->getMessage());
    }
    ?>
<!-- Recent Cases - Full Width Matching Other Panels -->
<div class="col-12 mb-4">
    <?php
    try {
        $user_id = $_SESSION['user_id'] ?? 0;
        $stmt = $pdo->prepare("
            SELECT 
                case_no,
                client_name,
                status,
                next_hearing_date
            FROM cases 
            WHERE user_id = ?
            ORDER BY id DESC 
            LIMIT 3
        ");
        $stmt->execute([$user_id]);
        $recent_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $recent_cases = [];
    }
    ?>

    <div class="col-12 mb-4">

        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-folder-open me-2"></i> Recent Cases
            </h5>
            <a href="cases.php" class="btn btn-primary btn-sm">View All</a>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Case No</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Next Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_cases)): ?>
                            <?php foreach ($recent_cases as $case): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($case['case_no'] ?? '-') ?></strong></td>
                                    <td><?= htmlspecialchars($case['client_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php
                                        $status = $case['status'] ?? 'Unknown';
                                        $class = match(strtolower($status)) {
                                            'active' => 'bg-success',
                                            'pending' => 'bg-warning text-dark',
                                            'hearing' => 'bg-info',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $class ?>"><?= htmlspecialchars($status) ?></span>
                                    </td>
                                    <td>
                                        <?= !empty($case['next_hearing_date']) 
                                            ? date('d M Y', strtotime($case['next_hearing_date'])) 
                                            : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No recent cases found in database logs.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

                    <!-- Quick Actions Panel -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h6 class="mb-0">Quick Actions</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex flex-wrap gap-3">
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCaseModal">
                                            <i class="fas fa-plus me-1"></i> New Case
                                        </button>
                                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCourtDateModal">
                                            <i class="fas fa-calendar-plus me-1"></i> Add Court Date
                                        </button>
                                        <button class="btn btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#setReminderModal">
                                            <i class="fas fa-bell me-1"></i> Set Reminder
                                        </button>
                                        <button class="btn btn-info text-white" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                            <i class="fas fa-file-upload me-1"></i> Upload Document
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> 
            </div> 
        </div> 
    </div> 

    <!-- MODAL WINDOWS -->
    <!-- Modal: New Case -->
    <div class="modal fade" id="newCaseModal" tabindex="-1" aria-labelledby="newCaseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newCaseModalLabel">Add New Legal Case</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addCaseForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="case_no" class="form-label">Case Number</label>
                            <input type="text" class="form-control" id="case_no" name="case_number" placeholder="e.g., C245/2025" required>
                        </div>
                        <div class="mb-3">
                            <label for="client" class="form-label">Client Name</label>
                            <input type="text" class="form-control" id="client" name="client" placeholder="e.g., Kenya Power Ltd" required>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Hearing">Hearing</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="next_date" class="form-label">Next Date</label>
                            <input type="date" class="form-control" id="next_date" name="next_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="sol_status" class="form-label">SOL Status</label>
                            <select class="form-select" id="sol_status" name="sol_status" required>
                                <option value="Safe">Safe</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Case</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Add Court Date -->
    <div class="modal fade" id="addCourtDateModal" tabindex="-1" aria-labelledby="addCourtDateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCourtDateModalLabel">Schedule New Court Date</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addCourtDateForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="event_title" class="form-label">Event Type / Title</label>
                            <input type="text" class="form-control" id="event_title" name="event_title" placeholder="e.g., Hearing - Civil Suit No. 245/2025" required>
                        </div>
                        <div class="mb-3">
                            <label for="court_location" class="form-label">Court / Location</label>
                            <input type="text" class="form-control" id="court_location" name="court_location" placeholder="e.g., High Court, Nairobi" required>
                        </div>
                        <div class="mb-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="event_date" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="event_date" name="event_date" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="event_time" class="form-label">Time</label>
                                    <input type="time" class="form-control" id="event_time" name="event_time" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Schedule Date</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Set Reminder -->
    <div class="modal fade" id="setReminderModal" tabindex="-1" aria-labelledby="setReminderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="setReminderModalLabel">Set New Reminder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="setReminderForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="reminder_text" class="form-label">Reminder Message / Task</label>
                            <input type="text" class="form-control" id="reminder_text" name="reminder_text" placeholder="e.g., File defense for Case No. L89/2024" required>
                        </div>
                        <div class="mb-3">
                            <label for="reminder_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="reminder_date" name="reminder_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="priority" class="form-label">Priority Level</label>
                            <select class="form-select" id="priority" name="priority" required>
                                <option value="Normal">Normal</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning text-dark">Save Reminder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Upload Document -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-dark" id="uploadDocumentModalLabel">Upload Case Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="uploadDocumentForm" enctype="multipart/form-data">
                    <div class="modal-body text-dark">
                        <div class="mb-3">
                            <label for="document_title" class="form-label">Document Title / Description</label>
                            <input type="text" class="form-control" id="document_title" name="document_title" placeholder="e.g., Affidavits of Service" required>
                        </div>
                        <div class="mb-3">
                            <label for="associated_case" class="form-label">Associated Case Number (Optional)</label>
                            <input type="text" class="form-control" id="associated_case" name="associated_case" placeholder="e.g., C245/2025">
                        </div>
                        <div class="mb-3">
                            <label for="uploaded_file" class="form-label">Select File (PDF, DOCX, JPG, PNG)</label>
                            <input type="file" class="form-control" id="uploaded_file" name="uploaded_file" required>
                            <div class="form-text">Maximum file size: 5MB.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info text-white">Upload File</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>

// Add this JavaScript at the bottom of your dashboard.php
document.getElementById('markAsReadBtn').addEventListener('click', function(e) {
    e.preventDefault();
    
    fetch('mark_notifications_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close the dropdown
            const dropdown = document.querySelector('.dropdown-menu');
            if (dropdown) dropdown.classList.remove('show');
            
            // Refresh the page
            window.location.reload();
        }
    })
    .catch(err => {
        console.error('Error:', err);
        window.location.reload(); // Fallback: still refresh
    });
});

// ====================== UNIVERSAL FORM HANDLER ======================
function handleFormSubmission(formId, endpoint, successMessage) {
    const form = document.getElementById(formId);
    if (!form) {
        console.warn(`Form with id "${formId}" not found.`);
        return;
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;

        // Disable button + loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            Processing...
        `;

        fetch(endpoint, {
            method: 'POST',
            body: new FormData(form)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(successMessage || 'Action completed successfully!');
                // Close the modal
                const modal = bootstrap.Modal.getInstance(form.closest('.modal'));
                if (modal) modal.hide();
                
                // Optional: reload page or refresh specific section
                setTimeout(() => {
                    location.reload();
                }, 800);
            } else {
                alert('Error: ' + (data.message || 'Something went wrong.'));
            }
        })
        .catch(error => {
            console.error('Submission error:', error);
            alert('Network or server error occurred. Please try again.');
        })
        .finally(() => {
            // Reset button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
}

// ====================== INITIALIZE ALL FORMS ======================

document.addEventListener('DOMContentLoaded', function() {
    
    // Add Court Date Modal
    handleFormSubmission(
        'addCourtDateForm', 
        'add_court_date.php', 
        'Court date scheduled successfully!'
    );

    // Set Reminder Modal
    handleFormSubmission(
        'setReminderForm', 
        'add_reminder.php', 
        'Reminder set successfully!'
    );

    // Upload Document Modal
    handleFormSubmission(
        'uploadDocumentForm', 
        'upload_document.php', 
        'Document uploaded successfully!'
    );

    // Add Case Form (if it exists on the page)
    handleFormSubmission(
        'addCaseForm', 
        'add_case.php', 
        'Case added successfully!'
    );

});async function loadNotifications() {
    try {
        const res = await fetch('api/notifications.php');
        const data = await res.json();

        document.getElementById('notifCount').textContent = data.count || 0;

        const list = document.getElementById('notificationsList');
        list.innerHTML = '';

        if (data.count === 0) {
            list.innerHTML = `<div class="text-center text-muted py-4">No new notifications</div>`;
            return;
        }

        data.notifications.forEach(n => {
            const div = document.createElement('div');
            div.className = "notification-item p-3 border-bottom";
            div.style.cursor = "pointer";
            div.innerHTML = `
                <div class="d-flex">
                    <div class="me-3 fs-4">${getIcon(n.type)}</div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">${n.title}</div>
                        <small class="text-muted">${n.message}</small>
                        <br><small class="text-primary">${n.reminder_date || n.sol_expiry_date || n.created_at || ''}</small>
                    </div>
                </div>
            `;
            div.onclick = () => {
                if (n.link && n.link !== '#') {
                    window.location.href = n.link;
                }
                loadNotifications();
            };
            list.appendChild(div);
        });
    } catch (e) {
        console.error("Notification error:", e);
    }
}

function getIcon(type) {
    if (type === 'sol') return '⚠️';
    if (type === 'reminder') return '🕒';
    if (type === 'discussion') return '💬';
    return '📌';
}

function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    if (dropdown.style.display === 'block') loadNotifications();
}

function markAllRead() {
    if (confirm("Mark all notifications as read?")) {
        // You can later add a backend endpoint for this
        loadNotifications();
    }
}

// Auto refresh
document.addEventListener('DOMContentLoaded', () => {
    loadNotifications();
    setInterval(loadNotifications, 30000);
});
</script>
</body>
</html>