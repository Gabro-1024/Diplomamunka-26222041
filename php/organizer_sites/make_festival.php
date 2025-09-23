<?php
// Start the session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

try {
    $pdo = db_connect();
} catch (Exception $e) {
    error_log("Error connecting to database: " . $e->getMessage());
}

// Function to redirect with errors
function redirectWithErrors($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header('Location: festival_maker.php');
    exit;
}

// Check if this is a form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    $errors = [];

    // Enhanced validation
    $errors = [];

    // Basic field validation
    if (empty($input['name'])) $errors[] = 'Event name is required.';
    if (empty($input['slogan'])) $errors[] = 'Event slogan is required.';
    if (empty($input['description'])) $errors[] = 'Event description is required.';
    if (empty($input['lineup'])) $errors[] = 'Lineup is required.';
    if (empty($input['venue_id'])) $errors[] = 'Venue selection is required.';
    if (empty($input['genres'])) $errors[] = 'At least one genre must be selected.';

    // Date validation
    try {
        $startDate = new DateTime($input['start_date']);
        $endDate = new DateTime($input['end_date']);
        $now = new DateTime();
        $minEndDate = clone $startDate;
        $minEndDate->modify('+1 day');

        if ($startDate < $now) {
            $errors[] = 'Start date must be in the future.';
        }
        if ($endDate <= $minEndDate) {
            $errors[] = 'End date must be at least 1 day after the start date.';
        }
    } catch (Exception $e) {
        $errors[] = 'Invalid date format.';
    }

    // File upload validation and handling
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['cover_image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        $fileType = mime_content_type($file['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = 'Invalid file type. Only JPEG, PNG, and WebP images are allowed.';
        }
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size must be less than 5MB.';
        }
    } else {
        $errors[] = 'Cover image is required.';
    }

    // Ticket validation
    $hasRegular = false;
    $hasVIP = false;
    $totalTickets = 0;

    if (!empty($input['ticket_types']) && is_array($input['ticket_types'])) {
        foreach ($input['ticket_types'] as $ticket) {
            if (empty($ticket['price']) || $ticket['price'] < 0) {
                $errors[] = 'Invalid ticket price.';
            }
            if (empty($ticket['quantity']) || $ticket['quantity'] < 1) {
                $errors[] = 'Invalid ticket quantity.';
            }
            if ($ticket['type'] === 'regular') $hasRegular = true;
            if ($ticket['type'] === 'vip') $hasVIP = true;
            $totalTickets += (int)$ticket['quantity'];
        }
    }

    if (!$hasRegular || !$hasVIP) {
        $errors[] = 'Both Regular and VIP ticket types are required.';
    }
    if ($totalTickets <= 0) {
        $errors[] = 'Total ticket quantity must be greater than 0.';
    }

    if (!empty($errors)) {
        redirectWithErrors($errors);
    }

    // Process form if no errors
    try {
        $pdo->beginTransaction();

        // Handle file upload first
        $finalFileName = '';
        $imageUrl = '';

        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['cover_image'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            // Create temporary event ID for the filename
            $tempEventId = time(); // We'll update this after getting the real event ID
            $finalFileName = 'portfolio-img-' . $tempEventId . '.' . $fileExt;
            $uploadDir = __DIR__ . '/../../assets/images/portfolio/';
            $finalPath = $uploadDir . $finalFileName;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Move the file with temporary name
            if (!move_uploaded_file($file['tmp_name'], $finalPath)) {
                throw new Exception('Failed to upload image file.');
            }

            // Set the URL that will be stored in database
            $imageUrl = 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/' . $finalFileName;
        } else {
            throw new Exception('Cover image is required.');
        }

        // Now insert the event with the correct image path
        $stmt = $pdo->prepare("
            INSERT INTO events (
                name, slogan, start_date, end_date, venue_id, 
                description, lineup, cover_image, organizer_id, total_tickets
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $input['name'],
            $input['slogan'] ?? '',
            $input['start_date'],
            $input['end_date'],
            (int)$input['venue_id'],
            $input['description'] ?? '',
            $input['lineup'] ?? '',
            $imageUrl, // Use the correct image URL
            $_SESSION['user_id'],
            $totalTickets
        ]);

        $eventId = $pdo->lastInsertId();

        // Rename the file with the actual event ID
        $newFileName = 'portfolio-img-' . $eventId . '.' . $fileExt;
        $newPath = $uploadDir . $newFileName;
        $newImageUrl = 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/' . $newFileName;

        // Rename the file
        if (!rename($finalPath, $newPath)) {
            throw new Exception('Failed to rename image file.');
        }

        // Update the event with the final image URL
        $updateStmt = $pdo->prepare("UPDATE events SET cover_image = ? WHERE id = ?");
        $updateStmt->execute([$newImageUrl, $eventId]);

        // Add debug output
        error_log("Debug - Image Upload:");
        error_log("Final Path: " . $newPath);
        error_log("Image URL: " . $newImageUrl);

        // Insert genres
        if (!empty($input['genres'])) {
            $genreStmt = $pdo->prepare("INSERT INTO event_categories (event_id, category) VALUES (?, ?)");
            foreach ($input['genres'] as $genre) {
                $genreStmt->execute([$eventId, $genre]);
            }
        }

        // Insert ticket types
        if (!empty($input['ticket_types'])) {
            $ticketStmt = $pdo->prepare("
                INSERT INTO ticket_types (event_id, ticket_type, price, remaining_tickets)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($input['ticket_types'] as $ticket) {
                $ticketStmt->execute([
                    $eventId,
                    $ticket['type'],
                    (int)$ticket['price'],
                    (int)$ticket['quantity']
                ]);
            }
        }
        var_dump($finalFileName, $finalPath, $imageUrl, $eventId);
        $pdo->commit();
        $_SESSION['success_message'] = 'Event created successfully!';
        header('Location: myevents.php');
        exit;

    } catch (Exception $e) {
        // Log the full error with trace
        error_log("Error in make_festival.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());

        // Rollback the transaction on error
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("Transaction rolled back");
        }

        // For debugging, show the actual error message to the user
        $_SESSION['error'] = 'Error: ' . $e->getMessage() . ". Please check the error logs for more details.";
        error_log("Redirecting back to: " . $_SERVER['HTTP_REFERER']);
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'festival_maker.php'));
        exit();
    }
}
