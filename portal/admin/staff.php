<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';
requireAdmin();

$conn = getDBConnection();

// ✅ Handle AJAX toggle requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'toggle_status') {
        $user_id = intval($_POST['user_id']);
        $new_status = $_POST['status'];

        $user = getUserById($user_id);
        if ($user && $user['role'] === 'staff') {
            if (toggleUserStatus($user_id, $new_status)) {
                logActivity($_SESSION['user_id'], 'Toggle Staff Status',
                    "Changed status for {$user['username']} to: $new_status");
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update status']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid user']);
        }
        exit;
    }
}

// Get filter and search parameters
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build query conditions - Only get staff users
$where_conditions = ["role = 'staff'"];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $types .= 's';
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = 'WHERE ' . implode(' AND ', $where_conditions);

// Count total staff with filters
$count_sql = "SELECT COUNT(*) as total FROM users $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_users / $per_page));

// Get staff users with pagination
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
$staffs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all staff for stats (without pagination)
$all_staff_stmt = $conn->prepare("SELECT * FROM users WHERE role = 'staff' ORDER BY created_at DESC");
$all_staff_stmt->execute();
$all_staff = $all_staff_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate stats
$active_count = count(array_filter($all_staff, fn($s) => $s['status'] === 'active'));
$inactive_count = count(array_filter($all_staff, fn($s) => $s['status'] === 'inactive'));

$page_title = 'Staff Management';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #3b2008;">Staff Management</h3>
          <p class="text-muted mb-0">Manage staff accounts and permissions</p>
        </div>
        <div>
          <a href="add_staff.php" class="btn btn-danger">
            <i class="fas fa-user-plus me-2"></i>Add New Staff
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats Cards Row -->
  <div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-4">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #3b2008 0%, #2a1505 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-users fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format(count($all_staff)); ?></h3>
          <p class="mb-0 opacity-75">Total Staff</p>
        </div>
      </div>
    </div>

    <div class="col-xl-4 col-md-4">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #6b3a1f 0%, #3d1c02 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-user-check fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($active_count); ?></h3>
          <p class="mb-0 opacity-75">Active Staff</p>
        </div>
      </div>
    </div>

    <div class="col-xl-4 col-md-4">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #c87533 0%, #a05a20 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-user-slash fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($inactive_count); ?></h3>
          <p class="mb-0 opacity-75">Inactive Staff</p>
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
            <div class="col-md-6">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-search me-1"></i>Search Username
              </label>
              <input type="text"
                     class="form-control"
                     name="search"
                     value="<?php echo htmlspecialchars($search); ?>"
                     placeholder="Search by username...">
            </div>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-toggle-on me-1"></i>Status
              </label>
              <select name="status" class="form-select">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
              </select>
            </div>
            <div class="col-md-3">
              <div class="btn-group w-100">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-filter me-1"></i>Apply Filter
                </button>
                <a href="staff.php" class="btn btn-outline-secondary">
                  <i class="fas fa-times me-1"></i>Clear
                </a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Staff Accounts Table -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="fas fa-users-cog me-2 icon-brown"></i>Staff Accounts
          </h5>
          <span class="badge bg-brown">
            <?php echo number_format($total_users); ?> Total Staff
          </span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($staffs)): ?>
            <div class="text-center py-5">
              <i class="fas fa-users fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No staff accounts found</h5>
              <p class="text-muted mb-3">Start by adding your first staff account</p>
              <a href="add_staff.php" class="btn btn-danger">
                <i class="fas fa-user-plus me-2"></i>Add First Staff
              </a>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover mb-0" id="staffTable">
                <thead class="table-light">
                  <tr>
                    <th class="border-0">ID</th>
                    <th class="border-0">Username</th>
                    <th class="border-0">Role</th>
                    <th class="border-0 text-center">Status</th>
                    <th class="border-0">Created</th>
                    <th class="border-0">Last Reset</th>
                    <th class="border-0">Edit Staff</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($staffs as $staff): ?>
                  <tr data-user-id="<?php echo $staff['id']; ?>">
                    <td class="align-middle">
                      <strong>#<?php echo str_pad($staff['id'], 3, '0', STR_PAD_LEFT); ?></strong>
                    </td>
                    <td class="align-middle">
                      <div class="d-flex align-items-center">
                        <div class="avatar-circle me-2">
                          <i class="fas fa-user"></i>
                        </div>
                        <strong><?php echo htmlspecialchars($staff['username']); ?></strong>
                      </div>
                    </td>
                    <td class="align-middle">
                      <span class="badge bg-brown">
                        <i class="fas fa-user me-1"></i><?php echo ucfirst($staff['role']); ?>
                      </span>
                    </td>
                    <td class="text-center align-middle">
                      <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input status-toggle"
                               type="checkbox"
                               data-user-id="<?php echo $staff['id']; ?>"
                               <?php echo $staff['status'] === 'active' ? 'checked' : ''; ?>
                               style="cursor: pointer;">
                      </div>
                      <small class="d-block mt-1 status-text <?php echo $staff['status'] === 'active' ? 'text-success' : 'text-muted'; ?>">
                        <?php echo ucfirst($staff['status']); ?>
                      </small>
                    </td>
                    <td class="text-muted small align-middle">
                      <?php echo formatDate($staff['created_at'], 'M j, Y'); ?>
                    </td>
                    <td class="text-muted small align-middle">
                      <?php if (!empty($staff['last_password_reset'])): ?>
                        <i class="fas fa-key me-1"></i>
                        <?php echo formatDate($staff['last_password_reset'], 'M j, Y H:i'); ?>
                      <?php else: ?>
                        <span class="text-muted">
                          <i class="fas fa-minus me-1"></i>Never
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="align-middle">
                      <a href="edit_staff.php?id=<?php echo $staff['id']; ?>"
                         class="btn btn-outline-brown btn-sm"
                         title="Edit Staff">
                        <i class="fas fa-edit"></i>
                      </a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Enhanced Pagination -->
        <?php if ($total_pages > 1 && !empty($staffs)): ?>
        <div class="card-footer bg-light">
          <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
              <p class="text-muted mb-0 small">
                Showing <?php echo number_format($offset + 1); ?> to
                <?php echo number_format(min($offset + $per_page, $total_users)); ?> of
                <?php echo number_format($total_users); ?> staff
              </p>
            </div>
            <div class="col-md-6">
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm justify-content-md-end justify-content-center mb-0">
                  <!-- First Page -->
                  <?php if ($page > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" title="First Page">
                      <i class="fas fa-angle-double-left"></i>
                    </a>
                  </li>
                  <?php endif; ?>

                  <!-- Previous Page -->
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" <?php echo $page <= 1 ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-left"></i>
                    </a>
                  </li>

                  <!-- Page Numbers -->
                  <?php
                  $start_page = max(1, $page - 2);
                  $end_page = min($total_pages, $page + 2);

                  if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '">1</a></li>';
                    if ($start_page > 2) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                  }

                  for ($i = $start_page; $i <= $end_page; $i++):
                  ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                      <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                        <?php echo $i; ?>
                      </a>
                    </li>
                  <?php
                  endfor;

                  if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '">' . $total_pages . '</a></li>';
                  }
                  ?>

                  <!-- Next Page -->
                  <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" <?php echo $page >= $total_pages ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-right"></i>
                    </a>
                  </li>

                  <!-- Last Page -->
                  <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" title="Last Page">
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

