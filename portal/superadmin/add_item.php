<?php
  define('BASE_PATH', dirname(__DIR__));
  $page_title = 'Add New Item';
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireSuperAdmin();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
      $_SESSION['error_message'] = 'Security token mismatch. Please try again.';
    } else {
      $item_name   = sanitizeInput($_POST['item_name'] ?? '');
      $description = sanitizeInput($_POST['description'] ?? '');
      $unit        = sanitizeInput($_POST['unit'] ?? '');
      $quantity    = (float)($_POST['quantity'] ?? 0);

      $required_fields = ['item_name', 'unit'];
      $errors = validateRequired($required_fields, $_POST);

      if (strlen($item_name) < 2) $errors[] = 'Item name must be at least 2 characters long';
      if ($quantity < 0) $errors[] = 'Quantity cannot be negative';

      if (empty($errors)) {
        try {
          $conn = getDBConnection();

          $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory WHERE item_name = ?");
          $stmt->bind_param("s", $item_name);
          $stmt->execute();
          $row = $stmt->get_result()->fetch_assoc();
          $stmt->close();

          if ($row['count'] > 0) {
            $_SESSION['error_message'] = 'An item with this name already exists.';
          } else {
            $stmt = $conn->prepare(
              "INSERT INTO inventory (item_name, description, unit, quantity) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("sssd", $item_name, $description, $unit, $quantity);

            if ($stmt->execute()) {
              $item_id = $conn->insert_id;
              $stmt->close();

              if ($quantity > 0) {
                  addTransaction($_SESSION['user_id'], $item_id, 'stock-in', $quantity, 'Initial stock');
              }

              logActivity($_SESSION['user_id'], 'Add Inventory Item', "Added new item: $item_name with quantity: $quantity $unit");
              $_SESSION['success_message'] = "Item \"$item_name\" has been successfully added to inventory.";
              redirect('superadmin/inventory.php?success=item_added');
            } else {
              $stmt->close();
              $_SESSION['error_message'] = 'Failed to add item. Please try again.';
            }
          }
        } catch (Exception $e) {
          error_log("Add item error: " . $e->getMessage());
          $_SESSION['error_message'] = 'An error occurred while adding the item.';
        }
      } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
      }
    }
  }
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #4a301f;">
            <i class="fas fa-plus-circle me-2"></i>Add New Inventory Item
          </h3>
          <p class="text-muted mb-0">Add a new raw ingredient or item to your inventory</p>
        </div>
        <div>
          <a href="inventory.php" class="btn btn-outline-brown">
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
          <form method="POST" id="addItemForm" novalidate>
            <?php echo getCsrfTokenField(); ?>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="item_name" class="form-label">
                    <i class="fas fa-tag me-1"></i>Item Name <span class="text-brown">*</span>
                  </label>
                  <input type="text"
                         class="form-control"
                         id="item_name"
                         name="item_name"
                         value="<?php echo htmlspecialchars($_POST['item_name'] ?? ''); ?>"
                         placeholder="e.g., White Rice, Chicken Breast"
                         required>
                  <div class="invalid-feedback">Please enter a valid item name (min. 2 characters).</div>
                </div>
              </div>

              <div class="col-md-6">
                <div class="mb-3">
                  <label for="unit" class="form-label">
                    <i class="fas fa-ruler me-1"></i>Unit of Measurement <span class="text-brown">*</span>
                  </label>
                  <select class="form-select" id="unit" name="unit" required>
                    <option value="">Choose unit...</option>
                    <optgroup label="Weight">
                      <option value="kg" <?php echo ($_POST['unit'] ?? '') === 'kg' ? 'selected' : ''; ?>>Kilograms (kg)</option>
                      <option value="g" <?php echo ($_POST['unit'] ?? '') === 'g' ? 'selected' : ''; ?>>Grams (g)</option>
                      <option value="lbs" <?php echo ($_POST['unit'] ?? '') === 'lbs' ? 'selected' : ''; ?>>Pounds (lbs)</option>
                    </optgroup>
                    <optgroup label="Volume">
                      <option value="liters" <?php echo ($_POST['unit'] ?? '') === 'liters' ? 'selected' : ''; ?>>Liters (L)</option>
                      <option value="ml" <?php echo ($_POST['unit'] ?? '') === 'ml' ? 'selected' : ''; ?>>Milliliters (ml)</option>
                    </optgroup>
                    <optgroup label="Count">
                      <option value="pcs" <?php echo ($_POST['unit'] ?? '') === 'pcs' ? 'selected' : ''; ?>>Pieces (pcs)</option>
                      <option value="bottles" <?php echo ($_POST['unit'] ?? '') === 'bottles' ? 'selected' : ''; ?>>Bottles</option>
                      <option value="cans" <?php echo ($_POST['unit'] ?? '') === 'cans' ? 'selected' : ''; ?>>Cans</option>
                      <option value="packs" <?php echo ($_POST['unit'] ?? '') === 'packs' ? 'selected' : ''; ?>>Packs</option>
                      <option value="boxes" <?php echo ($_POST['unit'] ?? '') === 'boxes' ? 'selected' : ''; ?>>Boxes</option>
                      <option value="containers" <?php echo ($_POST['unit'] ?? '') === 'containers' ? 'selected' : ''; ?>>Containers</option>
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
                        placeholder="Brief description of the item..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
              <small class="text-muted">Provide additional details about this inventory item</small>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="quantity" class="form-label">
                    <i class="fas fa-hashtag me-1"></i>Initial Quantity
                  </label>
                  <input type="number"
                         class="form-control"
                         id="quantity"
                         name="quantity"
                         value="<?php echo htmlspecialchars($_POST['quantity'] ?? '0'); ?>"
                         min="0"
                         step="0.01"
                         placeholder="0.00">
                  <small class="text-muted">Starting stock quantity (can be 0 or decimal)</small>
                </div>
              </div>

              <div class="col-md-6">
                <div class="alert alert-info-brown mb-0 mt-4">
                  <i class="fas fa-info-circle me-2"></i>
                  <small>
                    <strong>Note:</strong> If you enter a quantity greater than 0, it will be automatically recorded as a stock-in transaction.
                  </small>
                </div>
              </div>
            </div>

            <hr class="my-4">

            <div class="row">
              <div class="col-md-6">
                <button type="submit" class="btn btn-brown w-100">
                  <i class="fas fa-save me-2"></i>Add Item to Inventory
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

    <!-- Quick Examples Sidebar -->
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-lightbulb me-2 icon-brown"></i>Quick Add Examples
          </h6>
        </div>
        <div class="card-body">
          <p class="text-muted small mb-3">Click on an example to auto-fill the form</p>

          <div class="example-item" data-name="White Rice" data-unit="kg" data-desc="Premium white rice for cooking">
            <div class="d-flex align-items-center mb-3 p-3 border rounded hover-shadow cursor-pointer">
              <div class="example-icon me-3">
                <i class="fas fa-seedling fa-2x icon-brown"></i>
              </div>
              <div>
                <h6 class="mb-0">White Rice</h6>
                <small class="text-muted">Unit: kg</small>
              </div>
            </div>
          </div>

          <div class="example-item" data-name="Chicken Breast" data-unit="kg" data-desc="Fresh chicken breast meat">
            <div class="d-flex align-items-center mb-3 p-3 border rounded hover-shadow cursor-pointer">
              <div class="example-icon me-3">
                <i class="fas fa-drumstick-bite fa-2x icon-brown"></i>
              </div>
              <div>
                <h6 class="mb-0">Chicken Breast</h6>
                <small class="text-muted">Unit: kg</small>
              </div>
            </div>
          </div>

          <div class="example-item" data-name="Soy Sauce" data-unit="bottles" data-desc="Traditional Japanese soy sauce">
            <div class="d-flex align-items-center mb-3 p-3 border rounded hover-shadow cursor-pointer">
              <div class="example-icon me-3">
                <i class="fas fa-wine-bottle fa-2x icon-brown"></i>
              </div>
              <div>
                <h6 class="mb-0">Soy Sauce</h6>
                <small class="text-muted">Unit: bottles</small>
              </div>
            </div>
          </div>

          <div class="example-item" data-name="Cooking Oil" data-unit="liters" data-desc="Vegetable cooking oil">
            <div class="d-flex align-items-center mb-3 p-3 border rounded hover-shadow cursor-pointer">
              <div class="example-icon me-3">
                <i class="fas fa-oil-can fa-2x icon-brown"></i>
              </div>
              <div>
                <h6 class="mb-0">Cooking Oil</h6>
                <small class="text-muted">Unit: liters</small>
              </div>
            </div>
          </div>

          <div class="example-item" data-name="Eggs" data-unit="pcs" data-desc="Fresh chicken eggs">
            <div class="d-flex align-items-center mb-3 p-3 border rounded hover-shadow cursor-pointer">
              <div class="example-icon me-3">
                <i class="fas fa-egg fa-2x icon-brown"></i>
              </div>
              <div>
                <h6 class="mb-0">Eggs</h6>
                <small class="text-muted">Unit: pcs</small>
              </div>
            </div>
          </div>

          <div class="example-item" data-name="Onions" data-unit="kg" data-desc="Fresh yellow onions">
            <div class="d-flex align-items-center mb-3 p-3 border rounded hover-shadow cursor-pointer">
              <div class="example-icon me-3">
                <i class="fas fa-spa fa-2x icon-brown"></i>
              </div>
              <div>
                <h6 class="mb-0">Onions</h6>
                <small class="text-muted">Unit: kg</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tips Card -->
      <div class="card mt-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-tips me-2 text-info"></i>Best Practices
          </h6>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0 small">
            <li class="mb-2">
              <i class="fas fa-check-circle text-brown me-2"></i>
              Use descriptive names for easy identification
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle text-brown me-2"></i>
              Choose appropriate units for accurate tracking
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle text-brown me-2"></i>
              Add descriptions to clarify item specifics
            </li>
            <li class="mb-0">
              <i class="fas fa-check-circle text-brown me-2"></i>
              Enter initial quantity if you have stock on hand
            </li>
          </ul>
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
  color: #4a301f;
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

