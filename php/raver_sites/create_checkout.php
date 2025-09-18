<?php
// Start session to capture current user
require_once __DIR__ . '/../includes/auth_check.php';
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
$paymentMethod = strtolower($body['payment_method'] ?? 'stripe');

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
$sql = "SELECT ticket_type_id, ticket_type, price, remaining_tickets FROM ticket_types WHERE event_id = ? AND ticket_type_id IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lineItems = [];
$hufFactor = 100;
$totalAmountHuf = 0;
$effectiveRequested = [];
foreach ($tickets as $t) {
    $ttid = (int)$t['ticket_type_id'];
    if (!isset($requested[$ttid])) continue;
    $reqQty = (int)$requested[$ttid];
    $remaining = isset($t['remaining_tickets']) ? (int)$t['remaining_tickets'] : 0;
    $cap = max(0, min(5, $remaining));
    $qty = min($reqQty, $cap);
    if ($qty <= 0) continue;
    $effectiveRequested[$ttid] = $qty;
    $price = (int)$t['price'];
    if ($price <= 0) continue;
    $name = strtoupper((string)$t['ticket_type']) . ' TICKET';
    $totalAmountHuf += $price * $qty;
    $lineItems[] = [
        'price_data' => [
            'currency' => 'huf',
            'product_data' => [
                'name' => $name,
            ],
            'unit_amount' => $price * $hufFactor,
        ],
        'quantity' => $qty,
    ];
}

if (count($lineItems) === 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No available tickets for the selected types.']);
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
    'effective_requested' => $effectiveRequested,
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
$successUrl = sprintf('%s://%s%s/index.php?payment=success&session_id={CHECKOUT_SESSION_ID}%s', $scheme, $host, $basePath, $eventId ? ('&event_id=' . urlencode((string)$eventId)) : '');
$cancelUrl = sprintf('http://localhost/Diplomamunka-26222041/php/raver_sites/ticket_cart.php?event_id=' . urlencode((string)$eventId));

try {
    if ($paymentMethod === 'paypal') {
        // Build PayPal order for total amount in HUF
        $clientId = $_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID');
        $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? getenv('PAYPAL_CLIENT_SECRET');
        if (!$clientId || !$clientSecret) {
            throw new Exception('PayPal credentials not configured');
        }

        $env = new \PayPalCheckoutSdk\Core\SandboxEnvironment($clientId, $clientSecret);
        $client = new \PayPalCheckoutSdk\Core\PayPalHttpClient($env);

        $orderRequest = new \PayPalCheckoutSdk\Orders\OrdersCreateRequest();
        $orderRequest->prefer('return=representation');
        $orderRequest->body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => 'HUF',
                    'value' => number_format((float)$totalAmountHuf, 2, '.', ''),
                ],
                'description' => 'Tickets purchase',
            ]],
            'application_context' => [
                // PayPal will append token=<ORDER_ID> to return_url automatically
                'return_url' => sprintf('%s://%s%s/index.php?payment=paypal_success%s', $scheme, $host, $basePath, $eventId ? ('&event_id=' . urlencode((string)$eventId)) : ''),
                'cancel_url' => sprintf('%s://%s%s/raver_sites/ticket_cart.php%s', $scheme, $host, $basePath, $eventId ? ('?event_id=' . urlencode((string)$eventId)) : ''),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = $client->execute($orderRequest);
        if ($response->statusCode >= 200 && $response->statusCode < 300) {
            $order = $response->result;
            $approveUrl = null;
            foreach ($order->links as $lnk) {
                if ($lnk->rel === 'approve') { $approveUrl = $lnk->href; break; }
            }
            if (!$approveUrl) { throw new Exception('PayPal approval link not found'); }

            // Persist pending session details for PayPal keyed by order id
            $pendingDir = __DIR__ . '/../logs/pending_sessions';
            if (!is_dir($pendingDir)) { @mkdir($pendingDir, 0777, true); }
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $pendingData = [
                'time' => date('Y-m-d H:i:s'),
                'order_id' => $order->id,
                'user_id' => $userId,
                'event_id' => (int)$eventId,
                'items' => array_map(function($t) use ($effectiveRequested) {
                    return [
                        'ticket_type_id' => (int)$t['ticket_type_id'],
                        'ticket_type' => (string)$t['ticket_type'],
                        'price_huf' => (int)$t['price'],
                        'quantity' => (int)($effectiveRequested[(int)$t['ticket_type_id']] ?? 0),
                    ];
                }, $tickets),
            ];
            @file_put_contents($pendingDir . '/' . $order->id . '.json', json_encode($pendingData, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

            header('Content-Type: application/json');
            echo json_encode(['url' => $approveUrl]);
            exit;
        } else {
            throw new Exception('PayPal order creation failed');
        }
    } else {
        // Stripe default path
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'line_items' => $lineItems,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        // Persist pending session details for post-payment ticket generation
        $pendingDir = __DIR__ . '/../logs/pending_sessions';
        if (!is_dir($pendingDir)) { @mkdir($pendingDir, 0777, true); }
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $pendingData = [
            'time' => date('Y-m-d H:i:s'),
            'session_id' => $session->id,
            'user_id' => $userId,
            'event_id' => (int)$eventId,
            // Save trusted items with price and quantity per ticket_type_id
            'items' => array_map(function($t) use ($effectiveRequested) {
                return [
                    'ticket_type_id' => (int)$t['ticket_type_id'],
                    'ticket_type' => (string)$t['ticket_type'],
                    'price_huf' => (int)$t['price'],
                    'quantity' => (int)($effectiveRequested[(int)$t['ticket_type_id']] ?? 0),
                ];
            }, $tickets),
        ];
        @file_put_contents($pendingDir . '/' . $session->id . '.json', json_encode($pendingData, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

        header('Content-Type: application/json');
        echo json_encode(['url' => $session->url]);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
