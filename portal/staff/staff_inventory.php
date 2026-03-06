<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireStaff();

  $conn = getDBConnection();
  // AJAX: return low stock items as JSON
  if (isset($_GET['get_low_stock'])) {
      $lsconn = getDBConnection();
      $lsres = $lsconn->query("SELECT item_name as name, quantity, unit FROM inventory WHERE quantity <= 10 ORDER BY quantity ASC");
      header('Content-Type: application/json');
      echo json_encode($lsres->fetch_all(MYSQLI_ASSOC));
      exit;
  }

  // Handle send restock email — ONE combined email
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_restock_email'])) {
      require_once BASE_PATH . '/includes/mailer.php';
      $supplier_email  = trim($_POST['supplier_email'] ?? '');
      $low_stock_items = json_decode($_POST['low_stock_items'] ?? '[]', true);
      if (!empty($low_stock_items)) {
          sendRestockEmail('angelaccortes01@gmail.com', $low_stock_items);
          if (!empty($supplier_email) && filter_var($supplier_email, FILTER_VALIDATE_EMAIL)) {
              sendRestockEmail($supplier_email, $low_stock_items);
          }
      }
      $_SESSION['success_message'] = '✅ Restock request email sent!';
      header('Location: ' . $_SERVER['PHP_SELF']);
      exit;
  }
  // Get filter and search parameters
  $search = sanitizeInput($_GET['search'] ?? '');
  $filter = sanitizeInput($_GET['filter'] ?? '');

  // Pagination
  $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $per_page = 25;
  $offset = ($page - 1) * $per_page;

  // Build query
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
    $where_conditions[] = "quantity <= 10";
  }

  $where_sql = '';
  if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
  }

  // Get total count
  $count_sql = "SELECT COUNT(*) as total FROM inventory $where_sql";
  $count_stmt = $conn->prepare($count_sql);
  if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
  }
  $count_stmt->execute();
  $total_items = $count_stmt->get_result()->fetch_assoc()['total'];
  $count_stmt->close();

  $total_pages = max(1, ceil($total_items / $per_page));

  // Get inventory items
  $sql = "SELECT id, item_name, description, unit, quantity, updated_at
          FROM inventory
          $where_sql
          ORDER BY item_name ASC
          LIMIT ? OFFSET ?";

  $stmt = $conn->prepare($sql);
  $types_with_pagination = $types . 'ii';
  $params_with_pagination = array_merge($params, [$per_page, $offset]);
  if (!empty($params_with_pagination)) {
    $stmt->bind_param($types_with_pagination, ...$params_with_pagination);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  $items = $result->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // Get statistics
  $stats_stmt = $conn->prepare("
    SELECT
      COUNT(*) as total_items,
      SUM(CASE WHEN quantity <= 10 THEN 1 ELSE 0 END) as low_stock_count,
      SUM(CASE WHEN quantity > 10 AND quantity <= 50 THEN 1 ELSE 0 END) as medium_stock_count,
      SUM(CASE WHEN quantity > 50 THEN 1 ELSE 0 END) as high_stock_count
    FROM inventory
  ");
  $stats_stmt->execute();
  $stats = $stats_stmt->get_result()->fetch_assoc();
  $stats_stmt->close();

  $page_title = 'Inventory';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0 text-brown">Inventory
          </h3>
          <p class="text-muted mb-0">View inventory levels and manage stock</p>
        </div>
        <div>
                    <button class="btn me-2" onclick="openEmailModal()" style="background:#c87533;border-color:#c87533;color:#fff;border:1px solid #c87533;">
            <i class="fas fa-envelope me-2"></i>Email Supplier
          </button>

          <span class="badge bg-brown-soft text-brown fs-6">
            <i class="fas fa-user me-1"></i>Staff View
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats Cards Row -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100 stats-card-brown">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-boxes fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['total_items']); ?></h3>
          <p class="mb-0 opacity-75">Total Items</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100 stats-card-danger">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['low_stock_count']); ?></h3>
          <p class="mb-0 opacity-75">Low Stock</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100 stats-card-warning">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-box fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['medium_stock_count']); ?></h3>
          <p class="mb-0 opacity-75">Medium Stock</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100 stats-card-success">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-check-circle fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['high_stock_count']); ?></h3>
          <p class="mb-0 opacity-75">Good Stock</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Search and Filter Card -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-search me-1"></i>Search Items
              </label>
              <input type="text"
                     class="form-control"
                     name="search"
                     value="<?php echo htmlspecialchars($search); ?>"
                     placeholder="Search by name or description...">
            </div>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-filter me-1"></i>Filter by Stock
              </label>
              <select name="filter" class="form-select">
                <option value="">All Items</option>
                <option value="low_stock" <?php echo $filter === 'low_stock' ? 'selected' : ''; ?>>Low Stock (≤ 10)</option>
              </select>
            </div>
            <div class="col-md-4">
              <div class="btn-group w-100">
                <button type="submit" class="btn btn-brown">
                  <i class="fas fa-filter me-1"></i>Apply Filter
                </button>
                <a href="staff_inventory.php" class="btn btn-outline-secondary">
                  <i class="fas fa-times me-1"></i>Clear
                </a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Inventory Table -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="fas fa-list me-2 icon-brown"></i>Inventory Items
          </h5>
          <span class="badge bg-brown">
            <?php echo number_format($total_items); ?> Items
          </span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($items)): ?>
            <div class="text-center py-5">
              <i class="fas fa-boxes fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No inventory items found</h5>
              <?php if (!empty($search) || !empty($filter)): ?>
                <p class="text-muted mb-3">Try adjusting your search or filter criteria</p>
                <a href="staff_inventory.php" class="btn btn-outline-brown">Clear Filters</a>
              <?php else: ?>
                <p class="text-muted">No items available in inventory</p>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover mb-0" id="inventoryTable">
                <thead class="table-light">
                  <tr>
                    <th class="border-0" style="width: 25%;">Item Name</th>
                    <th class="border-0" style="width: 30%;">Description</th>
                    <th class="border-0 text-center" style="width: 10%;">Unit</th>
                    <th class="border-0 text-center" style="width: 12%;">Quantity</th>
                    <th class="border-0 text-center" style="width: 13%;">Status</th>
                    <th class="border-0 text-center" style="width: 10%;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $item): ?>
                  <tr>
                    <td class="fw-semibold align-middle">
                      <i class="fas fa-box me-2 text-brown"></i>
                      <?php echo htmlspecialchars($item['item_name']); ?>
                    </td>
                    <td class="text-muted small align-middle">
                      <?php echo htmlspecialchars($item['description'] ?: 'No description'); ?>
                    </td>
                    <td class="text-center align-middle">
                      <span class="badge bg-secondary">
                        <?php echo htmlspecialchars($item['unit']); ?>
                      </span>
                    </td>
                    <td class="text-center align-middle">
                      <strong class="fs-5 <?php
                        if ($item['quantity'] <= 10) echo 'text-danger';
                        elseif ($item['quantity'] <= 50) echo 'text-warning';
                        else echo 'text-success';
                      ?>">
                        <?php echo formatNumber($item['quantity']); ?>
                      </strong>
                    </td>
                    <td class="text-center align-middle">
                      <?php if ($item['quantity'] <= 10): ?>
                        <span class="badge bg-danger">
                          <i class="fas fa-exclamation-triangle me-1"></i>Low Stock
                        </span>
                      <?php elseif ($item['quantity'] <= 50): ?>
                        <span class="badge bg-warning text-dark">
                          <i class="fas fa-exclamation-circle me-1"></i>Medium
                        </span>
                      <?php else: ?>
                        <span class="badge bg-success">
                          <i class="fas fa-check-circle me-1"></i>Good
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center align-middle">
                      <a href="stock_out.php?id=<?php echo $item['id']; ?>"
                         class="btn btn-outline-brown btn-sm stockout-btn"
                         title="Stock Out">
                        <i class="fas fa-arrow-down me-1"></i>Stock Out
                      </a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1 && !empty($items)): ?>
        <div class="card-footer bg-light">
          <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
              <p class="text-muted mb-0 small">
                Showing <?php echo number_format($offset + 1); ?> to
                <?php echo number_format(min($offset + $per_page, $total_items)); ?> of
                <?php echo number_format($total_items); ?> items
              </p>
            </div>
            <div class="col-md-6">
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm justify-content-md-end justify-content-center mb-0">
                  <!-- First Page -->
                  <?php if ($page > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                      <i class="fas fa-angle-double-left"></i>
                    </a>
                  </li>
                  <?php endif; ?>

                  <!-- Previous Page -->
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                      <i class="fas fa-angle-left"></i>
                    </a>
                  </li>

                  <!-- Page Numbers -->
                  <?php
                  $start_page = max(1, $page - 2);
                  $end_page = min($total_pages, $page + 2);

                  if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&filter=' . urlencode($filter) . '">1</a></li>';
                    if ($start_page > 2) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                  }

                  for ($i = $start_page; $i <= $end_page; $i++):
                  ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                      <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                        <?php echo $i; ?>
                      </a>
                    </li>
                  <?php
                  endfor;

                  if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&filter=' . urlencode($filter) . '">' . $total_pages . '</a></li>';
                  }
                  ?>

                  <!-- Next Page -->
                  <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                      <i class="fas fa-angle-right"></i>
                    </a>
                  </li>

                  <!-- Last Page -->
                  <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
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

  <!-- Quick Stats Footer -->
  <div class="row mt-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <div class="row text-center">
            <div class="col-md-4 mb-3 mb-md-0">
              <div class="d-flex align-items-center justify-content-center">
                <i class="fas fa-info-circle icon-brown me-2 fs-4"></i>
                <div class="text-start">
                  <small class="text-muted d-block">Need to restock?</small>
                  <strong class="text-brown">Contact your admin</strong>
                </div>
              </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
              <div class="d-flex align-items-center justify-content-center">
                <i class="fas fa-clock text-warning me-2 fs-4"></i>
                <div class="text-start">
                  <small class="text-muted d-block">Last Updated</small>
                  <strong><?php echo date('M j, Y g:i A'); ?></strong>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="d-flex align-items-center justify-content-center">
                <i class="fas fa-user text-success me-2 fs-4"></i>
                <div class="text-start">
                  <small class="text-muted d-block">Logged in as</small>
                  <strong><?php echo htmlspecialchars($current_user['username']); ?></strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
