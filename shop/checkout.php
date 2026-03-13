<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

if (!isLoggedIn() || $_SESSION['role'] != 'customer') {
    $_SESSION['checkout_after_login'] = true;
    redirect('customer_login.php');
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT u.*, c.phone, c.address FROM users u LEFT JOIN customers c ON u.id = c.user_id WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — Coffee by Monday Mornings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style-checkout.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="index.php" class="navbar-logo">
        <img src="<?= BASE_URL ?>/assets/images/logo1.png" alt="Coffee by Monday Mornings">
    </a>
    <a href="menu.php" class="nav-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
        Back to Menu
    </a>
</nav>

<!-- PAGE HEADER -->
<div class="page-header">
    <p class="eyebrow">Almost there</p>
    <h1>Complete Your Order</h1>
    <div class="header-divider"></div>
</div>

<!-- CHECKOUT WRAPPER -->
<div class="checkout-wrapper">

    <!-- LEFT COLUMN -->
    <div class="left-col">

        <!-- STEP 1: DELIVERY INFO -->
        <div class="card fade-up">
            <div class="card-title">
                <span class="step-pill">1</span>
                Delivery Information
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fullName">Full Name *</label>
                    <input type="text" id="fullName" autocomplete="name"
                           value="<?= htmlspecialchars($customer['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" autocomplete="tel"
                           value="<?= htmlspecialchars($customer['phone']) ?>"
                           placeholder="09XX XXX XXXX" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" autocomplete="email"
                       value="<?= htmlspecialchars($customer['email']) ?>" required>
            </div>

            <?php if (empty($customer['address'])): ?>
            <div class="no-address-banner">
                <i class="fas fa-triangle-exclamation"></i>
                <span>No saved address found.</span>
                <a href="profile.php">Profile Settings →</a>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="address">Delivery Address *</label>
                <textarea id="address" rows="3" autocomplete="street-address"
                        placeholder="Street, Barangay, City, Province…" required><?= htmlspecialchars($customer['address']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="landmark">Landmark <span style="font-weight:400;text-transform:none;opacity:.6">(optional)</span></label>
                <input type="text" id="landmark" placeholder="e.g. Near 7-Eleven, beside the blue gate…">
            </div>

            <div class="form-group">
                <label for="notes">Order Notes <span style="font-weight:400;text-transform:none;opacity:.6">(optional)</span></label>
                <textarea id="notes" rows="2" placeholder="E.g. less ice, extra sugar, leave at door…"></textarea>
            </div>
        </div>

        <!-- STEP 1B: PIN YOUR LOCATION -->
        <div class="card fade-up">
            <div class="card-title">
                <span class="step-pill"><i class="fas fa-location-dot"></i></span>
                Pin Your Location
            </div>
            <p class="section-sublabel">Tap the map to drop your delivery pin, or type your address above.</p>
            <div class="map-placeholder">
                <!-- Map will be implemented here -->
            </div>
            <div class="no-pin-note">
                <i class="fas fa-circle-info"></i>
                No delivery pin yet — tap the map or type your address above.
            </div>
        </div>

        <!-- STEP 2: PAYMENT METHOD -->
        <div class="card fade-up">
            <div class="card-title">
                <span class="step-pill">2</span>
                Payment Method
            </div>

            <p class="section-sublabel">Select how you'd like to pay</p>

            <div class="payment-grid">

                <!-- GCash -->
                <label class="pay-option">
                    <input type="radio" name="payment" value="gcash" onchange="onPaymentChange(this.value)">
                    <div class="pay-card">
                        <div class="pay-logo">
                            <!--
                                To use the real GCash logo, place gcash-logo.png in your assets/images folder
                                and replace the badge below with:
                                <img src="<?= BASE_URL ?>/assets/images/gcash-logo.png" alt="GCash">
                            -->
                                <img src="<?= BASE_URL ?>/assets/images/gcash.png" alt="GCash">
                        </div>
                        <div class="pay-label">GCash</div>
                    </div>
                    <div class="pay-check">✓</div>
                </label>

                <!-- Maya -->
                <label class="pay-option">
                    <input type="radio" name="payment" value="paymaya" onchange="onPaymentChange(this.value)">
                    <div class="pay-card">
                        <div class="pay-logo">
                            <img src="<?= BASE_URL ?>/assets/images/maya.png" alt="Maya">
                        </div>
                        <div class="pay-label">Maya</div>
                    </div>
                    <div class="pay-check">✓</div>
                </label>

                <!-- Cash on Delivery -->
                <label class="pay-option">
                    <input type="radio" name="payment" value="cash" onchange="onPaymentChange(this.value)">
                    <div class="pay-card">
                        <div class="pay-logo">
                            <img src="<?= BASE_URL ?>/assets/images/cash.png" alt="Maya">
                        </div>
                        <div class="pay-label">Cash on Delivery</div>
                    </div>
                    <div class="pay-check">✓</div>
                </label>

            </div>

            <!-- Contextual hints -->
            <div class="pay-note" id="noteGcash">
                <span class="pay-note-icon"><i class="fa-solid fa-mobile-screen-button"></i></span>
                <span><strong>GCash via PayMongo</strong> — You'll be securely redirected to authorise payment from your GCash wallet.</span>
            </div>
            <div class="pay-note" id="noteMaya">
                <span class="pay-note-icon"><i class="fa-solid fa-mobile-screen-button"></i></span>
                <span><strong>Maya via PayMongo</strong> — You'll be securely redirected to complete payment through your Maya account.</span>
            </div>
            <div class="pay-note" id="noteCard">
                <span class="pay-note-icon"><i class="fa-regular fa-credit-card"></i></span>
                <span><strong>Card via PayMongo</strong> — Card details are entered on PayMongo's secure page. We never store your card info.</span>
            </div>
            <div class="pay-note" id="noteCash">
                <span class="pay-note-icon"><i class="fa-solid fa-money-bill-1-wave"></i></span>
                <span><strong>Cash on Delivery</strong> — Pay in cash when your order arrives. Please have the exact amount ready for our rider.</span>
            </div>

        </div>
    </div>

    <!-- RIGHT: ORDER SUMMARY -->
    <div class="card order-summary fade-up">
        <div class="card-title">
            <span class="step-pill">3</span>
            Order Summary
        </div>

        <div class="order-items-list" id="orderItems">
            <p style="color:var(--text-dim);font-size:13px;padding:8px 0;">Loading your items…</p>
        </div>

        <div class="divider"></div>

        <div class="summary-row">
            <span>Subtotal</span>
            <span id="subtotalAmount">₱0.00</span>
        </div>
        <div class="summary-row">
            <span>Delivery Fee</span>
            <span class="free-tag">FREE</span>
        </div>
        <div class="divider"></div>
        <div class="summary-row total">
            <span>Total</span>
            <span id="totalAmount">₱0.00</span>
        </div>

        <!-- Checklist -->
        <div class="order-checklist">
            <div class="checklist-item" id="checkAddress">
                <span class="check-circle"><i class="fas fa-check"></i></span>
                <span>Delivery address entered</span>
            </div>
            <div class="checklist-item" id="checkPin">
                <span class="check-circle"><i class="fas fa-check"></i></span>
                <span>Delivery pin placed on map</span>
            </div>
        </div>

        <button class="place-order-btn" onclick="placeOrder()" id="placeOrderBtn" disabled>
            <span id="btnLabel">Place Order</span>
            <svg id="btnArrow" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
            <span class="spinner" id="btnSpinner"></span>
        </button>

        <div class="secure-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            Payments secured by PayMongo
        </div>
    </div>

</div>

<!-- TOAST NOTIFICATION -->
<div class="toast" id="toast"></div>

<script>
let cart = [];
let selectedPaymentMethod = null;

// ── Init ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    cart = JSON.parse(localStorage.getItem('mmCart') || '[]');
    if (cart.length === 0) {
        showToast('Your cart is empty!', 'error');
        setTimeout(() => window.location.href = 'menu.php', 1800);
        return;
    }
    renderOrderSummary();
    updateChecklist();
});

