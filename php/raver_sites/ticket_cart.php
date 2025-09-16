<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Redirect to login if not authenticated
if (!isUserLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /Diplomamunka-26222041/php/sign-in.php');
    exit();
}

// Check if event ID is provided and valid
if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    $_SESSION['error'] = 'Invalid event specified.';
    header('Location: /Diplomamunka-26222041/php/festivals.php');
    exit();
}

$event_id = (int)$_GET['event_id'];

// Database connection
require_once '../includes/db_connect.php';

try {
    $pdo = db_connect();

    // Get event details
    $stmt = $pdo->prepare("SELECT id, name, start_date, end_date, cover_image FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception('Event not found');
    }

    // Get all ticket types for this event
    $stmt = $pdo->prepare("SELECT * FROM ticket_types WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $ticket_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
//    var_dump($ticket_types);
    $array_regular = $ticket_types[0];
    $array_vip = $ticket_types[1];
    
    if (empty($ticket_types)) {
        throw new Exception('No ticket types available for this event');
    }

} catch (Exception $e) {
    error_log('Error in ticket_cart.php: ' . $e->getMessage());
    header('Location: ../festivals.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // This is where payment processing would go
    // For now, just redirect to a thank you page
    header('Location: ../thank_you.php');
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Purchase Tickets - <?php echo htmlspecialchars($event['name']); ?> | Tickets @ Gábor</title>
  <link rel="shortcut icon" type="image/png" href="../../assets/images/logos/favicon.svg" />
  <link rel="stylesheet" href="http://localhost:63342/Diplomamunka-26222041/assets/libs/owl.carousel/dist/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="http://localhost:63342/Diplomamunka-26222041/assets/libs/aos-master/dist/aos.css">
  <link rel="stylesheet" href="http://localhost:63342/Diplomamunka-26222041/assets/css/styles.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
  <style>
    .ticket-card {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      border: 1px solid #e9ecef;
    }
    .ticket-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }
    /* VIP Ticket Styling */
    .ticket-vip {
      background: linear-gradient(135deg, #FFD700 0%, #FFC000 100%);
      border: 1px solid #FFC000;
      position: relative;
      overflow: hidden;
    }
    .ticket-vip::before {
      content: 'VIP';
      position: absolute;
      top: 10px;
      right: 10px;
      background: rgba(0, 0, 0, 0.2);
      color: white;
      font-weight: bold;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .ticket-vip h5 {
      color: #000;
      font-weight: 700;
    }
    .ticket-header {
      background-size: cover;
      background-position: center;
      height: 200px;
      position: relative;
    }
    .ticket-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(to bottom, rgba(0,0,0,0.1), rgba(0,0,0,0.7));
      display: flex;
      align-items: flex-end;
      padding: 20px;
      color: white;
    }
    .ticket-type {
      background: var(--bs-accent-blue);
      color: white;
      padding: 5px 15px;
      border-radius: 20px;
      font-size: 0.9rem;
      font-weight: 600;
    }
    .form-control, .form-select {
      border-radius: 8px;
      padding: 12px 15px;
      border: 1px solid #e0e0e0;
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--bs-accent-blue);
      box-shadow: 0 0 0 0.25rem rgba(34, 16, 255, 0.15);
    }
    .btn-pay {
      border-radius: 8px;
      padding: 12px 24px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    .btn-pay:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    .available-tickets {
      font-size: 0.9rem;
      color: #6c757d;
    }
    .ticket-price {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--bs-accent-blue);
    }
    .total-amount {
      font-size: 1.8rem;
      font-weight: 700;
      color: var(--bs-accent-blue);
    }
  </style>
</head>

<body>

  <!-- Header -->
  <?php include '../header.php'; ?>

  <!--  Page Wrapper -->
  <div class="page-wrapper overflow-hidden">

    <!-- Ticket Purchase Section -->
    <section class="py-5 py-lg-8 py-xl-10">
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-10">
            <div class="d-flex flex-column gap-6">
              <!-- Back to event -->
              <a href="../festival-detail.php?id=<?php echo $event_id; ?>" class="text-decoration-none d-inline-flex align-items-center text-dark mb-4" style="padding-top: 5em; max-width: fit-content;">
                <i class="fas fa-arrow-left me-2"></i> Back to Event
              </a>

              <!-- Page Header -->
              <div class="text-center mb-6">
                <h1 class="display-5 fw-bold mb-3">Purchase Tickets</h1>
                <p class="lead">Complete your purchase for <?php echo htmlspecialchars($event['name']); ?></p>
              </div>

              <div class="row g-5">
                <!-- Ticket Selection -->
                <div class="col-lg-7">
                  <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-5">
                      <h3 class="h4 mb-4">Select Tickets</h3>

                      <form id="ticketForm" method="POST" action="">
                        <?php foreach ($ticket_types as $ticket): ?>
                        <div class="mb-4 p-4 border rounded <?php echo $ticket['ticket_type'] === 'vip' ? 'ticket-vip' : ''; ?>">
                          <div class="d-flex justify-content-between align-items-center">
                            <div>
                              <h5 class="mb-1"><?php echo strtoupper($ticket['ticket_type']); ?> TICKET</h5>
                              <p class="mb-0 text-muted"><?php echo number_format($ticket['price'], 0, ',', ' '); ?> Ft</p>
                            </div>
                            <div class="text-end">
                              <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle quantity-btn" data-type="decrease" data-ticket-type="<?php echo $ticket['ticket_type_id']; ?>">-</button>
                                <input type="number"
                                       class="form-control mx-2 text-center ticket-quantity"
                                       id="quantity-<?php echo $ticket['ticket_type_id']; ?>"
                                       name="tickets[<?php echo $ticket['ticket_type_id']; ?>]"
                                       value="0"
                                       min="0"
                                       max="5"
                                       data-price="<?php echo $ticket['price']; ?>"
                                       data-name="<?php echo strtoupper($ticket['ticket_type']); ?> TICKET"
                                       style="width: 70px;">
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle quantity-btn" data-type="increase" data-ticket-type="<?php echo $ticket['ticket_type_id']; ?>">+</button>
                              </div>
                              <small class="text-muted d-block mt-1">
                                <?php echo $ticket['remaining_tickets']; ?> available
                              </small>
                            </div>
                          </div>
                        </div>
                        <?php endforeach; ?>
                      <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                    </div>
                  </div>
                </div>

                <!-- Order Summary -->
                <div class="col-lg-5">
                  <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-5">
                      <h3 class="h4 mb-4">Order Summary</h3>

                      <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                        <span class="text-muted">Event</span>
                        <span class="fw-medium"><?php echo htmlspecialchars($event['name']); ?></span>
                      </div>

                      <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                        <span class="text-muted">Date</span>
                        <span class="fw-medium">
                          <?php
                            $start_date = new DateTime($event['start_date']);
                            $end_date = new DateTime($event['end_date']);
                            if ($start_date->format('Y-m-d') === $end_date->format('Y-m-d')) {
                              echo $start_date->format('F j, Y');
                            } else {
                              echo $start_date->format('M j') . ' - ' . $end_date->format('M j, Y');
                            }
                          ?>
                        </span>
                      </div>

                      <div id="order-summary-items">
                        <!-- Ticket items will be added here by JavaScript -->
                      </div>
                      <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span id="subtotal">0 Ft</span>
                      </div>
                      <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Service Fee</span>
                        <span id="serviceFee">0 Ft</span>
                      </div>
                      <hr>
                      <div class="d-flex justify-content-between mb-0">
                        <h5>Total</h5>
                        <h5 id="total">0 Ft</h5>
                      </div>
                      <p class="text-muted small mb-4">* All prices include VAT. No additional fees will be charged.</p>

                      <div class="d-grid gap-3">
                        <button type="button" class="btn btn-dark btn-pay" id="checkout-button-stripe">
                          <i class="fab fa-cc-stripe me-2"></i> Pay with Stripe
                        </button>
                        <button type="button" class="btn btn-primary btn-pay" id="checkout-button-paypal">
                          <i class="fab fa-paypal me-2"></i> Pay with PayPal
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Event Banner Section -->
    <section class="position-relative d-flex align-items-center" style="min-height: 400px; background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('<?php echo htmlspecialchars($event['cover_image']); ?>') center/cover no-repeat;">
      <div class="container position-relative z-1">
        <div class="row">
          <div class="col-lg-8 mx-auto text-center text-white">
            <h1 class="display-4 fw-bold mb-3"><?php echo htmlspecialchars($event['name']); ?></h1>
            <p class="lead mb-0">
              <?php
                echo $start_date->format('F j, Y');
                if ($start_date->format('Y-m-d') !== $end_date->format('Y-m-d')) {
                  echo ' - ' . $end_date->format('F j, Y');
                }
              ?>
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <?php include '../footer.php'; ?>

  <div class="get-template hstack gap-2">
    <button class="btn bg-primary p-2 round-52 rounded-circle hstack justify-content-center flex-shrink-0"
      id="scrollToTopBtn">
      <iconify-icon icon="lucide:arrow-up" class="fs-7 text-dark"></iconify-icon>
    </button>
  </div>

  <!-- Add jQuery and Bootstrap JS bundle -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Custom JS for header scroll effect -->
  <script src="../../assets/js/custom.js"></script>

  <!-- Other JS libraries -->
  <script src="../../assets/libs/owl.carousel/dist/owl.carousel.min.js"></script>
  <script src="../../assets/libs/aos-master/dist/aos.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
  <script src="https://unpkg.com/vanilla-tilt@1.7.2/dist/vanilla-tilt.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/rellax"></script>
  <script src="https://cdn.jsdelivr.net/gh/raveren/raveren.github.io@main/assets/js/theme.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fslightbox"></script>
  <script src="https://cdn.jsdelivr.net/npm/isotope-layout@3/dist/isotope.pkgd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/imagesloaded@4/imagesloaded.pkgd.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize Owl Carousel if needed
      if (typeof $.fn.owlCarousel === 'function') {
        $('.owl-carousel').owlCarousel();
      }

      // Handle quantity buttons
      document.querySelectorAll('.quantity-btn').forEach(button => {
        button.addEventListener('click', function() {
          const type = this.getAttribute('data-type');
          const ticketType = this.getAttribute('data-ticket-type');
          const input = document.getElementById(`quantity-${ticketType}`);
          if (!input) return;

          let value = parseInt(input.value) || 0;
          const max = parseInt(input.max) || 5;

          if (type === 'increase' && value < max) {
            value++;
          } else if (type === 'decrease' && value > 0) {
            value--;
          }

          input.value = value;
          updateOrderSummary();
        });
      });

      // Handle direct input changes
      document.querySelectorAll('.ticket-quantity').forEach(input => {
        input.addEventListener('change', function() {
          const max = parseInt(this.max);
          const value = parseInt(this.value);

          if (isNaN(value) || value < 0) {
            this.value = 0;
          } else if (value > max) {
            this.value = max;
          }
          updateOrderSummary();
        });
      });

      // Function to update the order summary
      function updateOrderSummary() {
        let subtotal = 0;
        let totalTickets = 0;
        let orderItemsHtml = '';

        // Calculate subtotal based on selected tickets
        document.querySelectorAll('.ticket-quantity').forEach(input => {
          const quantity = parseInt(input.value) || 0;
          if (quantity > 0) {
            const price = parseFloat(input.getAttribute('data-price')) || 0;
            const ticketTypeId = input.getAttribute('id').replace('quantity-', '');
            const ticketType = document.querySelector(`[data-ticket-type="${ticketTypeId}"]`);
            const ticketName = ticketType ? ticketType.textContent.trim() : 'Ticket';
            const itemTotal = quantity * price;

            subtotal += itemTotal;
            totalTickets += quantity;

            // Add to order items
            orderItemsHtml += `
              <div class="d-flex justify-content-between mb-2">
                <span>${quantity} x ${ticketName}</span>
                <span>${itemTotal.toFixed(2)} Ft</span>
              </div>
            `;
          }
        });

        // Update the order summary
        const orderItemsContainer = document.getElementById('order-items');
        if (orderItemsContainer) {
          orderItemsContainer.innerHTML = orderItemsHtml || '<div class="text-muted">No tickets selected</div>';
        }

        // Update totals
        const subtotalElement = document.getElementById('subtotal');
        const totalElement = document.getElementById('total');

        if (subtotalElement) subtotalElement.textContent = `${subtotal.toFixed(2)} Ft`;
        if (totalElement) totalElement.textContent = `${subtotal.toFixed(2)} Ft`;
      }

      // Initial update of the order summary
      updateOrderSummary();
  });


    // Stripe checkout
    document.getElementById("checkout-button-stripe").addEventListener("click", async () => {
        // Collect selected ticket items
        const items = [];
        let clientTotal = 0;
        document.querySelectorAll('.ticket-quantity').forEach(input => {
          const quantity = parseInt(input.value, 10) || 0;
          if (quantity > 0) {
            const unitAmount = parseInt(input.getAttribute('data-price'), 10) || 0;
            const name = input.getAttribute('data-name') || 'Ticket';
            const ticketTypeId = input.id.startsWith('quantity-') ? input.id.replace('quantity-', '') : null;
            if (unitAmount > 0) {
              items.push({ name, unit_amount: unitAmount, quantity, ticket_type_id: ticketTypeId });
              clientTotal += unitAmount * quantity;
            }
          }
        });

        if (items.length === 0) {
          alert('Kérjük, válasszon legalább egy jegyet a folytatáshoz.');
          return;
        }

        if (clientTotal < 175) {
          alert('A Stripe fizetés minimális összege 175 Ft. Kérjük, növelje a mennyiséget.');
          return;
        }

        const eventIdInput = document.querySelector('input[name="event_id"]');
        const eventId = eventIdInput ? parseInt(eventIdInput.value, 10) : null;

        try {
          const response = await fetch("/Diplomamunka-26222041/php/raver_sites/create_checkout.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              event_id: eventId,
              items
            })
          });

          if (!response.ok) {
            const errText = await response.text();
            throw new Error(`HTTP ${response.status}: ${errText}`);
          }

          const data = await response.json();
          if (data.url) {
            window.location.href = data.url;
          } else {
            alert("Hiba történt: " + (data.error || 'Ismeretlen hiba'));
          }
        } catch (e) {
          console.error('Checkout error:', e);
          alert('Hiba történt a fizetés indításakor. Kérjük, próbálja meg később.');
        }
    });
  </script>
</body>

</html>