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
    'placed'    => ['label' => 'Order Placed!',       'sub' => 'Your order has been received. We\'ll start brewing soon!', 'icon' => 'fa-receipt',    'color' => '#C97B2B'],
    'brewing'   => ['label' => 'Brewing Your Order!', 'sub' => 'Our baristas are crafting your drinks right now.',          'icon' => 'fa-mug-hot',    'color' => '#F5C842'],
    'delivery'  => ['label' => 'On the Way!',         'sub' => 'Your order is out for delivery. Hang tight!',               'icon' => 'fa-motorcycle', 'color' => '#60a5fa'],
    'done'      => ['label' => 'Delivered!',          'sub' => 'Your order has been delivered. Enjoy your coffee!',         'icon' => 'fa-check',      'color' => '#4ade80'],
    'cancelled' => ['label' => 'Cancelled',           'sub' => 'This order has been cancelled.',                            'icon' => 'fa-times',      'color' => '#f87171'],
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

    <div class="order-footer">
        Order #<?= htmlspecialchars(strtoupper($order['order_number'])) ?>
    </div>

</div>

</body>
</html>