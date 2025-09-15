<?php
// Enable verbose error reporting and log to a dedicated file
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
// Ensure logs directory exists
$__logDir = __DIR__ . '/logs';
if (!is_dir($__logDir)) {
    @mkdir($__logDir, 0777, true);
}
$logFile = $__logDir . '/signinerrors.log';
ini_set('error_log', $logFile);
// Create the log file if it doesn't exist
if (!file_exists($logFile)) {
    file_put_contents($logFile, '');
    chmod($logFile, 0666); // Ensure the file is writable
}

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

error_log(str_repeat('=', 60));
error_log('Login attempt at ' . date('Y-m-d H:i:s'));

// If already logged in, return success
if (isUserLoggedIn()) {
    error_log('Already logged in - short-circuit success');
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'redirect' => 'http://localhost:63342/Diplomamunka-26222041/php/index.php',
        'message' => 'Already logged in',
        'code' => 'already_logged_in'
    ]);
    exit();
}

// Function to generate a secure token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'redirect' => '',
    'code' => ''
];

try {
    // Get and validate input
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['rememberMe']) ? true : false;

    error_log('Input received -> email valid: ' . ($email ? 'yes' : 'no') . ', password provided: ' . (!empty($password) ? 'yes' : 'no') . ', rememberMe: ' . ($rememberMe ? 'yes' : 'no'));

    if (!$email || empty($password)) {
        $response['code'] = 'missing_fields';
        throw new Exception('Please provide both email and password.');
    }

    // Connect to database
    $pdo = db_connect();

    // Get user by email
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log($user ? 'User lookup: found user' : 'User lookup: no user found for ' . $email);
    } catch (Throwable $dbEx) {
        $errorMsg = 'DB error during user lookup: ' . $dbEx->getMessage();
        error_log($errorMsg);
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $errorMsg . "\n", FILE_APPEND);
        $response['code'] = 'auth_error';
        throw new Exception('An error occurred during authentication. Please try again later.');
    }

    // First check if user exists
    if (!$user) {
        error_log('Login attempt - User not found: ' . $email);
        $response['code'] = 'user_not_found';
        throw new Exception('No account found with this email. Please check your email or sign up.');
    }

    // Then check if email is verified
    $isVerified = (int)($user['is_verified'] ?? 0) === 1;
    error_log('Email verified: ' . ($isVerified ? 'yes' : 'no'));
    if (!$isVerified) {
        $response['code'] = 'not_verified';
        throw new Exception('Please verify your email before logging in. Check your inbox.');
    }

    // Finally, verify the password
    $pwdOk = password_verify($password, $user['password_hash'] ?? '');
    error_log('Login attempt - User: ' . $email . ', Password match: ' . ($pwdOk ? 'yes' : 'no'));
    
    if (!$pwdOk) {
        $response['code'] = 'invalid_password';
        throw new Exception('Incorrect password. Please try again.');
    }

    // Generate session token
    $sessionToken = generateToken();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $expiresAt = $rememberMe ? '+30 days' : '+24 hours';
    $expiresAtDateTime = (new DateTime())->modify($expiresAt)->format('Y-m-d H:i:s');

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Create login session
        $stmt = $pdo->prepare('INSERT INTO login_sessions (user_id, user_agent, session_token, expires_at) VALUES (?, ?, ?, ?)');
        $ok = $stmt->execute([
            $user['id'],
            $userAgent,
            $sessionToken,
            $expiresAtDateTime
        ]);
        if (!$ok) {
            $info = $stmt->errorInfo();
            error_log('Failed to insert login session: ' . print_r($info, true));
            $response['code'] = 'session_insert_failed';
            throw new Exception('Could not create login session.');
        }

        // Set session cookie
        $cookieParams = [
            'expires' => strtotime($expiresAt),
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        
        $cookieSet = setcookie('session_token', $sessionToken, $cookieParams);
        error_log('Session cookie set: ' . ($cookieSet ? 'yes' : 'no'));
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'] ?? '';
        $_SESSION['last_activity'] = time();
        error_log('Session variables set for user_id ' . $_SESSION['user_id']);

        $pdo->commit();

        $response['success'] = true;
        $response['redirect'] = 'http://localhost:63342/Diplomamunka-26222041/php/index.php';
        $response['message'] = 'Login successful!';

    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Transaction error: ' . $e->getMessage());
        throw $e;
    }

} catch (Exception $e) {
    $errorMsg = 'Login error: ' . $e->getMessage() . ' [Code: ' . ($response['code'] ?? 'none') . ']';
    error_log($errorMsg);
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $errorMsg . "\n", FILE_APPEND);
    
    // Generic error message for all cases
    $response['message'] = 'An error occurred during sign in. Please try again.';
    if (empty($response['code'])) {
        $response['code'] = 'authentication_error';
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
