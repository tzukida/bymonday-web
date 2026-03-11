<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] != 'customer') {
    echo json_encode(['success' => false, 'message' => 'Login required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$menu_item_id = intval($data['menu_item_id'] ?? 0);
$user_id      = $_SESSION['user_id'];

if (!$menu_item_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid item.']);
    exit;
}

// Check if already favorited
$check = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND menu_item_id = ?");
$check->bind_param("ii", $user_id, $menu_item_id);
$check->execute();
$exists = $check->get_result()->num_rows > 0;
$check->close();

if ($exists) {
    // Remove favorite
    $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND menu_item_id = ?");
    $stmt->bind_param("ii", $user_id, $menu_item_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'action' => 'removed']);
} else {
    // Add favorite
    $stmt = $conn->prepare("INSERT INTO favorites (user_id, menu_item_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $menu_item_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'action' => 'added']);
}