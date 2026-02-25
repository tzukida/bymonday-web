<?php
/* ================= SESSION ================= */
session_name('SHOPSESSID');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,   // true if HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================= PATH CONFIG ================= */
define('SHOP_PATH', __DIR__);
define('ROOT_PATH', dirname(__DIR__)); // points to /bymonday

/* ================= BASE URL ================= */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];

/*
Local:
http://localhost/bymonday/shop

Production:
https://yourdomain.com/shop
*/

define('BASE_URL', $scheme . '://' . $host . '/bymonday/shop');

/* ================= DATABASE ================= */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'coffee_shop'); // keep separate DB if intentional

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

/* ================= PAYMONGO ================= */
if (file_exists(SHOP_PATH . '/paymongo_config.php')) {
    require_once SHOP_PATH . '/paymongo_config.php';
} else {
    define('PAYMONGO_SECRET', getenv('PAYMONGO_SECRET'));
    define('PAYMONGO_PUBLIC', getenv('PAYMONGO_PUBLIC'));
}

/* ================= HELPERS ================= */
function isLoggedIn() {
    return isset($_SESSION['user_id']) &&
           isset($_SESSION['role']) &&
           $_SESSION['role'] === 'customer';
}

function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

function redirect($path) {
    header("Location: " . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}
