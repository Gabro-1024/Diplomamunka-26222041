<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/signup_errors.log');
error_reporting(E_ALL);
// Master genre list
$allGenres = [
    'Ambient','Bass','Breakbeat','Classical','Country','Dance','Deep House','Disco','Drum & Bass','Dubstep','EDM','Electro',
    'Folk','Hardcore','Hardstyle','Hip-Hop','House','Indie','Jazz','K-Pop','Latin','Metal','Minimal','Pop','Progressive House',
    'Psytrance','Punk','R&B','Rap','Reggae','Reggaeton','Rock','Soul','Tech House','Techno','Trance','Trap','Trip-Hop'
];
sort($allGenres, SORT_NATURAL | SORT_FLAG_CASE);

// Initialize variables
$errors = [];
$formData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'city' => '',
    'birth_date' => '',
    'phone_number' => '',
    'role' => 'raver',
    'interests' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $formData = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'city' => trim($_POST['city'] ?? ''),
        'birth_date' => $_POST['birth_date'] ?? '',
        'phone_number' => trim($_POST['phone_number'] ?? ''),
        'role' => $_POST['role'] ?? 'raver',
        'interests' => $_POST['interests'] ?? []
    ];

    // Validate first name
    if (empty($formData['first_name'])) {
        $errors['first_name'] = 'First name is required.';
    } elseif (strlen($formData['first_name']) > 50) {
        $errors['first_name'] = 'First name cannot exceed 50 characters.';
    }

    // Validate last name
    if (empty($formData['last_name'])) {
        $errors['last_name'] = 'Last name is required.';
    } elseif (strlen($formData['last_name']) > 50) {
        $errors['last_name'] = 'Last name cannot exceed 50 characters.';
    }

    // Validate email
    if (empty($formData['email'])) {
        $errors['email'] = 'Email is required.';
    } elseif (strlen($formData['email']) > 60) {
        $errors['email'] = 'Email cannot exceed 60 characters.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    // Validate password
    if (empty($formData['password'])) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($formData['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $formData['password'])) {
        $errors['password'] = 'Password must include at least one uppercase letter, one lowercase letter, and one number.';
    }

    // Validate city
    if (!empty($formData['city']) && strlen($formData['city']) > 40) {
        $errors['city'] = 'City cannot exceed 40 characters.';
    }

    // Validate birth date
    if (!empty($formData['birth_date'])) {
        $birthDate = new DateTime($formData['birth_date']);
        $today = new DateTime();
        $minAgeDate = (new DateTime())->modify('-16 years');

        if ($birthDate > $today) {
            $errors['birth_date'] = 'Birth date cannot be in the future.';
        } elseif ($birthDate > $minAgeDate) {
            $errors['birth_date'] = 'You must be at least 16 years old.';
        }
    }

    // Validate phone number
    if (!empty($formData['phone_number'])) {
        if (!preg_match('/^[0-9 +-]+$/', $formData['phone_number'])) {
            $errors['phone_number'] = 'Phone number can only contain numbers, spaces, and + - characters.';
        }
    }

    // If no errors, proceed with database operations
    if (empty($errors)) {
        require_once __DIR__ . '/db_connect.php';

        try {
            $pdo = db_connect();

            // Check if email already exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$formData['email']]);
            if ($stmt->fetch()) {
                $errors['email'] = 'This email is already registered.';
            } else {
                // Generate registration token
                $regToken = bin2hex(random_bytes(32));
                $regTokenHash = password_hash($regToken, PASSWORD_DEFAULT);
                
                // Set token expiration (24 hours from now)
                $tokenExpires = (new DateTime())->modify('+24 hours')->format('Y-m-d H:i:s');
                
                // Hash password
                $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);

                // Start transaction
                $pdo->beginTransaction();
                
                try {
                    // Insert user with verification token
                    $stmt = $pdo->prepare('
                        INSERT INTO users (
                            first_name, last_name, email, password_hash, 
                            city, birth_date, phone_number, role, created_at,
                            reg_token, reg_token_expires, is_verified
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                    ');

                    try {
                        $success = $stmt->execute([
                            $formData['first_name'],
                            $formData['last_name'],
                            $formData['email'],
                            $hashedPassword,
                            $formData['city'] ?: null,
                            $formData['birth_date'] ?: null,
                            $formData['phone_number'] ?: null,
                            $formData['role'],
                            (new DateTime())->format('Y-m-d H:i:s'),
                            $regTokenHash,
                            $tokenExpires
                        ]);
                    } catch (PDOException $e) {
                        error_log('Database Error: ' . $e->getMessage());
                        error_log('SQL Query: ' . $stmt->queryString);
                        error_log('Parameters: ' . print_r([
                            'first_name' => $formData['first_name'],
                            'email' => $formData['email'],
                            'role' => $formData['role'],
                            'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
                            'token_length' => strlen($regTokenHash),
                            'token_expires' => $tokenExpires
                        ], true));
                        throw $e;
                    }

                    if ($success) {
                        $userId = $pdo->lastInsertId();
                        
                        // Insert user interests if any
                        if (!empty($formData['interests'])) {
                            $interestStmt = $pdo->prepare('INSERT INTO user_interests (user_id, style_name) VALUES (?, ?)');
                            foreach ($formData['interests'] as $interest) {
                                $interestStmt->execute([$userId, $interest]);
                            }
                        }
                        
                        // Include and send verification email
                        require_once __DIR__ . '/includes/send_email.php';
                        $emailSent = sendRegistrationEmail(
                            $formData['email'],
                            $formData['first_name'] . ' ' . $formData['last_name'],
                            $regToken
                        );
                        
                        if ($emailSent) {
                            $pdo->commit();
                            // Redirect to success page
                            header('Location: sign-in.php?registered=1');
                            exit;
                        } else {
                            throw new Exception('Failed to send verification email. Please try again later.');
                        }
                    } else {
                        throw new Exception('Failed to create user account');
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log('Registration error: ' . $e->getMessage());
                    $errors['general'] = 'An error occurred during registration. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tickets @ Gábor - Sign Up</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="shortcut icon" type="image/png" href="../assets/images/logos/favicon.svg" />
    <link rel="stylesheet" href="../assets/libs/owl.carousel/dist/assets/owl.carousel.min.css">
    <link rel="stylesheet" href="../assets/libs/aos-master/dist/aos.css">
    <link rel="stylesheet" href="../assets/css/styles.css" />
    <style>
        .is-invalid {
            border-color: #dc3545 !important;
        }
        .invalid-feedback {
            display: none;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
        .was-validated .form-control:invalid ~ .invalid-feedback {
            display: block;
        }
    </style>
</head>
<body>
<div class="page-wrapper overflow-hidden">
    <section class="bg-graphite border-top border-accent-blue border-4 d-flex align-items-center justify-content-center min-vh-100">
        <div class="container py-3">
            <div class="sign-in card mx-auto shadow-lg">
                <div class="card-body py-8 px-lg-5">
                    <a href="index.php" class="mb-8 hstack justify-content-center">
                        <img src="../assets/images/logos/logo-white.svg" alt="logo" class="img-fluid">
                    </a>

                    <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <div><?php echo htmlspecialchars($errors['general']); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <div>
                        Registration successful! Please check your email to verify your account.
                        <div class="small mt-1">If you don't see the email, please check your spam folder.</div>
                    </div>
                </div>
            <?php endif; ?>

                    <form class="d-flex flex-column gap-3 needs-validation" method="post" action="sign-up.php" novalidate>
                        <div class="row g-3">
                            <!-- First Name -->
                            <div class="col-md-6">
                                <label for="first_name" class="form-label text-graphite">First name</label>
                                <input type="text"
                                       name="first_name"
                                       id="first_name"
                                       class="form-control border-bottom <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['first_name']); ?>"
                                       maxlength="50"
                                       required>
                                <div class="invalid-feedback">
                                    <?php echo $errors['first_name'] ?? ''; ?>
                                </div>
                            </div>

                            <!-- Last Name -->
                            <div class="col-md-6">
                                <label for="last_name" class="form-label text-graphite">Last name</label>
                                <input type="text"
                                       name="last_name"
                                       id="last_name"
                                       class="form-control border-bottom <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['last_name']); ?>"
                                       maxlength="50"
                                       required>
                                <div class="invalid-feedback">
                                    <?php echo $errors['last_name'] ?? ''; ?>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label for="email" class="form-label text-graphite">Email</label>
                                <input type="email"
                                       name="email"
                                       id="email"
                                       class="form-control border-bottom <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['email']); ?>"
                                       maxlength="60"
                                       required>
                                <div class="invalid-feedback">
                                    <?php echo $errors['email'] ?? ''; ?>
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="col-md-6">
                                <label for="password" class="form-label text-graphite">Password</label>
                                <input type="password"
                                       name="password"
                                       id="password"
                                       class="form-control border-bottom <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                       minlength="8"
                                       pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$"
                                       title="Must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, and one number"
                                       required>
                                <div class="invalid-feedback">
                                    <?php echo $errors['password'] ?? ''; ?>
                                </div>
                            </div>

                            <!-- City -->
                            <div class="col-md-6">
                                <label for="city" class="form-label text-graphite">City (optional)</label>
                                <input type="text"
                                       name="city"
                                       id="city"
                                       class="form-control border-bottom <?php echo isset($errors['city']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['city']); ?>"
                                       maxlength="40">
                                <div class="invalid-feedback">
                                    <?php echo $errors['city'] ?? ''; ?>
                                </div>
                            </div>

                            <!-- Birth Date -->
                            <div class="col-md-6">
                                <label for="birth_date" class="form-label text-graphite">Birth date (optional)</label>
                                <input type="date"
                                       name="birth_date"
                                       id="birth_date"
                                       class="form-control border-bottom <?php echo isset($errors['birth_date']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['birth_date']); ?>"
                                       max="<?php echo date('Y-m-d', strtotime('-16 years')); ?>">
                                <div class="invalid-feedback">
                                    <?php echo $errors['birth_date'] ?? ''; ?>
                                </div>
                            </div>

                            <!-- Phone Number -->
                            <div class="col-md-6">
                                <label for="phone_number" class="form-label text-graphite">Phone number (optional)</label>
                                <input type="tel"
                                       name="phone_number"
                                       id="phone_number"
                                       class="form-control border-bottom <?php echo isset($errors['phone_number']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['phone_number']); ?>"
                                       pattern="[0-9 +\-]+"
                                       title="Only numbers, spaces, and + - characters are allowed">
                                <div class="invalid-feedback">
                                    <?php echo $errors['phone_number'] ?? ''; ?>
                                </div>
                            </div>

                            <!-- Role -->
                            <div class="col-md-6">
                                <label for="role" class="form-label text-graphite">Role</label>
                                <select name='role' id="role" class="form-select border-bottom">
                                    <option value="raver" <?php echo $formData['role'] === 'raver' ? 'selected' : ''; ?>>Raver</option>
                                    <option value="organizer" <?php echo $formData['role'] === 'organizer' ? 'selected' : ''; ?>>Organizer</option>
                                </select>
                            </div>

                            <!-- Music Interests -->
                            <div class="col-12">
                                <label class="form-label text-graphite">Music interests (optional)</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($allGenres as $idx => $genre):
                                        $id = 'genre_' . $idx;
                                        $checked = in_array($genre, $formData['interests']);
                                        ?>
                                        <input type="checkbox"
                                               class="btn-check"
                                               id="<?php echo $id; ?>"
                                               name="interests[]"
                                               value="<?php echo htmlspecialchars($genre); ?>"
                                            <?php echo $checked ? 'checked' : ''; ?>>
                                        <label class="btn btn-sm btn-outline-graphite rounded-pill" for="<?php echo $id; ?>">
                                            <?php echo htmlspecialchars($genre); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-graphite d-block mt-1">Pick as many as you like.</small>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-accent-blue w-100 justify-content-center py-2 fw-medium my-7 fs-4 lh-lg">
                            Create account
                        </button>
                    </form>

                    <p class="text-center mb-1 d-block fw-medium">
                        By creating an account, you agree with our
                        <a class="link-accent-blue" href="privacy-policy.html">Privacy</a> and
                        <a class="link-accent-blue" href="terms.html">Terms</a>.
                    </p>
                    <p class="mb-0 fw-medium text-center">
                        Already have an account?
                        <a class="link-accent-blue" href="sign-in.php">Sign In</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    // Client-side validation
    (function() {
        'use strict';

        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        const forms = document.querySelectorAll('.needs-validation');

        // Loop over them and prevent submission
        Array.from(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                form.classList.add('was-validated');
            }, false);
        });

        // Phone number input restriction
        const phoneInput = document.getElementById('phone_number');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9 +-]/g, '');
            });
        }

        // Password validation
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function(e) {
                this.setCustomValidity('');
                if (this.validity.patternMismatch) {
                    this.setCustomValidity('Password must include at least one uppercase letter, one lowercase letter, and one number.');
                }
            });
        }

        // Scroll to and focus on first error field
        document.addEventListener('DOMContentLoaded', function() {
            const firstError = document.querySelector('.is-invalid');
            if (firstError) {
                // Scroll to the error message at the top if it exists
                const errorAlert = document.querySelector('.alert-danger');
                if (errorAlert) {
                    errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    window.scrollBy(0, -50); // Adjust scroll position slightly up
                } else {
                    // Otherwise scroll to the first error field
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    window.scrollBy(0, -50); // Adjust scroll position slightly up
                }
                firstError.focus();
            }
        });

        // Add red border to invalid form controls when form is submitted
        const form = document.querySelector('.needs-validation');
        if (form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    // Add was-validated class to show validation messages
                    form.classList.add('was-validated');
                    
                    // Find first invalid field and scroll to it
                    const firstInvalid = form.querySelector(':invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        window.scrollBy(0, -50);
                        firstInvalid.focus();
                    }
                }
            }, false);
        }
    })();
</script>
</body>
</html>