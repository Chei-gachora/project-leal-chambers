<?php
// ====================== BEST SECURE SESSION SETUP ======================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

require_once 'connect.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id, firstname FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
            $stmt->execute([$token, $expiry, $email]);

            // TODO: Send email with reset link
            // For now, show the link (In production, send real email)
            $reset_link = "http://localhost/wakili/reset-password.php?token=" . $token;
            
            $success = "Password reset link has been sent to <strong>$email</strong>.<br><br>
                        <small>For development: <a href='$reset_link'>Click here to reset password</a></small>";
        } else {
            $error = "No account found with that email address.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Hello Wakili</title>
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
            padding: 30px;
            border-radius: 10px;
        }
        input[type="email"] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 6px;
            border: none;
        }
        button {
            background: #ffc107;
            color: black;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }
        .forgot-link { color: #ffc107; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Forgot Password</h2>
        <p>Enter your email to receive a reset link.</p>

        <?php if($error): ?>
            <p style="color: red;"><?= $error ?></p>
        <?php endif; ?>

        <?php if($success): ?>
            <p style="color: #90EE90;"><?= $success ?></p>
        <?php else: ?>
            <form method="POST">
                <input type="email" name="email" placeholder="Enter your email" required>
                <button type="submit">Send Reset Link</button>
            </form>
        <?php endif; ?>

        <p><a href="login.php" class="forgot-link">← Back to Login</a></p>
    </div>
</body>
</html>