<?php
/**
 * Database Connection - Hello Wakili Project
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database Configuration
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: ''; // Your local password
$dbname = getenv('DB_NAME') ?: 'legalpro';

$conn = new mysqli($host, $user, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// DO NOT echo anything here or add redirects in connect.php
// This file should ONLY establish the connection.
?>