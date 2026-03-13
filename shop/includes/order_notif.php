<?php if (isLoggedIn() && $_SESSION['role'] == 'customer'): ?>
<style>
.bm-notif-wrap {
    position: fixed;
    bottom: 28px;
    right: 24px;
    z-index: 99999;
    display: flex;
    flex-direction: column-reverse;
    gap: 10px;
    pointer-events: none;
}
.bm-notif {
    background: #1c1c1c;
    color: #fff;
    border-radius: 12px;
    padding: 13px 16px 13px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 260px;
    max-width: 320px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.35);
    pointer-events: auto;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    animation: bmSlideIn .3s ease;
    border-left: 4px solid #C97B2B;
}
.bm-notif.cancelled { border-left-color: #C97B2B; }
.bm-notif.delivery  { border-left-color: #C97B2B; }
.bm-notif.brewing   { border-left-color: #C97B2B; }
.bm-notif.done      { border-left-color: #C97B2B; }

.bm-notif-icon {
    font-size: 18px;
    flex-shrink: 0;
}
.bm-notif.brewing   .bm-notif-icon { color: #a78bfa; }
.bm-notif.delivery  .bm-notif-icon { color: #60a5fa; }
.bm-notif.done      .bm-notif-icon { color: #22c55e; }
.bm-notif.cancelled .bm-notif-icon { color: #f87171; }

.bm-notif-text { flex: 1; line-height: 1.4; }
.bm-notif-title { font-weight: 700; font-size: 13px; margin-bottom: 2px; }
.bm-notif-sub   { font-size: 11px; color: rgba(255,255,255,0.6); }
.bm-notif-link  {
    font-size: 11px; color: #C97B2B;
    text-decoration: none; font-weight: 600;
    display: inline-block; margin-top: 4px;
}
.bm-notif-link:hover { text-decoration: underline; }
.bm-notif-close {
    background: none; border: none;
    color: rgba(255,255,255,0.35);
    cursor: pointer; font-size: 13px;
    padding: 0; flex-shrink: 0;
    transition: color .2s;
}
.bm-notif-close:hover { color: #fff; }

@keyframes bmSlideIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes bmSlideOut {
    from { opacity: 1; transform: translateY(0); }
    to   { opacity: 0; transform: translateY(10px); }
}
</style>

<div class="bm-notif-wrap" id="bmNotifWrap"></div>

<script>
(function () {
    const STORAGE_KEY  = 'bm_ord_status';
    const POLL_MS      = 15000;
    const API          = '<?= BASE_URL ?>/api/check_order_status.php';
    const TRACK_URL    = '<?= BASE_URL ?>/track-order.php';

    const cfg = {
        brewing:   { icon: 'fa-mug-hot',       cls: 'brewing',   title: 'Order is being prepared!',  sub: 'Your order is now brewing.' },
        delivery:  { icon: 'fa-person-biking',  cls: 'delivery',  title: 'Out for delivery!',          sub: 'Your order is on its way.' },
        done:      { icon: 'fa-check-circle',   cls: 'done',      title: 'Order delivered!',           sub: 'Enjoy your order!' },
        cancelled: { icon: 'fa-times-circle',   cls: 'cancelled', title: 'Order cancelled.',           sub: 'Your order has been cancelled.' },
    };

    function load() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; }
        catch(e) { return {}; }
    }

    function save(map) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(map));
    }

    function toast(order, status) {
        const c = cfg[status];
        if (!c) return;
        const wrap = document.getElementById('bmNotifWrap');
        const num  = '#' + order.order_number.slice(-6).toUpperCase();
        const el   = document.createElement('div');
        el.className = 'bm-notif ' + c.cls;
        el.innerHTML = `
            <i class="fas ${c.icon} bm-notif-icon"></i>
            <div class="bm-notif-text">
                <div class="bm-notif-title">${c.title}</div>
                <div class="bm-notif-sub">Order ${num} — ${c.sub}</div>
                <a class="bm-notif-link" href="${TRACK_URL}?id=${order.id}">Track order →</a>
            </div>
            <button class="bm-notif-close" onclick="this.closest('.bm-notif').remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        wrap.appendChild(el);
        setTimeout(() => {
            el.style.animation = 'bmSlideOut .3s ease forwards';
            setTimeout(() => el.remove(), 300);
        }, 10000);
    }

    function poll(isLogin) {
        fetch(API)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const saved   = load();
                const current = {};

                data.orders.forEach(o => {
                    current[o.id] = o.order_status;
                    const prev = saved[o.id];
                    // Show toast if: status changed from what we last saved
                    if (prev && prev !== o.order_status) {
                        toast(o, o.order_status);
                    }
                });

                // Check orders that disappeared from active list
                // (moved to done or cancelled)
                const gone = Object.keys(saved).filter(id => !current[id]);
                if (gone.length > 0) {
                    gone.forEach(id => {
                        fetch(API + '?include_final=1&order_id=' + id)
                            .then(r => r.json())
                            .then(d => {
                                if (d.success && d.orders.length > 0) {
                                    const o = d.orders[0];
                                    if (saved[id] !== o.order_status) {
                                        toast(o, o.order_status);
                                    }
                                    current[id] = o.order_status;
                                    save({...load(), ...current});
                                }
                            }).catch(()=>{});
                    });
                }

                save({...saved, ...current});
            })
            .catch(() => {});
    }

    // Run immediately on page load (handles login scenario)
    poll(true);

    // Then keep polling
    setInterval(() => poll(false), POLL_MS);
})();
</script>
<?php endif; ?>