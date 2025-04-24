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
$active_page = 'manage_menu_items.php';

// Initialize session messages
$_SESSION['success_message'] = $_SESSION['success_message'] ?? '';
$_SESSION['error_message'] = $_SESSION['error_message'] ?? '';

// Upload directory
$upload_dir = '../../public/uploads/';
$upload_path = 'uploads/'; // Base path for DB
$max_file_size = 2 * 1024 * 1024; // 2MB
$allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];

// Ensure upload directory exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'Invalid CSRF token.';
    } else {
        try {
            $action = $_POST['action'] ?? '';

            if ($action === 'add' || $action === 'edit') {
                $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS));
                $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
                $category = trim(filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS));
                $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS));

                // Validate inputs
                if (empty($name)) {
                    throw new Exception('Name is required.');
                }
                if ($price === false || $price < 0) {
                    throw new Exception('Invalid price.');
                }
                $valid_categories = ['Appetizer', 'Main Course', 'Dessert', 'Beverage', 'Salad', 'Side', 'Pasta', 'Pizza', 'Sushi', 'Sandwich', 'Soup'];
                if (!in_array($category, $valid_categories)) {
                    throw new Exception('Invalid category.');
                }

                // Handle image upload
                $image_path = null;
                $old_image = null;
                if ($action === 'edit') {
                    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
                    if (!$item_id) {
                        throw new Exception('Invalid item ID.');
                    }
                    // Get old image for deletion
                    $stmt = $db->prepare('SELECT image_path FROM menu_items WHERE item_id = ?');
                    $stmt->execute([$item_id]);
                    $old_image = $stmt->fetchColumn();
                }

                if (!empty($_FILES['image']['name'])) {
                    $image = $_FILES['image'];
                    if ($image['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('Image upload failed.');
                    }
                    if ($image['size'] > $max_file_size) {
                        throw new Exception('Image size exceeds 2MB.');
                    }
                    if (!in_array($image['type'], $allowed_types)) {
                        throw new Exception('Only JPEG, JPG, and PNG images are allowed.');
                    }

                    // Use original filename, handle conflicts
                    $filename = pathinfo($image['name'], PATHINFO_BASENAME);
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $name_without_ext = pathinfo($filename, PATHINFO_FILENAME);
                    $counter = 0;
                    $new_filename = $filename;
                    $destination = $upload_dir . $new_filename;

                    // Check for existing files and append number if needed
                    while (file_exists($destination)) {
                        $counter++;
                        $new_filename = $name_without_ext . '_' . $counter . '.' . $ext;
                        $destination = $upload_dir . $new_filename;
                    }

                    if (!move_uploaded_file($image['tmp_name'], $destination)) {
                        throw new Exception('Failed to move uploaded image.');
                    }

                    $image_path = $upload_path . $new_filename;

                    // Delete old image if exists (edit mode)
                    if ($action === 'edit' && $old_image && file_exists('../../public/' . $old_image)) {
                        unlink('../../public/' . $old_image);
                    }
                } elseif ($action === 'edit') {
                    // Keep existing image if no new upload
                    $image_path = $old_image;
                }

                if ($action === 'add') {
                    $stmt = $db->prepare('INSERT INTO menu_items (name, price, category, description, image_path) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$name, $price, $category, $description ?: null, $image_path]);
                    $_SESSION['success_message'] = 'Menu item added successfully!';
                } elseif ($action === 'edit') {
                    $stmt = $db->prepare('UPDATE menu_items SET name = ?, price = ?, category = ?, description = ?, image_path = ? WHERE item_id = ?');
                    $stmt->execute([$name, $price, $category, $description ?: null, $image_path, $item_id]);
                    $_SESSION['success_message'] = 'Menu item updated successfully!';
                }
            } elseif ($action === 'delete') {
                $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
                if (!$item_id) {
                    throw new Exception('Invalid item ID.');
                }
                // Get image for deletion
                $stmt = $db->prepare('SELECT image_path FROM menu_items WHERE item_id = ?');
                $stmt->execute([$item_id]);
                $image = $stmt->fetchColumn();

                $stmt = $db->prepare('DELETE FROM menu_items WHERE item_id = ?');
                $stmt->execute([$item_id]);

                // Delete image file
                if ($image && file_exists('../../public/' . $image)) {
                    unlink('../../public/' . $image);
                }

                $_SESSION['success_message'] = 'Menu item deleted successfully!';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }
    }

    // Redirect to prevent form resubmission
    header('Location: manage_menu_items.php');
    exit;
}

