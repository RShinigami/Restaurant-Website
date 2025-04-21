<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
secureSessionStart();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Server-side validation
        if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = 'Username must be 3-50 characters.';
        }
        if (!isValidEmail($email)) {
            $errors[] = 'Invalid email address.';
        }
        if (empty($phone) || !preg_match('/^\+?\d{7,15}$/', $phone)) {
            $errors[] = 'Invalid phone number (7-15 digits).';
        }
        if (strlen($password) < 8 || !preg_match('/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/', $password)) {
            $errors[] = 'Password must be at least 8 characters with letters and numbers.';
        }
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }

        // Check if username or email exists
        $stmt = $db->prepare('SELECT COUNT(*) FROM customers WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Username or email already exists.';
        }

        // Register user
        if (empty($errors)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO customers (username, password, email, phone, is_admin) VALUES (?, ?, ?, ?, 0)');
            try {
                $stmt->execute([$username, $hashed_password, $email, $phone]);
                $success = 'Registration successful! <a href="login.php">Log in</a>.';
            } catch (PDOException $e) {
                $errors[] = 'Error registering user: ' . $e->getMessage();
            }
        }
    }
}
?>

<?php include '../includes/header.php'; ?>
<section class="form-container">
    <h1>Register</h1>
    <?php if ($success): ?>
        <p class="success"><?php echo $success; ?></p>
    <?php endif; ?>
    <?php if ($errors): ?>
        <ul class="errors">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form id="registerForm" method="POST" action="register.php">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?php echo isset($username) ? $username : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo isset($email) ? $email : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" value="<?php echo isset($phone) ? $phone : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Log in</a>.</p>
</section>
<?php include '../includes/footer.php'; ?>