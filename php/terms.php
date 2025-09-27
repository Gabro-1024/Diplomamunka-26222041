<?php
// Set the page title
$pageTitle = 'Terms & Conditions - Tickets @ Gábor';
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $pageTitle; ?></title>
  <!-- Favicon -->
  <link rel="shortcut icon" type="image/png" href="../assets/images/logos/favicon.svg" />
  <!-- CSS -->
  <link rel="stylesheet" href="../assets/libs/owl.carousel/dist/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="../assets/libs/aos-master/dist/aos.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    :root {
      --bs-primary: #FF6F61;
      --bs-primary-rgb: 255, 111, 97;
      --bs-accent-blue: #2210FF;
      --bs-accent-blue-rgb: 34, 16, 255;
    }
    
    body {
      font-family: 'Poppins', sans-serif;
      color: #333;
    }
    
    .banner-inner-section {
      min-height: 400px;
      background-size: cover;
      background-position: center;
      position: relative;
    }
    
    .banner-inner-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(to right, rgba(0,0,0,0.7), rgba(0,0,0,0.3));
    }
    
    .banner-content {
      position: relative;
      z-index: 1;
      padding: 100px 0 60px;
      color: white;
    }
    
    .terms-content {
      padding: 80px 0;
    }
    
    .terms-content h2 {
      color: var(--bs-primary);
      margin: 30px 0 20px;
      font-weight: 600;
    }
    
    .terms-content h3 {
      color: var(--bs-accent-blue);
      margin: 25px 0 15px;
      font-weight: 500;
    }
    
    .terms-content p, .terms-content li {
      color: #555;
      line-height: 1.8;
      margin-bottom: 15px;
    }
    
    .terms-content ul {
      padding-left: 20px;
    }
    
    .terms-content li {
      margin-bottom: 10px;
    }
  </style>
</head>

