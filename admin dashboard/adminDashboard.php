<?php
include '../Log-in Form/includes/config_session.inc.php';
include '../Log-in Form/includes/dbh.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Log-in Form/login.php');
    exit;
}
// In your admin dashboard, add this to show payment notifications:
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE type = 'payment_upload' AND is_read = 0 ORDER BY created_at DESC");
$stmt->execute();
$payment_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($payment_notifications as $notification) {
    echo "<div class='notification'>";
    echo "New payment proof: " . $notification['message'];
    echo "<a href='verify_payment.php?order_id=" . $notification['order_id'] . "'>View Payment</a>";
    echo "</div>";
}
// Get current date ranges
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Analytics Dashboard - Arko Flavors</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
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
            margin-left: 210px;
            padding: 2rem;
            min-height: 100vh;
            background-color: #f5f7fa;
            width: calc(100% - 220px);
        }

        .dashboard-header {
            margin-bottom: 2rem;
        }

        .dashboard-header h1 {
            color: #222e3c;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .dashboard-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
            transition: all 0.4s ease;
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
            gap: 0.5rem;
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

        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .chart-card h2 {
            color: #222e3c;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        .data-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .data-section h2 {
            color: #222e3c;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid #222e3c;
            color: #222e3c;
            font-weight: 600;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status.completed {
            background: #e8f8f0;
            color: #2ecc71;
        }

        .status.pending {
            background: #fff4e6;
            color: #e67e22;
        }

        .status.accepted {
            background: #e8f4ff;
            color: #3498db;
        }

        .positive {
            color: #2ecc71;
        }

        .negative {
            color: #e74c3c;
        }

        .section-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 1200px) {
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .section-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 60px;
                width: calc(100% - 60px);
                padding: 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- <?php include '../adminSidebar.php'; ?> -->

    <div class="main-content">

    <?php include 'admin_header.php'; ?>

        <!-- Main Summary Cards -->
        <div class="summary-cards">
            <?php
            // Total Revenue (All Time)
            $total_revenue_query = "SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM completed_orders";
            $total_revenue_stmt = $pdo->query($total_revenue_query);
            $total_revenue = $total_revenue_stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'];

            // Total Expenses (All Time)
            $total_expenses_query = "SELECT COALESCE(SUM(cash_out), 0) as total_expenses FROM cashflow";
            $total_expenses_stmt = $pdo->query($total_expenses_query);
            $total_expenses = $total_expenses_stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'];

            // Cash Balance
            $cash_balance_query = "SELECT COALESCE(SUM(cash_in) - SUM(cash_out), 0) as cash_balance FROM cashflow";
            $cash_balance_stmt = $pdo->query($cash_balance_query);
            $cash_balance = $cash_balance_stmt->fetch(PDO::FETCH_ASSOC)['cash_balance'];

            // Net Profit (Revenue - Expenses)
            $net_profit = $total_revenue - $total_expenses;
            ?>

            <div class="summary-card sales" data-aos="zoom-in" data-aos-duration="1000">
                <h3>TOTAL SALES</h3>
                <div class="value">₱<?php echo number_format($total_revenue, 2); ?></div>
                <div class="trend up">All-time revenue from completed orders</div>
            </div>

            <div class="summary-card expenses" data-aos="zoom-in" data-aos-duration="1000">
                <h3>TOTAL EXPENSES</h3>
                <div class="value">₱<?php echo number_format($total_expenses, 2); ?></div>
                <div class="trend down">Total cash outflows recorded</div>
            </div>

            <div class="summary-card cash" data-aos="zoom-in" data-aos-duration="1000">
                <h3>CASH BALANCE</h3>
                <div class="value <?php echo $cash_balance >= 0 ? 'positive' : 'negative'; ?>">
                    ₱<?php echo number_format($cash_balance, 2); ?>
                </div>
                <div class="trend <?php echo $cash_balance >= 0 ? 'up' : 'down'; ?>">
                    Current available cash
                </div>
            </div>

            <div class="summary-card profit" data-aos="zoom-in" data-aos-duration="1000">
                <h3>NET PROFIT</h3>
                <div class="value <?php echo $net_profit >= 0 ? 'positive' : 'negative'; ?>">
                    ₱<?php echo number_format($net_profit, 2); ?>
                </div>
                <div class="trend <?php echo $net_profit >= 0 ? 'up' : 'down'; ?>">
                    Revenue minus expenses
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid" data-aos="fade-up" data-aos-duration="1000">
            <div class="chart-card">
                <h2>Revenue vs Expenses (Last 6 Months)</h2>
                <div class="chart-container">
                    <canvas id="revenueExpensesChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h2>Cash Flow Overview</h2>
                <div class="chart-container">
                    <canvas id="cashFlowChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Data Section -->
        <div class="section-grid">
            <!-- Recent Orders -->
            <div class="data-section">
                <h2>Recent Orders</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent_orders_query = "
                            SELECT 'pending' as source, id, customer_name, total_amount, order_date 
                            FROM pending_orders 
                            UNION ALL 
                            SELECT 'accepted' as source, id, customer_name, total_amount, order_date 
                            FROM accepted_orders 
                            UNION ALL 
                            SELECT 'completed' as source, id, customer_name, total_amount, order_date 
                            FROM completed_orders 
                            ORDER BY order_date DESC 
                            LIMIT 6
                        ";
                        $recent_orders_stmt = $pdo->query($recent_orders_query);
                        $recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (empty($recent_orders)) {
                            echo '<tr><td colspan="5" style="text-align: center; color: #666;">No orders found</td></tr>';
                        } else {
                            foreach ($recent_orders as $order) {
                                $status_class = '';
                                $status_text = '';

                                switch ($order['source']) {
                                    case 'pending':
                                        $status_class = 'pending';
                                        $status_text = 'Pending';
                                        break;
                                    case 'accepted':
                                        $status_class = 'accepted';
                                        $status_text = 'Accepted';
                                        break;
                                    case 'completed':
                                        $status_class = 'completed';
                                        $status_text = 'Completed';
                                        break;
                                }

                                echo "
                                <tr>
                                    <td>#ORD-" . $order['id'] . "</td>
                                    <td>" . htmlspecialchars($order['customer_name']) . "</td>
                                    <td>₱" . number_format($order['total_amount'], 2) . "</td>
                                    <td><span class='status $status_class'>$status_text</span></td>
                                    <td>" . date('M j, H:i', strtotime($order['order_date'])) . "</td>
                                </tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Expenses -->
            <div class="data-section">
                <h2>Recent Expenses</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent_expenses_query = "
                            SELECT date, description, cash_out 
                            FROM cashflow 
                            WHERE cash_out > 0 
                            ORDER BY date DESC 
                            LIMIT 6
                        ";
                        $recent_expenses_stmt = $pdo->query($recent_expenses_query);
                        $recent_expenses = $recent_expenses_stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (empty($recent_expenses)) {
                            echo '<tr><td colspan="3" style="text-align: center; color: #666;">No expenses found</td></tr>';
                        } else {
                            foreach ($recent_expenses as $expense) {
                                echo "
                                <tr>
                                    <td>" . date('M j, Y', strtotime($expense['date'])) . "</td>
                                    <td>" . htmlspecialchars($expense['description']) . "</td>
                                    <td class='negative'>₱" . number_format($expense['cash_out'], 2) . "</td>
                                </tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Order Status Overview -->
        <div class="data-section">
            <h2>Order Status Overview</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                        <th>Total Amount</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Pending Orders
                    $pending_stats_query = "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM pending_orders";
                    $pending_stats_stmt = $pdo->query($pending_stats_query);
                    $pending_stats = $pending_stats_stmt->fetch(PDO::FETCH_ASSOC);

                    // Accepted Orders
                    $accepted_stats_query = "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM accepted_orders";
                    $accepted_stats_stmt = $pdo->query($accepted_stats_query);
                    $accepted_stats = $accepted_stats_stmt->fetch(PDO::FETCH_ASSOC);

                    // Completed Orders
                    $completed_stats_query = "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM completed_orders";
                    $completed_stats_stmt = $pdo->query($completed_stats_query);
                    $completed_stats = $completed_stats_stmt->fetch(PDO::FETCH_ASSOC);

                    $total_all_orders = $pending_stats['count'] + $accepted_stats['count'] + $completed_stats['count'];
                    $total_all_amount = $pending_stats['total'] + $accepted_stats['total'] + $completed_stats['total'];

                    $statuses = [
                        ['Pending', $pending_stats['count'], $pending_stats['total'], 'pending'],
                        ['Accepted', $accepted_stats['count'], $accepted_stats['total'], 'accepted'],
                        ['Completed', $completed_stats['count'], $completed_stats['total'], 'completed']
                    ];

                    foreach ($statuses as $status) {
                        $percentage = $total_all_orders > 0 ? ($status[1] / $total_all_orders) * 100 : 0;
                        echo "
                        <tr>
                            <td><span class='status {$status[3]}'>{$status[0]}</span></td>
                            <td>{$status[1]}</td>
                            <td>₱" . number_format($status[2], 2) . "</td>
                            <td>" . number_format($percentage, 1) . "%</td>
                        </tr>";
                    }

                    echo "
                    <tr style='background: #f8f9fa; font-weight: 600;'>
                        <td>TOTAL</td>
                        <td>{$total_all_orders}</td>
                        <td>₱" . number_format($total_all_amount, 2) . "</td>
                        <td>100%</td>
                    </tr>";
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Revenue vs Expenses Chart
        const revenueExpensesCtx = document.getElementById('revenueExpensesChart').getContext('2d');
        const revenueExpensesChart = new Chart(revenueExpensesCtx, {
            type: 'bar',
            data: {
                labels: ['<?php echo date('M', strtotime('-5 months')); ?>',
                    '<?php echo date('M', strtotime('-4 months')); ?>',
                    '<?php echo date('M', strtotime('-3 months')); ?>',
                    '<?php echo date('M', strtotime('-2 months')); ?>',
                    '<?php echo date('M', strtotime('-1 month')); ?>',
                    '<?php echo date('M'); ?>'
                ],
                datasets: [{
                        label: 'Revenue',
                        data: [12000, 19000, 15000, 25000, 22000, 30000],
                        backgroundColor: '#2ecc71',
                        borderColor: '#27ae60',
                        borderWidth: 1
                    },
                    {
                        label: 'Expenses',
                        data: [8000, 12000, 10000, 15000, 13000, 18000],
                        backgroundColor: '#e74c3c',
                        borderColor: '#c0392b',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        },
                        title: {
                            display: true,
                            text: 'Amount (₱)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Cash Flow Chart
        const cashFlowCtx = document.getElementById('cashFlowChart').getContext('2d');
        const cashFlowChart = new Chart(cashFlowCtx, {
            type: 'doughnut',
            data: {
                labels: ['Cash Inflow', 'Cash Outflow', 'Net Cash'],
                datasets: [{
                    data: [
                        <?php echo $total_revenue; ?>,
                        <?php echo $total_expenses; ?>,
                        <?php echo $cash_balance; ?>
                    ],
                    backgroundColor: [
                        '#2ecc71',
                        '#e74c3c',
                        '#3498db'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    </script>
</body>

</html>