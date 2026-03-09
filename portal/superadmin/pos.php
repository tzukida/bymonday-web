<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';

  requireAuth();

  $page_title = 'Point of Sale';
  require_once BASE_PATH . '/includes/header.php';

  $menu_items = getAvailableMenuItems();

  $categories = array_unique(array_column($menu_items, 'category'));

  $is_admin = in_array(($_SESSION['role'] ?? ''), ['admin', 'superadmin']);
?>

<div class="container-fluid">
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h1 class="h3 mb-0" style="color: #4a301f;">Point of Sale</h1>
          <p class="text-muted mb-0">Select items to create a new sale</p>
        </div>
        <div class="d-flex gap-2">
          <a href="sales_report.php" class="btn btn-outline-brown">
            <i class="fas fa-chart-line me-2"></i>Sales Report
          </a>
          <?php if ($is_admin): ?>
            <a href="menu_management.php" class="btn btn-brown">
              <i class="fas fa-cog me-2"></i>Manage Menu
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-white border-bottom py-3">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="mb-0">
              <i class="fas fa-utensils me-2 icon-brown"></i>Menu Items
            </h5>
            <div class="btn-group flex-wrap" role="group">
              <button type="button" class="btn btn-brown active" data-filter="all">
                <i class="fas fa-th me-1"></i>All
              </button>
              <?php foreach ($categories as $cat): ?>
                <button type="button" class="btn btn-outline-brown" data-filter="<?php echo htmlspecialchars($cat); ?>">
                  <?php echo htmlspecialchars($cat); ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="card-body p-4" style="max-height: calc(125vh - 100px); overflow-y: auto;">
          <?php if (empty($menu_items)): ?>
            <div class="text-center py-5">
              <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No menu items available</h5>
              <p class="text-muted">Please add menu items to start selling</p>
              <?php if ($is_admin): ?>
                <a href="add_menu_item.php" class="btn btn-brown">
                  <i class="fas fa-plus me-2"></i>Add Menu Item
                </a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="row g-4" id="menuItemsContainer">
              <?php foreach ($menu_items as $item): ?>
                <div class="col-xl-4 col-lg-6 col-md-6 menu-item" data-category="<?php echo htmlspecialchars($item['category']); ?>">
                  <div class="card h-100 menu-card <?php echo !$item['can_fulfill'] ? 'out-of-stock' : ''; ?>"
                       data-item-id="<?php echo $item['id']; ?>"
                       data-item-name="<?php echo htmlspecialchars($item['name']); ?>"
                       data-item-price="<?php echo $item['price']; ?>">
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
                             onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'menu-image-placeholder\'><i class=\'fas fa-utensils fa-3x text-white\'></i></div>';">
                      <?php else: ?>
                        <div class="menu-image-placeholder">
                          <i class="fas fa-utensils fa-3x text-white"></i>
                        </div>
                      <?php endif; ?>
                      <?php if (!$item['can_fulfill']): ?>
                        <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2">
                          <i class="fas fa-exclamation-triangle me-1"></i>Out of Stock
                        </span>
                      <?php endif; ?>
                    </div>
                    <div class="card-body d-flex flex-column p-3">
                      <h5 class="card-title fw-bold mb-2 text-dark"><?php echo htmlspecialchars($item['name']); ?></h5>
                      <p class="card-text text-muted small mb-3 flex-grow-1 menu-description">
                        <?php echo htmlspecialchars($item['description'] ?: 'No description available'); ?>
                      </p>
                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="h4 text-brown mb-0 fw-bold">₱<?php echo number_format($item['price'], 2); ?></span>
                        <span class="badge bg-brown-light fs-12"><?php echo htmlspecialchars($item['category']); ?></span>
                      </div>
                      <?php if ($item['can_fulfill']): ?>
                        <button class="btn btn-brown btn-lg w-100 add-to-cart">
                          <i class="fas fa-plus-circle me-2"></i>Add to Cart
                        </button>
                      <?php else: ?>
                        <button class="btn btn-secondary btn-lg w-100" disabled>
                          <i class="fas fa-times-circle me-2"></i>Unavailable
                        </button>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Cart Section -->
    <div class="col-lg-4">
      <div class="card sticky-top" style="top: 20px;">
        <div class="card-header text-white py-3" style="background: linear-gradient(135deg, #4a301f 0%, #2a1b11 100%);">
          <h5 class="mb-0">
            <i class="fas fa-shopping-cart me-2"></i>Current Order
            <span class="badge bg-white text-brown float-end" id="cartCount">0</span>
          </h5>
        </div>
        <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
          <div id="cartItems" class="p-3">
            <div class="text-center py-5 text-muted empty-cart-message">
              <i class="fas fa-shopping-cart fa-3x mb-3 opacity-50"></i>
              <p class="mb-0 fw-semibold">Cart is empty</p>
              <p class="small text-muted">Add items from the menu</p>
            </div>
          </div>
        </div>
        <div class="card-footer bg-white p-3">
          <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
            <h5 class="mb-0 text-muted">Subtotal:</h5>
            <h4 class="fw-bold text-brown mb-0" id="cartTotal">₱0.00</h4>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold text-muted small mb-2">
              <i class="fas fa-user me-1"></i>Customer Name (Optional)
            </label>
            <input type="text" class="form-control form-control-lg" id="customerName" placeholder="Walk-in Customer">
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold text-muted small mb-2">
              <i class="fas fa-credit-card me-1"></i>Payment Method
            </label>
            <select class="form-select form-select-lg" id="paymentMethod">
              <option value="cash">Cash</option>
              <option value="gcash">GCash</option>
              <option value="maya">Maya</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold text-muted small mb-2">
              <i class="fas fa-sticky-note me-1"></i>Remarks (Optional)
            </label>
            <textarea class="form-control" id="remarks" rows="3" placeholder="Add any special instructions..."></textarea>
          </div>

          <div class="d-grid gap-2">
            <button class="btn btn-brown btn-lg" id="processOrderBtn" disabled>
              <i class="fas fa-check-circle me-2"></i>Process Order
            </button>
            <button class="btn btn-outline-brown btn-lg" id="clearCartBtn">
              <i class="fas fa-trash me-2"></i>Clear Cart
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white border-0" style="background: linear-gradient(135deg, #3b2008 0%, #2a1505 100%);">
        <h5 class="modal-title">
          <i class="fas fa-check-circle me-2"></i>Order Successful
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <div class="mb-4">
          <div class="success-icon mx-auto mb-3">
            <i class="fas fa-check-circle icon-brown"></i>
          </div>
          <h4 class="mb-2">Order Processed Successfully!</h4>
          <p class="text-muted mb-0">Your order has been recorded</p>
        </div>

        <div class="alert alert-light border mb-0">
          <div class="row text-start">
            <div class="col-6">
              <small class="text-muted d-block mb-1">Order ID</small>
              <strong id="modalOrderId">#000000</strong>
            </div>
            <div class="col-6">
              <small class="text-muted d-block mb-1">Total Amount</small>
              <strong class="text-brown" id="modalOrderTotal">₱0.00</strong>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-brown" data-bs-dismiss="modal">
          <i class="fas fa-times me-2"></i>Close
        </button>
        <button type="button" class="btn btn-brown" id="viewReceiptBtn">
          <i class="fas fa-receipt me-2"></i>View Receipt
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white border-0" style="background: linear-gradient(135deg, #7d5633 0%, #654529 100%);">
        <h5 class="modal-title">
          <i class="fas fa-exclamation-circle me-2"></i>Order Failed
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <div class="mb-4">
          <div class="error-icon mx-auto mb-3">
            <i class="fas fa-times-circle text-brown"></i>
          </div>
          <h4 class="mb-2">Unable to Process Order</h4>
          <p class="text-muted mb-0" id="errorMessage">An error occurred while processing your order.</p>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-2"></i>Close
        </button>
        <button type="button" class="btn btn-brown" data-bs-dismiss="modal">
          <i class="fas fa-redo me-2"></i>Try Again
        </button>
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
  background-color: #4a301f;
  border-color: #4a301f;
  color: white;
}

.btn-brown:hover {
  background-color: #4d3420;
  border-color: #4d3420;
  color: white;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(56, 36, 23, 0.3);
}

.btn-brown:active,
.btn-brown.active {
  background-color: #4a301f !important;
  border-color: #4a301f !important;
  color: white;
  box-shadow: 0 2px 4px rgba(56, 36, 23, 0.3) !important;
}

.btn-outline-brown {
  color: #4a301f;
  border-color: #4a301f;
  background-color: transparent;
}

.btn-outline-brown:hover {
  background-color: #4a301f;
  border-color: #4a301f;
  color: white;
}

.bg-brown-light {
  background-color: #654529;
  color: white;
}

.menu-card {
  cursor: pointer;
  transition: all 0.3s ease;
  border: 2px solid transparent;
  overflow: hidden;
}

.menu-card:hover:not(.out-of-stock) {
  transform: translateY(-8px);
  box-shadow: 0 12px 24px rgba(56, 36, 23, 0.25);
  border-color: #4a301f;
}

.menu-card.out-of-stock {
  opacity: 0.6;
  cursor: not-allowed;
  filter: grayscale(50%);
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
  background: linear-gradient(135deg, #4a301f 0%, #2a1b11 100%);
  display: flex;
  align-items: center;
  justify-content: center;
}

.menu-description {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  min-height: 2.8em;
  line-height: 1.4em;
}

.cart-item {
  padding: 18px;
  border-bottom: 1px solid #eee;
  transition: background-color 0.2s;
  animation: slideIn 0.3s ease;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(-20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.cart-item:hover {
  background-color: #f8f9fa;
}

.cart-item:last-child {
  border-bottom: none;
}

.quantity-control {
  display: inline-flex;
  align-items: center;
  gap: 12px;
}

.quantity-btn {
  width: 36px;
  height: 36px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  transition: all 0.2s;
  font-size: 0.875rem;
}

.quantity-btn:hover {
  transform: scale(1.1);
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.sticky-top {
  position: sticky;
  z-index: 1020;
}

.empty-cart-message {
  animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

#cartCount {
  font-size: 0.875rem;
  padding: 0.4rem 0.75rem;
  min-width: 32px;
  font-weight: 600;
}

.btn-group .btn {
  padding: 0.5rem 1rem;
  font-size: 0.9rem;
}

.btn-group .btn.active {
  box-shadow: 0 2px 4px rgba(56, 36, 23, 0.3);
}

.success-icon, .error-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: rgba(25, 135, 84, 0.1);
}

.error-icon {
  background-color: rgba(125, 86, 51, 0.1);
}

.success-icon i, .error-icon i {
  font-size: 3rem;
}

.modal-content {
  border: none;
  box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.2);
}

.form-control-lg, .form-select-lg {
  padding: 0.75rem 1rem;
  font-size: 1rem;
}

@media (max-width: 1399px) {
  .menu-image, .menu-image-placeholder {
    height: 200px;
  }
}

@media (max-width: 991px) {
  .sticky-top {
    position: relative !important;
    top: 0 !important;
    margin-top: 0;
  }

  .menu-image, .menu-image-placeholder {
    height: 200px;
  }
}

@media (max-width: 768px) {
  .menu-image, .menu-image-placeholder {
    height: 220px;
  }

  .btn-group {
    flex-wrap: wrap;
  }

  .btn-group .btn {
    font-size: 0.85rem;
    padding: 0.4rem 0.8rem;
  }

  .card-body {
    padding: 1rem !important;
  }
}

.btn-added {
  --bs-btn-bg: #2a1505;
  --bs-btn-border-color: #2a1505;
  --bs-btn-color: #fff;
  background-color: #2a1505 !important;
  border-color: #2a1505 !important;
  color: #fff !important;
}

.form-select:focus,
.form-control:focus,
.form-check-input:focus {
  border-color: #6b3a1f !important;
  box-shadow: 0 0 0 0.2rem rgba(107, 58, 31, 0.25) !important;
  outline: none !important;
}

.form-select option:checked {
  background-color: #6b3a1f !important;
  color: #fff !important;
}
</style>

<script>
let cart = [];
let currentSaleId = null;

// Filter menu items
document.querySelectorAll('[data-filter]').forEach(btn => {
  btn.addEventListener('click', function() {
    const filter = this.dataset.filter;

    // Update active button
    document.querySelectorAll('[data-filter]').forEach(b => {
      b.classList.remove('active', 'btn-brown');
      b.classList.add('btn-outline-brown');
    });
    this.classList.remove('btn-outline-brown');
    this.classList.add('active', 'btn-brown');

    // Filter items with animation
    document.querySelectorAll('.menu-item').forEach(item => {
      if (filter === 'all' || item.dataset.category === filter) {
        item.style.display = 'block';
        setTimeout(() => item.style.opacity = '1', 10);
      } else {
        item.style.opacity = '0';
        setTimeout(() => item.style.display = 'none', 300);
      }
    });
  });
});

// Add to cart
document.querySelectorAll('.add-to-cart').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.stopPropagation();
    const card = this.closest('.menu-card');
    const itemId = card.dataset.itemId;
    const itemName = card.dataset.itemName;
    const itemPrice = parseFloat(card.dataset.itemPrice);

    // Check if item already in cart
    const existingItem = cart.find(item => item.id === itemId);

    if (existingItem) {
      existingItem.quantity++;
    } else {
      cart.push({
        id: itemId,
        name: itemName,
        price: itemPrice,
        quantity: 1
      });
    }

    // Visual feedback
    const originalHTML = this.innerHTML;
    this.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
    this.classList.remove('btn-brown');
    this.classList.add('btn-added');

    setTimeout(() => {
      this.innerHTML = originalHTML;
      this.classList.remove('btn-added');
      this.classList.add('btn-brown');
    }, 600);

    updateCart();
  });
});

