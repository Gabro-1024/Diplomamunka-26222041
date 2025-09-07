<?php
session_start();

// Function to check if user is logged in
function isUserLoggedIn() {
    // Check if session exists and user is logged in
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }
    
    // Check for remember me cookie
    if (isset($_COOKIE['session_token'])) {
        require_once __DIR__ . '/../db_connect.php';
        
        try {
            $pdo = db_connect();
            $token = $_COOKIE['session_token'];
            $currentTime = date('Y-m-d H:i:s');
            
            // Check if token exists and is not expired
            $stmt = $pdo->prepare('SELECT user_id FROM login_sessions WHERE session_token = ? AND expires_at > ?');
            $stmt->execute([$token, $currentTime]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($session) {
                // Get user data and set session
                $stmt = $pdo->prepare('SELECT id, email, role FROM users WHERE id = ?');
                $stmt->execute([$session['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    return true;
                }
            }
        } catch (Exception $e) {
            error_log('Auth check error: ' . $e->getMessage());
        }
    }
    
    return false;
}

// Redirect to home if user is already logged in
function redirectIfLoggedIn($redirectTo = 'index.php') {
    if (isUserLoggedIn()) {
        header('Location: ' . $redirectTo);
        exit();
    }
}
?>
