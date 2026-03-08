<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireAdmin();

  $conn = getDBConnection();
  $staff_id = $_GET['id'] ?? null;

  if (!$staff_id || !is_numeric($staff_id)) {
    $_SESSION['error_message'] = 'Invalid staff ID.';
    redirect('/staff.php');
  }

  // Get staff details
  $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'staff'");
  $stmt->bind_param("i", $staff_id);
  $stmt->execute();
  $staff = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$staff) {
    $_SESSION['error_message'] = 'Staff member not found.';
    redirect('/staff.php');
  }

  // Get recent activity for this staff member
  $stmt = $conn->prepare("
    SELECT t.*, i.item_name, i.unit
    FROM transactions t
    JOIN inventory i ON t.item_id = i.id
    WHERE t.user_id = ?
    ORDER BY t.timestamp DESC
    LIMIT 5
  ");
  $stmt->bind_param("i", $staff_id);
  $stmt->execute();
  $recent_transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // Get sales count
  $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales WHERE user_id = ?");
  $stmt->bind_param("i", $staff_id);
  $stmt->execute();
  $sales_count = $stmt->get_result()->fetch_assoc()['count'];
  $stmt->close();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $_SESSION['error_message'] = 'Security token mismatch. Please try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $role = sanitizeInput($_POST['role'] ?? '');

        $errors = [];

        if (empty($username)) {
            $errors[] = 'Username is required.';
        } elseif (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters long.';
        }

        if (empty($role)) {
            $errors[] = 'Role is required.';
        } elseif (!in_array($role, ['staff', 'admin'])) {
            $errors[] = 'Invalid role selected.';
        }

        // Check for duplicate username
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt_check->bind_param("si", $username, $staff_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $errors[] = 'Username already exists. Please choose a different username.';
        }
        $stmt_check->close();

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $stmt_update = $conn->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $stmt_update->bind_param("ssi", $username, $role, $staff_id);

                if ($stmt_update->execute()) {
                    $stmt_update->close();
                    $conn->commit();

                    $action_details = "Updated staff: $username (ID: $staff_id)";
                    if ($role !== $staff['role']) {
                        $action_details .= " - Role changed from {$staff['role']} to $role";
                    }

                    logActivity($_SESSION['user_id'], 'Edit Staff', $action_details);
                    $_SESSION['success_message'] = "Staff member '$username' updated successfully.";
                    redirect('/staff.php?success=staff_updated');
                } else {
                    throw new Exception($stmt_update->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Edit staff error: " . $e->getMessage());
                $_SESSION['error_message'] = 'Failed to update staff member. Please try again.';
            }
        } else {
            $_SESSION['error_message'] = implode('<br>', $errors);
        }
    }
  }

  $form_data = [
    'username' => $_POST['username'] ?? $staff['username'],
    'role' => $_POST['role'] ?? $staff['role'],
  ];

  $page_title = 'Edit Staff';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #3b2008;">
            <i class="fas fa-user-edit me-2"></i>Edit Staff Member
          </h3>
          <p class="text-muted mb-0">Update staff account information</p>
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
            <i class="fas fa-user me-2 icon-brown"></i>Staff Information
          </h5>
        </div>
        <div class="card-body">
          <form method="POST" id="editStaffForm" novalidate>
            <?php echo getCsrfTokenField(); ?>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-4">
                  <label for="username" class="form-label fw-semibold">
                    <i class="fas fa-user me-1"></i>Username <span class="text-danger">*</span>
                  </label>
                  <input type="text"
                         class="form-control"
                         id="username"
                         name="username"
                         value="<?php echo htmlspecialchars($form_data['username']); ?>"
                         placeholder="Enter username"
                         required>
                  <div class="invalid-feedback">Username must be at least 3 characters long.</div>
                  <small class="text-muted">Must be unique and at least 3 characters</small>
                </div>
              </div>

              <div class="col-md-6">
                <div class="mb-4">
                  <label for="role" class="form-label fw-semibold">
                    <i class="fas fa-user-tag me-1"></i>Role <span class="text-danger">*</span>
                  </label>
                  <select class="form-select" id="role" name="role" required>
                    <option value="">Select role...</option>
                    <option value="staff" <?php echo $form_data['role'] === 'staff' ? 'selected' : ''; ?>>
                      Staff - Basic access
                    </option>
                    <option value="admin" <?php echo $form_data['role'] === 'admin' ? 'selected' : ''; ?>>
                      Admin - Full access
                    </option>
                  </select>
                  <div class="invalid-feedback">Please select a role.</div>
                  <small class="text-muted">Defines access level and permissions</small>
                </div>
              </div>
            </div>

            <div class="alert alert-warning border-0">
              <i class="fas fa-lock me-2"></i>
              <strong>Password Management:</strong> Only SuperAdmin can reset passwords.
              Contact SuperAdmin if password change is needed.
            </div>

            <div class="alert alert-warning border-0">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Note:</strong> Changing the role will affect the user's access permissions immediately.
            </div>

            <hr class="my-4">

            <div class="row">
              <div class="col-md-6">
                <button type="submit" class="btn btn-danger btn-lg w-100">
                  <i class="fas fa-save me-2"></i>Save Changes
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
      <!-- Current Details Card -->
      <div class="card mb-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-info-circle me-2 " style="color:#3b2008;"></i>Current Details
          </h6>
        </div>
        <div class="card-body">
          <div class="text-center mb-3">
            <div class="avatar-circle-lg mx-auto mb-2" style="background: linear-gradient(135deg, #751312 0%, #5a0f0e 100%);">
              <i class="fas fa-user text-white"></i>
            </div>
            <h5 class="mb-1"><?php echo htmlspecialchars($staff['username']); ?></h5>
            <?php
            $role_badges = [
              'staff' => '<span class="badge bg-secondary">Staff</span>',
              'admin' => '<span class="badge bg-brown">Admin</span>'
            ];
            echo $role_badges[$staff['role']] ?? '';
            ?>
          </div>

          <hr>

          <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small">Status:</span>
              <?php if ($staff['status'] === 'active'): ?>
                <span class="badge bg-brown">Active</span>
              <?php else: ?>
                <span class="badge bg-secondary">Inactive</span>
              <?php endif; ?>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small">User ID:</span>
              <strong>#<?php echo str_pad($staff['id'], 3, '0', STR_PAD_LEFT); ?></strong>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small">Created:</span>
              <span class="small"><?php echo formatDate($staff['created_at'], 'M j, Y'); ?></span>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small">Total Sales:</span>
              <strong class="text-brown"><?php echo number_format($sales_count); ?></strong>
            </div>

            <?php if (!empty($staff['last_password_reset'])): ?>
            <div class="d-flex justify-content-between align-items-center">
              <span class="text-muted small">Last Reset:</span>
              <span class="small"><?php echo formatDate($staff['last_password_reset'], 'M j, Y'); ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <?php if (!empty($recent_transactions)): ?>
      <div class="card">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-history me-2 text-secondary"></i>Recent Activity
          </h6>
        </div>
        <div class="card-body p-0">
          <div class="list-group list-group-flush">
            <?php foreach ($recent_transactions as $trans): ?>
              <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="flex-grow-1">
                    <?php if ($trans['type'] === 'stock-in'): ?>
                      <span class="badge bg-brown mb-1">Stock In</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark mb-1">Stock Out</span>
                    <?php endif; ?>
                    <div class="small fw-semibold"><?php echo htmlspecialchars($trans['item_name']); ?></div>
                    <div class="small text-muted">
                      <?php echo number_format($trans['quantity'], 2); ?> <?php echo htmlspecialchars($trans['unit']); ?>
                    </div>
                  </div>
                  <div class="text-end small text-muted" style="min-width: 70px;">
                    <?php echo formatDate($trans['timestamp'], 'M j'); ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Tips Card -->
      <div class="card mt-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-lightbulb me-2 " style="color:#3b2008;"></i>Tips
          </h6>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0 small">
            <li class="mb-2">
              <i class="fas fa-check-circle  me-2"></i>
              Contact SuperAdmin for password resets
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle  me-2"></i>
              Admin role has full system access
            </li>
            <li class="mb-0">
              <i class="fas fa-check-circle  me-2"></i>
              Changes take effect immediately
            </li>
          </ul>
        </div>
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
}

