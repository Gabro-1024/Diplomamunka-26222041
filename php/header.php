<?php
// header.php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db_connect.php';

$current_page = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isUserLoggedIn();
$isOrganizer = false;
$userName = '';

if ($isLoggedIn) {
    $userName = $_SESSION['first_name'] ?? 'User';
    $userId = $_SESSION['user_id'] ?? 0;
    
    // Check if user is an organizer
    if ($userId) {
        $pdo = db_connect();
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $isOrganizer = ($user && $user['role'] === 'organizer');
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Tickets at Gábor - Fesztiváljegyek'; ?></title>
    <link rel="shortcut icon" type="image/svg+xml" href="/Diplomamunka-26222041/assets/images/logos/favicon.svg">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/Diplomamunka-26222041/assets/css/styles.css">
    
    <!-- Iconify -->
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JS -->
    <script src="/Diplomamunka-26222041/assets/js/custom.js"></script>
    
    <?php if (isset($custom_styles)) echo $custom_styles; ?>
</head>
<body>
<!-- Header -->
<header class="header border-4 border-primary border-top position-fixed start-0 top-0 w-100">
    <div class="container">
        <div class="header-wrapper d-flex align-items-center justify-content-between">
            <div class="logo">
                <a href="http://localhost:63342/Diplomamunka-26222041/php/index.php" class="logo-white">
                    <img src="http://localhost:63342/Diplomamunka-26222041/assets/images/logos/logo-white.svg" alt="logo" class="img-fluid">
                </a>
                <a href="http://localhost:63342/Diplomamunka-26222041/php/index.php" class="logo-dark">
                    <img src="http://localhost:63342/Diplomamunka-26222041/assets/images/logos/logo-dark.svg" alt="logo" class="img-fluid">
                </a>
            </div>
            <div class="d-flex align-items-center gap-4">

                <div class="btn-group">
                    <button
                        class="btn btn-secondary toggle-menu round-45 p-2 d-flex align-items-center justify-content-center bg-white rounded-circle"
                        type="button" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                        <iconify-icon icon="solar:hamburger-menu-line-duotone" class="menu-icon fs-8 text-dark"></iconify-icon>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end p-4">
                        <div class="d-flex flex-column gap-6">
                            <div class="hstack justify-content-between border-bottom pb-6">
                                <p class="mb-0 fs-5 text-dark">Menu</p>
                                <button type="button" class="btn-close opacity-75" aria-label="Close"></button>
                            </div>
                            <div class="d-flex flex-column gap-3">
                                <ul class="header-menu list-unstyled mb-0 d-flex flex-column gap-2">
                                    <li class="header-item">
                                        <a href="http://localhost/Diplomamunka-26222041/php/index.php" class="header-link hstack gap-2 fs-7 fw-bold text-dark <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                                            <img src="http://localhost:63342/Diplomamunka-26222041/assets/images/svgs/secondary-leaf.svg" alt="" width="20" height="20" class="img-fluid animate-spin">
                                            Home
                                        </a>
                                    </li>
                                    <li class="header-item">
                                        <a href="http://localhost/Diplomamunka-26222041/php/about-us.php" class="header-link hstack gap-2 fs-7 fw-bold text-dark <?php echo ($current_page == 'about-us.php') ? 'active' : ''; ?>">
                                            <img src="http://localhost/Diplomamunka-26222041/assets/images/svgs/secondary-leaf.svg" alt="" width="20" height="20" class="img-fluid animate-spin">
                                            About us
                                        </a>
                                    </li>
                                    <li class="header-item">
                                        <a href="http://localhost/Diplomamunka-26222041/php/festivals.php" class="header-link hstack gap-2 fs-7 fw-bold text-dark <?php echo ($current_page == 'festivals.php') ? 'active' : ''; ?>">
                                            <img src="http://localhost/Diplomamunka-26222041/assets/images/svgs/secondary-leaf.svg" alt="" width="20" height="20" class="img-fluid animate-spin">
                                            Festivals
                                        </a>
                                    </li>
                                    <li class="header-item">
                                        <a href="http://localhost/Diplomamunka-26222041/php/locations.php" class="header-link hstack gap-2 fs-7 fw-bold text-dark <?php echo ($current_page == 'locations.php') ? 'active' : ''; ?>">
                                            <img src="http://localhost/Diplomamunka-26222041/assets/images/svgs/secondary-leaf.svg" alt="" width="20" height="20" class="img-fluid animate-spin">
                                            Venues
                                        </a>
                                    </li>
                                    <li class="header-item">
                                        <a href="http://localhost/Diplomamunka-26222041/php/FAQ.php" class="header-link hstack gap-2 fs-7 fw-bold text-dark <?php echo ($current_page == 'FAQ.php') ? 'active' : ''; ?>">
                                            <img src="http://localhost/Diplomamunka-26222041/assets/images/svgs/secondary-leaf.svg" alt="" width="20" height="20" class="img-fluid animate-spin">
                                            FAQ
                                        </a>
                                    </li>
                                    <li class="header-item">
                                        <a href="http://localhost/Diplomamunka-26222041/php/contact.php" class="header-link hstack gap-2 fs-7 fw-bold text-dark <?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>">
                                            <img src="http://localhost/Diplomamunka-26222041/assets/images/svgs/secondary-leaf.svg" alt="" width="20" height="20" class="img-fluid animate-spin">
                                            Contact
                                        </a>
                                    </li>
                                </ul>
                                <?php if ($isLoggedIn): ?>
                                <div class="d-flex flex-column gap-2">
                                    <?php if ($isOrganizer): ?>
                                    <a href="http://localhost/Diplomamunka-26222041/php/organizer_sites/myevents.php" class="btn btn-accent-blue text-white fs-6 px-3 py-2 hstack gap-2 align-items-center">
                                        <iconify-icon icon="lucide:calendar-days" class="fs-6"></iconify-icon>
                                        <span>My Events</span>
                                    </a>
                                    <?php else: ?>
                                    <a href="http://localhost/Diplomamunka-26222041/php/raver_sites/profile.php" class="btn btn-accent-blue text-white fs-6 px-3 py-2 hstack gap-2 align-items-center">
                                        <iconify-icon icon="lucide:user" class="fs-6"></iconify-icon>
                                        <span>Profile</span>
                                    </a>
                                    <?php endif; ?>
                                    <a href="http://localhost/Diplomamunka-26222041/php/logout.php" class="btn btn-danger text-white fs-6 px-3 py-2 hstack gap-2 align-items-center" style="background-color: #dc3545; border-color: #dc3545;">
                                        <i class="fas fa-sign-out-alt"></i>
                                        <span>Logout</span>
                                    </a>
                                </div>
                                <?php else: ?>
                                <div class="hstack gap-3">
                                    <a href="sign-in.php" class="btn btn-outline-light fs-6 bg-white px-3 py-2 text-dark w-50 hstack justify-content-center">Sign In</a>
                                    <a href="sign-up.php" class="btn btn-dark text-white fs-6 bg-dark px-3 py-2 w-50 hstack justify-content-center">Sign Up</a>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a class="text-dark" href="tel:+381-800-123-1234">+381-800-123-1234</a>
                                <a class="fs-8 text-dark fw-bold" href="mailto:info@tickets.at.gabor.com">info@tickets.at.gabor.com</a>
                            </div>
                        </div>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>