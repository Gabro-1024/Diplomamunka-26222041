<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    die('Access Denied');
}

// Get PDO connection
try {
    $pdo = db_connect();
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Define valid roles
$validRoles = ['raver', 'organizer', 'worker', 'admin'];

// Handle user role update
if (isset($_POST['update_role']) && isset($_POST['user_id']) && isset($_POST['role'])) {
    $userId = (int)$_POST['user_id'];
    $newRole = in_array($_POST['role'], $validRoles) ? $_POST['role'] : 'raver';
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
        $stmt->execute([':role' => $newRole, ':id' => $userId]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success_message'] = 'User role updated successfully!';
        } else {
            $_SESSION['error_message'] = 'No changes were made to the user role.';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error updating user role: ' . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle user blocking/unblocking
if (isset($_POST['toggle_block_user'])) {
    $userId = (int)$_POST['toggle_block_user'];
    
    // Prevent blocking own account
    if (isset($_SESSION['user_id']) && $userId === (int)$_SESSION['user_id']) {
        $_SESSION['error_message'] = 'You cannot block your own account!';
    } else {
        try {
            // Toggle the block status
            $stmt = $pdo->prepare("UPDATE users SET is_blocked = NOT is_blocked WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            
            if ($stmt->rowCount() > 0) {
                // Get the new status to show in the message
                $stmt = $pdo->prepare("SELECT is_blocked FROM users WHERE id = :id");
                $stmt->execute([':id' => $userId]);
                $newStatus = $stmt->fetchColumn();
                
                $action = $newStatus ? 'blocked' : 'unblocked';
                $_SESSION['success_message'] = "User has been $action successfully!";
            } else {
                $_SESSION['error_message'] = 'User not found or no changes were made.';
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Error updating user status: ' . $e->getMessage();
        }
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch all users with their ticket counts (excluding the current admin)
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM tickets t WHERE t.owner_id = u.id) as ticket_count,
               (SELECT COUNT(*) FROM events e WHERE e.organizer_id = u.id) as events_organized
        FROM users u
        WHERE u.id != :current_user_id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([':current_user_id' => $_SESSION['user_id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching users: " . $e->getMessage());
}
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
    <link rel="icon" type="image/png" href="../../assets/images/logos/favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php';?>

        <!-- Main Content -->
        <div class="main-content">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
                <div class="container-fluid">
                    <h4 class="mb-0">Manage Users</h4>
                    <div class="d-flex align-items-center">
                        <!-- User management actions can be added here in the future -->
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
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
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
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'primary' : 'secondary'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $user['ticket_count']; ?></td>
                                                <td><?php echo $user['events_organized']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'primary' : 'secondary'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" 
                                                                class="btn btn-outline-secondary"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#roleModal"
                                                                data-user-id="<?php echo $user['id']; ?>"
                                                                data-user-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                                data-current-role="<?php echo $user['role']; ?>"
                                                                style="background-color: #f8f9fa !important; border-radius: 20%; margin: 3px;">
                                                            <i class='bx <?php echo $user['role'] === 'admin' ? 'bx-shield-x' : 'bx-shield'; ?>'></i>
                                                        </button>
                                                        <?php if ($user['is_blocked']): ?>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="toggle_block_user" value="<?php echo $user['id']; ?>">
                                                                <button type="submit" class="btn btn-sm" title="Unblock User" style="background-color: #f8f9fa !important; color: #198754;">
                                                                    <i class='bx bx-lock-open-alt me-1'></i> Unblock
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="toggle_block_user" value="<?php echo $user['id']; ?>">
                                                                <button type="submit" class="btn btn-sm" title="Block User" onclick="return confirm('Are you sure you want to block this user? They will be logged out and unable to log in again until unblocked.');" style="background-color: #f8f9fa !important; color: #dc3545;">
                                                                    <i class='bx bx-lock me-1'></i> Block
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
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

    <!-- Role Change Modal -->
    <div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" id="roleForm">
                    <input type="hidden" name="update_role" value="1">
                    <input type="hidden" name="user_id" id="modalUserId">
                    <input type="hidden" name="role" id="modalNewRole">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="roleModalLabel">Change User Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Change role for <strong id="userNameDisplay"></strong> from <span id="currentRole" class="badge"></span> to:</p>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary flex-fill role-option mb-2" data-role="raver">
                                <i class='bx bx-user'></i> Raver
                            </button>
                            <button type="button" class="btn btn-outline-success flex-fill role-option mb-2" data-role="organizer">
                                <i class='bx bx-calendar-event'></i> Organizator
                            </button>
                            <button type="button" class="btn btn-outline-info flex-fill role-option mb-2" data-role="worker">
                                <i class='bx bx-briefcase'></i> Worker
                            </button>
                            <button type="button" class="btn btn-outline-warning flex-fill role-option" data-role="admin">
                                <i class='bx bx-shield'></i> Admin
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_role" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Role change modal handling
        const roleModal = document.getElementById('roleModal');
        if (roleModal) {
            roleModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-user-id');
                const userName = button.getAttribute('data-user-name');
                const currentRole = button.getAttribute('data-current-role');

                // Update the modal's content
                const modalTitle = roleModal.querySelector('.modal-title');
                const userNameDisplay = roleModal.querySelector('#userNameDisplay');
                const currentRoleBadge = roleModal.querySelector('#currentRole');
                const userIdInput = roleModal.querySelector('#modalUserId');
                const roleInput = roleModal.querySelector('#modalNewRole');

                modalTitle.textContent = `Change Role for ${userName}`;
                userNameDisplay.textContent = userName;
                
                // Format current role display
                const roleNames = {
                    'raver': 'Raver',
                    'organizator': 'Organizator',
                    'worker': 'Worker',
                    'admin': 'Admin'
                };
                const roleColors = {
                    'raver': 'primary',
                    'organizator': 'success',
                    'worker': 'info',
                    'admin': 'warning'
                };
                
                currentRoleBadge.className = 'badge bg-' + (roleColors[currentRole] || 'secondary') + ' text-capitalize';
                currentRoleBadge.textContent = roleNames[currentRole] || currentRole;
                
                userIdInput.value = userId;
                roleInput.value = currentRole;

                // Update active state of role buttons
                roleModal.querySelectorAll('.role-option').forEach(btn => {
                    btn.classList.remove('active', 'btn-primary', 'btn-success', 'btn-info', 'btn-warning');
                    if (btn.dataset.role === currentRole) {
                        btn.classList.add('active', 'btn-' + (roleColors[currentRole] || 'primary'));
                    } else {
                        btn.classList.add('btn-outline-' + (roleColors[btn.dataset.role] || 'secondary'));
                    }
                });
            });
            
            // Handle role selection
            document.querySelectorAll('.role-option').forEach(button => {
                button.addEventListener('click', function() {
                    const role = this.dataset.role;
                    const roleColors = {
                        'raver': 'primary',
                        'organizator': 'success',
                        'worker': 'info',
                        'admin': 'warning'
                    };
                    
                    document.getElementById('modalNewRole').value = role;
                    
                    // Update button states
                    document.querySelectorAll('.role-option').forEach(btn => {
                        const btnRole = btn.dataset.role;
                        btn.classList.remove('active', 'btn-primary', 'btn-success', 'btn-info', 'btn-warning', 
                                          'btn-outline-primary', 'btn-outline-success', 'btn-outline-info', 'btn-outline-warning');
                        if (btn === this) {
                            btn.classList.add('active', 'btn-' + (roleColors[role] || 'primary'));
                        } else {
                            btn.classList.add('btn-outline-' + (roleColors[btnRole] || 'secondary'));
                        }
                    });
                });
            });

            // Form submission
            const roleForm = document.getElementById('roleForm');
            if (roleForm) {
                roleForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (response.redirected) {
                            window.location.href = response.url;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while updating the user role.');
                    });
                });
            }
        }
        
        // Confirm delete function
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle action rows
            document.querySelectorAll('.toggle-actions').forEach(button => {
                    const userId = this.getAttribute('data-user-id');
                    const actionRow = document.getElementById(`action-row-${userId}`);
                    const isVisible = actionRow.style.display === 'table-row';
                    
                    // Hide all other open action rows
                    document.querySelectorAll('.action-row').forEach(row => {
                        if (row.id !== `action-row-${userId}`) {
                            row.style.display = 'none';
                            const btn = row.previousElementSibling.querySelector('.toggle-actions');
                            if (btn) btn.classList.remove('active');
                        }
                    });
                    
                    // Toggle current row
                    actionRow.style.display = isVisible ? 'none' : 'table-row';
                    this.classList.toggle('active', !isVisible);
                });
            });
            
            // Close action rows when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.toggle-actions') && !e.target.closest('.action-buttons')) {
                    document.querySelectorAll('.action-row').forEach(row => {
                        row.style.display = 'none';
                    });
                    document.querySelectorAll('.toggle-actions').forEach(btn => {
                        btn.classList.remove('active');
                    });
                }
            });
        });
    </script>
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
