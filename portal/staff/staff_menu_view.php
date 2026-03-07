<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';

  requireStaff();

  $page_title = 'Menu Items';
  require_once BASE_PATH . '/includes/header.php';

  $category_filter = $_GET['category'] ?? 'all';
  $status_filter = $_GET['status'] ?? 'all';
  $search = $_GET['search'] ?? '';

  $all_items = getAllMenuItems();

  $unavailable_count = 0;
  foreach ($all_items as $item) {
    if (!$item['is_available'] || !canFulfillOrder($item['id'], 1)) {
      $unavailable_count++;
    }
  }

  $menu_items = array_filter($all_items, function($item) use ($category_filter, $status_filter, $search) {
    if ($category_filter !== 'all' && $item['category'] !== $category_filter) return false;
    if ($status_filter === 'available') {
      if (!$item['is_available'] || !canFulfillOrder($item['id'], 1)) return false;
    }
    if ($status_filter === 'unavailable') {
      if ($item['is_available'] && canFulfillOrder($item['id'], 1)) return false;
    }
    if (!empty($search)) {
      $search_lower = strtolower($search);
      if (strpos(strtolower($item['name']), $search_lower) === false &&
          strpos(strtolower($item['description']), $search_lower) === false) return false;
    }
    return true;
  });

  $categories = array_unique(array_column($all_items, 'category'));
?>

