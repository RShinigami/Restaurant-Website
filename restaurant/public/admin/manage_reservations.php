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
$active_page = 'manage_reservations.php';

// Initialize messages
$error_message = '';
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']); // Clear after use

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error_message = 'Invalid CSRF token.';
    } else {
        try {
            $action = $_POST['action'] ?? '';
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new Exception('Invalid reservation ID.');
            }

            if ($action === 'confirm') {
                $stmt = $db->prepare('UPDATE reservations_orders SET status = ? WHERE id = ? AND type = ? AND status = ?');
                $stmt->execute(['confirmed', $id, 'reservation', 'pending']);
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Reservation is already confirmed or cancelled.');
                }
                $_SESSION['success_message'] = 'Reservation confirmed successfully!';
            } elseif ($action === 'delete') {
                $stmt = $db->prepare('DELETE FROM reservations_orders WHERE id = ? AND type = ?');
                $stmt->execute([$id, 'reservation']);
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Reservation not found.');
                }
                $_SESSION['success_message'] = 'Reservation deleted successfully!';
            } else {
                throw new Exception('Invalid action.');
            }

            // Redirect to prevent form resubmission
            header('Location: manage_reservations.php');
            exit;
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Fetch all reservations with customer names
try {
    $stmt = $db->query('
        SELECT r.id, r.customer_id, r.date_time, r.status, r.table_number, r.special_requests, c.username AS customer_name
        FROM reservations_orders r
        JOIN customers c ON r.customer_id = c.customer_id
        WHERE r.type = ?
        ORDER BY r.date_time DESC
    ');
    $stmt->execute(['reservation']);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = 'Failed to fetch reservations: ' . $e->getMessage();
    $reservations = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reservations - Restaurant System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/css/reset.css">
    <style>

        .admin-container-reservations {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
            min-height: 100vh;
            background-color: #f9f9f9;
        }

        .dashboard-content {
            flex: 1;
            max-width: 1500px;
            margin: 3rem auto;
            margin-left: 18%;
            padding: 2.5rem;
            background: linear-gradient(145deg, #ffffff 0%, #f0f0f0 100%);
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
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

        .table-container {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1rem;
        }

        .table th, .table td {
            padding: 1.2rem;
            border: 1px solid #e0e0e0;
            text-align: left;
        }

        .table th {
            background: linear-gradient(145deg, #a52a2a 0%, #7a1717 100%);
            color: #fff;
            font-weight: 600;
        }

        .table td {
            background: #fafafa;
            transition: background 0.3s;
        }

        .table tr:nth-child(even) td {
            background: #f5f5f5;
        }

        .table tr:hover td {
            background: #f0f0f0;
        }

        .table td.actions {
            text-align: center;
            white-space: nowrap;
        }

        .table .btn {
            margin-right: 0.5rem;
            margin-top: 0.5rem;
            padding: 0.5rem 1rem;
            background-color: #a52a2a;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
        }

        .table .btn:hover {
            background-color: #7a1717;
            transform: translateY(-2px);
        }

        .table .btn i {
            margin-right: 0.5rem;
        }

        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 1rem;
        }

        .message.error {
            background: #dc3545;
            color: #fff;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 6px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.5s ease, transform 0.5s ease;
            transform: translateY(-20px);
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 768px) {
            .dashboard-content {
                margin-left: 220px;
                max-width: 90%;
                margin: 2rem auto;
                padding: 2rem;
            }

            .table th, .table td {
                padding: 0.8rem;
            }

            .toast {
                right: 10px;
                left: 10px;
                top: 10px;
            }
        }

        @media (max-width: 600px) {
            .admin-container-reservations {
                flex-direction: column;
            }

            .dashboard-content {
                margin-left: auto;
                max-width: 100%;
                margin: 1rem;
                padding: 1.5rem;
            }

            .table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <section class="admin-container-reservations">
        <?php include '../../includes/admin_sidebar.php'; ?>
        <div class="dashboard-content">
            <h1>Manage Reservations</h1>

            <!-- Error Messages -->
            <?php if ($error_message): ?>
                <div class="message error"><?php echo sanitize($error_message); ?></div>
            <?php endif; ?>

            <!-- Toast Notification -->
            <?php if ($success_message): ?>
                <div class="toast" id="success-toast"><?php echo sanitize($success_message); ?></div>
            <?php endif; ?>

            <!-- Reservations List -->
            <div class="table-container">
                <h2>Current Reservations</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer Name</th>
                            <th>Date & Time</th>
                            <th>Table Number</th>
                            <th>Special Requests</th>
                            <th>Status</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservations)): ?>
                            <tr><td colspan="7">No reservations found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr data-reservation-id="<?php echo $reservation['id']; ?>">
                                    <td><?php echo sanitize($reservation['id']); ?></td>
                                    <td><?php echo sanitize($reservation['customer_name']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($reservation['date_time'])); ?></td>
                                    <td><?php echo $reservation['table_number'] !== null ? sanitize($reservation['table_number']) : 'None'; ?></td>
                                    <td><?php echo $reservation['special_requests'] ? sanitize($reservation['special_requests']) : 'None'; ?></td>
                                    <td><?php echo sanitize(ucfirst($reservation['status'])); ?></td>
                                    <td class="actions">
                                        <?php if ($reservation['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="confirm">
                                                <input type="hidden" name="id" value="<?php echo $reservation['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                                <button type="submit" class="btn"><i class="fas fa-check"></i> Confirm</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this reservation?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $reservation['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                            <button type="submit" class="btn"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <script>
        // Debugging: Log when script runs
        console.log('manage_reservations.php JavaScript loaded');

        // Toast notification handler
        try {
            window.addEventListener("load", () => {
                console.log('Window loaded, checking for toast');
                const toast = document.getElementById("success-toast");
                if (toast) {
                    console.log('Showing toast');
                    toast.classList.add("show");
                    setTimeout(() => {
                        console.log('Hiding toast');
                        toast.classList.remove("show");
                    }, 2000);
                }
            });
        } catch (e) {
            console.error('Error in toast handler:', e);
        }
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>