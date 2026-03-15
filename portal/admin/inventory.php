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
        logActivity($_SESSION['user_id'], 'Export Inventory', 'Exported inventory to CSV: inventory_' . date('Y-m-d') . '.csv');
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
        logActivity($_SESSION['user_id'], 'Export Inventory', 'Exported inventory to PDF: inventory_' . date('Y-m-d') . '.pdf');
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
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-email-supplier" onclick="openEmailModal()">
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
                <button class="btn btn-outline-brown dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">
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
                        <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-outline-brown" title="Edit">
                          <i class="fas fa-edit"></i>
                        </a>
                        <a href="stock_in.php?item_id=<?php echo $item['id']; ?>" class="btn btn-outline-brown btn-sm" title="Stock In">
                          <i class="fas fa-plus"></i>
                        </a>
                        <a href="stock_out.php?item_id=<?php echo $item['id']; ?>" class="btn btn-outline-brown" title="Stock Out">
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

.btn-outline-brown:hover,
.btn-outline-brown:active,
.btn-outline-brown:focus {
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

.form-select:focus,
.form-control:focus {
  border-color: #4a301f !important;
  box-shadow: 0 0 0 0.2rem rgba(74, 48, 31, 0.25) !important;
  outline: none !important;
}

.form-select option:checked {
  background-color: #4a301f !important;
  color: #fff !important;
}

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