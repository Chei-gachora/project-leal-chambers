<?php
include 'connect.php';

// Get case ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("<div class='alert alert-danger'>❌ Invalid Case ID</div>");
}

// Fetch case details
$sql = "SELECT * FROM cases WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$case = $result->fetch_assoc();

if (!$case) {
    die("<div class='alert alert-danger'>❌ Case not found!</div>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case #<?php echo htmlspecialchars($case['case_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .case-header { background: linear-gradient(135deg, #0d6efd, #0b5ed7); color: white; }
        .info-label { font-weight: 600; color: #495057; }
        .null-value { color: #6c757d; font-style: italic; }
    </style>
</head>
<body>

<div class="container mt-4">
    <div class="card shadow">
        <div class="case-header card-header">
            <h3 class="mb-0">
                Case <?php echo htmlspecialchars($case['case_no']); ?> 
                <small>- <?php echo htmlspecialchars($case['title'] ?? 'No Title'); ?></small>
            </h3>
        </div>
        
        <div class="card-body">
            <div class="row g-4">

                <div class="col-md-6">
                    <strong class="info-label">Client Name:</strong><br>
                    <?php echo !empty($case['client_name']) ? htmlspecialchars($case['client_name']) : '<span class="null-value">Not Provided</span>'; ?>
                </div>

                <div class="col-md-6">
                    <strong class="info-label">Status:</strong><br>
                    <?php 
                    $status = $case['status'] ?? 'Unknown';
                    $badge = $status == 'Active' ? 'success' : ($status == 'Pending' ? 'warning' : 'secondary');
                    echo "<span class='badge bg-$badge'>$status</span>";
                    ?>
                </div>

                <div class="col-md-6">
                    <strong class="info-label">Matter:</strong><br>
                    <?php echo !empty($case['matter']) ? htmlspecialchars($case['matter']) : '<span class="null-value">N/A</span>'; ?>
                </div>

                <div class="col-md-6">
                    <strong class="info-label">Case Type:</strong><br>
                    <?php echo !empty($case['case_type']) ? htmlspecialchars($case['case_type']) : '<span class="null-value">N/A</span>'; ?>
                </div>

                <div class="col-md-6">
                    <strong class="info-label">Court:</strong><br>
                    <?php echo !empty($case['court']) ? htmlspecialchars($case['court']) : '<span class="null-value">N/A</span>'; ?>
                </div>

                <div class="col-md-6">
                    <strong class="info-label">Next Hearing Date:</strong><br>
                    <?php echo !empty($case['next_hearing_date']) ? htmlspecialchars($case['next_hearing_date']) : '<span class="null-value">Not Scheduled</span>'; ?>
                </div>

                <div class="col-md-6">
                    <strong class="info-label">SOL Expiry Date:</strong><br>
                    <?php echo !empty($case['sol_expiry_date']) ? htmlspecialchars($case['sol_expiry_date']) : '<span class="null-value">Not Set</span>'; ?>
                </div>

                <div class="col-md-6">
                    <strong class="info-label">Assigned Lawyer ID:</strong><br>
                    <?php echo !empty($case['assigned_lawyer_id']) ? htmlspecialchars($case['assigned_lawyer_id']) : '<span class="null-value">Not Assigned</span>'; ?>
                </div>

                <div class="col-12">
                    <strong class="info-label">Description:</strong><br>
                    <div class="p-3 border rounded bg-light">
                        <?php echo !empty($case['description']) ? nl2br(htmlspecialchars($case['description'])) : '<span class="null-value">No description provided.</span>'; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <strong class="info-label">Created At:</strong><br>
                    <?php echo $case['created_at']; ?>
                </div>
                <div class="col-md-6">
                    <strong class="info-label">Last Updated:</strong><br>
                    <?php echo $case['updated_at']; ?>
                </div>

            </div>
        </div>

        <div class="card-footer text-end">
            <a href="cases.php" class="btn btn-secondary">← Back to All Cases</a>
            <a href="edit_case.php?id=<?php echo $case['id']; ?>" class="btn btn-warning">Edit Case</a>
        </div>
    </div>
</div>

</body>
</html>