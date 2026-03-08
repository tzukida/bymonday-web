<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireAdmin();

  $item_id = $_GET['id'] ?? null;
  if (!$item_id || !is_numeric($item_id)) {
    $_SESSION['error_message'] = 'Invalid item ID.';
    redirect('admin/inventory.php');
  }

  $conn = getDBConnection();

  $stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
  $stmt->bind_param("i", $item_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'Item not found.';
    redirect('admin/inventory.php');
  }

  $item = $result->fetch_assoc();
  $stmt->close();

  // Check if item is used in any recipes
  $stmt = $conn->prepare("
    SELECT COUNT(*) as count,
           GROUP_CONCAT(DISTINCT m.name SEPARATOR ', ') as menu_items
    FROM recipe_ingredients ri
    JOIN menu_items m ON ri.menu_item_id = m.id
    WHERE ri.inventory_item_id = ?
  ");
  $stmt->bind_param("i", $item_id);
  $stmt->execute();
  $recipe_check = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $is_used_in_recipes = $recipe_check['count'] > 0;
  $used_in_menu_items = $recipe_check['menu_items'];

  // Get transaction count
  $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transactions WHERE item_id = ?");
  $stmt->bind_param("i", $item_id);
  $stmt->execute();
  $transaction_count = $stmt->get_result()->fetch_assoc()['count'];
  $stmt->close();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $_SESSION['error_message'] = 'Security token mismatch. Please try again.';
        redirect('admin/inventory.php');
    }

    $confirm = $_POST['confirm_delete'] ?? '';
    if ($confirm !== 'DELETE') {
        $_SESSION['error_message'] = 'Please type DELETE to confirm deletion.';
    } else {
        try {
            // Start transaction
            $conn->begin_transaction();

            // If item has quantity, record final stock-out
            if ($item['quantity'] > 0) {
                addTransaction($_SESSION['user_id'], $item_id, 'stock-out', $item['quantity'], 'Item deleted from inventory');
            }

            // Delete from recipe ingredients if exists
            if ($is_used_in_recipes) {
                $stmt = $conn->prepare("DELETE FROM recipe_ingredients WHERE inventory_item_id = ?");
                $stmt->bind_param("i", $item_id);
                $stmt->execute();
                $stmt->close();
            }

            // Delete the item
            $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $stmt->close();

            // Commit transaction
            $conn->commit();

            logActivity($_SESSION['user_id'], 'Delete Inventory Item', "Deleted item: {$item['item_name']} (ID: $item_id)");
            $_SESSION['success_message'] = 'Item "' . htmlspecialchars($item['item_name']) . '" has been successfully deleted.';
            redirect('admin/inventory.php?success=item_deleted');
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Delete item error: " . $e->getMessage());
            $_SESSION['error_message'] = 'Failed to delete item. Please try again.';
        }
    }
  }

  $page_title = 'Delete Item';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0 text-danger">
            <i class="fas fa-trash-alt me-2"></i>Delete Inventory Item
          </h3>
          <p class="text-muted mb-0">Permanently remove item from inventory</p>
        </div>
        <div>
          <a href="inventory.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Inventory
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-8">
      <!-- Warning Alert -->
      <div class="alert alert-danger border-danger">
        <div class="d-flex align-items-start">
          <div class="me-3 mt-1">
            <i class="fas fa-exclamation-triangle fa-2x"></i>
          </div>
          <div class="flex-grow-1">
            <h5 class="alert-heading mb-2">
              <i class="fas fa-exclamation-circle me-2"></i>Warning: Permanent Action
            </h5>
            <p class="mb-2">You are about to permanently delete this inventory item. This action <strong>cannot be undone</strong>.</p>
            <hr>
            <p class="mb-0 small">
              <i class="fas fa-info-circle me-1"></i>
              All transaction history for this item will be preserved, but the item itself will be removed from your inventory.
            </p>
          </div>
        </div>
      </div>

      <!-- Item Details Card -->
      <div class="card mb-4">
        <div class="card-header bg-white py-3">
          <h5 class="mb-0">
            <i class="fas fa-box me-2 icon-brown"></i>Item to be Deleted
          </h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="text-muted small mb-1">Item Name</label>
              <div class="fw-bold fs-5"><?php echo htmlspecialchars($item['item_name']); ?></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="text-muted small mb-1">Current Stock</label>
              <div class="fw-bold fs-5" style="color: #3b2008;">
                <?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
              </div>
            </div>
            <?php if (!empty($item['description'])): ?>
            <div class="col-12 mb-3">
              <label class="text-muted small mb-1">Description</label>
              <div><?php echo htmlspecialchars($item['description']); ?></div>
            </div>
            <?php endif; ?>
            <div class="col-md-6 mb-3">
              <label class="text-muted small mb-1">Created Date</label>
              <div><?php echo formatDate($item['created_at'], 'F j, Y g:i A'); ?></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="text-muted small mb-1">Last Updated</label>
              <div><?php echo formatDate($item['updated_at'], 'F j, Y g:i A'); ?></div>
            </div>
          </div>

          <hr>

          <!-- Impact Summary -->
          <div class="row">
            <div class="col-md-4">
              <div class="text-center p-3 border rounded">
                <div class="mb-2">
                  <i class="fas fa-history fa-2x " style="color:#3b2008;"></i>
                </div>
                <div class="fw-bold fs-5"><?php echo number_format($transaction_count); ?></div>
                <div class="small text-muted">Transactions</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="text-center p-3 border rounded">
                <div class="mb-2">
                  <i class="fas fa-utensils fa-2x <?php echo $is_used_in_recipes ? '' : ''; ?>" style="color:#3b2008;" style="color:#3b2008;"></i>
                </div>
                <div class="fw-bold fs-5"><?php echo $recipe_check['count']; ?></div>
                <div class="small text-muted">Menu Items</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="text-center p-3 border rounded">
                <div class="mb-2">
                  <i class="fas fa-box fa-2x text-secondary"></i>
                </div>
                <div class="fw-bold fs-5"><?php echo number_format($item['quantity'], 2); ?></div>
                <div class="small text-muted">Stock Value</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recipe Warning (if applicable) -->
      <?php if ($is_used_in_recipes): ?>
      <div class="alert alert-warning">
        <div class="d-flex align-items-start">
          <div class="me-3">
            <i class="fas fa-exclamation-triangle fa-2x"></i>
          </div>
          <div>
            <h6 class="alert-heading mb-2">
              <i class="fas fa-link me-2"></i>Recipe Dependencies Detected
            </h6>
            <p class="mb-2">This item is currently used in <strong><?php echo $recipe_check['count']; ?></strong> menu item recipe(s):</p>
            <div class="bg-white rounded p-2 mb-2">
              <small class="text-muted"><?php echo htmlspecialchars($used_in_menu_items); ?></small>
            </div>
            <p class="mb-0 small">
              <i class="fas fa-info-circle me-1"></i>
              Deleting this item will remove it from all recipes. These menu items may become unavailable if no alternative ingredients are set.
            </p>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Confirmation Form -->
      <div class="card border-danger">
        <div class="card-header text-white py-3" style="background-color: #3b2008;">
          <h5 class="mb-0">
            <i class="fas fa-shield-alt me-2"></i>Confirm Deletion
          </h5>
        </div>
        <div class="card-body">
          <form method="POST" id="deleteForm">
            <?php echo getCsrfTokenField(); ?>

            <div class="mb-4">
              <label for="confirm_delete" class="form-label fw-bold">
                Type <code class="text-danger fs-6">DELETE</code> to confirm:
              </label>
              <input type="text"
                     class="form-control form-control-lg"
                     id="confirm_delete"
                     name="confirm_delete"
                     placeholder="Type DELETE here"
                     autocomplete="off"
                     required>
              <small class="text-muted">This verification helps prevent accidental deletions</small>
            </div>

            <div class="form-check mb-4">
              <input class="form-check-input" type="checkbox" id="understand_checkbox" required>
              <label class="form-check-label" for="understand_checkbox">
                I understand that this action is permanent and cannot be undone
              </label>
            </div>

            <hr>

            <div class="row">
              <div class="col-md-6">
                <button type="submit"
                        class="btn btn-danger w-100"
                        id="deleteButton"
                        disabled>
                  <i class="fas fa-trash-alt me-2"></i>Delete Item Permanently
                </button>
              </div>
              <div class="col-md-6">
                <a href="inventory.php" class="btn btn-outline-secondary w-100">
                  <i class="fas fa-times me-2"></i>Cancel & Keep Item
                </a>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Alternative Actions -->
      <div class="card mt-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-lightbulb me-2 " style="color:#3b2008;"></i>Consider These Alternatives
          </h6>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <li class="mb-2">
              <i class="fas fa-arrow-right  me-2"></i>
              <a href="edit_item.php?id=<?php echo $item_id; ?>">Edit the item</a> if you want to update its information
            </li>
            <li class="mb-2">
              <i class="fas fa-arrow-right  me-2"></i>
              <a href="stock_out.php?item_id=<?php echo $item_id; ?>">Record a stock-out</a> to reduce quantity to zero
            </li>
            <li class="mb-0">
              <i class="fas fa-arrow-right  me-2"></i>
              Keep the item with zero stock for historical records
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.icon-brown {
  color: #3b2008;
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

code {
  background-color: #f8f9fa;
  padding: 0.2rem 0.4rem;
  border-radius: 0.25rem;
  font-size: 1rem;
}

.alert {
  border-width: 2px;
}

.btn-danger {
  background-color: #3b2008;
  border-color: #3b2008;
}

.btn-danger:hover:not(:disabled) {
  background-color: #2a1505;
  border-color: #2a1505;
}

.btn-danger:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.bg-brown { background-color: #3b2008; color: #fff; }
.text-brown { color: #3b2008; }
.btn-outline-brown { color: #3b2008; border-color: #3b2008; background-color: transparent; }
.btn-outline-brown:hover, .btn-outline-brown:active, .btn-outline-brown:focus {
  background-color: #3b2008; border-color: #3b2008; color: #fff;
}
.btn-primary { background-color: #3b2008; border-color: #3b2008; }
.btn-primary:hover { background-color: #2a1505; border-color: #2a1505; }

</style>

<script>
$(document).ready(function() {
  const deleteButton = $('#deleteButton');
  const confirmInput = $('#confirm_delete');
  const checkbox = $('#understand_checkbox');
  const form = $('#deleteForm');

  function checkFormValidity() {
    const inputValid = confirmInput.val().trim() === 'DELETE';
    const checkboxChecked = checkbox.is(':checked');

    if (inputValid && checkboxChecked) {
      deleteButton.prop('disabled', false);
      deleteButton.removeClass('btn-secondary').addClass('btn-danger');
    } else {
      deleteButton.prop('disabled', true);
      deleteButton.removeClass('btn-danger').addClass('btn-secondary');
    }
  }

  confirmInput.on('input', function() {
    const value = $(this).val().trim();
    if (value === 'DELETE') {
      $(this).removeClass('is-invalid').addClass('is-valid');
    } else if (value.length > 0) {
      $(this).removeClass('is-valid').addClass('is-invalid');
    } else {
      $(this).removeClass('is-valid is-invalid');
    }
    checkFormValidity();
  });

  checkbox.on('change', checkFormValidity);

  form.on('submit', function(e) {
    if (confirmInput.val().trim() !== 'DELETE') {
      e.preventDefault();
      confirmInput.addClass('is-invalid');
      alert('Please type DELETE to confirm deletion.');
      return false;
    }

    if (!checkbox.is(':checked')) {
      e.preventDefault();
      alert('Please confirm that you understand this action is permanent.');
      return false;
    }

    // Final confirmation
    const itemName = '<?php echo addslashes($item['item_name']); ?>';
    const confirmed = confirm(
      `Are you absolutely sure you want to delete "${itemName}"?\n\n` +
      `This action cannot be undone!`
    );

    if (!confirmed) {
      e.preventDefault();
      return false;
    }

    // Disable button to prevent double submission
    deleteButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Deleting...');
  });

  // Warning before leaving page
  let formSubmitted = false;
  form.on('submit', function() {
    formSubmitted = true;
  });

  $(window).on('beforeunload', function() {
    if (confirmInput.val().trim().length > 0 && !formSubmitted) {
      return 'Are you sure you want to leave? Your confirmation will be lost.';
    }
  });
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
