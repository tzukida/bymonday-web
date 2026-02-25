<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';

  requireSuperAdmin();
  $conn = getDBConnection();

  $user_id = $_GET['id'] ?? null;
  if (!$user_id || !is_numeric($user_id)) {
    $_SESSION['error_message'] = 'Invalid user ID.';
    redirect('superadmin/users.php');
  }

  // Check if user is superadmin - prevent editing superadmin accounts
  $check_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
  $check_stmt->bind_param("i", $user_id);
  $check_stmt->execute();
  $check_result = $check_stmt->get_result();
  $check_user = $check_result->fetch_assoc();
  $check_stmt->close();

  if ($check_user && $check_user['role'] === 'superadmin') {
    $_SESSION['error_message'] = 'SuperAdmin accounts cannot be edited.';
    redirect('superadmin/users.php');
  }

  // Get user details
  $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'User not found.';
    redirect('superadmin/users.php');
  }

  $user = $result->fetch_assoc();
  $stmt->close();

  // Get recent activity for this user
  $stmt = $conn->prepare("
    SELECT t.*, i.item_name, i.unit
    FROM transactions t
    JOIN inventory i ON t.item_id = i.id
    WHERE t.user_id = ?
    ORDER BY t.timestamp DESC
    LIMIT 5
  ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $recent_transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // Get sales count
  $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales WHERE user_id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $sales_count = $stmt->get_result()->fetch_assoc()['count'];
  $stmt->close();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $_SESSION['error_message'] = 'Security token mismatch. Please try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $role = sanitizeInput($_POST['role'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? 'active');

        $errors = [];

        if (empty($username)) {
            $errors[] = 'Username is required.';
        } elseif (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters long.';
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
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt_check->bind_param("si", $username, $user_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $errors[] = 'Username already exists. Please choose a different username.';
        }
        $stmt_check->close();

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $stmt_update = $conn->prepare("UPDATE users SET username = ?, role = ?, status = ? WHERE id = ?");
                $stmt_update->bind_param("sssi", $username, $role, $status, $user_id);

                if ($stmt_update->execute()) {
                    $stmt_update->close();
                    $conn->commit();

                    $action_details = "Updated user: $username (ID: $user_id)";
                    if ($role !== $user['role']) {
                        $action_details .= " - Role changed from {$user['role']} to $role";
                    }
                    if ($status !== $user['status']) {
                        $action_details .= " - Status changed from {$user['status']} to $status";
                    }

                    logActivity($_SESSION['user_id'], 'Edit User', $action_details);
                    $_SESSION['success_message'] = "User '$username' updated successfully.";
                    redirect('/superadmin/users.php?success=user_updated');
                } else {
                    throw new Exception($stmt_update->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Edit user error: " . $e->getMessage());
                $_SESSION['error_message'] = 'Failed to update user. Please try again.';
            }
        } else {
            $_SESSION['error_message'] = implode('<br>', $errors);
        }
    }
  }

  $form_data = [
    'username' => $_POST['username'] ?? $user['username'],
    'role' => $_POST['role'] ?? $user['role'],
    'status' => $_POST['status'] ?? $user['status'],
  ];

  $page_title = 'Edit User';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #4a301f;">
            <i class="fas fa-user-edit me-2"></i>Edit User Account
          </h3>
          <p class="text-muted mb-0">Update user account information for <strong class="text-brown"><?php echo htmlspecialchars($user['username']); ?></strong></p>
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
          <form method="POST" id="editUserForm" novalidate>
            <?php echo getCsrfTokenField(); ?>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-4">
                  <label for="username" class="form-label">
                    <i class="fas fa-user me-1"></i>Username <span class="text-brown">*</span>
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
                  <label for="role" class="form-label">
                    <i class="fas fa-user-tag me-1"></i>Role <span class="text-brown">*</span>
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

            <div class="mb-4">
              <label for="status" class="form-label">
                <i class="fas fa-toggle-on me-1"></i>Account Status <span class="text-brown">*</span>
              </label>
              <select class="form-select" id="status" name="status" required>
                <option value="active" <?php echo $form_data['status'] === 'active' ? 'selected' : ''; ?>>
                  Active - Can login
                </option>
                <option value="inactive" <?php echo $form_data['status'] === 'inactive' ? 'selected' : ''; ?>>
                  Inactive - Cannot login
                </option>
              </select>
              <div class="invalid-feedback">Please select account status.</div>
              <small class="text-muted">Inactive accounts cannot log in to the system</small>
            </div>

            <div class="alert alert-warning-brown border-0">
              <i class="fas fa-key me-2"></i>
              <small>
                <strong>Password Reset:</strong> To reset this user's password, use the "Reset Password" button from the users list page.
              </small>
            </div>

            <div class="alert alert-info-brown border-0">
              <i class="fas fa-info-circle me-2"></i>
              <small>
                <strong>Note:</strong> Changes to role and status take effect immediately. The user may need to log in again.
              </small>
            </div>

            <hr class="my-4">

            <div class="row">
              <div class="col-md-6">
                <button type="submit" class="btn btn-brown btn-lg w-100">
                  <i class="fas fa-save me-2"></i>Save Changes
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
      <!-- Current Details Card -->
      <div class="card mb-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-info-circle me-2 icon-brown"></i>Current Details
          </h6>
        </div>
        <div class="card-body">
          <div class="text-center mb-3">
            <div class="avatar-circle-lg mx-auto mb-3 bg-brown">
              <i class="fas fa-user text-white"></i>
            </div>
            <h5 class="mb-2 text-brown"><?php echo htmlspecialchars($user['username']); ?></h5>
            <?php
            $role_badges = [
              'staff' => '<span class="badge bg-secondary">Staff</span>',
              'admin' => '<span class="badge bg-brown">Admin</span>'
            ];
            echo $role_badges[$user['role']] ?? '';
            ?>
          </div>

          <hr>

          <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
              <span class="text-muted small">Status:</span>
              <?php if ($user['status'] === 'active'): ?>
                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
              <?php else: ?>
                <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Inactive</span>
              <?php endif; ?>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
              <span class="text-muted small">User ID:</span>
              <strong class="text-brown">#<?php echo str_pad($user['id'], 3, '0', STR_PAD_LEFT); ?></strong>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
              <span class="text-muted small">Created:</span>
              <span class="small"><?php echo formatDate($user['created_at'], 'M j, Y'); ?></span>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
              <span class="text-muted small">Total Sales:</span>
              <strong class="text-success"><?php echo number_format($sales_count); ?></strong>
            </div>

            <?php if (!empty($user['last_password_reset'])): ?>
            <div class="d-flex justify-content-between align-items-center">
              <span class="text-muted small">Last Reset:</span>
              <span class="small"><?php echo formatDate($user['last_password_reset'], 'M j, Y'); ?></span>
            </div>
            <?php endif; ?>
          </div>

        </div>
      </div>

      <!-- Recent Activity -->
      <?php if (!empty($recent_transactions)): ?>
      <div class="card mb-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-history me-2 icon-brown"></i>Recent Activity
          </h6>
        </div>
        <div class="card-body p-0">
          <div class="list-group list-group-flush">
            <?php foreach ($recent_transactions as $trans): ?>
              <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="flex-grow-1">
                    <?php if ($trans['type'] === 'stock-in'): ?>
                      <span class="badge bg-success mb-1"><i class="fas fa-arrow-down me-1"></i>Stock In</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark mb-1"><i class="fas fa-arrow-up me-1"></i>Stock Out</span>
                    <?php endif; ?>
                    <div class="small fw-semibold text-brown"><?php echo htmlspecialchars($trans['item_name']); ?></div>
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

      <!-- Quick Actions Card -->
      <div class="card mb-3">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
          </h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="users.php" class="btn btn-outline-brown btn-sm">
              <i class="fas fa-key me-2"></i>Reset Password
            </a>
            <button type="button" class="btn btn-outline-brown btn-sm" onclick="toggleStatus()">
              <i class="fas fa-toggle-on me-2"></i>Toggle Status
            </button>
          </div>
        </div>
      </div>

      <!-- Tips Card -->
      <div class="card">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0">
            <i class="fas fa-lightbulb me-2 text-warning"></i>SuperAdmin Tips
          </h6>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0 small">
            <li class="mb-2">
              <i class="fas fa-check-circle text-brown me-2"></i>
              You can reset passwords for all users
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle text-brown me-2"></i>
              Inactive users cannot access the system
            </li>
            <li class="mb-2">
              <i class="fas fa-check-circle text-brown me-2"></i>
              Role changes are logged in activity
            </li>
            <li class="mb-0">
              <i class="fas fa-check-circle text-brown me-2"></i>
              SuperAdmin accounts cannot be edited
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

