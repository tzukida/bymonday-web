<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';

  requireAdmin();

  $page_title = 'Menu Management';
  require_once BASE_PATH . '/includes/header.php';

  // Get filter parameters
  $category_filter = $_GET['category'] ?? 'all';
  $status_filter = $_GET['status'] ?? 'all';
  $search = $_GET['search'] ?? '';

  // Pagination
  $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $per_page = 15;
  $offset = ($page - 1) * $per_page;

  // Get all menu items for stats
  $all_items = getAllMenuItems();

  // Calculate actual availability stats
  $total_available = 0;
  $total_unavailable = 0;

  foreach ($all_items as $item) {
    $ingredients = getRecipeIngredients($item['id']);
    $has_recipe = count($ingredients) > 0;
    $can_fulfill = canFulfillOrder($item['id'], 1);
    $is_available = $item['is_available'];

    // Check if truly available
    if ($is_available && $has_recipe && $can_fulfill) {
      $total_available++;
    } else {
      $total_unavailable++;
    }
  }

  // Filter menu items
  $filtered_items = array_filter($all_items, function($item) use ($category_filter, $status_filter, $search) {
    if ($category_filter !== 'all' && $item['category'] !== $category_filter) {
      return false;
    }

    if ($status_filter === 'available' || $status_filter === 'unavailable') {
      // Calculate actual availability
      $ingredients = getRecipeIngredients($item['id']);
      $has_recipe = count($ingredients) > 0;
      $can_fulfill = canFulfillOrder($item['id'], 1);
      $is_available = $item['is_available'];

      $actually_available = $is_available && $has_recipe && $can_fulfill;

      if ($status_filter === 'available' && !$actually_available) {
        return false;
      }
      if ($status_filter === 'unavailable' && $actually_available) {
        return false;
      }
    }

    if (!empty($search)) {
      $search_lower = strtolower($search);
      $name_match = strpos(strtolower($item['name']), $search_lower) !== false;
      $desc_match = strpos(strtolower($item['description']), $search_lower) !== false;
      if (!$name_match && !$desc_match) {
        return false;
      }
    }

    return true;
  });

  // Apply pagination
  $total_items = count($filtered_items);
  $total_pages = max(1, ceil($total_items / $per_page));
  $menu_items = array_slice($filtered_items, $offset, $per_page);

  // Get unique categories
  $categories = array_unique(array_column($all_items, 'category'));
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #3b2008;">Menu Management</h3>
          <p class="text-muted mb-0">Manage menu items and their recipes</p>
        </div>
        <div class="d-flex gap-2">
          <a href="pos.php" class="btn btn-outline-secondary">
            <i class="fas fa-cash-register me-2"></i>Go to POS
          </a>
          <a href="add_menu_item.php" class="btn btn-danger">
            <i class="fas fa-plus me-2"></i>Add Menu Item
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats Cards Row -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #3b2008 0%, #2a1505 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-utensils fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo count($all_items); ?></h3>
          <p class="mb-0 opacity-75">Total Items</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #6b3a1f 0%, #3d1c02 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-check-circle fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo $total_available; ?></h3>
          <p class="mb-0 opacity-75">Available</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #5a2d00 0%, #3d1c02 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-times-circle fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo $total_unavailable; ?></h3>
          <p class="mb-0 opacity-75">Unavailable</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #c87533 0%, #a05a20 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-layer-group fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo count($categories); ?></h3>
          <p class="mb-0 opacity-75">Categories</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Search & Filter -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-search me-1"></i>Search Menu Items
              </label>
              <input type="text"
                     class="form-control"
                     name="search"
                     value="<?php echo htmlspecialchars($search); ?>"
                     placeholder="Search by name or description...">
            </div>
            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-layer-group me-1"></i>Category
              </label>
              <select name="category" class="form-select">
                <option value="all">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo htmlspecialchars($cat); ?>"
                          <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-toggle-on me-1"></i>Status
              </label>
              <select name="status" class="form-select">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                <option value="unavailable" <?php echo $status_filter === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
              </select>
            </div>
            <div class="col-md-4">
              <div class="btn-group w-100">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-filter me-1"></i>Apply Filter
                </button>
                <a href="menu_management.php" class="btn btn-outline-secondary">
                  <i class="fas fa-times me-1"></i>Clear
                </a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Menu Items Table -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="fas fa-list me-2 icon-brown"></i>Menu Items
          </h5>
          <span class="badge bg-brown">
            <?php echo number_format($total_items); ?> Total Items
          </span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($menu_items)): ?>
            <div class="text-center py-5">
              <i class="fas fa-utensils fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No menu items found</h5>
              <?php if (!empty($search) || $category_filter !== 'all' || $status_filter !== 'all'): ?>
                <p class="text-muted">Try adjusting your filters</p>
                <a href="menu_management.php" class="btn btn-outline-secondary">Clear Filters</a>
              <?php else: ?>
                <p class="text-muted">Add menu items to start selling</p>
                <a href="add_menu_item.php" class="btn btn-danger">
                  <i class="fas fa-plus me-2"></i>Add First Menu Item
                </a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="border-0 text-center" style="width: 70px;">ID</th>
                    <th class="border-0" style="width: 35%;">Item Details</th>
                    <th class="border-0 text-center">Category</th>
                    <th class="border-0 text-center">Price</th>
                    <th class="border-0 text-center">Status</th>
                    <th class="border-0 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($menu_items as $item): ?>
                    <?php
                      $ingredients = getRecipeIngredients($item['id']);
                      $can_fulfill = canFulfillOrder($item['id'], 1);
                      $ingredient_count = count($ingredients);
                    ?>
                    <tr>
                      <td class="text-center align-middle">
                        <strong class="text-gray">#<?php echo str_pad($item['id'], 3, '0', STR_PAD_LEFT); ?></strong>
                      </td>
                      <td class="align-middle">
                        <div class="d-flex align-items-center">
                          <div class="me-3">
                            <?php if (!empty($item['image_url'])): ?>
                              <?php
                                $image_path = $item['image_url'];
                                if (!preg_match('/^https?:\/\//', $image_path)) {
                                  $image_path = rtrim(BASE_URL, '/') . '/' . ltrim($image_path, '/');
                                }
                              ?>
                              <img src="<?php echo htmlspecialchars($image_path); ?>"
                                   class="rounded"
                                   style="width: 60px; height: 60px; object-fit: cover;"
                                   alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php else: ?>
                              <div class="rounded d-flex align-items-center justify-content-center"
                                   style="width: 60px; height: 60px; background: linear-gradient(135deg, #3b2008 0%, #2a1505 100%);">
                                <i class="fas fa-utensils text-white"></i>
                              </div>
                            <?php endif; ?>
                          </div>
                          <div>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['name']); ?></div>
                            <small class="text-muted d-block" style="font-size: 0.85rem;">
                              <?php echo htmlspecialchars(strlen($item['description']) > 60 ? substr($item['description'], 0, 60) . '...' : $item['description']); ?>
                            </small>
                          </div>
                        </div>
                      </td>
                      <td class="text-center align-middle">
                        <span class="badge bg-brown">
                          <?php echo htmlspecialchars($item['category']); ?>
                        </span>
                      </td>
                      <td class="text-center align-middle">
                        <span class="fw-bold text-brown">₱<?php echo number_format($item['price'], 2); ?></span>
                      </td>
                      <td class="text-center align-middle">
                        <?php
                          $has_recipe = $ingredient_count > 0;
                          $has_stock = $can_fulfill;
                          $is_available = $item['is_available'];

                          if (!$is_available) {
                            echo '<span class="badge bg-secondary"><i class="fas fa-ban me-1"></i>Unavailable</span>';
                          } elseif (!$has_recipe) {
                            echo '<span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i>No Recipe</span>';
                          } elseif (!$has_stock) {
                            echo '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Unavailable</span>';
                          } else {
                            echo '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Available</span>';
                          }
                        ?>
                      </td>
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="edit_menu_item.php?id=<?php echo $item['id']; ?>"
                             class="btn btn-outline-brown"
                             title="Edit Item">
                            <i class="fas fa-edit"></i>
                          </a>
                          <a href="manage_recipe.php?menu_id=<?php echo $item['id']; ?>"
                             class="btn btn-outline-brown"
                             title="Manage Recipe">
                            <i class="fas fa-book"></i>
                          </a>
                          <?php if ($ingredient_count > 0): ?>
                          <button class="btn btn-outline-brown"
                                  data-bs-toggle="modal"
                                  data-bs-target="#ingredientsModal<?php echo $item['id']; ?>"
                                  title="View Recipe">
                            <i class="fas fa-list"></i>
                          </button>
                          <?php endif; ?>
                          <a href="delete_menu_item.php?id=<?php echo $item['id']; ?>"
                             class="btn btn-outline-brown"
                             title="Delete Item"
                             onclick="return confirm('Are you sure you want to delete this menu item?');">
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
        <?php if ($total_pages > 1 && !empty($menu_items)): ?>
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
                  <?php if ($page > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                      <i class="fas fa-angle-double-left"></i>
                    </a>
                  </li>
                  <?php endif; ?>

                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                      <i class="fas fa-angle-left"></i>
                    </a>
                  </li>

                  <?php
                  $start_page = max(1, $page - 2);
                  $end_page = min($total_pages, $page + 2);

                  if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&category=' . urlencode($category_filter) . '&status=' . urlencode($status_filter) . '">1</a></li>';
                    if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                  }

                  for ($i = $start_page; $i <= $end_page; $i++):
                  ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                      <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                        <?php echo $i; ?>
                      </a>
                    </li>
                  <?php endfor;

                  if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&category=' . urlencode($category_filter) . '&status=' . urlencode($status_filter) . '">' . $total_pages . '</a></li>';
                  }
                  ?>

                  <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                      <i class="fas fa-angle-right"></i>
                    </a>
                  </li>

                  <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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

