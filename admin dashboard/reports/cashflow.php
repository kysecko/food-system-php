<?php
require_once '../../Log-in Form/includes/config_session.inc.php';
require_once '../../Log-in Form/includes/dbh.inc.php';

// Check if user is logged in and is admin
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

// Get period from query string or default to current month
$period = isset($_GET['period']) ? $_GET['period'] : 'current_month';
$start_date = '';
$end_date = date('Y-m-d');
$period_label = '';

switch ($period) {
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('-1 month'));
        $end_date = date('Y-m-t', strtotime('-1 month'));
        $period_label = date('F Y', strtotime('-1 month'));
        break;
    case 'last_quarter':
        $start_date = date('Y-m-01', strtotime('-3 months'));
        $end_date = date('Y-m-t');
        $period_label = 'Last 3 Months';
        break;
    case 'last_year':
        $start_date = date('Y-01-01', strtotime('-1 year'));
        $end_date = date('Y-12-31', strtotime('-1 year'));
        $period_label = date('Y', strtotime('-1 year'));
        break;
    default: // current_month
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        $period_label = date('F Y');
}

// CASH INFLOW (Cash received from customers - completed orders)
$cash_inflow_query = "SELECT COALESCE(SUM(total_amount), 0) as cash_inflow 
                    FROM completed_orders 
                    WHERE DATE(order_date) BETWEEN ? AND ?";
$cash_inflow_stmt = $pdo->prepare($cash_inflow_query);
$cash_inflow_stmt->execute([$start_date, $end_date]);
$cash_inflow = $cash_inflow_stmt->fetch(PDO::FETCH_ASSOC)['cash_inflow'];

// CASH OUTFLOW (Cash paid for expenses - from cashflow table)
$cash_outflow_query = "SELECT COALESCE(SUM(cash_out), 0) as cash_outflow 
                       FROM cashflow 
                       WHERE DATE(date) BETWEEN ? AND ?";
$cash_outflow_stmt = $pdo->prepare($cash_outflow_query);
$cash_outflow_stmt->execute([$start_date, $end_date]);
$cash_outflow = $cash_outflow_stmt->fetch(PDO::FETCH_ASSOC)['cash_outflow'];

// NET CASH FLOW
$net_cash_flow = $cash_inflow - $cash_outflow;

// BEGINNING AND ENDING CASH BALANCE
// Get beginning cash balance (sum of all cashflow before period start)
$beginning_cash_query = "SELECT COALESCE(SUM(cash_in) - SUM(cash_out), 0) as beginning_cash 
                        FROM cashflow 
                        WHERE date < ?";
$beginning_cash_stmt = $pdo->prepare($beginning_cash_query);
$beginning_cash_stmt->execute([$start_date]);
$beginning_cash = $beginning_cash_stmt->fetch(PDO::FETCH_ASSOC)['beginning_cash'];

// Calculate ending cash balance
$ending_cash = $beginning_cash + $net_cash_flow;

// Get monthly cash flow data for charts
$monthly_cashflow_query = "
    SELECT 
        YEAR(date) as year,
        MONTH(date) as month,
        COALESCE(SUM(cash_in), 0) as total_in,
        COALESCE(SUM(cash_out), 0) as total_out,
        (COALESCE(SUM(cash_in), 0) - COALESCE(SUM(cash_out), 0)) as net_cash
    FROM cashflow 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(date), MONTH(date)
    ORDER BY year, month
    LIMIT 6
";
$monthly_cashflow_stmt = $pdo->query($monthly_cashflow_query);
$monthly_cashflow_data = $monthly_cashflow_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$months = [];
$inflows = [];
$outflows = [];
$net_flows = [];

