<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
secureSessionStart();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate inputs
        if (empty($username) || empty($password)) {
            $errors[] = 'Username and password are required.';
        } elseif (!checkLoginAttempts($db, $username)) {
            $errors[] = 'Too many login attempts. Try again in 15 minutes.';
        } else {
            // Log attempt
            logLoginAttempt($db, $username);

            // Check credentials
            $stmt = $db->prepare('SELECT customer_id, username, password, is_admin FROM customers WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['customer_id'] = $user['customer_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = (bool)$user['is_admin'];
                session_regenerate_id(true);
                // Redirect based on role
                $redirect = $user['is_admin'] ? 'admin/dashboard.php' : 'index.php';
                header("Location: $redirect");
                exit;
            } else {
                $errors[] = 'Invalid username or password.';
            }
        }
    }
}
?>

<?php include '../includes/header.php'; ?>
<section class="form-container">
    <h1>Login</h1>
    <?php if ($errors): ?>
        <ul class="errors">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form id="loginForm" method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?php echo isset($username) ? $username : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="login-btn">Login</button>
        <a href="#" id="forgot-password">Forgot Password?</a>
    </form>
    <p>Don't have an account? <a href="register.php">Register</a>.</p>
</section>
<?php include '../includes/footer.php'; ?>