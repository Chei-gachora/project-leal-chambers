<?php
// forum.php
header('Content-Type: application/json');
include_once 'connect.php';

session_start(); // Added for department filtering

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Get logged-in user's department
$user_id = $_SESSION['user_id'] ?? null;
$department_id = null;

if ($user_id) {
    $deptStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
    $deptStmt->bind_param("i", $user_id);
    $deptStmt->execute();
    $result = $deptStmt->get_result();
    $department_id = $result->fetch_assoc()['department_id'] ?? null;
}

// Create New Topic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_topic') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $created_by = intval($_POST['created_by'] ?? 1);

    if (empty($title) || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Title and content are required']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO forum_topics (title, content, created_by, department_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssii", $title, $content, $created_by, $department_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Topic posted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to post topic']);
    }
    exit;
}

// Fetch All Topics - MODIFIED: Only user's department
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($department_id) {
        $sql = "
            SELECT f.*, u.firstname, u.lastname,
                   (SELECT COUNT(*) FROM forum_replies WHERE topic_id = f.id) as reply_count
            FROM forum_topics f
            LEFT JOIN users u ON f.created_by = u.id
            WHERE f.department_id = ?
            ORDER BY f.created_at DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $department_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT 1 LIMIT 0"); // Return empty
    }
    
    $topics = [];
    while ($row = $result->fetch_assoc()) {
        $topics[] = $row;
    }
    echo json_encode($topics);
    exit;
}
?>