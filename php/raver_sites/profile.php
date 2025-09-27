<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

/* Debug output function (commented out for production)
$debugOutput = function($label, $data) {
    echo "<div class='debug-section' style='background:#f8f9fa;padding:10px;margin:5px 0;border-left:4px solid #007bff;'>";
    echo "<strong>$label:</strong><br>";
    echo "<pre style='margin:5px 0 0 10px;'>";
    var_dump($data);
    echo "</pre></div>";
};
*/

if (!isUserLoggedIn()) {
    header('Location: http://localhost/Diplomamunka-26222041/php/sign-in.php');
    exit;
}

// Ensure UTF-8 for output
header('Content-Type: text/html; charset=UTF-8');

// Helpers (preserve accented characters)
function sanitize_str($s) {
    $s = (string)$s;
    $s = strip_tags($s);
    $s = trim($s);
    // Optionally limit length to avoid abuse, keep multibyte intact
    if (function_exists('mb_substr')) {
        $s = mb_substr($s, 0, 100, 'UTF-8');
    } else {
        $s = substr($s, 0, 100);
    }
    return $s;
}

$pdo = db_connect();
// Make sure the connection negotiates utf8mb4
try { $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"); } catch (Throwable $e) {}
$userId = (int)($_SESSION['user_id'] ?? 0);

// Music styles available (mirror sign-up.php $allGenres)
$allStyles = [
    'Ambient','Bass','Breakbeat','Classical','Country','Dance','Deep House','Disco','Drum & Bass','Dubstep','EDM','Electro',
    'Folk','Hardcore','Hardstyle','Hip-Hop','House','Indie','Jazz','K-Pop','Latin','Metal','Minimal','Pop','Progressive House',
    'Psytrance','Punk','R&B','Rap','Reggae','Reggaeton','Rock','Soul','Tech House','Techno','Trance','Trap','Trip-Hop'
];
if (function_exists('sort')) { sort($allStyles, SORT_NATURAL | SORT_FLAG_CASE); }

$success = null; $error = null;

// Handle updates
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_name') {
            $first = sanitize_str($_POST['first_name'] ?? '');
            $last  = sanitize_str($_POST['last_name'] ?? '');
            if ($first === '' || $last === '') { throw new Exception('First and last name are required.'); }
            $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ? WHERE id = ?');
            $stmt->execute([$first, $last, $userId]);
            $_SESSION['first_name'] = $first; // reflect in header
            $success = 'Name updated successfully.';
        }

        if ($action === 'update_music') {
            $chosen = (array)($_POST['music_preferences'] ?? []);
            // Normalize and filter to allowed list
            $chosen = array_values(array_intersect($allStyles, array_map('sanitize_str', $chosen)));
            $pdo->beginTransaction();
            try {
                $del = $pdo->prepare('DELETE FROM user_interests WHERE user_id = ?');
                $del->execute([$userId]);
                if (!empty($chosen)) {
                    $ins = $pdo->prepare('INSERT INTO user_interests (user_id, style_name) VALUES (?, ?)');
                    foreach ($chosen as $style) { $ins->execute([$userId, $style]); }
                }
                $pdo->commit();
                $success = 'Music preferences updated.';
            } catch (Throwable $tx) {
                $pdo->rollBack();
                throw $tx;
            }
        }

        if ($action === 'update_avatar' && isset($_FILES['profile_picture'])) {
            $f = $_FILES['profile_picture'];
            if ($f['error'] !== UPLOAD_ERR_OK) { throw new Exception('File upload failed.'); }
            if ($f['size'] > 2 * 1024 * 1024) { throw new Exception('File too large (max 2MB).'); }
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $f['tmp_name']) : mime_content_type($f['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) { throw new Exception('Only JPG, PNG or WEBP allowed.'); }
            $ext = $allowed[$mime];
            $root = dirname(__DIR__, 2); // project root
            $destDir = $root . '/assets/images/profiles';
            if (!is_dir($destDir)) { @mkdir($destDir, 0777, true); }
            $destPath = $destDir . '/user_' . $userId . '.' . $ext;
            // Remove older avatars with different extensions
            foreach (['jpg','png','webp'] as $e) { $p = $destDir . '/user_' . $userId . '.' . $e; if (is_file($p)) { @unlink($p); } }
            if (!move_uploaded_file($f['tmp_name'], $destPath)) { throw new Exception('Failed to save uploaded file.'); }
            $success = 'Profile picture updated.';
        }
    }
} catch (Throwable $e) { $error = $e->getMessage(); }