// ── Render order items ────────────────────────────────────
function renderOrderSummary() {
    let html = '';
    let subtotal = 0;

    cart.forEach(item => {
        const line = parseFloat(item.price) * parseInt(item.quantity);
        subtotal += line;
        html += `
            <div class="order-item">
                <div class="item-qty">${item.quantity}</div>
                <div class="item-info">
                    <div class="item-name">${escHtml(item.name)}</div>
                    <div class="item-meta">${item.size ? 'Size: ' + item.size.toUpperCase() + ' &nbsp;·&nbsp; ' : ''}₱${parseFloat(item.price).toFixed(2)} each</div>
                </div>
                <div class="item-price">₱${line.toFixed(2)}</div>
            </div>`;
    });

    document.getElementById('orderItems').innerHTML = html;
    document.getElementById('subtotalAmount').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('totalAmount').textContent   = '₱' + subtotal.toFixed(2);
}

// ── Payment selection ─────────────────────────────────────
function onPaymentChange(method) {
    selectedPaymentMethod = method;

    ['noteGcash','noteMaya','noteCard','noteCash'].forEach(id =>
        document.getElementById(id).classList.remove('visible')
    );

    const map = { gcash:'noteGcash', paymaya:'noteMaya', card:'noteCard', cash:'noteCash' };
    if (map[method]) document.getElementById(map[method]).classList.add('visible');

    document.getElementById('placeOrderBtn').disabled = false;
}

// ── Checklist ─────────────────────────────────────────────
function updateChecklist() {
    const address = document.getElementById('address').value.trim();

    const checkAddr = document.getElementById('checkAddress');
    const checkPin  = document.getElementById('checkPin');

    if (address) {
        checkAddr.classList.add('done');
    } else {
        checkAddr.classList.remove('done');
    }

    // Pin auto-checked for now (map not built yet)
    checkPin.classList.add('done');
}

document.getElementById('address').addEventListener('input', updateChecklist);

document.addEventListener('DOMContentLoaded', () => {
    updateChecklist();
});