<body>

  <!-- Header -->
  <?php include 'header.php'; ?>

  <!--  Page Wrapper -->
  <div class="page-wrapper overflow-hidden">

    <!--  Banner Section -->
    <section class="banner-section banner-inner-section position-relative overflow-hidden d-flex align-items-end"
      style="background-image: url(../assets/images/backgrounds/blog-banner.jpg);">
      <div class="container">
        <div class="d-flex flex-column gap-4 pb-5 pb-xl-10 position-relative z-1">
          <div class="row align-items-center">
            <div class="col-xl-6">
              <div class="d-flex align-items-center gap-4" data-aos="fade-up" data-aos-delay="100"
                data-aos-duration="1000">
                <img src="../assets/images/svgs/primary-leaf.svg" alt="" class="img-fluid animate-spin">
                <p class="mb-0 text-white fs-5 text-opacity-70">Please read our <span class="text-primary">Terms & Conditions</span> carefully before using our services.</p>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-end gap-3" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
            <h1 class="mb-0 fs-16 text-white lh-1">Terms & Conditions</h1>
            <a href="#terms-content" class="p-1 ps-7 bg-primary rounded-pill">
              <span class="bg-white round-52 rounded-circle d-flex align-items-center justify-content-center">
                <iconify-icon icon="lucide:arrow-down" class="fs-8 text-dark"></iconify-icon>
              </span>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Terms Content -->
    <section class="py-5 py-lg-11 py-xl-12" id="terms-content">
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-10">
            <div class="text-center mb-6">
              <span class="badge badge-accent-blue fs-6 px-3 py-2 mb-3">LEGAL</span>
              <h2 class="mb-3">Terms & Conditions</h2>
              <p class="lead mb-0">Last updated: September 27, 2025</p>
            </div>
            
            <div class="card border-0 shadow-sm p-4 p-lg-5 mb-5" data-aos="fade-up">
              <p class="mb-0">Welcome to Tickets @ Gábor! These Terms of Service ("Terms") govern your use of our website and services. By accessing or using our services, you agree to be bound by these Terms.</p>
            </div>

            <div class="mb-5" data-aos="fade-up" data-aos-delay="100">
              <h3 class="d-flex align-items-center gap-3 mb-4">
                <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">1</span>
                Account Registration
              </h3>
            <p>To use certain features of our service, you may be required to create an account. You agree to provide accurate and complete information and to keep this information up to date.</p>

            </div>

            <div class="mb-5" data-aos="fade-up" data-aos-delay="150">
              <h3 class="d-flex align-items-center gap-3 mb-4">
                <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">2</span>
                Ticket Purchases
              </h3>
            <p>All ticket sales are final. No refunds or exchanges unless otherwise stated. Tickets are non-transferable unless specified. We reserve the right to cancel any tickets purchased in violation of these Terms.</p>

            </div>

            <div class="mb-5" data-aos="fade-up" data-aos-delay="200">
              <h3 class="d-flex align-items-center gap-3 mb-4">
                <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">3</span>
                Event Changes and Cancellations
              </h3>
            <p>Event dates, times, venues, and artists are subject to change. In case of event cancellation, we will notify you and provide a refund of the ticket price. We are not responsible for any other expenses incurred.</p>

            </div>

            <div class="mb-5" data-aos="fade-up" data-aos-delay="250">
              <h3 class="d-flex align-items-center gap-3 mb-4">
                <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">4</span>
                User Conduct
              </h3>
            <p>You agree not to:</p>
            <ul>
              <li>Use our services for any unlawful purpose</li>
              <li>Impersonate any person or entity</li>
              <li>Interfere with or disrupt our services</li>
              <li>Attempt to gain unauthorized access to our systems</li>
              <li>Use any automated means to access our services</li>
            </ul>

            </div>

            <div class="mb-5" data-aos="fade-up" data-aos-delay="300">
              <h3 class="d-flex align-items-center gap-3 mb-4">
                <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">5</span>
                Intellectual Property
              </h3>
            <p>All content on our website, including text, graphics, logos, and images, is our property or the property of our licensors and is protected by copyright and other intellectual property laws.</p>

            </div>

            <div class="mb-5" data-aos="fade-up" data-aos-delay="350">
              <h3 class="d-flex align-items-center gap-3 mb-4">
                <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">6</span>
                Limitation of Liability
              </h3>
            <p>To the maximum extent permitted by law, we shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use of our services.</p>

            </div>

            <div class="mb-5" data-aos="fade-up" data-aos-delay="400">
              <h3 class="d-flex align-items-center gap-3 mb-4">
                <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">7</span>
                Changes to Terms
              </h3>
            <p>We reserve the right to modify these Terms at any time. We will notify you of any changes by posting the new Terms on this page.</p>

            </div>

            <div class="card border-0 bg-primary p-4 p-lg-5 text-center" data-aos="fade-up" data-aos-delay="450">
              <h3 class="mb-3">Need Help?</h3>
              <p class="mb-4">If you have any questions about our Terms & Conditions, please don't hesitate to reach out to our support team.</p>
              <div class="d-flex justify-content-center">
                <a href="contact.php" class="btn btn-accent-blue">
                  Contact Support
                  <iconify-icon icon="lucide:arrow-up-right" class="ms-2"></iconify-icon>
                </a>
              </div>
            </div>
            
            <div class="text-center mt-6">
              <p class="mb-0 text-muted">For legal inquiries, please email us at <a href="mailto:legal@ticketsgabor.com" class="link-accent-blue">legal@ticketsgabor.com</a></p>
            </div>
          </div>
        </div>
      </div>
    </section>

  </div>

  <!-- Footer -->
  <?php include 'footer.php'; ?>

  <!-- Scripts -->
  <script src="../assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="../assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/libs/owl.carousel/dist/owl.carousel.min.js"></script>
  <script src="../assets/libs/aos-master/dist/aos.js"></script>
  <script src="../assets/libs/vanilla-tilt/dist/vanilla-tilt.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script>
    // Initialize AOS
    AOS.init({
      duration: 800,
      easing: 'ease-in-out',
      once: true
    });
  </script>
  <!-- solar icons -->
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
</body>

</html>