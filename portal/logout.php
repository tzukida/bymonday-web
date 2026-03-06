<?php
  define('BASE_PATH', __DIR__);
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';

  logoutUser();

header('Location: ' . rtrim(BASE_URL, '/') . '/index.php?success=logout');
exit();