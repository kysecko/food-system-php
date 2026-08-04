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

// Handle filter parameters
$period = $_GET['period'] ?? 'current';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Set date ranges based on period
$current_date = date('Y-m-d');
switch ($period) {
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('-1 month'));
        $end_date = date('Y-m-t', strtotime('-1 month'));
        $report_date = date('F Y', strtotime('-1 month'));
        break;
    case 'last_quarter':
        $current_quarter = ceil(date('n') / 3);
        $year = date('Y');
        if ($current_quarter == 1) {
            $start_date = date('Y-10-01', strtotime('-1 year'));
            $end_date = date('Y-12-31', strtotime('-1 year'));
            $report_date = 'Q4 ' . ($year - 1);
        } else {
            $quarter_start_month = (($current_quarter - 2) * 3) + 1;
            $start_date = date("Y-{$quarter_start_month}-01", strtotime('-3 months'));
            $end_date = date("Y-m-t", strtotime($start_date . ' +2 months'));
            $report_date = 'Q' . ($current_quarter - 1) . ' ' . $year;
        }
        break;
    case 'last_year':
        $last_year = date('Y') - 1;
        $start_date = $last_year . '-01-01';
        $end_date = $last_year . '-12-31';
        $report_date = $last_year;
        break;
    case 'custom':
        if ($start_date && $end_date) {
            $report_date = date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date));
        } else {
            $period = 'current';
            $start_date = date('Y-m-01');
            $end_date = $current_date;
            $report_date = date('F j, Y');
        }
        break;
    default: // current
        $start_date = date('Y-m-01');
        $end_date = $current_date;
        $report_date = date('F j, Y');
        break;
}

// Calculate balance sheet data with date filters
// Assets
$cash_query = "SELECT COALESCE(SUM(cash_in - cash_out), 0) as cash_balance FROM cashflow WHERE date BETWEEN :start_date AND :end_date";
$cash_stmt = $pdo->prepare($cash_query);
$cash_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$cash_balance = $cash_stmt->fetch(PDO::FETCH_ASSOC)['cash_balance'];

// Inventory value calculation
$inventory_query = "SELECT COALESCE(SUM(CAST(prices AS DECIMAL(10,2))), 0) as inventory_value FROM menu_items WHERE is_available = 1";
$inventory_stmt = $pdo->query($inventory_query);
$inventory_value = $inventory_stmt->fetch(PDO::FETCH_ASSOC)['inventory_value'];

// Calculate Liabilities with date filters
$accounts_payable_query = "SELECT COALESCE(SUM(amount), 0) as accounts_payable 
                          FROM expenses 
                          WHERE category = 'account payable' 
                          AND status != 'paid'
                          AND expense_date BETWEEN :start_date AND :end_date";
$accounts_payable_stmt = $pdo->prepare($accounts_payable_query);
$accounts_payable_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$accounts_payable = $accounts_payable_stmt->fetch(PDO::FETCH_ASSOC)['accounts_payable'];

$loans_query = "SELECT 
                COALESCE(SUM(CASE WHEN category = 'short-term' THEN amount ELSE 0 END), 0) as short_term_loans,
                COALESCE(SUM(CASE WHEN category = 'long-term' THEN amount ELSE 0 END), 0) as long_term_loans,
                COALESCE(SUM(CASE WHEN category = 'note payable' THEN amount ELSE 0 END), 0) as notes_payable
               FROM expenses 
               WHERE (category IN ('short-term', 'long-term', 'note payable'))
               AND status != 'paid'
               AND expense_date BETWEEN :start_date AND :end_date";
$loans_stmt = $pdo->prepare($loans_query);
$loans_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$loans = $loans_stmt->fetch(PDO::FETCH_ASSOC);

$short_term_loans = $loans['short_term_loans'];
$long_term_loans = $loans['long_term_loans'];
$notes_payable = $loans['notes_payable'];

