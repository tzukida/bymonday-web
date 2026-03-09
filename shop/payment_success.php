<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/paymongo_config.php';
require_once BASE_PATH . '/config/config.php';

// Grab query params passed from payment_return.php
$order_id = htmlspecialchars($_GET['order_id'] ?? '');
$name     = htmlspecialchars($_GET['name']     ?? 'Customer');
$total    = floatval($_GET['total']            ?? 0);
$method   = htmlspecialchars($_GET['method']   ?? 'online');
$items    = intval($_GET['items']              ?? 0);

// Friendly method label
$method_labels = [
    'cash'    => 'Cash on Delivery',
    'gcash'   => 'GCash',
    'paymaya' => 'Maya',
    'card'    => 'Credit / Debit Card',
];
$method_display = $method_labels[$method] ?? ucfirst($method);

// Method icon
$method_icons = [
    'cash'    => '<i class="fa-solid fa-money-bill-1-wave"></i>',
    'gcash'   => '<i class="fa-solid fa-mobile-screen-button"></i>',
    'paymaya' => '<i class="fa-solid fa-mobile-screen-button">',
    'card'    => '<i class="fa-regular fa-credit-card"></i>',
];
$method_icon = $method_icons[$method] ?? '💳';

// First name only
$first_name = explode(' ', trim($name))[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed — Coffee by Monday Mornings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:    #1a0f08;
            --surface:    #2e1c0e;
            --surface2:   #3d2a1c;
            --accent:     #C97B2B;
            --accent-lt:  #E09A4A;
            --accent-dk:  #a05e1a;
            --green:      #4ade80;
            --green-dim:  rgba(74,222,128,0.12);
            --cream:      #F7F2EC;
            --cream-dk:   #EDE5D8;
            --text-muted: #c9b594;
            --text-dim:   #9b7e60;
            --border:     rgba(201,123,43,0.22);
            --radius:     14px;
            --radius-lg:  24px;
            --grad:       linear-gradient(135deg, #C97B2B 0%, #E09A4A 100%);
            --shadow:     0 8px 40px rgba(0,0,0,0.50);
            --shadow-lg:  0 28px 72px rgba(0,0,0,0.65);
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            background: var(--primary);
            color: var(--cream);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px 20px 60px;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Ambient background ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 50% at 50% 0%,   rgba(201,123,43,0.10) 0%, transparent 65%),
                radial-gradient(ellipse 50% 60% at 10% 100%, rgba(201,123,43,0.07) 0%, transparent 55%),
                radial-gradient(ellipse 40% 40% at 90% 80%,  rgba(74,222,128,0.04) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Floating coffee steam particles ── */
        .particles {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            bottom: -20px;
            border-radius: 50%;
            opacity: 0;
            animation: floatUp var(--dur) ease-in var(--delay) infinite;
        }

        @keyframes floatUp {
            0%   { opacity: 0;    transform: translateY(0)   scale(1);   }
            15%  { opacity: 0.5; }
            80%  { opacity: 0.1; }
            100% { opacity: 0;    transform: translateY(-100vh) scale(0.4); }
        }

        /* ── Main card ── */
        .card {
            position: relative;
            z-index: 1;
            background: rgba(46,28,14,0.75);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 52px 48px;
            max-width: 560px;
            width: 100%;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: var(--shadow-lg);
            text-align: center;
            animation: cardIn 0.7s cubic-bezier(0.34,1.36,0.64,1) both;
            margin-top: -20px;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: scale(0.88) translateY(30px); }
            to   { opacity: 1; transform: none; }
        }

        /* ── Checkmark circle ── */
        .check-wrap {
            position: relative;
            width: 90px;
            height: 90px;
            margin: 0 auto 28px;
        }

        .check-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: var(--green-dim);
            border: 2px solid rgba(74,222,128,0.3);
            animation: ringPulse 2.4s ease-in-out 0.5s infinite;
        }

        @keyframes ringPulse {
            0%, 100% { transform: scale(1);    opacity: 1;   }
            50%       { transform: scale(1.15); opacity: 0.4; }
        }

        .check-inner {
            position: absolute;
            inset: 8px;
            border-radius: 50%;
            background: rgba(74,222,128,0.18);
            display: grid;
            place-items: center;
        }

        .check-svg {
            width: 38px;
            height: 38px;
            animation: checkDraw 0.6s ease 0.3s both;
        }

        @keyframes checkDraw {
            from { opacity: 0; transform: scale(0.5) rotate(-15deg); }
            to   { opacity: 1; transform: none; }
        }

        .check-svg path {
            stroke: var(--green);
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            stroke-dasharray: 50;
            stroke-dashoffset: 50;
            animation: drawStroke 0.5s ease 0.6s forwards;
        }

        @keyframes drawStroke {
            to { stroke-dashoffset: 0; }
        }

        /* ── Confetti dots ── */
        .confetti-ring {
            position: absolute;
            inset: -12px;
            pointer-events: none;
        }

        .confetti-ring span {
            position: absolute;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            opacity: 0;
            animation: confettiBurst 0.6s ease var(--cd) forwards;
        }

        @keyframes confettiBurst {
            0%   { opacity: 1; transform: translate(0,0) scale(1); }
            100% { opacity: 0; transform: translate(var(--tx), var(--ty)) scale(0); }
        }

        /* ── Typography ── */
        .label-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--green-dim);
            border: 1px solid rgba(74,222,128,0.25);
            border-radius: 50px;
            padding: 5px 14px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--green);
            margin-bottom: 16px;
            animation: fadeUp 0.5s ease 0.8s both;
        }

        .label-tag::before {
            content: '';
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--green);
            animation: blink 1.4s ease-in-out infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; } 50% { opacity: 0.3; }
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 5vw, 2.6rem);
            font-weight: 700;
            color: var(--cream);
            line-height: 1.2;
            margin-bottom: 10px;
            animation: fadeUp 0.5s ease 0.9s both;
        }

        .subtitle {
            font-size: 15px;
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: 32px;
            animation: fadeUp 0.5s ease 1.0s both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: none; }
        }

        /* ── Order details ── */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 28px;
            animation: fadeUp 0.5s ease 1.1s both;
        }

        .detail-box {
            background: rgba(26,15,8,0.5);
            border: 1px solid rgba(201,123,43,0.15);
            border-radius: var(--radius);
            padding: 16px 14px;
            text-align: left;
        }

        .detail-box .d-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 5px;
        }

        .detail-box .d-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--cream);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .detail-box.highlight {
            border-color: rgba(201,123,43,0.35);
            background: rgba(201,123,43,0.07);
            grid-column: span 2;
        }

        .detail-box.highlight .d-value {
            font-size: 22px;
            color: var(--accent-lt);
            font-family: 'Playfair Display', serif;
        }

        /* ── Divider ── */
        .divider {
            height: 1px;
            background: var(--border);
            margin: 8px 0 28px;
            animation: fadeUp 0.5s ease 1.15s both;
        }

        /* ── What's next ── */
        .whats-next {
            background: rgba(26,15,8,0.4);
            border: 1px solid rgba(201,123,43,0.12);
            border-radius: var(--radius);
            padding: 20px;
            text-align: left;
            margin-bottom: 28px;
            animation: fadeUp 0.5s ease 1.2s both;
        }

        .whats-next h3 {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 14px;
        }

        .step-list { list-style: none; display: flex; flex-direction: column; gap: 11px; }

        .step-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .step-num {
            min-width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--grad);
            color: var(--primary);
            font-size: 11px;
            font-weight: 700;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* ── Actions ── */
        .actions {
            display: flex;
            gap: 12px;
            animation: fadeUp 0.5s ease 1.3s both;
        }

        .btn {
            flex: 1;
            padding: 14px;
            border-radius: var(--radius);
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.25s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
        }

        .btn-primary {
            background: var(--grad);
            color: var(--primary);
            box-shadow: 0 6px 24px rgba(201,123,43,0.35);
        }

        .btn-primary:hover {
            filter: brightness(1.08);
            transform: translateY(-2px);
            box-shadow: 0 10px 32px rgba(201,123,43,0.50);
        }

        .btn-secondary {
            background: rgba(201,123,43,0.08);
            color: var(--accent-lt);
            border: 1.5px solid var(--border);
        }

        .btn-secondary:hover {
            background: rgba(201,123,43,0.15);
            transform: translateY(-1px);
        }

        .btn svg { width: 16px; height: 16px; flex-shrink: 0; }

        /* ── Logo ── */
        .logo-wrap {
            position: relative;
            z-index: 1;
            animation: fadeUp 0.5s ease 0.1s both;
            margin-top: -20px;
        }

        .logo-wrap img {
            height: 150px;
            object-fit: contain;
            filter: drop-shadow(0 4px 20px rgba(201,123,43,0.3));
            margin-top: -20px;
        }

        /* ── Footer ── */
        .footer-note {
            position: relative;
            z-index: 1;
            margin-top: 28px;
            font-size: 12px;
            color: var(--text-dim);
            animation: fadeUp 0.5s ease 1.4s both;
        }

        /* ── Responsive ── */
        @media (max-width: 520px) {
            .card { padding: 36px 22px; }
            .details-grid { grid-template-columns: 1fr; }
            .detail-box.highlight { grid-column: span 1; }
            .actions { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- Floating particles -->
<div class="particles" id="particles"></div>

<!-- Logo -->
<div class="logo-wrap">
    <img src="<?= BASE_URL ?>/assets/images/logo1.png" alt="Coffee by Monday Mornings">
</div>

<!-- Main Card -->
<div class="card">

    <!-- Animated checkmark -->
    <div class="check-wrap">
        <div class="check-ring"></div>
        <div class="check-inner">
            <svg class="check-svg" viewBox="0 0 38 38">
                <path d="M8 19 L16 27 L30 11"/>
            </svg>
        </div>
        <div class="confetti-ring" id="confettiRing"></div>
    </div>

    <div class="label-tag">Order Confirmed</div>

    <h1>Thank you,<br><?php echo $first_name; ?>!</h1>

    <p class="subtitle">
        Your order has been placed successfully.<br>
        We're already brewing something wonderful for you.
    </p>

    <!-- Order details -->
    <div class="details-grid">

        <?php if ($order_id): ?>
        <div class="detail-box">
            <div class="d-label">Order Number</div>
            <div class="d-value">#<?php echo str_pad($order_id, 5, '0', STR_PAD_LEFT); ?></div>
        </div>
        <?php endif; ?>

        <div class="detail-box">
            <div class="d-label">Items Ordered</div>
            <div class="d-value"><?php echo $items; ?> item<?php echo $items !== 1 ? 's' : ''; ?></div>
        </div>

        <div class="detail-box">
            <div class="d-label">Payment Method</div>
            <div class="d-value"><?php echo $method_icon; ?> <?php echo $method_display; ?></div>
        </div>

        <div class="detail-box highlight">
            <div class="d-label">Amount Paid</div>
            <div class="d-value">₱<?php echo number_format($total, 2); ?></div>
        </div>

    </div>

    <div class="divider"></div>

    <!-- What's next -->
    <div class="whats-next">
        <h3>What happens next?</h3>
        <ul class="step-list">
            <li>
                <span class="step-num">1</span>
                <span>We're <strong style="color:var(--cream);">confirming your order</strong> and preparing it in our kitchen.</span>
            </li>
            <li>
                <span class="step-num">2</span>
                <span>You'll receive an <strong style="color:var(--cream);">email confirmation</strong> at your registered address shortly.</span>
            </li>
            <li>
                <span class="step-num">3</span>
                <span>Your order will be <strong style="color:var(--cream);">on its way</strong> once it's ready. Estimated delivery: 30–45 mins.</span>
            </li>
        </ul>
    </div>

    <!-- CTA buttons -->
    <div class="actions">
        <a href="index.php" class="btn btn-secondary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Home
        </a>
        <a href="menu.php" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 01-8 0"/>
            </svg>
            Order More
        </a>
    </div>

</div>

<p class="footer-note">Questions? Contact us at <strong style="color:var(--accent-lt);">support@coffeebymonday.com</strong></p>

<script>
// ── Confetti burst on load ──
function spawnConfetti() {
    const ring = document.getElementById('confettiRing');
    const colors = ['#C97B2B','#E09A4A','#F5C842','#4ade80','#f7f2ec'];
    const count = 12;

    for (let i = 0; i < count; i++) {
        const dot = document.createElement('span');
        const angle = (i / count) * 360;
        const dist  = 48 + Math.random() * 24;
        const rad   = (angle * Math.PI) / 180;
        const tx = Math.cos(rad) * dist + 'px';
        const ty = Math.sin(rad) * dist + 'px';

        dot.style.cssText = `
            left: 50%; top: 50%;
            background: ${colors[i % colors.length]};
            --tx: ${tx}; --ty: ${ty};
            --cd: ${0.5 + i * 0.04}s;
            transform: translate(-50%, -50%);
        `;
        ring.appendChild(dot);
    }
}

// ── Floating particles ──
function spawnParticles() {
    const container = document.getElementById('particles');
    const colors = ['rgba(201,123,43,', 'rgba(245,200,66,', 'rgba(224,154,74,'];

    for (let i = 0; i < 18; i++) {
        const p   = document.createElement('div');
        const size = 3 + Math.random() * 5;
        const color = colors[Math.floor(Math.random() * colors.length)];

        p.className = 'particle';
        p.style.cssText = `
            left: ${Math.random() * 100}%;
            width: ${size}px;
            height: ${size}px;
            background: ${color}${0.25 + Math.random() * 0.35});
            --dur: ${7 + Math.random() * 10}s;
            --delay: ${Math.random() * 8}s;
        `;
        container.appendChild(p);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    spawnConfetti();
    spawnParticles();

    // Clear cart from localStorage on success
    localStorage.removeItem('mmCart');
    localStorage.removeItem('pending_cart');
});
</script>

</body>
</html>
<script src="https://kit.fontawesome.com/6b1714638c.js" crossorigin="anonymous"></script>