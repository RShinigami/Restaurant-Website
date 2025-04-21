<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
secureSessionStart();

// Destroy session
$_SESSION = []; // Clear all session variables
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}
session_destroy();

// Start a new session for CSRF token or future use
secureSessionStart();

// Optional: Redirect after a delay (handled by JavaScript)
$redirect_delay = 10; // Seconds before redirect
?>

<?php include '../includes/header.php'; ?>
<section class="form-container">
    <h1>Logged Out</h1>
    <p class="success">You have been successfully logged out.</p>
    <p>You will be redirected to the homepage in <span id="countdown"><?php echo $redirect_delay; ?></span> seconds, or click below to return now.</p>
    <div class="hero-buttons">
        <a href="index.php" class="logout-btn">Return to Homepage</a>
        <a href="login.php" class="logout-btn">Log In Again</a>
    </div>
</section>

<script>
    // Countdown and auto-redirect
    let seconds = <?php echo $redirect_delay; ?>;
    const countdownElement = document.getElementById('countdown');
    const redirect = () => {
        window.location.href = 'index.php';
    };
    const updateCountdown = () => {
        seconds--;
        countdownElement.textContent = seconds;
        if (seconds <= 0) {
            redirect();
        }
    };
    if (seconds > 0) {
        setInterval(updateCountdown, 1000);
    }
</script>

<?php include '../includes/footer.php'; ?>