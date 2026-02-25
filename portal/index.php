<?php
  define('BASE_PATH', __DIR__);
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  $page_title = 'Login - ' . APP_NAME;

  if (isLoggedIn()) {

      if (isSuperAdmin()) {
          header("Location: " . BASE_URL . "/superadmin/dashboard.php");
          exit();
      }

      if (isAdmin()) {
          header("Location: " . BASE_URL . "/admin/dashboard.php");
          exit();
      }

      if (isStaff()) {
          header("Location: " . BASE_URL . "/staff/dashboard.php");
          exit();
      }

      session_destroy();
      header("Location: index.php");
      exit();
  }

  $error_message = $_SESSION['login_error'] ?? '';

  if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
      $error_message = "You have been logged out due to inactivity.";
  }

  unset($_SESSION['login_error']);

?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($page_title) ?></title>
  <link href="<?php echo getBaseURL(); ?>/assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body class="login-page">
  <div class="panel">
    <div class="logo-container">
      <img src="assets/img/logo-kape.png" alt="ByMonday Logo" onerror="this.style.display='none'" />
    </div>

    <h1>ByMonday</h1>
    <p style="text-align: center; color: #666; margin-bottom: 20px;">POS & Inventory Management System</p>
    <?php if ($error_message): ?>
      <div class="error-message">
        <?= htmlspecialchars($error_message) ?>
      </div>
    <?php endif; ?>

    <form id="loginForm" action="process_login.php" method="POST" novalidate>
      <?= getCsrfTokenField(); ?>
      <div class="input_box">
        <input type="text" name="username" id="username" placeholder="Username" required />
      </div>
      <div class="input_box">
        <input type="password" name="password" id="password" placeholder="Password" required />
        <span id="togglePassword">Show</span>
      </div>
      <button type="submit" id="loginBtn" class="button">Log In</button>
    </form>

    <div class="security-notice">
      <center>
        This system is for authorized personnel only. <br>
        All activities are logged and monitored.
      </center>
    </div>
  </div>

  <script>
    const togglePassword = document.querySelector("#togglePassword");
    const password = document.querySelector("#password");

    togglePassword.addEventListener("click", function () {
      const type = password.getAttribute("type") === "password" ? "text" : "password";
      password.setAttribute("type", type);
      this.textContent = type === "password" ? "Show" : "Hide";
    });

    document.getElementById('loginForm').addEventListener('submit', function(e) {
      const username = document.querySelector('input[name="username"]').value.trim();
      const passwordValue = document.querySelector('input[name="password"]').value;
      const loginBtn = document.getElementById('loginBtn');

      if (username === "") {
        e.preventDefault();
        alert('Username cannot be empty');
        return;
      }

      if (passwordValue === "") {
        e.preventDefault();
        alert('Password cannot be empty');
        return;
      }

      loginBtn.textContent = 'Logging in...';
      loginBtn.disabled = true;

      setTimeout(function() {
        loginBtn.textContent = 'Log In';
        loginBtn.disabled = false;
      }, 5000);
    });

    document.querySelector('input[name="username"]').focus();
    window.addEventListener('load', () => document.querySelector('input[name="password"]').value = '');
    window.addEventListener('pageshow', function(e) { if (e.persisted) window.location.reload(); });
  </script>

</body>
</html>
