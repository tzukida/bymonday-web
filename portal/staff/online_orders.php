<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';
requireStaff();

$conn = new mysqli('localhost', 'root', '', 'coffee_shop');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// Get counts per status
$counts = [];
$statuses = ['placed', 'brewing', 'delivery', 'done'];
foreach ($statuses as $s) {
    $r = $conn->prepare("SELECT COUNT(*) FROM orders WHERE order_status = ?");
    $r->bind_param("s", $s);
    $r->execute();
    $r->bind_result($counts[$s]);
    $r->fetch();
    $r->close();
}

// Get active tab
$active = $_GET['status'] ?? 'placed';
if (!in_array($active, $statuses)) $active = 'placed';

// Get orders for active tab
$stmt = $conn->prepare("
    SELECT o.*, 
           GROUP_CONCAT(oi.product_name, '|', oi.quantity, '|', oi.price, '|', oi.subtotal ORDER BY oi.id SEPARATOR ';;') as items_raw
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.order_status = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->bind_param("s", $active);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Online Orders';
require_once BASE_PATH . '/includes/header.php';
?>

<style>
    .orders-layout {
        display: flex;
        gap: 24px;
        align-items: flex-start;
    }

    /* ── Status Filter Panel ── */
    .status-panel {
        width: 220px;
        flex-shrink: 0;
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e8ddd5;
        overflow: hidden;
        position: sticky;
        top: 24px;
    }

    .status-panel-title {
        padding: 16px 20px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #9a7c65;
        border-bottom: 1px solid #f0e8e0;
    }

    .status-filter-btn {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        background: none;
        border: none;
        border-left: 3px solid transparent;
        cursor: pointer;
        text-align: left;
        transition: all .2s ease;
        text-decoration: none;
        color: #5a3e2b;
        font-size: 14px;
        font-weight: 500;
    }

    .status-filter-btn:hover {
        background: #fdf6ef;
        color: #C97B2B;
    }

    .status-filter-btn.active {
        background: #fdf6ef;
        border-left-color: #C97B2B;
        color: #C97B2B;
        font-weight: 700;
    }

    .status-filter-btn .status-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        flex-shrink: 0;
    }

    .status-filter-btn .status-label { flex: 1; }

    .status-badge {
        background: #C97B2B;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        min-width: 22px;
        text-align: center;
    }

    .status-badge.zero {
        background: #e8ddd5;
        color: #9a7c65;
    }

    /* Status icon colors */
    .icon-placed    { background: rgba(201,123,43,0.12); color: #C97B2B; }
    .icon-brewing   { background: rgba(129,140,248,0.12); color: #818cf8; }
    .icon-delivery  { background: rgba(96,165,250,0.12); color: #60a5fa; }
    .icon-done      { background: rgba(74,222,128,0.12); color: #4ade80; }

    /* ── Orders Panel ── */
    .orders-panel { flex: 1; min-width: 0; }

    .orders-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }

    .orders-panel-title {
        font-size: 18px;
        font-weight: 700;
        color: #2d1a0e;
    }

    .orders-panel-count {
        font-size: 13px;
        color: #9a7c65;
    }

    /* ── Order Cards ── */
    .orders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 16px;
    }

    .order-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e8ddd5;
        overflow: hidden;
        transition: box-shadow .2s ease;
    }

    .order-card:hover {
        box-shadow: 0 4px 20px rgba(201,123,43,0.12);
    }

    .order-card-header {
        padding: 14px 16px;
        background: #fdf6ef;
        border-bottom: 1px solid #f0e8e0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .order-icon-wrap {
        width: 38px;
        height: 38px;
        background: rgba(201,123,43,0.15);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #C97B2B;
        font-size: 15px;
        flex-shrink: 0;
    }

    .order-number {
        font-size: 14px;
        font-weight: 700;
        color: #2d1a0e;
    }

    .order-meta {
        font-size: 12px;
        color: #9a7c65;
        margin-top: 2px;
    }

    .order-total {
        margin-left: auto;
        font-size: 16px;
        font-weight: 800;
        color: #C97B2B;
    }

    .order-card-body { padding: 14px 16px; }

    .order-info-row {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        font-size: 13px;
        color: #5a3e2b;
        margin-bottom: 8px;
    }

    .order-info-row i {
        color: #C97B2B;
        margin-top: 2px;
        width: 14px;
        flex-shrink: 0;
    }

    .order-divider {
        border: none;
        border-top: 1px dashed #e8ddd5;
        margin: 12px 0;
    }

    .order-item-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
        color: #5a3e2b;
        padding: 3px 0;
    }

    .order-item-name { font-weight: 500; }
    .order-item-price { color: #9a7c65; }

    .order-totals {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #f0e8e0;
    }

    .order-totals-row {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: #9a7c65;
        margin-bottom: 4px;
    }

    .order-totals-row.total {
        font-size: 14px;
        font-weight: 700;
        color: #2d1a0e;
        margin-top: 6px;
    }

    /* ── Action Buttons ── */
    .order-card-actions {
        padding: 12px 16px;
        border-top: 1px solid #f0e8e0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .btn-accept {
        width: 100%;
        background: #C97B2B;
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 11px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        transition: all .2s;
    }

    .btn-accept:hover { background: #a8611e; }

    .btn-cancel-order {
        width: 100%;
        background: transparent;
        color: #f87171;
        border: 1.5px solid rgba(248,113,113,0.35);
        border-radius: 10px;
        padding: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        transition: all .2s;
    }

    .btn-cancel-order:hover {
        background: rgba(248,113,113,0.06);
        border-color: #f87171;
    }

    /* ── Empty State ── */
    .empty-state {
        text-align: center;
        padding: 80px 20px;
        color: #9a7c65;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.4;
        display: block;
    }

    .empty-state p { font-size: 15px; }

    /* ── Toast ── */
    .toast-wrap {
        position: fixed;
        bottom: 28px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        align-items: center;
    }

    .toast-msg {
        background: #2d1a0e;
        color: #fff;
        padding: 12px 22px;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        animation: toastIn .3s ease;
    }

    .toast-msg.success i { color: #4ade80; }
    .toast-msg.error i { color: #f87171; }

    @keyframes toastIn {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Confirm Modal ── */
    .confirm-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15,8,4,0.75);
        backdrop-filter: blur(8px);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }

    .confirm-overlay.open { display: flex; }

    .confirm-box {
        background: #fff;
        border-radius: 20px;
        width: 100%;
        max-width: 360px;
        padding: 28px 24px;
        text-align: center;
    }

    .confirm-icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: rgba(248,113,113,0.1);
        border: 2px solid rgba(248,113,113,0.25);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 14px;
        font-size: 20px;
        color: #f87171;
    }

    .confirm-title {
        font-size: 18px;
        font-weight: 800;
        color: #2d1a0e;
        margin-bottom: 6px;
    }

    .confirm-sub {
        font-size: 13px;
        color: #7a5c3a;
        margin-bottom: 22px;
        line-height: 1.5;
    }

    .confirm-actions { display: flex; gap: 10px; }

    .confirm-keep {
        flex: 1;
        background: #f5ede4;
        border: 1.5px solid rgba(201,123,43,0.2);
        color: #7a5c3a;
        border-radius: 10px;
        padding: 11px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all .2s;
    }

    .confirm-keep:hover { border-color: #C97B2B; color: #C97B2B; }

    .confirm-yes {
        flex: 1;
        background: #f87171;
        border: none;
        color: #fff;
        border-radius: 10px;
        padding: 11px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all .2s;
    }

    .confirm-yes:hover { background: #ef4444; }
</style>

<div class="page-header">
    <h1 class="page-title">Online Orders</h1>
    <p class="page-subtitle">Manage and process customer online orders</p>
</div>

<div class="orders-layout">

    <!-- ── Status Filter Panel ── -->
    <div class="status-panel">
        <div class="status-panel-title">Order Status</div>

        <?php
        $tabs = [
            'placed'   => ['label' => 'Pending',         'icon' => 'fa-hourglass-half',  'cls' => 'icon-placed'],
            'brewing'  => ['label' => 'Preparing',        'icon' => 'fa-mug-hot',         'cls' => 'icon-brewing'],
            'delivery' => ['label' => 'Out for Delivery', 'icon' => 'fa-person-biking',   'cls' => 'icon-delivery'],
            'done'     => ['label' => 'Delivered',        'icon' => 'fa-check-circle',    'cls' => 'icon-done'],
        ];
        foreach ($tabs as $key => $tab):
            $isActive = $active === $key;
            $count    = $counts[$key] ?? 0;
        ?>
        <a href="?status=<?= $key ?>"
           class="status-filter-btn <?= $isActive ? 'active' : '' ?>">
            <span class="status-icon <?= $tab['cls'] ?>">
                <i class="fas <?= $tab['icon'] ?>"></i>
            </span>
            <span class="status-label"><?= $tab['label'] ?></span>
            <span class="status-badge <?= $count === 0 ? 'zero' : '' ?>"><?= $count ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── Orders Panel ── -->
    <div class="orders-panel">
        <div class="orders-panel-header">
            <div class="orders-panel-title"><?= $tabs[$active]['label'] ?></div>
            <div class="orders-panel-count"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?></div>
        </div>

        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No <?= strtolower($tabs[$active]['label']) ?> orders right now</p>
        </div>

        <?php else: ?>
        <div class="orders-grid">
            <?php foreach ($orders as $order):
                // Parse items
                $items = [];
                if (!empty($order['items_raw'])) {
                    foreach (explode(';;', $order['items_raw']) as $raw) {
                        $parts = explode('|', $raw);
                        if (count($parts) === 4) {
                            $items[] = [
                                'name'     => $parts[0],
                                'qty'      => $parts[1],
                                'price'    => $parts[2],
                                'subtotal' => $parts[3],
                            ];
                        }
                    }
                }
                $item_count    = count($items);
                $payment_label = ['cash' => 'Cash', 'gcash' => 'GCash', 'paymaya' => 'Maya', 'card' => 'Card'][$order['payment_method']] ?? ucfirst($order['payment_method']);
            ?>
            <div class="order-card" id="card-<?= $order['id'] ?>">
                <div class="order-card-header">
                    <div class="order-icon-wrap">
                        <i class="fas fa-bag-shopping"></i>
                    </div>
                    <div>
                        <div class="order-number">Order #<?= strtoupper(substr($order['order_number'], -6)) ?></div>
                        <div class="order-meta"><?= $item_count ?> item<?= $item_count !== 1 ? 's' : '' ?> &bull; <?= $payment_label ?></div>
                    </div>
                    <div class="order-total">₱<?= number_format($order['total'], 2) ?></div>
                </div>

                <div class="order-card-body">
                    <div class="order-info-row">
                        <i class="fas fa-user"></i>
                        <span>Customer: <strong><?= htmlspecialchars($order['customer_name']) ?></strong></span>
                    </div>
                    <div class="order-info-row">
                        <i class="fas fa-location-dot"></i>
                        <span><?= htmlspecialchars($order['customer_address']) ?></span>
                    </div>

                    <hr class="order-divider">

                    <?php foreach ($items as $item): ?>
                    <div class="order-item-row">
                        <span class="order-item-name">
                            &bull; <?= htmlspecialchars($item['name']) ?> &times;<?= $item['qty'] ?>
                        </span>
                        <span class="order-item-price">₱<?= number_format($item['price'], 2) ?></span>
                    </div>
                    <?php endforeach; ?>

                    <div class="order-totals">
                        <div class="order-totals-row">
                            <span>Subtotal</span>
                            <span>₱<?= number_format($order['subtotal'], 2) ?></span>
                        </div>
                        <div class="order-totals-row">
                            <span>Delivery Fee</span>
                            <span>₱<?= number_format($order['delivery_fee'] ?? 50, 2) ?></span>
                        </div>
                        <div class="order-totals-row total">
                            <span>Total</span>
                            <span>₱<?= number_format($order['total'], 2) ?></span>
                        </div>
                    </div>
                </div>

                <?php if (in_array($active, ['placed', 'brewing'])): ?>
                <div class="order-card-actions">
                    <?php if ($active === 'placed'): ?>
                    <button class="btn-accept" onclick="acceptOrder(<?= $order['id'] ?>, this)">
                        <i class="fas fa-check"></i> Accept Order
                    </button>
                    <?php endif; ?>
                    <button class="btn-cancel-order" onclick="openCancel(<?= $order['id'] ?>)">
                        <i class="fas fa-times-circle"></i> Cancel Order
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cancel Confirm Modal -->
<div class="confirm-overlay" id="confirmModal">
    <div class="confirm-box">
        <div class="confirm-icon"><i class="fas fa-times-circle"></i></div>
        <div class="confirm-title">Cancel Order?</div>
        <div class="confirm-sub">This will cancel the order and notify the customer. This cannot be undone.</div>
        <div class="confirm-actions">
            <button class="confirm-keep" onclick="closeCancel()">Keep It</button>
            <button class="confirm-yes" onclick="confirmCancel()">Yes, Cancel</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast-wrap" id="toastWrap"></div>

<script>
let cancelOrderId = null;

function acceptOrder(orderId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    fetch('<?= getBaseURL() ?>/api/update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, status: 'brewing' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Order accepted — now preparing!');
            setTimeout(() => {
                document.getElementById('card-' + orderId).remove();
                checkEmpty();
            }, 800);
        } else {
            showToast('error', data.message || 'Something went wrong.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Accept Order';
        }
    });
}

function openCancel(orderId) {
    cancelOrderId = orderId;
    document.getElementById('confirmModal').classList.add('open');
}

function closeCancel() {
    cancelOrderId = null;
    document.getElementById('confirmModal').classList.remove('open');
}

function confirmCancel() {
    if (!cancelOrderId) return;
    closeCancel();

    fetch('<?= getBaseURL() ?>/api/update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: cancelOrderId, status: 'cancelled' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Order has been cancelled.');
            setTimeout(() => {
                document.getElementById('card-' + cancelOrderId).remove();
                checkEmpty();
            }, 800);
        } else {
            showToast('error', data.message || 'Something went wrong.');
        }
    });
}

function checkEmpty() {
    const grid = document.querySelector('.orders-grid');
    if (grid && grid.children.length === 0) {
        grid.outerHTML = `<div class="empty-state"><i class="fas fa-inbox"></i><p>No orders right now</p></div>`;
    }
}

function showToast(type, msg) {
    const wrap = document.getElementById('toastWrap');
    const el = document.createElement('div');
    el.className = 'toast-msg ' + type;
    el.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${msg}`;
    wrap.appendChild(el);
    setTimeout(() => el.remove(), 3500);
}
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>