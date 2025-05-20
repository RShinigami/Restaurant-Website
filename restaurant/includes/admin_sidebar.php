<?php

$active_page = $active_page ?? basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-header" id="sidebar-header">
        <h2>Admin Menu <i class="fas fa-bars toggle-icon" id="toggle-icon"></i></h2>
    </div>
    <nav class="sidebar-nav" id="sidebar-nav">
        <a href="dashboard.php" class="<?php echo $active_page === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="manage_tables.php" class="<?php echo $active_page === 'manage_tables.php' ? 'active' : ''; ?>">
            <i class="fas fa-chair"></i> Manage Tables
        </a>
        <a href="manage_customers.php" class="<?php echo $active_page === 'manage_customers.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Manage Users
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
        cursor: default;
    }

    .sidebar-header h2 {
        font-size: 1.6rem;
        color: #ffd700;
        font-weight: 500;
        letter-spacing: 1px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sidebar-header .toggle-icon {
        display: none;
        margin-left: 0.5rem;
        font-size: 1.2rem;
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
            position: absolute;
            top: 0;
            width: 100%;
            min-height: auto;
            box-shadow: none;
        }

        .sidebar-header {
            padding: 1rem;
            border-bottom: none;
            background: linear-gradient(180deg, #a52a2a 0%, #8b1a1a 100%);
            cursor: pointer;
            text-align: left;
        }

        .sidebar-header h2 {
            font-size: 1.4rem;
            justify-content: space-between;
        }

        .sidebar-header .toggle-icon {
            display: inline-block;
        }

        .sidebar-nav {
            display: none;
            flex-direction: column;
            position: relative;
            top: 0;
            left: 0;
            right: 0;
            background: #fff;
            padding: 1rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            border-radius: 0 0 8px 8px;
            z-index: 901;
        }

        .sidebar-nav.active {
            display: flex;
            flex-direction: column;
            padding: 1rem;
        }

        .sidebar-nav a {
            color: #a52a2a;
            padding: 0.8rem;
            margin: 0.2rem 0;
            background: #f9f9f9;
        }

        .sidebar-nav a:hover {
            background-color: #f0f0f0;
            transform: none;
        }

        .sidebar-nav a.active {
            background-color: #a52a2a;
            color: #fff;
            font-weight: 500;
        }

        .sidebar-nav a.logout {
            border-top: none;
            padding-top: 0.8rem;
        }
    }
</style>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const sidebarHeader = document.getElementById("sidebar-header");
        const sidebarNav = document.getElementById("sidebar-nav");
        const toggleIcon = document.getElementById("toggle-icon");

        // Toggle dropdown on header click
        sidebarHeader.addEventListener("click", (e) => {
            // Only toggle on mobile (≤600px)
            if (window.innerWidth <= 600) {
                sidebarNav.classList.toggle("active");
                toggleIcon.classList.toggle("fa-bars");
                toggleIcon.classList.toggle("fa-chevron-down");
                sidebarHeader.setAttribute("aria-expanded", sidebarNav.classList.contains("active"));
            }
        });

        // Close dropdown when clicking a link
        sidebarNav.querySelectorAll("a").forEach(link => {
            link.addEventListener("click", () => {
                if (window.innerWidth <= 600) {
                    sidebarNav.classList.remove("active");
                    toggleIcon.classList.add("fa-bars");
                    toggleIcon.classList.remove("fa-chevron-down");
                    sidebarHeader.setAttribute("aria-expanded", "false");
                }
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", (e) => {
            if (window.innerWidth <= 600 && !sidebarHeader.contains(e.target) && !sidebarNav.contains(e.target)) {
                sidebarNav.classList.remove("active");
                toggleIcon.classList.add("fa-bars");
                toggleIcon.classList.remove("fa-chevron-down");
                sidebarHeader.setAttribute("aria-expanded", "false");
            }
        });
    });
</script>