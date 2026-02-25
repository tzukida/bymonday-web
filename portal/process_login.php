<?php

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = "Please enter both username and password.";
    header("Location: index.php");
    exit;
  }

  $conn = getDBConnection();
  $stmt = $conn->prepare("SELECT id, username, password, role, status FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();
  $stmt->close();

  if ($user && password_verify($password, $user['password'])) {
      if ($user['status'] !== 'active') {
          $_SESSION['login_error'] = "Your account is inactive. Please contact the administrator.";
          header("Location: index.php");
          exit;
      }

      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['role'] = $user['role'];

      logActivity($user['id'], 'Login', "User logged in from IP: " . $_SERVER['REMOTE_ADDR']);

      if ($user['role'] === 'admin') {
          header("Location: admin/dashboard.php");
      } elseif ($user['role'] === 'staff') {
          header("Location: staff/dashboard.php");
      } else {
          header("Location: superadmin/dashboard.php");
      }
      exit;
  } else {
      // 🔴 Handle invalid username or password
      $_SESSION['login_error'] = "Invalid username or password.";
      header("Location: index.php");
      exit;
  }
} else {
  header("Location: index.php");
  exit;
}
