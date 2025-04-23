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
    <style>
        /* Page-specific styles */
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background-color: white;
            margin: 0;
            padding: 0;
        }

        .admin-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: white;
        }

        .dashboard-content {
            flex: 1;
            width: 100%;
            margin: 3rem auto;
            margin-left: 200px; /* Sidebar width + gap */
            padding: 2.5rem;
            box-shadow: none;
            background-color: white;
            border-radius: 10px;
            transition: transform 0.3s ease;
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
                margin-left: 220px; /* Adjusted for sidebar */
                max-width: 500px;
                margin: 2rem auto;
                padding: 2rem;
            }
        }

        @media (max-width: 600px) {
            .admin-container {
                flex-direction: column;
            }

            .dashboard-content {
                margin-left: auto;
                max-width: 100%;
                margin: 1rem;
                padding: 1.5rem;
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