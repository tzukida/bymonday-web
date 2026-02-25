<?php
require_once 'config.php';

if (!isset($_SESSION['payment_verified']) || $_SESSION['payment_verified'] !== true) {
    die("Unauthorized access.");
}

if (!isset($_SESSION['pending_order'])) {
    echo "Session lost. Please try again.";
    exit;
}


$orderData = $_SESSION['pending_order'];

unset($_SESSION['payment_verified']);
unset($_SESSION['pending_order']);

$conn->begin_transaction();

try {

    $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO orders 
        (user_id, order_number, customer_name, customer_email, customer_phone, customer_address, subtotal, total, payment_method, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "isssssddss",
        $user_id,
        $order_number,
        $orderData['customer_name'],
        $orderData['customer_email'],
        $orderData['customer_phone'],
        $orderData['customer_address'],
        $orderData['subtotal'],
        $orderData['total'],
        $orderData['payment_method'],
        $orderData['notes']
    );

    $stmt->execute();
    $order_id = $conn->insert_id;

    $stmt = $conn->prepare("INSERT INTO order_items 
        (order_id, product_id, product_name, size, quantity, price, subtotal)
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($orderData['items'] as $item) {

        $item_subtotal = $item['price'] * $item['quantity'];

        $stmt->bind_param(
            "iissidd",
            $order_id,
            $item['product_id'],
            $item['name'],
            $item['size'],
            $item['quantity'],
            $item['price'],
            $item_subtotal
        );

        $stmt->execute();
    }

    $conn->commit();

    echo "<h2>Order Placed Successfully!</h2>";
    echo "<p>Order Number: $order_number</p>";
    echo "<a href='menu.php'>Back to Menu</a>";

} catch (Exception $e) {
    $conn->rollback();
    echo "Error saving order.";
}
