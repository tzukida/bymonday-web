<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';

requireAuth();

$conn = getDBConnection();
$user = getUserById($_SESSION['user_id']);
$page_title = "Profile Settings";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    $username = trim($_POST['username'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long.";
    } elseif (usernameExists($username, $_SESSION['user_id'])) {
        $errors[] = "Username already exists. Please choose another.";
    }

  $passwordChanged = false;

  if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
      if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
          $errors[] = "Session expired or invalid. Please log in again.";
      } else {
          $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
          $stmt->bind_param("i", $_SESSION['user_id']);
          $stmt->execute();
          $result = $stmt->get_result();

          if ($result->num_rows === 0) {
              $errors[] = "No account found for your session (ID: " . htmlspecialchars($_SESSION['user_id']) . ").";
          } else {
              $user_data = $result->fetch_assoc();

              if (!isset($user_data['password']) || empty($user_data['password'])) {
                  $errors[] = "Your account password is missing in the database. Contact admin.";
              } elseif (!password_verify($current_password, $user_data['password'])) {
                  $errors[] = "Current password is incorrect.";
              } elseif (empty($new_password)) {
                  $errors[] = "Please enter a new password.";
              } elseif (strlen($new_password) < 6) {
                  $errors[] = "New password must be at least 6 characters long.";
              } elseif ($new_password !== $confirm_password) {
                  $errors[] = "New password and confirm password do not match.";
              } elseif ($current_password === $new_password) {
                  $errors[] = "New password cannot be the same as current password.";
              } else {
                  $passwordChanged = true;
              }
          }

          $stmt->close();
      }
  }

    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("UPDATE users SET username = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $username, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();

            if ($passwordChanged) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $hashed, $_SESSION['user_id']);
                $stmt->execute();
                $stmt->close();

                logActivity($_SESSION['user_id'], 'Password Changed', 'User changed their password');
                $_SESSION['success_message'] = "Password and profile updated successfully!";
            } else {
                if ($username !== $user['username']) {
                    logActivity($_SESSION['user_id'], 'Profile Updated', "Username changed from '{$user['username']}' to '{$username}'");
                }
                $_SESSION['success_message'] = "Profile updated successfully!";
            }

            $conn->commit();
            header("Location: profile.php");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "An error occurred while updating your profile. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
}

include BASE_PATH . '/includes/header.php';
?>


