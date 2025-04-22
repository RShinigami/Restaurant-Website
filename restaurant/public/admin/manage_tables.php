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
$active_page = 'manage_tables.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        die('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';
    $errors = [];

    if ($action === 'add' || $action === 'edit') {
        $table_number = filter_input(INPUT_POST, 'table_number', FILTER_VALIDATE_INT);
        $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS);

        if ($table_number < 1) {
            $errors[] = 'Invalid table number.';
        }
        if ($capacity < 1) {
            $errors[] = 'Invalid capacity.';
        }

        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $stmt = $db->prepare('INSERT INTO tables (table_number, capacity, description) VALUES (?, ?, ?)');
                    $stmt->execute([$table_number, $capacity, $description ?: null]);
                    echo '<script>showToast("Table added successfully!", "success"); setTimeout(() => location.reload(), 2000);</script>';
                } elseif ($action === 'edit') {
                    $table_id = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
                    if ($table_id) {
                        $stmt = $db->prepare('UPDATE tables SET table_number = ?, capacity = ?, description = ? WHERE table_id = ?');
                        $stmt->execute([$table_number, $capacity, $description ?: null, $table_id]);
                        echo '<script>showToast("Table updated successfully!", "success"); setTimeout(() => location.reload(), 2000);</script>';
                    } else {
                        $errors[] = 'Invalid table ID.';
                    }
                }
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $table_id = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
        if ($table_id) {
            try {
                $stmt = $db->prepare('SELECT COUNT(*) FROM reservations_orders WHERE table_number = (SELECT table_number FROM tables WHERE table_id = ?) AND status IN (?, ?)');
                $stmt->execute([$table_id, 'pending', 'confirmed']);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = 'Cannot delete table with active reservations.';
                } else {
                    $stmt = $db->prepare('DELETE FROM tables WHERE table_id = ?');
                    $stmt->execute([$table_id]);
                    echo '<script>showToast("Table deleted successfully!", "success"); setTimeout(() => location.reload(), 2000);</script>';
                }
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'Invalid table ID.';
        }
    }

    if (!empty($errors)) {
        $error_message = implode(' ', $errors);
        echo '<script>showToast("' . addslashes($error_message) . '", "error");</script>';
    }
}

