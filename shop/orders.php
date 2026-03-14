<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

if (!isLoggedIn() || $_SESSION['role'] != 'customer') {
    redirect('customer_login.php');
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT o.*, 
           GROUP_CONCAT(oi.product_name ORDER BY oi.id SEPARATOR ', ') as items_summary
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — Coffee by Monday Mornings</title>
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
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        text-decoration: none;
        font-size: 15px;
        transition: all .2s ease;
        flex-shrink: 0;
    }

    .back-btn:hover {
        background: rgba(201,123,43,0.3);
    }

    .navbar-title {
        font-family: 'Playfair Display', serif;
        font-size: 18px;
        font-weight: 700;
        color: #f7f2ec;
    }

    .page-header {
        padding: 32px 24px 16px;
        max-width: 700px;
        margin: 0 auto;
    }

    .page-header h1 {
        font-family: 'Playfair Display', serif;
        font-size: 32px;
        font-weight: 900;
        color: #1a0f08;
    }

    .page-header p {
        font-size: 14px;
        color: #7a5c3a;
        margin-top: 4px;
    }

    .orders-list {
        padding: 0 24px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        max-width: 700px;
        margin: 0 auto;
    }

    .order-card {
        background: #fff;
        border: 1px solid rgba(201,123,43,0.18);
        border-radius: 18px;
        padding: 20px;
        text-decoration: none;
        display: block;
        transition: all .25s ease;
        box-shadow: 0 2px 12px rgba(26,15,8,0.07);
    }

    .order-card:hover {
        border-color: rgba(201,123,43,0.45);
        transform: translateY(-2px);
        box-shadow: 0 10px 28px rgba(201,123,43,0.15);
    }

    .order-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .order-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .order-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: rgba(201,123,43,0.12);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #C97B2B;
        font-size: 16px;
        flex-shrink: 0;
    }

    .order-number {
        font-size: 13px;
        font-weight: 700;
        color: #1a0f08;
        letter-spacing: 0.5px;
    }

    .order-date {
        font-size: 11px;
        color: #9b7e60;
        margin-top: 2px;
    }

    .status-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 5px 14px;
        border-radius: 50px;
        text-transform: capitalize;
        white-space: nowrap;
    }

    .status-placed    { background: rgba(201,123,43,0.12); color: #a0621a; }
    .status-brewing   { background: rgba(245,180,30,0.15); color: #996b00; }
    .status-delivery  { background: rgba(59,130,246,0.12); color: #1d64c8; }
    .status-done      { background: rgba(34,197,94,0.12);  color: #15803d; }
    .status-cancelled { background: rgba(239,68,68,0.12);  color: #b91c1c; }

    .order-items-summary {
        font-size: 13px;
        color: #7a5c3a;
        margin-bottom: 12px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .order-total {
        font-size: 17px;
        font-weight: 800;
        color: #C97B2B;
        font-family: 'Playfair Display', serif;
    }

    .empty-state {
        text-align: center;
        padding: 80px 24px;
        color: #9b7e60;
    }

    .empty-state i {
        font-size: 52px;
        margin-bottom: 16px;
        display: block;
        color: #C97B2B;
        opacity: 0.4;
    }

    .empty-state h3 {
        font-family: 'Playfair Display', serif;
        font-size: 22px;
        color: #1a0f08;
        margin-bottom: 8px;
    }

    .empty-state p {
        font-size: 14px;
        color: #7a5c3a;
    }

    .empty-state a {
        display: inline-block;
        margin-top: 20px;
        padding: 12px 28px;
        background: linear-gradient(135deg, #C97B2B, #E09A4A);
        color: #fff;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        box-shadow: 0 6px 18px rgba(201,123,43,0.35);
    }
</style>
</head>
<body>

<nav class="navbar">
    <a href="menu.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
    <span class="navbar-title">Back to Menu</span>
</nav>

<div class="page-header" style="max-width:600px;margin:0 auto;">
    <h1>My Orders</h1>
    <p>Track and review your past orders.</p>
</div>

<div class="orders-list">
    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <i class="fas fa-bag-shopping"></i>
            <h3>No orders yet</h3>
            <p>Looks like you haven't placed any orders yet.</p>
            <a href="menu.php">Browse Menu</a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <a href="track-order.php?id=<?= $order['id'] ?>" class="order-card">
                <div class="order-card-top">
                    <div class="order-left">
                        <div class="order-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div>
                            <div class="order-number">#<?= htmlspecialchars(strtoupper($order['order_number'])) ?></div>
                            <div class="order-date"><?= date('M j, Y', strtotime($order['created_at'])) ?></div>
                        </div>
                    </div>
                    <span class="status-badge status-<?= $order['order_status'] ?>">
                        <?= ucfirst($order['order_status']) ?>
                    </span>
                </div>
                <div class="order-items-summary">
                    <?= htmlspecialchars($order['items_summary'] ?? 'No items') ?>
                </div>
                <?php if ($order['order_status'] === 'cancelled' && !empty($order['cancel_reason'])): ?>
                <div style="display:inline-flex; align-items:center; gap:6px; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); color:#b91c1c; font-size:11px; font-weight:600; padding:4px 10px; border-radius:20px; margin-bottom:8px;">
                    <i class="fas fa-circle-exclamation"></i>
                    <?= ($order['cancelled_by'] === 'staff') ? 'Cancelled by Store' : 'Cancelled by You' ?>
                    — <?= htmlspecialchars($order['cancel_reason']) ?>
                </div>
                <?php endif; ?>
                <div class="order-total">
                    ₱<?= number_format($order['total'], 2) ?>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/includes/order_notif.php'; ?>

</body>
</html>