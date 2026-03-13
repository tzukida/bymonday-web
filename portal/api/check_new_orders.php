<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    echo json_encode(['success' => false]);
    exit;
}

$cs = new mysqli('localhost', 'root', '', 'coffee_shop');
if ($cs->connect_error) {
    echo json_encode(['success' => false]);
    exit;
}
$cs->set_charset("utf8mb4");

$result = $cs->query("
    SELECT id, order_number, customer_name, total, delivery_fee, created_at
    FROM orders
    WHERE order_status = 'placed'
    ORDER BY created_at DESC
    LIMIT 20
");

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = [
        'id'            => $row['id'],
        'order_number'  => $row['order_number'],
        'customer_name' => $row['customer_name'],
        'total'         => $row['total'] + ($row['delivery_fee'] ?? 50),
        'created_at'    => $row['created_at'],
    ];
}

$cs->close();
echo json_encode(['success' => true, 'orders' => $orders]);