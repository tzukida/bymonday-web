<?php
/**
 * includes/email_supplier.php
 *
 * SETUP — no Composer needed, just download 3 PHPMailer files:
 *
 *   https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php
 *   https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php
 *   https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php
 *
 * Save all 3 into:  portal/includes/PHPMailer/
 *
 * Then fill in GMAIL_USER and GMAIL_APP_PASS below.
 * Get an App Password at: myaccount.google.com → Security → 2-Step → App Passwords
 */

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';

requireAuth();

ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input         = json_decode(file_get_contents('php://input'), true);
$supplierEmail = trim($input['supplier_email'] ?? '');
$items         = $input['items'] ?? [];

if (empty($supplierEmail) || !filter_var($supplierEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'A valid supplier email is required.']);
    exit;
}
if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'No items to restock.']);
    exit;
}

// ════════════════════════════════════════════════
//  YOUR GMAIL CREDENTIALS — edit these two lines
// ════════════════════════════════════════════════
define('GMAIL_USER',     'angelaccortes01@gmail.com');  // ← your Gmail address
define('GMAIL_APP_PASS', 'mjby lqxy gkzd zikw');
// ════════════════════════════════════════════════

$today     = date('F j, Y');
$reporter  = ($_SESSION['username'] ?? 'System') . ' (' . ucfirst($_SESSION['role'] ?? 'user') . ')';
$subject   = 'Restock Request - ' . date('Y-m-d');
$fromName  = 'ByMonday Inventory System';

// ── Plain-text body ───────────────────────────────────────────
$textBody  = "Dear Supplier,\n\n";
$textBody .= "We would like to request a restock for the following items as of {$today}:\n\n";
$textBody .= str_repeat('-', 52) . "\n";
$textBody .= str_pad('Item', 28) . str_pad('Requested Qty', 16) . "Unit\n";
$textBody .= str_repeat('-', 52) . "\n";
foreach ($items as $item) {
    $textBody .= str_pad($item['name'] ?? '', 28)
               . str_pad((int)($item['qty'] ?? 1), 16)
               . ($item['unit'] ?? '') . "\n";
}
$textBody .= str_repeat('-', 52) . "\n\n";
$textBody .= "Total items: " . count($items) . "\n";
$textBody .= "Requested by: {$reporter}\n\n";
$textBody .= "Please confirm availability and provide the earliest possible delivery date.\n\n";
$textBody .= "Best regards,\n{$fromName}\nSent on: " . date('F j, Y g:i A') . "\n";

// ── HTML body ─────────────────────────────────────────────────
$itemRowsHtml = '';
foreach ($items as $item) {
    $name = htmlspecialchars($item['name'] ?? '');
    $qty  = (int)($item['qty'] ?? 1);
    $unit = htmlspecialchars($item['unit'] ?? '');
    $itemRowsHtml .= "
        <tr>
          <td style='padding:10px 14px;border-bottom:1px solid #f0e8e0;'>
            <span style='color:#e07b39;margin-right:7px;'>&#9679;</span>
            <strong style='color:#2d2d2d;'>{$name}</strong>
          </td>
          <td style='padding:10px 14px;border-bottom:1px solid #f0e8e0;
                     text-align:center;font-weight:700;color:#4a301f;'>{$qty}</td>
          <td style='padding:10px 14px;border-bottom:1px solid #f0e8e0;color:#888;'>{$unit}</td>
        </tr>";
}
$totalItems = count($items);
$sentDate   = date('F j, Y g:i A');

