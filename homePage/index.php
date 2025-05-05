<?php
// Static page, no session or database for now
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Tasty Haven</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">Tasty Haven</a>
            <button class="menu-toggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <ul class="nav-links">
                <li><a href="#home" class="nav-link">Home</a></li>
                <li><a href="#about" class="nav-link">About</a></li>
                <li><a href="reserve.php" class="nav-link">Reserve</a></li>
                <li><a href="menu.php" class="nav-link">Menu</a></li>
                <li><a href="login.php" class="nav-link">Login</a></li>
                <li><a href="register.php" class="nav-link">Register</a></li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <h1>Welcome to Tasty Haven</h1>
            <p>Experience delicious meals and cozy dining. Book your table today!</p>
            <a href="reserve.php" class="btn">Reserve Now</a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <h2>About Us</h2>
            <p>Tasty Haven is your go-to place for delightful cuisine and a warm atmosphere. Our chefs craft every dish with love, using fresh ingredients to ensure a memorable dining experience.</p>
        </div>
    </section>

    <!-- Reservation Placeholder -->
    <section id="reserve" class="reserve-section">
        <div class="container">
            <h2>Book a Table</h2>
            <p>Plan your visit by reserving a table in advance. Click below to get started.</p>
            <a href="reserve.php" class="btn">Make a Reservation</a>
        </div>
    </section>

    <!-- Menu Placeholder -->
    <section id="menu" class="menu-section">
        <div class="container">
            <h2>Our Menu</h2>
            <p>Explore our delicious offerings, from appetizers to desserts. Pre-order your favorites!</p>
            <a href="menu.php" class="btn">View Menu</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>© 2025 Tasty Haven. All rights reserved.</p>
            <p>Contact: info@tastyhaven.com | (123) 456-7890</p>
        </div>
    </footer>

    <script src="index.js"></script>
</body>
</html>