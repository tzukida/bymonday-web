<?php
  define('BASE_PATH', __DIR__);
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';

  require_once BASE_PATH . '/includes/functions.php';
  if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'Logout', 'User logged out');
  }
  logoutUser();

  redirect('index.php?success=logout');
