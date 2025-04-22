<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
secureSessionStart();

// Restrict to admin users
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

// Generate CSRF token
$csrf_token = generateCsrfToken();

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
                    $stmt = $db->prepare('UPDATE tables SET table_number = ?, capacity = ?, description = ? WHERE table_id = ?');
                    $stmt->execute([$table_number, $capacity, $description ?: null, $table_id]);
                    echo '<script>showToast("Table updated successfully!", "success"); setTimeout(() => location.reload(), 2000);</script>';
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

<?php include '../includes/header.php'; ?>

<section class="admin-container">
    <h1>Manage Tables</h1>

    <!-- Add Table Form -->
    <div class="form-container">
        <h2>Add New Table</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
            <div class="form-group">
                <label for="table_number">Table Number</label>
                <input type="number" id="table_number" name="table_number" required min="1">
            </div>
            <div class="form-group">
                <label for="capacity">Capacity</label>
                <input type="number" id="capacity" name="capacity" required min="1">
            </div>
            <div class="form-group">
                <label for="description">Description (Optional)</label>
                <input type="text" id="description" name="description">
            </div>
            <button type="submit" class="btn">Add Table</button>
        </form>
    </div>

    <!-- Table List -->
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
                            <button class="btn edit-btn" data-table='<?php echo json_encode($table); ?>'>Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this table?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="table_id" value="<?php echo $table['table_id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                <button type="submit" class="btn">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Edit Table Modal -->
    <div class="modal" id="edit-table-modal">
        <div class="modal-content">
            <h2>Edit Table</h2>
            <form method="POST" id="edit-table-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="table_id" id="edit-table-id">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                <div class="form-group">
                    <label for="edit-table-number">Table Number</label>
                    <input type="number" id="edit-table-number" name="table_number" required min="1">
                </div>
                <div class="form-group">
                    <label for="edit-capacity">Capacity</label>
                    <input type="number" id="edit-capacity" name="capacity" required min="1">
                </div>
                <div class="form-group">
                    <label for="edit-description">Description (Optional)</label>
                    <input type="text" id="edit-description" name="description">
                </div>
                <button type="submit" class="btn">Update Table</button>
                <button type="button" class="btn" id="cancel-edit-btn">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>
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

<style>
.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}
.table th, .table td {
    padding: 0.8rem;
    border: 1px solid #ccc;
    text-align: left;
}
.table th {
    background-color: #a52a2a;
    color: #fff;
}
.table td button {
    margin-right: 0.5rem;
}
.table ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.table li {
    margin: 0.2rem 0;
}
</style>

<?php include '../includes/footer.php'; ?>
</main>
</body>
</html>