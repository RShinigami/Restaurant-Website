<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
secureSessionStart();

clearDB($db);

// Restrict to admins
if (!isset($_SESSION['customer_id']) || !$_SESSION['is_admin']) {
    header('Location: ../public/login.php');
    exit;
}

// Fetch admin info
$stmt = $db->prepare('SELECT username, email, phone FROM customers WHERE customer_id = ?');
$stmt->execute([$_SESSION['customer_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Generate CSRF token
$csrf_token = generateCsrfToken();
$active_page = 'dashboard.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Restaurant System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/css/reset.css">
    <style>
        /* Page-specific styles */

        .admin-container {
            display: flex;
            background-color: #f9f9f9;
            min-height: 80vh; /* Allow natural height with minimum */
        }

        .dashboard-content {
            flex: 1;
            max-width: 1200px;
            margin: 3rem auto;
            margin-left: 270px; /* Sidebar width (250px) + gap */
            padding: 2rem;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, margin 0.3s ease;
        }

        .dashboard-content:hover {
            transform: translateY(-5px);
        }

        .dashboard-content h1 {
            color: #a52a2a;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 600;
        }

        .admin-info {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .admin-info h2 {
            color: #a52a2a;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 500;
        }

        .admin-info p {
            font-size: 1.1rem;
            color: #333;
            margin: 0.5rem 0;
            display: flex;
            align-items: center;
        }

        .admin-info p i {
            color: #a52a2a;
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .statistics-placeholder {
            text-align: center;
            padding: 1.5rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .statistics-placeholder h2 {
            color: #a52a2a;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .statistics-placeholder p {
            color: #555;
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .dashboard-content {
                margin-left: 220px; /* Sidebar width (200px) + gap */
                max-width: 90%;
                padding: 1.5rem;
            }

            .dashboard-content h1 {
                font-size: 1.8rem;
            }

            .admin-info h2,
            .statistics-placeholder h2 {
                font-size: 1.3rem;
            }

            .admin-info p,
            .statistics-placeholder p {
                font-size: 1rem;
            }
        }

        @media (max-width: 600px) {
            .admin-container {
                flex-direction: column;
                padding-top: 60px; /* Space for fixed header */
            }

            .dashboard-content {
                margin-left: 0;
                margin: 1rem;
                max-width: 95%;
                padding: 1rem;
            }

            .dashboard-content h1 {
                font-size: 1.6rem;
            }

            .admin-info {
                padding: 1rem;
            }

            .admin-info h2 {
                font-size: 1.2rem;
            }

            .admin-info p {
                font-size: 0.9rem;
            }

            .statistics-placeholder {
                padding: 1rem;
            }

            .statistics-placeholder h2 {
                font-size: 1.2rem;
            }

            .statistics-placeholder p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <section class="admin-container">
        <?php include '../../includes/admin_sidebar.php'; ?>
        <div class="dashboard-content">
            <h1>Admin Dashboard</h1>
            <div class="admin-info">
                <h2>Welcome, <?php echo sanitize($admin['username']); ?>!</h2>
                <p><i class="fas fa-envelope"></i> Email: <?php echo sanitize($admin['email']); ?></p>
                <p><i class="fas fa-phone"></i> Phone: <?php echo sanitize($admin['phone'] ?: 'N/A'); ?></p>
            </div>
            <div class="statistics-placeholder">
                <h2>Statistics & Charts</h2>
                <p>Placeholder for statistics and charts (to be implemented later).</p>
            </div>
        </div>
    </section>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>