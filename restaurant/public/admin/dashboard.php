<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
secureSessionStart();

//clearDB($db);

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
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/css/reset.css">
    <style>
        .dash-wrapper {
            background-color: #f9f9f9;
            min-height: 100vh;
            padding: clamp(1rem, 2vw, 1.5rem);
            margin: 0;
        }

        .insights-panel {
            max-width: min(1280px, 94vw);
            margin: clamp(1.5rem, 2.5vw, 2rem) auto;
            margin-left: calc(250px + 2rem); /* Sidebar (250px) + gap */
            padding: clamp(1.2rem, 2vw, 1.8rem);
            transition: margin-left 0.3s ease, padding 0.3s ease;
        }

        .admin-profile-card {
            background: #fff;
            padding: clamp(1rem, 2vw, 1.5rem);
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: fadeInUp 0.6s ease-out;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .admin-profile-card:hover {
            transform: scale(1.03);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .admin-profile-card i {
            color: #a52a2a;
            font-size: clamp(1.8rem, 3.5vw, 2.2rem);
        }

        .admin-profile-card div {
            flex: 1;
        }

        .admin-profile-card h2 {
            color: #333;
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            font-weight: 600;
            margin: 0 0 0.5rem;
        }

        .admin-profile-card p {
            color: #555;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            margin: 0;
        }

        .insights-title {
            color: #a52a2a;
            font-size: clamp(1.8rem, 3.5vw, 2.2rem);
            font-weight: 700;
            text-align: center;
            margin-bottom: 1.5rem;
            animation: fadeIn 0.5s ease-out;
        }

        .insights-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: clamp(1.5rem, 2.5vw, 2rem);
        }

        @media (max-width: 768px) {
            .insights-panel {
                margin-left: calc(200px + 1.5rem); /* Sidebar (200px) + gap */
                max-width: 95vw;
                padding: clamp(1rem, 1.8vw, 1.5rem);
            }
        }

        @media (max-width: 600px) {
            .dash-wrapper {
                padding: clamp(0.8rem, 1.5vw, 1.2rem);
            }

            .insights-panel {
                margin: clamp(1rem, 2vw, 1.5rem);
                margin-left: 0;
                max-width: 96vw;
                padding: clamp(0.8rem, 1.5vw, 1.2rem);
            }

            .admin-profile-card {
                flex-direction: column;
                align-items: flex-start;
                padding: clamp(0.8rem, 1.5vw, 1.2rem);
            }

            .admin-profile-card i {
                font-size: clamp(1.6rem, 3vw, 2rem);
            }

            .admin-profile-card h2 {
                font-size: clamp(1.1rem, 2.2vw, 1.4rem);
            }

            .admin-profile-card p {
                font-size: clamp(0.85rem, 1.8vw, 1rem);
            }

            .insights-title {
                font-size: clamp(1.6rem, 3vw, 2rem);
            }
        }
    </style>
</head>
<body>
    <div class="dash-wrapper">
        <?php include '../../includes/admin_sidebar.php'; ?>
        <section class="insights-panel">
            <div class="admin-profile-card" role="button" aria-label="Admin Profile">
                <i class="fas fa-user-circle"></i>
                <div>
                    <h2><?php echo sanitize($admin['username']); ?></h2>
                    <p><?php echo sanitize($admin['email']); ?> | <?php echo sanitize($admin['phone'] ?: 'N/A'); ?></p>
                </div>
            </div>
            <h2 class="insights-title">Restaurant Insights</h2>
            <div class="insights-grid">
                <?php include '../../includes/statistics.php'; ?>
            </div>
        </section>
    </div>
</body>
</html>