<?php
  define('BASE_PATH', dirname(__DIR__));
  $page_title = 'Staff Dashboard';
  require_once BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  require_once BASE_PATH . '/includes/functions.php';

  requireStaff();

  $staff_id = $_SESSION['user_id'];
  logActivity($staff_id, 'Visit Dashboard', 'Staff accessed the dashboard page');

  $conn = getDBConnection();
  if (!$conn) {
    die("Database connection failed");
  }

  // Get current user info
  $current_user = getUserById($staff_id);

  // Basic inventory stats
  $result = $conn->query("SELECT COUNT(*) AS total FROM inventory");
  $total_items = $result ? $result->fetch_assoc()['total'] : 0;

  // Staff transactions
  $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM transactions WHERE user_id = ?");
  $stmt->bind_param("i", $staff_id);
  $stmt->execute();
  $staff_transactions = $stmt->get_result()->fetch_assoc()['total'];
  $stmt->close();

  // Today's transactions
  $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM transactions WHERE user_id = ? AND DATE(timestamp) = CURDATE()");
  $stmt->bind_param("i", $staff_id);
  $stmt->execute();
  $today_transactions = $stmt->get_result()->fetch_assoc()['total'];
  $stmt->close();

  // Week's transactions
  $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM transactions WHERE user_id = ? AND YEARWEEK(timestamp, 1) = YEARWEEK(CURDATE(), 1)");
  $stmt->bind_param("i", $staff_id);
  $stmt->execute();
  $week_transactions = $stmt->get_result()->fetch_assoc()['total'];
  $stmt->close();

  // Sales stats
  $stmt = $conn->prepare("SELECT COUNT(*) as count, IFNULL(SUM(total_amount), 0) as total FROM sales WHERE user_id = ?");
  $stmt->bind_param("i", $staff_id);
  $stmt->execute();
  $sales_result = $stmt->get_result()->fetch_assoc();
  $my_sales_count = $sales_result['count'];
  $my_sales_total = $sales_result['total'];
  $stmt->close();

  // Today's sales
  $stmt = $conn->prepare("
    SELECT
      COUNT(*) as count,
      IFNULL(SUM(total_amount), 0) as total
    FROM sales
    WHERE user_id = ?
    AND DATE(sale_date) = CURDATE()
  ");
  $stmt->bind_param("i", $staff_id);
  $stmt->execute();
  $today_sales = $stmt->get_result()->fetch_assoc();
  $today_sales_count = (int)$today_sales['count'];
  $today_sales_total = (float)$today_sales['total'];
  $stmt->close();

  // Low stock items
  $low_stock_items = getLowStockItems();
  $low_stock_count = count($low_stock_items);

  // Recent sales
  $stmt = $conn->prepare("
    SELECT s.*, COUNT(si.id) as item_count
    FROM sales s
    LEFT JOIN sales_items si ON s.id = si.sale_id
    WHERE s.user_id = ?
    GROUP BY s.id
    ORDER BY s.sale_date DESC
    LIMIT 5
  ");
  $stmt->bind_param("i", $staff_id);
  $stmt->execute();
  $recent_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // Calculate averages
  $account_age_days = max(1, floor((time() - strtotime($current_user['created_at'])) / 86400));
  $avg_transactions_per_day = $staff_transactions > 0 ? round($staff_transactions / $account_age_days, 1) : 0;
  $avg_sales_per_day = $my_sales_count > 0 ? round($my_sales_count / $account_age_days, 1) : 0;

  // Get chart data
  $sales_chart_data = [];
  $trans_chart_data = [];
  $has_sales_data = false;
  $has_trans_data = false;

  for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));

    // Sales data
    $stmt = $conn->prepare("SELECT COUNT(*) as count, IFNULL(SUM(total_amount), 0) as revenue FROM sales WHERE user_id = ? AND DATE(sale_date) = ?");
    $stmt->bind_param("is", $staff_id, $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $sales_count = (int)$row['count'];
    $sales_chart_data[] = [
      'date' => date('M j', strtotime($date)),
      'count' => $sales_count,
      'revenue' => (float)$row['revenue']
    ];
    if ($sales_count > 0) $has_sales_data = true;
    $stmt->close();

    // Transaction data
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transactions WHERE user_id = ? AND DATE(timestamp) = ?");
    $stmt->bind_param("is", $staff_id, $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $trans_count = (int)$row['count'];
    $trans_chart_data[] = [
      'date' => date('M j', strtotime($date)),
      'count' => $trans_count
    ];
    if ($trans_count > 0) $has_trans_data = true;
    $stmt->close();
  }

  require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="welcome-card">
                <div class="d-flex justify-content-between align-items-start flex-wrap">
                    <div class="mb-3 mb-md-0">
                        <h1 class="h2 mb-1 text-brown">
                            <i class="fas fa-user-circle me-2"></i>Welcome back, <?php echo htmlspecialchars($current_user['username']); ?>!
                        </h1>
                        <p class="text-muted mb-2">Here's what's happening with your account today</p>
                        <div class="d-flex gap-3 flex-wrap small">
                            <span class="badge bg-brown-soft">
                                <i class="fas fa-calendar me-1"></i><?php echo date('l, F j, Y'); ?>
                            </span>
                            <span class="badge bg-success-soft">
                                <i class="fas fa-clock me-1"></i><?php echo date('g:i A'); ?>
                            </span>
                            <span class="badge bg-warning-soft">
                                <i class="fas fa-user-tag me-1"></i>Staff Member
                            </span>
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="avatar-xl">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stats-card stats-card-brown h-100">
                <div class="stats-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stats-content">
                    <h3 class="mb-0"><?php echo number_format($my_sales_count); ?></h3>
                    <p class="mb-1">My Total Sales</p>
                    <small class="opacity-75">₱<?php echo number_format($my_sales_total, 2); ?> revenue</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stats-card stats-card-success h-100">
                <div class="stats-icon">
                    <i class="fas fa-cash-register"></i>
                </div>
                <div class="stats-content">
                    <h3 class="mb-0"><?php echo number_format($today_sales_count); ?></h3>
                    <p class="mb-1">Today's Sales</p>
                    <small class="opacity-75">₱<?php echo number_format($today_sales_total, 2); ?> revenue</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stats-card stats-card-warning h-100">
                <div class="stats-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stats-content">
                    <h3 class="mb-0"><?php echo number_format($staff_transactions); ?></h3>
                    <p class="mb-1">My Transactions</p>
                    <small class="opacity-75"><?php echo $today_transactions; ?> today</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stats-card stats-card-danger h-100">
                <div class="stats-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stats-content">
                    <h3 class="mb-0"><?php echo number_format($low_stock_count); ?></h3>
                    <p class="mb-1">Low Stock Alert</p>
                    <small class="opacity-75" style="font-size: 0.75rem;">Need restocking</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts & Quick Actions Row -->
    <div class="row g-4 mb-4">
        <!-- Activity Chart -->
        <div class="col-lg-8">
            <div class="card h-100 d-flex flex-column">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2 icon-brown"></i>
                            My Activity (Last 7 Days)
                        </h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-brown active" id="salesChartBtn" onclick="switchChart('sales')">
                                <i class="fas fa-shopping-cart me-1"></i>Sales
                            </button>
                            <button type="button" class="btn btn-outline-brown" id="transactionsChartBtn" onclick="switchChart('transactions')">
                                <i class="fas fa-exchange-alt me-1"></i>Transactions
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body flex-grow-1 d-flex flex-column p-3">
                    <?php if (!$has_sales_data && !$has_trans_data): ?>
                        <div class="text-center py-5 flex-grow-1 d-flex flex-column justify-content-center">
                            <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No Activity Data</h5>
                            <p class="text-muted mb-3">Start making sales or transactions to see your activity chart</p>
                            <a href="<?php echo getBaseURL(); ?>/staff_pos.php" class="btn btn-brown">
                                <i class="fas fa-cash-register me-1"></i>Make Your First Sale
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="chart-container flex-grow-1">
                            <canvas id="activityChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Alerts -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2 icon-brown"></i>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?php echo getBaseURL(); ?>/staff_pos.php" class="btn btn-brown btn-lg">
                            <i class="fas fa-cash-register me-2"></i>Point of Sale
                        </a>
                        <a href="<?php echo getBaseURL(); ?>/staff_inventory.php" class="btn btn-outline-brown">
                            <i class="fas fa-boxes me-2"></i>View Inventory
                        </a>
                        <a href="<?php echo getBaseURL(); ?>/staff_menu_view.php" class="btn btn-outline-brown">
                            <i class="fas fa-utensils me-2"></i>View Menu
                        </a>
                        <a href="<?php echo getBaseURL(); ?>/my_transactions.php" class="btn btn-outline-secondary">
                            <i class="fas fa-history me-2"></i>My History
                        </a>
                    </div>

                    <?php if ($low_stock_count > 0): ?>
                    <div class="alert alert-warning-brown mt-3 mb-0 py-2">
                        <div class="d-flex align-items-center mb-1">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong class="small">Low Stock Alert!</strong>
                        </div>
                        <p class="mb-1 small">
                            <strong><?php echo $low_stock_count; ?> items</strong> need restocking.
                        </p>
                        <small class="text-muted" style="font-size: 0.75rem;">Inform admin about restocking needs.</small>
                    </div>
                    <?php endif; ?>

                    <!-- Performance Badge -->
                    <div class="card mt-3 border-0 performance-badge">
                        <div class="card-body text-center">
                            <i class="fas fa-trophy fa-2x icon-brown mb-2"></i>
                            <h6 class="mb-1 text-brown">Your Performance</h6>
                            <p class="text-muted small mb-0">
                                Avg. <?php echo $avg_sales_per_day; ?> sales/day
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Sales & Low Stock Row -->
    <div class="row g-4 mb-4">
        <!-- Recent Sales -->
        <div class="col-lg-<?php echo $low_stock_count > 0 ? '6' : '12'; ?>">
            <div class="card h-100">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-receipt me-2 icon-brown"></i>
                            Recent Sales
                        </h5>
                        <a href="<?php echo getBaseURL(); ?>/my_transactions.php" class="btn btn-sm btn-outline-brown">
                            View All
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recent_sales)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_sales as $sale): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="badge bg-brown me-2">
                                            Sale #<?php echo str_pad($sale['id'], 4, '0', STR_PAD_LEFT); ?>
                                        </span>
                                        <small class="text-muted">
                                            <?php echo $sale['item_count']; ?> item(s)
                                        </small>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo formatDate($sale['sale_date'], 'M j, Y g:i A'); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <h6 class="mb-0 text-brown">
                                        ₱<?php echo number_format($sale['total_amount'], 2); ?>
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-3">No sales yet</p>
                        <a href="<?php echo getBaseURL(); ?>/staff_pos.php" class="btn btn-brown">
                            <i class="fas fa-cash-register me-1"></i>Make Your First Sale
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Low Stock Items -->
        <?php if ($low_stock_count > 0): ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header border-bottom low-stock-header">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Low Stock Items
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0">Item Name</th>
                                    <th class="border-0 text-center">Stock</th>
                                    <th class="border-0 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($low_stock_items, 0, 5) as $item): ?>
                                <tr>
                                    <td class="fw-semibold">
                                        <i class="fas fa-box me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge" style="background-color: #6b3a1f;">
                                            <?php echo $item['quantity'] . ' ' . htmlspecialchars($item['unit']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-danger small">
                                            <i class="fas fa-exclamation-circle me-1"></i>Critical
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-top">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Contact admin to restock these items immediately
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Performance Summary -->
    <?php if ($staff_transactions > 0 || $my_sales_count > 0): ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2 icon-brown"></i>
                        My Performance Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center g-4">
                        <div class="col-md-3">
                            <div class="performance-stat">
                                <div class="stat-icon bg-brown-soft">
                                    <i class="fas fa-shopping-cart text-brown"></i>
                                </div>
                                <h4 class="mt-3 mb-1 text-brown"><?php echo number_format($my_sales_count); ?></h4>
                                <p class="text-muted mb-0">Total Sales</p>
                                <small class="text-success">₱<?php echo number_format($my_sales_total, 2); ?></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="performance-stat">
                                <div class="stat-icon bg-success-soft">
                                    <i class="fas fa-calendar-week icon-brown"></i>
                                </div>
                                <h4 class="mt-3 mb-1 text-brown"><?php echo number_format($week_transactions); ?></h4>
                                <p class="text-muted mb-0">This Week</p>
                                <small class="text-muted">Transactions</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="performance-stat">
                                <div class="stat-icon bg-warning-soft">
                                    <i class="fas fa-calendar-day icon-brown"></i>
                                </div>
                                <h4 class="mt-3 mb-1 text-brown"><?php echo number_format($today_transactions); ?></h4>
                                <p class="text-muted mb-0">Today</p>
                                <small class="text-muted">Transactions</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="performance-stat">
                                <div class="stat-icon bg-info-soft">
                                    <i class="fas fa-chart-line icon-brown"></i>
                                </div>
                                <h4 class="mt-3 mb-1 text-brown"><?php echo $avg_sales_per_day; ?></h4>
                                <p class="text-muted mb-0">Daily Average</p>
                                <small class="text-muted">Sales per day</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
body {
  background: linear-gradient(135deg, #f5f0eb 0%, #e8ddd4 100%);
  min-height: 100vh;
}

.text-brown {
  color: #4a301f !important;
}

.bg-brown {
  background-color: #6b3a1f;
  color: #fff;
}

.icon-brown {
  color: #4a301f;
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

.btn-outline-brown.active {
  background-color: #382417;
  border-color: #382417;
  color: white;
}

.welcome-card {
    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
}

.avatar-xl {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4a301f 0%, #382417 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    box-shadow: 0 4px 6px rgba(74, 48, 31, 0.2);
}

.stats-card {
    border-radius: 12px;
    padding: 1.5rem;
    color: white;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: transform 0.2s;
    display: flex;
    align-items: center;
    gap: 1rem;
    height: 100%;
}

.stats-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

.stats-card-brown {
    background: linear-gradient(135deg, #4a301f 0%, #382417 100%);
}

.stats-card-success {
    background: linear-gradient(135deg, #6b3a1f 0%, #3d1c02 100%);
}

.stats-card-warning {
    background: linear-gradient(135deg, #5a2d00 0%, #3d1c02 100%);
}

.stats-card-danger {
    background: linear-gradient(135deg, #c87533 0%, #a05a20 100%);
}

.stats-icon {
    width: 60px;
    height: 60px;
    background: rgba(255,255,255,0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    flex-shrink: 0;
}

.stats-content h3 {
    font-size: 2rem;
    font-weight: 700;
}

.stats-content p {
    opacity: 0.9;
    margin-bottom: 0;
}

.badge.bg-brown-soft {
    background-color: rgba(74, 48, 31, 0.1);
    color: #4a301f;
}

.badge.bg-success-soft {
    background-color: rgba(107, 58, 31, 0.1);
    color: #6b3a1f;
}

.badge.bg-warning-soft {
    background-color: rgba(255, 193, 7, 0.1);
    color: #856404;
}

.performance-badge {
    background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
}

.performance-stat {
    padding: 1rem;
    border-radius: 8px;
    transition: all 0.3s;
}

.performance-stat:hover {
    background-color: #fff3e0;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.bg-brown-soft {
    background-color: rgba(74, 48, 31, 0.1);
}

.bg-success-soft {
    background-color: rgba(107, 58, 31, 0.1);
}

.bg-warning-soft {
    background-color: rgba(200, 117, 51, 0.1);
}

.bg-info-soft {
    background-color: rgba(90, 45, 0, 0.1);
}

.card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
    border-radius: 12px;
}

.card-header {
    border-radius: 12px 12px 0 0 !important;
}

.low-stock-header {
    background: linear-gradient(135deg, #6b3a1f 0%, #3d1c02 100%);
    color: white;
}

.alert-warning-brown {
    background-color: #fff8e1;
    border: 1px solid #ffd54f;
    color: #4a301f;
    border-radius: 0.5rem;
}

.chart-container {
    position: relative;
    min-height: 300px;
    height: 100%;
    width: 100%;
}

.chart-container canvas {
    position: absolute !important;
    top: 0;
    left: 0;
    width: 100% !important;
    height: 100% !important;
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

@media (max-width: 768px) {
    .welcome-card {
        padding: 1.5rem;
    }

    .stats-card {
        padding: 1rem;
    }

    .stats-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }

    .stats-content h3 {
        font-size: 1.5rem;
    }
}
</style>

<script>
<?php if ($has_sales_data || $has_trans_data): ?>
let currentChart = null;

const salesData = <?php echo json_encode($sales_chart_data); ?>;
const transData = <?php echo json_encode($trans_chart_data); ?>;

function createChart(type) {
    const canvas = document.getElementById('activityChart');
    if (!canvas) {
        console.error('Canvas element not found');
        return;
    }

    if (currentChart) {
        currentChart.destroy();
    }

    const data = type === 'sales' ? salesData : transData;
    const color = type === 'sales' ? '#4a301f' : '#382417';
    const label = type === 'sales' ? 'Sales Count' : 'Transactions';

    currentChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.map(d => d.date),
            datasets: [{
                label: label,
                data: data.map(d => d.count),
                backgroundColor: color,
                borderRadius: 6,
                maxBarThickness: 60
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    padding: 12,
                    titleColor: 'white',
                    bodyColor: 'white',
                    displayColors: true,
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += context.parsed.y;
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        color: '#6c757d',
                        precision: 0,
                        padding: 10
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)',
                        drawBorder: false
                    }
                },
                x: {
                    ticks: {
                        color: '#6c757d',
                        padding: 10
                    },
                    grid: {
                        display: false
                    }
                }
            },
            layout: {
                padding: {
                    top: 5,
                    right: 10,
                    bottom: 5,
                    left: 10
                }
            }
        }
    });
}

function switchChart(type) {
    document.getElementById('salesChartBtn').classList.toggle('active', type === 'sales');
    document.getElementById('transactionsChartBtn').classList.toggle('active', type === 'transactions');
    createChart(type);
}

// Initialize chart when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart !== 'undefined') {
        setTimeout(function() {
            createChart('sales');
        }, 200);
    } else {
        console.error('Chart.js not loaded');
    }
});

// Also try to initialize after window load
window.addEventListener('load', function() {
    if (typeof Chart !== 'undefined' && !currentChart) {
        setTimeout(function() {
            createChart('sales');
        }, 100);
    }
});
<?php endif; ?>

// Auto-refresh every 5 minutes
setInterval(function() {
    if (!document.hidden) {
        location.reload();
    }
}, 300000);

// Notification for low stock
<?php if ($low_stock_count > 0 && $low_stock_count >= 5): ?>
setTimeout(function() {
    showNotification('warning', '<?php echo $low_stock_count; ?> items are critically low on stock!');
}, 2000);
<?php endif; ?>

function showNotification(type, message) {
    const alertClass = type === 'warning' ? 'alert-warning' : 'alert-info';
    const iconClass = type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';

    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
    notification.innerHTML = `
        <i class="fas ${iconClass} me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(notification);

    setTimeout(function() {
        notification.classList.remove('show');
        setTimeout(function() {
            notification.remove();
        }, 150);
    }, 5000);
}
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
