<?php
require_once '../../Log-in Form/includes/config_session.inc.php';
require_once '../../Log-in Form/includes/dbh.inc.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../Log-in Form/login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id');
$stmt->bindParam(':id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    header('Location: ../../user dashboard/userDashboard.php');
    exit;
}

// Create sales table if it doesn't exist
try {
    $createSalesTable = $pdo->prepare("
        CREATE TABLE IF NOT EXISTS sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NULL,
            product_name VARCHAR(255) NOT NULL,
            account_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            total_amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'completed',
            sale_date DATETIME NOT NULL,
            receipt_image VARCHAR(500) NULL,
            order_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sale_date (sale_date),
            INDEX idx_status (status),
            INDEX idx_order_id (order_id)
        )
    ");
    $createSalesTable->execute();
} catch (PDOException $e) {
    // Table might already exist, continue silently
}

// Function to generate sales data from completed orders
function generateSalesFromCompletedOrders($pdo, $year, $month)
{
    $sales = [];

    // Get completed orders for the period
    $stmt = $pdo->prepare("
        SELECT * FROM completed_orders 
        WHERE YEAR(order_date)=? AND MONTH(order_date)=? 
        ORDER BY order_date DESC
    ");
    $stmt->execute([$year, $month]);
    $completed_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($completed_orders as $order) {
        $orderDetails = json_decode($order['order_details'], true);
        if (is_array($orderDetails)) {
            foreach ($orderDetails as $item) {
                // Calculate item price
                $item_price = $item['price'] ?? 0;
                if (!$item_price && isset($item['total'])) {
                    $item_price = $item['total'];
                } elseif (!$item_price) {
                    // Estimate price from total order amount
                    $item_price = $order['total_amount'] / count($orderDetails);
                }

                $sales[] = [
                    'id' => 'O-' . $order['id'] . '-' . uniqid(),
                    'product_id' => $item['menuId'] ?? null,
                    'product_name' => $item['name'] ?? 'Unknown Product',
                    'account_name' => $order['customer_name'],
                    'quantity' => $item['quantity'] ?? 1,
                    'total_amount' => $item_price,
                    'status' => 'completed',
                    'sale_date' => $order['order_date'],
                    'receipt_image' => $order['payment_screenshot'] ?? null,
                    'order_id' => $order['id']
                ];
            }
        } else {
            // If order details can't be parsed, create a single sales entry for the entire order
            $sales[] = [
                'id' => 'O-' . $order['id'],
                'product_id' => null,
                'product_name' => 'Complete Order',
                'account_name' => $order['customer_name'],
                'quantity' => 1,
                'total_amount' => $order['total_amount'],
                'status' => 'completed',
                'sale_date' => $order['order_date'],
                'receipt_image' => $order['payment_screenshot'] ?? null,
                'order_id' => $order['id']
            ];
        }
    }

    return $sales;
}
// Function to get monthly data from completed orders
function getMonthlyDataFromOrders($pdo, $year)
{
    $stmt = $pdo->prepare("
        SELECT YEAR(order_date) as year, MONTH(order_date) as month, 
               SUM(total_amount) as total_sales, COUNT(*) as sales_count
        FROM completed_orders 
        WHERE YEAR(order_date) = ? 
        GROUP BY YEAR(order_date), MONTH(order_date) 
        ORDER BY year, month
    ");
    $stmt->execute([$year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get yearly data from completed orders
function getYearlyDataFromOrders($pdo, $start_year, $end_year)
{
    $stmt = $pdo->prepare("
        SELECT YEAR(order_date) as year, 
               SUM(total_amount) as total_sales, COUNT(*) as sales_count
        FROM completed_orders 
        WHERE YEAR(order_date) BETWEEN ? AND ?
        GROUP BY YEAR(order_date) 
        ORDER BY year
    ");
    $stmt->execute([$start_year, $end_year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get daily data from completed orders
function getDailyDataFromOrders($pdo, $year, $month)
{
    $stmt = $pdo->prepare("
        SELECT DAY(order_date) as day, 
               SUM(total_amount) as total_sales, COUNT(*) as sales_count
        FROM completed_orders 
        WHERE YEAR(order_date) = ? AND MONTH(order_date) = ?
        GROUP BY DAY(order_date) 
        ORDER BY day
    ");
    $stmt->execute([$year, $month]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get filter parameters
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'monthly';

// Get sales data for table
$sales = [];
$total = 0;

try {
    // Try to get data from sales table first
    $stmt = $pdo->prepare("
        SELECT s.*, m.name as product_name 
        FROM sales s 
        LEFT JOIN menu_items m ON s.product_id = m.id 
        WHERE YEAR(s.sale_date)=? AND MONTH(s.sale_date)=? 
        ORDER BY s.sale_date DESC
    ");
    $stmt->execute([$year, $month]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no sales data, try to generate from completed orders
    if (empty($sales)) {
        $sales = generateSalesFromCompletedOrders($pdo, $year, $month);
    }
} catch (PDOException $e) {
    // If sales table doesn't exist or query fails, generate from completed orders
    $sales = generateSalesFromCompletedOrders($pdo, $year, $month);
}

$total = array_sum(array_column($sales, 'total_amount'));

// Get sales statistics
$total_sales_count = count($sales);
$completed_sales = array_filter($sales, function ($sale) {
    return $sale['status'] === 'completed';
});
$completed_count = count($completed_sales);
$pending_count = $total_sales_count - $completed_count;

// Additional stats from completed orders
$stmt_orders = $pdo->prepare("
    SELECT COUNT(*) as total_orders, SUM(total_amount) as total_revenue 
    FROM completed_orders 
    WHERE YEAR(order_date)=? AND MONTH(order_date)=?
");
$stmt_orders->execute([$year, $month]);
$order_stats = $stmt_orders->fetch(PDO::FETCH_ASSOC);

$total_orders_count = $order_stats['total_orders'] ?? 0;
$total_revenue_from_orders = $order_stats['total_revenue'] ?? 0;

// Get chart data based on timeframe
$chart_data = [];
$chart_labels = [];
$chart_values = [];

if ($timeframe === 'monthly') {
    // Monthly data for the selected year
    try {
        $stmt = $pdo->prepare("
            SELECT YEAR(sale_date) as year, MONTH(sale_date) as month, 
                   SUM(total_amount) as total_sales, COUNT(*) as sales_count
            FROM sales 
            WHERE YEAR(sale_date) = ? 
            GROUP BY YEAR(sale_date), MONTH(sale_date) 
            ORDER BY year, month
        ");
        $stmt->execute([$year]);
        $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback to completed orders
        $monthly_data = getMonthlyDataFromOrders($pdo, $year);
    }

    // Fill in missing months
    for ($m = 1; $m <= 12; $m++) {
        $found = false;
        foreach ($monthly_data as $data) {
            if ($data['month'] == $m) {
                $chart_labels[] = date('M', mktime(0, 0, 0, $m, 1));
                $chart_values[] = (float)$data['total_sales'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $chart_labels[] = date('M', mktime(0, 0, 0, $m, 1));
            $chart_values[] = 0;
        }
    }
} elseif ($timeframe === 'yearly') {
    // Yearly data for last 5 years
    $current_year = (int)date('Y');
    $start_year = $current_year - 4;

    try {
        $stmt = $pdo->prepare("
            SELECT YEAR(sale_date) as year, SUM(total_amount) as total_sales, COUNT(*) as sales_count
            FROM sales 
            WHERE YEAR(sale_date) BETWEEN ? AND ?
            GROUP BY YEAR(sale_date) 
            ORDER BY year
        ");
        $stmt->execute([$start_year, $current_year]);
        $yearly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback to completed orders
        $yearly_data = getYearlyDataFromOrders($pdo, $start_year, $current_year);
    }

    // Fill in missing years
    for ($y = $start_year; $y <= $current_year; $y++) {
        $found = false;
        foreach ($yearly_data as $data) {
            if ($data['year'] == $y) {
                $chart_labels[] = $y;
                $chart_values[] = (float)$data['total_sales'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $chart_labels[] = $y;
            $chart_values[] = 0;
        }
    }
} elseif ($timeframe === 'daily') {
    // Daily data for the selected month
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    try {
        $stmt = $pdo->prepare("
            SELECT DAY(sale_date) as day, SUM(total_amount) as total_sales, COUNT(*) as sales_count
            FROM sales 
            WHERE YEAR(sale_date) = ? AND MONTH(sale_date) = ?
            GROUP BY DAY(sale_date) 
            ORDER BY day
        ");
        $stmt->execute([$year, $month]);
        $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback to completed orders
        $daily_data = getDailyDataFromOrders($pdo, $year, $month);
    }

    // Fill in missing days
    for ($d = 1; $d <= $days_in_month; $d++) {
        $found = false;
        foreach ($daily_data as $data) {
            if ($data['day'] == $d) {
                $chart_labels[] = $d;
                $chart_values[] = (float)$data['total_sales'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $chart_labels[] = $d;
            $chart_values[] = 0;
        }
    }
}

// Get available years for filter dropdown
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT YEAR(order_date) as year 
        FROM completed_orders 
        UNION 
        SELECT DISTINCT YEAR(sale_date) as year 
        FROM sales 
        ORDER BY year DESC
    ");
    $stmt->execute();
    $available_years = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (PDOException $e) {
    // Fallback to current year if query fails
    $available_years = [date('Y')];
}

if (empty($available_years)) {
    $available_years = [date('Y')];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Sales Report - Food Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        * {
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
            margin: 0;
            padding: 0;
        }

        body {
            display: flex;
            min-height: 100vh;
            background-color: #f5f7fa;
        }

        .main-content {
            margin-left: 220px;
            padding: 2rem;
            min-height: 100vh;
            background-color: #f5f7fa;
            width: calc(100% - 220px);
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: #222e3c;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .sales-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .summary-card h3 {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .summary-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #222e3c;
            margin-bottom: 0.5rem;
        }

        .summary-card .trend {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
        }

        .summary-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
        }

        .trend.up {
            color: #2ecc71;
        }

        .trend.down {
            color: #e74c3c;
        }

        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-header h2 {
            color: #222e3c;
            font-size: 1.3rem;
        }

        .chart-filters {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filter-select {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .chart-wrapper {
            position: relative;
            height: 400px;
            width: 100%;
        }

        .sales-table-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .sales-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sales-table th {
            text-align: left;
            padding: 1rem;
            border-bottom: 1px solid #eee;
            color: #666;
            font-weight: 500;
            background: #f8f9fa;
        }

        .sales-table td {
            padding: 1rem;
            border-bottom: 1px solid #f5f5f5;
        }

        .sales-table tr:hover {
            background: #f8f9fa;
        }

        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status.pending {
            background: #fff4e6;
            color: #e67e22;
        }

        .status.completed {
            background: #e8f8f0;
            color: #2ecc71;
        }

        .status.cooking {
            background: #e6f3ff;
            color: #3498db;
        }

        .status.to-be-delivered {
            background: #fff8e6;
            color: #f39c12;
        }

        .status.received {
            background: #f0e6ff;
            color: #9b59b6;
        }

        .receipt-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #eee;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .receipt-img:hover {
            transform: scale(1.1);
        }

        .total-row {
            background: #222e3c !important;
            color: white;
            font-weight: 600;
        }

        .total-row td {
            border-bottom: none;
            padding: 1.2rem 1rem;
        }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
        }

        .data-source-badge {
            background: #4361ee;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }

        .sync-button {
            background: #2ecc71;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .sync-button:hover {
            background: #27ae60;
            transform: translateY(-2px);
        }

        .sync-button:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 60px;
                width: calc(100% - 60px);
                padding: 1rem;
            }

            .sales-table {
                font-size: 0.9rem;
            }

            .sales-table th,
            .sales-table td {
                padding: 0.75rem 0.5rem;
            }

            .chart-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .chart-filters {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }

            .sales-table-container {
                overflow-x: auto;
            }

            .filters {
                flex-direction: column;
                align-items: stretch;
            }
        }

        /* Modal for receipt preview */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 12px;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
        }

        .no-data {
            text-align: center;
            color: #666;
            padding: 3rem;
            font-style: italic;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: #e8f8f0;
            color: #2ecc71;
            border: 1px solid #d4edda;
        }

        .alert-info {
            background-color: #e6f3ff;
            color: #3498db;
            border: 1px solid #b3d9ff;
        }
    </style>
</head>

<body>
    <?php include '../../adminSidebar.php'; ?>

    <div class="main-content">
        <?php include '../admin_header.php'; ?>


        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                ✅ <?php echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['sync_message'])): ?>
            <div class="alert alert-info">
                🔄 <?php echo $_SESSION['sync_message'];
                    unset($_SESSION['sync_message']); ?>
            </div>
        <?php endif; ?>

        <div class="sales-summary">
            <div class="summary-card">
                <h3>TOTAL REVENUE</h3>
                <div class="value">₱<?= number_format($total, 2) ?></div>
                <div class="trend up">↑ From <?= $total_sales_count ?> sales</div>
            </div>
            <div class="summary-card">
                <h3>COMPLETED ORDERS</h3>
                <div class="value"><?= $total_orders_count ?></div>
                <div class="trend up">↑ This period</div>
            </div>
            <div class="summary-card">
                <h3>ORDER REVENUE</h3>
                <div class="value">₱<?= number_format($total_revenue_from_orders, 2) ?></div>
                <div class="trend up">↑ From completed orders</div>
            </div>
            <div class="summary-card">
                <h3>SUCCESS RATE</h3>
                <div class="value"><?= $total_orders_count > 0 ? round(($completed_count / $total_orders_count) * 100, 1) : 0 ?>%</div>
                <div class="trend up">↑ Completion rate</div>
            </div>
        </div>

        <!-- Sales Chart Section -->
        <div class="chart-container">
            <div class="chart-header">
                <h2>Sales Performance</h2>
                <div class="chart-filters">
                    <form method="GET" id="chartForm" style="display: flex; gap: 1rem; align-items: center;">
                        <select name="timeframe" class="filter-select" onchange="updateChartFilters()">
                            <option value="daily" <?= $timeframe === 'daily' ? 'selected' : '' ?>>Daily</option>
                            <option value="monthly" <?= $timeframe === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                            <option value="yearly" <?= $timeframe === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                        </select>

                        <select name="year" class="filter-select" id="yearSelect">
                            <?php foreach ($available_years as $available_year): ?>
                                <option value="<?= $available_year ?>" <?= $year == $available_year ? 'selected' : '' ?>>
                                    <?= $available_year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($timeframe === 'daily' || $timeframe === 'monthly'): ?>
                            <select name="month" class="filter-select" id="monthSelect">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        <?php endif; ?>

                        <button type="submit" class="filter-select" style="background: #4361ee; color: white; border: none;">
                            Apply Filters
                        </button>

                    </form>
                </div>
            </div>

            <div class="chart-wrapper">
                <?php if (array_sum($chart_values) > 0): ?>
                    <canvas id="salesChart"></canvas>
                <?php else: ?>
                    <div class="no-data">
                        <h3>No sales data available for the selected period</h3>
                        <p>Complete some orders to see sales data here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sales Table Section -->
        <div class="sales-table-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>Sales Details</h3>
                <div style="color: #666; font-size: 0.9rem;">
                    Showing <?= count($sales) ?> records for <?= date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?>
                </div>
            </div>

            <table class="sales-table">
                <thead>
                    <tr>
                        <th>Sale ID</th>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Qty</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Receipt</th>
                        <th>Order ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #666; padding: 2rem;">
                                No sales recorded for <?= date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?>
                                <br><small>Complete some orders to see sales data here</small>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales as $row): ?>
                            <tr class="sale-row" data-status="<?= htmlspecialchars($row['status']) ?>"
                                data-product="<?= $row['product_id'] ?>">
                                <td>#<?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['product_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['account_name'] ?? 'N/A') ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td><strong>₱<?= number_format($row['total_amount'], 2) ?></strong></td>
                                <td>
                                    <span class="status <?= str_replace(' ', '-', $row['status']) ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M j, g:i A', strtotime($row['sale_date'])) ?></td>
                                <td>
                                    <?php if ($row['receipt_image'] && file_exists(__DIR__ . '/../../' . $row['receipt_image'])): ?>
                                        <img src="../../<?= htmlspecialchars($row['receipt_image']) ?>"
                                            class="receipt-img"
                                            onclick="showReceipt('../../<?= htmlspecialchars($row['receipt_image']) ?>')"
                                            alt="Receipt">
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($row['order_id'])): ?>
                                        <a href="../manage ordersmanageOrders.php?tab=completed#order-<?= $row['order_id'] ?>"
                                            style="color: #4361ee; text-decoration: none;">
                                            #<?= $row['order_id'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="4"><strong>PERIOD TOTAL</strong></td>
                        <td><strong>₱<?= number_format($total, 2) ?></strong></td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Receipt Preview Modal -->
    <div id="receiptModal" class="modal">
        <span class="close-modal" onclick="closeReceipt()">&times;</span>
        <img class="modal-content" id="receiptImage">
    </div>

    <script>
        // Update chart filters based on timeframe selection
        function updateChartFilters() {
            const timeframe = document.querySelector('select[name="timeframe"]').value;
            const yearSelect = document.getElementById('yearSelect');
            const monthSelect = document.getElementById('monthSelect');

            if (timeframe === 'yearly') {
                monthSelect.style.display = 'none';
            } else {
                monthSelect.style.display = 'inline-block';
            }
        }

        // Initialize chart filters on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateChartFilters();

            <?php if (array_sum($chart_values) > 0): ?>
                initializeChart();
            <?php endif; ?>
        });

        // Initialize sales chart
        function initializeChart() {
            const ctx = document.getElementById('salesChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        label: 'Sales Revenue (₱)',
                        data: <?= json_encode($chart_values) ?>,
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#4361ee',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return `₱${context.parsed.y.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString('en-PH');
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        }

        // Sync sales data with completed orders
        function syncSalesData() {
            const syncButton = document.getElementById('syncButton');
            const originalText = syncButton.innerHTML;

            syncButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/></svg> Syncing...';
            syncButton.disabled = true;

            fetch('sync_sales.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=sync_sales'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message);
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        alert('❌ ' + data.message);
                        syncButton.innerHTML = originalText;
                        syncButton.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Error syncing sales data');
                    syncButton.innerHTML = originalText;
                    syncButton.disabled = false;
                });
        }

        // Show receipt in modal
        function showReceipt(src) {
            document.getElementById('receiptImage').src = src;
            document.getElementById('receiptModal').style.display = 'flex';
        }

        // Close receipt modal
        function closeReceipt() {
            document.getElementById('receiptModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('receiptModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeReceipt();
            }
        });

        // Auto-refresh data every 30 seconds
        setInterval(() => {
            // Only refresh if no modal is open and user is active
            if (document.getElementById('receiptModal').style.display === 'none') {
                // You can add a more sophisticated refresh logic here
                console.log('Auto-refresh available');
            }
        }, 30000);
    </script>
</body>

</html>