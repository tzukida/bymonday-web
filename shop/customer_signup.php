<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up - Coffee by Monday Mornings</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

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
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(212, 136, 59, 0.15) 0%, transparent 70%);
      border-radius: 50%;
      top: -150px;
      left: -150px;
      animation: pulse 4s ease-in-out infinite;
    }

    body::after {
      content: '';
      position: absolute;
      width: 350px;
      height: 350px;
      background: radial-gradient(circle, rgba(255, 163, 26, 0.1) 0%, transparent 70%);
      border-radius: 50%;
      bottom: -100px;
      right: -100px;
      animation: pulse 5s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 0.5; }
      50% { transform: scale(1.1); opacity: 0.8; }
    }

    .signup-container {
      background: white;
      padding: 30px 30px;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
      width: 100%;
      max-width: 450px;
      position: relative;
      z-index: 1;
      animation: slideUp 0.6s ease;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .back-btn {
      background: transparent;
      border: 2px solid #e0e0e0;
      color: #432109;
      font-size: 13px;
      cursor: pointer;
      margin-bottom: 15px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      border-radius: 20px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .back-btn:hover {
      background: #f4f1e8;
      border-color: #d4883b;
      color: #d4883b;
      transform: translateX(-5px);
    }

    .logo-small {
      text-align: center;
      margin-top: -30px;
      margin-bottom: -30px;
    }

    .logo-small img {
      width: 140px;
      height: auto;
      animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }

    h2 {
      text-align: center;
      color: #432109;
      margin-bottom: 6px;
      font-size: 26px;
      font-weight: bold;
    }

    .subtitle {
      text-align: center;
      color: #d4883b;
      margin-bottom: 22px;
      font-size: 13px;
      font-weight: 500;
    }

    .form-group {
      margin-bottom: 14px;
      position: relative;
    }

    label {
      display: block;
      color: #432109;
      margin-bottom: 7px;
      font-size: 13px;
      font-weight: 600;
    }

    .input-wrapper {
      position: relative;
    }

    .input-icon {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #654321;
      font-size: 14px;
    }

    input {
      width: 100%;
      padding: 12px 14px 12px 42px;
      border: 2px solid #e0e0e0;
      border-radius: 12px;
      font-size: 14px;
      transition: all 0.3s ease;
      background: #f9f9f9;
      font-family: inherit;
    }

    input:focus {
      outline: none;
      border-color: #654321;
      background: white;
      box-shadow: 0 0 0 4px rgba(212, 136, 59, 0.1);
    }

    input::placeholder {
      color: #aaa;
      font-size: 13px;
    }

    .signup-btn {
      width: 100%;
      padding: 13px;
      background: linear-gradient(135deg, #8B4513, #654321);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-size: 14px;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 6px;
      box-shadow: 0 8px 20px rgba(101, 67, 33, 0.3);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .signup-btn:hover {
      background: linear-gradient(135deg, #8B4513, #654321);
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(101, 67, 33, 0.4);
    }

    .signup-btn:active {
      transform: translateY(0);
    }

    .divider {
      display: flex;
      align-items: center;
      margin: 18px 0;
      color: #999;
      font-size: 12px;
    }

    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: #e0e0e0;
    }

    .divider span {
      padding: 0 16px;
      font-weight: 500;
    }

    .login-link {
      text-align: center;
      color: #666;
      font-size: 13px;
    }

    .login-link a {
      color: #d4883b;
      text-decoration: none;
      font-weight: bold;
      transition: all 0.3s ease;
    }

    .login-link a:hover {
      color: #432109;
      text-decoration: underline;
    }

    .error {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      border: 2px solid #fca5a5;
      color: #991b1b;
      padding: 10px 14px;
      border-radius: 12px;
      margin-bottom: 16px;
      font-size: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
      animation: shake 0.5s ease;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-10px); }
      75% { transform: translateX(10px); }
    }

    .error i {
      font-size: 16px;
    }

    .success {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      border: 2px solid #6ee7b7;
      color: #065f46;
      padding: 10px 14px;
      border-radius: 12px;
      margin-bottom: 16px;
      font-size: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
      animation: slideDown 0.5s ease;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .success i {
      font-size: 18px;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    @media (max-width: 580px) {
      .signup-container {
        padding: 30px 25px;
      }

      h2 {
        font-size: 24px;
      }

      .logo-small img {
        width: 140px;
      }

      .signup-btn {
        padding: 14px;
        font-size: 14px;
      }

      .form-row {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="signup-container">
    <button class="back-btn" onclick="location.href='index.php'">
      <i class="fas fa-arrow-left"></i> Back to Menu
    </button>
    <div class="logo-small">
      <img src="<?= BASE_URL ?>/assets/images/logo1.png" alt="Coffee by Monday Mornings">
    </div>
    <h2>Create Account</h2>
    <p class="subtitle">Join us for the perfect coffee experience</p>
    <?php
    if (isset($_SESSION['error'])) {
      echo '<div class="error"><i class="fas fa-exclamation-circle"></i><span>' . htmlspecialchars($_SESSION['error']) . '</span></div>';
      unset($_SESSION['error']);
    }
    if (isset($_SESSION['success'])) {
      echo '<div class="success"><i class="fas fa-check-circle"></i><span>' . htmlspecialchars($_SESSION['success']) . '</span></div>';
      unset($_SESSION['success']);
    }
    ?>
    <form action="customer_signup_process.php" method="POST">
      <div class="form-group">
        <label>Full Name</label>
        <div class="input-wrapper">
          <i class="fas fa-user input-icon"></i>
          <input type="text" name="full_name" required placeholder="Enter your full name">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Email Address</label>
          <div class="input-wrapper">
            <i class="fas fa-envelope input-icon"></i>
            <input type="email" name="email" required placeholder="your@email.com">
          </div>
        </div>

        <div class="form-group">
          <label>Phone Number</label>
          <div class="input-wrapper">
            <i class="fas fa-phone input-icon"></i>
            <input type="tel" name="phone" required placeholder="09XX XXX XXXX">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>Delivery Address</label>
        <div class="input-wrapper">
          <i class="fas fa-map-marker-alt input-icon"></i>
          <input type="text" name="address" required placeholder="Enter your delivery address">
        </div>
      </div>

      <div class="form-group">
        <label>Username</label>
        <div class="input-wrapper">
          <i class="fas fa-user-circle input-icon"></i>
          <input type="text" name="username" required placeholder="Choose a username">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="password" required placeholder="Create password">
          </div>
        </div>

        <div class="form-group">
          <label>Confirm Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="confirm_password" required placeholder="Confirm password">
          </div>
        </div>
      </div>

      <button type="submit" class="signup-btn">Create Account</button>
      <div class="divider">
        <span>or</span>
      </div>
      <div class="login-link">
        Already have an account? <a href="customer_login.php">Sign in here</a>
      </div>
    </form>
  </div>
</body>
</html>
