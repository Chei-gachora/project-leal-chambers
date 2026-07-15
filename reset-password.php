<?php
// ====================== SECURE SESSION SETUP ======================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

$host = 'localhost';
$db   = 'lawyers';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = "Invalid or missing reset token.";
} else {
    // Check if token is valid and not expired
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "This password reset link is invalid or has expired.";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($error)) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
        $stmt->execute([$hashed_password, $token]);

        $success = "Your password has been successfully reset. You can now <a href='login.php'>login</a> with your new password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Hello Wakili</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #1e1e1e;
            color: #ddd;
            text-align: center;
            padding: 50px;
        }
        .container {
            max-width: 420px;
            margin: 0 auto;
            background: #2d2d2d;
            padding: 40px;
            border-radius: 12px;
        }
        input[type="password"] {
            width: 100%;
            padding: 14px;
            margin: 10px 0;
            border-radius: 6px;
            border: none;
            font-size: 16px;
        }
        button {
            background: #ffc107;
            color: black;
            padding: 14px 32px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        .error { color: #ff6b6b; }
        .success { color: #90EE90; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Your Password</h2>

        <?php if($error): ?>
            <p class="error"><?= $error ?></p>
            <p><a href="forgot-password.php" style="color: #ffc107;">Request a new reset link</a></p>
        <?php elseif($success): ?>
            <p class="success"><?= $success ?></p>
        <?php else: ?>
            <form method="POST">
                <input type="password" name="password" placeholder="New Password" required>
                <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                <button type="submit">Reset Password</button>
            </form>
        <?php endif; ?>

        <p style="margin-top: 20px;">
            <a href="login.php" style="color: #ffc107; text-decoration: none;">← Back to Login</a>
        </p>
    </div>
</body>
</html>