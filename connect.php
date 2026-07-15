<?php
/**
 * Database Connection - Hello Wakili Project
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database Configuration
$host = getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: 'localhost');
$port = getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: '3306');
$user = getenv('MYSQLUSER') ?: (getenv('DB_USER') ?: 'root');
$password = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASSWORD') ?: ''); // Your local password
$dbname = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: 'lawyers');

// Mysqli Connection
$conn = new mysqli($host, $user, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// PDO Connection
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed (PDO): " . $e->getMessage());
}

// DO NOT echo anything here or add redirects in connect.php
// This file should ONLY establish the connection.
?>