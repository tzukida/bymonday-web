<?php

  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireAdmin();

  $conn = getDBConnection();

  $search = sanitizeInput($_GET['search'] ?? '');
  $user_filter = isset($_GET['user']) ? (int)$_GET['user'] : '';
  $action_filter = sanitizeInput($_GET['action'] ?? '');
  $date_from = sanitizeInput($_GET['date_from'] ?? '');
  $date_to = sanitizeInput($_GET['date_to'] ?? '');

  $per_page = 25;
  $page = max(1, (int)($_GET['page'] ?? 1));
  $offset = ($page - 1) * $per_page;

  $where_conditions = ["1=1"];
  $params = [];
  $types = '';

  $excluded_actions = ['Visit Dashboard', 'Visit', 'View', 'Access', 'Browse'];
  $excluded_clause = "(";
  $first = true;
  foreach ($excluded_actions as $excluded) {
    if (!$first) $excluded_clause .= " AND ";
    $excluded_clause .= "a.action NOT LIKE ?";
    $params[] = "%$excluded%";
    $types .= 's';
    $first = false;
  }
  $excluded_clause .= ")";
  $where_conditions[] = $excluded_clause;

  if (!empty($search)) {
    $where_conditions[] = "(a.action LIKE ? OR a.details LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
  }

  if ($user_filter) {
    $where_conditions[] = "a.user_id = ?";
    $params[] = $user_filter;
    $types .= 'i';
  }

  if (!empty($action_filter)) {
    $where_conditions[] = "a.action LIKE ?";
    $action_param = "%$action_filter%";
    $params[] = $action_param;
    $types .= 's';
  }

  if ($date_from) {
    $where_conditions[] = "DATE(a.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
  }

  if ($date_to) {
    $where_conditions[] = "DATE(a.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
  }

  $where = implode(' AND ', $where_conditions);

  $count_sql = "SELECT COUNT(*) as total FROM activity_log a
                JOIN users u ON a.user_id = u.id
                WHERE $where";
  $count_stmt = $conn->prepare($count_sql);
  if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
  }
  $count_stmt->execute();
  $total_logs = $count_stmt->get_result()->fetch_assoc()['total'];
  $count_stmt->close();

  $total_pages = max(1, ceil($total_logs / $per_page));

  $sql = "SELECT a.*, u.username
          FROM activity_log a
          JOIN users u ON a.user_id = u.id
          WHERE $where
          ORDER BY a.created_at DESC
          LIMIT ? OFFSET ?";

  $stmt = $conn->prepare($sql);
  $types_with_pagination = $types . 'ii';
  $params_with_pagination = array_merge($params, [$per_page, $offset]);
  if (!empty($params_with_pagination)) {
    $stmt->bind_param($types_with_pagination, ...$params_with_pagination);
  }
  $stmt->execute();
  $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  $users_stmt = $conn->prepare("SELECT id, username FROM users ORDER BY username ASC");
  $users_stmt->execute();
  $users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $users_stmt->close();

  $actions_stmt = $conn->prepare("SELECT DISTINCT action FROM activity_log ORDER BY action ASC");
  $actions_stmt->execute();
  $actions = $actions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $actions_stmt->close();

  $stats_sql = "SELECT
                  COUNT(*) as total_activities,
                  COUNT(DISTINCT user_id) as active_users,
                  COUNT(DISTINCT DATE(created_at)) as active_days
                FROM activity_log a
                WHERE $where";
  $stats_stmt = $conn->prepare($stats_sql);
  if (!empty($params)) {
    $stats_stmt->bind_param($types, ...$params);
  }
  $stats_stmt->execute();
  $stats = $stats_stmt->get_result()->fetch_assoc();
  $stats_stmt->close();

  $today_count = $conn->query("SELECT COUNT(*) as count FROM activity_log WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];

  $page_title = 'Activity Log';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #3b2008;">Activity Log</h3>
          <p class="text-muted mb-0">Monitor all system activities and user actions</p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-brown" id="exportAuditBtn">
            <i class="fas fa-file-excel me-2"></i>Export to Excel
          </button>
          <a href="<?php echo getBaseURL(); ?>/dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats Cards Row -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #3b2008 0%, #2a1505 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-clipboard-list fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['total_activities']); ?></h3>
          <p class="mb-0 opacity-75">Total Activities</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #6b3a1f 0%, #3d1c02 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-calendar-day fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($today_count); ?></h3>
          <p class="mb-0 opacity-75">Today's Activities</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #5a2d00 0%, #3d1c02 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-users fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['active_users']); ?></h3>
          <p class="mb-0 opacity-75">Active Users</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #c87533 0%, #a05a20 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-calendar-week fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['active_days']); ?></h3>
          <p class="mb-0 opacity-75">Active Days</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Compact Search and Filter Card -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <form method="GET">
            <!-- Main Filters Row -->
            <div class="row g-2 mb-2">
              <div class="col-lg-4 col-md-6">
                <label class="form-label-sm mb-1 text-muted">
                  <i class="fas fa-search me-1"></i>Search
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       name="search"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search action, details, or username...">
              </div>
              <div class="col-lg-2 col-md-6">
                <label class="form-label-sm mb-1 text-muted">
                  <i class="fas fa-user me-1"></i>User
                </label>
                <select name="user" class="form-select form-select-sm">
                  <option value="">All Users</option>
                  <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>"
                            <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-lg-3 col-md-6">
                <label class="form-label-sm mb-1 text-muted">
                  <i class="fas fa-tag me-1"></i>Action
                </label>
                <select name="action" class="form-select form-select-sm">
                  <option value="">All Actions</option>
                  <?php foreach ($actions as $action): ?>
                    <option value="<?php echo htmlspecialchars($action['action']); ?>"
                            <?php echo $action_filter === $action['action'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($action['action']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-lg-3 col-md-6">
                <label class="form-label-sm mb-1 text-muted">&nbsp;</label>
                <div class="d-flex gap-1">
                  <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="fas fa-filter me-1"></i>Apply
                  </button>
                  <a href="activity_log.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-times"></i>
                  </a>
                  <button type="button" class="btn btn-outline-secondary btn-sm" style="color: #6b3a1f; border-color: #6b3a1f;" data-bs-toggle="collapse" data-bs-target="#dateFilter">
                    <i class="fas fa-calendar-alt"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Collapsible Date Range Filter -->
            <div class="collapse <?php echo ($date_from || $date_to) ? 'show' : ''; ?>" id="dateFilter">
              <div class="row g-2 pt-2 border-top">
                <div class="col-md-3">
                  <label class="form-label-sm mb-1 text-muted">
                    <i class="fas fa-calendar-alt me-1"></i>From Date
                  </label>
                  <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label-sm mb-1 text-muted">
                    <i class="fas fa-calendar-alt me-1"></i>To Date
                  </label>
                  <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-2">
                  <label class="form-label-sm mb-1">&nbsp;</label>
                  <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-calendar-check me-1"></i>Apply Dates
                  </button>
                </div>
              </div>
            </div>
          </form>

          <!-- Active Filters Display -->
          <?php if (!empty($search) || $user_filter || $action_filter || $date_from || $date_to): ?>
          <div class="mt-2 pt-2 border-top">
            <small class="text-muted d-block mb-1"><i class="fas fa-filter me-1"></i>Active Filters:</small>
            <div class="d-flex flex-wrap gap-1">
              <?php if (!empty($search)): ?>
                <span class="badge bg-secondary">Search: <?php echo htmlspecialchars($search); ?></span>
              <?php endif; ?>
              <?php if ($user_filter):
                $user_name = array_filter($users, fn($u) => $u['id'] == $user_filter)[0]['username'] ?? '';
              ?>
                <span class="badge bg-secondary">User: <?php echo htmlspecialchars($user_name); ?></span>
              <?php endif; ?>
              <?php if ($action_filter): ?>
                <span class="badge bg-secondary">Action: <?php echo htmlspecialchars($action_filter); ?></span>
              <?php endif; ?>
              <?php if ($date_from): ?>
                <span class="badge bg-secondary">From: <?php echo date('M j, Y', strtotime($date_from)); ?></span>
              <?php endif; ?>
              <?php if ($date_to): ?>
                <span class="badge bg-secondary">To: <?php echo date('M j, Y', strtotime($date_to)); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Activity Log Table -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="fas fa-history me-2 icon-brown"></i>Activity Records
          </h5>
          <span class="badge bg-brown">
            <?php echo number_format($total_logs); ?> Records
          </span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($logs)): ?>
            <div class="text-center py-5">
              <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No activity logs found</h5>
              <?php if (!empty($search) || $user_filter || $action_filter || $date_from || $date_to): ?>
                <p class="text-muted mb-3">Try adjusting your filters</p>
                <a href="activity_log.php" class="btn btn-outline-secondary">Clear Filters</a>
              <?php else: ?>
                <p class="text-muted">Activity logs will appear here as users perform actions</p>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0" id="auditLogTable">
                <thead class="table-light">
                  <tr>
                    <th class="border-0 text-center" style="width: 60px;">#</th>
                    <th class="border-0">User</th>
                    <th class="border-0">Action</th>
                    <th class="border-0">Details</th>
                    <th class="border-0">Date & Time</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $row_number = $offset + 1;
                  foreach ($logs as $log):
                  ?>
                  <tr>
                    <td class="text-center text-muted small">
                      <strong><?php echo $row_number++; ?></strong>
                    </td>
                    <td>
                      <div class="d-flex align-items-center">
                        <div class="me-2">
                          <i class="fas fa-user-circle fa-lg text-muted"></i>
                        </div>
                        <strong class="text-dark"><?php echo htmlspecialchars($log['username']); ?></strong>
                      </div>
                    </td>
                    <td>
                      <?php
                        // Determine badge color based on action type
                        $action = $log['action'];
                        $badge_color = '#6c757d'; // default grey
                        $icon = 'fa-circle';

                        // AUTH
                        if (in_array($action, ['Login', 'Logout'])) {
                            $badge_color = '#3b2008'; 
                            $icon = $action === 'Login' ? 'fa-sign-in-alt' : 'fa-sign-out-alt';

                        // MENU
                        } elseif (in_array($action, ['Add Menu Item', 'Update Menu Item', 'Delete Menu Item', 'Remove Menu Item Image'])) {
                            $badge_color = '#c97b2b'; // amber brown
                            $icon = 'fa-utensils';

                        // RECIPE
                        } elseif (in_array($action, ['Add Recipe Ingredient', 'Remove Recipe Ingredient', 'Update Recipe Quantity'])) {
                            $badge_color = '#a0522d'; // sienna
                            $icon = 'fa-book';

                        // INVENTORY
                        } elseif (in_array($action, ['Add Inventory Item', 'Edit Inventory Item', 'Delete Inventory Item', 'Export Inventory'])) {
                            $badge_color = '#5c3010'; 
                            $icon = 'fa-boxes';

                        // STOCK / EXPORT
                        } elseif (in_array($action, ['Export Stock Logs', 'Export Audit Logs', 'Export Sales Report', 'Export Password Resets', 'Print Sales Report'])) {
                            $badge_color = '#4d2c0a'; 
                            $icon = 'fa-file-export';

                        // POS / SALES
                        } elseif (in_array($action, ['Process Sale'])) {
                            $badge_color = '#7a3b10'; 
                            $icon = 'fa-cash-register';

                        // ONLINE ORDERS
                        } elseif (in_array($action, ['Accept Order', 'Cancel Order', 'Mark Order Ready', 'Mark Order Delivered', 'Assign Rider'])) {
                            $badge_color = '#9c4a1a'; 
                            $icon = 'fa-bag-shopping';

                        // ACCOUNT MANAGEMENT
                        } elseif (in_array($action, ['Add Staff', 'Add User', 'Edit Staff', 'Toggle Staff Status', 'Toggle User Status', 'Reset Password', 'Password Changed', 'Profile Updated', 'Password Reset'])) {
                            $badge_color = '#6b3a1f'; 
                            $icon = 'fa-user-cog';

                        // DATABASE
                        } elseif (in_array($action, ['Database Backup'])) {
                            $badge_color = '#2a1505'; 
                            $icon = 'fa-database';
                        }
                      ?>
                      <span class="badge <?php echo $badge_class; ?>" <?php echo !empty($badge_style) ? 'style="' . $badge_style . '"' : ''; ?>>
                        <i class="fas <?php echo $icon; ?> me-1"></i>
                        <?php echo htmlspecialchars($action); ?>
                      </span>
                    </td>
                    <td>
                      <?php
                        $details = $log['details'];
                        if (strlen($details) > 60):
                      ?>
                        <span title="<?php echo htmlspecialchars($details); ?>" data-bs-toggle="tooltip">
                          <?php echo htmlspecialchars(substr($details, 0, 60)) . '...'; ?>
                        </span>
                      <?php else: ?>
                        <?php echo htmlspecialchars($details); ?>
                      <?php endif; ?>
                    </td>
                    <td class="text-muted small">
                      <div>
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('M j, Y', strtotime($log['created_at'])); ?>
                      </div>
                      <div>
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('h:i A', strtotime($log['created_at'])); ?>
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
        <?php if ($total_pages > 1 && !empty($logs)): ?>
        <div class="card-footer bg-light">
          <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
              <p class="text-muted mb-0 small">
                Showing <?php echo number_format($offset + 1); ?> to
                <?php echo number_format(min($offset + $per_page, $total_logs)); ?> of
                <?php echo number_format($total_logs); ?> records
              </p>
            </div>
            <div class="col-md-6">
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm justify-content-md-end justify-content-center mb-0">
                  <!-- First Page -->
                  <?php if ($page > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&user=<?php echo $user_filter; ?>&action=<?php echo urlencode($action_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" title="First Page">
                      <i class="fas fa-angle-double-left"></i>
                    </a>
                  </li>
                  <?php endif; ?>

                  <!-- Previous Page -->
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search); ?>&user=<?php echo $user_filter; ?>&action=<?php echo urlencode($action_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" <?php echo $page <= 1 ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-left"></i>
                    </a>
                  </li>

                  <!-- Page Numbers -->
                  <?php
                  $start_page = max(1, $page - 2);
                  $end_page = min($total_pages, $page + 2);

                  if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&user=' . $user_filter . '&action=' . urlencode($action_filter) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">1</a></li>';
                    if ($start_page > 2) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                  }

                  for ($i = $start_page; $i <= $end_page; $i++):
                  ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                      <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&user=<?php echo $user_filter; ?>&action=<?php echo urlencode($action_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                        <?php echo $i; ?>
                      </a>
                    </li>
                  <?php
                  endfor;

                  if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&user=' . $user_filter . '&action=' . urlencode($action_filter) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">' . $total_pages . '</a></li>';
                  }
                  ?>

                  <!-- Next Page -->
                  <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search); ?>&user=<?php echo $user_filter; ?>&action=<?php echo urlencode($action_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" <?php echo $page >= $total_pages ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-right"></i>
                    </a>
                  </li>

                  <!-- Last Page -->
                  <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&user=<?php echo $user_filter; ?>&action=<?php echo urlencode($action_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" title="Last Page">
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

.text-gray {
  color: #595C5F;
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

.badge {
  font-weight: 500;
  padding: 0.35rem 0.65rem;
}

.form-label-sm {
  font-size: 0.875rem;
  font-weight: 500;
  color: #495057;
}

.btn-primary {
  background-color: #3b2008 !important;
  border-color: #3b2008;
}

.btn-primary:hover {
  background-color: #2a1505;
  border-color: #2a1505;
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

.form-select-sm, .form-control-sm {
  font-size: 0.875rem;
}

.badge {
  font-size: 0.75rem;
}

@media (max-width: 768px) {
  .table {
    font-size: 0.875rem;
  }

  .btn-sm {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
  }
}

@media (max-width: 576px) {
  .d-flex.gap-1 {
    flex-direction: column;
    gap: 0.25rem !important;
  }

  .d-flex.gap-1 .btn {
    width: 100%;
  }
}
.form-select:focus,
.form-control:focus,
.form-check-input:focus {
  border-color: #3b2008 !important;
  box-shadow: 0 0 0 0.2rem rgba(59, 32, 8, 0.25) !important;
  outline: none !important;
}

.form-select {
  accent-color: #3b2008;
}

option:checked,
option:hover {
  background-color: #3b2008 !important;
  color: #fff !important;
}

</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
// ── Export to Excel ──
document.getElementById('exportAuditBtn').addEventListener('click', function() {
    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Exporting...';

    const exportData = [
        ['#', 'User', 'Action', 'Details', 'Date & Time']
    ];

    document.querySelectorAll('#auditLogTable tbody tr').forEach((row) => {
        if (!row.querySelector('td')) return;
        const cells = row.querySelectorAll('td');
        exportData.push([
            cells[0].textContent.trim(),
            cells[1].textContent.trim(),
            cells[2].textContent.trim(),
            cells[3].textContent.trim(),
            cells[4].textContent.trim()
        ]);
    });

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(exportData);
    ws['!cols'] = [
        { wch: 5 },
        { wch: 20 },
        { wch: 25 },
        { wch: 50 },
        { wch: 20 }
    ];
    XLSX.utils.book_append_sheet(wb, ws, 'Audit Logs');
    const filename = `Audit_Logs_${new Date().toISOString().split('T')[0]}.xlsx`;
    XLSX.writeFile(wb, filename);

    // Log to audit trail
    fetch('<?php echo getBaseURL(); ?>/api/log_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'Export Audit Logs', details: 'Exported audit logs to Excel: ' + filename })
    }).catch(() => {});

    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }, 800);
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
