<?php
try {
    $db = new PDO('sqlite:E:\Kinda 9raya xD\JS_PHP\Project\restaurant\public\restaurant.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}
?>
