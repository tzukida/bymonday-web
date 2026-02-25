<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireAdmin();
  $conn = getDBConnection();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $_SESSION['error_message'] = 'Security token mismatch. Please try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
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
                $role = 'staff'; // Admin can only create staff accounts
                $status = 'active'; // New staff are active by default
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
                    $password_text = $use_default_password ? ' with default password' : ' with custom password';
                    logActivity($_SESSION['user_id'], 'Add Staff',
                        "Added new staff account: $username (ID: $new_user_id)$password_text");

                    $conn->commit();
                    $_SESSION['success_message'] = "Staff member '$username' has been successfully added." .
                        ($use_default_password ? " Default password: " . DEFAULT_RESET_PASSWORD : "");
                    redirect('admin/staff.php?success=staff_added');
                } else {
                    throw new Exception($stmt_insert->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Add staff error: " . $e->getMessage());
                $_SESSION['error_message'] = 'Failed to add staff member. Please try again.';
            }
        } else {
            $_SESSION['error_message'] = implode('<br>', $errors);
        }
    }
  }

  // Get current staff count
  $all_users = getAllUsers();
  $staff_count = count(array_filter($all_users, fn($u) => $u['role'] === 'staff'));
  $active_staff = count(array_filter($all_users, fn($u) => $u['role'] === 'staff' && $u['status'] === 'active'));

  $page_title = 'Add Staff';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #751312;">
            <i class="fas fa-user-plus me-2"></i>Add New Staff Member
          </h3>
          <p class="text-muted mb-0">Create a new staff account for your team</p>
        </div>
        <div>
          <a href="staff.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Staff List
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
            <i class="fas fa-user me-2 icon-red"></i>Staff Information
          </h5>
        </div>
        <div class="card-body">
          <form method="POST" id="addStaffForm" novalidate>
            <?php echo getCsrfTokenField(); ?>

            <!-- Username -->
            <div class="mb-4">
              <label for="username" class="form-label fw-semibold">
                <i class="fas fa-user me-1"></i>Username <span class="text-danger">*</span>
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
              <label class="form-label fw-semibold">
                <i class="fas fa-lock me-1"></i>Password <span class="text-danger">*</span>
              </label>

              <div class="form-check mb-3">
                <input class="form-check-input"
                       type="checkbox"
                       id="use_default_password"
                       name="use_default_password"
                       <?php echo isset($_POST['use_default_password']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="use_default_password">
                  <strong>Use default password:</strong> <code class="text-dark"><?php echo DEFAULT_RESET_PASSWORD; ?></code>
                  <br>
                  <small class="text-muted">Recommended for quick staff setup</small>
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
                  <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
                <div class="invalid-feedback">Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long.</div>
                <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters required</small>
              </div>
            </div>

            <!-- Role Display (Fixed as Staff) -->
            <div class="mb-4">
              <label class="form-label fw-semibold">
                <i class="fas fa-user-tag me-1"></i>Role
              </label>
              <div class="form-control bg-light" style="cursor: not-allowed;">
                <span class="badge bg-secondary me-2">
                  <i class="fas fa-user me-1"></i>Staff
                </span>
                Basic access (POS, inventory viewing)
              </div>
              <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Only SuperAdmin can create Admin accounts. Contact SuperAdmin if needed.
              </small>
            </div>

            <div class="alert alert-info border-0">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Note:</strong> New staff accounts will be created as <strong>Active</strong> and can login immediately.
              Password resets can be requested from SuperAdmin.
            </div>

            <hr class="my-4">

            <div class="row">
              <div class="col-md-6">
                <button type="submit" class="btn btn-danger btn-lg w-100">
                  <i class="fas fa-user-plus me-2"></i>Add Staff Member
                </button>
              </div>
              <div class="col-md-6">
                <a href="staff.php" class="btn btn-outline-secondary btn-lg w-100">
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
      <!-- Staff Stats Card -->
      <div class="card mb-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-chart-bar me-2 text-info"></i>Current Staff
          </h6>
        </div>
        <div class="card-body">
          <div class="text-center mb-3">
            <div class="avatar-circle-lg mx-auto mb-2" style="background: linear-gradient(135deg, #751312 0%, #5a0f0e 100%);">
              <i class="fas fa-users text-white"></i>
            </div>
            <h2 class="mb-0" style="color: #751312;"><?php echo $staff_count; ?></h2>
            <small class="text-muted">Total Staff Members</small>
          </div>

          <hr>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small">Active Staff:</span>
            <strong class="text-success"><?php echo $active_staff; ?></strong>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted small">Inactive Staff:</span>
            <strong class="text-warning"><?php echo $staff_count - $active_staff; ?></strong>
          </div>
        </div>
      </div>

      <!-- Staff Permissions Card -->
      <div class="card mb-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-shield-alt me-2 text-secondary"></i>Staff Access Level
          </h6>
        </div>
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="avatar-circle-sm me-2 bg-secondary">
              <i class="fas fa-user"></i>
            </div>
            <strong>Staff Permissions</strong>
          </div>

          <ul class="list-unstyled mb-0 small">
            <li class="mb-2">
              <i class="fas fa-check-circle text-success me-2"></i>
              Point of Sale access
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle text-success me-2"></i>
              View inventory items
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle text-success me-2"></i>
              View menu items
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle text-success me-2"></i>
              View own sales history
            </li>
            <li class="mb-2">
              <i class="fas fa-times-circle text-danger me-2"></i>
              <del>Cannot manage inventory</del>
            </li>
            <li class="mb-0">
              <i class="fas fa-times-circle text-danger me-2"></i>
              <del>Cannot manage other users</del>
            </li>
          </ul>
        </div>
      </div>

      <!-- Account Management Info -->
      <div class="card">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-lightbulb me-2 text-warning"></i>Account Management
          </h6>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0 small">
            <li class="mb-2">
              <i class="fas fa-info-circle text-info me-2"></i>
              You can edit staff usernames and roles
            </li>
            <li class="mb-2">
              <i class="fas fa-key text-warning me-2"></i>
              Password resets require SuperAdmin
            </li>
            <li class="mb-2">
              <i class="fas fa-user-shield text-primary me-2"></i>
              Creating Admin accounts requires SuperAdmin
            </li>
            <li class="mb-0">
              <i class="fas fa-history text-secondary me-2"></i>
              All actions are logged for security
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.icon-red {
  color: #751312;
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.form-label {
  font-weight: 500;
  color: #495057;
}

.btn-danger {
  background-color: #751312;
  border-color: #751312;
}

.btn-danger:hover {
  background-color: #5a0f0e;
  border-color: #5a0f0e;
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

.avatar-circle-lg {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
}

.form-check-input:checked {
  background-color: #751312;
  border-color: #751312;
}

code {
  background-color: #f8f9fa;
  padding: 0.2rem 0.4rem;
  border-radius: 0.25rem;
  font-size: 0.9em;
}

del {
  opacity: 0.6;
}
</style>

<script>
$(document).ready(function() {
  const form = $('#addStaffForm');
  const usernameInput = $('#username');
  const passwordInput = $('#password');
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

  // Username input improvements
  usernameInput.on('input', function() {
    // Remove spaces and special characters as user types
    let val = $(this).val();
    val = val.replace(/[^a-zA-Z0-9_.-]/g, '');
    $(this).val(val);
  });
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
