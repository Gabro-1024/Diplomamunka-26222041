<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    var_dump($_SESSION);
    die('Access Denied');
}

try {
    $conn = db_connect();
}
catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// Get event count
$eventStmt = $conn->query("SELECT COUNT(*) as count FROM events");
$eventCount = $eventStmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get user count
$userStmt = $conn->query("SELECT COUNT(*) as count FROM users");
$userCount = $userStmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get total revenue (with COALESCE to handle NULL results)
$revenueStmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM purchases WHERE status = 'completed'");
$totalRevenue = $revenueStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get tickets sold count
$ticketsStmt = $conn->query("SELECT COUNT(*) as count FROM tickets t JOIN purchases p ON t.purchase_id = p.id WHERE p.status = 'completed'");
$ticketsSold = $ticketsStmt->fetch(PDO::FETCH_ASSOC)['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Tickets @ Gábor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/logos/favicon.svg">
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        <!-- Main Content -->
        <div class="main-content">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
                <div class="container-fluid">
                    <h4 class="mb-0">Dashboard</h4>
                    <div class="d-flex align-items-center">
                        <span class="me-3">Welcome, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Admin'); ?></span>
                    </div>
                </div>
            </nav>

            <div class="container-fluid py-4">
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Events</h6>
                                        <h3 class="mb-0"><?php echo $eventCount; ?></h3>
                                    </div>
                                    <div class="stat-icon bg-primary">
                                        <i class='bx bxs-calendar'></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Users</h6>
                                        <h3 class="mb-0"><?php echo $userCount; ?></h3>
                                    </div>
                                    <div class="stat-icon bg-success">
                                        <i class='bx bxs-user'></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Revenue</h6>
                                        <h3 class="mb-0"><?php echo number_format($totalRevenue, 0, ',', ' '); ?> HUF</h3>
                                    </div>
                                    <div class="stat-icon bg-warning">
                                        <i class='bx bxs-dollar-circle'></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Tickets Sold</h6>
                                        <h3 class="mb-0"><?php echo $ticketsSold; ?></h3>
                                    </div>
                                    <div class="stat-icon bg-info">
                                        <i class='bx bxs-purchase-tag-alt'></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Date</th>
                                                <th>Ticket Type</th>
                                                <th>Price</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $recentTickets = $conn->query("
                                                SELECT t.*, e.name as event_name, p.purchase_date, tt.ticket_type 
                                                FROM tickets t 
                                                JOIN purchases p ON t.purchase_id = p.id 
                                                JOIN events e ON t.event_id = e.id 
                                                JOIN ticket_types tt ON t.event_id = tt.event_id 
                                                ORDER BY p.purchase_date DESC 
                                                LIMIT 5
                                            ")->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($recentTickets as $ticket) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($ticket['event_name']) . "</td>";
                                                echo "<td>" . date('M d, Y', strtotime($ticket['purchase_date'])) . "</td>";
                                                echo "<td>" . ucfirst($ticket['ticket_type']) . "</td>";
                                                echo "<td>" . number_format($ticket['price'], 0, ',', ' ') . " HUF</td>";
                                                echo "<td><span class='badge bg-success'>Completed</span></td>";
                                                echo "</tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activate current nav item
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.nav-link').forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