$total_liabilities = $accounts_payable + $short_term_loans + $long_term_loans + $notes_payable;

// Revenue calculation with date filters
$total_revenue_query = "SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM completed_orders WHERE order_date BETWEEN :start_date AND :end_date";
$total_revenue_stmt = $pdo->prepare($total_revenue_query);
$total_revenue_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$total_revenue = $total_revenue_stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'];

$total_expenses_query = "SELECT COALESCE(SUM(cash_out), 0) as total_expenses FROM cashflow WHERE date BETWEEN :start_date AND :end_date";
$total_expenses_stmt = $pdo->prepare($total_expenses_query);
$total_expenses_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$total_expenses = $total_expenses_stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'];

$net_income = $total_revenue - $total_expenses;

// Total Assets and Liabilities + Equity
$total_assets = $cash_balance + $inventory_value;
$total_liabilities_equity = $total_liabilities + $net_income;
?>

<?php
require_once '../../Log-in Form/includes/config_session.inc.php';
require_once '../../Log-in Form/includes/dbh.inc.php';

/* ----- AUTH ----- */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../Log-in Form/login.php');
    exit;
}
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() !== 'admin') {
    header('Location: ../../user dashboard/userDashboard.php');
    exit;
}

/* ----- DATE FILTER (same as before) ----- */
$period = $_GET['period'] ?? 'current';
/* … (keep the whole date-logic you already have) … */

/* -------------------------------------------------
   1. CASH (cash_in – cash_out)  – taken from cashflow
   ------------------------------------------------- */
$cash_q = "SELECT COALESCE(SUM(cash_in - cash_out),0) AS cash_balance
           FROM cashflow
           WHERE date BETWEEN ? AND ?";
$cash_s = $pdo->prepare($cash_q);
$cash_s->execute([$start_date, $end_date]);
$cash_balance = $cash_s->fetchColumn();

/* -------------------------------------------------
   2. INVENTORY (same as before)
   ------------------------------------------------- */
$inv_q = "SELECT COALESCE(SUM(CAST(prices AS DECIMAL(10,2))),0) AS inventory_value
          FROM menu_items WHERE is_available = 1";
$inventory_value = $pdo->query($inv_q)->fetchColumn();

/* -------------------------------------------------
   3. EXPENSES – split Current / Non-Current
        • rent, utilities, supplies → CURRENT
        • everything else → NON-CURRENT
   ------------------------------------------------- */
$exp_q = "
    SELECT category, SUM(amount) AS amt
    FROM expenses
    WHERE status = 'paid'
      AND expense_date BETWEEN ? AND ?
    GROUP BY category";
$exp_s = $pdo->prepare($exp_q);
$exp_s->execute([$start_date, $end_date]);
$exp_rows = $exp_s->fetchAll(PDO::FETCH_KEY_PAIR);

$current_exp   = 0;
$noncurrent_exp = 0;
foreach ($exp_rows as $cat => $amt) {
    if (in_array($cat, ['rent expense', 'utilities expenses', 'supplies expenses'])) {
        $current_exp   += $amt;
    } else {
        $noncurrent_exp += $amt;
    }
}

/* -------------------------------------------------
   4. LIABILITIES – split Current / Non-Current
        • account payable, short-term, note payable → CURRENT
        • long-term → NON-CURRENT
   ------------------------------------------------- */
$liab_q = "
    SELECT category, SUM(amount) AS amt
    FROM expenses
    WHERE status != 'paid'
      AND expense_date BETWEEN ? AND ?
    GROUP BY category";
$liab_s = $pdo->prepare($liab_q);
$liab_s->execute([$start_date, $end_date]);
$liab_rows = $liab_s->fetchAll(PDO::FETCH_KEY_PAIR);

$current_liab = 0;
$noncurrent_liab = 0;
foreach ($liab_rows as $cat => $amt) {
    if (in_array($cat, ['account payable', 'short-term', 'note payable'])) {
        $current_liab += $amt;
    } elseif ($cat === 'long-term') {
        $noncurrent_liab += $amt;
    }
}

