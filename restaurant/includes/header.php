<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
secureSessionStart();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant System</title>
    <link rel="stylesheet" href="/assets/css/reset.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <script src="/assets/js/scripts.js" defer></script>
</head>
<body>
    <header>
        <nav>
            <a href="/public/index.php" class="logo">Restaurant</a>
            <button class="nav-toggle" aria-label="Toggle navigation">☰</button>
            <div class="nav-menu">
                <ul>
                    <li><a href="/public/order.php">Order</a></li>
                    <li><a href="/public/reserve.php">Reserve</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="/public/account.php">Account</a></li>
                        <li><a href="/public/logout.php" class="logout-link">Logout</a></li>
                        <?php if (isAdmin()): ?>
                            <li><a href="/public/admin/dashboard.php">Admin Dashboard</a></li>
                            <li><a href="/public/tables.php">Manage Tables</a></li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li><a href="/public/login.php">Login</a></li>
                        <li><a href="/public/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>
    <main>