// Fetch all tables and upcoming reservations
$stmt = $db->query('SELECT * FROM tables ORDER BY table_number');
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch reservations for the next 7 days
$today = (new DateTime())->format('Y-m-d 00:00:00');
$next_week = (new DateTime())->modify('+7 days')->format('Y-m-d 23:59:59');
$stmt = $db->prepare('
    SELECT ro.table_number, ro.date_time, ro.status, c.username 
    FROM reservations_orders ro 
    JOIN customers c ON ro.customer_id = c.customer_id 
    WHERE ro.type = ? AND ro.status IN (?, ?) 
    AND ro.date_time BETWEEN ? AND ? 
    ORDER BY ro.date_time
');
$stmt->execute(['reservation', 'pending', 'confirmed', $today, $next_week]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
$reservations_by_table = [];
foreach ($reservations as $res) {
    $reservations_by_table[$res['table_number']][] = $res;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tables - Restaurant System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <style>
        /* Page-specific styles */
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: linear-gradient(135deg, #f9f9f9 0%, #e0e0e0 100%);
            margin: 0;
        }

        .admin-container {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
            min-height: 100vh;
            background: linear-gradient(135deg, #f9f9f9 0%, #e0e0e0 100%);
        }

        .dashboard-content {
            flex: 1;
            max-width: 1500px; /* Wider for table */
            margin: 3rem auto;
            margin-left: 18%; /* Sidebar width + gap */
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

        h2 {
            color: #a52a2a;
            font-size: 1.5rem;
            margin: 1.5rem 0 1rem;
            font-weight: 500;
        }

        .form-container {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            width: 100%;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            color: #333;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 94%;
            padding: 0.8rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: #a52a2a;
            outline: none;
        }

        .btn {
            background-color: #a52a2a;
            color: #fff;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
        }

        .btn:hover {
            background-color: #7a1717;
            transform: translateY(-2px);
        }

        .btn i {
            margin-right: 0.5rem;
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
            padding: 1rem;
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
        }

        .table td ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .table td li {
            margin: 0.3rem 0;
            color: #555;
        }

        .table .btn {
            margin-right: 0.5rem;
            margin-top: 0.5rem;
            padding: 0.5rem 1rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
        }

        .modal-content h2 {
            color: #a52a2a;
            margin-bottom: 1.5rem;
        }

        .modal-content .btn {
            margin-top: 1rem;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem 2rem;
            border-radius: 6px;
            color: #fff;
            font-size: 1rem;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 1100;
        }

        .toast.success {
            background: #28a745;
        }

        .toast.error {
            background: #dc3545;
        }

        .toast.active {
            opacity: 1;
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

            .table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <section class="admin-container">
        <?php include '../../includes/admin_sidebar.php'; ?>
        <div class="dashboard-content">
            <h1>Manage Tables</h1>

            <!-- Add Table Form -->
            <div class="form-container">
                <h2>Add New Table</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <div class="form-group">
                        <label for="table_number"><i class="fas fa-hashtag"></i> Table Number</label>
                        <input type="number" id="table_number" name="table_number" required min="1">
                    </div>
                    <div class="form-group">
                        <label for="capacity"><i class="fas fa-users"></i> Capacity</label>
                        <input type="number" id="capacity" name="capacity" required min="1">
                    </div>
                    <div class="form-group">
                        <label for="description"><i class="fas fa-info-circle"></i> Description (Optional)</label>
                        <input type="text" id="description" name="description">
                    </div>
                    <button type="submit" class="btn"><i class="fas fa-plus"></i> Add Table</button>
                </form>
            </div>

            <!-- Table List -->
            <div class="table-container">
                <h2>Current Tables</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Table Number</th>
                            <th>Capacity</th>
                            <th>Description</th>
                            <th>Upcoming Reservations</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tables)): ?>
                            <tr><td colspan="5">No tables found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tables as $table): ?>
                                <tr>
                                    <td><?php echo sanitize($table['table_number']); ?></td>
                                    <td><?php echo sanitize($table['capacity']); ?></td>
                                    <td><?php echo sanitize($table['description'] ?: 'None'); ?></td>
                                    <td>
                                        <?php
                                        $table_res = $reservations_by_table[$table['table_number']] ?? [];
                                        if (empty($table_res)) {
                                            echo 'None';
                                        } else {
                                            echo '<ul>';
                                            foreach ($table_res as $res) {
                                                $date = (new DateTime($res['date_time']))->format('Y-m-d h:i A');
                                                echo '<li>' . sanitize($date) . ' (' . sanitize($res['username']) . ', ' . sanitize($res['status']) . ')</li>';
                                            }
                                            echo '</ul>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn edit-btn" data-table='<?php echo json_encode($table); ?>'><i class="fas fa-edit"></i> Edit</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this table?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="table_id" value="<?php echo $table['table_id']; ?>">
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

            <!-- Edit Table Modal -->
            <div class="modal" id="edit-table-modal">
                <div class="modal-content">
                    <h2>Edit Table</h2>
                    <form method="POST" id="edit-table-form">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="table_id" id="edit-table-id">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                        <div class="form-group">
                            <label for="edit-table-number"><i class="fas fa-hashtag"></i> Table Number</label>
                            <input type="number" id="edit-table-number" name="table_number" required min="1">
                        </div>
                        <div class="form-group">
                            <label for="edit-capacity"><i class="fas fa-users"></i> Capacity</label>
                            <input type="number" id="edit-capacity" name="capacity" required min="1">
                        </div>
                        <div class="form-group">
                            <label for="edit-description"><i class="fas fa-info-circle"></i> Description (Optional)</label>
                            <input type="text" id="edit-description" name="description">
                        </div>
                        <button type="submit" class="btn"><i class="fas fa-save"></i> Update Table</button>
                        <button type="button" class="btn" id="cancel-edit-btn"><i class="fas fa-times"></i> Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Toast Notification -->
            <div class="toast" id="toast"></div>
        </div>
    </section>

    <script>
        // Show toast notification
        function showToast(message, type) {
            const toast = document.getElementById("toast");
            if (toast) {
                toast.textContent = message;
                toast.className = `toast ${type} active`;
                setTimeout(() => {
                    toast.className = "toast";
                }, 3000);
            }
        }

        // Edit table modal
        const editModal = document.getElementById("edit-table-modal");
        const editForm = document.getElementById("edit-table-form");
        const cancelEditBtn = document.getElementById("cancel-edit-btn");

        document.querySelectorAll(".edit-btn").forEach(button => {
            button.addEventListener("click", () => {
                const table = JSON.parse(button.getAttribute("data-table"));
                document.getElementById("edit-table-id").value = table.table_id;
                document.getElementById("edit-table-number").value = table.table_number;
                document.getElementById("edit-capacity").value = table.capacity;
                document.getElementById("edit-description").value = table.description || "";
                editModal.classList.add("active");
            });
        });

        cancelEditBtn.addEventListener("click", () => {
            editModal.classList.remove("active");
        });

        editModal.addEventListener("click", (e) => {
            if (e.target === editModal) {
                editModal.classList.remove("active");
            }
        });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>