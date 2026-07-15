<?php
// Include database connection
require_once 'connect.php';

// Useful for debugging, but disable these in a live production environment!
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get form data and sanitize
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';

    // Basic validation
    if (empty($firstname) || empty($lastname) || empty($email) || empty($password)) {
        die("❌ All required fields must be filled!");
    }

    // 1. FIXED LOGIC: Check if email already exists
    // Never check if a password exists in the database to see if a user is a duplicate!
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        die("❌ An account with this email already exists!");
    }
    $check->close();

    // Hash password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 2. FIXED SYNTAX: Removed trailing commas and matched placeholders
    // Ensure the column names in ( ... ) match your database table exactly
    $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, email, password) VALUES (?, ?, ?, ?)");
    
    // "ssss" means 4 strings
    $stmt->bind_param("ssss", $firstname, $lastname, $email, $hashed_password);

    if ($stmt->execute()) {
        $stmt->close();
        // Success - Redirect to login page
        header("Location: login.php?registered=1");
        exit();
    } else {
        die("❌ Registration failed: " . $stmt->error);
    }

} else {
    header("Location: register.php");
    exit();
}
?>