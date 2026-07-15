<?php
// ====================== SECURE SESSION MANAGEMENT ======================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Update activity for auto-logout (5 minutes)
$_SESSION['last_activity'] = time();

/**
 * LegalPro - Reports & Analytics Dashboard
 * File: reports.php
 */

// Include database connection
require_once 'connect.php'; 

// Sanitize the logged-in user's ID for safe SQL usage
$userId = $conn->real_escape_string($_SESSION['user_id']);

// --- 1. DATA EXTRACTION & AGGREGATION QUERIES (USER-SCOPED) ---

// A. Document Metrics - Filtered by current user
$docTotalQ = $conn->query("SELECT COUNT(*) as total FROM documents WHERE user_id = '$userId'");
$totalDocs = $docTotalQ ? $docTotalQ->fetch_assoc()['total'] : 0;

$docLinkedQ = $conn->query("SELECT COUNT(*) as linked FROM documents WHERE user_id = '$userId' AND case_number IS NOT NULL AND case_number != ''");
$linkedDocs = $docLinkedQ ? $docLinkedQ->fetch_assoc()['linked'] : 0;
$generalDocs = max(0, $totalDocs - $linkedDocs);

// B. Case Metrics - Filtered by current user
$casesTotalQ = $conn->query("SELECT COUNT(*) as total FROM cases WHERE user_id = '$userId'");
$totalCases = $casesTotalQ ? $casesTotalQ->fetch_assoc()['total'] : 0;

$casesActiveQ = $conn->query("SELECT COUNT(*) as active FROM cases WHERE user_id = '$userId' AND status = 'Active'");
$activeCases = $casesActiveQ ? $casesActiveQ->fetch_assoc()['active'] : 0;
$closedCases = max(0, $totalCases - $activeCases);

// C. Team Metrics - Filters to show only the logged-in user's active profile status
// (Note: If you have an 'organization_id' or 'firm_id', change this query to match team members in the same firm)
$teamTotalQ = $conn->query("SELECT COUNT(*) as total FROM users WHERE id = '$userId' AND status = 'active'");
$totalTeam = $teamTotalQ ? $teamTotalQ->fetch_assoc()['total'] : 0;

// D. Upcoming Hearings - Filtered by current user's hearings
// (Assumes court_dates table has a user_id, or relates directly to the user's assigned cases)
$currentDateStr = '2026-06-30';
$hearingsQ = $conn->query("SELECT COUNT(*) as total FROM court_dates WHERE user_id = '$userId' AND event_date >= '$currentDateStr'");
$upcomingHearings = $hearingsQ ? $hearingsQ->fetch_assoc()['total'] : 0;

// E. SOL At Risk - next 30 days for current user
$solRiskQ = $conn->query("SELECT COUNT(*) as total FROM cases WHERE user_id = '$userId' AND sol_expiry_date BETWEEN '$currentDateStr' AND DATE_ADD('$currentDateStr', INTERVAL 30 DAY)");
$solAtRisk = $solRiskQ ? $solRiskQ->fetch_assoc()['total'] : 0;

// F. Chart Data 1: Case Status Distribution for current user
$statusDist = ['Active' => 0, 'Pending' => 0, 'Closed' => 0, 'Appealed' => 0];
$statusQ = $conn->query("SELECT status, COUNT(*) as count FROM cases WHERE user_id = '$userId' GROUP BY status");
if ($statusQ) {
    while ($row = $statusQ->fetch_assoc()) {
        if (array_key_exists($row['status'], $statusDist)) {
            $statusDist[$row['status']] = (int)$row['count'];
        }
    }
}

// G. Chart Data 2: Document Types Breakdown for current user
$docTypesDist = [];
$docTypeQ = $conn->query("SELECT file_type, COUNT(*) as count FROM documents WHERE user_id = '$userId' GROUP BY file_type");
if ($docTypeQ) {
    while ($row = $docTypeQ->fetch_assoc()) {
        $type = !empty($row['file_type']) ? $row['file_type'] : 'Unknown';
        $docTypesDist[$type] = (int)$row['count'];
    }
}

