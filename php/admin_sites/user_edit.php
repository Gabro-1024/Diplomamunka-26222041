<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    die('Access Denied');
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = null;
$userInterests = [];
$allInterests = [];

// Fetch all available interests
$interestResult = $conn->query("SELECT DISTINCT style_name FROM user_interests ORDER BY style_name");
while ($row = $interestResult->fetch_assoc()) {
    $allInterests[] = $row['style_name'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $required = ['first_name', 'last_name', 'email', 'role'];
    $errors = [];
    
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    // Email validation
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    // Check if email already exists (for new users or when email is changed)
    $email = $conn->real_escape_string($_POST['email']);
    $checkEmailQuery = "SELECT id FROM users WHERE email = '$email' AND id != $userId";
    if ($conn->query($checkEmailQuery)->num_rows > 0) {
        $errors[] = 'This email is already registered';
    }
    
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Prepare user data
            $firstName = $conn->real_escape_string($_POST['first_name']);
            $lastName = $conn->real_escape_string($_POST['last_name']);
            $email = $conn->real_escape_string($_POST['email']);
            $phone = $conn->real_escape_string($_POST['phone'] ?? '');
            $city = $conn->real_escape_string($_POST['city'] ?? '');
            $birthDate = !empty($_POST['birth_date']) ? "'" . $conn->real_escape_string($_POST['birth_date']) . "'" : 'NULL';
            $role = $conn->real_escape_string($_POST['role']);
            
            // Handle password update if provided
            $passwordUpdate = '';
            if (!empty($_POST['password'])) {
                if ($_POST['password'] !== $_POST['confirm_password']) {
                    throw new Exception('Passwords do not match');
                }
                $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $passwordUpdate = ", password_hash = '$hashedPassword'";
            }
            
            if ($userId > 0) {
                // Update existing user
                $updateQuery = "UPDATE users SET 
                                first_name = '$firstName',
                                last_name = '$lastName',
                                email = '$email',
                                phone_number = " . ($phone ? "'$phone'" : 'NULL') . ",
                                city = " . ($city ? "'$city'" : 'NULL') . ",
                                birth_date = $birthDate,
                                role = '$role'
                                $passwordUpdate
                                WHERE id = $userId";
                
                $conn->query($updateQuery);
                
                // Delete existing interests
                $conn->query("DELETE FROM user_interests WHERE user_id = $userId");
                
                $successMessage = 'User updated successfully!';
            } else {
                // Create new user
                if (empty($_POST['password'])) {
                    throw new Exception('Password is required for new users');
                }
                
                $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                $insertQuery = "INSERT INTO users (first_name, last_name, email, phone_number, city, birth_date, role, password_hash, is_verified) 
                                VALUES ('$firstName', '$lastName', '$email', " . 
                                ($phone ? "'$phone'" : 'NULL') . ", " . 
                                ($city ? "'$city'" : 'NULL') . ", 
                                $birthDate, 
                                '$role', 
                                '$hashedPassword',
                                1)";
                
                $conn->query($insertQuery);
                $userId = $conn->insert_id;
                
                $successMessage = 'User created successfully!';
            }
            
            // Add interests
            if (isset($_POST['interests']) && is_array($_POST['interests'])) {
                foreach ($_POST['interests'] as $interest) {
                    $interest = $conn->real_escape_string($interest);
                    $conn->query("INSERT INTO user_interests (user_id, style_name) VALUES ($userId, '$interest')");
                }
            }
            
            $conn->commit();
            $_SESSION['success_message'] = $successMessage;
            header('Location: users.php');
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error saving user: ' . $e->getMessage();
        }
    }
}

// If editing, load the user data
if ($userId > 0) {
    $user = $conn->query("SELECT * FROM users WHERE id = $userId")->fetch_assoc();
    
    if ($user) {
        // Load user interests
        $interestResult = $conn->query("SELECT style_name FROM user_interests WHERE user_id = $userId");
        while ($row = $interestResult->fetch_assoc()) {
            $userInterests[] = $row['style_name'];
        }
    } else {
        $userId = 0; // Reset if user not found
    }
}

