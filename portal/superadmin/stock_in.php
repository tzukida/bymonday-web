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

  // Get recent stock-in transactions for this item
  $stmt = $conn->prepare("
    SELECT t.*, u.username
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.item_id = ? AND t.type = 'stock-in'
    ORDER BY t.timestamp DESC
    LIMIT 5
  ");
  $stmt->bind_param("i", $item_id);
  $stmt->execute();
  $recent_stock_ins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $_SESSION['error_message'] = 'Security token mismatch. Please try again.';
        redirect("/stock_in.php?item_id={$item_id}");
    }

    $quantity_in = (float)($_POST['quantity'] ?? 0);
    $remarks = sanitizeInput($_POST['remarks'] ?? '');

    if ($quantity_in <= 0) {
        $_SESSION['error_message'] = 'Quantity must be greater than zero.';
    } else {
        $conn->begin_transaction();
        try {
            $new_qty = $item['quantity'] + $quantity_in;
            $stmt = $conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
            $stmt->bind_param("di", $new_qty, $item_id);
            $stmt->execute();
            $stmt->close();

            addTransaction($_SESSION['user_id'], $item_id, 'stock-in', $quantity_in, $remarks);

            $conn->commit();

            logActivity($_SESSION['user_id'], 'Stock In', "Added {$quantity_in} {$item['unit']} to {$item['item_name']}");
            $_SESSION['success_message'] = "Successfully added {$quantity_in} {$item['unit']} to inventory.";
            redirect('superadmin/inventory.php?success=stock_in');

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Stock in error: " . $e->getMessage());
            $_SESSION['error_message'] = 'Failed to record stock in. Please try again.';
        }
    }
  }

  $form_data = [
    'quantity' => $_POST['quantity'] ?? '',
    'remarks' => $_POST['remarks'] ?? '',
  ];

  $page_title = 'Stock In';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #4a301f;">
            <i class="fas fa-arrow-up me-2"></i>Stock In - Add Inventory
          </h3>
          <p class="text-muted mb-0">Record new stock received for inventory item</p>
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
      <div class="card mb-4" style="border: 2px solid #4a301f !important;">
        <div class="card-header text-white py-3" style="background: linear-gradient(135deg, #3b2008 0%, #2a1505 100%);">
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
                  <span class="fw-bold fs-5" style="color: #4a301f;">
                    <?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
                  </span>
                </div>
                <div class="vr"></div>
                <div>
                  <small class="text-muted d-block">Unit</small>
                  <span class="badge bg-brown">
                    <?php echo htmlspecialchars($item['unit']); ?>
                  </span>
                </div>
                <div class="vr"></div>
                <div>
                  <small class="text-muted d-block">Status</small>
                  <?php if ($item['quantity'] < 10): ?>
                    <span class="badge bg-brown" style="opacity:0.6;">Low Stock</span>
                  <?php elseif ($item['quantity'] < 50): ?>
                    <span class="badge bg-brown" style="opacity:0.75;">Medium Stock</span>
                  <?php else: ?>
                    <span class="badge bg-brown">Good Stock</span>
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

      <!-- Stock In Form -->
      <div class="card">
        <div class="card-header bg-white py-3">
          <h5 class="mb-0">
            <i class="fas fa-clipboard-list me-2 icon-brown"></i>Record Stock In
          </h5>
        </div>
        <div class="card-body">
          <form method="POST" id="stockInForm" novalidate>
            <?php echo getCsrfTokenField(); ?>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-4">
                  <label for="quantity" class="form-label fw-semibold">
                    <i class="fas fa-plus-circle icon-brown me-1"></i>
                    Quantity to Add <span class="text-danger">*</span>
                  </label>
                  <div class="input-group input-group-lg">
                    <input type="number"
                           class="form-control border-brown"
                           id="quantity"
                           name="quantity"
                           value="<?php echo htmlspecialchars($form_data['quantity']); ?>"
                           min="0.01"
                           step="0.01"
                           placeholder="0.00"
                           required>
                    <span class="input-group-text bg-brown text-white">
                      <?php echo htmlspecialchars($item['unit']); ?>
                    </span>
                  </div>
                  <div class="invalid-feedback">Please enter a valid quantity greater than 0.</div>
                  <small class="text-muted">Enter the amount of stock you're adding</small>
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
                      <span style="color: #4a301f;">+ Adding:</span>
                      <span class="fw-bold" style="color: #4a301f;" id="adding-display">0.00 <?php echo htmlspecialchars($item['unit']); ?></span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <span class="fw-bold">New Total:</span>
                      <span class="fw-bold fs-5" style="color: #4a301f;" id="new-total">
                        <?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="mb-4">
              <label for="remarks" class="form-label fw-semibold">
                <i class="fas fa-comment-dots icon-brown me-1"></i>
                Remarks <small class="text-muted fw-normal">(Optional)</small>
              </label>
              <textarea class="form-control"
                        id="remarks"
                        name="remarks"
                        rows="4"
                        placeholder="e.g., Supplier delivery, Purchase order #1234, Weekly restock..."><?php echo htmlspecialchars($form_data['remarks']); ?></textarea>
              <small class="text-muted">Add any notes about this stock addition (supplier, purchase order, reason, etc.)</small>
            </div>

            <!-- Quick Remarks -->
            <div class="mb-4">
              <label class="form-label fw-semibold text-muted small">
                <i class="fas fa-bolt me-1"></i>Quick Remarks
              </label>
              <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-brown quick-remark" data-remark="Supplier delivery">
                  Supplier delivery
                </button>
                <button type="button" class="btn btn-sm btn-outline-brown quick-remark" data-remark="Weekly restock">
                  Weekly restock
                </button>
                <button type="button" class="btn btn-sm btn-outline-brown quick-remark" data-remark="Emergency purchase">
                  Emergency purchase
                </button>
                <button type="button" class="btn btn-sm btn-outline-brown quick-remark" data-remark="Monthly inventory">
                  Monthly inventory
                </button>
                <button type="button" class="btn btn-sm btn-outline-brown quick-remark" data-remark="Bulk order">
                  Bulk order
                </button>
              </div>
            </div>

            <div class="alert" style="background-color: #fff3e0; border-color: #c87533; color: #3b2008;">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Note:</strong> This transaction will be permanently recorded in the inventory history.
              The current stock will be increased by the amount you enter above.
            </div>

            <hr class="my-4">

            <div class="row">
              <div class="col-md-6">
                <button type="submit" class="btn btn-brown btn-lg w-100">
                  <i class="fas fa-check me-2"></i>Confirm Stock In
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
            <i class="fas fa-bolt me-2 icon-brown"></i>Quick Actions
          </h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="edit_item.php?id=<?php echo $item_id; ?>" class="btn btn-outline-brown btn-sm">
              <i class="fas fa-edit me-2"></i>Edit Item Details
            </a>
            <a href="stock_out.php?item_id=<?php echo $item_id; ?>" class="btn btn-outline-brown btn-sm">
              <i class="fas fa-arrow-down me-2"></i>Switch to Stock Out
            </a>
            <a href="transactions.php" class="btn btn-outline-brown btn-sm">
              <i class="fas fa-history me-2"></i>View All Transactions
            </a>
          </div>
        </div>
      </div>

      <!-- Recent Stock In History -->
      <?php if (!empty($recent_stock_ins)): ?>
      <div class="card">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-history me-2 icon-brown"></i>Recent Stock Ins
          </h6>
        </div>
        <div class="card-body p-0">
          <div class="list-group list-group-flush">
            <?php foreach ($recent_stock_ins as $trans): ?>
              <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="flex-grow-1">
                    <div class="fw-bold" style="color: #4a301f;">
                      +<?php echo number_format($trans['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
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
            <i class="fas fa-lightbulb me-2 icon-brown"></i>Best Practices
          </h6>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0 small">
            <li class="mb-2">
              <i class="fas fa-check-circle icon-brown me-2"></i>
              Always count items carefully before recording
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle icon-brown me-2"></i>
              Include supplier or purchase order in remarks
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle icon-brown me-2"></i>
              Record stock immediately upon receipt
            </li>
            <li class="mb-0">
              <i class="fas fa-check-circle icon-brown me-2"></i>
              Check expiry dates for perishable items
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<style>

.icon-brown {
  color: #4a301f;
}

.text-brown {
  color: #4a301f;
}

.bg-brown {
  background-color: #4a301f;
  color: #fff;
}

.btn-brown {
  --bs-btn-bg: #4a301f;
  --bs-btn-border-color: #4a301f;
  --bs-btn-color: #fff;
  --bs-btn-hover-bg: #5d3d28;
  --bs-btn-hover-border-color: #5d3d28;
  --bs-btn-hover-color: #fff;
  --bs-btn-active-bg: #4a301f;
  --bs-btn-active-border-color: #4a301f;
  --bs-btn-active-color: #fff;
  background-color: #4a301f;
  border-color: #4a301f;
  color: #fff;
}

.btn-outline-brown {
  color: #4a301f;
  border-color: #4a301f;
  background-color: transparent;
}

.btn-outline-brown:hover,
.btn-outline-brown:active {
  background-color: #4a301f;
  border-color: #4a301f;
  color: white;
}

.border-brown {
  border-color: #4a301f !important;
}

.form-control:focus,
.form-select:focus {
  border-color: #4a301f !important;
  box-shadow: 0 0 0 0.2rem rgba(74, 48, 31, 0.25) !important;
}

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
  background-color: #4a301f;
  border-color: #4a301f;
  color: white;
}

#adding-display, #new-total {
  transition: all 0.3s ease;
}

