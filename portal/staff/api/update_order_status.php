<?php
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$order_id  = intval($data['order_id'] ?? 0);
$status    = trim($data['status'] ?? '');
$reason    = trim($data['reason'] ?? '');

$allowed = ['brewing', 'delivery', 'done', 'cancelled'];
if (!$order_id || !in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit;
}

$cs = new mysqli('localhost', 'root', '', 'coffee_shop');
if ($cs->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error.']);
    exit;
}
$cs->set_charset("utf8mb4");

if ($status === 'cancelled') {
    $cancelled_by = $_SESSION['username'] ?? 'Staff';
    $stmt = $cs->prepare("UPDATE orders SET order_status = ?, cancel_reason = ?, cancelled_by = ? WHERE id = ?");
    $stmt->bind_param("sssi", $status, $reason, $cancelled_by, $order_id);
} else {
    $stmt = $cs->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $order_id);
}

$stmt->execute();
$stmt->close();
$cs->close();

echo json_encode(['success' => true]);