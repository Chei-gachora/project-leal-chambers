<?php
include 'connect.php';

// Get case ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("<div class='alert alert-danger'>❌ Invalid Case ID</div>");
}

// Fetch current case data
$sql = "SELECT * FROM cases WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$case = $result->fetch_assoc();

if (!$case) {
    die("<div class='alert alert-danger'>❌ Case not found!</div>");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $case_no          = $_POST['case_no'] ?? '';
    $title            = $_POST['title'] ?? '';
    $client_name      = $_POST['client_name'] ?? '';
    $matter           = $_POST['matter'] ?? '';
    $case_type        = $_POST['case_type'] ?? '';
    $court            = $_POST['court'] ?? '';
    $status           = $_POST['status'] ?? 'Active';
    $next_hearing_date = $_POST['next_hearing_date'] ?? null;
    $sol_expiry_date   = $_POST['sol_expiry_date'] ?? null;
    $description      = $_POST['description'] ?? '';
    $assigned_lawyer_id = $_POST['assigned_lawyer_id'] ?? null;

    $update_sql = "UPDATE cases SET 
        case_no = ?, 
        title = ?, 
        client_name = ?, 
        matter = ?, 
        case_type = ?, 
        court = ?, 
        status = ?, 
        next_hearing_date = ?, 
        sol_expiry_date = ?, 
        description = ?, 
        assigned_lawyer_id = ?,
        updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?";

    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssssssssssi", 
        $case_no, $title, $client_name, $matter, $case_type, 
        $court, $status, $next_hearing_date, $sol_expiry_date, 
        $description, $assigned_lawyer_id, $id);

    if ($stmt->execute()) {
        echo "<div class='alert alert-success'>✅ Case updated successfully!</div>";
        // Refresh data
        $result = $conn->query("SELECT * FROM cases WHERE id = $id");
        $case = $result->fetch_assoc();
    } else {
        echo "<div class='alert alert-danger'>❌ Error updating case.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Case #<?php echo htmlspecialchars($case['case_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .form-control { margin-bottom: 15px; }
    </style>
</head>
<body class="p-4">

<div class="container">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4>Edit Case - <?php echo htmlspecialchars($case['case_no']); ?></h4>
        </div>
        <div class="card-body">

            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Case Number</label>
                        <input type="text" name="case_no" class="form-control" 
                               value="<?php echo htmlspecialchars($case['case_no'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" 
                               value="<?php echo htmlspecialchars($case['title'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Client Name</label>
                        <input type="text" name="client_name" class="form-control" 
                               value="<?php echo htmlspecialchars($case['client_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="Active" <?= $case['status']=='Active'?'selected':'' ?>>Active</option>
                            <option value="Pending" <?= $case['status']=='Pending'?'selected':'' ?>>Pending</option>
                            <option value="Closed" <?= $case['status']=='Closed'?'selected':'' ?>>Closed</option>
                            <option value="Appealed" <?= $case['status']=='Appealed'?'selected':'' ?>>Appealed</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Matter</label>
                        <input type="text" name="matter" class="form-control" 
                               value="<?php echo htmlspecialchars($case['matter'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Case Type</label>
                        <input type="text" name="case_type" class="form-control" 
                               value="<?php echo htmlspecialchars($case['case_type'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Court</label>
                        <input type="text" name="court" class="form-control" 
                               value="<?php echo htmlspecialchars($case['court'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Next Hearing Date</label>
                        <input type="date" name="next_hearing_date" class="form-control" 
                               value="<?php echo $case['next_hearing_date']; ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">SOL Expiry Date</label>
                        <input type="date" name="sol_expiry_date" class="form-control" 
                               value="<?php echo $case['sol_expiry_date']; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Assigned Lawyer ID</label>
                        <input type="number" name="assigned_lawyer_id" class="form-control" 
                               value="<?php echo $case['assigned_lawyer_id']; ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="5"><?php 
                            echo htmlspecialchars($case['description'] ?? ''); 
                        ?></textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="cases.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-success">Save Changes</button>
                </div>
            </form>

        </div>
    </div>
</div>

</body>
</html>