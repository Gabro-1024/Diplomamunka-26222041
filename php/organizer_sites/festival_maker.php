<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    var_dump($_SESSION);
    header('Location: ../sign-in.php');
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';

$musicGenres = [
    'Bass', 'Hardcore', 'House', 'Metal', 'Minimal', 'Pop',
    'Progressive House', 'Psytrance', 'Reggae', 'Rock', 'Techno', 'Trance'
];

// Initialize database connection
$pdo = db_connect();

// Get all venues for the dropdown
$venues = [];
try {
    $stmt = $pdo->query("SELECT id, name, city, country FROM venues ORDER BY name");
    $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching venues: " . $e->getMessage());
    // Set empty array to prevent errors in the form
    $venues = [];
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Process form data
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

        // All fields required
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

        if (!empty($missing_fields)) {
            throw new Exception("Please fill in the following required fields: " . implode(", ", $missing_fields));
        }

        // Handle file upload
        $cover_image = 'default-event.jpg';
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../assets/images/events/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
            }
            
            $cover_image = uniqid('event_') . '.' . $file_extension;
            if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_dir . $cover_image)) {
                throw new Exception("Failed to upload image. Please try again.");
            }
        }

        // Insert event into database
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
            $cover_image,
            $organizer_id,
            $total_tickets
        ]);

        if (!$success) {
            throw new Exception("Failed to create event. Please try again.");
        }

        $event_id = $pdo->lastInsertId();

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
                    // Ensure ticket_type is either 'regular' or 'vip'
                    $ticketType = in_array(strtolower($ticket['type']), ['regular', 'vip'])
                        ? strtolower($ticket['type'])
                        : 'regular';

                    $ticketStmt->execute([
                        $event_id,
                        $ticketType,
                        (int)$ticket['price'],
                        (int)$ticket['quantity']  // Using quantity as remaining_tickets initially
                    ]);
                }
            }
        }

        $pdo->commit();
        header('Location: myevents.php?created=1');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error creating event: " . $e->getMessage());
        $_SESSION['form_errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        header('Location: festival_maker.php');
        exit();
    }
}

// Update image path structure
$base_url = 'http://localhost:63342/Diplomamunka-26222041';
$image_path = $base_url . '/assets/images/portfolio/';
?>

<?php
// Start the session and check if user is logged in as organizer
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Get any form errors from session
$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];

// Clear the errors after we've retrieved them
unset($_SESSION['form_errors']);
unset($_SESSION['form_data']);
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create New Event - Tickets @ Gábor</title>
  <link rel="shortcut icon" type="image/png" href="../../assets/images/logos/favicon.svg" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <style>
    .page-wrapper {
        padding-top: 120px;
        padding-bottom: 60px;
    }
    .form-section {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(0,0,0,0.1);
        padding: 30px;
    }
    .form-label {
        font-weight: 600;
        margin-bottom: 8px;
    }
    .required:after {
        content: " *";
        color: #dc3545;
    }
    .ticket-type {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        border: 1px solid #dee2e6;
    }
    .preview-image {
        max-width: 200px;
        max-height: 200px;
        object-fit: cover;
        border-radius: 8px;
        margin-top: 10px;
        display: none;
    }
    .genre-checkbox { margin-right: 10px; }
  </style>
</head>

