<?php
// views/layout/header.php
require_once __DIR__ . '/../../includes/auth.php';
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
        const CURRENCY_SYMBOL = '<?php echo isLoggedIn() ? getCurrencySymbol() : "$"; ?>';
    </script>
    <link rel="icon" type="image/jpeg" href="logo.jpg">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <header>
        <div class="container">
            <h1 class="logo-text"><img src="logo.jpg" alt="AlphaBank Logo" class="logo-img">AlphaBank</h1>
            <nav>
                <?php if (isLoggedIn()): ?>
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
                        <button class="notification-bell-btn">🔔</button>
                        <span class="notification-badge" id="nav-badge">0</span>
                        <div class="notification-dropdown" id="notification-dropdown">
                            <div class="notification-header">
                                Notifications
                                <span id="mark-read" style="font-size: 0.7rem; color: var(--secondary-color); cursor: pointer;">Mark all as read</span>
                            </div>
                            <div class="notification-list" id="notification-list">
                                <div class="empty-notifications">Loading...</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <a href="logout.php" class="btn-logout">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="<?php echo $current_page == 'login.php' ? 'active' : ''; ?>">Login</a>
                    <a href="register.php" class="<?php echo $current_page == 'register.php' ? 'active' : ''; ?>">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container">
