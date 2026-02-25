<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';

requireAdmin(); // Only admin can delete

$menu_item_id = (int)($_GET['id'] ?? 0);

if ($menu_item_id === 0) {
    $_SESSION['error_message'] = 'Invalid menu item ID.';
    header('Location: menu_management.php');
    exit;
}

$conn = getDBConnection();

// Fetch the item first
$stmt = $conn->prepare("SELECT name, image_url FROM menu_items WHERE id = ?");
$stmt->bind_param("i", $menu_item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    $_SESSION['error_message'] = 'Menu item not found.';
    header('Location: menu_management.php');
    exit;
}

// Delete image file if it exists
if (!empty($item['image_url'])) {
    $image_path = BASE_PATH . $item['image_url'];
    if (file_exists($image_path)) {
        unlink($image_path);
    }
}

// Delete the menu item record
$stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
$stmt->bind_param("i", $menu_item_id);

if ($stmt->execute()) {
    logActivity($_SESSION['user_id'], 'Delete Menu Item', "Deleted menu item: {$item['name']}");
    $_SESSION['success_message'] = "Menu item '{$item['name']}' deleted successfully!";
} else {
    $_SESSION['error_message'] = 'Failed to delete menu item: ' . $conn->error;
}

$stmt->close();
$conn->close();

header('Location: menu_management.php');
exit;
?>
