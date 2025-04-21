<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
secureSessionStart();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $theme = isset($_POST['theme']) && in_array($_POST['theme'], ['white', 'dark']) ? $_POST['theme'] : 'white';
    setUserTheme($db, $theme);
}
?>