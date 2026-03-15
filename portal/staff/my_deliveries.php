<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';

requireStaff();

$page_title = 'Deliveries';
require_once BASE_PATH . '/includes/header.php';

$rider_id = intval($_SESSION['user_id']);

$cs = new mysqli('localhost', 'root', '', 'coffee_shop');
if ($cs->connect_error) die("Connection failed: " . $cs->connect_error);
$cs->set_charset("utf8mb4");

$stmt = $cs->prepare("
    SELECT o.*, GROUP_CONCAT(oi.product_name, ' x', oi.quantity ORDER BY oi.id SEPARATOR ', ') as items_summary
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.rider_id = ?
    AND o.order_status IN ('brewing', 'delivery')
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $rider_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$cs->close();
?>

<div class="container-fluid py-4 px-4">

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0 fw-bold" style="color:#2d1a0e;">Deliveries</h4>
            <p class="text-muted mb-0 small">Orders assigned to you for delivery</p>
        </div>
        <button class="btn btn-sm" onclick="location.reload()"
            style="background:#f3ece4; border:1px solid #e0d0c0; color:#6b4c2a; border-radius:8px;">
            <i class="fas fa-rotate-right me-1"></i> Refresh
        </button>
    </div>

    <!-- Orders List -->
    <div id="ordersContainer">
        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <i class="fas fa-person-biking"></i>
            <p>No deliveries assigned to you</p>
        </div>
        <?php else: ?>
        <div class="orders-list">
            <?php foreach ($orders as $order): ?>
            <div class="order-row" id="order-<?= $order['id'] ?>">

                <!-- Left: Order Info -->
                <div class="order-main">
                    <div class="order-header">
                        <div class="order-num">
                            <i class="fas fa-receipt me-2" style="color:#C97B2B;"></i>
                            #<?= strtoupper(substr($order['order_number'], -8)) ?>
                        </div>
                        <span class="order-time">
                            <i class="fas fa-clock me-1"></i>
                            <?= date('M j, g:i A', strtotime($order['created_at'])) ?>
                        </span>
                        <span class="pay-badge pay-<?= $order['payment_method'] ?>">
                            <?= $order['payment_method'] === 'cash'
                                ? '<i class="fas fa-money-bill-wave me-1"></i>COD'
                                : '<i class="fas fa-mobile-screen-button me-1"></i>' . ucfirst($order['payment_method']) ?>
                        </span>
                        <span class="status-pill status-<?= $order['order_status'] ?>">
                            <?php if ($order['order_status'] === 'brewing'): ?>
                                <i class="fas fa-mug-hot me-1"></i> Preparing
                            <?php else: ?>
                                <i class="fas fa-person-biking me-1"></i> Out for Delivery
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="order-body">
                        <div class="customer-info">
                            <div class="customer-name">
                                <i class="fas fa-user me-2" style="color:#9b7e60;font-size:12px;"></i>
                                <?= htmlspecialchars($order['customer_name']) ?>
                            </div>
                            <div class="customer-detail">
                                <i class="fas fa-phone me-2" style="color:#9b7e60;font-size:11px;"></i>
                                <?= htmlspecialchars($order['customer_phone']) ?>
                            </div>
                            <div class="customer-detail">
                                <i class="fas fa-location-dot me-2" style="color:#9b7e60;font-size:11px;"></i>
                                <?= htmlspecialchars($order['customer_address']) ?>
                            </div>
                        </div>

                        <div class="order-items-text">
                            <i class="fas fa-bag-shopping me-2" style="color:#9b7e60;font-size:11px;"></i>
                            <?= htmlspecialchars($order['items_summary'] ?? '—') ?>
                        </div>
                    </div>
                </div>

                <!-- Right: Total + Actions -->
                <div class="order-right">
                    <div class="order-total-wrap">
                        <div class="order-total-label">Total</div>
                        <div class="order-total-amount">₱<?= number_format($order['total'] + ($order['delivery_fee'] ?? 50), 2) ?></div>
                    </div>
                    <div class="order-actions">
                        <button class="btn-map" disabled>
                            <i class="fas fa-map me-1"></i> View Map
                        </button>
                        <?php if ($order['order_status'] === 'delivery'): ?>
                        <button class="btn-done" onclick="markDelivered(<?= $order['id'] ?>)">
                            <i class="fas fa-check-double me-1"></i> Delivered
                        </button>
                        <?php else: ?>
                        <button class="btn-done" style="opacity:0.4;cursor:not-allowed;" disabled>
                            <i class="fas fa-check-double me-1"></i> Delivered
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

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="pageToast" class="toast align-items-center border-0 text-white" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="pageToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<style>
.orders-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.order-row {
    background: #fff;
    border: 1.5px solid #f0e8df;
    border-radius: 16px;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 24px;
    transition: all .2s ease;
}
.order-row:hover { border-color: #d4b896; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }

.order-main { flex: 1; min-width: 0; }

.order-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.order-num {
    font-weight: 700;
    font-size: 14px;
    color: #2d1a0e;
}
.order-time {
    font-size: 12px;
    color: #9b7e60;
    margin-left: auto;
}
.pay-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 50px;
}
.pay-cash   { background: #f0fdf4; color: #16a34a; }
.pay-online { background: #eff6ff; color: #2563eb; }
.pay-card   { background: #faf5ff; color: #7c3aed; }

.status-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 50px;
}
.status-brewing  { background: rgba(129,140,248,0.15); color: #818cf8; }
.status-delivery { background: rgba(96,165,250,0.15);  color: #60a5fa; }

.order-body {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
}
.customer-info { min-width: 200px; }
.customer-name {
    font-size: 13px;
    font-weight: 600;
    color: #2d1a0e;
    margin-bottom: 4px;
}
.customer-detail {
    font-size: 12px;
    color: #7a5c3a;
    margin-bottom: 3px;
    line-height: 1.4;
}
.order-items-text {
    font-size: 12px;
    color: #7a5c3a;
    flex: 1;
    line-height: 1.6;
    align-self: center;
}

.order-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
    flex-shrink: 0;
}
.order-total-wrap { text-align: right; }
.order-total-label { font-size: 11px; color: #9b7e60; font-weight: 500; }
.order-total-amount { font-size: 20px; font-weight: 800; color: #C97B2B; }

.order-actions { display: flex; gap: 8px; align-items: center; }

.btn-map {
    padding: 8px 14px;
    background: #f5ede3;
    border: 1.5px solid #d4b896;
    color: #6b4c2a;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: not-allowed;
    opacity: 0.6;
}

.btn-done {
    background: linear-gradient(135deg, #a78bfa, #7c3aed);
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    white-space: nowrap;
}
.btn-done:hover:not(:disabled) { opacity: .88; transform: translateY(-1px); }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9b7e60;
}
.empty-state i { font-size: 40px; opacity: .3; display: block; margin-bottom: 12px; }
.empty-state p { font-size: 14px; font-weight: 500; }
</style>

<script>
function markDelivered(orderId) {
    fetch('api/update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, status: 'done' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById('order-' + orderId);
            if (row) {
                row.style.transition = 'all .3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                setTimeout(() => row.remove(), 300);
            }
        } else {
            showToast(data.message || 'Failed to update.', false);
        }
    })
    .catch(() => showToast('Network error. Try again.', false));
}

function showToast(msg, success) {
    const toast = document.getElementById('pageToast');
    document.getElementById('pageToastMsg').textContent = msg;
    toast.className = 'toast align-items-center border-0 text-white ' + (success ? 'bg-success' : 'bg-danger');
    new bootstrap.Toast(toast, { delay: 4000 }).show();
}
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>