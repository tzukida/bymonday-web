<?php

  if (!defined('BASE_PATH')) {
    die('Direct access not permitted');
  }

  function sanitizeInput($data) {
    if (is_array($data)) {
      foreach ($data as $key => $value) {
        $data[$key] = sanitizeInput($value);
      }
    } else {
      $data = trim($data);
      $data = stripslashes($data);
      $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
  }

  function validateRequired($fields, $data) {
    $errors = [];
    foreach ($fields as $field) {
      if (empty($data[$field])) {
        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
      }
    }
    return $errors;
  }

  function getUserByUsername($username) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, username, password, role, status FROM users WHERE username = ? AND status = 'active'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
  }

  function getUserById($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, username, role, status, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
  }

  function getAllUsers($limit = null, $offset = 0) {
      $conn = getDBConnection();
      $sql = "SELECT id, username, role, status, created_at, last_password_reset FROM users ORDER BY created_at DESC";

      if ($limit !== null) {
          $sql .= " LIMIT ? OFFSET ?";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param("ii", $limit, $offset);
      } else {
          $stmt = $conn->prepare($sql);
      }

      $stmt->execute();
      $result = $stmt->get_result();
      $users = $result->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
      return $users;
  }


  function countUsers() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $row = $result->fetch_assoc();
    return $row['total'];
  }

  function getInventoryItems($search = '', $filter = '', $limit = 25, $offset = 0) {
    $conn = getDBConnection();
    $where = "1=1";
    $types = '';
    $params = [];

    if (!empty($search)) {
      $where .= " AND (item_name LIKE ? OR description LIKE ?)";
      $search_param = "%$search%";
      $params[] = $search_param;
      $params[] = $search_param;
      $types .= 'ss';
    }
    if ($filter === 'low_stock') {
      $where .= " AND quantity < 10";
    }
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE $where ORDER BY updated_at DESC LIMIT ? OFFSET ?");
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  function countInventoryItems($search = '', $filter = '') {
    $conn = getDBConnection();
    $where = "1=1";
    $types = '';
    $params = [];

    if (!empty($search)) {
      $where .= " AND (item_name LIKE ? OR description LIKE ?)";
      $search_param = "%$search%";
      $params[] = $search_param;
      $params[] = $search_param;
      $types .= 'ss';
    }

    if ($filter === 'low_stock') {
      $where .= " AND quantity < 10";
    }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM inventory WHERE $where");
    if (!empty($params)) {
      $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    return $total;
  }

  function getInventoryItemById($item_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, item_name, description, unit, quantity, created_at, updated_at FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    return $item;
  }

  function getLowStockItems() {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, item_name, quantity, unit FROM inventory WHERE quantity < 10 ORDER BY quantity ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $items;
  }

  function getTransactions($user_id = null, $limit = null, $offset = 0) {
    $conn = getDBConnection();
    $sql = "SELECT t.id, t.type, t.quantity, t.remarks, t.timestamp, u.username, i.item_name, i.unit
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            JOIN inventory i ON t.item_id = i.id";

    $params = [];
    $types = '';

    if ($user_id !== null) {
      $sql .= " WHERE t.user_id = ?";
      $params[] = $user_id;
      $types .= 'i';
    }

    $sql .= " ORDER BY t.timestamp DESC";

    if ($limit !== null) {
      $sql .= " LIMIT ? OFFSET ?";
      $params[] = $limit;
      $params[] = $offset;
      $types .= 'ii';
    }

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
      $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $transactions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $transactions;
  }

  function countTransactions($user_id = null) {
    $conn = getDBConnection();
    $sql = "SELECT COUNT(*) as total FROM transactions";

    if ($user_id !== null) {
      $sql .= " WHERE user_id = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i", $user_id);
    } else {
      $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'];
  }

  function addTransaction($user_id, $item_id, $type, $quantity, $remarks = '') {
    $conn = getDBConnection();

    try {
      $stmt = $conn->prepare("
        INSERT INTO transactions (user_id, item_id, type, quantity, remarks, timestamp)
        VALUES (?, ?, ?, ?, ?, NOW())
      ");
      $stmt->bind_param("iisis", $user_id, $item_id, $type, $quantity, $remarks);
      $stmt->execute();
      $stmt->close();
      return true;
    } catch (Exception $e) {
      return false;
    }
  }

  function getLoginLogs($limit = null, $offset = 0) {
    $conn = getDBConnection();
    $sql = "SELECT l.id, l.login_time, l.logout_time, l.ip_address, u.username
            FROM login_logs l
            JOIN users u ON l.user_id = u.id
            ORDER BY l.login_time DESC";

    if ($limit !== null) {
      $sql .= " LIMIT ? OFFSET ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ii", $limit, $offset);
    } else {
      $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $logs;
  }

  function countLoginLogs() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT COUNT(*) as total FROM login_logs");
    $row = $result->fetch_assoc();
    return $row['total'];
  }

  function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
  }

  function formatNumber($number) {
    return number_format($number);
  }

  function exportToCSV($filename, $data, $headers) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);

    foreach ($data as $row) {
      fputcsv($output, $row);
    }

    fclose($output);
    exit();
  }

  function generateDatabaseBackup() {
    $conn = getDBConnection();
    $backup = "-- Meals Inventory Management System Database Backup\n";
    $backup .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";

    $tables = ['users', 'inventory', 'transactions', 'login_logs'];

    foreach ($tables as $table) {
      $backup .= "-- Table structure for table `$table`\n";
      $result = $conn->query("SHOW CREATE TABLE `$table`");
      $row = $result->fetch_assoc();
      $backup .= $row['Create Table'] . ";\n\n";

      $backup .= "-- Dumping data for table `$table`\n";
      $result = $conn->query("SELECT * FROM `$table`");

      while ($row = $result->fetch_assoc()) {
        $backup .= "INSERT INTO `$table` VALUES (";
        $values = [];
        foreach ($row as $value) {
          $values[] = $value === null ? 'NULL' : "'" . addslashes($value) . "'";
        }
        $backup .= implode(', ', $values) . ");\n";
      }
        $backup .= "\n";
    }
    return $backup;
  }

  function usernameExists($username, $exclude_id = null) {
    $conn = getDBConnection();
    $sql = "SELECT COUNT(*) as count FROM users WHERE username = ?";

    if ($exclude_id !== null) {
      $sql .= " AND id != ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("si", $username, $exclude_id);
    } else {
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("s", $username);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['count'] > 0;
  }

  function adminUrl(string $path = ''): string {
    return rtrim(BASE_URL, '/') . '/admin/' . ltrim($path, '/');
  }

  function logActivity($user_id, $action, $details = '') {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $action, $details);
    $stmt->execute();
    $stmt->close();
  }

  function logLogin($user_id, $ip_address = null) {
    $conn = getDBConnection();
    $action = "Login";
    $details = "User logged in" . ($ip_address ? " from IP: $ip_address" : "");
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $action, $details);
    $stmt->execute();
    $stmt->close();
  }

  function getDashboardStats() {
    $conn = getDBConnection();
    $stats = [];

    $stats['total_items'] = $conn->query("SELECT COUNT(*) AS count FROM inventory")->fetch_assoc()['count'];
    $stats['total_users'] = $conn->query("SELECT COUNT(*) AS count FROM users WHERE status = 'active'")->fetch_assoc()['count'];
    $stats['total_transactions'] = $conn->query("SELECT COUNT(*) AS count FROM transactions")->fetch_assoc()['count'];

    $row = $conn->query("SELECT IFNULL(SUM(quantity),0) AS total FROM transactions WHERE type = 'stock-in'")->fetch_assoc();
    $stats['total_stock_in'] = $row['total'];

    $row = $conn->query("SELECT IFNULL(SUM(quantity),0) AS total FROM transactions WHERE type = 'stock-out'")->fetch_assoc();
    $stats['total_stock_out'] = $row['total'];

    $stats['low_stock_count'] = $conn->query("
      SELECT COUNT(*) AS count
      FROM inventory
      WHERE quantity < 10
    ")->fetch_assoc()['count'];
    return $stats;
  }

  // Add these functions to your existing functions.php file

  // ========== POS FUNCTIONS ==========

  /**
   * Get all available menu items with stock check
   */
  function getAvailableMenuItems() {
      $conn = getDBConnection();
      $stmt = $conn->prepare("
          SELECT DISTINCT m.id, m.name, m.description, m.price, m.category, m.image_url, m.is_available,
                 (SELECT COUNT(*) FROM recipe_ingredients ri WHERE ri.menu_item_id = m.id) as ingredient_count
          FROM menu_items m
          WHERE m.is_available = TRUE
          ORDER BY m.category, m.name
      ");
      $stmt->execute();
      $result = $stmt->get_result();
      $items = [];

      while ($row = $result->fetch_assoc()) {
          // Check if we can fulfill this order
          $can_fulfill = canFulfillOrder($row['id'], 1);
          $row['can_fulfill'] = $can_fulfill;

          // Only add if not already in array (prevent duplicates)
          $exists = false;
          foreach ($items as $item) {
              if ($item['id'] == $row['id']) {
                  $exists = true;
                  break;
              }
          }

          if (!$exists) {
              $items[] = $row;
          }
      }

      $stmt->close();
      return $items;
  }

  /**
   * Check if we have enough ingredients to fulfill an order
   */
  function canFulfillOrder($menu_item_id, $quantity = 1) {
      $conn = getDBConnection();

      // Get all required ingredients for this menu item
      $stmt = $conn->prepare("
          SELECT ri.inventory_item_id, ri.quantity_needed, i.quantity as available_quantity
          FROM recipe_ingredients ri
          JOIN inventory i ON ri.inventory_item_id = i.id
          WHERE ri.menu_item_id = ?
      ");
      $stmt->bind_param("i", $menu_item_id);
      $stmt->execute();
      $result = $stmt->get_result();

      $can_fulfill = true;

      while ($row = $result->fetch_assoc()) {
          $needed = $row['quantity_needed'] * $quantity;
          $available = $row['available_quantity'];

          if ($available < $needed) {
              $can_fulfill = false;
              break;
          }
      }

      $stmt->close();
      return $can_fulfill;
  }

  /**
   * Deduct ingredients from inventory for a menu item
   */
  function deductIngredientsForMenuItem($menu_item_id, $quantity, $user_id, $sale_id) {
      $conn = getDBConnection();

      // Get all ingredients for this menu item
      $stmt = $conn->prepare("
          SELECT ri.inventory_item_id, ri.quantity_needed, i.item_name
          FROM recipe_ingredients ri
          JOIN inventory i ON ri.inventory_item_id = i.id
          WHERE ri.menu_item_id = ?
      ");
      $stmt->bind_param("i", $menu_item_id);
      $stmt->execute();
      $result = $stmt->get_result();

      while ($row = $result->fetch_assoc()) {
          $inventory_item_id = $row['inventory_item_id'];
          $quantity_to_deduct = $row['quantity_needed'] * $quantity;

          // Update inventory
          $update_stmt = $conn->prepare("
              UPDATE inventory
              SET quantity = quantity - ?
              WHERE id = ?
          ");
          $update_stmt->bind_param("di", $quantity_to_deduct, $inventory_item_id);
          $update_stmt->execute();
          $update_stmt->close();

          // Log transaction
          $trans_stmt = $conn->prepare("
              INSERT INTO transactions (user_id, item_id, type, quantity, remarks)
              VALUES (?, ?, 'stock-out', ?, ?)
          ");
          $remarks = "POS Sale #$sale_id - Auto deduction";
          $trans_stmt->bind_param("iids", $user_id, $inventory_item_id, $quantity_to_deduct, $remarks);
          $trans_stmt->execute();
          $trans_stmt->close();
      }

      $stmt->close();
  }

  /**
   * Get menu item by ID
   */
  function getMenuItemById($menu_item_id) {
      $conn = getDBConnection();
      $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ?");
      $stmt->bind_param("i", $menu_item_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $item = $result->fetch_assoc();
      $stmt->close();
      return $item;
  }

  /**
   * Get all menu items (for management)
   */
  function getAllMenuItems($limit = null, $offset = 0) {
      $conn = getDBConnection();
      $sql = "SELECT * FROM menu_items ORDER BY category, name";

      if ($limit !== null) {
          $sql .= " LIMIT ? OFFSET ?";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param("ii", $limit, $offset);
      } else {
          $stmt = $conn->prepare($sql);
      }

      $stmt->execute();
      $result = $stmt->get_result();
      $items = $result->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
      return $items;
  }

  /**
   * Get recipe ingredients for a menu item
   */
  function getRecipeIngredients($menu_item_id) {
      $conn = getDBConnection();
      $stmt = $conn->prepare("
          SELECT ri.*, i.item_name, i.unit as inventory_unit, i.quantity as available_quantity
          FROM recipe_ingredients ri
          JOIN inventory i ON ri.inventory_item_id = i.id
          WHERE ri.menu_item_id = ?
          ORDER BY i.item_name
      ");
      $stmt->bind_param("i", $menu_item_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $ingredients = $result->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
      return $ingredients;
  }

  /**
   * Get sales records
   */
  function getSales($user_id = null, $limit = null, $offset = 0, $date_from = null, $date_to = null) {
      $conn = getDBConnection();

      $where_conditions = [];
      $params = [];
      $types = '';

      if ($user_id !== null) {
          $where_conditions[] = "s.user_id = ?";
          $params[] = $user_id;
          $types .= 'i';
      }

      if ($date_from !== null) {
          $where_conditions[] = "DATE(s.sale_date) >= ?";
          $params[] = $date_from;
          $types .= 's';
      }

      if ($date_to !== null) {
          $where_conditions[] = "DATE(s.sale_date) <= ?";
          $params[] = $date_to;
          $types .= 's';
      }

      $where_sql = '';
      if (!empty($where_conditions)) {
          $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
      }

      $sql = "SELECT s.*, u.username
              FROM sales s
              JOIN users u ON s.user_id = u.id
              $where_sql
              ORDER BY s.sale_date DESC";

      if ($limit !== null) {
          $sql .= " LIMIT ? OFFSET ?";
          $params[] = $limit;
          $params[] = $offset;
          $types .= 'ii';
      }

      $stmt = $conn->prepare($sql);
      if (!empty($params)) {
          $stmt->bind_param($types, ...$params);
      }

      $stmt->execute();
      $result = $stmt->get_result();
      $sales = $result->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
      return $sales;
  }

  /**
   * Get sale by ID with items
   */
  function getSaleById($sale_id) {
      $conn = getDBConnection();

      // Get sale info
      $stmt = $conn->prepare("
          SELECT s.*, u.username
          FROM sales s
          JOIN users u ON s.user_id = u.id
          WHERE s.id = ?
      ");
      $stmt->bind_param("i", $sale_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $sale = $result->fetch_assoc();
      $stmt->close();

      if (!$sale) {
          return null;
      }

      // Get sale items
      $stmt = $conn->prepare("
          SELECT si.*, m.name as menu_item_name
          FROM sales_items si
          JOIN menu_items m ON si.menu_item_id = m.id
          WHERE si.sale_id = ?
      ");
      $stmt->bind_param("i", $sale_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $sale['items'] = $result->fetch_all(MYSQLI_ASSOC);
      $stmt->close();

      return $sale;
  }

  /**
   * Count total sales
   */
  function countSales($user_id = null, $date_from = null, $date_to = null) {
      $conn = getDBConnection();

      $where_conditions = [];
      $params = [];
      $types = '';

      if ($user_id !== null) {
          $where_conditions[] = "user_id = ?";
          $params[] = $user_id;
          $types .= 'i';
      }

      if ($date_from !== null) {
          $where_conditions[] = "DATE(sale_date) >= ?";
          $params[] = $date_from;
          $types .= 's';
      }

      if ($date_to !== null) {
          $where_conditions[] = "DATE(sale_date) <= ?";
          $params[] = $date_to;
          $types .= 's';
      }

      $where_sql = '';
      if (!empty($where_conditions)) {
          $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
      }

      $stmt = $conn->prepare("SELECT COUNT(*) as total FROM sales $where_sql");
      if (!empty($params)) {
          $stmt->bind_param($types, ...$params);
      }

      $stmt->execute();
      $result = $stmt->get_result();
      $row = $result->fetch_assoc();
      $stmt->close();
      return $row['total'];
  }

  /**
   * Get total sales amount
   */
  function getTotalSalesAmount($date_from = null, $date_to = null) {
      $conn = getDBConnection();

      $where_conditions = [];
      $params = [];
      $types = '';

      if ($date_from !== null) {
          $where_conditions[] = "DATE(sale_date) >= ?";
          $params[] = $date_from;
          $types .= 's';
      }

      if ($date_to !== null) {
          $where_conditions[] = "DATE(sale_date) <= ?";
          $params[] = $date_to;
          $types .= 's';
      }

      $where_sql = '';
      if (!empty($where_conditions)) {
          $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
      }

      $stmt = $conn->prepare("SELECT IFNULL(SUM(total_amount), 0) as total FROM sales $where_sql");
      if (!empty($params)) {
          $stmt->bind_param($types, ...$params);
      }

      $stmt->execute();
      $result = $stmt->get_result();
      $row = $result->fetch_assoc();
      $stmt->close();
      return $row['total'];
  }

  /**
   * Get top selling items
   */
  function getTopSellingItems($limit = 10, $date_from = null, $date_to = null) {
      $conn = getDBConnection();

      $where_conditions = [];
      $params = [];
      $types = '';

      if ($date_from !== null) {
          $where_conditions[] = "DATE(s.sale_date) >= ?";
          $params[] = $date_from;
          $types .= 's';
      }

      if ($date_to !== null) {
          $where_conditions[] = "DATE(s.sale_date) <= ?";
          $params[] = $date_to;
          $types .= 's';
      }

      $where_sql = '';
      if (!empty($where_conditions)) {
          $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
      }

      $sql = "SELECT m.name, SUM(si.quantity) as total_sold, SUM(si.subtotal) as total_revenue
              FROM sales_items si
              JOIN menu_items m ON si.menu_item_id = m.id
              JOIN sales s ON si.sale_id = s.id
              $where_sql
              GROUP BY si.menu_item_id, m.name
              ORDER BY total_sold DESC
              LIMIT ?";

      $params[] = $limit;
      $types .= 'i';

      $stmt = $conn->prepare($sql);
      $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $result = $stmt->get_result();
      $items = $result->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
      return $items;
  }

  /**
   * Update dashboard stats to include POS data
   */
  function getDashboardStatsWithPOS() {
      $stats = getDashboardStats();

      $conn = getDBConnection();

      // Add POS stats
      $stats['total_sales'] = $conn->query("SELECT COUNT(*) AS count FROM sales")->fetch_assoc()['count'];
      $stats['today_sales'] = $conn->query("SELECT COUNT(*) AS count FROM sales WHERE DATE(sale_date) = CURDATE()")->fetch_assoc()['count'];
      $stats['today_revenue'] = $conn->query("SELECT IFNULL(SUM(total_amount), 0) AS total FROM sales WHERE DATE(sale_date) = CURDATE()")->fetch_assoc()['total'];
      $stats['total_revenue'] = $conn->query("SELECT IFNULL(SUM(total_amount), 0) AS total FROM sales")->fetch_assoc()['total'];

      return $stats;
  }

  // Add to functions.php

  /**
   * Reset user password to default
   */
   function resetUserPassword($user_id, $reset_by_id) {
       $conn = getDBConnection();

       $default_password = DEFAULT_RESET_PASSWORD;
       $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

       $stmt = $conn->prepare("
           UPDATE users
           SET password = ?,
               password_reset = 1,
               last_password_reset = NOW(),
               reset_by = ?
           WHERE id = ?
       ");
       $stmt->bind_param("sii", $hashed_password, $reset_by_id, $user_id);
       $success = $stmt->execute();
       $stmt->close();

       if ($success) {
           logPasswordReset($user_id, $reset_by_id, $default_password);
       }

       return $success;
   }


  /**
   * Log password reset activity
   */
  function logPasswordReset($user_id, $reset_by_id, $default_password) {
      $conn = getDBConnection();
      $ip_address = getClientIP();

      // Store encrypted version for logging
      $encrypted_password = base64_encode($default_password);

      $stmt = $conn->prepare("
          INSERT INTO password_reset_log (user_id, reset_by, default_password, reset_date, ip_address)
          VALUES (?, ?, ?, NOW(), ?)
      ");
      $stmt->bind_param("iiss", $user_id, $reset_by_id, $encrypted_password, $ip_address);
      $stmt->execute();
      $stmt->close();

      // Log to activity log
      $user = getUserById($user_id);
      $reset_by = getUserById($reset_by_id);
      logActivity($reset_by_id, 'Password Reset', "Reset password for user: {$user['username']} (ID: $user_id)");
  }

  /**
   * Get password reset history
   */
  function getPasswordResetHistory($limit = 50, $offset = 0) {
      $conn = getDBConnection();
      $stmt = $conn->prepare("
          SELECT prl.*,
                 u.username as user_username,
                 r.username as reset_by_username
          FROM password_reset_log prl
          JOIN users u ON prl.user_id = u.id
          JOIN users r ON prl.reset_by = r.id
          ORDER BY prl.reset_date DESC
          LIMIT ? OFFSET ?
      ");
      $stmt->bind_param("ii", $limit, $offset);
      $stmt->execute();
      $result = $stmt->get_result();
      $history = $result->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
      return $history;
  }

  /**
   * Toggle user account status
   */
  function toggleUserStatus($user_id, $status) {
      $conn = getDBConnection();

      // Validate status
      $allowed_status = ['active', 'inactive'];
      if (!in_array($status, $allowed_status)) {
          return false;
      }

      $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
      $stmt->bind_param("si", $status, $user_id);
      $success = $stmt->execute();
      $stmt->close();

      return $success;
  }

  /**
   * Check if password needs reset
   */
  function needsPasswordReset($user_id) {
      $conn = getDBConnection();
      $stmt = $conn->prepare("SELECT password_reset FROM users WHERE id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $user = $result->fetch_assoc();
      $stmt->close();

      return $user && $user['password_reset'] == 1;
  }

  /**
   * Clear password reset flag
   */
  function clearPasswordResetFlag($user_id) {
      $conn = getDBConnection();
      $stmt = $conn->prepare("UPDATE users SET password_reset = 0 WHERE id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();
  }
?>
