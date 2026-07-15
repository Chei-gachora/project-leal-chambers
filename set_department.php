<?php
// set_department.php
require_once 'connect.php';

session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department_id = (int)($_POST['department_id'] ?? 0);

    if ($department_id > 0) {
        $stmt = $conn->prepare("UPDATE users SET department_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $department_id, $user_id);
        
        if ($stmt->execute()) {
            $success = "Department updated successfully!";
            // Refresh session if needed
            $_SESSION['department_id'] = $department_id;
            header("Location: login.php"); // or dashboard.php
            exit();
        } else {
            $error = "Failed to update department. Please try again.";
        }
        $stmt->close();
    } else {
        $error = "Please select a valid department.";
    }
}

// Fetch all departments for dropdown
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Department - LegalPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #0f172a; color: #e2e8f0; }
        .card { background-color: #1e2937; border: none; }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card p-4 shadow">
                    <h3 class="text-center mb-4">Select Your Department</h3>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <select name="department_id" class="form-select form-select-lg" required>
                                <option value="">-- Select Department --</option>
                                <?php while ($dept = $departments->fetch_assoc()): ?>
                                    <option value="<?= $dept['id'] ?>">
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            Save & Go to Dashboard
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>