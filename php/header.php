<?php
// header.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets at Gábor - Fesztiváljegyek</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<!-- Header -->
<header class="header border-4 border-primary border-top position-fixed start-0 top-0 w-100">
    <div class="container">
        <div class="header-wrapper d-flex align-items-center justify-content-between">
            <div class="logo">
                <a href="index.php" class="logo-white">
                    <img src="../assets/images/logos/logo-white.svg" alt="logo" class="img-fluid">
                </a>
                <a href="index.php" class="logo-dark">
                    <img src="../assets/images/logos/logo-dark.svg" alt="logo" class="img-fluid">
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
                                        <a href="index.php" class="header-link hstack gap-2 fs-7 fw-bold text-dark <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                                            <img src="../assets/images/svgs/secondary-leaf.svg" alt="" width="20" height="20" class="img-fluid animate-spin">
                                            Home
                                        </a>
                                    </li>
                                    <li class="header-item">
                                        <a href="about-us.php" class="header-link hstack gap-2 fs-7 fw-bold text-dark <?php echo ($current_page == 'about-us.php') ? 'active' : ''; ?>">
                                            <img src="../assets/images/svgs/secondary-leaf.svg" alt="" width="20" height="20" class="img-fluid animate-spin">
                                            About us
                                        </a>
                                    </li>
                                    <li class="header-item">
                                        <a href="festivals.php" class="header-link hstack gap-2 fs-7 fw-bold text-dark <?php echo ($current_page == 'festivals.php') ? 'active' : ''; ?>">
                                            <img src="../assets/images/svgs/secondary-leaf.svg" alt="" width="20" height="20" class="img-fluid animate-spin">
                                            Festivals
                                        </a>
                                    </li>
                                    <li class="header-item">
                                        <a href="locations.php" class="header-link hstack gap-2 fs-7 fw-bold text-dark <?php echo ($current_page == 'locations.php') ? 'active' : ''; ?>">
                                            <img src="../assets/images/svgs/secondary-leaf.svg" alt="" width="20" height="20" class="img-fluid animate-spin">
                                            Venues
                                        </a>
                                    </li>
                                    <li class="header-item">
                                        <a href="FAQ.php" class="header-link hstack gap-2 fs-7 fw-bold text-dark <?php echo ($current_page == 'FAQ.php') ? 'active' : ''; ?>">
                                            <img src="../assets/images/svgs/secondary-leaf.svg" alt="" width="20" height="20" class="img-fluid animate-spin">
                                            FAQ
                                        </a>
                                    </li>
                                    <li class="header-item">
                                        <a href="contact.php" class="header-link hstack gap-2 fs-7 fw-bold text-dark <?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>">
                                            <img src="../assets/images/svgs/secondary-leaf.svg" alt="" width="20" height="20" class="img-fluid animate-spin">
                                            Contact
                                        </a>
                                    </li>
                                </ul>
                                <div class="hstack gap-3">
                                    <a href="sign-in.php"
                                       class="btn btn-outline-light fs-6 bg-white px-3 py-2 text-dark w-50 hstack justify-content-center">Sign
                                        In</a>
                                    <a href="sign-up.php"
                                       class="btn btn-dark text-white fs-6 bg-dark px-3 py-2 w-50 hstack justify-content-center">Sign
                                        Up</a>
                                </div>
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