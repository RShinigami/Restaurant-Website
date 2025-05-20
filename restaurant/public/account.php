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
                                <tr data-id="<?php echo $reservation['id']; ?>">
                                    <td data-label="Date & Time"><?php echo sanitize(date('Y-m-d h:i A', strtotime($reservation['date_time']))); ?></td>
                                    <td data-label="Table">
                                        <?php
                                        $table_label = $reservation['table_number'] ? 'Table ' . $reservation['table_number'] : 'N/A';
                                        if ($reservation['description']) {
                                            $table_label .= ' (' . sanitize($reservation['description']) . ')';
                                        }
                                        echo $table_label;
                                        ?>
                                    </td>
                                    <td data-label="Status"><?php echo sanitize(ucfirst($reservation['status'])); ?></td>
                                    <td data-label="Special Requests"><?php echo $reservation['special_requests'] ? sanitize($reservation['special_requests']) : 'None'; ?></td>
                                    <td data-label="Actions">
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
                                <tr data-id="<?php echo $order['id']; ?>">
                                    <td data-label="Order ID"><?php echo sanitize($order['id']); ?></td>
                                    <td data-label="Date"><?php echo sanitize(date('Y-m-d h:i A', strtotime($order['date_time']))); ?></td>
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
                                    <td data-label="Actions">
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

    <!-- Cancel Reservation Modal -->
    <div class="modal" id="cancel-reservation-modal">
        <div class="modal-content">
            <h2>Cancel Reservation</h2>
            <p>Are you sure you want to cancel this reservation?</p>
            <div class="modal-buttons">
                <button class="btn" id="confirm-reservation-cancel-btn" data-id="" data-csrf="<?php echo sanitize($csrf_token); ?>">Confirm</button>
                <button class="btn" id="cancel-reservation-cancel-btn" type="button">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Cancel Order Modal -->
    <div class="modal" id="cancel-order-modal">
        <div class="modal-content">
            <h2>Cancel Order</h2>
            <p>Are you sure you want to cancel this order?</p>
            <div class="modal-buttons">
                <button class="btn" id="confirm-order-cancel-btn" data-id="" data-csrf="<?php echo sanitize($csrf_token); ?>">Confirm</button>
                <button class="btn" id="cancel-order-cancel-btn" type="button">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>
</section>
</main>

<style>
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

    .modal-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }

    .modal-content .btn {
        margin-top: 0.4rem;
        padding: clamp(0.6rem, 1.5vw, 0.9rem) clamp(1rem, 2vw, 1.2rem);
        font-size: clamp(0.9rem, 1.8vw, 1.1rem);
        background-color: #a52a2a;
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.3s, transform 0.3s, box-shadow 0.3s;
        min-height: 40px;
        min-width: 100px;
    }

    .modal-content .btn:hover {
        background-color: #7a1717;
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
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

    @media (max-width: 768px) {
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
            padding: clamp(0.6rem, 1.5vw, 0.9rem) clamp(1rem, 2vw, 1.2rem);
            font-size: clamp(0.9rem, 1.8vw, 1.1rem);
            min-height: 40px;
            min-width: 100px;
        }

        .toast {
            padding: clamp(0.3rem, 0.8vw, 0.5rem) clamp(0.6rem, 1.2vw, 0.8rem);
            font-size: clamp(0.8rem, 1.6vw, 0.95rem);
        }
    }

    @media (max-width: 600px) {
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
            padding: clamp(0.6rem, 1.5vw, 0.9rem) clamp(1rem, 2vw, 1.2rem);
            font-size: clamp(0. deportivo9rem, 1.8vw, 1.1rem);
            min-height: 40px;
            min-width: 100px;
        }

        .toast {
            padding: clamp(0.3rem, 0.8vw, 0.5rem) clamp(0.6rem, 1.2vw, 0.8rem);
            font-size: clamp(0.75rem, 1.4vw, 0.9rem);
            bottom: clamp(5px, 1vw, 8px);
            right: clamp(5px, 1vw, 8px);
        }

        /* Improved wide table layout */
        .reservations-container,
        .orders-container {
            width: 100%;
            padding: clamp(0.8rem, 1.5vw, 1rem);
            box-sizing: border-box;
        }

        .reservations-table,
        .orders-table {
            display: block;
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            font-size: clamp(0.9rem, 1.8vw, 1.1rem);
            line-height: 1.5;
        }

        .reservations-table thead,
        .orders-table thead {
            display: none;
        }

        .reservations-table tbody,
        .reservations-table tr,
        .orders-table tbody,
        .orders-table tr {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }

        .reservations-table tr,
        .orders-table tr {
            margin-bottom: 1.2rem;
            padding: clamp(0.8rem, 1.5vw, 1rem);
            border: 1px solid #d1d1d1;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
        }

        .reservations-table td,
        .orders-table td {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
            padding: clamp(0.6rem, 1.2vw, 0.8rem);
            border: none;
            text-align: left;
            white-space: normal;
            box-sizing: border-box;
        }

        .reservations-table td[data-label="Special Requests"],
        .orders-table td[data-label="Items"] {
            max-height: 120px;
            overflow-y: auto;
            overflow-x: hidden;
            word-wrap: break-word;
            width: 100%;
        }

        .reservations-table td::before,
        .orders-table td::before {
            content: attr(data-label);
            display: block;
            font-weight: 700;
            color: #a52a2a;
            font-size: clamp(0.9rem, 1.8vw, 1.1rem);
            margin-bottom: 0.3rem;
        }

        .reservations-table td ul,
        .orders-table td ul {
            padding: 0;
            margin: 0;
            list-style: none;
            width: 100%;
            word-wrap: break-word;
        }

        .reservations-table td ul li,
        .orders-table td ul li {
            font-size: clamp(0.85rem, 1.8vw, 1rem);
            margin-bottom: 0.4rem;
        }

        .reservations-table td[data-label="Actions"],
        .orders-table td[data-label="Actions"] {
            align-items: flex-end;
        }

        .reservations-table td[data-label="Actions"] .btn,
        .orders-table td[data-label="Actions"] .btn {
            padding: clamp(0.6rem, 1.5vw, 0.9rem) clamp(1rem, 2vw, 1.2rem);
            font-size: clamp(0.9rem, 1.8vw, 1.1rem);
            min-height: 40px;
            min-width: 100px;
            background-color: #a52a2a;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.3s, box-shadow 0.3s;
        }

        .reservations-table td[data-label="Actions"] .btn:hover,
        .orders-table td[data-label="Actions"] .btn:hover {
            background-color: #7a1717;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
        }
    }
