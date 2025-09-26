<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Initialize database connection
$conn = db_connect();

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    die('Access Denied');
}

// Handle event deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $eventId = (int)$_GET['delete'];
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // First, get the event to find the cover image path
        $eventStmt = $conn->prepare("SELECT cover_image FROM events WHERE id = ?");
        $eventStmt->execute([$eventId]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($event) {
            // Delete the cover image if it exists
            if (!empty($event['cover_image'])) {
                $imagePath = $_SERVER['DOCUMENT_ROOT'] . '/Diplomamunka-26222041/' . ltrim($event['cover_image'], '/');
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            // Delete related records first using prepared statements
            $conn->prepare("DELETE FROM event_categories WHERE event_id = ?")->execute([$eventId]);
            $conn->prepare("DELETE FROM ticket_types WHERE event_id = ?")->execute([$eventId]);
            
            // Delete the event
            $conn->prepare("DELETE FROM events WHERE id = ?")->execute([$eventId]);
            
            $conn->commit();
            $_SESSION['success_message'] = 'Event and associated data deleted successfully!';
        } else {
            throw new Exception('Event not found');
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = 'Error deleting event: ' . $e->getMessage();
    }
    
    header('Location: events.php');
    exit();
}

// Fetch all events with organizer names
$events = $conn->query("
    SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) as organizer_name
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.id
    ORDER BY e.start_date DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/logos/favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
                <div class="container-fluid">
                    <h4 class="mb-0">Manage Events</h4>
                </div>
            </nav>

            <div class="container-fluid py-4">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Event Name</th>
                                        <th>Organizer</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($events as $event): ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php 
                                                    $coverImage = '';
                                                    if (!empty($event['cover_image'])) {
                                                        // If it's a full URL, use it directly
                                                        if (filter_var($event['cover_image'], FILTER_VALIDATE_URL)) {
                                                            $coverImage = $event['cover_image'];
                                                        } 
                                                        // If it's a relative path, use the specified base URL
                                                        else {
                                                            $coverImage = 'http://localhost:63342/Diplomamunka-26222041/' . ltrim($event['cover_image']);
                                                        }
                                                    }
                                                    ?>
                                                    <?php if (!empty($coverImage)): ?>
                                                        <img src="<?php echo htmlspecialchars($coverImage); ?>" 
                                                             alt="<?php echo htmlspecialchars($event['name']); ?>" 
                                                             class="rounded me-3" width="50" height="50" style="object-fit: cover;">
                                                    <?php endif; ?>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($event['name']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($event['slogan'] ?? 'No slogan'); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($event['organizer_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($event['start_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($event['end_date'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="event_edit.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class='bx bx-edit text-white'></i>
                                                    </a>
                                                    <a href="#" onclick="confirmDelete(<?php echo $event['id']; ?>)" class="btn btn-sm btn-outline-danger" title="Delete Event">
                                                        <i class='bx bx-trash'></i> Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($events)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <div class="text-muted">No events found. <a href="event_edit.php">Create your first event</a></div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmDelete(eventId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'events.php?delete=' + eventId;
                }
            });
        }
    </script>
</body>
</html>
