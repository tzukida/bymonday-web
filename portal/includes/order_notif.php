<?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'staff'): ?>
<style>
.bm-staff-notif-wrap {
    position: fixed;
    bottom: 28px;
    right: 24px;
    z-index: 99999;
    display: flex;
    flex-direction: column-reverse;
    gap: 10px;
    pointer-events: none;
}
.bm-staff-notif {
    background: #3b1f0a;
    border: 1px solid rgba(201,123,43,0.4);
    border-left: 4px solid #C97B2B;
    border-radius: 12px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 270px;
    max-width: 340px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    pointer-events: auto;
    font-family: inherit;
    font-size: 13px;
    animation: staffNotifIn .3s ease;
}
.bm-staff-notif-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    background: rgba(201,123,43,0.2);
    color: #e8a04a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}
.bm-staff-notif-text { flex: 1; line-height: 1.4; }
.bm-staff-notif-title {
    font-weight: 700;
    font-size: 13px;
    color: #f5e6d0;
    margin-bottom: 2px;
}
.bm-staff-notif-sub {
    font-size: 11px;
    color: rgba(201,181,148,0.7);
}
.bm-staff-notif-link {
    font-size: 11px;
    color: #e8a04a;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    margin-top: 4px;
}
.bm-staff-notif-link:hover { text-decoration: underline; }
.bm-staff-notif-close {
    background: none; border: none;
    color: rgba(201,181,148,0.4);
    cursor: pointer; font-size: 12px;
    padding: 0; flex-shrink: 0;
    transition: color .2s;
}
.bm-staff-notif-close:hover { color: #f5e6d0; }

@keyframes staffNotifIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes staffNotifOut {
    from { opacity: 1; transform: translateY(0); }
    to   { opacity: 0; transform: translateY(10px); }
}
</style>

<div class="bm-staff-notif-wrap" id="bmStaffNotifWrap"></div>

<script>
(function () {
    const STORAGE_KEY = 'bm_staff_orders';
    const POLL_MS     = 15000;
    const API         = '<?= BASE_URL ?>/api/check_new_orders.php';
    const ORDERS_URL  = '<?= BASE_URL ?>/staff/online_orders.php';

    function load() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; }
        catch(e) { return []; }
    }

    function save(ids) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    }

    function toast(order) {
        const wrap = document.getElementById('bmStaffNotifWrap');
        const el   = document.createElement('div');
        el.className = 'bm-staff-notif';
        el.innerHTML = `
            <div class="bm-staff-notif-icon">
                <i class="fas fa-bag-shopping"></i>
            </div>
            <div class="bm-staff-notif-text">
                <div class="bm-staff-notif-title">New Order Received!</div>
                <div class="bm-staff-notif-sub">
                    ${escHtml(order.customer_name)} — ₱${parseFloat(order.total).toLocaleString('en-PH', {minimumFractionDigits:2})}
                </div>
                <a class="bm-staff-notif-link" href="${ORDERS_URL}">View Orders →</a>
            </div>
            <button class="bm-staff-notif-close" onclick="this.closest('.bm-staff-notif').remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        wrap.appendChild(el);
        setTimeout(() => {
            el.style.animation = 'staffNotifOut .3s ease forwards';
            setTimeout(() => el.remove(), 300);
        }, 10000);
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function poll() {
        fetch(API)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const saved      = load();
                const currentIds = data.orders.map(o => o.id);
                const newOrders  = data.orders.filter(o => !saved.includes(o.id));

                newOrders.forEach(o => toast(o));
                save(currentIds);
            })
            .catch(() => {});
    }

    poll();
    setInterval(poll, POLL_MS);
})();
</script>
<?php endif; ?>