<!-- Modals for Ingredients -->
<?php foreach ($menu_items as $item): ?>
  <?php $ingredients = getRecipeIngredients($item['id']); ?>
  <?php if (!empty($ingredients)): ?>
  <div class="modal fade" id="ingredientsModal<?php echo $item['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header text-white" style="background: linear-gradient(135deg, #3b2008 0%, #2a1505 100%);">
          <h5 class="modal-title">
            <i class="fas fa-list me-2"></i>
            Recipe for <?php echo htmlspecialchars($item['name']); ?>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Ingredient</th>
                  <th class="text-center">Required</th>
                  <th class="text-center">Available</th>
                  <th class="text-center">Can Make</th>
                  <th class="text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ingredients as $ing): ?>
                  <?php
                    $can_make = floor($ing['available_quantity'] / $ing['quantity_needed']);
                    $is_sufficient = $ing['available_quantity'] >= $ing['quantity_needed'];
                  ?>
                  <tr class="<?php echo !$is_sufficient ? 'table-danger' : ''; ?>">
                    <td class="align-middle">
                      <strong><?php echo htmlspecialchars($ing['item_name']); ?></strong>
                    </td>
                    <td class="text-center align-middle">
                      <span class="badge bg-primary">
                        <?php echo $ing['quantity_needed'] . ' ' . $ing['unit']; ?>
                      </span>
                    </td>
                    <td class="text-center align-middle">
                      <span class="badge bg-<?php echo $is_sufficient ? 'success' : 'danger'; ?>">
                        <?php echo $ing['available_quantity'] . ' ' . $ing['inventory_unit']; ?>
                      </span>
                    </td>
                    <td class="text-center align-middle">
                      <strong class="text-<?php echo $can_make > 0 ? 'success' : 'danger'; ?>">
                        <?php echo $can_make; ?> servings
                      </strong>
                    </td>
                    <td class="text-center align-middle">
                      <?php if ($is_sufficient): ?>
                        <span class="badge bg-success">
                          <i class="fas fa-check me-1"></i>OK
                        </span>
                      <?php else: ?>
                        <span class="badge bg-danger">
                          <i class="fas fa-exclamation-triangle me-1"></i>Low
                        </span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php $can_fulfill = canFulfillOrder($item['id'], 1); ?>
          <div class="mt-3">
            <?php if (!$can_fulfill): ?>
              <div class="alert alert-danger mb-0">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Insufficient Stock!</strong> Cannot fulfill orders due to low ingredient levels.
              </div>
            <?php else: ?>
              <div class="alert alert-success mb-0">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Ready to Serve!</strong> All ingredients are in stock.
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="modal-footer">
          <a href="manage_recipe.php?menu_id=<?php echo $item['id']; ?>" class="btn btn-primary">
            <i class="fas fa-edit me-2"></i>Edit Recipe
          </a>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
<?php endforeach; ?>

<style>
.icon-brown {
  color: #3b2008;
}

.bg-brown {
  background-color: #3b2008;
  color: #fff;
}

.text-brown {
  color: #3b2008;
}

.btn-outline-brown {
  color: #3b2008;
  border-color: #3b2008;
  background-color: transparent;
}

.btn-outline-brown:hover,
.btn-outline-brown:active,
.btn-outline-brown:focus {
  background-color: #3b2008;
  border-color: #3b2008;
  color: #fff;
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

.btn-danger, .btn-primary {
  background-color: #3b2008;
  border-color: #3b2008;
}

.btn-danger:hover, .btn-primary:hover {
  background-color: #2a1505;
  border-color: #2a1505;
}

.modal-content {
  border: none;
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
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

  .table {
    font-size: 0.875rem;
  }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