.avatar-circle-lg {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  background: linear-gradient(135deg, #4a301f 0%, #382417 100%);
}

.list-group-item {
  border-left: none;
  border-right: none;
  transition: background-color 0.2s;
}

.list-group-item:hover {
  background-color: #fff3e0;
}

.list-group-item:first-child {
  border-top: none;
}

.list-group-item:last-child {
  border-bottom: none;
}

.alert-warning-brown {
  background-color: #fff8e1;
  border: 1px solid #ffd54f;
  color: #4a301f;
  border-radius: 0.375rem;
}

.alert-warning-brown strong {
  color: #382417;
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

.badge {
  font-size: 0.8rem;
  padding: 0.4rem 0.6rem;
}
</style>

<script>
// Toggle status shortcut
function toggleStatus() {
  const statusSelect = document.getElementById('status');
  statusSelect.value = statusSelect.value === 'active' ? 'inactive' : 'active';

  // Highlight the select
  statusSelect.classList.add('bg-light');
  setTimeout(() => {
    statusSelect.classList.remove('bg-light');
  }, 1000);

  // Scroll to select
  statusSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

$(document).ready(function() {
  const form = $('#editUserForm');
  const usernameInput = $('#username');
  const roleSelect = $('#role');
  const statusSelect = $('#status');

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

    // Confirmation for important changes
    const originalRole = '<?php echo $user['role']; ?>';
    const originalStatus = '<?php echo $user['status']; ?>';
    const newRole = roleSelect.val();
    const newStatus = statusSelect.val();

    let changes = [];

    if (originalRole !== newRole) {
      const roleNames = {
        'staff': 'Staff',
        'admin': 'Admin'
      };
      changes.push(`Role: ${roleNames[originalRole]} → ${roleNames[newRole]}`);
    }

    if (originalStatus !== newStatus) {
      const statusNames = {
        'active': 'Active',
        'inactive': 'Inactive'
      };
      changes.push(`Status: ${statusNames[originalStatus]} → ${statusNames[newStatus]}`);
    }

    if (changes.length > 0) {
      const confirmed = confirm(
        `You are making the following changes:\n\n` +
        changes.join('\n') +
        `\n\nThese changes will take effect immediately.\n\n` +
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
