<?php
// This file contains the sidebar HTML that's included in all admin pages
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h3>Tickets @ Gábor</h3>
        <p class="text-muted">Admin Panel</p>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class='bx bxs-dashboard'></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="events.php" class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['events.php', 'event_edit.php']) ? 'active' : ''; ?>">
                <i class='bx bxs-calendar-event'></i> Events
            </a>
        </li>
        <li class="nav-item">
            <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                <i class='bx bxs-user'></i> Users
            </a>
        </li>
        <li class="nav-item mt-4">
            <a href="../index.php" class="nav-link">
                <i class='bx bx-home'></i> Back to Site
            </a>
        </li>
        <li class="nav-item">
            <a href="../logout.php" class="nav-link text-danger">
                <i class='bx bx-log-out'></i> Logout
            </a>
        </li>
    </ul>
</div>
