<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON content type
header('Content-Type: application/json');

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token']);
    exit;
}

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organizer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $pdo = db_connect();
    $pdo->beginTransaction();

    // Get and validate event ID
    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    if (!$event_id) {
        throw new Exception('Invalid event ID');
    }

    $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ? AND organizer_id = ?");
    $stmt->execute([$event_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Event not found or access denied');
    }

    $required_fields = [
        'name' => 'Event name',
        'slogan' => 'Event slogan',
        'description' => 'Event description',
        'start_date' => 'Start date',
        'end_date' => 'End date',
        'venue_id' => 'Venue',
        'lineup' => 'Event lineup'
    ];

    $data = [];
    $errors = [];

    foreach ($required_fields as $field => $label) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            $errors[$field] = "$label is required";
        } else {
            $data[$field] = trim($_POST[$field]);
        }
    }

    // Validate ticket types
    $ticket_types = [
        'regular' => [
            'price' => filter_input(INPUT_POST, 'regular_price', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]),
            'quantity' => filter_input(INPUT_POST, 'regular_quantity', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]])
        ],
        'vip' => [
            'price' => filter_input(INPUT_POST, 'vip_price', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]),
            'quantity' => filter_input(INPUT_POST, 'vip_quantity', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]])
        ]
    ];

    // Validate ticket types
    foreach ($ticket_types as $type => $values) {
        if ($values['price'] === false) {
            $errors[$type . '_price'] = 'Invalid ' . $type . ' ticket price (must be between 0 and 1,000,000 HUF)';
        }
        if ($values['quantity'] === false) {
            $errors[$type . '_quantity'] = 'Invalid ' . $type . ' ticket quantity (must be between 0 and 100,000)';
        }
    }

    // Validate dates using timestamps for more accurate comparison
    $start_date = new DateTime($data['start_date']);
    $end_date = new DateTime($data['end_date']);
    $now = new DateTime();
    $max_end_date = new DateTime('2051-12-31 23:59:59');
    
    // Convert to timestamps for precise comparison
    $start_timestamp = $start_date->getTimestamp();
    $end_timestamp = $end_date->getTimestamp();
    $one_month_later = (clone $start_date)->modify('+1 month')->getTimestamp();

    if ($start_date < $now) {
        $errors['start_date'] = 'Start date must be in the future';
    }

    if ($end_date <= $start_date) {
        $errors['end_date'] = 'End date must be after start date';
    }

    if ($end_date > $max_end_date) {
        $errors['end_date'] = 'End date cannot be later than December 31, 2051';
    }

    if ($end_timestamp > $one_month_later) {
        $errors['end_date'] = 'Event duration cannot exceed one month';
    }

    // Validate venue exists
    $stmt = $pdo->prepare("SELECT id FROM venues WHERE id = ?");
    $stmt->execute([$data['venue_id']]);
    if (!$stmt->fetch()) {
        $errors['venue_id'] = 'Selected venue does not exist';
    }

    // Check if there are any errors
    if (!empty($errors)) {
        throw new Exception('Validation failed');
    }

    // Update event details
    $stmt = $pdo->prepare("
        UPDATE events 
        SET name = ?, slogan = ?, description = ?, start_date = ?, end_date = ?, 
            venue_id = ?, lineup = ?
        WHERE id = ? AND organizer_id = ?
    ");
    $stmt->execute([
        $data['name'],
        $data['slogan'],
        $data['description'],
        $data['start_date'],
        $data['end_date'],
        $data['venue_id'],
        $data['lineup'],
        $event_id,
        $_SESSION['user_id']
    ]);

    // Update or insert ticket types
    foreach ($ticket_types as $type => $values) {
        // Check if ticket type exists
        $stmt = $pdo->prepare("
            SELECT ticket_type_id FROM ticket_types 
            WHERE event_id = ? AND ticket_type = ?
        ");
        $stmt->execute([$event_id, $type]);
        $ticket_type = $stmt->fetch();

        if ($ticket_type) {
            // Update existing ticket type
            $stmt = $pdo->prepare("
                UPDATE ticket_types 
                SET price = ?, remaining_tickets = ?
                WHERE ticket_type_id = ? AND event_id = ?
            ");
            $stmt->execute([
                $values['price'],
                $values['quantity'],
                $ticket_type['ticket_type_id'],
                $event_id
            ]);
        } else {
            // Insert new ticket type
            $stmt = $pdo->prepare("
                INSERT INTO ticket_types (event_id, ticket_type, price, remaining_tickets)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $event_id,
                $type,
                $values['price'],
                $values['quantity']
            ]);
        }
    }

    // Handle categories
    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
        // First, delete existing categories
        $stmt = $pdo->prepare("DELETE FROM event_categories WHERE event_id = ?");
        $stmt->execute([$event_id]);

        // Insert new categories
        $stmt = $pdo->prepare("
            INSERT INTO event_categories (event_id, category) 
            VALUES (?, ?)
        ");
        foreach ($_POST['categories'] as $category) {
            $stmt->execute([$event_id, trim($category)]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Event updated successfully',
        'event_id' => $event_id
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $response = ['success' => false];

    if ($e->getMessage() === 'Validation failed') {
        $response['errors'] = $errors;
    } else {
        $response['message'] = 'An error occurred while updating the event: ' . $e->getMessage();
        error_log('Event update error: ' . $e->getMessage());
    }

    http_response_code(400);
    echo json_encode($response);
}