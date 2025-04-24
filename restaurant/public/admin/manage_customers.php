<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
secureSessionStart();

// Restrict to admins
if (!isset($_SESSION['customer_id']) || !$_SESSION['is_admin']) {
    header('Location: ../public/login.php');
    exit;
}

// Generate CSRF token
$csrf_token = generateCsrfToken();
$active_page = 'manage_customers.php';

// Initialize session messages
$_SESSION['success_message'] = $_SESSION['success_message'] ?? '';
$_SESSION['error_message'] = $_SESSION['error_message'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        $errors = [];

        if ($action === 'add' || $action === 'edit') {
            $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS));
            $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
            $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS));
            $password = $_POST['password'] ?? '';
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;

            if (empty($username)) {
                $errors[] = 'Username is required.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email address.';
            }
            if ($action === 'add' && empty($password)) {
                $errors[] = 'Password is required for new users.';
            }

            if (empty($errors)) {
                try {
                    if ($action === 'add') {
                        // Check for duplicate username or email
                        $stmt = $db->prepare('SELECT COUNT(*) FROM customers WHERE username = ? OR email = ?');
                        $stmt->execute([$username, $email]);
                        if ($stmt->fetchColumn() > 0) {
                            $errors[] = 'Username or email already exists.';
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $db->prepare('INSERT INTO customers (username, email, phone, password, is_admin) VALUES (?, ?, ?, ?, ?)');
                            $stmt->execute([$username, $email, $phone ?: null, $hashed_password, $is_admin]);
                            $_SESSION['success_message'] = 'User added successfully!';
                        }
                    } elseif ($action === 'edit') {
                        $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
                        if ($customer_id) {
                            // Check for duplicate username or email (excluding current customer)
                            $stmt = $db->prepare('SELECT COUNT(*) FROM customers WHERE (username = ? OR email = ?) AND customer_id != ?');
                            $stmt->execute([$username, $email, $customer_id]);
                            if ($stmt->fetchColumn() > 0) {
                                $errors[] = 'Username or email already exists.';
                            } else {
                                $params = [$username, $email, $phone ?: null, $is_admin, $customer_id];
                                $sql = 'UPDATE customers SET username = ?, email = ?, phone = ?, is_admin = ?';
                                if (!empty($password)) {
                                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                    $sql .= ', password = ?';
                                    $params[] = $hashed_password;
                                }
                                $sql .= ' WHERE customer_id = ?';
                                $stmt = $db->prepare($sql);
                                $stmt->execute($params);
                                $_SESSION['success_message'] = 'User updated successfully!';
                            }
                        } else {
                            $errors[] = 'Invalid customer ID.';
                        }
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
            if ($customer_id) {
                try {
                    // Prevent deleting self
                    if ($customer_id === $_SESSION['customer_id']) {
                        $errors[] = 'You cannot delete your own account.';
                    } else {
                        // Check if last admin
                        $stmt = $db->prepare('SELECT COUNT(*) FROM customers WHERE is_admin = 1');
                        $stmt->execute();
                        $admin_count = $stmt->fetchColumn();
                        $stmt = $db->prepare('SELECT is_admin FROM customers WHERE customer_id = ?');
                        $stmt->execute([$customer_id]);
                        $is_admin = $stmt->fetchColumn();
                        if ($is_admin && $admin_count <= 1) {
                            $errors[] = 'Cannot delete the last admin account.';
                        } else {
                            $stmt = $db->prepare('DELETE FROM customers WHERE customer_id = ?');
                            $stmt->execute([$customer_id]);
                            $_SESSION['success_message'] = 'User deleted successfully!';
                        }
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            } else {
                $errors[] = 'Invalid customer ID.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['error_message'] = implode(' ', $errors);
        }
    }

    // Redirect to prevent form resubmission
    header('Location: manage_customers.php');
    exit;
}

// Fetch all customers
$stmt = $db->query('SELECT customer_id, username, email, phone, is_admin FROM customers ORDER BY username');
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Restaurant System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/css/reset.css">
    <style>
        .admin-container-customers {
            display: flex;
            background-color: #f9f9f9;
            min-height: 80vh;
            transition: padding-top 0.3s ease;
        }

        .dashboard-content {
            flex: 1;
            max-width: min(1200px, 94vw);
            margin: clamp(1rem, 2vw, 1.5rem) auto;
            margin-left: calc(250px + 1.5rem); /* Sidebar (250px) + gap */
            padding: clamp(1rem, 2vw, 1.5rem);
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, margin 0.3s ease, padding 0.3s ease;
        }

        .dashboard-content:hover {
            transform: translateY(-2px);
        }

        .dashboard-content h1 {
            color: #a52a2a;
            font-size: clamp(1.6rem, 3.5vw, 2rem);
            margin-bottom: 1.2rem;
            text-align: center;
            font-weight: 600;
        }

        .table-container h2,
        .form-container h2,
        .modal-content h2 {
            color: #a52a2a;
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            margin: 1.2rem 0 0.8rem;
            font-weight: 500;
        }

        .form-container {
            background: #fff;
            padding: clamp(0.8rem, 1.5vw, 1.2rem);
            border-radius: 5px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 0.8rem;
        }

        .form-group label {
            display: block;
            color: #333;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            margin-bottom: 0.4rem;
        }

        .form-group input {
            width: 100%;
            padding: clamp(0.5rem, 1.2vw, 0.8rem);
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input:focus {
            border-color: #a52a2a;
            box-shadow: 0 0 4px rgba(165, 42, 42, 0.2);
            outline: none;
        }

        .form-group.checkbox {
            display: flex;
            align-items: center;
        }

        .form-group.checkbox label {
            margin-left: 0.4rem;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
        }

        .form-group.checkbox input {
            width: auto;
            margin-right: 0.4rem;
        }

        .btn {
            background-color: #a52a2a;
            color: #fff;
            padding: clamp(0.5rem, 1.2vw, 0.8rem) clamp(0.8rem, 1.8vw, 1.2rem);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            transition: background-color 0.3s, transform 0.3s, box-shadow 0.3s;
            display: inline-flex;
            align-items: center;
        }

        .btn:hover {
            background-color: #7a1717;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
        }

        .btn i {
            margin-right: 0.4rem;
            font-size: clamp(1rem, 2.2vw, 1.2rem);
        }

        .table-container {
            background: #fff;
            padding: clamp(0.8rem, 1.5vw, 1.2rem);
            border-radius: 5px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            overflow-x: auto;
            position: relative;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
        }

        .table th,
        .table td {
            padding: clamp(0.5rem, 1.2vw, 0.8rem);
            border: 1px solid #e0e0e0;
            text-align: left;
            min-width: clamp(70px, 12vw, 90px);
        }

        .table th {
            background: linear-gradient(145deg, #a52a2a 0%, #7a1717 100%);
            color: #fff;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table td {
            background: #fafafa;
            transition: background 0.3s;
            word-break: break-word;
        }

        .table tr:nth-child(even) td {
            background: #f5f5f5;
        }

        .table tr:hover td {
            background: #f0f0f0;
        }

        .table td.actions {
            text-align: center;
            white-space: nowrap;
        }

        .table td.admin-status {
            text-align: center;
        }

        .table .btn {
            margin: 0.3rem 0.4rem;
            padding: clamp(0.3rem, 0.8vw, 0.5rem) clamp(0.6rem, 1.2vw, 0.8rem);
            font-size: clamp(0.85rem, 1.8vw, 1rem);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #fff;
            padding: clamp(0.8rem, 1.5vw, 1.2rem);
            border-radius: 6px;
            box-shadow: 0 5px 14px rgba(0, 0, 0, 0.15);
            max-width: min(400px, 92vw);
            width: 92%;
        }

        .modal-content h2 {
            color: #a52a2a;
            margin-bottom: 0.8rem;
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
        }

        .modal-content .btn {
            margin-top: 0.4rem;
        }

        .toast {
            position: fixed;
            bottom: clamp(8px, 1.5vw, 12px);
            right: clamp(8px, 1.5vw, 12px);
            padding: clamp(0.4rem, 1vw, 0.6rem) clamp(0.8rem, 1.5vw, 1rem);
            border-radius: 4px;
            color: #fff;
            font-size: clamp(0.85rem, 1.8vw, 1rem);
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 1100;
        }

        .toast.success {
            background: #28a745;
        }

        .toast.error {
            background: #dc3545;
        }

        .toast.active {
            opacity: 1;
        }

        /* Scroll shadow for table */
        .table-container::before,
        .table-container::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 12px;
            pointer-events: none;
            z-index: 11;
            transition: opacity 0.3s;
        }

        .table-container::before {
            left: 0;
            background: linear-gradient(to right, rgba(0, 0, 0, 0.06), transparent);
            opacity: 0;
        }

        .table-container::after {
            right: 0;
            background: linear-gradient(to left, rgba(0, 0, 0, 0.06), transparent);
            opacity: 0;
        }

        .table-container.scroll-left::before {
            opacity: 1;
        }

        .table-container.scroll-right::after {
            opacity: 1;
        }

        /* Sidebar width adjustment */
        @media (max-width: 768px) {
            .dashboard-content {
                margin-left: calc(200px + 1rem); /* Sidebar (200px) + gap */
                max-width: 95vw;
                padding: clamp(0.8rem, 1.5vw, 1.2rem);
            }

            .dashboard-content h1 {
                font-size: clamp(1.5rem, 3.2vw, 1.9rem);
            }

            .table-container h2,
            .form-container h2,
            .modal-content h2 {
                font-size: clamp(1.1rem, 2.2vw, 1.4rem);
            }

            .form-group label {
                font-size: clamp(0.85rem, 1.8vw, 1rem);
            }

            .form-group input {
                padding: clamp(0.4rem, 1vw, 0.6rem);
                font-size: clamp(0.85rem, 1.8vw, 1rem);
            }

            .form-group.checkbox label {
                font-size: clamp(0.85rem, 1.8vw, 1rem);
            }

            .btn {
                padding: clamp(0.4rem, 1vw, 0.6rem) clamp(0.6rem, 1.5vw, 1rem);
                font-size: clamp(0.85rem, 1.8vw, 1rem);
            }

            .btn i {
                font-size: clamp(0.9rem, 2vw, 1.1rem);
            }

            .table th,
            .table td {
                padding: clamp(0.4rem, 1vw, 0.6rem);
                font-size: clamp(0.85rem, 1.8vw, 1rem);
                min-width: clamp(65px, 11vw, 85px);
            }

            .table .btn {
                margin: 0.2rem 0.3rem;
                padding: clamp(0.25rem, 0.6vw, 0.4rem) clamp(0.5rem, 1vw, 0.7rem);
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
            }

            .modal-content {
                padding: clamp(0.6rem, 1.2vw, 1rem);
                max-width: min(380px, 94vw);
            }

            .toast {
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
            }
        }

        /* Navbar transition */
        @media (max-width: 600px) {
            .admin-container-customers {
                flex-direction: column;
                padding-top: 60px; /* Space for navbar */
            }

            .dashboard-content {
                margin-left: 0;
                margin: clamp(0.8rem, 1.8vw, 1rem);
                max-width: 96vw;
                padding: clamp(0.6rem, 1.2vw, 1rem);
            }

            .dashboard-content h1 {
                font-size: clamp(1.4rem, 3vw, 1.8rem);
            }

            .form-container {
                padding: clamp(0.6rem, 1.2vw, 1rem);
            }

            .form-container h2 {
                font-size: clamp(1rem, 2vw, 1.3rem);
            }

            .form-group label {
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
            }

            .form-group input {
                padding: clamp(0.3rem, 0.8vw, 0.5rem);
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
            }

            .form-group.checkbox label {
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
            }

            .btn {
                padding: clamp(0.3rem, 0.8vw, 0.5rem) clamp(0.5rem, 1.2vw, 0.8rem);
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
            }

            .btn i {
                font-size: clamp(0.8rem, 1.6vw, 1rem);
            }

            .table-container {
                padding: clamp(0.6rem, 1.2vw, 1rem);
            }

            .table-container h2 {
                font-size: clamp(1rem, 2vw, 1.3rem);
            }

            /* Stacked table layout */
            .table {
                display: block;
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
            }

            .table thead {
                display: none;
            }

            .table tbody,
            .table tr {
                display: block;
            }

            .table tr {
                margin-bottom: 0.8rem;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                background: #fafafa;
            }

            .table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: clamp(0.3rem, 0.8vw, 0.5rem);
                border: none;
                border-bottom: 1px solid #e0e0e0;
                min-width: 0;
                text-align: right;
            }

            .table td:last-child {
                border-bottom: none;
            }

            .table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #a52a2a;
                text-align: left;
                flex: 1;
                white-space: nowrap;
                margin-right: 0.5rem;
            }

            .table td.actions {
                display: flex;
                justify-content: center;
                flex-wrap: wrap;
            }

            .table td.actions::before {
                content: none;
            }

            .table td.admin-status {
                text-align: right;
            }

            .table .btn {
                margin: clamp(0.2rem, 0.5vw, 0.3rem);
                padding: clamp(0.2rem, 0.5vw, 0.3rem) clamp(0.4rem, 0.8vw, 0.6rem);
                font-size: clamp(0.75rem, 1.4vw, 0.9rem);
            }

            .modal-content {
                padding: clamp(0.5rem, 1vw, 0.8rem);
                max-width: 95vw;
            }

            .modal-content h2 {
                font-size: clamp(1rem, 2vw, 1.3rem);
            }

            .toast {
                padding: clamp(0.3rem, 0.8vw, 0.5rem) clamp(0.6rem, 1.2vw, 0.8rem);
                font-size: clamp(0.75rem, 1.4vw, 0.9rem);
                bottom: clamp(5px, 1vw, 8px);
                right: clamp(5px, 1vw, 8px);
            }
        }
    </style>
</head>
<body>
    <section class="admin-container-customers">
        <?php include '../../includes/admin_sidebar.php'; ?>
        <div class="dashboard-content">
            <h1>Manage Users</h1>

            <!-- Add User Form -->
            <div class="form-container">
                <h2>Add New User</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Phone (Optional)</label>
                        <input type="text" id="phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group checkbox">
                        <input type="checkbox" id="is_admin" name="is_admin">
                        <label for="is_admin">Is Admin</label>
                    </div>
                    <button type="submit" class="btn"><i class="fas fa-plus"></i> Add User</button>
                </form>
            </div>

            <!-- Users List -->
            <div class="table-container">
                <h2>Current Users</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th class="admin-status">Admin</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr><td colspan="5">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td data-label="Username"><?php echo sanitize($customer['username']); ?></td>
                                    <td data-label="Email"><?php echo sanitize($customer['email']); ?></td>
                                    <td data-label="Phone"><?php echo sanitize($customer['phone'] ?: 'N/A'); ?></td>
                                    <td data-label="Admin" class="admin-status"><?php echo $customer['is_admin'] ? 'Yes' : 'No'; ?></td>
                                    <td data-label="Actions" class="actions">
                                        <button class="btn edit-btn" data-customer='<?php echo json_encode($customer); ?>'><i class="fas fa-edit"></i> Edit</button>
                                        <button class="btn delete-btn" data-customer-id="<?php echo $customer['customer_id']; ?>"><i class="fas fa-trash"></i> Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            
    </section>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>
        </div>
        <!-- Edit Customer Modal -->
        <div class="modal" id="edit-customer-modal">
            <div class="modal-content">
                <h2>Edit User</h2>
                <form method="POST" id="edit-customer-form">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="customer_id" id="edit-customer-id">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <div class="form-group">
                        <label for="edit-username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="edit-username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="edit-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-phone"><i class="fas fa-phone"></i> Phone (Optional)</label>
                        <input type="text" id="edit-phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="edit-password"><i class="fas fa-lock"></i> Password (Leave blank to keep unchanged)</label>
                        <input type="password" id="edit-password" name="password">
                    </div>
                    <div class="form-group checkbox">
                        <input type="checkbox" id="edit-is_admin" name="is_admin">
                        <label for="edit-is_admin">Is Admin</label>
                    </div>
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Update User</button>
                    <button type="button" class="btn" id="cancel-edit-btn"><i class="fas fa-times"></i> Cancel</button>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal" id="delete-customer-modal">
            <div class="modal-content">
                <h2>Confirm Deletion</h2>
                <p>Are you sure you want to delete this user?</p>
                <form method="POST" id="delete-customer-form">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="customer_id" id="delete-customer-id">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <button type="submit" class="btn"><i class="fas fa-trash"></i> Confirm</button>
                    <button type="button" class="btn" id="cancel-delete-btn"><i class="fas fa-times"></i> Cancel</button>
                </form>
            </div>
        </div>

    <script>
        // Show toast notification
        function showToast(message, type) {
            const toast = document.getElementById("toast");
            if (toast) {
                toast.textContent = message;
                toast.className = `toast ${type} active`;
                setTimeout(() => {
                    toast.className = "toast";
                }, 2000);
            }
        }

        // Display session-based toast messages on page load
        window.addEventListener("load", () => {
            const successMessage = "<?php echo addslashes($_SESSION['success_message']); ?>";
            const errorMessage = "<?php echo addslashes($_SESSION['error_message']); ?>";
            if (successMessage) {
                showToast(successMessage, "success");
            } else if (errorMessage) {
                showToast(errorMessage, "error");
            }
            // Clear session messages
            <?php unset($_SESSION['success_message'], $_SESSION['error_message']); ?>
        });

        // Edit customer modal
        const editModal = document.getElementById("edit-customer-modal");
        const editForm = document.getElementById("edit-customer-form");
        const cancelEditBtn = document.getElementById("cancel-edit-btn");

        document.querySelectorAll(".edit-btn").forEach(button => {
            button.addEventListener("click", () => {
                const customer = JSON.parse(button.getAttribute("data-customer"));
                document.getElementById("edit-customer-id").value = customer.customer_id;
                document.getElementById("edit-username").value = customer.username;
                document.getElementById("edit-email").value = customer.email;
                document.getElementById("edit-phone").value = customer.phone || "";
                document.getElementById("edit-is_admin").checked = customer.is_admin == 1;
                editModal.classList.add("active");
            });
        });

        cancelEditBtn.addEventListener("click", () => {
            editModal.classList.remove("active");
        });

        editModal.addEventListener("click", (e) => {
            if (e.target === editModal) {
                editModal.classList.remove("active");
            }
        });

        // Delete customer modal
        const deleteModal = document.getElementById("delete-customer-modal");
        const deleteForm = document.getElementById("delete-customer-form");
        const cancelDeleteBtn = document.getElementById("cancel-delete-btn");

        document.querySelectorAll(".delete-btn").forEach(button => {
            button.addEventListener("click", () => {
                const customerId = button.getAttribute("data-customer-id");
                document.getElementById("delete-customer-id").value = customerId;
                deleteModal.classList.add("active");
            });
        });

        cancelDeleteBtn.addEventListener("click", () => {
            deleteModal.classList.remove("active");
        });

        deleteModal.addEventListener("click", (e) => {
            if (e.target === deleteModal) {
                deleteModal.classList.remove("active");
            }
        });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>