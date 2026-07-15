<?php
/**
 * Database Connection - Hello Wakili Project
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database Configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'lawyers';     // Make sure this matches your database name

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("❌ Database Connection Failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// DO NOT echo anything here or add redirects in connect.php
// This file should ONLY establish the connection.
?>
