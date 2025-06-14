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
$active_page = 'manage_orders.php';

// Initialize messages
$error_message = '';
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']); // Clear after use

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error_message = 'Invalid CSRF token.';
    } else {
        try {
            $action = $_POST['action'] ?? '';
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new Exception('Invalid order ID.');
            }

            // Verify order exists
            $stmt = $db->prepare('SELECT status FROM reservations_orders WHERE id = ? AND type = ?');
            $stmt->execute([$id, 'order']);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new Exception('Order not found.');
            }

            if ($action === 'confirm') {
                if ($order['status'] !== 'pending') {
                    throw new Exception('Order is already confirmed or cancelled.');
                }
                $stmt = $db->prepare('UPDATE reservations_orders SET status = ? WHERE id = ? AND type = ?');
                $stmt->execute(['confirmed', $id, 'order']);
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Failed to confirm order.');
                }
                $_SESSION['success_message'] = 'Order confirmed successfully!';
            } elseif ($action === 'delete') {
                $db->beginTransaction();
                $stmt = $db->prepare('DELETE FROM order_items WHERE order_id = ?');
                $stmt->execute([$id]);
                $stmt = $db->prepare('DELETE FROM reservations_orders WHERE id = ? AND type = ?');
                $stmt->execute([$id, 'order']);
                if ($stmt->rowCount() === 0) {
                    $db->rollBack();
                    throw new Exception('Order not found.');
                }
                $db->commit();
                $_SESSION['success_message'] = 'Order deleted successfully!';
            } else {
                throw new Exception('Invalid action.');
            }

            // Redirect to prevent form resubmission
            header('Location: manage_orders.php');
            exit;
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Fetch all orders with customer names and items
try {
    $stmt = $db->prepare('
        SELECT ro.id, ro.customer_id, ro.date_time, ro.status, c.username AS customer_name
        FROM reservations_orders ro
        JOIN customers c ON ro.customer_id = c.customer_id
        WHERE ro.type = ?
        ORDER BY ro.date_time DESC
    ');
    $stmt->execute(['order']);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch order items and calculate totals
    $order_items = [];
    foreach ($orders as &$order) {
        $stmt = $db->prepare('
            SELECT oi.quantity, m.name, m.price
            FROM order_items oi
            JOIN menu_items m ON oi.menu_id = m.item_id
            WHERE oi.order_id = ?
        ');
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $order_items[$order['id']] = $items;

        $total = 0.0;
        foreach ($items as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        $order['total'] = $total;
    }
    unset($order);
} catch (Exception $e) {
    $error_message = 'Failed to fetch orders: ' . $e->getMessage();
    $orders = [];
    $order_items = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Restaurant System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/css/reset.css">
    <style>
        .admin-container-orders {
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

        .table-container h2 {
            color: #a52a2a;
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            margin: 1.2rem 0 0.8rem;
            font-weight: 500;
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

        .table .btn {
            margin: 0.3rem 0.4rem;
            padding: clamp(0.3rem, 0.8vw, 0.5rem) clamp(0.6rem, 1.2vw, 0.8rem);
            font-size: clamp(0.85rem, 1.8vw, 1rem);
            background-color: #a52a2a;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.3s, box-shadow 0.3s;
            display: inline-flex;
            align-items: center;
        }

        .table .btn:hover {
            background-color: #7a1717;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
        }

        .table .btn i {
            margin-right: 0.4rem;
            font-size: clamp(1rem, 2.2vw, 1.2rem);
        }

        .table ul {
            margin: 0;
            padding-left: 1.2rem;
            list-style: disc;
        }

        .table ul li {
            font-size: clamp(0.9rem, 2vw, 1.1rem);
        }

        .message {
            padding: clamp(0.4rem, 1vw, 0.6rem) clamp(0.8rem, 1.5vw, 1rem);
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
        }

        .message.error {
            background: #dc3545;
            color: #fff;
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
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            margin-bottom: 0.8rem;
        }

        .modal-content p {
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            color: #333;
            margin-bottom: 0.8rem;
        }

        .modal-content .btn {
            margin-top: 0.4rem;
            padding: clamp(0.5rem, 1.2vw, 0.8rem) clamp(0.8rem, 1.8vw, 1.2rem);
            font-size: clamp(0.9rem, 2vw, 1.1rem);
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

            .table-container h2 {
                font-size: clamp(1.1rem, 2.2vw, 1.4rem);
            }

            .table th,
            .table td {
                padding: clamp(0.4rem, 1vw, 0.6rem);
                font-size: clamp(0.85rem, 1.8vw, 1rem);
                min-width: clamp(65px, 11vw, 85px);
            }

            .table ul li {
                font-size: clamp(0.85rem, 1.8vw, 1rem);
            }

            .table .btn {
                margin: 0.2rem 0.3rem;
                padding: clamp(0.25rem, 0.6vw, 0.4rem) clamp(0.5rem, 1vw, 0.7rem);
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
            }

            .table .btn i {
                font-size: clamp(0.9rem, 2vw, 1.1rem);
            }

            .message {
                padding: clamp(0.3rem, 0.8vw, 0.5rem) clamp(0.6rem, 1.2vw, 0.8rem);
                font-size: clamp(0.85rem, 1.8vw, 1rem);
            }

            .modal-content {
                padding: clamp(0.6rem, 1.2vw, 1rem);
                max-width: min(380px, 94vw);
            }

            .modal-content h2 {
                font-size: clamp(1.1rem, 2.2vw, 1.4rem);
            }

            .modal-content p {
                font-size: clamp(0.85rem, 1.8vw, 1rem);
            }

            .modal-content .btn {
                padding: clamp(0.4rem, 1vw, 0.6rem) clamp(0.6rem, 1.5vw, 1rem);
                font-size: clamp(0.85rem, 1.8vw, 1rem);
            }

            .toast {
                padding: clamp(0.3rem, 0.8vw, 0.5rem) clamp(0.6rem, 1.2vw, 0.8rem);
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
            }
        }

        /* Navbar transition */
        @media (max-width: 600px) {
            .admin-container-orders {
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

            .table td ul {
                padding-left: 0;
                list-style: none;
                text-align: left;
                flex: 2;
            }

            .table td ul li {
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
            }

            .table .btn {
                margin: clamp(0.2rem, 0.5vw, 0.3rem);
                padding: clamp(0.2rem, 0.5vw, 0.3rem) clamp(0.4rem, 0.8vw, 0.6rem);
                font-size: clamp(0.75rem, 1.4vw, 0.9rem);
            }

            .table .btn i {
                font-size: clamp(0.8rem, 1.6vw, 1rem);
            }

            .message {
                padding: clamp(0.3rem, 0.8vw, 0.5rem) clamp(0.6rem, 1.2vw, 0.8rem);
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
            }

            .modal-content {
                padding: clamp(0.5rem, 1vw, 0.8rem);
                max-width: 95vw;
            }

            .modal-content h2 {
                font-size: clamp(1rem, 2vw, 1.3rem);
            }

            .modal-content p {
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
            }

            .modal-content .btn {
                padding: clamp(0.3rem, 0.8vw, 0.5rem) clamp(0.5rem, 1.2vw, 0.8rem);
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
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
    <section class="admin-container-orders">
        <?php include '../../includes/admin_sidebar.php'; ?>
        <div class="dashboard-content">
            <h1>Manage Orders</h1>

            <!-- Error Messages -->
            <?php if ($error_message): ?>
                <div class="message error"><?php echo sanitize($error_message); ?></div>
            <?php endif; ?>

            <!-- Orders List -->
            <div class="table-container">
                <h2>Current Orders</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer Name</th>
                            <th>Date & Time</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="7">No orders found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr data-order-id="<?php echo $order['id']; ?>">
                                    <td data-label="ID"><?php echo sanitize($order['id']); ?></td>
                                    <td data-label="Customer Name"><?php echo sanitize($order['customer_name']); ?></td>
                                    <td data-label="Date & Time"><?php echo date('Y-m-d H:i', strtotime($order['date_time'])); ?></td>
                                    <td data-label="Items">
                                        <?php
                                        $items = $order_items[$order['id']] ?? [];
                                        if (empty($items)) {
                                            echo 'No items';
                                        } else {
                                            echo '<ul>';
                                            foreach ($items as $item) {
                                                echo '<li>' . sanitize($item['name']) . ' (x' . $item['quantity'] . ')</li>';
                                            }
                                            echo '</ul>';
                                        }
                                        ?>
                                    </td>
                                    <td data-label="Total">$<?php echo number_format($order['total'] ?? 0.0, 2); ?></td>
                                    <td data-label="Status"><?php echo sanitize(ucfirst($order['status'])); ?></td>
                                    <td data-label="Actions" class="actions">
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <button class="btn confirm-btn" data-order-id="<?php echo $order['id']; ?>"><i class="fas fa-check"></i> Confirm</button>
                                        <?php endif; ?>
                                        <button class="btn delete-btn" data-order-id="<?php echo $order['id']; ?>"><i class="fas fa-trash"></i> Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Confirm Order Modal -->
        <div class="modal" id="confirm-order-modal">
                <div class="modal-content">
                    <h2>Confirm Order</h2>
                    <p>Are you sure you want to confirm this order?</p>
                    <form method="POST" id="confirm-order-form">
                        <input type="hidden" name="action" value="confirm">
                        <input type="hidden" name="id" id="confirm-order-id">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                        <button type="submit" class="btn"><i class="fas fa-check"></i> Confirm</button>
                        <button type="button" class="btn" id="cancel-confirm-btn"><i class="fas fa-times"></i> Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Delete Order Modal -->
            <div class="modal" id="delete-order-modal">
                <div class="modal-content">
                    <h2>Confirm Deletion</h2>
                    <p>Are you sure you want to delete this order?</p>
                    <form method="POST" id="delete-order-form">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-order-id">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                        <button type="submit" class="btn"><i class="fas fa-trash"></i> Confirm</button>
                        <button type="button" class="btn" id="cancel-delete-btn"><i class="fas fa-times"></i> Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Toast Notification -->
            <div class="toast" id="toast"></div>
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
                    toast.textContent = "";
                }, 2000);
            }
        }

        // Display session-based toast messages on page load
        window.addEventListener("load", () => {
            const successMessage = "<?php echo addslashes($success_message); ?>";
            const errorMessage = "<?php echo addslashes($error_message); ?>";
            if (successMessage) {
                showToast(successMessage, "success");
            } else if (errorMessage) {
                showToast(errorMessage, "error");
            }
        });

        // Confirm modal handlers
        const confirmModal = document.getElementById("confirm-order-modal");
        const confirmForm = document.getElementById("confirm-order-form");
        const cancelConfirmBtn = document.getElementById("cancel-confirm-btn");

        document.querySelectorAll(".confirm-btn").forEach(button => {
            button.addEventListener("click", () => {
                const orderId = button.getAttribute("data-order-id");
                document.getElementById("confirm-order-id").value = orderId;
                confirmModal.classList.add("active");
            });
        });

        cancelConfirmBtn.addEventListener("click", () => {
            confirmModal.classList.remove("active");
            confirmForm.reset();
        });

        confirmModal.addEventListener("click", (e) => {
            if (e.target === confirmModal) {
                confirmModal.classList.remove("active");
                confirmForm.reset();
            }
        });

        // Delete modal handlers
        const deleteModal = document.getElementById("delete-order-modal");
        const deleteForm = document.getElementById("delete-order-form");
        const cancelDeleteBtn = document.getElementById("cancel-delete-btn");

        document.querySelectorAll(".delete-btn").forEach(button => {
            button.addEventListener("click", () => {
                const orderId = button.getAttribute("data-order-id");
                document.getElementById("delete-order-id").value = orderId;
                deleteModal.classList.add("active");
            });
        });

        cancelDeleteBtn.addEventListener("click", () => {
            deleteModal.classList.remove("active");
            deleteForm.reset();
        });

        deleteModal.addEventListener("click", (e) => {
            if (e.target === deleteModal) {
                deleteModal.classList.remove("active");
                deleteForm.reset();
            }
        });
    </script>

</body>
</html>