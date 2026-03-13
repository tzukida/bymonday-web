<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/email_config.php';

if (!isLoggedIn() || $_SESSION['role'] != 'customer') {
    redirect('customer_login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile.php');
}

$conn->query("SET time_zone = '+08:00'");

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id, username, email FROM users WHERE id = ? AND role = 'customer' LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || empty($user['email'])) {
    $_SESSION['profile_error'] = 'No email address found on your account.';
    redirect('profile.php');
}

$token = bin2hex(random_bytes(32));

$del = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
$del->bind_param("i", $user['id']);
$del->execute();
$del->close();

$ins = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)");
$ins->bind_param("is", $user['id'], $token);
$ins->execute();
$ins->close();

$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$resetLink = $protocol . '://' . $_SERVER['HTTP_HOST']
           . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')
           . '/reset_password.php?token=' . $token;

$sent = false;
$phpmailerCandidates = [
    dirname(__DIR__) . '/portal/includes/PHPMailer/',
    __DIR__ . '/includes/PHPMailer/',
    __DIR__ . '/PHPMailer/',
];
$phpmailerDir = null;
foreach ($phpmailerCandidates as $candidate) {
    if (file_exists($candidate . 'PHPMailer.php')) {
        $phpmailerDir = $candidate;
        break;
    }
}

if ($phpmailerDir !== null) {
    require_once $phpmailerDir . 'Exception.php';
    require_once $phpmailerDir . 'PHPMailer.php';
    require_once $phpmailerDir . 'SMTP.php';

    $mail    = new \PHPMailer\PHPMailer\PHPMailer(true);
    $appName = 'Coffee by Monday Mornings';
    date_default_timezone_set('Asia/Manila');
    $sentDate = date('F j, Y g:i A');

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('GMAIL_USER')     ? GMAIL_USER     : 'angelaccortes01@gmail.com';
        $mail->Password   = defined('GMAIL_APP_PASS') ? GMAIL_APP_PASS : 'mjby lqxy gkzd zikw';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($mail->Username, $appName);
        $mail->addAddress($user['email'], $user['username']);
        $mail->Subject = 'Password Reset Request — ' . $appName;
        $mail->isHTML(true);

        $mail->Body = "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;background:#F4F1E8;'>
  <div style='max-width:520px;margin:32px auto;background:#fff;border-radius:20px;
              box-shadow:0 20px 60px rgba(0,0,0,0.15);overflow:hidden;'>
    <div style='background:linear-gradient(135deg,#8B4513,#654321);padding:30px 32px;text-align:center;'>
      <h2 style='color:#fff;margin:0;font-size:1.3rem;'>&#9749; Password Reset</h2>
      <p style='color:rgba(255,255,255,0.65);margin:6px 0 0;font-size:0.82rem;'>{$sentDate}</p>
    </div>
    <div style='padding:30px 32px;'>
      <p style='color:#432109;margin-top:0;'>Hi <strong>{$user['username']}</strong>,</p>
      <p style='color:#555;line-height:1.7;'>
        We received a request to reset your password for your
        <strong>Coffee by Monday Mornings</strong> account.
        Click the button below — this link is valid for <strong>1 hour</strong>.
      </p>
      <div style='text-align:center;margin:28px 0;'>
        <a href='{$resetLink}'
           style='display:inline-block;padding:14px 38px;
                  background:linear-gradient(135deg,#8B4513,#654321);
                  color:#fff;text-decoration:none;border-radius:12px;
                  font-weight:bold;font-size:1rem;
                  box-shadow:0 8px 20px rgba(101,67,33,0.3);'>
          Reset My Password
        </a>
      </div>
      <p style='color:#888;font-size:0.82rem;line-height:1.6;'>
        If the button doesn't work, copy this link into your browser:<br>
        <a href='{$resetLink}' style='color:#d4883b;word-break:break-all;'>{$resetLink}</a>
      </p>
      <p style='color:#bbb;font-size:0.78rem;margin-top:20px;
                border-top:1px solid #f0e8e0;padding-top:14px;'>
        If you didn't request this, you can safely ignore this email.
      </p>
    </div>
    <div style='background:#F4F1E8;padding:14px 32px;border-top:1px solid #e8ddd5;'>
      <p style='color:#aaa;font-size:0.74rem;margin:0;text-align:center;'>
        Sent automatically by <strong style='color:#888;'>Coffee by Monday Mornings</strong>.
      </p>
    </div>
  </div>
</body>
</html>";

        $mail->send();
        $sent = true;
    } catch (\Exception $e) {
        $sent = false;
    }
}

if ($sent) {
    $_SESSION['profile_success'] = 'Password reset link sent to ' . $user['email'] . '. Check your inbox.';
} else {
    $_SESSION['profile_error'] = 'Failed to send reset email. Please try again.';
}

redirect('profile.php');
?>