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
            max-width: 1500px;
            margin: 3rem auto;
            margin-left: 18%;
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

        .table .btn {
            margin-right: 0.5rem;
            margin-top: 0.5rem;
            padding: 0.5rem 1rem;
            background-color: #a52a2a;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
        }

        .table .btn:hover {
            background-color: #7a1717;
            transform: translateY(-2px);
        }

        .table .btn i {
            margin-right: 0.5rem;
        }

        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 1rem;
        }

        .message.error {
            background: #dc3545;
            color: #fff;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 6px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.5s ease, transform 0.5s ease;
            transform: translateY(-20px);
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
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

            .toast {
                right: 10px;
                left: 10px;
                top: 10px;
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
        }
    </style>
</head>
<body>
    <section class="admin-container">
        <?php include '../../includes/admin_sidebar.php'; ?>
        <div class="dashboard-content">
            <h1>Manage Orders</h1>

            <!-- Error Messages -->
            <?php if ($error_message): ?>
                <div class="message error"><?php echo sanitize($error_message); ?></div>
            <?php endif; ?>

            <!-- Toast Notification -->
            <?php if ($success_message): ?>
                <div class="toast" id="success-toast"><?php echo sanitize($success_message); ?></div>
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
                                    <td><?php echo sanitize($order['id']); ?></td>
                                    <td><?php echo sanitize($order['customer_name']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($order['date_time'])); ?></td>
                                    <td>
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
                                    <td>$<?php echo number_format($order['total'] ?? 0.0, 2); ?></td>
                                    <td><?php echo sanitize(ucfirst($order['status'])); ?></td>
                                    <td class="actions">
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="confirm">
                                                <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                                <button type="submit" class="btn"><i class="fas fa-check"></i> Confirm</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this order?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
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
        </div>
    </section>

    <script>
        // Debugging: Log when script runs
        console.log('manage_orders.php JavaScript loaded');

        // Toast notification handler
        try {
            window.addEventListener("load", () => {
                console.log('Window loaded, checking for toast');
                const toast = document.getElementById("success-toast");
                if (toast) {
                    console.log('Showing toast');
                    toast.classList.add("show");
                    setTimeout(() => {
                        console.log('Hiding toast');
                        toast.classList.remove("show");
                    }, 2000);
                }
            });
        } catch (e) {
            console.error('Error in toast handler:', e);
        }
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>