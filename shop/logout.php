<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

// Store the role before destroying session
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

// Destroy session
session_destroy();

// Redirect based on previous role
if ($role === 'admin') {
    header('Location: pos_login.php');
} else {
    header('Location: index.php');
}
exit;
?>
