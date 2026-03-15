<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';
requireAdmin();

$order_id = intval($_GET['id'] ?? 0);
if (!$order_id) { die('Invalid order.'); }

$cs = new mysqli('localhost', 'root', '', 'coffee_shop');
if ($cs->connect_error) { die('DB error.'); }
$cs->set_charset("utf8mb4");

$stmt = $cs->prepare("
    SELECT o.*, u.username as account_username, u.email as account_email,
           c.phone as saved_phone, c.address as saved_address
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN customers c ON o.user_id = c.user_id
    WHERE o.id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { die('Order not found.'); }

$items_stmt = $cs->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();
$cs->close();

$order_total = $order['total'] + ($order['delivery_fee'] ?? 50);

$steps = ['placed','brewing','delivery','done'];
$step_labels = ['Order Placed','Brewing','Out for Delivery','Delivered'];
$step_icons  = ['fa-receipt','fa-mug-hot','fa-person-biking','fa-check-circle'];
$current_step = array_search($order['order_status'], $steps);
if ($current_step === false) $current_step = 0;

$page_title = 'Order Detail — ' . strtoupper(substr($order['order_number'], -8));
require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color:#3b2008;">
            <i class="fas fa-route me-2"></i>Order Detail
          </h3>
          <p class="text-muted mb-0"><?php echo strtoupper($order['order_number']); ?></p>
        </div>
        <a href="javascript:history.back()" class="btn btn-outline-brown">
          <i class="fas fa-arrow-left me-2"></i>Back
        </a>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Left Column -->
    <div class="col-lg-8">

      <!-- Status Timeline -->
      <?php if ($order['order_status'] !== 'cancelled'): ?>
      <div class="card mb-4">
        <div class="card-header bg-white py-3">
          <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2 icon-brown"></i>Order Status</h5>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center position-relative">
            <div style="position:absolute;top:20px;left:10%;right:10%;height:3px;background:#e9ecef;z-index:0;">
              <div style="height:100%;background:#3b2008;width:<?php echo ($current_step / (count($steps)-1)) * 100; ?>%;"></div>
            </div>
            <?php foreach ($steps as $i => $step): ?>
            <div class="text-center" style="z-index:1;flex:1;">
              <div class="mx-auto mb-2 d-flex align-items-center justify-content-center rounded-circle"
                   style="width:42px;height:42px;background:<?php echo $i <= $current_step ? '#3b2008' : '#e9ecef'; ?>;color:<?php echo $i <= $current_step ? '#fff' : '#adb5bd'; ?>;">
                <i class="fas <?php echo $step_icons[$i]; ?>"></i>
              </div>
              <small style="color:<?php echo $i <= $current_step ? '#3b2008' : '#adb5bd'; ?>;font-weight:<?php echo $i === $current_step ? '700' : '400'; ?>;">
                <?php echo $step_labels[$i]; ?>
              </small>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="alert" style="background:#fee2e2;border-color:#fca5a5;color:#991b1b;">
        <i class="fas fa-times-circle me-2"></i>
        <strong>Order Cancelled</strong>
        <?php if (!empty($order['cancel_reason'])): ?>
          — <?php echo htmlspecialchars($order['cancel_reason']); ?>
        <?php endif; ?>
        <?php if (!empty($order['cancelled_by'])): ?>
          <span class="ms-2 badge" style="background:#991b1b;">by <?php echo htmlspecialchars($order['cancelled_by']); ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Order Items -->
      <div class="card mb-4">
        <div class="card-header bg-white py-3">
          <h5 class="mb-0"><i class="fas fa-bag-shopping me-2 icon-brown"></i>Order Items</h5>
        </div>
        <div class="card-body p-0">
          <table class="table mb-0">
            <thead class="table-light">
              <tr>
                <th class="border-0">Product</th>
                <th class="border-0">Size</th>
                <th class="border-0 text-center">Qty</th>
                <th class="border-0 text-end">Price</th>
                <th class="border-0 text-end">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $item): ?>
              <tr>
                <td class="fw-semibold"><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td class="text-muted"><?php echo htmlspecialchars($item['size'] ?? '—'); ?></td>
                <td class="text-center"><?php echo $item['quantity']; ?></td>
                <td class="text-end">₱<?php echo number_format($item['price'], 2); ?></td>
                <td class="text-end fw-semibold">₱<?php echo number_format($item['subtotal'], 2); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
              <tr>
                <td colspan="4" class="text-end">Subtotal:</td>
                <td class="text-end">₱<?php echo number_format($order['subtotal'], 2); ?></td>
              </tr>
              <tr>
                <td colspan="4" class="text-end">Delivery Fee:</td>
                <td class="text-end">₱<?php echo number_format($order['delivery_fee'] ?? 50, 2); ?></td>
              </tr>
              <tr>
                <td colspan="4" class="text-end fw-bold">Total:</td>
                <td class="text-end fw-bold text-brown" style="font-size:1.1rem;">₱<?php echo number_format($order_total, 2); ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

    </div>

    <!-- Right Column -->
    <div class="col-lg-4">

      <!-- Customer Info -->
      <div class="card mb-4">
        <div class="card-header bg-white py-3">
          <h5 class="mb-0"><i class="fas fa-user me-2 icon-brown"></i>Customer Details</h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <small class="text-muted d-block">Full Name</small>
            <span class="fw-semibold"><?php echo htmlspecialchars($order['customer_name']); ?></span>
          </div>
          <div class="mb-3">
            <small class="text-muted d-block">Email</small>
            <span><?php echo htmlspecialchars($order['customer_email']); ?></span>
          </div>
          <div class="mb-3">
            <small class="text-muted d-block">Phone</small>
            <span><?php echo htmlspecialchars($order['customer_phone']); ?></span>
          </div>
          <div class="mb-3">
            <small class="text-muted d-block">Delivery Address</small>
            <span><?php echo htmlspecialchars($order['customer_address']); ?></span>
          </div>
          <?php if (!empty($order['account_username'])): ?>
          <hr>
          <div class="mb-2">
            <small class="text-muted d-block">Account Username</small>
            <span class="badge bg-brown"><?php echo htmlspecialchars($order['account_username']); ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($order['notes'])): ?>
          <hr>
          <div>
            <small class="text-muted d-block">Notes</small>
            <span class="text-muted fst-italic"><?php echo htmlspecialchars($order['notes']); ?></span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Delivery Info -->
      <div class="card mb-4">
        <div class="card-header bg-white py-3">
          <h5 class="mb-0"><i class="fas fa-person-biking me-2 icon-brown"></i>Delivery Info</h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <small class="text-muted d-block">Driver</small>
            <span class="fw-semibold"><?php echo htmlspecialchars($order['rider_name'] ?? '—'); ?></span>
          </div>
          <div class="mb-3">
            <small class="text-muted d-block">Assigned By</small>
            <span><?php echo htmlspecialchars($order['assigned_by'] ?? '—'); ?></span>
          </div>
          <div class="mb-3">
            <small class="text-muted d-block">Payment Method</small>
            <span class="badge" style="background-color:#c97b2b; color:#fff;">
              <?php echo strtolower($order['payment_method']) === 'cash' ? 'COD' : ucfirst($order['payment_method']); ?>
            </span>
          </div>
          <div>
            <small class="text-muted d-block">Order Date</small>
            <span><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></span>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>