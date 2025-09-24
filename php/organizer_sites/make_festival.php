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

function redirectWithErrors($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header('Location: festival_maker.php');
    exit;
}

function log_to_file($message, $data = null) {
    $logDir = 'http://localhost/Diplomamunka-26222041/php/logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . 'event_creation.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMsg = "[$timestamp] $message";
    if ($data !== null) {
        $logMsg .= ' | ' . print_r($data, true);
    }
    file_put_contents($logFile, $logMsg . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    $errors = [];

    log_to_file("Form submission received", [
        'POST' => $_POST,
        'FILES' => $_FILES
    ]);

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
        $maxEndDate = clone $startDate;
        $maxEndDate->modify('+1 month');

        if ($startDate < $now) {
            $errors[] = 'Start date must be in the future.';
        }
        if ($endDate < $minEndDate || $endDate > $maxEndDate) {
            $errors[] = 'End date must be at least 1 day and no more than one month after the start date.';
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
            $price = isset($ticket['price']) && $ticket['price'] !== '' ? (int)$ticket['price'] : 0;
            $quantity = isset($ticket['quantity']) && $ticket['quantity'] !== '' ? (int)$ticket['quantity'] : 0;

            if ($price < 0) {
                $errors[] = 'Ticket price cannot be negative.';
            }
            if ($quantity < 0) {
                $errors[] = 'Ticket quantity cannot be negative.';
            }
            if ($price > 0 && $price < 100) {
                $errors[] = 'Ticket price must be at least 100 HUF.';
            }
            // If quantity is empty, treat as 0 (already handled above)
            if ($ticket['type'] === 'regular' && $quantity > 0) $hasRegular = true;
            if ($ticket['type'] === 'vip' && $quantity > 0) $hasVIP = true;
            $totalTickets += $quantity;
        }
    }

    if (!$hasRegular || !$hasVIP) {
        $errors[] = 'Both Regular and VIP ticket types are required (with at least 1 ticket each).';
    }
    if ($totalTickets < 0) {
        $errors[] = 'Total ticket quantity cannot be negative.';
    }

    // Log after validation
    log_to_file("Validation completed", [
        'errors' => $errors,
        'input' => $input
    ]);

    if (!empty($errors)) {
        log_to_file("Validation failed", $errors);
        redirectWithErrors($errors);
    }

    try {
        $pdo->beginTransaction();
        log_to_file("Transaction started");

        // Handle file upload first
        $finalFileName = '';
        $imageUrl = '';

        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['cover_image'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            // Create temporary event ID for the filename
            $tempEventId = time();
            $finalFileName = 'portfolio-img-' . $tempEventId . '.' . $fileExt;
            $uploadDir = __DIR__ . '/../../assets/images/portfolio/';
            $finalPath = $uploadDir . $finalFileName;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            log_to_file("Moving uploaded file", [
                'tmp_name' => $file['tmp_name'],
                'finalPath' => $finalPath
            ]);

            if (!move_uploaded_file($file['tmp_name'], $finalPath)) {
                log_to_file("Failed to upload image file", $finalPath);
                throw new Exception('Failed to upload image file.');
            }

            $imageUrl = 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/' . $finalFileName;
            log_to_file("Image uploaded", [
                'finalFileName' => $finalFileName,
                'imageUrl' => $imageUrl
            ]);
        } else {
            log_to_file("Cover image is missing or upload error.");
            throw new Exception('Cover image is required.');
        }

        // Prepare event data for insertion
        $eventData = [
            $input['name'],
            $input['slogan'] ?? '',
            $input['start_date'],
            $input['end_date'],
            (int)$input['venue_id'],
            $input['description'] ?? '',
            $input['lineup'] ?? '',
            $imageUrl,
            $_SESSION['user_id'],
            $totalTickets
        ];
        log_to_file("Prepared event data for insertion", $eventData);

        // Now insert the event with the correct image path
        $stmt = $pdo->prepare("
            INSERT INTO events (
                name, slogan, start_date, end_date, venue_id, 
                description, lineup, cover_image, organizer_id, total_tickets
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $success = $stmt->execute($eventData);
        log_to_file("Event insert executed", [
            'success' => $success,
            'eventData' => $eventData
        ]);

        $eventId = $pdo->lastInsertId();
        log_to_file("Event inserted with ID", $eventId);

        // Rename the file with the actual event ID
        $newFileName = 'portfolio-img-' . $eventId . '.' . $fileExt;
        $newPath = $uploadDir . $newFileName;
        $newImageUrl = 'http://localhost:63342/Diplomamunka-26222041/assets/images/portfolio/' . $newFileName;

        log_to_file("Renaming image file", [
            'from' => $finalPath,
            'to' => $newPath
        ]);

        if (!rename($finalPath, $newPath)) {
            log_to_file("Failed to rename image file", [
                'from' => $finalPath,
                'to' => $newPath
            ]);
            throw new Exception('Failed to rename image file.');
        }
        log_to_file("Image renamed", $newPath);

        // Update the event with the final image URL
        $updateStmt = $pdo->prepare("UPDATE events SET cover_image = ? WHERE id = ?");
        $updateSuccess = $updateStmt->execute([$newImageUrl, $eventId]);
        log_to_file("Event cover_image updated", [
            'newImageUrl' => $newImageUrl,
            'updateSuccess' => $updateSuccess
        ]);

        // Insert genres
        if (!empty($input['genres'])) {
            $genreStmt = $pdo->prepare("INSERT INTO event_categories (event_id, category) VALUES (?, ?)");
            foreach ($input['genres'] as $genre) {
                $genreStmt->execute([$eventId, $genre]);
                log_to_file("Inserted genre", [
                    'eventId' => $eventId,
                    'genre' => $genre
                ]);
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
                log_to_file("Inserted ticket type", [
                    'eventId' => $eventId,
                    'ticket' => $ticket
                ]);
            }
        }

        log_to_file("Event creation committed", $eventId);
        $pdo->commit();
        $_SESSION['success_message'] = 'Event created successfully!';
        header('Location: myevents.php');
        exit;

    } catch (Exception $e) {
        log_to_file("Exception", $e->getMessage());
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
            log_to_file("Transaction rolled back");
        }
        $_SESSION['error'] = 'Error: ' . $e->getMessage() . ". Please check the error logs for more details.";
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'festival_maker.php'));
        exit();
    }
}
