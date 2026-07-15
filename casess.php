<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$_SESSION['last_activity'] = time();   // For 5-min auto logout

include 'connect.php';

// Get logged-in user ID
$user_id = $_SESSION['user_id'];

// Handle search and filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query - NOW FILTERED BY USER
$sql = "SELECT * FROM cases WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $sql .= " AND (case_no LIKE ? OR client_name LIKE ? OR matter LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= "sss";
}

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$cases = [];
while ($row = $result->fetch_assoc()) {
    $cases[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cases | LegalPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #0f172a; 
            color: #e2e8f0;
            background-image: linear-gradient(rgba(15,23,42,0.85), rgba(15,23,42,0.85));
        }
        
        .sidebar { 
            background-color: rgba(30, 41, 55, 0.95); 
            backdrop-filter: blur(12px);
            min-height: 100vh; 
        }
        
        .glass {
            background: rgba(30, 41, 55, 0.65) !important;
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .top-bar { 
            background: rgba(30, 41, 55, 0.85); 
            backdrop-filter: blur(12px); 
        }
        
        .nav-link.active { 
            background-color: #3b82f6 !important; 
            color: white !important; 
        }
        
        .table th {
            background-color: rgba(30, 41, 55, 0.8) !important;
            color: #94a3b8;
        }
        
        .badge-active { background-color: #22c55e; }
        .badge-pending { background-color: #eab308; color: #1e2937; }
        .badge-hearing { background-color: #3b82f6; }
        .badge-closed { background-color: #64748b; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar p-4">
            <h4 class="mb-4"><i class="fas fa-gavel"></i> wakiliPro</h4>
            <ul class="nav flex-column">
                <li class="nav-item"><a href="dashboard.php" class="nav-link text-light"><i class="fas fa-home me-2"></i> Dashboard</a></li>
                <li class="nav-item"><a href="calender.php" class="nav-link text-light"><i class="fas fa-calendar-alt me-2"></i> Calendar</a></li>
                <li class="nav-item"><a href="cases.php" class="nav-link active"><i class="fas fa-folder-open me-2"></i> Cases</a></li>
                <li class="nav-item"><a href="soltracker.php" class="nav-link text-light"><i class="fas fa-clock me-2"></i> SOL Tracker</a></li>
                <li class="nav-item"><a href="team.php" class="nav-link text-light"><i class="fas fa-users me-2"></i> Team</a></li>
                <li class="nav-item"><a href="documents.php" class="nav-link text-light"><i class="fas fa-file-alt me-2"></i> Documents</a></li>
                <li class="nav-item"><a href="Reportts.php" class="nav-link text-light"><i class="fas fa-chart-bar me-2"></i> Reports</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 p-0">
            <!-- Top Bar -->
            <div class="top-bar p-3 d-flex justify-content-between align-items-center border-bottom">
                <h5>Case Management</h5>
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCaseModal">
                        <i class="fas fa-plus me-2"></i> New Case
                    </button>

                    
                </div>
            </div>

            <div class="p-4">
                <!-- Search and Filter -->
                <div class="glass p-4 mb-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <form method="GET" class="d-flex">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search cases by number, client or matter..." 
                                       value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="btn btn-primary ms-2"><i class="fas fa-search"></i></button>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <form method="GET">
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Statuses</option>
                                    <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Hearing" <?= $status_filter === 'Hearing' ? 'selected' : '' ?>>Hearing</option>
                                    <option value="Closed" <?= $status_filter === 'Closed' ? 'selected' : '' ?>>Closed</option>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Cases Table -->
                <div class="glass">
                    <div class="p-4 border-bottom d-flex justify-content-between">
                        <h5>All Cases (<?= count($cases) ?>)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Case Number</th>
                                    <th>Client</th>
                                    <th>Matter</th>
                                    <th>Status</th>
                                    <th>Next Date</th>
                                    <th>SOL Status</th>
                                    <th>Actions</th>

                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($cases)): ?>
                                    <?php foreach ($cases as $case): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($case['case_no'] ?? '') ?></strong></td>
                                        <td><?= htmlspecialchars($case['client_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($case['matter'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php 
                                            $status = $case['status'] ?? 'Active';
                                            $badgeClass = match($status) {
                                                'Active' => 'badge-active',
                                                'Pending' => 'badge-pending',
                                                'Hearing' => 'badge-hearing',
                                                'Closed' => 'badge-closed',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($case['next_hearing_date'] ?? '-') ?></td>
                                        <td>
                                            <?php 
                                            $sol = $case['sol_status'] ?? 'Safe';
                                            $solClass = ($sol === 'Critical') ? 'bg-danger' : 'bg-success';
                                            ?>
                                            <span class="badge <?= $solClass ?>"><?= htmlspecialchars($sol) ?></span>
                                        </td>
                                        
                                        
<td>
    <a href="view_case.php?id=<?php echo $case['id']; ?>" 
       class="btn btn-primary btn-sm me-1">View</a>
    
    <a href="edit_case.php?id=<?php echo $case['id']; ?>" 
       class="btn btn-warning btn-sm">Edit</a>
</td>
                                    
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">No cases found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>




<!-- New Case Modal -->
<form id="newCaseForm">
<!-- New Case Modal -->
<div class="modal fade" id="newCaseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass">
            <div class="modal-header">
                <h5 class="modal-title">Add New Case</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newCaseForm">
                    <div class="mb-3">
                        <label>Case Number</label>
                        <input type="text" name="case_no" class="form-control" required placeholder="C245/2025">
                    </div>
                    <div class="mb-3">
                        <label>Client Name</label>
                        <input type="text" name="client_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Matter / Description</label>
                        <textarea name="matter" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Status</label>
                            <select name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Hearing">Hearing</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Next Hearing Date</label>
                            <input type="date" name="next_hearing_date" class="form-control">
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
</div>

  
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('newCaseForm').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('add_case.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                bootstrap.Modal.getInstance(document.getElementById('newCaseModal')).hide();
                location.reload(); // Refresh the cases list
            } else {
                alert(data.message);
            }
        })
        .catch(() => alert('Failed to save case'));
};


</script>
</body>
</html>