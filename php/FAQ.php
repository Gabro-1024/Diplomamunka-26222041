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
            <div class="col-xl-4">
              <div class="d-flex align-items-center gap-4" data-aos="fade-up" data-aos-delay="100"
                data-aos-duration="1000">
                <img src="../assets/images/svgs/primary-leaf.svg" alt="" class="img-fluid animate-spin">
                <p class="mb-0 text-white fs-5 text-opacity-70">Excited to <span class="text-primary">begin something
                    amazing?</span>Get in touch—we'd love to connect with you!</p>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-end gap-3" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
            <h1 class="mb-0 fs-16 text-white lh-1">Blog</h1>
            <a href="javascript:void(0)" class="p-1 ps-7 bg-primary rounded-pill">
              <span class="bg-white round-52 rounded-circle d-flex align-items-center justify-content-center">
                <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
              </span>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!--  Blog Section -->
    <section class="blog-section py-5 py-lg-11 py-xl-12">
      <div class="container">
        <div class="row">
          <div class="col-lg-6 mb-7">
            <div class="resources d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
              data-aos-duration="1000">
              <a href="blog-detail.html"
                class="resources-img resources-img-blog position-relative overflow-hidden d-block">
                <img src="../assets/images/resources/resources-1.jpg" alt="resources" class="img-fluid">
              </a>
              <div class="resources-details">
                <p class="mb-0">Dec 24, 2025</p>
                <h4 class="mb-0">A campaign that connects</h4>
              </div>
            </div>
          </div>
          <div class="col-lg-6 mb-7">
            <div class="resources d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="200"
              data-aos-duration="1000">
              <a href="blog-detail.html"
                class="resources-img resources-img-blog position-relative overflow-hidden d-block">
                <img src="../assets/images/resources/resources-2.jpg" alt="resources" class="img-fluid">
              </a>
              <div class="resources-details">
                <p class="mb-0">Dec 24, 2025</p>
                <h4 class="mb-0">An breaking boundaries our latest brand redesign</h4>
              </div>
            </div>
          </div>
          <div class="col-lg-6 mb-7">
            <div class="resources d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="300"
              data-aos-duration="1000">
              <a href="blog-detail.html"
                class="resources-img resources-img-blog position-relative overflow-hidden d-block">
                <img src="../assets/images/resources/resources-3.jpg" alt="resources" class="img-fluid">
              </a>
              <div class="resources-details">
                <p class="mb-0">Dec 24, 2025</p>
                <h4 class="mb-0">Recognized for design</h4>
              </div>
            </div>
          </div>
          <div class="col-lg-6 mb-7">
            <div class="resources d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="400"
              data-aos-duration="1000">
              <a href="blog-detail.html"
                class="resources-img resources-img-blog position-relative overflow-hidden d-block">
                <img src="../assets/images/services/services-img-1.jpg" alt="resources" class="img-fluid">
              </a>
              <div class="resources-details">
                <p class="mb-0">Dec 24, 2025</p>
                <h4 class="mb-0">The Modern Lens Perspectives on Culture & Trends</h4>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </div>

  <footer class="footer bg-dark py-5 py-lg-11 py-xl-12">
    <div class="container">
      <div class="row">
        <div class="col-xl-5 mb-8 mb-xl-0">
          <div class="d-flex flex-column gap-8 pe-xl-5">
            <h2 class="mb-0 text-white">Build something together?</h2>
            <div class="d-flex flex-column gap-2">
              <a href="https://www.wrappixel.com/" target="_blank" class="link-hover hstack gap-3 text-white fs-5">
                <iconify-icon icon="lucide:arrow-up-right" class="fs-7 text-primary"></iconify-icon>
                info@wrappixel.com
              </a>
              <a href="https://maps.app.goo.gl/hpDp81fqzGt5y4bC8" target="_blank"
                class="link-hover hstack gap-3 text-white fs-5">
                <iconify-icon icon="lucide:map-pin" class="fs-7 text-primary"></iconify-icon>
                info@wrappixel.com
              </a>
            </div>
          </div>
        </div>
        <div class="col-md-4 col-xl-2 mb-8 mb-xl-0">
          <ul class="footer-menu list-unstyled mb-0 d-flex flex-column gap-2">
            <li><a class="link-hover fs-5 text-white" href="index.php">Home</a></li>
            <li><a class="link-hover fs-5 text-white" href="about-us.php">About</a></li>
            <li><a class="link-hover fs-5 text-white" id="services" href="#services">Services</a></li>
            <li><a class="link-hover fs-5 text-white" href="projects.html">Work</a></li>
            <li><a class="link-hover fs-5 text-white" href="terms-and-conditions.html">Terms</a></li>
            <li><a class="link-hover fs-5 text-white" href="privacy-policy.html">Privacy Policy</a></li>
            <li><a class="link-hover fs-5 text-white" href="404.php">Error 404</a></li>
          </ul>
        </div>
        <div class="col-md-4 col-xl-2 mb-8 mb-xl-0">
          <ul class="footer-menu list-unstyled mb-0 d-flex flex-column gap-2">
            <li><a class="link-hover fs-5 text-white" href="#!">Facebook</a></li>
            <li><a class="link-hover fs-5 text-white" href="#!">Instagram</a></li>
            <li><a class="link-hover fs-5 text-white" href="#!">Twitter</a></li>
          </ul>
        </div>
        <div class="col-md-4 col-xl-3 mb-8 mb-xl-0">
          <p class="mb-0 text-white text-opacity-70 text-md-end">© Studiova copyright 2025</p>
        </div>
      </div>
    </div>
  <p class="mb-0 text-white text-opacity-70 text-md-center mt-10">Distributed by <a class="text-white" href="https://www.themewagon.com" target="_blank">ThemeWagon</a></p>
  </footer>

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
</body>

</html>