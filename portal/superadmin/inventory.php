<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';

  requireSuperAdmin();

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
    $where_conditions[] = "quantity <= 10";
  }

  $where_sql = '';
  if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
  }

  if (isset($_GET['export'])) {
    $export_format = $_GET['export'];

    $sql = "SELECT item_name, description, unit, quantity, created_at, updated_at FROM inventory";
    if ($where_sql) $sql .= " $where_sql";
    $sql .= " ORDER BY item_name ASC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($export_format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=inventory_' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Item Name','Description','Unit','Quantity','Status','Created At','Updated At']);

        while ($row = $result->fetch_assoc()) {
            $status = $row['quantity'] < 10 ? 'Low Stock' : ($row['quantity'] < 50 ? 'Medium Stock' : 'Good Stock');
            fputcsv($output, [$row['item_name'],$row['description'],$row['unit'],$row['quantity'],$status,$row['created_at'],$row['updated_at']]);
        }
        fclose($output);
        exit;
    }

    if ($export_format === 'pdf') {
        require_once BASE_PATH . '/vendor/autoload.php';

        header("Content-Type: application/pdf");
        header("Content-Disposition: attachment; filename=inventory_" . date('Y-m-d') . ".pdf");
        header("Pragma: no-cache");
        header("Expires: 0");

        echo "<h2>Inventory Report</h2>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>Item Name</th><th>Description</th><th>Unit</th><th>Quantity</th><th>Status</th><th>Created At</th><th>Updated At</th></tr>";

        while ($row = $result->fetch_assoc()) {
            $status = $row['quantity'] < 10 ? 'Low Stock' : ($row['quantity'] < 50 ? 'Medium Stock' : 'Good Stock');
            echo "<tr>
                    <td>".htmlspecialchars($row['item_name'])."</td>
                    <td>".htmlspecialchars($row['description'])."</td>
                    <td>".htmlspecialchars($row['unit'])."</td>
                    <td>".htmlspecialchars($row['quantity'])."</td>
                    <td>".htmlspecialchars($status)."</td>
                    <td>".htmlspecialchars($row['created_at'])."</td>
                    <td>".htmlspecialchars($row['updated_at'])."</td>
                  </tr>";
        }
        echo "</table>";
        exit;
    }
  }

  $page_title = 'Inventory Management';
  $include_datatables = true;
  require_once BASE_PATH . '/includes/header.php';

  $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $per_page = 25;
  $offset = ($page - 1) * $per_page;

  $items = getInventoryItems($search, $filter, $per_page, $offset);
  $total_items = countInventoryItems($search, $filter);
  $total_pages = max(1, ceil($total_items / $per_page));

?>

<div class="container-fluid">
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #4a301f;">Inventory Management</h3>
          <p class="text-muted mb-0">Manage your inventory items and stock levels</p>
        </div>
        <div>
                    <button class="btn me-2" onclick="openEmailModal()" style="background:#c87533;border-color:#c87533;color:#fff;border:1px solid #c87533;">
            <i class="fas fa-envelope me-2"></i>Email Supplier
          </button>
