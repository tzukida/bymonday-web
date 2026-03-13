<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$conn = getDBConnection();
$result = $conn->query("
    SELECT id, username
    FROM users 
    WHERE role = 'staff' AND status = 'active'
    ORDER BY username ASC
");

$staff = [];
while ($row = $result->fetch_assoc()) {
    $staff[] = [
        'id'        => $row['id'],
        'full_name' => $row['username'],
        'email'     => '',
    ];
}

echo json_encode(['success' => true, 'staff' => $staff]);