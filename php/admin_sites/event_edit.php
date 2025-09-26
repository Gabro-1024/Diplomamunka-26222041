<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Initialize database connection
try{
    $conn = db_connect();
}
catch (Exception $e){
    die('Database connection error: ' . $e->getMessage());
}

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    die('Access Denied');
}

$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$event = null;
$ticketTypes = [];
$eventCategories = [];
$allCategories = [];
$venues = [];
$organizers = [];

// Fetch all venues with capacity
$venues = $conn->query("SELECT id, name, city, capacity FROM venues ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Organizer is set to the current user

// Fetch all available categories from existing events
$allCategories = $conn->query("SELECT DISTINCT category FROM event_categories ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$allCategoriesJson = json_encode($allCategories);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $required = ['name', 'start_date', 'end_date', 'organizer_id', 'venue_id', 'description', 'total_tickets'];
    $errors = [];

    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }

    // Date validation
    $dateError = '';
    if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
        try {
            $start = new DateTime($_POST['start_date']);
            $end = new DateTime($_POST['end_date']);
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
        } catch (Exception $e) {
            $dateError = "Invalid date format: Please enter valid start and end dates.";
        }
        
        if ($dateError) {
            $errors[] = $dateError;
        }
    }

    // Validate ticket prices
    if (isset($_POST['ticket_types']) && is_array($_POST['ticket_types'])) {
        foreach ($_POST['ticket_types'] as $type => $ticket) {
            $price = $ticket['price'] ?? 0;
            
            // Check if price is a valid integer
            if (!is_numeric($price) || $price != (int)$price) {
                $errors[] = ucfirst($type) . ' ticket price must be a whole number';
                continue;
            }
            
            $price = (int)$price;
            
            // Check price range and divisibility by 10
            if ($price < 100) {
                $errors[] = ucfirst($type) . ' ticket price must be at least 100 HUF';
            } elseif ($price > 100000) {
                $errors[] = ucfirst($type) . ' ticket price cannot exceed 100,000 HUF';
            } elseif ($price % 10 !== 0) {
                $errors[] = ucfirst($type) . ' ticket price must be divisible by 10';
            }
            
            // Validate quantity is a positive integer
            $quantity = $ticket['quantity'] ?? 0;
            if (!is_numeric($quantity) || $quantity < 0 || $quantity != (int)$quantity) {
                $errors[] = ucfirst($type) . ' ticket quantity must be a non-negative whole number';
            }
        }
    }

    // Calculate total tickets from all ticket types
    $totalTickets = 0;
    if (isset($_POST['ticket_types']) && is_array($_POST['ticket_types'])) {
        foreach ($_POST['ticket_types'] as $ticket) {
            $quantity = isset($ticket['quantity']) ? (int)$ticket['quantity'] : 0;
            $totalTickets += $quantity;
        }
    }
    
    // Get venue capacity
    $venueStmt = $conn->prepare("SELECT capacity FROM venues WHERE id = ?");
    $venueStmt->execute([(int)$_POST['venue_id']]);
    $venueCapacity = (int)$venueStmt->fetchColumn();
    
    // Validate total tickets against venue capacity
    if ($totalTickets > $venueCapacity) {
        $errors[] = "Total tickets ({$totalTickets}) cannot exceed venue capacity ({$venueCapacity})";
    }
    
    // Don't process the form if there are validation errors
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
    }
    // Log all form data and files for debugging
    $_SESSION['debug'] = [
        'post_data' => $_POST,
        'files_data' => $_FILES,
        'validation_errors' => $errors,
        'file_validation' => []
    ];

    if (empty($errors)) {
        $conn->beginTransaction();

        try {
            // Venue capacity is already loaded above
            
            // Prepare event data
            $eventData = [
                'name' => $_POST['name'],
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'organizer_id' => (int)$_SESSION['user_id'],
                'venue_id' => (int)$_POST['venue_id'],
                'description' => $_POST['description'],
                'total_tickets' => $venueCapacity, // Use venue capacity
                'slogan' => $_POST['slogan'] ?? null,
                'lineup' => $_POST['lineup'] ?? 'No lineup announced yet :(',
            ];

            // Handle file upload
            if (!empty($_FILES['cover_image']['name'])) {
                $_SESSION['debug']['file_validation']['start'] = 'Starting file validation';
                $_SESSION['debug']['file_validation']['file_info'] = $_FILES['cover_image'];
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/Diplomamunka-26222041/assets/images/portfolio/';
                $uploadOk = true;
                $errorMsg = '';
                
                // Log initial values
                $_SESSION['debug']['file_validation']['upload_dir'] = $uploadDir;
                $_SESSION['debug']['file_validation']['file_size'] = $_FILES['cover_image']['size'];
                $_SESSION['debug']['file_validation']['file_name'] = $_FILES['cover_image']['name'];
                $_SESSION['debug']['file_validation']['file_tmp_name'] = $_FILES['cover_image']['tmp_name'];
                
                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    if (!mkdir($uploadDir, 0777, true)) {
                        $errors[] = 'Error: Failed to create upload directory.';
                        $uploadOk = false;
                    }
                }
                
                if ($uploadOk) {
                    $fileName = basename($_FILES['cover_image']['name']);
                    $fileTmpName = $_FILES['cover_image']['tmp_name'];
                    $fileSize = $_FILES['cover_image']['size'];
                    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    
                    // Allowed file types
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    // Check file extension
                    if (!in_array($fileExt, $allowed)) {
                        $errorMsg = 'Only JPG, PNG, and WebP files are allowed. Found: ' . $fileExt;
                        $uploadOk = false;
                        $_SESSION['debug']['file_validation']['extension_check'] = 'Failed - ' . $errorMsg;
                    } else {
                        $_SESSION['debug']['file_validation']['extension_check'] = 'Passed';
                    }
                    
                    // Check file size (max 5MB)
                    $maxFileSize = 5 * 1024 * 1024; // 5MB
                    if ($fileSize > $maxFileSize) {
                        $errorMsg = 'File is too large. Maximum size is 5MB. Actual size: ' . round($fileSize / 1024 / 1024, 2) . 'MB';
                        $uploadOk = false;
                        $_SESSION['debug']['file_validation']['size_check'] = 'Failed - ' . $errorMsg;
                    } elseif ($fileSize == 0) {
                        $errorMsg = 'File is empty.';
                        $uploadOk = false;
                        $_SESSION['debug']['file_validation']['size_check'] = 'Failed - ' . $errorMsg;
                    } else {
                        $_SESSION['debug']['file_validation']['size_check'] = 'Passed';
                    }
                    
                    // Verify it's a real image
                    if ($uploadOk) {
                        $check = getimagesize($fileTmpName);
                        if ($check === false) {
                            $errorMsg = 'File is not a valid image.';
                            $uploadOk = false;
                        }
                        
                        // Verify MIME type
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $fileTmpName);
                        finfo_close($finfo);
                        
                        $allowedMimeTypes = [
                            'image/jpeg',
                            'image/png',
                            'image/webp'
                        ];
                        
                        if (!in_array($mime, $allowedMimeTypes)) {
                            $errorMsg = 'Invalid file format. Only JPG, PNG, and WebP are allowed.';
                            $uploadOk = false;
                        }
                    }
                }
                
                if (!$uploadOk) {
                    $errorMsg = 'Error uploading file: ' . $errorMsg;
                    $errors[] = $errorMsg;
                    $_SESSION['debug']['file_validation']['result'] = 'Validation failed: ' . $errorMsg;
                    $_SESSION['debug']['file_validation']['upload_ok'] = false;
                    
                    // Stop form processing if file validation fails
                    $_SESSION['form_errors'] = $errors;
                    $_SESSION['debug']['form_processing'] = 'Stopped due to file validation errors';
                    header('Location: ' . $_SERVER['HTTP_REFERER']);
                    exit();
                } else {
                    $_SESSION['debug']['file_validation']['result'] = 'Validation passed';
                    $_SESSION['debug']['file_validation']['upload_ok'] = true;
                    // If this is an update, delete any existing image with the same base name
                    if ($eventId > 0) {
                        $existingImages = glob($uploadDir . 'portfolio-img-' . $eventId . '.' . '*');
                        foreach ($existingImages as $existingImage) {
                            if (file_exists($existingImage)) {
                                unlink($existingImage);
                            }
                        }
                    }
                    
                    // Create new filename in the format: portfolio-img-[event_id].[ext]
                    $newEventId = $eventId > 0 ? $eventId : 'temp';
                    $fileName = 'portfolio-img-' . $newEventId . '.' . $fileExt;
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetPath)) {
                        // Store the full URL in the database
                        $eventData['cover_image'] = 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/' . $fileName;
                        
                        // If this is a new event, we'll update the filename after we get the event ID
                        if ($eventId == 0) {
                            $_SESSION['pending_cover_image'] = $targetPath;
                            $_SESSION['pending_cover_extension'] = $fileExt;
                        }
                    } else {
                        $errors[] = 'Failed to upload image. Error: ' . $_FILES['cover_image']['error'];
                    }
                }
            }

            if ($eventId > 0) {
                // Update existing event
                $setClause = [];
                $params = [];
                foreach ($eventData as $key => $value) {
                    $setClause[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
                $params[':id'] = $eventId;

                $sql = "UPDATE events SET " . implode(', ', $setClause) . " WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);

                $message = 'Event updated successfully!';
            } else {
                // Create new event
                $eventData['created_at'] = date('Y-m-d H:i:s');
                $columns = implode(', ', array_keys($eventData));
                $placeholders = ':' . implode(', :', array_keys($eventData));

                $sql = "INSERT INTO events ($columns) VALUES ($placeholders)";
                $stmt = $conn->prepare($sql);

                // Bind parameters with PDO parameter binding
                foreach ($eventData as $key => $value) {
                    $stmt->bindValue(":$key", $value);
                }

                $stmt->execute();
                $eventId = $conn->lastInsertId();
                
                // If this is a new event with a cover image, rename the temp file
                if (isset($_SESSION['pending_cover_image'])) {
                    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/Diplomamunka-26222041/assets/images/portfolio/';
                    $newFileName = $uploadDir . 'portfolio-img-' . $eventId . '.' . $_SESSION['pending_cover_extension'];
                    
                    // Ensure the directory exists
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    if (file_exists($_SESSION['pending_cover_image'])) {
                        if (rename($_SESSION['pending_cover_image'], $newFileName)) {
                            // Update the database with the full URL
                            $fullUrl = 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/portfolio-img-' . $eventId . '.' . $_SESSION['pending_cover_extension'];
                            $updateStmt = $conn->prepare("UPDATE events SET cover_image = ? WHERE id = ?");
                            $updateStmt->execute([$fullUrl, $eventId]);
                            
                            // Update the event data array for the current request
                            $eventData['cover_image'] = $fullUrl;
                        } else {
                            error_log("Failed to rename file from {$_SESSION['pending_cover_image']} to {$newFileName}");
                        }
                    }
                    unset($_SESSION['pending_cover_image']);
                    unset($_SESSION['pending_cover_extension']);
                }
                
                $message = 'Event created successfully!';
            }

            // Handle ticket types
            if (isset($_POST['ticket_types']) && is_array($_POST['ticket_types'])) {
                // Delete existing ticket types for this event if editing
                if ($eventId > 0) {
                    $conn->prepare("DELETE FROM ticket_types WHERE event_id = ?")->execute([$eventId]);
                }

                // Define the correct order of ticket types
                $ticketTypeOrder = ['regular', 'vip'];
                $ticketStmt = $conn->prepare("
                    INSERT INTO ticket_types 
                    (event_id, ticket_type, price, remaining_tickets) 
                    VALUES (:event_id, :ticket_type, :price, :remaining_tickets)
                ");

                // Process ticket types in the defined order
                foreach ($ticketTypeOrder as $type) {
                    if (isset($_POST['ticket_types'][$type])) {
                        $ticket = $_POST['ticket_types'][$type];
                        $ticketStmt->execute([
                            ':event_id' => $eventId,
                            ':ticket_type' => $type,
                            ':price' => (int)$ticket['price'],
                            ':remaining_tickets' => (int)$ticket['quantity']
                        ]);
                    }
                }
            }

            // Handle categories
            if (isset($_POST['categories']) && is_array($_POST['categories'])) {
                // Delete existing categories for this event if editing
                if ($eventId > 0) {
                    $conn->prepare("DELETE FROM event_categories WHERE event_id = ?")->execute([$eventId]);
                }

                // Insert new categories
                $categoryStmt = $conn->prepare("INSERT INTO event_categories (event_id, category) VALUES (:event_id, :category)");

                foreach ($_POST['categories'] as $category) {
                    if (!empty(trim($category))) {
                        $categoryStmt->execute([
                            ':event_id' => $eventId,
                            ':category' => $category
                        ]);
                    }
                }
            }

            $conn->commit();

            // Clear any previous errors
            if (isset($_SESSION['form_errors'])) {
                unset($_SESSION['form_errors']);
            }
            
            $_SESSION['success_message'] = $message;
            header("Location: events.php");
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = 'An error occurred: ' . $e->getMessage();
        }
    }
}

