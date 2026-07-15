<?php
// fetch_discussions.php - With Delete Functionality
$host = 'localhost';
$db   = 'lawyers';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Start session to get logged-in user
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // AJAX Delete Handler
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_discussion') {
        header('Content-Type: application/json');
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM discussions WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    // Get logged-in user's department
    $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    $department_id = null;
    
    if ($user_id) {
        $userStmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $department_id = $userStmt->fetchColumn();
    }

    // Fetch Discussions - MODIFIED to filter by user's department
    if ($department_id) {
        $stmt = $pdo->prepare("SELECT * FROM discussions 
                               WHERE department_id = ? 
                               ORDER BY created_at DESC");
        $stmt->execute([$department_id]);
    } else {
        $stmt = $pdo->query("SELECT * FROM discussions ORDER BY created_at DESC LIMIT 0");
    }
    $discussions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo '<div class="row g-4 p-2">';
    if (empty($discussions)) {
        echo "<p style='color:#64748b;'>No discussions started yet in your department. Be the first to start one!</p>";
    } else {
        foreach ($discussions as $disc) {
            echo "<div class='discussion-card p-4 glass mb-3 border border-secondary rounded'>";
            echo "<div class='d-flex justify-content-between align-items-start'>";
            echo "<div>";
            echo "<h4>" . htmlspecialchars($disc['title']) . "</h4>";
            echo "<div class='discussion-meta text-muted small'>Posted on: " . $disc['created_at'] . "</div>";
            echo "<p class='mt-2'>" . nl2br(htmlspecialchars($disc['message'])) . "</p>";
            echo "</div>";
            echo "<button class='btn btn-sm btn-outline-danger delete-discussion' data-id='{$disc['id']}'>🗑 Delete</button>";
            echo "</div>";
            echo "</div>";
        }
    }
    echo '</div>';
} catch (PDOException $e) {
    echo "<p class='text-danger'>Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>