// Update cart display
function updateCart() {
  const cartItemsContainer = document.getElementById('cartItems');
  const cartTotalElement = document.getElementById('cartTotal');
  const cartCountElement = document.getElementById('cartCount');
  const processBtn = document.getElementById('processOrderBtn');

  cartCountElement.textContent = cart.length;

  if (cart.length === 0) {
    cartItemsContainer.innerHTML = `
      <div class="text-center py-5 text-muted empty-cart-message">
        <i class="fas fa-shopping-cart fa-3x mb-3 opacity-50"></i>
        <p class="mb-0 fw-semibold">Cart is empty</p>
        <p class="small text-muted">Add items from the menu</p>
      </div>
    `;
    cartTotalElement.textContent = '₱0.00';
    processBtn.disabled = true;
    return;
  }

  let total = 0;
  let html = '';

  cart.forEach((item, index) => {
    const subtotal = item.price * item.quantity;
    total += subtotal;

    html += `
      <div class="cart-item">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="flex-grow-1">
            <h6 class="mb-1 fw-bold text-dark">${item.name}</h6>
            <small class="text-muted">₱${item.price.toFixed(2)} each</small>
          </div>
          <button class="btn btn-sm btn-link text-brown p-0 ms-2" onclick="removeFromCart(${index})" title="Remove item">
            <i class="fas fa-times fs-5"></i>
          </button>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <div class="quantity-control">
            <button class="btn btn-sm btn-outline-brown quantity-btn" onclick="decreaseQuantity(${index})">
              <i class="fas fa-minus"></i>
            </button>
            <span class="fw-bold px-2 fs-6">${item.quantity}</span>
            <button class="btn btn-sm btn-outline-brown quantity-btn" onclick="increaseQuantity(${index})">
              <i class="fas fa-plus"></i>
            </button>
          </div>
          <strong class="text-brown fs-5">₱${subtotal.toFixed(2)}</strong>
        </div>
      </div>
    `;
  });

  cartItemsContainer.innerHTML = html;
  cartTotalElement.textContent = '₱' + total.toFixed(2);
  processBtn.disabled = false;
}

