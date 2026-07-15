<?php
// save_discussion.php - Matches your table schema
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
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $user_id = $_SESSION['user_id'] ?? 1;  // fallback

    if (!empty($title) && !empty($message)) {
        try {
            // MODIFIED: Get user's department and save it
            $deptStmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
            $deptStmt->execute([$user_id]);
            $department_id = $deptStmt->fetchColumn();

            // Insert with department_id
            $stmt = $pdo->prepare("INSERT INTO discussions (title, message, created_by, department_id, created_at) 
                                  VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $message, $user_id, $department_id]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Title and message required']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
// ... your existing code that handles saving a reply into the DB ...
//if ($insert_query_successful) {
    
    // Include the helper function script
   // require_once 'send_notification_helper.php';
    
    // Define your notification payload
   // $title = "💬 Reply on: " . $discussion_title;
   // $message = "A new reply was posted: " . substr($reply_text, 0, 100) . "...";
    
    // Fire the email instantly to the logged-in user
   // sendInstantEmailNotification($conn, $_SESSION['user_id'], $title, $message);
//}

?>