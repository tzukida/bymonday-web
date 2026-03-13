<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';

requireStaff();

$page_title = 'Online Orders';
require_once BASE_PATH . '/includes/header.php';

// Connect to coffee_shop DB
$cs = new mysqli('localhost', 'root', '', 'coffee_shop');
if ($cs->connect_error) die("Connection failed: " . $cs->connect_error);
$cs->set_charset("utf8mb4");

$active_tab = $_GET['tab'] ?? 'placed';
$allowed_tabs = ['placed', 'brewing', 'delivery', 'done'];
if (!in_array($active_tab, $allowed_tabs)) $active_tab = 'placed';

// Get counts for each tab
$counts = [];
foreach ($allowed_tabs as $tab) {
    $r = $cs->query("SELECT COUNT(*) as c FROM orders WHERE order_status = '$tab'");
    $counts[$tab] = $r->fetch_assoc()['c'];
}

// Get orders for active tab
$orders = [];
$stmt = $cs->prepare("
    SELECT o.*, GROUP_CONCAT(oi.product_name, ' x', oi.quantity ORDER BY oi.id SEPARATOR ', ') as items_summary
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.order_status = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->bind_param("s", $active_tab);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$cs->close();

$tab_labels = [
    'placed'   => ['label' => 'Pending',          'icon' => 'fa-hourglass-half', 'color' => '#f59e0b'],
    'brewing'  => ['label' => 'Preparing',         'icon' => 'fa-mug-hot',        'color' => '#818cf8'],
    'delivery' => ['label' => 'Out for Delivery',  'icon' => 'fa-person-biking',  'color' => '#60a5fa'],
    'done'     => ['label' => 'Delivered',         'icon' => 'fa-check-circle',   'color' => '#4ade80'],
];
?>

<div class="container-fluid py-4 px-4">

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0 fw-bold" style="color:#2d1a0e;">Online Orders</h4>
            <p class="text-muted mb-0 small">Manage and update customer delivery orders</p>
        </div>
        <button class="btn btn-sm" onclick="location.reload()"
            style="background:#f3ece4; border:1px solid #e0d0c0; color:#6b4c2a; border-radius:8px;">
            <i class="fas fa-rotate-right me-1"></i> Refresh
        </button>
    </div>

    <!-- Status Tab Cards -->
    <div class="row g-3 mb-4">
        <?php foreach ($tab_labels as $key => $tab): ?>
        <div class="col-6 col-md-3">
            <a href="?tab=<?= $key ?>" class="text-decoration-none">
                <div class="stat-card <?= $active_tab === $key ? 'stat-card-active' : '' ?>"
                     style="<?= $active_tab === $key ? "border-color: {$tab['color']}55; box-shadow: 0 4px 20px {$tab['color']}22;" : '' ?>">
                    <div class="stat-icon" style="<?= $active_tab === $key ? "background: {$tab['color']}18; color: {$tab['color']};" : '' ?>">
                        <i class="fas <?= $tab['icon'] ?>"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-count" style="<?= $active_tab === $key ? "color: {$tab['color']};" : '' ?>">
                            <?= $counts[$key] ?>
                        </div>
                        <div class="stat-label"><?= $tab['label'] ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Orders List -->
    <div id="ordersContainer">
        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <i class="fas <?= $tab_labels[$active_tab]['icon'] ?>"></i>
            <p>No <?= strtolower($tab_labels[$active_tab]['label']) ?> orders</p>
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
                            <?= $order['payment_method'] === 'cash' ? '<i class="fas fa-money-bill-wave me-1"></i>COD' : '<i class="fas fa-mobile-screen-button me-1"></i>' . ucfirst($order['payment_method']) ?>
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

                    <?php if ($active_tab === 'placed'): ?>
                    <div class="order-actions">
                        <button class="btn-accept" onclick="updateStatus(<?= $order['id'] ?>, 'brewing')">
                            <i class="fas fa-check me-1"></i> Accept
                        </button>
                        <button class="btn-cancel-order" onclick="confirmCancel(<?= $order['id'] ?>)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php elseif ($active_tab === 'brewing'): ?>
                    <div class="order-actions">
                        <button class="btn-deliver" onclick="updateStatus(<?= $order['id'] ?>, 'delivery')">
                            <i class="fas fa-person-biking me-1"></i> Out for Delivery
                        </button>
                        <button class="btn-cancel-order" onclick="confirmCancel(<?= $order['id'] ?>)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php elseif ($active_tab === 'delivery'): ?>
                    <div class="order-actions">
                        <button class="btn-done" onclick="updateStatus(<?= $order['id'] ?>, 'done')">
                            <i class="fas fa-check-double me-1"></i> Mark Delivered
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cancel Confirmation Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius:16px; border:none;">
            <div class="modal-body text-center p-4">
                <div style="width:52px;height:52px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                    <i class="fas fa-times-circle" style="color:#ef4444;font-size:22px;"></i>
                </div>
                <h6 class="fw-bold mb-1">Cancel this order?</h6>
                <p class="text-muted small mb-4">This action cannot be undone.</p>
                <div class="d-flex gap-2">
                    <button class="btn btn-light flex-fill" data-bs-dismiss="modal" style="border-radius:10px;">Never mind</button>
                    <button class="btn flex-fill" id="confirmCancelBtn" style="background:#ef4444;color:#fff;border-radius:10px;">Cancel Order</button>
                </div>
            </div>
        </div>
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
/* ── Stat Cards ── */
.stat-card {
    background: #fff;
    border: 1.5px solid #f0e8df;
    border-radius: 16px;
    padding: 18px 16px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all .25s ease;
    cursor: pointer;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
.stat-card-active { background: #fdf9f5; }

.stat-icon {
    width: 46px; height: 46px;
    border-radius: 12px;
    background: #f5ede3;
    color: #9b7e60;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
    transition: all .25s ease;
}
.stat-count {
    font-size: 26px;
    font-weight: 800;
    color: #2d1a0e;
    line-height: 1;
    margin-bottom: 2px;
}
.stat-label {
    font-size: 12px;
    color: #9b7e60;
    font-weight: 500;
}

/* ── Orders List ── */
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
.pay-cash    { background: #f0fdf4; color: #16a34a; }
.pay-online  { background: #eff6ff; color: #2563eb; }
.pay-card    { background: #faf5ff; color: #7c3aed; }

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

/* ── Right Side ── */
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

.btn-accept {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: #fff; border: none;
    padding: 8px 16px; border-radius: 10px;
    font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all .2s;
    white-space: nowrap;
}
.btn-accept:hover { opacity: .88; transform: translateY(-1px); }

.btn-deliver {
    background: linear-gradient(135deg, #60a5fa, #2563eb);
    color: #fff; border: none;
    padding: 8px 16px; border-radius: 10px;
    font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all .2s;
    white-space: nowrap;
}
.btn-deliver:hover { opacity: .88; transform: translateY(-1px); }

.btn-done {
    background: linear-gradient(135deg, #a78bfa, #7c3aed);
    color: #fff; border: none;
    padding: 8px 16px; border-radius: 10px;
    font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all .2s;
    white-space: nowrap;
}
.btn-done:hover { opacity: .88; transform: translateY(-1px); }

.btn-cancel-order {
    width: 34px; height: 34px;
    background: #fef2f2;
    border: 1.5px solid #fecaca;
    color: #ef4444;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 13px;
    transition: all .2s; flex-shrink: 0;
}
.btn-cancel-order:hover { background: #ef4444; color: #fff; border-color: #ef4444; }

/* ── Empty State ── */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9b7e60;
}
.empty-state i { font-size: 40px; opacity: .3; display: block; margin-bottom: 12px; }
.empty-state p { font-size: 14px; font-weight: 500; }
</style>

<script>
let cancelOrderId = null;

function updateStatus(orderId, newStatus) {
    fetch('api/update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, status: newStatus })
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
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(data.message || 'Failed to update order.', false);
        }
    })
    .catch(() => showToast('Network error. Try again.', false));
}

function confirmCancel(orderId) {
    cancelOrderId = orderId;
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}

document.getElementById('confirmCancelBtn').addEventListener('click', function () {
    if (!cancelOrderId) return;
    bootstrap.Modal.getInstance(document.getElementById('cancelModal')).hide();
    updateStatus(cancelOrderId, 'cancelled');
    cancelOrderId = null;
});

function showToast(msg, success) {
    const toast = document.getElementById('pageToast');
    document.getElementById('pageToastMsg').textContent = msg;
    toast.className = 'toast align-items-center border-0 text-white ' + (success ? 'bg-success' : 'bg-danger');
    new bootstrap.Toast(toast, { delay: 4000 }).show();
}
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>