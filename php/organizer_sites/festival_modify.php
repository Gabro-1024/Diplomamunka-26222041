<?php
ini_set('display_errors', 0);
ini_set('error_reporting', E_NOTICE);
if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header('Location: ../sign-in.php');
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';

$musicGenres = [
    'Ambient', 'Bass', 'Breakbeat', 'Classical', 'Country', 'Dance', 'Deep House', 'Disco', 'Drum & Bass', 'Dubstep', 'EDM', 'Electro',
    'Folk', 'Hardcore', 'Hardstyle', 'Hip-Hop', 'House', 'Indie', 'Jazz', 'K-Pop', 'Latin', 'Metal', 'Minimal', 'Pop', 'Progressive House',
    'Psytrance', 'Punk', 'R&B', 'Rap', 'Reggae', 'Reggaeton', 'Rock', 'Soul', 'Tech House', 'Techno', 'Trance', 'Trap', 'Trip-Hop'
];
sort($musicGenres, SORT_NATURAL | SORT_FLAG_CASE);

$pdo = db_connect();

$venues = [];
try {
    $stmt = $pdo->query("SELECT id, name, city, country, capacity FROM venues ORDER BY name");
    $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store venue capacities for JavaScript validation
    $venueCapacities = [];
    foreach ($venues as $venue) {
        $venueCapacities[$venue['id']] = (int)$venue['capacity'];
    }
} catch (PDOException $e) {
    error_log("Error fetching venues: " . $e->getMessage());
    $venues = [];
}

$event = null;
$ticketTypes = [];
$eventGenres = [];
$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['form_errors'][] = "No event specified.";
    header('Location: myevents.php');
    exit();
}