body {
  background: linear-gradient(135deg, #f5f0eb 0%, #e8ddd4 100%);
  min-height: 100vh;
}

.text-brown {
  color: #4a301f !important;
}

.icon-brown {
  color: #4a301f;
}

.bg-brown {
  background-color: #382417;
  color: #fff;
}

.bg-brown-soft {
  background-color: rgba(74, 48, 31, 0.1);
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

.stats-card-brown {
  background: linear-gradient(135deg, #4a301f 0%, #382417 100%);
}

.stats-card-danger {
  background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%);
}

.stats-card-warning {
  background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
}

.stats-card-success {
  background: linear-gradient(135deg, #198754 0%, #146c43 100%);
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  transition: transform 0.2s, box-shadow 0.2s;
  border-radius: 12px;
}

.card:hover {
  box-shadow: 0 0.5rem 1rem rgba(74, 48, 31, 0.15);
}

.table thead th {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.5px;
  color: #6c757d;
  white-space: nowrap;
}

.table tbody tr {
  transition: background-color 0.2s;
}

.table tbody tr:hover {
  background-color: #fff3e0;
}

.badge {
  font-weight: 500;
  padding: 0.35rem 0.65rem;
}

.form-label {
  font-weight: 500;
  color: #495057;
}

.form-control:focus,
.form-select:focus {
  border-color: #654529;
  box-shadow: 0 0 0 0.2rem rgba(101, 69, 41, 0.25);
}

.pagination {
  --bs-pagination-active-bg: #382417;
  --bs-pagination-active-border-color: #382417;
  --bs-pagination-hover-color: #4a301f;
}

.pagination .page-link {
  color: #6c757d;
  border-radius: 0.25rem;
  margin: 0 2px;
  transition: all 0.2s;
}

.pagination .page-link:hover {
  background-color: #fff3e0;
  border-color: #dee2e6;
  color: #4a301f;
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

.stockout-btn {
  font-size: 0.8rem;
  font-weight: 600;
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
  .table thead th {
    font-size: 0.7rem;
    padding: 0.5rem 0.25rem;
  }

  .table tbody td {
    padding: 0.5rem 0.25rem;
  }

  .badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.4rem;
  }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-refresh every 5 minutes
setTimeout(function() {
  if (!document.hidden) {
    location.reload();
  }
}, 5 * 60 * 1000);

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>


<!-- ── Email Supplier Modal ── -->
<style>
#emailSupplierModal .modal-content {
    background: #2e1c0e;
    border: 1px solid rgba(201,123,43,0.3);
    border-radius: 20px;
    overflow: hidden;
    color: #f7f2ec;
}
#emailSupplierModal .modal-header {
    background: #1a0f08;
    border-bottom: 1px solid rgba(201,123,43,0.2);
    padding: 20px 24px;
}
#emailSupplierModal .modal-title {
    font-family: Georgia, serif;
    font-size: 1.1rem;
    color: #f7f2ec;
}
#emailSupplierModal .modal-body {
    background: #2e1c0e;
    padding: 20px 24px;
}
#emailSupplierModal .modal-footer {
    background: #1a0f08;
    border-top: 1px solid rgba(201,123,43,0.2);
    padding: 16px 24px;
}
#emailSupplierModal .restock-subtitle {
    color: #c9b594;
    font-size: 13px;
    margin-bottom: 16px;
}
#emailSupplierModal .restock-label {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #c97b2b;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
#restockItemsList {
    background: rgba(26,15,8,0.6);
    border: 1px solid rgba(201,123,43,0.2);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 16px;
    max-height: 280px;
    overflow-y: auto;
}
.restock-item-row {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 12px;
    padding: 11px 14px;
    border-bottom: 1px solid rgba(201,123,43,0.1);
    transition: background 0.15s;
}
.restock-item-row:last-child { border-bottom: none; }
.restock-item-row:hover { background: rgba(201,123,43,0.07); }
.restock-item-name {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #f7f2ec;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    min-width: 0;
}
.restock-item-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #c97b2b;
    flex-shrink: 0;
}
.restock-item-qty {
    color: #dc3545;
    font-size: 13px;
    font-weight: 700;
    font-family: monospace;
    min-width: 28px;
    text-align: center;
}
.qty-btn {
    width: 24px; height: 24px;
    border-radius: 6px;
    background: rgba(201,123,43,0.15);
    border: 1px solid rgba(201,123,43,0.3);
    color: #c97b2b;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s;
    line-height: 1;
}
.qty-btn:hover { background: rgba(201,123,43,0.35); color: #f7f2ec; }
.qty-control {
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(26,15,8,0.4);
    border: 1px solid rgba(201,123,43,0.2);
    border-radius: 8px;
    padding: 3px 8px;
}
.restock-item-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}
.restock-item-unit {
    color: #9b7e60;
    font-size: 11px;
    min-width: 28px;
    text-align: left;
}
.restock-item-remove {
    width: 22px; height: 22px;
    border-radius: 50%;
    background: rgba(220,53,69,0.15);
    border: 1px solid rgba(220,53,69,0.3);
    color: #dc3545;
    font-size: 12px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s;
    line-height: 1;
}
.restock-item-remove:hover { background: rgba(220,53,69,0.35); }
#emailSupplierModal .supplier-email-label {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #c9b594;
    margin-bottom: 8px;
}
#emailSupplierModal .form-control {
    background: rgba(26,15,8,0.6);
    border: 1px solid rgba(201,123,43,0.3);
    border-radius: 10px;
    color: #f7f2ec;
    padding: 10px 14px;
}
#emailSupplierModal .form-control::placeholder { color: #9b7e60; }
#emailSupplierModal .form-control:focus {
    background: rgba(26,15,8,0.8);
    border-color: #c97b2b;
    box-shadow: 0 0 0 3px rgba(201,123,43,0.15);
    color: #f7f2ec;
}
#emailSupplierModal .form-hint {
    font-size: 11px;
    color: #9b7e60;
    margin-top: 5px;
}
#emailSupplierModal .btn-cancel {
    background: rgba(201,123,43,0.1);
    color: #c9b594;
    border: 1px solid rgba(201,123,43,0.2);
    border-radius: 10px;
    padding: 10px 20px;
}
#emailSupplierModal .btn-cancel:hover { background: rgba(201,123,43,0.2); }
#emailSupplierModal .btn-send {
    background: linear-gradient(135deg, #c97b2b, #e09a4a);
    color: #1a0f08;
    border: none;
    border-radius: 10px;
    padding: 10px 22px;
    font-weight: 700;
    box-shadow: 0 4px 16px rgba(201,123,43,0.35);
}
#emailSupplierModal .btn-send:hover { filter: brightness(1.08); }
#emailSupplierModal .empty-msg {
    padding: 20px;
    text-align: center;
    color: #9b7e60;
    font-size: 13px;
}
</style>

