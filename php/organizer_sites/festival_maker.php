<?php
ini_set('display_errors', 0);
ini_set('error_reporting', E_NOTICE );
// --- BEGIN: All PHP logic and redirects must be before any HTML output ---
if (!isset($_SESSION)) session_start();
// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    var_dump($_SESSION);
    header('Location: ../sign-in.php');
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';

// Use the same genres as in sign-up
$musicGenres = [
    'Ambient','Bass','Breakbeat','Classical','Country','Dance','Deep House','Disco','Drum & Bass','Dubstep','EDM','Electro',
    'Folk','Hardcore','Hardstyle','Hip-Hop','House','Indie','Jazz','K-Pop','Latin','Metal','Minimal','Pop','Progressive House',
    'Psytrance','Punk','R&B','Rap','Reggae','Reggaeton','Rock','Soul','Tech House','Techno','Trance','Trap','Trip-Hop'
];
sort($musicGenres, SORT_NATURAL | SORT_FLAG_CASE);

// Initialize database connection
$pdo = db_connect();

// Get all venues for the dropdown
$venues = [];
try {
    $stmt = $pdo->query("SELECT id, name, city, country FROM venues ORDER BY name");
    $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching venues: " . $e->getMessage());
    $venues = [];
}

// --- Error and form data handling ---
$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- Validate form data before any output ---
        $name = trim($_POST['name'] ?? '');
        $slogan = trim($_POST['slogan'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $venue_id = (int)($_POST['venue_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $lineup = trim($_POST['lineup'] ?? '');
        $organizer_id = $_SESSION['user_id'] ?? 0;
        $total_tickets = (int)($_POST['total_tickets'] ?? 0);
        $genres = $_POST['genres'] ?? [];

        $missing_fields = [];
        if (empty($name)) $missing_fields[] = "Event name";
        if (empty($slogan)) $missing_fields[] = "Event slogan";
        if (empty($start_date)) $missing_fields[] = "Start date";
        if (empty($end_date)) $missing_fields[] = "End date";
        if ($venue_id <= 0) $missing_fields[] = "Venue";
        if (empty($description)) $missing_fields[] = "Description";
        if (empty($lineup)) $missing_fields[] = "Lineup";
        if (empty($total_tickets)) $missing_fields[] = "Total tickets";
        if (empty($genres)) $missing_fields[] = "At least one music genre";

        // --- Date validation ---
        $dateError = '';
        if (!empty($start_date) && !empty($end_date)) {
            try {
                $start = new DateTime($start_date);
                $end = new DateTime($end_date);
                $now = new DateTime();
                $minEnd = clone $start;
                $minEnd->modify('+1 day');
                if ($start < $now) {
                    $dateError = "Start date must be in the future.";
                } elseif ($end < $minEnd) {
                    $dateError = "End date must be at least 1 day after start date (including time).";
                }
            } catch (Exception $e) {
                $dateError = "Invalid date format.";
            }
        }
        if ($dateError) $missing_fields[] = $dateError;

        if (!empty($missing_fields)) {
            $_SESSION['form_errors'][] = "Please fill in the following required fields: " . implode(", ", $missing_fields);
            $_SESSION['form_data'] = $_POST;
            header('Location: festival_maker.php');
            exit();
        }

        // --- File upload validation ---
        $cover_image_url = null;
        $fileExt = null;
        $upload_dir = __DIR__ . '/../../assets/images/portfolio/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $fileExt = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($fileExt, $allowed_extensions)) {
                $_SESSION['form_errors'][] = "Invalid file type. Only JPG, PNG, and WEBP are allowed.";
                $_SESSION['form_data'] = $_POST;
                header('Location: festival_maker.php');
                exit();
            }
            $tempFileName = uniqid('event_tmp_') . '.' . $fileExt;
            $tempFilePath = $upload_dir . $tempFileName;
            if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], $tempFilePath)) {
                $_SESSION['form_errors'][] = "Failed to upload image. Please try again.";
                $_SESSION['form_data'] = $_POST;
                header('Location: festival_maker.php');
                exit();
            }
        } else {
            $_SESSION['form_errors'][] = "Cover image is required.";
            $_SESSION['form_data'] = $_POST;
            header('Location: festival_maker.php');
            exit();
        }

        // --- Insert event with temporary image path ---
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO events (
                name, slogan, start_date, end_date, venue_id, 
                description, lineup, cover_image, organizer_id, total_tickets
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $success = $stmt->execute([
            $name,
            $slogan,
            $start_date,
            $end_date,
            $venue_id,
            $description,
            $lineup,
            '', // Temporary, will update after file rename
            $organizer_id,
            $total_tickets
        ]);
        if (!$success) {
            $pdo->rollBack();
            $_SESSION['form_errors'][] = "Failed to create event. Please try again.";
            $_SESSION['form_data'] = $_POST;
            header('Location: festival_maker.php');
            exit();
        }
        $event_id = $pdo->lastInsertId();

        // --- Rename/move the file to the correct name ---
        $finalFileName = "portfolio-img-{$event_id}." . $fileExt;
        $finalFilePath = $upload_dir . $finalFileName;
        if (!rename($tempFilePath, $finalFilePath)) {
            $pdo->rollBack();
            $_SESSION['form_errors'][] = "Failed to finalize image upload.";
            $_SESSION['form_data'] = $_POST;
            header('Location: festival_maker.php');
            exit();
        }
        $cover_image_url = "http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/{$finalFileName}";

        // --- Update event with correct image URL ---
        $updateStmt = $pdo->prepare("UPDATE events SET cover_image = ? WHERE id = ?");
        $updateStmt->execute([$cover_image_url, $event_id]);

        // Save genres to event_categories
        $catStmt = $pdo->prepare("INSERT INTO event_categories (event_id, category) VALUES (?, ?)");
        foreach ($genres as $genre) {
            if (in_array($genre, $musicGenres)) {
                $catStmt->execute([$event_id, $genre]);
            }
        }

        // Process ticket types
        if (isset($_POST['ticket_types']) && is_array($_POST['ticket_types'])) {
            $ticketStmt = $pdo->prepare("
                INSERT INTO ticket_types (event_id, ticket_type, price, remaining_tickets)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($_POST['ticket_types'] as $ticket) {
                if (!empty($ticket['type']) && isset($ticket['price']) && !empty($ticket['quantity'])) {
                    $ticketType = in_array(strtolower($ticket['type']), ['regular', 'vip'])
                        ? strtolower($ticket['type'])
                        : 'regular';

                    $ticketStmt->execute([
                        $event_id,
                        $ticketType,
                        (int)$ticket['price'],
                        (int)$ticket['quantity']
                    ]);
                }
            }
        }

        $pdo->commit();
        header('Location: myevents.php?created=1');
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['form_errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        header('Location: festival_maker.php');
        exit();
    }
}

// Update image path structure
$base_url = 'http://localhost:63342/Diplomamunka-26222041';
$image_path = $base_url . '/assets/images/portfolio/';

// --- END: All PHP logic and redirects must be before any HTML output ---

include '../header.php';
?>

<!-- Page Content -->
<div class="page-wrapper">
    <div class="container py-4">
      <div class="row justify-content-center">
        <div class="col-lg-10">
          <!-- Page Header -->
          <div class="mb-4" data-aos="fade-down" data-aos-duration="700">
            <h1 class="fw-bold" style="margin-top: 1em;">Create New Event</h1>
            <p class="text-muted">Fill in the details below to create your event<span class="text-danger"> (Every field is required!)</span></p>
          </div>
          
          <?php if (!empty($formErrors)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert" data-aos="fade-in" data-aos-duration="700">
            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i> Please fix the following errors:</h5>
            <ul class="mb-0">
              <?php foreach ($formErrors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
              <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>
          
          <?php if (isset($successMessage)): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert" data-aos="fade-in" data-aos-duration="700">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($successMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>

          <div class="form-section">
            <form class="needs-validation" novalidate action="festival_maker.php" method="post" enctype="multipart/form-data">
              <!-- Basic Information -->
              <div class="card mb-4" data-aos="zoom-in-up" data-aos-delay="100">
                <div class="card-header bg-light">
                  <h5 class="mb-0 text-primary">Basic Information</h5>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label for="name" class="form-label required">Event Name</label>
                      <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>" required>
                      <div class="invalid-feedback">Please provide an event name.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="slogan" class="form-label required">Event Slogan</label>
                      <input type="text" class="form-control" id="slogan" name="slogan" value="<?php echo htmlspecialchars($formData['slogan'] ?? ''); ?>" required>
                      <div class="invalid-feedback">Please provide a slogan.</div>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-6">
                      <label for="start_date" class="form-label required">Start Date & Time</label>
                      <input type="datetime-local" class="form-control" id="start_date" name="start_date"
                        value="<?php echo htmlspecialchars($formData['start_date'] ?? ''); ?>"
                        min="<?= date('Y-m-d\TH:i') ?>" required>
                      <div class="invalid-feedback">Please provide a valid start date and time.</div>
                    </div>
                    <div class="col-md-6">
                      <label for="end_date" class="form-label required">End Date & Time</label>
                      <input type="datetime-local" class="form-control" id="end_date" name="end_date"
                        value="<?php echo htmlspecialchars($formData['end_date'] ?? ''); ?>"
                        min="<?= date('Y-m-d\TH:i', strtotime('+1 day')) ?>" required>
                      <div class="invalid-feedback">Please provide a valid end date and time.</div>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label for="venue_id" class="form-label required">Venue</label>
                    <select class="form-select" id="venue_id" name="venue_id" required>
                      <option value="" disabled <?php echo !isset($formData['venue_id']) ? 'selected' : ''; ?>>Select a venue</option>
                      <?php foreach ($venues as $venue) { ?>
                        <option value="<?php echo $venue['id']; ?>" <?php echo (isset($formData['venue_id']) && $formData['venue_id'] == $venue['id']) ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($venue['name']); ?> (<?php echo $venue['city']; ?>, <?php echo $venue['country']; ?>)
                        </option>
                      <?php } ?>
                    </select>
                    <div class="invalid-feedback">Please select a venue.</div>
                  </div>
                  <div class="mb-3">
                    <label for="description" class="form-label required">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                    <div class="invalid-feedback">Please provide a description.</div>
                  </div>
                  <div class="mb-3">
                    <label for="lineup" class="form-label required">Lineup (comma-separated)</label>
                    <textarea class="form-control" id="lineup" name="lineup" rows="2" required><?php echo htmlspecialchars($formData['lineup'] ?? ''); ?></textarea>
                    <div class="form-text">Separate artist names with commas</div>
                    <div class="invalid-feedback">Please provide a lineup.</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label required">Music Genres</label>
                    <div>
                      <?php foreach ($musicGenres as $genre): ?>
                        <label class="form-check-label genre-checkbox">
                          <input class="form-check-input" type="checkbox" name="genres[]" value="<?php echo $genre; ?>"
                            <?php if (!empty($formData['genres']) && in_array($genre, $formData['genres'])) echo 'checked'; ?>
                                 style="margin: 0 0.5em;" required>
                          <?php echo htmlspecialchars($genre); ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                    <div class="invalid-feedback">Please select at least one genre.</div>
                  </div>
                </div>
              </div>

              <!-- Cover Image -->
              <div class="card mb-4" data-aos="zoom-in-up" data-aos-delay="200">
                <div class="card-header bg-light">
                  <h5 class="mb-0 text-primary">Cover Image</h5>
                </div>
                <div class="card-body">
                  <div class="mb-3">
                    <label for="cover_image" class="form-label required">Cover Image</label>
                    <div class="custom-image-upload mb-2" id="customImageUpload" tabindex="0" role="button" aria-label="Upload cover image">
                      <input class="form-control visually-hidden" type="file" id="cover_image" name="cover_image" accept="image/jpeg, image/png, image/webp" required>
                      <div class="upload-area d-flex flex-column align-items-center justify-content-center">
                        <iconify-icon icon="mdi:image-plus" style="font-size: 2.5rem; color: #1a73e8;"></iconify-icon>
                        <span class="mt-2 text-secondary">Click or drag image here to upload</span>
                      </div>
                      <img id="imagePreview" class="preview-image mt-2" style="display:none; max-width: 100%; border-radius: 8px;" />
                    </div>
                    <div class="invalid-feedback">Please upload a valid image (JPEG, PNG, or WebP) under 5MB.</div>
                    <div class="form-text">Recommended size: 1200x630px, max 5MB</div>
                  </div>
                </div>
              </div>

              <!-- Ticket Types -->
              <div class="card mb-4" data-aos="zoom-in-up" data-aos-delay="300">
                <div class="card-header bg-light">
                  <h5 class="mb-0 text-primary">Ticket Types</h5>
                </div>
                <div class="card-body">
                  <div id="ticketTypes">
                    <!-- Regular Ticket -->
                    <div class="ticket-type mb-3 p-3 border rounded" style="background: #eaf1ff;">
                      <div class="row g-3 align-items-center">
                        <div class="col-md-4 d-flex align-items-center">
                          <span class="badge" style="background: #1a73e8; color: #fff; font-size: 1rem; padding: 8px 18px;">Regular</span>
                        </div>
                        <div class="col-md-4">
                          <label class="form-label">Price (HUF)
                          <input type="number" class="form-control" name="ticket_types[0][price]" min="0" step="100"
                            value="<?php echo htmlspecialchars($formData['ticket_types'][0]['price'] ?? ''); ?>" required>
                              <span class="form-text text-muted">At least 100 HUF</span>
                        </div>
                        <div class="col-md-4">
                          <label class="form-label">Quantity
                          <input type="number" class="form-control" name="ticket_types[0][quantity]" min="1"
                            value="<?php echo htmlspecialchars($formData['ticket_types'][0]['quantity'] ?? ''); ?>" required>
                              <span class="form-text text-muted">At least 1 piece</span>
                        </div>
                        <input type="hidden" name="ticket_types[0][type]" value="regular">
                      </div>
                    </div>
                    <!-- VIP Ticket -->
                    <div class="ticket-type mb-3 p-3 border rounded" style="background: linear-gradient(135deg, #FFD700 0%, #F0C000 100%);">
                      <div class="row g-3 align-items-center">
                        <div class="col-md-4 d-flex align-items-center">
                          <span class="badge" style="background: #000; color: #FFD700; font-size: 1rem; padding: 8px 18px;">VIP</span>
                        </div>
                        <div class="col-md-4">
                          <label class="form-label">Price (HUF)
                          <input type="number" class="form-control" name="ticket_types[1][price]" min="0" step="100"
                            value="<?php echo htmlspecialchars($formData['ticket_types'][1]['price'] ?? ''); ?>" required>
                              <span class="form-text text-muted">At least 100 HUF</span>
                          </div>
                        <div class="col-md-4">
                          <label class="form-label">Quantity
                          <input type="number" class="form-control" name="ticket_types[1][quantity]" min="1"
                            value="<?php echo htmlspecialchars($formData['ticket_types'][1]['quantity'] ?? ''); ?>" required>
                              <span class="form-text text-muted">At least 1 piece</span>
                        </div>
                        <input type="hidden" name="ticket_types[1][type]" value="vip">
                      </div>
                    </div>
                  </div>
                  <div class="form-text text-muted">Both Regular and VIP ticket types are required.</div>
                </div>
              </div>

              <!-- Total Tickets (hidden, auto-calculated) -->
              <input type="hidden" id="total_tickets" name="total_tickets" value="<?php echo htmlspecialchars($formData['total_tickets'] ?? ''); ?>" required>

              <!-- Submit Button -->
              <div class="d-grid gap-2 d-md-flex justify-content-md-end" data-aos="fade-up" data-aos-delay="400">
                <a href="myevents.php" class="btn btn-outline-secondary me-md-2">Cancel</a>
                <button type="submit" class="btn btn-primary">
                  <iconify-icon icon="lucide:save" class="me-1"></iconify-icon> Create Event
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

<?php include '../footer.php'; ?>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
<script src="../../assets/libs/owl.carousel/dist/owl.carousel.min.js"></script>
<link href="../../assets/libs/aos-master/dist/aos.css" rel="stylesheet">
<script src="../../assets/libs/aos-master/dist/aos.js"></script>
<script src="../../assets/js/custom.js"></script>

<style>
.form-control, .form-select {
    border: 2px solid #ced4da !important;
    padding: 8px !important;
    background-clip: padding-box;
    box-shadow: none !important;
}
.form-control:focus, .form-select:focus {
    border-color: #1a73e8 !important;
    box-shadow: 0 0 0 0.15rem rgba(26,115,232,.15) !important;
}
.custom-image-upload {
    border: 2px dashed #1a73e8;
    border-radius: 10px;
    padding: 24px;
    cursor: pointer;
    background: #f8fafc;
    transition: border-color 0.2s;
    position: relative;
    min-height: 140px;
    outline: none;
}
.custom-image-upload:focus, .custom-image-upload:hover {
    border-color: #0d47a1;
    background: #f1f7ff;
}
.custom-image-upload input[type="file"] {
    display: none;
}
.custom-image_upload .upload-area {
    pointer-events: none;
}
.custom-image-upload.dragover {
    border-color: #388e3c;
    background: #e8f5e9;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');

    // Validate dates when they change
    function validateDates() {
        const start = new Date(startDateInput.value);
        const end = new Date(endDateInput.value);
        const now = new Date();
        const minEndDate = new Date(start);
        minEndDate.setDate(minEndDate.getDate() + 1);
        const maxEndDate = new Date(start);
        maxEndDate.setMonth(maxEndDate.getMonth() + 1);

        // Set min attribute for start date (today)
        const today = new Date();
        today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
        startDateInput.min = today.toISOString().slice(0, 16);

        // Set min and max attribute for end date (start date + 1 day, start date + 1 month)
        if (start && start >= now) {
            const minEnd = new Date(start);
            minEnd.setDate(minEnd.getDate() + 1);
            minEnd.setMinutes(minEnd.getMinutes() - minEnd.getTimezoneOffset());
            endDateInput.min = minEnd.toISOString().slice(0, 16);

            const maxEnd = new Date(start);
            maxEnd.setMonth(maxEnd.getMonth() + 1);
            maxEnd.setMinutes(maxEnd.getMinutes() - maxEnd.getTimezoneOffset());
            endDateInput.max = maxEnd.toISOString().slice(0, 16);
        }

        // Check if end date is at least 1 day after start and no more than 1 month after start
        let isValid = true;
        let message = '';
        if (start && end) {
            if (end < minEndDate || end > maxEndDate) {
                isValid = false;
                message = 'End date must be at least 1 day and no more than one month after start date';
            }
        }
        return {
            isValid: isValid,
            message: message
        };
    }

    startDateInput.addEventListener('change', validateDates);
    endDateInput.addEventListener('change', validateDates);

    // Custom image upload logic
    const customUpload = document.getElementById('customImageUpload');
    const fileInput = document.getElementById('cover_image');
    const preview = document.getElementById('imagePreview');
    const uploadArea = customUpload.querySelector('.upload-area');

    // Click or keyboard triggers file input
    customUpload.addEventListener('click', function(e) {
        if (e.target !== fileInput) fileInput.click();
    });
    customUpload.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            fileInput.click();
        }
    });

    // Drag & drop support
    customUpload.addEventListener('dragover', function(e) {
        e.preventDefault();
        customUpload.classList.add('dragover');
    });
    customUpload.addEventListener('dragleave', function(e) {
        e.preventDefault();
        customUpload.classList.remove('dragover');
    });
    customUpload.addEventListener('drop', function(e) {
        e.preventDefault();
        customUpload.classList.remove('dragover');
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            showPreview(fileInput);
        }
    });

    // Show preview on file select
    fileInput.addEventListener('change', function() {
        showPreview(fileInput);
    });

    function showPreview(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            const file = input.files[0];
            const fileExt = file.name.split('.').pop().toLowerCase();
            const validTypes = ['jpg', 'jpeg', 'png', 'webp'];
            if (!validTypes.includes(fileExt)) {
                alert('Please upload a valid image file (jpg, png, or webp)');
                input.value = '';
                preview.style.display = 'none';
                return;
            }
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                uploadArea.style.display = 'none';
            }
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
            uploadArea.style.display = 'flex';
        }
    }

    // Auto-calculate total tickets when quantities change
    document.querySelectorAll('input[name^="ticket_types"][name$="[quantity]"]').forEach(function(input) {
        input.addEventListener('change', function() {
            let total = 0;
            document.querySelectorAll('input[name^="ticket_types"][name$="[quantity]"]').forEach(function(input) {
                let val = parseInt(input.value, 10);
                if (isNaN(val) || val < 0) val = 0;
                input.value = val;
                total += val;
            });
            document.getElementById('total_tickets').value = total;
        });
    });

    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;
        let errors = [];

        // Validate dates
        const dateValidation = validateDates();
        if (!dateValidation.isValid) {
            isValid = false;
            errors.push(dateValidation.message);
        }

        // Validate file
        const fileInput = document.getElementById('cover_image');
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
            const maxSize = 5 * 1024 * 1024; // 5MB

            if (!validTypes.includes(file.type)) {
                isValid = false;
                errors.push('Please select a valid image file (JPEG, PNG, or WebP)');
            }

            if (file.size > maxSize) {
                isValid = false;
                errors.push('File size must be less than 5MB');
            }
        }

        // Validate tickets
        let regularTicketFound = false;
        let vipTicketFound = false;
        let totalTickets = 0;

        document.querySelectorAll('.ticket-type').forEach(function(ticketTypeDiv, idx) {
            const priceInput = ticketTypeDiv.querySelector('input[name^="ticket_types"][name$="[price]"]');
            const quantityInput = ticketTypeDiv.querySelector('input[name^="ticket_types"][name$="[quantity]"]');
            let price = parseInt(priceInput.value, 10);
            let quantity = parseInt(quantityInput.value, 10);

            if (isNaN(price)) price = 0;
            if (isNaN(quantity)) quantity = 0;
            if (quantity < 0) quantity = 0;
            if (price < 0) price = 0;

            priceInput.value = price;
            quantityInput.value = quantity;

            // Enforce minimum price
            if (price > 0 && price < 100) {
                isValid = false;
                errors.push('Ticket price must be at least 100 HUF.');
            }
            if (price < 0) {
                isValid = false;
                errors.push('Ticket price cannot be negative.');
            }
            if (quantity < 0) {
                isValid = false;
                errors.push('Ticket quantity cannot be negative.');
            }

            // Check ticket type
            const type = ticketTypeDiv.querySelector('input[type="hidden"][name$="[type]"]').value;
            if (type === 'regular' && quantity > 0) regularTicketFound = true;
            if (type === 'vip' && quantity > 0) vipTicketFound = true;

            totalTickets += quantity;
        });

        if (!regularTicketFound || !vipTicketFound) {
            isValid = false;
            errors.push('Both Regular and VIP ticket types are required (with at least 1 ticket each).');
        }

        if (totalTickets < 0) {
            isValid = false;
            errors.push('Total ticket quantity cannot be negative.');
        }

        // Validate genres
        const genres = document.querySelectorAll('input[name="genres[]"]:checked');
        if (genres.length === 0) {
            isValid = false;
            errors.push('Please select at least one music genre');
        }

        if (!isValid) {
            e.preventDefault();
            alert('Please fix the following errors:\n\n' + errors.join('\n'));
        }
    });

    // Ensure AOS is initialized after DOM and CSS are loaded
    setTimeout(function() {
        if (window.AOS) {
            AOS.init({
                once: true,
                duration: 700
            });
        }
    }, 100);
});
</script>
</body>
</html>
