<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
secureSessionStart();

// Restrict to admins
if (!isset($_SESSION['customer_id']) || !$_SESSION['is_admin']) {
    header('Location: ../public/login.php');
    exit;
}

// Generate CSRF token
$csrf_token = generateCsrfToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Restaurant System</title>
    <style>
        /* Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
        }

        /* Header */
        header {
            background-color: #a52a2a;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 1rem;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-logo {
            color: #fff;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: #a52a2a;
            color: #fff;
            position: fixed;
            top: 0;
            left: -250px;
            height: 100vh;
            transition: transform 0.3s ease-in-out;
            z-index: 900;
            box-shadow: 2px 0 4px rgba(0, 0, 0, 0.1);
        }

        .sidebar.active {
            transform: translateX(250px);
        }

        .sidebar-header {
            padding: 1rem;
            border-bottom: 1px solid #8b1a1a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            color: #ffd700;
        }

        .sidebar-close {
            background: none;
            border: none;
            color: #ffd700;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .sidebar-nav {
            padding: 1rem;
        }

        .sidebar-nav a {
            display: block;
            color: #fff;
            padding: 0.8rem;
            margin: 0.2rem 0;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .sidebar-nav a:hover {
            background-color: #8b1a1a;
        }

        .sidebar-nav a.active {
            background-color: #8b1a1a;
            color: #ffd700;
            font-weight: bold;
        }

        /* Content */
        .dashboard-content {
            max-width: 500px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .dashboard-content h1 {
            color: #a52a2a;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
                left: -200px;
            }

            .sidebar.active {
                transform: translateX(200px);
            }

            .dashboard-content {
                margin: 1rem;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <section class="admin-dashboard">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Admin Menu</h2>
                <button class="sidebar-close" id="sidebar-close" aria-label="Close sidebar">✕</button>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_tables.php">Manage Tables</a>
                <a href="manage_customers.php">Manage Customers</a>
                <a href="manage_menu_items.php">Manage Menu Items</a>
                <a href="manage_reservations.php">Manage Reservations</a>
                <a href="manage_orders.php" class="active">Manage Orders</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>
        <div class="dashboard-content">
            <h1>Manage Orders</h1>
            <p>Placeholder for order management functionality (to be implemented).</p>
        </div>
    </section>

    <?php include '../../includes/footer.php'; ?>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const sidebarToggle = document.querySelector(".sidebar-toggle");
            const sidebar = document.querySelector(".sidebar");
            const sidebarClose = document.querySelector(".sidebar-close");

            if (sidebarToggle && sidebar && sidebarClose) {
                // Toggle sidebar
                sidebarToggle.addEventListener("click", () => {
                    sidebar.classList.toggle("active");
                    sidebarToggle.classList.toggle("active");
                });

                // Close sidebar
                sidebarClose.addEventListener("click", () => {
                    sidebar.classList.remove("active");
                    sidebarToggle.classList.remove("active");
                });

                // Close on outside click
                document.addEventListener("click", (event) => {
                    if (
                        sidebar.classList.contains("active") &&
                        !sidebar.contains(event.target) &&
                        !sidebarToggle.contains(event.target)
                    ) {
                        sidebar.classList.remove("active");
                        sidebarToggle.classList.remove("active");
                    }
                });

                // Close on link click
                sidebar.querySelectorAll(".sidebar-nav a").forEach((link) => {
                    link.addEventListener("click", () => {
                        sidebar.classList.remove("active");
                        sidebarToggle.classList.remove("active");
                    });
                });

                // Close on escape key
                document.addEventListener("keydown", (e) => {
                    if (e.key === "Escape" && sidebar.classList.contains("active")) {
                        sidebar.classList.remove("active");
                        sidebarToggle.classList.remove("active");
                    }
                });
            }
        });
    </script>
</body>
</html>