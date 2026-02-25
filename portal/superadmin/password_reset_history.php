<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireSuperAdmin();

  // Get filter parameters
  $search = sanitizeInput($_GET['search'] ?? '');
  $admin_filter = sanitizeInput($_GET['admin'] ?? '');
  $period_filter = sanitizeInput($_GET['period'] ?? '');

  // Pagination
  $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $per_page = 25;
  $offset = ($page - 1) * $per_page;

  // Get all password reset history
  $all_history = getPasswordResetHistory(1000);

  // Apply filters
  $filtered_history = array_filter($all_history, function($log) use ($search, $admin_filter, $period_filter) {
    // Search filter
    if (!empty($search)) {
      $search_lower = strtolower($search);
      $username_match = strpos(strtolower($log['user_username']), $search_lower) !== false;
      $admin_match = strpos(strtolower($log['reset_by_username']), $search_lower) !== false;
      $ip_match = strpos($log['ip_address'], $search) !== false;
      if (!$username_match && !$admin_match && !$ip_match) {
        return false;
      }
    }

    // Admin filter
    if (!empty($admin_filter) && $log['reset_by_username'] !== $admin_filter) {
      return false;
    }

    // Period filter
    if (!empty($period_filter)) {
      $reset_time = strtotime($log['reset_date']);
      switch ($period_filter) {
        case 'today':
          if (date('Y-m-d', $reset_time) !== date('Y-m-d')) {
            return false;
          }
          break;
        case 'week':
          if ($reset_time < strtotime('-7 days')) {
            return false;
          }
          break;
        case 'month':
          if (date('Y-m', $reset_time) !== date('Y-m')) {
            return false;
          }
          break;
      }
    }

    return true;
  });

  // Calculate stats from all history
  $total_resets = count($all_history);
  $today_resets = count(array_filter($all_history, function($log) {
    return date('Y-m-d', strtotime($log['reset_date'])) === date('Y-m-d');
  }));
  $this_week_resets = count(array_filter($all_history, function($log) {
    return strtotime($log['reset_date']) >= strtotime('-7 days');
  }));
  $this_month_resets = count(array_filter($all_history, function($log) {
    return date('Y-m', strtotime($log['reset_date'])) === date('Y-m');
  }));

  // Get unique admins for filter dropdown
  $admins = array_unique(array_column($all_history, 'reset_by_username'));
  sort($admins);

  // Apply pagination
  $total_filtered = count($filtered_history);
  $total_pages = max(1, ceil($total_filtered / $per_page));
  $reset_history = array_slice($filtered_history, $offset, $per_page);

  $page_title = 'Password Reset History';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #4a301f;">Password Reset History</h3>
          <p class="text-muted mb-0">Track all password reset activities in the system</p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-brown" id="exportBtn">
            <i class="fas fa-file-excel me-2"></i>Export to Excel
          </button>
          <a href="users.php" class="btn btn-brown">
            <i class="fas fa-arrow-left me-2"></i>Back to Users
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
            <i class="fas fa-history fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($total_resets); ?></h3>
          <p class="mb-0 opacity-75">Total Resets</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #4d3420 0%, #382417 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-calendar-day fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($today_resets); ?></h3>
          <p class="mb-0 opacity-75">Today</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #654529 0%, #4d3420 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-calendar-week fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($this_week_resets); ?></h3>
          <p class="mb-0 opacity-75">This Week</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #7d5633 0%, #654529 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-calendar-alt fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($this_month_resets); ?></h3>
          <p class="mb-0 opacity-75">This Month</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Search and Filter Card -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="fas fa-filter me-2 icon-brown"></i>Filter History
          </h5>
          <a href="password_reset_history.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-times me-1"></i>Clear Filters
          </a>
        </div>
        <div class="card-body">
          <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-search me-1"></i>Search Reset History
              </label>
              <input type="text"
                     class="form-control"
                     name="search"
                     value="<?php echo htmlspecialchars($search); ?>"
                     placeholder="Search by username, admin, or IP...">
            </div>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-user-shield me-1"></i>Reset By Admin
              </label>
              <select name="admin" class="form-select">
                <option value="">All Admins</option>
                <?php foreach ($admins as $admin): ?>
                  <option value="<?php echo htmlspecialchars($admin); ?>"
                          <?php echo $admin_filter === $admin ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($admin); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">
                <i class="fas fa-calendar me-1"></i>Time Period
              </label>
              <select name="period" class="form-select">
                <option value="">All Time</option>
                <option value="today" <?php echo $period_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="week" <?php echo $period_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                <option value="month" <?php echo $period_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
              </select>
            </div>
            <div class="col-md-3">
              <div class="btn-group w-100">
                <button type="submit" class="btn btn-brown">
                  <i class="fas fa-filter me-1"></i>Apply Filter
                </button>
                <a href="password_reset_history.php" class="btn btn-outline-secondary">
                  <i class="fas fa-times me-1"></i>Clear
                </a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Reset History Table -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="fas fa-list me-2 icon-brown"></i>Reset Activity Log
          </h5>
          <span class="badge bg-brown">
            <?php echo number_format($total_filtered); ?> Records
          </span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($reset_history)): ?>
            <div class="text-center py-5">
              <i class="fas fa-history fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No password reset records found</h5>
              <?php if (!empty($search) || !empty($admin_filter) || !empty($period_filter)): ?>
                <p class="text-muted mb-3">Try adjusting your search or filter criteria</p>
                <a href="password_reset_history.php" class="btn btn-outline-brown">Clear Filters</a>
              <?php else: ?>
                <p class="text-muted mb-0">Password reset activities will appear here</p>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="border-0">#</th>
                    <th class="border-0">User Account</th>
                    <th class="border-0">Default Password</th>
                    <th class="border-0">Reset By</th>
                    <th class="border-0">Date & Time</th>
                    <th class="border-0">IP Address</th>
                    <th class="border-0 text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $counter = $offset + 1;
                  foreach ($reset_history as $log):
                    $is_recent = strtotime($log['reset_date']) >= strtotime('-24 hours');
                  ?>
                  <tr class="<?php echo $is_recent ? 'table-row-recent' : ''; ?>">
                    <td class="align-middle">
                      <strong class="text-gray">#<?php echo str_pad($counter++, 3, '0', STR_PAD_LEFT); ?></strong>
                    </td>
                    <td class="align-middle">
                      <div class="d-flex align-items-center">
                        <div class="avatar-circle me-2">
                          <i class="fas fa-user"></i>
                        </div>
                        <strong><?php echo htmlspecialchars($log['user_username']); ?></strong>
                      </div>
                    </td>
                    <td class="align-middle">
                      <div class="password-container">
                        <code class="password-text blurred" data-password="<?php echo htmlspecialchars(base64_decode($log['default_password'])); ?>">
                          <?php echo str_repeat('•', 8); ?>
                        </code>
                        <button class="btn btn-sm btn-outline-secondary toggle-password" title="Show/Hide Password">
                          <i class="fas fa-eye"></i>
                        </button>
                      </div>
                    </td>
                    <td class="align-middle">
                      <span class="badge bg-brown">
                        <i class="fas fa-user-shield me-1"></i>
                        <?php echo htmlspecialchars($log['reset_by_username']); ?>
                      </span>
                    </td>
                    <td class="align-middle">
                      <div class="text-muted small">
                        <div class="mb-1">
                          <i class="fas fa-calendar me-1"></i>
                          <?php echo date('M j, Y', strtotime($log['reset_date'])); ?>
                        </div>
                        <div>
                          <i class="fas fa-clock me-1"></i>
                          <?php echo date('h:i:s A', strtotime($log['reset_date'])); ?>
                        </div>
                      </div>
                    </td>
                    <td class="align-middle text-muted small">
                      <i class="fas fa-network-wired me-1"></i>
                      <?php echo htmlspecialchars($log['ip_address']); ?>
                    </td>
                    <td class="align-middle text-center">
                      <?php if ($is_recent): ?>
                        <span class="badge bg-success">
                          <i class="fas fa-clock me-1"></i>Recent
                        </span>
                      <?php else: ?>
                        <span class="badge bg-secondary">
                          <i class="fas fa-check me-1"></i>Completed
                        </span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Enhanced Pagination -->
        <?php if ($total_pages > 1 && !empty($reset_history)): ?>
        <div class="card-footer bg-light">
          <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
              <p class="text-muted mb-0 small">
                Showing <?php echo number_format($offset + 1); ?> to
                <?php echo number_format(min($offset + $per_page, $total_filtered)); ?> of
                <?php echo number_format($total_filtered); ?> records
              </p>
            </div>
            <div class="col-md-6">
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm justify-content-md-end justify-content-center mb-0">
                  <!-- First Page -->
                  <?php if ($page > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&admin=<?php echo urlencode($admin_filter); ?>&period=<?php echo urlencode($period_filter); ?>" title="First Page">
                      <i class="fas fa-angle-double-left"></i>
                    </a>
                  </li>
                  <?php endif; ?>

                  <!-- Previous Page -->
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search); ?>&admin=<?php echo urlencode($admin_filter); ?>&period=<?php echo urlencode($period_filter); ?>" <?php echo $page <= 1 ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-left"></i>
                    </a>
                  </li>

                  <!-- Page Numbers -->
                  <?php
                  $start_page = max(1, $page - 2);
                  $end_page = min($total_pages, $page + 2);

                  if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&admin=' . urlencode($admin_filter) . '&period=' . urlencode($period_filter) . '">1</a></li>';
                    if ($start_page > 2) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                  }

                  for ($i = $start_page; $i <= $end_page; $i++):
                  ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                      <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&admin=<?php echo urlencode($admin_filter); ?>&period=<?php echo urlencode($period_filter); ?>">
                        <?php echo $i; ?>
                      </a>
                    </li>
                  <?php
                  endfor;

                  if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&admin=' . urlencode($admin_filter) . '&period=' . urlencode($period_filter) . '">' . $total_pages . '</a></li>';
                  }
                  ?>

                  <!-- Next Page -->
                  <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search); ?>&admin=<?php echo urlencode($admin_filter); ?>&period=<?php echo urlencode($period_filter); ?>" <?php echo $page >= $total_pages ? 'tabindex="-1"' : ''; ?>>
                      <i class="fas fa-angle-right"></i>
                    </a>
                  </li>

                  <!-- Last Page -->
                  <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&admin=<?php echo urlencode($admin_filter); ?>&period=<?php echo urlencode($period_filter); ?>" title="Last Page">
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

