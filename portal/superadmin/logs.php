<?php
  define('BASE_PATH', dirname(__DIR__));
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';
  requireSuperAdmin();


  $conn = getDBConnection();

  $limit = 20;
  $page = max(1, (int)($_GET['page'] ?? 1));
  $offset = ($page - 1) * $limit;

  $count_stmt = $conn->prepare("SELECT COUNT(*) FROM logs");
  $count_stmt->execute();
  $count_stmt->bind_result($total);
  $count_stmt->fetch();
  $count_stmt->close();

  $stmt = $conn->prepare("
    SELECT l.created_at, u.username, l.action
    FROM logs l
    JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
  ");

  $stmt->bind_param("ii", $limit, $offset);
  $stmt->execute();
  $result = $stmt->get_result();

  $page_title = 'System Logs';
  require_once BASE_PATH . '/includes/header.php';
?>

<div class="row">
  <div class="col-12">
      <h2><i class="fas fa-file-alt"></i> System Logs</h2>
  </div>
</div>

<table class="table table-striped table-bordered">
  <thead>
      <tr>
          <th>Date & Time</th>
          <th>User</th>
          <th>Action</th>
      </tr>
  </thead>
  <tbody>
    <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="3" class="text-center">No logs found.</td></tr>
    <?php else: ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td><?php echo htmlspecialchars($row['username']); ?></td>
                <td><?php echo htmlspecialchars($row['action']); ?></td>
            </tr>
        <?php endwhile; ?>
    <?php endif; ?>
  </tbody>
</table>

<?php
  $total_pages = ceil($total / $limit);
  if ($total_pages > 1):
?>
<nav>
  <ul class="pagination justify-content-center">
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
      <li class="page-item <?php echo ($p === $page) ? 'active' : ''; ?>">
        <a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
