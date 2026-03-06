<?php
/* =====================================================
   BYMONDAY PORTAL CONFIGURATION
   ===================================================== */

/* ================== SESSION CONFIG ================== */
session_name('PORTALSESSID');

ini_set('session.gc_maxlifetime', 1800);
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => '/',
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']),
    'samesite' => 'Strict'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================== DATABASE CONFIG ================== */
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'bymonday');

/* ================== APP CONFIG ================== */
define('APP_NAME', 'ByMonday');
define('SESSION_TIMEOUT', 1800);

/* ================== SECURITY CONFIG ================== */
define('CSRF_TOKEN_NAME', '_token');
define('PASSWORD_MIN_LENGTH', 6);

/* ⚠ Change this in production */
define('DEFAULT_RESET_PASSWORD', 'user@123');
define('PASSWORD_RESET_REQUIRED', true);

/* ================== PATH CONFIG ================== */
define('PORTAL_PATH', __DIR__);
define('SUPERADMIN_PATH', PORTAL_PATH . '/superadmin');
define('ADMIN_PATH', PORTAL_PATH . '/admin');
define('STAFF_PATH', PORTAL_PATH . '/staff');
define('INCLUDES_PATH', PORTAL_PATH . '/includes');

/* ================== BASE URL ================== */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];

/*
If running locally:
http://localhost/bymonday/portal

If production:
https://bymonday.com/portal
*/

$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
// Always extract everything up to and including /portal
preg_match('#(.*?/portal)#', $scriptPath, $matches);
$basePath = $matches[1] ?? '/portal';
define('BASE_URL', $scheme . '://' . $host . $basePath);

/* ================== DATABASE CONNECTION ================== */
function getDBConnection() {
    static $connection = null;

    if ($connection === null) {
        $connection = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if ($connection->connect_error) {
            die("Database connection failed: " . $connection->connect_error);
        }

        $connection->set_charset("utf8mb4");
    }

    return $connection;
}

/* ================== ENVIRONMENT CONFIG ================== */
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

/* ================== TIMEZONE ================== */
date_default_timezone_set('Asia/Manila');

/* ================== CSRF TOKEN GENERATION ================== */
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
