<?php
/* -------------------------------------------------------------
   daily_report.php
   Daily Sales & Expense Summary with Date Filter
   CORRECTED: Uses `expenses` table for categories
   ------------------------------------------------------------- */
require_once '../Log-in Form/includes/config_session.inc.php';
require_once '../Log-in Form/includes/dbh.inc.php';

/* ---------- AUTHENTICATION ---------- */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../Log-in Form/login.php');
    exit;
}
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id');
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['role'] !== 'admin') {
    header('Location: ../user dashboard/userDashboard.php');
    exit;
}

/* ---------- FILTER LOGIC ---------- */
$filter = $_GET['filter'] ?? 'today';
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$report_title = '';

switch ($filter) {
    case 'yesterday':
        $start_date = $end_date = date('Y-m-d', strtotime('-1 day'));
        $report_title = 'Yesterday (' . date('M j, Y', strtotime($start_date)) . ')';
        break;
    case 'last7':
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-6 days'));
        $report_title = 'Last 7 Days';
        break;
    case 'custom':
        if ($start_date && $end_date && $start_date <= $end_date) {
            $report_title = date('M j', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date));
        } else {
            $filter = 'today';
            $start_date = $end_date = date('Y-m-d');
            $report_title = 'Today (' . date('M j, Y') . ')';
        }
        break;
    case 'today':
    default:
        $start_date = $end_date = date('Y-m-d');
        $report_title = 'Today (' . date('M j, Y') . ')';
        break;
}

/* ---------- DATA QUERIES ---------- */

/* 1. SALES */
$sales_stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(total_amount), 0) AS total_sales,
        COUNT(*) AS order_count,
        COALESCE(AVG(total_amount), 0) AS avg_order
    FROM completed_orders 
    WHERE DATE(order_date) BETWEEN :start AND :end
");
$sales_stmt->execute([':start' => $start_date, ':end' => $end_date]);
$sales = $sales_stmt->fetch(PDO::FETCH_ASSOC);

$total_sales = $sales['total_sales'];
$order_count = $sales['order_count'];
$avg_order   = $sales['avg_order'];

/* 2. TOTAL EXPENSES (from cashflow) */
$exp_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(cash_out), 0) AS total_expenses
    FROM cashflow 
    WHERE DATE(date) BETWEEN :start AND :end
");
$exp_stmt->execute([':start' => $start_date, ':end' => $end_date]);
$total_expenses = $exp_stmt->fetchColumn();

/* 3. TOP EXPENSE CATEGORIES (from `expenses` table) */
$cat_stmt = $pdo->prepare("
    SELECT category, SUM(amount) AS amount
    FROM expenses 
    WHERE expense_date BETWEEN :start AND :end
    GROUP BY category
    ORDER BY amount DESC
    LIMIT 3
");
$cat_stmt->execute([':start' => $start_date, ':end' => $end_date]);
$top_categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

/* 4. CASH FLOW */
$cash_in_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(cash_in), 0) FROM cashflow 
    WHERE DATE(date) BETWEEN :start AND :end
");
$cash_in_stmt->execute([':start' => $start_date, ':end' => $end_date]);
$cash_in = $cash_in_stmt->fetchColumn();

$net_cash_flow = $cash_in - $total_expenses;

/* 5. Ending Cash Balance (Cumulative up to end_date) */
$ending_cash_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(cash_in - cash_out), 0) 
    FROM cashflow 
    WHERE date <= :end
