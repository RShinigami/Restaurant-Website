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
        .admin-container {
            display: flex;
            background-color: #f9f9f9;
            min-height: 80vh;
            transition: padding-top 0.3s ease;
        }

        .dashboard-content {
            flex: 1;
            max-width: min(1200px, 94vw);
            margin: clamp(1rem, 2vw, 1.5rem) auto;
            margin-left: calc(250px + 1.5rem); /* Sidebar (250px) + gap */
            padding: clamp(1rem, 2vw, 1.5rem);
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, margin 0.3s ease, padding 0.3s ease;
        }

        .dashboard-content:hover {
            transform: translateY(-2px);
        }

        .dashboard-content h1 {
            color: #a52a2a;
            font-size: clamp(1.6rem, 3.5vw, 2rem);
            margin-bottom: 1.2rem;
            text-align: center;
            font-weight: 600;
        }

        .admin-info {
            background: #fff;
            padding: clamp(0.8rem, 1.5vw, 1.2rem);
            border-radius: 5px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
        }

        .admin-info h2 {
            color: #a52a2a;
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            margin-bottom: 0.8rem;
            text-align: center;
            font-weight: 500;
        }

        .admin-info p {
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            color: #333;
            margin: 0.5rem 0;
            display: flex;
            align-items: center;
        }

        .admin-info p i {
            color: #a52a2a;
            margin-right: 0.6rem;
            font-size: clamp(1rem, 2.2vw, 1.2rem);
        }

        .statistics-placeholder {
            text-align: center;
            padding: clamp(0.8rem, 1.5vw, 1.2rem);
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .statistics-placeholder h2 {
            color: #a52a2a;
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            margin-bottom: 0.8rem;
        }

        .statistics-placeholder p {
            color: #555;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
        }

        /* Sidebar width adjustment */
        @media (max-width: 768px) {
            .dashboard-content {
                margin-left: calc(200px + 1rem); /* Sidebar (200px) + gap */
                max-width: 95vw;
                padding: clamp(0.8rem, 1.5vw, 1.2rem);
            }

            .dashboard-content h1 {
                font-size: clamp(1.5rem, 3.2vw, 1.9rem);
            }

            .admin-info h2,
            .statistics-placeholder h2 {
                font-size: clamp(1.1rem, 2.2vw, 1.4rem);
            }

            .admin-info p,
            .statistics-placeholder p {
                font-size: clamp(0.85rem, 1.8vw, 1rem);
            }

            .admin-info p i {
                font-size: clamp(0.9rem, 2vw, 1.1rem);
            }
        }

        /* Navbar transition */
        @media (max-width: 600px) {
            .admin-container {
                flex-direction: column;
                padding-top: 60px; /* Space for navbar */
            }

            .dashboard-content {
                margin-left: 0;
                margin: clamp(0.8rem, 1.8vw, 1rem);
                max-width: 96vw;
                padding: clamp(0.6rem, 1.2vw, 1rem);
            }

            .dashboard-content h1 {
                font-size: clamp(1.4rem, 3vw, 1.8rem);
            }

            .admin-info {
                padding: clamp(0.6rem, 1.2vw, 1rem);
            }

            .admin-info h2 {
                font-size: clamp(1rem, 2vw, 1.3rem);
            }

            .admin-info p {
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
            }

            .admin-info p i {
                font-size: clamp(0.8rem, 1.6vw, 1rem);
            }

            .statistics-placeholder {
                padding: clamp(0.6rem, 1.2vw, 1rem);
            }

            .statistics-placeholder h2 {
                font-size: clamp(1rem, 2vw, 1.3rem);
            }

            .statistics-placeholder p {
                font-size: clamp(0.8rem, 1.6vw, 0.95rem);
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