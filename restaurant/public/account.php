<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
secureSessionStart();

// Redirect non-logged-in users
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Generate CSRF token
$csrf_token = generateCsrfToken();

// Handle AJAX cancellation (reservations and orders)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (!validateCsrfToken($_POST['csrf_token'])) {
        $response['message'] = 'Invalid CSRF token.';
        echo json_encode($response);
        exit;
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($id <= 0) {
        $response['message'] = 'Invalid ID.';
        echo json_encode($response);
        exit;
    }

    try {
        $action = $_POST['action'];
        $type = $action === 'cancel_reservation' ? 'reservation' : 'order';

        // Verify item belongs to user and is pending
        $stmt = $db->prepare('
            SELECT status 
            FROM reservations_orders 
            WHERE id = ? AND customer_id = ? AND type = ?
        ');
        $stmt->execute([$id, $_SESSION['customer_id'], $type]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $response['message'] = ucfirst($type) . ' not found or not yours.';
            echo json_encode($response);
            exit;
        }

        if ($item['status'] !== 'pending') {
            $response['message'] = 'Only pending ' . $type . 's can be cancelled.';
            echo json_encode($response);
            exit;
        }

        // Cancel item
        $stmt = $db->prepare('UPDATE reservations_orders SET status = ? WHERE id = ?');
        $stmt->execute(['cancelled', $id]);

        $response['success'] = true;
        $response['message'] = ucfirst($type) . ' cancelled successfully!';
    } catch (PDOException $e) {
        $response['message'] = 'Failed to cancel ' . $type . ': ' . $e->getMessage();
        error_log('Cancel ' . $type . ' error: ' . $e->getMessage());
    }

    echo json_encode($response);
    exit;
}

// Fetch user's reservations
$stmt = $db->prepare('
    SELECT ro.id, ro.date_time, ro.status, ro.table_number, ro.special_requests, 
           t.description
    FROM reservations_orders ro
    LEFT JOIN tables t ON ro.table_number = t.table_number
    WHERE ro.customer_id = ? AND ro.type = ?
    ORDER BY ro.date_time DESC
');
$stmt->execute([$_SESSION['customer_id'], 'reservation']);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's orders
$stmt = $db->prepare('
    SELECT ro.id, ro.date_time, ro.status
    FROM reservations_orders ro
    WHERE ro.customer_id = ? AND ro.type = ?
    ORDER BY ro.date_time DESC
');
$stmt->execute([$_SESSION['customer_id'], 'order']);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch order items and calculate totals
$order_items = [];
foreach ($orders as &$order) {
    try {
        $stmt = $db->prepare('
            SELECT oi.quantity, m.name, m.price
            FROM order_items oi
            JOIN menu_items m ON oi.menu_id = m.item_id
            WHERE oi.order_id = ?
        ');
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $order_items[$order['id']] = $items;

        // Calculate total
        $total = 0.0;
        foreach ($items as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        $order['total'] = $total;
    } catch (PDOException $e) {
        error_log('Error fetching order items for order_id ' . $order['id'] . ': ' . $e->getMessage());
        $order_items[$order['id']] = [];
        $order['total'] = 0.0;
    }
}
unset($order); // Unset reference to avoid accidental modification
?>

<?php include '../includes/header.php'; ?>

<main>
<section class="account-page">
    <h1>Your Account</h1>

    <!-- Reservations Section -->
    <div class="reservations-container">
        <h2>Reservations</h2>
        <?php if (empty($reservations)): ?>
            <p>No reservations found.</p>
        <?php else: ?>
            <table class="reservations-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Table</th>
                        <th>Status</th>
                        <th>Special Requests</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $reservation): ?>
                        <tr>
                            <td><?php echo sanitize(date('Y-m-d h:i A', strtotime($reservation['date_time']))); ?></td>
                            <td>
                                <?php
                                $table_label = $reservation['table_number'] ? 'Table ' . $reservation['table_number'] : 'N/A';
                                if ($reservation['description']) {
                                    $table_label .= ' (' . sanitize($reservation['description']) . ')';
                                }
                                echo $table_label;
                                ?>
                            </td>
                            <td><?php echo sanitize(ucfirst($reservation['status'])); ?></td>
                            <td><?php echo $reservation['special_requests'] ? sanitize($reservation['special_requests']) : 'None'; ?></td>
                            <td>
                                <?php if ($reservation['status'] === 'pending'): ?>
                                    <button class="btn cancel-btn cancel-reservation-btn" 
                                            data-id="<?php echo $reservation['id']; ?>" 
                                            data-csrf="<?php echo sanitize($csrf_token); ?>">Cancel</button>
                                <?php else: ?>
                                    <span>-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Orders Section -->
    <div class="orders-container">
        <h2>Orders</h2>
        <?php if (empty($orders)): ?>
            <p>No orders found.</p>
        <?php else: ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo sanitize($order['id']); ?></td>
                            <td><?php echo sanitize(date('Y-m-d h:i A', strtotime($order['date_time']))); ?></td>
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
                            <td>
                                <?php if ($order['status'] === 'pending'): ?>
                                    <button class="btn cancel-btn cancel-order-btn" 
                                            data-id="<?php echo $order['id']; ?>" 
                                            data-csrf="<?php echo sanitize($csrf_token); ?>">Cancel</button>
                                <?php else: ?>
                                    <span>-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>
</section>
</main>

<?php include '../includes/footer.php'; ?>
</body>
</html>