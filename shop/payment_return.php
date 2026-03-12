<?php
define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/config/config.php';         // IMPORTANT
require_once BASE_PATH . '/config/paymongo_config.php';


// Only logged-in customers should land here
if (!isLoggedIn() || $_SESSION['role'] != 'customer') {
    redirect('customer_login.php');
}

// Retrieve the pending order saved in session by create_checkout.php
$order_data = $_SESSION['pending_order'] ?? null;

if (!$order_data) {
    // Nothing to process — send back to menu
    redirect('menu.php');
}

// ── Verify payment with PayMongo (optional but recommended) ──
// PayMongo appends ?session_id=xxx to the success_url
$session_id = $_GET['session_id'] ?? null;
$payment_verified = false;
$paymongo_ref     = null;

if ($session_id) {
    $auth = base64_encode(PAYMONGO_SECRET . ":");
    $ch = curl_init("https://api.paymongo.com/v1/checkout_sessions/" . urlencode($session_id));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "accept: application/json",
            "authorization: Basic $auth"
        ],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    if ($resp) {
        $session = json_decode($resp, true);
        $status = $session['data']['attributes']['status'] ?? '';
        if ($status === 'succeeded') {
            $payment_verified = true;
            $paymongo_ref = $session['data']['id'];
        }
    }
} else {
    // No session_id — could be a direct hit; still allow COD-style save
    $payment_verified = true;
}

$order_id = null;

if ($payment_verified) {
    $user_id = $_SESSION['user_id'];
    $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

    $stmt = $conn->prepare("
        INSERT INTO orders
            (user_id, order_number, customer_name, customer_email, customer_phone,
             customer_address, subtotal, total, payment_method,
             payment_status, order_status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'placed', ?)
    ");

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
        $order_data['payment_method'],
        $order_data['notes']
    );

    if ($stmt->execute()) {
        $order_id = $conn->insert_id;

        $item_stmt = $conn->prepare("
            INSERT INTO order_items
                (order_id, product_id, product_name, size, quantity, price, subtotal)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($order_data['items'] as $item) {
            $item_subtotal = $item['price'] * $item['quantity'];
            $product_id = $item['product_id'] ?? null;
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

        // Insert payment details
        $pay_stmt = $conn->prepare("INSERT INTO payment_details (order_id, payment_method) VALUES (?, ?)");
        $pay_stmt->bind_param("is", $order_id, $order_data['payment_method']);
        $pay_stmt->execute();
        $pay_stmt->close();
    }

    unset($_SESSION['pending_order']);
}

// Debug - remove after fixing
if (!$order_id) {
    echo "<pre>";
    echo "payment_verified: " . ($payment_verified ? 'true' : 'false') . "\n";
    echo "order_data: " . print_r($order_data, true) . "\n";
    echo "Last DB error: " . $conn->error . "\n";
    echo "session_id: " . $session_id . "\n";
    echo "</pre>";
    exit;
}

header("Location: track-order.php?id=" . $order_id);
exit;