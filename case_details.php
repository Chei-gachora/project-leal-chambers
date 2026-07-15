<?php
include 'connect.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("❌ Invalid Case ID");
}

$id = (int)$_GET['id'];

// Fetch full case details
$stmt = $conn->prepare("
    SELECT * FROM cases 
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$case = $result->fetch_assoc();

if (!$case) {
    die("❌ Case not found");
}

// Calculate days remaining
$days_remaining = 'N/A';
if (!empty($case['sol_expiry_date'])) {
    $today = new DateTime();
    $expiry = new DateTime($case['sol_expiry_date']);
    $diff = $today->diff($expiry);
    $days_remaining = (int)$diff->format('%r%a');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Details | <?= htmlspecialchars($case['case_no']) ?> - LegalPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #0f172a; color: #e2e8f0; }
        .glass { background: rgba(30, 41, 55, 0.85); backdrop-filter: blur(12px); border-radius: 16px; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="glass p-5">
        <a href="soltracker.php" class="btn btn-outline-light mb-4">
            ← Back to SOL Tracker
        </a>

        <h2>Case Details: <?= htmlspecialchars($case['case_no']) ?></h2>
        <hr>

        <div class="row">
            <div class="col-md-6">
                <h5 class="text-info">Basic Information</h5>
                <table class="table table-dark">
                    <tr><th>Client</th><td><?= htmlspecialchars($case['client_name']) ?></td></tr>
                    <tr><th>Case Type</th><td><?= htmlspecialchars($case['case_type']) ?></td></tr>
                    <tr><th>Court</th><td><?= htmlspecialchars($case['court'] ?? 'N/A') ?></td></tr>
                    <tr><th>Status</th><td><span class="badge bg-<?= $case['status'] == 'Active' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($case['status']) ?></span></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h5 class="text-info">SOL Information</h5>
                <table class="table table-dark">
                    <tr><th>Accrual Date</th><td><?= htmlspecialchars($case['created_at'] ?? 'N/A') ?></td></tr>
                    <tr><th>Expiry Date</th><td><?= htmlspecialchars($case['sol_expiry_date'] ?? 'N/A') ?></td></tr>
                    <tr><th>Days Remaining</th>
                        <td>
                            <?php if ($days_remaining <= 0): ?>
                                <strong class="text-danger">Expired</strong>
                            <?php else: ?>
                                <strong class="text-warning"><?= $days_remaining ?> days</strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="mt-4">
            <h5 class="text-info">Matter / Description</h5>
            <div class="glass p-4">
                <?= nl2br(htmlspecialchars($case['matter'] ?? $case['description'] ?? 'No description available')) ?>
            </div>
        </div>

        <div class="mt-4">
            <a href="soltracker.php" class="btn btn-outline-light me-2">Back to List</a>
            <a href="#" class="btn btn-warning">Edit Case</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>