<div class="modal fade" id="emailSupplierModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">✉️ &nbsp;Email Supplier</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="restock-subtitle">Review low-stock items and send a single restock request email.</p>

        <div class="restock-label">
          ⚠️ &nbsp;Restock List &nbsp;<span id="restockBadge" style="background:rgba(220,53,69,0.2);color:#dc3545;border-radius:20px;padding:2px 10px;font-size:11px;">0</span>
        </div>

        <div id="restockItemsList"></div>

        <form method="POST" id="emailSupplierForm">
          <input type="hidden" name="send_restock_email" value="1">
          <input type="hidden" name="low_stock_items" id="lowStockItemsInput" value="">
          <div>
            <div class="supplier-email-label">@ &nbsp;Supplier Email &nbsp;<span style="font-weight:400;text-transform:none;letter-spacing:0;color:#9b7e60;">(optional)</span></div>
            <input type="email" name="supplier_email" class="form-control" placeholder="supplier@example.com">
            <div class="form-hint">Leave blank to only notify angelaccortes01@gmail.com</div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-send" onclick="submitEmailForm()">
          <i class="fas fa-paper-plane me-2"></i>Send Restock Email
        </button>
      </div>
    </div>
  </div>
</div>

<script>
let restockItems = [];

function openEmailModal() {
    fetch(window.location.pathname + '?get_low_stock=1')
        .then(r => r.json())
        .then(items => {
            restockItems = items.map(i => ({...i, included: true, requestQty: 1}));
            renderRestockList();
            new bootstrap.Modal(document.getElementById('emailSupplierModal')).show();
        });
}

