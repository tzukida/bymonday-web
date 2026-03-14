<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] != 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true);
$order_id = intval($data['order_id'] ?? 0);
$reason   = trim($data['reason'] ?? 'Customer requested');
$user_id  = $_SESSION['user_id'];

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order.']);
    exit;
}

$check = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND order_status = 'placed'");
$check->bind_param("ii", $order_id, $user_id);
$check->execute();
$exists = $check->get_result()->num_rows > 0;
$check->close();

if (!$exists) {
    echo json_encode(['success' => false, 'message' => 'Order cannot be cancelled.']);
    exit;
}

$stmt = $conn->prepare("UPDATE orders SET order_status = 'cancelled', cancel_reason = ?, cancelled_by = 'customer' WHERE id = ? AND user_id = ?");
$stmt->bind_param("sii", $reason, $order_id, $user_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);