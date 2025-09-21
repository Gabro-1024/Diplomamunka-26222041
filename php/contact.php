<?php
require_once __DIR__ . '/includes/db_connect.php';

$formErrors = [];
$formSuccess = '';

// Helper to trim and normalize
function _contact_post($key, $default = '') { return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default; }

// Simple structured logging to php/logs/contact_submissions.log
$__logDir = __DIR__ . '/logs';
if (!is_dir($__logDir)) { @mkdir($__logDir, 0777, true); }
$__logFile = $__logDir . '/contact_submissions.log';
function _mask($s) {
    if ($s === null || $s === '') return $s === '' ? '' : 'null';
    $s = (string)$s; $len = strlen($s); if ($len <= 12) { return substr($s,0,3) . '...' . substr($s,-2); }
    return substr($s,0,8) . '...' . substr($s,-4);
}
function contact_log($event, array $ctx = []) {
    global $__logFile;
    $row = [
        'time' => date('c'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'ctx' => $ctx,
    ];
    @file_put_contents($__logFile, json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
}

$nameVal = _contact_post('name');
$emailVal = _contact_post('email');
$messageVal = _contact_post('message');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log request arrival
    contact_log('request_received', [
        'session_active' => session_status() === PHP_SESSION_ACTIVE,
        'session_id' => session_id(),
        'has_cookie' => isset($_COOKIE[session_name()])
    ]);
        // Basic validations
        if ($nameVal === '' || mb_strlen($nameVal) < 2 || mb_strlen($nameVal) > 50) {
            $formErrors['name'] = 'Please enter your name (2–50 characters).';
        }
        if ($emailVal === '' || !filter_var($emailVal, FILTER_VALIDATE_EMAIL) || mb_strlen($emailVal) > 60) {
            $formErrors['email'] = 'Please enter a valid email address (max 60 characters).';
        }
        if ($messageVal === '' || mb_strlen($messageVal) < 10 || mb_strlen($messageVal) > 2000) {
            $formErrors['message'] = 'Please enter a message (10–2000 characters).';
        }

        if (empty($formErrors)) {
            // Persist to DB (table: contact with columns name, mail, message)
            try {
                $pdo = db_connect();
                $stmt = $pdo->prepare('INSERT INTO contact (name, mail, message) VALUES (?, ?, ?)');
                // Ensure we respect column size limits
                $nameDb = mb_substr($nameVal, 0, 50);
                $emailDb = mb_substr($emailVal, 0, 60);
                $messageDb = $messageVal; // TEXT, keep full message
                $stmt->execute([$nameDb, $emailDb, $messageDb]);

                contact_log('db_insert_success', [
                    'name' => $nameDb,
                    'mail' => $emailDb,
                    'insert_id' => method_exists($pdo, 'lastInsertId') ? $pdo->lastInsertId() : null,
                ]);
                $nameVal = $emailVal = $messageVal = '';
                $formSuccess = 'Thank you! Your message has been received.';

            } catch (Throwable $tx) {
                contact_log('db_insert_error', [ 'error' => $tx->getMessage() ]);
                $formErrors['general'] = 'We could not save your message at the moment. Please try again later.';
            }
        }
        else {
            contact_log('validation_failed', [ 'errors' => $formErrors ]);
        }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contact - Tickets @ Gábor</title>
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
      style="background-image: url(../assets/images/backgrounds/contact-banner.jpg);">
      <div class="container">
        <div class="d-flex flex-column gap-4 pb-5 pb-xl-10 position-relative z-1">
          <div class="row align-items-center">
            <div class="col-xl-4">
              <div class="d-flex align-items-center gap-4" data-aos="fade-up" data-aos-delay="100"
                data-aos-duration="1000">
                <img src="../assets/images/svgs/primary-leaf.svg" alt="" class="img-fluid animate-spin">
                <p class="mb-0 text-white fs-5 text-opacity-70 p-3 rounded-3" style="background-color: rgba(0,0,0,0.4); backdrop-filter: blur(5px);">
                    Ready to <span class="text-primary">start something</span> great? Reach out we’d love to hear from you.</p>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-end gap-3" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
            <h1 class="mb-0 fs-16 text-white lh-1">Contact</h1>
            <a href="sign-up.php" class="p-1 ps-7 bg-primary rounded-pill">
              <span class="bg-white round-52 rounded-circle d-flex align-items-center justify-content-center">
                <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
              </span>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!--  Get in touch Section -->
    <section class="get-in-touch py-5 py-lg-11 py-xl-12">
      <div class="container">
        <div class="d-flex flex-column gap-5 gap-xl-10">
          <div class="row gap-7 gap-xl-0">
            <div class="col-xl-4 col-xxl-4">
              <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
                data-aos-duration="1000">
                <span
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">06</span>
                <hr class="border-line bg-white">
                <span class="badge badge-accent-blue">Contact us</span>
              </div>
            </div>
            <div class="col-xl-8 col-xxl-7">
              <div class="row">
                <div class="col-xxl-8">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                    <h2 class="mb-0">Get in touch</h2>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row justify-content-between gap-7 gap-xl-0">
            <div class="col-xl-3">
              <p class="mb-0 fs-5" data-aos="fade-right" data-aos-delay="100" data-aos-duration="1000">Let’s collaborate
                and create something amazing! Tell us about your project—We’re all
                ears.</p>
            </div>
            <div class="col-xl-8">
              <form method="post" action="contact.php" class="d-flex flex-column gap-4" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000" novalidate>
                <?php if (!empty($formSuccess)): ?>
                  <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($formSuccess, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                  </div>
                <?php endif; ?>

                <?php if (!empty($formErrors['general'])): ?>
                  <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($formErrors['general'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                  </div>
                <?php endif; ?>

                

                <div>
                  <label for="contactName" class="form-label">Name</label>
                  <input type="text" class="form-control border-bottom border-dark <?php echo isset($formErrors['name']) ? 'is-invalid' : ''; ?>" id="contactName" name="name" placeholder="Name" value="<?php echo htmlspecialchars($nameVal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                  <?php if (isset($formErrors['name'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($formErrors['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div><?php endif; ?>
                </div>

                <div>
                  <label for="contactEmail" class="form-label">Email</label>
                  <input type="email" class="form-control border-bottom border-dark <?php echo isset($formErrors['email']) ? 'is-invalid' : ''; ?>" id="contactEmail" name="email" placeholder="Email" value="<?php echo htmlspecialchars($emailVal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" aria-describedby="emailHelp">
                  <?php if (isset($formErrors['email'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($formErrors['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div><?php endif; ?>
                </div>

                <div>
                  <label for="contactMessage" class="form-label">Message</label>
                  <textarea class="form-control border-bottom border-dark <?php echo isset($formErrors['message']) ? 'is-invalid' : ''; ?>" id="contactMessage" name="message" placeholder="Tell us about your project" rows="5"><?php echo htmlspecialchars($messageVal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
                  <?php if (isset($formErrors['message'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($formErrors['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div><?php endif; ?>
                </div>

                <button type="submit" class="btn btn-accent-blue w-100 justify-content-center">
                  <span class="btn-text">Submit message</span>
                  <iconify-icon icon="lucide:arrow-up-right" class="btn-icon bg-white text-dark round-52 rounded-circle hstack justify-content-center fs-7 shadow-sm"></iconify-icon>
                </button>
              </form>
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