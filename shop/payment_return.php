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
    // ── Save order to database ──
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        INSERT INTO orders
            (user_id, customer_name, customer_email, customer_phone,
             customer_address, notes, subtotal, total,
             payment_method, payment_ref, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW())
    ");

    $stmt->bind_param(
        "isssssddss",
        $user_id,
        $order_data['customer_name'],
        $order_data['customer_email'],
        $order_data['customer_phone'],
        $order_data['customer_address'],
        $order_data['notes'],
        $order_data['subtotal'],
        $order_data['total'],
        $order_data['payment_method'],
        $paymongo_ref
    );

    if ($stmt->execute()) {
        $order_id = $conn->insert_id;

        // Save order items
        $item_stmt = $conn->prepare("
            INSERT INTO order_items (order_id, product_name, size, quantity, price)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($order_data['items'] as $item) {
            $item_stmt->bind_param(
                "issid",
                $order_id,
                $item['name'],
                $item['size'],
                $item['quantity'],
                $item['price']
            );
            $item_stmt->execute();
        }
    }

    // Clear the pending order from session
    unset($_SESSION['pending_order']);
}

// ── Redirect to the success page ──
$params = http_build_query([
    'order_id' => $order_id,
    'name'     => $order_data['customer_name'],
    'total'    => $order_data['total'],
    'method'   => $order_data['payment_method'],
    'items'    => count($order_data['items']),
]);

header("Location: payment_success.php?" . $params);
exit;