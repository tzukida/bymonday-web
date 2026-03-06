<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireAdmin();

  $conn = getDBConnection();

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $backupSQL = "-- Database Backup: " . DB_NAME . "\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
    $tables = [];
    $resultTables = $conn->query("SHOW TABLES");
    while ($row = $resultTables->fetch_array()) {
        $tables[] = $row[0];
    }
    foreach ($tables as $table) {
        $row = $conn->query("SHOW CREATE TABLE `$table`")->fetch_assoc();
        $backupSQL .= "-- Table structure for `$table`\n";
        $backupSQL .= $row['Create Table'] . ";\n\n";
        $resultData = $conn->query("SELECT * FROM `$table`");
        while ($data = $resultData->fetch_assoc()) {
            $columns = array_map(function($col){ return "`$col`"; }, array_keys($data));
            $values  = array_map(function($val) use ($conn) {
                if (is_null($val)) return "NULL";
                return "'" . $conn->real_escape_string($val) . "'";
            }, array_values($data));
            $backupSQL .= "INSERT INTO `$table` (" . implode(",", $columns) . ") VALUES (" . implode(",", $values) . ");\n";
        }
        $backupSQL .= "\n";
    }
    $filename = 'inventory_backup_' . date('Ymd_His') . '.sql';

    logActivity($_SESSION['user_id'], 'Database Backup', 'Created database backup: ' . $filename);

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($backupSQL));
    echo $backupSQL;
    exit;
  }

  $db_stats = [];
  $resultTables = $conn->query("SHOW TABLES");
  while ($row = $resultTables->fetch_array()) {
    $table = $row[0];
    $result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
    if ($result) {
      $count_row = $result->fetch_assoc();
      $db_stats[$table] = $count_row['count'];
    }
  }

  $db_size_query = $conn->query("
    SELECT
      ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
    FROM information_schema.TABLES
    WHERE table_schema = '" . DB_NAME . "'
  ");
  $db_size = $db_size_query->fetch_assoc()['size_mb'];

  $last_backup_stmt = $conn->prepare("
    SELECT a.created_at, u.username
    FROM activity_log a
    JOIN users u ON a.user_id = u.id
    WHERE a.action = 'Database Backup'
    ORDER BY a.created_at DESC
    LIMIT 1
  ");
  $last_backup_stmt->execute();
  $last_backup_result = $last_backup_stmt->get_result();
  $last_backup = $last_backup_result->fetch_assoc();
  $last_backup_stmt->close();

  $backup_history_stmt = $conn->prepare("
    SELECT a.created_at, a.details, u.username
    FROM activity_log a
    JOIN users u ON a.user_id = u.id
    WHERE a.action = 'Database Backup'
    ORDER BY a.created_at DESC
    LIMIT 10
  ");
  $backup_history_stmt->execute();
  $backup_history = $backup_history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $backup_history_stmt->close();

  $page_title = 'Database Backup';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
  <!-- Page Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #3b2008;">Database Backup</h3>
          <p class="text-muted mb-0">Create and manage your database backups</p>
        </div>
        <div>
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
            <i class="fas fa-database fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo $db_size; ?> MB</h3>
          <p class="mb-0 opacity-75">Database Size</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #6b3a1f 0%, #3d1c02 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-table fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo count($db_stats); ?></h3>
          <p class="mb-0 opacity-75">Total Tables</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #5a2d00 0%, #3d1c02 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-server fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format(array_sum($db_stats)); ?></h3>
          <p class="mb-0 opacity-75">Total Records</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #c87533 0%, #a05a20 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-clock fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1" style="font-size: 1.3rem;">
            <?php
              if ($last_backup) {
                $days_ago = floor((time() - strtotime($last_backup['created_at'])) / 86400);
                echo $days_ago == 0 ? 'Today' : ($days_ago == 1 ? '1 day ago' : $days_ago . ' days ago');
              } else {
                echo 'Never';
              }
            ?>
          </h3>
          <p class="mb-0 opacity-75">Last Backup</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Backup Generator Card -->
    <div class="col-lg-8">
      <div class="card h-100" style="border-color: #6b3a1f !important;">
        <div class="card-header text-white" style="background: linear-gradient(135deg, #6b3a1f 0%, #3d1c02 100%);">
          <h5 class="mb-0">
            <i class="fas fa-download me-2"></i>Generate New Backup
          </h5>
        </div>
        <div class="card-body d-flex flex-column">
          <div class="row flex-grow-1">
            <div class="col-md-7 d-flex flex-column">
              <div class="alert alert-info mb-4 htable">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Backup Information</strong>
                <ul class="mt-2">
                  <li>Creates a complete SQL dump of all tables</li>
                  <li>Includes all data, structure, and relationships</li>
                  <li>File format: SQL (.sql)</li>
                  <li>Recommended frequency: Daily or before major changes</li>
                </ul>
              </div>

              <div class="mt-8">
                <form method="POST" onsubmit="return confirmBackup();">
                  <div class="d-grid">
                    <button type="submit" class="btn btn-lg text-white" style="background-color: #6b3a1f; border-color: #6b3a1f;">
                      <i class="fas fa-download me-2"></i>Generate Backup Now
                    </button>
                  </div>
                </form>

                <?php if ($last_backup): ?>
                <div class="mt-3 pt-3 border-top text-center">
                  <small class="text-muted">
                    <i class="fas fa-history me-1"></i>
                    Last backup by <strong><?php echo htmlspecialchars($last_backup['username']); ?></strong>
                    on <?php echo date('M j, Y \a\t g:i A', strtotime($last_backup['created_at'])); ?>
                  </small>
                </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="col-md-5 d-flex flex-column">
              <h6 class="text-muted mb-3"><i class="fas fa-list me-2"></i>Tables to backup:</h6>
              <div class="backup-tables-list flex-grow-1">
                <?php foreach ($db_stats as $table => $count): ?>
                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded mb-2">
                  <span class="text-capitalize small">
                    <i class="fas fa-table text-primary me-2" style="font-size: 0.75rem;"></i>
                    <?php echo str_replace('_', ' ', $table); ?>
                  </span>
                  <span class="badge bg-secondary"><?php echo number_format($count); ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Best Practices Card -->
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header bg-white">
          <h5 class="mb-0">
            <i class="fas fa-lightbulb me-2 icon-red"></i>Best Practices
          </h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <div class="d-flex mb-3">
              <div class="me-3">
                <div class="bg-success bg-opacity-10 rounded p-2">
                  <i class="fas fa-check-circle text-success fa-lg"></i>
                </div>
              </div>
              <div>
                <h6 class="mb-1">Regular Schedule</h6>
                <small class="text-muted">Create backups daily or before major updates</small>
              </div>
            </div>
            <div class="d-flex mb-3">
              <div class="me-3">
                <div class="bg-primary bg-opacity-10 rounded p-2">
                  <i class="fas fa-shield-alt text-primary fa-lg"></i>
                </div>
              </div>
              <div>
                <h6 class="mb-1">Secure Storage</h6>
                <small class="text-muted">Store backups in a secure, separate location</small>
              </div>
            </div>
            <div class="d-flex mb-3">
              <div class="me-3">
                <div class="bg-warning bg-opacity-10 rounded p-2">
                  <i class="fas fa-vial text-warning fa-lg"></i>
                </div>
              </div>
              <div>
                <h6 class="mb-1">Test Restores</h6>
                <small class="text-muted">Periodically verify backup integrity</small>
              </div>
            </div>
            <div class="d-flex">
              <div class="me-3">
                <div class="bg-info bg-opacity-10 rounded p-2">
                  <i class="fas fa-copy text-info fa-lg"></i>
                </div>
              </div>
              <div>
                <h6 class="mb-1">Multiple Copies</h6>
                <small class="text-muted">Keep multiple backup versions</small>
              </div>
            </div>
          </div>

          <div class="alert alert-warning mb-0">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <small><strong>Important:</strong> Download and store backups immediately. This system does not automatically save backups to the server.</small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Backup History -->
  <div class="row mt-4">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-white">
          <h5 class="mb-0">
            <i class="fas fa-history me-2 icon-brown"></i>Recent Backup History
          </h5>
        </div>
        <div class="card-body p-0">
          <?php if (empty($backup_history)): ?>
            <div class="text-center py-5">
              <i class="fas fa-file-archive fa-4x text-muted mb-3"></i>
              <h5 class="text-muted">No backups yet</h5>
              <p class="text-muted mb-0">Generate your first backup to see history</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="border-0">#</th>
                    <th class="border-0">Backup File</th>
                    <th class="border-0">Created By</th>
                    <th class="border-0">Date & Time</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($backup_history as $index => $backup): ?>
                  <tr>
                    <td class="text-muted small">
                      <strong><?php echo $index + 1; ?></strong>
                    </td>
                    <td>
                      <i class="fas fa-file-archive me-2" style="color: #c87533;"></i>
                      <strong><?php echo htmlspecialchars(str_replace('Created database backup: ', '', $backup['details'])); ?></strong>
                    </td>
                    <td>
                      <span class="badge bg-info text-white">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($backup['username']); ?>
                      </span>
                    </td>
                    <td class="text-muted small">
                      <div>
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('M j, Y', strtotime($backup['created_at'])); ?>
                      </div>
                      <div>
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('h:i A', strtotime($backup['created_at'])); ?>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
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

.btn-success {
  background-color: #6b3a1f;
  border-color: #6b3a1f;
}

.btn-success:hover {
  background-color: #3d1c02;
  border-color: #3d1c02;
}

code {
  background-color: #f8f9fa;
  padding: 0.2rem 0.4rem;
  border-radius: 0.25rem;
  color: #d63384;
}

.backup-tables-list {
  overflow-y: auto;
  padding-right: 5px;
  height:100px;
}

.backup-tables-list::-webkit-scrollbar {
  width: 6px;

}

.backup-tables-list::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

.backup-tables-list::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 10px;
}

.backup-tables-list::-webkit-scrollbar-thumb:hover {
  background: #555;
}

.htable {
  height: 300px;
}

@media (max-width: 768px) {
  .card-body h3 {
    font-size: 1.5rem;
  }

  .table {
    font-size: 0.875rem;
  }
}
</style>

<script>
function confirmBackup() {
  return confirm('Generate database backup?\n\nThis will create a downloadable SQL file containing all your data.\n\nClick OK to proceed.');
}
<?php if (isset($_GET['backup_success'])): ?>
  setTimeout(function() {
    alert('Backup generated successfully!\nPlease store it in a secure location.');
  }, 500);
<?php endif; ?>
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