.hover-shadow {
  transition: all 0.3s ease;
}

.hover-shadow:hover {
  box-shadow: 0 0.25rem 0.5rem rgba(74, 48, 31, 0.15);
  transform: translateY(-2px);
  border-color: #654529 !important;
}

.cursor-pointer {
  cursor: pointer;
}

.example-icon {
  width: 50px;
  text-align: center;
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

.example-item.active .hover-shadow {
  background-color: #fff3e0;
  border-color: #654529 !important;
}

.alert-info-brown {
  background-color: #fff3e0;
  border: 1px solid #ffcc80;
  color: #4a301f;
  border-radius: 0.375rem;
}

.alert-info-brown strong {
  color: #382417;
}
.form-control.is-valid,
.form-select.is-valid,
.was-validated .form-control:valid,
.was-validated .form-select:valid {
  --bs-form-valid-border-color: #4a301f;
  --bs-form-valid-color: #4a301f;
  border-color: #4a301f !important;
  background-color: #fff8f3 !important;
  background-image: none !important;
  color: inherit !important;
}

.form-control.is-valid:focus,
.form-select.is-valid:focus,
.was-validated .form-control:valid:focus,
.was-validated .form-select:valid:focus {
  border-color: #4a301f !important;
  background-color: #fff8f3 !important;
  box-shadow: 0 0 0 0.2rem rgba(74, 48, 31, 0.25) !important;
}

.valid-feedback {
  color: #4a301f !important;
}

:root {
  --bs-form-valid-color: #4a301f;
  --bs-form-valid-border-color: #4a301f;
}

</style>

<script>
$(document).ready(function() {
  const form = $('#addItemForm');
  const itemNameInput = $('#item_name');
  const unitSelect = $('#unit');
  const quantityInput = $('#quantity');
  const descriptionInput = $('#description');

  // Real-time validation
  itemNameInput.on('blur', function() {
    const value = $(this).val().trim();
    if (value.length < 2) {
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
    const value = parseFloat($(this).val());
    if (isNaN(value) || value < 0) {
      $(this).addClass('is-invalid').removeClass('is-valid');
    } else {
      $(this).removeClass('is-invalid').addClass('is-valid');
    }
  });

  // Example click handler
  $('.example-item').on('click', function() {
    const name = $(this).data('name');
    const unit = $(this).data('unit');
    const desc = $(this).data('desc');

    itemNameInput.val(name).trigger('blur');
    unitSelect.val(unit).trigger('change');
    descriptionInput.val(desc);
    quantityInput.val('0');

    // Visual feedback
    $('.example-item').removeClass('active');
    $(this).addClass('active');

    // Scroll to form
    $('html, body').animate({
      scrollTop: form.offset().top - 100
    }, 500);

    // Show success tooltip
    $(this).find('.hover-shadow').addClass('bg-light');
    setTimeout(() => {
      $(this).find('.hover-shadow').removeClass('bg-light');
    }, 1000);
  });

  // Form submission validation
  form.on('submit', function(e) {
    let isValid = true;

    // Validate item name
    if (itemNameInput.val().trim().length < 2) {
      itemNameInput.addClass('is-invalid');
      isValid = false;
    }

    // Validate unit
    if (!unitSelect.val()) {
      unitSelect.addClass('is-invalid');
      isValid = false;
    }

    // Validate quantity
    const quantity = parseFloat(quantityInput.val());
    if (isNaN(quantity) || quantity < 0) {
      quantityInput.addClass('is-invalid');
      isValid = false;
    }

    if (!isValid) {
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