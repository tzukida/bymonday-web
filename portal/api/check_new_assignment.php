<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    echo json_encode(['success' => false]);
    exit;
}

$rider_id = intval($_SESSION['user_id']);

$cs = new mysqli('localhost', 'root', '', 'coffee_shop');
if ($cs->connect_error) {
    echo json_encode(['success' => false]);
    exit;
}
$cs->set_charset("utf8mb4");

$stmt = $cs->prepare("
    SELECT id, order_number, assigned_by
    FROM orders
    WHERE rider_id = ?
    AND order_status = 'brewing'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $rider_id);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = [
        'id'           => $row['id'],
        'order_number' => $row['order_number'],
        'assigned_by'  => $row['assigned_by'] ?? 'Staff',
    ];
}

$stmt->close();
$cs->close();
echo json_encode(['success' => true, 'orders' => $orders]);