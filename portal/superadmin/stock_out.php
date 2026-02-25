<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireSuperAdmin();

  $item_id = $_GET['id'] ?? $_GET['item_id'] ?? null;
  if (!$item_id || !is_numeric($item_id)) {
    $_SESSION['error_message'] = 'Invalid item ID.';
    redirect('superadmin/inventory.php');
  }

  $conn = getDBConnection();

  $stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
  $stmt->bind_param("i", $item_id);
  $stmt->execute();
  $item = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$item) {
    $_SESSION['error_message'] = 'Item not found.';
    redirect('superadmin/inventory.php');
  }

  // Get recent stock-out transactions for this item
  $stmt = $conn->prepare("
    SELECT t.*, u.username
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.item_id = ? AND t.type = 'stock-out'
    ORDER BY t.timestamp DESC
    LIMIT 5
  ");
  $stmt->bind_param("i", $item_id);
  $stmt->execute();
  $recent_stock_outs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $_SESSION['error_message'] = 'Security token mismatch. Please try again.';
        redirect("/stock_out.php?item_id={$item_id}");
    }

    $quantity_out = (float)($_POST['quantity'] ?? 0);
    $remarks = sanitizeInput($_POST['remarks'] ?? '');

    if ($quantity_out <= 0) {
        $_SESSION['error_message'] = 'Quantity must be greater than zero.';
    } elseif ($quantity_out > $item['quantity']) {
        $_SESSION['error_message'] = "Quantity exceeds available stock. Available: {$item['quantity']} {$item['unit']}";
    } else {
        $conn->begin_transaction();
        try {
            $new_qty = $item['quantity'] - $quantity_out;
            $stmt = $conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
            $stmt->bind_param("di", $new_qty, $item_id);
            $stmt->execute();
            $stmt->close();

            addTransaction($_SESSION['user_id'], $item_id, 'stock-out', $quantity_out, $remarks);

            $conn->commit();

            logActivity($_SESSION['user_id'], 'Stock Out', "Removed {$quantity_out} {$item['unit']} from {$item['item_name']}");
            $_SESSION['success_message'] = "Successfully removed {$quantity_out} {$item['unit']} from inventory.";
            redirect('superadmin/inventory.php?success=stock_out');

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Stock out error: " . $e->getMessage());
            $_SESSION['error_message'] = 'Failed to record stock out. Please try again.';
        }
    }
  }

  $form_data = [
    'quantity' => $_POST['quantity'] ?? '',
    'remarks' => $_POST['remarks'] ?? '',
  ];

  $page_title = 'Stock Out';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0 text-warning">
            <i class="fas fa-arrow-down me-2"></i>Stock Out - Remove Inventory
          </h3>
          <p class="text-muted mb-0">Record inventory items removed or used</p>
        </div>
        <div>
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
      <!-- Item Info Card -->
      <div class="card mb-4 border-warning border-2">
        <div class="card-header text-dark py-3" style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);">
          <h5 class="mb-0">
            <i class="fas fa-box me-2"></i>Item Information
          </h5>
        </div>
        <div class="card-body">
          <div class="row align-items-center">
            <div class="col-md-8">
              <h4 class="mb-2"><?php echo htmlspecialchars($item['item_name']); ?></h4>
              <?php if (!empty($item['description'])): ?>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($item['description']); ?></p>
              <?php endif; ?>
              <div class="d-flex gap-3 flex-wrap">
                <div>
                  <small class="text-muted d-block">Current Stock</small>
                  <span class="fw-bold fs-5" style="color: #ffc107;">
                    <?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
                  </span>
                </div>
                <div class="vr"></div>
                <div>
                  <small class="text-muted d-block">Unit</small>
                  <span class="badge bg-info text-white">
                    <?php echo htmlspecialchars($item['unit']); ?>
                  </span>
                </div>
                <div class="vr"></div>
                <div>
                  <small class="text-muted d-block">Status</small>
                  <?php if ($item['quantity'] < 10): ?>
                    <span class="badge bg-danger">Low Stock</span>
                  <?php elseif ($item['quantity'] < 50): ?>
                    <span class="badge bg-warning text-dark">Medium Stock</span>
                  <?php else: ?>
                    <span class="badge bg-success">Good Stock</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
              <div class="bg-light rounded p-3 border">
                <div class="text-muted small mb-1">Last Updated</div>
                <div class="fw-semibold"><?php echo formatDate($item['updated_at'], 'M j, Y'); ?></div>
                <div class="small text-muted"><?php echo formatDate($item['updated_at'], 'g:i A'); ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Low Stock Warning -->
      <?php if ($item['quantity'] < 10): ?>
      <div class="alert alert-danger mb-4">
        <div class="d-flex align-items-center">
          <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
          <div>
            <strong>Low Stock Warning!</strong><br>
            <small>This item has low stock levels. Consider restocking before removing more inventory.</small>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Stock Out Form -->
      <div class="card">
        <div class="card-header bg-white py-3">
          <h5 class="mb-0">
            <i class="fas fa-clipboard-list me-2 text-warning"></i>Record Stock Out
          </h5>
        </div>
        <div class="card-body">
          <form method="POST" id="stockOutForm" novalidate>
            <?php echo getCsrfTokenField(); ?>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-4">
                  <label for="quantity" class="form-label fw-semibold">
                    <i class="fas fa-minus-circle text-warning me-1"></i>
                    Quantity to Remove <span class="text-danger">*</span>
                  </label>
                  <div class="input-group input-group-lg">
                    <input type="number"
                           class="form-control border-warning"
                           id="quantity"
                           name="quantity"
                           value="<?php echo htmlspecialchars($form_data['quantity']); ?>"
                           min="0.01"
                           max="<?php echo $item['quantity']; ?>"
                           step="0.01"
                           placeholder="0.00"
                           required>
                    <span class="input-group-text bg-warning text-dark">
                      <?php echo htmlspecialchars($item['unit']); ?>
                    </span>
                  </div>
                  <div class="invalid-feedback">
                    Please enter a valid quantity between 0.01 and <?php echo number_format($item['quantity'], 2); ?>.
                  </div>
                  <small class="text-muted">
                    Maximum available: <strong><?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?></strong>
                  </small>
                </div>
              </div>

              <div class="col-md-6">
                <div class="mb-4">
                  <label class="form-label fw-semibold text-muted">
                    <i class="fas fa-calculator me-1"></i>Stock Preview
                  </label>
                  <div class="border rounded p-3 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span class="text-muted">Current:</span>
                      <span class="fw-bold"><?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span class="text-warning">- Removing:</span>
                      <span class="fw-bold text-warning" id="removing-display">0.00 <?php echo htmlspecialchars($item['unit']); ?></span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <span class="fw-bold">Remaining:</span>
                      <span class="fw-bold fs-5" id="remaining-total" style="color: #ffc107;">
                        <?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
                      </span>
                    </div>
                  </div>
                  <div id="low-stock-warning" class="mt-2 small text-danger" style="display: none;">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>Warning:</strong> This will result in low stock!
                  </div>
                </div>
              </div>
            </div>

            <div class="mb-4">
              <label for="remarks" class="form-label fw-semibold">
                <i class="fas fa-comment-dots text-warning me-1"></i>
                Reason for Removal <span class="text-danger">*</span>
              </label>
              <textarea class="form-control"
                        id="remarks"
                        name="remarks"
                        rows="4"
                        placeholder="e.g., Used in production, Spoilage, Waste, Damaged items, Quality control rejection..."
                        required><?php echo htmlspecialchars($form_data['remarks']); ?></textarea>
              <div class="invalid-feedback">Please provide a reason for removing stock.</div>
              <small class="text-muted">Documenting the reason helps maintain accurate inventory records</small>
            </div>

            <!-- Quick Remarks -->
            <div class="mb-4">
              <label class="form-label fw-semibold text-muted small">
                <i class="fas fa-bolt me-1"></i>Common Reasons
              </label>
              <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-secondary quick-remark" data-remark="Used in production">
                  Used in production
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary quick-remark" data-remark="Spoilage/Expired">
                  Spoilage/Expired
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary quick-remark" data-remark="Damaged during storage">
                  Damaged during storage
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary quick-remark" data-remark="Quality control rejection">
                  Quality control rejection
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary quick-remark" data-remark="Waste/Spillage">
                  Waste/Spillage
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary quick-remark" data-remark="Sample/Testing">
                  Sample/Testing
                </button>
              </div>
            </div>

            <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle me-2"></i>
              <strong>Important:</strong> This transaction will be permanently recorded in the inventory history.
              The current stock will be decreased by the amount you enter above. This action cannot be easily undone.
            </div>

            <hr class="my-4">

            <div class="row">
              <div class="col-md-6">
                <button type="submit" class="btn btn-warning btn-lg w-100 text-dark" id="submitBtn">
                  <i class="fas fa-check me-2"></i>Confirm Stock Out
                </button>
              </div>
              <div class="col-md-6">
                <a href="inventory.php" class="btn btn-outline-secondary btn-lg w-100">
                  <i class="fas fa-times me-2"></i>Cancel
                </a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
      <!-- Quick Actions -->
      <div class="card mb-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
          </h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="edit_item.php?id=<?php echo $item_id; ?>" class="btn btn-outline-primary btn-sm">
              <i class="fas fa-edit me-2"></i>Edit Item Details
            </a>
            <a href="stock_in.php?item_id=<?php echo $item_id; ?>" class="btn btn-outline-success btn-sm">
              <i class="fas fa-arrow-up me-2"></i>Switch to Stock In
            </a>
            <a href="transactions.php" class="btn btn-outline-info btn-sm">
              <i class="fas fa-history me-2"></i>View All Transactions
            </a>
          </div>
        </div>
      </div>

      <!-- Recent Stock Out History -->
      <?php if (!empty($recent_stock_outs)): ?>
      <div class="card">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-history me-2 text-warning"></i>Recent Stock Outs
          </h6>
        </div>
        <div class="card-body p-0">
          <div class="list-group list-group-flush">
            <?php foreach ($recent_stock_outs as $trans): ?>
              <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="flex-grow-1">
                    <div class="fw-bold text-danger">
                      -<?php echo number_format($trans['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
                    </div>
                    <div class="small text-muted">
                      <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($trans['username']); ?>
                    </div>
                    <?php if (!empty($trans['remarks'])): ?>
                      <div class="small text-muted mt-1">
                        <i class="fas fa-comment me-1"></i><?php echo htmlspecialchars($trans['remarks']); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="text-end small text-muted" style="min-width: 80px;">
                    <?php echo formatDate($trans['timestamp'], 'M j'); ?><br>
                    <?php echo formatDate($trans['timestamp'], 'g:i A'); ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Tips Card -->
      <div class="card mt-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-lightbulb me-2 text-warning"></i>Best Practices
          </h6>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0 small">
            <li class="mb-2">
              <i class="fas fa-check-circle text-success me-2"></i>
              Always provide a clear reason for removal
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle text-success me-2"></i>
              Double-check quantities before confirming
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle text-success me-2"></i>
              Document waste or spoilage for auditing
            </li>
            <li class="mb-0">
              <i class="fas fa-check-circle text-success me-2"></i>
              Monitor low stock items regularly
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
.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.vr {
  opacity: 0.2;
}

.form-label {
  font-weight: 500;
  color: #495057;
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

.quick-remark:hover {
  background-color: #ffc107;
  border-color: #ffc107;
  color: #000;
}

#removing-display, #remaining-total {
  transition: all 0.3s ease;
}

.input-group-lg .form-control {
  font-size: 1.5rem;
  font-weight: 600;
}
</style>

<script>
$(document).ready(function() {
  const form = $('#stockOutForm');
  const quantityInput = $('#quantity');
  const remarksInput = $('#remarks');
  const currentStock = <?php echo $item['quantity']; ?>;
  const unit = '<?php echo htmlspecialchars($item['unit']); ?>';
  const lowStockWarning = $('#low-stock-warning');

  // Real-time stock calculation
  quantityInput.on('input', function() {
    const removeAmount = parseFloat($(this).val()) || 0;

    // Update display
    $('#removing-display').text(removeAmount.toFixed(2) + ' ' + unit);

    const remaining = currentStock - removeAmount;
    $('#remaining-total').text(remaining.toFixed(2) + ' ' + unit);

    // Validation
    if (removeAmount <= 0 || removeAmount > currentStock) {
      $(this).addClass('is-invalid').removeClass('is-valid');
    } else {
      $(this).removeClass('is-invalid').addClass('is-valid');
    }

    // Color coding based on remaining stock
    const remainingTotal = $('#remaining-total');
    if (remaining < 10) {
      remainingTotal.removeClass('text-success text-warning').addClass('text-danger');
      lowStockWarning.show();
    } else if (remaining < 50) {
      remainingTotal.removeClass('text-success text-danger').addClass('text-warning');
      lowStockWarning.hide();
    } else {
      remainingTotal.removeClass('text-warning text-danger').addClass('text-success');
      lowStockWarning.hide();
    }
  });

  // Quick remarks
  $('.quick-remark').on('click', function() {
    const remark = $(this).data('remark');
    const currentRemarks = remarksInput.val().trim();

    if (currentRemarks === '') {
      remarksInput.val(remark);
    } else {
      remarksInput.val(currentRemarks + ', ' + remark);
    }

    remarksInput.removeClass('is-invalid').addClass('is-valid');

    // Visual feedback
    $(this).addClass('active');
    setTimeout(() => {
      $(this).removeClass('active');
    }, 200);
  });

  // Remarks validation
  remarksInput.on('input', function() {
    if ($(this).val().trim().length > 0) {
      $(this).removeClass('is-invalid').addClass('is-valid');
    } else {
      $(this).removeClass('is-valid');
    }
  });

  // Form validation
  form.on('submit', function(e) {
    let isValid = true;

    const quantity = parseFloat(quantityInput.val());
    const remarks = remarksInput.val().trim();

    // Validate quantity
    if (isNaN(quantity) || quantity <= 0) {
      e.preventDefault();
      quantityInput.addClass('is-invalid');
      quantityInput.focus();
      alert('Please enter a valid quantity greater than 0.');
      isValid = false;
    } else if (quantity > currentStock) {
      e.preventDefault();
      quantityInput.addClass('is-invalid');
      quantityInput.focus();
      alert(`Quantity exceeds available stock.\nAvailable: ${currentStock.toFixed(2)} ${unit}\nEntered: ${quantity.toFixed(2)} ${unit}`);
      isValid = false;
    }

    // Validate remarks
    if (remarks.length === 0) {
      e.preventDefault();
      remarksInput.addClass('is-invalid');
      if (isValid) remarksInput.focus();
      alert('Please provide a reason for removing stock.');
      isValid = false;
    }

    if (!isValid) {
      return false;
    }

    // Final confirmation
    const remaining = currentStock - quantity;
    let confirmMsg = `Are you sure you want to remove ${quantity.toFixed(2)} ${unit}?\n\n` +
                     `Current Stock: ${currentStock.toFixed(2)} ${unit}\n` +
                     `Removing: ${quantity.toFixed(2)} ${unit}\n` +
                     `Remaining: ${remaining.toFixed(2)} ${unit}\n` +
                     `Reason: ${remarks}`;

    // Extra warning for low stock
    if (remaining < 10) {
      confirmMsg += `\n\n⚠️ WARNING: This will result in LOW STOCK!`;
    }

    const confirmed = confirm(confirmMsg);

    if (!confirmed) {
      e.preventDefault();
      return false;
    }

    // Disable submit button to prevent double submission
    $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
  });

  // Auto-focus on quantity input
  quantityInput.focus();
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
