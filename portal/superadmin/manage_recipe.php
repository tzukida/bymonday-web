<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';

  requireSuperAdmin();

  $menu_id = isset($_GET['menu_id']) ? intval($_GET['menu_id']) : 0;
  $menu_item = getMenuItemById($menu_id);

  if (!$menu_item) {
    $_SESSION['error_message'] = 'Menu item not found';
    header('Location: menu_management.php');
    exit;
  }

  // Handle add ingredient
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $inventory_item_id = intval($_POST['inventory_item_id']);
    $quantity_needed = floatval($_POST['quantity_needed']);

    $conn = getDBConnection();

    // Check if ingredient already exists
    $check_stmt = $conn->prepare("SELECT id FROM recipe_ingredients WHERE menu_item_id = ? AND inventory_item_id = ?");
    $check_stmt->bind_param("ii", $menu_id, $inventory_item_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($exists) {
      $_SESSION['error_message'] = 'This ingredient is already in the recipe';
    } else {
      // Get unit from inventory
      $unit_stmt = $conn->prepare("SELECT unit FROM inventory WHERE id = ?");
      $unit_stmt->bind_param("i", $inventory_item_id);
      $unit_stmt->execute();
      $unit_result = $unit_stmt->get_result()->fetch_assoc();
      $unit = $unit_result['unit'];
      $unit_stmt->close();

      $stmt = $conn->prepare("INSERT INTO recipe_ingredients (menu_item_id, inventory_item_id, quantity_needed, unit) VALUES (?, ?, ?, ?)");
      $stmt->bind_param("iids", $menu_id, $inventory_item_id, $quantity_needed, $unit);

      if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Ingredient added to recipe successfully';
        logActivity($_SESSION['user_id'], 'Add Recipe Ingredient', "Added ingredient to {$menu_item['name']}");
      } else {
        $_SESSION['error_message'] = 'Failed to add ingredient';
      }
      $stmt->close();
    }

    header('Location: manage_recipe.php?menu_id=' . $menu_id);
    exit;
  }

  // Handle delete ingredient
  if (isset($_GET['delete_ingredient'])) {
    $ingredient_id = intval($_GET['delete_ingredient']);

    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM recipe_ingredients WHERE id = ? AND menu_item_id = ?");
    $stmt->bind_param("ii", $ingredient_id, $menu_id);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Ingredient removed from recipe';
      logActivity($_SESSION['user_id'], 'Remove Recipe Ingredient', "Removed ingredient from {$menu_item['name']}");
    } else {
      $_SESSION['error_message'] = 'Failed to remove ingredient';
    }
    $stmt->close();

    header('Location: manage_recipe.php?menu_id=' . $menu_id);
    exit;
  }

  // Handle update quantity
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $ingredient_id = intval($_POST['ingredient_id']);
    $quantity_needed = floatval($_POST['quantity_needed']);

    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE recipe_ingredients SET quantity_needed = ? WHERE id = ? AND menu_item_id = ?");
    $stmt->bind_param("dii", $quantity_needed, $ingredient_id, $menu_id);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Quantity updated successfully';
      logActivity($_SESSION['user_id'], 'Update Recipe Quantity', "Updated ingredient quantity for {$menu_item['name']}");
    } else {
      $_SESSION['error_message'] = 'Failed to update quantity';
    }
    $stmt->close();

    header('Location: manage_recipe.php?menu_id=' . $menu_id);
    exit;
  }

  $page_title = 'Manage Recipe - ' . $menu_item['name'];
  require_once BASE_PATH . '/includes/header.php';

  $ingredients = getRecipeIngredients($menu_id);
  $all_inventory = getAllInventoryForDropdown();
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #4a301f;">
            <i class="fas fa-book me-2"></i>Manage Recipe
          </h3>
          <p class="text-muted mb-0">
            <strong class="text-brown"><?php echo htmlspecialchars($menu_item['name']); ?></strong>
            <span class="mx-2">•</span>
            <span class="badge bg-brown">₱<?php echo number_format($menu_item['price'], 2); ?></span>
          </p>
        </div>
        <div class="d-flex gap-2">
          <a href="edit_menu_item.php?id=<?php echo $menu_id; ?>" class="btn btn-outline-brown">
            <i class="fas fa-edit me-2"></i>Edit Menu Item
          </a>
          <a href="menu_management.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Menu
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Current Recipe -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-white py-3">
          <h5 class="mb-0">
            <i class="fas fa-list me-2 icon-brown"></i>Recipe Ingredients
          </h5>
        </div>
        <div class="card-body p-0">
          <?php if (empty($ingredients)): ?>
            <div class="text-center py-5">
              <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No ingredients added yet</h5>
              <p class="text-muted mb-3">Add ingredients to complete the recipe for this menu item</p>
              <div class="d-flex justify-content-center gap-2">
                <i class="fas fa-arrow-right text-brown"></i>
                <span class="text-muted">Use the form on the right to add ingredients</span>
              </div>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Ingredient</th>
                    <th>Quantity Needed</th>
                    <th>Unit</th>
                    <th>Available Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($ingredients as $ing): ?>
                    <tr>
                      <td class="fw-semibold"><?php echo htmlspecialchars($ing['item_name']); ?></td>
                      <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Update quantity?')">
                          <input type="hidden" name="action" value="update">
                          <input type="hidden" name="ingredient_id" value="<?php echo $ing['id']; ?>">
                          <div class="input-group input-group-sm" style="max-width: 150px;">
                            <input type="number" name="quantity_needed" class="form-control" value="<?php echo $ing['quantity_needed']; ?>" step="0.01" required>
                            <button type="submit" class="btn btn-brown btn-sm">
                              <i class="fas fa-save"></i>
                            </button>
                          </div>
                        </form>
                      </td>
                      <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($ing['unit']); ?></span></td>
                      <td>
                        <span class="badge <?php echo $ing['available_quantity'] < $ing['quantity_needed'] ? 'bg-brown" style="opacity:0.6;' : 'bg-brown'; ?>">
                          <?php echo $ing['available_quantity']; ?> <?php echo htmlspecialchars($ing['inventory_unit']); ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($ing['available_quantity'] >= $ing['quantity_needed']): ?>
                          <span class="badge bg-brown"><i class="fas fa-check me-1"></i>OK</span>
                        <?php else: ?>
                          <span class="badge bg-brown" style="opacity:0.6;"><i class="fas fa-exclamation-triangle me-1"></i>Low</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <a href="?menu_id=<?php echo $menu_id; ?>&delete_ingredient=<?php echo $ing['id']; ?>"
                           class="btn btn-sm btn-outline-brown"
                           onclick="return confirm('Remove this ingredient from the recipe?')">
                          <i class="fas fa-trash"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Stock Check Result -->
      <div class="card mt-3">
        <div class="card-body">
          <h6 class="mb-3">
            <i class="fas fa-check-circle me-2 icon-brown"></i>Recipe Status & Stock Analysis
          </h6>
          <?php
            $can_make = canFulfillOrder($menu_id, 1);
            $max_servings = getMaxServings($menu_id);
          ?>
          <?php if (empty($ingredients)): ?>
            <div class="alert alert-warning mb-0">
              <i class="fas fa-exclamation-circle me-2"></i>
              <strong>Recipe Incomplete!</strong> Please add ingredients to complete this recipe.
            </div>
          <?php elseif ($can_make): ?>
            <div class="alert mb-0" style="background-color: #fff3e0; border: 1px solid #c87533; color: #3b2008;">
              <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                  <i class="fas fa-check-circle fa-2x icon-brown"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                  <h6 class="alert-heading mb-1">Ready to Sell!</h6>
                  <p class="mb-0">This item can be prepared with current inventory.</p>
                  <hr class="my-2">
                  <p class="mb-0 small">
                    <i class="fas fa-chart-line me-1"></i>
                    <strong>Maximum servings:</strong> Approximately <strong class="text-brown"><?php echo $max_servings; ?></strong> servings can be made with current stock.
                  </p>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="alert alert-danger mb-0">
              <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                  <i class="fas fa-exclamation-triangle fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                  <h6 class="alert-heading mb-1">Cannot Sell!</h6>
                  <p class="mb-0">Insufficient ingredients. Please restock low items before offering this item.</p>
                  <hr class="my-2">
                  <p class="mb-0 small">
                    <i class="fas fa-info-circle me-1"></i>
                    Check the "Low" status items above and restock them.
                  </p>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recipe Summary Card -->
      <?php if (!empty($ingredients)): ?>
      <div class="card mt-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-chart-pie me-2 icon-brown"></i>Recipe Summary
          </h6>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="text-center p-3 border rounded bg-light">
                <i class="fas fa-list-ol fa-2x text-brown mb-2"></i>
                <h4 class="mb-0 text-brown"><?php echo count($ingredients); ?></h4>
                <small class="text-muted">Total Ingredients</small>
              </div>
            </div>
            <div class="col-md-4">
              <div class="text-center p-3 border rounded bg-light">
                <i class="fas fa-sort-numeric-up fa-2x text-brown mb-2"></i>
                <h4 class="mb-0 text-brown"><?php echo $max_servings; ?></h4>
                <small class="text-muted">Max Servings</small>
              </div>
            </div>
            <div class="col-md-4">
              <div class="text-center p-3 border rounded bg-light">
                <?php
                  $low_stock_count = 0;
                  foreach ($ingredients as $ing) {
                    if ($ing['available_quantity'] < $ing['quantity_needed']) {
                      $low_stock_count++;
                    }
                  }
                ?>
                <i class="fas fa-exclamation-circle fa-2x icon-brown mb-2"></i>
                <h4 class="mb-0 text-brown"><?php echo $low_stock_count; ?></h4>
                <small class="text-muted">Low Stock Items</small>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Add Ingredient Form -->
    <div class="col-lg-4">
      <div class="card sticky-top" style="top: 20px;">
        <div class="card-header bg-brown text-white py-3">
          <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add Ingredient</h5>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="mb-3">
              <label class="form-label">
                <i class="fas fa-box me-1"></i>Select Ingredient <span class="text-brown">*</span>
              </label>
              <select name="inventory_item_id" class="form-select" required>
                <option value="">Choose ingredient...</option>
                <?php foreach ($all_inventory as $item): ?>
                  <?php
                    // Check if already in recipe
                    $already_added = false;
                    foreach ($ingredients as $ing) {
                      if ($ing['inventory_item_id'] == $item['id']) {
                        $already_added = true;
                        break;
                      }
                    }
                  ?>
                  <?php if (!$already_added): ?>
                    <option value="<?php echo $item['id']; ?>">
                      <?php echo htmlspecialchars($item['item_name']); ?>
                      (<?php echo $item['quantity']; ?> <?php echo $item['unit']; ?> available)
                    </option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Only items not yet in the recipe are shown</small>
            </div>

            <div class="mb-3">
              <label class="form-label">
                <i class="fas fa-balance-scale me-1"></i>Quantity Needed <span class="text-brown">*</span>
              </label>
              <input type="number" name="quantity_needed" class="form-control" step="0.01" min="0.01" required placeholder="e.g., 200">
              <small class="text-muted">Enter quantity needed per serving</small>
            </div>

            <div class="alert alert-info-brown">
              <i class="fas fa-info-circle me-2"></i>
              <small>The unit will be automatically taken from the inventory item.</small>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-brown">
                <i class="fas fa-plus me-2"></i>Add to Recipe
              </button>
            </div>
          </form>
        </div>

        <div class="card-footer bg-light">
          <h6 class="mb-2">
            <i class="fas fa-lightbulb me-2 icon-brown"></i>Tips
          </h6>
          <ul class="small mb-0 ps-3">
            <li class="mb-1">Add all required ingredients</li>
            <li class="mb-1">Use accurate measurements</li>
            <li class="mb-1">Update quantities as needed</li>
            <li class="mb-0">Check stock status regularly</li>
          </ul>
        </div>
      </div>

      <!-- Quick Actions Card -->
      <div class="card mt-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-bolt me-2 icon-brown"></i>Quick Actions
          </h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="edit_menu_item.php?id=<?php echo $menu_id; ?>" class="btn btn-outline-brown btn-sm">
              <i class="fas fa-edit me-2"></i>Edit Menu Item Details
            </a>
            <a href="inventory.php" class="btn btn-outline-brown btn-sm">
              <i class="fas fa-warehouse me-2"></i>Manage Inventory
            </a>
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