// Fetch current data
$usr = null; $styles = [];
try {
    $s = $pdo->prepare('SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1');
    $s->execute([$userId]);
    $usr = $s->fetch(PDO::FETCH_ASSOC) ?: ['first_name'=>'','last_name'=>'','email'=>''];
    $si = $pdo->prepare('SELECT style_name FROM user_interests WHERE user_id = ?');
    $si->execute([$userId]);
    $styles = array_map(fn($r) => $r['style_name'], $si->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) { /* ignore */ }

// Compute avatar path if exists
$avatarRel = null;
foreach (['jpg','png','webp'] as $e) {
    $candidate = '/assets/images/profiles/user_' . $userId . '.' . $e;
    if (is_file(dirname(__DIR__, 2) . $candidate)) { $avatarRel = $candidate; break; }
}
if ($avatarRel === null) { $avatarRel = '/assets/images/team/team-img-1.jpg'; }

// Fetch user's tickets (only unused)
$userTickets = [];
try {
    $sql = "SELECT t.id, t.qr_code_path, t.event_id, t.is_used, t.price, e.name AS event_name, e.start_date, e.venue_id
            FROM tickets t
            INNER JOIN events e ON t.event_id = e.id
            WHERE t.owner_id = ? AND t.is_used = 0
            ORDER BY e.start_date DESC, t.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $userTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch venue names for tickets (if needed)
    $venueNames = [];
    if ($userTickets) {
        $venueIds = array_unique(array_column($userTickets, 'venue_id'));
        if ($venueIds) {
            $in = str_repeat('?,', count($venueIds) - 1) . '?';
            $venueStmt = $pdo->prepare("SELECT id, name FROM venues WHERE id IN ($in)");
            $venueStmt->execute($venueIds);
            foreach ($venueStmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
                $venueNames[$v['id']] = $v['name'];
            }
        }
    }
} catch (Throwable $e) {
    // Log the error but don't reset $userTickets if it was successfully set
    error_log("Error fetching tickets: " . $e->getMessage());
    if (!isset($userTickets)) {
        $userTickets = [];
    }
}

// Debug output commented out for production

?>
<?php 
$page_title = 'My Profile - Tickets @ Gábor';
include __DIR__ . '/../header.php'; 
?>
  <style>
    body{
        background-color: #f8f9fa;
        opacity: 0;
        transition: opacity 0.5s ease-in-out;
    }
    body.loaded {
        opacity: 1;
    }
    .profile-card {
      transition: all 0.3s ease, transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      opacity: 0;
      transform: translateY(20px);
    }
    .aos-animate .profile-card {
      opacity: 1;
      transform: translateY(0);
    }
    .profile-card:hover {
      transform: translateY(-5px) !important;
      box-shadow: 0 12px 28px rgba(var(--bs-accent-blue-rgb), 0.2) !important;
      border-color: rgba(var(--bs-accent-blue-rgb), 0.8) !important;
      border-width: 2px !important;
      border-radius: 12px !important;
    }
    .card {
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .my-tickets-section {
      margin-top: 48px;
      margin-bottom: 48px;
    }
    .ticket-card {
      border: 1.5px solid #1a73e8;
      border-radius: 12px;
      transition: box-shadow 0.2s;
      background: #fff;
      box-shadow: 0 2px 8px rgba(26,115,232,0.06);
    }
    .ticket-card:hover {
      box-shadow: 0 6px 24px rgba(26,115,232,0.13);
      border-color: #0d47a1;
    }
    .qr-img {
      width: 80px;
      height: 80px;
      object-fit: contain;
      background: #f8f9fa;
      border-radius: 8px;
      border: 1px solid #e0e0e0;
      padding: 4px;
    }
    .ticket-used {
      opacity: 0.6;
      filter: grayscale(1);
    }
    .ticket-status {
      font-size: 0.95rem;
      font-weight: 500;
      padding: 2px 10px;
      border-radius: 8px;
      display: inline-block;
    }
    .ticket-status.used {
      background: #e57373;
      color: #fff;
    }
    .ticket-status.valid {
      background: #43a047;
      color: #fff;
    }
    .ticket-status.upcoming {
      background: #1a73e8;
      color: #fff;
    }
    .qr-zoom-modal {
      position: fixed;
      z-index: 2000;
      left: 0; top: 0; width: 100vw; height: 100vh;
      display: flex; align-items: center; justify-content: center;
      background: rgba(0,0,0,0.65);
      animation: fadeIn 0.2s;
    }
    .qr-zoom-backdrop {
      position: absolute;
      left: 0; top: 0; width: 100vw; height: 100vh;
      background: transparent;
    }
    .qr-zoom-content {
      position: relative;
      z-index: 2;
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.25);
      padding: 32px 32px 24px 32px;
      display: flex;
      flex-direction: column;
      align-items: center;
      max-width: 95vw;
      max-height: 90vh;
      animation: zoomIn 0.2s;
    }
    .qr-zoom-content img {
      max-width: 60vw;
      max-height: 60vh;
      width: auto;
      height: auto;
      display: block;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(26,115,232,0.13);
      background: #f8f9fa;
    }
    .qr-zoom-close {
      position: absolute;
      top: 10px; right: 18px;
      background: none;
      border: none;
      font-size: 2.2rem;
      color: #333;
      cursor: pointer;
      line-height: 1;
      z-index: 3;
      transition: color 0.15s;
    }
    .qr-zoom-close:hover {
      color: #1a73e8;
    }
    @keyframes fadeIn {
      from { opacity: 0; }
      to   { opacity: 1; }
    }
    @keyframes zoomIn {
      from { transform: scale(0.85);}
      to   { transform: scale(1);}
    }
  </style>

  <div class="page-wrapper overflow-hidden" style="padding-top: 120px;">
    <section class="py-5 py-lg-8">
      <div class="container">
        <div class="row g-4" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
          <div class="col-lg-4">
            <div class="card border border-accent-blue profile-card h-100" data-aos="fade-right" data-aos-delay="200">
              <div class="card-body d-flex flex-column align-items-center gap-3">
                <img src="http://localhost/Diplomamunka-26222041<?php echo htmlspecialchars($avatarRel); ?>" alt="avatar" class="rounded-circle" style="width: 128px; height:128px; object-fit:cover;">
                <div class="text-center">
                  <h5 class="mb-1"><?php echo htmlspecialchars(($usr['first_name'] ?? '') . ' ' . ($usr['last_name'] ?? '')); ?></h5>
                  <p class="mb-0 text-muted"><?php echo htmlspecialchars($usr['email'] ?? ''); ?></p>
                </div>
                <?php if (!empty($styles)): ?>
                <div class="d-flex flex-wrap gap-1 justify-content-center">
                  <?php foreach ($styles as $st): ?>
                    <span class="badge badge-accent-blue"><?php echo htmlspecialchars($st); ?></span>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger w-100 mb-0"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success w-100 mb-0"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
              </div>
            </div>
          </div>
          <div class="col-lg-8">
            <div class="d-flex flex-column gap-4">
              <div class="card border border-accent-blue profile-card" data-aos="fade-up" data-aos-delay="200">
                <div class="card-body">
                  <h5 class="mb-4 text-accent-blue">Change name</h5>
                  <form method="post" accept-charset="UTF-8" class="row g-3">
                    <input type="hidden" name="action" value="update_name">
                    <div class="col-md-6">
                      <label class="form-label">First name</label>
                      <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($usr['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Last name</label>
                      <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($usr['last_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-12">
                      <button class="btn btn-accent-blue">Save</button>
                    </div>
                  </form>
                </div>
              </div>

              <div class="card border border-accent-blue profile-card" data-aos="fade-up" data-aos-delay="300">
                <div class="card-body">
                  <h5 class="mb-4 text-accent-blue">Profile picture</h5>
                  <form method="post" accept-charset="UTF-8" enctype="multipart/form-data" class="d-flex flex-column gap-3">
                    <input type="hidden" name="action" value="update_avatar">
                    <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp" class="form-control" required>
                    <button class="btn btn-accent-blue align-self-start">Upload</button>
                  </form>
                </div>
              </div>

              <div class="card border border-accent-blue profile-card" data-aos="fade-up" data-aos-delay="400">
                <div class="card-body">
                  <h5 class="mb-4 text-accent-blue">Music preferences</h5>
                  <form method="post" accept-charset="UTF-8" class="row g-2">
                    <input type="hidden" name="action" value="update_music">
                    <?php foreach ($allStyles as $style): $id = 'st_' . md5($style); $checked = in_array($style, $styles, true); ?>
                      <div class="col-6 col-md-4">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="<?php echo $id; ?>" name="music_preferences[]" value="<?php echo htmlspecialchars($style); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                          <label class="form-check-label" for="<?php echo $id; ?>"><?php echo htmlspecialchars($style); ?></label>
                        </div>
                      </div>
                    <?php endforeach; ?>
                    <div class="col-12 mt-2">
                      <button class="btn btn-accent-blue">Save preferences</button>
                    </div>
                  </form>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <!-- My Tickets Section -->
  <div class="container my-tickets-section">
    <div class="row">
      <div class="col-12">
        <h3 class="mb-4 text-primary" data-aos="fade-up" data-aos-delay="100">My tickets</h3>
      </div>
    </div>
    <?php 
// Debug output commented out for production
?>
<div class="row g-4">
      <?php if (empty($userTickets)):?>
        <div class="col-12">
          <div class="alert alert-info mb-0">You have not purchased any valid (unused) tickets yet.</div>
        </div>
      <?php else: ?>
        <?php
          foreach ($userTickets as $ticket):
          $eventDate = date('Y.m.d. H:i', strtotime($ticket['start_date']));
          $venue = $venueNames[$ticket['venue_id']] ?? 'Unknown venue';
          $now = new DateTime();
          $eventStart = new DateTime($ticket['start_date']);
          $status = ($eventStart > $now ? 'upcoming' : 'valid');
          $statusText = ($eventStart > $now ? 'Upcoming' : 'Valid');
          $qrAbs = 'http://localhost/Diplomamunka-26222041/php/' . ltrim($ticket['qr_code_path']);
        ?>
        <div class="col-md-6 col-lg-4">
          <div class="ticket-card p-4 d-flex flex-column gap-3" data-aos="fade-up" data-aos-delay="100">
            <div class="d-flex align-items-center gap-3">
              <img src="<?php echo htmlspecialchars($qrAbs); ?>" alt="QR code" class="qr-img qr-zoom-trigger" style="cursor:pointer;" data-qr="<?php echo htmlspecialchars($qrAbs); ?>">
              <div>
                <span class="ticket-status <?php echo $status; ?>"><?php echo $statusText; ?></span>
                <div class="fw-bold"><?php echo htmlspecialchars($ticket['event_name']); ?></div>
                <div class="text-muted small"><?php echo htmlspecialchars($eventDate); ?></div>
              </div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-medium">Ticket ID: <?php echo (int)$ticket['id']; ?></span>
              <span class="fw-bold text-primary"><?php echo number_format($ticket['price'], 0, '', ' '); ?> Ft</span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- QR Zoom Modal -->
  <div id="qrZoomModal" class="qr-zoom-modal" style="display:none;">
    <div class="qr-zoom-backdrop"></div>
    <div class="qr-zoom-content">
      <img src="" alt="QR code enlarged" id="qrZoomImg">
      <button type="button" class="qr-zoom-close" aria-label="Close">&times;</button>
    </div>
  </div>

  <style>
    /* ...existing styles... */
    .qr-zoom-modal {
      position: fixed;
      z-index: 2000;
      left: 0; top: 0; width: 100vw; height: 100vh;
      display: flex; align-items: center; justify-content: center;
      background: rgba(0,0,0,0.65);
      animation: fadeIn 0.2s;
    }
    .qr-zoom-backdrop {
      position: absolute;
      left: 0; top: 0; width: 100vw; height: 100vh;
      background: transparent;
    }
    .qr-zoom-content {
      position: relative;
      z-index: 2;
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.25);
      padding: 32px 32px 24px 32px;
      display: flex;
      flex-direction: column;
      align-items: center;
      max-width: 95vw;
      max-height: 90vh;
      animation: zoomIn 0.2s;
    }
    .qr-zoom-content img {
      max-width: 60vw;
      max-height: 60vh;
      width: auto;
      height: auto;
      display: block;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(26,115,232,0.13);
      background: #f8f9fa;
    }
    .qr-zoom-close {
      position: absolute;
      top: 10px; right: 18px;
      background: none;
      border: none;
      font-size: 2.2rem;
      color: #333;
      cursor: pointer;
      line-height: 1;
      z-index: 3;
      transition: color 0.15s;
    }
    .qr-zoom-close:hover {
      color: #1a73e8;
    }
    @keyframes fadeIn {
      from { opacity: 0; }
      to   { opacity: 1; }
    }
    @keyframes zoomIn {
      from { transform: scale(0.85);}
      to   { transform: scale(1);}
    }
  </style>

  <?php include __DIR__ . '/../footer.php'; ?>

  <script src="http://localhost/Diplomamunka-26222041/assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="http://localhost/Diplomamunka-26222041/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="http://localhost/Diplomamunka-26222041/assets/libs/aos-master/dist/aos.js"></script>
  <script src="http://localhost/Diplomamunka-26222041/assets/js/custom.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <script>
    // Initialize AOS with custom settings
    document.addEventListener('DOMContentLoaded', function() {
      AOS.init({
        duration: 600,
        easing: 'ease-out-cubic',
        once: true,
        mirror: false
      });
      document.body.classList.add('loaded');

      // QR Zoom logic
      function closeQrZoom() {
        document.getElementById('qrZoomModal').style.display = 'none';
        document.getElementById('qrZoomImg').src = '';
      }
      document.querySelectorAll('.qr-zoom-trigger').forEach(function(img) {
        img.addEventListener('click', function() {
          var src = img.getAttribute('data-qr');
          var modal = document.getElementById('qrZoomModal');
          var modalImg = document.getElementById('qrZoomImg');
          modalImg.src = src;
          modal.style.display = 'flex';
        });
      });
      document.querySelector('.qr-zoom-close').addEventListener('click', closeQrZoom);
      document.querySelector('.qr-zoom-backdrop').addEventListener('click', closeQrZoom);
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeQrZoom();
      });
    });
  </script>
</body>
</html>
<?php exit; ?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile - Tickets @ Gábor</title>
  <link rel="shortcut icon" type="image/png" href="http://localhost/Diplomamunka-26222041/assets/images/logos/favicon.svg" />
  <link rel="stylesheet" href="http://localhost/Diplomamunka-26222041/assets/libs/owl.carousel/dist/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="http://localhost/Diplomamunka-26222041/assets/libs/aos-master/dist/aos.css">
  <link rel="stylesheet" href="http://localhost/Diplomamunka-26222041/assets/css/styles.css" />
  </head>
  <body>

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
              <span class="badge text-bg-dark">Stats & facts</span>
            </div>
          </div>
          <div class="col-xl-8 col-xxl-7">
            <div class="d-flex flex-column gap-9">
              <div class="row">
                <div class="col-xxl-8">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                    <h2 class="mb-0">High quality web design solutions you can trust.</h2>
                    <p class="fs-5 mb-0">When selecting a web design agency, it's essential to consider its reputation,
                      experience, and the specific needs of your project.</p>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 col-lg-4 mb-7 mb-lg-0">
                  <div class="d-flex flex-column gap-6 pt-9 border-top" data-aos="fade-up" data-aos-delay="200"
                    data-aos-duration="1000">
                    <h2 class="mb-0 fs-14"><span class="count" data-target="40">40</span>K+</h2>
                    <p class="mb-0">People who have launched their websites</p>
                  </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-7 mb-lg-0">
                  <div class="d-flex flex-column gap-6 pt-9 border-top" data-aos="fade-up" data-aos-delay="300"
                    data-aos-duration="1000">
                    <h2 class="mb-0 fs-14"><span class="count" data-target="238">238</span>+</h2>
                    <p class="mb-0">Experienced professionals ready to assist</p>
                  </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-7 mb-lg-0">
                  <div class="d-flex flex-column gap-6 pt-9 border-top" data-aos="fade-up" data-aos-delay="400"
                    data-aos-duration="1000">
                    <h2 class="mb-0 fs-14"><span class="count" data-target="3">3</span>M+</h2>
                    <p class="mb-0">Support through messages and live consultations</p>
                  </div>
                </div>
              </div>
              <a href="about-us.php" class="btn" data-aos="fade-up" data-aos-delay="500" data-aos-duration="1000">
                <span class="btn-text">Who we are</span>
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
                <span class="badge text-bg-dark">Portfolio</span>
              </div>
            </div>
            <div class="col-xl-8 col-xxl-7">
              <div class="row">
                <div class="col-xxl-8">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                    <h2 class="mb-0">Featured projects</h2>
                    <p class="fs-5 mb-0">A glimpse into our creativity—exploring innovative designs, successful
                      collaborations, and transformative digital experiences.</p>
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
                  <h3 class="mb-0">Snapclear</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">UX Strategy</span>
                    <span class="badge text-dark border">UI Design</span>
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
                  <h3 class="mb-0">Amber Bottle</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">Web development</span>
                    <span class="badge text-dark border">Digital design</span>
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
                  <h3 class="mb-0">Pixelforge</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">UI/UX design</span>
                    <span class="badge text-dark border">Web development</span>
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
                  <h3 class="mb-0">BioTrack LIMS</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">Brand identity</span>
                    <span class="badge text-dark border">Digital design</span>
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
                  <h3 class="mb-0">Amber Bottle</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">Photography</span>
                    <span class="badge text-dark border">Studio</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="item">
              <div class="portfolio d-flex flex-column gap-6">
                <div class="portfolio-img position-relative overflow-hidden">
                  <img src="../assets/images/portfolio/portfolio-img-6.jpg" alt="" class="img-fluid">
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
          </div>
        </div>
      </div>
    </section>

    <!--  Services Section -->
    <section class="services py-5 py-lg-11 py-xl-12 bg-dark" id="services">
      <div class="container">
        <div class="d-flex flex-column gap-5 gap-xl-10">
          <div class="row gap-7 gap-xl-0">
            <div class="col-xl-4 col-xxl-4">
              <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
                data-aos-duration="1000">
                <span
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">03</span>
                <hr class="border-line bg-white">
                <span class="badge text-dark bg-white">Services</span>
              </div>
            </div>
            <div class="col-xl-8 col-xxl-7">
              <div class="row">
                <div class="col-xxl-8">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                    <h2 class="mb-0 text-white">What we do</h2>
                    <p class="fs-5 mb-0 text-white text-opacity-70">A glimpse into our creativity—exploring innovative
                      designs, successful collaborations, and transformative digital experiences.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="services-tab">
            <div class="row gap-5 gap-xl-0">
              <div class="col-xl-4">
                <div class="tab-content" data-aos="zoom-in" data-aos-delay="100" data-aos-duration="1000">
                  <div class="tab-pane active" id="one" role="tabpanel" aria-labelledby="one-tab" tabindex="0">
                    <img src="../assets/images/services/services-img-1.jpg" alt="services" class="img-fluid">
                  </div>
                  <div class="tab-pane" id="two" role="tabpanel" aria-labelledby="two-tab" tabindex="0">
                    <img src="../assets/images/services/services-img-2.jpg" alt="services" class="img-fluid">
                  </div>
                  <div class="tab-pane" id="three" role="tabpanel" aria-labelledby="three-tab" tabindex="0">
                    <img src="../assets/images/services/services-img-3.jpg" alt="services" class="img-fluid">
                  </div>
                  <div class="tab-pane" id="four" role="tabpanel" aria-labelledby="four-tab" tabindex="0">
                    <img src="../assets/images/services/services-img-4.jpg" alt="services" class="img-fluid">
                  </div>
                </div>
              </div>
              <div class="col-xl-8">
                <div class="d-flex flex-column gap-5">
                  <ul class="nav nav-tabs" id="myTab" role="tablist" data-aos="fade-up" data-aos-delay="200"
                    data-aos-duration="1000">
                    <li
                      class="nav-item py-4 py-lg-8 border-top border-white border-opacity-10 d-flex align-items-center w-100"
                      role="presentation">
                      <div class="row w-100 align-items-center gx-3">
                        <div class="col-lg-6 col-xxl-5">
                          <button class="nav-link fs-10 fw-bold py-1 px-0 border-0 rounded-0 flex-shrink-0 active"
                            id="one-tab" data-bs-toggle="tab" data-bs-target="#one" type="button" role="tab"
                            aria-controls="one" aria-selected="true">Brand identity</button>
                        </div>
                        <div class="col-lg-6 col-xxl-7">
                          <p class="text-white text-opacity-70 mb-0">
                            When selecting a web design agency, it's essential to consider its reputation, experience,
                            and
                            the
                            specific needs of your project.
                          </p>
                        </div>
                      </div>
                    </li>
                    <li
                      class="nav-item py-4 py-lg-8 border-top border-white border-opacity-10 d-flex align-items-center w-100"
                      role="presentation">
                      <div class="row w-100 align-items-center gx-3">
                        <div class="col-lg-6 col-xxl-5">
                          <button class="nav-link fs-10 fw-bold py-1 px-0 border-0 rounded-0 flex-shrink-0" id="two-tab"
                            data-bs-toggle="tab" data-bs-target="#two" type="button" role="tab" aria-controls="two"
                            aria-selected="false">Web development</button>
                        </div>
                        <div class="col-lg-6 col-xxl-7">
                          <p class="text-white text-opacity-70 mb-0">
                            When selecting a web design agency, it's essential to consider its reputation, experience,
                            and
                            the
                            specific needs of your project.
                          </p>
                        </div>
                      </div>
                    </li>
                    <li
                      class="nav-item py-4 py-lg-8 border-top border-white border-opacity-10 d-flex align-items-center w-100"
                      role="presentation">
                      <div class="row w-100 align-items-center gx-3">
                        <div class="col-lg-6 col-xxl-5">
                          <button class="nav-link fs-10 fw-bold py-1 px-0 border-0 rounded-0 flex-shrink-0"
                            id="three-tab" data-bs-toggle="tab" data-bs-target="#three" type="button" role="tab"
                            aria-controls="three" aria-selected="false">Content creation</button>
                        </div>
                        <div class="col-lg-6 col-xxl-7">
                          <p class="text-white text-opacity-70 mb-0">
                            When selecting a web design agency, it's essential to consider its reputation, experience,
                            and
                            the
                            specific needs of your project.
                          </p>
                        </div>
                      </div>
                    </li>
                    <li
                      class="nav-item py-4 py-lg-8 border-top border-white border-opacity-10 d-flex align-items-center w-100"
                      role="presentation">
                      <div class="row w-100 align-items-center gx-3">
                        <div class="col-lg-6 col-xxl-5">
                          <button class="nav-link fs-10 fw-bold py-1 px-0 border-0 rounded-0 flex-shrink-0"
                            id="four-tab" data-bs-toggle="tab" data-bs-target="#four" type="button" role="tab"
                            aria-controls="four" aria-selected="false">Motion & 3d modeling</button>
                        </div>
                        <div class="col-lg-6 col-xxl-7">
                          <p class="text-white text-opacity-70 mb-0">
                            When selecting a web design agency, it's essential to consider its reputation, experience,
                            and
                            the
                            specific needs of your project.
                          </p>
                        </div>
                      </div>
                    </li>
                  </ul>
                  <a href="projects.html" class="btn border border-white border-opacity-25" data-aos="fade-up"
                    data-aos-delay="300" data-aos-duration="1000">
                    <span class="btn-text">See our Work</span>
                    <iconify-icon icon="lucide:arrow-up-right"
                      class="btn-icon bg-white text-dark round-52 rounded-circle hstack justify-content-center fs-7 shadow-sm"></iconify-icon>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!--  Why choose us Section -->
    <section class="why-choose-us py-5 py-lg-11 py-xl-12">
      <div class="container">
        <div class="row justify-content-between gap-5 gap-xl-0">
          <div class="col-xl-3 col-xxl-3">
            <div class="d-flex flex-column gap-7">
              <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
                data-aos-duration="1000">
                <span
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">04</span>
                <hr class="border-line bg-white">
                <span class="badge text-bg-dark">About us</span>
              </div>
              <h2 class="mb-0" data-aos="fade-right" data-aos-delay="200" data-aos-duration="1000">Why choose us</h2>
              <p class="mb-0 fs-5" data-aos="fade-right" data-aos-delay="300" data-aos-duration="1000">We blend
                creativity with strategy to craft unique digital experiences that make an
                impact.
                With a focus on innovation, attention to details.</p>
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
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-line-duotone"
                              class="fs-6 text-dark"></iconify-icon></a></li>
                      </ul>
                      <p class="mb-0 fs-6 text-dark">The team exceeded our expectations with a stunning brand identity.
                      </p>
                    </div>
                    <div class="position-relative z-1">
                      <div class="pb-6 border-bottom">
                        <h2 class="mb-0">98.6%</h2>
                        <p class="mb-0">Customer satisfaction</p>
                      </div>
                      <div class="hstack gap-6 pt-6">
                        <img src="../assets/images/profile/avatar-1.png" alt=""
                          class="img-fluid rounded-circle overflow-hidden flex-shrink-0" width="64" height="64">
                        <div>
                          <h5 class="mb-0">Wade Warren</h5>
                          <p class="mb-0">Bank of America</p>
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
                        <h2 class="mb-0 text-white">500+</h2>
                        <p class="mb-0 text-white text-opacity-70">Successful projects completed</p>
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
                      <h2 class="mb-0">238+</h2>
                      <p class="mb-0 text-dark">Brands served worldwide</p>
                    </div>
                    <div class="d-flex flex-column gap-3">
                      <a href="index.html" class="logo-dark">
                        <img src="http://localhost:63342/Diplomamunka-26222041/assets/images/logos/logo-dark.svg" alt="logo" class="img-fluid">
                      </a>
                      <p class="mb-0 fs-5 text-dark">Our global reach allows us to create unique, culturally relevant
                        designs for businesses across different industries.</p>
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
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">05</span>
                <hr class="border-line bg-white">
                <span class="badge text-bg-dark">Testimonial</span>
              </div>
            </div>
            <div class="col-xl-8 col-xxl-7">
              <div class="row">
                <div class="col-xxl-8">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                    <h2 class="mb-0">Stories from clients</h2>
                    <p class="fs-5 mb-0 text-opacity-70">Real experiences, genuine feedback—discover how our creative
                      solutions have transformed brands and elevated businesses.</p>
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
                    <h4 class="mb-0">Our website redesign was flawless. They understood our vision perfectly!</h4>
                  </div>
                  <div class="hstack gap-3">
                    <img src="../assets/images/testimonial/testimonial-1.jpg" alt=""
                      class="img-fluid rounded-circle overflow-hidden flex-shrink-0" width="60" height="60">
                    <div>
                      <h5 class="mb-1 fw-normal">Albert Flores</h5>
                      <p class="mb-0">MasterCard</p>
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
                    <h4 class="mb-0 text-white pe-xl-2">From concept to execution, they delivered outstanding results.
                      Highly recommend their expertise!</h4>
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
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-line-duotone"
                              class="fs-6 text-white"></iconify-icon></a></li>
                      </ul>
                      <h6 class="mb-0 text-white fw-medium">4.0</h6>
                    </div>
                  </div>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="hstack gap-3">
                      <img src="../assets/images/testimonial/testimonial-2.jpg" alt=""
                        class="img-fluid rounded-circle overflow-hidden flex-shrink-0" width="60" height="60">
                      <div>
                        <h5 class="mb-1 fw-normal text-white">Robert Fox</h5>
                        <p class="mb-0 text-white text-opacity-70">Mitsubishi</p>
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
                    <h4 class="mb-0">Super smooth process with incredible results. highly recommend!</h4>
                  </div>
                  <div class="hstack gap-3">
                    <img src="../assets/images/testimonial/testimonial-3.jpg" alt=""
                      class="img-fluid rounded-circle overflow-hidden flex-shrink-0" width="60" height="60">
                    <div>
                      <h5 class="mb-1 fw-normal">Jenny Wilson</h5>
                      <p class="mb-0">Pizza Hut</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!--  Meet our team Section -->
    <section class="meet-our-team py-5 py-lg-11 py-xl-12">
      <div class="container">
        <div class="d-flex flex-column gap-5 gap-xl-11">
          <div class="row gap-7 gap-xl-0">
            <div class="col-xl-4 col-xxl-4">
              <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
                data-aos-duration="1000">
                <span
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">06</span>
                <hr class="border-line bg-white">
                <span class="badge text-bg-dark">The team</span>
              </div>
            </div>
            <div class="col-xl-8 col-xxl-7">
              <div class="row">
                <div class="col-xxl-8">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                    <h2 class="mb-0">Meet our team</h2>
                    <p class="fs-5 mb-0 text-opacity-70">Our team is committed to redefining digital experiences through
                      innovative web solutions while fostering a diverse and collaborative environment.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 col-xl-3 mb-7 mb-xl-0">
              <div class="meet-team d-flex flex-column gap-4" data-aos="fade-up" data-aos-delay="100"
                data-aos-duration="1000">
                <div class="meet-team-img position-relative overflow-hidden">
                  <img src="../assets/images/team/team-img-1.jpg" alt="team-img" class="img-fluid w-100">
                  <div class="meet-team-overlay p-7 d-flex flex-column justify-content-end">
                    <ul class="social list-unstyled mb-0 hstack gap-2 justify-content-end">
                      <li><a href="#!"
                          class="btn bg-white p-2 round-45 rounded-circle hstack justify-content-center"><img
                            src="../assets/images/svgs/icon-twitter.svg" alt="twitter"></a></li>
                      <li><a href="#!"
                          class="btn bg-white p-2 round-45 rounded-circle hstack justify-content-center"><img
                            src="../assets/images/svgs/icon-be.svg" alt="be"></a></li>
                      <li><a href="#!"
                          class="btn bg-white p-2 round-45 rounded-circle hstack justify-content-center"><img
                            src="../assets/images/svgs/icon-linkedin.svg" alt="linkedin"></a></li>
                    </ul>
                  </div>
                </div>
                <div class="meet-team-details">
                  <h4 class="mb-0">Martha Finley</h4>
                  <p class="mb-0">Creative Director</p>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-7 mb-xl-0">
              <div class="meet-team d-flex flex-column gap-4" data-aos="fade-up" data-aos-delay="200"
                data-aos-duration="1000">
                <div class="meet-team-img position-relative overflow-hidden">
                  <img src="../assets/images/team/team-img-2.jpg" alt="team-img" class="img-fluid w-100">
                  <div class="meet-team-overlay p-7 d-flex flex-column justify-content-end">
                    <ul class="social list-unstyled mb-0 hstack gap-2 justify-content-end">
                      <li><a href="#!"
                          class="btn bg-white p-2 round-45 rounded-circle hstack justify-content-center"><img
                            src="../assets/images/svgs/icon-twitter.svg" alt="twitter"></a></li>
                      <li><a href="#!"
                          class="btn bg-white p-2 round-45 rounded-circle hstack justify-content-center"><img
                            src="../assets/images/svgs/icon-be.svg" alt="be"></a></li>
                      <li><a href="#!"
                          class="btn bg-white p-2 round-45 rounded-circle hstack justify-content-center"><img
                            src="../assets/images/svgs/icon-linkedin.svg" alt="linkedin"></a></li>
                    </ul>
                  </div>
                </div>
                <div class="meet-team-details">
                  <h4 class="mb-0">Floyd Miles</h4>
                  <p class="mb-0">Marketing Strategist</p>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-7 mb-xl-0">
              <div class="meet-team d-flex flex-column gap-4" data-aos="fade-up" data-aos-delay="300"
                data-aos-duration="1000">
                <div class="meet-team-img position-relative overflow-hidden">
                  <img src="../assets/images/team/team-img-3.jpg" alt="team-img" class="img-fluid w-100">
                  <div class="meet-team-overlay p-7 d-flex flex-column justify-content-end">
                    <ul class="social list-unstyled mb-0 hstack gap-2 justify-content-end">
                      <li><a href="#!"
                          class="btn bg-white p-2 round-45 rounded-circle hstack justify-content-center"><img
                            src="../assets/images/svgs/icon-twitter.svg" alt="twitter"></a></li>
                      <li><a href="#!"
                          class="btn bg-white p-2 round-45 rounded-circle hstack justify-content-center"><img
                            src="../assets/images/svgs/icon-be.svg" alt="be"></a></li>
                      <li><a href="#!"
                          class="btn bg-white p-2 round-45 rounded-circle hstack justify-content-center"><img
                            src="../assets/images/svgs/icon-linkedin.svg" alt="linkedin"></a></li>
                    </ul>
                  </div>
                </div>
                <div class="meet-team-details">
                  <h4 class="mb-0">Glenna Snyder</h4>
                  <p class="mb-0">Lead Designer</p>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-7 mb-xl-0">
              <div class="meet-team d-flex flex-column gap-4" data-aos="fade-up" data-aos-delay="400"
                data-aos-duration="1000">
                <div class="meet-team-img position-relative overflow-hidden">
                  <img src="../assets/images/team/team-img-4.jpg" alt="team-img" class="img-fluid w-100">
                  <div class="meet-team-overlay p-7 d-flex flex-column justify-content-end">
                    <ul class="social list-unstyled mb-0 hstack gap-2 justify-content-end">
                      <li><a href="#!"
                          class="btn bg-white p-2 round-45 rounded-circle hstack justify-content-center"><img
                            src="../assets/images/svgs/icon-twitter.svg" alt="twitter"></a></li>
                      <li><a href="#!"
                          class="btn bg-white p-2 round-45 rounded-circle hstack justify-content-center"><img
                            src="../assets/images/svgs/icon-be.svg" alt="be"></a></li>
                      <li><a href="#!"
                          class="btn bg-white p-2 round-45 rounded-circle hstack justify-content-center"><img
                            src="../assets/images/svgs/icon-linkedin.svg" alt="linkedin"></a></li>
                    </ul>
                  </div>
                </div>
                <div class="meet-team-details">
                  <h4 class="mb-0">Albert Flores</h4>
                  <p class="mb-0">UX/UI Developer</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!--  Pricing Section -->
    <section class="pricing-section py-5 py-lg-11 py-xl-12 bg-light-gray">
      <div class="container">
        <div class="d-flex flex-column gap-5 gap-xl-10">
          <div class="d-flex flex-column gap-5 gap-xl-11">
            <div class="row gap-7 gap-xl-0">
              <div class="col-xl-4 col-xxl-4">
                <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
                  data-aos-duration="1000">
                  <span
                    class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">07</span>
                  <hr class="border-line bg-white">
                  <span class="badge text-bg-dark">Pricing</span>
                </div>
              </div>
              <div class="col-xl-8 col-xxl-7">
                <div class="row">
                  <div class="col-xxl-8">
                    <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                      data-aos-duration="1000">
                      <h2 class="mb-0">Affordable pricing</h2>
                      <p class="fs-5 mb-0 text-opacity-70">A glimpse into our creativity—exploring innovative designs,
                        successful collaborations, and transformative digital experiences.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-lg-6 col-xl-4 mb-7 mb-xl-0 d-flex align-items-stretch">
                <div class="card w-100" data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000">
                  <div class="card-body p-7 p-xxl-5 d-flex flex-column gap-8">
                    <div class="d-flex flex-column gap-6">
                      <h5 class="mb-0 fw-medium">Launch</h5>
                      <div class="hstack gap-2">
                        <h3 class="mb-0">$699</h3>
                        <p class="mb-0">/month</p>
                      </div>
                      <p class="mb-0">Ideal for startups and small businesses taking their first steps online.</p>
                    </div>
                    <div class="pt-8 border-top d-flex flex-column gap-6">
                      <h6 class="mb-0 fw-normal">What’s Included:</h6>
                      <ul class="list-unstyled d-flex flex-column gap-3 mb-0">
                        <li class="hstack gap-3">
                          <span
                            class="round-32 rounded-circle bg-primary flex-shrink-0 hstack justify-content-center"><iconify-icon
                              icon="lucide:check" class="fs-6 text-dark"></iconify-icon></span>
                          <h6 class="mb-0 fw-normal">Competitive research & insights</h6>
                        </li>
                        <li class="hstack gap-3">
                          <span
                            class="round-32 rounded-circle bg-primary flex-shrink-0 hstack justify-content-center"><iconify-icon
                              icon="lucide:check" class="fs-6 text-dark"></iconify-icon></span>
                          <h6 class="mb-0 fw-normal">Wireframing and prototyping</h6>
                        </li>
                        <li class="hstack gap-3">
                          <span
                            class="round-32 rounded-circle bg-primary flex-shrink-0 hstack justify-content-center"><iconify-icon
                              icon="lucide:check" class="fs-6 text-dark"></iconify-icon></span>
                          <h6 class="mb-0 fw-normal">Basic tracking setup (Google Analytics, etc.)</h6>
                        </li>
                        <li class="hstack gap-3">
                          <span
                            class="round-32 rounded-circle bg-primary flex-shrink-0 hstack justify-content-center"><iconify-icon
                              icon="lucide:check" class="fs-6 text-dark"></iconify-icon></span>
                          <h6 class="mb-0 fw-normal">Standard contact form integration</h6>
                        </li>
                      </ul>
                    </div>
                    <a href="" class="btn w-100 justify-content-center">
                      <span class="btn-text">Subscribe now</span>
                      <iconify-icon icon="lucide:arrow-up-right"
                        class="btn-icon bg-white text-dark round-52 rounded-circle hstack justify-content-center fs-7 shadow-sm"></iconify-icon>
                    </a>
                  </div>
                </div>
              </div>
              <div class="col-lg-6 col-xl-4 mb-7 mb-xl-0 d-flex align-items-stretch">
                <div class="card w-100" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
                  <div class="card-body p-7 p-xxl-5 d-flex flex-column gap-8">
                    <div class="d-flex flex-column gap-6">
                      <div class="hstack gap-3">
                        <h5 class="mb-0 fw-medium">Scale</h5>
                        <span class="badge text-bg-dark hstack gap-2"><iconify-icon icon="lucide:flame"
                            class="fs-5"></iconify-icon>Most popular</span>
                      </div>
                      <div class="hstack gap-2">
                        <h3 class="mb-0 text-opacity-50 text-dark"><del>$2,199</del></h3>
                        <h3 class="mb-0">$1,699</h3>
                        <p class="mb-0">/month</p>
                      </div>
                      <p class="mb-0">Perfect for growing brands needing more customization and flexibility.</p>
                    </div>
                    <div class="pt-8 border-top d-flex flex-column gap-6">
                      <h6 class="mb-0 fw-normal">What’s Included:</h6>
                      <ul class="list-unstyled d-flex flex-column gap-3 mb-0">
                        <li class="hstack gap-3">
                          <span
                            class="round-32 rounded-circle bg-primary flex-shrink-0 hstack justify-content-center"><iconify-icon
                              icon="lucide:check" class="fs-6 text-dark"></iconify-icon></span>
                          <h6 class="mb-0 fw-normal">Everything in the Launch Plan</h6>
                        </li>
                        <li class="hstack gap-3">
                          <span
                            class="round-32 rounded-circle bg-primary flex-shrink-0 hstack justify-content-center"><iconify-icon
                              icon="lucide:check" class="fs-6 text-dark"></iconify-icon></span>
                          <h6 class="mb-0 fw-normal">Custom design for up to 10 pages</h6>
                        </li>
                        <li class="hstack gap-3">
                          <span
                            class="round-32 rounded-circle bg-primary flex-shrink-0 hstack justify-content-center"><iconify-icon
                              icon="lucide:check" class="fs-6 text-dark"></iconify-icon></span>
                          <h6 class="mb-0 fw-normal">Seamless social media integration</h6>
                        </li>
                        <li class="hstack gap-3">
                          <span
                            class="round-32 rounded-circle bg-primary flex-shrink-0 hstack justify-content-center"><iconify-icon
                              icon="lucide:check" class="fs-6 text-dark"></iconify-icon></span>
                          <h6 class="mb-0 fw-normal">SEO enhancements for key pages</h6>
                        </li>
                      </ul>
                    </div>
                    <a href="" class="btn w-100 justify-content-center">
                      <span class="btn-text">Subscribe now</span>
                      <iconify-icon icon="lucide:arrow-up-right"
                        class="btn-icon bg-white text-dark round-52 rounded-circle hstack justify-content-center fs-7 shadow-sm"></iconify-icon>
                    </a>
                  </div>
                </div>
              </div>
              <div class="col-lg-6 col-xl-4 mb-7 mb-xl-0 d-flex align-items-stretch">
                <div class="card w-100" data-aos="fade-up" data-aos-delay="300" data-aos-duration="1000">
                  <div class="card-body p-7 p-xxl-5 d-flex flex-column gap-8">
                    <div class="d-flex flex-column gap-6">
                      <h5 class="mb-0 fw-medium">Elevate</h5>
                      <div class="hstack gap-2">
                        <h3 class="mb-0">$3,499</h3>
                        <p class="mb-0">/month</p>
                      </div>
                      <p class="mb-0">Best suited for established businesses wanting a fully tailored experience.</p>
                    </div>
                    <div class="pt-8 border-top d-flex flex-column gap-6">
                      <h6 class="mb-0 fw-normal">What’s Included:</h6>
                      <ul class="list-unstyled d-flex flex-column gap-3 mb-0">
                        <li class="hstack gap-3">
                          <span
                            class="round-32 rounded-circle bg-primary flex-shrink-0 hstack justify-content-center"><iconify-icon
                              icon="lucide:check" class="fs-6 text-dark"></iconify-icon></span>
                          <h6 class="mb-0 fw-normal">Everything in the Scale Plan</h6>
                        </li>
                        <li class="hstack gap-3">
                          <span
                            class="round-32 rounded-circle bg-primary flex-shrink-0 hstack justify-content-center"><iconify-icon
                              icon="lucide:check" class="fs-6 text-dark"></iconify-icon></span>
                          <h6 class="mb-0 fw-normal">E-commerce functionality (if needed)</h6>
                        </li>
                        <li class="hstack gap-3">
                          <span
                            class="round-32 rounded-circle bg-primary flex-shrink-0 hstack justify-content-center"><iconify-icon
                              icon="lucide:check" class="fs-6 text-dark"></iconify-icon></span>
                          <h6 class="mb-0 fw-normal">Branded email template design</h6>
                        </li>
                        <li class="hstack gap-3">
                          <span
                            class="round-32 rounded-circle bg-primary flex-shrink-0 hstack justify-content-center"><iconify-icon
                              icon="lucide:check" class="fs-6 text-dark"></iconify-icon></span>
                          <h6 class="mb-0 fw-normal">Priority support for six months after launch</h6>
                        </li>
                      </ul>
                    </div>
                    <a href="" class="btn w-100 justify-content-center">
                      <span class="btn-text">Subscribe now</span>
                      <iconify-icon icon="lucide:arrow-up-right"
                        class="btn-icon bg-white text-dark round-52 rounded-circle hstack justify-content-center fs-7 shadow-sm"></iconify-icon>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="d-flex flex-column gap-8" data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000">
            <p class="fs-5 mb-0 text-center text-dark">More than 320 trusted partners & clients</p>
            <div class="marquee w-100 d-flex align-items-center overflow-hidden">
              <div class="marquee-content d-flex align-items-center gap-8">
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-1.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-2.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-3.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-4.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-5.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-1.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-2.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-3.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-4.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-5.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-1.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-2.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-3.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-4.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-5.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-1.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-2.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-3.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-4.svg" alt="partners" class="img-fluid">
                </div>
                <div class="marquee-tag hstack justify-content-center">
                  <img src="../assets/images/pricing/partners-5.svg" alt="partners" class="img-fluid">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!--  FAQ Section -->
    <section class="faq py-5 py-lg-11 py-xl-12">
      <div class="container">
        <div class="d-flex flex-column gap-5 gap-xl-11">
          <div class="row gap-7 gap-xl-0">
            <div class="col-xl-4 col-xxl-4">
              <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
                data-aos-duration="1000">
                <span
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">08</span>
                <hr class="border-line bg-white">
                <span class="badge text-bg-dark">FAQs</span>
              </div>
            </div>
            <div class="col-xl-8 col-xxl-7">
              <div class="row">
                <div class="col-xxl-9">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                    <h2 class="mb-0">Frequently asked questions</h2>
                    <p class="fs-5 mb-0 text-opacity-70">Discover how we tailor our solutions to meet unique needs,
                      delivering impactful strategies, personalized branding, and exceptional customer experiences.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row justify-content-end">
            <div class="col-xl-8">
              <div class="accordion accordion-flush" id="accordionFlushExample" data-aos="fade-up" data-aos-delay="200"
                data-aos-duration="1000">
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed fs-8 fw-bold" type="button" data-bs-toggle="collapse"
                      data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
                      What services does your agency offer?
                    </button>
                  </h2>
                  <div id="flush-collapseOne" class="accordion-collapse collapse"
                    data-bs-parent="#accordionFlushExample">
                    <div class="accordion-body pt-0 fs-5 text-dark">Yes, we provide post-launch support to ensure smooth
                      implementation and offer ongoing maintenance packages for clients needing regular updates or
                      technical assistance.</div>
                  </div>
                </div>
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed fs-8 fw-bold" type="button" data-bs-toggle="collapse"
                      data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo">
                      How long does a typical project take?
                    </button>
                  </h2>
                  <div id="flush-collapseTwo" class="accordion-collapse collapse"
                    data-bs-parent="#accordionFlushExample">
                    <div class="accordion-body pt-0 fs-5 text-dark">Yes, we provide post-launch support to ensure smooth
                      implementation and offer ongoing maintenance packages for clients needing regular updates or
                      technical assistance.</div>
                  </div>
                </div>
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed fs-8 fw-bold" type="button" data-bs-toggle="collapse"
                      data-bs-target="#flush-collapseThree" aria-expanded="false" aria-controls="flush-collapseThree">
                      Do you offer custom designs, or do you use templates?
                    </button>
                  </h2>
                  <div id="flush-collapseThree" class="accordion-collapse collapse"
                    data-bs-parent="#accordionFlushExample">
                    <div class="accordion-body pt-0 fs-5 text-dark">Yes, we provide post-launch support to ensure smooth
                      implementation and offer ongoing maintenance packages for clients needing regular updates or
                      technical assistance.</div>
                  </div>
                </div>
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed fs-8 fw-bold" type="button" data-bs-toggle="collapse"
                      data-bs-target="#flush-collapseFour" aria-expanded="false" aria-controls="flush-collapseFour">
                      What’s the cost of a project?
                    </button>
                  </h2>
                  <div id="flush-collapseFour" class="accordion-collapse collapse"
                    data-bs-parent="#accordionFlushExample">
                    <div class="accordion-body pt-0 fs-5 text-dark">Yes, we provide post-launch support to ensure smooth
                      implementation and offer ongoing maintenance packages for clients needing regular updates or
                      technical assistance.</div>
                  </div>
                </div>
                <div class="accordion-item border-bottom">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed fs-8 fw-bold" type="button" data-bs-toggle="collapse"
                      data-bs-target="#flush-collapseFive" aria-expanded="false" aria-controls="flush-collapseFive">
                      Do you provide ongoing support after project completion?
                    </button>
                  </h2>
                  <div id="flush-collapseFive" class="accordion-collapse collapse"
                    data-bs-parent="#accordionFlushExample">
                    <div class="accordion-body pt-0 fs-5 text-dark">Yes, we provide post-launch support to ensure smooth
                      implementation and offer ongoing maintenance packages for clients needing regular updates or
                      technical assistance.</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!--  Recent news Section -->
    <section class="Recent-news bg-light-gray py-5 py-lg-11 py-xl-12">
      <div class="container">
        <div class="d-flex flex-column gap-5 gap-xl-11">
          <div class="row gap-7 gap-xl-0">
            <div class="col-xl-4 col-xxl-4">
              <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
                data-aos-duration="1000">
                <span
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">09</span>
                <hr class="border-line bg-white">
                <span class="badge text-bg-dark">Resources</span>
              </div>
            </div>
            <div class="col-xl-8 col-xxl-7">
              <div class="row">
                <div class="col-xxl-8">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                    <h2 class="mb-0">Recent news</h2>
                    <p class="fs-5 mb-0 text-opacity-70">Explore the latest trends, bold projects, and creative insights
                      from our agency—shaping the future of branding, digital experiences, and storytelling.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-xl-6 mb-7 mb-xl-0">
              <div class="resources d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                data-aos-duration="1000">
                <a href="blog-detail.html"
                  class="resources-img resources-img-first position-relative overflow-hidden d-block">
                  <img src="../assets/images/resources/resources-1.jpg" alt="resources" class="img-fluid">
                </a>
                <div class="resources-details">
                  <p class="mb-0">Dec 24, 2025</p>
                  <h4 class="mb-0">A campaign that connects</h4>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-7 mb-xl-0">
              <div class="resources d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="200"
                data-aos-duration="1000">
                <a href="blog-detail.html" class="resources-img position-relative overflow-hidden d-block">
                  <img src="../assets/images/resources/resources-2.jpg" alt="resources" class="img-fluid">
                </a>
                <div class="resources-details">
                  <p class="mb-0">Dec 24, 2025</p>
                  <h4 class="mb-0">An breaking boundaries our latest brand redesign</h4>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-7 mb-xl-0">
              <div class="resources d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="300"
                data-aos-duration="1000">
                <a href="blog-detail.html" class="resources-img position-relative overflow-hidden d-block">
                  <img src="../assets/images/resources/resources-3.jpg" alt="resources" class="img-fluid">
                </a>
                <div class="resources-details">
                  <p class="mb-0">Dec 24, 2025</p>
                  <h4 class="mb-0">Recognized for design</h4>
                </div>
              </div>
            </div>
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
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">10</span>
                <hr class="border-line bg-white">
                <span class="badge text-bg-dark">Contact us</span>
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
                and create something amazing! Tell me about your project—I’m all
                ears.</p>
            </div>
            <div class="col-xl-8">
              <form class="d-flex flex-column gap-7" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
                <div>
                  <input type="text" class="form-control border-bottom border-dark" id="formGroupExampleInput"
                    placeholder="Name">
                </div>
                <div>
                  <input type="email" class="form-control border-bottom border-dark" id="exampleInputEmail1"
                    placeholder="Email" aria-describedby="emailHelp">
                </div>
                <div>
                  <textarea class="form-control border-bottom border-dark" id="exampleFormControlTextarea1"
                    placeholder="Tell us about your project" rows="3"></textarea>
                </div>
                <button type="submit" class="btn w-100 justify-content-center">
                  <span class="btn-text">Submit message</span>
                  <iconify-icon icon="lucide:arrow-up-right"
                    class="btn-icon bg-white text-dark round-52 rounded-circle hstack justify-content-center fs-7 shadow-sm"></iconify-icon>
                </button>
              </form>
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
            <li><a class="link-hover fs-5 text-white" href="index.html">Home</a></li>
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

