<?php
  if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
  }
  require_once BASE_PATH . '/config/config.php';

  function isLoggedIn() {
      return isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role']);
  }
  function isSuperAdmin() {
      return isLoggedIn() && $_SESSION['role'] === 'superadmin';
  }
  function isAdmin() {
      return isLoggedIn() && $_SESSION['role'] === 'admin';
  }
  function isStaff() {
      return isLoggedIn() && $_SESSION['role'] === 'staff';
  }

  function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
      $inactive = time() - $_SESSION['last_activity'];
      if ($inactive >= SESSION_TIMEOUT) {
        session_destroy();
        return false;
      }
    }
    $_SESSION['last_activity'] = time();
    return true;
  }

  function requireAuth() {
    if (!isLoggedIn()) {
      redirect('index.php?error=login_required');
    }
    if (!checkSessionTimeout()) {
      redirect('index.php?error=session_expired');
    }
  }
  function requireSuperAdmin() {
    requireAuth();
    if (!isSuperAdmin()) {
      redirect('/admin/dashboard.php');
    }
  }

  function requireAdmin() {
    requireAuth();
    if (!(isAdmin())) {
      redirect('/staff/dashboard.php');
    }
  }

  function requireStaff() {
    requireAuth();
    if (!(isStaff())) {
      redirect('/index.php');
    }
  }


  function loginUser($user_id, $username, $role) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();

    logUserLogin($user_id);
  }

  function logUserLogin($user_id) {
    $conn = getDBConnection();
    $ip_address = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $conn->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, user_agent) VALUES (?, NOW(), ?, ?)");
    $stmt->bind_param("iss", $user_id, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
  }

  function logUserLogout($user_id) {
    $conn = getDBConnection();
    $ip_address = getClientIP();

    $stmt = $conn->prepare("UPDATE login_logs SET logout_time = NOW() WHERE user_id = ? AND ip_address = ? AND logout_time IS NULL ORDER BY login_time DESC LIMIT 1");
    $stmt->bind_param("is", $user_id, $ip_address);
    $stmt->execute();
    $stmt->close();
  }

  function getClientIP() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
      if (array_key_exists($key, $_SERVER) === true) {
        foreach (explode(',', $_SERVER[$key]) as $ip) {
          $ip = trim($ip);
          if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return $ip;
          }
        }
      }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  }

  function verifyCsrfToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
  }

  function getCsrfTokenField() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . $_SESSION[CSRF_TOKEN_NAME] . '">';
  }

  function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
  }

  function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
  }

  function isValidPassword($password) {
    return strlen($password) >= PASSWORD_MIN_LENGTH;
  }

  function redirect($location) {
    if (!defined('BASE_URL')) {
      die("BASE_URL is not defined.");
    }
    $path = ltrim($location, '/');
    $url = rtrim(BASE_URL, '/') . '/' . $location;
    header("Location: " . $url);
    exit();
  }

  function getBaseURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname($script);

    if ($path === '/' || $path === '\\') {
        $path = '';
    }

    return $protocol . '://' . $host . $path;
  }

  function logoutUser() {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
      );
    }
    session_destroy();
  }

  function canManageUsers() {
      return isSuperAdmin();
  }

  function canResetPasswords() {
      return isSuperAdmin();
  }
?>
