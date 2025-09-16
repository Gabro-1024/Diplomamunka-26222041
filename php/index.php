<?php
// Handle Stripe success callback: /php/index.php?payment=success&session_id=...
require_once __DIR__ . '/includes/auth_check.php'; // starts session

if (isset($_GET['payment']) && $_GET['payment'] === 'success' && !empty($_GET['session_id'])) {
    $sessionId = $_GET['session_id'];
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
    $purchaseLog = $logDir . '/purchases.log';

    try {
        // Load Composer and env
        require_once __DIR__ . '/../vendor/autoload.php';
        $projectRoot = realpath(__DIR__ . '/..');
        if ($projectRoot && file_exists($projectRoot . '/.env')) {
            $dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
            $dotenv->load();
        }
        $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');
        if (!$secretKey) { throw new Exception('Stripe key missing'); }
        \Stripe\Stripe::setApiKey($secretKey);

        // Retrieve session
        $session = \Stripe\Checkout\Session::retrieve($sessionId);
        $paid = ($session->payment_status === 'paid');
        $currency = strtolower($session->currency);
        $amountTotalMinor = (int)($session->amount_total ?? 0);
        // Convert to major units for DB storing. For HUF we treat minor=2 decimals as used in checkout
        $divisor = ($currency === 'huf') ? 100 : 100;
        $amountMajor = $amountTotalMinor / $divisor;
        $status = $paid ? 'completed' : 'failed';

        // Insert into purchases
        require_once __DIR__ . '/includes/db_connect.php';
        $pdo = db_connect();
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        if (!$userId) { throw new Exception('User not authenticated for purchase record'); }

        // Prevent duplicate insert within this browser session
        if (!isset($_SESSION['recorded_sessions'])) { $_SESSION['recorded_sessions'] = []; }
        if (isset($_SESSION['recorded_sessions'][$sessionId])) {
            // already recorded in this session
        } else {
            $stmt = $pdo->prepare('INSERT INTO purchases (user_id, amount, status, payment_method) VALUES (?, ?, ?, ?)');
            $stmt->execute([$userId, $amountMajor, $status, 'stripe']);
            $_SESSION['recorded_sessions'][$sessionId] = true;
        }

        // Optional: log
        $logData = [
            'time' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'session_id' => $sessionId,
            'currency' => $currency,
            'amount_total_minor' => $amountTotalMinor,
            'amount_stored' => $amountMajor,
            'status' => $status,
            'payment_method' => 'stripe',
        ];
        @file_put_contents($purchaseLog, json_encode($logData, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

        // Show a small flash message (optional)
        $_SESSION['payment_message'] = $paid ? 'Payment completed successfully.' : 'Payment not completed.';

    } catch (Throwable $e) {
        $errData = [
            'time' => date('Y-m-d H:i:s'),
            'session_id' => $sessionId,
            'error' => $e->getMessage(),
        ];
        @file_put_contents($purchaseLog, json_encode($errData, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
        // Do not block page render; just continue
    }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tickets @ Gábor</title>
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
    <section class="banner-section position-relative d-flex align-items-end min-vh-100">
      <video class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover" autoplay muted loop playsinline>
        <source src="../assets/images/backgrounds/banner-video.mp4" type="video/mp4" />
      </video>
      <div class="container">
        <div class="d-flex flex-column gap-4 pb-8 position-relative z-1">
          <div class="row align-items-center">
            <div class="col-xl-4">
              <div class="d-flex align-items-center gap-4" data-aos="fade-up" data-aos-delay="100"
                data-aos-duration="1000">
                <img src="../assets/images/svgs/primary-leaf.svg" alt="" class="img-fluid animate-spin">
                  <p class="mb-0 text-white fs-5 text-opacity-70">Discover <span class="text-primary">
                    the best festival tickets</span> in one place - experiences you'll remember forever.</p>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-end gap-3" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
            <h1 class="mb-0 fs-16 text-white lh-1">Tickets @ Gábor</h1>
            <a href="about-us.php" class="p-1 ps-7 bg-primary rounded-pill">
              <span class="bg-white round-52 rounded-circle d-flex align-items-center justify-content-center">
                <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
              </span>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!--  Stats & Facts Section -->
    <section class="stats-facts py-5 py-lg-11 py-xl-12 position-relative overflow-hidden">
      <div class="container">
        <div class="row gap-7 gap-xl-0">
          <div class="col-xl-4 col-xxl-4">
            <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
              data-aos-duration="1000">
              <span
                class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">01</span>
              <hr class="border-line">
              <span class="badge badge-accent-blue">Statistics</span>
            </div>
          </div>
          <div class="col-xl-8 col-xxl-7">
            <div class="d-flex flex-column gap-9">
              <div class="row">
                <div class="col-xxl-8">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                    <h2 class="mb-0">Hungary's leading festival ticket seller.</h2>
                      <p class="fs-5 mb-0">Secure your spot at the biggest names and most unforgettable experiences
                          - with simple, fast and reliable ticket purchasing.</p>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 col-lg-4 mb-7 mb-lg-0">
                  <div class="d-flex flex-column gap-6 pt-9 border-top" data-aos="fade-up" data-aos-delay="200"
                    data-aos-duration="1000">
                    <h2 class="mb-0 fs-14"><span class="count" data-target="40">40</span>K+</h2>
                    <p class="mb-0">Festival tickets sold</p>
                  </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-7 mb-lg-0">
                  <div class="d-flex flex-column gap-6 pt-9 border-top" data-aos="fade-up" data-aos-delay="300"
                    data-aos-duration="1000">
                    <h2 class="mb-0 fs-14"><span class="count" data-target="238">25</span>+</h2>
                    <p class="mb-0">Partner festivals</p>
                  </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-7 mb-lg-0">
                  <div class="d-flex flex-column gap-6 pt-9 border-top" data-aos="fade-up" data-aos-delay="400"
                    data-aos-duration="1000">
                    <h2 class="mb-0 fs-14"><span class="count" data-target="3">99</span>%</h2>
                    <p class="mb-0">Satisfied customers</p>
                  </div>
                </div>
              </div>
              <a href="about-us.php" class="btn" data-aos="fade-up" data-aos-delay="500" data-aos-duration="1000">
                <span class="btn-text">More info about us</span>
                <iconify-icon icon="lucide:arrow-up-right"
                  class="btn-icon bg-white text-dark round-52 rounded-circle hstack justify-content-center fs-7 shadow-sm"></iconify-icon>
              </a>
            </div>
          </div>
        </div>
      </div>
      <div class="position-absolute bottom-0 start-0" data-aos="zoom-in" data-aos-delay="100" data-aos-duration="1000">
        <img src="../assets/images/backgrounds/stats-facts-bg.svg" alt="" class="img-fluid">
      </div>
    </section>

    <!--  Featured Projects Section -->
    <section class="featured-projects py-5 py-lg-11 py-xl-12 bg-light-gray">
      <div class="d-flex flex-column gap-5 gap-xl-11">
        <div class="container">
          <div class="row gap-7 gap-xl-0">
            <div class="col-xl-4 col-xxl-4">
              <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
                data-aos-duration="1000">
                <span
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">02</span>
                <hr class="border-line">
                <span class="badge badge-accent-blue">Events</span>
              </div>
            </div>
            <div class="col-xl-8 col-xxl-7">
              <div class="row">
                <div class="col-xxl-8">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                      <h2 class="mb-0">Our featured festivals</h2>
                      <p class="fs-5 mb-0">Discover our most popular festivals - the best music
                          festivals you've been waiting for.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="featured-projects-slider px-3">
          <div class="owl-carousel owl-theme">
            <div class="item">
              <div class="portfolio d-flex flex-column gap-6">
                <div class="portfolio-img position-relative overflow-hidden">
                  <img src="../assets/images/portfolio/portfolio-img-1.jpg" alt="" class="img-fluid">
                  <div class="portfolio-overlay">
                    <a href="projects-detail.html"
                      class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                      <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                    </a>
                  </div>
                </div>
                <div class="portfolio-details d-flex flex-column gap-3">
                  <h3 class="mb-0">Sziget Festival</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">August 7-13</span>
                    <span class="badge text-dark border">Budapest</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="item">
              <div class="portfolio d-flex flex-column gap-6">
                <div class="portfolio-img position-relative overflow-hidden">
                  <img src="../assets/images/portfolio/portfolio-img-2.jpg" alt="" class="img-fluid">
                  <div class="portfolio-overlay">
                    <a href="projects-detail.html"
                      class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                      <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                    </a>
                  </div>
                </div>
                <div class="portfolio-details d-flex flex-column gap-3">
                  <h3 class="mb-0">Balaton Sound</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">June 26-30</span>
                    <span class="badge text-dark border">Zamárdi</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="item">
              <div class="portfolio d-flex flex-column gap-6">
                <div class="portfolio-img position-relative overflow-hidden">
                  <img src="../assets/images/portfolio/portfolio-img-3.jpg" alt="" class="img-fluid">
                  <div class="portfolio-overlay">
                    <a href="projects-detail.html"
                      class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                      <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                    </a>
                  </div>
                </div>
                <div class="portfolio-details d-flex flex-column gap-3">
                  <h3 class="mb-0">VOLT Festival</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">June 19-23</span>
                    <span class="badge text-dark border">Sopron</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="item">
              <div class="portfolio d-flex flex-column gap-6">
                <div class="portfolio-img position-relative overflow-hidden">
                  <img src="../assets/images/portfolio/portfolio-img-4.jpg" alt="" class="img-fluid">
                  <div class="portfolio-overlay">
                    <a href="projects-detail.html"
                      class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                      <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                    </a>
                  </div>
                </div>
                <div class="portfolio-details d-flex flex-column gap-3">
                  <h3 class="mb-0">EFOTT</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">July 10-14</span>
                    <span class="badge text-dark border">Tapolca</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="item">
              <div class="portfolio d-flex flex-column gap-6">
                <div class="portfolio-img position-relative overflow-hidden">
                  <img src="../assets/images/portfolio/portfolio-img-5.jpg" alt="" class="img-fluid">
                  <div class="portfolio-overlay">
                    <a href="projects-detail.html"
                      class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                      <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                    </a>
                  </div>
                </div>
                <div class="portfolio-details d-flex flex-column gap-3">
                  <h3 class="mb-0">SZIN Festival</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">August 27-31</span>
                    <span class="badge text-dark border">Szeged</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!--  Services Section -->
<!--    <section class="services py-5 py-lg-11 py-xl-12 bg-dark" id="services">-->
<!--      <div class="container">-->
<!--        <div class="d-flex flex-column gap-5 gap-xl-10">-->
<!--          <div class="row gap-7 gap-xl-0">-->
<!--            <div class="col-xl-4 col-xxl-4">-->
<!--              <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"-->
<!--                data-aos-duration="1000">-->
<!--                <span-->
<!--                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">03</span>-->
<!--                <hr class="border-line bg-white">-->
<!--                <span class="badge text-dark bg-white">Services</span>-->
<!--              </div>-->
<!--            </div>-->
<!--            <div class="col-xl-8 col-xxl-7">-->
<!--              <div class="row">-->
<!--                <div class="col-xxl-8">-->
<!--                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"-->
<!--                    data-aos-duration="1000">-->
<!--                    <h2 class="mb-0 text-white">What we do</h2>-->
<!--                    <p class="fs-5 mb-0 text-white text-opacity-70">A glimpse into our creativity—exploring innovative-->
<!--                      designs, successful collaborations, and transformative digital experiences.</p>-->
<!--                  </div>-->
<!--                </div>-->
<!--              </div>-->
<!--            </div>-->
<!--          </div>-->
<!--          <div class="services-tab">-->
<!--            <div class="row gap-5 gap-xl-0">-->
<!--              <div class="col-xl-4">-->
<!--                <div class="tab-content" data-aos="zoom-in" data-aos-delay="100" data-aos-duration="1000">-->
<!--                  <div class="tab-pane active" id="one" role="tabpanel" aria-labelledby="one-tab" tabindex="0">-->
<!--                    <img src="../assets/images/services/services-img-1.jpg" alt="services" class="img-fluid">-->
<!--                  </div>-->
<!--                  <div class="tab-pane" id="two" role="tabpanel" aria-labelledby="two-tab" tabindex="0">-->
<!--                    <img src="../assets/images/services/services-img-2.jpg" alt="services" class="img-fluid">-->
<!--                  </div>-->
<!--                  <div class="tab-pane" id="three" role="tabpanel" aria-labelledby="three-tab" tabindex="0">-->
<!--                    <img src="../assets/images/services/services-img-3.jpg" alt="services" class="img-fluid">-->
<!--                  </div>-->
<!--                  <div class="tab-pane" id="four" role="tabpanel" aria-labelledby="four-tab" tabindex="0">-->
<!--                    <img src="../assets/images/services/services-img-4.jpg" alt="services" class="img-fluid">-->
<!--                  </div>-->
<!--                </div>-->
<!--              </div>-->
<!--              <div class="col-xl-8">-->
<!--                <div class="d-flex flex-column gap-5">-->
<!--                  <ul class="nav nav-tabs" id="myTab" role="tablist" data-aos="fade-up" data-aos-delay="200"-->
<!--                    data-aos-duration="1000">-->
<!--                    <li-->
<!--                      class="nav-item py-4 py-lg-8 border-top border-white border-opacity-10 d-flex align-items-center w-100"-->
<!--                      role="presentation">-->
<!--                      <div class="row w-100 align-items-center gx-3">-->
<!--                        <div class="col-lg-6 col-xxl-5">-->
<!--                          <button class="nav-link fs-10 fw-bold py-1 px-0 border-0 rounded-0 flex-shrink-0 active"-->
<!--                            id="one-tab" data-bs-toggle="tab" data-bs-target="#one" type="button" role="tab"-->
<!--                            aria-controls="one" aria-selected="true">Brand identity</button>-->
<!--                        </div>-->
<!--                        <div class="col-lg-6 col-xxl-7">-->
<!--                          <p class="text-white text-opacity-70 mb-0">-->
<!--                            When selecting a web design agency, it's essential to consider its reputation, experience,-->
<!--                            and-->
<!--                            the-->
<!--                            specific needs of your project.-->
<!--                          </p>-->
<!--                        </div>-->
<!--                      </div>-->
<!--                    </li>-->
<!--                    <li-->
<!--                      class="nav-item py-4 py-lg-8 border-top border-white border-opacity-10 d-flex align-items-center w-100"-->
<!--                      role="presentation">-->
<!--                      <div class="row w-100 align-items-center gx-3">-->
<!--                        <div class="col-lg-6 col-xxl-5">-->
<!--                          <button class="nav-link fs-10 fw-bold py-1 px-0 border-0 rounded-0 flex-shrink-0" id="two-tab"-->
<!--                            data-bs-toggle="tab" data-bs-target="#two" type="button" role="tab" aria-controls="two"-->
<!--                            aria-selected="false">Web development</button>-->
<!--                        </div>-->
<!--                        <div class="col-lg-6 col-xxl-7">-->
<!--                          <p class="text-white text-opacity-70 mb-0">-->
<!--                            When selecting a web design agency, it's essential to consider its reputation, experience,-->
<!--                            and-->
<!--                            the-->
<!--                            specific needs of your project.-->
<!--                          </p>-->
<!--                        </div>-->
<!--                      </div>-->
<!--                    </li>-->
<!--                    <li-->
<!--                      class="nav-item py-4 py-lg-8 border-top border-white border-opacity-10 d-flex align-items-center w-100"-->
<!--                      role="presentation">-->
<!--                      <div class="row w-100 align-items-center gx-3">-->
<!--                        <div class="col-lg-6 col-xxl-5">-->
<!--                          <button class="nav-link fs-10 fw-bold py-1 px-0 border-0 rounded-0 flex-shrink-0"-->
<!--                            id="three-tab" data-bs-toggle="tab" data-bs-target="#three" type="button" role="tab"-->
<!--                            aria-controls="three" aria-selected="false">Content creation</button>-->
<!--                        </div>-->
<!--                        <div class="col-lg-6 col-xxl-7">-->
<!--                          <p class="text-white text-opacity-70 mb-0">-->
<!--                            When selecting a web design agency, it's essential to consider its reputation, experience,-->
<!--                            and-->
<!--                            the-->
<!--                            specific needs of your project.-->
<!--                          </p>-->
<!--                        </div>-->
<!--                      </div>-->
<!--                    </li>-->
<!--                    <li-->
<!--                      class="nav-item py-4 py-lg-8 border-top border-white border-opacity-10 d-flex align-items-center w-100"-->
<!--                      role="presentation">-->
<!--                      <div class="row w-100 align-items-center gx-3">-->
<!--                        <div class="col-lg-6 col-xxl-5">-->
<!--                          <button class="nav-link fs-10 fw-bold py-1 px-0 border-0 rounded-0 flex-shrink-0"-->
<!--                            id="four-tab" data-bs-toggle="tab" data-bs-target="#four" type="button" role="tab"-->
<!--                            aria-controls="four" aria-selected="false">Motion & 3d modeling</button>-->
<!--                        </div>-->
<!--                        <div class="col-lg-6 col-xxl-7">-->
<!--                          <p class="text-white text-opacity-70 mb-0">-->
<!--                            When selecting a web design agency, it's essential to consider its reputation, experience,-->
<!--                            and-->
<!--                            the-->
<!--                            specific needs of your project.-->
<!--                          </p>-->
<!--                        </div>-->
<!--                      </div>-->
<!--                    </li>-->
<!--                  </ul>-->
<!--                  <a href="projects.html" class="btn border border-white border-opacity-25" data-aos="fade-up"-->
<!--                    data-aos-delay="300" data-aos-duration="1000">-->
<!--                    <span class="btn-text">See our Work</span>-->
<!--                    <iconify-icon icon="lucide:arrow-up-right"-->
<!--                      class="btn-icon bg-white text-dark round-52 rounded-circle hstack justify-content-center fs-7 shadow-sm"></iconify-icon>-->
<!--                  </a>-->
<!--                </div>-->
<!--              </div>-->
<!--            </div>-->
<!--          </div>-->
<!--        </div>-->
<!--      </div>-->
<!--    </section>-->

    <!--  Why choose us Section -->
    <section class="why-choose-us py-5 py-lg-11 py-xl-12">
      <div class="container">
        <div class="row justify-content-between gap-5 gap-xl-0">
          <div class="col-xl-3 col-xxl-3">
            <div class="d-flex flex-column gap-7">
              <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
                data-aos-duration="1000">
                <span
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">03</span>
                <hr class="border-line">
                <span class="badge badge-accent-blue">About us</span>
              </div>
                <h2 class="mb-0" data-aos="fade-right" data-aos-delay="200" data-aos-duration="1000">Why choose us?</h2>
                <p class="mb-0 fs-5" data-aos="fade-right" data-aos-delay="300" data-aos-duration="1000">Guaranteed best
                    prices, instant delivery and excellent customer support when purchasing festival tickets.</p>
            </div>
          </div>
          <div class="col-xl-9 col-xxl-8">
            <div class="row">
              <div class="col-lg-4 mb-7 mb-lg-0">
                <div class="card position-relative overflow-hidden bg-primary h-100" data-aos="fade-up"
                  data-aos-delay="100" data-aos-duration="1000">
                  <div class="card-body d-flex flex-column justify-content-between">
                    <div class="d-flex flex-column gap-3 position-relative z-1">
                      <ul class="list-unstyled mb-0 hstack gap-1">
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-dark"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-dark"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-dark"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-dark"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-dark"></iconify-icon></a></li>
                      </ul>
                      <p class="mb-0 fs-6 text-dark">Fast and hassle-free ticket purchase with instant e-ticket delivery.</p>
                    </div>
                    <div class="position-relative z-1">
                      <div class="pb-6 border-bottom">
                        <h2 class="mb-0">99%</h2>
                        <p class="mb-0">Customer satisfaction</p>
                      </div>
                      <div class="hstack gap-6 pt-6">
                        <img src="../assets/images/profile/avatar-1.png" alt=""
                          class="img-fluid rounded-circle overflow-hidden flex-shrink-0" width="64" height="64">
                        <div>
                          <h5 class="mb-0">Kovács Eszter</h5>
                          <p class="mb-0">Budapest</p>
                        </div>
                      </div>
                    </div>
                    <div class="position-absolute bottom-0 end-0">
                      <img src="../assets/images/backgrounds/customer-satisfaction-bg.svg" alt="" class="img-fluid">
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4 mb-7 mb-lg-0">
                <div class="d-flex flex-column gap-7" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
                  <div class="position-relative">
                    <img src="../assets/images/services/services-img-2.jpg" alt="" class="img-fluid w-100">
                  </div>

                  <div class="card bg-dark">
                    <div class="card-body d-flex flex-column gap-7">
                      <div>
                        <h2 class="mb-0 text-white">25+</h2>
                        <p class="mb-0 text-white text-opacity-70">Partners</p>
                      </div>
                      <ul class="d-flex align-items-center mb-0">
                        <li>
                          <a href="javascript:void(0)">
                            <img src="../assets/images/profile/user-1.jpg" width="44" height="44"
                              class="rounded-circle border border-2 border-dark" alt="user-1">
                          </a>
                        </li>
                        <li class="ms-n2">
                          <a href="javascript:void(0)">
                            <img src="../assets/images/profile/user-2.jpg" width="44" height="44"
                              class="rounded-circle border border-2 border-dark" alt="user-2">
                          </a>
                        </li>
                        <li class="ms-n2">
                          <a href="javascript:void(0)">
                            <img src="../assets/images/profile/user-3.jpg" width="44" height="44"
                              class="rounded-circle border border-2 border-dark" alt="user-3">
                          </a>
                        </li>
                        <li class="ms-n2">
                          <a href="javascript:void(0)">
                            <img src="../assets/images/profile/user-4.jpg" width="44" height="44"
                              class="rounded-circle border border-2 border-dark" alt="user-4">
                          </a>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4 mb-7 mb-lg-0">
                <div class="card border h-100 position-relative overflow-hidden" data-aos="fade-up" data-aos-delay="300"
                  data-aos-duration="1000">
                  <span
                    class="border rounded-circle round-490 d-block position-absolute top-0 start-50 translate-middle"></span>
                  <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                      <h2 class="mb-0">0%</h2>
                      <p class="mb-0 text-dark">Transaction fee</p>
                    </div>
                    <div class="d-flex flex-column gap-3">
                      <a href="http://localhost:63342/Diplomamunka-26222041/php/index.php" class="logo-dark">
                        <img src="http://localhost:63342/Diplomamunka-26222041/assets/images/logos/logo-white.svg" alt="logo" class="img-fluid">
                      </a>
                      <p class="mb-0 fs-5 text-dark">No hidden costs - all ticket prices are final, without transaction fees.</p>
                    </div>
                  </div>
                  <span
                    class="border rounded-circle round-490 d-block position-absolute top-100 start-50 translate-middle"></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!--  Testimonial Section -->
    <section class="testimonial py-5 py-lg-11 py-xl-12 bg-light-gray">
      <div class="container">
        <div class="d-flex flex-column gap-5 gap-xl-11">
          <div class="row gap-7 gap-xl-0">
            <div class="col-xl-4 col-xxl-4">
              <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
                data-aos-duration="1000">
                <span
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">04</span>
                <hr class="border-line bg-white">
                <span class="badge badge-accent-blue">Reviews</span>
              </div>
            </div>
            <div class="col-xl-8 col-xxl-7">
              <div class="row">
                <div class="col-xxl-8">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                      <h2 class="mb-0">Our customers' reviews</h2>
                      <p class="fs-5 mb-0 text-opacity-70">Read what those who have already purchased festival tickets
                          from us say about us.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row gap-7 gap-lg-0">
            <div class="col-lg-4 col-xl-3 d-flex align-items-stretch">
              <div class="card bg-primary w-100" data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000">
                <div class="card-body d-flex flex-column gap-5 gap-xl-11 justify-content-between">
                  <div class="d-flex flex-column gap-4">
                    <p class="mb-0">Hear from them</p>
                    <h4 class="mb-0">The ticket purchase was fast and seamless, I received my e-ticket immediately!</h4>
                  </div>
                  <div class="hstack gap-3">
                    <img src="../assets/images/testimonial/testimonial-1.jpg" alt=""
                      class="img-fluid rounded-circle overflow-hidden flex-shrink-0" width="60" height="60">
                    <div>
                      <h5 class="mb-1 fw-normal">Nagy Adrián</h5>
                      <p class="mb-0">Debrecen</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-xl-6 d-flex align-items-stretch">
              <div class="card bg-dark w-100" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
                <div class="card-body d-flex flex-column gap-5 gap-xl-11 justify-content-between">
                  <div class="d-flex flex-column gap-4">
                    <p class="mb-0 text-white text-opacity-70">Hear from them</p>
                    <h4 class="mb-0 text-white pe-xl-2">I found the best prices here,
                        and the customer service was excellent when I had a question.</h4>
                    <div class="hstack gap-2">
                      <ul class="list-unstyled mb-0 hstack gap-1">
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-white"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-white"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-white"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-white"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-white"></iconify-icon></a></li>
                      </ul>
                      <h6 class="mb-0 text-white fw-medium">5.0</h6>
                    </div>
                  </div>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="hstack gap-3">
                      <img src="../assets/images/testimonial/testimonial-2.jpg" alt=""
                        class="img-fluid rounded-circle overflow-hidden flex-shrink-0" width="60" height="60">
                      <div>
                        <h5 class="mb-1 fw-normal text-white">Horváth Béla</h5>
                        <p class="mb-0 text-white text-opacity-70">Szeged</p>
                      </div>
                    </div>
                    <span><img src="../assets/images/testimonial/quete.svg" alt="quete"
                        class="img-fluid flex-shrink-0"></span>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-xl-3 d-flex align-items-stretch">
              <div class="card w-100" data-aos="fade-up" data-aos-delay="300" data-aos-duration="1000">
                <div class="card-body d-flex flex-column gap-5 gap-xl-11 justify-content-between">
                  <div class="d-flex flex-column gap-4">
                    <p class="mb-0">Hear from them</p>
                    <h4 class="mb-0">I've purchased tickets here several times, everything was always perfect, highly recommend!</h4>
                  </div>
                  <div class="hstack gap-3">
                    <img src="../assets/images/testimonial/testimonial-3.jpg" alt=""
                      class="img-fluid rounded-circle overflow-hidden flex-shrink-0" width="60" height="60">
                    <div>
                      <h5 class="mb-1 fw-normal">Tóth Zsuzsa</h5>
                      <p class="mb-0">Pécs</p>
                    </div>
                  </div>
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