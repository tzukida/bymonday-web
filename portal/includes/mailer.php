<?php
/**
 * mailer.php - Combined restock request email via Gmail SMTP
 * PHPMailer files must be in: portal/includes/PHPMailer/
 */

define('LOW_STOCK_THRESHOLD', 10);

/**
 * Send ONE combined restock email with all low-stock items in a single table.
 *
 * @param string $to     Recipient email
 * @param array  $items  Array of ['name'=>, 'quantity'=>, 'unit'=>]
 */
function sendRestockEmail($to, array $items) {
    $dir = __DIR__ . '/PHPMailer/';
    if (!file_exists($dir . 'PHPMailer.php')) {
        error_log("[RestockEmail] PHPMailer not found at $dir");
        return false;
    }
    require_once $dir . 'PHPMailer.php';
    require_once $dir . 'SMTP.php';
    require_once $dir . 'Exception.php';

    $date      = date('Y-m-d');
    $threshold = LOW_STOCK_THRESHOLD;
    $count     = count($items);

    // Build plain text rows
    $plain_rows = '';
    foreach ($items as $item) {
        $plain_rows .= "[LOW STOCK] {$item['name']} | Current: {$item['quantity']} | Requested: {$item['quantity']} {$item['unit']} | Threshold: {$threshold} {$item['unit']}\n";
    }

    // Build HTML rows
    $html_rows = '';
    foreach ($items as $item) {
        $html_rows .= "
        <tr>
            <td style='border:1px solid #dee2e6;padding:10px 14px;font-weight:600;'>⚠️ {$item['name']}</td>
            <td style='border:1px solid #dee2e6;padding:10px 14px;color:#dc3545;font-weight:700;'>{$item['quantity']} / {$threshold} {$item['unit']}</td>
            <td style='border:1px solid #dee2e6;padding:10px 14px;color:#198754;font-weight:700;'>{$item['quantity']} {$item['unit']}</td>
        </tr>";
    }

    $subject = "Restock Request — $date ($count item" . ($count !== 1 ? 's' : '') . ")";

    $plain = "Dear Supplier,\n\n"
           . "We would like to request a restock for the following ingredients that have reached low-stock levels as of $date:\n\n"
           . "--------------------------------\n"
           . $plain_rows
           . "--------------------------------\n\n"
           . "Please confirm availability and provide the earliest possible delivery date.\n\n"
           . "Best regards,\nCoffee by Monday Mornings";

    $html = "
<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;font-family:Arial,sans-serif;background:#f4f4f4;'>
<table width='100%' cellpadding='0' cellspacing='0' style='padding:30px 0;background:#f4f4f4;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);'>
  <tr><td style='background:#3b2008;padding:24px 36px;'>
    <h2 style='color:#fff;margin:0;font-size:20px;'>☕ Coffee by Monday Mornings</h2>
    <p style='color:#e8c99a;margin:4px 0 0;font-size:13px;'>Inventory Restock Request</p>
  </td></tr>
  <tr><td style='background:#fff3cd;padding:12px 36px;border-bottom:2px solid #ffc107;'>
    <p style='margin:0;color:#856404;font-size:14px;font-weight:bold;'>⚠️ $count item" . ($count !== 1 ? 's' : '') . " need restocking as of $date</p>
  </td></tr>
  <tr><td style='padding:28px 36px;color:#212529;font-size:15px;line-height:1.7;'>
    <p>Dear Supplier,</p>
    <p>We would like to request a restock for the following ingredients that have reached low-stock levels:</p>
    <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;margin:20px 0;'>
      <tr style='background:#f8f9fa;'>
        <th style='border:1px solid #dee2e6;padding:10px 14px;text-align:left;color:#495057;'>Item</th>
        <th style='border:1px solid #dee2e6;padding:10px 14px;text-align:left;color:#495057;'>Current / Threshold</th>
        <th style='border:1px solid #dee2e6;padding:10px 14px;text-align:left;color:#495057;'>Qty Requested</th>
      </tr>
      $html_rows
    </table>
    <p>Please confirm availability and provide the earliest possible delivery date.</p>
    <p style='margin-top:24px;'>Best regards,<br><strong>Coffee by Monday Mornings</strong></p>
    <div style='margin-top:28px;text-align:center;'>
      <a href='http://localhost/bymonday/portal/admin/inventory.php'
         style='display:inline-block;background:#3b2008;color:#ffffff;text-decoration:none;
                padding:13px 32px;border-radius:8px;font-weight:700;font-size:15px;
                letter-spacing:0.3px;box-shadow:0 4px 12px rgba(59,32,8,0.3);'>
        📦 View Inventory
      </a>
    </div>
  </td></tr>
  <tr><td style='background:#f8f9fa;padding:16px 36px;border-top:1px solid #dee2e6;'>
    <p style='margin:0;color:#adb5bd;font-size:12px;'>Automated message from Coffee by Monday Mornings inventory system.</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>";

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'angelaccortes01@gmail.com';
        $mail->Password   = 'jiau zhtv axuq idjh';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 30;
        $mail->setFrom('angelaccortes01@gmail.com', 'Coffee by Monday Mornings');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $plain;
        $mail->send();
        error_log("[RestockEmail] ✅ Sent to $to — $count items");
        return true;
    } catch (\Exception $e) {
        error_log("[RestockEmail] ❌ Failed to $to: " . $e->getMessage());
        return false;
    }
}

/**
 * Legacy single-item wrapper — kept for stock_out.php auto-trigger
 */
function sendLowStockAlert($to, $item_name, $quantity, $unit) {
    return sendRestockEmail($to, [['name' => $item_name, 'quantity' => $quantity, 'unit' => $unit]]);
}