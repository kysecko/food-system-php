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
        $period_label = 'Last Quarter';
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

// Revenue Calculations
$revenue_query = "SELECT COALESCE(SUM(total_amount), 0) as total_revenue 
                    FROM completed_orders 
                    WHERE order_date BETWEEN ? AND ?";
$revenue_stmt = $pdo->prepare($revenue_query);
$revenue_stmt->execute([$start_date, $end_date]);
$total_revenue = $revenue_stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'];

// Expense Calculations
$expenses_query = "SELECT COALESCE(SUM(cash_out), 0) as total_expenses 
                    FROM cashflow 
                    WHERE date BETWEEN ? AND ?";
$expenses_stmt = $pdo->prepare($expenses_query);
$expenses_stmt->execute([$start_date, $end_date]);
$total_expenses = $expenses_stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'];

// Cost of Goods Sold (COGS) - Simplified calculation
$cogs_query = "SELECT COALESCE(SUM(total_amount * 0.4), 0) as cogs 
                FROM completed_orders 
                WHERE order_date BETWEEN ? AND ?"; // Assuming 40% COGS
$cogs_stmt = $pdo->prepare($cogs_query);
$cogs_stmt->execute([$start_date, $end_date]);
$cogs = $cogs_stmt->fetch(PDO::FETCH_ASSOC)['cogs'];

// Gross Profit and Net Income
$gross_profit = $total_revenue - $cogs;
$operating_expenses = $total_expenses;
$net_income = $gross_profit - $operating_expenses;

