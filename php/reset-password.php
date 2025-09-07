<?php
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

// Function to log errors
function logError($message, $context = []) {
    $logMessage = sprintf(
        "[%s] %s %s" . PHP_EOL,
        date('Y-m-d H:i:s'),
        $message,
        !empty($context) ? json_encode($context, JSON_PRETTY_PRINT) : ''
    );
    error_log($logMessage, 3, $GLOBALS['logFile']);
}

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/db_connect.php';

$error = '';
$success = false;
$token = $_GET['token'] ?? '';
$validToken = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    logError('Password reset attempt', ['token' => substr($token, 0, 10) . '...', 'ip' => $_SERVER['REMOTE_ADDR']]);
    
    try {
        $pdo = db_connect();
        
        // Validate token
        $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0');
        $stmt->execute([$token]);
        $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$resetRequest) {
            logError('Invalid reset token', ['token' => substr($token, 0, 10) . '...', 'ip' => $_SERVER['REMOTE_ADDR']]);
            throw new Exception('Invalid or expired token. Please request a new password reset.');
        }
        
        // Validate password
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters.');
        }
        
        if ($password !== $confirmPassword) {
            throw new Exception('Passwords do not match.');
        }
        
        // Update password and mark token as used
        $pdo->beginTransaction();
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $email = $resetRequest['email'];
            
            // Update user's password
            $updateUser = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
            $updateUser->execute([$hashedPassword, $email]);
            
            // Mark token as used
            $updateToken = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
            $updateToken->execute([$token]);
            
            $pdo->commit();
            $success = true;
            
            logError('Password reset successful', [
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            logError('Password update failed', [
                'error' => $e->getMessage(),
                'email' => $resetRequest['email'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            throw $e;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} else {
    // Validate token on page load
    if ($token) {
        logError('Token validation attempt', [
            'token' => substr($token, 0, 10) . '...',
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
        try {
            $pdo = db_connect();
            $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0');
            $stmt->execute([$token]);
            $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resetRequest) {
                $validToken = true;
                $email = $resetRequest['email'];
                logError('Token validated successfully', [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
            } else {
                $error = 'Invalid or expired token. Please request a new password reset.';
                logError('Invalid token on page load', [
                    'token' => substr($token, 0, 10) . '...',
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again.';
            logError('Token validation error', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 10) . '...',
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
        }
    } else {
        $error = 'No reset token provided.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password - Tickets at Gábor</title>
  <link rel="shortcut icon" type="image/png" href="../assets/images/logos/favicon.svg" />
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
  <div class="page-wrapper overflow-hidden">
    <section class="bg-graphite border-top border-accent-blue border-4 d-flex align-items-center justify-content-center min-vh-100">
      <div class="container py-3">
        <div class="sign-in card mx-auto shadow-lg">
          <div class="card-body py-8 px-lg-5">
            <a href="index.php" class="mb-8 hstack justify-content-center">
              <img src="../assets/images/logos/logo-white.svg" alt="logo" class="img-fluid">
            </a>
            
            <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
              <i class="fas fa-check-circle me-2"></i>
              <div>Your password has been reset successfully. <a href="sign-in.php" class="alert-link">Sign in</a> with your new password.</div>
            </div>
            <?php else: ?>
                <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                  <i class="fas fa-exclamation-circle me-2"></i>
                  <div><?php echo htmlspecialchars($error); ?></div>
                </div>
                <?php endif; ?>

                <?php if ($validToken): ?>
                <h2 class="text-center mb-4 text-graphite">Reset Your Password</h2>
                <form method="POST" class="d-flex flex-column gap-3 needs-validation" novalidate>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="password" class="form-label text-graphite">New Password</label>
                        <input type="password" name="password" id="password" class="form-control" required minlength="8">
                        <div class="invalid-feedback">
                            Please enter a password with at least 8 characters.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label text-graphite">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        <div class="invalid-feedback">
                            Passwords must match.
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2 mt-3">
                        Reset Password
                    </button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <a href="sign-in.php" class="text-decoration-none text-graphite">
                    <i class="fas fa-arrow-left me-1"></i> Back to Sign In
                </a>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
  
  <script>
  // Client-side validation
  (function() {
    'use strict';
    var form = document.querySelector('.needs-validation');
    if (form) {
      form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    }
  })();
  </script>
</body>
</html>