");
$ending_cash_stmt->execute([':end' => $end_date]);
$ending_cash = $ending_cash_stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Report - Arko Flavors</title>
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
            background: #f5f7fa;
        }

        .main-content {
            margin-left: 220px;
            padding: 2rem;
            width: calc(100%-220px);
            background: #f5f7fa;
        }

        .page-header h1 {
            font-size: 2rem;
            color: #222e3c;
            margin-bottom: .5rem;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
            transition: .3s;
        }

        .summary-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, .12);
        }

        .summary-card h3 {
            font-size: .9rem;
            color: #666;
            margin-bottom: .5rem;
        }

        .summary-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #222e3c;
        }

        .summary-card .description {
            font-size: .85rem;
            color: #888;
        }

        .positive {
            color: #2ecc71;
        }

        .negative {
            color: #e74c3c;
        }

        .report-container {
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
            margin-bottom: 2rem;
        }

        .report-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .report-header h2 {
            font-size: 1.5rem;
            color: #222e3c;
        }

        .report-date {
            color: #666;
            font-size: 1rem;
        }

        .date-filter {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
            background: #fff;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }

        .filter-group label {
            font-size: .9rem;
            color: #666;
            font-weight: 500;
        }

        .filter-select,
        .filter-input {
            padding: .75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
            font-size: .9rem;
            min-width: 180px;
        }

        .apply-btn,
        .print-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #4361ee;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: .75rem 1.5rem;
            cursor: pointer;
            font-weight: 500;
            transition: .3s;
        }

        .print-btn {
            background: #2ecc71;
        }

        .apply-btn:hover {
            background: #3a56e0;
            transform: translateY(-2px);
        }

        .print-btn:hover {
            background: #27ae60;
            transform: translateY(-2px);
        }

        .section {
            margin-bottom: 2rem;
        }

        .section h3 {
            font-size: 1.2rem;
            color: #222e3c;
            margin-bottom: 1rem;
            border-bottom: 1px solid #eee;
            padding-bottom: .5rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .data-table th,
        .data-table td {
            padding: .75rem 1rem;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .data-table th {
            background: #f8f9fa;
            color: #222e3c;
            font-weight: 600;
        }

        .data-table .amount {
            text-align: right;
            font-family: 'Courier New', monospace;
        }

        .top-categories {
            list-style: none;
            padding: 0;
        }

        .top-categories li {
            display: flex;
            justify-content: space-between;
            padding: .5rem 0;
            border-bottom: 1px dashed #ddd;
        }

        .top-categories li:last-child {
            border: none;
        }

        @media (max-width:900px) {
            .main-content {
                margin-left: 60px;
                width: calc(100%-60px);
            }

            .date-filter {
                flex-direction: column;
                align-items: stretch;
            }
        }

        @media (max-width:768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .summary-cards {
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
    <?php include '../adminSidebar.php'; ?>
    <div class="main-content">
        <?php include 'admin_header.php'; ?>

        <!-- SUMMARY CARDS -->
        <div class="summary-cards">
            <div class="summary-card">
                <h3>TOTAL SALES</h3>
                <div class="value positive">₱<?= number_format($total_sales, 2) ?></div>
                <div class="description"><?= $order_count ?> orders</div>
            </div>
            <div class="summary-card">
                <h3>AVG ORDER VALUE</h3>
                <div class="value">₱<?= number_format($avg_order, 2) ?></div>
                <div class="description">Per completed order</div>
            </div>
            <div class="summary-card">
                <h3>TOTAL EXPENSES</h3>
                <div class="value negative">₱<?= number_format($total_expenses, 2) ?></div>
                <div class="description">Cash outflows</div>
            </div>
            <div class="summary-card">
                <h3>NET CASH FLOW</h3>
                <div class="value <?= $net_cash_flow >= 0 ? 'positive' : 'negative' ?>">
                    ₱<?= number_format($net_cash_flow, 2) ?>
                </div>
                <div class="description">In - Out</div>
            </div>
        </div>

        <!-- FILTER -->
        <form method="GET" class="date-filter">
            <div class="filter-group">
                <label for="filter">Quick Filter</label>
                <select class="filter-select" id="filter" name="filter" onchange="toggleCustom()">
                    <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="yesterday" <?= $filter === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                    <option value="last7" <?= $filter === 'last7' ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="custom" <?= $filter === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                </select>
            </div>
            <div class="filter-group" id="customRange" style="display:<?= $filter === 'custom' ? 'flex' : 'none' ?>;">
                <label>From - To</label>
                <div style="display:flex;gap:.5rem;align-items:center;">
                    <input type="date" class="filter-input" name="start_date" value="<?= htmlspecialchars($start_date) ?>" max="<?= date('Y-m-d') ?>">
                    <span>to</span>
                    <input type="date" class="filter-input" name="end_date" value="<?= htmlspecialchars($end_date) ?>" max="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div style="display:flex;gap:1rem;align-items:flex-end;">
                <button type="submit" class="apply-btn">Apply</button>
                <button type="button" class="print-btn" onclick="window.print()">Print</button>
            </div>
        </form>

        <!-- DETAILED REPORT -->
        <div class="report-container">
            <div class="report-header">
                <h2>Daily Operations Report</h2>
                <div class="report-date"><?= $report_title ?></div>
            </div>

            <!-- SALES BREAKDOWN -->
            <div class="section">
                <h3>Sales Summary</h3>
                <table class="data-table">
                    <tr>
                        <th>Metric</th>
                        <th class="amount">Amount</th>
                    </tr>
                    <tr>
                        <td>Total Sales</td>
                        <td class="amount positive">₱<?= number_format($total_sales, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Number of Orders</td>
                        <td class="amount"><?= $order_count ?></td>
                    </tr>
                    <tr>
                        <td>Average Order Value</td>
                        <td class="amount">₱<?= number_format($avg_order, 2) ?></td>
                    </tr>
                </table>
            </div>

            <!-- EXPENSE BREAKDOWN -->
            <div class="section">
                <h3>Expense Summary (by Category)</h3>
                <table class="data-table">
                    <tr>
                        <th>Category</th>
                        <th class="amount">Amount</th>
                    </tr>
                    <?php if (empty($top_categories)): ?>
                        <tr>
                            <td colspan="2" style="text-align:center;color:#888;">No categorized expenses</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($top_categories as $cat): ?>
                            <tr>
                                <td><?= ucwords(str_replace(['_payable', '_'], ' ', $cat['category'])) ?></td>
                                <td class="amount negative">₱<?= number_format($cat['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><strong>Total Cash Out</strong></td>
                            <td class="amount negative"><strong>₱<?= number_format($total_expenses, 2) ?></strong></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- CASH FLOW -->
            <div class="section">
                <h3>Cash Flow Summary</h3>
                <table class="data-table">
                    <tr>
                        <th>Description</th>
                        <th class="amount">Amount</th>
                    </tr>
                    <tr>
                        <td>Cash In</td>
                        <td class="amount positive">₱<?= number_format($cash_in, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Cash Out</td>
                        <td class="amount negative">₱<?= number_format($total_expenses, 2) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Net Cash Flow</strong></td>
                        <td class="amount <?= $net_cash_flow >= 0 ? 'positive' : 'negative' ?>"><strong>₱<?= number_format($net_cash_flow, 2) ?></strong></td>
                    </tr>
                    <tr>
                        <td>Ending Cash Balance</td>
                        <td class="amount">₱<?= number_format($ending_cash, 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleCustom() {
            const sel = document.getElementById('filter');
            document.getElementById('customRange').style.display = (sel.value === 'custom') ? 'flex' : 'none';
        }
        document.addEventListener('DOMContentLoaded', () => {
            const today = new Date().toISOString().split('T')[0];
            document.querySelectorAll('input[type="date"]').forEach(input => input.max = today);
        });
    </script>
</body>

</html>