function removeFromCart(index) {
  cart.splice(index, 1);
  updateCart();
}

function decreaseQuantity(index) {
  if (cart[index].quantity > 1) {
    cart[index].quantity--;
    updateCart();
  } else {
    removeFromCart(index);
  }
}

function increaseQuantity(index) {
  cart[index].quantity++;
  updateCart();
}

// Clear cart
document.getElementById('clearCartBtn').addEventListener('click', function() {
  if (cart.length === 0) {
    return;
  }

  if (confirm('Are you sure you want to clear the cart?')) {
    cart = [];
    document.getElementById('customerName').value = '';
    document.getElementById('remarks').value = '';
    document.getElementById('paymentMethod').value = 'cash';
    updateCart();
  }
});

// Process order
document.getElementById('processOrderBtn').addEventListener('click', function() {
  if (cart.length === 0) {
    return;
  }

  const customerName = document.getElementById('customerName').value.trim();
  const paymentMethod = document.getElementById('paymentMethod').value;
  const remarks = document.getElementById('remarks').value.trim();

  // Calculate total
  const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

  // Disable button to prevent double submission
  this.disabled = true;
  this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';

  // Prepare order data
  const orderData = {
    items: cart,
    customer_name: customerName || 'Walk-in Customer',
    payment_method: paymentMethod,
    remarks: remarks,
    total_amount: total
  };

  // Send to server
  fetch('process_sale.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(orderData)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Store sale ID
      currentSaleId = data.sale_id;

      // Update modal
      document.getElementById('modalOrderId').textContent = '#' + String(data.sale_id).padStart(6, '0');
      document.getElementById('modalOrderTotal').textContent = '₱' + total.toFixed(2);

      // Clear cart
      cart = [];
      document.getElementById('customerName').value = '';
      document.getElementById('remarks').value = '';
      document.getElementById('paymentMethod').value = 'cash';
      updateCart();

      // Show success modal
      const successModal = new bootstrap.Modal(document.getElementById('successModal'));
      successModal.show();
    } else {
      // Show error modal
      document.getElementById('errorMessage').textContent = data.message || 'An error occurred while processing the order.';
      const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
      errorModal.show();
    }
  })
  .catch(error => {
    console.error('Error:', error);
    document.getElementById('errorMessage').textContent = 'An unexpected error occurred. Please try again.';
    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
    errorModal.show();
  })
  .finally(() => {
    // Re-enable button
    this.disabled = false;
    this.innerHTML = '<i class="fas fa-check-circle me-2"></i>Process Order';
  });
});

// View receipt button
document.getElementById('viewReceiptBtn').addEventListener('click', function() {
  if (currentSaleId) {
    window.open('receipt.php?sale_id=' + currentSaleId, '_blank');
    bootstrap.Modal.getInstance(document.getElementById('successModal')).hide();
  }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
  // Ctrl/Cmd + Enter to process order
  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
    if (!document.getElementById('processOrderBtn').disabled) {
      document.getElementById('processOrderBtn').click();
    }
  }

  // ESC to clear cart
  if (e.key === 'Escape' && cart.length > 0 && !document.querySelector('.modal.show')) {
    if (confirm('Clear cart?')) {
      document.getElementById('clearCartBtn').click();
    }
  }
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