.text-gray {
  color: #595C5F;
}

.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
  box-shadow: 0 0.5rem 1rem rgba(74, 48, 31, 0.15);
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

.table-row-recent {
  background-color: #fff3cd !important;
}

.table-row-recent:hover {
  background-color: #ffe69c !important;
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

.password-container {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.password-text {
  background-color: #f8f9fa;
  padding: 0.375rem 0.75rem;
  border-radius: 0.25rem;
  font-family: 'Courier New', monospace;
  font-size: 0.875rem;
  transition: all 0.3s ease;
  min-width: 100px;
  display: inline-block;
}

.password-text.blurred {
  filter: blur(5px);
  user-select: none;
}

.password-text.revealed {
  filter: none;
  background-color: #fff3e0;
  color: #7d5633;
  font-weight: 600;
}

.toggle-password {
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  transition: all 0.2s;
}

.toggle-password:hover {
  background-color: #382417;
  border-color: #382417;
  color: white;
}

.toggle-password.active {
  background-color: #382417;
  border-color: #382417;
  color: white;
}

.badge {
  font-weight: 500;
  padding: 0.35rem 0.65rem;
}

.form-label {
  font-weight: 500;
  color: #495057;
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

@media (max-width: 768px) {
  .table {
    font-size: 0.875rem;
  }

  .avatar-circle {
    width: 30px;
    height: 30px;
    font-size: 0.75rem;
  }

  .password-container {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const passwordText = this.previousElementSibling;
            const password = passwordText.dataset.password;
            const icon = this.querySelector('i');

            if (passwordText.classList.contains('blurred')) {
                // Show password
                passwordText.classList.remove('blurred');
                passwordText.classList.add('revealed');
                passwordText.textContent = password;
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                this.classList.add('active');
            } else {
                // Hide password
                passwordText.classList.remove('revealed');
                passwordText.classList.add('blurred');
                passwordText.textContent = '••••••••';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                this.classList.remove('active');
            }
        });
    });

    // Export to Excel
    document.getElementById('exportBtn').addEventListener('click', function() {
        const btn = this;
        const originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Exporting...';

        // Prepare data for export
        const exportData = [
            ['#', 'User Account', 'Default Password', 'Reset By', 'Date', 'Time', 'IP Address', 'Status']
        ];

        document.querySelectorAll('table tbody tr').forEach((row, index) => {
            if (!row.querySelector('td')) return;

            const cells = row.querySelectorAll('td');
            const num = cells[0].textContent.trim();
            const username = cells[1].querySelector('strong').textContent.trim();
            const password = cells[2].querySelector('.password-text').dataset.password;
            const resetBy = cells[3].querySelector('.badge').textContent.trim();
            const dateTimeDiv = cells[4].querySelectorAll('div div');
            const date = dateTimeDiv[0] ? dateTimeDiv[0].textContent.replace(/\s+/g, ' ').trim().replace(/^.*?\s/, '') : '';
            const time = dateTimeDiv[1] ? dateTimeDiv[1].textContent.replace(/\s+/g, ' ').trim().replace(/^.*?\s/, '') : '';
            const ipAddress = cells[5].textContent.replace(/\s+/g, ' ').trim().replace(/^.*?\s/, '');
            const status = cells[6].querySelector('.badge').textContent.trim();

            exportData.push([
                num,
                username,
                password || '',
                resetBy,
                date,
                time,
                ipAddress,
                status
            ]);
        });

        // Create workbook and worksheet
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(exportData);

        // Set column widths
        ws['!cols'] = [
            { wch: 5 },  // #
            { wch: 20 }, // User Account
            { wch: 15 }, // Password
            { wch: 20 }, // Reset By
            { wch: 15 }, // Date
            { wch: 15 }, // Time
            { wch: 20 }, // IP Address
            { wch: 12 }  // Status
        ];

        // Add worksheet to workbook
        XLSX.utils.book_append_sheet(wb, ws, 'Password Resets');

        // Generate filename with current date
        const filename = `Password_Reset_History_${new Date().toISOString().split('T')[0]}.xlsx`;

        // Save file
        XLSX.writeFile(wb, filename);

        // Reset button
        setTimeout(function() {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            showAlert('success', 'Export completed successfully!');
        }, 500);
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
        document.body.insertAdjacentHTML('beforeend', alertHtml);
        setTimeout(function() {
            document.querySelector('.alert').remove();
        }, 3000);
    }
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
