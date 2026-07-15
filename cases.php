<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get logged-in user ID
$user_id = $_SESSION['user_id'];

// 1. Include your database connection script
include 'connect.php'; 

// 2. Fetch cases for the logged-in user only
$sql = "SELECT case_no, client_name, status, next_hearing_date, sol_expiry_date 
        FROM cases 
        WHERE user_id = ? 
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Cases - LegalPro</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #0f111a; 
            color: #ffffff;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding-top: 20px;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .right-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .btn {
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            border: 1px solid transparent;
        }
        .back-btn {
            background-color: #1a1c23;
            color: #a0aec0;
            border-color: #2d3748;
        }
        .back-btn:hover {
            background-color: #2d3748;
            color: #fff;
        }
        .add-btn {
            background-color: #2563eb;
            color: #ffffff;
        }
        .add-btn:hover {
            background-color: #1d4ed8;
        }
        .cases-card {
            background-color: #ffffff;
            color: #1a1a1a;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }
        .cases-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 15px;
        }
        .cases-table th {
            color: #718096;
            font-weight: 600;
            padding: 16px 12px;
            border-bottom: 2px solid #edf2f7;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        .cases-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #edf2f7;
            color: #2d3748;
        }
        .cases-table tr:hover {
            background-color: #f7fafc;
        }
        .status-badge {
            background-color: #e6fffa;
            color: #047481;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
        }
        .sol-warning {
            color: #e53e3e;
            font-weight: 600;
        }
        .no-data {
            text-align: center;
            padding: 50px;
            color: #718096;
            font-size: 16px;
        }

        /* --- MODAL WINDOW STYLING --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(4px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-box {
            background-color: #1a1d29;
            color: #ffffff;
            width: 100%;
            max-width: 500px;
            border-radius: 12px;
            border: 1px solid #2d3142;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        }
        .modal-header {
            padding: 16px 24px;
            border-b: 1px solid #2d3142;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #2d3142;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        .close-modal {
            background: none;
            border: none;
            color: #a0aec0;
            font-size: 24px;
            cursor: pointer;
        }
        .close-modal:hover {
            color: #ffffff;
        }
        .modal-body {
            padding: 24px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #a0aec0;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }
        .form-control {
            width: 100%;
            box-sizing: border-box;
            background-color: #242838;
            border: 1px solid #3a3f58;
            border-radius: 6px;
            padding: 10px 12px;
            color: #ffffff;
            font-size: 14px;
            transition: 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #2563eb;
        }
        .form-row {
            display: flex;
            gap: 16px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #2d3142;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .cancel-btn {
            background-color: #242838;
            color: #a0aec0;
            border: 1px solid #3a3f58;
        }
        .cancel-btn:hover {
            background-color: #3a3f58;
            color: #fff;
        }
        .alert-bar {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-size: 14px;
        }
        .alert-success { background-color: #155724; color: #d4edda; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #721c24; color: #f8d7da; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Status Messaging Feedback Alerts -->
    <?php if(isset($_GET['success'])): ?>
        <div class="alert-bar alert-success">✓ <?php echo htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>
    <?php if(isset($_GET['error'])): ?>
        <div class="alert-bar alert-error">❌ Error: <?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>
     
    <div class="header-section">
        <h2>All Database Records</h2>
        <div class="right-actions">
            <!-- Modal triggers for addition -->
            <button class="btn add-btn" onclick="openModal()">+ New Case</button>
            <a href="dashboard.php" class="btn back-btn">← Back to Dashboard</a>
        </div>
    </div>

    <div class="cases-card">
        <table class="cases-table">
            <thead>
                <tr>
                    <th>Case No</th>
                    <th>Client Name</th>
                    <th>Current Status</th>
                    <th>Next Hearing Date</th>
                    <th>Statute of Limitations (SOL)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // 3. Verify execution and rows count
                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td style="font-weight: 600; color: #1a0dab;">' . htmlspecialchars($row['case_no']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['client_name']) . '</td>'; 
                        echo '<td><span class="status-badge">' . htmlspecialchars($row['status']) . '</span></td>';
                        echo '<td>' . (!empty($row['next_hearing_date']) ? htmlspecialchars($row['next_hearing_date']) : 'None Specified') . '</td>'; 
                        echo '<td class="sol-warning">' . (!empty($row['sol_expiry_date']) ? htmlspecialchars($row['sol_expiry_date']) : 'N/A') . '</td>'; 
                        echo '</tr>';
                    }
                } else {
                    echo '<tr>';
                    echo '<td colspan="5" class="no-data">No case files or system records were found in the database.</td>';
                    echo '</tr>';
                }
                
                $conn->close();
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- --- POPUP INTERACTIVE CASE CREATION MODAL --- -->
<div class="modal-overlay" id="caseModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add New Case</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        
        <!-- form endpoints connect to your database processor handler -->
        <form action="add_case.php" method="POST">
            <div class="modal-body">
                
                <div class="form-group">
                    <label>Case Number</label>
                    <input type="text" name="case_no" class="form-control" placeholder="e.g. c306/2026" required>
                </div>

                <div class="form-group">
                    <label>Client Name</label>
                    <input type="text" name="client_name" class="form-control" placeholder="e.g. junior big" required>
                </div>

                <div class="form-group">
                    <label>Matter / Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="e.g. chicken theft" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Pending" selected>Pending</option>
                            <option value="Closed">Closed</option>
                            <option value="Appealed">Appealed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Next Hearing Date</label>
                        <input type="date" name="next_hearing_date" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Statute of Limitations (SOL) Expiry</label>
                    <input type="date" name="sol_expiry_date" class="form-control" required>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn cancel-btn" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn add-btn">Save Case</button>
            </div>
        </form>
    </div>
</div>/*

<script>
    function openModal() {
        document.getElementById('caseModal').style.display = 'flex';
    }
    function closeModal() {
        document.getElementById('caseModal').style.display = 'none';
    }
    
    // Close modal if user clicks outside the inner active dialog box
    window.onclick = function(event) {
        let modal = document.getElementById('caseModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

</body>
</html>