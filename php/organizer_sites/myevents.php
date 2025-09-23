<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Check if user is logged in and is an organizer
if (!isUserLoggedIn()) {
    header('Location: /Diplomamunka-26222041/php/sign-in.php');
    exit;
}

$userId = $_SESSION['user_id'];
$pdo = db_connect();

// Get user role
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Redirect if not an organizer
if (!$user || $user['role'] !== 'organizer') {
    header('Location: /Diplomamunka-26222041/php/raver_sites/profile.php');
    exit;
}

// Get events created by this organizer
$stmt = $pdo->prepare('SELECT e.*, v.name as venue_name FROM events e 
                      LEFT JOIN venues v ON e.venue_id = v.id 
                      WHERE e.organizer_id = ? 
                      ORDER BY e.start_date DESC');
$stmt->execute([$userId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'My Events - Organizer Dashboard';
$custom_styles = "
    <style>
        .event-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .event-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .page-wrapper {
            padding-top: 120px;
        }
        .stats-panel {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            background: #f8f9fa;
            border-radius: 0 0 8px 8px;
            margin: -1rem auto 0;
            margin-top: -17px !important;
            width: calc(100% - 2rem);
            border: 1px solid transparent;
            border-top: none;
            padding: 1rem;
        }
        .stats-panel.active {
            max-height: 500px;
            padding: 1rem;
            margin-top: -17px !important;
            border-color: #dee2e6;
        }
    </style>
";

include __DIR__ . '/../header.php';
?>

<body class="bg-light">
  <!-- Page Wrapper -->
  <div class="page-wrapper overflow-hidden" style="padding-top: 120px;">
    <section class="py-5 py-lg-8">
      <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-5">
          <h1 class="display-5 fw-bold mb-0">My Events</h1>
          <a href="festival_maker.php" class="btn btn-accent-blue">
            <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
            Create New Event
          </a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
              echo $_SESSION['success_message']; 
              unset($_SESSION['success_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (empty($events)): ?>
          <div class="text-center py-5 my-5">
            <div class="mb-4">
              <iconify-icon icon="lucide:calendar-x" style="font-size: 64px; color: #6c757d;"></iconify-icon>
            </div>
            <h3>No events yet</h3>
            <p class="text-muted mb-4">You haven't created any events yet. Get started by creating your first event!</p>
            <a href="festival_maker.php" class="btn btn-accent-blue">
              <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
              Create Your First Event
            </a>
          </div>
        <?php else: ?>
          <div class="row g-4">
            <?php foreach ($events as $event): 
              $startDate = new DateTime($event['start_date']);
              $endDate = new DateTime($event['end_date']);
              $now = new DateTime();
              $status = ($startDate > $now) ? 'upcoming' : (($endDate < $now) ? 'past' : 'ongoing');
              $statusClass = [
                'upcoming' => 'bg-info',
                'ongoing' => 'bg-success',
                'past' => 'bg-secondary'
              ][$status];
//              var_dump($event);
//              var_dump($now);
            ?>
              <div class="col-md-6 col-lg-4" data-aos="fade-up">
                <div class="event-card-wrapper">
                <div class="card h-100 event-card">
                  <div class="position-relative">
                    <img src="<?php echo htmlspecialchars($event['cover_image']); ?>" class="event-image" alt="<?php echo htmlspecialchars($event['name']); ?>">
                    <span class="badge rounded-pill <?php echo $statusClass; ?> status-badge">
                      <?php echo ucfirst($status); ?>
                    </span>
                  </div>
                  <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($event['name']); ?></h5>
                    <p class="text-muted small mb-3">
                      <iconify-icon icon="lucide:calendar" class="me-1"></iconify-icon>
                      <?php echo $startDate->format('M j, Y'); ?> - <?php echo $endDate->format('M j, Y'); ?>
                      <br>
                      <iconify-icon icon="lucide:map-pin" class="me-1"></iconify-icon>
                      <?php echo htmlspecialchars($event['venue_name'] ?? 'Location TBD'); ?>
                    </p>
                    <!-- Ticket Info -->
                    <div class="text-center mb-3">
                      <?php
                      // Get ticket types and counts for this event
                      $ticketStmt = $pdo->prepare('SELECT ticket_type, price, remaining_tickets FROM ticket_types WHERE event_id = ?');
                      $ticketStmt->execute([$event['id']]);
                      $ticketTypes = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);

                      // Group tickets by type and calculate totals
                      $ticketSummary = [
                          'regular' => ['count' => 0, 'price' => 0],
                          'vip' => ['count' => 0, 'price' => 0]
                      ];

                      foreach ($ticketTypes as $ticket) {
                          $type = $ticket['ticket_type'];
                          $ticketSummary[$type]['count'] += $ticket['remaining_tickets'];
                          $ticketSummary[$type]['price'] = $ticket['price']; // Store the price (assuming same price per type)
                      }

                      // Display ticket types that exist for this event
                      foreach (['vip', 'regular'] as $type):
                          $typeName = strtoupper($type);
                          $badgeClass = $type === 'vip' ? 'bg-warning text-dark' : 'bg-light text-white';
                      ?>
                      <div class="mb-2">
                          <span class="badge <?php echo $badgeClass; ?> me-1">
                              <iconify-icon icon="lucide:ticket" class="me-1"></iconify-icon>
                              <?php echo $typeName; ?>:
                              <?php
                              if ($ticketSummary[$type]['count'] > 0) {
                                  echo number_format($ticketSummary[$type]['count']) . ' (' . number_format($ticketSummary[$type]['price']) . ' HUF)';
                              } else {
                                  echo '<strong>SOLD OUT!</strong>';
                              }
                              ?>
                          </span>
                      </div>
                      <?php endforeach; ?>
                    </div>
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-center gap-2">
                      <a href="festival_modify.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-secondary">
                        <iconify-icon icon="lucide:edit-2" class="me-1"></iconify-icon> Edit
                      </a>
                      <button type="button" 
                              class="btn btn-sm btn-outline-secondary stats-btn" 
                              data-bs-toggle="popover"
                              data-bs-html="true"
                              data-bs-title="Event Statistics"
                              data-event-id="<?php echo $event['id']; ?>"
                              data-start-date="<?php echo $event['start_date']; ?>"
                              data-end-date="<?php echo $event['end_date']; ?>"
                              data-bs-content="Loading...">
                        <iconify-icon icon="lucide:bar-chart-2" class="me-1"></iconify-icon>
                        <span>Stats</span>
                      </button>
                    </div>
                  </div>
                </div>
                <div class="stats-panel" id="stats-<?php echo $event['id']; ?>">
                    <div class="text-center py-2">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
              </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>

<?php include __DIR__ . '/../footer.php'; ?>
  <script src="../../assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="../../assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../assets/libs/owl.carousel/dist/owl.carousel.min.js"></script>
  <script src="../../assets/libs/aos-master/dist/aos.js"></script>
  <script src="../../assets/js/custom.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <style>
    .event-card-wrapper {
        margin-bottom: 1rem;
    }
    .stats-panel {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        background: #f8f9fa;
        border-radius: 0 0 8px 8px;
        margin: 0 auto;
        width: calc(100% - 2rem);
        padding: 0 1rem;
        border: 1px solid transparent;
        border-top: none;
    }
    .stats-panel.active {
        max-height: 500px;
        padding: 1rem;
        border-color: #dee2e6;
    }
  </style>
<script>
    // Initialize AOS with custom settings
    document.addEventListener('DOMContentLoaded', function() {
        AOS.init({
            duration: 600,
            easing: 'ease-out-cubic',
            once: true,
            mirror: false
        });

        // Format currency
        const formatCurrency = (amount) => {
            return new Intl.NumberFormat('hu-HU', { 
                style: 'decimal',
                maximumFractionDigits: 0 
            }).format(amount) + ' HUF';
        };

        // Store fetched data to prevent multiple requests
        const eventStatsCache = new Map();

        // Handle stats button clicks
        document.querySelectorAll('.stats-btn').forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                const eventId = this.getAttribute('data-event-id');
                const statsPanel = document.getElementById(`stats-${eventId}`);
                
                // Toggle active class
                const isActive = statsPanel.classList.toggle('active');
                
                if (isActive) {
                    // Only load data if not already in cache
                    if (!eventStatsCache.has(eventId)) {
                        statsPanel.innerHTML = `
                            <div class="text-center py-2">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>`;
                        
                        try {
                            const response = await fetch(`/Diplomamunka-26222041/php/api/get_event_stats.php?event_id=${eventId}`);
                            const data = await response.json();
                            
                            if (data.success) {
                                eventStatsCache.set(eventId, data);
                                updateStatsPanel(statsPanel, data);
                            } else {
                                throw new Error(data.message || 'Failed to load statistics');
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            statsPanel.innerHTML = `
                                <div class="alert alert-danger m-0">
                                    Error loading statistics: ${error.message}
                                </div>`;
                        }
                    } else {
                        updateStatsPanel(statsPanel, eventStatsCache.get(eventId));
                    }
                }
            });
        });

        // Function to update stats panel content
        function updateStatsPanel(panel, data) {
            const event = data.event;
            const tickets = data.tickets;
            const payments = data.payments;

            // Format time left text
            let timeLeftText;
            if (event.is_ended) {
                timeLeftText = 'Event has ended';
            } else if (event.is_ongoing) {
                timeLeftText = 'Event is ongoing';
            } else {
                timeLeftText = `${event.days_until} day${event.days_until !== 1 ? 's' : ''} until event starts`;
            }

            // Calculate total tickets sold
            const totalSold = event.total_tickets - event.remaining_tickets;
            const soldPercentage = event.total_tickets > 0
                ? Math.round((totalSold / event.total_tickets) * 100)
                : 0;

            // Build stats HTML
            const statsHTML = `
                <div class="small">
                    <h6 class="mb-2 fw-bold">${event.name}</h6>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between">
                            <span>Status:</span>
                            <strong>${event.is_ongoing ? 'Ongoing' : event.is_ended ? 'Ended' : 'Upcoming'}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Time Left:</span>
                            <strong>${timeLeftText}</strong>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="d-flex justify-content-between">
                            <span>Total Tickets:</span>
                            <strong>${event.total_tickets}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Sold Tickets:</span>
                            <strong>${totalSold} (${soldPercentage}%)</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Remaining:</span>
                            <strong>${event.remaining_tickets}</strong>
                        </div>
                        ${tickets.vip.sold > 0 ? `
                        <div class="d-flex justify-content-between">
                            <span>VIP Tickets:</span>
                            <strong>${tickets.vip.sold} / ${tickets.vip.total} (${formatCurrency(tickets.vip.price)} each)</strong>
                        </div>` : ''}
                        ${tickets.regular.sold > 0 ? `
                        <div class="d-flex justify-content-between">
                            <span>Regular Tickets:</span>
                            <strong>${tickets.regular.sold} / ${tickets.regular.total} (${formatCurrency(tickets.regular.price)} each)</strong>
                        </div>` : ''}
                    </div>

                    <div class="mt-2 pt-2 border-top">
                        <div class="d-flex justify-content-between">
                            <span>Total Revenue:</span>
                            <strong>${formatCurrency(data.total_revenue)}</strong>
                        </div>
                        ${payments.paypal.tickets > 0 ? `
                        <div class="d-flex justify-content-between">
                            <span>PayPal:</span>
                            <strong>${formatCurrency(payments.paypal.amount)} (${payments.paypal.tickets} tix)</strong>
                        </div>` : ''}
                        ${payments.stripe.tickets > 0 ? `
                        <div class="d-flex justify-content-between">
                            <span>Stripe:</span>
                            <strong>${formatCurrency(payments.stripe.amount)} (${payments.stripe.tickets} tix)</strong>
                        </div>` : ''}
                    </div>
                </div>`;

            panel.innerHTML = statsHTML;
        }
    });
</script>