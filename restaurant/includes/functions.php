<?php
// Shared PHP functions

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['customer_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

// Generate CSRF token
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Secure session start
ob_start(); // Prevent headers sent error

function secureSessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        session_regenerate_id(true);
    }
}

// Check login attempts (basic rate limiting)
function checkLoginAttempts($db, $username) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempt_time > ?');
    $stmt->execute([$username, date('Y-m-d H:i:s', strtotime('-15 minutes'))]);
    $attempts = $stmt->fetchColumn();
    return $attempts < 5; // Allow 5 attempts in 15 minutes
}

// Log login attempt
function logLoginAttempt($db, $username) {
    $stmt = $db->prepare('INSERT INTO login_attempts (username, attempt_time) VALUES (?, ?)');
    $stmt->execute([$username, date('Y-m-d H:i:s')]);
}
?>

<?php
// Create login_attempts table if not exists
try {
    $db->exec('CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        attempt_time DATETIME NOT NULL
    )');
} catch (PDOException $e) {
    die('Failed to create login_attempts table: ' . $e->getMessage());
}
?>