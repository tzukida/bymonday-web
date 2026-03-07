<?php
/**
 * crypto_payment.php
 * A realistic-looking crypto payment page for demo/presentation purposes.
 * Shows a QR code, wallet address, countdown timer, and auto-confirms after payment simulation.
 */
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

// Get order data from session (set by checkout.php)
$order_data = $_SESSION['pending_crypto_order'] ?? null;

if (!$order_data) {
    // Fallback for direct access / demo
    $order_data = [
        'total'          => $_GET['amount'] ?? 350.00,
        'order_id'       => 'CBM-' . time(),
        'customer_name'  => 'Customer',
        'customer_email' => '',
    ];
}

$total    = floatval($order_data['total'] ?? $order_data['subtotal'] ?? 0);
$order_id = $order_data['order_id'] ?? ('CBM-' . time());

// Fake but realistic-looking crypto values
$btc_rate    = 3800000;  // approx BTC/PHP
$eth_rate    = 200000;   // approx ETH/PHP
$usdt_rate   = 58;       // approx USDT/PHP

$btc_amount  = number_format($total / $btc_rate, 8);
$eth_amount  = number_format($total / $eth_rate, 6);
$usdt_amount = number_format($total / $usdt_rate, 2);

// Fake wallet addresses (realistic-looking)
$wallets = [
    'btc'  => '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divf' . substr(md5($order_id), 0, 6),
    'eth'  => '0x742d35Cc6634C0532925a3b8D4C9E2' . strtoupper(substr(md5($order_id.'eth'), 0, 6)),
    'usdt' => 'TN9gKgaHz9Q2L8wK5uQ7gS3P1xJ4' . strtoupper(substr(md5($order_id.'usdt'), 0, 8)),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Payment — Coffee by Monday Mornings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <!-- QR Code generator library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --primary:    #1a0f08;
            --surface:    #2e1c0e;
            --surface2:   #3d2a1c;
            --accent:     #C97B2B;
            --accent-lt:  #E09A4A;
            --accent-dk:  #a05e1a;
            --cream:      #F7F2EC;
            --cream-dk:   #EDE5D8;
            --text-muted: #c9b594;
            --text-dim:   #9b7e60;
            --border:     rgba(201,123,43,0.22);
            --radius:     14px;
            --radius-lg:  24px;
            --grad:       linear-gradient(135deg, #C97B2B 0%, #E09A4A 100%);
            --shadow-lg:  0 28px 72px rgba(0,0,0,0.65);
            --orange:     #f97316;
            --green:      #4ade80;
            --green-dim:  rgba(74,222,128,0.12);
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            background: var(--primary);
            color: var(--cream);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 16px 60px;
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 50% at 50% 0%, rgba(201,123,43,0.10) 0%, transparent 65%),
                radial-gradient(ellipse 50% 60% at 10% 100%, rgba(201,123,43,0.07) 0%, transparent 55%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Logo ── */
        .logo-wrap {
            position: relative;
            z-index: 1;
            margin-bottom: 8px;
        }
        .logo-wrap img {
            height: 80px;
            object-fit: contain;
            filter: drop-shadow(0 4px 20px rgba(201,123,43,0.3));
        }

        /* ── Main card ── */
        .card {
            position: relative;
            z-index: 1;
            background: rgba(46,28,14,0.85);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 32px 28px;
            max-width: 520px;
            width: 100%;
            backdrop-filter: blur(16px);
            box-shadow: var(--shadow-lg);
            animation: cardIn 0.6s cubic-bezier(0.34,1.2,0.64,1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(20px) scale(0.97); }
            to   { opacity: 1; transform: none; }
        }

        /* ── Header ── */
        .pay-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .pay-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            font-weight: 700;
        }

        .pay-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(249,115,22,0.12);
            border: 1px solid rgba(249,115,22,0.3);
            border-radius: 50px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--orange);
            letter-spacing: 0.5px;
        }

        .pay-badge .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--orange);
            animation: blink 1.2s ease-in-out infinite;
        }

        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.2} }

        /* ── Amount ── */
        .amount-box {
            background: rgba(26,15,8,0.6);
            border: 1px solid rgba(201,123,43,0.2);
            border-radius: var(--radius);
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }

        .amount-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 6px;
        }

        .amount-php {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--accent-lt);
        }

        .amount-order {
            font-size: 12px;
            color: var(--text-dim);
            margin-top: 4px;
        }

        /* ── Coin tabs ── */
        .coin-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }

        .coin-tab {
            flex: 1;
            padding: 10px 8px;
            border-radius: 10px;
            background: rgba(26,15,8,0.5);
            border: 1.5px solid rgba(201,123,43,0.15);
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }

        .coin-tab:hover {
            border-color: rgba(201,123,43,0.4);
        }

        .coin-tab.active {
            border-color: var(--accent);
            background: rgba(201,123,43,0.1);
        }

        .coin-icon { font-size: 20px; margin-bottom: 4px; }
        .coin-name { font-size: 11px; font-weight: 600; letter-spacing: 0.5px; color: var(--text-muted); }
        .coin-amount { font-size: 10px; color: var(--text-dim); font-family: 'JetBrains Mono', monospace; }

        /* ── QR + Wallet section ── */
        .payment-body {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .qr-wrap {
            flex-shrink: 0;
            background: #fff;
            border-radius: 12px;
            padding: 10px;
            width: 130px;
            height: 130px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-wrap canvas, .qr-wrap img { border-radius: 4px; }

        .wallet-info { flex: 1; }

        .wallet-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 8px;
        }

        .wallet-address-wrap {
            background: rgba(26,15,8,0.7);
            border: 1px solid rgba(201,123,43,0.2);
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 10px;
            position: relative;
        }

        .wallet-address {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            color: var(--cream);
            word-break: break-all;
            line-height: 1.6;
            padding-right: 28px;
        }

        .copy-btn {
            position: absolute;
            top: 8px; right: 8px;
            background: rgba(201,123,43,0.15);
            border: 1px solid rgba(201,123,43,0.3);
            border-radius: 6px;
            padding: 4px 6px;
            cursor: pointer;
            color: var(--accent-lt);
            font-size: 11px;
            transition: all 0.2s;
        }

        .copy-btn:hover { background: rgba(201,123,43,0.3); }

        .crypto-amount-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(26,15,8,0.5);
            border: 1px solid rgba(201,123,43,0.15);
            border-radius: 8px;
            padding: 8px 12px;
        }

        .crypto-amount-label { font-size: 11px; color: var(--text-dim); }
        .crypto-amount-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            font-weight: 600;
            color: var(--accent-lt);
        }

        /* ── Timer ── */
        .timer-wrap {
            background: rgba(249,115,22,0.07);
            border: 1px solid rgba(249,115,22,0.2);
            border-radius: var(--radius);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .timer-icon { font-size: 20px; flex-shrink: 0; }

        .timer-text { flex: 1; }
        .timer-text p { font-size: 12px; color: var(--text-dim); margin-bottom: 2px; }

        .timer-countdown {
            font-family: 'JetBrains Mono', monospace;
            font-size: 20px;
            font-weight: 600;
            color: var(--orange);
            letter-spacing: 2px;
        }

        .timer-bar-wrap {
            height: 3px;
            background: rgba(249,115,22,0.15);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }

        .timer-bar {
            height: 100%;
            background: var(--orange);
            border-radius: 2px;
            transition: width 1s linear;
        }

        /* ── Steps ── */
        .steps {
            background: rgba(26,15,8,0.4);
            border: 1px solid rgba(201,123,43,0.12);
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 20px;
        }

        .steps-title {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 12px;
        }

        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .step-item:last-child { margin-bottom: 0; }

        .step-num {
            min-width: 20px; height: 20px;
            border-radius: 50%;
            background: var(--grad);
            color: var(--primary);
            font-size: 10px;
            font-weight: 700;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* ── Confirm btn ── */
        .confirm-btn {
            width: 100%;
            padding: 16px;
            border-radius: var(--radius);
            background: var(--grad);
            color: var(--primary);
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.25s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 6px 24px rgba(201,123,43,0.35);
        }

        .confirm-btn:hover {
            filter: brightness(1.08);
            transform: translateY(-2px);
            box-shadow: 0 10px 32px rgba(201,123,43,0.50);
        }

        .confirm-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .spinner {
            width: 16px; height: 16px;
            border: 2px solid rgba(26,15,8,0.3);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: none;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Success overlay ── */
        .success-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(26,15,8,0.95);
            z-index: 100;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 24px;
            animation: fadeIn 0.4s ease both;
        }

        .success-overlay.show { display: flex; }

        @keyframes fadeIn { from{opacity:0} to{opacity:1} }

        .success-check {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: var(--green-dim);
            border: 2px solid rgba(74,222,128,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-bottom: 20px;
            animation: popIn 0.5s cubic-bezier(0.34,1.6,0.64,1) both;
        }

        @keyframes popIn {
            from { transform: scale(0); }
            to   { transform: scale(1); }
        }

        .success-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .success-sub {
            color: var(--text-muted);
            margin-bottom: 28px;
            line-height: 1.6;
        }

        .success-actions { display: flex; gap: 12px; }

        .btn {
            padding: 13px 24px;
            border-radius: var(--radius);
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--grad);
            color: var(--primary);
        }

        .btn-secondary {
            background: rgba(201,123,43,0.1);
            color: var(--accent-lt);
            border: 1.5px solid var(--border);
        }

        /* ── Responsive ── */
        @media (max-width: 480px) {
            .payment-body { flex-direction: column; align-items: center; }
            .qr-wrap { width: 160px; height: 160px; }
            .wallet-info { width: 100%; }
        }
    </style>
</head>
<body>

<!-- Success overlay -->
<div class="success-overlay" id="successOverlay">
    <div class="success-check">✓</div>
    <div class="success-title">Payment Confirmed!</div>
    <p class="success-sub">
        Your crypto payment has been received.<br>
        Order <strong style="color:var(--cream)"><?= htmlspecialchars($order_id) ?></strong> is now being prepared.
    </p>
    <div class="success-actions">
        <a href="index.php" class="btn btn-secondary">Home</a>
        <a href="menu.php" class="btn btn-primary">Order More</a>
    </div>
</div>

<!-- Logo -->
<div class="logo-wrap">
    <img src="<?= BASE_URL ?>/assets/images/logo1.png" alt="Coffee by Monday Mornings">
</div>

<!-- Main Card -->
<div class="card">

    <!-- Header -->
    <div class="pay-header">
        <div class="pay-title">Crypto Payment</div>
        <div class="pay-badge">
            <span class="dot"></span>
            Awaiting Payment
        </div>
    </div>

    <!-- Amount -->
    <div class="amount-box">
        <div class="amount-label">Amount Due</div>
        <div class="amount-php">₱<?= number_format($total, 2) ?></div>
        <div class="amount-order">Order <?= htmlspecialchars($order_id) ?></div>
    </div>

    <!-- Coin tabs -->
    <div class="coin-tabs">
        <div class="coin-tab active" onclick="selectCoin('btc', this)">
            <div class="coin-icon">₿</div>
            <div class="coin-name">BTC</div>
            <div class="coin-amount"><?= $btc_amount ?></div>
        </div>
        <div class="coin-tab" onclick="selectCoin('eth', this)">
            <div class="coin-icon">Ξ</div>
            <div class="coin-name">ETH</div>
            <div class="coin-amount"><?= $eth_amount ?></div>
        </div>
        <div class="coin-tab" onclick="selectCoin('usdt', this)">
            <div class="coin-icon">₮</div>
            <div class="coin-name">USDT</div>
            <div class="coin-amount"><?= $usdt_amount ?></div>
        </div>
    </div>

    <!-- QR + Wallet -->
    <div class="payment-body">
        <div class="qr-wrap" id="qrWrap"></div>
        <div class="wallet-info">
            <div class="wallet-label">Send exactly to this address</div>
            <div class="wallet-address-wrap">
                <div class="wallet-address" id="walletAddress"><?= $wallets['btc'] ?></div>
                <button class="copy-btn" onclick="copyAddress()" title="Copy address">⧉</button>
            </div>
            <div class="crypto-amount-row">
                <span class="crypto-amount-label">Amount</span>
                <span class="crypto-amount-value" id="cryptoAmount"><?= $btc_amount ?> BTC</span>
            </div>
        </div>
    </div>

    <!-- Timer -->
    <div class="timer-wrap">
        <div class="timer-icon">⏱</div>
        <div class="timer-text">
            <p>Payment window expires in</p>
            <div class="timer-countdown" id="countdown">15:00</div>
            <div class="timer-bar-wrap">
                <div class="timer-bar" id="timerBar" style="width:100%"></div>
            </div>
        </div>
    </div>

    <!-- Steps -->
    <div class="steps">
        <div class="steps-title">How to pay</div>
        <div class="step-item">
            <span class="step-num">1</span>
            <span>Select your preferred cryptocurrency above (BTC, ETH, or USDT)</span>
        </div>
        <div class="step-item">
            <span class="step-num">2</span>
            <span>Open your crypto wallet app and scan the QR code or copy the address</span>
        </div>
        <div class="step-item">
            <span class="step-num">3</span>
            <span>Send the <strong style="color:var(--cream)">exact amount</strong> shown — incorrect amounts may cause delays</span>
        </div>
        <div class="step-item">
            <span class="step-num">4</span>
            <span>Click <strong style="color:var(--cream)">"I've Sent the Payment"</strong> once your transaction is broadcast</span>
        </div>
    </div>

    <!-- Confirm button -->
    <button class="confirm-btn" id="confirmBtn" onclick="confirmPayment()">
        <span id="confirmLabel">✓ I've Sent the Payment</span>
        <span class="spinner" id="confirmSpinner"></span>
    </button>

</div>

<!-- JS data from PHP -->
<script>
const WALLETS = <?= json_encode($wallets) ?>;
const AMOUNTS = {
    btc:  '<?= $btc_amount ?> BTC',
    eth:  '<?= $eth_amount ?> ETH',
    usdt: '<?= $usdt_amount ?> USDT'
};

let currentCoin = 'btc';

// ── QR Code generation ─────────────────────────────────────
function generateQR(address) {
    const wrap = document.getElementById('qrWrap');
    wrap.innerHTML = '';
    new QRCode(wrap, {
        text: address,
        width: 110,
        height: 110,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
}

// ── Coin selection ─────────────────────────────────────────
function selectCoin(coin, el) {
    currentCoin = coin;
    document.querySelectorAll('.coin-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');

    const address = WALLETS[coin];
    document.getElementById('walletAddress').textContent = address;
    document.getElementById('cryptoAmount').textContent  = AMOUNTS[coin];
    generateQR(address);
}

// ── Copy address ───────────────────────────────────────────
function copyAddress() {
    const addr = document.getElementById('walletAddress').textContent;
    navigator.clipboard.writeText(addr).then(() => {
        const btn = document.querySelector('.copy-btn');
        btn.textContent = '✓';
        btn.style.color = '#4ade80';
        setTimeout(() => { btn.textContent = '⧉'; btn.style.color = ''; }, 1800);
    }).catch(() => {
        // Fallback for older browsers
        const el = document.createElement('textarea');
        el.value = addr;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    });
}

// ── Countdown timer (15 minutes) ──────────────────────────
const TOTAL_SECS = 15 * 60;
let remaining = TOTAL_SECS;

function updateTimer() {
    const m = Math.floor(remaining / 60).toString().padStart(2, '0');
    const s = (remaining % 60).toString().padStart(2, '0');
    document.getElementById('countdown').textContent = `${m}:${s}`;
    document.getElementById('timerBar').style.width = (remaining / TOTAL_SECS * 100) + '%';

    if (remaining <= 0) {
        clearInterval(timerInterval);
        document.getElementById('countdown').textContent = '00:00';
        document.getElementById('countdown').style.color = '#ef4444';
        document.getElementById('confirmBtn').disabled = true;
        document.getElementById('confirmLabel').textContent = 'Payment window expired';
    }
    remaining--;
}

const timerInterval = setInterval(updateTimer, 1000);

// ── Confirm payment ────────────────────────────────────────
function confirmPayment() {
    const btn     = document.getElementById('confirmBtn');
    const label   = document.getElementById('confirmLabel');
    const spinner = document.getElementById('confirmSpinner');

    btn.disabled          = true;
    label.textContent     = 'Verifying transaction…';
    spinner.style.display = 'block';

    // Simulate blockchain verification delay (2-3 seconds)
    const delay = 2000 + Math.random() * 1500;

    setTimeout(() => {
        // Save order to database
        fetch('save_crypto_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ coin: currentCoin })
        })
        .then(r => r.json())
        .then(() => {
            spinner.style.display = 'none';
            label.textContent     = 'Confirmed!';
            localStorage.removeItem('mmCart');
            localStorage.removeItem('pending_cart');
            clearInterval(timerInterval);
            setTimeout(() => {
                document.getElementById('successOverlay').classList.add('show');
            }, 400);
        })
        .catch(() => {
            spinner.style.display = 'none';
            label.textContent     = 'Confirmed!';
            localStorage.removeItem('mmCart');
            localStorage.removeItem('pending_cart');
            clearInterval(timerInterval);
            setTimeout(() => {
                document.getElementById('successOverlay').classList.add('show');
            }, 400);
        });

    }, delay);
}

// ── Init ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    generateQR(WALLETS.btc);
});
</script>
</body>
</html>