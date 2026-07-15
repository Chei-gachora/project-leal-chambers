<?php
// add_case.php
session_start();

$host = 'localhost';
$dbname = 'lawyers';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $case_no = trim($_POST['case_no'] ?? $_POST['case_number'] ?? '');
    $client_name = trim($_POST['client_name'] ?? $_POST['client'] ?? '');
    $matter = trim($_POST['matter'] ?? $_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $next_hearing_date = trim($_POST['next_hearing_date'] ?? $_POST['next_date'] ?? null);

    // Get logged-in user ID
    $user_id = $_SESSION['user_id'] ?? null;

    if (!empty($case_no) && !empty($client_name) && $user_id !== null) {
        try {
            $stmt = $pdo->prepare("INSERT INTO cases (user_id, case_no, client_name, matter, status, next_hearing_date, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $case_no, $client_name, $matter, $status, $next_hearing_date]);
            echo json_encode(['success' => true, 'message' => 'Case added successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Case Number, Client Name are required and you must be logged in.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>