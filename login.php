<?php
// ====================== BEST SECURE SESSION SETUP ======================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 1800); // 30 minutes
    session_set_cookie_params([
        'lifetime' => 1800,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Destroy any previous session completely on login attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);   // ← Most Important
}

require_once 'connect.php';

$login_error = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $login_error = "Please enter both email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT id, firstname, lastname, email, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // FINAL FRESH SESSION
            session_regenerate_id(true);
            $_SESSION = []; // Clear everything

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['firstname']  = $user['firstname'];
            $_SESSION['lastname']   = $user['lastname'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'] ?? 'lawyer';
            $_SESSION['last_activity'] = time();

            header("Location: dashboard.php");
            exit();
        } else {
            $login_error = "Invalid email or password.";
        }
    }
}

// Messages
if (isset($_GET['logout'])) $success_message = "✅ You have been successfully logged out.";
if (isset($_GET['timeout'])) $login_error = "Session expired. Please login again.";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="login.css">
    <title>Hello Wakili - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.1/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .hero-bg {
            background-image: linear-gradient(rgba(0,0,0,0.75), rgba(0,0,0,0.85)), url('imagelogin.jpg');
            background-size: cover;
            background-position: center;
        }
        .glass {
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .gold-accent { color: #d4af37; }
    </style>
</head>
<body class="bg-zinc-950 text-white min-h-screen flex items-center justify-center hero-bg">
<div class="max-w-7xl mx-auto px-6 py-12 w-full">
    <div class="flex flex-col lg:flex-row items-center gap-16">
        <!-- Left Side -->
        <div class="lg:w-1/2 text-center lg:text-left">
            <div class="flex items-center justify-center lg:justify-start gap-3 mb-8">
                <div class="w-12 h-12 bg-amber-500 rounded-xl flex items-center justify-center text-2xl">⚖️</div>
                <h1 class="text-4xl font-bold tracking-tight">Hello wakili</h1>
            </div>
            <h2 class="text-6xl lg:text-7xl font-semibold leading-tight mb-6">
                Justice.<br>
                <span class="gold-accent">Excellence.</span><br>
                Integrity.
            </h2>
            <p class="text-xl text-gray-300 max-w-md mx-auto lg:mx-0">
                Secure client portal for attorneys and legal professionals
            </p>
        </div>

        <!-- Login Form -->
        <div class="lg:w-1/2 w-full max-w-lg mx-auto">
            <div class="glass rounded-3xl p-10 shadow-2xl">
                <div class="text-center mb-8">
                    <h3 class="text-3xl font-semibold mb-2">Welcome Back</h3>
                    <p class="text-gray-400">Sign in to access your legal dashboard</p>
                </div>

                <?php if ($success_message): ?>
                    <div class="mb-6 p-4 bg-green-500/20 border border-green-500/30 rounded-2xl text-green-400 text-center">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($login_error): ?>
                    <div class="mb-6 p-4 bg-red-500/20 border border-red-500/30 rounded-2xl text-red-400 text-center">
                        <?= htmlspecialchars($login_error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Email Address</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="w-full bg-white/10 border border-white/20 rounded-2xl py-4 px-12 text-white" 
                                   placeholder="paulchege@zetech.ac.ke" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="password" class="w-full bg-white/10 border border-white/20 rounded-2xl py-4 px-12 text-white" 
                                   placeholder="••••••••" required>
                            <button type="button" onclick="togglePassword()" class="absolute right-5 top-1/2 -translate-y-1/2 text-gray-400">
                                <i id="eyeIcon" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-black font-semibold py-4 rounded-2xl text-lg">
                        Sign In Securely
                    </button>
                    
<div style="text-align: center; margin-top: 15px;">
    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
</div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pwd.type === "password") {
        pwd.type = "text"; icon.classList.replace('fa-eye','fa-eye-slash');
    } else {
        pwd.type = "password"; icon.classList.replace('fa-eye-slash','fa-eye');
    }
}
</script>
</body>
</html>