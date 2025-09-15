<?php
require_once __DIR__ . '/includes/auth_check.php';
redirectIfLoggedIn();
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password - Tickets at Gábor</title>
  <link rel="shortcut icon" type="image/png" href="../assets/images/logos/favicon.svg" />
  <link rel="stylesheet" href="http://localhost:63342/Diplomamunka-26222041/assets/libs/owl.carousel/dist/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="http://localhost:63342/Diplomamunka-26222041/assets/libs/aos-master/dist/aos.css">
  <link rel="stylesheet" href="http://localhost:63342/Diplomamunka-26222041/assets/css/styles.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
  <!--  Page Wrapper -->
  <div class="page-wrapper overflow-hidden">
    <section class="bg-graphite border-top border-accent-blue border-4 d-flex align-items-center justify-content-center min-vh-100">
      <div class="container py-3">
        <div class="sign-in card mx-auto shadow-lg">
          <div class="card-body py-8 px-lg-5">
            <a href="http://localhost:63342/Diplomamunka-26222041/php/index.php" class="mb-8 hstack justify-content-center">
              <img src="http://localhost:63342/Diplomamunka-26222041/assets/images/logos/logo-white.svg" alt="logo" class="img-fluid">
            </a>
            
            <?php if (isset($_GET['reset']) && $_GET['reset'] == 'sent'): ?>
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
              <i class="fas fa-check-circle me-2"></i>
              <div>
                If an account exists with this email, you will receive a password reset link shortly.
              </div>
            </div>
            <?php endif; ?>
            
            <h2 class="text-center mb-4 text-graphite">Reset Your Password</h2>
            <p class="text-center text-muted mb-4">Enter your email address and we'll send you a link to reset your password.</p>
            
            <form id="forgotPasswordForm" class="d-flex flex-column gap-3 needs-validation" novalidate>
              <div id="forgotPasswordError" class="alert alert-danger d-none" role="alert"></div>
              <div class="row g-3">
                <!-- Email -->
                <div class="col-12">
                  <label for="email" class="form-label text-graphite">Email</label>
                  <div class="input-group">
                    <span class="input-group-text bg-light px-2 m-1">
                      <i class="fas fa-envelope text-white"></i>
                    </span>
                    <input type="email"
                           name="email"
                           id="email"
                           class="form-control py-2"
                           placeholder="Enter your email address"
                           required
                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                    <div class="invalid-feedback">
                      Please enter a valid email address.
                    </div>
                  </div>
                </div>
                
                <!-- Submit Button -->
                <div class="col-12 mt-4">
                  <button type="submit" id="resetButton" class="btn btn-primary w-100 py-2">
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    <span class="button-text">Send Reset Link</span>
                  </button>
                </div>
                
                <!-- Back to Login -->
                <div class="col-12 text-center mt-3">
                  <a href="sign-in.php" class="text-decoration-none text-graphite">
                    <i class="fas fa-arrow-left me-1"></i> Back to Sign In
                  </a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </section>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('forgotPasswordForm');
    const errorDiv = document.getElementById('forgotPasswordError');
    const resetButton = document.getElementById('resetButton');
    const spinner = resetButton.querySelector('.spinner-border');
    const buttonText = resetButton.querySelector('.button-text');

    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      // Reset error state
      errorDiv.classList.add('d-none');
      resetButton.disabled = true;
      spinner.classList.remove('d-none');
      buttonText.textContent = 'Sending...';

      try {
        const formData = new FormData(form);
        
        const response = await fetch('process-forgot-password.php', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json().catch(error => {
          console.error('Error parsing JSON:', error);
          throw new Error('Invalid response from server');
        });

        console.log('Reset password response:', data);

        if (data.success) {
          // Redirect to success page
          window.location.href = 'forgot-password.php?reset=sent';
        } else {
          // Show generic error message
          errorDiv.textContent = 'An error occurred. Please try again.';
          errorDiv.classList.remove('d-none');
          
          // Log the full error to console for debugging
          if (data.code) {
            console.error(`Reset password failed with code: ${data.code}`);
          }
        }
      } catch (error) {
        console.error('Reset password error:', error);
        errorDiv.textContent = 'An error occurred. Please try again.';
        errorDiv.classList.remove('d-none');
      } finally {
        resetButton.disabled = false;
        spinner.classList.add('d-none');
        buttonText.textContent = 'Send Reset Link';
      }
    });
    
    // Form validation
    form.addEventListener('submit', function(event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
  </script>
</body>
</html>