.icon-brown {
  color: #4a301f;
}

.text-brown {
  color: #4a301f !important;
}

.bg-brown {
  background-color: #382417 !important;
  color: white !important;
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

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
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

.alert-info-brown {
  background-color: #fff3e0;
  border: 1px solid #ffcc80;
  color: #4a301f;
  border-radius: 0.375rem;
}

.alert-success-brown {
  background-color: #f0f9f4;
  border: 1px solid #86efac;
  color: #166534;
  border-radius: 0.375rem;
}

.table > :not(caption) > * > * {
  padding: 0.75rem;
}

.table-hover > tbody > tr:hover > * {
  background-color: #fff3e0;
}

.badge {
  font-size: 0.8rem;
  padding: 0.4rem 0.6rem;
}

.alert-heading {
  font-weight: 600;
}
</style>

<?php
function getAllInventoryForDropdown() {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, item_name, quantity, unit FROM inventory ORDER BY item_name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $items;
}

function getMaxServings($menu_item_id) {
    $ingredients = getRecipeIngredients($menu_item_id);

    if (empty($ingredients)) {
        return 0;
    }

    $max_servings = PHP_INT_MAX;

    foreach ($ingredients as $ing) {
        if ($ing['quantity_needed'] > 0) {
            $possible = floor($ing['available_quantity'] / $ing['quantity_needed']);
            $max_servings = min($max_servings, $possible);
        }
    }

    return $max_servings == PHP_INT_MAX ? 0 : $max_servings;
}
?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