function renderRestockList() {
    const list = document.getElementById('restockItemsList');
    const active = restockItems.filter(i => i.included);
    document.getElementById('restockBadge').textContent = active.length;
    document.getElementById('lowStockItemsInput').value = JSON.stringify(active);

    if (active.length === 0) {
        list.innerHTML = '<div class="empty-msg"><i class="fas fa-check-circle" style="color:#4ade80;margin-right:6px;"></i>No items selected.</div>';
        return;
    }

    list.innerHTML = restockItems.map((item, idx) => item.included ? `
        <div class="restock-item-row" id="row-${idx}">
            <div class="restock-item-name">
                <span class="restock-item-dot"></span>
                ${item.name}
            </div>
            <div class="restock-item-actions">
                <div class="qty-control">
                    <button class="qty-btn" onclick="changeQty(${idx}, -1)">−</button>
                    <span class="restock-item-qty" id="qty-${idx}">${item.requestQty}</span>
                    <button class="qty-btn" onclick="changeQty(${idx}, 1)">+</button>
                </div>
                <span class="restock-item-unit">${item.unit}</span>
                <button class="restock-item-remove" onclick="removeItem(${idx})" title="Remove">✕</button>
            </div>
        </div>` : ''
    ).join('');
}

function removeItem(idx) {
    restockItems[idx].included = false;
    renderRestockList();
}
function changeQty(idx, delta) {
    restockItems[idx].requestQty = Math.max(1, (restockItems[idx].requestQty || 1) + delta);
    document.getElementById('qty-' + idx).textContent = restockItems[idx].requestQty;
    // update hidden input live
    const active = restockItems.filter(i => i.included).map(i => ({...i, quantity: i.requestQty}));
    document.getElementById('lowStockItemsInput').value = JSON.stringify(active);
}

function submitEmailForm() {
    const active = restockItems.filter(i => i.included).map(i => ({...i, quantity: i.requestQty}));
    if (active.length === 0) {
        alert('No items selected to send.');
        return;
    }
    document.getElementById('lowStockItemsInput').value = JSON.stringify(active);
    const btn = document.querySelector('#emailSupplierModal .btn-send');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
    document.getElementById('emailSupplierForm').submit();
}
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>