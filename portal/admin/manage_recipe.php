<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';

  requireAdmin();

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
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h1 class="h2 text-gradient mb-0">Manage Recipe</h1>
          <p class="text-muted mb-0"><?php echo htmlspecialchars($menu_item['name']); ?> - ₱<?php echo number_format($menu_item['price'], 2); ?></p>
        </div>
        <div>
          <a href="menu_management.php" class="btn btn-secondary">
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
        <div class="card-header bg-white">
          <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recipe Ingredients</h5>
        </div>
        <div class="card-body p-0">
          <?php if (empty($ingredients)): ?>
            <div class="text-center py-5">
              <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No ingredients added yet</h5>
              <p class="text-muted">Add ingredients to complete the recipe</p>
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
                            <button type="submit" class="btn btn-primary btn-sm">
                              <i class="fas fa-save"></i>
                            </button>
                          </div>
                        </form>
                      </td>
                      <td><?php echo htmlspecialchars($ing['unit']); ?></td>
                      <td>
                        <span class="badge <?php echo $ing['available_quantity'] < $ing['quantity_needed'] ? 'bg-brown' : 'bg-brown'; ?>" <?php echo $ing['available_quantity'] < $ing['quantity_needed'] ? 'style="opacity:0.75;"' : ''; ?>>
                          <?php echo $ing['available_quantity']; ?> <?php echo htmlspecialchars($ing['inventory_unit']); ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($ing['available_quantity'] >= $ing['quantity_needed']): ?>
                          <span class="badge bg-brown"><i class="fas fa-check me-1"></i>OK</span>
                        <?php else: ?>
                          <span class="badge bg-brown" style="opacity:0.75;"><i class="fas fa-times me-1"></i>Low</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <a href="?menu_id=<?php echo $menu_id; ?>&delete_ingredient=<?php echo $ing['id']; ?>"
                           class="btn btn-sm btn-danger"
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
          <h6 class="mb-3"><i class="fas fa-check-circle me-2"></i>Recipe Status</h6>
          <?php
            $can_make = canFulfillOrder($menu_id, 1);
            $max_servings = getMaxServings($menu_id);
          ?>
          <?php if ($can_make): ?>
            <div class="alert mb-0" style="background-color: #fff3e0; border-color: #ffcc80; color: #3b2008;">
              <i class="fas fa-check-circle me-2"></i>
              <strong>Ready to Sell!</strong> This item can be prepared.
              <br>
              <small>You can make approximately <strong><?php echo $max_servings; ?></strong> servings with current stock.</small>
            </div>
          <?php else: ?>
            <div class="alert mb-0" style="background-color: #fdf0e8; border-color: #c87533; color: #3b2008;">
              <i class="fas fa-exclamation-triangle me-2"></i>
              <strong>Cannot Sell!</strong> Insufficient ingredients. Please restock low items.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Add Ingredient Form -->
    <div class="col-lg-4">
      <div class="card sticky-top" style="top: 20px;">
        <div class="card-header text-white" style="background-color: #3b2008;">
          <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add Ingredient</h5>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="mb-3">
              <label class="form-label">Select Ingredient *</label>
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
            </div>

            <div class="mb-3">
              <label class="form-label">Quantity Needed *</label>
              <input type="number" name="quantity_needed" class="form-control" step="0.01" min="0.01" required placeholder="e.g., 200">
              <small class="text-muted">Enter quantity needed per serving</small>
            </div>

            <div class="alert" style="background-color: #fff3e0; border-color: #ffcc80; color: #3b2008;">
              <i class="fas fa-info-circle me-2"></i>
              <small>The unit will be automatically taken from the inventory item.</small>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-danger">
                <i class="fas fa-plus me-2"></i>Add to Recipe
              </button>
            </div>
          </form>
        </div>

        <div class="card-footer bg-light">
          <h6 class="mb-2"><i class="fas fa-lightbulb me-2"></i>Tips</h6>
          <ul class="small mb-0">
            <li>Add all required ingredients</li>
            <li>Use accurate measurements</li>
            <li>Update quantities as needed</li>
            <li>Check stock status regularly</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

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

<style>
.btn-danger {
  background-color: #3b2008;
  border-color: #3b2008;
}
.btn-danger:hover {
  background-color: #2a1505;
  border-color: #2a1505;
}
.btn-danger:active, .btn-danger:focus, .btn-danger:focus-visible {
  background-color: #2a1505 !important;
  border-color: #2a1505 !important;
  box-shadow: 0 0 0 0.25rem rgba(59, 32, 8, 0.4) !important;
}
.btn-primary {
  background-color: #3b2008;
  border-color: #3b2008;
}
.btn-primary:hover {
  background-color: #2a1505;
  border-color: #2a1505;
}
.btn-primary:active, .btn-primary:focus, .btn-primary:focus-visible {
  background-color: #2a1505 !important;
  border-color: #2a1505 !important;
  box-shadow: 0 0 0 0.25rem rgba(59, 32, 8, 0.4) !important;
}
.bg-brown {
  background-color: #3b2008 !important;
  color: #fff;
}

.form-select:focus,
.form-control:focus,
.form-check-input:focus {
  border-color: #3b2008 !important;
  box-shadow: 0 0 0 0.2rem rgba(59, 32, 8, 0.25) !important;
  outline: none !important;
}
.form-select { accent-color: #3b2008; }
option:checked, option:hover { background-color: #3b2008 !important; color: #fff !important; }
.dropdown-item:active, .dropdown-item.active { background-color: #3b2008 !important; color: #fff !important; }

</style>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
