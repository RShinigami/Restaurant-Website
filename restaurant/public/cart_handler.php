<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
secureSessionStart();

header('Content-Type: application/json');

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Helper: Fetch item details
function getItemDetails($db, $item_id) {
    $stmt = $db->prepare('SELECT name, price FROM menu_items WHERE item_id = ?');
    $stmt->execute([$item_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];

    switch ($action) {
        case 'add':
            $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
            if ($item_id && getItemDetails($db, $item_id)) {
                $_SESSION['cart'][$item_id] = ($_SESSION['cart'][$item_id] ?? 0) + 1;
                $response['success'] = true;
                $response['message'] = 'Item added to cart!';
            } else {
                $response['message'] = 'Invalid item.';
            }
            break;

        case 'remove':
            $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
            if ($item_id && isset($_SESSION['cart'][$item_id])) {
                unset($_SESSION['cart'][$item_id]);
                $response['success'] = true;
                $response['message'] = 'Item removed from cart!';
            } else {
                $response['message'] = 'Item not in cart.';
            }
            break;

        case 'update':
            $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
            $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
            if ($item_id && $quantity >= 0 && isset($_SESSION['cart'][$item_id])) {
                if ($quantity == 0) {
                    unset($_SESSION['cart'][$item_id]);
                } else {
                    $_SESSION['cart'][$item_id] = $quantity;
                }
                $response['success'] = true;
                $response['message'] = 'Cart updated!';
            } else {
                $response['message'] = 'Invalid item or quantity.';
            }
            break;

        case 'clear':
            $_SESSION['cart'] = [];
            $response['success'] = true;
            $response['message'] = 'Cart cleared!';
            break;

        default:
            $response['message'] = 'Invalid action.';
    }

    // Return cart data
    $cart_items = [];
    $total = 0;
    foreach ($_SESSION['cart'] as $item_id => $quantity) {
        $item = getItemDetails($db, $item_id);
        if ($item) {
            $cart_items[] = [
                'item_id' => $item_id,
                'name' => $item['name'],
                'quantity' => $quantity,
                'price' => $item['price'],
                'subtotal' => $item['price'] * $quantity
            ];
            $total += $item['price'] * $quantity;
        }
    }
    $response['cart'] = $cart_items;
    $response['total'] = number_format($total, 2);
    $response['cart_count'] = array_sum($_SESSION['cart']);

    echo json_encode($response);
    exit;
}
?>