// If editing, load the event data
if ($eventId > 0) {
    // First get the event data
    $query = "SELECT * FROM events WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($event) {
        // Get ticket types with explicit ordering
        $ticketQuery = "
            SELECT ticket_type, price, remaining_tickets 
            FROM ticket_types 
            WHERE event_id = :event_id 
            ORDER BY FIELD(ticket_type, 'regular', 'vip')
        ";
        $ticketStmt = $conn->prepare($ticketQuery);
        $ticketStmt->execute([':event_id' => $eventId]);
        $ticketTypes = [
            'regular' => ['price' => 0, 'quantity' => 0],
            'vip' => ['price' => 0, 'quantity' => 0]
        ];
        
        while ($row = $ticketStmt->fetch(PDO::FETCH_ASSOC)) {
            $ticketTypes[$row['ticket_type']] = [
                'price' => $row['price'],
                'quantity' => (int)$row['remaining_tickets']
            ];
        }
        
        // Get categories
        $categoryQuery = "SELECT category FROM event_categories WHERE event_id = :event_id";
        $categoryStmt = $conn->prepare($categoryQuery);
        $categoryStmt->execute([':event_id' => $eventId]);
        $eventCategories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Add ticket types to event data for backward compatibility
        $event['ticket_types'] = implode(',', array_keys($ticketTypes));
        $event['ticket_prices'] = implode(',', array_column($ticketTypes, 'price'));
        $event['ticket_quantities'] = implode(',', array_column($ticketTypes, 'quantity'));
        $event['categories'] = implode(',', $eventCategories);
    }
    
    if (!$event) {
        $_SESSION['error_message'] = 'Event not found';
        header('Location: events.php');
        exit();
    } else {
        // Ticket types are already loaded in the query above
        
        // Process categories
        $eventCategories = !empty($event['categories']) ? explode(',', $event['categories']) : [];
        
        // Remove the temporary fields we added
        unset($event['ticket_type_ids'], $event['ticket_types'], 
              $event['ticket_prices'], $event['ticket_quantities'], 
              $event['categories']);
    }
}

