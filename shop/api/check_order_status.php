<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] != 'customer') {
    echo json_encode(['success' => false]);
    exit;
}

$user_id       = $_SESSION['user_id'];
$include_final = isset($_GET['include_final']) && $_GET['include_final'] == 1;
$order_id      = intval($_GET['order_id'] ?? 0);

if ($include_final && $order_id) {
    $stmt = $conn->prepare("
        SELECT id, order_number, order_status
        FROM orders
        WHERE user_id = ? AND id = ?
        AND order_status IN ('done', 'cancelled')
    ");
    $stmt->bind_param("ii", $user_id, $order_id);
} else {
    $stmt = $conn->prepare("
        SELECT id, order_number, order_status
        FROM orders
        WHERE user_id = ?
        AND order_status IN ('placed', 'brewing', 'delivery')
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = [
        'id'           => $row['id'],
        'order_number' => $row['order_number'],
        'order_status' => $row['order_status'],
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'orders' => $orders]);