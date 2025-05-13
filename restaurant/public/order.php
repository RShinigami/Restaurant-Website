<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
secureSessionStart();

// Redirect non-logged-in users
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Fetch all menu items
$categories = [];
$stmt = $db->query('SELECT DISTINCT category FROM menu_items ORDER BY category');
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
$menu_items = [];
foreach ($categories as $category) {
    $stmt = $db->prepare('SELECT item_id, name, description, price, image_path, category FROM menu_items WHERE category = ? ORDER BY name');
    $stmt->execute([$category]);
    $menu_items[$category] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Generate CSRF token
$csrf_token = generateCsrfToken();

// Handle AJAX order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_order') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (!validateCsrfToken($_POST['csrf_token'])) {
        $response['message'] = 'Invalid CSRF token.';
        echo json_encode($response);
        exit;
    }

    if (empty($_SESSION['cart'])) {
        $response['message'] = 'Cart is empty!';
        echo json_encode($response);
        exit;
    }

    try {
        $db->beginTransaction();

        // Insert order into reservations_orders
        $stmt = $db->prepare('
            INSERT INTO reservations_orders (customer_id, type, date_time, status)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([
            $_SESSION['customer_id'],
            'order',
            date('Y-m-d H:i'),
            'pending'
        ]);
        $order_id = $db->lastInsertId();

        // Insert order items
        $stmt = $db->prepare('
            INSERT INTO order_items (order_id, menu_id, quantity)
            VALUES (?, ?, ?)
        ');
        foreach ($_SESSION['cart'] as $item_id => $quantity) {
            $stmt->execute([$order_id, $item_id, $quantity]);
        }

        $db->commit();
        $_SESSION['cart'] = []; // Clear cart
        $response['success'] = true;
        $response['message'] = 'Order placed successfully! Redirecting to your account...';
    } catch (PDOException $e) {
        $db->rollBack();
        $response['message'] = 'Failed to place order: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
?>

<?php include '../includes/header.php'; ?>

<section class="order-page">
    <h1>Order Your Meal</h1>

    <!-- Filters -->
    <div class="order-filters">
        <select id="category-filter" aria-label="Filter by category">
            <option value="all">All Categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo sanitize($category); ?>"><?php echo sanitize($category); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" id="search-filter" placeholder="Search menu..." aria-label="Search menu items">
    </div>

    <!-- Menu Items -->
    <div class="menu-grid" id="menu-grid">
        <?php foreach ($menu_items as $category => $items): ?>
            <?php foreach ($items as $item): ?>
                <div class="item-card" data-category="<?php echo sanitize($category); ?>" data-name="<?php echo sanitize(strtolower($item['name'])); ?>">
                    <?php if ($item['image_path'] && file_exists(__DIR__ . '/' . $item['image_path'])): ?>
                        <img loading="lazy" src="<?php echo sanitize($item['image_path']); ?>" alt="<?php echo sanitize($item['name']); ?>">
                    <?php endif; ?>
                    <div class="item-details">
                        <h4><?php echo sanitize($item['name']); ?></h4>
                        <p><?php echo sanitize($item['description'] ?: 'A delicious dish!'); ?></p>
                        <p class="price">$<?php echo number_format($item['price'], 2); ?></p>
                        <button class="btn add-to-cart" data-item-id="<?php echo $item['item_id']; ?>">Add to Cart</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <!-- Cart Sidebar -->
    <div class="cart-sidebar" id="cart-sidebar">
        <button class="cart-toggle" aria-label="Toggle cart">
            <span class="cart-icon">🛒</span>
            <span class="cart-count" id="cart-count">0</span>
        </button>
        <div class="cart-content">
            <button class="cart-close" aria-label="Close cart">✕</button>
            <h2>Your Cart</h2>
            <div id="cart-items"></div>
            <p id="cart-total">Total: $0.00</p>
            <button class="btn" id="place-order-btn" disabled>Place Order</button>
            <button class="btn" id="clear-cart-btn">Clear Cart</button>
        </div>
    </div>

    <!-- Order Confirmation Modal -->
    <div class="modal" id="order-modal">
        <div class="modal-content">
            <h2>Confirm Your Order</h2>
            <div id="modal-cart-items"></div>
            <p id="modal-cart-total">Total: $0.00</p>
            <div class="modal-buttons">
                <button class="btn" id="submit-order-btn" data-csrf="<?php echo sanitize($csrf_token); ?>">Submit Order</button>
                <button class="btn" id="cancel-order-btn">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>
</section>

<script>
// Toast notification (inline to avoid scripts.js dependency for this page)
function showToast(message, type) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type} active`;
    setTimeout(() => { toast.className = 'toast'; }, 3000);
}

// Move order submission to inline script to ensure CSRF token access
document.addEventListener('DOMContentLoaded', () => {
    const submitOrderBtn = document.getElementById('submit-order-btn');
    const orderModal = document.getElementById('order-modal');

    if (submitOrderBtn) {
        submitOrderBtn.addEventListener('click', () => {
            const formData = new FormData();
            formData.append('action', 'submit_order');
            formData.append('csrf_token', submitOrderBtn.dataset.csrf);

            fetch('/public/order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    orderModal.classList.remove('active');
                    // Update cart UI
                    document.getElementById('cart-items').innerHTML = '<p>Your cart is empty.</p>';
                    document.getElementById('cart-total').textContent = 'Total: $0.00';
                    document.getElementById('cart-count').textContent = '0';
                    document.getElementById('place-order-btn').disabled = true;
                    // Redirect to account.php
                    setTimeout(() => {
                        window.location.href = 'account.php#orders';
                    }, 2000);
                }
            })
            .catch(error => {
                showToast('Error submitting order: ' + error, 'error');
                console.error('Fetch error:', error);
            });
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
</main>
</body>
</html>