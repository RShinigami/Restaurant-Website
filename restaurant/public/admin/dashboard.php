<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
secureSessionStart();

// Restrict to admins
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}
?>

<?php include '../../includes/header.php'; ?>
<section class="admin-container">
    <h1>Admin Dashboard</h1>
    <p>Welcome, Admin! Manage the restaurant from here.</p>
    <ul class="admin-links">
        <li><a href="customers.php">Manage Customers</a></li>
        <li><a href="menu.php">Manage Menu Items</a></li>
        <li><a href="orders.php">Manage Reservations/Orders</a></li>
        <li><a href="stats.php">View Statistics</a></li>
    </ul>
</section>
<?php include '../../includes/footer.php'; ?>