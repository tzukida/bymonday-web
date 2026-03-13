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

$stmt = $cs->prepare("UPDATE orders SET rider_id = ?, rider_name = ? WHERE id = ?");
$stmt->bind_param("isi", $rider_id, $rider_name, $order_id);
$stmt->execute();
$stmt->close();
$cs->close();

echo json_encode(['success' => true]);