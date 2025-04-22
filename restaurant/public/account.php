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

// Handle AJAX cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_reservation') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (!validateCsrfToken($_POST['csrf_token'])) {
        $response['message'] = 'Invalid CSRF token.';
        echo json_encode($response);
        exit;
    }

    $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
    if ($reservation_id <= 0) {
        $response['message'] = 'Invalid reservation ID.';
        echo json_encode($response);
        exit;
    }

    try {
        // Verify reservation belongs to user and is pending
        $stmt = $db->prepare('
            SELECT status 
            FROM reservations_orders 
            WHERE id = ? AND customer_id = ? AND type = ?
        ');
        $stmt->execute([$reservation_id, $_SESSION['customer_id'], 'reservation']);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reservation) {
            $response['message'] = 'Reservation not found or not yours.';
            echo json_encode($response);
            exit;
        }

        if ($reservation['status'] !== 'pending') {
            $response['message'] = 'Only pending reservations can be cancelled.';
            echo json_encode($response);
            exit;
        }

        // Cancel reservation
        $stmt = $db->prepare('UPDATE reservations_orders SET status = ? WHERE id = ?');
        $stmt->execute(['cancelled', $reservation_id]);

        $response['success'] = true;
        $response['message'] = 'Reservation cancelled successfully!';
    } catch (PDOException $e) {
        $response['message'] = 'Failed to cancel reservation: ' . $e->getMessage();
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
                                    <button class="btn cancel-btn" 
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const cancelButtons = document.querySelectorAll('.cancel-btn');
    const toast = document.getElementById('toast');

    cancelButtons.forEach(button => {
        button.addEventListener('click', () => {
            const reservationId = button.dataset.id;
            const csrfToken = button.dataset.csrf;

            if (confirm('Are you sure you want to cancel this reservation?')) {
                const formData = new FormData();
                formData.append('action', 'cancel_reservation');
                formData.append('reservation_id', reservationId);
                formData.append('csrf_token', csrfToken);

                fetch('/public/account.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }
                })
                .catch(error => {
                    showToast('Error cancelling reservation.', 'error');
                    console.error('Fetch error:', error);
                });
            }
        });
    });

    // Toast notification
    function showToast(message, type) {
        toast.textContent = message;
        toast.className = `toast ${type} active`;
        setTimeout(() => { toast.className = 'toast'; }, 3000);
    }
});
</script>

<style>
.account-page {
    max-width: 800px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.reservations-container, .orders-container {
    width: 100%;
    max-width: 800px;
    margin: 2rem auto;
}

.reservations-container h2, .orders-container h2 {
    color: #a52a2a;
    margin-bottom: 1rem;
}

.reservations-table, .orders-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
    table-layout: fixed;
}

.reservations-table th, .reservations-table td,
.orders-table th, .orders-table td {
    padding: 1rem;
    border: 2px solid #ccc;
    text-align: left;
    vertical-align: top;
    overflow-wrap: break-word;
    min-width: 80px;
    max-width: 150px;
    font-size: 16px;
}

.reservations-table th, .orders-table th {
    background-color: #a52a2a;
    color: #fff;
    font-weight: bold;
}

.reservations-table td, .orders-table td {
    background-color: #fff;
}

.reservations-table tr:nth-child(even) td, .orders-table tr:nth-child(even) td {
    background-color: #f9f9f9;
}

.reservations-table tr:hover td, .orders-table tr:hover td {
    background-color: #f1f1f1;
}

.orders-table ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.orders-table ul li {
    margin: 0.3rem 0;
}

.cancel-btn {
    background-color: #a52a2a;
    width: auto;
    color: #fff;
    border: none;
    padding: 0.6rem 1.2rem;
    cursor: pointer;
    transition: background-color 0.3s;
    font-size: 16px;
}

.cancel-btn:hover {
    background-color: #8b0000;
}

@media (max-width: 768px) {
    .reservations-table, .orders-table {
        font-size: 14px;
    }

    .reservations-table th, .reservations-table td,
    .orders-table th, .orders-table td {
        padding: 0.8rem;
        min-width: 60px;
        max-width: 120px;
    }

    .cancel-btn {
        padding: 0.5rem 1rem;
        font-size: 14px;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
</main>
</body>
</html>