// Set default values for new event
if (!$event) {
    $event = [
        'name' => '',
        'start_date' => date('Y-m-d\TH:i'),
        'end_date' => date('Y-m-d\TH:i', strtotime('+2 hours')),
        'organizer_id' => '',
        'venue_id' => '',
        'description' => '',
        'total_tickets' => 100,
        'price' => '',
        'slogan' => '',
        'status' => 'draft',
        'cover_image' => ''
    ];
    $event = [
        'name' => '',
        'slogan' => '',
        'lineup' => 'No lineup announced yet :(',
        'description' => '',
        'start_date' => date('Y-m-d\TH:i'),
        'end_date' => date('Y-m-d\TH:i', strtotime('+1 day')),
        'cover_image' => '',
        'organizer_id' => $_SESSION['user_id'] ?? 0,
        'venue_id' => 0,
        'total_tickets' => 100
    ];

    $ticketTypes = [
        'regular' => ['price' => 0, 'quantity' => 0],
        'vip' => ['price' => 0, 'quantity' => 0]
    ];
    
    $ticketTypesStmt = $conn->prepare("SELECT ticket_type, price, remaining_tickets FROM ticket_types WHERE event_id = ?");
    $ticketTypesStmt->execute([$eventId]);
    while ($row = $ticketTypesStmt->fetch(PDO::FETCH_ASSOC)) {
        $ticketTypes[$row['ticket_type']] = [
            'price' => $row['price'],
            'quantity' => $row['remaining_tickets']
        ];
    }
}

