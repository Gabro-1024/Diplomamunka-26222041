<?php
//Fesztiválok listázása
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

  <!-- Header -->
  <?php include 'header.php'; ?>

  <!--  Page Wrapper -->
  <div class="page-wrapper overflow-hidden">

    <!--  Banner Section -->
    <section class="banner-section banner-inner-section position-relative overflow-hidden d-flex align-items-end"
      style="background-image: url(../assets/images/backgrounds/projects-banner.jpg);">
      <div class="container">
        <div class="d-flex flex-column gap-4 pb-5 pb-xl-10 position-relative z-1">
          <div class="row align-items-center">
            <div class="col-xl-4">
              <div class="d-flex align-items-center gap-4" data-aos="fade-up" data-aos-delay="100"
                data-aos-duration="1000">
                <img src="../assets/images/svgs/primary-leaf.svg" alt="" class="img-fluid animate-spin">
                <p class="mb-0 text-white fs-5 text-opacity-70">A <span class="text-primary">showcase of
                    creativity</span>, strategy, and results explore the projects that define us.</p>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-end gap-3" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
            <h1 class="mb-0 fs-16 text-white lh-1">Projects</h1>
            <a href="javascript:void(0)" class="p-1 ps-7 bg-primary rounded-pill">
              <span class="bg-white round-52 rounded-circle d-flex align-items-center justify-content-center">
                <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
              </span>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!--  Project Section -->
    <section class="project py-5 py-lg-11 py-xl-12">
      <div class="container">
        <div class="row">
          <div class="col-lg-6 mb-7">
            <div class="portfolio d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
              data-aos-duration="1000">
              <div class="portfolio-img position-relative overflow-hidden">
                <img src="../assets/images/portfolio/portfolio-img-5.jpg" alt="" class="img-fluid w-100">
                <div class="portfolio-overlay">
                  <a href="projects-detail.html"
                    class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                    <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                  </a>
                </div>
              </div>
              <div class="portfolio-details d-flex flex-column gap-3">
                <h3 class="mb-0">Amber Bottle</h3>
                <div class="hstack gap-2">
                  <span class="badge text-dark border">Photography</span>
                  <span class="badge text-dark border">Studio</span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 mb-7">
            <div class="portfolio d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="200"
              data-aos-duration="1000">
              <div class="portfolio-img position-relative overflow-hidden">
                <img src="../assets/images/portfolio/portfolio-img-4.jpg" alt="" class="img-fluid w-100">
                <div class="portfolio-overlay">
                  <a href="projects-detail.html"
                    class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                    <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                  </a>
                </div>
              </div>
              <div class="portfolio-details d-flex flex-column gap-3">
                <h3 class="mb-0">BioTrack LIMS</h3>
                <div class="hstack gap-2">
                  <span class="badge text-dark border">Brand identity</span>
                  <span class="badge text-dark border">Digital design</span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 mb-7">
            <div class="portfolio d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="300"
              data-aos-duration="1000">
              <div class="portfolio-img position-relative overflow-hidden">
                <img src="../assets/images/portfolio/portfolio-img-6.jpg" alt="" class="img-fluid w-100">
                <div class="portfolio-overlay">
                  <a href="projects-detail.html"
                    class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                    <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                  </a>
                </div>
              </div>
              <div class="portfolio-details d-flex flex-column gap-3">
                <h3 class="mb-0">Digital Magazine</h3>
                <div class="hstack gap-2">
                  <span class="badge text-dark border">Digital design</span>
                  <span class="badge text-dark border">Web development</span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 mb-7">
            <div class="portfolio d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="400"
              data-aos-duration="1000">
              <div class="portfolio-img position-relative overflow-hidden">
                <img src="../assets/images/portfolio/portfolio-img-3.jpg" alt="" class="img-fluid w-100">
                <div class="portfolio-overlay">
                  <a href="projects-detail.html"
                    class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                    <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                  </a>
                </div>
              </div>
              <div class="portfolio-details d-flex flex-column gap-3">
                <h3 class="mb-0">Pixelforge</h3>
                <div class="hstack gap-2">
                  <span class="badge text-dark border">UI/UX design</span>
                  <span class="badge text-dark border">Web development</span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 mb-7">
            <div class="portfolio d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="500"
              data-aos-duration="1000">
              <div class="portfolio-img position-relative overflow-hidden">
                <img src="../assets/images/portfolio/portfolio-img-1.jpg" alt="" class="img-fluid w-100">
                <div class="portfolio-overlay">
                  <a href="projects-detail.html"
                    class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                    <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                  </a>
                </div>
              </div>
              <div class="portfolio-details d-flex flex-column gap-3">
                <h3 class="mb-0">Snapclear</h3>
                <div class="hstack gap-2">
                  <span class="badge text-dark border">UX Strategy</span>
                  <span class="badge text-dark border">UI Design</span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 mb-7">
            <div class="portfolio d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="600"
              data-aos-duration="1000">
              <div class="portfolio-img position-relative overflow-hidden">
                <img src="../assets/images/portfolio/portfolio-img-2.jpg" alt="" class="img-fluid w-100">
                <div class="portfolio-overlay">
                  <a href="projects-detail.html"
                    class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                    <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                  </a>
                </div>
              </div>
              <div class="portfolio-details d-flex flex-column gap-3">
                <h3 class="mb-0">Transfermax</h3>
                <div class="hstack gap-2">
                  <span class="badge text-dark border">Web development</span>
                  <span class="badge text-dark border">Digital design</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </div>

  <!-- Footer section -->
  <?php include 'footer.php'; ?>

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