foreach ($monthly_cashflow_data as $data) {
    $months[] = date('M Y', mktime(0, 0, 0, $data['month'], 1, $data['year']));
    $inflows[] = $data['total_in'];
    $outflows[] = $data['total_out'];
    $net_flows[] = $data['net_cash'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Flow Statement - Arko Flavors</title>
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
            margin-left: 220px;
            padding: 2rem;
            min-height: 100vh;
            background-color: #f5f7fa;
            width: calc(100% - 220px);
        }

        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
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

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
            margin-bottom: 0.5rem;
        }

        .summary-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
        }

        .summary-card .description {
            font-size: 0.85rem;
            color: #888;
        }

        .summary-card.inflow .value {
            color: #2ecc71;
        }

        .summary-card.outflow .value {
            color: #e74c3c;
        }

        .summary-card.net .value {
            color: #3498db;
        }

        .summary-card.balance .value {
            color: #9b59b6;
        }

        .cashflow-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .cashflow-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        .cashflow-table th {
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid #222e3c;
            color: #222e3c;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .cashflow-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eee;
        }

        .section-header {
            background: #f8f9fa;
            font-weight: 600;
            color: #222e3c;
            font-size: 1rem;
        }

        .subsection {
            padding-left: 2rem !important;
        }

        .total-row {
            background: #222e3c;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .total-row td {
            border-bottom: none;
            padding: 1rem;
        }

        .amount {
            text-align: right;
            font-weight: 500;
        }

        .positive {
            color: #2ecc71;
        }

        .negative {
            color: #e74c3c;
        }

        .charts-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 1024px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
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

        .date-filter {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            font-size: 0.9rem;
            min-width: 200px;
        }

        .apply-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #4361ee;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .apply-btn:hover {
            background: #3a56e0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .period-info {
            background: #e8f8f0;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .period-info h3 {
            color: #2ecc71;
            margin-bottom: 0.5rem;
        }

        .financial-ratios {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .ratio-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #4361ee;
        }

        .ratio-card h4 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .ratio-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #222e3c;
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 60px;
                width: calc(100% - 60px);
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }

            .cashflow-container {
                overflow-x: auto;
            }

            .date-filter {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-select {
                min-width: auto;
            }
        }
    </style>
</head>

