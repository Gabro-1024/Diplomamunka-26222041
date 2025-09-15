<?php
session_start();
require_once __DIR__ . '/includes/auth_check.php';

// Clear all session variables
$_SESSION = [];

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear the remember me cookie if it exists
if (isset($_COOKIE['session_token'])) {
    setcookie('session_token', '', time() - 3600, '/', '', true, true);
}

// Redirect to home page
header('Location: http://localhost:63342/Diplomamunka-26222041/php/index.php');
exit();
