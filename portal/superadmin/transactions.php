<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireSuperAdmin();

  $conn = getDBConnection();

  $search = sanitizeInput($_GET['search'] ?? '');
  $item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : '';
  $type = isset($_GET['type']) && in_array($_GET['type'], ['stock-in', 'stock-out']) ? $_GET['type'] : '';
  $date_from = sanitizeInput($_GET['date_from'] ?? '');
  $date_to = sanitizeInput($_GET['date_to'] ?? '');
  $user_filter = isset($_GET['user']) ? (int)$_GET['user'] : '';

  $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $per_page = 25;
  $offset = ($page - 1) * $per_page;

  $where_conditions = ["1=1"];
  $params = [];
  $types = '';

  if (!empty($search)) {
    $where_conditions[] = "(i.item_name LIKE ? OR t.remarks LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
  }

  if ($item_id) {
    $where_conditions[] = "t.item_id = ?";
    $params[] = $item_id;
    $types .= 'i';
  }

  if ($type) {
    $where_conditions[] = "t.type = ?";
    $params[] = $type;
    $types .= 's';
  }

  if ($date_from) {
    $where_conditions[] = "DATE(t.timestamp) >= ?";
    $params[] = $date_from;
    $types .= 's';
  }

  if ($date_to) {
    $where_conditions[] = "DATE(t.timestamp) <= ?";
    $params[] = $date_to;
    $types .= 's';
  }

  if ($user_filter) {
    $where_conditions[] = "t.user_id = ?";
    $params[] = $user_filter;
    $types .= 'i';
  }

  $where = implode(' AND ', $where_conditions);

  $count_sql = "SELECT COUNT(*) as total FROM transactions t
                JOIN inventory i ON t.item_id = i.id
                WHERE $where";
  $count_stmt = $conn->prepare($count_sql);
  if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
  }
  $count_stmt->execute();
  $total_items = $count_stmt->get_result()->fetch_assoc()['total'];
  $count_stmt->close();

  $total_pages = max(1, ceil($total_items / $per_page));

  $sql = "SELECT t.id, t.timestamp, i.item_name, i.unit, t.quantity, t.type, t.remarks, u.username
          FROM transactions t
          JOIN inventory i ON t.item_id = i.id
          JOIN users u ON t.user_id = u.id
          WHERE $where
          ORDER BY t.timestamp DESC
          LIMIT ? OFFSET ?";

  $stmt = $conn->prepare($sql);
  $types_with_pagination = $types . 'ii';
  $params_with_pagination = array_merge($params, [$per_page, $offset]);
  if (!empty($params_with_pagination)) {
    $stmt->bind_param($types_with_pagination, ...$params_with_pagination);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  $transactions = $result->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  $items_stmt = $conn->prepare("SELECT id, item_name FROM inventory ORDER BY item_name ASC");
  $items_stmt->execute();
  $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $items_stmt->close();

  $users_stmt = $conn->prepare("SELECT id, username FROM users ORDER BY username ASC");
  $users_stmt->execute();
  $users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $users_stmt->close();

  $stats_sql = "SELECT
                  COUNT(*) as total_transactions,
                  SUM(CASE WHEN type = 'stock-in' THEN 1 ELSE 0 END) as stock_in_count,
                  SUM(CASE WHEN type = 'stock-out' THEN 1 ELSE 0 END) as stock_out_count,
                  SUM(CASE WHEN type = 'stock-in' THEN quantity ELSE 0 END) as total_stock_in,
                  SUM(CASE WHEN type = 'stock-out' THEN quantity ELSE 0 END) as total_stock_out
                FROM transactions t
                WHERE $where";
  $stats_stmt = $conn->prepare($stats_sql);
  if (!empty($params)) {
    $stats_stmt->bind_param($types, ...$params);
  }
  $stats_stmt->execute();
  $stats = $stats_stmt->get_result()->fetch_assoc();
  $stats_stmt->close();

  $page_title = 'Stock History';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #4a301f;">Stock History</h3>
          <p class="text-muted mb-0">Track all inventory stock movements</p>
        </div>
        <div>
          <a href="<?php echo getBaseURL(); ?>/inventory.php" class="btn btn-outline-brown">
            <i class="fas fa-arrow-left me-2"></i>Back to Inventory
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats Cards Row -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #382417 0%, #2a1b11 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-list fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['total_transactions']); ?></h3>
          <p class="mb-0 opacity-75">Total Records</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #4d3420 0%, #382417 100%);">
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
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #654529 0%, #4d3420 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-arrow-down fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['stock_out_count']); ?></h3>
          <p class="mb-0 opacity-75">Stock Out</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #7d5633 0%, #654529 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-balance-scale fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['total_stock_in'] - $stats['total_stock_out']); ?></h3>
          <p class="mb-0 opacity-75">Net Movement</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Compact Search and Filter Card -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <form method="GET">
            <!-- Main Filters Row -->
            <div class="row g-2 mb-2">
              <div class="col-lg-4 col-md-6">
                <label class="form-label-sm mb-1 text-muted">
                  <i class="fas fa-search me-1"></i>Search
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       name="search"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search item or remarks...">
              </div>
              <div class="col-lg-2 col-md-6">
                <label class="form-label-sm mb-1 text-muted">
                  <i class="fas fa-box me-1"></i>Item
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
              <div class="col-lg-2 col-md-4">
                <label class="form-label-sm mb-1 text-muted">
                  <i class="fas fa-filter me-1"></i>Type
                </label>
                <select name="type" class="form-select form-select-sm">
                  <option value="">All Types</option>
                  <option value="stock-in" <?php echo $type === 'stock-in' ? 'selected' : ''; ?>>Stock In</option>
                  <option value="stock-out" <?php echo $type === 'stock-out' ? 'selected' : ''; ?>>Stock Out</option>
                </select>
              </div>
              <div class="col-lg-2 col-md-4">
                <label class="form-label-sm mb-1 text-muted">
                  <i class="fas fa-user me-1"></i>User
                </label>
                <select name="user" class="form-select form-select-sm">
                  <option value="">All Users</option>
                  <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>"
                            <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-lg-2 col-md-4">
                <label class="form-label-sm mb-1 text-muted">&nbsp;</label>
                <div class="d-flex gap-1">
                  <button type="submit" class="btn btn-brown btn-sm flex-grow-1">
                    <i class="fas fa-filter me-1"></i>Apply
                  </button>
                  <a href="transactions.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-times"></i>
                  </a>
                  <button type="button" class="btn btn-outline-brown btn-sm" data-bs-toggle="collapse" data-bs-target="#dateFilter">
                    <i class="fas fa-calendar-alt"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Collapsible Date Range Filter -->
            <div class="collapse <?php echo ($date_from || $date_to) ? 'show' : ''; ?>" id="dateFilter">
              <div class="row g-2 pt-2 border-top">
                <div class="col-md-3">
                  <label class="form-label form-label-sm mb-1">
                    <i class="fas fa-calendar-alt me-1"></i>From Date
                  </label>
                  <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm mb-1">
                    <i class="fas fa-calendar-alt me-1"></i>To Date
                  </label>
                  <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-2">
                  <label class="form-label form-label-sm mb-1">&nbsp;</label>
                  <button type="submit" class="btn btn-brown btn-sm w-100">
                    <i class="fas fa-calendar-check me-1"></i>Apply Dates
                  </button>
                </div>
              </div>
            </div>
          </form>

          <!-- Active Filters Display -->
          <?php if (!empty($search) || $item_id || $type || $date_from || $date_to || $user_filter): ?>
          <div class="mt-2 pt-2 border-top">
            <small class="text-muted d-block mb-1"><i class="fas fa-filter me-1"></i>Active Filters:</small>
            <div class="d-flex flex-wrap gap-1">
              <?php if (!empty($search)): ?>
                <span class="badge bg-secondary">Search: <?php echo htmlspecialchars($search); ?></span>
              <?php endif; ?>
              <?php if ($item_id):
                $item_name = array_filter($items, fn($i) => $i['id'] == $item_id)[0]['item_name'] ?? '';
              ?>
                <span class="badge bg-secondary">Item: <?php echo htmlspecialchars($item_name); ?></span>
              <?php endif; ?>
              <?php if ($type): ?>
                <span class="badge bg-secondary">Type: <?php echo ucfirst($type); ?></span>
              <?php endif; ?>
              <?php if ($user_filter):
                $user_name = array_filter($users, fn($u) => $u['id'] == $user_filter)[0]['username'] ?? '';
              ?>
                <span class="badge bg-secondary">User: <?php echo htmlspecialchars($user_name); ?></span>
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
            <i class="fas fa-history me-2 icon-brown"></i>Stock Movement Records
          </h5>
          <span class="badge bg-brown">
            <?php echo number_format($total_items); ?> Records
          </span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($transactions)): ?>
            <div class="text-center py-5">
              <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No stock movements found</h5>
              <?php if (!empty($search) || $item_id || $type || $date_from || $date_to || $user_filter): ?>
                <p class="text-muted mb-3">Try adjusting your filters</p>
                <a href="transactions.php" class="btn btn-outline-brown">Clear Filters</a>
              <?php else: ?>
                <p class="text-muted mb-3">Start recording stock movements</p>
                <div class="d-flex gap-2 justify-content-center">
                  <a href="<?php echo getBaseURL(); ?>/stock_in.php" class="btn btn-success">
                    <i class="fas fa-arrow-up me-1"></i>Add Stock In
                  </a>
                  <a href="<?php echo getBaseURL(); ?>/stock_out.php" class="btn btn-warning">
                    <i class="fas fa-arrow-down me-1"></i>Add Stock Out
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
                    <th class="border-0 text-center">Processed By</th>
                    <th class="border-0">Date & Time</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $row_number = $offset + 1;
                  foreach ($transactions as $transaction):
                  ?>
                  <tr>
                    <td class="text-center text-muted small">
                      <strong><?php echo $row_number++; ?></strong>
                    </td>
                    <td>
                      <strong class="text-dark"><?php echo htmlspecialchars($transaction['item_name']); ?></strong>
                      <small class="text-muted d-block">Unit: <?php echo htmlspecialchars($transaction['unit']); ?></small>
                    </td>
                    <td class="text-center">
                      <strong class="fs-5 <?php echo $transaction['type'] === 'stock-in' ? 'text-success' : 'text-warning'; ?>">
                        <?php echo $transaction['type'] === 'stock-in' ? '+' : '-'; ?>
                        <?php echo number_format($transaction['quantity']); ?>
                      </strong>
                    </td>
                    <td class="text-center">
                      <?php if ($transaction['type'] === 'stock-in'): ?>
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
                        $remarks = $transaction['remarks'];
                        if (strlen($remarks) > 50):
                      ?>
                        <span title="<?php echo htmlspecialchars($remarks); ?>" data-bs-toggle="tooltip">
                          <?php echo htmlspecialchars(substr($remarks, 0, 50)) . '...'; ?>
                        </span>
                      <?php else: ?>
                        <?php echo htmlspecialchars($remarks); ?>
                      <?php endif; ?>
                    </td>
                    <td class="text-center">
                      <span class="badge bg-brown">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($transaction['username']); ?>
                      </span>
                    </td>
                    <td class="text-muted small">
                      <div>
                        <i class="fas fa-calendar me-1" style="color: #3b2008;"></i>
                        <?php echo date('M j, Y', strtotime($transaction['timestamp'])); ?>
                      </div>
                      <div>
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('h:i A', strtotime($transaction['timestamp'])); ?>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Enhanced Pagination -->
        <?php if ($total_pages > 1 && !empty($transactions)): ?>
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
                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&item_id=<?php echo $item_id; ?>&type=<?php echo urlencode($type); ?>&user=<?php echo $user_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" title="First Page">
                      <i class="fas fa-angle-double-left"></i>
                    </a>
                  </li>
                  <?php endif; ?>

                  <!-- Previous Page -->
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search); ?>&item_id=<?php echo $item_id; ?>&type=<?php echo urlencode($type); ?>&user=<?php echo $user_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" <?php echo $page <= 1 ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-left"></i>
                    </a>
                  </li>

                  <!-- Page Numbers -->
                  <?php
                  $start_page = max(1, $page - 2);
                  $end_page = min($total_pages, $page + 2);

                  if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&item_id=' . $item_id . '&type=' . urlencode($type) . '&user=' . $user_filter . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">1</a></li>';
                    if ($start_page > 2) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                  }

                  for ($i = $start_page; $i <= $end_page; $i++):
                  ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                      <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&item_id=<?php echo $item_id; ?>&type=<?php echo urlencode($type); ?>&user=<?php echo $user_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                        <?php echo $i; ?>
                      </a>
                    </li>
                  <?php
                  endfor;

                  if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&item_id=' . $item_id . '&type=' . urlencode($type) . '&user=' . $user_filter . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">' . $total_pages . '</a></li>';
                  }
                  ?>

                  <!-- Next Page -->
                  <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search); ?>&item_id=<?php echo $item_id; ?>&type=<?php echo urlencode($type); ?>&user=<?php echo $user_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" <?php echo $page >= $total_pages ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-right"></i>
                    </a>
                  </li>

                  <!-- Last Page -->
                  <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&item_id=<?php echo $item_id; ?>&type=<?php echo urlencode($type); ?>&user=<?php echo $user_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" title="Last Page">
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
</div>