<div class="container-fluid">
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #382417;">Menu Items</h3>
          <p class="text-muted mb-0">View available menu items and their recipes</p>
        </div>
        <div>
          <span class="badge bg-brown fs-6">
            <i class="fas fa-eye me-2"></i>Read-Only View
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Statistics Cards -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #3b2008 0%, #2a1505 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3"><i class="fas fa-utensils fa-2x opacity-75"></i></div>
          <h3 class="mb-1"><?php echo count($all_items); ?></h3>
          <p class="mb-0 opacity-75">Total Items</p>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #6b3a1f 0%, #3d1c02 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3"><i class="fas fa-check-circle fa-2x opacity-75"></i></div>
          <h3 class="mb-1"><?php echo count($all_items) - $unavailable_count; ?></h3>
          <p class="mb-0 opacity-75">Available</p>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #5a2d00 0%, #3d1c02 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3"><i class="fas fa-times-circle fa-2x opacity-75"></i></div>
          <h3 class="mb-1"><?php echo $unavailable_count; ?></h3>
          <p class="mb-0 opacity-75">Unavailable</p>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #c87533 0%, #a05a20 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3"><i class="fas fa-layer-group fa-2x opacity-75"></i></div>
          <h3 class="mb-1"><?php echo count($categories); ?></h3>
          <p class="mb-0 opacity-75">Categories</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Search & Filter Card -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-search me-1"></i>Search Items
              </label>
              <input type="text" class="form-control" name="search"
                     value="<?php echo htmlspecialchars($search); ?>"
                     placeholder="Search by name or description...">
            </div>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-layer-group me-1"></i>Category
              </label>
              <select name="category" class="form-select form-select-brown">
                <option value="all">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo htmlspecialchars($cat); ?>"
                          <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-filter me-1"></i>Status
              </label>
              <select name="status" class="form-select form-select-brown">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                <option value="unavailable" <?php echo $status_filter === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
              </select>
            </div>
            <div class="col-md-2">
              <div class="btn-group w-100">
                <button type="submit" class="btn btn-brown">
                  <i class="fas fa-filter me-1"></i>Apply
                </button>
                <a href="staff_menu_view.php" class="btn btn-outline-secondary">
                  <i class="fas fa-times"></i>
                </a>
              </div>
            </div>
          </form>

          <?php if (!empty($search) || $category_filter !== 'all' || $status_filter !== 'all'): ?>
          <div class="mt-3 pt-3 border-top">
            <small class="text-muted d-block mb-2"><i class="fas fa-filter me-1"></i>Active Filters:</small>
            <div class="d-flex flex-wrap gap-2">
              <?php if (!empty($search)): ?>
                <span class="badge bg-secondary">Search: <?php echo htmlspecialchars($search); ?></span>
              <?php endif; ?>
              <?php if ($category_filter !== 'all'): ?>
                <span class="badge bg-secondary">Category: <?php echo htmlspecialchars($category_filter); ?></span>
              <?php endif; ?>
              <?php if ($status_filter !== 'all'): ?>
                <span class="badge bg-secondary">Status: <?php echo ucfirst($status_filter); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Menu Items Grid -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="fas fa-list me-2 icon-brown"></i>Menu Items
          </h5>
          <span class="badge bg-brown"><?php echo count($menu_items); ?> Items</span>
        </div>
        <div class="card-body">
          <?php if (empty($menu_items)): ?>
            <div class="text-center py-5">
              <i class="fas fa-utensils fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No menu items found</h5>
              <?php if (!empty($search) || $category_filter !== 'all' || $status_filter !== 'all'): ?>
                <p class="text-muted mb-3">Try adjusting your filters</p>
                <a href="staff_menu_view.php" class="btn btn-outline-brown">
                  <i class="fas fa-times me-2"></i>Clear Filters
                </a>
              <?php else: ?>
                <p class="text-muted">No menu items available at the moment</p>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="row g-4">
              <?php foreach ($menu_items as $item): ?>
                <?php
                  $ingredients = getRecipeIngredients($item['id']);
                  $can_fulfill = canFulfillOrder($item['id'], 1);
                  $ingredient_count = count($ingredients);
                  $has_recipe = $ingredient_count > 0;
                ?>
                <div class="col-xl-4 col-lg-6 col-md-6">
                  <div class="card h-100 menu-item-card">
                    <div class="position-relative">
                      <?php if (!empty($item['image_url'])): ?>
                        <?php
                          $image_path = $item['image_url'];
                          if (!preg_match('/^https?:\/\//', $image_path)) {
                            $image_path = rtrim(BASE_URL, '/') . '/' . ltrim($image_path, '/');
                          }
                        ?>
                        <img src="<?php echo htmlspecialchars($image_path); ?>"
                             class="card-img-top menu-image"
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="menu-image-placeholder" style="display: none;">
                          <i class="fas fa-utensils fa-4x text-white"></i>
                        </div>
                      <?php else: ?>
                        <div class="menu-image-placeholder">
                          <i class="fas fa-utensils fa-4x text-white"></i>
                        </div>
                      <?php endif; ?>
                      <div class="position-absolute top-0 end-0 m-3">
                        <?php if (!$has_recipe): ?>
                          <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i>No Recipe</span>
                        <?php elseif (!$can_fulfill || !$item['is_available']): ?>
                          <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Unavailable</span>
                        <?php else: ?>
                          <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Available</span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="card-body d-flex flex-column">
                      <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                          <h5 class="card-title fw-bold mb-0" style="color: #382417;">
                            <?php echo htmlspecialchars($item['name']); ?>
                          </h5>
                          <span class="badge bg-brown"><?php echo htmlspecialchars($item['category']); ?></span>
                        </div>
                        <p class="card-text text-muted small mb-0 flex-grow-1">
                          <?php echo htmlspecialchars($item['description'] ?: 'No description available'); ?>
                        </p>
                      </div>
                      <div class="row g-3 mb-3">
                        <div class="col-6">
                          <div class="border rounded p-3 text-center h-100">
                            <small class="text-muted d-block mb-1">Price</small>
                            <h4 class="mb-0" style="color: #382417;">₱<?php echo number_format($item['price'], 2); ?></h4>
                          </div>
                        </div>
                        <div class="col-6">
                          <div class="border rounded p-3 text-center h-100">
                            <small class="text-muted d-block mb-1">Ingredients</small>
                            <h4 class="mb-0">
                              <?php if ($ingredient_count > 0): ?>
                                <span class="badge bg-brown fs-6"><?php echo $ingredient_count; ?></span>
                              <?php else: ?>
                                <span class="badge bg-warning text-dark fs-6">0</span>
                              <?php endif; ?>
                            </h4>
                          </div>
                        </div>
                      </div>
                      <button class="btn btn-brown w-100"
                              data-bs-toggle="modal"
                              data-bs-target="#ingredientsModal<?php echo $item['id']; ?>">
                        <i class="fas fa-book me-2"></i>View Recipe
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col-12">
      <div class="alert-brown-info">
        <div class="d-flex align-items-center">
          <i class="fas fa-info-circle fa-2x me-3" style="color: #3b2008;"></i>
          <div>
            <strong>Read-Only View</strong><br>
            <small>You can view menu items and their recipes but cannot modify them. Contact your administrator if any items need to be updated.</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modals for Ingredients -->
