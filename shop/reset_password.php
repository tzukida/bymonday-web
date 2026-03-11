<?php
/**
 * reset_password.php
 * Place in project root — same level as customer_login.php
 */

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

// Force MySQL session timezone to match PHP (Asia/Manila)
// so expires_at comparisons with NOW() are consistent.
$conn->query("SET time_zone = '+08:00'");

$token       = trim($_GET['token'] ?? $_POST['token'] ?? '');
$tokenValid  = false;
$tokenUser   = null;
$message     = '';
$messageType = '';
$success     = false;

// ── Validate token ───────────────────────────────────────────────────────────
if (!empty($token)) {
    $stmt = $conn->prepare(
        "SELECT prt.id, prt.user_id, u.username, u.email
         FROM password_reset_tokens prt
         JOIN users u ON prt.user_id = u.id
         WHERE prt.token = ?
           AND prt.used = 0
           AND prt.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $tokenUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $tokenValid = !empty($tokenUser);
}

if (!$tokenValid && empty($message)) {
    $message     = 'This password reset link is invalid or has already expired. Please request a new one.';
    $messageType = 'error';
}

// ── Handle new password submission ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $newPassword     = $_POST['new_password']     ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $minLen          = 8;

    if (strlen($newPassword) < $minLen) {
        $message     = "Password must be at least {$minLen} characters long.";
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message     = 'Passwords do not match. Please try again.';
        $messageType = 'error';
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param("si", $hashed, $tokenUser['user_id']);
        $updated = $upd->execute();
        $upd->close();

        if ($updated) {
            // Mark token as used
            $mark = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $mark->bind_param("s", $token);
            $mark->execute();
            $mark->close();

            $success     = true;
            $message     = 'Your password has been reset! You can now sign in with your new password.';
            $messageType = 'success';
        } else {
            $message     = 'Something went wrong. Please try again.';
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
  <title>Reset Password - Coffee by Monday Mornings</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #F4F1E8;
      min-height: 100vh;
      display: flex; justify-content: center; align-items: center;
      padding: 20px; position: relative;
      overflow-y: scroll; overflow-x: hidden;
    }

    body::before {
      content: '';
      position: absolute; width: 400px; height: 400px;
      background: radial-gradient(circle, rgba(212,136,59,0.15) 0%, transparent 70%);
      border-radius: 50%; top: -150px; left: -150px;
      animation: pulse 4s ease-in-out infinite;
    }

    body::after {
      content: '';
      position: absolute; width: 350px; height: 350px;
      background: radial-gradient(circle, rgba(255,163,26,0.1) 0%, transparent 70%);
      border-radius: 50%; bottom: -100px; right: -100px;
      animation: pulse 5s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 0.5; }
      50%       { transform: scale(1.1); opacity: 0.8; }
    }

    .login-container {
      background: white; padding: 30px 30px; border-radius: 20px;
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
      background: transparent; border: 2px solid #e0e0e0;
      color: #432109; font-size: 13px; cursor: pointer;
      margin-bottom: 15px; display: inline-flex; align-items: center; gap: 6px;
      padding: 7px 14px; border-radius: 20px;
      font-weight: 600; transition: all 0.3s ease;
    }

    .back-btn:hover {
      background: #f4f1e8; border-color: #d4883b;
      color: #d4883b; transform: translateX(-5px);
    }

    .logo-small { text-align: center; margin-top: -30px; margin-bottom: -30px; }

    .logo-small img { width: 140px; height: auto; animation: float 3s ease-in-out infinite; }

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

    .toggle-pw {
      position: absolute; right: 14px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none; color: #aaa;
      cursor: pointer; font-size: 13px; padding: 0;
    }

    .toggle-pw:hover { color: #654321; }

    input {
      width: 100%; padding: 12px 42px 12px 42px;
      border: 2px solid #e0e0e0; border-radius: 12px;
      font-size: 14px; transition: all 0.3s ease;
      background: #f9f9f9; font-family: inherit;
    }

    input:focus {
      outline: none; border-color: #654321; background: white;
      box-shadow: 0 0 0 4px rgba(212,136,59,0.1);
    }

    .strength-bar { height: 5px; border-radius: 4px; background: #eee; margin-top: 8px; overflow: hidden; }
    .strength-fill { height: 100%; border-radius: 4px; width: 0; transition: width .3s, background .3s; }
    .strength-label { font-size: 11px; margin-top: 4px; color: #aaa; min-height: 14px; }
    .match-label    { font-size: 11px; margin-top: 4px; min-height: 14px; }

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

    <h2><?= $success ? 'Password Updated!' : 'Set New Password'; ?></h2>
    <p class="subtitle">
      <?php if ($success): ?>
        You're all set — sign in with your new password.
      <?php elseif ($tokenValid): ?>
        Choose a strong new password for your account.
      <?php else: ?>
        This link is no longer valid.
      <?php endif; ?>
    </p>

    <?php if (!empty($message)): ?>
      <div class="<?= $messageType === 'success' ? 'success' : 'error'; ?>">
        <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <span><?= htmlspecialchars($message); ?></span>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>

      <button class="login-btn" onclick="location.href='customer_login.php'">
        <i class="fas fa-sign-in-alt" style="margin-right:6px;"></i>Sign In Now
      </button>

    <?php elseif ($tokenValid): ?>

      <form action="" method="POST" id="resetForm">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">

        <div class="form-group">
          <label>New Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="new_password" id="new_password"
                   required autocomplete="new-password" placeholder="Enter new password">
            <button type="button" class="toggle-pw" onclick="togglePw('new_password', this)">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
          <div class="strength-label" id="strengthLabel"></div>
        </div>

        <div class="form-group">
          <label>Confirm New Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="confirm_password" id="confirm_password"
                   required autocomplete="new-password" placeholder="Re-enter new password">
            <button type="button" class="toggle-pw" onclick="togglePw('confirm_password', this)">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <div class="match-label" id="matchLabel"></div>
        </div>

        <button type="submit" class="login-btn" id="submitBtn">
          <i class="fas fa-save" style="margin-right:6px;"></i>Save New Password
        </button>
      </form>

    <?php else: ?>

      <button class="login-btn" onclick="location.href='forgot_password.php'">
        <i class="fas fa-redo" style="margin-right:6px;"></i>Request New Link
      </button>

    <?php endif; ?>

    <div class="divider"><span>or</span></div>

    <div class="signup-link">
      Don't have an account? <a href="customer_signup.php">Create one now</a>
    </div>

  </div>

  <script>
    function togglePw(id, btn) {
      const el  = document.getElementById(id);
      const ico = btn.querySelector('i');
      if (el.type === 'password') {
        el.type = 'text';
        ico.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        el.type = 'password';
        ico.classList.replace('fa-eye-slash', 'fa-eye');
      }
    }

    const pwEl   = document.getElementById('new_password');
    const cfEl   = document.getElementById('confirm_password');
    const fill   = document.getElementById('strengthFill');
    const sLabel = document.getElementById('strengthLabel');
    const mLabel = document.getElementById('matchLabel');

    function calcStrength(pw) {
      let s = 0;
      if (pw.length >= 8)          s++;
      if (pw.length >= 12)         s++;
      if (/[A-Z]/.test(pw))        s++;
      if (/[0-9]/.test(pw))        s++;
      if (/[^A-Za-z0-9]/.test(pw)) s++;
      return s;
    }

    const levels = [
      { pct: '0%',   color: '#eee',    text: '' },
      { pct: '25%',  color: '#e74c3c', text: 'Weak' },
      { pct: '50%',  color: '#e07b39', text: 'Fair' },
      { pct: '75%',  color: '#d4883b', text: 'Good' },
      { pct: '90%',  color: '#27ae60', text: 'Strong' },
      { pct: '100%', color: '#1e8449', text: 'Very Strong' },
    ];

    pwEl?.addEventListener('input', function() {
      const l = levels[calcStrength(this.value)] || levels[0];
      fill.style.width   = l.pct;
      fill.style.background = l.color;
      sLabel.textContent = l.text;
      sLabel.style.color = l.color;
      checkMatch();
    });

    cfEl?.addEventListener('input', checkMatch);

    function checkMatch() {
      if (!cfEl.value) { mLabel.textContent = ''; return; }
      if (pwEl.value === cfEl.value) {
        mLabel.textContent = '✓ Passwords match';
        mLabel.style.color = '#27ae60';
      } else {
        mLabel.textContent = '✗ Passwords do not match';
        mLabel.style.color = '#e74c3c';
      }
    }

    document.getElementById('resetForm')?.addEventListener('submit', function(e) {
      if (pwEl.value.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters.');
        return;
      }
      if (pwEl.value !== cfEl.value) {
        e.preventDefault();
        alert('Passwords do not match.');
        return;
      }
      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i>Saving…';
    });
  </script>
</body>
</html>