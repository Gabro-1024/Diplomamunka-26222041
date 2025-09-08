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
        if (empty($password)) {
            throw new Exception('Password is required.');
        } elseif (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long.');
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
            throw new Exception('Password must include at least one uppercase letter, one lowercase letter, and one number.');
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>
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
                <form method="POST" id="resetPasswordForm" class="d-flex flex-column gap-3 needs-validation" novalidate>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="password" class="form-label text-graphite">New Password</label>
                        <div class="input-group">
                            <input type="password" 
                                   name="password" 
                                   id="password" 
                                   class="form-control" 
                                   required 
                                   minlength="8"
                                   pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$"
                                   oninput="checkPasswordStrength(this.value)">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text text-muted small">
                            Password must be at least 8 characters long and include:
                            <ul class="mb-0 ps-3">
                                <li id="length" class="text-danger">At least 8 characters</li>
                                <li id="uppercase" class="text-danger">One uppercase letter (A-Z)</li>
                                <li id="lowercase" class="text-danger">One lowercase letter (a-z)</li>
                                <li id="number" class="text-danger">One number (0-9)</li>
                            </ul>
                        </div>
                        <div class="invalid-feedback">
                            Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, and a number.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label text-graphite">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" 
                                   name="confirm_password" 
                                   id="confirm_password" 
                                   class="form-control" 
                                   required
                                   oninput="checkPasswordMatch()">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="passwordMatch" class="form-text"></div>
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
    // Toggle password visibility
    document.addEventListener('DOMContentLoaded', function() {
      // Toggle password visibility
      document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
          const targetId = this.getAttribute('data-target');
          const input = document.getElementById(targetId);
          const icon = this.querySelector('i');
          
          if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
          } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
          }
        });
      });

      // Form validation
      const form = document.getElementById('resetPasswordForm');
      if (form) {
        form.addEventListener('submit', function(event) {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      }
    });

    // Check password strength
    function checkPasswordStrength(password) {
      const lengthValid = password.length >= 8;
      const hasUppercase = /[A-Z]/.test(password);
      const hasLowercase = /[a-z]/.test(password);
      const hasNumber = /\d/.test(password);
      
      // Update UI
      if (document.getElementById('length')) {
        document.getElementById('length').className = lengthValid ? 'text-success' : 'text-danger';
        document.getElementById('uppercase').className = hasUppercase ? 'text-success' : 'text-danger';
        document.getElementById('lowercase').className = hasLowercase ? 'text-success' : 'text-danger';
        document.getElementById('number').className = hasNumber ? 'text-success' : 'text-danger';
      }
      
      // Return whether all requirements are met
      return lengthValid && hasUppercase && hasLowercase && hasNumber;
    }
    
    // Check if passwords match
    function checkPasswordMatch() {
      const password = document.getElementById('password')?.value || '';
      const confirmPassword = document.getElementById('confirm_password')?.value || '';
      const matchMessage = document.getElementById('passwordMatch');
      
      if (!matchMessage) return false;
      
      if (confirmPassword === '') {
        matchMessage.textContent = '';
        return false;
      }
      
      if (password === confirmPassword) {
        matchMessage.textContent = 'Passwords match!';
        matchMessage.className = 'form-text text-success';
        return true;
      } else {
        matchMessage.textContent = 'Passwords do not match.';
        matchMessage.className = 'form-text text-danger';
        return false;
      }
    }
    
    // Add real-time validation
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    if (passwordInput) {
      passwordInput.addEventListener('input', function() {
        checkPasswordStrength(this.value);
        // Re-check password match when password changes
        checkPasswordMatch();
      });
    }
    
    if (confirmPasswordInput) {
      confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    }
  </script>
</body>
</html>
