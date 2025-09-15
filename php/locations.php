<?php
require_once 'includes/db_connect.php';

try {
    $pdo = db_connect();
    
    // Query to get all locations ordered by name
    $sql = "SELECT * FROM venues ORDER BY name ASC";
    $stmt = $pdo->query($sql);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log('Database error in locations.php: ' . $e->getMessage());
    // Don't expose database errors to users
    die('A database error occurred. Please try again later.');
}
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Locations - Tickets @ Gábor</title>
  <link rel="shortcut icon" type="image/png" href="../assets/images/logos/favicon.svg" />
  <link rel="stylesheet" href="http://localhost:63342/Diplomamunka-26222041/assets/libs/owl.carousel/dist/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="http://localhost:63342/Diplomamunka-26222041/assets/libs/aos-master/dist/aos.css">
  <link rel="stylesheet" href="http://localhost:63342/Diplomamunka-26222041/assets/css/styles.css" />
</head>

<body>

  <!-- Header -->
  <?php include 'header.php'; ?>

  <!--  Page Wrapper -->
  <div class="page-wrapper overflow-hidden">

    <!--  Banner Section -->
    <section class="banner-section banner-inner-section position-relative overflow-hidden d-flex align-items-end"
      style="background-image: url(../assets/images/backgrounds/venues-banner.jpg);">
      <div class="container">
        <div class="d-flex flex-column gap-4 pb-5 pb-xl-10 position-relative z-1">
          <div class="row align-items-center">
            <div class="col-xl-4">
              <div class="d-flex align-items-center gap-4" data-aos="fade-up" data-aos-delay="100"
                data-aos-duration="1000">
                <img src="../assets/images/svgs/primary-leaf.svg" alt="" class="img-fluid animate-spin">
                <p class="mb-0 text-white fs-5 text-opacity-70">Discover the most popular festival <span class="text-primary">locations</span> in Hungary. Find the perfect spot for your next festival experience!</p>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-end gap-3" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
            <h1 class="mb-0 fs-16 text-white lh-1">Locations</h1>
            <a href="FAQ.php" class="p-1 ps-7 bg-primary rounded-pill">
              <span class="bg-white round-52 rounded-circle d-flex align-items-center justify-content-center">
                <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
              </span>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!--  Locations Section -->
    <section class="project py-5 py-lg-11 py-xl-12">
      <div class="container">
        <div class="row">
          <?php if (!empty($locations)): ?>
            <?php 
            $imageIndex = 1;
            $delay = 100;
            foreach ($locations as $location): 
                // Cycle through available images (1-5)
                $imageNumber = $imageIndex % 5 + 1;
                $imagePath = "../assets/images/portfolio/portfolio-img-{$imageNumber}.jpg";
                
                // Format capacity
                $formattedCapacity = number_format($location['capacity']);
            ?>
            <div class="col-lg-6 mb-7">
              <div class="portfolio d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>" data-aos-duration="1000">
                <div class="portfolio-img position-relative overflow-hidden">
                  <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($location['name']); ?>" class="img-fluid w-100">
                  <div class="portfolio-overlay">
                    <a href="location-detail.php?id=<?php echo $location['id']; ?>" 
                       class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                      <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                    </a>
                  </div>
                </div>
                <div class="portfolio-details d-flex flex-column gap-3">
                  <h3 class="mb-0"><?php echo htmlspecialchars($location['name']); ?></h3>
                  <div class="hstack gap-2 flex-wrap">
                    <span class="badge text-dark border">
                      <iconify-icon icon="mdi:map-marker" class="me-1"></iconify-icon>
                      <?php echo htmlspecialchars(explode(',', $location['address'])[0]); ?>
                    </span>
                    <span class="badge text-dark border">
                      <iconify-icon icon="mdi:account-group" class="me-1"></iconify-icon>
                      Capacity: <?php echo $formattedCapacity; ?>+
                    </span>
                  </div>
                  <?php if (!empty($location['address'])): ?>
                    <p class="text-muted mb-0 small">
                      <iconify-icon icon="mdi:map-marker-outline" class="me-1"></iconify-icon>
                      <?php echo htmlspecialchars($location['address']); ?>
                    </p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php 
                $imageIndex++;
                $delay += 100;
                if ($delay > 600) $delay = 100; // Reset delay after 6 items
            endforeach; 
            ?>
          <?php else: ?>
            <div class="col-12 text-center py-5">
              <h3>No locations available at the moment.</h3>
              <p class="lead">Please check back later for updates!</p>
            </div>
          <?php endif; ?>
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