<?php
// includes/admin_sidebar.php
$active_page = $active_page ?? basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Admin Menu</h2>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="<?php echo $active_page === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="manage_tables.php" class="<?php echo $active_page === 'manage_tables.php' ? 'active' : ''; ?>">
            <i class="fas fa-chair"></i> Manage Tables
        </a>
        <a href="manage_customers.php" class="<?php echo $active_page === 'manage_customers.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Manage Customers
        </a>
        <a href="manage_menu_items.php" class="<?php echo $active_page === 'manage_menu_items.php' ? 'active' : ''; ?>">
            <i class="fas fa-utensils"></i> Manage Menu Items
        </a>
        <a href="manage_reservations.php" class="<?php echo $active_page === 'manage_reservations.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i> Manage Reservations
        </a>
        <a href="manage_orders.php" class="<?php echo $active_page === 'manage_orders.php' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> Manage Orders
        </a>
        <a href="../logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<style>
    .sidebar {
        width: 250px;
        background: linear-gradient(180deg, #a52a2a 0%, #8b1a1a 100%);
        color: #fff;
        position: fixed;
        top: 0;
        left: 0;
        min-height: 100vh;
        box-shadow: 3px 0 10px rgba(0, 0, 0, 0.2);
        z-index: 900;
        font-family: 'Roboto', Arial, sans-serif;
    }

    .sidebar-header {
        padding: 1.5rem 1rem;
        border-bottom: 1px solid #7a1717;
        text-align: center;
    }

    .sidebar-header h2 {
        font-size: 1.6rem;
        color: #ffd700;
        font-weight: 500;
        letter-spacing: 1px;
    }

    .sidebar-nav {
        display: flex;
        flex-direction: column;
        list-style: none;
        padding: 1.5rem;
    }

    .sidebar-nav a {
        display: flex;
        align-items: center;
        color: #fff;
        padding: 0.9rem 1rem;
        margin: 0.3rem 0;
        border-radius: 6px;
        text-decoration: none;
        font-size: 1.1rem;
        font-weight: 400;
        transition: all 0.3s ease;
    }

    .sidebar-nav a i {
        margin-right: 0.75rem;
        font-size: 1.2rem;
    }

    .sidebar-nav a:hover {
        background-color: #7a1717;
        transform: translateX(5px);
    }

    .sidebar-nav a.active {
        background-color: #7a1717;
        color: #ffd700;
        font-weight: 600;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    .sidebar-nav a.logout {
        margin-top: auto;
        border-top: 1px solid #7a1717;
        padding-top: 1rem;
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 200px;
        }
    }

    @media (max-width: 600px) {
        .sidebar {
            position: static;
            width: 100%;
            min-height: auto;
        }
    }
</style>