<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';

  requireSuperAdmin();
  $conn = getDBConnection();

  // Handle AJAX requests
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
      header('Content-Type: application/json');

      if ($_POST['action'] === 'toggle_status') {
          $user_id = intval($_POST['user_id']);
          $new_status = $_POST['status'];

          if (toggleUserStatus($user_id, $new_status)) {
              $user = getUserById($user_id);
              logActivity($_SESSION['user_id'], 'Toggle User Status',
                  "Changed status for {$user['username']} to: $new_status");
              echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
          } else {
              echo json_encode(['success' => false, 'message' => 'Failed to update status']);
          }
          exit;
      }

      if ($_POST['action'] === 'reset_password') {
          $user_id = intval($_POST['user_id']);
          $user = getUserById($user_id);

          if ($user && resetUserPassword($user_id, $_SESSION['user_id'])) {
              echo json_encode([
                  'success' => true,
                  'message' => 'Password reset successfully',
                  'default_password' => DEFAULT_RESET_PASSWORD
              ]);
          } else {
              echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
          }
          exit;
      }
  }

  // Get filter and search parameters
  $search = sanitizeInput($_GET['search'] ?? '');
  $role_filter = sanitizeInput($_GET['role'] ?? '');
  $status_filter = sanitizeInput($_GET['status'] ?? '');

  // Pagination
  $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $per_page = 15;
  $offset = ($page - 1) * $per_page;

  // Build query conditions
  $where_conditions = [];
  $params = [];
  $types = '';

  if (!empty($search)) {
    $where_conditions[] = "(username LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $types .= 's';
  }

  if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $types .= 's';
  }

  if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
  }

  $where_sql = '';
  if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
  }

  // Count total users with filters
  $count_sql = "SELECT COUNT(*) as total FROM users $where_sql";
  $count_stmt = $conn->prepare($count_sql);
  if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
  }
  $count_stmt->execute();
  $total_users = $count_stmt->get_result()->fetch_assoc()['total'];
  $total_pages = max(1, ceil($total_users / $per_page));

  // Get users with pagination
  $sql = "SELECT * FROM users $where_sql ORDER BY id ASC LIMIT ? OFFSET ?";
  $stmt = $conn->prepare($sql);

  if (!empty($params)) {
    $params[] = $per_page;
    $params[] = $offset;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
  } else {
    $stmt->bind_param('ii', $per_page, $offset);
  }

  $stmt->execute();
  $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

  // Get all users for stats (without pagination)
  $all_users = getAllUsers();

  // Calculate stats
  $active_count = count(array_filter($all_users, fn($u) => $u['status'] === 'active'));
  $inactive_count = count(array_filter($all_users, fn($u) => $u['status'] === 'inactive'));
  $admin_count = count(array_filter($all_users, fn($u) => in_array($u['role'], ['superadmin', 'admin'])));

  $page_title = 'Account Management';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #4a301f;">Account Management</h3>
          <p class="text-muted mb-0">Manage user accounts and permissions</p>
        </div>
        <div>
          <a href="add_staff.php" class="btn btn-brown">
            <i class="fas fa-user-plus me-2"></i>Add New User
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats Cards Row -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #382417 0%, #2a1b11 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-users fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format(count($users)); ?></h3>
          <p class="mb-0 opacity-75">Total Accounts</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #4d3420 0%, #382417 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-user-check fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($active_count); ?></h3>
          <p class="mb-0 opacity-75">Active Accounts</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #654529 0%, #4d3420 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-user-slash fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($inactive_count); ?></h3>
          <p class="mb-0 opacity-75">Inactive Accounts</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #7d5633 0%, #654529 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-user-shield fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($admin_count); ?></h3>
          <p class="mb-0 opacity-75">Admin Accounts</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Search and Filter Card -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-search me-1"></i>Search Username
              </label>
              <input type="text"
                     class="form-control"
                     name="search"
                     value="<?php echo htmlspecialchars($search); ?>"
                     placeholder="Search by username...">
            </div>
            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-user-tag me-1"></i>Role
              </label>
              <select name="role" class="form-select">
                <option value="">All Roles</option>
                <option value="superadmin" <?php echo $role_filter === 'superadmin' ? 'selected' : ''; ?>>Super Admin</option>
                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="staff" <?php echo $role_filter === 'staff' ? 'selected' : ''; ?>>Staff</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-toggle-on me-1"></i>Status
              </label>
              <select name="status" class="form-select">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
              </select>
            </div>
            <div class="col-md-4">
              <div class="btn-group w-100">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-filter me-1"></i>Apply Filter
                </button>
                <a href="users.php" class="btn btn-outline-secondary">
                  <i class="fas fa-times me-1"></i>Clear
                </a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- User Accounts Table -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card- bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="fas fa-users-cog me-2 icon-brown"></i>User Accounts
          </h5>
          <div class="d-flex align-items-center gap-3">
            <span class="badge bg-brown">
              <?php echo number_format($total_users); ?> Total Users
            </span>
            <a href="password_reset_history.php" class="btn btn-sm btn-outline-brown">
              <i class="fas fa-history me-1"></i>Reset History
            </a>
          </div>
        </div>
        <div class="card-body p-0">
          <?php if (empty($users)): ?>
            <div class="text-center py-5">
              <i class="fas fa-users fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No user accounts found</h5>
              <p class="text-muted mb-3">Start by adding your first user account</p>
              <a href="add_staff.php" class="btn btn-brown">
                <i class="fas fa-user-plus me-2"></i>Add First User
              </a>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover mb-0" id="usersTable">
                <thead class="table-light">
                  <tr>
                    <th class="border-0">ID</th>
                    <th class="border-0">Username</th>
                    <th class="border-0">Role</th>
                    <th class="border-0 text-center">Status</th>
                    <th class="border-0">Created</th>
                    <th class="border-0">Last Reset</th>
                    <th class="border-0 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $user): ?>
                  <tr data-user-id="<?php echo $user['id']; ?>">
                    <td class="align-middle">
                      <strong>#<?php echo str_pad($user['id'], 3, '0', STR_PAD_LEFT); ?></strong>
                    </td>
                    <td class="align-middle">
                      <div class="d-flex align-items-center">
                        <div class="avatar-circle me-2">
                          <i class="fas fa-user"></i>
                        </div>
                        <div>
                          <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                          <?php if ($user['id'] == $_SESSION['user_id']): ?>
                            <span class="badge bg-info ms-1">You</span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                    <td class="align-middle">
                      <?php
                      $role_badges = [
                        'superadmin' => '<span class="badge bg-brown"><i class="fas fa-crown me-1"></i>Super Admin</span>',
                        'admin' => '<span class="badge" style="background: linear-gradient(135deg, #4d3420 0%, #382417 100%);"><i class="fas fa-user-shield me-1"></i>Admin</span>',
                        'staff' => '<span class="badge bg-secondary"><i class="fas fa-user me-1"></i>Staff</span>'
                      ];
                      echo $role_badges[$user['role']] ?? '';
                      ?>
                    </td>
                    <td class="text-center align-middle">
                      <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input status-toggle"
                               type="checkbox"
                               data-user-id="<?php echo $user['id']; ?>"
                               <?php echo $user['status'] === 'active' ? 'checked' : ''; ?>
                               <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>
                               style="cursor: pointer;">
                      </div>
                      <small class="d-block mt-1 status-text <?php echo $user['status'] === 'active' ? 'text-success' : 'text-muted'; ?>">
                        <?php echo ucfirst($user['status']); ?>
                      </small>
                    </td>
                    <td class="text-muted small align-middle">
                      <?php echo formatDate($user['created_at'], 'M j, Y'); ?>
                    </td>
                    <td class="text-muted small align-middle">
                      <?php if (!empty($user['last_password_reset'])): ?>
                        <i class="fas fa-key me-1"></i>
                        <?php echo formatDate($user['last_password_reset'], 'M j, Y H:i'); ?>
                      <?php else: ?>
                        <span class="text-muted">
                          <i class="fas fa-minus me-1"></i>Never
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center align-middle">
                      <div class="btn-group btn-group-sm" role="group">
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <a href="edit_staff.php?id=<?php echo $user['id']; ?>"
                           class="btn btn-outline-brown"
                           title="Edit User">
                          <i class="fas fa-edit"></i>
                        </a>
                        <button class="btn btn-outline-warning reset-password-btn"
                                data-user-id="<?php echo $user['id']; ?>"
                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                title="Reset Password">
                          <i class="fas fa-key"></i>
                        </button>
                        <?php else: ?>
                        <a href="profile.php"
                           class="btn btn-outline-brown"
                           title="Edit Profile">
                          <i class="fas fa-user-edit"></i>
                        </a>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Enhanced Pagination -->
        <?php if ($total_pages > 1 && !empty($users)): ?>
        <div class="card-footer bg-light">
          <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
              <p class="text-muted mb-0 small">
                Showing <?php echo number_format($offset + 1); ?> to
                <?php echo number_format(min($offset + $per_page, $total_users)); ?> of
                <?php echo number_format($total_users); ?> users
              </p>
            </div>
            <div class="col-md-6">
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm justify-content-md-end justify-content-center mb-0">
                  <!-- First Page -->
                  <?php if ($page > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" title="First Page">
                      <i class="fas fa-angle-double-left"></i>
                    </a>
                  </li>
                  <?php endif; ?>

                  <!-- Previous Page -->
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" <?php echo $page <= 1 ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-left"></i>
                    </a>
                  </li>

                  <!-- Page Numbers -->
                  <?php
                  $start_page = max(1, $page - 2);
                  $end_page = min($total_pages, $page + 2);

                  // Show first page if we're not starting from page 1
                  if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&role=' . urlencode($role_filter) . '&status=' . urlencode($status_filter) . '">1</a></li>';
                    if ($start_page > 2) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                  }

                  for ($i = $start_page; $i <= $end_page; $i++):
                  ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                      <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                        <?php echo $i; ?>
                      </a>
                    </li>
                  <?php
                  endfor;

                  // Show last page if we're not ending at the last page
                  if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&role=' . urlencode($role_filter) . '&status=' . urlencode($status_filter) . '">' . $total_pages . '</a></li>';
                  }
                  ?>

                  <!-- Next Page -->
                  <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" <?php echo $page >= $total_pages ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-right"></i>
                    </a>
                  </li>

                  <!-- Last Page -->
                  <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" title="Last Page">
                      <i class="fas fa-angle-double-right"></i>
                    </a>
                  </li>
                  <?php endif; ?>
                </ul>
              </nav>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Password Reset Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">
          <i class="fas fa-key text-warning me-2"></i>Reset Password
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2">
        <div class="text-center mb-3">
          <div class="avatar-circle-lg mx-auto mb-3" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
            <i class="fas fa-user-lock text-white"></i>
          </div>
          <p class="mb-2">Are you sure you want to reset the password for</p>
          <h5 class="text-dark mb-0" id="resetUsername"></h5>
        </div>
        <div class="alert alert-info border-0" style="background-color: #e7f3ff;">
          <i class="fas fa-info-circle me-2"></i>
          <small>The password will be reset to: <code class="text-dark"><?php echo DEFAULT_RESET_PASSWORD; ?></code></small>
        </div>
        <div id="resetResult" class="alert d-none border-0"></div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i>Cancel
        </button>
        <button type="button" class="btn btn-warning" id="confirmResetBtn">
          <i class="fas fa-key me-1"></i>Reset Password
        </button>
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
  color: #4a301f;
}

