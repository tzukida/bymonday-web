<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireStaff();

  $conn = getDBConnection();
  $user_id = $_SESSION['user_id'];

  $item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : '';
  $type = isset($_GET['type']) && in_array($_GET['type'], ['stock-in', 'stock-out']) ? $_GET['type'] : '';
  $date_from = sanitizeInput($_GET['date_from'] ?? '');
  $date_to = sanitizeInput($_GET['date_to'] ?? '');

  $page = max(1, (int)($_GET['page'] ?? 1));
  $per_page = 25;
  $offset = ($page - 1) * $per_page;

  $where = "t.user_id = ?";
  $params = [$user_id];
  $types = 'i';

  if ($item_id) {
    $where .= " AND t.item_id = ?";
    $params[] = $item_id;
    $types .= 'i';
  }

  if ($type) {
    $where .= " AND t.type = ?";
    $params[] = $type;
    $types .= 's';
  }

  if ($date_from) {
    $where .= " AND DATE(t.timestamp) >= ?";
    $params[] = $date_from;
    $types .= 's';
  }

  if ($date_to) {
    $where .= " AND DATE(t.timestamp) <= ?";
    $params[] = $date_to;
    $types .= 's';
  }

  // Get statistics
  $stats_sql = "SELECT
                  COUNT(*) as total_transactions,
                  SUM(CASE WHEN type = 'stock-in' THEN 1 ELSE 0 END) as stock_in_count,
                  SUM(CASE WHEN type = 'stock-out' THEN 1 ELSE 0 END) as stock_out_count,
                  SUM(CASE WHEN type = 'stock-in' THEN quantity ELSE 0 END) as total_stock_in,
                  SUM(CASE WHEN type = 'stock-out' THEN quantity ELSE 0 END) as total_stock_out
                FROM transactions t
                WHERE $where";
  $stats_stmt = $conn->prepare($stats_sql);
  $stats_stmt->bind_param($types, ...$params);
  $stats_stmt->execute();
  $stats = $stats_stmt->get_result()->fetch_assoc();
  $stats_stmt->close();

  // Get sales statistics
  $sales_where = "user_id = ?";
  $sales_params = [$user_id];
  $sales_types = 'i';

  if ($date_from) {
    $sales_where .= " AND DATE(sale_date) >= ?";
    $sales_params[] = $date_from;
    $sales_types .= 's';
  }

  if ($date_to) {
    $sales_where .= " AND DATE(sale_date) <= ?";
    $sales_params[] = $date_to;
    $sales_types .= 's';
  }

  $sales_stats_sql = "SELECT
                        COUNT(*) as total_sales,
                        IFNULL(SUM(total_amount), 0) as total_revenue
                      FROM sales
                      WHERE $sales_where";
  $sales_stats_stmt = $conn->prepare($sales_stats_sql);
  $sales_stats_stmt->bind_param($sales_types, ...$sales_params);
  $sales_stats_stmt->execute();
  $sales_stats = $sales_stats_stmt->get_result()->fetch_assoc();
  $sales_stats_stmt->close();

  // Get total count for pagination
  $count_stmt = $conn->prepare("SELECT COUNT(*) FROM transactions t WHERE $where");
  $count_stmt->bind_param($types, ...$params);
  $count_stmt->execute();
  $count_stmt->bind_result($total_items);
  $count_stmt->fetch();
  $count_stmt->close();
  $total_pages = max(1, ceil($total_items / $per_page));

  // Get transactions
  $sql = "SELECT t.id, t.timestamp, i.item_name, i.unit, t.quantity, t.type, t.remarks
        FROM transactions t
        JOIN inventory i ON t.item_id = i.id
        WHERE $where
        ORDER BY t.timestamp DESC
        LIMIT ? OFFSET ?";
  $stmt = $conn->prepare($sql);
  $types_with_limit = $types . 'ii';
  $stmt->bind_param($types_with_limit, ...array_merge($params, [$per_page, $offset]));
  $stmt->execute();
  $transactions = $stmt->get_result();
  $stmt->close();

  // Get items for filter dropdown
  $items_stmt = $conn->prepare("SELECT id, item_name FROM inventory ORDER BY item_name ASC");
  $items_stmt->execute();
  $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $items_stmt->close();

  $page_title = 'My Sales & Transactions';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #382417;">My Activities
          </h3>
          <p class="text-muted mb-0">View your sales and inventory transaction history</p>
        </div>
        <div>
          <span class="badge bg-info fs-6">
            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Statistics Cards -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #382417 0%, #5a0f0e 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-cash-register fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($sales_stats['total_sales']); ?></h3>
          <p class="mb-0 opacity-75">Total Sales</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-peso-sign fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1">₱<?php echo number_format($sales_stats['total_revenue'], 2); ?></h3>
          <p class="mb-0 opacity-75">Total Revenue</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-arrow-up fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['stock_in_count']); ?></h3>
          <p class="mb-0 opacity-75">Stock In</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-arrow-down fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['stock_out_count']); ?></h3>
          <p class="mb-0 opacity-75">Stock Out</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Sales History Section -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="fas fa-shopping-cart me-2 icon-red"></i>My Sales History
          </h5>
          <a href="<?php echo getBaseURL(); ?>/staff_pos.php" class="btn btn-sm btn-danger">
            <i class="fas fa-plus me-1"></i>New Sale
          </a>
        </div>
        <div class="card-body p-0">
          <?php
          // Get sales for this user with date filters
          $sales_list_where = "user_id = ?";
          $sales_list_params = [$user_id];
          $sales_list_types = 'i';

          if ($date_from) {
            $sales_list_where .= " AND DATE(sale_date) >= ?";
            $sales_list_params[] = $date_from;
            $sales_list_types .= 's';
          }

          if ($date_to) {
            $sales_list_where .= " AND DATE(sale_date) <= ?";
            $sales_list_params[] = $date_to;
            $sales_list_types .= 's';
          }

          $sales_sql = "SELECT * FROM sales WHERE $sales_list_where ORDER BY sale_date DESC LIMIT 10";
          $sales_stmt = $conn->prepare($sales_sql);
          $sales_stmt->bind_param($sales_list_types, ...$sales_list_params);
          $sales_stmt->execute();
          $recent_sales = $sales_stmt->get_result();
          $sales_stmt->close();
          ?>

          <?php if ($recent_sales->num_rows === 0): ?>
            <div class="text-center py-5">
              <i class="fas fa-cash-register fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No sales yet</h5>
              <p class="text-muted mb-3">Start making sales to see your transaction history</p>
              <a href="<?php echo getBaseURL(); ?>/staff_pos.php" class="btn btn-danger">
                <i class="fas fa-cash-register me-1"></i>Go to POS
              </a>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="border-0">Sale ID</th>
                    <th class="border-0">Customer</th>
                    <th class="border-0 text-end">Amount</th>
                    <th class="border-0 text-center">Payment</th>
                    <th class="border-0">Date & Time</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($sale = $recent_sales->fetch_assoc()):
                    $payment_icons = [
                      'cash' => 'fa-money-bill-wave',
                      'gcash' => 'fa-mobile-alt',
                      'card' => 'fa-credit-card'
                    ];
                    $payment_colors = [
                      'cash' => 'success',
                      'gcash' => 'primary',
                      'card' => 'warning'
                    ];
                    $method = strtolower($sale['payment_method']);
                    $icon = $payment_icons[$method] ?? 'fa-money-bill-wave';
                    $color = $payment_colors[$method] ?? 'secondary';
                  ?>
                  <tr>
                    <td>
                      <strong class="text-gray">#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                    </td>
                    <td>
                      <i class="fas fa-user me-1 text-muted" style="font-size: 0.75rem;"></i>
                      <?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in'); ?>
                    </td>
                    <td class="text-end">
                      <span class="fw-bold text-danger">₱<?php echo number_format($sale['total_amount'], 2); ?></span>
                    </td>
                    <td class="text-center">
                      <span class="badge bg-<?php echo $color; ?>">
                        <i class="fas <?php echo $icon; ?> me-1"></i>
                        <?php echo ucfirst($sale['payment_method']); ?>
                      </span>
                    </td>
                    <td class="text-muted small">
                      <div>
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('M j, Y', strtotime($sale['sale_date'])); ?>
                      </div>
                      <div>
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('h:i A', strtotime($sale['sale_date'])); ?>
                      </div>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Compact Filter Card -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <form method="GET">
            <!-- Main Filters Row -->
            <div class="row g-2 mb-2">
              <div class="col-lg-3 col-md-6">
                <label class="form-label-sm mb-1 text-muted">
                  <i class="fas fa-box me-1"></i>Item (for inventory)
                </label>
                <select name="item_id" class="form-select form-select-sm">
                  <option value="">All Items</option>
                  <?php foreach ($items as $item): ?>
                    <option value="<?php echo $item['id']; ?>"
                            <?php echo $item_id == $item['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($item['item_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-lg-3 col-md-6">
                <label class="form-label-sm mb-1 text-muted">
                  <i class="fas fa-filter me-1"></i>Type (for inventory)
                </label>
                <select name="type" class="form-select form-select-sm">
                  <option value="">All Types</option>
                  <option value="stock-in" <?php echo $type === 'stock-in' ? 'selected' : ''; ?>>Stock In</option>
                  <option value="stock-out" <?php echo $type === 'stock-out' ? 'selected' : ''; ?>>Stock Out</option>
                </select>
              </div>
              <div class="col-lg-4 col-md-8">
                <label class="form-label-sm mb-1 text-muted">
                  <i class="fas fa-calendar-alt me-1"></i>Date Range (for all)
                </label>
                <div class="input-group input-group-sm">
                  <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                  <span class="input-group-text">to</span>
                  <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
              </div>
              <div class="col-lg-2 col-md-4">
                <label class="form-label-sm mb-1 text-muted">&nbsp;</label>
                <div class="d-flex gap-1">
                  <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="fas fa-filter me-1"></i>Apply
                  </button>
                  <a href="my_transactions.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-times"></i>
                  </a>
                </div>
              </div>
            </div>
          </form>

          <!-- Active Filters Display -->
          <?php if ($item_id || $type || $date_from || $date_to): ?>
          <div class="mt-2 pt-2 border-top">
            <small class="text-muted d-block mb-1">
              <i class="fas fa-filter me-1"></i>Active Filters:
            </small>
            <div class="d-flex flex-wrap gap-1">
              <?php if ($item_id):
                $item_name = array_filter($items, fn($i) => $i['id'] == $item_id)[0]['item_name'] ?? '';
              ?>
                <span class="badge bg-secondary">Item: <?php echo htmlspecialchars($item_name); ?></span>
              <?php endif; ?>
              <?php if ($type): ?>
                <span class="badge bg-secondary">Type: <?php echo ucfirst($type); ?></span>
              <?php endif; ?>
              <?php if ($date_from): ?>
                <span class="badge bg-secondary">From: <?php echo date('M j, Y', strtotime($date_from)); ?></span>
              <?php endif; ?>
              <?php if ($date_to): ?>
                <span class="badge bg-secondary">To: <?php echo date('M j, Y', strtotime($date_to)); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Transactions Table -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="fas fa-clipboard-list me-2 icon-red"></i>Inventory Transaction Records
          </h5>
          <span class="badge bg-red">
            <?php echo number_format($total_items); ?> Records
          </span>
        </div>
        <div class="card-body p-0">
          <?php if ($transactions->num_rows === 0): ?>
            <div class="text-center py-5">
              <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No transactions found</h5>
              <?php if ($item_id || $type || $date_from || $date_to): ?>
                <p class="text-muted mb-3">Try adjusting your filters</p>
                <a href="my_transactions.php" class="btn btn-outline-danger">Clear Filters</a>
              <?php else: ?>
                <p class="text-muted mb-3">You haven't made any inventory transactions yet</p>
                <div class="d-flex gap-2 justify-content-center">
                  <a href="<?php echo getBaseURL(); ?>/staff_inventory.php" class="btn btn-danger">
                    <i class="fas fa-boxes me-1"></i>View Inventory
                  </a>
                </div>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="border-0 text-center" style="width: 60px;">#</th>
                    <th class="border-0">Item</th>
                    <th class="border-0 text-center">Quantity</th>
                    <th class="border-0 text-center">Type</th>
                    <th class="border-0">Remarks</th>
                    <th class="border-0">Date & Time</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $row_number = $offset + 1;
                  while ($row = $transactions->fetch_assoc()):
                  ?>
                  <tr>
                    <td class="text-center text-muted small">
                      <strong><?php echo $row_number++; ?></strong>
                    </td>
                    <td>
                      <strong class="text-dark"><?php echo htmlspecialchars($row['item_name']); ?></strong>
                      <small class="text-muted d-block">Unit: <?php echo htmlspecialchars($row['unit']); ?></small>
                    </td>
                    <td class="text-center">
                      <strong class="fs-5 <?php echo $row['type'] === 'stock-in' ? 'text-success' : 'text-warning'; ?>">
                        <?php echo $row['type'] === 'stock-in' ? '+' : '-'; ?>
                        <?php echo number_format($row['quantity']); ?>
                      </strong>
                    </td>
                    <td class="text-center">
                      <?php if ($row['type'] === 'stock-in'): ?>
                        <span class="badge bg-success">
                          <i class="fas fa-arrow-up me-1"></i>Stock In
                        </span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark">
                          <i class="fas fa-arrow-down me-1"></i>Stock Out
                        </span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php
                        $remarks = $row['remarks'];
                        if (strlen($remarks) > 50):
                      ?>
                        <span title="<?php echo htmlspecialchars($remarks); ?>" data-bs-toggle="tooltip">
                          <?php echo htmlspecialchars(substr($remarks, 0, 50)) . '...'; ?>
                        </span>
                      <?php else: ?>
                        <?php echo htmlspecialchars($remarks); ?>
                      <?php endif; ?>
                    </td>
                    <td class="text-muted small">
                      <div>
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('M j, Y', strtotime($row['timestamp'])); ?>
                      </div>
                      <div>
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('h:i A', strtotime($row['timestamp'])); ?>
                      </div>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Enhanced Pagination -->
        <?php if ($total_pages > 1 && $transactions->num_rows > 0): ?>
        <div class="card-footer bg-light">
          <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
              <p class="text-muted mb-0 small">
                Showing <?php echo number_format($offset + 1); ?> to
                <?php echo number_format(min($offset + $per_page, $total_items)); ?> of
                <?php echo number_format($total_items); ?> records
              </p>
            </div>
            <div class="col-md-6">
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm justify-content-md-end justify-content-center mb-0">
                  <!-- First Page -->
                  <?php if ($page > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=1&item_id=<?php echo $item_id; ?>&type=<?php echo urlencode($type); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" title="First Page">
                      <i class="fas fa-angle-double-left"></i>
                    </a>
                  </li>
                  <?php endif; ?>

                  <!-- Previous Page -->
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>&item_id=<?php echo $item_id; ?>&type=<?php echo urlencode($type); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" <?php echo $page <= 1 ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-left"></i>
                    </a>
                  </li>

                  <!-- Page Numbers -->
                  <?php
                  $start_page = max(1, $page - 2);
                  $end_page = min($total_pages, $page + 2);

                  if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&item_id=' . $item_id . '&type=' . urlencode($type) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">1</a></li>';
                    if ($start_page > 2) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                  }

                  for ($i = $start_page; $i <= $end_page; $i++):
                  ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                      <a class="page-link" href="?page=<?php echo $i; ?>&item_id=<?php echo $item_id; ?>&type=<?php echo urlencode($type); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                        <?php echo $i; ?>
                      </a>
                    </li>
                  <?php
                  endfor;

                  if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&item_id=' . $item_id . '&type=' . urlencode($type) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">' . $total_pages . '</a></li>';
                  }
                  ?>

                  <!-- Next Page -->
                  <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo min($total_pages, $page + 1); ?>&item_id=<?php echo $item_id; ?>&type=<?php echo urlencode($type); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" <?php echo $page >= $total_pages ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-right"></i>
                    </a>
                  </li>

                  <!-- Last Page -->
                  <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&item_id=<?php echo $item_id; ?>&type=<?php echo urlencode($type); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" title="Last Page">
                      <i class="fas fa-angle-double-right"></i>
                    </a>
                  </li>
                  <?php endif; ?>
                </ul>
              </nav>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Info Alert -->
  <div class="row mt-4">
    <div class="col-12">
      <div class="alert alert-info border-0">
        <div class="d-flex align-items-center">
          <i class="fas fa-info-circle fa-2x me-3"></i>
          <div>
            <strong>My Activity Overview</strong><br>
            <small>This page shows all your sales and inventory activities. The statistics at the top show your sales performance, while the tables below show your sales history and inventory stock movements.</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
