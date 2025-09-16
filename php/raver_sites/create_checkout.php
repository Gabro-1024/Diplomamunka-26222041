<?php
// Composer autoload from project root
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables from project root
$projectRoot = realpath(__DIR__ . '/../../');
if ($projectRoot && file_exists($projectRoot . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
    $dotenv->load();
}

// Stripe secret key setup
$secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');
if (!$secretKey) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Stripe secret key not configured']);
    exit;
}
\Stripe\Stripe::setApiKey($secretKey);

// Read POST body (cart data)
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$items = $body['items'] ?? [];
$eventId = $body['event_id'] ?? null;

if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No items provided for checkout']);
    exit;
}

if (!$eventId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing event_id']);
    exit;
}

// Load DB and validate items from server-side to prevent tampering
require_once __DIR__ . '/../includes/db_connect.php';
try {
    $pdo = db_connect();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

// Map requested quantities by ticket_type_id
$requested = [];
foreach ($items as $it) {
    if (!isset($it['ticket_type_id'])) continue;
    $ttid = (int)$it['ticket_type_id'];
    $qty = isset($it['quantity']) ? (int)$it['quantity'] : 0;
    if ($ttid > 0 && $qty > 0) {
        $requested[$ttid] = ($requested[$ttid] ?? 0) + $qty;
    }
}

if (empty($requested)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No valid ticket selections']);
    exit;
}

// Fetch ticket info from DB
$placeholders = implode(',', array_fill(0, count($requested), '?'));
$params = array_merge([(int)$eventId], array_map('intval', array_keys($requested)));
$sql = "SELECT ticket_type_id, ticket_type, price FROM ticket_types WHERE event_id = ? AND ticket_type_id IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build Stripe line items using trusted DB data
$lineItems = [];
// Some Stripe accounts/API versions display HUF with 2 decimals. If that applies,
// set factor to 100 so that 5990 HUF becomes 599000 "minor units".
$hufFactor = 100; // adjust if your Stripe account treats HUF as zero-decimal
$totalAmountHuf = 0; // real HUF total for validations
foreach ($tickets as $t) {
    $ttid = (int)$t['ticket_type_id'];
    if (!isset($requested[$ttid])) continue;
    $qty = (int)$requested[$ttid];
    if ($qty <= 0) continue;
    $price = (int)$t['price']; // stored in HUF (major unit)
    if ($price <= 0) continue;
    $name = strtoupper((string)$t['ticket_type']) . ' TICKET';
    $totalAmountHuf += $price * $qty;
    $lineItems[] = [
        'price_data' => [
            'currency' => 'huf',
            'product_data' => [
                'name' => $name,
            ],
            // Send amount in units Stripe expects for your account
            'unit_amount' => $price * $hufFactor,
        ],
        'quantity' => $qty,
    ];
}

if (count($lineItems) === 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid items for checkout']);
    exit;
}

// Stripe minimum for HUF is 175 Ft (check against real HUF total)
if ($totalAmountHuf < 175) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'A Stripe fizetés minimális összege 175 Ft. Kérjük, adjon a kosárhoz több jegyet.']);
    exit;
}

// Debug logging
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
$debugLog = $logDir . '/stripe_debug.log';
$now = date('Y-m-d H:i:s');
$debugPayload = [
    'time' => $now,
    'event_id' => $eventId,
    'request_body' => $body,
    'requested' => $requested,
    'db_tickets' => $tickets,
    'line_items' => $lineItems,
    'computed_total_huf' => $totalAmountHuf,
    'huf_factor' => $hufFactor,
];
@file_put_contents($debugLog, json_encode($debugPayload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// Build success and cancel URLs
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = '/Diplomamunka-26222041/php';
$successUrl = sprintf('http://localhost:63342/Diplomamunka-26222041/php/index.php?payment=success&session_id={CHECKOUT_SESSION_ID}', $scheme, $host, $basePath);
$cancelUrl = sprintf('%s://%s%s/raver_sites/ticket_cart.php%s', $scheme, $host, $basePath, $eventId ? ('?event_id=' . urlencode((string)$eventId)) : '');

try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'mode' => 'payment',
        'line_items' => $lineItems,
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
    ]);

    header('Content-Type: application/json');
    echo json_encode(['url' => $session->url]);
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
