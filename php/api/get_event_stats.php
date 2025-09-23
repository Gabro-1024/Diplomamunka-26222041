<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_connect.php';

// Enable error logging
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../php/logs/stats_errors.log');

// Function to log errors
function logError($message, $data = []) {
    $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    if (!empty($data)) {
        $logMessage .= 'Data: ' . print_r($data, true) . "\n";
    }
    error_log($logMessage);
    return ['success' => false, 'message' => $message, 'debug' => $data];
}

// Check if event_id is provided
if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    $error = logError('Invalid event ID provided', ['_GET' => $_GET]);
    echo json_encode($error);
    exit;
}

$eventId = (int)$_GET['event_id'];

// Log the request
logError('Stats request received', ['event_id' => $eventId, 'timestamp' => date('Y-m-d H:i:s')]);

try {
    $pdo = db_connect();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    // Get event details
    // Get event details with error handling and logging
    try {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        if (!$stmt->execute([$eventId])) {
            throw new Exception('Failed to execute event query');
        }
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            logError('Event not found', ['event_id' => $eventId]);
            echo json_encode(['success' => false, 'message' => 'Event not found']);
            exit;
        }
    } catch (PDOException $e) {
        logError('Database error fetching event', [
            'error' => $e->getMessage(),
            'event_id' => $eventId,
            'trace' => $e->getTraceAsString()
        ]);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    // Get ticket types and counts with error handling
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ticket_type,
                price,
                remaining_tickets,
                (SELECT COUNT(*) FROM tickets t WHERE t.event_id = tt.event_id AND t.price = tt.price) as sold_tickets,
                (SELECT COUNT(*) FROM tickets t WHERE t.event_id = tt.event_id AND t.price = tt.price AND t.is_used = 1) as used_tickets
            FROM ticket_types tt
            WHERE tt.event_id = ?
            GROUP BY ticket_type, price, remaining_tickets
        ");
        
        if (!$stmt->execute([$eventId])) {
            throw new Exception('Failed to execute ticket types query');
        }
        
        $ticketTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        logError('Ticket types fetched', ['count' => count($ticketTypes)]);
        
    } catch (PDOException $e) {
        logError('Error fetching ticket types', [
            'error' => $e->getMessage(),
            'event_id' => $eventId,
            'trace' => $e->getTraceAsString()
        ]);
        $ticketTypes = [];
    }

    // Calculate ticket statistics
    $ticketStats = [
        'regular' => ['sold' => 0, 'total' => 0, 'used' => 0, 'price' => 0, 'remaining' => 0],
        'vip' => ['sold' => 0, 'total' => 0, 'used' => 0, 'price' => 0, 'remaining' => 0],
        'total_remaining' => 0
    ];

    foreach ($ticketTypes as $type) {
        $ticketType = strtolower($type['ticket_type']); // Ensure consistent case
        $sold = (int)$type['sold_tickets'];
        $remaining = (int)$type['remaining_tickets'];
        $total = $sold + $remaining;
        
        $ticketStats[$ticketType]['sold'] = $sold;
        $ticketStats[$ticketType]['used'] = (int)$type['used_tickets'];
        $ticketStats[$ticketType]['price'] = (float)$type['price'];
        $ticketStats[$ticketType]['remaining'] = $remaining;
        $ticketStats[$ticketType]['total'] = $total;
        
        // Update total remaining tickets across all types
        $ticketStats['total_remaining'] += $remaining;
    }

    // Get payment information
    $stmt = $pdo->prepare("
        SELECT 
            p.payment_method,
            SUM(t.price) as total_amount,
            COUNT(t.id) as ticket_count
        FROM purchases p
        JOIN tickets t ON p.id = t.purchase_id
        WHERE t.event_id = ? AND p.status = 'completed'
        GROUP BY p.payment_method
    ");
    $stmt->execute([$eventId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $paymentStats = [
        'paypal' => ['amount' => 0, 'tickets' => 0],
        'stripe' => ['amount' => 0, 'tickets' => 0],
        'total' => 0
    ];

    foreach ($payments as $payment) {
        $method = strtolower($payment['payment_method']);
        $paymentStats[$method]['amount'] = (float)$payment['total_amount'];
        $paymentStats[$method]['tickets'] = (int)$payment['ticket_count'];
        $paymentStats['total'] += (float)$payment['total_amount'];
    }

    // Calculate time until event
    $now = new DateTime();
    $startDate = new DateTime($event['start_date']);
    $endDate = new DateTime($event['end_date']);
    $timeToEvent = $now->diff($startDate);
    $isEventOngoing = ($now >= $startDate && $now <= $endDate);
    $isEventEnded = ($now > $endDate);

    // Prepare response
    $response = [
        'success' => true,
        'event' => [
            'name' => $event['name'],
            'start_date' => $event['start_date'],
            'end_date' => $event['end_date'],
            'is_ongoing' => $isEventOngoing,
            'is_ended' => $isEventEnded,
            'days_until' => $timeToEvent->days,
            'total_tickets' => (int)$event['total_tickets'],
            'remaining_tickets' => $ticketStats['total_remaining']
        ],
        'tickets' => $ticketStats,
        'payments' => $paymentStats,
        'total_revenue' => $paymentStats['total'],
        'tickets_sold_percentage' => $event['total_tickets'] > 0 
            ? round((($event['total_tickets'] - $ticketStats['total_remaining']) / $event['total_tickets']) * 100, 1)
            : 0
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching statistics: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