.input-group-lg .form-control {
  font-size: 1.5rem;
  font-weight: 600;
}
</style>

<script>
$(document).ready(function() {
  const form = $('#stockInForm');
  const quantityInput = $('#quantity');
  const remarksInput = $('#remarks');
  const currentStock = <?php echo $item['quantity']; ?>;
  const unit = '<?php echo htmlspecialchars($item['unit']); ?>';

  // Real-time stock calculation
  quantityInput.on('input', function() {
    const addAmount = parseFloat($(this).val()) || 0;

    // Update display
    $('#adding-display').text(addAmount.toFixed(2) + ' ' + unit);

    const newTotal = currentStock + addAmount;
    $('#new-total').text(newTotal.toFixed(2) + ' ' + unit);

    // Validation
    if (addAmount <= 0) {
      $(this).addClass('is-invalid').removeClass('is-valid');
    } else {
      $(this).removeClass('is-invalid').addClass('is-valid');
    }

    // Highlight if significant change
    if (addAmount > currentStock * 0.5) {
      $('#new-total').css('color', '#c87533');
    } else {
      $('#new-total').css('color', '#4a301f');
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

    // Visual feedback
    $(this).addClass('active');
    setTimeout(() => {
      $(this).removeClass('active');
    }, 200);
  });

  // Form validation
  form.on('submit', function(e) {
    const quantity = parseFloat(quantityInput.val());

    if (isNaN(quantity) || quantity <= 0) {
      e.preventDefault();
      quantityInput.addClass('is-invalid');
      quantityInput.focus();
      alert('Please enter a valid quantity greater than 0.');
      return false;
    }

    // Confirmation for large quantities
    if (quantity > currentStock * 2) {
      const confirmed = confirm(
        `You're adding ${quantity.toFixed(2)} ${unit}, which is more than double the current stock.\n\n` +
        `Current: ${currentStock.toFixed(2)} ${unit}\n` +
        `Adding: ${quantity.toFixed(2)} ${unit}\n` +
        `New Total: ${(currentStock + quantity).toFixed(2)} ${unit}\n\n` +
        `Is this correct?`
      );

      if (!confirmed) {
        e.preventDefault();
        return false;
      }
    }
  });

  // Auto-focus on quantity input
  quantityInput.focus();
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
