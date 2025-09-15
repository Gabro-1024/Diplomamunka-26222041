<?php
require_once 'includes/db_connect.php';

try {
    $pdo = db_connect();
    
    // Query to get all events ordered by start date
    $sql = "SELECT * FROM events WHERE end_date >= NOW() ORDER BY start_date ASC";
    $stmt = $pdo->query($sql);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log('Database error in festivals.php: ' . $e->getMessage());
    // Don't expose database errors to users
    die('A database error occurred. Please try again later.');
}
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Festivals - Tickets @ Gábor</title>
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
      style="background-image: url(../assets/images/backgrounds/festivals-banner.jpg);">
      <div class="container">
        <div class="d-flex flex-column gap-4 pb-5 pb-xl-10 position-relative z-1">
          <div class="row align-items-center">
            <div class="col-xl-4">
              <div class="d-flex align-items-center gap-4" data-aos="fade-up" data-aos-delay="100"
                data-aos-duration="1000">
                <img src="../assets/images/svgs/primary-leaf.svg" alt="" class="img-fluid animate-spin">
                <p class="mb-0 text-white fs-5 text-opacity-70">Discover the most exciting festivals in Hungary. <span class="text-primary">Get your tickets now</span> for an unforgettable experience!</p>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-end gap-3" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
            <h1 class="mb-0 fs-16 text-white lh-1">Festivals</h1>
            <a href="locations.php" class="p-1 ps-7 bg-primary rounded-pill">
              <span class="bg-white round-52 rounded-circle d-flex align-items-center justify-content-center">
                <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
              </span>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!--  Events Section -->
    <section class="project py-5 py-lg-11 py-xl-12">
      <div class="container">
        <div class="row">
          <?php
          if (!empty($events)) {
              $imageIndex = 1;
              $delay = 100;
              // Output data of each row
              foreach ($events as $row) {
                  // Format dates
                  $startDate = new DateTime($row['start_date']);
                  $endDate = new DateTime($row['end_date']);
                  $dateFormat = 'M j';
                  
                  // If in the same month
                  if ($startDate->format('Y-m') === $endDate->format('Y-m')) {
                      $dateRange = $startDate->format('M j') . '-' . $endDate->format('j');
                  } else {
                      $dateRange = $startDate->format('M j') . ' - ' . $endDate->format('M j');
                  }
                  
                  // Get location from venue_id (simplified for now)
                  $location = '';
                  switch($row['venue_id']) {
                      case 1: $location = 'Budapest'; break;
                      case 2: $location = 'Zamárdi'; break;
                      case 3: $location = 'Sopron'; break;
                      case 4: $location = 'Lake Velence'; break;
                      default: $location = 'Hungary';
                  }
                  
                  // Get the event's cover image from the database
                  $imagePath = !empty($row['cover_image']) ? htmlspecialchars($row['cover_image']) : "../assets/images/portfolio/portfolio-img-1.jpg";
                  
                  // Output the festival card
                  echo '<div class="col-lg-6 mb-7">
                    <div class="portfolio d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="' . $delay . '" data-aos-duration="1000">
                      <div class="portfolio-img position-relative overflow-hidden">
                        <img src="' . $imagePath . '" alt="' . htmlspecialchars($row['name']) . '" class="img-fluid w-100">
                        <div class="portfolio-overlay">
                          <a href="festival-detail.php?id=' . $row['id'] . '" class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                            <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                          </a>
                        </div>
                      </div>
                      <div class="portfolio-details d-flex flex-column gap-3">
                        <h3 class="mb-0">' . htmlspecialchars($row['name']) . '</h3>
                        <div class="hstack gap-2">
                          <span class="badge text-dark border">' . $dateRange . '</span>
                          <span class="badge text-dark border">' . $location . '</span>';
                          
                          // Add a badge if this is a featured festival
                          if (in_array($row['name'], ['Sziget Festival', 'Balaton Sound', 'VOLT Festival'])) {
                              echo '<span class="badge bg-accent-blue text-white">Featured</span>';
                          }
                          
                        echo '</div>';
                        
                        // Add slogan if available
                        if (!empty($row['slogan'])) {
                            echo '<p class="text-muted mb-0">' . htmlspecialchars($row['slogan']) . '</p>';
                        }
                        
                      echo '</div>
                    </div>
                  </div>';
                  
                  // Update counters
                  $imageIndex++;
                  $delay += 100;
                  if ($delay > 600) $delay = 100; // Reset delay after 6 items
              }
          } else {
              echo '<div class="col-12 text-center py-5">
                <h3>No upcoming festivals at the moment.</h3>
                <p class="lead">Please check back later for updates!</p>
              </div>';
          }
          ?>
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