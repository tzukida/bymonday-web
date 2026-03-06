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
          <h3 class="h3 mb-0" style="color: #3b2008;">Inventory Management</h3>
          <p class="text-muted mb-0">Manage your inventory items and stock levels</p>
        </div>
        <div>
          <a href="add_item.php" class="btn btn-danger">
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
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-filter me-1"></i>Apply Filter
                </button>
                <a href="inventory.php" class="btn btn-outline-secondary">
                  <i class="fas fa-times me-1"></i>Clear
                </a>
              </div>
            </div>
            <div class="col-md-2">
              <div class="dropdown w-100">
                <button class="btn btn-outline-secondary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" style="color: #6b3a1f; border-color: #6b3a1f;">
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
                <a href="add_item.php" class="btn btn-danger">
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
                        <a href="delete_item.php?id=<?php echo $item['id']; ?>" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this item?')">
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
.icon-brown {
  color: #3b2008;
}

.bg-brown {
  background-color: #3b2008;
  color: #fff;
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
  box-shadow: 0 0.5rem 1rem rgba(59, 32, 8, 0.15);
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
  --bs-pagination-active-bg: #3b2008;
  --bs-pagination-active-border-color: #3b2008;
  --bs-pagination-hover-color: #3b2008;
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
  color: #3b2008;
}

.pagination .page-item.active .page-link {
  background-color: #3b2008;
  border-color: #3b2008;
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

.btn-danger {
  background-color: #3b2008;
  border-color: #3b2008;
}

.btn-danger:hover {
  background-color: #2a1505;
  border-color: #2a1505;
}

.btn-primary {
  background-color: #3b2008;
  border-color: #3b2008;
}

.btn-primary:hover {
  background-color: #2a1505;
  border-color: #2a1505;
}

.btn-outline-primary:hover {
  background-color: #6b3a1f;
  border-color: #6b3a1f;
  color: #fff;
}

.btn-outline-success {
  color: #6b3a1f;
  border-color: #6b3a1f;
}

.btn-outline-success:hover {
  background-color: #6b3a1f;
  border-color: #6b3a1f;
  color: #fff;
}

.btn-outline-warning {
  color: #c87533;
  border-color: #c87533;
}

.btn-outline-warning:hover {
  background-color: #c87533;
  border-color: #c87533;
  color: #fff;
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
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
