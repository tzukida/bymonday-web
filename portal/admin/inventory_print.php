<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireAdmin();

  $conn = getDBConnection();

  $search = sanitizeInput($_GET['search'] ?? '');
  $filter = sanitizeInput($_GET['filter'] ?? '');

  $where_conditions = [];
  $params = [];
  $types = '';

  if (!empty($search)) {
    $where_conditions[] = "(item_name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
  }

  if ($filter === 'low_stock') {
    $where_conditions[] = "quantity < 10";
  }

  $where_sql = '';
  if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
  }

  $sql = "SELECT item_name, description, unit, quantity, created_at, updated_at FROM inventory";
  if ($where_sql) $sql .= " $where_sql";
  $sql .= " ORDER BY item_name ASC";

  $stmt = $conn->prepare($sql);
  if (!empty($params)) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $result = $stmt->get_result();

  $items = [];
  while ($row = $result->fetch_assoc()) {
    $items[] = $row;
  }
  $total_items = count($items);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Printable Inventory</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
        th { background-color: #f2f2f2; }
        h2 { text-align: center; }
    </style>
</head>
<body>
    <h2>Inventory Report</h2>
    <p>Total Items: <?php echo $total_items; ?></p>

    <table>
        <thead>
            <tr>
                <th>Item Name</th>
                <th>Description</th>
                <th>Unit</th>
                <th>Quantity</th>
                <th>Status</th>
                <th>Updated At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)): ?>
                <?php foreach ($items as $item): ?>
                    <?php
                        $status = $item['quantity'] < 10 ? 'Low Stock' : ($item['quantity'] < 50 ? 'Medium Stock' : 'Good Stock');
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td><?php echo $status; ?></td>
                        <td><?php echo htmlspecialchars($item['updated_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center;">No items found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