// Set default values for new user
if (!$user) {
    $user = [
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone_number' => '',
        'city' => '',
        'birth_date' => '',
        'role' => 'raver'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $userId ? 'Edit' : 'Create'; ?> User - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .select2-container--default .select2-selection--multiple {
            min-height: 38px;
            border: 1px solid #ced4da;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #e9ecef;
            border: 1px solid #ced4da;
        }
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #6c757d;
            margin: 0 auto 1rem;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
                <div class="container-fluid">
                    <h4 class="mb-0"><?php echo $userId ? 'Edit' : 'Create'; ?> User</h4>
                    <div class="d-flex align-items-center">
                        <a href="users.php" class="btn btn-outline-secondary me-2">
                            <i class='bx bx-arrow-back'></i> Back to Users
                        </a>
                        <button type="submit" form="userForm" class="btn btn-primary">
                            <i class='bx bx-save'></i> Save User
                        </button>
                    </div>
                </div>
            </nav>

            <div class="container-fluid py-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form id="userForm" method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">User Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" required 
                                                   value="<?php echo htmlspecialchars($user['first_name']); ?>">
                                            <div class="invalid-feedback">Please provide a first name.</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" required
                                                   value="<?php echo htmlspecialchars($user['last_name']); ?>">
                                            <div class="invalid-feedback">Please provide a last name.</div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required
                                               value="<?php echo htmlspecialchars($user['email']); ?>">
                                        <div class="invalid-feedback">Please provide a valid email address.</div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone"
                                                   value="<?php echo htmlspecialchars($user['phone_number']); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="city" class="form-label">City</label>
                                            <input type="text" class="form-control" id="city" name="city"
                                                   value="<?php echo htmlspecialchars($user['city']); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="birth_date" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="birth_date" name="birth_date"
                                               value="<?php echo !empty($user['birth_date']) ? date('Y-m-d', strtotime($user['birth_date'])) : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="raver" <?php echo $user['role'] === 'raver' ? 'selected' : ''; ?>>Raverboy</option>
                                            <option value="organizer" <?php echo $user['role'] === 'organizer' ? 'selected' : ''; ?>>Organizer</option>
                                            <option value="worker" <?php echo $user['role'] === 'worker' ? 'selected' : ''; ?>>Worker</option>
                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                        <div class="invalid-feedback">Please select a role.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="interests" class="form-label">Music Interests</label>
                                        <select class="form-select" id="interests" name="interests[]" multiple>
                                            <?php foreach ($allInterests as $interest): ?>
                                                <option value="<?php echo htmlspecialchars($interest); ?>"
                                                    <?php echo in_array($interest, $userInterests) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($interest); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Account Security</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($userId > 0): ?>
                                        <div class="alert alert-info">
                                            <i class='bx bx-info-circle'></i> Leave password fields blank to keep the current password.
                                        </div>
                                    <?php endif; ?>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label"><?php echo $userId ? 'New ' : ''; ?>Password <?php echo !$userId ? '<span class="text-danger">*</span>' : ''; ?></label>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   <?php echo !$userId ? 'required' : ''; ?>>
                                            <?php if (!$userId): ?>
                                                <div class="invalid-feedback">Please provide a password.</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">Confirm <?php echo $userId ? 'New ' : ''; ?>Password <?php echo !$userId ? '<span class="text-danger">*</span>' : ''; ?></label>
                                            <input type="password" class="form-control" id="confirm_password" 
                                                   name="confirm_password" <?php echo !$userId ? 'required' : ''; ?>>
                                            <?php if (!$userId): ?>
                                                <div class="invalid-feedback">Please confirm the password.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($userId > 0): ?>
                                        <div class="form-text">
                                            <i class='bx bx-shield-quarter'></i> Last updated: 
                                            <?php echo !empty($user['updated_at']) ? date('M d, Y', strtotime($user['updated_at'])) : 'Never'; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Account Status</h5>
                                </div>
                                <div class="card-body text-center">
                                    <div class="avatar-preview mb-3">
                                        <?php 
                                        $initials = strtoupper(
                                            substr($user['first_name'] ?? 'U', 0, 1) . 
                                            substr($user['last_name'] ?? 'U', 0, 1)
                                        );
                                        echo $initials;
                                        ?>
                                    </div>
                                    
                                    <h5 class="mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                                    <p class="text-muted mb-3"><?php echo ucfirst($user['role']); ?></p>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class='bx bx-save'></i> Save User
                                        </button>
                                        <a href="users.php" class="btn btn-outline-secondary">
                                            <i class='bx bx-x'></i> Cancel
                                        </a>
                                    </div>

                                    <?php if ($userId > 0): ?>
                                        <hr>
                                        <div class="text-start">
                                            <h6 class="mb-3">Account Details</h6>
                                            <p class="mb-1">
                                                <i class='bx bx-calendar text-muted me-2'></i>
                                                <span class="text-muted">Created: </span>
                                                <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                            </p>
                                            <p class="mb-0">
                                                <i class='bx bx-calendar-check text-muted me-2'></i>
                                                <span class="text-muted">Last Login: </span>
                                                <?php 
                                                $lastLogin = $conn->query("
                                                    SELECT MAX(created_at) as last_login 
                                                    FROM login_sessions 
                                                    WHERE user_id = $userId
                                                ")->fetch_assoc()['last_login'];
                                                
                                                echo $lastLogin ? date('M d, Y H:i', strtotime($lastLogin)) : 'Never';
                                                ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2 for interests
        $(document).ready(function() {
            $('#interests').select2({
                tags: true,
                tokenSeparators: [',', ' '],
                placeholder: 'Select or type interests',
                allowClear: true
            });
            
            // Form validation
            (function () {
                'use strict'
                
                var forms = document.querySelectorAll('.needs-validation')
                
                Array.prototype.slice.call(forms)
                    .forEach(function (form) {
                        form.addEventListener('submit', function (event) {
                            if (!form.checkValidity()) {
                                event.preventDefault()
                                event.stopPropagation()
                            }
                            
                            // Custom password validation for new users
                            if (form.id === 'userForm' && <?php echo !$userId ? 'true' : 'false'; ?>) {
                                const password = document.getElementById('password').value;
                                const confirmPassword = document.getElementById('confirm_password').value;
                                
                                if (password !== confirmPassword) {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    alert('Passwords do not match!');
                                    return false;
                                }
                            }
                            
                            form.classList.add('was-validated')
                        }, false)
                    })
            })()
        });
    </script>
</body>
</html>