// ── Validation ────────────────────────────────────────────
function validate() {
    updateChecklist();
    const name    = document.getElementById('fullName').value.trim();
    const email   = document.getElementById('email').value.trim();
    const phone   = document.getElementById('phone').value.trim();
    const address = document.getElementById('address').value.trim();

    if (!name)    { showToast('Please enter your full name.', 'error'); return false; }
    if (!email)   { showToast('Please enter your email address.', 'error'); return false; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showToast('Please enter a valid email address.', 'error'); return false;
    }
    if (!phone)   { showToast('Please enter your phone number.', 'error'); return false; }
    if (!address) { showToast('Please enter your delivery address.', 'error'); return false; }
    if (!selectedPaymentMethod) { showToast('Please select a payment method.', 'error'); return false; }

    return true;
}

// ── Place order ───────────────────────────────────────────
function placeOrder() {
    if (!validate()) return;

    setLoading(true);

    fetch('http://localhost/bymonday/portal/api/menu_items.php')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) {
                setLoading(false);
                showToast('Could not verify item availability. Please try again.', 'error');
                return;
            }

            var unavailableItems = [];

            cart = cart.filter(function(cartItem) {
                var found = data.items.find(function(apiItem) {
                    return apiItem.id == cartItem.product_id;
                });
                if (found && !found.actually_available) {
                    unavailableItems.push(cartItem.name);
                    return false;
                }
                return true;
            });

            if (unavailableItems.length > 0) {
                localStorage.setItem('mmCart', JSON.stringify(cart));
                renderOrderSummary();
                setLoading(false);
                showToast(unavailableItems.join(', ') + ' is no longer available and was removed from your order.', 'error');
                if (cart.length === 0) {
                    setTimeout(function() { window.location.href = 'menu.php'; }, 2500);
                }
                return;
            }

            proceedWithOrder();
        })
        .catch(function() {
            setLoading(false);
            showToast('Could not verify item availability. Please try again.', 'error');
        });
}

function proceedWithOrder() {
    const subtotal = cart.reduce((s, i) => s + parseFloat(i.price) * parseInt(i.quantity), 0);

    const orderData = {
        customer_name:    document.getElementById('fullName').value.trim(),
        customer_email:   document.getElementById('email').value.trim(),
        customer_phone:   document.getElementById('phone').value.trim(),
        customer_address: document.getElementById('address').value.trim(),
        notes:            document.getElementById('notes').value.trim(),
        items:            cart,
        subtotal:         subtotal,
        total:            subtotal,
        payment_method:   selectedPaymentMethod,
    };

    setLoading(true);

    // ── Cash on Delivery ──────────────────────────────────
    if (selectedPaymentMethod === 'cash') {
        fetch('save_cod_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderData)
        })
        .then(r => r.text())
        .then(text => {
            console.log("RAW RESPONSE:", text);
            return JSON.parse(text);
        })
        .then(data => {
            if (data.success) {
                localStorage.removeItem('mmCart');
                window.location.href = 'track-order.php?id=' + data.order_id;
            } else {
                showToast(data.message || 'Order failed. Please try again.', 'error');
                setLoading(false);
            }
        })
        .catch(err => {
            console.error('COD error:', err);
            showToast('Network error. Please try again.', 'error');
            setLoading(false);
        });
        return;
    }

    // ── PayMongo (GCash / Maya / Card) ────────────────────
    fetch('create_checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            amount:         subtotal,
            payment_method: selectedPaymentMethod,
            order_data:     orderData
        })
    })
    .then(r => r.json())
    .then(data => {
        console.log('PayMongo response:', data);

        const url = data?.data?.attributes?.checkout_url;
        if (url) {
            // Keep cart until payment_return confirms success
            localStorage.setItem('pending_cart', JSON.stringify(cart));
            window.location.href = url;
        } else {
            // Show the PayMongo error detail if available
            const errMsg = data?.errors?.[0]?.detail
                || data?.error
                || 'Failed to create checkout. Check your PayMongo key and try again.';
            showToast(errMsg, 'error');
            console.error('PayMongo error detail:', data);
            setLoading(false);
        }
    })
    .catch(err => {
        console.error('Fetch error:', err);
        showToast('Network error. Please check your connection.', 'error');
        setLoading(false);
    });
}

// ── Helpers ───────────────────────────────────────────────
function setLoading(on) {
    const btn     = document.getElementById('placeOrderBtn');
    const label   = document.getElementById('btnLabel');
    const arrow   = document.getElementById('btnArrow');
    const spinner = document.getElementById('btnSpinner');

    btn.disabled          = on;
    label.textContent     = on ? 'Processing…' : 'Place Order';
    arrow.style.display   = on ? 'none' : '';
    spinner.style.display = on ? 'block' : 'none';
}

function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast' + (type ? ' ' + type : '');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3500);
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once BASE_PATH . '/includes/order_notif.php'; ?>
</body>
</html>
<script src="https://kit.fontawesome.com/6b1714638c.js" crossorigin="anonymous"></script>