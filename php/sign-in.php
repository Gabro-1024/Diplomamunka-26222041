<?php
require_once __DIR__ . '/includes/auth_check.php';
redirectIfLoggedIn();
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Studiova</title>
  <link rel="shortcut icon" type="image/png" href="../assets/images/logos/favicon.svg" />
  <link rel="stylesheet" href="../assets/libs/owl.carousel/dist/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="../assets/libs/aos-master/dist/aos.css">
  <link rel="stylesheet" href="../assets/css/styles.css" />
</head>

<body>

  <!--  Page Wrapper -->
  <div class="page-wrapper overflow-hidden">

    <section class="bg-graphite border-top border-accent-blue border-4 d-flex align-items-center justify-content-center min-vh-100">
      <div class="container py-3">
        <div class="sign-in card mx-auto shadow-lg">
          <div class="card-body py-8 px-lg-5">
            <a href="index.php" class="mb-8 hstack justify-content-center">
              <img src="../assets/images/logos/logo-white.svg" alt="logo" class="img-fluid">
            </a>
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-check-circle-fill flex-shrink-0 me-2" viewBox="0 0 16 16">
                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
              </svg>
              <div>
                Registration successful! Please check your email to verify your account.
              </div>
            </div>
            <?php endif; ?>
            
            <form id="loginForm" class="d-flex flex-column gap-3 needs-validation" novalidate>
              <div id="loginError" class="alert alert-danger d-none" role="alert"></div>
              <div class="row g-3">
                <!-- Email -->
                <div class="col-12">
                  <label for="email" class="form-label text-graphite">Email</label>
                  <input type="email"
                         name="email"
                         id="email"
                         class="form-control border-bottom"
                         placeholder="Enter your email"
                         required>
                  <div class="invalid-feedback">
                    Please enter a valid email address.
                  </div>
                </div>

                <!-- Password -->
                <div class="col-12">
                  <div class="input-group">
                    <input type="password"
                           name="password"
                           id="password"
                           class="form-control py-2"
                           placeholder="Enter your password"
                           required>
                    <div class="invalid-feedback">
                      Please enter your password.
                    </div>
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="rememberMe" id="rememberMe" value="1">
                  <label class="form-check-label text-graphite" for="rememberMe">
                    Remember me
                  </label>
                </div>
                <a href="forgot-password.php" class="text-graphite">Forgot password?</a>
              </div>

              <button type="submit" class="btn btn-primary w-100 py-3 fw-bold fs-5" id="loginButton">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                <span class="button-text">Sign In</span>
              </button>

              <p class="text-center text-graphite mt-4 mb-0">
                Don't have an account? 
                <a href="sign-up.php" class="text-primary fw-bold">Sign up</a>
              </p>
          </div>
        </div>
      </div>
    </section>

  </div>

  <div class="get-template hstack gap-2">
    
    <button class="btn bg-primary p-2 round-52 rounded-circle hstack justify-content-center flex-shrink-0"
      id="scrollToTopBtn">
      <iconify-icon icon="lucide:arrow-up" class="fs-7 text-dark"></iconify-icon>
    </button>
  </div>


  <script src="../assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="../assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/libs/owl.carousel/dist/owl.carousel.min.js"></script>
  <script src="../assets/libs/aos-master/dist/aos.js"></script>
  <script src="../assets/js/custom.js"></script>
  <!-- solar icons -->
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginError = document.getElementById('loginError');
    const loginButton = document.getElementById('loginButton');
    const spinner = loginButton.querySelector('.spinner-border');
    const buttonText = loginButton.querySelector('.button-text');

    loginForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      // Reset error state
      loginError.classList.add('d-none');
      loginButton.disabled = true;
      spinner.classList.remove('d-none');
      buttonText.textContent = 'Signing in...';

      try {
        const formData = new FormData(loginForm);
        
        const response = await fetch('process-signin.php', {
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

        console.log('Login response:', data);

        if (data.success) {
          // Add a small delay to ensure session is properly set
          setTimeout(() => {
            window.location.href = data.redirect || 'index.php';
          }, 100);
        } else {
          // Show only generic error message to user
          loginError.textContent = 'Invalid email or password. Please try again.';
          loginError.classList.remove('d-none');
          
          // Log the full error to console for debugging
          if (data.code) {
            console.error(`Login failed with code: ${data.code}`);
          }
        }
      } catch (error) {
        console.error('Login error:', error);
        // Show generic error message
        loginError.textContent = 'An error occurred during sign in. Please try again.';
        loginError.classList.remove('d-none');
      } finally {
        loginButton.disabled = false;
        spinner.classList.add('d-none');
        buttonText.textContent = 'Sign In';
      }
    });
  });
  </script>
</body>

</html>