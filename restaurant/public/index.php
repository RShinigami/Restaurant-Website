<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
secureSessionStart();

//clearDB($db);

if (isAdmin()) {
    // Redirect to admin dashboard if logged in as admin
    header('Location: admin/dashboard.php');
    exit;
}
// Fetch categories
$categories = [];
$stmt = $db->query('SELECT DISTINCT category FROM menu_items ORDER BY category');
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all items per category for preview (all users)
$preview_items = [];
foreach ($categories as $category) {
    $stmt = $db->prepare('SELECT name, description, price, image_path FROM menu_items WHERE category = ? ORDER BY name');
    $stmt->execute([$category]);
    $preview_items[$category] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all items for full menu (logged-in users)
$full_menu = [];
if (isLoggedIn()) {
    foreach ($categories as $category) {
        $stmt = $db->prepare('SELECT name, description, price, image_path FROM menu_items WHERE category = ? ORDER BY name');
        $stmt->execute([$category]);
        $full_menu[$category] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


$restaurant_images = [
    ['title' => 'Restaurant Interior', 'image_path' => 'uploads/restaurant_interior.jpg'],
    ['title' => 'Outdoor Seating', 'image_path' => 'uploads/outdoor_seating.jpg'],
    ['title' => 'Kitchen', 'image_path' => 'uploads/kitchen.jpg'],
    ['title' => 'Dessert Display', 'image_path' => 'uploads/dessert_display.jpg'],
];


$chef_images = [
    ['title' => 'Chef John', 'image_path' => 'uploads/chef_john.jpg'],
    ['title' => 'Chef Maria', 'image_path' => 'uploads/chef_maria.jpg'],
    ['title' => 'Chef Alex', 'image_path' => 'uploads/chef_ahmed.jpg'],
    ['title' => 'Chef Sarah', 'image_path' => 'uploads/chef_sarah.jpg'],
];


$logo_path = '../assets/images/logo.png';
?>

<?php include '../includes/header.php'; ?>
<section class="hero">
    <h1 class="animated fadeIn">
        <?php if (isLoggedIn()): ?>
            Welcome, <?php echo sanitize($_SESSION['username'] ?? 'User'); ?>!
        <?php else: ?>
            Welcome to Our Restaurant!
        <?php endif; ?>
    </h1>
    <?php if (file_exists(__DIR__ . '/' . $logo_path)): ?>
        <img loading="lazy" src="<?php echo sanitize($logo_path); ?>" alt="Restaurant Logo" class="hero-logo animated fadeInUp animated-delay-1">
    <?php endif; ?>
    <p class="animated fadeInUp animated-delay-2">Enjoy delicious meals and seamless table reservations.</p>
    <?php if (!isLoggedIn()): ?>
        <div class="hero-buttons">
            <a href="login.php" class="btn animate-click animated fadeInUp animated-delay-3">Login</a>
            <a href="register.php" class="btn animate-click animated fadeInUp animated-delay-4">Register</a>
        </div>
    <?php endif; ?>
</section>

<section class="menu-preview">
    <h2 class="animated fadeIn">Explore Our Menu</h2>
    <?php foreach ($preview_items as $category => $items): ?>
        <?php if (!empty($items)): ?>
            <div class="category-section">
                <h3 class="animated fadeInUp"><?php echo sanitize($category); ?></h3>
                <div class="slider-container">
                    <div class="slider-track" data-category="<?php echo sanitize($category); ?>">
                        <?php $delay = 0; ?>
                        <?php foreach ($items as $item): ?>
                            <div class="slider-item animated fadeInUp animated-delay-<?php echo $delay % 4; ?>">
                                <div class="item-card">
                                    <?php if ($item['image_path'] && file_exists(__DIR__ . '/' . $item['image_path'])): ?>
                                        <img loading="lazy" src="<?php echo sanitize($item['image_path']); ?>" alt="<?php echo sanitize($item['name']); ?>">
                                    <?php endif; ?>
                                    <div class="item-details">
                                        <h4><?php echo sanitize($item['name']); ?></h4>
                                        <p><?php echo sanitize($item['description'] ?: 'A delicious dish!'); ?></p>
                                        <p class="price">$<?php echo number_format($item['price'], 2); ?></p>
                                        <?php if (isLoggedIn()): ?>
                                            <a href="order.php" class="btn animate-click">Order Now</a>
                                        <?php else: ?>
                                            <a href="login.php" class="btn animate-click">Login to Order</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php $delay++; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</section>

<section class="restaurant-gallery">
    <h2 class="animated fadeIn">Our Restaurant</h2>
    <div class="gallery-grid">
        <?php $delay = 0; ?>
        <?php foreach ($restaurant_images as $image): ?>
            <?php if ($image['image_path'] && file_exists(__DIR__ . '/' . $image['image_path'])): ?>
                <div class="gallery-card animated fadeInUp animated-delay-<?php echo $delay % 4; ?>">
                    <img loading="lazy" src="<?php echo sanitize($image['image_path']); ?>" alt="<?php echo sanitize($image['title']); ?>">
                    <div class="gallery-details">
                        <p><?php echo sanitize($image['title']); ?></p>
                    </div>
                </div>
                <?php $delay++; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>

<section class="chefs-gallery">
    <h2 class="animated fadeIn">Meet Our Chefs</h2>
    <div class="gallery-grid">
        <?php $delay = 0; ?>
        <?php foreach ($chef_images as $image): ?>
            <?php if ($image['image_path'] && file_exists(__DIR__ . '/' . $image['image_path'])): ?>
                <div class="gallery-card animated fadeInUp animated-delay-<?php echo $delay % 4; ?>">
                    <img loading="lazy" src="<?php echo sanitize($image['image_path']); ?>" alt="<?php echo sanitize($image['title']); ?>">
                    <div class="gallery-details">
                        <p><?php echo sanitize($image['title']); ?></p>
                    </div>
                </div>
                <?php $delay++; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>

<?php if (isLoggedIn()): ?>
    <section class="full-menu" id="full-menu">
        <h2 class="animated fadeIn">Our Full Menu</h2>
        <?php foreach ($full_menu as $category => $items): ?>
            <?php if (!empty($items)): ?>
                <div class="category-section">
                    <h3 class="animated fadeInUp"><?php echo sanitize($category); ?></h3>
                    <div class="item-grid">
                        <?php $delay = 0; ?>
                        <?php foreach ($items as $item): ?>
                            <div class="item-card animated fadeInUp animated-delay-<?php echo $delay % 4; ?>">
                                <?php if ($item['image_path'] && file_exists(__DIR__ . '/' . $item['image_path'])): ?>
                                    <img loading="lazy" src="<?php echo sanitize($item['image_path']); ?>" alt="<?php echo sanitize($item['name']); ?>">
                                <?php endif; ?>
                                <div class="item-details">
                                    <h4><?php echo sanitize($item['name']); ?></h4>
                                    <p><?php echo sanitize($item['description'] ?: 'A delicious dish!'); ?></p>
                                    <p class="price">$<?php echo number_format($item['price'], 2); ?></p>
                                    <a href="order.php" class="btn animate-click">Order Now</a>
                                </div>
                            </div>
                            <?php $delay++; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>