<a href="add_item.php" class="btn btn-brown">
            <i class="fas fa-plus me-2"></i>Add New Item
          </a>
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
            <div class="col-md-4">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-search me-1"></i>Search Items
              </label>
              <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or description...">
            </div>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-filter me-1"></i>Filter by Stock
              </label>
              <select name="filter" class="form-select">
                <option value="">All Items</option>
                <option value="low_stock" <?php echo $filter === 'low_stock' ? 'selected' : ''; ?>>Low Stock (< 10)</option>
              </select>
            </div>
            <div class="col-md-3">
              <div class="btn-group w-100">
                <button type="submit" class="btn btn-brown">
                  <i class="fas fa-filter me-1"></i>Apply Filter
                </button>
                <a href="inventory.php" class="btn btn-outline-secondary">
                  <i class="fas fa-times me-1"></i>Clear
                </a>
              </div>
            </div>
            <div class="col-md-2">
              <div class="dropdown w-100">
                <button class="btn btn-outline-success dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">
                  <i class="fas fa-download me-1"></i>Export
                </button>
                <ul class="dropdown-menu">
                  <li>
                    <a class="dropdown-item" href="inventory.php?export=csv<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $filter ? '&filter=' . urlencode($filter) : ''; ?>">
                      <i class="fas fa-file-csv me-2"></i>CSV File
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item" href="inventory_print.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?><?php echo $filter ? '&filter=' . urlencode($filter) : ''; ?>" target="_blank">
                      <i class="fas fa-file-pdf me-2"></i>PDF File
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Inventory Table Card -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="fas fa-list me-2 icon-brown"></i>Inventory Items
          </h5>
          <span class="badge bg-brown">
            <?php echo number_format($total_items); ?> Total Items
          </span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($items)): ?>
            <div class="text-center py-5">
              <i class="fas fa-boxes fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No inventory items found</h5>
              <p class="text-muted mb-3">
                <?php if (!empty($search) || !empty($filter)): ?>
                  Try adjusting your search or filter criteria
                <?php else: ?>
                  Start by adding your first inventory item
                <?php endif; ?>
              </p>
              <?php if (empty($search) && empty($filter)): ?>
                <a href="add_item.php" class="btn btn-brown">
                  <i class="fas fa-plus me-2"></i>Add First Item
                </a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover mb-0" id="inventoryTable">
                <thead class="table-light">
                  <tr>
                    <th class="border-0">Item Name</th>
                    <th class="border-0">Description</th>
                    <th class="border-0">Unit</th>
                    <th class="border-0 text-center">Quantity</th>
                    <th class="border-0 text-center">Status</th>
                    <th class="border-0">Last Updated</th>
                    <th class="border-0 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $item): ?>
                  <tr>
                    <td class="fw-semibold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                    <td class="text-muted"><?php echo htmlspecialchars($item['description'] ?: 'No description'); ?></td>
                    <td><?php echo htmlspecialchars($item['unit']); ?></td>
                    <td class="text-center fw-bold"><?php echo formatNumber($item['quantity']); ?></td>
                    <td class="text-center">
                      <?php if ($item['quantity'] < 10): ?>
                        <span class="badge bg-danger">
                          <i class="fas fa-exclamation-triangle me-1"></i>Low Stock
                        </span>
                      <?php elseif ($item['quantity'] < 50): ?>
                        <span class="badge bg-warning text-dark">
                          <i class="fa-solid fa-circle-exclamation me-1"></i>Medium Stock
                        </span>
                      <?php else: ?>
                        <span class="badge bg-success">
                          <i class="fas fa-check-circle me-1"></i>Good Stock
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?php echo formatDate($item['updated_at'], 'M j, Y'); ?></td>
                    <td class="text-center">
                      <div class="btn-group btn-group-sm" role="group">
                        <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-outline-primary" title="Edit">
                          <i class="fas fa-edit"></i>
                        </a>
                        <a href="stock_in.php?item_id=<?php echo $item['id']; ?>" class="btn btn-outline-success" title="Stock In">
                          <i class="fas fa-plus"></i>
                        </a>
                        <a href="stock_out.php?item_id=<?php echo $item['id']; ?>" class="btn btn-outline-warning" title="Stock Out">
                          <i class="fas fa-minus"></i>
                        </a>
                        <a href="delete_item.php?id=<?php echo $item['id']; ?>" class="btn btn-outline-brown" title="Delete" onclick="return confirm('Are you sure you want to delete this item?')">
                          <i class="fas fa-trash"></i>
                        </a>
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
                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" title="First Page">
                      <i class="fas fa-angle-double-left"></i>
                    </a>
                  </li>
                  <?php endif; ?>

                  <!-- Previous Page -->
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" <?php echo $page <= 1 ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-left"></i>
                    </a>
                  </li>

                  <!-- Page Numbers -->
                  <?php
                  $start_page = max(1, $page - 2);
                  $end_page = min($total_pages, $page + 2);

                  // Show first page if we're not starting from page 1
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

                  // Show last page if we're not ending at the last page
                  if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&filter=' . urlencode($filter) . '">' . $total_pages . '</a></li>';
                  }
                  ?>

                  <!-- Next Page -->
                  <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" <?php echo $page >= $total_pages ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-right"></i>
                    </a>
                  </li>

                  <!-- Last Page -->
                  <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" title="Last Page">
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

.bg-brown {
  background-color: #4a301f;
  color: #fff;
}

.btn-brown {
  background-color: #4a301f;
  border-color: #4a301f;
  color: white;
}

.btn-brown:hover {
  background-color: #5d3d28;
  border-color: #5d3d28;
  color: white;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(74, 48, 31, 0.3);
}

.btn-outline-brown {
  color: #4a301f;
  border-color: #4a301f;
  background-color: transparent;
}

.btn-outline-brown:hover {
  background-color: #4a301f;
  border-color: #4a301f;
  color: white;
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  transition: transform 0.2s, box-shadow 0.2s;
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
}

.table tbody tr {
  transition: background-color 0.2s;
}

.table tbody tr:hover {
  background-color: #f8f9fa;
}

.btn-group-sm .btn {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

.pagination {
  --bs-pagination-active-bg: #4a301f;
  --bs-pagination-active-border-color: #4a301f;
  --bs-pagination-hover-color: #4a301f;
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
  color: #4a301f;
}

.pagination .page-item.active .page-link {
  background-color: #4a301f;
  border-color: #4a301f;
  color: white;
  font-weight: 600;
}

.pagination .page-item.disabled .page-link {
  color: #adb5bd;
  background-color: transparent;
  border-color: #dee2e6;
}

.badge {
  font-weight: 500;
  padding: 0.35rem 0.65rem;
}

.form-label {
  font-weight: 500;
  color: #495057;
}

@media (max-width: 768px) {
  .btn-group-sm {
    display: flex;
    flex-direction: column;
  }

  .btn-group-sm .btn {
    margin-bottom: 0.25rem;
    border-radius: 0.25rem !important;
  }

  .pagination {
    font-size: 0.875rem;
  }

  .table {
    font-size: 0.875rem;
  }
}
</style>

<script>
$(document).ready(function() {
    // DataTables initialization (if needed)
    if (typeof $.fn.DataTable !== 'undefined' && $('#inventoryTable').length) {
        $('#inventoryTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0,'asc']],
            columnDefs: [{ orderable: false, targets: [6] }],
            lengthChange: false,
            searching: false,
            info: false,
            paging: false
        });
    }

    // Auto-refresh after 5 minutes
    setTimeout(function() {
        if (!document.hidden) location.reload();
    }, 5 * 60 * 1000);
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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