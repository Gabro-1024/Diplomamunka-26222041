<?php
require_once 'db_connect.php';

// Check if festival ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: festivals.php');
    exit();
}

$festival_id = (int)$_GET['id'];

try {
    $pdo = db_connect();
    
    // Query to get festival details with venue and organizer information
    $sql = "SELECT e.*, 
                   v.name as venue_name, 
                   v.address, 
                   v.city,
                   v.country,
                   v.cover_image as venue_cover,
                   v.capacity as venue_capacity,
                   u.first_name as organizer_first_name,
                   u.last_name as organizer_last_name
            FROM events e 
            LEFT JOIN venues v ON e.venue_id = v.id 
            LEFT JOIN users u ON e.organizer_id = u.id 
            WHERE e.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$festival_id]);
    $festival = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$festival) {
        header('Location: festivals.php');
        exit();
    }
    
    // Format dates
    $start_date = new DateTime($festival['start_date']);
    $end_date = new DateTime($festival['end_date']);
    $date_format = 'F j, Y';
    
    // Create Google Calendar link with minimal information
    $google_calendar_url = 'https://www.google.com/calendar/render?action=TEMPLATE';
    $google_calendar_url .= '&text=' . urlencode($festival['name']);
    $google_calendar_url .= '&dates=' . $start_date->format('Ymd\THi00\Z') . '/' . $end_date->format('Ymd\THi00\Z');
    $google_calendar_url .= '&location=' . urlencode($festival['venue_name']);
    
} catch (Exception $e) {
    error_log('Database error in festival-detail.php: ' . $e->getMessage());
    header('Location: festivals.php');
    exit();
}
?>
<!doctype html>
<html lang="en">
<!-- Header -->
<?php include 'header.php'; ?>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($festival['name']); ?> - Tickets @ Gábor</title>
  <link rel="shortcut icon" type="image/png" href="../assets/images/logos/favicon.svg" />
  <link rel="stylesheet" href="../assets/libs/owl.carousel/dist/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="../assets/libs/aos-master/dist/aos.css">
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    /* Color Variables */
    :root {
      --primary: #FF6F61;
      --primary-hover: #e65a4d;
      --secondary: #1F2A2E;
      --accent: #2210FF;
      --light-gray: #F4F8FA;
      --dark-text: #1F2A2E;
      --muted-text: rgba(31, 42, 46, 0.6);
    }
    
    /* Header Styles */
    header {
      position: sticky;
      top: 0;
      z-index: 1030;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
    }
    
    /* Ensure logo is visible */
    .logo-white {
      display: block !important;
    }
    .logo-dark {
      display: none !important;
    }
    
    /* Primary Buttons & Badges */
    .btn-primary, .badge.bg-primary {
      background-color: var(--primary) !important;
      border-color: var(--primary) !important;
      color: white !important;
    }
    
    .btn-primary:hover {
      background-color: var(--primary-hover) !important;
      border-color: var(--primary-hover) !important;
    }
    
    /* Accent Buttons */
    .btn-accent-blue, .btn-outline-primary {
      color: var(--accent) !important;
      border-color: var(--accent) !important;
    }
    
    .btn-accent-blue:hover, .btn-outline-primary:hover {
      background-color: var(--accent) !important;
      color: white !important;
    }
    
    /* Text Colors */
    .text-accent-blue {
      color: var(--accent) !important;
    }
    
    .text-muted {
      color: var(--muted-text) !important;
    }
    
    .text-dark {
      color: var(--dark-text) !important;
    }
    
    /* Card Styles */
    .card {
      border: 1px solid rgba(0, 0, 0, 0.05);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1) !important;
    }
    
    /* Accordion Styles */
    .accordion-button {
      background-color: var(--light-gray);
      color: var(--dark-text);
      font-weight: 600;
      border: none;
      box-shadow: none;
      padding: 1.25rem 1.5rem;
      transition: all 0.3s ease;
    }
    
    .accordion-button:not(.collapsed) {
      background-color: var(--primary);
      color: white;
      box-shadow: none;
    }
    
    .accordion-button:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 0.25rem rgba(255, 111, 97, 0.25);
    }
    
    .accordion-button::after {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%231F2A2E'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
      transition: transform 0.3s ease;
    }
    
    .accordion-button:not(.collapsed)::after {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='white'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
    }
    
    .accordion-item {
      border: 1px solid rgba(0, 0, 0, 0.05);
      border-radius: 0.5rem !important;
      overflow: hidden;
      margin-bottom: 0.75rem;
      background-color: white;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }
    
    .accordion-item:hover {
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }
    
    .accordion-body {
      padding: 1.25rem 1.5rem;
    }
    
    .schedule-item {
      padding: 1rem;
      border-radius: 0.5rem;
      background-color: var(--light-gray);
      margin-bottom: 1rem;
      transition: all 0.3s ease;
    }
    
    .schedule-item:last-child {
      margin-bottom: 0;
    }
    
    .schedule-item:hover {
      transform: translateX(5px);
      background-color: #e8f0fe;
    }
    
    .schedule-item h5 {
      color: var(--dark-text);
      margin-bottom: 0.5rem;
    }
    
    .schedule-item p {
      color: var(--muted-text);
      margin-bottom: 0;
    }
    
    .badge {
      font-weight: 500;
      padding: 0.4em 0.8em;
      border-radius: 1rem;
    }
    
    /* Sticky Sidebar */
    .sticky-sidebar {
      position: sticky;
      top: 100px;
      z-index: 1;
      transition: top 0.3s ease;
      height: fit-content;
    }

    @media (max-width: 991.98px) {
      .sticky-sidebar {
        position: static;
        margin-top: 2rem;
        max-height: none;
        overflow-y: visible;
      }
    }
    
    .action-buttons {
      display: flex;
      gap: 1rem;
      margin: 2rem 0;
      flex-wrap: wrap;
    }
    .btn-google-calendar {
      background-color: #4285F4;
      color: white;
      border: none;
    }
    .btn-google-calendar:hover {
      background-color: #357ABD;
      color: white;
    }
    .btn-tickets {
      background-color: #2210FF;
      color: white;
      border: none;
    }
    .btn-tickets:hover {
      background-color: #1a0dcc;
      color: white;
    }
    .festival-details {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 2rem;
      margin: 2rem 0;
    }
    .detail-item {
      margin-bottom: 1rem;
    }
    .detail-item i {
      margin-right: 0.5rem;
      color: #2210FF;
    }
  </style>
