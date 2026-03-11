<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

$status  = 'error';
$message = 'Invalid or missing verification link.';

if (!empty($_GET['token'])) {
    $raw_token  = trim($_GET['token']);
    $token_hash = hash('sha256', $raw_token);

    // Clean up expired entries
    $conn->query("DELETE FROM pending_verifications WHERE expires_at < NOW()");

    // Look up the pending record
    $stmt = $conn->prepare("
        SELECT * FROM pending_verifications
        WHERE token_hash = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $pending = $result->fetch_assoc();

        // Double-check expiry
        if (strtotime($pending['expires_at']) < time()) {
            $status  = 'expired';
            $message = 'This verification link has expired. Please sign up again.';
        } else {
            // Check if username/email was registered by someone else in the meantime
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $chk->bind_param("ss", $pending['email'], $pending['username']);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                // Clean up pending row
                $conn->query("DELETE FROM pending_verifications WHERE token_hash = '" . $token_hash . "'");
                $status  = 'error';
                $message = 'This email or username was already registered. Please sign up with different details.';
            } else {
                // NOW create the real account
                $conn->begin_transaction();
                try {
                    // Insert into users
                    $ins = $conn->prepare("
                        INSERT INTO users (username, email, password, full_name, role)
                        VALUES (?, ?, ?, ?, 'customer')
                    ");
                    $ins->bind_param("ssss",
                        $pending['username'],
                        $pending['email'],
                        $pending['password_hash'],
                        $pending['full_name']
                    );
                    $ins->execute();
                    $user_id = $conn->insert_id;

                    // Insert into customers
                    $ins2 = $conn->prepare("
                        INSERT INTO customers (user_id, phone, address)
                        VALUES (?, ?, ?)
                    ");
                    $ins2->bind_param("iss", $user_id, $pending['phone'], $pending['address']);
                    $ins2->execute();

                    // Delete the pending row
                    $del = $conn->prepare("DELETE FROM pending_verifications WHERE token_hash = ?");
                    $del->bind_param("s", $token_hash);
                    $del->execute();

                    $conn->commit();

                    $status  = 'success';
                    $message = 'Your email has been verified and your account is ready! You can now log in.';

                } catch (\Exception $e) {
                    $conn->rollback();
                    error_log('[VERIFY] Account creation failed: ' . $e->getMessage());
                    $status  = 'error';
                    $message = 'Something went wrong while creating your account. Please try again.';
                }
            }
        }
    } else {
        $message = 'Invalid verification link. It may have already been used or expired.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Verification - Coffee by Monday Mornings</title>
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
      overflow: hidden;
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
      0%,100% { transform: scale(1); opacity: 0.5; }
      50%      { transform: scale(1.1); opacity: 0.8; }
    }

    .card {
      background: white;
      padding: 48px 40px;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.15);
      width: 100%;
      max-width: 420px;
      text-align: center;
      position: relative;
      z-index: 1;
      animation: slideUp 0.6s ease;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(30px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .icon {
      font-size: 56px;
      margin-bottom: 20px;
      display: block;
    }

    .icon.success { color: #059669; }
    .icon.error   { color: #dc2626; }
    .icon.expired { color: #b45309; }

    h2 {
      color: #432109;
      font-size: 22px;
      margin-bottom: 12px;
      font-weight: bold;
    }

    p {
      color: #666;
      font-size: 14px;
      line-height: 1.6;
      margin-bottom: 28px;
    }

    .btn {
      display: inline-block;
      padding: 13px 32px;
      background: linear-gradient(135deg, #8B4513, #654321);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-size: 14px;
      font-weight: bold;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.3s ease;
      box-shadow: 0 8px 20px rgba(101,67,33,0.3);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(101,67,33,0.4);
    }

    .logo { margin-bottom: 8px; }
    .logo img { width: 100px; height: auto; }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">
      <img src="<?= BASE_URL ?>/assets/images/logo1.png" alt="Coffee by Monday Mornings">
    </div>

    <?php if ($status === 'success'): ?>
      <i class="fas fa-check-circle icon success"></i>
      <h2>Account Activated!</h2>
      <p><?= htmlspecialchars($message) ?></p>
      <a href="<?= BASE_URL ?>/customer_login.php" class="btn">
        <i class="fas fa-sign-in-alt"></i> Log In Now
      </a>

    <?php elseif ($status === 'expired'): ?>
      <i class="fas fa-clock icon expired"></i>
      <h2>Link Expired</h2>
      <p><?= htmlspecialchars($message) ?></p>
      <a href="<?= BASE_URL ?>/customer_signup.php" class="btn">
        <i class="fas fa-user-plus"></i> Sign Up Again
      </a>

    <?php else: ?>
      <i class="fas fa-times-circle icon error"></i>
      <h2>Verification Failed</h2>
      <p><?= htmlspecialchars($message) ?></p>
      <a href="<?= BASE_URL ?>/customer_signup.php" class="btn">
        <i class="fas fa-arrow-left"></i> Back to Sign Up
      </a>
    <?php endif; ?>
  </div>
</body>
</html>