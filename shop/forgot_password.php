<?php
/**
 * forgot_password.php
 * Place in project root — same level as customer_login.php
 *
 * REQUIRED SQL (run once in coffee_shop database):
 * ────────────────────────────────────────────────
 * CREATE TABLE IF NOT EXISTS password_reset_tokens (
 *   id         INT AUTO_INCREMENT PRIMARY KEY,
 *   user_id    INT NOT NULL,
 *   token      VARCHAR(64) NOT NULL UNIQUE,
 *   expires_at DATETIME NOT NULL,
 *   used       TINYINT(1) NOT NULL DEFAULT 0,
 *   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   INDEX idx_token   (token),
 *   INDEX idx_user_id (user_id)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 * ────────────────────────────────────────────────
 */

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

// Force MySQL session timezone to match PHP (Asia/Manila)
// so NOW() + INTERVAL 1 HOUR stores the correct expiry time.
$conn->query("SET time_zone = '+08:00'");

$message     = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');

    if (empty($identifier)) {
        $message     = 'Please enter your username or email address.';
        $messageType = 'error';
    } else {
        // Look up by username OR email
        $stmt = $conn->prepare(
            "SELECT id, username, email FROM users
             WHERE (username = ? OR email = ?) AND role = 'customer'
             LIMIT 1"
        );
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && !empty($user['email'])) {
            $token = bin2hex(random_bytes(32));

            // Remove old tokens for this user
            $del = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
            $del->bind_param("i", $user['id']);
            $del->execute();
            $del->close();

            // Store new token — use MySQL NOW() so expires_at and the
            // validation query both use the same database clock (avoids
            // PHP timezone vs MySQL timezone mismatch).
            $ins = $conn->prepare(
                "INSERT INTO password_reset_tokens (user_id, token, expires_at)
                 VALUES (?, ?, NOW() + INTERVAL 1 HOUR)"
            );
            $ins->bind_param("is", $user['id'], $token);
            $ins->execute();
            $ins->close();

            // Build reset link
            $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $resetLink = $protocol . '://' . $_SERVER['HTTP_HOST']
                       . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')
                       . '/reset_password.php?token=' . $token;

            // Send via PHPMailer
            // Search multiple candidate paths so it works whether PHPMailer is
            // placed inside shop/includes/ or the shared portal/includes/ folder.
            $sent = false;
            $phpmailerCandidates = [
                dirname(__DIR__) . '/portal/includes/PHPMailer/', // bymonday/portal/includes/PHPMailer/ ✓
                __DIR__ . '/includes/PHPMailer/',                  // shop/includes/PHPMailer/
                __DIR__ . '/PHPMailer/',                           // shop/PHPMailer/
            ];
            $phpmailerDir = null;
            foreach ($phpmailerCandidates as $candidate) {
                if (file_exists($candidate . 'PHPMailer.php')) {
                    $phpmailerDir = $candidate;
                    break;
                }
            }

            if ($phpmailerDir === null) {
                error_log('[ForgotPassword] PHPMailer not found. Checked: ' . implode(', ', $phpmailerCandidates));
            } else {
                require_once $phpmailerDir . 'Exception.php';
                require_once $phpmailerDir . 'PHPMailer.php';
                require_once $phpmailerDir . 'SMTP.php';

                $mail     = new \PHPMailer\PHPMailer\PHPMailer(true);
                $appName  = 'Coffee by Monday Mornings';
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
                    $mail->AltBody = "Hi {$user['username']},\n\nReset your password (valid 1 hour):\n{$resetLink}\n\nIf you didn't request this, ignore this email.\n\n— Coffee by Monday Mornings";
                    $mail->send();
                    $sent = true;
                } catch (\Exception $e) {
                    error_log('[ForgotPassword] PHPMailer error: ' . $mail->ErrorInfo);
                }
            } // end if phpmailerDir found

            $message     = $sent
                ? 'A password reset link has been sent to your email. Please check your inbox.'
                : 'Failed to send the reset email. Please try again or contact support.';
            $messageType = $sent ? 'success' : 'error';
        } else {
            $message     = 'No account found with that username or email. Please check and try again.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - Coffee by Monday Mornings</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #F4F1E8;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
      position: relative;
      overflow-y: scroll;
      overflow-x: hidden;
    }

    body::before {
      content: '';
      position: absolute;
      width: 400px; height: 400px;
      background: radial-gradient(circle, rgba(212,136,59,0.15) 0%, transparent 70%);
      border-radius: 50%;
      top: -150px; left: -150px;
      animation: pulse 4s ease-in-out infinite;
    }

    body::after {
      content: '';
      position: absolute;
      width: 350px; height: 350px;
      background: radial-gradient(circle, rgba(255,163,26,0.1) 0%, transparent 70%);
      border-radius: 50%;
      bottom: -100px; right: -100px;
      animation: pulse 5s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 0.5; }
      50%       { transform: scale(1.1); opacity: 0.8; }
    }

    .login-container {
      background: white;
      padding: 30px 30px;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.15);
      width: 100%; max-width: 450px;
      position: relative; z-index: 1;
      animation: slideUp 0.6s ease;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(30px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .back-btn {
      background: transparent;
      border: 2px solid #e0e0e0;
      color: #432109;
      font-size: 13px; cursor: pointer;
      margin-bottom: 15px;
      display: inline-flex; align-items: center; gap: 6px;
      padding: 7px 14px; border-radius: 20px;
      font-weight: 600; transition: all 0.3s ease;
    }

    .back-btn:hover {
      background: #f4f1e8; border-color: #d4883b;
      color: #d4883b; transform: translateX(-5px);
    }

    .logo-small { text-align: center; margin-top: -30px; margin-bottom: -30px; }

    .logo-small img {
      width: 140px; height: auto;
      animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50%       { transform: translateY(-10px); }
    }

    h2 { text-align: center; color: #432109; margin-bottom: 6px; font-size: 26px; font-weight: bold; }

    .subtitle { text-align: center; color: #d4883b; margin-bottom: 22px; font-size: 13px; font-weight: 500; }

    .form-group { margin-bottom: 16px; position: relative; }

    label { display: block; color: #432109; margin-bottom: 7px; font-size: 13px; font-weight: 600; }

    .input-wrapper { position: relative; }

    .input-icon {
      position: absolute; left: 15px; top: 50%;
      transform: translateY(-50%); color: #654321; font-size: 14px;
    }

    input {
      width: 100%; padding: 12px 14px 12px 42px;
      border: 2px solid #e0e0e0; border-radius: 12px;
      font-size: 14px; transition: all 0.3s ease;
      background: #f9f9f9; font-family: inherit;
    }

    input:focus {
      outline: none; border-color: #654321; background: white;
      box-shadow: 0 0 0 4px rgba(212,136,59,0.1);
    }

    .login-btn {
      width: 100%; padding: 13px;
      background: linear-gradient(135deg, #8B4513, #654321);
      color: #fff; border: none; border-radius: 12px;
      font-size: 14px; font-weight: bold; cursor: pointer;
      transition: all 0.3s ease; margin-top: 6px;
      box-shadow: 0 8px 20px rgba(101,67,33,0.3);
      text-transform: uppercase; letter-spacing: 0.5px;
    }

    .login-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(101,67,33,0.4); }
    .login-btn:active  { transform: translateY(0); }
    .login-btn:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

    .divider {
      display: flex; align-items: center;
      margin: 18px 0; color: #999; font-size: 12px;
    }

    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e0e0e0; }
    .divider span { padding: 0 16px; font-weight: 500; }

    .signup-link { text-align: center; color: #666; font-size: 13px; }

    .signup-link a { color: #d4883b; text-decoration: none; font-weight: bold; transition: all 0.3s ease; }
    .signup-link a:hover { color: #432109; text-decoration: underline; }

    .error {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      border: 2px solid #fca5a5; color: #991b1b;
      padding: 10px 14px; border-radius: 12px; margin-bottom: 16px;
      font-size: 12px; display: flex; align-items: center; gap: 8px;
      animation: shake 0.5s ease;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25%       { transform: translateX(-10px); }
      75%       { transform: translateX(10px); }
    }

    .error i { font-size: 16px; }

    .success {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      border: 2px solid #6ee7b7; color: #065f46;
      padding: 10px 14px; border-radius: 12px; margin-bottom: 16px;
      font-size: 12px; display: flex; align-items: center; gap: 8px;
      animation: slideDown 0.5s ease;
    }

    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .success i { font-size: 18px; }

    .help-note { text-align: center; color: #aaa; font-size: 11px; margin-top: 10px; }

    @media (max-width: 580px) {
      .login-container { padding: 30px 25px; }
      h2 { font-size: 24px; }
      .logo-small img { width: 140px; }
      .login-btn { padding: 14px; font-size: 14px; }
    }
  </style>
</head>
<body>
  <div class="login-container">

    <button class="back-btn" onclick="location.href='customer_login.php'">
      <i class="fas fa-arrow-left"></i> Back to Login
    </button>

    <div class="logo-small">
      <img src="<?= BASE_URL ?>/assets/images/logo1.png" alt="Coffee by Monday Mornings">
    </div>

    <h2>Forgot Password?</h2>
    <p class="subtitle">We'll send a reset link to your email</p>

    <?php if (!empty($message)): ?>
      <div class="<?= $messageType === 'success' ? 'success' : 'error'; ?>">
        <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <span><?= htmlspecialchars($message); ?></span>
      </div>
    <?php endif; ?>

    <?php if (empty($message) || $messageType === 'error'): ?>
    <form action="" method="POST" id="forgotForm">
      <div class="form-group">
        <label>Username or Email</label>
        <div class="input-wrapper">
          <i class="fas fa-user input-icon"></i>
          <input
            type="text"
            name="identifier"
            required
            autocomplete="username"
            placeholder="Enter your username or email"
            value="<?= htmlspecialchars($_POST['identifier'] ?? ''); ?>"
          >
        </div>
      </div>

      <button type="submit" class="login-btn" id="submitBtn">
        <i class="fas fa-paper-plane" style="margin-right:6px;"></i>Send Reset Link
      </button>

      <p class="help-note">
        <i class="fas fa-info-circle" style="color:#d4883b;"></i>
        Use the email address linked to your account.
      </p>
    </form>
    <?php endif; ?>

    <div class="divider"><span>or</span></div>

    <div class="signup-link">
      Don't have an account? <a href="customer_signup.php">Create one now</a>
    </div>

  </div>

  <script>
    document.getElementById('forgotForm')?.addEventListener('submit', function() {
      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i>Sending…';
    });
  </script>
</body>
</html>