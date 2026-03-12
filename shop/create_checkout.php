<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/paymongo_config.php';

header("Content-Type: application/json");

// Prevent accidental HTML output
ini_set('display_errors', 0);
error_reporting(0);

// ─────────────────────────────────────────
// AUTH CHECK
// ─────────────────────────────────────────
if (!isLoggedIn() || $_SESSION['role'] !== 'customer') {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// ─────────────────────────────────────────
// READ JSON INPUT
// ─────────────────────────────────────────
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data || !isset($data['amount']) || !isset($data['order_data'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request data"]);
    exit;
}

$order_data = $data['order_data'];
$_SESSION['pending_order'] = $order_data;

// ─────────────────────────────────────────
// AMOUNT (convert to centavos)
// ─────────────────────────────────────────
$amount = intval(round(floatval($data['amount']) * 100));

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid amount"]);
    exit;
}

// ─────────────────────────────────────────
// PAYMENT METHOD (MUST BE ARRAY)
$requested_method = $data['payment_method'] ?? 'card';

$allowed_methods = ['card', 'gcash', 'paymaya'];

if (!in_array($requested_method, $allowed_methods)) {
    echo json_encode(["error" => "Invalid payment method"]);
    exit;
}

$payment_method_types = [$requested_method];


// ─────────────────────────────────────────
// BUILD LINE ITEMS
// ─────────────────────────────────────────
$line_items = [];

if (!empty($order_data['items']) && is_array($order_data['items'])) {

    foreach ($order_data['items'] as $item) {

        $item_amount = intval(round(floatval($item['price']) * 100));
        if ($item_amount <= 0) continue;

        $name = trim($item['name'] ?? 'Coffee Item');

        if (!empty($item['size'])) {
            $name .= ' (' . strtoupper($item['size']) . ')';
        }

        $line_items[] = [
            "currency" => "PHP",
            "amount"   => $item_amount,
            "name"     => $name,
            "quantity" => intval($item['quantity'] ?? 1)
        ];
    }
}

// Fallback if no items
if (empty($line_items)) {
    $line_items[] = [
        "currency" => "PHP",
        "amount"   => $amount,
        "name"     => "Coffee by Monday Mornings Order",
        "quantity" => 1
    ];
}

// ─────────────────────────────────────────
// SUCCESS & CANCEL URL
// (Make sure BASE_URL is defined in config.php)
// ─────────────────────────────────────────
$success_url = 'http://localhost/bymonday/shop/payment_return.php';
$cancel_url  = BASE_URL . '/checkout.php';

// ─────────────────────────────────────────
// BUILD PAYMONGO PAYLOAD
// ─────────────────────────────────────────
$payload = [
    "data" => [
        "attributes" => [
            "billing" => [
                "name"  => $order_data['customer_name']  ?? '',
                "email" => $order_data['customer_email'] ?? '',
                "phone" => $order_data['customer_phone'] ?? ''
            ],
            "send_email_receipt"   => false,
            "show_description"     => true,
            "show_line_items"      => true,
            "description"          => "Order from Coffee by Monday Mornings",
            "line_items"           => $line_items,
            "payment_method_types" => $payment_method_types,
            "success_url"          => $success_url,
            "cancel_url"           => $cancel_url
        ]
    ]
];

// ─────────────────────────────────────────
// CALL PAYMONGO API
// ─────────────────────────────────────────
$auth = base64_encode(PAYMONGO_SECRET . ":");

$ch = curl_init("https://api.paymongo.com/v1/checkout_sessions");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "Content-Type: application/json",
        "Accept: application/json",
        "Authorization: Basic $auth"
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);

curl_close($ch);

// ─────────────────────────────────────────
// HANDLE CURL ERROR
// ─────────────────────────────────────────
if ($curl_err) {
    http_response_code(500);
    echo json_encode(["error" => "cURL Error: " . $curl_err]);
    exit;
}

// ─────────────────────────────────────────
// HANDLE PAYMONGO ERROR
// ─────────────────────────────────────────
if ($http_code !== 200 && $http_code !== 201) {

    http_response_code($http_code);

    // Forward PayMongo JSON cleanly
    if ($response) {
        echo $response;
    } else {
        echo json_encode(["error" => "Unknown PayMongo error"]);
    }

    exit;
}

// ─────────────────────────────────────────
// SUCCESS — RETURN JSON
// ─────────────────────────────────────────
echo $response;
exit;
