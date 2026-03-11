<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

if (!isLoggedIn() || $_SESSION['role'] != 'customer') {
    redirect('customer_login.php');
}

$user_id = $_SESSION['user_id'];

// Fetch favorited items from portal via DB
$stmt = $conn->prepare("SELECT menu_item_id FROM favorites WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$fav_result = $stmt->get_result();
$favorite_ids = [];
while ($row = $fav_result->fetch_assoc()) {
    $favorite_ids[] = $row['menu_item_id'];
}
$stmt->close();

// Fetch all menu items from portal API and filter to favorites only
$portal_api_url = 'http://localhost/bymonday/portal/api/menu_items.php';
$response = @file_get_contents($portal_api_url);
$favorites = [];

if ($response !== false) {
    $data = json_decode($response, true);
    if (!empty($data['success']) && !empty($data['items'])) {
        foreach ($data['items'] as $item) {
            if (in_array($item['id'], $favorite_ids)) {
                if (!empty($item['image_url'])) {
                    $item['image_full_url'] = 'http://localhost/bymonday/portal' . $item['image_url'];
                } else {
                    $item['image_full_url'] = null;
                }
                $favorites[] = $item;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites — Coffee by Monday Mornings</title>
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
            position: sticky; top: 0;
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
            display: flex; align-items: center; justify-content: center;
            text-decoration: none;
            font-size: 15px;
            transition: all .2s ease;
            flex-shrink: 0;
        }
        .back-btn:hover { background: rgba(201,123,43,0.3); }

        .navbar-title {
            font-family: 'Playfair Display', serif;
            font-size: 18px; font-weight: 700;
            color: #f7f2ec;
        }

        .page-header {
            padding: 32px 24px 16px;
            max-width: 900px;
            margin: 0 auto;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 32px; font-weight: 900;
            color: #1a0f08;
        }

        .page-header p {
            font-size: 14px; color: #7a5c3a;
            margin-top: 4px;
        }

        /* ── Grid ── */
        .favorites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            padding: 0 24px;
            max-width: 900px;
            margin: 0 auto;
        }

        /* ── Card ── */
        .fav-card {
            background: #fff;
            border: 1px solid rgba(201,123,43,0.15);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(26,15,8,0.07);
            transition: all .25s ease;
            display: flex;
            flex-direction: column;
        }

        .fav-card:hover {
            border-color: rgba(201,123,43,0.4);
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(201,123,43,0.15);
        }

        .fav-card-img-wrap {
            position: relative;
            height: 160px;
            overflow: hidden;
        }

        .fav-card-img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform .4s ease;
        }

        .fav-card:hover .fav-card-img { transform: scale(1.05); }

        .fav-card-category {
            position: absolute; top: 10px; left: 10px;
            background: rgba(26,15,8,0.72);
            backdrop-filter: blur(4px);
            color: #f7f2ec;
            font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px;
            padding: 4px 10px; border-radius: 50px;
        }

        .fav-card-unfav {
            position: absolute; top: 10px; right: 10px;
            width: 32px; height: 32px;
            border-radius: 50%;
            background: rgba(26,15,8,0.6);
            backdrop-filter: blur(4px);
            border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: #e63946; font-size: 13px;
            transition: all .2s ease;
        }
        .fav-card-unfav:hover { background: rgba(230,57,70,0.85); color: #fff; transform: scale(1.1); }

        .fav-card-body {
            padding: 16px;
            display: flex; flex-direction: column;
            gap: 6px; flex: 1;
        }

        .fav-card-name {
            font-family: 'Playfair Display', serif;
            font-size: 16px; font-weight: 700;
            color: #1a0f08; line-height: 1.2;
        }

        .fav-card-desc {
            font-size: 12px; color: #7a5c3a;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .fav-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 10px;
        }

        .fav-card-price {
            font-family: 'Playfair Display', serif;
            font-size: 18px; font-weight: 900;
            color: #C97B2B;
        }

        .fav-card-add {
            background: linear-gradient(135deg, #C97B2B, #E09A4A);
            color: #fff; border: none;
            border-radius: 50px;
            padding: 8px 16px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px; font-weight: 700;
            cursor: pointer;
            display: flex; align-items: center; gap: 6px;
            transition: all .2s ease;
            box-shadow: 0 4px 12px rgba(201,123,43,0.3);
        }
        .fav-card-add:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(201,123,43,0.45); }

        .fav-card-unavailable {
            background: #ccc; color: #fff;
            border: none; border-radius: 50px;
            padding: 8px 16px;
            font-size: 13px; font-weight: 700;
            cursor: not-allowed; opacity: 0.7;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 80px 24px;
            max-width: 400px;
            margin: 0 auto;
        }

        .empty-state i {
            font-size: 52px; color: #C97B2B;
            opacity: 0.35; display: block;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 22px; color: #1a0f08;
            margin-bottom: 8px;
        }

        .empty-state p { font-size: 14px; color: #7a5c3a; }

        .empty-state a {
            display: inline-block; margin-top: 20px;
            padding: 12px 28px;
            background: linear-gradient(135deg, #C97B2B, #E09A4A);
            color: #fff; border-radius: 50px;
            text-decoration: none; font-weight: 600; font-size: 14px;
            box-shadow: 0 6px 18px rgba(201,123,43,0.35);
        }

        /* ── Toast ── */
        .toast {
            position: fixed; bottom: 28px; left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: #2e1c0e; color: #f7f2ec;
            padding: 12px 22px; border-radius: 50px;
            font-size: 13px; font-weight: 600;
            opacity: 0; pointer-events: none;
            transition: all .3s ease;
            z-index: 9999;
            white-space: nowrap;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

        .cart-btn {
            position: relative;
            background: linear-gradient(135deg, #C97B2B, #E09A4A);
            border: none; cursor: pointer;
            width: 44px; height: 44px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 17px; flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(201,123,43,0.45);
            transition: all .3s ease;
        }
        .cart-btn:hover { transform: translateY(-2px) scale(1.05); }
        .cart-badge {
            position: absolute; top: -5px; right: -5px;
            background: #e63946; color: #fff;
            min-width: 19px; height: 19px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 800;
            border: 2px solid #1a0f08;
        }

        /* Cart modal */
        .cart-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,8,4,0.80);
            backdrop-filter: blur(10px);
            z-index: 2000;
            align-items: center; justify-content: center;
            padding: 24px;
        }
        .cart-overlay.open { display: flex; }
        .cart-box {
            background: #fff; border-radius: 24px;
            width: 100%; max-width: 480px;
            max-height: 88dvh;
            display: flex; flex-direction: column;
            box-shadow: 0 24px 64px rgba(0,0,0,0.45);
        }
        .cart-head {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(201,123,43,0.12);
        }
        .cart-head h2 {
            font-family: 'Playfair Display', serif;
            font-size: 18px; font-weight: 900; color: #1a0f08;
            display: flex; align-items: center; gap: 8px;
        }
        .cart-close {
            background: none; border: none; cursor: pointer;
            font-size: 18px; color: #9b7e60;
            transition: color .2s;
        }
        .cart-close:hover { color: #1a0f08; }
        .cart-items {
            flex: 1; overflow-y: auto; padding: 16px 24px;
            display: flex; flex-direction: column; gap: 12px;
        }
        .cart-empty {
            text-align: center; padding: 40px 0;
            color: #9b7e60;
        }
        .cart-empty i { font-size: 36px; margin-bottom: 10px; display: block; opacity: 0.4; }
        .cart-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px; background: #F7F2EC;
            border-radius: 12px;
        }
        .cart-item-info { flex: 1; }
        .cart-item-name { font-size: 14px; font-weight: 600; color: #1a0f08; }
        .cart-item-meta { font-size: 12px; color: #9b7e60; margin-top: 2px; }
        .cart-item-right { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
        .cart-item-price { font-size: 14px; font-weight: 700; color: #C97B2B; }
        .cart-qty-ctrl { display: flex; align-items: center; gap: 8px; }
        .cqty-btn {
            background: #fff; border: 1.5px solid rgba(201,123,43,0.25);
            width: 26px; height: 26px; border-radius: 50%;
            cursor: pointer; font-size: 15px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            color: #C97B2B; transition: all .2s;
        }
        .cqty-btn:hover { background: #C97B2B; color: #fff; }
        .cqty-val { font-size: 14px; font-weight: 700; color: #1a0f08; min-width: 16px; text-align: center; }
        .cart-remove-btn {
            background: none; border: none; cursor: pointer;
            color: #ccc; font-size: 13px; transition: color .2s;
            padding: 4px;
        }
        .cart-remove-btn:hover { color: #e63946; }
        .cart-foot {
            padding: 16px 24px 20px;
            border-top: 1px solid rgba(201,123,43,0.12);
        }
        .cart-total-row {
            display: flex; justify-content: space-between;
            margin-bottom: 14px; font-size: 15px; font-weight: 600; color: #1a0f08;
        }
        .cart-total-amount { color: #C97B2B; font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 900; }
        .checkout-btn {
            width: 100%; background: linear-gradient(135deg, #C97B2B, #E09A4A);
            color: #fff; border: none; border-radius: 12px;
            padding: 14px; font-family: 'DM Sans', sans-serif;
            font-size: 15px; font-weight: 700; cursor: pointer;
            box-shadow: 0 6px 18px rgba(201,123,43,0.35);
            transition: all .25s ease;
        }
        .checkout-btn:hover { transform: translateY(-2px); }
        .checkout-btn:disabled { background: #ccc; box-shadow: none; cursor: not-allowed; transform: none; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="menu.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
    <span class="navbar-title">Back to Menu</span>
    <div style="margin-left:auto;">
        <button class="cart-btn" onclick="openCart()" aria-label="View cart">
            <i class="fas fa-shopping-bag"></i>
            <span class="cart-badge" id="cartCount">0</span>
        </button>
    </div>
</nav>

<div class="page-header">
    <h1>My Favorites</h1>
    <p><?= count($favorites) ?> saved item<?= count($favorites) !== 1 ? 's' : '' ?></p>
</div>

<div <?= empty($favorites) ? '' : 'class="favorites-grid"' ?>>
    <?php if (empty($favorites)): ?>
        <div class="empty-state">
            <i class="fas fa-heart"></i>
            <h3>No favorites yet</h3>
            <p>Tap the heart on any item to save it here.</p>
            <a href="menu.php">Browse Menu</a>
        </div>
    <?php else: ?>
        <?php foreach ($favorites as $item): ?>
            <div class="fav-card" id="fav-card-<?= $item['id'] ?>">
                <div class="fav-card-img-wrap">
                    <img class="fav-card-img"
                         src="<?= !empty($item['image_full_url']) ? htmlspecialchars($item['image_full_url']) : BASE_URL . '/assets/images/placeholder.jpg' ?>"
                         alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
                    <span class="fav-card-category"><?= htmlspecialchars($item['category']) ?></span>
                    <button class="fav-card-unfav"
                            onclick="removeFavorite(this, <?= $item['id'] ?>)"
                            aria-label="Remove from favorites">
                        <i class="fas fa-heart"></i>
                    </button>
                </div>
                <div class="fav-card-body">
                    <div class="fav-card-name"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="fav-card-desc"><?= htmlspecialchars($item['description'] ?? '') ?></div>
                    <div class="fav-card-footer">
                        <div class="fav-card-price">₱<?= number_format($item['price'], 2) ?></div>
                        <?php if ($item['actually_available']): ?>
                            <button class="fav-card-add"
                                    onclick="addToCart(<?= htmlspecialchars(json_encode([
                                        'id'         => $item['id'],
                                        'product_id' => $item['id'],
                                        'name'       => $item['name'],
                                        'price'      => $item['price'],
                                        'size'       => null,
                                        'quantity'   => 1,
                                        'image'      => null
                                    ])) ?>)">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        <?php else: ?>
                            <button class="fav-card-unavailable" disabled>
                                <i class="fas fa-times"></i> Out of Stock
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Cart Modal -->
<div id="cartModal" class="cart-overlay">
    <div class="cart-box">
        <div class="cart-head">
            <h2><i class="fas fa-shopping-bag" style="color:#C97B2B"></i> Your Cart</h2>
            <button class="cart-close" onclick="closeCart()"><i class="fas fa-times"></i></button>
        </div>
        <div class="cart-items" id="cartItems">
            <div class="cart-empty"><i class="fas fa-shopping-bag"></i><p>Your cart is empty</p></div>
        </div>
        <div class="cart-foot">
            <div class="cart-total-row">
                <span>Total</span>
                <span class="cart-total-amount" id="cartTotal">₱0.00</span>
            </div>
            <button id="checkoutBtn" class="checkout-btn" onclick="checkout()" disabled>
                Proceed to Checkout &nbsp;<i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
let cart = JSON.parse(localStorage.getItem('mmCart') || '[]');

// Sync badge on load
syncBadge();

function syncBadge() {
    document.getElementById('cartCount').textContent =
        cart.reduce((s, i) => s + i.quantity, 0);
}

function addToCart(item) {
    const idx = cart.findIndex(c => c.id == item.id);
    if (idx > -1) {
        cart[idx].quantity += 1;
    } else {
        item.quantity = 1;
        cart.push(item);
    }
    localStorage.setItem('mmCart', JSON.stringify(cart));
    syncBadge();
    showToast(item.name + ' added to cart!');
}

function openCart() {
    renderCart();
    document.getElementById('cartModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeCart() {
    document.getElementById('cartModal').classList.remove('open');
    document.body.style.overflow = '';
}

function renderCart() {
    const el = document.getElementById('cartItems');
    if (!cart.length) {
        el.innerHTML = '<div class="cart-empty"><i class="fas fa-shopping-bag"></i><p>Your cart is empty</p></div>';
        document.getElementById('cartTotal').textContent = '₱0.00';
        document.getElementById('checkoutBtn').disabled = true;
        return;
    }
    let html = '', total = 0;
    cart.forEach((item, i) => {
        const sub = item.price * item.quantity;
        total += sub;
        html += `<div class="cart-item">
            <div class="cart-item-info">
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-meta">₱${parseFloat(item.price).toFixed(2)} each</div>
            </div>
            <div class="cart-item-right">
                <span class="cart-item-price">₱${sub.toFixed(2)}</span>
                <div class="cart-qty-ctrl">
                    <button class="cqty-btn" onclick="cqty(${i},-1)">−</button>
                    <span class="cqty-val">${item.quantity}</span>
                    <button class="cqty-btn" onclick="cqty(${i},1)">+</button>
                </div>
            </div>
            <button class="cart-remove-btn" onclick="removeItem(${i})"><i class="fas fa-trash-alt"></i></button>
        </div>`;
    });
    el.innerHTML = html;
    document.getElementById('cartTotal').textContent = '₱' + total.toFixed(2);
    document.getElementById('checkoutBtn').disabled = false;
}

function cqty(idx, delta) {
    cart[idx].quantity = Math.max(1, cart[idx].quantity + delta);
    localStorage.setItem('mmCart', JSON.stringify(cart));
    syncBadge();
    renderCart();
}

function removeItem(idx) {
    cart.splice(idx, 1);
    localStorage.setItem('mmCart', JSON.stringify(cart));
    syncBadge();
    renderCart();
}

function checkout() {
    <?php if (isLoggedIn() && $_SESSION['role'] == 'customer'): ?>
        window.location.href = 'checkout.php';
    <?php else: ?>
        if (confirm('Please log in to checkout. Go to login?')) {
            window.location.href = 'customer_login.php';
        }
    <?php endif; ?>
}

document.getElementById('cartModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeCart();
});

function removeFavorite(btn, itemId) {
    fetch('api/toggle_favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ menu_item_id: itemId })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        if (data.action === 'removed') {
            const card = document.getElementById('fav-card-' + itemId);
            card.style.transition = 'opacity .3s ease, transform .3s ease';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
            setTimeout(() => {
                card.remove();
                const remaining = document.querySelectorAll('.fav-card').length;
                document.querySelector('.page-header p').textContent =
                    remaining + ' saved item' + (remaining !== 1 ? 's' : '');
                if (remaining === 0) location.reload();
            }, 300);
            showToast('Removed from favorites');
        }
    })
    .catch(() => {});
}

let toastTimer;
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2500);
}
</script>

</body>
</html>