<style>
.icon-brown {
  color: #3b2008;
}

.bg-brown {
  background-color: #3b2008;
  color: #fff;
}

.btn-outline-brown {
  color: #3b2008;
  border-color: #3b2008;
  background-color: transparent;
}

.btn-outline-brown:hover,
.btn-outline-brown:active,
.btn-outline-brown:focus {
  background-color: #3b2008;
  border-color: #3b2008;
  color: #fff;
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
  box-shadow: 0 0.5rem 1rem rgba(59, 32, 8, 0.15);
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
  background: linear-gradient(135deg, #3b2008 0%, #2a1505 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
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
  --bs-pagination-active-bg: #3b2008;
  --bs-pagination-active-border-color: #3b2008;
  --bs-pagination-hover-color: #3b2008;
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
  color: #3b2008;
}

.pagination .page-item.active .page-link {
  background-color: #3b2008;
  border-color: #3b2008;
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

.btn-danger {
  background-color: #3b2008;
  border-color: #3b2008;
}

.btn-danger:hover {
  background-color: #2a1505;
  border-color: #2a1505;
}

.btn-primary {
  background-color: #3b2008;
  border-color: #3b2008;
}

.btn-primary:hover {
  background-color: #2a1505;
  border-color: #2a1505;
}

.badge {
  font-weight: 500;
  padding: 0.35rem 0.65rem;
}

.bg-red {
  background-color: #751312;
  color: #fff;
}

@media (max-width: 768px) {
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
    // Status toggle
    $('.status-toggle').on('change', function() {
        const userId = $(this).data('user-id');
        const newStatus = $(this).is(':checked') ? 'active' : 'inactive';
        const $row = $(this).closest('tr');
        const $statusText = $row.find('.status-text');
        const $toggle = $(this);

        $.ajax({
            url: 'staff.php',
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
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
