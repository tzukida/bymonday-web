<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action  = trim($data['action'] ?? '');
$details = trim($data['details'] ?? '');

if (empty($action)) {
    echo json_encode(['success' => false]);
    exit;
}

logActivity($_SESSION['user_id'], $action, $details);
echo json_encode(['success' => true]);