// Fetch all menu items
$stmt = $db->query('SELECT * FROM menu_items ORDER BY category, name');
$menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Menu Items - Restaurant System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/css/reset.css">
    <style>

        .admin-container-menu {
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

        .form-container h2, .modal-content h2, .table-container h2 {
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

        .form-group input, .form-group select, .form-group textarea {
            width: 94%;
            padding: 0.8rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input[type="file"] {
            padding: 0.4rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
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
        #edit-menu-item-modal > .modal-content {
            height: 99%;
        }

        .modal-content h2 {
            color: #a52a2a;
            margin-bottom: 0.5rem;
        }

        .modal-content .btn {
            margin-top: 0rem;
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
            .admin-container-menu {
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

            .form-group input, .form-group select, .form-group textarea, .form-group input[type="file"] {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <section class="admin-container-menu">
        <?php include '../../includes/admin_sidebar.php'; ?>
        <!-- Toast Notification -->
        <div class="toast" id="toast"></div>
        <div class="dashboard-content">
            <h1>Manage Menu Items</h1>

            

            <!-- Add Menu Item Form -->
            <div class="form-container">
                <h2>Add New Menu Item</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <div class="form-group">
                        <label for="name"><i class="fas fa-utensils"></i> Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="price"><i class="fas fa-dollar-sign"></i> Price</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="category"><i class="fas fa-list"></i> Category</label>
                        <select id="category" name="category" required>
                            <option value="Appetizer">Appetizer</option>
                            <option value="Main Course">Main Course</option>
                            <option value="Dessert">Dessert</option>
                            <option value="Beverage">Beverage</option>
                            <option value="Salad">Salad</option>
                            <option value="Side">Side</option>
                            <option value="Pasta">Pasta</option>
                            <option value="Pizza">Pizza</option>
                            <option value="Sushi">Sushi</option>
                            <option value="Sandwich">Sandwich</option>
                            <option value="Soup">Soup</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="description"><i class="fas fa-info-circle"></i> Description (Optional)</label>
                        <textarea id="description" name="description"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="image"><i class="fas fa-image"></i> Image (Optional, JPEG/JPG/PNG, max 2MB)</label>
                        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/jpg">
                    </div>
                    <button type="submit" class="btn"><i class="fas fa-plus"></i> Add Menu Item</button>
                </form>
            </div>

            <!-- Menu Items List -->
            <div class="table-container">
                <h2>Current Menu Items</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($menu_items)): ?>
                            <tr><td colspan="5">No menu items found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($menu_items as $item): ?>
                                <tr data-item-id="<?php echo $item['item_id']; ?>">
                                    <td><?php echo sanitize($item['name']); ?></td>
                                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                                    <td><?php echo sanitize($item['category']); ?></td>
                                    <td><?php echo sanitize($item['description'] ?: 'None'); ?></td>
                                    <td class="actions">
                                        <button class="btn edit-btn" data-item='<?php echo json_encode($item); ?>'><i class="fas fa-edit"></i> Edit</button>
                                        <button class="btn delete-btn" data-item-id="<?php echo $item['item_id']; ?>"><i class="fas fa-trash"></i> Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
        <!-- Edit Menu Item Modal -->
        <div class="modal" id="edit-menu-item-modal">
                <div class="modal-content">
                    <h2>Edit Menu Item</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="item_id" id="edit-item-id">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                        <div class="form-group">
                            <label for="edit-name"><i class="fas fa-utensils"></i> Name</label>
                            <input type="text" id="edit-name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-price"><i class="fas fa-dollar-sign"></i> Price</label>
                            <input type="number" id="edit-price" name="price" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-category"><i class="fas fa-list"></i> Category</label>
                            <select id="edit-category" name="category" required>
                                <option value="Appetizer">Appetizer</option>
                                <option value="Main Course">Main Course</option>
                                <option value="Dessert">Dessert</option>
                                <option value="Beverage">Beverage</option>
                                <option value="Salad">Salad</option>
                                <option value="Side">Side</option>
                                <option value="Pasta">Pasta</option>
                                <option value="Pizza">Pizza</option>
                                <option value="Sushi">Sushi</option>
                                <option value="Sandwich">Sandwich</option>
                                <option value="Soup">Soup</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit-description"><i class="fas fa-info-circle"></i> Description (Optional)</label>
                            <textarea id="edit-description" name="description"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit-image"><i class="fas fa-image"></i> Image (Optional, JPEG/JPG/PNG, max 2MB)</label>
                            <input type="file" id="edit-image" name="image" accept="image/jpeg,image/png,image/jpg">
                            <p>Current: <span id="current-image"></span></p>
                        </div>
                        <button type="submit" class="btn"><i class="fas fa-save"></i> Update Menu Item</button>
                        <button type="button" class="btn" id="cancel-edit-btn"><i class="fas fa-times"></i> Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div class="modal" id="delete-menu-item-modal">
                <div class="modal-content">
                    <h2>Confirm Deletion</h2>
                    <p>Are you sure you want to delete this menu item?</p>
                    <form method="POST" id="delete-menu-item-form">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="item_id" id="delete-item-id">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                        <button type="submit" class="btn"><i class="fas fa-trash"></i> Confirm</button>
                        <button type="button" class="btn" id="cancel-delete-btn"><i class="fas fa-times"></i> Cancel</button>
                    </form>
                </div>
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
                }, 2000);
            }
        }

        // Display session-based toast messages on page load
        window.addEventListener("load", () => {
            const successMessage = "<?php echo addslashes($_SESSION['success_message']); ?>";
            const errorMessage = "<?php echo addslashes($_SESSION['error_message']); ?>";
            if (successMessage) {
                showToast(successMessage, "success");
            } else if (errorMessage) {
                showToast(errorMessage, "error");
            }
            // Clear session messages
            <?php unset($_SESSION['success_message'], $_SESSION['error_message']); ?>
        });

        // Edit modal handlers
        const editModal = document.getElementById("edit-menu-item-modal");
        const cancelEditBtn = document.getElementById("cancel-edit-btn");

        document.querySelectorAll(".edit-btn").forEach(button => {
            button.addEventListener("click", () => {
                const item = JSON.parse(button.getAttribute("data-item"));
                document.getElementById("edit-item-id").value = item.item_id;
                document.getElementById("edit-name").value = item.name;
                document.getElementById("edit-price").value = item.price;
                document.getElementById("edit-category").value = item.category;
                document.getElementById("edit-description").value = item.description || "";
                document.getElementById("current-image").innerHTML = item.image_path 
                    ? `<a href="/restaurant/public/${item.image_path}" target="_blank">${item.image_path}</a>` 
                    : 'No image';
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

        // Delete modal handlers
        const deleteModal = document.getElementById("delete-menu-item-modal");
        const deleteForm = document.getElementById("delete-menu-item-form");
        const cancelDeleteBtn = document.getElementById("cancel-delete-btn");

        document.querySelectorAll(".delete-btn").forEach(button => {
            button.addEventListener("click", () => {
                const itemId = button.getAttribute("data-item-id");
                document.getElementById("delete-item-id").value = itemId;
                deleteModal.classList.add("active");
            });
        });

        cancelDeleteBtn.addEventListener("click", () => {
            deleteModal.classList.remove("active");
        });

        deleteModal.addEventListener("click", (e) => {
            if (e.target === deleteModal) {
                deleteModal.classList.remove("active");
            }
        });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>