/* -------------------------------------------------
   5. REVENUE (from completed_orders – same as sales.php)
   ------------------------------------------------- */
$rev_q = "SELECT COALESCE(SUM(total_amount),0) AS revenue
          FROM completed_orders
          WHERE order_date BETWEEN ? AND ?";
$rev_s = $pdo->prepare($rev_q);
$rev_s->execute([$start_date, $end_date]);
$revenue = $rev_s->fetchColumn();

/* -------------------------------------------------
   6. NET INCOME = Revenue – Total Paid Expenses
   ------------------------------------------------- */
$total_paid_exp = $current_exp + $noncurrent_exp;
$net_income = $revenue - $total_paid_exp;

/* -------------------------------------------------
   7. TOTALS
   ------------------------------------------------- */
$total_current_assets   = $cash_balance + $inventory_value + $current_exp;   // expenses are *assets* when paid
$total_noncurrent_assets = $noncurrent_exp;
$total_assets           = $total_current_assets + $total_noncurrent_assets;

$total_current_liab     = $current_liab;
$total_noncurrent_liab  = $noncurrent_liab;
$total_liabilities      = $total_current_liab + $total_noncurrent_liab;

$total_liab_equity = $total_liabilities + $net_income;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Balance Sheet – Arko Flavors</title>
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
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
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

        .balance-sheet-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .balance-sheet-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .balance-sheet-header h2 {
            color: #222e3c;
            font-size: 1.5rem;
            margin-top: 0.5rem;
        }

        .report-date {
            color: #666;
            font-size: 1rem;
        }

        .balance-sheet-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        .balance-sheet-table th {
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid #222e3c;
            color: #222e3c;
            font-weight: 600;
            font-size: 1rem;
        }

        .balance-sheet-table td {
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
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }

        .filter-select,
        .filter-input {
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            font-size: 0.9rem;
            min-width: 200px;
        }

        .filter-input {
            min-width: 150px;
        }

        .custom-date-range {
            display: flex;
            gap: 0.5rem;
            align-items: center;
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
            height: fit-content;
            margin-top: 1.5rem;
        }

        .apply-btn:hover {
            background: #3a56e0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .balance-status {
            text-align: center;
            margin-top: 2rem;
            padding: 1.5rem;
            border-radius: 12px;
            background: <?php echo ($total_assets == $total_liabilities_equity) ? '#e8f8f0' : '#fff4e6'; ?>;
            border: 2px solid <?php echo ($total_assets == $total_liabilities_equity) ? '#d4edda' : '#ffeaa7'; ?>;
        }

        .balance-status h3 {
            color: <?php echo ($total_assets == $total_liabilities_equity) ? '#2ecc71' : '#e67e22'; ?>;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .balance-status p {
            color: #666;
            font-size: 0.9rem;
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
            height: fit-content;
            margin-top: 1.5rem;
        }

        .print-btn:hover {
            background: #27ae60;
            transform: translateY(-2px);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }

        @media (max-width: 1024px) {
            .summary-cards {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 60px;
                width: calc(100% - 60px);
                padding: 1rem;
            }

            .date-filter {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-actions {
                align-self: stretch;
                justify-content: space-between;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }

            .balance-sheet-container {
                overflow-x: auto;
            }

            .filter-select,
            .filter-input {
                min-width: auto;
            }

            .balance-sheet-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .financial-ratios {
                grid-template-columns: 1fr;
            }

            .custom-date-range {
                flex-direction: column;
                align-items: stretch;
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

        /* (keep all your existing styles – only add the centering for modals if you copy them here) */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, .6);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
        }
    </style>
</head>

<body>
    <?php include '../../adminSidebar.php'; ?>
    <div class="main-content">
        <?php include '../admin_header.php'; ?>

        <!-- SUMMARY CARDS (updated) -->
        <div class="summary-cards">
            <div class="summary-card">
                <h3>Total Assets</h3>
                <div class="value">₱<?= number_format($total_assets, 2) ?></div>
            </div>
            <div class="summary-card">
                <h3>Total Liabilities</h3>
                <div class="value">₱<?= number_format($total_liabilities, 2) ?></div>
            </div>
            <div class="summary-card">
                <h3>Net Income</h3>
                <div class="value <?= $net_income >= 0 ? 'positive' : 'negative' ?>">₱<?= number_format($net_income, 2) ?></div>
            </div>
            <div class="summary-card">
                <h3>Equity</h3>
                <div class="value">₱<?= number_format($net_income, 2) ?></div>
            </div>
        </div>

        <!-- DATE FILTER (unchanged) -->
        <form method="GET" class="date-filter" data-aos="fade-right" data-aos-duration="1000">
            <div class="filter-group">
                <label for="periodSelect">Time Period</label>
                <select class="filter-select" id="periodSelect" name="period" onchange="toggleCustomDates()">
                    <option value="current" <?php echo $period === 'current' ? 'selected' : ''; ?>>Current Month</option>
                    <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                    <option value="last_quarter" <?php echo $period === 'last_quarter' ? 'selected' : ''; ?>>Last Quarter</option>
                    <option value="last_year" <?php echo $period === 'last_year' ? 'selected' : ''; ?>>Last Year</option>
                    <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>

            <div class="filter-group" id="customDateRange" style="display: <?php echo $period === 'custom' ? 'flex' : 'none'; ?>; flex-direction: column; gap: 0.5rem;">
                <label>Custom Date Range</label>
                <div class="custom-date-range">
                    <input type="date" class="filter-input" name="start_date" value="<?php echo $start_date; ?>" placeholder="Start Date">
                    <span>to</span>
                    <input type="date" class="filter-input" name="end_date" value="<?php echo $end_date; ?>" placeholder="End Date">
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="apply-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 5H2" />
                        <path d="M6 12h12" />
                        <path d="M9 19h6" />
                        <path d="M16 5h6" />
                        <path d="M19 8V2" />
                    </svg>
                    Apply Filter
                </button>
                <button type="button" class="print-btn" onclick="window.print()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3v12" />
                        <path d="m8 11 4 4 4-4" />
                        <path d="M8 5H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-4" />
                    </svg>
                    Print Report
                </button>
            </div>
        </form>


        <div class="balance-sheet-container">
            <div class="balance-sheet-header">
                <h2>Balance Sheet</h2>
                <div class="report-date">As of <?= $report_date ?></div>
            </div>

            <table class="balance-sheet-table">
                <!-- ==== ASSETS ==== -->
                <thead>
                    <tr>
                        <th colspan="2">ASSETS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="section-header">
                        <td>Current Assets</td>
                        <td class="amount"></td>
                    </tr>
                    <tr>
                        <td class="subsection">Cash & Cash Equivalents</td>
                        <td class="amount positive">₱<?= number_format($cash_balance, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="subsection">Inventory</td>
                        <td class="amount positive">₱<?= number_format($inventory_value, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="subsection">Paid Current Expenses</td>
                        <td class="amount positive">₱<?= number_format($current_exp, 2) ?></td>
                    </tr>
                    <tr class="section-header">
                        <td><strong>Total Current Assets</strong></td>
                        <td class="amount"><strong>₱<?= number_format($total_current_assets, 2) ?></strong></td>
                    </tr>

                    <tr class="section-header">
                        <td>Non-Current Assets</td>
                        <td class="amount"></td>
                    </tr>
                    <tr>
                        <td class="subsection">Paid Non-Current Expenses</td>
                        <td class="amount positive">₱<?= number_format($noncurrent_exp, 2) ?></td>
                    </tr>
                    <tr class="section-header">
                        <td><strong>Total Non-Current Assets</strong></td>
                        <td class="amount"><strong>₱<?= number_format($total_noncurrent_assets, 2) ?></strong></td>
                    </tr>

                    <tr class="total-row">
                        <td><strong>Total Assets</strong></td>
                        <td class="amount"><strong>₱<?= number_format($total_assets, 2) ?></strong></td>
                    </tr>
                </tbody>

                <!-- ==== LIABILITIES & EQUITY ==== -->
                <thead>
                    <tr>
                        <th colspan="2">LIABILITIES & EQUITY</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="section-header">
                        <td>Current Liabilities</td>
                        <td class="amount"></td>
                    </tr>
                    <tr>
                        <td class="subsection">Accounts Payable</td>
                        <td class="amount negative">₱<?= number_format($liab_rows['account payable'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="subsection">Short-Term Loans</td>
                        <td class="amount negative">₱<?= number_format($liab_rows['short-term'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="subsection">Notes Payable</td>
                        <td class="amount negative">₱<?= number_format($liab_rows['note payable'] ?? 0, 2) ?></td>
                    </tr>
                    <tr class="section-header">
                        <td><strong>Total Current Liabilities</strong></td>
                        <td class="amount"><strong>₱<?= number_format($total_current_liab, 2) ?></strong></td>
                    </tr>

                    <tr class="section-header">
                        <td>Non-Current Liabilities</td>
                        <td class="amount"></td>
                    </tr>
                    <tr>
                        <td class="subsection">Long-Term Loans</td>
                        <td class="amount negative">₱<?= number_format($liab_rows['long-term'] ?? 0, 2) ?></td>
                    </tr>
                    <tr class="section-header">
                        <td><strong>Total Non-Current Liabilities</strong></td>
                        <td class="amount"><strong>₱<?= number_format($total_noncurrent_liab, 2) ?></strong></td>
                    </tr>

                    <tr class="section-header">
                        <td>Equity</td>
                        <td class="amount"></td>
                    </tr>
                    <tr>
                        <td class="subsection">Retained Earnings (Net Income)</td>
                        <td class="amount <?= $net_income >= 0 ? 'positive' : 'negative' ?>">₱<?= number_format($net_income, 2) ?></td>
                    </tr>

                    <tr class="total-row">
                        <td><strong>Total Liabilities & Equity</strong></td>
                        <td class="amount"><strong>₱<?= number_format($total_liab_equity, 2) ?></strong></td>
                    </tr>
                </tbody>
            </table>

            <!-- BALANCE CHECK -->
            <div class="balance-status" style="background:<?= $total_assets == $total_liab_equity ? '#e8f8f0' : '#fff4e6' ?>;border:2px solid <?= $total_assets == $total_liab_equity ? '#d4edda' : '#ffeaa7' ?>;">
                <h3><?= $total_assets == $total_liab_equity ? 'Balance Sheet Balanced' : 'Balance Sheet Imbalanced' ?></h3>
                <p>Assets: ₱<?= number_format($total_assets, 2) ?> | Liabilities + Equity: ₱<?= number_format($total_liab_equity, 2) ?></p>
            </div>
        </div>
    </div>
</body>

</html>

<script>
    function toggleCustomDates() {
        const periodSelect = document.getElementById('periodSelect');
        const customDateRange = document.getElementById('customDateRange');

        if (periodSelect.value === 'custom') {
            customDateRange.style.display = 'flex';
        } else {
            customDateRange.style.display = 'none';
        }
    }

    // Add hover effects to table rows
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('.balance-sheet-table tr:not(.section-header):not(.total-row)');
        rows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fa';
            });
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
    });

    // Set max date for custom date inputs to today
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        const endDateInput = document.querySelector('input[name="end_date"]');
        if (endDateInput) {
            endDateInput.max = today;
        }

        const startDateInput = document.querySelector('input[name="start_date"]');
        if (startDateInput && endDateInput.value) {
            startDateInput.max = endDateInput.value;
        }
    });
</script>
</body>

</html>