.bg-brown {
  background-color: #382417;
  color: #fff;
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
  transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
  box-shadow: 0 0.5rem 1rem rgba(56, 36, 23, 0.15);
}

.table thead th {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.5px;
  color: #6c757d;
}

.table tbody tr {
  transition: background-color 0.2s;
}

.table tbody tr:hover {
  background-color: #f8f9fa;
}

.avatar-circle {
  width: 35px;
  height: 35px;
  border-radius: 50%;
  background: linear-gradient(135deg, #382417 0%, #2a1b11 100%);
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

.btn-group-sm .btn {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

.form-check-input.status-toggle {
  width: 2.5rem;
  height: 1.25rem;
  cursor: pointer;
}

.form-check-input.status-toggle:checked {
  background-color: #198754;
  border-color: #198754;
}

.status-text {
  font-weight: 500;
  font-size: 0.75rem;
}

.pagination {
  --bs-pagination-active-bg: #382417;
  --bs-pagination-active-border-color: #382417;
  --bs-pagination-hover-color: #382417;
}

.pagination .page-link {
  color: #6c757d;
  border-radius: 0.25rem;
  margin: 0 2px;
  transition: all 0.2s;
}

.pagination .page-link:hover {
  background-color: #f8f9fa;
  border-color: #dee2e6;
  color: #382417;
}

.pagination .page-item.active .page-link {
  background-color: #382417;
  border-color: #382417;
  color: white;
  font-weight: 600;
}

.pagination .page-item.disabled .page-link {
  color: #adb5bd;
  background-color: transparent;
  border-color: #dee2e6;
}

.form-label {
  font-weight: 500;
  color: #495057;
}

.btn-primary {
  background-color: #382417;
  border-color: #382417;
}

.btn-primary:hover {
  background-color: #4d3420;
  border-color: #4d3420;
}

.badge {
  font-weight: 500;
  padding: 0.35rem 0.65rem;
}

.modal-content {
  border: none;
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.modal-header {
  background-color: #f8f9fa;
}

@media (max-width: 768px) {
  .btn-group-sm {
    display: flex;
    flex-direction: column;
  }

  .btn-group-sm .btn {
    margin-bottom: 0.25rem;
    border-radius: 0.25rem !important;
  }

  .table {
    font-size: 0.875rem;
  }

  .avatar-circle {
    width: 30px;
    height: 30px;
    font-size: 0.75rem;
  }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // No DataTables - using manual pagination instead

    // Status toggle
    $('.status-toggle').on('change', function() {
        const userId = $(this).data('user-id');
        const newStatus = $(this).is(':checked') ? 'active' : 'inactive';
        const $row = $(this).closest('tr');
        const $statusText = $row.find('.status-text');
        const $toggle = $(this);

        $.ajax({
            url: 'users.php',
            method: 'POST',
            data: {
                ajax: true,
                action: 'toggle_status',
                user_id: userId,
                status: newStatus
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $statusText.text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));
                    if (newStatus === 'active') {
                        $statusText.removeClass('text-muted').addClass('text-success');
                    } else {
                        $statusText.removeClass('text-success').addClass('text-muted');
                    }
                    showAlert('success', response.message);
                } else {
                    $toggle.prop('checked', !$toggle.is(':checked'));
                    showAlert('danger', response.message);
                }
            },
            error: function() {
                $toggle.prop('checked', !$toggle.is(':checked'));
                showAlert('danger', 'An error occurred while updating status');
            }
        });
    });

    // Reset password modal
    let currentUserId = null;
    const resetModal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));

    $('.reset-password-btn').on('click', function() {
        currentUserId = $(this).data('user-id');
        const username = $(this).data('username');
        $('#resetUsername').text(username);
        $('#resetResult').addClass('d-none');
        resetModal.show();
    });

    // Confirm reset
    $('#confirmResetBtn').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Resetting...');

        $.ajax({
            url: 'users.php',
            method: 'POST',
            data: {
                ajax: true,
                action: 'reset_password',
                user_id: currentUserId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#resetResult')
                        .removeClass('d-none alert-danger')
                        .addClass('alert-success')
                        .html(`<i class="fas fa-check-circle me-2"></i>${response.message}<br>
                               <small><strong>New Password:</strong> <code class="text-dark">${response.default_password}</code></small>`);

                    setTimeout(function() {
                        resetModal.hide();
                        location.reload();
                    }, 3000);
                } else {
                    $('#resetResult')
                        .removeClass('d-none alert-success')
                        .addClass('alert-danger')
                        .html(`<i class="fas fa-exclamation-circle me-2"></i>${response.message}`);
                }
            },
            error: function() {
                $('#resetResult')
                    .removeClass('d-none alert-success')
                    .addClass('alert-danger')
                    .html('<i class="fas fa-exclamation-circle me-2"></i>An error occurred');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-key me-1"></i>Reset Password');
            }
        });
    });

    function showAlert(type, message) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3"
                 role="alert" style="z-index: 9999; min-width: 300px;">
                <i class="fas ${iconClass} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('body').append(alertHtml);
        setTimeout(function() {
            $('.alert').fadeOut(function() { $(this).remove(); });
        }, 3000);
    }

    // Auto-refresh after 5 minutes
    setTimeout(function() {
        if (!document.hidden) location.reload();
    }, 5 * 60 * 1000);
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php';
