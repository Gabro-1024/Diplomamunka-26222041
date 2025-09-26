<?php
require_once('../includes/db_connect.php');
require_once('../includes/auth_check.php');

// Check if user is logged in and is a worker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header('Location: ../sign-in.php');
    exit();
}

try {
    $conn = db_connect();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$messageType = '';

// Get recent used tickets from database
$recentScans = [];
try {
    $stmt = $conn->query("SELECT t.*, e.name AS event_name, 'Standard' AS type_name 
                         FROM tickets t 
                         JOIN events e ON t.event_id = e.id 
                         WHERE t.is_used = 1 
                         ORDER BY t.purchase_id DESC 
                         LIMIT 10");
    $recentScans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching recent scans: " . $e->getMessage());
}

// Handle ticket validation if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_code'])) {
    $ticketData = json_decode(trim($_POST['ticket_code']), true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($ticketData['c'])) {
        $message = "Invalid QR code format. Please scan a valid ticket QR code.";
        $messageType = 'danger';
    } else {
        $ticketCode = $ticketData['c'];
        $userId = $ticketData['uid'] ?? null;
        $eventId = $ticketData['eid'] ?? null;
        
        // Validate ticket in database by matching the code in qr_code_path
        $searchPattern = '%' . $ticketCode . '%';
        $sql = "SELECT t.*, e.name AS event_name, 'Standard' AS type_name 
                FROM tickets t 
                JOIN events e ON t.event_id = e.id
                WHERE t.qr_code_path LIKE :searchPattern 
                AND t.is_used != 1";
        
        $params = [':searchPattern' => $searchPattern];
        
        if ($userId !== null) {
            $sql .= " AND t.owner_id = :userId";
            $params[':userId'] = $userId;
        }
        
        if ($eventId !== null) {
            $sql .= " AND t.event_id = :eventId";
            $params[':eventId'] = $eventId;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ticket) {
            // Mark ticket as used
            $updateStmt = $conn->prepare("UPDATE tickets SET is_used = 1 WHERE id = :ticketId");
            $updateStmt->bindParam(':ticketId', $ticket['id'], PDO::PARAM_INT);
            $updateStmt->execute();
            
            // Ticket is already updated in the database with is_used = 1
            // The next page load will show it in recent scans
            
            $message = "Ticket validated successfully! - " . htmlspecialchars($ticket['type_name']) . " for " . htmlspecialchars($ticket['event_name']);
            $messageType = 'success';
        } else {
            $message = "Invalid or already used ticket code";
            $messageType = 'danger';
        }
        
        // Close the cursor to free up the connection
        $stmt->closeCursor();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Scanner - Tickets @ Gábor</title>
    <link rel="icon" href="../../assets/images/logos/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .scanner-container {
            max-width: 600px;
            margin: 0 auto;
            border: 3px solid #2210FF;
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        #reader {
            width: 100%;
            height: auto;
        }
        .btn-accent-blue {
            background-color: #2210FF;
            color: white;
            border: none;
        }
        .btn-accent-blue:hover {
            background-color: #1a0dc7;
            color: white;
        }
        .ticket-card {
            border-left: 4px solid #2210FF;
        }
        .nav-tabs .nav-link.active {
            color: #2210FF;
            border-color: #2210FF #2210FF #f8f9fa;
        }
        .nav-tabs .nav-link {
            color: #6c757d;
        }
    </style>
</head>

<body class="bg-light">
  <!-- Main Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
      <a class="navbar-brand" href="#">
        <img src="../../assets/images/logos/logo-dark.svg" alt="Studiova" height="30">
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item">
            <a class="nav-link active" href="#">Ticket Scanner</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#recent-scans">Recent Scans</a>
          </li>
        </ul>
        <div class="d-flex align-items-center">
          <span class="text-light me-3">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Worker'); ?></span>
          <a href="../logout.php" class="btn btn-outline-light btn-sm">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="container py-4">
    <?php if ($message): ?>
      <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show mb-4" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>Scan Ticket</h5>
          </div>
          <div class="card-body text-center p-4">
            <div id="reader" class="scanner-container mb-4"></div>
            <p class="text-muted mb-3">- OR -</p>
            <form method="POST" class="mb-0">
              <div class="input-group">
                <input type="text" name="ticket_code" class="form-control form-control-lg" 
                      placeholder="Enter ticket code manually" required>
                <button class="btn btn-accent-blue px-4" type="submit">
                  <i class="fas fa-check me-2"></i>Validate
                </button>
              </div>
            </form>
          </div>
        </div>

        <div class="card shadow-sm" id="recent-scans">
          <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Scans</h5>
          </div>
          <div class="card-body p-0">
            <div id="scan-history" class="list-group list-group-flush">
              <?php if (!empty($recentScans)): ?>
                <?php foreach ($recentScans as $scan): ?>
                  <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <h6 class="mb-1"><?php echo htmlspecialchars($scan['type_name']); ?></h6>
                        <small class="text-muted"><?php echo htmlspecialchars($scan['event_name']); ?></small>
                      </div>
                      <div class="text-end">
                        <div class="text-success small">
                          <i class="fas fa-check-circle me-1"></i> Validated
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="list-group-item">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="mb-1">No recent scans</h6>
                      <small class="text-muted">No tickets have been validated yet</small>
                    </div>
                    <span class="badge bg-secondary">No data</span>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
      <p class="mb-0">&copy; <?php echo date('Y'); ?> Tickets @ Gábor. All rights reserved.</p>
    </div>
  </footer>

  <!-- Required Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/html5-qrcode@2.3.4/html5-qrcode.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize QR Code Scanner
      function onScanSuccess(decodedText, decodedResult) {
        // Stop the scanner
        html5QrCode.stop().then(ignore => {
          // QR Code scanning is stopped
        }).catch(err => {
          // Handle error, if any
          console.error('Error stopping scanner:', err);
        });
        
        // Show loading state
        const scanHistory = document.getElementById('scan-history');
        scanHistory.innerHTML = `
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
                <div>
                  <h6 class="mb-0">Validating ticket...</h6>
                  <small class="text-muted">Please wait</small>
                </div>
              </div>
              <span class="badge bg-info">Processing</span>
            </div>
          </div>
        `;
        
        // Submit the form with the scanned code
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ticket_code';
        input.value = decodedText;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
      }

      function onScanFailure(error) {
        // Handle scan failure
        console.error('QR Code scan failed:', error);
      }

      // Initialize the QR code scanner
      let html5QrCode;
      const scannerElement = document.getElementById('reader');
      
      if (scannerElement) {
        html5QrCode = new Html5Qrcode("reader");
        const config = { 
          fps: 10, 
          qrbox: { width: 250, height: 250 },
          aspectRatio: 1.0
        };

        // Start the scanner
        html5QrCode.start(
          { facingMode: "environment" },
          config,
          onScanSuccess,
          onScanFailure
        ).catch(err => {
          console.error('Error starting scanner:', err);
          // Fallback to file input if camera access is denied
          document.getElementById('manual-entry-form').classList.remove('d-none');
        });
      }

      // Clean up scanner when leaving the page
      window.addEventListener('beforeunload', function() {
        if (html5QrCode && html5QrCode.isScanning) {
          html5QrCode.stop().catch(console.error);
        }
      });

      // Toggle manual entry form
      const manualEntryBtn = document.getElementById('manual-entry-btn');
      const manualEntryForm = document.getElementById('manual-entry-form');
      
      if (manualEntryBtn && manualEntryForm) {
        manualEntryBtn.addEventListener('click', function() {
          manualEntryForm.classList.toggle('d-none');
          this.textContent = this.textContent.includes('Show') ? 'Hide Manual Entry' : 'Enter Code Manually';
        });
      }
    });
  </script>
</body>
</html>