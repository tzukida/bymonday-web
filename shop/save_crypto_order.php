<?php
/**
 * save_crypto_order.php
 * Saves the pending crypto order to the database after fake payment confirmation.
 */
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$coin  = isset($input['coin']) ? strtoupper($input['coin']) : 'CRYPTO';

$order_data = $_SESSION['pending_crypto_order'] ?? null;

if (!$order_data) {
    echo json_encode(['success' => false, 'message' => 'No pending order found']);
    exit;
}

$user_id      = $_SESSION['user_id'];
$order_number = $order_data['order_id'] ?? ('CBM-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6)));
$payment_ref  = 'CRYPTO-' . $coin . '-' . strtoupper(substr(md5($order_number . time()), 0, 10));

$stmt = $conn->prepare("
    INSERT INTO orders
        (user_id, order_number, customer_name, customer_email, customer_phone,
         customer_address, subtotal, total, payment_method,
         payment_status, order_status, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'pending', ?)
");

$payment_method = 'crypto_' . strtolower($coin);

$stmt->bind_param(
    "isssssddss",
    $user_id,
    $order_number,
    $order_data['customer_name'],
    $order_data['customer_email'],
    $order_data['customer_phone'],
    $order_data['customer_address'],
    $order_data['subtotal'],
    $order_data['total'],
    $payment_method,
    $order_data['notes']
);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
    exit;
}

$order_id = $conn->insert_id;

// Save order items
if (!empty($order_data['items'])) {
    $item_stmt = $conn->prepare("
        INSERT INTO order_items
            (order_id, product_id, product_name, size, quantity, price, subtotal)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($order_data['items'] as $item) {
        $item_subtotal = $item['price'] * $item['quantity'];
        $product_id    = $item['product_id'] ?? null;

        $item_stmt->bind_param(
            "iissidd",
            $order_id,
            $product_id,
            $item['name'],
            $item['size'],
            $item['quantity'],
            $item['price'],
            $item_subtotal
        );
        $item_stmt->execute();
    }
}

// Save payment details
$pay_stmt = $conn->prepare("
    INSERT INTO payment_details (order_id, payment_method, payment_ref)
    VALUES (?, ?, ?)
");
$pay_stmt->bind_param("iss", $order_id, $payment_method, $payment_ref);
$pay_stmt->execute();

// Clear the session
unset($_SESSION['pending_crypto_order']);

echo json_encode([
    'success'      => true,
    'order_id'     => $order_id,
    'order_number' => $order_number,
    'payment_ref'  => $payment_ref
]);