$event_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND organizer_id = ?");
    $stmt->execute([$event_id, $_SESSION['user_id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $_SESSION['form_errors'][] = "Event not found or you don't have permission to edit it.";
        header('Location: myevents.php');
        exit();
    }

    $stmt = $pdo->prepare("SELECT category FROM event_categories WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $eventGenres = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT * FROM ticket_types WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $ticketTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formData = [
        'name' => $event['name'],
        'slogan' => $event['slogan'],
        'start_date' => date('Y-m-d\TH:i', strtotime($event['start_date'])),
        'end_date' => date('Y-m-d\TH:i', strtotime($event['end_date'])),
        'venue_id' => $event['venue_id'],
        'description' => $event['description'],
        'lineup' => $event['lineup'],
        'total_tickets' => $event['total_tickets'],
        'genres' => $eventGenres
    ];

    foreach ($ticketTypes as $ticket) {
        if ($ticket['ticket_type'] === 'regular') {
            $formData['regular_price'] = $ticket['price'];
            $formData['regular_quantity'] = $ticket['remaining_tickets'];
        } elseif ($ticket['ticket_type'] === 'vip') {
            $formData['vip_price'] = $ticket['price'];
            $formData['vip_quantity'] = $ticket['remaining_tickets'];
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching event data: " . $e->getMessage());
    $_SESSION['form_errors'][] = "Error loading event data. Please try again.";
    header('Location: myevents.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Log the start of form processing
        error_log("Starting form processing for event update");
        error_log("POST data: " . print_r($_POST, true));
        error_log("FILES data: " . print_r($_FILES, true));
        error_log("Session CSRF: " . ($_SESSION['csrf_token'] ?? 'not set'));
        error_log("POST CSRF: " . ($_POST['csrf_token'] ?? 'not set'));

        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || empty($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            error_log("CSRF token validation failed");
            throw new Exception("Invalid or expired form submission. Please try again.");
        }
        // Get venue capacity first
        $venue_id = (int)($_POST['venue_id'] ?? 0);
        $venue_capacity = 0;
        if ($venue_id) {
            $stmt = $pdo->prepare("SELECT capacity FROM venues WHERE id = ?");
            $stmt->execute([$venue_id]);
            $venue = $stmt->fetch(PDO::FETCH_ASSOC);
            $venue_capacity = $venue ? (int)$venue['capacity'] : 0;
        }

        // Get ticket quantities
        $regular_quantity = (int)($_POST['regular_quantity'] ?? 0);
        $vip_quantity = (int)($_POST['vip_quantity'] ?? 0);
        $total_tickets = $regular_quantity + $vip_quantity;

        // Validate ticket quantities against venue capacity
        if ($total_tickets > $venue_capacity) {
            throw new Exception("Total number of tickets ($total_tickets) exceeds the venue's capacity ($venue_capacity).");
        }

        // Proceed with other validations
        $name = trim($_POST['name'] ?? '');
        $slogan = trim($_POST['slogan'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $venue_id = (int)($_POST['venue_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $lineup = trim($_POST['lineup'] ?? '');
        $total_tickets = (int)($_POST['total_tickets'] ?? 0);
        $genres = $_POST['genres'] ?? [];
        $regular_price = (float)($_POST['regular_price'] ?? 0);
        $regular_quantity = (int)($_POST['regular_quantity'] ?? 0);
        $vip_price = (float)($_POST['vip_price'] ?? 0);
        $vip_quantity = (int)($_POST['vip_quantity'] ?? 0);

        $missing_fields = [];
        if (empty($name)) $missing_fields[] = "Event name";
        if (empty($slogan)) $missing_fields[] = "Event slogan";
        if (empty($start_date)) $missing_fields[] = "Start date";
        if (empty($end_date)) $missing_fields[] = "End date";
        if ($venue_id <= 0) $missing_fields[] = "Venue";
        if (empty($description)) $missing_fields[] = "Description";
        if (empty($lineup)) $missing_fields[] = "Lineup";
        if (empty($total_tickets)) $missing_fields[] = "Total tickets";
        // Validate prices (must be between 100 and 100,000 HUF, whole numbers only)
        if (!is_numeric($regular_price) || $regular_price < 100 || $regular_price > 100000 || $regular_price != (int)$regular_price) {
            $missing_fields[] = "Regular ticket price must be a whole number between 100 and 100,000 HUF";
        }
        if (!is_numeric($vip_price) || $vip_price < 100 || $vip_price > 100000 || $vip_price != (int)$vip_price) {
            $missing_fields[] = "VIP ticket price must be a whole number between 100 and 100,000 HUF";
        }

        // Validate quantities (must be 0 or positive)
        if (!is_numeric($regular_quantity) || $regular_quantity < 0) $missing_fields[] = "Regular ticket quantity";
        if (!is_numeric($vip_quantity) || $vip_quantity < 0) $missing_fields[] = "VIP ticket quantity";

        // Check if at least one ticket type has quantity > 0
        if ($regular_quantity <= 0 && $vip_quantity <= 0) {
            $missing_fields[] = "At least one ticket type must have a quantity greater than 0";
        }

        // Validate genres
        if (empty($genres)) $missing_fields[] = "At least one music genre";

        // Validate required fields
        if (empty($name) || empty($slogan) || empty($start_date) || empty($end_date) || empty($venue_id) || empty($description) || empty($lineup) || !isset($_POST['genres']) || empty($_POST['genres'])) {
            throw new Exception("All fields are required.");
        }

        // Validate at least one ticket type has quantity > 0
        if ($regular_quantity <= 0 && $vip_quantity <= 0) {
            throw new Exception("At least one ticket type must have a quantity greater than 0.");
        }

        // Validate ticket quantities are not negative
        if ($regular_quantity < 0 || $vip_quantity < 0) {
            throw new Exception("Ticket quantities cannot be negative.");
        }

        $dateError = '';
        if (!empty($start_date) && !empty($end_date)) {
            try {
                $start = new DateTime($start_date);
                $end = new DateTime($end_date);
                $now = new DateTime();
                $min_date = new DateTime('2025-01-01 00:00:00');
                $max_date = new DateTime('2050-12-31 23:59:59');
                $minEnd = clone $start;
                $minEnd->modify('+1 day');
                $oneMonthLater = (clone $start)->modify('+1 month');
                
                // Get the year from the dates
                $start_year = (int)$start->format('Y');
                $end_year = (int)$end->format('Y');
                
                if ($start_year < 2025 || $end_year < 2025) {
                    $dateError = "Dates must be in or after the year 2025.";
                } elseif ($start_year > 2050 || $end_year > 2050) {
                    $dateError = "Dates cannot be later than December 31, 2050.";
                } elseif ($start < $now) {
                    $dateError = "Start date must be in the future.";
                } elseif ($end < $minEnd) {
                    $dateError = "End date must be at least 1 day after start date (including time).";
                } elseif ($end > $oneMonthLater) {
                    $dateError = "Event duration cannot exceed one month.";
                }
            }
            catch (Exception $e) {
                $dateError = "Invalid date format: Please enter valid start and end dates.";
            }
        }
        if ($dateError) $missing_fields[] = $dateError;

        if (!empty($missing_fields)) {
            $_SESSION['form_errors'] = ["Please fix the following issues: " . implode(", ", $missing_fields)];
            $_SESSION['form_data'] = $_POST;
            header('Location: festival_modify.php?id=' . $event_id);
            exit();
        }

        $pdo->beginTransaction();

        $cover_image_url = $event['cover_image'];
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../assets/images/portfolio/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Validate file type
            $fileExt = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['cover_image']['tmp_name']);
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];

            if (!in_array($fileExt, $allowed_extensions) || !in_array($mime_type, $allowed_mime_types)) {
                throw new Exception("Invalid file type. Only JPG, PNG, and WebP images are allowed.");
            }

            // Validate file size (5MB max)
            $max_file_size = 5 * 1024 * 1024;
            if ($_FILES['cover_image']['size'] > $max_file_size) {
                throw new Exception("File is too large. Maximum size is 5MB.");
            }

            $image_info = getimagesize($_FILES['cover_image']['tmp_name']);
            if ($image_info === false) {
                throw new Exception("The uploaded file is not a valid image.");
            }

            $finalFileName = "portfolio-img-{$event_id}." . $fileExt;
            $finalFilePath = $upload_dir . $finalFileName;

            if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], $finalFilePath)) {
                throw new Exception("Failed to upload image.");
            }

            $cover_image_url = "http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/{$finalFileName}";
        }

        $stmt = $pdo->prepare("UPDATE events SET name = ?, slogan = ?, start_date = ?, end_date = ?, venue_id = ?, description = ?, lineup = ?, cover_image = ?, total_tickets = ? WHERE id = ?");
        $stmt->execute([$name, $slogan, $start_date, $end_date, $venue_id, $description, $lineup, $cover_image_url, $total_tickets, $event_id]);

        $pdo->prepare("DELETE FROM event_categories WHERE event_id = ?")->execute([$event_id]);
        $catStmt = $pdo->prepare("INSERT INTO event_categories (event_id, category) VALUES (?, ?)");
        foreach ($genres as $genre) {
            if (in_array($genre, $musicGenres)) {
                $catStmt->execute([$event_id, $genre]);
            }
        }

        $pdo->prepare("DELETE FROM ticket_types WHERE event_id = ?")->execute([$event_id]);
        $ticketStmt = $pdo->prepare("INSERT INTO ticket_types (event_id, ticket_type, price, remaining_tickets) VALUES (?, ?, ?, ?)");

        $ticketStmt->execute([$event_id, 'regular', (int)$regular_price, $regular_quantity]);
        $ticketStmt->execute([$event_id, 'vip', (int)$vip_price, $vip_quantity]);

        $pdo->commit();
        error_log("Event update committed successfully");
        $_SESSION['success_message'] = "Event updated successfully!";

        error_log("Redirecting to myevents.php");

        if (!headers_sent()) {
            header('Location: myevents.php');
            exit();
        } else {
            error_log("Headers already sent, cannot redirect");
            echo '<div class="alert alert-warning">Update successful, but could not redirect. <a href="myevents.php">Click here to continue</a>.</div>';
        }

    } catch (Exception $e) {
        $errorMessage = "Error updating event: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
        error_log($errorMessage);

        if (isset($pdo) && $pdo->inTransaction()) {
            try {
                $pdo->rollBack();
                error_log("Transaction rolled back");
            } catch (Exception $rollbackEx) {
                error_log("Error during rollback: " . $rollbackEx->getMessage());
            }
        }

        $_SESSION['form_errors'] = ["An error occurred: " . $e->getMessage()];
        $_SESSION['form_data'] = $_POST;

        error_log("Error occurred, redirecting back to form");

        if (!headers_sent()) {
            header('Location: festival_modify.php?id=' . $event_id);
            exit();
        } else {
            error_log("Headers already sent, cannot redirect");
            echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

include '../header.php';
?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)); ?>">
        <title>Edit Event - <?php echo htmlspecialchars($event['name']); ?></title>
        <script>
            // Simple image preview function
            function previewImage(input) {
                const preview = document.getElementById('imagePreview');
                const container = document.getElementById('imagePreviewContainer');

                if (input.files && input.files[0]) {
                    const file = input.files[0];
                    const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
                    const maxSize = 5 * 1024 * 1024; // 5MB

                    // Validate file type
                    if (!validTypes.includes(file.type)) {
                        alert('Please upload a valid image (JPEG, PNG, or WebP)');
                        input.value = '';
                        return;
                    }

                    // Validate file size
                    if (file.size > maxSize) {
                        alert('File is too large. Maximum size is 5MB.');
                        input.value = '';
                        return;
                    }

                    // Create and show preview
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        if (preview) preview.src = e.target.result;
                        if (container) container.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                }
            }
        </script>
        <script>
            window.csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        </script>
        <link rel="shortcut icon" type="image/png" href="../../assets/images/logos/favicon.svg"/>
        <link rel="stylesheet" href="../../assets/libs/bootstrap/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
        <script src="../../assets/libs/jquery/dist/jquery.min.js"></script>
        <script src="../../assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../../assets/libs/owl.carousel/dist/owl.carousel.min.js"></script>
        <script src="../../assets/libs/aos-master/dist/aos.js"></script>
        <script src="../../assets/js/custom.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
        <style>
            .required:after {
                content: " *";
                color: red;
            }

            .form-section {
                max-width: 1200px;
                margin: 0 auto;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
            }

            .card {
                border-radius: 10px;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
                margin-bottom: 2rem;
                background-color: rgba(0, 255, 255, 0.58);
                backdrop-filter: blur(10px);
                border: 2px solid rgba(255, 255, 255, 0.8) !important;
            }

            .card-header {
                background-color: rgba(248, 249, 250, 0.95);
                border-bottom: 1px solid #eaeaea;
                padding: 1.25rem 1.5rem;
                border-top-left-radius: 10px !important;
                border-top-right-radius: 10px !important;
                backdrop-filter: blur(5px);
            }

            .card-body {
                padding: 2rem;
            }

            .btn-accent-blue {
                background-color: #2210FF;
                color: white;
                border: 2px solid #2210FF;
                padding: 0.5rem 1.5rem;
                border-radius: 5px;
                transition: all 0.3s ease;
            }

            .btn-accent-blue:hover {
                background-color: #1a0dcc;
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                border-color: #1a0dcc;
            }

            .custom-image-upload {
                border: 2px dashed #dee2e6;
                border-radius: 8px;
                padding: 2rem;
                text-align: center;
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
                min-height: 200px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .btn-outline-primary {
                background-color: rgba(255, 255, 255, 0.95);
                color: #2210FF;
                border: 2px solid #2210FF;
                padding: 0.5rem 1.5rem;
                border-radius: 5px;
                transition: all 0.3s ease;
                backdrop-filter: blur(5px);
            }

            .btn-outline-primary:hover {
                background-color: #2210FF;
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }

            .btn-outline-secondary {
                background-color: rgba(255, 255, 255, 0.95);
                color: #6c757d;
                border: 2px solid #6c757d;
                padding: 0.5rem 1.5rem;
                border-radius: 5px;
                transition: all 0.3s ease;
                backdrop-filter: blur(5px);
            }

            .btn-outline-secondary:hover {
                background-color: #6c757d;
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }

            .btn-outline-danger {
                background-color: rgba(255, 255, 255, 0.95);
                color: #dc3545;
                border: 2px solid #dc3545;
                padding: 0.25rem 0.5rem;
                border-radius: 5px;
                transition: all 0.3s ease;
                backdrop-filter: blur(5px);
            }

            .btn-outline-danger:hover {
                background-color: #dc3545;
                color: white;
            }

            body {
                background-image: <?php echo !empty($event['cover_image']) ? "url('" . htmlspecialchars($event['cover_image']) . "')" : "linear-gradient(135deg, #667eea 0%, #764ba2 100%)"; ?>;
                background-size: cover;
                background-position: center;
                background-attachment: fixed;
                background-repeat: no-repeat;
                position: relative;
            }

            body::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.75);
                z-index: -1;
            }

            .preview-image {
                max-width: 200px;
                max-height: 150px;
                margin-top: 1rem;
                border-radius: 8px;
                border: 2px solid #dee2e6;
            }

            .ticket-type-card {
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 1.5rem;
                margin-bottom: 1.5rem;
                background-color: #fff;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            .ticket-type-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1rem;
                padding-bottom: 0.75rem;
                border-bottom: 1px solid #eee;
            }

            .add-ticket-type i {
                margin-right: 0.5rem;
            }

            .form-control, .form-select {
                background-color: rgba(255, 255, 255, 0.95) !important;
                border: 2px solid #e0e0e0 !important;
                border-radius: 8px !important;
                padding: 0.75rem 1rem !important;
                transition: all 0.3s ease !important;
                backdrop-filter: blur(5px) !important;
            }

            .form-control:focus, .form-select:focus {
                background-color: rgba(255, 255, 255, 1) !important;
                border-color: #2210FF !important;
                box-shadow: 0 0 0 0.2rem rgba(34, 16, 255, 0.25) !important;
                transform: translateY(-1px) !important;
            }

            .form-control:hover, .form-select:hover {
                border-color: #2210FF !important;
                background-color: rgba(255, 255, 255, 0.98) !important;
            }

            .alert {
                border: none;
                color: white !important;
                margin-bottom: 1rem;
                padding: 1rem 1.5rem;
            }

            .alert-dismissible .btn-close {
                filter: invert(1) grayscale(100%) brightness(200%);
            }

            .alert-danger {
                background-color: #dc3545 !important;
            }

            .alert-success {
                background-color: #198754 !important;
            }

            .alert-warning {
                background-color: #ffc107 !important;
                color: #000 !important;
            }

            .alert-warning .btn-close {
                filter: none !important;
            }

            .form-control.is-invalid, .was-validated .form-control:invalid,
            .form-select.is-invalid, .was-validated .form-select:invalid,
            .genre-checkbox.is-invalid, .was-validated .genre-checkbox:invalid {
                border-color: #dc3545 !important;
                padding-right: calc(1.5em + 0.75rem);
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right calc(0.375em + 0.1875rem) center;
                background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
            }

            .invalid-feedback {
                display: none;
                width: 100%;
                margin-top: 0.25rem;
                font-size: 0.875em;
                color: #dc3545;
            }

            .is-invalid ~ .invalid-feedback,
            .is-invalid ~ .invalid-tooltip,
            .was-validated :invalid ~ .invalid-feedback,
            .was-validated :invalid ~ .invalid-tooltip {
                display: block;
            }

            body {
                background-image: <?php echo !empty($event['cover_image']) ? "url('" . htmlspecialchars($event['cover_image']) . "')" : "linear-gradient(135deg, #667eea 0%, #764ba2 100%)"; ?>;
                background-size: cover;
                background-position: center;
                background-attachment: fixed;
                background-repeat: no-repeat;
                position: relative;
            }

            body::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.75);
                z-index: -1;
            }
        </style>
    </head>
    <body>


    <!-- Header -->
    <?php include '../header.php'; ?>

    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" data-aos="fade-in"
             data-aos-duration="700">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($successMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <script src="../../assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="../../assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    <!-- Page Content -->
    <div class="page-wrapper">
        <div class="container py-4">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="mb-4" data-aos="fade-down" data-aos-duration="700">
                        <h1 class="fw-bold text-primary" style="margin-top: 1em;">Modify Event</h1>
                        <p class="text-white">Update the details of your event<span class="text-danger"> (Every field is required!)</span>
                        </p>
                    </div>
                    <div class="form-section">
                        <!-- Alert Container for Form Messages -->
                        <div id="formAlerts" class="mb-4">
                            <?php if (!empty($formErrors)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo implode('<br>', $formErrors); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                                            aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['success_message'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php
                                    echo $_SESSION['success_message'];
                                    unset($_SESSION['success_message']);
                                    ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                                            aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <form id="eventForm" class="needs-validation" novalidate
                              action="festival_modify.php?id=<?php echo $event_id; ?>"
                              method="post" enctype="multipart/form-data"
                              style="border-radius: 6px; border: 1px solid #e0e0e0;">
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <!-- Basic Information -->
                            <div class="card mb-4" data-aos="zoom-in-up" data-aos-delay="100"
                                 style="border: 1px solid #e0e0e0; border-radius: 6px;">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0 text-primary">Basic Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label required">Event Name</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>"
                                                   required>
                                            <div class="invalid-feedback">Please provide an event name.</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="slogan" class="form-label required">Event Slogan</label>
                                            <input type="text" class="form-control" id="slogan" name="slogan"
                                                   value="<?php echo htmlspecialchars($formData['slogan'] ?? ''); ?>"
                                                   required>
                                            <div class="invalid-feedback">Please provide a slogan.</div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="start_date" class="form-label required">Start Date &
                                                Time</label>
                                            <input type="datetime-local" class="form-control" id="start_date"
                                                   name="start_date"
                                                   value="<?php echo htmlspecialchars($formData['start_date'] ?? ''); ?>"
                                                   min="<?php echo date('Y-m-d\TH:i'); ?>"
                                                   max="2051-12-30T23:59"
                                                   required>
                                            <div class="invalid-feedback">Please provide a valid start date and time.
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="end_date" class="form-label required">End Date & Time</label>
                                            <input type="datetime-local" class="form-control" id="end_date"
                                                   name="end_date"
                                                   value="<?php echo htmlspecialchars($formData['end_date'] ?? ''); ?>"
                                                   min="<?php echo date('Y-m-d\TH:i', strtotime('+1 day')); ?>"
                                                   max="2051-12-31T23:59"
                                                   required>
                                            <div class="invalid-feedback">End date must be at least 1 day after start
                                                date and no later than December 31, 2051. Maximum event duration is 1
                                                month.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="venue_id" class="form-label required">Venue</label>
                                        <select class="form-select" id="venue_id" name="venue_id"
                                                onchange="updateVenueCapacity()" required>
                                            <option value=""
                                                    disabled <?php echo !isset($formData['venue_id']) ? 'selected' : ''; ?>>
                                                Select a venue
                                            </option>
                                            <?php foreach ($venues as $venue) { ?>
                                                <option value="<?php echo $venue['id']; ?>" <?php echo (isset($formData['venue_id']) && $formData['venue_id'] == $venue['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($venue['name']); ?>
                                                    (<?php echo $venue['city']; ?>, <?php echo $venue['country']; ?>)
                                                </option>
                                            <?php } ?>
                                        </select>
                                        <div class="invalid-feedback">Please select a venue.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="description" class="form-label required">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="4"
                                                  required><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                                        <div class="invalid-feedback">Please provide a description.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="lineup" class="form-label required">Lineup (comma-separated)</label>
                                        <textarea class="form-control" id="lineup" name="lineup" rows="2"
                                                  required><?php echo htmlspecialchars($formData['lineup'] ?? ''); ?></textarea>
                                        <div class="form-text">Separate artist names with commas</div>
                                        <div class="invalid-feedback">Please provide a lineup.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label required">Music Genres</label>
                                        <div>
                                            <?php foreach ($musicGenres as $genre): ?>
                                                <label class="form-check-label genre-checkbox">
                                                    <input class="form-check-input" type="checkbox" name="genres[]"
                                                           value="<?php echo $genre; ?>" <?php if (!empty($formData['genres']) && in_array($genre, $formData['genres'])) echo 'checked'; ?>
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
                                        <label for="cover_image" class="form-label">Upload New Cover Image
                                            (optional)</label>
                                        <div class="position-relative">
                                            <div class="custom-image-upload mb-2 border-2 border-dashed rounded-3 p-4 text-center"
                                                 id="customImageUpload"
                                                 tabindex="0"
                                                 role="button"
                                                 aria-label="Upload cover image"
                                                 style="background-color: #f8f9fa; min-height: 150px; cursor: pointer; position: relative;">
                                                <input class="form-control position-absolute top-0 start-0 w-100 h-100 opacity-0"
                                                       type="file"
                                                       id="cover_image"
                                                       name="cover_image"
                                                       accept="image/jpeg, image/png, image/webp"
                                                       onchange="previewImage(this)">
                                                <div class="upload-area d-flex flex-column align-items-center justify-content-center h-100">
                                                    <iconify-icon icon="mdi:image-plus"
                                                                  style="font-size: 2.5rem; color: #6c757d;"></iconify-icon>
                                                    <span class="mt-2 text-secondary">Click to upload cover image</span>
                                                    <small class="text-muted">Max 5MB • JPG, PNG, WebP</small>
                                                </div>
                                            </div>
                                            <div id="fileSelectedIndicator"
                                                 class="position-absolute top-0 end-0 m-2 p-2 bg-success text-white rounded"
                                                 style="display: none;">
                                                <i class="bi bi-check-circle-fill me-1"></i> File selected
                                            </div>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback">Please upload a valid image (JPEG, PNG, or WebP) under
                                        5MB.
                                        <div class="form-text">Recommended size: 1200x630px, max 5MB. Leave empty to
                                            keep
                                            current image.
                                        </div>
                                    </div>
                                    <div id="imagePreviewContainer" class="mt-3"
                                         style="<?php echo !empty($event['cover_image']) ? '' : 'display: none;' ?>">
                                        <label class="form-label"><?php echo !empty($event['cover_image']) ? 'Current' : 'Selected'; ?>
                                            Image:</label><br>
                                        <img src="<?php echo !empty($event['cover_image']) ? htmlspecialchars($event['cover_image']) : ''; ?>"
                                             alt="Image preview"
                                             id="imagePreview"
                                             style="max-width: 300px; max-height: 200px; border-radius: 8px; border: 2px solid #dee2e6; object-fit: contain;"/>
                                        <div class="mt-2">
                                        </div>
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
                                        <div class="ticket-type mb-3 p-3 border rounded" style="background: #eaf1ff;">
                                            <div class="row g-3 align-items-center">
                                                <div class="col-md-4 d-flex align-items-center">
                                                <span class="badge"
                                                      style="background: #1a73e8; color: #fff; font-size: 1rem; padding: 8px 18px;">Regular</span>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Price (HUF)<input type="number"
                                                                                                class="form-control"
                                                                                                name="regular_price"
                                                                                                min="100"
                                                                                                step="100"
                                                                                                value="<?php echo htmlspecialchars($formData['regular_price'] ?? '1000'); ?>"
                                                                                                required><span
                                                                class="form-text text-muted">Minimum 100 HUF</span>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Quantity<input type="number"
                                                                                             class="form-control"
                                                                                             name="regular_quantity"
                                                                                             min="0"
                                                                                             value="<?php echo htmlspecialchars($formData['regular_quantity'] ?? '0'); ?>"
                                                                                             required><span
                                                                class="form-text text-muted">Enter 0 if not available</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ticket-type mb-3 p-3 border rounded"
                                             style="background: linear-gradient(135deg, #FFD700 0%, #F0C000 100%);">
                                            <div class="row g-3 align-items-center">
                                                <div class="col-md-4 d-flex align-items-center">
                                                <span class="badge"
                                                      style="background: #000; color: #FFD700; font-size: 1rem; padding: 8px 18px;">VIP</span>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Price (HUF)<input type="number"
                                                                                                class="form-control"
                                                                                                name="vip_price"
                                                                                                min="100"
                                                                                                step="100"
                                                                                                value="<?php echo htmlspecialchars($formData['vip_price'] ?? '2000'); ?>"
                                                                                                required><span
                                                                class="form-text text-muted">Minimum 100 HUF</span>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Quantity<input type="number"
                                                                                             class="form-control"
                                                                                             name="vip_quantity" min="0"
                                                                                             value="<?php echo htmlspecialchars($formData['vip_quantity'] ?? '100'); ?>"
                                                                                             required><span
                                                                class="form-text text-muted">Enter 0 if not available</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-text text-muted">At least one ticket type must have a quantity
                                        greater than 0. Total tickets cannot exceed the venue's capacity.
                                    </div>
                                    <div id="venueCapacityMessage" class="text-danger d-none">Total tickets exceed venue
                                        capacity. Please reduce the number of tickets.
                                    </div>
                                    <input type="hidden" id="venueCapacities"
                                           value='<?php echo json_encode($venueCapacities); ?>'>
                                    <input type="hidden" id="currentVenueId"
                                           value="<?php echo $formData['venue_id'] ?? ''; ?>">
                                </div>
                            </div>

                            <input type="hidden" id="total_tickets" name="total_tickets"
                                   value="<?php echo htmlspecialchars($formData['total_tickets'] ?? ''); ?>" required>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end" data-aos="fade-up"
                                 data-aos-delay="500" style="margin: 1em 0;">
                                <a href="myevents.php" class="btn btn-outline-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <iconify-icon icon="lucide:save" class="me-1"></iconify-icon>
                                    Update Event
                                </button>
                            </div>
                        </form>
                    </div>
                    <!-- Debug Info -->
                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                        <div class="container mt-4">
                            <div class="card">
                                <div class="card-header bg-warning">
                                    <h5 class="mb-0">Debug Information</h5>
                                </div>
                                <div class="card-body">
                                    <h6>POST Data:</h6>
                                    <pre><?php print_r($_POST); ?></pre>

                                    <h6>FILES Data:</h6>
                                    <pre><?php print_r($_FILES); ?></pre>

                                    <h6>Session Data:</h6>
                                    <pre><?php print_r($_SESSION); ?></pre>

                                    <h6>SQL Queries:</h6>
                                    <pre>
            <?php
            if (isset($pdo) && $pdo->inTransaction()) {
                echo "Transaction in progress\n";
            } else {
                echo "No active transaction\n";
            }

            // Show the last query executed if available
            if (isset($stmt)) {
                echo "Last query: " . $stmt->queryString . "\n";
                echo "Parameters: " . print_r($stmt->debugDumpParams(), true) . "\n";
            }
            ?>
            </pre>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
    </html>

    <script>
        // Venue capacity validation
        function updateVenueCapacity() {
            const venueId = document.getElementById('venue_id').value;
            const venueCapacities = JSON.parse(document.getElementById('venueCapacities').value);
            const capacity = venueCapacities[venueId] || 0;

            // Update the current venue ID
            document.getElementById('currentVenueId').value = venueId;

            // Update the max attributes for quantity inputs
            const regularQty = document.querySelector('input[name="regular_quantity"]');
            const vipQty = document.querySelector('input[name="vip_quantity"]');

            if (regularQty && vipQty) {
                regularQty.max = capacity - (parseInt(vipQty.value) || 0);
                vipQty.max = capacity - (parseInt(regularQty.value) || 0);

                // Trigger validation
                validateTicketQuantities();
            }
        }

        function validateTicketQuantities() {
            const venueId = document.getElementById('currentVenueId').value;
            if (!venueId) return true; // No venue selected yet

            const venueCapacities = JSON.parse(document.getElementById('venueCapacities').value);
            const capacity = venueCapacities[venueId] || 0;

            const regularQty = parseInt(document.querySelector('input[name="regular_quantity"]').value) || 0;
            const vipQty = parseInt(document.querySelector('input[name="vip_quantity"]').value) || 0;
            const totalTickets = regularQty + vipQty;

            const capacityMessage = document.getElementById('venueCapacityMessage');
            const submitButton = document.querySelector('button[type="submit"]');

            // Check for negative values
            if (regularQty < 0 || vipQty < 0) {
                capacityMessage.textContent = 'Ticket quantities cannot be negative';
                capacityMessage.classList.remove('d-none');
                submitButton.disabled = true;
                return false;
            }

            // Check if total exceeds venue capacity
            if (totalTickets > capacity) {
                capacityMessage.textContent = 'Total tickets exceed venue capacity. Please reduce the number of tickets.';
                capacityMessage.classList.remove('d-none');
                submitButton.disabled = true;
                return false;
            } else {
                capacityMessage.classList.add('d-none');
                submitButton.disabled = false;
                return true;
            }
        }

        // Add event listeners for quantity changes
        document.addEventListener('input', function (e) {
            if (e.target.matches('input[name="regular_quantity"], input[name="vip_quantity"]')) {
                validateTicketQuantities();

                // Update the other quantity's max value
                const venueId = document.getElementById('currentVenueId').value;
                if (venueId) {
                    const venueCapacities = JSON.parse(document.getElementById('venueCapacities').value);
                    const capacity = venueCapacities[venueId] || 0;
                    const regularQty = parseInt(document.querySelector('input[name="regular_quantity"]').value) || 0;
                    const vipQty = parseInt(document.querySelector('input[name="vip_quantity"]').value) || 0;

                    if (e.target.name === 'regular_quantity') {
                        document.querySelector('input[name="vip_quantity"]').max = Math.max(0, capacity - regularQty);
                    } else {
                        document.querySelector('input[name="regular_quantity"]').max = Math.max(0, capacity - vipQty);
                    }
                }
            }
        });

        // Utility function to show validation errors
        function showValidationError(input, message) {
            const formControl = input.closest('.form-control, .form-select');
            const feedback = formControl.nextElementSibling;

            formControl.classList.add('is-invalid');
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.textContent = message;
                feedback.style.display = 'block';
            }
        }

        // Utility function to clear validation errors
        function clearValidation(input) {
            const formControl = input.closest('.form-control, .form-select');
            const feedback = formControl.nextElementSibling;

            formControl.classList.remove('is-invalid');
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.style.display = 'none';
            }
        }

        // Form validation and submission
        const form = document.getElementById('eventForm');
        if (form) {
            // Helper function to show validation errors
            function showError(field, message) {
                const formGroup = field.closest('.form-group') || field.closest('.form-check') || field.closest('.mb-3');
                if (!formGroup) return null;

                field.classList.add('is-invalid');

                // Remove existing error message
                const existingError = formGroup.querySelector('.invalid-feedback');
                if (existingError) {
                    existingError.remove();
                }

                // Create and append new error message
                const errorElement = document.createElement('div');
                errorElement.className = 'invalid-feedback d-block';
                errorElement.textContent = message;
                formGroup.appendChild(errorElement);

                return field;
            }

            // Helper function to show alert messages
            function showAlert(type, message) {
                const alertContainer = document.getElementById('formAlerts') || createAlertContainer();
                if (!alertContainer) return;

                const alert = document.createElement('div');
                alert.className = `alert alert-${type} alert-dismissible fade show`;
                alert.role = 'alert';
                alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

                alertContainer.appendChild(alert);

                // Scroll to alert
                setTimeout(() => {
                    alert.scrollIntoView({behavior: 'smooth', block: 'center'});
                }, 100);

                return alert;
            }

            // Create alert container if it doesn't exist
            function createAlertContainer() {
                const container = document.createElement('div');
                container.id = 'formAlerts';
                form.parentNode.insertBefore(container, form);
                return container;
            }

            // Validate end time against start time (no validation, just UI feedback)
            function validateEndTime(input) {
                // Always remove any validation classes
                input.classList.remove('is-invalid');

                // This is just for UI feedback, we're not blocking submission
                const startTime = input.getAttribute('data-start-time');
                const endTime = input.value;

                // Show a visual indicator if end time is before start time
                if (endTime && startTime && endTime <= startTime) {
                    input.classList.add('bg-light');
                } else {
                    input.classList.remove('bg-light');
                }
            }

            // Handle form submission
            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...';

                // Clear previous messages and validation errors
                const alertContainer = document.getElementById('formAlerts');
                if (alertContainer) {
                    alertContainer.innerHTML = '';
                }

                // Clear previous validation states
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());

                // Helper function to show validation errors
                function showError(field, message) {
                    const formGroup = field.closest('.form-group') || field.closest('.form-check') || field.closest('.mb-3');
                    if (!formGroup) return;

                    field.classList.add('is-invalid');

                    let errorElement = formGroup.querySelector('.invalid-feedback');
                    if (!errorElement) {
                        errorElement = document.createElement('div');
                        errorElement.className = 'invalid-feedback d-block';
                        formGroup.appendChild(errorElement);
                    }
                    errorElement.textContent = message;

                    return field;
                }

                try {
                    // Basic client-side validation
                    let hasErrors = false;
                    let firstErrorField = null;

                    // Check required fields
                    const requiredFields = form.querySelectorAll('[required]');
                    requiredFields.forEach(field => {
                        // Skip file inputs for required check (handled in backend)
                        if (field.type === 'file') return;

                        // For checkboxes (like genres)
                        if (field.type === 'checkbox') {
                            const checkboxes = form.querySelectorAll(`input[name="${field.name}"]:checked`);
                            if (checkboxes.length === 0) {
                                const errorField = showError(field, 'This field is required');
                                if (!firstErrorField) firstErrorField = errorField;
                                hasErrors = true;
                            }
                            return;
                        }

                        // For other input types
                        if (!field.value.trim()) {
                            const errorField = showError(field, 'This field is required');
                            if (!firstErrorField) firstErrorField = errorField;
                            hasErrors = true;
                        }
                    });

                    // If client-side validation fails, stop here
                    if (hasErrors) {
                        if (firstErrorField) {
                            firstErrorField.scrollIntoView({behavior: 'smooth', block: 'center'});
                            firstErrorField.focus();
                        }
                        throw new Error('Please fill in all required fields.');
                    }

                    // Prepare form data
                    const formData = new FormData(form);

                    // Add CSRF token to form data
                    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
                    if (csrfToken) {
                        formData.append('csrf_token', csrfToken);
                    }

                    // Show loading state
                    const loadingAlert = showAlert('info', 'Updating event, please wait...');

                    // Send the request to the API
                    const response = await fetch('api/update_event.php', {
                        method: 'POST',
                        body: formData
                    });

                    // Remove loading alert
                    if (loadingAlert) loadingAlert.remove();

                    const result = await response.json();

                    if (response.ok && result.success) {
                        // Show success message
                        showAlert('success', result.message || 'Event updated successfully!');

                        // Redirect after a short delay
                        if (result.redirect) {
                            setTimeout(() => {
                                window.location.href = result.redirect;
                            }, 1500);
                        }
                    } else {
                        // Handle validation errors
                        if (result.errors) {
                            let firstErrorField = null;

                            // Show field-specific errors
                            for (const [field, message] of Object.entries(result.errors)) {
                                const input = form.querySelector(`[name="${field}"]`);
                                if (input) {
                                    const errorField = showError(input, message);
                                    if (!firstErrorField) firstErrorField = errorField;
                                } else {
                                    // For non-field specific errors (like ticket_quantities)
                                    showAlert('danger', message);
                                }
                            }

                        }
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showAlert('danger', error.message || 'An unexpected error occurred. Please try again.');
                } finally {
                    // Reset button state
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    if (fileError) fileError.style.display = 'block';
                    field.classList.add('is-invalid');
                    hasErrors = true;
                    if (!firstInvalidField) firstInvalidField = field;
                }
            }

            // For checkboxes (like genres)
            if (field.type === 'checkbox') {
                const checkboxes = document.querySelectorAll(`input[name="${field.name}"]:checked`);
                if (checkboxes.length === 0) {
                    const fieldLabel = field.closest('.form-check')?.querySelector('.form-check-label')?.textContent?.trim() || 'This field';
                    showValidationError(field, `${fieldLabel} is required`);
                    hasErrors = true;
                    if (!firstInvalidField) firstInvalidField = field;
                }
            }

            // For other input types
            if (!field.value.trim()) {
                const fieldLabel = field.labels?.[0]?.textContent?.replace('*', '').trim() || 'This field';
                showValidationError(field, `${fieldLabel} is required`);
                hasErrors = true;
                if (!firstInvalidField) firstInvalidField = field;
            }
        }
        )

        // Function to show alert messages
        function showAlert(type, message) {
            if (!alertContainer) return;

            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.role = 'alert';
            alert.innerHTML = `
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;

            alertContainer.appendChild(alert);

            // Scroll to alert
            setTimeout(() => {
                alert.scrollIntoView({behavior: 'smooth', block: 'center'});
            }, 100);
        }

        // Handle checkbox groups (like genres)
        const checkboxGroups = form.querySelectorAll('input[type="checkbox"][name^="genres"]');
        if (checkboxGroups.length > 0) {
            const atLeastOneChecked = Array.from(checkboxGroups).some(cb => cb.checked);
            if (!atLeastOneChecked) {
                hasErrors = true;
                checkboxGroups.forEach(cb => {
                    showValidationError(cb, 'Please select at least one genre');
                    if (!firstInvalidField) firstInvalidField = cb;
                });
            }
        }

        // 2. Validate dates
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(endDateInput.value);
        
        // Check year range (2025-2050)
        const startYear = startDate.getFullYear();
        const endYear = endDate.getFullYear();
        
        if (startYear < 2025 || endYear < 2025) {
            showValidationError(startDateInput, 'Dates must be in or after the year 2025');
            hasErrors = true;
            firstErrorField = firstErrorField || startDateInput;
        }
        
        if (startYear > 2050 || endYear > 2050) {
            showValidationError(endDateInput, 'Dates cannot be later than December 31, 2050');
            hasErrors = true;
            firstErrorField = firstErrorField || endDateInput;
        }
        const now = new Date();
        const maxEndDate = new Date('2051-12-31T23:59:59');

        // Reset any previous date errors
        [startDateInput, endDateInput].forEach(input => {
            input.setCustomValidity('');
            input.classList.remove('is-invalid');
        });

        // Check if dates are valid
        if (isNaN(startDate.getTime())) {
            showValidationError(startDateInput, 'Please provide a valid start date and time');
            hasErrors = true;
            if (!firstInvalidField) firstInvalidField = startDateInput;
        } else if (startDate < now) {
            showValidationError(startDateInput, 'Start date must be in the future');
            hasErrors = true;
            if (!firstInvalidField) firstInvalidField = startDateInput;
        }

        if (isNaN(endDate.getTime())) {
            showValidationError(endDateInput, 'Please provide a valid end date and time');
            hasErrors = true;
            if (!firstInvalidField) firstInvalidField = endDateInput;
        } else {
            if (endDate <= startDate) {
                showValidationError(endDateInput, 'End date must be after start date');
                hasErrors = true;
                if (!firstInvalidField) firstInvalidField = endDateInput;
            } else if (endDate > maxEndDate) {
                showValidationError(endDateInput, 'End date cannot be later than December 31, 2051');
                hasErrors = true;
                if (!firstInvalidField) firstInvalidField = endDateInput;
            }

            // Check event duration (max 1 month)
            const oneMonthLater = new Date(startDate);
            oneMonthLater.setMonth(oneMonthLater.getMonth() + 1);

            if (endDate > oneMonthLater) {
                showValidationError(endDateInput, 'Maximum event duration is 1 month');
                hasErrors = true;
                if (!firstInvalidField) firstInvalidField = endDateInput;
            }
        }

        if (hasErrors) {
            if (firstInvalidField) {
                firstInvalidField.scrollIntoView({behavior: 'smooth', block: 'center'});
                firstInvalidField.focus();
            }
            throw new Error('Please correct the date and time values.');
        }

        // 3. Validate ticket quantities
        const regularQtyInput = document.querySelector('input[name="regular_quantity"]');
        const vipQtyInput = document.querySelector('input[name="vip_quantity"]');
        const regularQty = parseInt(regularQtyInput?.value) || 0;
        const vipQty = parseInt(vipQtyInput?.value) || 0;

        // Reset previous errors
        [regularQtyInput, vipQtyInput].forEach(input => {
            if (input) {
                input.classList.remove('is-invalid');
                const feedback = input.closest('.form-group')?.querySelector('.invalid-feedback');
                if (feedback) feedback.style.display = 'none';
                if (!firstInvalidField) firstInvalidField = input;
            }
        });

        // Check if ticket quantities are valid numbers
        if (isNaN(regularQty) || isNaN(vipQty)) {
            const ticketQtyInputs = [regularQtyInput, vipQtyInput].filter(Boolean);
            ticketQtyInputs.forEach(input => {
                if (input) {
                    showValidationError(input, 'Please enter a valid number');
                    if (!firstInvalidField) firstInvalidField = input;
                }
            });
            hasErrors = true;
        }

        // Validate ticket quantities and prices
        const regularPriceInput = document.querySelector('input[name="regular_price"]');
        const vipPriceInput = document.querySelector('input[name="vip_price"]');
        const regularPrice = parseInt(regularPriceInput.value) || 0;
        const vipPrice = parseInt(vipPriceInput.value) || 0;

        // Validate quantities
        if (regularQty < 0) {
            showValidationError(regularQtyInput, 'Quantity cannot be negative');
            if (!firstInvalidField) firstInvalidField = regularQtyInput;
            hasErrors = true;
        }
        if (vipQty < 0) {
            showValidationError(vipQtyInput, 'Quantity cannot be negative');
            if (!firstInvalidField) firstInvalidField = vipQtyInput;
            hasErrors = true;
        }

        // Validate prices (100 - 100,000 HUF, whole numbers only)
        if (isNaN(regularPrice) || regularPrice < 100 || regularPrice > 100000) {
            showValidationError(regularPriceInput, 'Price must be between 100 and 100,000 HUF');
            if (!firstInvalidField) firstInvalidField = regularPriceInput;
            hasErrors = true;
        }
        if (isNaN(vipPrice) || vipPrice < 100 || vipPrice > 100000) {
            showValidationError(vipPriceInput, 'Price must be between 100 and 100,000 HUF');
            if (!firstInvalidField) firstInvalidField = vipPriceInput;
            hasErrors = true;
        }

        // Prevent decimal input
        [regularPriceInput, vipPriceInput].forEach(input => {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        });

        // Check venue capacity
        if (!validateTicketQuantities()) {
            hasErrors = true;
            // validateTicketQuantities should handle showing the error message
        }

        if (hasErrors) {
            if (firstInvalidField) {
                firstInvalidField.scrollIntoView({behavior: 'smooth', block: 'center'});
                let alertContainer = document.getElementById('formAlerts');
                if (!alertContainer) {
                    alertContainer = document.createElement('div');
                    alertContainer.id = 'formAlerts';
                    const form = document.querySelector('form');
                    form?.parentNode.insertBefore(alertContainer, form);
                }

                // Clear previous alerts if any
                alertContainer.innerHTML = '';

                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show text-white`;
                alertDiv.role = 'alert';
                alertDiv.style.color = 'white';

                // Set background color based on alert type
                if (type === 'danger') {
                    alertDiv.style.backgroundColor = '#dc3545';
                } else if (type === 'success') {
                    alertDiv.style.backgroundColor = '#198754';
                } else if (type === 'warning') {
                    alertDiv.style.backgroundColor = '#ffc107';
                } else {
                    alertDiv.style.backgroundColor = '#0d6efd';
                }

                // Add close button with white color
                alertDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close" style="opacity: 1;"></button>
            </div>
        `;

                alertContainer.appendChild(alertDiv);

                // Scroll to the alert
                alertDiv.scrollIntoView({behavior: 'smooth', block: 'nearest'});
            }

            // Debug file upload
            document.addEventListener('DOMContentLoaded', function () {
                console.log('DOM fully loaded');

                const imageUpload = document.getElementById('cover_image');

                if (!imageUpload) {
                    console.error('File input not found!');
                    return;
                }

                console.log('File input found:', imageUpload);

                // Simple debug - log when file is selected
                imageUpload.addEventListener('change', function (e) {
                    console.log('File input changed!');
                    console.log('Files:', e.target.files);

                    if (e.target.files && e.target.files[0]) {
                        const file = e.target.files[0];
                        console.log('File selected:', file.name, 'Type:', file.type, 'Size:', file.size);

                        // Simple validation
                        const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
                        const maxSize = 5 * 1024 * 1024; // 5MB

                        if (!validTypes.includes(file.type)) {
                            console.error('Invalid file type:', file.type);
                            alert('Please upload a valid image (JPEG, PNG, or WebP)');
                            this.value = '';
                            return;
                        }

                        if (file.size > maxSize) {
                            console.error('File too large:', file.size);
                            alert('File is too large. Maximum size is 5MB.');
                            this.value = '';
                            return;
                        }

                        console.log('File is valid!');
                        alert('File selected successfully: ' + file.name);
                    } else {
                        console.log('No file selected or file selection cancelled');
                    }
                });

                // Add click handler to the upload area for better debugging
                const uploadArea = document.querySelector('.custom-image-upload');
                if (uploadArea) {
                    uploadArea.addEventListener('click', function () {
                        console.log('Upload area clicked!');
                    });
                }

                if (imageUpload) {
                    // Show preview when a file is selected
                    imageUpload.addEventListener('change', function (e) {
                        const file = e.target.files[0];
                        if (file) {
                            // Simple validation
                            const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
                            const maxSize = 5 * 1024 * 1024; // 5MB

                            if (!validTypes.includes(file.type)) {
                                alert('Please upload a valid image (JPEG, PNG, or WebP)');
                                this.value = '';
                                return;
                            }

                            if (file.size > maxSize) {
                                alert('File is too large. Maximum size is 5MB.');
                                this.value = '';
                                return;
                            }

                            // Create and show preview
                            const reader = new FileReader();
                            reader.onload = function (e) {
                                if (imagePreview) {
                                    imagePreview.src = e.target.result;
                                    imagePreviewContainer.style.display = 'block';
                                }
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                if (imagePreview && imagePreviewContainer) {
                    imagePreview.src = e.target.result;
                    imagePreviewContainer.style.display = 'block';
                    uploadArea.style.display = 'none';

                    // Remove any error messages if validation passes
                    const existingError = customUpload.parentElement.querySelector('.alert-danger');
                    if (existingError) {
                        existingError.remove();
                    }
                }
            };
            reader.onerror = function () {
                showError('Error reading the file. Please try again.');
            };
            reader.readAsDataURL(file);
        }
        )
        ;

        // Handle drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            customUpload.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            customUpload.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            customUpload.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            customUpload.style.borderColor = '#2210FF';
            customUpload.style.backgroundColor = 'rgba(34, 16, 255, 0.05)';
        }

        function unhighlight() {
            customUpload.style.borderColor = '#dee2e6';
            customUpload.style.backgroundColor = '#f8f9fa';
        }

        // Handle dropped files
        customUpload.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const file = dt.files[0];
            if (file && file.type.match('image.*')) {
                imageUpload.files = dt.files;
                const event = new Event('change');
                imageUpload.dispatchEvent(event);
            }
        }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form.needs-validation');
            if (form) {
                form.addEventListener('submit', function (e) {

                    if (!form.checkValidity() || !validateTicketQuantities()) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            }

            // Image upload handling
            const fileInput = document.getElementById('cover_image');
            const customUpload = document.getElementById('customImageUpload');
            const preview = document.getElementById('imagePreview');
            const uploadArea = customUpload ? customUpload.querySelector('.upload-area') : null;

            if (!fileInput || !customUpload) return;

            // Handle click on the custom upload area
            customUpload.addEventListener('click', function (e) {
                if (e.target !== fileInput) {
                    fileInput.click();
                }
            });

            // Handle file selection
            fileInput.addEventListener('change', function (e) {
                const file = e.target.files[0];

                if (file) {
                    // Check file type
                    const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
                    if (!validTypes.includes(file.type)) {
                        alert('Please select a valid image file (JPEG, PNG, or WebP)');
                        return;
                    }

                    // Check file size (5MB max)
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    if (file.size > maxSize) {
                        alert('Image size should be less than 5MB');
                        return;
                    }

                    // Create preview
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        if (preview) {
                            preview.src = e.target.result;
                            preview.style.display = 'block';
                            if (uploadArea) {
                                uploadArea.style.display = 'none';
                            }
                        }
                    }
                    reader.onerror = function () {
                        alert('Error reading the file. Please try again.');
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Drag and drop handlers
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            function highlight() {
                customUpload.classList.add('highlight');
            }

            function unhighlight() {
                customUpload.classList.remove('highlight');
            }

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;

                if (files.length) {
                    fileInput.files = files;
                    const event = new Event('change');
                    fileInput.dispatchEvent(event);
                }
            }

            // Add event listeners for drag and drop
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                customUpload.addEventListener(eventName, preventDefaults, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                customUpload.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                customUpload.addEventListener(eventName, unhighlight, false);
            });

            customUpload.addEventListener('drop', handleDrop, false);
        });

        // Ensure AOS is initialized after DOM and CSS are loaded
        setTimeout(function () {
            if (window.AOS) {
                AOS.init({
                    once: true,
                    duration: 700
                });
            }
        }, 100);

        // Debug: Log any errors that occur
        window.addEventListener('error', function (e) {
            debugLog('Global error:', {
                message: e.message,
                filename: e.filename,
                lineno: e.lineno,
                colno: e.colno,
                error: e.error
            });
        });
    </script>

<?php include '../footer.php'; ?>