.form-label {
  font-weight: 500;
  color: #495057;
}

.btn-danger {
  background-color: #3b2008;
  border-color: #3b2008;
}

.btn-danger:hover {
  background-color: #2a1505;
  border-color: #2a1505;
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

.list-group-item {
  border-left: none;
  border-right: none;
}

.list-group-item:first-child {
  border-top: none;
}

.list-group-item:last-child {
  border-bottom: none;
}

.bg-brown { background-color: #3b2008; color: #fff; }
.text-brown { color: #3b2008; }
.btn-outline-brown { color: #3b2008; border-color: #3b2008; background-color: transparent; }
.btn-outline-brown:hover, .btn-outline-brown:active, .btn-outline-brown:focus {
  background-color: #3b2008; border-color: #3b2008; color: #fff;
}
.btn-primary { background-color: #3b2008; border-color: #3b2008; }
.btn-primary:hover { background-color: #2a1505; border-color: #2a1505; }


.form-select:focus,
.form-control:focus,
.form-check-input:focus {
  border-color: #3b2008 !important;
  box-shadow: 0 0 0 0.2rem rgba(59, 32, 8, 0.25) !important;
  outline: none !important;
}
.form-select { accent-color: #3b2008; }
option:checked, option:hover { background-color: #3b2008 !important; color: #fff !important; }

</style>

<script>
$(document).ready(function() {
  const form = $('#editStaffForm');
  const usernameInput = $('#username');
  const roleSelect = $('#role');

  // Real-time validation
  usernameInput.on('blur', function() {
    if ($(this).val().trim().length < 3) {
      $(this).addClass('is-invalid').removeClass('is-valid');
    } else {
      $(this).removeClass('is-invalid').addClass('is-valid');
    }
  });

  roleSelect.on('change', function() {
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

    // Validate role
    if (!roleSelect.val()) {
      roleSelect.addClass('is-invalid');
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

    // Confirmation if changing role
    const originalRole = '<?php echo $staff['role']; ?>';
    const newRole = roleSelect.val();

    if (originalRole !== newRole) {
      const roleNames = {
        'staff': 'Staff',
        'admin': 'Admin'
      };

      const confirmed = confirm(
        `You are changing the role from ${roleNames[originalRole]} to ${roleNames[newRole]}.\n\n` +
        `This will change the user's access permissions immediately.\n\n` +
        `Do you want to continue?`
      );

      if (!confirmed) {
        e.preventDefault();
        return false;
      }
    }
  });
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
