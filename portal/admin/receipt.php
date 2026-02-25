<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';

  requireAuth();

  $sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;
  $sale = getSaleById($sale_id);

  if (!$sale) {
    die('Sale not found');
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Receipt #<?php echo $sale_id; ?> - J's Rice Bowl</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    @media print {
      .no-print { display: none !important; }
      body { margin: 0; background: white; }
      .receipt-container {
        box-shadow: none !important;
      }
    }

    body {
      background: #f5f5f5;
      padding: 20px;
    }

    .receipt-container {
      max-width: 380px;
      margin: 20px auto;
      padding: 25px 20px;
      border: 2px solid #000;
      font-family: 'Courier New', monospace;
      background: white;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .receipt-header {
      text-align: center;
      border-bottom: 2px dashed #000;
      padding-bottom: 15px;
      margin-bottom: 15px;
    }

    .store-logo {
      font-size: 32px;
      margin-bottom: 5px;
    }

    .receipt-title {
      font-size: 22px;
      font-weight: bold;
      margin-bottom: 3px;
      letter-spacing: 1px;
    }

    .store-subtitle {
      font-size: 11px;
      margin-bottom: 8px;
      color: #333;
    }

    .store-contact {
      font-size: 11px;
      color: #555;
    }

    .receipt-number {
      text-align: center;
      font-size: 14px;
      font-weight: bold;
      padding: 8px;
      background: #f9f9f9;
      border: 1px solid #ddd;
      margin-bottom: 12px;
      letter-spacing: 2px;
    }

    .receipt-info {
      margin-bottom: 15px;
      font-size: 11px;
      line-height: 1.6;
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 2px;
    }

    .info-label {
      font-weight: bold;
    }

    .divider {
      border-top: 1px dashed #999;
      margin: 12px 0;
    }

    .divider-bold {
      border-top: 2px dashed #000;
      margin: 15px 0;
    }

    .receipt-items {
      border-top: 2px dashed #000;
      padding: 12px 0;
      margin-bottom: 10px;
    }

    .items-header {
      display: flex;
      justify-content: space-between;
      font-weight: bold;
      font-size: 10px;
      padding-bottom: 6px;
      margin-bottom: 10px;
      border-bottom: 1px solid #666;
      text-transform: uppercase;
    }

    .receipt-item {
      margin-bottom: 10px;
      font-size: 12px;
    }

    .item-name {
      font-weight: bold;
      margin-bottom: 2px;
    }

    .item-row {
      display: flex;
      justify-content: space-between;
      font-size: 11px;
      color: #555;
    }

    .item-qty {
      background: #f0f0f0;
      padding: 1px 6px;
      border-radius: 2px;
      font-weight: bold;
      margin-right: 5px;
    }

    .receipt-total {
      font-size: 16px;
      font-weight: bold;
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      border-top: 2px solid #000;
      border-bottom: 2px solid #000;
      margin-bottom: 12px;
    }

    .payment-badge {
      text-align: center;
      padding: 8px;
      background: #f9f9f9;
      border: 1px dashed #999;
      margin-bottom: 15px;
      font-size: 11px;
      font-weight: bold;
    }

    .remarks-box {
      background: #fffbea;
      border-left: 3px solid #f39c12;
      padding: 10px;
      margin-bottom: 15px;
      font-size: 11px;
    }

    .remarks-label {
      font-weight: bold;
      margin-bottom: 4px;
      font-size: 10px;
      text-transform: uppercase;
    }

    .barcode-section {
      text-align: center;
      padding: 10px;
      margin: 15px 0;
      border: 1px dashed #ccc;
      background: #fafafa;
    }

    .barcode {
      font-size: 20px;
      font-weight: bold;
      letter-spacing: 2px;
      margin-bottom: 4px;
    }

    .barcode-number {
      font-size: 9px;
      color: #666;
      letter-spacing: 1px;
    }

    .receipt-footer {
      text-align: center;
      font-size: 11px;
      border-top: 2px dashed #000;
      padding-top: 15px;
      line-height: 1.6;
    }

    .footer-thanks {
      font-weight: bold;
      font-size: 13px;
      margin-bottom: 5px;
    }

    .footer-links {
      margin-top: 10px;
      font-size: 10px;
      color: #666;
    }

    .footer-divider {
      margin: 12px 0;
      color: #ccc;
    }

    /* Button Styles */
    .no-print {
      text-align: center;
      margin-bottom: 20px;
      max-width: 380px;
      margin-left: auto;
      margin-right: auto;
    }

    .no-print .btn {
      margin: 0 5px;
      padding: 10px 20px;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      font-weight: 600;
      border-radius: 6px;
    }

    .btn-primary {
      background: #751312;
      border-color: #751312;
    }

    .btn-primary:hover {
      background: #5a0f0e;
      border-color: #5a0f0e;
    }
  </style>
</head>
<body>
  <div class="no-print">
    <button onclick="window.print()" class="btn btn-primary">
      <i class="fas fa-print me-2"></i>Print Receipt
    </button>
    <button onclick="window.close()" class="btn btn-secondary">
      <i class="fas fa-times me-2"></i>Close
    </button>
  </div>

  <div class="receipt-container">
    <!-- Header -->
    <div class="receipt-header">
      <div class="store-logo"></div>
      <div class="receipt-title">J'S RICE BOWL</div>
      <div class="store-subtitle">Filipino Rice Meals & Desserts</div>
      <div class="store-contact">(+63) 977-6378-932</div>
    </div>

    <!-- Receipt Number -->
    <div class="receipt-number">
      #<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?>
    </div>

    <!-- Transaction Info -->
    <div class="receipt-info">
      <div class="info-row">
        <span class="info-label">Date:</span>
        <span><?php echo formatDate($sale['sale_date'], 'M j, Y'); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Time:</span>
        <span><?php echo formatDate($sale['sale_date'], 'g:i A'); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Cashier:</span>
        <span><?php echo htmlspecialchars($sale['username']); ?></span>
      </div>
      <?php if ($sale['customer_name']): ?>
      <div class="info-row">
        <span class="info-label">Customer:</span>
        <span><?php echo htmlspecialchars($sale['customer_name']); ?></span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Items -->
    <div class="receipt-items">
      <div class="items-header">
        <span>Item</span>
        <span>Amount</span>
      </div>

      <?php foreach ($sale['items'] as $item): ?>
      <div class="receipt-item">
        <div class="item-name"><?php echo htmlspecialchars($item['menu_item_name']); ?></div>
        <div class="item-row">
          <div>
            <span class="item-qty"><?php echo $item['quantity']; ?>x</span>
            <span>₱<?php echo number_format($item['unit_price'], 2); ?></span>
          </div>
          <div style="font-weight: bold;">₱<?php echo number_format($item['subtotal'], 2); ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Total -->
    <div class="receipt-total">
      <div>TOTAL:</div>
      <div>₱<?php echo number_format($sale['total_amount'], 2); ?></div>
    </div>

    <!-- Payment Method -->
    <div class="payment-badge">
      <?php
        $payment_icons = [
          'cash' => 'fa-money-bill-wave',
          'gcash' => 'fa-mobile-alt',
          'card' => 'fa-credit-card'
        ];
        $method = strtolower($sale['payment_method']);
        $icon = $payment_icons[$method] ?? 'fa-money-bill-wave';
      ?>
      <i class="fas <?php echo $icon; ?>"></i>
      PAID VIA <?php echo strtoupper($sale['payment_method']); ?>
    </div>

    <!-- Remarks -->
    <?php if ($sale['remarks']): ?>
    <div class="remarks-box">
      <div class="remarks-label">Special Notes:</div>
      <div><?php echo nl2br(htmlspecialchars($sale['remarks'])); ?></div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="receipt-footer">
      <div class="footer-thanks">Thank you for your order!</div>
      <div>Please come again!</div>
      <div class="footer-links">
        <div>www.js-rice-bowl.com</div>
        <div>Facebook: J's Rice Bowl</div>
      </div>
      <div class="footer-divider">═══════════════════</div>
      <div style="font-size: 9px; color: #999;">Powered by J's Rice Bowl System</div>
    </div>
  </div>

  <script>
    document.addEventListener('keydown', function(e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        window.print();
      }
      if (e.key === 'Escape') {
        window.close();
      }
    });
  </script>
</body>
</html>
