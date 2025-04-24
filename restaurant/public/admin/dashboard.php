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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
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
            transition: margin 0.3s ease, padding 0.3s ease;
        }

        .admin-header {
            background: #fff;
            padding: clamp(0.8rem, 1.5vw, 1.2rem);
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: fadeInDown 0.5s ease-out;
        }

        .admin-header h1 {
            color: #a52a2a;
            font-size: clamp(1.4rem, 3vw, 1.8rem);
            font-weight: 600;
            margin: 0;
        }

        .admin-info {
            font-size: clamp(0.85rem, 2vw, 1rem);
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-info i {
            color: #a52a2a;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: clamp(1rem, 2vw, 1.5rem);
            margin-top: 1.5rem;
        }

        @media (max-width: 768px) {
            .dashboard-content {
                margin-left: calc(200px + 1rem); /* Sidebar (200px) + gap */
                max-width: 95vw;
                padding: clamp(0.8rem, 1.5vw, 1.2rem);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

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

            .admin-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                padding: clamp(0.6rem, 1.2vw, 1rem);
            }

            .admin-header h1 {
                font-size: clamp(1.3rem, 2.8vw, 1.7rem);
            }

            .admin-info {
                font-size: clamp(0.8rem, 1.8vw, 0.95rem);
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <section class="admin-container">
        <?php include '../../includes/admin_sidebar.php'; ?>
        <div class="dashboard-content">
            <div class="admin-header animate__animated animate__fadeInDown">
                <h1>Admin Dashboard</h1>
                <div class="admin-info">
                    <i class="fas fa-user"></i>
                    <span><?php echo sanitize($admin['username']); ?> | <?php echo sanitize($admin['email']); ?> | <?php echo sanitize($admin['phone'] ?: 'N/A'); ?></span>
                </div>
            </div>
            <div class="stats-grid">
                <?php include '../../includes/statistics.php'; ?>
            </div>
        </div>
    </section>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>