</head>


<body>


  <!--  Page Wrapper -->
  <div class="page-wrapper overflow-hidden">
    <!--  Banner Section -->
    <section class="banner-section banner-inner-section position-relative overflow-hidden d-flex align-items-end"
      style="background-image: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url(<?php echo !empty($festival['venue_cover']) ? '../' . htmlspecialchars($festival['venue_cover']) : '../assets/images/backgrounds/venues-banner.jpg'; ?>); background-size: cover; background-position: center;">
      <div class="container">
        <div class="d-flex flex-column gap-4 pb-5 pb-xl-10 position-relative z-1">
          <div class="row align-items-center">
            <div class="col-xl-6">
              <div class="d-flex align-items-center gap-4" data-aos="fade-up" data-aos-delay="100"
                data-aos-duration="1000">
                <img src="../assets/images/svgs/primary-leaf.svg" alt="" class="img-fluid animate-spin">
                <p class="mb-0 text-white fs-5 text-opacity-70">
                  <span class="text-primary"><?php echo htmlspecialchars($festival['venue_name']); ?></span> • 
                  <?php echo $start_date->format($date_format); ?>
                  <?php if ($start_date->format('Y-m-d') !== $end_date->format('Y-m-d')): ?>
                    - <?php echo $end_date->format($date_format); ?>
                  <?php endif; ?>
                </p>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-end gap-3" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
            <h1 class="mb-0 fs-15 text-white lh-1"><?php echo htmlspecialchars($festival['name']); ?></h1>
            <a href="#tickets" class="p-1 ps-7 bg-primary rounded-pill">
              <span class="bg-white round-52 rounded-circle d-flex align-items-center justify-content-center">
                <iconify-icon icon="lucide:arrow-down" class="fs-8 text-dark"></iconify-icon>
              </span>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Festival Details Section -->
    <section class="py-5 py-lg-11 py-xl-12">
      <div class="container">
        <div class="row">
          <!-- Main Content -->
          <div class="col-lg-8">
            <div class="pe-lg-5">
              <h2 class="fs-3 fw-bold mb-4">About the Festival</h2>
              <div class="mb-5">
                <?php echo nl2br(htmlspecialchars($festival['description'])); ?>
              </div>
              
              <!-- Venue Section -->
              <?php if (!empty($festival['venue_name'])): ?>
              <div class="mb-5">
                <h3 class="fs-4 fw-bold mb-4">Venue Information</h3>
                <div class="card border-0 shadow-sm">
                  <?php if (!empty($festival['venue_cover'])): ?>
                  <img src="../assets/images/venues/<?php echo htmlspecialchars($festival['venue_cover']); ?>" 
                       alt="<?php echo htmlspecialchars($festival['venue_name']); ?>" 
                       class="card-img-top" style="max-height: 300px; object-fit: cover;">
                  <?php endif; ?>
                  <div class="card-body">
                    <h4 class="fs-5 fw-bold mb-3"><?php echo htmlspecialchars($festival['venue_name']); ?></h4>
                    
                    <div class="venue-details">
                      <!-- Address -->
                      <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="text-primary mt-1">
                          <iconify-icon icon="solar:map-point-wave-bold" class="fs-5"></iconify-icon>
                        </div>
                        <div>
                          <p class="mb-0 fw-medium">Location</p>
                          <p class="mb-0"><?php echo htmlspecialchars($festival['address']); ?></p>
                          <p class="mb-0 text-muted">
                            <?php 
                            $location_parts = [];
                            if (!empty($festival['city'])) $location_parts[] = $festival['city'];
                            if (!empty($festival['country'])) $location_parts[] = $festival['country'];
                            echo htmlspecialchars(implode(', ', $location_parts));
                            ?>
                          </p>
                        </div>
                      </div>
                      
                      <?php if (!empty($festival['venue_capacity'])): ?>
                      <!-- Capacity -->
                      <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="text-primary">
                          <iconify-icon icon="solar:users-group-rounded-bold" class="fs-5"></iconify-icon>
                        </div>
                        <div>
                          <p class="mb-0 fw-medium">Capacity</p>
                          <p class="mb-0"><?php echo number_format($festival['venue_capacity']); ?> people</p>
                        </div>
                      </div>
                      <?php endif; ?>
                      
                      <!-- Get Directions Button -->
                      <div class="mt-4">
                        <a href="https://www.google.com/maps/dir//<?php echo urlencode($festival['address'] . ', ' . $festival['city'] . ', ' . $festival['country']); ?>" 
                           target="_blank" class="btn btn-outline-primary">
                          <i class="fas fa-directions me-2"></i>Get Directions
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php endif; ?>

              <!-- Lineup / Performers Section -->
              <?php if (!empty($festival['lineup'])): ?>
              <div class="mb-5">
                <h3 class="fs-4 fw-bold mb-4">Lineup</h3>
                <div class="row g-4">
                  <?php 
                  $performers = explode(',', $festival['lineup']);
                  foreach (array_slice($performers, 0, 4) as $performer): 
                  ?>
                  <div class="col-md-6">
                    <div class="d-flex align-items-center gap-3">
                      <div class="rounded-circle bg-light" style="width: 60px; height: 60px; overflow: hidden;">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode(trim($performer)); ?>" 
                             alt="<?php echo htmlspecialchars(trim($performer)); ?>" 
                             class="img-fluid">
                      </div>
                      <div>
                        <h4 class="mb-0 fs-5 fw-bold"><?php echo htmlspecialchars(trim($performer)); ?></h4>
                        <span class="text-muted">Headliner</span>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                  <?php if (count($performers) > 4): ?>
                  <div class="col-12">
                    <button class="btn btn-link text-primary p-0">+<?php echo (count($performers) - 4); ?> more artists</button>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>
              
              <!-- Schedule Section -->
              <div class="mb-5">
                <h3 class="fs-4 fw-bold mb-4">Schedule</h3>
                <div class="accordion" id="scheduleAccordion">
                  <?php
                  // Generate schedule based on festival dates
                  $interval = new DateInterval('P1D');
                  $period = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));
                  $dayCount = 1;
                  
                  foreach ($period as $date):
                      $dayName = $date->format('l');
                      $formattedDate = $date->format('F j, Y');
                      $dayId = 'day' . $dayCount;
                  ?>
                  <div class="accordion-item mb-3 border-0">
                    <h2 class="accordion-header" id="heading<?php echo $dayCount; ?>">
                      <button class="accordion-button bg-light rounded-3 p-4 fw-bold" type="button"
                              data-bs-toggle="collapse" data-bs-target="#<?php echo $dayId; ?>"
                              aria-expanded="<?php echo $dayCount === 1 ? 'true' : 'false'; ?>"
                              aria-controls="<?php echo $dayId; ?>">
                        <?php echo $dayName; ?>, <?php echo $formattedDate; ?>
                      </button>
                    </h2>
                    <div id="<?php echo $dayId; ?>" 
                         class="accordion-collapse collapse <?php echo $dayCount === 1 ? 'show' : ''; ?>" 
                         aria-labelledby="heading<?php echo $dayCount; ?>" 
                         data-bs-parent="#scheduleAccordion">
                      <div class="accordion-body p-4">
                        <div class="schedule-item mb-3">
                          <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0 fw-bold">Main Stage</h5>
                            <span class="badge bg-primary">12:00 PM - 11:00 PM</span>
                          </div>
                          <p class="mb-0">Full day of performances by various artists</p>
                        </div>
                        <hr>
                        <div class="schedule-item">
                          <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0 fw-bold">Food & Drinks</h5>
                            <span class="badge bg-primary">11:00 AM - 12:00 AM</span>
                          </div>
                          <p class="mb-0">Local food trucks and bars open all day</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php 
                      $dayCount++;
                  endforeach; 
                  ?>
                </div>
              </div>
              
              <!-- Location Section -->
              <div id="location" class="mb-5">
                <h3 class="fs-4 fw-bold mb-4">Location</h3>
                <div class="card border-0 shadow-sm">
                  <div class="card-body">
                    <?php if (!empty($festival['venue_name'])): ?>
                      <h4 class="fs-5 fw-bold"><?php echo htmlspecialchars($festival['venue_name']); ?></h4>
                      <p class="mb-2">
                        <i class="fas fa-map-marker-alt text-accent-blue"></i>
                        <?php 
                        $address_parts = array_filter([
                            $festival['address'],
                            $festival['city'],
                            $festival['country']
                        ]);
                        echo htmlspecialchars(implode(', ', $address_parts));
                        ?>
                      </p>
                      <?php if (!empty($festival['venue_capacity'])): ?>
                      <p class="mb-3">
                        <i class="fas fa-users text-muted"></i>
                        <span class="ms-1">Capacity: <?php echo number_format($festival['venue_capacity']); ?> people</span>
                      </p>
                      <?php endif; ?>
                      <a href="https://www.google.com/maps/dir//<?php echo urlencode($festival['address'] . ', ' . $festival['city'] . ', ' . $festival['country']); ?>"
                         target="_blank" class="btn btn-accent-blue">
                        <i class="fas fa-directions me-2"></i>Get Directions on Google Maps
                      </a>
                    <?php else: ?>
                      <div class="text-center py-4">
                        <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">Venue information will be announced soon.</p>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Sidebar with Action Buttons -->
          <div class="col-lg-4 mt-5 mt-lg-0">
            <div class="sticky-sidebar">
              <div class="card border-0 shadow-sm p-4">
                <h3 class="fs-5 fw-bold mb-4" id="tickets">Get Your Tickets</h3>
                
                <!-- Date & Time -->
                <div class="mb-4">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="far fa-calendar-alt"></i>
                    <span class="fw-medium">Date & Time</span>
                  </div>
                  <p class="mb-0">
                    <?php if ($start_date->format('Y-m-d') === $end_date->format('Y-m-d')): ?>
                      <?php echo $start_date->format('l, F j, Y'); ?><br>
                      <?php echo $start_date->format('g:i A'); ?> - <?php echo $end_date->format('g:i A'); ?>
                    <?php else: ?>
                      <strong>Start:</strong> <?php echo $start_date->format('l, F j, Y g:i A'); ?><br>
                      <strong>End:</strong> <?php echo $end_date->format('l, F j, Y g:i A'); ?>
                    <?php endif; ?>
                  </p>
                </div>
                
                <!-- Location -->
                <div class="mb-4">
                  <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="fas fa-map-marker-alt text-accent-blue"></i>
                    <span class="fw-bold">Location</span>
                  </div>
                  <div class="venue-info">
                    <h4 class="h5 fw-bold text-accent-blue mb-2"><?php echo htmlspecialchars($festival['venue_name']); ?></h4>
                    <div class="d-flex align-items-start gap-2 mb-2">
                      <div>
                        <p class="mb-0"><?php echo htmlspecialchars($festival['address']); ?></p>
                        <p class="text-muted mb-0">
                          <?php 
                          $location_parts = [];
                          if (!empty($festival['city'])) $location_parts[] = $festival['city'];
                          if (!empty($festival['country'])) $location_parts[] = $festival['country'];
                          echo htmlspecialchars(implode(', ', $location_parts));
                          ?>
                        </p>
                      </div>
                    </div>
                    <?php if (!empty($festival['venue_capacity'])): ?>
                    <div class="d-flex align-items-center gap-2 text-muted mb-3">
                      <i class="fas fa-users"></i>
                      <span>Capacity: <?php echo number_format($festival['venue_capacity']); ?> people</span>
                    </div>
                    <?php endif; ?>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($festival['address'] . ', ' . $festival['city'] . ', ' . $festival['country']); ?>" 
                       target="_blank" 
                       class="btn btn-outline-accent-blue w-100">
                      <i class="fas fa-directions me-2"></i>Get Directions
                    </a>
                  </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                  <a href="<?php echo $google_calendar_url; ?>" 
                     target="_blank" 
                     class="btn btn-google-calendar flex-grow-1 d-flex align-items-center justify-content-center"
                     data-aos="fade-up" data-aos-delay="100">
                    <iconify-icon icon="solar:calendar-add-bold" class="me-2 fs-5"></iconify-icon>
                    <span>Add to Calendar</span>
                  </a>
                  
                  <a href="#" 
                     class="btn btn-tickets flex-grow-1 d-flex align-items-center justify-content-center"
                     data-aos="fade-up" data-aos-delay="200"
                     onclick="window.location.href='tickets.php?event_id=<?php echo $festival_id; ?>'; return false;">
                    <iconify-icon icon="solar:ticket-bold" class="me-2 fs-5"></iconify-icon>
                    <span>Buy Tickets</span>
                  </a>
                </div>
                
                <!-- Share Buttons -->
                <div class="mt-4">
                  <p class="fw-medium mb-2">Share this event:</p>
                  <div class="d-flex gap-2">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"); ?>" 
                       target="_blank" class="btn btn-outline-secondary btn-sm rounded-circle">
                      <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"); ?>&text=<?php echo urlencode('Check out ' . $festival['name'] . '!'); ?>" 
                       target="_blank" class="btn btn-outline-secondary btn-sm rounded-circle">
                      <i class="fab fa-twitter"></i>
                    </a>
                    <a href="whatsapp://send?text=<?php echo urlencode('Check out ' . $festival['name'] . ' - ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"); ?>" 
                       target="_blank" class="btn btn-outline-secondary btn-sm rounded-circle">
                      <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="mailto:?subject=<?php echo urlencode($festival['name']); ?>&body=<?php echo urlencode('Check out this event: ' . $festival['name'] . ' - ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"); ?>" 
                       class="btn btn-outline-secondary btn-sm rounded-circle">
                      <i class="far fa-envelope"></i>
                    </a>
                  </div>
                </div>
              </div>
              
              <!-- Organizer Info -->
              <div class="card border-0 shadow-sm mt-4 p-4">
                <h4 class="fs-5 fw-bold mb-3">Organizer</h4>
                <div class="d-flex align-items-center gap-3">
                  <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" 
                       style="width: 60px; height: 60px;">
                    <i class="fas fa-user-tie fs-4 text-muted"></i>
                  </div>
                  <div>
                    <h5 class="mb-0 fw-bold">
                      <?php 
                      $organizer_name = trim($festival['organizer_first_name'] . ' ' . $festival['organizer_last_name']);
                      echo !empty($organizer_name) ? htmlspecialchars($organizer_name) : 'Event Organizer'; 
                      ?>
                    </h5>
                    <p class="mb-0 text-muted">Event Organizer</p>
                  </div>
                </div>
                <a href="#" class="btn btn-outline-primary btn-sm mt-3 w-100">
                  <i class="far fa-envelope me-2"></i>Contact Organizer
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <!-- Footer -->
  <?php include 'footer.php'; ?>

  <div class="get-template hstack gap-2">
    <button class="btn bg-primary p-2 round-52 rounded-circle hstack justify-content-center flex-shrink-0"
      id="scrollToTopBtn">
      <iconify-icon icon="lucide:arrow-up" class="fs-7 text-dark"></iconify-icon>
    </button>
  </div>

  <!-- Scripts -->
  <script src="../assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="../assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/libs/owl.carousel/dist/owl.carousel.min.js"></script>
  <script src="../assets/libs/aos-master/dist/aos.js"></script>
  <script src="../assets/js/custom.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
</body>

</html>