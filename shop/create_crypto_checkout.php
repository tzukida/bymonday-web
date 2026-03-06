<?php

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['error' => 'Invalid request data']);
    exit;
}

$amount     = floatval($input['amount'] ?? 0);
$order_data = $input['order_data'] ?? [];

if ($amount <= 0) {
    echo json_encode(['error' => 'Invalid amount']);
    exit;
}


$order_id = 'CBM-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

$order_data['order_id']       = $order_id;
$order_data['total']          = $amount;
$order_data['payment_method'] = 'crypto';

$_SESSION['pending_crypto_order'] = $order_data;

echo json_encode([
    'invoice_url' => BASE_URL . '/crypto_payment.php'
]);