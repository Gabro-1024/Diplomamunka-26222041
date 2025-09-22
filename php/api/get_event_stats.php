<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_connect.php';

// Check if event_id is provided
if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit;
}

$eventId = (int)$_GET['event_id'];
$pdo = db_connect();

try {
    // Get event details
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception('Event not found');
    }

    // Get ticket types and counts
    $stmt = $pdo->prepare("
        SELECT 
            ticket_type,
            price,
            (SELECT COUNT(*) FROM tickets t WHERE t.event_id = tt.event_id AND t.price = tt.price) as sold_tickets,
            (SELECT COUNT(*) FROM tickets t WHERE t.event_id = tt.event_id AND t.price = tt.price AND t.is_used = 1) as used_tickets
        FROM ticket_types tt
        WHERE tt.event_id = ?
        GROUP BY ticket_type, price
    ");
    $stmt->execute([$eventId]);
    $ticketTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate ticket statistics
    $ticketStats = [
        'regular' => ['sold' => 0, 'total' => 0, 'used' => 0, 'price' => 0],
        'vip' => ['sold' => 0, 'total' => 0, 'used' => 0, 'price' => 0]
    ];

    foreach ($ticketTypes as $type) {
        $ticketType = $type['ticket_type'];
        $ticketStats[$ticketType]['sold'] += (int)$type['sold_tickets'];
        $ticketStats[$ticketType]['used'] += (int)$type['used_tickets'];
        $ticketStats[$ticketType]['price'] = (float)$type['price'];
        $ticketStats[$ticketType]['total'] = $ticketStats[$ticketType]['sold'] + $event['remaining_tickets'];
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
            'remaining_tickets' => (int)$event['remaining_tickets']
        ],
        'tickets' => $ticketStats,
        'payments' => $paymentStats,
        'total_revenue' => $paymentStats['total'],
        'tickets_sold_percentage' => $event['total_tickets'] > 0 
            ? round((($event['total_tickets'] - $event['remaining_tickets']) / $event['total_tickets']) * 100, 1)
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