</style>

<script>
// Toast notification
function showToast(message, type) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type} active`;
    setTimeout(() => {
        toast.className = 'toast';
        toast.textContent = '';
    }, 3000);
}

// Modal and cancellation handlers
document.addEventListener('DOMContentLoaded', () => {
    const cancelReservationModal = document.getElementById('cancel-reservation-modal');
    const cancelOrderModal = document.getElementById('cancel-order-modal');
    const confirmReservationCancelBtn = document.getElementById('confirm-reservation-cancel-btn');
    const cancelReservationCancelBtn = document.getElementById('cancel-reservation-cancel-btn');
    const confirmOrderCancelBtn = document.getElementById('confirm-order-cancel-btn');
    const cancelOrderCancelBtn = document.getElementById('cancel-order-cancel-btn');

    // Cancel reservation modal
    document.querySelectorAll('.cancel-reservation-btn').forEach(button => {
        // Remove existing listeners to prevent duplicates
        button.removeEventListener('click', handleReservationClick);
        button.addEventListener('click', handleReservationClick);
    });

    function handleReservationClick(event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        const id = this.getAttribute('data-id');
        confirmReservationCancelBtn.setAttribute('data-id', id);
        cancelReservationModal.classList.add('active');
    }

    if (confirmReservationCancelBtn) {
        confirmReservationCancelBtn.addEventListener('click', () => {
            const id = confirmReservationCancelBtn.getAttribute('data-id');
            const csrf = confirmReservationCancelBtn.getAttribute('data-csrf');
            const formData = new FormData();
            formData.append('action', 'cancel_reservation');
            formData.append('id', id);
            formData.append('csrf_token', csrf);

            fetch('/public/account.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    cancelReservationModal.classList.remove('active');
                    const row = document.querySelector(`.reservations-table tr[data-id="${id}"]`);
                    if (row) {
                        const statusCell = row.querySelector('td[data-label="Status"]') || row.cells[2];
                        const actionCell = row.querySelector('td[data-label="Actions"]') || row.cells[4];
                        statusCell.textContent = 'Cancelled';
                        actionCell.innerHTML = '<span>-</span>';
                    }
                }
            })
            .catch(error => {
                showToast('Error cancelling reservation: ' + error, 'error');
                console.error('Fetch error:', error);
            });
        });
    }

    if (cancelReservationCancelBtn) {
        cancelReservationCancelBtn.addEventListener('click', () => {
            cancelReservationModal.classList.remove('active');
            confirmReservationCancelBtn.setAttribute('data-id', '');
        });
    }

    cancelReservationModal.addEventListener('click', (e) => {
        if (e.target === cancelReservationModal) {
            cancelReservationModal.classList.remove('active');
            confirmReservationCancelBtn.setAttribute('data-id', '');
        }
    });

    // Cancel order modal
    document.querySelectorAll('.cancel-order-btn').forEach(button => {
        // Remove existing listeners to prevent duplicates
        button.removeEventListener('click', handleOrderClick);
        button.addEventListener('click', handleOrderClick);
    });

    function handleOrderClick(event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        const id = this.getAttribute('data-id');
        confirmOrderCancelBtn.setAttribute('data-id', id);
        cancelOrderModal.classList.add('active');
    }

    if (confirmOrderCancelBtn) {
        confirmOrderCancelBtn.addEventListener('click', () => {
            const id = confirmOrderCancelBtn.getAttribute('data-id');
            const csrf = confirmOrderCancelBtn.getAttribute('data-csrf');
            const formData = new FormData();
            formData.append('action', 'cancel_order');
            formData.append('id', id);
            formData.append('csrf_token', csrf);

            fetch('/public/account.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    cancelOrderModal.classList.remove('active');
                    const row = document.querySelector(`.orders-table tr[data-id="${id}"]`);
                    if (row) {
                        const statusCell = row.querySelector('td[data-label="Status"]') || row.cells[4];
                        const actionCell = row.querySelector('td[data-label="Actions"]') || row.cells[5];
                        statusCell.textContent = 'Cancelled';
                        actionCell.innerHTML = '<span>-</span>';
                    }
                }
            })
            .catch(error => {
                showToast('Error cancelling order: ' + error, 'error');
                console.error('Fetch error:', error);
            });
        });
    }

    if (cancelOrderCancelBtn) {
        cancelOrderCancelBtn.addEventListener('click', () => {
            cancelOrderModal.classList.remove('active');
            confirmOrderCancelBtn.setAttribute('data-id', '');
        });
    }

    cancelOrderModal.addEventListener('click', (e) => {
        if (e.target === cancelOrderModal) {
            cancelOrderModal.classList.remove('active');
            confirmOrderCancelBtn.setAttribute('data-id', '');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>