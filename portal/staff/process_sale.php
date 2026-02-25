<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';

requireAuth();

header('Content-Type: application/json');

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid order data']);
    exit;
}

$conn = getDBConnection();

try {
    // Start transaction
    $conn->begin_transaction();

    $user_id = $_SESSION['user_id'];
    $customer_name = sanitizeInput($data['customer_name'] ?? '');
    $payment_method = sanitizeInput($data['payment_method'] ?? 'cash');
    $remarks = sanitizeInput($data['remarks'] ?? '');
    $total_amount = floatval($data['total_amount']);

    // Validate stock availability for all items
    foreach ($data['items'] as $item) {
        $menu_item_id = intval($item['id']);
        $quantity = intval($item['quantity']);

        // Check if we can fulfill this order
        if (!canFulfillOrder($menu_item_id, $quantity)) {
            throw new Exception("Insufficient stock for " . $item['name']);
        }
    }

    // Insert sale record
    $stmt = $conn->prepare("INSERT INTO sales (user_id, total_amount, payment_method, customer_name, remarks) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idsss", $user_id, $total_amount, $payment_method, $customer_name, $remarks);
    $stmt->execute();
    $sale_id = $conn->insert_id;
    $stmt->close();

    // Process each item
    foreach ($data['items'] as $item) {
        $menu_item_id = intval($item['id']);
        $quantity = intval($item['quantity']);
        $unit_price = floatval($item['price']);
        $subtotal = $unit_price * $quantity;

        // Insert sale item
        $stmt = $conn->prepare("INSERT INTO sales_items (sale_id, menu_item_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiidd", $sale_id, $menu_item_id, $quantity, $unit_price, $subtotal);
        $stmt->execute();
        $stmt->close();

        // Deduct ingredients from inventory
        deductIngredientsForMenuItem($menu_item_id, $quantity, $user_id, $sale_id);
    }

    // Log activity
    logActivity($user_id, 'Process Sale', "Processed sale #$sale_id - Total: ₱" . number_format($total_amount, 2));

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order processed successfully',
        'sale_id' => $sale_id
    ]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