<style>

body {
  background: linear-gradient(135deg, #f5f0eb 0%, #e8ddd4 100%);
  min-height: 100vh;
}

.icon-brown {
  color: #4a301f;
}

.text-brown {
  color: #4a301f;
}

.bg-brown {
  background-color: #382417;
  color: #fff;
}

.btn-brown {
  background-color: #382417;
  border-color: #382417;
  color: white;
}

.btn-brown:hover {
  background-color: #4d3420;
  border-color: #4d3420;
  color: white;
}

.btn-outline-brown {
  color: #382417;
  border-color: #382417;
  background-color: transparent;
}

.btn-outline-brown:hover,
.btn-outline-brown:active,
.btn-outline-brown:focus {
  background-color: #382417;
  border-color: #382417;
  color: white;
}

.text-gray {
  color: #595C5F;
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
  box-shadow: 0 0.5rem 1rem rgba(56, 36, 23, 0.15);
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

.input-group-text {
  font-size: 0.875rem;
}

.btn-success {
  background-color: #198754;
  border-color: #198754;
}

.btn-success:hover {
  background-color: #146c43;
  border-color: #146c43;
}

.btn-warning {
  background-color: #ffc107;
  border-color: #ffc107;
  color: #000;
}

.btn-warning:hover {
  background-color: #e0a800;
  border-color: #e0a800;
  color: #000;
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

.form-select-sm, .form-control-sm {
  font-size: 0.875rem;
}

.input-group-sm .input-group-text {
  padding: 0.25rem 0.5rem;
}

.badge {
  font-size: 0.75rem;
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
