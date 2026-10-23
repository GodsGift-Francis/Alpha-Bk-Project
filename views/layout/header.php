<?php
// views/layout/header.php
require_once __DIR__ . '/../../includes/auth.php';
$logged_in = isLoggedIn();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlphaBank - Secure Modern Banking</title>
    <meta name="csrf-token" content="<?php echo getCsrfToken(); ?>">
    <script>
        const CURRENCY_SYMBOL = '<?php echo $logged_in ? getCurrencySymbol() : "$"; ?>';
    </script>
    <link rel="icon" type="image/jpeg" href="logo.jpg">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header>
        <div class="container">
            <h1 class="logo-text"><img src="logo.jpg" alt="AlphaBank Logo" class="logo-img">AlphaBank</h1>
            <button class="nav-toggle" type="button" aria-controls="primary-nav" aria-expanded="false">Menu</button>
            <nav id="primary-nav" aria-label="Main navigation">
                <?php if ($logged_in): ?>
                    <?php if (isAdmin()): ?>
                        <a href="admin.php" class="<?php echo $current_page == 'admin.php' ? 'active' : ''; ?>" style="color: var(--warning-color);">Admin Panel</a>
                    <?php else: ?>
                        <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
                    <?php endif; ?>

                    <?php if (!isAdmin()): ?>
                        <a href="deposit.php" class="<?php echo $current_page == 'deposit.php' ? 'active' : ''; ?>">Deposit</a>
                        <a href="withdraw.php" class="<?php echo $current_page == 'withdraw.php' ? 'active' : ''; ?>">Withdraw</a>
                        <a href="transfer.php" class="<?php echo $current_page == 'transfer.php' ? 'active' : ''; ?>">Transfer</a>
                    <?php endif; ?>
                    
                    <a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">Settings</a>
                    
                    <?php if (!isAdmin()): ?>
                    <div class="notification-wrapper" id="notification-trigger">
                        <button type="button" class="notification-bell-btn" aria-label="Notifications" aria-expanded="false" aria-controls="notification-dropdown">🔔</button>
                        <span class="notification-badge" id="nav-badge">0</span>
                        <div class="notification-dropdown" id="notification-dropdown">
                            <div class="notification-header">
                                Notifications
                                <button type="button" id="mark-read" class="text-button">Mark all as read</button>
                            </div>
                            <div class="notification-list" id="notification-list">
                                <div class="empty-notifications">Loading...</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form action="logout.php" method="post" class="logout-form">
                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                        <button type="submit" class="btn-logout">Logout</button>
                    </form>
                <?php else: ?>
                    <a href="login.php" class="<?php echo $current_page == 'login.php' ? 'active' : ''; ?>">Login</a>
                    <a href="register.php" class="<?php echo $current_page == 'register.php' ? 'active' : ''; ?>">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container" id="main-content">