<?php foreach ($menu_items as $item): ?>
  <?php $ingredients = getRecipeIngredients($item['id']); ?>
  <div class="modal fade" id="ingredientsModal<?php echo $item['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header text-white" style="background: linear-gradient(135deg, #3b2008 0%, #2a1505 100%);">
          <h5 class="modal-title">
            <i class="fas fa-book me-2"></i>Recipe: <?php echo htmlspecialchars($item['name']); ?>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <div class="card border-0 bg-light">
                <div class="card-body text-center">
                  <small class="text-muted d-block mb-2"><i class="fas fa-tag me-1"></i>Category</small>
                  <span class="badge bg-brown fs-6"><?php echo htmlspecialchars($item['category']); ?></span>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card border-0 bg-light">
                <div class="card-body text-center">
                  <small class="text-muted d-block mb-2"><i class="fas fa-peso-sign me-1"></i>Price</small>
                  <h4 class="mb-0" style="color: #382417;">₱<?php echo number_format($item['price'], 2); ?></h4>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card border-0 bg-light">
                <div class="card-body text-center">
                  <small class="text-muted d-block mb-2"><i class="fas fa-list-ul me-1"></i>Ingredients</small>
                  <h4 class="mb-0">
                    <span class="badge bg-brown fs-6"><?php echo count($ingredients); ?></span>
                  </h4>
                </div>
              </div>
            </div>
          </div>

          <?php if (empty($ingredients)): ?>
            <div class="alert alert-warning border-0">
              <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                <div>
                  <strong>No Recipe Configured!</strong>
                  <p class="mb-0 mt-1 small">This menu item doesn't have any ingredients set up yet. Please contact your administrator to configure the recipe.</p>
                </div>
              </div>
            </div>
          <?php else: ?>
            <h6 class="mb-3">
              <i class="fas fa-list-ul me-2 icon-brown"></i>Required Ingredients
            </h6>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="border-0">Ingredient Name</th>
                    <th class="border-0 text-center">Required</th>
                    <th class="border-0 text-center">Available</th>
                    <th class="border-0 text-center">Can Make</th>
                    <th class="border-0 text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($ingredients as $ing): ?>
                    <?php
                      $can_make = floor($ing['available_quantity'] / $ing['quantity_needed']);
                      $is_sufficient = $ing['available_quantity'] >= $ing['quantity_needed'];
                    ?>
                    <tr class="<?php echo !$is_sufficient ? 'table-danger' : ''; ?>">
                      <td>
                        <div class="d-flex align-items-center">
                          <i class="fas fa-cube me-2 icon-brown"></i>
                          <strong><?php echo htmlspecialchars($ing['item_name']); ?></strong>
                        </div>
                      </td>
                      <td class="text-center">
                        <span class="badge bg-brown">
                          <?php echo number_format($ing['quantity_needed'], 2) . ' ' . $ing['unit']; ?>
                        </span>
                      </td>
                      <td class="text-center">
                        <span class="badge bg-<?php echo $is_sufficient ? 'brown' : 'danger'; ?>">
                          <?php echo number_format($ing['available_quantity'], 2) . ' ' . $ing['inventory_unit']; ?>
                        </span>
                      </td>
                      <td class="text-center">
                        <strong class="fs-5" style="color: <?php echo $can_make > 0 ? '#3b2008' : '#dc3545'; ?>">
                          <?php echo $can_make; ?>
                        </strong>
                      </td>
                      <td class="text-center">
                        <?php if ($is_sufficient): ?>
                          <span class="badge bg-brown"><i class="fas fa-check me-1"></i>OK</span>
                        <?php else: ?>
                          <span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Low</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <?php $can_fulfill = canFulfillOrder($item['id'], 1); ?>
            <div class="mt-4">
              <?php if (!$can_fulfill): ?>
                <div class="alert-recipe-danger">
                  <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                    <div>
                      <strong>Insufficient Stock!</strong>
                      <p class="mb-0 mt-1 small">Cannot fulfill orders for this item due to low ingredient levels.</p>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <div class="alert-recipe-success">
                  <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fa-2x me-3" style="color: #3b2008;"></i>
                    <div>
                      <strong>Ready to Serve!</strong>
                      <p class="mb-0 mt-1 small">All ingredients are in stock and ready.</p>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-2"></i>Close
          </button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<style>
