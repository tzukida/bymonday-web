<?php
  if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
  }
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  $page_title = $page_title ?? APP_NAME;
  $current_user = isLoggedIn() ? getUserById($_SESSION['user_id']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($page_title); ?></title>

  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="../assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">

  <style>
    /* ── Flash alert overrides ── */
    .alert-success {
      background-color: #fff3e0 !important;
      border-color: #c87533 !important;
      color: #3b2008 !important;
    }
    .alert-success .btn-close { filter: none; }
    .alert-success i { color: #3b2008; }

    .alert-danger {
      background-color: #fdf0e8 !important;
      border-color: #c87533 !important;
      color: #3b2008 !important;
    }
    .alert-danger .btn-close { filter: none; }
    .alert-danger i { color: #3b2008; }

    .alert-warning {
      background-color: #fff8ec !important;
      border-color: #c87533 !important;
      color: #3b2008 !important;
    }
    .alert-warning .btn-close { filter: none; }
    .alert-warning i { color: #3b2008; }
  </style>
</head>

<body class="<?php echo isLoggedIn() ? 'dashboard' : 'login-page'; ?>">
  <?php if (isLoggedIn()): ?>
  <div class="mobile-header">
    <button class="mobile-toggle" onclick="toggleMobileSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <h1 class="mobile-title"><?php echo APP_NAME; ?></h1>
  </div>
  <div class="sidebar-overlay" onclick="toggleMobileSidebar()"></div>

  <nav class="sidebar" id="sidebar">
    <div class="sidebar-header d-flex justify-content-between align-items-center">
      <a href="<?php echo getBaseURL(); ?>/dashboard.php" class="sidebar-brand flex-grow-1 text-center">
        <span class="nav-text " style="font-size: 1.5rem;">ByMonday</span>
      </a>
      <button class="toggle-btn d-none d-lg-block ms-2" onclick="toggleSidebar()" id="toggleBtn">
        <i class="fas fa-angle-left"></i>
      </button>
    </div>


    <div class="sidebar-nav">
      <?php if (isAdmin()): ?>
      <!-- ADMIN NAVIGATION -->
      <div class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/dashboard.php">
          <i class="fas fa-tachometer-alt"></i>
          <span class="nav-text">Dashboard</span>
        </a>
      </div>

      <div class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'pos.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/pos.php">
          <i class="fas fa-cash-register"></i>
          <span class="nav-text">Point of Sale</span>
        </a>
      </div>

      <div class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'inventory.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/inventory.php">
          <i class="fas fa-boxes"></i>
          <span class="nav-text">Inventory</span>
        </a>
      </div>

      <div class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'menu_management.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/menu_management.php">
          <i class="fas fa-utensils"></i>
          <span class="nav-text">Menu Items</span>
        </a>
      </div>

      <div class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'staff.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/staff.php">
          <i class="fas fa-users"></i>
          <span class="nav-text">Staff Management</span>
        </a>
      </div>

      <div class="nav-item">
        <button class="dropdown-btn" onclick="toggleSubMenu(this)">
          <i class="fas fa-chart-bar"></i>
          <span class="nav-text">Reports</span>
          <i class="fas fa-chevron-down dropdown-arrow"></i>
        </button>
        <div class="sub-menu">
          <a class="nav-link" href="<?php echo getBaseURL(); ?>/sales_report.php">
            <i class="fas fa-receipt"></i>
            <span class="nav-text">Sales Report</span>
          </a>
          <a class="nav-link" href="<?php echo getBaseURL(); ?>/transactions.php">
            <i class="fas fa-history"></i>
            <span class="nav-text">Stock History</span>
          </a>
        </div>
      </div>

      <div class="nav-item">
        <button class="dropdown-btn" onclick="toggleSubMenu(this)">
          <i class="fas fa-cog"></i>
          <span class="nav-text">System</span>
          <i class="fas fa-chevron-down dropdown-arrow"></i>
        </button>
        <div class="sub-menu">
          <a class="nav-link" href="<?php echo getBaseURL(); ?>/activity_log.php">
            <i class="fas fa-history"></i>
            <span class="nav-text">Activity Logs</span>
          </a>
          <a class="nav-link" href="<?php echo getBaseURL(); ?>/backup.php">
            <i class="fas fa-database"></i>
            <span class="nav-text">Database Backup</span>
          </a>
        </div>
      </div>

    <?php elseif (isSuperAdmin()): ?>
    <!-- SUPERADMIN NAVIGATION -->
    <div class="nav-item">
      <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/dashboard.php">
        <i class="fas fa-tachometer-alt"></i>
        <span class="nav-text">Dashboard</span>
      </a>
    </div>

    <div class="nav-item">
      <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'inventory.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/inventory.php">
        <i class="fas fa-boxes"></i>
        <span class="nav-text">Inventory</span>
      </a>
    </div>

    <div class="nav-item">
      <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'menu_management.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/menu_management.php">
        <i class="fas fa-utensils"></i>
        <span class="nav-text">Menu Items</span>
      </a>
    </div>

    <div class="nav-item">
      <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/users.php">
        <i class="fas fa-users-cog"></i>
        <span class="nav-text">Account Management</span>
      </a>
    </div>

    <div class="nav-item">
      <button class="dropdown-btn" onclick="toggleSubMenu(this)">
        <i class="fas fa-chart-bar"></i>
        <span class="nav-text">Reports</span>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
      </button>
      <div class="sub-menu">
        <a class="nav-link" href="<?php echo getBaseURL(); ?>/sales_report.php">
          <i class="fas fa-receipt"></i>
          <span class="nav-text">Sales Report</span>
        </a>
        <a class="nav-link" href="<?php echo getBaseURL(); ?>/transactions.php">
          <i class="fas fa-history"></i>
          <span class="nav-text">Stock History</span>
        </a>
      </div>
    </div>

    <div class="nav-item">
      <button class="dropdown-btn" onclick="toggleSubMenu(this)">
        <i class="fas fa-cog"></i>
        <span class="nav-text">System</span>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
      </button>
      <div class="sub-menu">
        <a class="nav-link" href="<?php echo getBaseURL(); ?>/activity_log.php">
          <i class="fas fa-history"></i>
          <span class="nav-text">Activity Logs</span>
        </a>
        <a class="nav-link" href="<?php echo getBaseURL(); ?>/password_reset_history.php">
          <i class="fas fa-key"></i>
          <span class="nav-text">Password Reset History</span>
        </a>
        <a class="nav-link" href="<?php echo getBaseURL(); ?>/backup.php">
          <i class="fas fa-database"></i>
          <span class="nav-text">Database Backup</span>
        </a>
      </div>
    </div>

      <?php else: ?>
      <!-- STAFF NAVIGATION -->
      <div class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/dashboard.php">
          <i class="fas fa-tachometer-alt"></i>
          <span class="nav-text">Dashboard</span>
        </a>
      </div>

      <div class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'staff_pos.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/staff_pos.php">
          <i class="fas fa-cash-register"></i>
          <span class="nav-text">Point of Sale</span>
        </a>
      </div>

      <div class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'staff_inventory.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/staff_inventory.php">
          <i class="fas fa-boxes"></i>
          <span class="nav-text">Inventory</span>
        </a>
      </div>

      <div class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'staff_menu_view.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/staff_menu_view.php">
          <i class="fas fa-utensils"></i>
          <span class="nav-text">Menu Items</span>
        </a>
      </div>

      <div class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'online_orders.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/online_orders.php">
          <i class="fas fa-bag-shopping"></i>
          <span class="nav-text">Online Orders</span>
        </a>
      </div>

      <div class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'my_deliveries.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/my_deliveries.php">
          <i class="fas fa-person-biking"></i>
          <span class="nav-text">My Deliveries</span>
        </a>
      </div>

      <div class="nav-item">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'my_transactions.php') ? 'active' : ''; ?>" href="<?php echo getBaseURL(); ?>/my_transactions.php">
          <i class="fas fa-history"></i>
          <span class="nav-text">My Activities</span>
        </a>
      </div>
      <?php endif; ?>
    </div>

    <div class="sidebar-user">
      <div class="user-info-wrapper">
        <a href="<?php echo getBaseURL(); ?>/profile.php" class="user-info <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>" title="View Profile">
          <div class="user-avatar">
            <i class="fas fa-user-circle"></i>
          </div>
          <div class="user-details">
            <div class="user-name">
              <?php echo htmlspecialchars($current_user['username']); ?>
            </div>
            <div class="user-role">
              <?php
                if (isSuperAdmin()) {
                  echo 'Super Admin';
                } elseif (isAdmin()) {
                  echo 'Admin';
                } else {
                  echo 'Staff';
                }
              ?>
            </div>
          </div>
          <i class="fas fa-cog user-settings-icon" title="Profile Settings"></i>
        </a>
      </div>
      <a href="<?php echo BASE_URL; ?>/logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?');">
        <i class="fas fa-sign-out-alt"></i>
        <span class="nav-text">Logout</span>
      </a>
    </div>
  </nav>

  <div class="main-content" id="mainContent">
    <?php else: ?>
    <div class="container">
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php
        switch ($_GET['error']) {
          case 'login_required':
              echo 'Please login to access this page.';
              break;
          case 'session_expired':
              echo 'Your session has expired. Please login again.';
              break;
          case 'invalid_credentials':
              echo 'Invalid username or password.';
              break;
          case 'access_denied':
              echo 'Access denied. Insufficient permissions.';
              break;
          default:
              echo 'An error occurred. Please try again.';
        }
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php
        switch ($_GET['success']) {
          case 'logout':
              echo 'You have been successfully logged out.';
              break;
          case 'item_added':
              echo 'Item has been successfully added to inventory.';
              break;
          case 'item_updated':
              echo 'Item has been successfully updated.';
              break;
          case 'item_deleted':
              echo 'Item has been successfully deleted.';
              break;
          case 'transaction_added':
              echo 'Transaction has been successfully recorded.';
              break;
          case 'sale_completed':
              echo 'Sale has been successfully processed.';
              break;
          case 'user_added':
              echo 'User account has been successfully created.';
              break;
          case 'user_updated':
              echo 'User account has been successfully updated.';
              break;
          case 'user_deleted':
              echo 'User account has been successfully deleted.';
              break;
          case 'profile_updated':
              echo 'Profile has been successfully updated.';
              break;
          case 'password_changed':
              echo 'Password has been successfully changed.';
              break;
          default:
              echo 'Operation completed successfully.';
        }
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

<style>
.sidebar-user {
  border-top: 1px solid rgba(240, 165, 74, 0.5);
  padding: 1rem;
  margin-top: auto;
}

.user-info-wrapper {
  margin-bottom: 0.5rem;
}

.user-info {
  display: flex;
  align-items: center;
  padding: 0.75rem;
  text-decoration: none;
  color: #f0a54a;
  border-radius: 0.5rem;
  transition: all 0.3s ease;
  position: relative;
}

.user-info:hover {
  background-color: rgba(255, 255, 255, 0.1);
  transform: translateX(5px);
}

.user-info.active {
  background-color: rgba(255, 255, 255, 0.1);
}

.user-avatar {
  font-size: 2rem;
  margin-right: 0.75rem;
  color: #f0a54a;
  flex-shrink: 0;
}

.user-details {
  flex: 1;
  min-width: 0;
}

.user-name {
  font-weight: 600;
  font-size: 0.95rem;
  whitespace: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 0.125rem;
}

.user-role {
  font-size: 0.75rem;
  opacity: 0.8;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-settings-icon {
  font-size: 1rem;
  opacity: 0.7;
  transition: all 0.3s ease;
  margin-left: 0.5rem;
}

.user-info:hover .user-settings-icon {
  opacity: 1;
  transform: rotate(90deg);
}

.logout-btn {
  display: flex;
  align-items: center;
  padding: 0.75rem;
  text-decoration: none;
  color: #f0a54a;
  border-radius: 0.5rem;
  transition: all 0.3s ease;
  background-color: #4a301f;
}

.logout-btn:hover {
  background-color: rgba(255, 255, 255, 0.1);
  color: #f0a54a;
  transform: translateX(5px);
}

.logout-btn i {
  margin-right: 0.75rem;
  font-size: 1.1rem;
}

/* Collapsed sidebar styles */
.sidebar.collapsed .user-info {
  justify-content: center;
  padding: 0.75rem 0.5rem;
}

.sidebar.collapsed .user-avatar {
  margin-right: 0;
  font-size: 1.75rem;
}

.sidebar.collapsed .user-details,
.sidebar.collapsed .user-settings-icon {
  display: none;
}

.sidebar.collapsed .logout-btn {
  justify-content: center;
  padding: 0.75rem 0.5rem;
}

.sidebar.collapsed .logout-btn i {
  margin-right: 0;
}

.sidebar.collapsed .logout-btn .nav-text {
  display: none;
}

/* Mobile responsive */
@media (max-width: 991px) {
  .sidebar-user {
    padding: 1rem 0.75rem;
  }

  .user-info {
    padding: 0.65rem;
  }

  .user-avatar {
    font-size: 1.75rem;
  }

  .user-name {
    font-size: 0.9rem;
  }

  .logout-btn {
    padding: 0.65rem;
  }
}
</style>

<script>
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleBtn = document.getElementById('toggleBtn');

    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('expanded');

    const icon = toggleBtn.querySelector('i');
    icon.classList.toggle('fa-angle-left');
    icon.classList.toggle('fa-angle-right');

    if (sidebar.classList.contains('collapsed')) {
        closeAllSubMenus();
    }
  }

  function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
  }

  function toggleSubMenu(button) {
    const subMenu = button.nextElementSibling;
    const isCurrentlyOpen = subMenu.classList.contains('show');

    closeAllSubMenus();

    if (!isCurrentlyOpen) {
        subMenu.classList.add('show');
        button.classList.add('rotate');
    }
  }

  function closeAllSubMenus() {
    document.querySelectorAll('.sub-menu').forEach(menu => {
        menu.classList.remove('show');
        menu.previousElementSibling.classList.remove('rotate');
    });
  }

  document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.mobile-toggle');

    if (window.innerWidth <= 991 &&
        !sidebar.contains(event.target) &&
        !toggle?.contains(event.target) &&
        sidebar.classList.contains('show')) {
        toggleMobileSidebar();
    }
  });

  window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (window.innerWidth > 991) {
        sidebar.classList.remove('show');
        overlay?.classList.remove('show');
    }
  });

  if (window.innerWidth < 1200 && window.innerWidth > 991) {
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        if (sidebar && !sidebar.classList.contains('collapsed')) {
            toggleSidebar();
        }
    });
  }

</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap5.min.js"></script>
