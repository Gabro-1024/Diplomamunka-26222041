<?php
// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ensure logs directory exists
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Set error log file
$logFile = $logDir . '/password_reset_errors.log';
ini_set('error_log', $logFile);

// Function to log custom errors
function logError($message, $context = []) {
    $logMessage = sprintf(
        "[%s] %s %s" . PHP_EOL,
        date('Y-m-d H:i:s'),
        $message,
        !empty($context) ? json_encode($context, JSON_PRETTY_PRINT) : ''
    );
    error_log($logMessage, 3, $GLOBALS['logFile']);
}

// Set content type to JSON
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'code' => ''
];

try {
    logError('Password reset request started');
    
    // Check if the request is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $errorMsg = 'Invalid request method: ' . $_SERVER['REQUEST_METHOD'];
        logError($errorMsg, ['ip' => $_SERVER['REMOTE_ADDR']]);
        throw new Exception('Invalid request method');
    }

    // Get and validate email
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    if (!$email) {
        $errorMsg = 'Invalid email format';
        logError($errorMsg, ['email' => $_POST['email'] ?? '']);
        throw new Exception('Please provide a valid email address.');
    }

    // Include Composer's autoloader
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Include database connection and email functions
    require_once __DIR__ . '/includes/db_connect.php';
    require_once __DIR__ . '/includes/send_email.php';



    // Connect to database
    try {
        $pdo = db_connect();
    } catch (Exception $e) {
        logError('Database connection failed', ['error' => $e->getMessage()]);
        throw new Exception('A database error occurred. Please try again later.');
    }

    // Check if email exists in the database
    try {
        $stmt = $pdo->prepare('SELECT id, first_name, email FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        logError('User lookup', ['email' => $email, 'user_found' => !empty($user)]);
    } catch (Exception $e) {
        logError('Database query failed', ['error' => $e->getMessage()]);
        throw new Exception('A database error occurred.');
    }

    // If user exists, proceed with sending reset email
    if ($user) {
        try {
            // Generate a secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour

            // Store the token in the database
            $stmt = $pdo->prepare('INSERT INTO password_resets (email, token, created_at, expires_at) VALUES (?, ?, NOW(), ?)');
            $stmt->execute([$email, $token, $expires]);
            logError('Password reset token generated', ['email' => $email, 'token' => $token]);
        } catch (Exception $e) {
            logError('Failed to store password reset token', [
                'error' => $e->getMessage(),
                'email' => $email
            ]);
            throw new Exception('Failed to process your request. Please try again.');
        }

        // Generate reset link
        $resetLink = 'https' . "://$_SERVER[HTTP_HOST]/Diplomamunka-26222041/php/reset-password.php?token=" . $token;
        
        // Email content
        $subject = 'Password Reset Request - Tickets @ Gábor';
        $htmlContent = "
            <h2>Password Reset Request</h2>
            <p>Hello {$user['first_name']},</p>
            <p>We received a request to reset your password. Click the button below to set a new password:</p>
            <p>
                <a href='$resetLink' style='background: #2210FF; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    Reset Password
                </a>
            </p>
            <p>Or copy and paste this link into your browser:<br>
            <a href='$resetLink'>$resetLink</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you didn't request this, you can safely ignore this email.</p>
        ";
        
        $textContent = "Password Reset Request\n\n" .
                     "Hello {$user['first_name']},\n\n" .
                     "We received a request to reset your password. Please visit the following link to set a new password:\n\n" .
                     "$resetLink\n\n" .
                     "This link will expire in 1 hour.\n\n" .
                     "If you didn't request this, you can safely ignore this email.";
        
        // Include the send_email.php file
        require_once __DIR__ . '/includes/send_email.php';
        
        // Send the email using the existing function
        if (sendPasswordResetEmail($email, $user['first_name'], $token, $resetLink)) {
            logError('Password reset email sent', ['email' => $email]);
        } else {
            logError('Failed to send password reset email', ['email' => $email]);
            throw new Exception('Failed to send reset email. Please try again.');
        }
    } else {
        logError('Password reset requested for non-existent email', ['email' => $email]);
    }

    // Always return success to prevent email enumeration
    $response['success'] = true;
    $response['message'] = 'If an account exists with this email, you will receive a password reset link shortly.';
    logError('Password reset process completed', ['email' => $email, 'success' => true]);
    
} catch (Exception $e) {
    // Log the error with context
    $context = [
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    logError('Password reset error: ' . $e->getMessage(), $context);
    
    // Return generic error message
    $response['success'] = false;
    $response['message'] = 'An error occurred. Please try again later.';
    $response['code'] = 'reset_error';
}

// Return JSON response
echo json_encode($response);
