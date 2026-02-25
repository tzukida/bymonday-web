<?php
  // Session configuration
  ini_set('session.gc_maxlifetime', 1800);
  session_set_cookie_params(1800);

  // Database Configuration
  if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
  }
  if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', 'root');
  }
  if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', '');
  }
  if (!defined('DB_NAME')) {
    define('DB_NAME', 'bymonday');
  }

  // Application Configuration
  if (!defined('APP_NAME')) {
    define('APP_NAME', 'ByMonday');
  }
  if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 1800);
  }

  // Security Configuration
  if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', '_token');
  }
  if (!defined('PASSWORD_MIN_LENGTH')) {
    define('PASSWORD_MIN_LENGTH', 6);
  }


// Default password for reset
if (!defined('DEFAULT_RESET_PASSWORD')) {
  define('DEFAULT_RESET_PASSWORD', 'user@123');
}
if (!defined('PASSWORD_RESET_REQUIRED')) {
  define('PASSWORD_RESET_REQUIRED', true);
}


  // File paths
  if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
  }
  if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', BASE_PATH . '/includes');
  }
  if (!defined('SUPERADMIN_PATH')) {
    define('SUPERADMIN_PATH', BASE_PATH . '/superadmin');
  }
  if (!defined('ADMIN_PATH')) {
    define('ADMIN_PATH', BASE_PATH . '/admin');
  }
  if (!defined('STAFF_PATH')) {
    define('STAFF_PATH', BASE_PATH . '/staff');
  }

  // Base URL Configuration
  if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $projectPath = '/portal';
    define('BASE_URL', $scheme . '://' . $host . $projectPath);
  }

  // Database Connection Function
  function getDBConnection() {
    static $connection = null;
    if ($connection === null) {
      try {
        $connection = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if ($connection->connect_error) {
          die("Database connection failed: " . $connection->connect_error);
        }
        $connection->set_charset("utf8mb4");
      } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
      }
    }
    return $connection;
  }

  // Error reporting
  if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
  } else {
    error_reporting(0);
    ini_set('display_errors', 0);
  }

  // Timezone setting
  date_default_timezone_set('Asia/Manila');

  // Start session if not already started
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  // Generate CSRF token if not exists
  if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
  }
