<?php
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$order_id  = intval($data['order_id'] ?? 0);
$rider_id  = intval($data['rider_id'] ?? 0);
$rider_name = trim($data['rider_name'] ?? '');

if (!$order_id || !$rider_id || !$rider_name) {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit;
}

$cs = new mysqli('localhost', 'root', '', 'coffee_shop');
if ($cs->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error.']);
    exit;
}
$cs->set_charset("utf8mb4");

$assigned_by = $_SESSION['username'] ?? 'Staff';
$stmt = $cs->prepare("UPDATE orders SET rider_id = ?, rider_name = ?, assigned_by = ? WHERE id = ?");
$stmt->bind_param("issi", $rider_id, $rider_name, $assigned_by, $order_id);
$stmt->execute();
$stmt->close();
$cs->close();

echo json_encode(['success' => true]);