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

  // Get transaction history for this item
  $stmt = $conn->prepare("
    SELECT t.*, u.username
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.item_id = ?
    ORDER BY t.timestamp DESC
    LIMIT 5
  ");
  $stmt->bind_param("i", $item_id);
  $stmt->execute();
  $recent_transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $_SESSION['error_message'] = 'Security token mismatch. Please try again.';
    } else {
        $item_name = sanitizeInput($_POST['item_name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $unit = sanitizeInput($_POST['unit'] ?? '');
        $quantity = (float)($_POST['quantity'] ?? 0);

        $required_fields = ['item_name', 'unit'];
        $errors = validateRequired($required_fields, $_POST);

        if (strlen($item_name) < 2) {
            $errors[] = 'Item name must be at least 2 characters long';
        }
        if ($quantity < 0) {
            $errors[] = 'Quantity cannot be negative';
        }

        // Check for duplicate name
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory WHERE item_name = ? AND id != ?");
        $stmt->bind_param("si", $item_name, $item_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row['count'] > 0) {
            $errors[] = 'Another item with this name already exists.';
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE inventory SET item_name = ?, description = ?, unit = ?, quantity = ? WHERE id = ?");
            $stmt->bind_param("sssdi", $item_name, $description, $unit, $quantity, $item_id);

            if ($stmt->execute()) {
                $stmt->close();

                // Record quantity change as transaction if changed
                $diff = $quantity - $item['quantity'];
                if ($diff !== 0) {
                    $type = $diff > 0 ? 'stock-in' : 'stock-out';
                    addTransaction($_SESSION['user_id'], $item_id, $type, abs($diff), 'Manual adjustment via edit');
                }

                logActivity($_SESSION['user_id'], 'Edit Inventory Item', "Updated item: $item_name (ID: $item_id)");
                $_SESSION['success_message'] = 'Item "' . htmlspecialchars($item_name) . '" updated successfully.';
                redirect('admin/inventory.php?success=item_updated');
            } else {
                $stmt->close();
                $_SESSION['error_message'] = 'Failed to update item. Please try again.';
            }
        } else {
            $_SESSION['error_message'] = implode('<br>', $errors);
        }
    }
  }

  $page_title = 'Edit Item';
  require_once BASE_PATH . '/includes/header.php';

  $form_data = [
    'item_name' => $_POST['item_name'] ?? $item['item_name'],
    'description' => $_POST['description'] ?? $item['description'],
    'unit' => $_POST['unit'] ?? $item['unit'],
    'quantity' => $_POST['quantity'] ?? $item['quantity'],
  ];
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #3b2008;">
            <i class="fas fa-edit me-2"></i>Edit Inventory Item
          </h3>
          <p class="text-muted mb-0">Update item information and stock levels</p>
        </div>
        <div class="d-flex gap-2">
          <a href="delete_item.php?id=<?php echo $item_id; ?>"
             class="btn btn-outline-danger"
             onclick="return confirm('Are you sure you want to delete this item?');">
            <i class="fas fa-trash me-2"></i>Delete Item
          </a>
          <a href="inventory.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Inventory
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Main Form -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-white py-3">
          <h5 class="mb-0">
            <i class="fas fa-box me-2 icon-brown"></i>Item Information
          </h5>
        </div>
        <div class="card-body">
          <form method="POST" id="editItemForm" novalidate>
            <?php echo getCsrfTokenField(); ?>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="item_name" class="form-label">
                    <i class="fas fa-tag me-1"></i>Item Name <span class="text-danger">*</span>
                  </label>
                  <input type="text"
                         class="form-control"
                         id="item_name"
                         name="item_name"
                         value="<?php echo htmlspecialchars($form_data['item_name']); ?>"
                         placeholder="Item name"
                         required>
                  <div class="invalid-feedback">Please enter a valid item name (min. 2 characters).</div>
                </div>
              </div>

              <div class="col-md-6">
                <div class="mb-3">
                  <label for="unit" class="form-label">
                    <i class="fas fa-ruler me-1"></i>Unit of Measurement <span class="text-danger">*</span>
                  </label>
                  <select class="form-select" id="unit" name="unit" required>
                    <option value="">Choose unit...</option>
                    <optgroup label="Weight">
                      <option value="kg" <?php echo ($form_data['unit'] === 'kg') ? 'selected' : ''; ?>>Kilograms (kg)</option>
                      <option value="g" <?php echo ($form_data['unit'] === 'g') ? 'selected' : ''; ?>>Grams (g)</option>
                      <option value="lbs" <?php echo ($form_data['unit'] === 'lbs') ? 'selected' : ''; ?>>Pounds (lbs)</option>
                    </optgroup>
                    <optgroup label="Volume">
                      <option value="liters" <?php echo ($form_data['unit'] === 'liters') ? 'selected' : ''; ?>>Liters (L)</option>
                      <option value="ml" <?php echo ($form_data['unit'] === 'ml') ? 'selected' : ''; ?>>Milliliters (ml)</option>
                    </optgroup>
                    <optgroup label="Count">
                      <option value="pcs" <?php echo ($form_data['unit'] === 'pcs') ? 'selected' : ''; ?>>Pieces (pcs)</option>
                      <option value="bottles" <?php echo ($form_data['unit'] === 'bottles') ? 'selected' : ''; ?>>Bottles</option>
                      <option value="cans" <?php echo ($form_data['unit'] === 'cans') ? 'selected' : ''; ?>>Cans</option>
                      <option value="packs" <?php echo ($form_data['unit'] === 'packs') ? 'selected' : ''; ?>>Packs</option>
                      <option value="boxes" <?php echo ($form_data['unit'] === 'boxes') ? 'selected' : ''; ?>>Boxes</option>
                      <option value="containers" <?php echo ($form_data['unit'] === 'containers') ? 'selected' : ''; ?>>Containers</option>
                    </optgroup>
                  </select>
                  <div class="invalid-feedback">Please select a unit of measurement.</div>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label for="description" class="form-label">
                <i class="fas fa-align-left me-1"></i>Description <small class="text-muted">(Optional)</small>
              </label>
              <textarea class="form-control"
                        id="description"
                        name="description"
                        rows="3"
                        placeholder="Brief description of the item..."><?php echo htmlspecialchars($form_data['description']); ?></textarea>
              <small class="text-muted">Provide additional details about this inventory item</small>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="quantity" class="form-label">
                    <i class="fas fa-hashtag me-1"></i>Current Quantity <span class="text-danger">*</span>
                  </label>
                  <input type="number"
                         class="form-control"
                         id="quantity"
                         name="quantity"
                         value="<?php echo htmlspecialchars($form_data['quantity']); ?>"
                         min="0"
                         step="0.01"
                         required>
                  <small class="text-muted">
                    Previous quantity: <strong><?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?></strong>
                  </small>
                </div>
              </div>

              <div class="col-md-6">
                <div class="alert alert-warning mb-0 mt-4">
                  <i class="fas fa-exclamation-triangle me-2"></i>
                  <small>
                    <strong>Warning:</strong> Changing the quantity will create a transaction record. Use Stock In/Out for regular inventory movements.
                  </small>
                </div>
              </div>
            </div>

            <hr class="my-4">

            <div class="row">
              <div class="col-md-6">
                <button type="submit" class="btn btn-danger w-100">
                  <i class="fas fa-save me-2"></i>Save Changes
                </button>
              </div>
              <div class="col-md-6">
                <a href="inventory.php" class="btn btn-outline-secondary w-100">
                  <i class="fas fa-times me-2"></i>Cancel
                </a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Item Details Sidebar -->
    <div class="col-lg-4">
      <!-- Current Status Card -->
      <div class="card mb-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-info-circle me-2 " style="color:#3b2008;"></i>Current Status
          </h6>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted">Current Stock:</span>
              <span class="fw-bold fs-5" style="color: #3b2008;">
                <?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
              </span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted">Status:</span>
              <?php if ($item['quantity'] < 10): ?>
                <span class="badge bg-danger">
                  <i class="fas fa-exclamation-triangle me-1"></i>Low Stock
                </span>
              <?php elseif ($item['quantity'] < 50): ?>
                <span class="badge bg-warning text-dark">
                  <i class="fas fa-circle-exclamation me-1"></i>Medium Stock
                </span>
              <?php else: ?>
                <span class="badge bg-brown">
                  <i class="fas fa-check-circle me-1"></i>Good Stock
                </span>
              <?php endif; ?>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted">Created:</span>
              <span class="small"><?php echo formatDate($item['created_at'], 'M j, Y'); ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <span class="text-muted">Last Updated:</span>
              <span class="small"><?php echo formatDate($item['updated_at'], 'M j, Y g:i A'); ?></span>
            </div>
          </div>

          <hr>

          <div class="d-grid gap-2">
            <a href="stock_in.php?item_id=<?php echo $item_id; ?>" class="btn btn-sm btn-success">
              <i class="fas fa-plus me-2"></i>Add Stock (Stock In)
            </a>
            <a href="stock_out.php?item_id=<?php echo $item_id; ?>" class="btn btn-sm btn-warning">
              <i class="fas fa-minus me-2"></i>Remove Stock (Stock Out)
            </a>
          </div>
        </div>
      </div>

      <!-- Recent Activity Card -->
      <?php if (!empty($recent_transactions)): ?>
      <div class="card">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-history me-2 text-secondary"></i>Recent Activity
          </h6>
        </div>
        <div class="card-body p-0">
          <div class="list-group list-group-flush">
            <?php foreach ($recent_transactions as $trans): ?>
              <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <?php if ($trans['type'] === 'stock-in'): ?>
                      <span class="badge bg-success mb-1">
                        <i class="fas fa-arrow-up me-1"></i>Stock In
                      </span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark mb-1">
                        <i class="fas fa-arrow-down me-1"></i>Stock Out
                      </span>
                    <?php endif; ?>
                    <div class="small text-muted">
                      <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($trans['username']); ?>
                    </div>
                    <?php if (!empty($trans['remarks'])): ?>
                      <div class="small text-muted mt-1">
                        <i class="fas fa-comment me-1"></i><?php echo htmlspecialchars($trans['remarks']); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="text-end">
                    <div class="fw-bold"><?php echo number_format($trans['quantity'], 2); ?></div>
                    <div class="small text-muted"><?php echo formatDate($trans['timestamp'], 'M j, g:i A'); ?></div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="card-footer bg-white border-top">
            <a href="transactions.php" class="text-decoration-none text-gray small">
              <i class="fas fa-eye me-1"></i>View All Transactions
            </a>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
.icon-brown {
  color: #3b2008;
}

.text-gray {
  color: #595C5F;
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
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

.list-group-item {
  border-left: none;
  border-right: none;
}

.list-group-item:first-child {
  border-top: none;
}

.list-group-item:last-child {
  border-bottom: none;
}

.bg-brown { background-color: #3b2008; color: #fff; }
.text-brown { color: #3b2008; }
.btn-outline-brown { color: #3b2008; border-color: #3b2008; background-color: transparent; }
.btn-outline-brown:hover, .btn-outline-brown:active, .btn-outline-brown:focus {
  background-color: #3b2008; border-color: #3b2008; color: #fff;
}
.btn-primary { background-color: #3b2008; border-color: #3b2008; }
.btn-primary:hover { background-color: #2a1505; border-color: #2a1505; }


.form-select:focus,
.form-control:focus,
.form-check-input:focus {
  border-color: #3b2008 !important;
  box-shadow: 0 0 0 0.2rem rgba(59, 32, 8, 0.25) !important;
  outline: none !important;
}
.form-select { accent-color: #3b2008; }
option:checked, option:hover { background-color: #3b2008 !important; color: #fff !important; }
</style>

<script>
$(document).ready(function() {
  const form = $('#editItemForm');
  const itemNameInput = $('#item_name');
  const unitSelect = $('#unit');
  const quantityInput = $('#quantity');

  // Real-time validation
  itemNameInput.on('blur', function() {
    if ($(this).val().trim().length < 2) {
      $(this).addClass('is-invalid').removeClass('is-valid');
    } else {
      $(this).removeClass('is-invalid').addClass('is-valid');
    }
  });

  unitSelect.on('change', function() {
    if (!$(this).val()) {
      $(this).addClass('is-invalid').removeClass('is-valid');
    } else {
      $(this).removeClass('is-invalid').addClass('is-valid');
    }
  });

  quantityInput.on('input', function() {
    const val = parseFloat($(this).val());
    if (isNaN(val) || val < 0) {
      $(this).addClass('is-invalid').removeClass('is-valid');
    } else {
      $(this).removeClass('is-invalid').addClass('is-valid');
    }
  });

  // Form submission validation
  form.on('submit', function(e) {
    let valid = true;

    if (itemNameInput.val().trim().length < 2) {
      itemNameInput.addClass('is-invalid');
      valid = false;
    }

    if (!unitSelect.val()) {
      unitSelect.addClass('is-invalid');
      valid = false;
    }

    const qty = parseFloat(quantityInput.val());
    if (isNaN(qty) || qty < 0) {
      quantityInput.addClass('is-invalid');
      valid = false;
    }

    if (!valid) {
      e.preventDefault();
      // Scroll to first error
      const firstError = $('.is-invalid').first();
      if (firstError.length) {
        $('html, body').animate({
          scrollTop: firstError.offset().top - 100
        }, 300);
      }
    }
  });
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
