<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Initialize database connection
$conn = db_connect();

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

// Fetch all venues
$venues = $conn->query("SELECT id, name, city FROM venues ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all organizers
$organizers = $conn->query("SELECT id, first_name, last_name FROM users WHERE role = 'organizer' OR role = 'admin' ORDER BY first_name")
    ->fetchAll(PDO::FETCH_ASSOC);

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
    
    if (strtotime($_POST['end_date']) < strtotime($_POST['start_date'])) {
        $errors[] = 'End date must be after start date';
    }
    
    if (empty($errors)) {
        $conn->beginTransaction();
        
        try {
            // Prepare event data
            $eventData = [
                'name' => $_POST['name'],
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'organizer_id' => (int)$_POST['organizer_id'],
                'venue_id' => (int)$_POST['venue_id'],
                'description' => $_POST['description'],
                'total_tickets' => (int)$_POST['total_tickets'],
                'price' => !empty($_POST['price']) ? (int)$_POST['price'] : 0,
                'slogan' => $_POST['slogan'] ?? null,
                'status' => $_POST['status'] ?? 'draft',
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Handle file upload
            if (!empty($_FILES['cover_image']['name'])) {
                $uploadDir = '../../uploads/events/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExt = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
                $fileName = uniqid('event_') . '.' . $fileExt;
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetPath)) {
                    $eventData['cover_image'] = 'uploads/events/' . $fileName;
                    
                    // Delete old image if exists
                    if ($eventId > 0 && !empty($event['cover_image'])) {
                        $oldImage = '../../' . $event['cover_image'];
                        if (file_exists($oldImage)) {
                            unlink($oldImage);
                        }
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
                $message = 'Event created successfully!';
            }
            
            // Handle ticket types
            if (isset($_POST['ticket_types']) && is_array($_POST['ticket_types'])) {
                // Delete existing ticket types for this event if editing
                if ($eventId > 0) {
                    $conn->prepare("DELETE FROM ticket_types WHERE event_id = ?")->execute([$eventId]);
                }
                
                // Insert new ticket types
                if (isset($_POST['ticket_types']) && is_array($_POST['ticket_types'])) {
                    $ticketStmt = $conn->prepare("INSERT INTO ticket_types (event_id, ticket_type) VALUES (:event_id, :ticket_type)");
                    
                    foreach ($_POST['ticket_types'] as $ticket) {
                        if (!empty($ticket['name'])) {
                            $ticketStmt->execute([
                                ':event_id' => $eventId,
                                ':ticket_type' => $ticket['name']
                            ]);
                        }
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
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = :id");
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        $_SESSION['error_message'] = 'Event not found';
        header('Location: events.php');
        exit();
    } else {
        // Load ticket types for this event
        $ticketStmt = $conn->prepare("SELECT ticket_type_id as id, ticket_type as name FROM ticket_types WHERE event_id = :event_id");
        $ticketStmt->execute([':event_id' => $eventId]);
        $ticketTypes = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Load event categories
        $categoryStmt = $conn->prepare("SELECT category FROM event_categories WHERE event_id = :event_id");
        $categoryStmt->execute([':event_id' => $eventId]);
        $eventCategories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
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
    
    // Default ticket types
    $ticketTypes = [
        'regular' => ['price' => 0, 'quantity' => 0],
        'vip' => ['price' => 0, 'quantity' => 0]
    ];
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
        body { padding: 0; margin: 0; font-size: 0.9rem; }
        .main-content { padding: 1rem; }
        .card { margin-bottom: 0.5rem; border: none; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
        .card-header { padding: 0.5rem 0.75rem; background-color: #f8f9fa; border-bottom: 1px solid rgba(0,0,0,.05); font-size: 0.95rem; }
        .card-body { padding: 0.75rem; }
        .form-label { margin-bottom: 0.2rem; font-weight: 500; font-size: 0.85rem; }
        .form-control, .form-select { 
            margin-bottom: 0.4rem; 
            font-size: 0.85rem; 
            height: calc(1.4em + 0.5rem + 2px);
            padding: 0.25rem 0.5rem;
        }
        .btn { padding: 0.25rem 0.6rem; font-size: 0.8rem; }
        .ticket-type-card { 
            background: #f8f9fa; 
            padding: 0.3rem 0.4rem; 
            margin-bottom: 0.3rem; 
            border-radius: 0.15rem; 
            border: 1px solid rgba(0,0,0,.05);
        }
        .ticket-type-card:last-child { margin-bottom: 0; }
        .ticket-type-card h6 { 
            font-size: 0.8rem; 
            margin: 0 0 0.15rem 0; 
            color: #495057; 
            line-height: 1.1;
        }
        .row { margin-bottom: -0.3rem; }
        .row > div { margin-bottom: 0.3rem; }
        
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
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mb-2">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
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
                                        <label for="name" class="form-label">Event Name <span class="text-danger">*</span></label>
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
                                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                                        <div class="invalid-feedback">Please provide a description.</div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="start_date" class="form-label">Start Date & Time <span class="text-danger">*</span></label>
                                            <input type="datetime-local" class="form-control" id="start_date" name="start_date" required
                                                   value="<?php echo date('Y-m-d\TH:i', strtotime($event['start_date'])); ?>">
                                            <div class="invalid-feedback">Please provide a start date and time.</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="end_date" class="form-label">End Date & Time <span class="text-danger">*</span></label>
                                            <input type="datetime-local" class="form-control" id="end_date" name="end_date" required
                                                   value="<?php echo date('Y-m-d\TH:i', strtotime($event['end_date'])); ?>">
                                            <div class="invalid-feedback">Please provide an end date and time.</div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="organizer_id" class="form-label">Organizer <span class="text-danger">*</span></label>
                                            <select class="form-select" id="organizer_id" name="organizer_id" required>
                                                <option value="">Select Organizer</option>
                                                <?php foreach ($organizers as $organizer): ?>
                                                    <option value="<?php echo $organizer['id']; ?>" 
                                                        <?php echo $organizer['id'] == $event['organizer_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($organizer['first_name'] . ' ' . $organizer['last_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Please select an organizer.</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="venue_id" class="form-label">Venue <span class="text-danger">*</span></label>
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
                                        <label for="total_tickets" class="form-label">Total Tickets Available <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="total_tickets" name="total_tickets" required
                                               min="1" value="<?php echo $event['total_tickets']; ?>">
                                        <div class="invalid-feedback">Please provide the total number of tickets available.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="categories" class="form-label">Categories</label>
                                        <select class="form-select" id="categories" name="categories[]" multiple>
                                            <?php foreach ($allCategories as $category): ?>
                                                <option value="<?php echo htmlspecialchars($category); ?>"
                                                    <?php echo in_array($category, $eventCategories) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Ticket Types</h5>
                                </div>
                                <div class="card-body">
                                    <div id="ticketTypesContainer">
                                        <?php foreach (['regular' => 'Regular', 'vip' => 'VIP'] as $type => $label): ?>
                                            <?php $ticket = $ticketTypes[$type] ?? ['price' => 0, 'quantity' => 0]; ?>
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
                                                                       min="0" step="1" 
                                                                       value="<?php echo number_format($ticket['price'], 0, ',', ' '); ?>" 
                                                                       required>
                                                            </div>
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
                                    <?php if (!empty($event['cover_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($event['cover_image']); ?>" 
                                             alt="Event Cover" class="img-fluid mb-3 preview-image" id="imagePreview">
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
                                    <small class="text-muted d-block mt-2">Recommended size: 1200x630px (16:9 aspect ratio)</small>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Publish</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class='bx bx-save'></i> Save Event
                                        </button>
                                        <a href="events.php" class="btn btn-outline-secondary">
                                            <i class='bx bx-x'></i> Cancel
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2 for categories
        $(document).ready(function() {
            $('#categories').select2({
                tags: true,
                tokenSeparators: [',', ' '],
                placeholder: 'Select or type categories',
                allowClear: true
            });
            
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
            
            reader.onloadend = function() {
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
