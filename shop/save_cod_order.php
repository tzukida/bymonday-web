<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] != 'customer') {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Generate order number
$order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

$stmt = $conn->prepare("
    INSERT INTO orders
    (user_id, order_number, customer_name, customer_email, customer_phone,
     customer_address, subtotal, total, payment_method,
     payment_status, order_status, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'cash', 'pending', 'placed', ?)
");

$stmt->bind_param(
    "isssssdds",
    $user_id,
    $order_number,
    $data['customer_name'],
    $data['customer_email'],
    $data['customer_phone'],
    $data['customer_address'],
    $data['subtotal'],
    $data['total'],
    $data['notes']
);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => $stmt->error]);
    exit;
}

$order_id = $conn->insert_id;

/* Save order items */
$item_stmt = $conn->prepare("
    INSERT INTO order_items
    (order_id, product_id, product_name, size, quantity, price, subtotal)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

foreach ($data['items'] as $item) {
    $subtotal = $item['price'] * $item['quantity'];
    $product_id = $item['product_id'] ?? null;

    $item_stmt->bind_param(
        "iissidd",
        $order_id,
        $product_id,
        $item['name'],
        $item['size'],
        $item['quantity'],
        $item['price'],
        $subtotal
    );
    $item_stmt->execute();
}

/* Insert into payment_details */
$payment_stmt = $conn->prepare("
    INSERT INTO payment_details (order_id, payment_method)
    VALUES (?, 'cash')
");
$payment_stmt->bind_param("i", $order_id);
$payment_stmt->execute();

echo json_encode([
    "success" => true,
    "order_id" => $order_id
]);
