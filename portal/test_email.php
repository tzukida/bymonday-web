<?php

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/mailer.php';

echo "<h2>📧 Email Test</h2>";
echo "<p>Sending to: <strong>angelaccortes01@gmail.com</strong></p>";

$result = sendLowStockAlert(
    'angelaccortes01@gmail.com',
    'Arabica Beans',
    5,
    'kg'
);

if ($result) {
    echo "<p style='color:green;font-size:20px;'>✅ Email sent! Check your Gmail inbox (and spam folder).</p>";
} else {
    echo "<p style='color:red;font-size:18px;'>❌ Failed. Check XAMPP error log:</p>";
    echo "<p><code>C:/xampp/php/logs/php_error_log</code></p>";
    $log = 'C:/xampp/php/logs/php_error_log';
    if (file_exists($log)) {
        $lines = array_slice(file($log), -30);
        echo "<pre style='background:#111;color:#0f0;padding:12px;font-size:12px;'>";
        echo htmlspecialchars(implode('', $lines));
        echo "</pre>";
    }
}