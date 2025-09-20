<?php
require_once 'includes/db_connect.php';

// Fetch venue by id from query string
try {
    $pdo = db_connect();
    $venue = null;
    $venueImages = [];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM venues WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $venue = $stmt->fetch(PDO::FETCH_ASSOC);
        // Fetch related images for this venue
        $imgStmt = $pdo->prepare('SELECT image_path FROM venue_images WHERE venue_id = :id ORDER BY id ASC');
        $imgStmt->execute([':id' => $id]);
        $venueImages = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
    }
    if (!$venue) {
        http_response_code(404);
        $error_message = 'The requested venue could not be found.';
    }
    // Determine banner image
    $bannerImage = !empty($venue['cover_image'] ?? '')
        ? $venue['cover_image']
        : '../assets/images/backgrounds/venues-banner.jpg';
} catch (Exception $e) {
    error_log('Database error in location-detail.php: ' . $e->getMessage());
    http_response_code(500);
    $error_message = 'A database error occurred. Please try again later.';
}
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo isset($venue['name']) ? htmlspecialchars($venue['name']) . ' - Tickets @ Gábor' : 'Venue - Tickets @ Gábor'; ?></title>
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
      style="background-image: url('<?php echo htmlspecialchars($bannerImage); ?>');">
      <div class="container">
        <div class="d-flex flex-column gap-4 pb-5 pb-xl-10 position-relative z-1">
          <div class="row align-items-center">
            <div class="col-xl-4">
              <div class="d-flex align-items-center gap-4" data-aos="fade-up" data-aos-delay="100"
                data-aos-duration="1000">
                <img src="../assets/images/svgs/primary-leaf.svg" alt="" class="img-fluid animate-spin">
                <p class="mb-0 text-white fs-5 text-opacity-70">Discover details about this festival venue.</p>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-end gap-3" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
            <h1 class="mb-0 fs-15 text-white lh-1"><?php echo isset($venue['name']) ? htmlspecialchars($venue['name']) : 'Venue not found'; ?></h1>
            <a href="locations.php" class="p-1 ps-7 bg-primary rounded-pill" title="Back to Locations">
              <span class="bg-white round-52 rounded-circle d-flex align-items-center justify-content-center">
                <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
              </span>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!--  Venue Detail Section -->
    <section class="blog-detail py-5 py-lg-11 py-xl-12">
      <div class="container">
        <?php if (isset($error_message)): ?>
          <div class="row">
            <div class="col-12 text-center py-5">
              <h3><?php echo htmlspecialchars($error_message); ?></h3>
              <p class="lead"><a href="locations.php" class="link-primary">Back to Locations</a></p>
            </div>
          </div>
        <?php else: ?>
        <div class="d-flex flex-column gap-7 gap-xl-11">
          <div class="row gap-4 gap-lg-0">
            <div class="col-lg-4">
              <h2 class="fs-13 mb-0" data-aos="fade-right" data-aos-delay="100" data-aos-duration="1000">About the venue</h2>
            </div>
            <div class="col-lg-8">
              <div data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
                <div class="hstack gap-2 flex-wrap mb-3">
                  <?php if (!empty($venue['address'])): ?>
                    <span class="badge text-dark border">
                      <iconify-icon icon="mdi:map-marker" class="me-1"></iconify-icon>
                      <?php echo htmlspecialchars($venue['address']); ?>
                    </span>
                  <?php endif; ?>
                  <?php if (isset($venue['capacity'])): ?>
                    <span class="badge text-dark border">
                      <iconify-icon icon="mdi:account-group" class="me-1"></iconify-icon>
                      Capacity: <?php echo number_format((int)$venue['capacity']); ?>+
                    </span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($venue['story'] ?? '')): ?>
                  <p class="fs-5 mb-0"><?php echo nl2br(htmlspecialchars($venue['story'])); ?></p>
                <?php elseif (!empty($venue['description'] ?? '')): ?>
                  <p class="fs-5 mb-0"><?php echo nl2br(htmlspecialchars($venue['description'])); ?></p>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="blog-detail-img" data-aos="fade-up" data-aos-delay="300" data-aos-duration="1000">
            <img src="<?php echo htmlspecialchars(!empty($venue['cover_image']) ? $venue['cover_image'] : '../assets/images/portfolio/portfolio-img-1.jpg'); ?>" alt="<?php echo htmlspecialchars($venue['name']); ?>" class="img-fluid">
          </div>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <?php if (empty($error_message) && !empty($venueImages)): ?>
    <!--  Venue Gallery Section -->
    <section class="py-3 py-lg-6 py-xl-7">
      <div class="container">
        <div class="d-flex flex-column gap-4">
          <h3 class="mb-0">Gallery</h3>
          <div class="row g-3">
            <?php foreach ($venueImages as $imgPath): ?>
              <div class="col-12 col-sm-6 col-lg-4">
                <div class="position-relative overflow-hidden rounded-2">
                  <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($venue['name']); ?> image" class="img-fluid w-100"/>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

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