<body>
    <?php include '../../adminSidebar.php'; ?>

    <div class="main-content">
        <div class="page-header" data-aos="fade-down" data-aos-duration="1000">
                   <?php include '../admin_header.php'; ?>

            <div class="date-filter" data-aos="fade-right" data-aos-duration="1000">
                <select class="filter-select" id="periodSelect">
                    <option value="current_month" <?php echo $period === 'current_month' ? 'selected' : ''; ?>>Current
                        Month</option>
                    <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Last Month
                    </option>
                    <option value="last_quarter" <?php echo $period === 'last_quarter' ? 'selected' : ''; ?>>Last Quarter
                    </option>
                    <option value="last_year" <?php echo $period === 'last_year' ? 'selected' : ''; ?>>Last Year</option>
                </select>
                <button class="apply-btn" onclick="applyFilter()"><svg xmlns="http://www.w3.org/2000/svg" width="18"
                        height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-list-filter-plus-icon lucide-list-filter-plus">
                        <path d="M12 5H2" />
                        <path d="M6 12h12" />
                        <path d="M9 19h6" />
                        <path d="M16 5h6" />
                        <path d="M19 8V2" />
                    </svg>Apply Filter</button>
            </div>
        </div>

        <div class="period-info" data-aos="zoom-in" data-aos-duration="1000">
            <h3>Reporting Period: <?php echo $period_label; ?></h3>
            <p>From <?php echo date('M j, Y', strtotime($start_date)); ?> to
                <?php echo date('M j, Y', strtotime($end_date)); ?>
            </p>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards" data-aos="zoom-in" data-aos-duration="1000">
            <div class="summary-card inflow">
                <h3>CASH INFLOW</h3>
                <div class="value positive">
                    ₱<?php echo number_format($cash_inflow, 2); ?>
                </div>
                <div class="description">💰 Sales & Revenue</div>
            </div>
            <div class="summary-card outflow">
                <h3>CASH OUTFLOW</h3>
                <div class="value negative">
                    ₱<?php echo number_format($cash_outflow, 2); ?>
                </div>
                <div class="description">📤 Expenses & Costs</div>
            </div>
            <div class="summary-card net">
                <h3>NET CASH FLOW</h3>
                <div class="value <?php echo $net_cash_flow >= 0 ? 'positive' : 'negative'; ?>">
                    ₱<?php echo number_format($net_cash_flow, 2); ?>
                </div>
                <div class="description">📈 Inflow minus Outflow</div>
            </div>
            <div class="summary-card balance">
                <h3>ENDING CASH BALANCE</h3>
                <div class="value <?php echo $ending_cash >= 0 ? 'positive' : 'negative'; ?>">
                    ₱<?php echo number_format($ending_cash, 2); ?>
                </div>
                <div class="description">🏦 Current cash position</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-container" data-aos="fade-up" data-aos-duration="1000">
            <div class="chart-card">
                <h2>Cash Flow Trend (Last 6 Months)</h2>
                <div class="chart-container">
                    <canvas id="cashFlowChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h2>Cash Flow Distribution</h2>
                <div class="chart-container">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Cash Flow Statement -->
        <div class="cashflow-container">
            <table class="cashflow-table">
                <!-- Cash from Operating Activities -->
                <thead>
                    <tr>
                        <th colspan="2">CASH FLOWS FROM OPERATING ACTIVITIES</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Cash received from customers</td>
                        <td class="amount positive">₱<?php echo number_format($cash_inflow, 2); ?></td>
                    </tr>
                    <tr>
                        <td class="subsection">Cash paid for operating expenses</td>
                        <td class="amount negative">(₱<?php echo number_format($cash_outflow, 2); ?>)</td>
                    </tr>
                    <tr class="section-header">
                        <td><strong>Net Cash Provided by Operating Activities</strong></td>
                        <td class="amount <?php echo $net_cash_flow >= 0 ? 'positive' : 'negative'; ?>">
                            <strong>₱<?php echo number_format($net_cash_flow, 2); ?></strong>
                        </td>
                    </tr>
                </tbody>

                <!-- Cash Balance -->
                <tbody>
                    <tr>
                        <td>Cash balance at beginning of period</td>
                        <td class="amount">₱<?php echo number_format($beginning_cash, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>Cash balance at end of period</strong></td>
                        <td class="amount">
                            <strong class="<?php echo $ending_cash >= 0 ? 'positive' : 'negative'; ?>">
                                ₱<?php echo number_format($ending_cash, 2); ?>
                            </strong>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Financial Ratios -->
            <div class="financial-ratios">
                <div class="ratio-card">
                    <h4>Cash Flow Margin</h4>
                    <div class="ratio-value">
                        <?php echo $cash_inflow > 0 ? number_format(($net_cash_flow / $cash_inflow) * 100, 1) . '%' : 'N/A'; ?>
                    </div>
                    <small>Profitability ratio</small>
                </div>
                <div class="ratio-card">
                    <h4>Expense Coverage</h4>
                    <div class="ratio-value">
                        <?php echo $cash_outflow > 0 ? number_format($cash_inflow / $cash_outflow, 2) : 'N/A'; ?>
                    </div>
                    <small>Revenue to expense ratio</small>
                </div>
                <div class="ratio-card">
                    <h4>Cash Efficiency</h4>
                    <div class="ratio-value">
                        <?php echo $cash_inflow > 0 ? number_format(($net_cash_flow / $cash_inflow) * 100, 1) . '%' : 'N/A'; ?>
                    </div>
                    <small>Net cash per revenue</small>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Cash Flow Trend Chart
        const cashFlowCtx = document.getElementById('cashFlowChart').getContext('2d');
        const cashFlowChart = new Chart(cashFlowCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [
                    {
                        label: 'Cash Inflow',
                        data: <?php echo json_encode($inflows); ?>,
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Cash Outflow',
                        data: <?php echo json_encode($outflows); ?>,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Net Cash Flow',
                        data: <?php echo json_encode($net_flows); ?>,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderDash: [5, 5]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
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

        // Cash Flow Distribution Chart
        const distributionCtx = document.getElementById('distributionChart').getContext('2d');
        const distributionChart = new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Cash Inflow', 'Cash Outflow'],
                datasets: [{
                    data: [
                        <?php echo $cash_inflow; ?>,
                        <?php echo $cash_outflow; ?>
                    ],
                    backgroundColor: [
                        '#2ecc71',
                        '#e74c3c'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        function applyFilter() {
            const period = document.getElementById('periodSelect').value;
            window.location.href = `?period=${period}`;
        }

        // Add hover effects to table rows
        document.addEventListener('DOMContentLoaded', function () {
            const rows = document.querySelectorAll('.cashflow-table tr:not(.section-header):not(.total-row)');
            rows.forEach(row => {
                row.addEventListener('mouseenter', function () {
                    this.style.backgroundColor = '#f8f9fa';
                });
                row.addEventListener('mouseleave', function () {
                    this.style.backgroundColor = '';
                });
            });
        });
    </script>
</body>

</html>