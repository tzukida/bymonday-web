<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        SELECT id, name, description, price, category, image_url, is_available
        FROM menu_items
        ORDER BY category, name
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];

    while ($row = $result->fetch_assoc()) {
        $has_recipe = count(getRecipeIngredients($row['id'])) > 0;
        $can_fulfill = canFulfillOrder($row['id'], 1);
        $actually_available = (bool)$row['is_available'] && $has_recipe && $can_fulfill;

        $items[] = [
            'id'                 => (int)$row['id'],
            'name'               => $row['name'],
            'description'        => $row['description'],
            'price'              => (float)$row['price'],
            'category'           => $row['category'],
            'image_url'          => $row['image_url'],
            'actually_available' => $actually_available,
        ];
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'items'   => $items,
        'count'   => count($items),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch menu items'
    ]);
}