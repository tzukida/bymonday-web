</main>

<?php if (isLoggedIn()): ?>
<footer class="footer-custom">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-md-4 text-center text-md-start mb-3 mb-md-0">
        <p>
          <i class="fas fa-copyright"></i>
          <span><?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</span>
        </p>
      </div>
      <div class="col-md-4 text-center mb-3 mb-md-0">

      </div>
      <div class="col-md-4 text-center text-md-end">
        <p>
          <i class="fas fa-clock"></i>
          <span>Last Activity: <?php echo date('g:i A'); ?></span>
        </p>
      </div>
    </div>
  </div>
</footer>
<?php endif; ?>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<!-- Custom CSS -->
<link href="<?php echo getBaseURL(); ?>/assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">

<?php if (isset($include_charts) && $include_charts): ?>
  <script src="<?php echo getBaseURL(); ?>/assets/js/charts.js"></script>
<?php endif; ?>

<?php if (isset($include_datatables) && $include_datatables): ?>
  <script src="<?php echo getBaseURL(); ?>/assets/js/datatables.js"></script>
<?php endif; ?>

<?php if (isLoggedIn()): ?>
<script>
  // Session Management
  let sessionTimeout = <?php echo SESSION_TIMEOUT * 1000; ?>;
  let warningTime = sessionTimeout - (5 * 60 * 1000);

  setTimeout(function() {
    if (confirm('Your session will expire in 5 minutes. Do you want to stay logged in?')) {
      window.location.reload();
    }
  }, warningTime);

  // Keep session alive
  setInterval(function() {
    fetch('<?php echo getBaseURL(); ?>/includes/keep_alive.php')
      .catch(function(error) {
        console.log('Session keep-alive failed:', error);
      });
  }, 10 * 60 * 1000);
</script>
<?php endif; ?>

<?php if (isLoggedIn() && isAdmin()): ?>
<script>
  $(document).ready(function() {
    checkLowStock();
  });

  function checkLowStock() {
    fetch('<?php echo getBaseURL(); ?>/admin/check_low_stock.php')
      .then(response => response.json())
      .then(data => {
        if (data.count > 0) {
          showLowStockNotification(data.count, data.items);
        }
      })
      .catch(error => console.error('Low stock check failed:', error));
  }

  function showLowStockNotification(count, items) {
    let itemsList = items.map(item => `${item.item_name} (${item.quantity} ${item.unit})`).join(', ');
    let notification = `
      <div class="alert alert-warning alert-dismissible fade show low-stock-alert" role="alert" style="border-left: 4px solid #7d5633;">
        <i class="fas fa-exclamation-triangle me-2" style="color: #7d5633;"></i>
        <strong>Low Stock Alert!</strong> ${count} item(s) running low: ${itemsList}
        <a href="<?php echo getBaseURL(); ?>/admin/inventory.php?filter=low_stock" class="alert-link" style="color: #382417; font-weight: 600;">View Details</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    `;
    if (!$('.low-stock-alert').length) {
      $('main').prepend(notification);
    }
  }
</script>
<?php endif; ?>

<style>
  .footer-custom {
    background: linear-gradient(135deg, #382417 0%, #4d3420 100%);
    color: #f0a54a;
    padding: 2rem 0;
    margin-top: 3rem;
    box-shadow: 0 -2px 15px rgba(56, 36, 23, 0.2);
  }

  .footer-custom p {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
  }

  .footer-custom i {
    color: #f0a54a;
  }

  @media (min-width: 768px) {
    .footer-custom .text-md-start p {
      justify-content: flex-start;
    }

    .footer-custom .text-md-end p {
      justify-content: flex-end;
    }
  }
</style>

<?php require_once BASE_PATH . '/includes/order_notif.php'; ?>

<!-- Internet Connection Toast -->
<div class="connection-toast" id="connectionToast">
  <i class="fas fa-wifi" id="connectionIcon"></i>
  <span id="connectionMsg"></span>
</div>

<script>
(function () {
  const toast   = document.getElementById('connectionToast');
  const icon    = document.getElementById('connectionIcon');
  const msg     = document.getElementById('connectionMsg');
  let hideTimer = null;

  function showConnectionToast(isOnline) {
    clearTimeout(hideTimer);
    if (isOnline) {
      toast.className = 'connection-toast online';
      icon.className  = 'fas fa-wifi';
      msg.textContent = 'Connection restored.';
      hideTimer = setTimeout(() => toast.classList.remove('show'), 3500);
    } else {
      toast.className = 'connection-toast offline';
      icon.className  = 'fas fa-wifi-slash';
      msg.textContent = 'No internet connection.';
    }
    toast.classList.add('show');
  }

  window.addEventListener('online',  () => showConnectionToast(true));
  window.addEventListener('offline', () => showConnectionToast(false));
})();
</script>
</body>
</html>
