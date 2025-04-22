<?php
require_once '.../../config/db.php';
require_once '../../includes/functions.php';
secureSessionStart();

// Restrict to admins
if (!isset($_SESSION['customer_id']) || !$_SESSION['is_admin']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    $response['message'] = 'Invalid request.';
    echo json_encode($response);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'])) {
    $response['message'] = 'Invalid CSRF token.';
    echo json_encode($response);
    exit;
}

$action = $_POST['action'];

try {
    $db->beginTransaction();

    if ($action === 'add_table') {
        $table_number = filter_input(INPUT_POST, 'table_number', FILTER_VALIDATE_INT);
        $description = trim($_POST['description'] ?? '');
        $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);

        if ($table_number <= 0 || $capacity <= 0 || $capacity > 20) {
            $response['message'] = 'Invalid table number or capacity.';
            echo json_encode($response);
            exit;
        }

        $stmt = $db->prepare('SELECT COUNT(*) FROM tables WHERE table_number = ?');
        $stmt->execute([$table_number]);
        if ($stmt->fetchColumn() > 0) {
            $response['message'] = 'Table number already exists.';
            echo json_encode($response);
            exit;
        }

        $stmt = $db->prepare('INSERT INTO tables (table_number, description, capacity) VALUES (?, ?, ?)');
        $stmt->execute([$table_number, $description ?: null, $capacity]);
        $response['success'] = true;
        $response['message'] = 'Table added successfully!';
    }

    elseif ($action === 'update_table') {
        $table_number = filter_input(INPUT_POST, 'table_number', FILTER_VALIDATE_INT);
        $description = trim($_POST['description'] ?? '');
        $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);

        if ($table_number <= 0 || $capacity <= 0 || $capacity > 20) {
            $response['message'] = 'Invalid table number or capacity.';
            echo json_encode($response);
            exit;
        }

        $stmt = $db->prepare('UPDATE tables SET description = ?, capacity = ? WHERE table_number = ?');
        $stmt->execute([$description ?: null, $capacity, $table_number]);
        $response['success'] = true;
        $response['message'] = 'Table updated successfully!';
    }

    elseif ($action === 'delete_table') {
        $table_number = filter_input(INPUT_POST, 'table_number', FILTER_VALIDATE_INT);
        if ($table_number <= 0) {
            $response['message'] = 'Invalid table number.';
            echo json_encode($response);
            exit;
        }

        $stmt = $db->prepare('SELECT COUNT(*) FROM reservations_orders WHERE table_number = ? AND status IN (?, ?)');
        $stmt->execute([$table_number, 'pending', 'confirmed']);
        if ($stmt->fetchColumn() > 0) {
            $response['message'] = 'Cannot delete table with active reservations.';
            echo json_encode($response);
            exit;
        }

        $stmt = $db->prepare('DELETE FROM tables WHERE table_number = ?');
        $stmt->execute([$table_number]);
        $response['success'] = true;
        $response['message'] = 'Table deleted successfully!';
    }

    elseif ($action === 'update_customer') {
        $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
        $username = trim($_POST['username'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $phone = trim($_POST['phone'] ?? '');

        if ($customer_id <= 0 || !$username || !$email) {
            $response['message'] = 'Invalid customer data.';
            echo json_encode($response);
            exit;
        }

        if ($phone && !preg_match('/^\+?\d{7,15}$/', $phone)) {
            $response['message'] = 'Invalid phone number.';
            echo json_encode($response);
            exit;
        }

        $stmt = $db->prepare('SELECT COUNT(*) FROM customers WHERE username = ? AND id != ?');
        $stmt->execute([$username, $customer_id]);
        if ($stmt->fetchColumn() > 0) {
            $response['message'] = 'Username already taken.';
            echo json_encode($response);
            exit;
        }

        $stmt = $db->prepare('UPDATE customers SET username = ?, email = ?, phone = ? WHERE id = ?');
        $stmt->execute([$username, $email, $phone ?: null, $customer_id]);
        $response['success'] = true;
        $response['message'] = 'Customer updated successfully!';
    }

    elseif ($action === 'delete_customer') {
        $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
        if ($customer_id <= 0 || $customer_id === $_SESSION['customer_id']) {
            $response['message'] = 'Invalid customer or cannot delete self.';
            echo json_encode($response);
            exit;
        }

        $stmt = $db->prepare('DELETE FROM reservations_orders WHERE customer_id = ?');
        $stmt->execute([$customer_id]);
        $stmt = $db->prepare('DELETE FROM customers WHERE id = ?');
        $stmt->execute([$customer_id]);
        $response['success'] = true;
        $response['message'] = 'Customer and their records deleted successfully!';
    }

    elseif ($action === 'add_menu_item' || $action === 'update_menu_item') {
        $item_id = $action === 'update_menu_item' ? filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT) : null;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $category = trim($_POST['category'] ?? '');
        $valid_categories = ['Appetizer', 'Salad', 'Main Course', 'Pasta', 'Pizza', 'Dessert', 'Beverage', 'Side'];

        if (!$name || $price < 0 || !in_array($category, $valid_categories)) {
            $response['message'] = 'Invalid menu item data.';
            echo json_encode($response);
            exit;
        }

        $image_path = null;
        if (!empty($_FILES['image']['name'])) {
            $upload_dir = '../public/assets/images/menu/';
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image_name = uniqid() . '.' . $ext;
            $image_path = 'assets/images/menu/' . $image_name;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name)) {
                $response['message'] = 'Failed to upload image.';
                echo json_encode($response);
                exit;
            }
        }

        if ($action === 'add_menu_item') {
            $stmt = $db->prepare('INSERT INTO menu_items (name, description, price, category, image_path) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $description ?: null, $price, $category, $image_path]);
            $response['message'] = 'Menu item added successfully!';
        } else {
            $stmt = $db->prepare('UPDATE menu_items SET name = ?, description = ?, price = ?, category = ?, image_path = COALESCE(?, image_path) WHERE item_id = ?');
            $stmt->execute([$name, $description ?: null, $price, $category, $image_path, $item_id]);
            $response['message'] = 'Menu item updated successfully!';
        }
        $response['success'] = true;
    }

    elseif ($action === 'delete_menu_item') {
        $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
        if ($item_id <= 0) {
            $response['message'] = 'Invalid menu item ID.';
            echo json_encode($response);
            exit;
        }

        $stmt = $db->prepare('SELECT image_path FROM menu_items WHERE item_id = ?');
        $stmt->execute([$item_id]);
        $image_path = $stmt->fetchColumn();
        if ($image_path && file_exists('../public/' . $image_path)) {
            unlink('../public/' . $image_path);
        }

        $stmt = $db->prepare('DELETE FROM order_items WHERE menu_id = ?');
        $stmt->execute([$item_id]);
        $stmt = $db->prepare('DELETE FROM menu_items WHERE item_id = ?');
        $stmt->execute([$item_id]);
        $response['success'] = true;
        $response['message'] = 'Menu item deleted successfully!';
    }

    elseif ($action === 'confirm_reservation' || $action === 'cancel_reservation') {
        $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
        if ($reservation_id <= 0) {
            $response['message'] = 'Invalid reservation ID.';
            echo json_encode($response);
            exit;
        }

        $status = $action === 'confirm_reservation' ? 'confirmed' : 'cancelled';
        $stmt = $db->prepare('UPDATE reservations_orders SET status = ? WHERE id = ? AND type = ?');
        $stmt->execute([$status, $reservation_id, 'reservation']);
        if ($stmt->rowCount() === 0) {
            $response['message'] = 'Reservation not found.';
            echo json_encode($response);
            exit;
        }
        $response['success'] = true;
        $response['message'] = 'Reservation ' . ($action === 'confirm_reservation' ? 'confirmed' : 'cancelled') . ' successfully!';
    }

    elseif ($action === 'delete_reservation') {
        $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
        if ($reservation_id <= 0) {
            $response['message'] = 'Invalid reservation ID.';
            echo json_encode($response);
            exit;
        }

        $stmt = $db->prepare('DELETE FROM reservations_orders WHERE id = ? AND type = ?');
        $stmt->execute([$reservation_id, 'reservation']);
        if ($stmt->rowCount() === 0) {
            $response['message'] = 'Reservation not found.';
            echo json_encode($response);
            exit;
        }
        $response['success'] = true;
        $response['message'] = 'Reservation deleted successfully!';
    }

    elseif ($action === 'confirm_order' || $action === 'cancel_order') {
        $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        if ($order_id <= 0) {
            $response['message'] = 'Invalid order ID.';
            echo json_encode($response);
            exit;
        }

        $status = $action === 'confirm_order' ? 'confirmed' : 'cancelled';
        $stmt = $db->prepare('UPDATE reservations_orders SET status = ? WHERE id = ? AND type = ?');
        $stmt->execute([$status, $order_id, 'order']);
        if ($stmt->rowCount() === 0) {
            $response['message'] = 'Order not found.';
            echo json_encode($response);
            exit;
        }
        $response['success'] = true;
        $response['message'] = 'Order ' . ($action === 'confirm_order' ? 'confirmed' : 'cancelled') . ' successfully!';
    }

    elseif ($action === 'delete_order') {
        $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        if ($order_id <= 0) {
            $response['message'] = 'Invalid order ID.';
            echo json_encode($response);
            exit;
        }

        $stmt = $db->prepare('DELETE FROM order_items WHERE order_id = ?');
        $stmt->execute([$order_id]);
        $stmt = $db->prepare('DELETE FROM reservations_orders WHERE id = ? AND type = ?');
        $stmt->execute([$order_id, 'order']);
        if ($stmt->rowCount() === 0) {
            $response['message'] = 'Order not found.';
            echo json_encode($response);
            exit;
        }
        $response['success'] = true;
        $response['message'] = 'Order deleted successfully!';
    }

    else {
        $response['message'] = 'Unknown action.';
        echo json_encode($response);
        exit;
    }

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Admin handler error: ' . $e->getMessage());
}

echo json_encode($response);
exit;
?>