<body class="bg-light">
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
      <a class="navbar-brand" href="myevents.php">
        <iconify-icon icon="lucide:music-2" class="me-2"></iconify-icon>
        Event Manager
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="myevents.php">My Events</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="festival_maker.php">Create Event</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="../logout.php">Logout</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Page Content -->
  <div class="page-wrapper">
    <div class="container py-4">
      <div class="row justify-content-center">
        <div class="col-lg-10">
          <!-- Page Header -->
          <div class="mb-4">
            <h1 class="fw-bold">Create New Event</h1>
            <p class="text-muted">Fill in the details below to create your event</p>
          </div>
          
          <?php if (!empty($formErrors)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
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
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($successMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>

          <div class="form-section">
            <form class="needs-validation" novalidate action="festival_maker.php" method="post" enctype="multipart/form-data">
              <!-- Basic Information -->
              <div class="card mb-4">
                <div class="card-header bg-light">
                  <h5 class="mb-0">Basic Information</h5>
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
                            <?php if (!empty($formData['genres']) && in_array($genre, $formData['genres'])) echo 'checked'; ?> required>
                          <?php echo htmlspecialchars($genre); ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                    <div class="invalid-feedback">Please select at least one genre.</div>
                  </div>
                </div>
              </div>

              <!-- Cover Image -->
              <div class="card mb-4">
                <div class="card-header bg-light">
                  <h5 class="mb-0">Cover Image</h5>
                </div>
                <div class="card-body">
                  <div class="mb-3">
                    <label for="cover_image" class="form-label required">Cover Image</label>
                    <input class="form-control" type="file" id="cover_image" name="cover_image" accept="image/jpeg, image/png, image/webp" required onchange="previewImage(this)">
                    <div class="invalid-feedback">Please upload a valid image (JPEG, PNG, or WebP) under 5MB.</div>
                    <div class="form-text">Recommended size: 1200x630px, max 5MB</div>
                    <img id="imagePreview" class="preview-image" style="display:none;" />
                  </div>
                </div>
              </div>

              <!-- Ticket Types -->
              <div class="mb-4">
                <h5 class="mb-3">Ticket Types <span class="text-danger">*</span></h5>
                <div id="ticketTypes">
                  <div class="ticket-type mb-3 p-3 border rounded">
                    <div class="row g-3">
                      <div class="col-md-4">
                        <label class="form-label">Ticket Type</label>
                        <select class="form-select" name="ticket_types[0][type]" required>
                          <option value="regular" selected>Regular</option>
                        </select>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Price (HUF) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="ticket_types[0][price]" min="0" step="100"
                          value="<?php echo htmlspecialchars($formData['ticket_types'][0]['price'] ?? ''); ?>" required>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="ticket_types[0][quantity]" min="1"
                          value="<?php echo htmlspecialchars($formData['ticket_types'][0]['quantity'] ?? ''); ?>" required>
                      </div>
                    </div>
                  </div>
                  <div class="ticket-type mb-3 p-3 border rounded">
                    <div class="row g-3">
                      <div class="col-md-4">
                        <label class="form-label">Ticket Type</label>
                        <select class="form-select" name="ticket_types[1][type]" required>
                          <option value="vip" selected>VIP</option>
                        </select>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Price (HUF) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="ticket_types[1][price]" min="0" step="100"
                          value="<?php echo htmlspecialchars($formData['ticket_types'][1]['price'] ?? ''); ?>" required>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="ticket_types[1][quantity]" min="1"
                          value="<?php echo htmlspecialchars($formData['ticket_types'][1]['quantity'] ?? ''); ?>" required>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="form-text text-muted">Both Regular and VIP ticket types are required.</div>
              </div>

              <!-- Total Tickets (hidden, auto-calculated) -->
              <input type="hidden" id="total_tickets" name="total_tickets" value="<?php echo htmlspecialchars($formData['total_tickets'] ?? ''); ?>" required>

              <!-- Submit Button -->
              <div class="d-grid gap-2 d-md-flex justify-content-md-end">
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

  <!-- Footer -->
  <footer class="bg-dark text-white py-4 mt-5">
    <div class="container">
      <div class="text-center">
        <p class="mb-0">&copy; <?php echo date('Y'); ?> Event Manager. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <!-- JavaScript -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

        // Set min attribute for start date (today)
        const today = new Date();
        today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
        startDateInput.min = today.toISOString().slice(0, 16);

        // Set min attribute for end date (start date + 1 day)
        if (start && start >= now) {
            const minEnd = new Date(start);
            minEnd.setDate(minEnd.getDate() + 1);
            minEnd.setMinutes(minEnd.getMinutes() - minEnd.getTimezoneOffset());
            endDateInput.min = minEnd.toISOString().slice(0, 16);
        }

        return {
            isValid: (!start || !end) ? true : (end >= minEndDate),
            message: 'End date must be at least 1 day after start date'
        };
    }

    startDateInput.addEventListener('change', validateDates);
    endDateInput.addEventListener('change', validateDates);

    // Update the image preview function
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            const file = input.files[0];
            const fileExt = file.name.split('.').pop().toLowerCase();

            // Validate file type
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
            }
        }
    }

    // Auto-calculate total tickets when quantities change
    document.querySelectorAll('input[name^="ticket_types"][name$="[quantity]"]').forEach(function(input) {
        input.addEventListener('change', function() {
            let total = 0;
            document.querySelectorAll('input[name^="ticket_types"][name$="[quantity]"]').forEach(function(input) {
                total += parseInt(input.value) || 0;
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

        document.querySelectorAll('input[name^="ticket_types"][name$="[quantity]"]').forEach(function(input) {
            const quantity = parseInt(input.value) || 0;
            const type = input.closest('.ticket-type').querySelector('select').value;

            if (quantity > 0) {
                if (type === 'regular') regularTicketFound = true;
                if (type === 'vip') vipTicketFound = true;
            }
            totalTickets += quantity;
        });

        if (!regularTicketFound || !vipTicketFound) {
            isValid = false;
            errors.push('Both Regular and VIP ticket types are required');
        }

        if (totalTickets <= 0) {
            isValid = false;
            errors.push('Total ticket quantity must be greater than 0');
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
});
  </script>
</body>
</html>
