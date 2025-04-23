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

// Fetch current user details
$stmt = $db->prepare('SELECT username, email, phone FROM customers WHERE customer_id = ?');
$stmt->execute([$_SESSION['customer_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);     
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (!validateCsrfToken($_POST['csrf_token'])) {
        $response['message'] = 'Invalid CSRF token.';
        error_log('CSRF validation failed: ' . print_r($_POST, true));
        echo json_encode($response);
        exit;
    }

    try {
        if ($_POST['action'] === 'update_profile') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Validate inputs
            if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
                $response['message'] = 'Username must be 3-50 characters.';
                echo json_encode($response);
                exit;
            }
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'Invalid email address.';
                echo json_encode($response);
                exit;
            }
            if (empty($phone) || !preg_match('/^\+?\d{7,15}$/', $phone)) {
                $response['message'] = 'Invalid phone number (7-15 digits).';
                echo json_encode($response);
                exit;
            }

            // Check if email or username is taken
            $stmt = $db->prepare('SELECT customer_id FROM customers WHERE (email = ? OR username = ?) AND customer_id != ?');
            $stmt->execute([$email, $username, $_SESSION['customer_id']]);
            if ($stmt->fetch()) {
                $response['message'] = 'Email or username already taken.';
                echo json_encode($response);
                exit;
            }

            // Fetch current password hash
            $stmt = $db->prepare('SELECT password FROM customers WHERE customer_id = ?');
            $stmt->execute([$_SESSION['customer_id']]);
            $stored_password = $stmt->fetchColumn();

            // If updating email or password, verify current password
            $update_email = $email !== $user['email'];
            $update_password = !empty($new_password);
            if ($update_email || $update_password) {
                if (empty($current_password) || !password_verify($current_password, $stored_password)) {
                    $response['message'] = 'Incorrect current password.';
                    echo json_encode($response);
                    exit;
                }
            }

            // Validate new password
            if ($update_password) {
                if ($new_password !== $confirm_password) {
                    $response['message'] = 'New passwords do not match.';
                    echo json_encode($response);
                    exit;
                }
                if (!preg_match('/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/', $new_password) || strlen($new_password) < 8) {
                    $response['message'] = 'New password must be 8+ characters with letters and numbers.';
                    echo json_encode($response);
                    exit;
                }
            }

            // Prepare update
            $update_fields = ['username = ?', 'email = ?', 'phone = ?'];
            $update_values = [$username, $email, $phone];
            if ($update_password) {
                $update_fields[] = 'password = ?';
                $update_values[] = password_hash($new_password, PASSWORD_DEFAULT);
            }

            $stmt = $db->prepare('UPDATE customers SET ' . implode(', ', $update_fields) . ' WHERE customer_id = ?');
            $update_values[] = $_SESSION['customer_id'];
            $stmt->execute($update_values);

            $response['success'] = true;
            $response['message'] = 'Profile updated successfully!';
        } elseif ($_POST['action'] === 'cancel_reservation' || $_POST['action'] === 'cancel_order') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                $response['message'] = 'Invalid ID.';
                echo json_encode($response);
                exit;
            }

            $type = $_POST['action'] === 'cancel_reservation' ? 'reservation' : 'order';

            // Verify item belongs to user and is pending
            $stmt = $db->prepare('SELECT status FROM reservations_orders WHERE id = ? AND customer_id = ? AND type = ?');
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
        } else {
            $response['message'] = 'Invalid action.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Operation failed: ' . $e->getMessage();
        error_log('Account operation error: ' . $e->getMessage());
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
unset($order);
?>

<?php include '../includes/header.php'; ?>

<main>
<section class="account-page">
    <h1>Your Account</h1>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" data-tab="account-details">Account Details</button>
        <button class="tab-btn" data-tab="reservations">Reservations</button>
        <button class="tab-btn" data-tab="orders">Orders</button>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Account Details -->
        <div class="tab-pane active" id="account-details">
            <div class="form-container">
                <h2>Edit Profile</h2>
                <form id="profile-form">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo sanitize($user['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo sanitize($user['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo sanitize($user['phone']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="current_password">Current Password (required for email or password changes)</label>
                        <input type="password" id="current_password" name="current_password">
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password (optional)</label>
                        <input type="password" id="new_password" name="new_password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password">
                    </div>
                    <button type="submit" class="btn">Update Profile</button>
                </form>
            </div>
        </div>

        <!-- Reservations -->
        <div class="tab-pane" id="reservations">
            <div class="reservations-container">
                <h2>Your Reservations</h2>
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
        </div>

        <!-- Orders -->
        <div class="tab-pane" id="orders">
            <div class="orders-container">
                <h2>Your Orders</h2>
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
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>
</section>
</main>

<?php include '../includes/footer.php'; ?>
</body>
</html>