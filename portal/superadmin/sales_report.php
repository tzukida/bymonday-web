<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';

  requireAuth();

  $page_title = 'Sales Report';
  require_once BASE_PATH . '/includes/header.php';

  // Get filter parameters
  $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
  $date_to = $_GET['date_to'] ?? date('Y-m-d');
  $staff_filter = $_GET['staff_id'] ?? 'all';
  $payment_filter = $_GET['payment_method'] ?? 'all';
  $page = (int)($_GET['page'] ?? 1);
  $per_page = 25;
  $offset = ($page - 1) * $per_page;

  // Apply filters
  $user_filter = ($staff_filter !== 'all' && is_numeric($staff_filter)) ? (int)$staff_filter : null;

  // Get sales data with filters
  $conn = getDBConnection();

  // Build WHERE clause
  $where_conditions = [];
  $params = [];
  $types = '';

  // Always apply date filter
  $where_conditions[] = "DATE(s.sale_date) BETWEEN ? AND ?";
  $params[] = $date_from;
  $params[] = $date_to;
  $types .= 'ss';

  if ($user_filter) {
    $where_conditions[] = "s.user_id = ?";
    $params[] = $user_filter;
    $types .= 'i';
  }

  if ($payment_filter !== 'all') {
    $where_conditions[] = "s.payment_method = ?";
    $params[] = $payment_filter;
    $types .= 's';
  }

  $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

  // Get filtered sales
  $sql = "SELECT s.*, u.username
          FROM sales s
          JOIN users u ON s.user_id = u.id
          $where_sql
          ORDER BY s.sale_date DESC
          LIMIT ? OFFSET ?";

  $params[] = $per_page;
  $params[] = $offset;
  $types .= 'ii';

  $stmt = $conn->prepare($sql);
  if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // Count total filtered sales
  $count_sql = "SELECT COUNT(*) as total FROM sales s $where_sql";
  $stmt = $conn->prepare($count_sql);
  if (!empty($where_conditions)) {
    $count_params = array_slice($params, 0, count($params) - 2); // Remove limit and offset
    $count_types = substr($types, 0, -2);
    $stmt->bind_param($count_types, ...$count_params);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  $total_sales = $result->fetch_assoc()['total'];
  $stmt->close();

  $total_pages = ceil($total_sales / $per_page);

  // Get total revenue with filters
  $revenue_sql = "SELECT IFNULL(SUM(total_amount), 0) as total FROM sales s $where_sql";
  $stmt = $conn->prepare($revenue_sql);
  if (!empty($where_conditions)) {
    $stmt->bind_param($count_types, ...$count_params);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  $total_revenue = $result->fetch_assoc()['total'];
  $stmt->close();

  // Get top items with filters
  $top_sql = "SELECT m.name, SUM(si.quantity) as total_sold, SUM(si.subtotal) as total_revenue
              FROM sales_items si
              JOIN menu_items m ON si.menu_item_id = m.id
              JOIN sales s ON si.sale_id = s.id
              $where_sql
              GROUP BY si.menu_item_id, m.name
              ORDER BY total_sold DESC
              LIMIT 10";

  $stmt = $conn->prepare($top_sql);
  if (!empty($where_conditions)) {
    $stmt->bind_param($count_types, ...$count_params);
  }
  $stmt->execute();
  $top_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // Get all staff for filter
  $all_staff = getAllUsers();

  // Calculate additional stats
  $average_sale = $total_sales > 0 ? $total_revenue / $total_sales : 0;

  // Get payment method breakdown with filters
  $payment_sql = "SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total
                  FROM sales s
                  $where_sql
                  GROUP BY payment_method";

  $stmt = $conn->prepare($payment_sql);
  if (!empty($where_conditions)) {
    $stmt->bind_param($count_types, ...$count_params);
  }
  $stmt->execute();
  $payment_breakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #4a301f;">Sales Report
          </h3>
          <p class="text-muted mb-0">Analyze sales performance and trends</p>
        </div>
        <div class="d-flex gap-2">
          <button onclick="window.print()" class="btn btn-outline-brown">
            <i class="fas fa-print me-2"></i>Print Report
          </button>
          <a href="pos.php" class="btn btn-brown">
            <i class="fas fa-cash-register me-2"></i>New Sale
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
            <i class="fas fa-receipt fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($total_sales); ?></h3>
          <p class="mb-0 opacity-75">Total Sales</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #4d3420 0%, #382417 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-peso-sign fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1">₱<?php echo number_format($total_revenue, 2); ?></h3>
          <p class="mb-0 opacity-75">Total Revenue</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #654529 0%, #4d3420 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-calculator fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1">₱<?php echo number_format($average_sale, 2); ?></h3>
          <p class="mb-0 opacity-75">Average Sale</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #7d5633 0%, #654529 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-trophy fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1" style="font-size: 1.3rem;">
            <?php echo !empty($top_items) ? htmlspecialchars($top_items[0]['name']) : 'N/A'; ?>
          </h3>
          <p class="mb-0 opacity-75">Top Seller</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters Card -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="fas fa-filter me-2 icon-brown"></i>Filter Reports
          </h5>
          <a href="sales_report.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-times me-1"></i>Clear Filters
          </a>
        </div>
        <div class="card-body">
          <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-calendar-alt me-1"></i>Date From
              </label>
              <input type="date"
                     class="form-control"
                     name="date_from"
                     value="<?php echo htmlspecialchars($date_from); ?>">
            </div>

            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-calendar-alt me-1"></i>Date To
              </label>
              <input type="date"
                     class="form-control"
                     name="date_to"
                     value="<?php echo htmlspecialchars($date_to); ?>">
            </div>

            <?php if (isSuperAdmin()): ?>
            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-user me-1"></i>User
              </label>
              <select name="staff_id" class="form-select">
                <option value="all">All Users</option>
                <?php foreach ($all_staff as $staff): ?>
                  <option value="<?php echo $staff['id']; ?>"
                          <?php echo $staff_filter == $staff['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($staff['username']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-credit-card me-1"></i>Payment
              </label>
              <select name="payment_method" class="form-select">
                <option value="all">All Methods</option>
                <option value="cash" <?php echo $payment_filter == 'cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="gcash" <?php echo $payment_filter == 'gcash' ? 'selected' : ''; ?>>GCash</option>
                <option value="card" <?php echo $payment_filter == 'card' ? 'selected' : ''; ?>>Card</option>
              </select>
            </div>

            <div class="col-md-2">
              <div class="btn-group w-100">
                <button type="submit" class="btn btn-brown">
                  <i class="fas fa-filter me-1"></i>Apply
                </button>
              </div>
            </div>
            <?php else: ?>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-credit-card me-1"></i>Payment
              </label>
              <select name="payment_method" class="form-select">
                <option value="all">All Methods</option>
                <option value="cash" <?php echo $payment_filter == 'cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="gcash" <?php echo $payment_filter == 'gcash' ? 'selected' : ''; ?>>GCash</option>
                <option value="card" <?php echo $payment_filter == 'card' ? 'selected' : ''; ?>>Card</option>
              </select>
            </div>

            <div class="col-md-3">
              <div class="btn-group w-100">
                <button type="submit" class="btn btn-brown">
                  <i class="fas fa-filter me-1"></i>Apply Filter
                </button>
                <a href="sales_report.php" class="btn btn-outline-secondary">
                  <i class="fas fa-times me-1"></i>Clear
                </a>
              </div>
            </div>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Sales Table -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="fas fa-list me-2 icon-brown"></i>Sales Transactions
          </h5>
          <span class="badge bg-brown">
            <?php echo number_format($total_sales); ?> Total Sales
          </span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($sales)): ?>
            <div class="text-center py-5">
              <i class="fas fa-receipt fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No sales found</h5>
              <p class="text-muted">Try adjusting your date range or filters</p>
              <a href="pos.php" class="btn btn-brown mt-2">
                <i class="fas fa-cash-register me-2"></i>Make First Sale
              </a>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="border-0 text-center" style="width: 80px;">Sale ID</th>
                    <th class="border-0">Date & Time</th>
                    <th class="border-0">Customer</th>
                    <th class="border-0 text-center">Staff</th>
                    <th class="border-0 text-center">Payment</th>
                    <th class="border-0 text-end">Amount</th>
                    <th class="border-0 text-center" style="width: 100px;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($sales as $sale): ?>
                    <tr>
                      <td class="text-center align-middle">
                        <strong class="text-gray">#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                      </td>
                      <td class="align-middle">
                        <div class="small">
                          <i class="fas fa-calendar me-1 text-muted"></i>
                          <?php echo formatDate($sale['sale_date'], 'M j, Y'); ?>
                        </div>
                        <div class="small text-muted">
                          <i class="fas fa-clock me-1"></i>
                          <?php echo formatDate($sale['sale_date'], 'g:i A'); ?>
                        </div>
                      </td>
                      <td class="align-middle">
                        <i class="fas fa-user me-1 text-muted"></i>
                        <?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in Customer'); ?>
                      </td>
                      <td class="text-center align-middle">
                        <span class="badge bg-brown text-white">
                          <?php echo htmlspecialchars($sale['username']); ?>
                        </span>
                      </td>
                      <td class="text-center align-middle">
                        <?php
                          $payment_icons = [
                            'cash' => 'fa-money-bill-wave',
                            'gcash' => 'fa-mobile-alt',
                            'card' => 'fa-credit-card'
                          ];
                          $payment_colors = [
                            'cash' => '#7a3b10',
                            'gcash' => '#b85c1a',
                            'card' => '#d4873a'
                          ];
                          $method = strtolower($sale['payment_method']);
                          $icon = $payment_icons[$method] ?? 'fa-money-bill-wave';
                          $color = $payment_colors[$method] ?? 'secondary';
                        ?>
                        <span class="badge" style="background-color: <?php echo $color; ?>; color: #fff;">
                          <i class="fas <?php echo $icon; ?> me-1"></i>
                          <?php echo ucfirst($sale['payment_method']); ?>
                        </span>
                      </td>
                      <td class="text-end align-middle">
                        <span class="fw-bold text-brown" style="font-size: 1.1rem;">
                          ₱<?php echo number_format($sale['total_amount'], 2); ?>
                        </span>
                      </td>
                      <td class="text-center align-middle">
                        <a href="receipt.php?sale_id=<?php echo $sale['id']; ?>"
                           class="btn btn-sm btn-outline-brown"
                           target="_blank"
                           title="View Receipt">
                          <i class="fas fa-receipt"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                  <tr>
                    <td colspan="5" class="text-end fw-bold">Total:</td>
                    <td class="text-end">
                      <strong class="text-brown" style="font-size: 1.2rem;">
                        ₱<?php echo number_format($total_revenue, 2); ?>
                      </strong>
                    </td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Enhanced Pagination -->
            <?php if ($total_pages > 1): ?>
              <div class="card-footer bg-light">
                <div class="row align-items-center">
                  <div class="col-md-6 mb-3 mb-md-0">
                    <p class="text-muted mb-0 small">
                      Showing <?php echo number_format($offset + 1); ?> to
                      <?php echo number_format(min($offset + $per_page, $total_sales)); ?> of
                      <?php echo number_format($total_sales); ?> sales
                    </p>
                  </div>
                  <div class="col-md-6">
                    <nav aria-label="Page navigation">
                      <ul class="pagination pagination-sm justify-content-md-end justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                          <a class="page-link" href="?page=1&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&staff_id=<?php echo urlencode($staff_filter); ?>&payment_method=<?php echo urlencode($payment_filter); ?>">
                            <i class="fas fa-angle-double-left"></i>
                          </a>
                        </li>
                        <?php endif; ?>

                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                          <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&staff_id=<?php echo urlencode($staff_filter); ?>&payment_method=<?php echo urlencode($payment_filter); ?>">
                            <i class="fas fa-angle-left"></i>
                          </a>
                        </li>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        if ($start_page > 1) {
                          echo '<li class="page-item"><a class="page-link" href="?page=1&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&staff_id=' . urlencode($staff_filter) . '&payment_method=' . urlencode($payment_filter) . '">1</a></li>';
                          if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }

                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                          <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&staff_id=<?php echo urlencode($staff_filter); ?>&payment_method=<?php echo urlencode($payment_filter); ?>">
                              <?php echo $i; ?>
                            </a>
                          </li>
                        <?php endfor;

                        if ($end_page < $total_pages) {
                          if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                          echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&staff_id=' . urlencode($staff_filter) . '&payment_method=' . urlencode($payment_filter) . '">' . $total_pages . '</a></li>';
                        }
                        ?>

                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                          <a class="page-link" href="?page=<?php echo min($total_pages, $page + 1); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&staff_id=<?php echo urlencode($staff_filter); ?>&payment_method=<?php echo urlencode($payment_filter); ?>">
                            <i class="fas fa-angle-right"></i>
                          </a>
                        </li>

                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                          <a class="page-link" href="?page=<?php echo $total_pages; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&staff_id=<?php echo urlencode($staff_filter); ?>&payment_method=<?php echo urlencode($payment_filter); ?>">
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
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Right Sidebar -->
    <div class="col-lg-4">
      <!-- Top Sellers -->
      <div class="card mb-4">
        <div class="card-header bg-white py-3">
          <h5 class="mb-0">
            <i class="fas fa-trophy me-2 icon-brown"></i>Top Selling Items
          </h5>
        </div>
        <div class="card-body">
          <?php if (empty($top_items)): ?>
            <div class="text-center py-4 text-muted">
              <i class="fas fa-chart-bar fa-3x mb-3"></i>
              <p>No sales data available</p>
            </div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php
              $rank_colors = [
                1 => 'bg-warning text-dark',
                2 => 'bg-secondary text-white',
                3 => 'bg-bronze text-white'
              ];
              foreach ($top_items as $index => $item):
                $rank = $index + 1;
                $badge_class = $rank_colors[$rank] ?? 'bg-light text-dark';
              ?>
                <div class="list-group-item px-0 border-0">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-start flex-grow-1">
                      <span class="badge rank-badge <?php echo $badge_class; ?> me-2">
                        #<?php echo $rank; ?>
                      </span>
                      <div>
                        <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                        <small class="text-muted">
                          <i class="fas fa-box me-1"></i><?php echo number_format($item['total_sold']); ?> sold
                        </small>
                      </div>
                    </div>
                    <div class="text-end">
                      <strong class="text-brown d-block">
                        ₱<?php echo number_format($item['total_revenue'], 2); ?>
                      </strong>
                    </div>
                  </div>
                  <?php if ($index < count($top_items) - 1): ?>
                    <hr class="my-2">
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Payment Method Breakdown -->
      <?php if (!empty($payment_breakdown)): ?>
      <div class="card">
        <div class="card-header bg-white py-3">
          <h5 class="mb-0">
            <i class="fas fa-credit-card me-2 icon-brown"></i>Payment Methods
          </h5>
        </div>
        <div class="card-body">
          <canvas id="paymentChart" style="max-height: 250px;"></canvas>
          <div class="mt-4">
            <?php foreach ($payment_breakdown as $payment):
              $percentage = ($payment['total'] / $total_revenue) * 100;
              $method_colors = [
                'cash'  => '#382417',
                'gcash' => '#4d3420',
                'card'  => '#654529'
              ];
              $color = $method_colors[strtolower($payment['payment_method'])] ?? '#7d5633';
            ?>
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="text-muted">
                    <i class="fas fa-circle me-2" style="font-size: 0.6rem; color: <?php echo $color; ?>;"></i>
                    <?php echo ucfirst($payment['payment_method']); ?>
                  </span>
                  <strong>₱<?php echo number_format($payment['total'], 2); ?></strong>
                </div>
                <div class="progress" style="height: 8px;">
                  <div class="progress-bar"
                       role="progressbar"
                       style="width: <?php echo $percentage; ?>%; background-color: <?php echo $color; ?>;">
                  </div>
                </div>
                <small class="text-muted">
                  <?php echo number_format($percentage, 1); ?>% • <?php echo $payment['count']; ?> transactions
                </small>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
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

.btn-outline-brown:hover {
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

.rank-badge {
  font-weight: 600;
  font-size: 0.85rem;
  padding: 0.4rem 0.6rem;
  min-width: 38px;
  text-align: center;
  border: none !important;
}

.bg-bronze {
  background-color: #CD7F32 !important;
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

.progress {
  background-color: #e9ecef;
}

@media print {
  .btn, .pagination, .card-header, .sidebar {
    display: none !important;
  }

  .card {
    box-shadow: none;
    border: 1px solid #dee2e6;
  }

  .main-content {
    margin-left: 0 !important;
  }
}

@media (max-width: 768px) {
  .table {
    font-size: 0.875rem;
  }

  .rank-badge {
    font-size: 0.75rem;
    padding: 0.3rem 0.5rem;
    min-width: 32px;
  }
}

.form-control:focus,
.form-select:focus {
  border-color: #4a301f !important;
  box-shadow: 0 0 0 0.2rem rgba(74, 48, 31, 0.25) !important;
  outline: none !important;
}

.form-select option:checked {
  background-color: #3b2008 !important;
  color: #fff !important;
}

</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const paymentCtx = document.getElementById('paymentChart');

  <?php if (!empty($payment_breakdown)): ?>
  if (paymentCtx) {
    new Chart(paymentCtx, {
      type: 'doughnut',
      data: {
        labels: <?php echo json_encode(array_map(function($p) { return ucfirst($p['payment_method']); }, $payment_breakdown)); ?>,
        datasets: [{
          data: <?php echo json_encode(array_column($payment_breakdown, 'total')); ?>,
          backgroundColor: [
            'rgba(56, 36, 23, 0.8)',
            'rgba(77, 52, 32, 0.8)',
            'rgba(101, 69, 41, 0.8)',
            'rgba(125, 86, 51, 0.8)'
          ],
          borderWidth: 0,
          hoverOffset: 10
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            padding: 12,
            callbacks: {
              label: function(context) {
                const label = context.label || '';
                const value = context.parsed;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = ((value / total) * 100).toFixed(1);
                return [
                  `${label}`,
                  `Amount: ₱${value.toLocaleString('en-US', {minimumFractionDigits: 2})}`,
                  `Share: ${percentage}%`
                ];
              }
            }
          }
        },
        cutout: '65%'
      }
    });
  }
  <?php endif; ?>
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
