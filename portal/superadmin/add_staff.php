<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';

  requireSuperAdmin();
  $conn = getDBConnection();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $_SESSION['error_message'] = 'Security token mismatch. Please try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = sanitizeInput($_POST['role'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? 'active');
        $use_default_password = isset($_POST['use_default_password']);

        $errors = [];

        // Validation
        if (empty($username)) {
            $errors[] = 'Username is required.';
        } elseif (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters long.';
        }

        if ($use_default_password) {
            $password = DEFAULT_RESET_PASSWORD;
        } elseif (empty($password)) {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        }

        if (empty($role)) {
            $errors[] = 'Role is required.';
        } elseif (!in_array($role, ['admin', 'staff'])) {
            $errors[] = 'Invalid role selected.';
        }

        if (!in_array($status, ['active', 'inactive'])) {
            $errors[] = 'Invalid status selected.';
        }

        // Check for duplicate username
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $errors[] = 'Username already exists. Please choose a different username.';
        }
        $stmt_check->close();

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $hashed_password = hashPassword($password);
                $password_reset = $use_default_password ? 1 : 0;

                $stmt_insert = $conn->prepare("
                    INSERT INTO users
                    (username, password, role, status, password_reset, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt_insert->bind_param("ssssi", $username, $hashed_password, $role, $status, $password_reset);

                if ($stmt_insert->execute()) {
                    $new_user_id = $conn->insert_id;
                    $stmt_insert->close();

                    // Log the activity
                    $status_text = $status === 'active' ? 'active' : 'inactive';
                    $password_text = $use_default_password ? ' with default password' : ' with custom password';
                    logActivity($_SESSION['user_id'], 'Add User',
                        "Added new $role account: $username (ID: $new_user_id, Status: $status_text)$password_text");

                    $conn->commit();
                    $_SESSION['success_message'] = "User '$username' has been successfully created." .
                        ($use_default_password ? " Default password: " . DEFAULT_RESET_PASSWORD : "");
                    redirect('superadmin/users.php?success=user_added');
                } else {
                    throw new Exception($stmt_insert->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Add user error: " . $e->getMessage());
                $_SESSION['error_message'] = 'Failed to add user. Please try again.';
            }
        } else {
            $_SESSION['error_message'] = implode('<br>', $errors);
        }
    }
  }

  $page_title = 'Add New User';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #4a301f;">
            <i class="fas fa-user-plus me-2"></i>Add New User Account
          </h3>
          <p class="text-muted mb-0">Create a new admin or staff account</p>
        </div>
        <div>
          <a href="users.php" class="btn btn-outline-brown">
            <i class="fas fa-arrow-left me-2"></i>Back to Users
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Main Form -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-white py-3">
          <h5 class="mb-0">
            <i class="fas fa-user me-2 icon-brown"></i>User Information
          </h5>
        </div>
        <div class="card-body">
          <form method="POST" id="addUserForm" novalidate>
            <?php echo getCsrfTokenField(); ?>

            <!-- Username -->
            <div class="mb-4">
              <label for="username" class="form-label">
                <i class="fas fa-user me-1"></i>Username <span class="text-brown">*</span>
              </label>
              <input type="text"
                     class="form-control"
                     id="username"
                     name="username"
                     value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                     placeholder="Enter unique username"
                     required>
              <div class="invalid-feedback">Username must be at least 3 characters long.</div>
              <small class="text-muted">Must be unique and at least 3 characters</small>
            </div>

            <!-- Password Options -->
            <div class="mb-4">
              <label class="form-label">
                <i class="fas fa-lock me-1"></i>Password <span class="text-brown">*</span>
              </label>

              <div class="form-check mb-3 p-3 border rounded bg-light">
                <input class="form-check-input"
                       type="checkbox"
                       id="use_default_password"
                       name="use_default_password"
                       <?php echo isset($_POST['use_default_password']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="use_default_password">
                  <strong>Use default password:</strong> <code class="text-brown bg-white"><?php echo DEFAULT_RESET_PASSWORD; ?></code>
                  <br>
                  <small class="text-muted">User will need to change password on first login</small>
                </label>
              </div>

              <div id="customPasswordField" <?php echo isset($_POST['use_default_password']) ? 'style="display:none;"' : ''; ?>>
                <div class="input-group">
                  <input type="password"
                         class="form-control"
                         id="password"
                         name="password"
                         placeholder="Enter custom password"
                         minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                  <button class="btn btn-outline-brown" type="button" id="togglePassword">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
                <div class="invalid-feedback">Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long.</div>
                <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters required</small>
              </div>
            </div>

            <!-- Role -->
            <div class="mb-4">
              <label for="role" class="form-label">
                <i class="fas fa-user-tag me-1"></i>Role <span class="text-brown">*</span>
              </label>
              <select class="form-select" id="role" name="role" required>
                <option value="">Select role...</option>
                <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>
                  <i class="fas fa-user-shield"></i> Admin - Full system access
                </option>
                <option value="staff" <?php echo ($_POST['role'] ?? '') === 'staff' ? 'selected' : ''; ?>>
                  <i class="fas fa-user"></i> Staff - Limited access (POS & viewing)
                </option>
              </select>
              <div class="invalid-feedback">Please select a role.</div>
              <small class="text-muted">Defines the user's access level and permissions</small>
            </div>

            <!-- Status -->
            <div class="mb-4">
              <label for="status" class="form-label">
                <i class="fas fa-toggle-on me-1"></i>Account Status <span class="text-brown">*</span>
              </label>
              <select class="form-select" id="status" name="status" required>
                <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>
                  Active - Can login immediately
                </option>
                <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>
                  Inactive - Cannot login
                </option>
              </select>
              <div class="invalid-feedback">Please select account status.</div>
              <small class="text-muted">You can change this later from the user management page</small>
            </div>

            <div class="alert alert-info-brown border-0">
              <i class="fas fa-info-circle me-2"></i>
              <small>
                <strong>Note:</strong> All new user accounts and password assignments are logged for security purposes.
                You can reset passwords later from the user management page.
              </small>
            </div>

            <hr class="my-4">

            <div class="row">
              <div class="col-md-6">
                <button type="submit" class="btn btn-brown btn-lg w-100">
                  <i class="fas fa-user-plus me-2"></i>Create User Account
                </button>
              </div>
              <div class="col-md-6">
                <a href="users.php" class="btn btn-outline-secondary btn-lg w-100">
                  <i class="fas fa-times me-2"></i>Cancel
                </a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
      <!-- Role Permissions Card -->
      <div class="card mb-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-shield-alt me-2 icon-brown"></i>Role Permissions
          </h6>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <div class="d-flex align-items-center mb-2">
              <div class="avatar-circle-sm me-2 bg-brown">
                <i class="fas fa-user-shield"></i>
              </div>
              <strong class="text-brown">Admin</strong>
            </div>
            <ul class="small mb-0 ps-4">
              <li class="mb-1">Full inventory management</li>
              <li class="mb-1">Menu item management</li>
              <li class="mb-1">View all staff accounts</li>
              <li class="mb-1">Point of Sale access</li>
              <li class="mb-1">Sales reports and analytics</li>
              <li>Activity logs viewing</li>
            </ul>
          </div>

          <hr>

          <div>
            <div class="d-flex align-items-center mb-2">
              <div class="avatar-circle-sm me-2 bg-secondary">
                <i class="fas fa-user"></i>
              </div>
              <strong>Staff</strong>
            </div>
            <ul class="small mb-0 ps-4">
              <li class="mb-1">Point of Sale access</li>
              <li class="mb-1">View inventory items</li>
              <li class="mb-1">View menu items</li>
              <li class="mb-1">View own sales history</li>
              <li>Limited reporting</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Quick Stats Card -->
      <div class="card mb-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-chart-bar me-2 text-brown"></i>Current Users
          </h6>
        </div>
        <div class="card-body">
          <?php
          $stats_users = getAllUsers();
          $total_users = count($stats_users);
          $active_users = count(array_filter($stats_users, fn($u) => $u['status'] === 'active'));
          $admin_count = count(array_filter($stats_users, fn($u) => in_array($u['role'], ['superadmin', 'admin'])));
          $staff_count = count(array_filter($stats_users, fn($u) => $u['role'] === 'staff'));
          ?>
          <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
            <span class="text-muted small">Total Users:</span>
            <strong class="text-brown fs-5"><?php echo $total_users; ?></strong>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
            <span class="text-muted small">Active Users:</span>
            <strong class="text-brown fs-5"><?php echo $active_users; ?></strong>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
            <span class="text-muted small">Admins:</span>
            <strong class="text-brown fs-5"><?php echo $admin_count; ?></strong>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted small">Staff:</span>
            <strong class="text-secondary fs-5"><?php echo $staff_count; ?></strong>
          </div>
        </div>
      </div>

      <!-- Security Tips Card -->
      <div class="card">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-lightbulb me-2 icon-brown"></i>Security Tips
          </h6>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0 small">
            <li class="mb-2">
              <i class="fas fa-check-circle text-brown me-2"></i>
              Use strong, unique usernames
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle text-brown me-2"></i>
              Default password is secure and temporary
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle text-brown me-2"></i>
              Deactivate unused accounts
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle text-brown me-2"></i>
              All actions are logged
            </li>
            <li class="mb-0">
              <i class="fas fa-check-circle text-brown me-2"></i>
              You can reset passwords anytime
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
body {
  background: linear-gradient(135deg, #f5f0eb 0%, #e8ddd4 100%);
  min-height: 100vh;
}

.icon-brown {
  color: #4a301f;
}

.text-brown {
  color: #4a301f !important;
}

.bg-brown {
  background-color: #382417 !important;
  color: white !important;
}

.btn-brown {
  background-color: #382417;
  border-color: #382417;
  color: white;
}

.btn-brown:hover {
  background-color: #4d3420;
  border-color: #4d3420;
  color: white;
}

.btn-outline-brown {
  color: #382417;
  border-color: #382417;
  background-color: transparent;
}

.btn-outline-brown:hover {
  background-color: #382417;
  border-color: #382417;
  color: white;
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.form-label {
  font-weight: 500;
  color: #495057;
}

.form-control:focus,
.form-select:focus {
  border-color: #654529;
  box-shadow: 0 0 0 0.2rem rgba(101, 69, 41, 0.25);
}

.form-check-input:checked {
  background-color: #382417;
  border-color: #382417;
}

.form-check-input:focus {
  border-color: #654529;
  box-shadow: 0 0 0 0.2rem rgba(101, 69, 41, 0.25);
}

.avatar-circle-sm {
  width: 35px;
  height: 35px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 0.875rem;
}

code {
  background-color: #f8f9fa;
  padding: 0.2rem 0.4rem;
  border-radius: 0.25rem;
  font-size: 0.9em;
  border: 1px solid #e0d4c3;
}

.alert-info-brown {
  background-color: #fff3e0;
  border: 1px solid #ffcc80;
  color: #4a301f;
  border-radius: 0.375rem;
}

.alert-info-brown strong {
  color: #382417;
}
</style>

<script>
$(document).ready(function() {
  const form = $('#addUserForm');
  const usernameInput = $('#username');
  const passwordInput = $('#password');
  const roleSelect = $('#role');
  const statusSelect = $('#status');
  const useDefaultCheckbox = $('#use_default_password');
  const customPasswordField = $('#customPasswordField');
  const togglePasswordBtn = $('#togglePassword');

  // Toggle password visibility
  togglePasswordBtn.on('click', function() {
    const type = passwordInput.attr('type') === 'password' ? 'text' : 'password';
    passwordInput.attr('type', type);
    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
  });

  // Toggle custom password field
  useDefaultCheckbox.on('change', function() {
    if ($(this).is(':checked')) {
      customPasswordField.slideUp();
      passwordInput.prop('required', false).removeClass('is-invalid is-valid');
    } else {
      customPasswordField.slideDown();
      passwordInput.prop('required', true);
    }
  });

  // Real-time validation
  usernameInput.on('blur', function() {
    const val = $(this).val().trim();
    if (val.length < 3) {
      $(this).addClass('is-invalid').removeClass('is-valid');
    } else {
      $(this).removeClass('is-invalid').addClass('is-valid');
    }
  });

  passwordInput.on('input', function() {
    if (!useDefaultCheckbox.is(':checked')) {
      const val = $(this).val();
      if (val.length > 0 && val.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
        $(this).addClass('is-invalid').removeClass('is-valid');
      } else if (val.length >= <?php echo PASSWORD_MIN_LENGTH; ?>) {
        $(this).removeClass('is-invalid').addClass('is-valid');
      } else {
        $(this).removeClass('is-invalid is-valid');
      }
    }
  });

  roleSelect.on('change', function() {
    if (!$(this).val()) {
      $(this).addClass('is-invalid').removeClass('is-valid');
    } else {
      $(this).removeClass('is-invalid').addClass('is-valid');
    }
  });

  statusSelect.on('change', function() {
    if (!$(this).val()) {
      $(this).addClass('is-invalid').removeClass('is-valid');
    } else {
      $(this).removeClass('is-invalid').addClass('is-valid');
    }
  });

  // Form validation
  form.on('submit', function(e) {
    let valid = true;

    // Validate username
    if (usernameInput.val().trim().length < 3) {
      usernameInput.addClass('is-invalid');
      valid = false;
    }

    // Validate password (if not using default)
    if (!useDefaultCheckbox.is(':checked')) {
      const pwd = passwordInput.val();
      if (pwd.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
        passwordInput.addClass('is-invalid');
        valid = false;
      }
    }

    // Validate role
    if (!roleSelect.val()) {
      roleSelect.addClass('is-invalid');
      valid = false;
    }

    // Validate status
    if (!statusSelect.val()) {
      statusSelect.addClass('is-invalid');
      valid = false;
    }

    if (!valid) {
      e.preventDefault();
      // Scroll to first error
      const firstError = $('.is-invalid').first();
      if (firstError.length) {
        $('html, body').animate({
          scrollTop: firstError.offset().top - 100
        }, 300);
      }
      return false;
    }
  });

  // Auto-generate username suggestion (optional feature)
  let suggestionTimeout;
  usernameInput.on('input', function() {
    clearTimeout(suggestionTimeout);
    const val = $(this).val().trim();

    if (val.length >= 3) {
      $(this).removeClass('is-invalid');
    }
  });
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
