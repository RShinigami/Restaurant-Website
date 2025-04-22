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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        die('Invalid CSRF token.');
    }

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
            $errors[] = 'Password is required for new customers.';
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
                        echo '<script>showToast("Customer added successfully!", "success"); setTimeout(() => location.reload(), 2000);</script>';
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
                            echo '<script>showToast("Customer updated successfully!", "success"); setTimeout(() => location.reload(), 2000);</script>';
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
                        echo '<script>showToast("Customer deleted successfully!", "success"); setTimeout(() => location.reload(), 2000);</script>';
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
        $error_message = implode(' ', $errors);
        echo '<script>showToast("' . addslashes($error_message) . '", "error");</script>';
    }
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
    <title>Manage Customers - Restaurant System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <style>
        /* Page-specific styles */
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: linear-gradient(135deg, #f9f9f9 0%, #e0e0e0 100%);
            margin: 0;
        }

        .admin-container {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
            min-height: 100vh;
            background: linear-gradient(135deg, #f9f9f9 0%, #e0e0e0 100%);
        }

        .dashboard-content {
            flex: 1;
            max-width: 1500px; /* Wider for table */
            margin: 3rem auto;
            margin-left: 18%; /* Sidebar width + gap */
            padding: 2.5rem;
            background: linear-gradient(145deg, #ffffff 0%, #f0f0f0 100%);
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }

        .dashboard-content:hover {
            transform: translateY(-5px);
        }

        .dashboard-content h1 {
            color: #a52a2a;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 600;
        }

        h2 {
            color: #a52a2a;
            font-size: 1.5rem;
            margin: 1.5rem 0 1rem;
            font-weight: 500;
        }

        .form-container {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            width: 100%;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            color: #333;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 94%;
            padding: 0.8rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: #a52a2a;
            outline: none;
        }

        .form-group.checkbox {
            display: flex;
            align-items: center;
        }

        .form-group.checkbox label {
            margin-left: 0;
            font-size: 1rem;
        }
        .form-group.checkbox input {
            width: auto;
            margin-right: 0.5rem;
        }

        .btn {
            background-color: #a52a2a;
            color: #fff;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
        }

        .btn:hover {
            background-color: #7a1717;
            transform: translateY(-2px);
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .table-container {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1rem;
        }

        .table th, .table td {
            padding: 1.2rem;
            border: 1px solid #e0e0e0;
            text-align: left;
        }

        .table th {
            background: linear-gradient(145deg, #a52a2a 0%, #7a1717 100%);
            color: #fff;
            font-weight: 600;
        }

        .table td {
            background: #fafafa;
            transition: background 0.3s;
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
            margin-right: 0.5rem;
            margin-top: 0.5rem;
            padding: 0.5rem 1rem;
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
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
        }

        .modal-content h2 {
            color: #a52a2a;
            margin-bottom: 1.5rem;
        }

        .modal-content .btn {
            margin-top: 1rem;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem 2rem;
            border-radius: 6px;
            color: #fff;
            font-size: 1rem;
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

        @media (max-width: 768px) {
            .dashboard-content {
                margin-left: 220px;
                max-width: 90%;
                margin: 2rem auto;
                padding: 2rem;
            }

            .table th, .table td {
                padding: 0.8rem;
            }
        }

        @media (max-width: 600px) {
            .admin-container {
                flex-direction: column;
            }

            .dashboard-content {
                margin-left: auto;
                max-width: 100%;
                margin: 1rem;
                padding: 1.5rem;
            }

            .table {
                font-size: 0.9rem;
            }

            .form-group input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <section class="admin-container">
        <?php include '../../includes/admin_sidebar.php'; ?>
        <div class="dashboard-content">
            <h1>Manage Customers</h1>

            <!-- Add Customer Form -->
            <div class="form-container">
                <h2>Add New Customer</h2>
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
                    <button type="submit" class="btn"><i class="fas fa-plus"></i> Add Customer</button>
                </form>
            </div>

            <!-- Customer List -->
            <div class="table-container">
                <h2>Current Customers</h2>
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
                            <tr><td colspan="5">No customers found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><?php echo sanitize($customer['username']); ?></td>
                                    <td><?php echo sanitize($customer['email']); ?></td>
                                    <td><?php echo sanitize($customer['phone'] ?: 'N/A'); ?></td>
                                    <td class="admin-status"><?php echo $customer['is_admin'] ? 'Yes' : 'No'; ?></td>
                                    <td class="actions">
                                        <button class="btn edit-btn" data-customer='<?php echo json_encode($customer); ?>'><i class="fas fa-edit"></i> Edit</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this customer?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="customer_id" value="<?php echo $customer['customer_id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                            <button type="submit" class="btn"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Edit Customer Modal -->
            <div class="modal" id="edit-customer-modal">
                <div class="modal-content">
                    <h2>Edit Customer</h2>
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
                        <button type="submit" class="btn"><i class="fas fa-save"></i> Update Customer</button>
                        <button type="button" class="btn" id="cancel-edit-btn"><i class="fas fa-times"></i> Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Toast Notification -->
            <div class="toast" id="toast"></div>
        </div>
    </section>

    <script>
        // Show toast notification
        function showToast(message, type) {
            const toast = document.getElementById("toast");
            if (toast) {
                toast.textContent = message;
                toast.className = `toast ${type} active`;
                setTimeout(() => {
                    toast.className = "toast";
                }, 3000);
            }
        }

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
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>