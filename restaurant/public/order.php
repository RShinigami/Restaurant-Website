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
$categories = ['Appetizer', 'Salad', 'Main Course', 'Pasta', 'Pizza', 'Dessert', 'Beverage', 'Side'];
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
            <form id="order-form" method="POST" action="order.php">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                <button type="submit" class="btn" name="submit_order">Submit Order</button>
                <button type="button" class="btn" id="cancel-order-btn">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>
</section>

<?php
// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        die('Invalid CSRF token.');
    }
    if (!empty($_SESSION['cart'])) {
        try {
            $db->beginTransaction();
            foreach ($_SESSION['cart'] as $item_id => $quantity) {
                $stmt = $db->prepare('INSERT INTO reservations_orders (customer_id, type, item_id, quantity, date_time, status) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $_SESSION['customer_id'],
                    'order',
                    $item_id,
                    $quantity,
                    date('Y-m-d H:i:s'),
                    'pending'
                ]);
            }
            $db->commit();
            $_SESSION['cart'] = []; // Clear cart
            echo '<script>showToast("Order placed successfully!", "success"); setTimeout(() => location.reload(), 2000);</script>';
        } catch (PDOException $e) {
            $db->rollBack();
            echo '<script>showToast("Failed to place order: ' . addslashes($e->getMessage()) . '", "error");</script>';
        }
    } else {
        echo '<script>showToast("Cart is empty!", "error");</script>';
    }
}
?>

<?php include '../includes/footer.php'; ?>
</main>
</body>
</html>