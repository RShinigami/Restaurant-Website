<?php
// Static page, no session or database for now
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Tasty Haven</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
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
            <h1>Savor the Moment at Tasty Haven</h1>
            <p class="tagline">Indulge in exquisite flavors and warm hospitality.</p>
            <a href="reserve.php" class="btn btn-primary">Reserve Your Table</a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <h2>Our Story</h2>
            <div class="card">
                <p>At Tasty Haven, we believe dining is an experience. Our chefs use fresh, local ingredients to craft dishes that delight the senses, served in a cozy, welcoming atmosphere.</p>
            </div>
        </div>
    </section>

    <!-- Reservation Placeholder -->
    <section id="reserve" class="reserve-section">
        <div class="container">
            <h2>Book Your Table</h2>
            <div class="card">
                <p>Secure your spot for an unforgettable meal. Reservations are just a click away.</p>
                <a href="reserve.php" class="btn btn-secondary">Make a Reservation</a>
            </div>
        </div>
    </section>

    <!-- Menu Placeholder -->
    <section id="menu" class="menu-section">
        <div class="container">
            <h2>Explore Our Menu</h2>
            <div class="card">
                <p>From savory starters to decadent desserts, our menu has something for everyone.</p>
                <a href="menu.php" class="btn btn-secondary">View Menu</a>
            </div>
        </div>
    </section>

    <!-- Testimonial Section -->
    <section id="testimonial" class="testimonial-section">
        <div class="container">
            <h2>What Our Guests Say</h2>
            <div class="card">
                <p>"Tasty Haven is a gem! The food is phenomenal, and the ambiance is perfect for any occasion."</p>
                <p class="author">– Jane Doe</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h3>Tasty Haven</h3>
                    <p>123 Flavor Street, Food City</p>
                    <p>info@tastyhaven.com</p>
                    <p>(123) 456-7890</p>
                </div>
                <div>
                    <h3>Follow Us</h3>
                    <div class="social-links">
                        <a href="#" class="social-icon">Facebook</a>
                        <a href="#" class="social-icon">Instagram</a>
                        <a href="#" class="social-icon">Twitter</a>
                    </div>
                </div>
            </div>
            <p class="footer-bottom">© 2025 Tasty Haven. All rights reserved.</p>
        </div>
    </footer>

    <script src="index.js"></script>
</body>
</html>