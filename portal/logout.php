<?php
  define('BASE_PATH', __DIR__);
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';

  logoutUser();

  redirect('index.php?success=logout');
