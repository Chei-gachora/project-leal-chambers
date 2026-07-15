<?php
// view_profile.php
header('Content-Type: application/json');

require_once 'connect.php';

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

$user_id = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("
        SELECT id, firstname, lastname, email, phone, role, 
               jurisdiction, profile_pic, status, created_at 
        FROM users 
        WHERE id = :id
    ");
    
    $stmt->execute([':id' => $user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($profile) {
        // Full name
        $profile['full_name'] = $profile['firstname'] . ' ' . $profile['lastname'];
        
        echo json_encode([
            'success' => true,
            'profile' => $profile
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Profile not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error fetching profile: ' . $e->getMessage()]);
}
?>