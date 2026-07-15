<?php
/**
 * Secure Session Management - LegalPro
 */

if (session_status() === PHP_SESSION_NONE) {
    // Secure Session Settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0);        // Set to 1 when using HTTPS
    ini_set('session.gc_maxlifetime', 300);     // 5 minutes
    ini_set('session.cookie_lifetime', 300);

    session_set_cookie_params([
        'lifetime' => 300,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}

// Regenerate session ID on login (Security)
function regenerateSession() {
    session_regenerate_id(true);
}

// Check inactivity and logout
function checkSessionTimeout() {
    $timeout = 300; // 5 minutes in seconds

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        // Session expired
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
}

// Call this on every page
checkSessionTimeout();
?>