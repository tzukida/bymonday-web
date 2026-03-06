<?php
  define('BASE_PATH', dirname(__DIR__));
  $page_title = 'Dashboard';
  require_once  BASE_PATH . '/config/config.php';
  require_once BASE_PATH . '/includes/auth.php';
  requireAdmin();

  require_once BASE_PATH . '/includes/header.php';

  $user_role = isSuperAdmin() ? 'SuperAdmin' : 'Admin';
  logActivity($_SESSION['user_id'], 'Visit Dashboard', "$user_role accessed the dashboard page");

  $stats = getDashboardStatsWithPOS();
  $low_stock_items = getLowStockItems();

  $recent_sales = getSales(null, 10);
  $top_items = getTopSellingItems(5);

  $recent_inventory_activity = getTransactions(null, 10);
?>

<div class="container-fluid">
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="h3 mb-0" style="color: #3b2008;">
            <?php echo $user_role; ?> Dashboard
          </h3>
          <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($current_user['username']); ?></p>
        </div>
        <div class="text-muted">
          <i class="fas fa-calendar-alt me-1"></i>
          <?php echo date('l, F j, Y'); ?>
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
            <i class="fas fa-shopping-cart fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['today_sales']); ?></h3>
          <p class="mb-0 opacity-75">Today's Sales</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #5c3010 0%, #3b2008 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-peso-sign fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1">₱<?php echo number_format($stats['today_revenue'], 2); ?></h3>
          <p class="mb-0 opacity-75">Today's Revenue</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #7a4a28 0%, #5c3010 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-boxes fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['total_items']); ?></h3>
          <p class="mb-0 opacity-75">Inventory Items</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="card text-white h-100" style="background: linear-gradient(135deg, #a06535 0%, #7a4a28 100%);">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
          </div>
          <h3 class="mb-1"><?php echo number_format($stats['low_stock_count']); ?></h3>
          <p class="mb-0 opacity-75">Low Stock Items</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Charts Row -->
  <div class="row g-4">
    <!-- Sales Trend Chart -->
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <i class="fas fa-chart-line me-2 icon-brown"></i>
            Sales & Revenue Trends
          </h5>
          <span class="badge bg-brown">Last 7 Days</span>
        </div>
        <div class="card-body">
          <div style="position: relative; height: 350px;">
            <canvas id="salesChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Sales by Payment Method (Pie Chart) -->
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header bg-white">
          <h5 class="mb-0">
            <i class="fas fa-credit-card me-2 icon-brown"></i>
            Payment Methods
          </h5>
        </div>
        <div class="card-body">
          <div style="position: relative; height: 280px;">
            <canvas id="paymentChart"></canvas>
          </div>
          <div class="mt-3 pt-3 border-top">
            <div class="d-flex justify-content-between align-items-center">
              <small class="text-muted">Total Revenue</small>
              <strong class="text-success">₱<?php echo number_format($stats['total_revenue'], 2); ?></strong>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Second Row: Top Items and Recent Sales -->
  <div class="row g-4 mt-2">
    <!-- Top Selling Items -->
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header bg-white">
          <h5 class="mb-0">
            <i class="fas fa-trophy me-2 icon-brown"></i>
            Top Selling Items
          </h5>
        </div>
        <div class="card-body">
          <?php if (empty($top_items)): ?>
            <div class="text-center py-4 text-muted">
              <i class="fas fa-chart-bar fa-3x mb-3"></i>
              <p>No sales data yet</p>
              <a href="pos.php" class="btn btn-sm" style="background-color: #3b2008; color: white;">
                <i class="fas fa-cash-register me-1"></i>Start Selling
              </a>
            </div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php
              $rank_colors = [
                1 => 'bg-warning text-dark',
                2 => 'bg-secondary text-white',
                3 => 'bg-bronze text-white',
              ];
              foreach ($top_items as $index => $item):
                $rank = $index + 1;
                $badge_class = $rank_colors[$rank] ?? 'bg-light text-dark';
              ?>
                <div class="list-group-item px-0 border-0">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-start flex-grow-1">
                      <span class="badge rank-badge <?php echo $badge_class; ?> me-2">#<?php echo $rank; ?></span>
                      <div>
                        <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                        <small class="text-muted">
                          <i class="fas fa-box me-1"></i><?php echo number_format($item['total_sold']); ?> sold
                        </small>
                      </div>
                    </div>
                    <div class="text-end">
                      <strong class="text-success d-block">₱<?php echo number_format($item['total_revenue'], 2); ?></strong>
                    </div>
                  </div>
                  <?php if ($index < count($top_items) - 1): ?>
                    <hr class="my-2">
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="card-footer bg-white border-top">
          <a href="sales_report.php" class="text-decoration-none text-gray">
            <i class="fas fa-eye me-1"></i>View Full Report
          </a>
        </div>
      </div>
    </div>

    <!-- Recent Sales -->
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <i class="fas fa-history me-2 icon-brown"></i>
            Recent Sales
          </h5>
          <span class="badge bg-brown">
            <?php echo number_format($stats['total_sales']); ?> Total
          </span>
        </div>
        <div class="card-body p-0">
          <?php if (!empty($recent_sales)): ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="border-0">Sale ID</th>
                  <th class="border-0">Customer</th>
                  <th class="border-0 text-end">Amount</th>
                  <th class="border-0 text-center">Payment</th>
                  <th class="border-0 text-center">Staff</th>
                  <th class="border-0">Time</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent_sales as $sale):
                  $payment_icons = [
                    'cash' => 'fa-money-bill-wave',
                    'gcash' => 'fa-mobile-alt',
                    'card' => 'fa-credit-card'
                  ];
                  $payment_colors = [
                    'cash' => 'success',
                    'gcash' => 'primary',
                    'card' => 'warning'
                  ];
                  $method = strtolower($sale['payment_method']);
                  $icon = $payment_icons[$method] ?? 'fa-money-bill-wave';
                  $color = $payment_colors[$method] ?? 'secondary';
                ?>
                <tr>
                  <td>
                    <strong class="text-gray">#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                  </td>
                  <td>
                    <i class="fas fa-user me-1 text-muted" style="font-size: 0.75rem;"></i>
                    <?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in'); ?>
                  </td>
                  <td class="text-end">
                    <span class="fw-bold" style="color: #3b2008;">₱<?php echo number_format($sale['total_amount'], 2); ?></span>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-<?php echo $color; ?>">
                      <i class="fas <?php echo $icon; ?> me-1"></i>
                      <?php echo ucfirst($sale['payment_method']); ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-info text-white">
                      <?php echo htmlspecialchars($sale['username']); ?>
                    </span>
                  </td>
                  <td>
                    <div class="small text-muted">
                      <i class="fas fa-clock me-1"></i>
                      <?php echo formatDate($sale['sale_date'], 'M j, g:i A'); ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="text-center py-5">
            <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
            <p class="text-muted">No sales yet</p>
            <a href="pos.php" class="btn" style="background-color: #3b2008; color: white;">
              <i class="fas fa-cash-register me-1"></i>Make First Sale
            </a>
          </div>
          <?php endif; ?>
        </div>
        <div class="card-footer bg-white border-top">
          <a href="sales_report.php" class="text-decoration-none text-gray">
            <i class="fas fa-eye me-1"></i>View All Sales History
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Third Row: Low Stock Alert and Inventory Activity -->
  <div class="row g-4 mt-2">
    <!-- Low Stock Alert -->
    <?php if (!empty($low_stock_items)): ?>
    <div class="col-lg-6">
      <div class="card h-100 border-danger">
        <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #5c3010, #3b2008); color: white;">
          <h5 class="mb-0">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Low Stock Alert
          </h5>
          <span class="badge bg-white text-dark" style="color: #3b2008 !important;">
            <?php echo count($low_stock_items); ?> Items
          </span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table mb-0 table-sm">
              <thead class="table-light">
                <tr>
                  <th class="border-0">Item Name</th>
                  <th class="border-0 text-center">Stock</th>
                  <th class="border-0 text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($low_stock_items, 0, 10) as $item): ?>
                <tr>
                  <td class="fw-semibold align-middle">
                    <?php echo htmlspecialchars($item['item_name']); ?>
                  </td>
                  <td class="text-center align-middle">
                    <span class="badge bg-warning text-danger">
                      <i class="fas fa-box-open me-1"></i>
                      <?php echo $item['quantity']; ?> <?php echo $item['unit']; ?>
                    </span>
                  </td>
                  <td class="text-center align-middle">
                    <a href="<?php echo getBaseURL(); ?>/stock_in.php?item_id=<?php echo $item['id']; ?>"
                       class="btn btn-sm btn-outline-success">
                      <i class="fas fa-plus me-1"></i>Restock
                    </a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer bg-white border-top">
          <div class="d-flex justify-content-between align-items-center">
            <a href="<?php echo getBaseURL(); ?>/inventory.php?filter=low_stock"
               class="text-decoration-none fw-semibold" style="color: #3b2008;">
              <i class="fas fa-list me-1"></i>View All Low Stock Items
            </a>
            <?php if (count($low_stock_items) > 10): ?>
            <span class="text-muted small">
              +<?php echo count($low_stock_items) - 10; ?> more
            </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Recent Inventory Activity -->
    <div class="col-lg-<?php echo !empty($low_stock_items) ? '6' : '12'; ?>">
      <div class="card h-100">
        <div class="card-header bg-white">
          <h5 class="mb-0">
            <i class="fas fa-clipboard-list me-2 icon-brown"></i>
            Recent Inventory Activity
          </h5>
        </div>
        <div class="card-body p-0">
          <?php if (!empty($recent_inventory_activity)): ?>
          <div class="table-responsive">
            <table class="table mb-0 table-sm">
              <thead class="table-light">
                <tr>
                  <th class="border-0">Item</th>
                  <th class="border-0">Type</th>
                  <th class="border-0 text-center">Quantity</th>
                  <th class="border-0">Staff</th>
                  <th class="border-0">Time</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($recent_inventory_activity, 0, 8) as $activity): ?>
                <tr>
                  <td class="fw-semibold align-middle">
                    <?php echo htmlspecialchars($activity['item_name']); ?>
                  </td>
                  <td class="align-middle">
                    <?php if ($activity['type'] === 'stock-in'): ?>
                      <span class="badge bg-success">
                        <i class="fas fa-arrow-up me-1"></i>Stock In
                      </span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">
                        <i class="fas fa-arrow-down me-1"></i>Stock Out
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center align-middle fw-bold">
                    <?php echo number_format($activity['quantity']); ?> <?php echo $activity['unit']; ?>
                  </td>
                  <td class="align-middle">
                    <small><?php echo htmlspecialchars($activity['username']); ?></small>
                  </td>
                  <td class="text-muted small align-middle">
                    <?php echo formatDate($activity['timestamp'], 'M j, g:i A'); ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="text-center py-5">
            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
            <p class="text-muted">No inventory activity yet</p>
            <a href="<?php echo getBaseURL(); ?>/inventory.php" class="btn btn-sm" style="background-color: #3b2008; color: white;">
              <i class="fas fa-boxes me-1"></i>Manage Inventory
            </a>
          </div>
          <?php endif; ?>
        </div>
        <div class="card-footer bg-white border-top">
          <a href="<?php echo getBaseURL(); ?>/transactions.php" class="text-decoration-none text-gray">
            <i class="fas fa-eye me-1"></i>View All Transactions
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const chartData = {
    salesTrends: <?php
      $conn = getDBConnection();
      $trends = [];
      for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $conn->prepare("SELECT COUNT(*) as count, IFNULL(SUM(total_amount), 0) as revenue FROM sales WHERE DATE(sale_date) = ?");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $trends[] = [
            'date' => date('M j', strtotime($date)),
            'sales' => (int)$row['count'],
            'revenue' => (float)$row['revenue']
        ];
        $stmt->close();
      }
      echo json_encode($trends);
    ?>,
    paymentMethods: <?php
      $conn = getDBConnection();
      $stmt = $conn->prepare("
        SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total
        FROM sales
        GROUP BY payment_method
      ");
      $stmt->execute();
      $result = $stmt->get_result();
      $payments = [];
      while ($row = $result->fetch_assoc()) {
        $payments[] = [
          'method' => ucfirst($row['payment_method']),
          'count' => (int)$row['count'],
          'total' => (float)$row['total']
        ];
      }
      $stmt->close();
      echo json_encode($payments);
    ?>
  };

  document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
  });

  function initializeCharts() {
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
      new Chart(salesCtx, {
        type: 'bar',
        data: {
          labels: chartData.salesTrends.map(item => item.date),
          datasets: [
            {
              label: 'Sales Count',
              data: chartData.salesTrends.map(item => item.sales),
              backgroundColor: 'rgba(59, 32, 8, 0.7)',
              borderWidth: 0,
              borderRadius: 6,
              yAxisID: 'y'
            },
            {
              label: 'Revenue (₱)',
              data: chartData.salesTrends.map(item => item.revenue),
              type: 'line',
              borderColor: 'rgba(92, 48, 16, 1)',
              backgroundColor: 'rgba(92, 48, 16, 0.1)',
              borderWidth: 3,
              tension: 0.4,
              fill: true,
              yAxisID: 'y1',
              pointRadius: 5,
              pointBackgroundColor: 'rgba(92, 48, 16, 1)',
              pointBorderColor: '#fff',
              pointBorderWidth: 2
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'index',
            intersect: false
          },
          plugins: {
            legend: {
              position: 'top',
              labels: {
                usePointStyle: true,
                padding: 15,
                font: { size: 12 }
              }
            },
            tooltip: {
              backgroundColor: 'rgba(0,0,0,0.8)',
              titleColor: 'white',
              bodyColor: 'white',
              padding: 12,
              borderColor: 'rgba(255,255,255,0.1)',
              borderWidth: 1,
              callbacks: {
                label: function(context) {
                  let label = context.dataset.label || '';
                  if (label) label += ': ';
                  if (context.dataset.label === 'Revenue (₱)') {
                    label += '₱' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2});
                  } else {
                    label += context.parsed.y;
                  }
                  return label;
                }
              }
            }
          },
          scales: {
            y: {
              type: 'linear',
              display: true,
              position: 'left',
              beginAtZero: true,
              ticks: {
                stepSize: 1,
                color: '#6c757d',
                font: { size: 11 }
              },
              grid: { color: 'rgba(0,0,0,0.05)' },
              title: {
                display: true,
                text: 'Number of Sales',
                color: '#6c757d'
              }
            },
            y1: {
              type: 'linear',
              display: true,
              position: 'right',
              beginAtZero: true,
              ticks: {
                color: '#6c757d',
                font: { size: 11 },
                callback: function(value) {
                  return '₱' + value.toLocaleString();
                }
              },
              grid: { drawOnChartArea: false },
              title: {
                display: true,
                text: 'Revenue Amount',
                color: '#6c757d'
              }
            },
            x: {
              ticks: {
                color: '#6c757d',
                font: { size: 11 }
              },
              grid: { display: false }
            }
          }
        }
      });
    }

    const paymentCtx = document.getElementById('paymentChart');
    if (paymentCtx && chartData.paymentMethods.length > 0) {
      new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
          labels: chartData.paymentMethods.map(item => item.method),
          datasets: [{
            data: chartData.paymentMethods.map(item => item.total),
            backgroundColor: [
              'rgba(59, 32, 8, 0.8)',
              'rgba(92, 48, 16, 0.8)',
              'rgba(122, 74, 40, 0.8)',
              'rgba(160, 101, 53, 0.8)'
            ],
            borderWidth: 0,
            hoverOffset: 10
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                padding: 15,
                usePointStyle: true,
                font: { size: 12 },
                generateLabels: function(chart) {
                  const data = chart.data;
                  if (data.labels.length && data.datasets.length) {
                    return data.labels.map((label, i) => {
                      const value = data.datasets[0].data[i];
                      const count = chartData.paymentMethods[i].count;
                      return {
                        text: `${label}: ₱${value.toLocaleString()} (${count} sales)`,
                        fillStyle: data.datasets[0].backgroundColor[i],
                        hidden: false,
                        index: i
                      };
                    });
                  }
                  return [];
                }
              }
            },
            tooltip: {
              backgroundColor: 'rgba(0,0,0,0.8)',
              titleColor: 'white',
              bodyColor: 'white',
              padding: 12,
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.parsed;
                  const count = chartData.paymentMethods[context.dataIndex].count;
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = ((value / total) * 100).toFixed(1);
                  return [
                    `${label}`,
                    `Amount: ₱${value.toLocaleString('en-US', {minimumFractionDigits: 2})}`,
                    `Sales: ${count}`,
                    `Share: ${percentage}%`
                  ];
                }
              }
            }
          },
          cutout: '65%'
        }
      });
    } else if (paymentCtx) {
      paymentCtx.parentElement.innerHTML = `
        <div class="d-flex align-items-center justify-content-center h-100 text-muted">
          <div class="text-center">
            <i class="fas fa-chart-pie fa-3x mb-3"></i>
            <p class="mb-0">No payment data yet</p>
          </div>
        </div>
      `;
    }
  }

  setInterval(function() {
    if (!document.hidden) location.reload();
  }, 300000);
</script>

<style>
.card {
  border: none;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
  transform: translateY(-2px);
  box-shadow: 0 0.5rem 1rem rgba(59, 32, 8, 0.15);
}

.icon-brown { color: #3b2008; }
.bg-brown { background-color: #3b2008; color: #fff; }
.text-gray { color: #595C5F; }
.bg-bronze { background-color: #CD7F32 !important; }

.rank-badge {
  font-weight: 600;
  font-size: 0.85rem;
  padding: 0.4rem 0.6rem;
  min-width: 38px;
  text-align: center;
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

.table-sm td, .table-sm th {
  padding: 0.6rem;
}

@media (max-width: 768px) {
  .card-body h3 {
    font-size: 1.5rem;
  }

  .table-sm {
    font-size: 0.85rem;
  }

  .rank-badge {
    font-size: 0.75rem;
    padding: 0.3rem 0.5rem;
    min-width: 32px;
  }

  .table {
    font-size: 0.875rem;
  }
}
</style>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
