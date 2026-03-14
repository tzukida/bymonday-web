<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

if (!isLoggedIn() || $_SESSION['role'] != 'customer') {
    redirect('customer_login.php');
}

$user_id = $_SESSION['user_id'];
$order_id = intval($_GET['id'] ?? 0);

if (!$order_id) {
    redirect('orders.php');
}

$stmt = $conn->prepare("
    SELECT o.*, c.phone as customer_phone_saved
    FROM orders o
    LEFT JOIN customers c ON o.user_id = c.user_id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    redirect('orders.php');
}

$items_stmt = $conn->prepare("
    SELECT * FROM order_items WHERE order_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

$steps = ['placed', 'brewing', 'delivery', 'done'];
$step_labels = ['Placed', 'Brewing', 'Delivery', 'Done'];
$step_icons  = ['fa-receipt', 'fa-mug-hot', 'fa-motorcycle', 'fa-check'];
$current_step = array_search($order['order_status'], $steps);
if ($current_step === false) $current_step = 0;

$status_hero = [
    'placed'    => ['label' => 'Order Placed',        'sub' => 'Your order has been received. We\'ll start preparing it soon!', 'icon' => 'fa-hourglass-half', 'color' => '#C97B2B'],
    'brewing'   => ['label' => 'Preparing',           'sub' => 'Our team is crafting your order with care. Won\'t be long!',    'icon' => 'fa-mug-hot',        'color' => '#818cf8'],
    'delivery'  => ['label' => 'Out for Delivery',    'sub' => 'Your order is on its way! Expect it to arrive shortly.',        'icon' => 'fa-person-biking',  'color' => '#60a5fa'],
    'done'      => ['label' => 'Delivered!',          'sub' => 'Your order has been delivered. Enjoy your coffee!',             'icon' => 'fa-check',          'color' => '#4ade80'],
    'cancelled' => ['label' => 'Cancelled',           'sub' => 'This order has been cancelled.',                                'icon' => 'fa-times',          'color' => '#f87171'],
];

$hero = $status_hero[$order['order_status']] ?? $status_hero['placed'];
$delivery_fee = $order['delivery_fee'] ?? 50.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order — Coffee by Monday Mornings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #F7F2EC;
            color: #1a0f08;
            min-height: 100vh;
            padding-bottom: 60px;
        }

        /* ── Navbar ── */
        .navbar {
            position: sticky;
            top: 0;
            background: rgba(26,15,8,0.97);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(201,123,43,0.15);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            z-index: 100;
        }

        .back-btn {
            background: rgba(201,123,43,0.15);
            border: 1.5px solid rgba(201,123,43,0.3);
            color: #C97B2B;
            width: 38px; height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 15px;
            transition: all .2s ease;
            flex-shrink: 0;
        }

        .back-btn:hover { background: rgba(201,123,43,0.3); }

        .navbar-title {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            font-weight: 700;
            color: #f7f2ec;
        }

        /* ── Hero status banner ── */
        .hero-banner {
            background: #2e1c0e;
            padding: 40px 24px 36px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-banner::before {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, <?= $hero['color'] ?>22 0%, transparent 70%);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }

        .hero-icon-wrap {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: <?= $hero['color'] ?>22;
            border: 2.5px solid <?= $hero['color'] ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            position: relative;
            z-index: 1;
        }

        .hero-icon-wrap i {
            font-size: 28px;
            color: <?= $hero['color'] ?>;
        }

        .hero-label {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 900;
            color: #f7f2ec;
            position: relative;
            z-index: 1;
        }

        .hero-sub {
            font-size: 13px;
            color: #9b7e60;
            margin-top: 6px;
            position: relative;
            z-index: 1;
        }

        /* ── Progress stepper ── */
        .stepper-wrap {
            background: #2e1c0e;
            padding: 0 24px 32px;
        }

        .stepper {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            max-width: 500px;
            margin: 0 auto;
            position: relative;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 18px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: rgba(201,123,43,0.2);
            z-index: 0;
        }

        .step.completed:not(:last-child)::after {
            background: #C97B2B;
        }

        .step-circle {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(201,123,43,0.1);
            border: 2px solid rgba(201,123,43,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            color: #9b7e60;
            position: relative;
            z-index: 1;
            transition: all .3s ease;
        }

        .step.completed .step-circle {
            background: #C97B2B;
            border-color: #C97B2B;
            color: #fff;
        }

        .step.active .step-circle {
            background: rgba(201,123,43,0.2);
            border-color: #C97B2B;
            color: #C97B2B;
            box-shadow: 0 0 0 4px rgba(201,123,43,0.15);
        }

        .step-label {
            font-size: 10px;
            font-weight: 600;
            color: #9b7e60;
            margin-top: 8px;
            text-align: center;
            letter-spacing: 0.3px;
        }

        .step.completed .step-label,
        .step.active .step-label {
            color: #C97B2B;
        }

        /* ── Content area ── */
        .content {
            padding: 24px;
            max-width: 700px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* ── Info cards ── */
        .info-card {
            background: #fff;
            border: 1px solid rgba(201,123,43,0.15);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(26,15,8,0.07);
        }

        .info-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(201,123,43,0.1);
        }

        .info-card-header-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: rgba(201,123,43,0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #C97B2B;
            font-size: 13px;
        }

        .info-card-header span {
            font-size: 13px;
            font-weight: 700;
            color: #1a0f08;
            letter-spacing: 0.3px;
        }

        .info-card-body {
            padding: 16px 20px;
        }

        /* ── Contact details ── */
        .contact-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
        }

        .contact-row:last-child { margin-bottom: 0; }

        .contact-row i {
            font-size: 13px;
            color: #C97B2B;
            margin-top: 2px;
            width: 16px;
        }

        .contact-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #9b7e60;
            margin-bottom: 2px;
        }

        .contact-value {
            font-size: 14px;
            color: #1a0f08;
            font-weight: 500;
        }

        .contact-hint {
            font-size: 11px;
            color: #9b7e60;
            margin-top: 2px;
        }

        /* ── Order summary ── */
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(201,123,43,0.08);
        }

        .summary-item:last-child { border-bottom: none; }

        .summary-item-name {
            font-size: 14px;
            color: #1a0f08;
            font-weight: 500;
        }

        .summary-item-qty {
            font-size: 12px;
            color: #9b7e60;
        }

        .summary-item-price {
            font-size: 14px;
            color: #1a0f08;
            font-weight: 600;
        }

        .summary-divider {
            height: 1px;
            background: rgba(201,123,43,0.12);
            margin: 4px 0;
        }

        .summary-fee-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            color: #7a5c3a;
        }

        .summary-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0 4px;
            border-top: 1.5px solid rgba(201,123,43,0.2);
            margin-top: 4px;
        }

        .summary-total-label {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #7a5c3a;
        }

        .summary-total-amount {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            font-weight: 900;
            color: #C97B2B;
        }

        /* ── Delivery info ── */
        .delivery-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .delivery-row i {
            font-size: 14px;
            color: #C97B2B;
            margin-top: 2px;
        }

        .delivery-address {
            font-size: 14px;
            color: #1a0f08;
            font-weight: 500;
            line-height: 1.5;
        }

        /* ── Order number footer ── */
        .order-footer {
            text-align: center;
            font-size: 12px;
            color: #9b7e60;
            padding: 8px 24px 0;
            letter-spacing: 0.5px;
        }

        .driver-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(201,123,43,0.12);
            border: 1px solid rgba(201,123,43,0.3);
            color: #C97B2B;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 50px;
            margin-top: 8px;
        }

        .driver-status-badge i {
            font-size: 8px;
            color: #C97B2B;
            width: auto;
            margin-top: 0;
        }

        .btn-cancel-order {
            width: 100%;
            background: transparent;
            border: 1.5px solid rgba(248,113,113,0.4);
            color: #f87171;
            border-radius: 12px;
            padding: 13px 24px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all .2s ease;
            margin-top: 8px;
        }
        .btn-cancel-order:hover {
            background: rgba(248,113,113,0.08);
            border-color: #f87171;
        }

        /* Confirm modal */
        .confirm-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,8,4,0.80);
            backdrop-filter: blur(10px);
            z-index: 2000;
            align-items: center; justify-content: center;
            padding: 24px;
        }
        .confirm-overlay.open { display: flex; }
        .confirm-box {
            background: #fff; border-radius: 24px;
            width: 100%; max-width: 380px;
            padding: 32px 28px;
            text-align: center;
            box-shadow: 0 24px 64px rgba(0,0,0,0.3);
        }
        .confirm-icon {
            width: 60px; height: 60px; border-radius: 50%;
            background: rgba(248,113,113,0.1);
            border: 2px solid rgba(248,113,113,0.3);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            font-size: 22px; color: #f87171;
        }
        .confirm-title {
            font-family: 'Playfair Display', serif;
            font-size: 20px; font-weight: 900;
            color: #1a0f08; margin-bottom: 8px;
        }
        .confirm-sub {
            font-size: 13px; color: #7a5c3a;
            margin-bottom: 24px; line-height: 1.5;
        }
        .confirm-actions {
            display: flex; gap: 10px;
        }
        .confirm-btn-cancel {
            flex: 1; background: #F7F2EC;
            border: 1.5px solid rgba(201,123,43,0.2);
            color: #7a5c3a; border-radius: 12px;
            padding: 12px; font-family: 'DM Sans', sans-serif;
            font-size: 14px; font-weight: 600; cursor: pointer;
            transition: all .2s;
        }
        .confirm-btn-cancel:hover { border-color: #C97B2B; color: #C97B2B; }
        .confirm-btn-confirm {
            flex: 1; background: #f87171;
            border: none; color: #fff; border-radius: 12px;
            padding: 12px; font-family: 'DM Sans', sans-serif;
            font-size: 14px; font-weight: 700; cursor: pointer;
            transition: all .2s;
        }
        .confirm-btn-confirm:hover { background: #ef4444; }

        .confirm-header { margin-bottom: 20px; }

        .confirm-reasons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
            justify-content: center;
        }

        .cust-reason-btn {
            padding: 8px 14px;
            border-radius: 20px;
            border: 1.5px solid rgba(201,123,43,0.25);
            background: #F7F2EC;
            color: #7a5c3a;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: all .2s;
        }
        .cust-reason-btn:hover { border-color: #C97B2B; color: #C97B2B; }
        .cust-reason-btn.selected { background: rgba(248,113,113,0.08); border-color: #f87171; color: #ef4444; }

        #custCustomReason {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid rgba(201,123,43,0.2);
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            color: #1a0f08;
            margin-bottom: 20px;
            outline: none;
            transition: border-color .2s;
        }
        #custCustomReason:focus { border-color: #C97B2B; }

        .confirm-btn-confirm:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <a href="orders.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
    <span class="navbar-title">Track Order</span>
</nav>

<!-- Hero Status Banner -->
<div class="hero-banner">
    <div class="hero-icon-wrap">
        <i class="fas <?= $hero['icon'] ?>"></i>
    </div>
    <div class="hero-label"><?= $hero['label'] ?></div>
    <div class="hero-sub"><?= $hero['sub'] ?></div>
</div>

<!-- Progress Stepper -->
<?php if ($order['order_status'] !== 'cancelled'): ?>
<div class="stepper-wrap">
    <div class="stepper">
        <?php foreach ($steps as $i => $step): ?>
            <?php
                $class = '';
                if ($i < $current_step) $class = 'completed';
                elseif ($i === $current_step) $class = 'active';
            ?>
            <div class="step <?= $class ?>">
                <div class="step-circle">
                    <i class="fas <?= $step_icons[$i] ?>"></i>
                </div>
                <div class="step-label"><?= $step_labels[$i] ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Content -->
<div class="content">

    <?php if ($order['order_status'] === 'cancelled' && !empty($order['cancel_reason'])): ?>
    <div style="background:#fef2f2; border:1px solid rgba(248,113,113,0.25); border-radius:12px; padding:14px 20px; display:flex; align-items:center; gap:10px;">
        <i class="fas fa-circle-exclamation" style="color:#f87171; font-size:15px; flex-shrink:0;"></i>
        <div style="font-size:11px; font-weight:700; color:#f87171; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px;">
            <?= ($order['cancelled_by'] === 'staff') ? 'Cancelled by Store' : 'Cancelled by You' ?>
        </div>
        <div style="font-size:13px; color:#7a5c3a;"><?= htmlspecialchars($order['cancel_reason']) ?></div>
    </div>
    <?php endif; ?>

    <!-- Driver Info (delivery status only) -->
    <?php if ($order['order_status'] === 'delivery'): ?>
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-header-icon"><i class="fas fa-person-biking"></i></div>
            <span>Your Driver</span>
        </div>
        <div class="info-card-body">
            <div class="contact-row">
                <i class="fas fa-user"></i>
                <div>
                    <div class="contact-label">Name</div>
                    <div class="contact-value">To be assigned</div>
                </div>
            </div>
            <div class="contact-row">
                <i class="fas fa-phone"></i>
                <div>
                    <div class="contact-label">Phone</div>
                    <div class="contact-value">—</div>
                </div>
            </div>
            <div class="driver-status-badge">
                <i class="fas fa-circle"></i> On the way to you
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contact Details -->
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-header-icon"><i class="fas fa-user"></i></div>
            <span>Your Contact Details</span>
        </div>
        <div class="info-card-body">
            <div class="contact-row">
                <i class="fas fa-user"></i>
                <div>
                    <div class="contact-label">Name</div>
                    <div class="contact-value"><?= htmlspecialchars($order['customer_name']) ?></div>
                </div>
            </div>
            <div class="contact-row">
                <i class="fas fa-phone"></i>
                <div>
                    <div class="contact-label">Phone</div>
                    <?php if (!empty($order['customer_phone'])): ?>
                        <div class="contact-value"><?= htmlspecialchars($order['customer_phone']) ?></div>
                    <?php else: ?>
                        <div class="contact-hint">No phone number saved. Update your profile to include it on future orders.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Summary -->
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-header-icon"><i class="fas fa-receipt"></i></div>
            <span>Order Summary</span>
        </div>
        <div class="info-card-body">
            <?php foreach ($items as $item): ?>
                <div class="summary-item">
                    <div>
                        <div class="summary-item-name"><?= htmlspecialchars($item['product_name']) ?><?= $item['size'] ? ' (' . strtoupper($item['size']) . ')' : '' ?></div>
                        <div class="summary-item-qty">x<?= $item['quantity'] ?></div>
                    </div>
                    <div class="summary-item-price">₱<?= number_format($item['subtotal'], 2) ?></div>
                </div>
            <?php endforeach; ?>

            <div class="summary-fee-row">
                <span>Subtotal</span>
                <span>₱<?= number_format($order['subtotal'], 2) ?></span>
            </div>
            <div class="summary-fee-row">
                <span>Delivery Fee</span>
                <span>₱<?= number_format($delivery_fee, 2) ?></span>
            </div>
            <div class="summary-total-row">
                <span class="summary-total-label">Total</span>
                <span class="summary-total-amount">₱<?= number_format($order['total'] + $delivery_fee, 2) ?></span>
            </div>
        </div>
    </div>

    <!-- Delivery Info -->
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-header-icon"><i class="fas fa-location-dot"></i></div>
            <span>Delivery Info</span>
        </div>
        <div class="info-card-body">
            <div class="delivery-row">
                <i class="fas fa-location-dot"></i>
                <div class="delivery-address"><?= htmlspecialchars($order['customer_address']) ?></div>
            </div>
        </div>
    </div>

    <?php if ($order['order_status'] === 'placed'): ?>
    <button class="btn-cancel-order" onclick="openCancelConfirm()">
        <i class="fas fa-times-circle"></i> Cancel Order
    </button>
    <?php endif; ?>

    <div class="order-footer">
        Order #<?= htmlspecialchars(strtoupper($order['order_number'])) ?>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="confirm-overlay" id="confirmModal">
        <div class="confirm-box">
            <div class="confirm-header">
                <div class="confirm-icon"><i class="fas fa-times-circle"></i></div>
                <div class="confirm-title">Cancel Order</div>
                <div class="confirm-sub">Select a reason for cancellation</div>
            </div>
            <div class="confirm-reasons">
                <button class="cust-reason-btn" onclick="selectCustReason(this, 'Changed my mind')">Changed my mind</button>
                <button class="cust-reason-btn" onclick="selectCustReason(this, 'Ordered by mistake')">Ordered by mistake</button>
                <button class="cust-reason-btn" onclick="selectCustReason(this, 'Found a better option')">Found a better option</button>
                <button class="cust-reason-btn" onclick="selectCustReason(this, 'Taking too long')">Taking too long</button>
            </div>
            <input type="text" id="custCustomReason" placeholder="Or type custom reason..."
                oninput="onCustCustomReason(this)">
            <div class="confirm-actions">
                <button class="confirm-btn-cancel" onclick="closeCancelConfirm()">Keep Order</button>
                <button class="confirm-btn-confirm" id="custConfirmBtn" disabled onclick="confirmCancel()">Confirm</button>
            </div>
        </div>
    </div>

</div>

<script>
let custSelectedReason = null;

function openCancelConfirm() {
    custSelectedReason = null;
    document.querySelectorAll('.cust-reason-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('custCustomReason').value = '';
    document.getElementById('custConfirmBtn').disabled = true;
    document.getElementById('confirmModal').classList.add('open');
}

function closeCancelConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
}

function selectCustReason(el, reason) {
    document.querySelectorAll('.cust-reason-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    custSelectedReason = reason;
    document.getElementById('custCustomReason').value = '';
    document.getElementById('custConfirmBtn').disabled = false;
}

function onCustCustomReason(el) {
    document.querySelectorAll('.cust-reason-btn').forEach(b => b.classList.remove('selected'));
    custSelectedReason = el.value.trim() || null;
    document.getElementById('custConfirmBtn').disabled = !custSelectedReason;
}

function confirmCancel() {
    if (!custSelectedReason) return;
    fetch('cancel_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: <?= $order_id ?>, reason: custSelectedReason })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Could not cancel order.');
            closeCancelConfirm();
        }
    })
    .catch(() => {
        alert('Something went wrong. Please try again.');
        closeCancelConfirm();
    });
}
</script>

<?php require_once BASE_PATH . '/includes/order_notif.php'; ?>
</body>
</html>