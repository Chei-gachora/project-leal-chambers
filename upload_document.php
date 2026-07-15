<?php
header('Content-Type: application/json');

session_start();   // ← Added for user session

$host = 'localhost';
$dbname = 'lawyers';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Connection Failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document_title = trim($_POST['document_title'] ?? '');
    $case_number    = trim($_POST['associated_case'] ?? ''); 

    // Get logged-in user ID
    $user_id = $_SESSION['user_id'] ?? null;

    if ($user_id === null) {
        echo json_encode(['success' => false, 'message' => 'You must be logged in to upload documents.']);
        exit;
    }

    if (empty($document_title)) {
        echo json_encode(['success' => false, 'message' => 'Document title is required.']);
        exit;
    }

    if (!isset($_FILES['uploaded_file']) || $_FILES['uploaded_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File upload failed or no file selected.']);
        exit;
    }

    $file = $_FILES['uploaded_file'];
    
    $max_size = 5 * 1024 * 1024; 
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File size exceeds the maximum 5MB limit.']);
        exit;
    }

    $allowed_extensions = ['pdf', 'docx', 'jpg', 'jpeg', 'png'];
    $file_info = pathinfo($file['name']);
    $file_ext  = strtolower($file_info['extension'] ?? '');

    if (!in_array($file_ext, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, DOCX, JPG, PNG.']);
        exit;
    }

    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $new_file_name = uniqid('doc_', true) . '.' . $file_ext;
    $target_path   = $upload_dir . $new_file_name;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        try {
            $sql = "INSERT INTO documents (user_id, document_title, case_number, file_name, file_path, file_type, file_size) 
                    VALUES (:user_id, :document_title, :case_number, :file_name, :file_path, :file_type, :file_size)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id'        => $user_id,
                ':document_title' => $document_title,
                ':case_number'    => !empty($case_number) ? $case_number : null,
                ':file_name'      => $file['name'], 
                ':file_path'      => $target_path,  
                ':file_type'      => $file['type'],
                ':file_size'      => $file['size']
            ]);

            echo json_encode(['success' => true, 'message' => 'Document successfully uploaded and recorded.']);
        } catch (PDOException $e) {
            if (file_exists($target_path)) {
                unlink($target_path);
            }
            echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not save file to server directory.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>