<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $discussion_id = intval($_POST['discussion_id'] ?? 0);
    $reply_text = trim($_POST['reply_message'] ?? '');
    $user_id = $_SESSION['user_id'] ?? 1;

    if ($discussion_id > 0 && !empty($reply_text)) {
        try {
            // Get user's department
            $deptStmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
            $deptStmt->execute([$user_id]);
            $department_id = $deptStmt->fetchColumn() ?? 0;

            $stmt = $pdo->prepare("INSERT INTO discussion_replies 
                (discussion_id, user_id, reply, department_id, created_at) 
                VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$discussion_id, $user_id, $reply_text, $department_id]);
            
            echo json_encode(['success' => true, 'message' => 'Reply submitted!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>