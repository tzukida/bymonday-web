<?php
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true);
$order_id = intval($data['order_id'] ?? 0);
$status   = $data['status'] ?? '';

$allowed = ['brewing', 'delivery', 'done', 'cancelled'];
if (!$order_id || !in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'coffee_shop');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed.']);
    exit;
}
$conn->set_charset("utf8mb4");

$stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $order_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);