$musicGenres = [
    'Ambient', 'Bass', 'Breakbeat', 'Classical', 'Country', 'Dance', 'Deep House', 'Disco', 'Drum & Bass', 'Dubstep', 'EDM', 'Electronic',
    'Folk', 'Hardcore', 'Hardstyle', 'Hip-Hop', 'House', 'Indie', 'Jazz', 'K-Pop', 'Latin', 'Metal', 'Minimal', 'Pop', 'Progressive House',
    'Psytrance', 'Punk', 'R&B', 'Rap', 'Reggae', 'Reggaeton', 'Rock', 'Soul', 'Tech House', 'Techno', 'Trance', 'Trap', 'Trip-Hop'
];
sort($musicGenres, SORT_NATURAL | SORT_FLAG_CASE);

$eventCategories = [];
if ($eventId > 0) {
    $stmt = $conn->query("SELECT category FROM event_categories WHERE event_id = $eventId");
    $eventCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $eventId ? 'Edit' : 'Create'; ?> Event - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/logos/favicon.svg">
    <style>
        body {
            padding: 0;
            margin: 0;
            font-size: 0.9rem;
        }

        .main-content {
            padding: 1rem;
        }

        .card {
            margin-bottom: 0.5rem;
            border: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
        }

        .card-header {
            padding: 0.5rem 0.75rem;
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0, 0, 0, .05);
            font-size: 0.95rem;
        }

        .card-body {
            padding: 0.75rem;
        }

        .form-label {
            margin-bottom: 0.2rem;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .form-control, .form-select {
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
            height: calc(1.4em + 0.5rem + 2px);
            padding: 0.25rem 0.5rem;
        }

        .btn {
            padding: 0.25rem 0.6rem;
            font-size: 0.8rem;
        }

        .ticket-type-card {
            background: #f8f9fa;
            padding: 0.3rem 0.4rem;
            margin-bottom: 0.3rem;
            border-radius: 0.15rem;
            border: 1px solid rgba(0, 0, 0, .05);
        }

        .ticket-type-card:last-child {
            margin-bottom: 0;
        }

        .ticket-type-card h6 {
            font-size: 0.8rem;
            margin: 0 0 0.15rem 0;
            color: #495057;
            line-height: 1.1;
        }

        .row {
            margin-bottom: -0.3rem;
        }

        .row > div {
            margin-bottom: 0.3rem;
        }

        /* Specific element spacing adjustments */
        #eventForm > div > div.col-lg-8 > div:nth-child(2) > div.card-body {
            padding: 0.3rem 0.5rem 0.4rem;
            max-height: 320px !important;
        }

        #eventForm > div > div.col-lg-8 > div.card.mb-4 {
            margin-bottom: 0.3rem !important;
        }

        .form-control, .form-select {
            padding: 0.15rem 0.35rem !important;
            min-height: 28px !important;
            height: auto !important;
        }

        /* Remove outline during inspection */
        *:focus, *:focus-visible, *:focus-within {
            outline: none !important;
            box-shadow: none !important;
        }
    </style>
    <style>
        .select2-container--default .select2-selection--multiple {
            min-height: 38px;
            border: 1px solid #ced4da;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #e9ecef;
            border: 1px solid #ced4da;
        }

        .ticket-type-card {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
        }

        .card {
            height: fit-content;
            margin-bottom: 0 !important;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
            <div class="container-fluid">
                <h4 class="mb-0"><?php echo $eventId ? 'Edit' : 'Create'; ?> Event</h4>
                <div class="d-flex align-items-center">
                    <a href="events.php" class="btn btn-outline-secondary me-2">
                        <i class='bx bx-arrow-back'></i> Back to Events
                    </a>
                    <button type="submit" form="eventForm" class="btn btn-primary">
                        <i class='bx bx-save'></i> Save Event
                    </button>
                </div>
            </div>
        </nav>

        <div class="container-fluid p-0">
            <?php
            // Display both current errors and any stored in session
            $allErrors = [];
            if (!empty($errors)) {
                $allErrors = array_merge($allErrors, $errors);
            }
            if (isset($_SESSION['form_errors']) && is_array($_SESSION['form_errors'])) {
                $allErrors = array_merge($allErrors, $_SESSION['form_errors']);
                unset($_SESSION['form_errors']);
            }

            // Debug output
            if (isset($_SESSION['debug'])) {
                echo '<div class="card mb-4">';
//                echo '<div class="card-header bg-warning">Debug Information</div>';
//                echo '<div class="card-body"><pre>';
//                echo htmlspecialchars(print_r($_SESSION['debug'], true));
                echo '</pre></div></div>';

                // Clear debug info after showing it
                unset($_SESSION['debug']);
            }
            if (!empty($allErrors)): ?>
                <div class="alert alert-danger mb-2">
                    <h5 class="alert-heading">Please fix the following errors:</h5>
                    <ul class="mb-0">
                        <?php foreach (array_unique($allErrors) as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form id="eventForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex gap-3">
                        <div class="flex-grow-1">
                            <div class="card mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Event Details</h5>
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Event Name <span
                                                    class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" required
                                               value="<?php echo htmlspecialchars($event['name']); ?>">
                                        <div class="invalid-feedback">Please provide an event name.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="slogan" class="form-label">Slogan (Optional)</label>
                                        <input type="text" class="form-control" id="slogan" name="slogan"
                                               value="<?php echo htmlspecialchars($event['slogan']); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="lineup" class="form-label">Lineup</label>
                                        <textarea class="form-control" id="lineup" name="lineup" rows="3"
                                                  placeholder="List artists, speakers, or performers separated by commas"><?php echo htmlspecialchars($event['lineup']); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description <span
                                                    class="text-danger">*</span></label>
                                        <textarea class="form-control" id="description" name="description" rows="5"
                                                  required><?php echo htmlspecialchars($event['description']); ?></textarea>
                                        <div class="invalid-feedback">Please provide a description.</div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="start_date" class="form-label">Start Date & Time <span
                                                        class="text-danger">*</span></label>
                                            <input type="datetime-local" class="form-control" id="start_date"
                                                   name="start_date" required
                                                   value="<?php echo date('Y-m-d\TH:i', strtotime($event['start_date'])); ?>">
                                            <div class="invalid-feedback">Please provide a start date and time.</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="end_date" class="form-label">End Date & Time <span
                                                        class="text-danger">*</span></label>
                                            <input type="datetime-local" class="form-control" id="end_date"
                                                   name="end_date" required
                                                   value="<?php echo date('Y-m-d\TH:i', strtotime($event['end_date'])); ?>">
                                            <div class="invalid-feedback">Please provide a valid end date and time.</div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- Organizer field removed - automatically set to current user -->
                                        <input type="hidden" name="organizer_id"
                                               value="<?php echo $_SESSION['user_id']; ?>">
                                        <div class="col-md-6 mb-3">
                                            <label for="venue_id" class="form-label">Venue <span
                                                        class="text-danger">*</span></label>
                                            <select class="form-select" id="venue_id" name="venue_id" required>
                                                <option value="">Select Venue</option>
                                                <?php foreach ($venues as $venue): ?>
                                                    <option value="<?php echo $venue['id']; ?>"
                                                        <?php echo $venue['id'] == $event['venue_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($venue['name'] . ' - ' . $venue['city']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Please select a venue.</div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Venue Capacity</label>
                                        <div class="form-control-plaintext" id="venueCapacityDisplay">
                                            <?php 
                                            $capacity = 0;
                                            if ($eventId > 0 && !empty($event['venue_id'])) {
                                                $venueStmt = $conn->prepare("SELECT capacity FROM venues WHERE id = ?");
                                                $venueStmt->execute([$event['venue_id']]);
                                                $capacity = $venueStmt->fetchColumn() ?: 0;
                                            }
                                            echo number_format($capacity) . ' tickets';
                                            ?>
                                        </div>
                                        <input type="hidden" id="total_tickets" name="total_tickets" value="<?php echo $capacity; ?>">
                                        <small class="text-muted">Capacity is determined by the selected venue.</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Music Genres</label>
                                        <div class="row">
                                            <?php 
                                            $genresPerColumn = ceil(count($musicGenres) / 3);
                                            $chunks = array_chunk($musicGenres, $genresPerColumn);
                                            foreach ($chunks as $column): ?>
                                                <div class="col-md-4">
                                                    <?php foreach ($column as $genre): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   name="categories[]" 
                                                                   value="<?php echo htmlspecialchars($genre); ?>"
                                                                   id="genre_<?php echo preg_replace('/[^a-z0-9]/i', '_', strtolower($genre)); ?>"
                                                                   <?php echo in_array($genre, $eventCategories) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="genre_<?php echo preg_replace('/[^a-z0-9]/i', '_', strtolower($genre)); ?>">
                                                                <?php echo htmlspecialchars($genre); ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="form-text">Select one or more music genres</div>
                                    </div>
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Ticket Types</h5>
                                </div>
                                <div class="card-body">
                                    <div id="ticketTypesContainer">
                                        <?php 
                                        $ticketLabels = [
                                            'regular' => 'Regular',
                                            'vip' => 'VIP'
                                        ];
                                        foreach ($ticketLabels as $type => $label): 
                                            $ticket = $ticketTypes[$type] ?? ['price' => 0, 'quantity' => 0];
                                        ?>
                                            <div class="ticket-type-card">
                                                <h6><?php echo $label; ?> Tickets</h6>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Price (HUF)</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text">HUF</span>
                                                                <input type="number" class="form-control"
                                                                       name="ticket_types[<?php echo $type; ?>][price]"
                                                                       min="100" 
                                                                       max="100000"
                                                                       step="10"
                                                                       value="<?php echo $ticket['price']; ?>"
                                                                       required>
                                                            </div>
                                                            <div class="form-text" style="margin: 0 1rem;">Must be between 100 and 100,000 HUF (increments of 10)</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Quantity</label>
                                                            <input type="number" class="form-control"
                                                                   name="ticket_types[<?php echo $type; ?>][quantity]"
                                                                   min="0"
                                                                   value="<?php echo $ticket['quantity']; ?>"
                                                                   required>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div style="width: 350px; flex-shrink: 0;">
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Event Cover Image</h5>
                            </div>
                            <div class="card-body text-center">
                                <?php 
                                $coverImage = '';
                                if (!empty($event['cover_image'])) {
                                    // If it's a full URL, use it directly
                                    if (filter_var($event['cover_image'], FILTER_VALIDATE_URL)) {
                                        $coverImage = $event['cover_image'];
                                    } 
                                    // If it's a relative path, use the specified base URL
                                    else {
                                        $coverImage = 'http://localhost:63342/Diplomamunka-26222041/' . ltrim($event['cover_image']);
                                    }
                                }
                                ?>
                                <?php if (!empty($coverImage)): ?>
                                    <img src="<?php echo htmlspecialchars($coverImage); ?>" 
                                         alt="Event Cover" class="img-fluid mb-3 preview-image" id="imagePreview" style="max-height: 200px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light p-5 mb-3 text-center">
                                        <i class='bx bx-image text-muted' style="font-size: 3rem;"></i>
                                        <p class="mt-2 mb-0">No image selected</p>
                                    </div>
                                    <img src="" alt="" class="img-fluid mb-3 preview-image d-none" id="imagePreview">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label for="cover_image" class="form-label">Upload New Image</label>
                                    <input class="form-control" type="file" id="cover_image" name="cover_image"
                                           accept="image/*" onchange="previewImage(this)">
                                </div>
                                <small class="text-muted d-block mt-2">Recommended size: 1200x630px (16:9 aspect
                                    ratio)</small>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    // Set minimum end date to start date + 1 day
    document.getElementById('start_date').addEventListener('change', function() {
        const startDate = new Date(this.value);
        const endDateInput = document.getElementById('end_date');
        
        if (startDate) {
            // Set minimum end date to start date + 1 day
            const minEndDate = new Date(startDate);
            minEndDate.setDate(minEndDate.getDate() + 1);
            
            // Set maximum end date to 1 month after start date
            const maxEndDate = new Date(startDate);
            maxEndDate.setMonth(maxEndDate.getMonth() + 1);
            
            // Format dates for the datetime-local input (YYYY-MM-DDThh:mm)
            const formatDate = (date) => {
                return date.toISOString().slice(0, 16);
            };
            
            // Update end date constraints
            endDateInput.min = formatDate(minEndDate);
            endDateInput.max = formatDate(maxEndDate);
            
            // If current end date is before new min date, update it
            if (endDateInput.value && new Date(endDateInput.value) < minEndDate) {
                endDateInput.value = formatDate(minEndDate);
            }
        }
    });
    
    // Initialize end date constraints on page load
    document.addEventListener('DOMContentLoaded', function() {
        const startDateInput = document.getElementById('start_date');
        if (startDateInput.value) {
            startDateInput.dispatchEvent(new Event('change'));
        }
    });
</script>
<script>
// Store venue capacities in a JavaScript object for quick lookup
const venueCapacities = {
    <?php 
    $venueCaps = [];
    foreach ($venues as $venue) {
        $venueCaps[] = '"' . $venue['id'] . '": ' . $venue['capacity'];
    }
    echo implode(",\n    ", $venueCaps);
    ?>
};

// Function to update capacity display
function updateVenueCapacity(venueId) {
    const capacityDisplay = document.getElementById('venueCapacityDisplay');
    const totalTicketsInput = document.getElementById('total_tickets');
    
    if (venueId && venueCapacities[venueId] !== undefined) {
        const capacity = parseInt(venueCapacities[venueId]);
        capacityDisplay.textContent = capacity.toLocaleString() + ' tickets';
        totalTicketsInput.value = capacity;
    } else {
        capacityDisplay.textContent = '0 tickets';
        totalTicketsInput.value = '0';
    }
}

// Update venue capacity display when venue selection changes
document.getElementById('venue_id').addEventListener('change', function() {
    updateVenueCapacity(this.value);
});

// Initialize with current venue capacity
const initialVenueId = document.getElementById('venue_id').value;
if (initialVenueId) {
    updateVenueCapacity(initialVenueId);
}
</script>
<script>
    $(document).ready(function () {
        // No Select2 initialization needed for checkboxes

        // Form validation
        (function () {
            'use strict'

            var forms = document.querySelectorAll('.needs-validation')

            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }

                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    });

    // Image preview
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        const file = input.files[0];
        const reader = new FileReader();

        reader.onloadend = function () {
            preview.src = reader.result;
            preview.classList.remove('d-none');

            // Hide the placeholder if it exists
            const placeholder = input.previousElementSibling;
            if (placeholder && placeholder.classList.contains('bg-light')) {
                placeholder.style.display = 'none';
            }
        }

        if (file) {
            reader.readAsDataURL(file);
        } else {
            preview.src = '';
            preview.classList.add('d-none');

            // Show the placeholder again
            const placeholder = input.previousElementSibling;
            if (placeholder && placeholder.classList.contains('bg-light')) {
                placeholder.style.display = 'block';
            }
        }
    }
</script>
</body>
</html>