// Calculate percentages for insights
$gross_margin = $total_revenue > 0 ? ($gross_profit / $total_revenue) * 100 : 0;
$net_margin = $total_revenue > 0 ? ($net_income / $total_revenue) * 100 : 0;
$expense_ratio = $total_revenue > 0 ? ($operating_expenses / $total_revenue) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Statement - Arko Flavors</title>
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
            color: #222e3c;
            margin-bottom: 0.5rem;
        }

        .summary-card .description {
            font-size: 0.85rem;
            color: #888;
        }

        .summary-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
        }

        .income-statement-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .statement-header {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .income-statement-header h2 {
            color: #222e3c;
            font-size: 1.5rem;
            margin-top: 0.5rem;
            /* Add spacing between date and title */
        }

        .report-date {
            color: #666;
            font-size: 1rem;
        }

        .income-statement-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        .income-statement-table th {
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid #222e3c;
            color: #222e3c;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .income-statement-table td {
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
            color: #555;
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
            font-family: 'Courier New', monospace;
        }

        .positive {
            color: #2ecc71;
        }

        .negative {
            color: #e74c3c;
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
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            border: 2px solid #d4edda;
        }

        .period-info h3 {
            color: #2ecc71;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .period-info p {
            color: #666;
            margin: 0;
        }

        .performance-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .metric-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #4361ee;
        }

        .metric-card h4 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .metric-description {
            font-size: 0.8rem;
            color: #888;
        }

        .print-btn {
            background: #2ecc71;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .print-btn:hover {
            background: #27ae60;
            transform: translateY(-2px);
        }

        @media (max-width: 1024px) {
            .summary-cards {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
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

            .income-statement-container {
                overflow-x: auto;
            }

            .date-filter {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-select {
                min-width: auto;
            }

            .statement-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .performance-metrics {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .main-content,
            .main-content * {
                visibility: visible;
            }

            .main-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 1rem;
            }

            .date-filter,
            .print-btn {
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php include '../../adminSidebar.php'; ?>

    <div class="main-content">
                <?php include '../admin_header.php'; ?>


        <div class="period-info" data-aos="zoom-in" data-aos-duration="1000">
            <h3>Reporting Period: <?php echo $period_label; ?></h3>
            <p>From <?php echo date('M j, Y', strtotime($start_date)); ?> to
                <?php echo date('M j, Y', strtotime($end_date)); ?>
            </p>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards"  data-aos="zoom-in" data-aos-duration="1000">
            <div class="summary-card">
                <h3>TOTAL REVENUE</h3>
                <div class="value">₱<?php echo number_format($total_revenue, 2); ?></div>
                <div class="description">💰 Total sales income</div>
            </div>
            <div class="summary-card">
                <h3>GROSS PROFIT</h3>
                <div class="value <?php echo $gross_profit >= 0 ? 'positive' : 'negative'; ?>">
                    ₱<?php echo number_format($gross_profit, 2); ?>
                </div>
                <div class="description">📈 After cost of goods</div>
            </div>
            <div class="summary-card">
                <h3>NET INCOME</h3>
                <div class="value <?php echo $net_income >= 0 ? 'positive' : 'negative'; ?>">
                    ₱<?php echo number_format($net_income, 2); ?>
                </div>
                <div class="description">🏢 Final profit/loss</div>
            </div>
            <div class="summary-card">
                <h3>OPERATING EXPENSES</h3>
                <div class="value">₱<?php echo number_format($operating_expenses, 2); ?></div>
                <div class="description">📊 Business costs</div>
            </div>
        </div>

        <div class="date-filter"  data-aos="fade-right" data-aos-duration="1000">
            <select class="filter-select" id="periodSelect">
                <option value="current_month" <?php echo $period === 'current_month' ? 'selected' : ''; ?>>Current Month
                </option>
                <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                <option value="last_quarter" <?php echo $period === 'last_quarter' ? 'selected' : ''; ?>>Last Quarter
                </option>
                <option value="last_year" <?php echo $period === 'last_year' ? 'selected' : ''; ?>>Last Year</option>
            </select>
            <button class="apply-btn" onclick="applyFilter()">Apply Filter</button>
            <button class="print-btn" onclick="window.print()">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="lucide lucide-import-icon lucide-import">
                    <path d="M12 3v12" />
                    <path d="m8 11 4 4 4-4" />
                    <path d="M8 5H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-4" />
                </svg>
                Print Statement
            </button>
        </div>

        <div class="income-statement-container"  data-aos="fade-up" data-aos-duration="1000">
            <div class="statement-header">
                <h2>Income Statement</h2>
                <div class="report-date">As of <?php echo date('F j, Y'); ?></div>
            </div>

            <table class="income-statement-table">
                <!-- Revenue Section -->
                <thead>
                    <tr>
                        <th colspan="2">REVENUE</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Food Sales</td>
                        <td class="amount positive">₱<?php echo number_format($total_revenue, 2); ?></td>
                    </tr>
                    <tr class="section-header">
                        <td><strong>Total Revenue</strong></td>
                        <td class="amount"><strong>₱<?php echo number_format($total_revenue, 2); ?></strong></td>
                    </tr>
                </tbody>

                <!-- Cost of Goods Sold -->
                <thead>
                    <tr>
                        <th colspan="2">COST OF GOODS SOLD</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="subsection">Food Ingredients</td>
                        <td class="amount negative">₱<?php echo number_format($cogs, 2); ?></td>
                    </tr>
                    <tr class="section-header">
                        <td><strong>Gross Profit</strong></td>
                        <td class="amount <?php echo $gross_profit >= 0 ? 'positive' : 'negative'; ?>">
                            <strong>₱<?php echo number_format($gross_profit, 2); ?></strong>
                        </td>
                    </tr>
                </tbody>

                <!-- Operating Expenses -->
                <thead>
                    <tr>
                        <th colspan="2">OPERATING EXPENSES</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="subsection">Operating Expenses</td>
                        <td class="amount negative">₱<?php echo number_format($total_expenses, 2); ?></td>
                    </tr>
                    <tr class="section-header">
                        <td><strong>Total Operating Expenses</strong></td>
                        <td class="amount negative">
                            <strong>₱<?php echo number_format($operating_expenses, 2); ?></strong>
                        </td>
                    </tr>
                </tbody>

                <!-- Net Income -->
                <tbody>
                    <tr class="total-row">
                        <td><strong>NET INCOME</strong></td>
                        <td class="amount">
                            <strong class="<?php echo $net_income >= 0 ? 'positive' : 'negative'; ?>">
                                ₱<?php echo number_format($net_income, 2); ?>
                            </strong>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Performance Metrics -->
            <div class="performance-metrics">
                <div class="metric-card">
                    <h4>Gross Margin</h4>
                    <div class="metric-value"
                        style="color: <?php echo $gross_margin >= 30 ? '#2ecc71' : '#e74c3c'; ?>;">
                        <?php echo number_format($gross_margin, 1); ?>%
                    </div>
                    <div class="metric-description">Profit after COGS</div>
                </div>
                <div class="metric-card">
                    <h4>Net Margin</h4>
                    <div class="metric-value" style="color: <?php echo $net_margin >= 0 ? '#2ecc71' : '#e74c3c'; ?>;">
                        <?php echo number_format($net_margin, 1); ?>%
                    </div>
                    <div class="metric-description">Final profit percentage</div>
                </div>
                <div class="metric-card">
                    <h4>Expense Ratio</h4>
                    <div class="metric-value" style="color: #222e3c;">
                        <?php echo number_format($expense_ratio, 1); ?>%
                    </div>
                    <div class="metric-description">Cost to revenue ratio</div>
                </div>
                <div class="metric-card">
                    <h4>COGS Ratio</h4>
                    <div class="metric-value" style="color: #222e3c;">
                        <?php echo $total_revenue > 0 ? number_format(($cogs / $total_revenue) * 100, 1) : '0'; ?>%
                    </div>
                    <div class="metric-description">Cost of goods percentage</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function applyFilter() {
            const period = document.getElementById('periodSelect').value;
            window.location.href = `?period=${period}`;
        }

        // Add hover effects to table rows
        document.addEventListener('DOMContentLoaded', function () {
            const rows = document.querySelectorAll('.income-statement-table tr:not(.section-header):not(.total-row)');
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