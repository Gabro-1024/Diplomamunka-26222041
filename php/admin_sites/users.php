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

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = (int)$_GET['delete'];
    
    // Prevent deleting own account
    if ($userId === $_SESSION['user_id']) {
        $_SESSION['error_message'] = 'You cannot delete your own account!';
    } else {
        // Start transaction
        $conn->beginTransaction();
        
        try {
            // Delete related records first (simplified example - adjust based on your database constraints)
            $conn->prepare("DELETE FROM user_interests WHERE user_id = ?")->execute([$userId]);
            
            // Delete the user
            $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            
            $conn->commit();
            $_SESSION['success_message'] = 'User deleted successfully!';
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error_message'] = 'Error deleting user: ' . $e->getMessage();
        }
    }
    
    header('Location: users.php');
    exit();
}

// Handle role update
if (isset($_POST['update_role']) && is_numeric($_POST['user_id'])) {
    $userId = (int)$_POST['user_id'];
    $newRole = $_POST['role'];
    
    // Prevent changing own role
    if ($userId === $_SESSION['user_id']) {
        $_SESSION['error_message'] = 'You cannot change your own role!';
    } else {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$newRole, $userId]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success_message'] = 'User role updated successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to update user role or no changes were made.';
        }
    }
    
    header('Location: users.php');
    exit();
}

// Fetch all users with their ticket counts
$users = $conn->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM tickets t WHERE t.owner_id = u.id) as ticket_count,
           (SELECT COUNT(*) FROM events e WHERE e.organizer_id = u.id) as events_organized
    FROM users u
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
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
                    <h4 class="mb-0">Manage Users</h4>
                    <div class="d-flex align-items-center">
                        <a href="user_add.php" class="btn btn-primary">
                            <i class='bx bx-user-plus'></i> Add New User
                        </a>
                    </div>
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
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Tickets</th>
                                        <th>Events</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($users) > 0): ?>
                                        <?php $counter = 1; ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar me-3">
                                                            <span class="avatar-initial rounded-circle bg-primary text-white">
                                                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h6>
                                                            <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'primary' : 'secondary'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $user['ticket_count']; ?></td>
                                                <td><?php echo $user['events_organized']; ?></td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                            Actions
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                    <input type="hidden" name="role" value="<?php echo $user['role'] === 'admin' ? 'user' : 'admin'; ?>">
                                                                    <button type="submit" name="update_role" class="dropdown-item">
                                                                        <?php echo $user['role'] === 'admin' ? 'Remove Admin' : 'Make Admin'; ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <a href="#" class="dropdown-item text-danger" 
                                                                   onclick="return confirmDelete(<?php echo $user['id']; ?>, '<?php echo addslashes($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                                                    Delete User
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <div class="text-muted">No users found.</div>
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
        function confirmDelete(userId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete the user and all their data. This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!',
                dangerMode: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'users.php?delete=' + userId;
                }
            });
        }
    </script>
</body>
</html>
