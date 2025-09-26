<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;

// Periodic cleanup: remove pending session JSON files older than 24 hours on every page load
$__pendingSweepDir = __DIR__ . '/logs/pending_sessions';
if (is_dir($__pendingSweepDir)) {
    $__nowTs = time();
    $__maxAgeSeconds = 86400; // 24h
    foreach ((array)glob($__pendingSweepDir . '/*.json') as $__pf) {
        $__mt = @filemtime($__pf);
        if ($__mt !== false && ($__nowTs - $__mt) > $__maxAgeSeconds) {
            @unlink($__pf);
        }
    }
}

if (isset($_GET['payment'])) {
    $paymentType = $_GET['payment'];
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
    $purchaseLog = $logDir . '/purchases.log';

    // Ensure helpers are available
    $emailHelper = __DIR__ . '/includes/send_email.php';
    if (is_file($emailHelper)) {
        require_once $emailHelper;
    }

    // PayPal success
    if ($paymentType === 'paypal_success' && !empty($_GET['token'])) {
        $orderId = $_GET['token'];
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            $projectRoot = realpath(__DIR__ . '/..');
            if ($projectRoot && file_exists($projectRoot . '/.env')) {
                $dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
                $dotenv->load();
            }

            // Setup PayPal client
            $clientId = $_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID');
            $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? getenv('PAYPAL_CLIENT_SECRET');
            if (!$clientId || !$clientSecret) { throw new Exception('PayPal credentials missing'); }
            $env = new \PayPalCheckoutSdk\Core\SandboxEnvironment($clientId, $clientSecret);
            $client = new \PayPalCheckoutSdk\Core\PayPalHttpClient($env);

            // Capture the order
            $captureReq = new \PayPalCheckoutSdk\Orders\OrdersCaptureRequest($orderId);
            $captureReq->prefer('return=representation');
            $captureRes = $client->execute($captureReq);
            $paid = ($captureRes->statusCode >= 200 && $captureRes->statusCode < 300 && isset($captureRes->result) && strtolower($captureRes->result->status ?? '') === 'completed');

            // Amount and currency
            $amountMajor = 0.00;
            $currency = 'huf';
            if (isset($captureRes->result->purchase_units[0]->payments->captures[0])) {
                $cap = $captureRes->result->purchase_units[0]->payments->captures[0];
                $amountMajor = (float)($cap->amount->value ?? 0);
                $currency = strtolower($cap->amount->currency_code ?? 'HUF');
            }
            $status = $paid ? 'completed' : 'failed';

            // Insert into purchases
            require_once __DIR__ . '/includes/db_connect.php';
            $pdo = db_connect();
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            if (!$userId) { throw new Exception('User not authenticated for purchase record'); }

            if (!isset($_SESSION['recorded_paypal'])) { $_SESSION['recorded_paypal'] = []; }
            $purchaseId = null;
            if (isset($_SESSION['recorded_paypal'][$orderId])) {
                // try to re-use last purchase id
                if (isset($_SESSION['last_purchase_id'])) {
                    $purchaseId = (int)$_SESSION['last_purchase_id'];
                } else {
                    $find = $pdo->prepare('SELECT id FROM purchases WHERE user_id = ? AND payment_method = ? ORDER BY id DESC LIMIT 1');
                    $find->execute([$userId, 'paypal']);
                    $row = $find->fetch(PDO::FETCH_ASSOC);
                    if ($row) { $purchaseId = (int)$row['id']; }
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO purchases (user_id, amount, status, payment_method) VALUES (?, ?, ?, ?)');
                $stmt->execute([$userId, $amountMajor, $status, 'paypal']);
                $_SESSION['recorded_paypal'][$orderId] = true;
                $purchaseId = (int)$pdo->lastInsertId();
                $_SESSION['last_purchase_id'] = $purchaseId;
            }

            // If paid, generate tickets from pending order payload
            if ($paid) {
                if (!isset($_SESSION['ticketed_paypal'])) { $_SESSION['ticketed_paypal'] = []; }
                if (!isset($_SESSION['ticketed_paypal'][$orderId])) {
                    $pendingDir = __DIR__ . '/logs/pending_sessions';
                    $pendingFile = $pendingDir . '/' . $orderId . '.json';
                    if (is_file($pendingFile)) {
                        $pendingJson = json_decode(file_get_contents($pendingFile), true);
                        if (json_last_error() === JSON_ERROR_NONE && !empty($pendingJson)) {
                            $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : (int)($pendingJson['event_id'] ?? 0);
                            $items = $pendingJson['items'] ?? [];
                            if ($eventId > 0 && !empty($items)) {
                                $qrDir = __DIR__ . '/worker_sites/qrcodes';
                                if (!is_dir($qrDir)) { @mkdir($qrDir, 0777, true); }

                                $pdo->beginTransaction();
                                try {
                                    $insertStmt = $pdo->prepare('INSERT INTO tickets (qr_code_path, event_id, owner_id, is_used, price, purchase_id) VALUES (?, ?, ?, 0, ?, ?)');
                                    $generatedFiles = [];
                                    foreach ($items as $it) {
                                        $qty = (int)($it['quantity'] ?? 0);
                                        $priceHuf = (int)($it['price_huf'] ?? 0);
                                        if ($qty <= 0 || $priceHuf <= 0) { continue; }
                                        for ($i = 0; $i < $qty; $i++) {
                                            $code = bin2hex(random_bytes(16));
                                            $qrPathRel = 'worker_sites/qrcodes/' . $orderId . '_' . $code . '.png';
                                            $qrPathAbs = __DIR__ . '/' . $qrPathRel;

                                            $qrPayload = json_encode([
                                                'oid' => $orderId,
                                                'uid' => $userId,
                                                'eid' => $eventId,
                                                'c'   => $code,
                                                'ts'  => time(),
                                            ], JSON_UNESCAPED_SLASHES);
                                            // Generate QR with endroid/qr-code
                                            try {
                                                // First try with high error correction and nice styling
                                                $builder = new Builder(
                                                    writer: new PngWriter(),
                                                    data: $qrPayload,
                                                    encoding: new Encoding('UTF-8'),
                                                    errorCorrectionLevel: ErrorCorrectionLevel::High,
                                                    size: 300,
                                                    margin: 10,
                                                    roundBlockSizeMode: RoundBlockSizeMode::Margin
                                                );
                                                
                                                $qrResult = $builder->build();
                                                $qrResult->saveToFile($qrPathAbs);
                                                
                                                // Verify the file was created successfully
                                                if (!is_file($qrPathAbs) || filesize($qrPathAbs) === 0) {
                                                    throw new Exception('Empty QR file generated');
                                                }
                                            } catch (Throwable $e) {
                                                // Fallback to simpler QR if the first attempt fails
                                                try {
                                                    $fallbackBuilder = new Builder(
                                                        writer: new PngWriter(),
                                                        data: 'TICKET:' . $code, // Fallback to just the code if JSON fails
                                                        encoding: new Encoding('UTF-8'),
                                                        errorCorrectionLevel: ErrorCorrectionLevel::High,
                                                        size: 300,
                                                        margin: 10,
                                                        roundBlockSizeMode: RoundBlockSizeMode::Margin
                                                    );
                                                    $qrResult = $fallbackBuilder->build();
                                                    $qrResult->saveToFile($qrPathAbs);
                                                } catch (Throwable $e) {
                                                    // Last resort: create a text file with the code
                                                    @file_put_contents($qrPathAbs, "QR Generation Failed. Code: " . $code);
                                                }
                                            }

                                            $priceDecimal = number_format($priceHuf, 2, '.', '');
                                            $insertStmt->execute([$qrPathRel, $eventId, $userId, $priceDecimal, $purchaseId]);
                                            $generatedFiles[] = $qrPathRel;
                                        }
                                    }
                                    // Decrement remaining_tickets per ticket_type in this purchase (PayPal)
                                    if (!empty($items)) {
                                        // Aggregate quantities by ticket_type_id
                                        $toDecrement = [];
                                        foreach ($items as $it) {
                                            $ttid = isset($it['ticket_type_id']) ? (int)$it['ticket_type_id'] : 0;
                                            $qty  = isset($it['quantity']) ? (int)$it['quantity'] : 0;
                                            if ($ttid > 0 && $qty > 0) {
                                                $toDecrement[$ttid] = ($toDecrement[$ttid] ?? 0) + $qty;
                                            }
                                        }
                                        if (!empty($toDecrement)) {
                                            $decStmt = $pdo->prepare('UPDATE ticket_types SET remaining_tickets = GREATEST(remaining_tickets - ?, 0) WHERE ticket_type_id = ? AND event_id = ?');
                                            foreach ($toDecrement as $ttid => $qty) {
                                                $decStmt->execute([$qty, $ttid, $eventId]);
                                            }
                                        }
                                    }
                                    $pdo->commit();
                                    $_SESSION['ticketed_paypal'][$orderId] = true;
                                    @unlink($pendingFile);

                                    // Send tickets via email
                                    if (!empty($generatedFiles)) {
                                        // Prefer PayPal payer email if available, otherwise fallback to session email
                                        $buyerEmail = null;
                                        if (isset($captureRes->result) && isset($captureRes->result->payer) && isset($captureRes->result->payer->email_address)) {
                                            $buyerEmail = (string) $captureRes->result->payer->email_address;
                                        }
                                        if (!$buyerEmail) { $buyerEmail = $_SESSION['email'] ?? null; }
                                        if ($buyerEmail && empty($_SESSION['email'])) { $_SESSION['email'] = $buyerEmail; }

                                        if ($buyerEmail) {
                                            $meta = [
                                                'purchase_id' => $purchaseId,
                                                'amount' => $amountMajor,
                                                'currency' => $currency,
                                            ];
                                            try { @sendTicketsEmail($buyerEmail, '', $generatedFiles, $meta); } catch (Throwable $e) {}
                                        }
                                    }
                                } catch (Throwable $txe) {
                                    $pdo->rollBack();
                                    @file_put_contents($purchaseLog, json_encode([
                                        'time' => date('Y-m-d H:i:s'),
                                        'order_id' => $orderId,
                                        'error' => 'PayPal ticket insert failed: ' . $txe->getMessage()
                                    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
                                }
                            }
                        }
                    }
                }
            }

            // Ensure pending file is removed even if no tickets were generated (PayPal)
            $pf = __DIR__ . '/logs/pending_sessions/' . $orderId . '.json';
            if (is_file($pf)) { @unlink($pf); }

            // Stripe success
            $_SESSION['payment_flash'] = [
                'type' => $paid ? 'success' : 'warning',
                'title' => $paid ? 'Payment successful (PayPal)' : 'Payment not completed (PayPal)',
                'message' => $paid ? 'Your payment was completed successfully.' : 'Your payment did not complete. You can try again.',
                'amount' => $amountMajor,
                'currency' => strtoupper($currency),
            ];
        } catch (Throwable $e) {
            @file_put_contents($purchaseLog, json_encode([
                'time' => date('Y-m-d H:i:s'),
                'order_id' => $orderId ?? null,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
        }
    }

    // Stripe success: index.php?payment=success&session_id=...
    if ($paymentType === 'success' && !empty($_GET['session_id'])) {
        $sessionId = $_GET['session_id'];
        try {
            // Load Composer and env
            require_once __DIR__ . '/../vendor/autoload.php';
            $projectRoot = realpath(__DIR__ . '/..');
            if ($projectRoot && file_exists($projectRoot . '/.env')) {
                $dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
                $dotenv->load();
            }

            // Stripe session
            $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');
            if (!$secretKey) { throw new Exception('Stripe key missing'); }
            \Stripe\Stripe::setApiKey($secretKey);
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            $paid = ($session->payment_status === 'paid');
            $currency = strtolower($session->currency);
            $amountTotalMinor = (int)($session->amount_total ?? 0);
            $divisor = ($currency === 'huf') ? 100 : 100;
            $amountMajor = $amountTotalMinor / $divisor;
            $status = $paid ? 'completed' : 'failed';

            // Insert into purchases
            require_once __DIR__ . '/includes/db_connect.php';
            $pdo = db_connect();
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            if (!$userId) { throw new Exception('User not authenticated for purchase record'); }

            if (!isset($_SESSION['recorded_sessions'])) { $_SESSION['recorded_sessions'] = []; }
            $purchaseId = null;
            if (isset($_SESSION['recorded_sessions'][$sessionId])) {
                if (isset($_SESSION['last_purchase_id'])) {
                    $purchaseId = (int)$_SESSION['last_purchase_id'];
                } else {
                    $find = $pdo->prepare('SELECT id FROM purchases WHERE user_id = ? AND payment_method = ? ORDER BY id DESC LIMIT 1');
                    $find->execute([$userId, 'stripe']);
                    $row = $find->fetch(PDO::FETCH_ASSOC);
                    if ($row) { $purchaseId = (int)$row['id']; }
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO purchases (user_id, amount, status, payment_method) VALUES (?, ?, ?, ?)');
                $stmt->execute([$userId, $amountMajor, $status, 'stripe']);
                $_SESSION['recorded_sessions'][$sessionId] = true;
                $purchaseId = (int)$pdo->lastInsertId();
                $_SESSION['last_purchase_id'] = $purchaseId;
            }

            // Generate tickets from pending session
            if ($paid) {
                if (!isset($_SESSION['ticketed_sessions'])) { $_SESSION['ticketed_sessions'] = []; }
                if (!isset($_SESSION['ticketed_sessions'][$sessionId])) {
                    $pendingDir = __DIR__ . '/logs/pending_sessions';
                    $pendingFile = $pendingDir . '/' . $sessionId . '.json';
                    if (is_file($pendingFile)) {
                        $pendingJson = json_decode(file_get_contents($pendingFile), true);
                        if (json_last_error() === JSON_ERROR_NONE && !empty($pendingJson)) {
                            $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : (int)($pendingJson['event_id'] ?? 0);
                            $items = $pendingJson['items'] ?? [];
                            if ($eventId > 0 && !empty($items)) {
                                $qrDir = __DIR__ . '/worker_sites/qrcodes';
                                if (!is_dir($qrDir)) { @mkdir($qrDir, 0777, true); }

                                $pdo->beginTransaction();
                                try {
                                    $insertStmt = $pdo->prepare('INSERT INTO tickets (qr_code_path, event_id, owner_id, is_used, price, purchase_id) VALUES (?, ?, ?, 0, ?, ?)');
                                    $generatedFiles = [];
                                    foreach ($items as $it) {
                                        $qty = (int)($it['quantity'] ?? 0);
                                        $priceHuf = (int)($it['price_huf'] ?? 0);
                                        if ($qty <= 0 || $priceHuf <= 0) { continue; }
                                        for ($i = 0; $i < $qty; $i++) {
                                            $code = bin2hex(random_bytes(16));
                                            $qrPathRel = 'worker_sites/qrcodes/' . $sessionId . '_' . $code . '.png';
                                            $qrPathAbs = __DIR__ . '/' . $qrPathRel;

                                            $qrPayload = json_encode([
                                                'sid' => $sessionId,
                                                'uid' => $userId,
                                                'eid' => $eventId,
                                                'c'   => $code,
                                                'ts'  => time(),
                                            ], JSON_UNESCAPED_SLASHES);
                                            // Generate QR with endroid/qr-code
                                            try {
                                                // First try with high error correction and nice styling
                                                $builder = new Builder(
                                                    writer: new PngWriter(),
                                                    data: $qrPayload,
                                                    encoding: new Encoding('UTF-8'),
                                                    errorCorrectionLevel: ErrorCorrectionLevel::High,
                                                    size: 300,
                                                    margin: 10,
                                                    roundBlockSizeMode: RoundBlockSizeMode::Margin
                                                );

                                                $qrResult = $builder->build();
                                                $qrResult->saveToFile($qrPathAbs);

                                                // Verify the file was created successfully
                                                if (!is_file($qrPathAbs) || filesize($qrPathAbs) === 0) {
                                                    throw new Exception('Empty QR file generated');
                                                }
                                            } catch (Throwable $e) {
                                                // Fallback to simpler QR if the first attempt fails
                                                try {
                                                    $fallbackBuilder = new Builder(
                                                        writer: new PngWriter(),
                                                        data: 'TICKET:' . $code, // Fallback to just the code if JSON fails
                                                        encoding: new Encoding('UTF-8'),
                                                        errorCorrectionLevel: ErrorCorrectionLevel::High,
                                                        size: 300,
                                                        margin: 10,
                                                        roundBlockSizeMode: RoundBlockSizeMode::Margin
                                                    );
                                                    $qrResult = $fallbackBuilder->build();
                                                    $qrResult->saveToFile($qrPathAbs);
                                                } catch (Throwable $e) {
                                                    // Last resort: create a text file with the code
                                                    @file_put_contents($qrPathAbs, "QR Generation Failed. Code: " . $code);
                                                }
                                            }

                                            $priceDecimal = number_format($priceHuf, 2, '.', '');
                                            $insertStmt->execute([$qrPathRel, $eventId, $userId, $priceDecimal, $purchaseId]);
                                            $generatedFiles[] = $qrPathRel;
                                        }
                                    }
                                    // Decrement remaining_tickets per ticket_type in this purchase (Stripe)
                                    if (!empty($items)) {
                                        // Aggregate quantities by ticket_type_id
                                        $toDecrement = [];
                                        foreach ($items as $it) {
                                            $ttid = isset($it['ticket_type_id']) ? (int)$it['ticket_type_id'] : 0;
                                            $qty  = isset($it['quantity']) ? (int)$it['quantity'] : 0;
                                            if ($ttid > 0 && $qty > 0) {
                                                $toDecrement[$ttid] = ($toDecrement[$ttid] ?? 0) + $qty;
                                            }
                                        }
                                        if (!empty($toDecrement)) {
                                            $decStmt = $pdo->prepare('UPDATE ticket_types SET remaining_tickets = GREATEST(remaining_tickets - ?, 0) WHERE ticket_type_id = ? AND event_id = ?');
                                            foreach ($toDecrement as $ttid => $qty) {
                                                $decStmt->execute([$qty, $ttid, $eventId]);
                                            }
                                        }
                                    }
                                    $pdo->commit();
                                    $_SESSION['ticketed_sessions'][$sessionId] = true;
                                    @unlink($pendingFile);

                                    // Send tickets via email
                                    if (!empty($generatedFiles)) {
                                        // Prefer Stripe session email if available, otherwise fallback to session email
                                        $buyerEmail = null;
                                        if (isset($session->customer_details) && isset($session->customer_details->email)) {
                                            $buyerEmail = (string) $session->customer_details->email;
                                        } elseif (isset($session->customer_email)) {
                                            $buyerEmail = (string) $session->customer_email;
                                        }
                                        if (!$buyerEmail) { $buyerEmail = $_SESSION['email'] ?? null; }
                                        if ($buyerEmail && empty($_SESSION['email'])) { $_SESSION['email'] = $buyerEmail; }

                                        if ($buyerEmail) {
                                            $meta = [
                                                'purchase_id' => $purchaseId,
                                                'amount' => $amountMajor,
                                                'currency' => $currency,
                                            ];
                                            try { @sendTicketsEmail($buyerEmail, '', $generatedFiles, $meta); } catch (Throwable $e) {}
                                        }
                                    }
                                } catch (Throwable $txe) {
                                    $pdo->rollBack();
                                    @file_put_contents($purchaseLog, json_encode([
                                        'time' => date('Y-m-d H:i:s'),
                                        'session_id' => $sessionId,
                                        'error' => 'Ticket insert failed: ' . $txe->getMessage()
                                    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
                                }
                            }
                        }
                    }
                }
            }

            // Ensure pending file is removed even if no tickets were generated (Stripe)
            $pf = __DIR__ . '/logs/pending_sessions/' . $sessionId . '.json';
            if (is_file($pf)) { @unlink($pf); }

            $_SESSION['payment_flash'] = [
                'type' => $paid ? 'success' : 'warning',
                'title' => $paid ? 'Payment successful (Stripe)' : 'Payment not completed (Stripe)',
                'message' => $paid ? 'Your payment was completed successfully.' : 'Your payment did not complete. You can try again.',
                'amount' => $amountMajor,
                'currency' => strtoupper($currency),
            ];
        } catch (Throwable $e) {
            @file_put_contents($purchaseLog, json_encode([
                'time' => date('Y-m-d H:i:s'),
                'session_id' => $sessionId ?? null,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tickets @ Gábor</title>
  <link rel="shortcut icon" type="image/png" href="../assets/images/logos/favicon.svg" />
  <link rel="stylesheet" href="http://localhost:63342/Diplomamunka-26222041/assets/libs/owl.carousel/dist/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="http://localhost:63342/Diplomamunka-26222041/assets/libs/aos-master/dist/aos.css">
  <link rel="stylesheet" href="http://localhost:63342/Diplomamunka-26222041/assets/css/styles.css" />
  <style>
    .payment-toast {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      z-index: 1055;
      max-width: 520px;
      width: 90%;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,.2);
      color: #111;
      padding: 20px 48px 20px 20px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }
    .payment-toast.success { background: #7cfc00; }
    .payment-toast.warning { background: #ffe08a; }
    .payment-toast .toast-title { font-weight: 700; margin-bottom: 4px; }
    .payment-toast .amount { font-weight: 600; }
    .payment-toast .toast-close {
      position: absolute;
      top: 10px; right: 10px;
      background: transparent; border: 0;
      font-size: 1.25rem; line-height: 1; cursor: pointer;
    }
  </style>
</head>
<body>

  <!-- Header -->
  <?php include 'header.php'; ?>

  <?php if (!empty($_SESSION['payment_flash'])): $flash = $_SESSION['payment_flash']; unset($_SESSION['payment_flash']); ?>
    <div id="paymentToast" class="payment-toast <?php echo $flash['type'] === 'success' ? 'success' : 'warning'; ?>" role="status" aria-live="polite">
      <div>
        <div class="toast-title"><?php echo htmlspecialchars($flash['title']); ?></div>
        <div>
          <?php echo htmlspecialchars($flash['message']); ?>
          <?php if (!empty($flash['amount'])): ?>
            <span class="amount ms-1">(<?php echo number_format((float)$flash['amount'], 2, '.', ' '); ?> Ft)</span>
          <?php endif; ?>
        </div>
      </div>
      <button class="toast-close" aria-label="Close" onclick="document.getElementById('paymentToast')?.remove();">&times;</button>
    </div>
    <script>
      setTimeout(() => { document.getElementById('paymentToast')?.remove(); }, 6000);
    </script>
  <?php endif; ?>

  <!--  Page Wrapper -->
  <div class="page-wrapper overflow-hidden">

    <!--  Banner Section -->
    <section class="banner-section position-relative d-flex align-items-end min-vh-100">
      <video class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover" autoplay muted loop playsinline>
        <source src="../assets/images/backgrounds/banner-video.mp4" type="video/mp4" />
      </video>
      <div class="container">
        <div class="d-flex flex-column gap-4 pb-8 position-relative z-1">
          <div class="row align-items-center">
            <div class="col-xl-4">
              <div class="d-flex align-items-center gap-4" data-aos="fade-up" data-aos-delay="100"
                data-aos-duration="1000">
                <img src="../assets/images/svgs/primary-leaf.svg" alt="" class="img-fluid animate-spin">
                  <p class="mb-0 text-white fs-5 text-opacity-70">Discover <span class="text-primary">
                    the best festival tickets</span> in one place - experiences you'll remember forever.</p>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-end gap-3" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
            <h1 class="mb-0 fs-16 text-white lh-1">Tickets @ Gábor</h1>
            <a href="about-us.php" class="p-1 ps-7 bg-primary rounded-pill">
              <span class="bg-white round-52 rounded-circle d-flex align-items-center justify-content-center">
                <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
              </span>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!--  Stats & Facts Section -->
<!--      --><?php //var_dump($_SESSION); ?>
    <section class="stats-facts py-5 py-lg-11 py-xl-12 position-relative overflow-hidden">
      <div class="container">
        <div class="row gap-7 gap-xl-0">
          <div class="col-xl-4 col-xxl-4">
            <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
              data-aos-duration="1000">
              <span
                class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">01</span>
              <hr class="border-line">
              <span class="badge badge-accent-blue">Statistics</span>
            </div>
          </div>
          <div class="col-xl-8 col-xxl-7">
            <div class="d-flex flex-column gap-9">
              <div class="row">
                <div class="col-xxl-8">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                    <h2 class="mb-0">Hungary's leading festival ticket seller.</h2>
                      <p class="fs-5 mb-0">Secure your spot at the biggest names and most unforgettable experiences
                          - with simple, fast and reliable ticket purchasing.</p>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 col-lg-4 mb-7 mb-lg-0">
                  <div class="d-flex flex-column gap-6 pt-9 border-top" data-aos="fade-up" data-aos-delay="200"
                    data-aos-duration="1000">
                    <h2 class="mb-0 fs-14"><span class="count" data-target="40">40</span>K+</h2>
                    <p class="mb-0">Festival tickets sold</p>
                  </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-7 mb-lg-0">
                  <div class="d-flex flex-column gap-6 pt-9 border-top" data-aos="fade-up" data-aos-delay="300"
                    data-aos-duration="1000">
                    <h2 class="mb-0 fs-14"><span class="count" data-target="25">25</span>+</h2>
                    <p class="mb-0">Partner festivals</p>
                  </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-7 mb-lg-0">
                  <div class="d-flex flex-column gap-6 pt-9 border-top" data-aos="fade-up" data-aos-delay="400"
                    data-aos-duration="1000">
                    <h2 class="mb-0 fs-14"><span class="count" data-target="99">99</span>%</h2>
                    <p class="mb-0">Satisfied customers</p>
                  </div>
                </div>
              </div>
              <a href="about-us.php" class="btn" data-aos="fade-up" data-aos-delay="500" data-aos-duration="1000" style="z-index: 1;">
                <span class="btn-text">More info about us</span>
                <iconify-icon icon="lucide:arrow-up-right"
                  class="btn-icon bg-white text-dark round-52 rounded-circle hstack justify-content-center fs-7 shadow-sm"></iconify-icon>
              </a>
            </div>
          </div>
        </div>
      </div>
      <div class="position-absolute bottom-0 start-0" data-aos="zoom-in" data-aos-delay="100" data-aos-duration="1000">
        <img src="../assets/images/backgrounds/stats-facts-bg.svg" alt="" class="img-fluid">
      </div>
    </section>

    <!--  Featured Projects Section -->
    <section class="featured-projects py-5 py-lg-11 py-xl-12 bg-light-gray">
      <div class="d-flex flex-column gap-5 gap-xl-11">
        <div class="container">
          <div class="row gap-7 gap-xl-0">
            <div class="col-xl-4 col-xxl-4">
              <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
                data-aos-duration="1000">
                <span
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">02</span>
                <hr class="border-line">
                <span class="badge badge-accent-blue">Events</span>
              </div>
            </div>
            <div class="col-xl-8 col-xxl-7">
              <div class="row">
                <div class="col-xxl-8">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                      <h2 class="mb-0">Our featured festivals</h2>
                      <p class="fs-5 mb-0">Discover our most popular festivals - the best music
                          festivals you've been waiting for.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="featured-projects-slider px-3">
          <div class="owl-carousel owl-theme">
            <div class="item">
              <div class="portfolio d-flex flex-column gap-6">
                <div class="portfolio-img position-relative overflow-hidden">
                  <img src="../assets/images/portfolio/portfolio-img-19.jpg" alt="" class="img-fluid">
                  <div class="portfolio-overlay">
                    <a href="projects-detail.html"
                      class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                      <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                    </a>
                  </div>
                </div>
                <div class="portfolio-details d-flex flex-column gap-3">
                  <h3 class="mb-0">Sziget Festival</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">August 7-13</span>
                    <span class="badge text-dark border">Budapest</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="item">
              <div class="portfolio d-flex flex-column gap-6">
                <div class="portfolio-img position-relative overflow-hidden">
                  <img src="../assets/images/portfolio/portfolio-img-2.jpg" alt="" class="img-fluid">
                  <div class="portfolio-overlay">
                    <a href="projects-detail.html"
                      class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                      <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                    </a>
                  </div>
                </div>
                <div class="portfolio-details d-flex flex-column gap-3">
                  <h3 class="mb-0">Balaton Sound</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">June 26-30</span>
                    <span class="badge text-dark border">Zamárdi</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="item">
              <div class="portfolio d-flex flex-column gap-6">
                <div class="portfolio-img position-relative overflow-hidden">
                  <img src="../assets/images/portfolio/portfolio-img-21.jpg" alt="" class="img-fluid">
                  <div class="portfolio-overlay">
                    <a href="projects-detail.html"
                      class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                      <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                    </a>
                  </div>
                </div>
                <div class="portfolio-details d-flex flex-column gap-3">
                  <h3 class="mb-0">VOLT Festival</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">June 19-23</span>
                    <span class="badge text-dark border">Sopron</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="item">
              <div class="portfolio d-flex flex-column gap-6">
                <div class="portfolio-img position-relative overflow-hidden">
                  <img src="../assets/images/portfolio/portfolio-img-20.jpg" alt="" class="img-fluid">
                  <div class="portfolio-overlay">
                    <a href="projects-detail.html"
                      class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                      <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                    </a>
                  </div>
                </div>
                <div class="portfolio-details d-flex flex-column gap-3">
                  <h3 class="mb-0">EFOTT</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">July 10-14</span>
                    <span class="badge text-dark border">Tapolca</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="item">
              <div class="portfolio d-flex flex-column gap-6">
                <div class="portfolio-img position-relative overflow-hidden">
                  <img src="../assets/images/portfolio/portfolio-img-22.jpg" alt="" class="img-fluid">
                  <div class="portfolio-overlay">
                    <a href="projects-detail.html"
                      class="position-absolute top-50 start-50 translate-middle bg-primary round-64 rounded-circle hstack justify-content-center">
                      <iconify-icon icon="lucide:arrow-up-right" class="fs-8 text-dark"></iconify-icon>
                    </a>
                  </div>
                </div>
                <div class="portfolio-details d-flex flex-column gap-3">
                  <h3 class="mb-0">SZIN Festival</h3>
                  <div class="hstack gap-2">
                    <span class="badge text-dark border">August 27-31</span>
                    <span class="badge text-dark border">Szeged</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!--  Why choose us Section -->
    <section class="why-choose-us py-5 py-lg-11 py-xl-12">
      <div class="container">
        <div class="row justify-content-between gap-5 gap-xl-0">
          <div class="col-xl-3 col-xxl-3">
            <div class="d-flex flex-column gap-7">
              <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
                data-aos-duration="1000">
                <span
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">03</span>
                <hr class="border-line">
                <span class="badge badge-accent-blue">About us</span>
              </div>
                <h2 class="mb-0" data-aos="fade-right" data-aos-delay="200" data-aos-duration="1000">Why choose us?</h2>
                <p class="mb-0 fs-5" data-aos="fade-right" data-aos-delay="300" data-aos-duration="1000">Guaranteed best
                    prices, instant delivery and excellent customer support when purchasing festival tickets.</p>
            </div>
          </div>
          <div class="col-xl-9 col-xxl-8">
            <div class="row">
              <div class="col-lg-4 mb-7 mb-lg-0">
                <div class="card position-relative overflow-hidden bg-primary h-100" data-aos="fade-up"
                  data-aos-delay="100" data-aos-duration="1000">
                  <div class="card-body d-flex flex-column justify-content-between">
                    <div class="d-flex flex-column gap-3 position-relative z-1">
                      <ul class="list-unstyled mb-0 hstack gap-1">
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-dark"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-dark"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-dark"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-dark"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-dark"></iconify-icon></a></li>
                      </ul>
                      <p class="mb-0 fs-6 text-dark">Fast and hassle-free ticket purchase with instant e-ticket delivery.</p>
                    </div>
                    <div class="position-relative z-1">
                      <div class="pb-6 border-bottom">
                        <h2 class="mb-0">99%</h2>
                        <p class="mb-0">Customer satisfaction</p>
                      </div>
                      <div class="hstack gap-6 pt-6">
                        <img src="../assets/images/profile/avatar-1.png" alt=""
                          class="img-fluid rounded-circle overflow-hidden flex-shrink-0" width="64" height="64">
                        <div>
                          <h5 class="mb-0">Kovács Eszter</h5>
                          <p class="mb-0">Budapest</p>
                        </div>
                      </div>
                    </div>
                    <div class="position-absolute bottom-0 end-0">
                      <img src="../assets/images/backgrounds/customer-satisfaction-bg.svg" alt="" class="img-fluid">
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4 mb-7 mb-lg-0">
                <div class="d-flex flex-column gap-7" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
                  <div class="position-relative">
                    <img src="../assets/images/services/services-img-2.jpg" alt="" class="img-fluid w-100">
                  </div>

                  <div class="card bg-dark">
                    <div class="card-body d-flex flex-column gap-7">
                      <div>
                        <h2 class="mb-0 text-white">25+</h2>
                        <p class="mb-0 text-white text-opacity-70">Partners</p>
                      </div>
                      <ul class="d-flex align-items-center mb-0">
                        <li>
                          <a href="javascript:void(0)">
                            <img src="../assets/images/profile/user-1.jpg" width="44" height="44"
                              class="rounded-circle border border-2 border-dark" alt="user-1">
                          </a>
                        </li>
                        <li class="ms-n2">
                          <a href="javascript:void(0)">
                            <img src="../assets/images/profile/user-2.jpg" width="44" height="44"
                              class="rounded-circle border border-2 border-dark" alt="user-2">
                          </a>
                        </li>
                        <li class="ms-n2">
                          <a href="javascript:void(0)">
                            <img src="../assets/images/profile/user-3.jpg" width="44" height="44"
                              class="rounded-circle border border-2 border-dark" alt="user-3">
                          </a>
                        </li>
                        <li class="ms-n2">
                          <a href="javascript:void(0)">
                            <img src="../assets/images/profile/user-4.jpg" width="44" height="44"
                              class="rounded-circle border border-2 border-dark" alt="user-4">
                          </a>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4 mb-7 mb-lg-0">
                <div class="card border h-100 position-relative overflow-hidden" data-aos="fade-up" data-aos-delay="300"
                  data-aos-duration="1000">
                  <span
                    class="border rounded-circle round-490 d-block position-absolute top-0 start-50 translate-middle"></span>
                  <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                      <h2 class="mb-0">0%</h2>
                      <p class="mb-0 text-dark">Transaction fee</p>
                    </div>
                    <div class="d-flex flex-column gap-3">
                      <a href="http://localhost:63342/Diplomamunka-26222041/php/index.php" class="logo-dark">
                        <img src="http://localhost:63342/Diplomamunka-26222041/assets/images/logos/logo-white.svg" alt="logo" class="img-fluid">
                      </a>
                      <p class="mb-0 fs-5 text-dark">No hidden costs - all ticket prices are final, without transaction fees.</p>
                    </div>
                  </div>
                  <span
                    class="border rounded-circle round-490 d-block position-absolute top-100 start-50 translate-middle"></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!--  Testimonial Section -->
    <section class="testimonial py-5 py-lg-11 py-xl-12 bg-light-gray">
      <div class="container">
        <div class="d-flex flex-column gap-5 gap-xl-11">
          <div class="row gap-7 gap-xl-0">
            <div class="col-xl-4 col-xxl-4">
              <div class="d-flex align-items-center gap-7 py-2" data-aos="fade-right" data-aos-delay="100"
                data-aos-duration="1000">
                <span
                  class="round-36 flex-shrink-0 text-dark rounded-circle bg-primary hstack justify-content-center fw-medium">04</span>
                <hr class="border-line bg-white">
                <span class="badge badge-accent-blue">Reviews</span>
              </div>
            </div>
            <div class="col-xl-8 col-xxl-7">
              <div class="row">
                <div class="col-xxl-8">
                  <div class="d-flex flex-column gap-6" data-aos="fade-up" data-aos-delay="100"
                    data-aos-duration="1000">
                      <h2 class="mb-0">Our customers' reviews</h2>
                      <p class="fs-5 mb-0 text-opacity-70">Read what those who have already purchased festival tickets
                          from us say about us.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row gap-7 gap-lg-0">
            <div class="col-lg-4 col-xl-3 d-flex align-items-stretch">
              <div class="card bg-primary w-100" data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000">
                <div class="card-body d-flex flex-column gap-5 gap-xl-11 justify-content-between">
                  <div class="d-flex flex-column gap-4">
                    <p class="mb-0">Hear from them</p>
                    <h4 class="mb-0">The ticket purchase was fast and seamless, I received my e-ticket immediately!</h4>
                  </div>
                  <div class="hstack gap-3">
                    <img src="../assets/images/testimonial/testimonial-1.jpg" alt=""
                      class="img-fluid rounded-circle overflow-hidden flex-shrink-0" width="60" height="60">
                    <div>
                      <h5 class="mb-1 fw-normal">Nagy Adrián</h5>
                      <p class="mb-0">Debrecen</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-xl-6 d-flex align-items-stretch">
              <div class="card bg-dark w-100" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
                <div class="card-body d-flex flex-column gap-5 gap-xl-11 justify-content-between">
                  <div class="d-flex flex-column gap-4">
                    <p class="mb-0 text-white text-opacity-70">Hear from them</p>
                    <h4 class="mb-0 text-white pe-xl-2">I found the best prices here,
                        and the customer service was excellent when I had a question.</h4>
                    <div class="hstack gap-2">
                      <ul class="list-unstyled mb-0 hstack gap-1">
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-white"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-white"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-white"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-white"></iconify-icon></a></li>
                        <li><a class="hstack" href="javascript:void(0)"><iconify-icon icon="solar:star-bold"
                              class="fs-6 text-white"></iconify-icon></a></li>
                      </ul>
                      <h6 class="mb-0 text-white fw-medium">5.0</h6>
                    </div>
                  </div>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="hstack gap-3">
                      <img src="../assets/images/testimonial/testimonial-2.jpg" alt=""
                        class="img-fluid rounded-circle overflow-hidden flex-shrink-0" width="60" height="60">
                      <div>
                        <h5 class="mb-1 fw-normal text-white">Horváth Béla</h5>
                        <p class="mb-0 text-white text-opacity-70">Szeged</p>
                      </div>
                    </div>
                    <span><img src="../assets/images/testimonial/quete.svg" alt="quete"
                        class="img-fluid flex-shrink-0"></span>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-xl-3 d-flex align-items-stretch">
              <div class="card w-100" data-aos="fade-up" data-aos-delay="300" data-aos-duration="1000">
                <div class="card-body d-flex flex-column gap-5 gap-xl-11 justify-content-between">
                  <div class="d-flex flex-column gap-4">
                    <p class="mb-0">Hear from them</p>
                    <h4 class="mb-0">I've purchased tickets here several times, everything was always perfect, highly recommend!</h4>
                  </div>
                  <div class="hstack gap-3">
                    <img src="../assets/images/testimonial/testimonial-3.jpg" alt=""
                      class="img-fluid rounded-circle overflow-hidden flex-shrink-0" width="60" height="60">
                    <div>
                      <h5 class="mb-1 fw-normal">Tóth Zsuzsa</h5>
                      <p class="mb-0">Pécs</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <!-- Footer section -->
  <?php include 'footer.php'; ?>

  <div class="get-template hstack gap-2">
    <button class="btn bg-primary p-2 round-52 rounded-circle hstack justify-content-center flex-shrink-0"
      id="scrollToTopBtn">
      <iconify-icon icon="lucide:arrow-up" class="fs-7 text-dark"></iconify-icon>
    </button>
  </div>

  <script src="../assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="../assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/libs/owl.carousel/dist/owl.carousel.min.js"></script>
  <script src="../assets/libs/aos-master/dist/aos.js"></script>
  <script src="../assets/js/custom.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
</body>
</html>