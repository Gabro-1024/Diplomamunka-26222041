<?php
// Set the page title
$pageTitle = 'Privacy Policy - Tickets @ Gábor';
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
    
    .privacy-content {
      padding: 80px 0;
    }
    
    .privacy-content h2 {
      color: var(--bs-primary);
      margin: 30px 0 20px;
      font-weight: 600;
    }
    
    .privacy-content h3 {
      color: var(--bs-accent-blue);
      margin: 25px 0 15px;
      font-weight: 500;
    }
    
    .privacy-content p, .privacy-content li {
      color: #555;
      line-height: 1.8;
      margin-bottom: 15px;
    }
    
    .privacy-content ul {
      padding-left: 20px;
    }
    
    .privacy-content li {
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
      <div class="container-fluid px-3 px-sm-4 px-xl-5">
        <div class="d-flex flex-column gap-4 py-8 py-lg-10 py-xl-12 position-relative z-1">
          <div class="row align-items-center">
            <div class="col-12">
              <div class="d-flex align-items-center gap-4" data-aos="fade-up" data-aos-delay="100"
                data-aos-duration="1000">
                <img src="../assets/images/svgs/primary-leaf.svg" alt="" class="img-fluid animate-spin">
                <p class="mb-0 text-white fs-5 text-opacity-70">Your <span class="text-primary">privacy matters</span> to us. Learn how we protect and manage your personal information.</p>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-end gap-3" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
            <h1 class="mb-0 fs-16 text-white lh-1">Privacy Policy</h1>
            <a href="#privacy-content" class="p-1 ps-7 bg-primary rounded-pill">
              <span class="bg-white round-52 rounded-circle d-flex align-items-center justify-content-center">
                <iconify-icon icon="lucide:arrow-down" class="fs-8 text-dark"></iconify-icon>
              </span>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Privacy Content -->
    <section class="py-5 py-lg-8 py-xl-10" id="privacy-content">
      <div class="container-fluid px-3 px-sm-4 px-xl-5">
        <div class="row">
          <div class="col-12 px-0 px-sm-3">
            <div class="text-center mb-6">
              <span class="badge badge-accent-blue fs-6 px-3 py-2 mb-3">PRIVACY</span>
              <h2 class="mb-3">Our Privacy Policy</h2>
              <p class="lead mb-0">Last updated: September 27, 2025</p>
            </div>
            
            <div class="card border-0 shadow-sm p-4 p-lg-5 mb-5" data-aos="fade-up">
              <p class="mb-0">At Tickets @ Gábor, we take your privacy seriously. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website or use our services.</p>
            </div>

            <div class="mb-5" data-aos="fade-up" data-aos-delay="100">
              <h3 class="d-flex align-items-center gap-3 mb-4">
                <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">1</span>
                Information We Collect
              </h3>
            <p>We may collect personal information that you voluntarily provide to us when you register on the site, place an order, subscribe to our newsletter, or otherwise contact us. The personal information we may collect includes:</p>
            <ul>
              <li>Name and contact information (email, phone number, address)</li>
              <li>Payment information (processed securely by our payment processors)</li>
              <li>Event preferences and purchase history</li>
              <li>IP address and device information</li>
              <li>Usage data and analytics</li>
            </ul>

            </div>

            <div class="mb-5" data-aos="fade-up" data-aos-delay="150">
              <h3 class="d-flex align-items-center gap-3 mb-4">
                <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">2</span>
                How We Use Your Information
              </h3>
            <p>We use the information we collect to:</p>
            <ul>
              <li>Process and manage your ticket purchases</li>
              <li>Communicate with you about your orders and events</li>
              <li>Improve our website and services</li>
              <li>Send promotional emails (you can opt-out at any time)</li>
              <li>Prevent fraud and ensure security</li>
              <li>Comply with legal obligations</li>
            </ul>

            </div>

            <div class="mb-5" data-aos="fade-up" data-aos-delay="200">
              <h3 class="d-flex align-items-center gap-3 mb-4">
                <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">3</span>
                Data Security
              </h3>
            <p>We implement appropriate security measures to protect your personal information. However, no method of transmission over the Internet or electronic storage is 100% secure, and we cannot guarantee absolute security.</p>

            </div>

            <div class="mb-5" data-aos="fade-up" data-aos-delay="250">
              <h3 class="d-flex align-items-center gap-3 mb-4">
                <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">4</span>
                Your Rights
              </h3>
            <p>You have the right to:</p>
            <ul>
              <li>Access, update, or delete your personal information</li>
              <li>Opt-out of marketing communications</li>
              <li>Request a copy of your data</li>
              <li>Lodge a complaint with a data protection authority</li>
            </ul>

            </div>

            <div class="mb-5" data-aos="fade-up" data-aos-delay="300">
              <h3 class="d-flex align-items-center gap-3 mb-4">
                <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">5</span>
                Third-Party Services
              </h3>
            <p>We may use third-party services (like payment processors) that collect, monitor, and analyze information to provide better services.</p>

            </div>

            <div class="mb-5" data-aos="fade-up" data-aos-delay="350">
              <h3 class="d-flex align-items-center gap-3 mb-4">
                <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">6</span>
                Changes to This Policy
              </h3>
            <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new policy on this page.</p>

            </div>

            <div class="card border-0 bg-primary p-4 p-lg-5 text-center" data-aos="fade-up" data-aos-delay="400">
              <h3 class="mb-3">Still have questions?</h3>
              <p class="mb-4">If you have any questions about our Privacy Policy or how we handle your data, please don't hesitate to contact us.</p>
              <div class="d-flex justify-content-center">
                <a href="contact.php" class="btn btn-accent-blue">
                  Contact Us
                  <iconify-icon icon="lucide:arrow-up-right" class="ms-2"></iconify-icon>
                </a>
              </div>
            </div>
            
            <div class="text-center mt-6">
              <p class="mb-0 text-muted">Email us at <a href="mailto:privacy@ticketsgabor.com" class="link-accent-blue">privacy@ticketsgabor.com</a> for any privacy-related inquiries.</p>
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