body { background-color: #F3EDE8; }

.icon-brown { color: #382417; }

.bg-brown {
  background-color: #382417;
  color: #fff;
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
  transition: transform 0.2s, box-shadow 0.2s;
}

.menu-item-card {
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  border: 1px solid #e0e0e0;
}

.menu-item-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 0.5rem 1rem rgba(59,32,8,0.18) !important;
}

.menu-image {
  width: 100%;
  height: 220px;
  object-fit: cover;
  background-color: #f8f9fa;
}

.menu-image-placeholder {
  width: 100%;
  height: 220px;
  background: linear-gradient(135deg, #3b2008 0%, #2a1505 100%);
  display: flex;
  align-items: center;
  justify-content: center;
}

.form-select:focus,
.form-control:focus {
  border-color: #382417;
  box-shadow: 0 0 0 0.2rem rgba(56,36,23,0.25);
}

.form-select-brown { border-color: #ced4da; }
.form-select-brown:focus {
  border-color: #382417;
  box-shadow: 0 0 0 0.2rem rgba(56,36,23,0.25);
}

.table thead th {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.5px;
  color: #6c757d;
}

.table tbody tr { transition: background-color 0.2s; }
.table tbody tr:hover:not(.table-danger) { background-color: #f8f9fa; }

.badge { font-weight: 500; padding: 0.35rem 0.65rem; }

.btn-brown {
  background-color: #382417;
  border-color: #382417;
  color: #fff;
}
.btn-brown:hover,
.btn-brown:active,
.btn-brown:focus,
.btn-brown:focus-visible,
.btn-brown.active {
  background-color: #382417 !important;
  border-color: #382417 !important;
  color: #fff !important;
  box-shadow: none !important;
}

.btn-outline-brown {
  color: #382417;
  border-color: #382417;
  background-color: transparent;
}
.btn-outline-brown:hover,
.btn-outline-brown:active,
.btn-outline-brown:focus {
  background-color: #382417 !important;
  border-color: #382417 !important;
  color: #fff !important;
  box-shadow: none !important;
}

.modal-content {
  border: none;
  box-shadow: 0 1rem 3rem rgba(0,0,0,0.175);
}

.alert-brown-info {
  background-color: #f5ede6;
  border: 1px solid #c8a080;
  color: #3b2008;
  border-radius: 0.5rem;
  padding: 1rem 1.25rem;
}

/* Ready to Serve — brown tinted */
.alert-recipe-success {
  background-color: #f5ede6;
  border: 1px solid #c8a080;
  color: #3b2008;
  border-radius: 0.5rem;
  padding: 1rem 1.25rem;
}

/* Insufficient Stock */
.alert-recipe-danger {
  background-color: #fdecea;
  border: 1px solid #c0392b;
  color: #7b1c14;
  border-radius: 0.5rem;
  padding: 1rem 1.25rem;
}

@media (max-width: 768px) {
  .menu-image, .menu-image-placeholder { height: 180px; }
  .table { font-size: 0.875rem; }
}
</style>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
