<?php
try {
    $db = new PDO('sqlite:E:\Kinda 9raya xD\JS_PHP\Project\restaurant\public\restaurant.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

function clearDB($db){

    try {
        $stmt = $db->prepare('DELETE FROM reservations_orders WHERE status = ?');
        $stmt->execute(['cancelled']);
    } catch (Exception $e) {
        $error_message = 'Failed to delete cancelled reservations: ' . $e->getMessage();
    }

}
?>
