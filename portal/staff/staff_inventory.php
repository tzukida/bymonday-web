<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireStaff();

  $conn = getDBConnection();

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
        <div class="d-flex gap-2 align-items-center">
          <button type="button" class="btn btn-email-supplier" onclick="openEmailModal()">
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
                <i class="fas fa-clock icon-brown me-2 fs-4"></i>
                <div class="text-start">
                  <small class="text-muted d-block">Last Updated</small>
                  <strong><?php echo date('M j, Y g:i A'); ?></strong>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="d-flex align-items-center justify-content-center">
                <i class="fas fa-user icon-brown me-2 fs-4"></i>
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
  background: linear-gradient(135deg, #6b3a1f 0%, #3d1c02 100%);
}

.stats-card-warning {
  background: linear-gradient(135deg, #5a2d00 0%, #3d1c02 100%);
}

.stats-card-success {
  background: linear-gradient(135deg, #c87533 0%, #a05a20 100%);
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

<style>
/* Email Supplier button — warm glowing gradient */
.btn-email-supplier {
  background: linear-gradient(135deg, #c87533 0%, #a05a20 100%);
  border: none;
  color: #fff;
  font-weight: 600;
  letter-spacing: 0.3px;
  box-shadow: 0 4px 15px rgba(200, 117, 51, 0.4);
  transition: all 0.3s ease;
}
.btn-email-supplier:hover,
.btn-email-supplier:focus,
.btn-email-supplier:active {
  background: linear-gradient(135deg, #d98844 0%, #b56a2e 100%) !important;
  color: #fff !important;
  box-shadow: 0 6px 20px rgba(200, 117, 51, 0.6) !important;
  transform: translateY(-1px);
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





<!-- Email Supplier Modal -->
<div class="modal fade" id="emailSupplierModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
    <div class="modal-content" style="border-radius:14px; border:none; overflow:hidden; background:linear-gradient(160deg, #c8722a 0%, #7a3a12 25%, #3b1f0e 55%, #1e1208 100%);">
      <div class="modal-header border-0 pb-2" style="background:transparent; padding:20px 24px 12px;">
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-envelope" style="color:#c8956a; font-size:1rem;"></i>
          <h5 class="modal-title fw-bold mb-0" style="color:#fff; font-size:1.05rem;">Email Supplier</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div style="background:transparent; padding:0 24px 16px;">
        <p class="mb-0" style="color:#a07860; font-size:0.83rem;">Review low-stock items and send a single restock request email.</p>
      </div>
      <div class="modal-body p-0" style="background:transparent;">
        <div id="emailRestockList">
          <div class="d-flex align-items-center gap-2 px-4 py-3" style="border-bottom:1px solid rgba(200,140,80,0.15);">
            <i class="fas fa-exclamation-triangle" style="color:#e8a830; font-size:0.85rem;"></i>
            <span class="fw-bold" style="color:#d4b896; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.8px;">Restock List</span>
            <span id="lowStockBadge" class="badge rounded-pill ms-1" style="background:#c0622a; font-size:0.72rem; padding:3px 10px; font-weight:600;">0</span>
          </div>
          <div id="lowStockItems" style="max-height:240px; overflow-y:auto;"></div>
        </div>
        <div id="noLowStockMsg" class="d-none text-center py-4 px-4">
          <i class="fas fa-check-circle fa-2x mb-2" style="color:#5a9a5a;"></i>
          <p class="small mb-0" style="color:#8a7060;">All items are sufficiently stocked.</p>
        </div>
        <div class="px-4 py-3" style="border-top:1px solid rgba(200,140,80,0.15);">
          <label class="d-block mb-2" style="color:#a07860; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.8px;">
            Supplier Email
          </label>
          <input type="email" id="supplierEmailInput" class="form-control"
                 value="angelaccortes01@gmail.com" placeholder="supplier@example.com"
                 style="background:rgba(0,0,0,0.35); border:1px solid rgba(200,140,80,0.25); color:#e8ddd4; border-radius:8px; font-size:0.9rem; padding:10px 14px;">
        </div>
      </div>
      <div class="modal-footer border-0 gap-2 px-4 py-3" style="background:transparent; border-top:1px solid rgba(200,140,80,0.15);">
        <button type="button" class="btn flex-fill fw-semibold" data-bs-dismiss="modal"
                style="background:rgba(0,0,0,0.3); border:1px solid rgba(200,140,80,0.2); color:#c8a882; border-radius:8px; padding:10px; font-size:0.9rem;">
          Cancel
        </button>
        <button type="button" class="btn flex-fill fw-semibold" id="sendEmailBtn"
                style="background:linear-gradient(135deg,#c87533 0%,#a05a20 100%); border:none; color:#fff;
                       border-radius:8px; padding:10px; font-size:0.9rem;
                       box-shadow:0 4px 15px rgba(200,117,51,0.45);
                       transition:all 0.3s ease;">
          <i class="fas fa-paper-plane me-2"></i>Send Email
        </button>
      </div>
    </div>
  </div>
</div>

<style>
#lowStockItems::-webkit-scrollbar { width: 5px; }
#lowStockItems::-webkit-scrollbar-track { background: transparent; }
#lowStockItems::-webkit-scrollbar-thumb { background: #4a3528; border-radius: 4px; }
#supplierEmailInput:focus {
  background: #1e1610 !important; border-color: #8a5a30 !important;
  color: #e8ddd4 !important; box-shadow: 0 0 0 0.2rem rgba(138,90,48,0.25) !important;
}
</style>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
  <div id="emailToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body fw-semibold" id="emailToastMsg"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script>
var lowStockData = <?php
  $conn_ls = getDBConnection();
  $ls_stmt = $conn_ls->prepare("SELECT item_name, quantity, unit FROM inventory WHERE quantity <= 10 ORDER BY quantity ASC");
  $ls_stmt->execute();
  echo json_encode($ls_stmt->get_result()->fetch_all(MYSQLI_ASSOC));
  $ls_stmt->close();
?>;
var qtyOverrides = {};

function openEmailModal() {
  qtyOverrides = {};
  var list = document.getElementById('lowStockItems');
  var noStock = document.getElementById('noLowStockMsg');
  var restockSec = document.getElementById('emailRestockList');
  var sendBtn = document.getElementById('sendEmailBtn');
  list.innerHTML = '';
  if (lowStockData.length === 0) {
    noStock.classList.remove('d-none');
    restockSec.classList.add('d-none');
    sendBtn.disabled = true;
  } else {
    noStock.classList.add('d-none');
    restockSec.classList.remove('d-none');
    sendBtn.disabled = false;
    document.getElementById('lowStockBadge').textContent = lowStockData.length;
    lowStockData.forEach(function(item, idx) {
      qtyOverrides[idx] = 1;
      var row = document.createElement('div');
      row.id = 'restock_row_' + idx;
      row.style.cssText = 'display:flex;align-items:center;padding:10px 24px;border-bottom:1px solid rgba(200,140,80,0.12);gap:10px;background:rgba(0,0,0,0.15);';
      row.innerHTML =
        '<span style="color:#c0622a;font-size:9px;flex-shrink:0;">&#9679;</span>' +
        '<span class="fw-semibold" style="color:#e8ddd4;font-size:0.88rem;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escHtml(item.item_name) + '</span>' +
        '<button onclick="changeQty(' + idx + ',-1)" style="width:28px;height:28px;border-radius:6px;border:none;background:#4a301f;color:#e8a830;font-weight:700;font-size:1.1rem;cursor:pointer;flex-shrink:0;">&#8722;</button>' +
        '<span id="qty_' + idx + '" style="min-width:24px;text-align:center;color:#e8ddd4;font-size:0.88rem;font-weight:600;">1</span>' +
        '<button onclick="changeQty(' + idx + ',1)" style="width:28px;height:28px;border-radius:6px;border:none;background:#4a301f;color:#e8a830;font-weight:700;font-size:1.1rem;cursor:pointer;flex-shrink:0;">+</button>' +
        '<span style="color:#8a7060;font-size:0.8rem;min-width:30px;">' + escHtml(item.unit) + '</span>' +
        '<button onclick="removeRestockItem(' + idx + ')" style="width:26px;height:26px;border-radius:50%;border:none;background:#5a1f1f;color:#e07878;font-size:0.8rem;cursor:pointer;flex-shrink:0;" title="Remove">&#10005;</button>';
      list.appendChild(row);
    });
  }
  new bootstrap.Modal(document.getElementById('emailSupplierModal')).show();
}

function escHtml(str) {
  var d = document.createElement('div');
  d.appendChild(document.createTextNode(str));
  return d.innerHTML;
}
function changeQty(idx, delta) {
  qtyOverrides[idx] = Math.max(1, (qtyOverrides[idx] || 1) + delta);
  var el = document.getElementById('qty_' + idx);
  if (el) el.textContent = qtyOverrides[idx];
}
function removeRestockItem(idx) {
  var row = document.getElementById('restock_row_' + idx);
  if (row) row.remove();
  delete qtyOverrides[idx];
  var remaining = document.querySelectorAll('[id^="restock_row_"]').length;
  document.getElementById('lowStockBadge').textContent = remaining;
  if (remaining === 0) {
    document.getElementById('noLowStockMsg').classList.remove('d-none');
    document.getElementById('sendEmailBtn').disabled = true;
  }
}

document.getElementById('sendEmailBtn').addEventListener('click', function() {
  var supplierEmail = document.getElementById('supplierEmailInput').value.trim();
  var emailInput = document.getElementById('supplierEmailInput');
  if (!supplierEmail) {
    emailInput.focus(); emailInput.style.borderColor = '#c0622a';
    showToast('Please enter a supplier email address.', false); return;
  }
  emailInput.style.borderColor = '';
  var items = [];
  lowStockData.forEach(function(item, idx) {
    if (document.getElementById('restock_row_' + idx)) {
      items.push({ name: item.item_name, qty: qtyOverrides[idx] || 1, unit: item.unit });
    }
  });
  if (items.length === 0) { showToast('No items to send.', false); return; }
  var btn = document.getElementById('sendEmailBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
  fetch('../includes/email_supplier.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ supplier_email: supplierEmail, items: items })
  })
  .then(function(r) {
    var ct = r.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      return r.text().then(function(t) { throw new Error('Server error: ' + t.substring(0,150)); });
    }
    return r.json();
  })
  .then(function(data) {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Email';
    if (data.success) {
      bootstrap.Modal.getInstance(document.getElementById('emailSupplierModal')).hide();
      showToast(data.message || 'Email sent!', true);
    } else {
      showToast(data.message || 'Failed to send email.', false);
    }
  })
  .catch(function(err) {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Email';
    showToast(err.message || 'Request failed.', false);
  });
});

function showToast(msg, success) {
  var toast = document.getElementById('emailToast');
  document.getElementById('emailToastMsg').textContent = msg;
  toast.className = 'toast align-items-center border-0 text-white ' + (success ? 'bg-success' : 'bg-danger');
  new bootstrap.Toast(toast, { delay: 5000 }).show();
}
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>