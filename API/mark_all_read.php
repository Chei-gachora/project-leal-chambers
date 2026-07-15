<?php
session_start();
header('Content-Type: application/json');

include_once '../connect.php';

try {
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    
    if ($user_id > 0) {
        // You can add logic here later to mark specific notifications as read if needed
        // For now, we just simulate clearing
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
?>