$htmlBody = "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;font-family:Arial,sans-serif;background:#f5f0eb;'>
  <div style='max-width:580px;margin:32px auto;background:#fff;border-radius:14px;
              box-shadow:0 2px 16px rgba(0,0,0,0.1);overflow:hidden;'>
    <div style='background:#4a301f;padding:28px 32px;'>
      <h2 style='color:#fff;margin:0;font-size:1.3rem;'>&#128230; Restock Request</h2>
      <p style='color:rgba(255,255,255,0.65);margin:5px 0 0;font-size:0.88rem;'>{$today}</p>
    </div>
    <div style='padding:28px 32px;'>
      <p style='color:#444;margin-top:0;'>Dear Supplier,</p>
      <p style='color:#444;'>Please restock the following low-stock items and confirm
         the earliest available delivery date.</p>
      <table width='100%' cellpadding='0' cellspacing='0'
             style='border-collapse:collapse;border:1px solid #f0e8e0;
                    border-radius:8px;overflow:hidden;margin:18px 0;'>
        <thead>
          <tr style='background:#f8f3ef;'>
            <th style='padding:10px 14px;text-align:left;color:#4a301f;
                       font-size:0.78rem;text-transform:uppercase;letter-spacing:0.6px;'>Item</th>
            <th style='padding:10px 14px;text-align:center;color:#4a301f;
                       font-size:0.78rem;text-transform:uppercase;letter-spacing:0.6px;'>Qty</th>
            <th style='padding:10px 14px;text-align:left;color:#4a301f;
                       font-size:0.78rem;text-transform:uppercase;letter-spacing:0.6px;'>Unit</th>
          </tr>
        </thead>
        <tbody>{$itemRowsHtml}</tbody>
        <tfoot>
          <tr style='background:#f8f3ef;'>
            <td colspan='3' style='padding:9px 14px;font-size:0.82rem;color:#888;text-align:right;'>
              Total: <strong style='color:#4a301f;'>{$totalItems} item(s)</strong>
            </td>
          </tr>
        </tfoot>
      </table>
      <p style='color:#444;margin-bottom:4px;'>Best regards,</p>
      <p style='color:#4a301f;font-weight:700;margin-top:0;'>{$fromName}</p>
    </div>
    <div style='background:#f8f3ef;padding:14px 32px;border-top:1px solid #ede5dd;'>
      <p style='color:#aaa;font-size:0.76rem;margin:0;'>
        Requested by: <strong style='color:#888;'>{$reporter}</strong>
        &nbsp;|&nbsp; {$sentDate}
      </p>
      <p style='color:#ccc;font-size:0.72rem;margin:4px 0 0;'>
        Sent automatically by the ByMonday Inventory System.
      </p>
    </div>
  </div>
</body>
</html>";

// ── PHPMailer via Gmail SMTP ───────────────────────────────────
$phpmailerDir = BASE_PATH . '/includes/PHPMailer/';

if (!file_exists($phpmailerDir . 'PHPMailer.php')) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'PHPMailer not found. Download the 3 files into portal/includes/PHPMailer/ — see comment at top of email_supplier.php.'
    ]);
    exit;
}

if (GMAIL_APP_PASS === '') {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Gmail App Password not set. Open includes/email_supplier.php and paste your 16-character App Password into GMAIL_APP_PASS.'
    ]);
    exit;
}

require_once $phpmailerDir . 'Exception.php';
require_once $phpmailerDir . 'PHPMailer.php';
require_once $phpmailerDir . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$mail = new PHPMailer(true);
$errorDetail = '';

try {
    $mail->isSMTP();
    $mail->Host        = 'smtp.gmail.com';
    $mail->SMTPAuth    = true;
    $mail->Username    = GMAIL_USER;
    $mail->Password    = GMAIL_APP_PASS;
    $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port        = 587;
    $mail->CharSet     = 'UTF-8';

    $mail->setFrom(GMAIL_USER, $fromName);
    $mail->addAddress($supplierEmail);
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body    = $htmlBody;
    $mail->AltBody = $textBody;

    $mail->send();
    $sent = true;

} catch (MailException $e) {
    $sent        = false;
    $errorDetail = $mail->ErrorInfo;
}

ob_clean();
if ($sent) {
    logActivity(
        $_SESSION['user_id'],
        'Email Supplier',
        'Sent restock request to ' . $supplierEmail . '. Items: ' . count($items)
    );
    echo json_encode([
        'success' => true,
        'message' => 'Restock email sent to ' . $supplierEmail . '!'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Email failed: ' . ($errorDetail ?: 'Unknown error. Check App Password.')
    ]);
}