// H. Top 10 Active Cases for current user
$activeCasesList = [];
$listQ = $conn->query("SELECT case_no, client_name, status, next_hearing_date, sol_expiry_date FROM cases WHERE user_id = '$userId' ORDER BY next_hearing_date ASC LIMIT 10");
if ($listQ) {
    while ($row = $listQ->fetch_assoc()) {
        $activeCasesList[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LegalPro - System Reports & Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #0f172a;
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
        }
        .sidebar {
            background-color: #1e293b;
        }
        .card {
            background-color: #1e293b;
            border: 1px solid #334155;
        }
        /* Printable clean layout configurations */
        @media print {
            body { background: white; color: black; }
            .no-print, .sidebar { display: none !important; }
            .print-container { width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .card { background: white !important; border: 1px solid #cbd5e1 !important; color: black !important; }
            span, h1, h2, h3, p, td, th { color: black !important; }
        }
    </style>
</head>
<body class="flex min-h-screen">

    <aside class="w-64 sidebar flex flex-col justify-between p-4 hidden md:flex no-print">
        <div>
            <div class="flex items-center gap-3 px-2 py-4 mb-6">
                 <i class="fas fa-gavel"></i>
                <span class="text-xl font-bold tracking-wider text-white">LegalPro</span>
            </div>
            <nav class="space-y-1">
                <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-slate-700 hover:text-white transition">
                    <i class="fa-solid fa-chart-pie w-5"></i> Dashboard
                </a>
                <a href="calendar.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-slate-700 hover:text-white transition">
                    <i class="fa-solid fa-calendar-days w-5"></i> Calendar
                </a>
                <a href="casess.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-slate-700 hover:text-white transition">
                    <i class="fa-solid fa-briefcase w-5"></i> Cases
                </a>
                <a href="sol_tracker.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-slate-700 hover:text-white transition">
                    <i class="fa-solid fa-triangle-exclamation w-5"></i> SOL Tracker
                </a>
                <a href="team.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-slate-700 hover:text-white transition">
                    <i class="fa-solid fa-users w-5"></i> Team
                </a>
                <a href="documents.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-slate-700 hover:text-white transition">
                    <i class="fa-solid fa-file-lines w-5"></i> Documents
                </a>
                <a href="reports.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-blue-600 text-white font-medium shadow-lg shadow-blue-600/20">
                    <i class="fa-solid fa-chart-line w-5"></i> Reports
                </a>
            </nav>
        </div>
        <div class="border-t border-slate-700 pt-4 flex items-center gap-3 px-2">
            <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center font-bold text-white uppercase text-sm">
                <?php echo isset($_SESSION['firstname']) ? substr($_SESSION['firstname'], 0, 1) : 'P'; ?>
            </div>
            <div>
                <p class="text-sm font-semibold text-white leading-none mb-1"><?php echo isset($_SESSION['firstname']) ? $_SESSION['firstname'] . ' ' . $_SESSION['lastname'] : 'Paul Njeri'; ?></p>
                <span class="text-xs text-gray-400">Lawyer / Administrator</span>
            </div>
        </div>
    </aside>

    <main class="flex-1 p-6 md:p-8 overflow-y-auto print-container">
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8 border-b border-slate-800 pb-5 no-print">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-white">System Analytics Report</h1>
                <p class="text-gray-400 text-sm mt-1">Real-time compilation and audit summary of LegalPro parameters.</p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="window.location.reload();" class="px-4 py-2 text-sm font-medium text-gray-300 bg-slate-800 border border-slate-700 rounded-lg hover:bg-slate-700 transition flex items-center gap-2">
                    <i class="fa-solid fa-rotate"></i> Refresh
                </button>
                <button onclick="window.print();" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-500 shadow-lg shadow-blue-600/20 transition flex items-center gap-2">
                    <i class="fa-solid fa-download"></i> Export / Download PDF
                </button>
            </div>
        </div>

        <div class="hidden print:block mb-8 text-center border-b pb-4">
            <h1 class="text-3xl font-bold">LEGALPRO MANAGEMENT SYSTEM - SUMMARY REPORT</h1>
            <p class="text-sm text-gray-600 mt-2">Generated Automatically on: <?php echo date('Y-m-d H:i:s'); ?> | Target State Reference: 2026</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="card p-5 rounded-xl flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Active Cases</span>
                    <h3 class="text-3xl font-bold mt-2 text-white"><?php echo $activeCases; ?></h3>
                    <p class="text-xs text-gray-400 mt-1"><?php echo $closedCases; ?> closed records archive</p>
                </div>
                <div class="p-3 bg-blue-500/10 text-blue-500 rounded-lg">
                    <i class="fa-solid fa-briefcase text-xl"></i>
                </div>
            </div>

            <div class="card p-5 rounded-xl flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Files Managed</span>
                    <h3 class="text-3xl font-bold mt-2 text-white"><?php echo $totalDocs; ?></h3>
                    <p class="text-xs text-gray-400 mt-1"><?php echo $linkedDocs; ?> case-linked, <?php echo $generalDocs; ?> standalone</p>
                </div>
                <div class="p-3 bg-emerald-500/10 text-emerald-500 rounded-lg">
                    <i class="fa-solid fa-file-invoice text-xl"></i>
                </div>
            </div>

            <div class="card p-5 rounded-xl flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Active Legal Staff</span>
                    <h3 class="text-3xl font-bold mt-2 text-white"><?php echo $totalTeam; ?></h3>
                    <p class="text-xs text-emerald-400 mt-1"><i class="fa-solid fa-circle text-[8px] mr-1"></i> System online operational</p>
                </div>
                <div class="p-3 bg-purple-500/10 text-purple-500 rounded-lg">
                    <i class="fa-solid fa-users text-xl"></i>
                </div>
            </div>

            <div class="card p-5 rounded-xl flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">SOL Risks (30 Days)</span>
                    <h3 class="text-3xl font-bold mt-2 <?php echo $solAtRisk > 0 ? 'text-rose-500' : 'text-white'; ?>">
                        <?php echo $solAtRisk; ?>
                    </h3>
                    <p class="text-xs text-gray-400 mt-1">Requires immediate evaluation</p>
                </div>
                <div class="p-3 <?php echo $solAtRisk > 0 ? 'bg-rose-500/10 text-rose-500' : 'bg-amber-500/10 text-amber-500'; ?> rounded-lg">
                    <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="card p-5 rounded-xl">
                <h3 class="text-base font-bold text-white mb-4"><i class="fa-solid fa-chart-pie mr-2 text-blue-500"></i> Case Status Allocation Breakdowns</h3>
                <div class="relative max-h-64 flex justify-center">
                    <canvas id="caseStatusChart" width="250" height="250"></canvas>
                </div>
            </div>

            <div class="card p-5 rounded-xl">
                <h3 class="text-base font-bold text-white mb-4"><i class="fa-solid fa-chart-bar mr-2 text-emerald-500"></i> Uploaded Documents Extensions Matrix</h3>
                <div class="relative max-h-64 flex justify-center">
                    <canvas id="docTypeChart" width="250" height="250"></canvas>
                </div>
            </div>
        </div>

        <div class="card rounded-xl overflow-hidden mb-8">
            <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                <h3 class="text-base font-bold text-white"><i class="fa-solid fa-list-check mr-2 text-amber-500"></i> Priority Tracked Case Indexes</h3>
                <span class="text-xs bg-slate-800 border border-slate-700 px-2.5 py-1 rounded-full text-gray-400">Top 10 Display Limits</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-800/50 border-b border-slate-800 text-xs font-semibold uppercase text-gray-400 tracking-wider">
                            <th class="p-4">Case Reference ID</th>
                            <th class="p-4">Client Destination</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4">Next Scheduled Action Date</th>
                            <th class="p-4">Statute Limitation Expiry</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 text-sm text-gray-300">
                        <?php if(!empty($activeCasesList)): ?>
                            <?php foreach($activeCasesList as $case): ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="p-4 font-mono font-medium text-white"><?php echo htmlspecialchars($case['case_no']); ?></td>
                                    <td class="p-4"><?php echo htmlspecialchars($case['client_name']); ?></td>
                                    <td class="p-4 text-center">
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold <?php echo $case['status'] === 'Active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'; ?>">
                                            <?php echo htmlspecialchars($case['status']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <i class="fa-regular fa-calendar-check mr-2 text-gray-500"></i>
                                        <?php echo !empty($case['next_hearing_date']) ? date('d M Y', strtotime($case['next_hearing_date'])) : '<span class="text-gray-600">Unscheduled</span>'; ?>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-xs bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                            <?php echo !empty($case['sol_expiry_date']) ? date('d M Y', strtotime($case['sol_expiry_date'])) : 'N/A'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-gray-500">No matching system cases available in the database scope matching this condition context.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>


            
            <div class="card p-5 rounded-xl flex flex-col justify-between">
                <div>
                    <h4 class="text-sm font-bold uppercase tracking-wider text-gray-400 mb-2">Automated Audit Disclaimer</h4>
                    <p class="text-xs text-gray-400 leading-relaxed">
                        Data shown here represents compiled system records synchronized across instances directly. Actions regarding Statute of Limitations constraints must be re-verified against physical documents files to mitigate absolute edge risks.
                    </p>
                </div>
                <div class="text-right text-[11px] text-slate-500 mt-4 font-mono">
                    System Node Reference Timestamp Context: 2026/06/30
                </div>
            </div>
        </div>
    </main>

    <script>
        // Inject compiled PHP distributions safely into JavaScript variables
        const caseStatusData = <?php echo json_encode(array_values($statusDist)); ?>;
        const caseStatusLabels = <?php echo json_encode(array_keys($statusDist)); ?>;

        const docTypesData = <?php echo json_encode(array_values($docTypesDist)); ?>;
        const docTypesLabels = <?php echo json_encode(array_keys($docTypesDist)); ?>;

        // Render Case Distribution Graph
        const ctxStatus = document.getElementById('caseStatusChart').getContext('2d');
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: caseStatusLabels,
                datasets: [{
                    data: caseStatusData,
                    backgroundColor: ['#10b981', '#f59e0b', '#64748b', '#ef4444'],
                    borderWidth: 2,
                    borderColor: '#1e293b'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#94a3b8', font: { family: 'Inter', size: 11 } }
                    }
                }
            }
        });

        // Render Document Breakdown Graph
        const ctxDocs = document.getElementById('docTypeChart').getContext('2d');
        new Chart(ctxDocs, {
            type: 'bar',
            data: {
                labels: docTypesLabels.length ? docTypesLabels : ['No Documents Found'],
                datasets: [{
                    label: 'File Volumes',
                    data: docTypesData.length ? docTypesData : [0],
                    backgroundColor: '#3b82f6',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#94a3b8', font: { size: 10 } },
                        grid: { color: '#334155' }
                    },
                    x: {
                        ticks: { color: '#94a3b8', font: { size: 10 } },
                        grid: { display: false }
                    }
                }
            }
        });
    </script>
</body>
</html>