body {
  background-color: #F3EDE8;
}

.icon-red {
  color: #382417;
}

.bg-red {
  background-color: #382417;
  color: #fff;
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
  box-shadow: 0 0.5rem 1rem rgba(117, 19, 18, 0.15);
}

.table thead th {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.5px;
  color: #6c757d;
}

.table tbody tr {
  transition: background-color 0.2s;
}

.table tbody tr:hover {
  background-color: #f8f9fa;
}

.badge {
  font-weight: 500;
  padding: 0.35rem 0.65rem;
}

.form-label-sm {
  font-size: 0.875rem;
  font-weight: 500;
  color: #495057;
}

.btn-primary {
  background-color: #382417;
  border-color: #382417;
}

.btn-primary:hover {
  background-color: #5a0f0e;
  border-color: #5a0f0e;
}

.btn-outline-danger {
  color: #382417;
  border-color: #382417;
}

.btn-outline-danger:hover {
  background-color: #382417;
  border-color: #382417;
  color: #fff;
}

.pagination {
  --bs-pagination-active-bg: #382417;
  --bs-pagination-active-border-color: #382417;
  --bs-pagination-hover-color: #382417;
}

.pagination .page-link {
  color: #6c757d;
  border-radius: 0.25rem;
  margin: 0 2px;
  transition: all 0.2s;
}

.pagination .page-link:hover {
  background-color: #f8f9fa;
  border-color: #dee2e6;
  color: #382417;
}

.pagination .page-item.active .page-link {
  background-color: #382417;
  border-color: #382417;
  color: white;
  font-weight: 600;
}

.pagination .page-item.disabled .page-link {
  color: #adb5bd;
  background-color: transparent;
  border-color: #dee2e6;
}

.alert {
  border-radius: 0.5rem;
}

@media (max-width: 768px) {
  .table {
    font-size: 0.875rem;
  }

  .btn-sm {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
  }

  .fs-5 {
    font-size: 1rem !important;
  }

  .card-body h3 {
    font-size: 1.5rem;
  }
}

@media (max-width: 576px) {
  .d-flex.gap-1 {
    flex-direction: column;
    gap: 0.25rem !important;
  }

  .d-flex.gap-1 .btn {
    width: 100%;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Initialize tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