<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h3 class="h3 mb-0" style="color: #3b2008;">
            <i class="fas fa-user-cog me-2"></i>Profile Settings
          </h3>
          <p class="text-muted mb-0">Manage your account information and security</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">
          <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-8 col-xl-6 mx-auto">
      <!-- Profile Information Card -->
      <div class="card mb-4">
        <div class="card-header bg-white">
          <h5 class="mb-0">
            <i class="fas fa-user me-2 icon-brown"></i>Profile Information
          </h5>
        </div>
        <div class="card-body">
          <form method="POST" id="profileForm">
            <!-- Account Details -->
            <div class="mb-4">
              <label class="form-label fw-bold">
                <i class="fas fa-user-tag me-1"></i>Username
                <span class="text-danger">*</span>
              </label>
              <input type="text"
                     name="username"
                     class="form-control"
                     value="<?php echo htmlspecialchars($user['username']); ?>"
                     required
                     minlength="3"
                     placeholder="Enter your username">
              <small class="form-text text-muted">
                This is how you'll be identified in the system
              </small>
            </div>

            <!-- Account Info Display -->
            <div class="row mb-4">
              <div class="col-md-6">
                <label class="form-label fw-bold">
                  <i class="fas fa-shield-alt me-1"></i>Role
                </label>
                <input type="text"
                       class="form-control"
                       value="<?php
                         if (isSuperAdmin()) echo 'Super Admin';
                         elseif (isAdmin()) echo 'Admin';
                         else echo 'Staff';
                       ?>"
                       disabled>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold">
                  <i class="fas fa-calendar-alt me-1"></i>Member Since
                </label>
                <input type="text"
                       class="form-control"
                       value="<?php echo date('M j, Y', strtotime($user['created_at'])); ?>"
                       disabled>
              </div>
            </div>

            <hr class="my-4">

            <!-- Password Change Section -->
            <div class="password-section">
              <h5 class="mb-3">
                <i class="fas fa-key me-2 text-warning"></i>Change Password
              </h5>
              <p class="text-muted small mb-3">
                Leave password fields empty if you don't want to change your password
              </p>

              <div class="mb-3">
                <label class="form-label fw-bold">
                  <i class="fas fa-lock me-1"></i>Current Password
                </label>
                <div class="input-group">
                  <input type="password"
                         name="current_password"
                         id="currentPassword"
                         class="form-control"
                         placeholder="Enter current password">
                  <button class="btn btn-outline-secondary"
                          type="button"
                          onclick="togglePassword('currentPassword', this)">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
                <small class="form-text text-muted">
                  Required to change password
                </small>
              </div>

              <div class="mb-3">
                <label class="form-label fw-bold">
                  <i class="fas fa-lock me-1"></i>New Password
                </label>
                <div class="input-group">
                  <input type="password"
                         name="new_password"
                         id="newPassword"
                         class="form-control"
                         minlength="6"
                         placeholder="Enter new password">
                  <button class="btn btn-outline-secondary"
                          type="button"
                          onclick="togglePassword('newPassword', this)">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
                <small class="form-text text-muted">
                  Must be at least 6 characters long
                </small>
              </div>

              <div class="mb-4">
                <label class="form-label fw-bold">
                  <i class="fas fa-lock me-1"></i>Confirm New Password
                </label>
                <div class="input-group">
                  <input type="password"
                         name="confirm_password"
                         id="confirmPassword"
                         class="form-control"
                         placeholder="Confirm new password">
                  <button class="btn btn-outline-secondary"
                          type="button"
                          onclick="togglePassword('confirmPassword', this)">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
            </div>

            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-danger btn-lg">
                <i class="fas fa-save me-2"></i>Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Security Notice -->
      <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Security Tip:</strong> Use a strong password with a mix of letters, numbers, and special characters. Never share your password with anyone.
      </div>
    </div>
  </div>
</div>

<style>
.icon-brown {
  color: #3b2008;
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  border-radius: 0.5rem;
}

.card-header {
  border-bottom: 2px solid #f0f0f0;
  padding: 1.25rem;
}

.form-label {
  margin-bottom: 0.5rem;
  color: #495057;
}

.form-control:focus {
  border-color: #3b2008;
  box-shadow: 0 0 0 0.2rem rgba(59, 32, 8, 0.25);
}

.btn-danger {
  background-color: #3b2008;
  border-color: #3b2008;
}

.btn-danger:hover {
  background-color: #2a1505;
  border-color: #2a1505;
}

.password-section {
  background-color: #f8f9fa;
  padding: 1.5rem;
  border-radius: 0.5rem;
  margin-bottom: 1.5rem;
}

.input-group .btn-outline-secondary {
  border-color: #ced4da;
}

.input-group .btn-outline-secondary:hover {
  background-color: #e9ecef;
  border-color: #ced4da;
  color: #495057;
}

.alert-info {
  background-color: #e7f3ff;
  border-color: #b3d9ff;
  color: #004085;
}

@media (max-width: 768px) {
  .password-section {
    padding: 1rem;
  }
}
</style>

<script>
function togglePassword(inputId, button) {
  const input = document.getElementById(inputId);
  const icon = button.querySelector('i');

  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}

document.getElementById('profileForm').addEventListener('submit', function(e) {
  const newPassword = document.getElementById('newPassword').value;
  const confirmPassword = document.getElementById('confirmPassword').value;
  const currentPassword = document.getElementById('currentPassword').value;

  if (newPassword || confirmPassword || currentPassword) {
    if (!currentPassword) {
      e.preventDefault();
      alert('Please enter your current password to change your password.');
      return false;
    }

    if (!newPassword) {
      e.preventDefault();
      alert('Please enter a new password.');
      return false;
    }

    if (newPassword !== confirmPassword) {
      e.preventDefault();
      alert('New passwords do not match!');
      return false;
    }

    if (newPassword.length < 6) {
      e.preventDefault();
      alert('New password must be at least 6 characters long.');
      return false;
    }
  }
});

document.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
      const bsAlert = new bootstrap.Alert(alert);
      bsAlert.close();
    });
  }, 5000);
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
