<?php
require_once "../../Log-in Form/includes/config_session.inc.php";
require_once "../../Log-in Form/includes/dbh.inc.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../Log-in Form/login.php');
    exit;
}

// Handle AJAX requests for view/edit (return JSON)
if (isset($_GET['view_id']) && !empty($_GET['view_id'])) {
    $expense_id = $_GET['view_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE expense_id = ?");
        $stmt->execute([$expense_id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($expense);
        exit;
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

if (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) {
    $expense_id = $_GET['edit_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE expense_id = ?");
        $stmt->execute([$expense_id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($expense);
        exit;
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_bill') {
            // Add new bill
            $name = trim($_POST['name']);
            $no = trim($_POST['no']);
            $expense_date = $_POST['expense_date'];
            $due_date = $_POST['due_date'];
            $description = trim($_POST['description']);
            $amount = $_POST['amount'];
            $deposit_to = $_POST['deposit_to'];
            $category = $_POST['category'];
            $status = 'pending';

            try {
                $stmt = $pdo->prepare("INSERT INTO expenses (name, no, expense_date, due_date, description, amount, deposit_to, category, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $no, $expense_date, $due_date, $description, $amount, $deposit_to, $category, $status]);

                $_SESSION['success_message'] = 'Bill recorded successfully!';
            } catch (PDOException $e) {
                $_SESSION['error_message'] = 'Error recording bill: ' . $e->getMessage();
            }

            header("Location: expenses.php");
            exit;
        } elseif ($_POST['action'] === 'add_expense') {
            // Add new expense
            $name = trim($_POST['name']);
            $expense_date = $_POST['expense_date'];
            $description = trim($_POST['description']);
            $amount = $_POST['amount'];
            $category = $_POST['category'];
            $deposit_to = 'cash'; // Default for expenses
            $status = 'paid'; // Expenses are typically paid immediately

            $deposit_to = 'cash';          // always cash for expenses
            $status     = 'paid';          // expenses are paid immediately

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
            INSERT INTO expenses 
                (name, expense_date, description, amount, deposit_to, category, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $expense_date, $description, $amount, $deposit_to, $category, $status]);

                // 2. RECORD CASH-OUT in cashflow table (cash_out = amount)
                $cf = $pdo->prepare("
            INSERT INTO cashflow (date, cash_out, description, type)
            VALUES (?, ?, ?, 'expense')");
                $cf->execute([$expense_date, $amount, "Expense: $name"]);

                $pdo->commit();

                $_SESSION['success_message'] = 'Business expense recorded successfully!';
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
            }

            // *** NEW *** redirect to balance sheet
            header("Location: balancesheet.php");
            exit;
        }
    } elseif ($_POST['action'] === 'pay_bill') {
        // Pay bill
        $expense_id = $_POST['expense_id'];

        try {
            $stmt = $pdo->prepare("UPDATE expenses SET status = 'paid' WHERE expense_id = ?");
            $stmt->execute([$expense_id]);

            $_SESSION['success_message'] = 'Bill paid successfully!';
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Error paying bill: ' . $e->getMessage();
        }

        header("Location: expenses.php");
        exit;
    } elseif ($_POST['action'] === 'update_expense') {
        // Update expense
        $expense_id = $_POST['expense_id'];
        $name = trim($_POST['name']);
        $no = trim($_POST['no']);
        $expense_date = $_POST['expense_date'];
        $due_date = $_POST['due_date'];
        $description = trim($_POST['description']);
        $amount = $_POST['amount'];
        $deposit_to = $_POST['deposit_to'];
        $category = $_POST['category'];

        try {
            $stmt = $pdo->prepare("UPDATE expenses SET name = ?, no = ?, expense_date = ?, due_date = ?, description = ?, amount = ?, deposit_to = ?, category = ? WHERE expense_id = ?");
            $stmt->execute([$name, $no, $expense_date, $due_date, $description, $amount, $deposit_to, $category, $expense_id]);

            $_SESSION['success_message'] = 'Expense updated successfully!';
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Error updating expense: ' . $e->getMessage();
        }

        header("Location: expenses.php");
        exit;
    }
}


// Handle delete
if (isset($_GET['delete_id'])) {
    $expense_id = $_GET['delete_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE expense_id = ?");
        $stmt->execute([$expense_id]);

        $_SESSION['success_message'] = 'Expense deleted successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error deleting expense: ' . $e->getMessage();
    }

    header("Location: expenses.php");
    exit;
}

// Fetch expenses from database
try {
    $stmt = $pdo->prepare("SELECT * FROM expenses ORDER BY expense_date DESC");
    $stmt->execute();
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary statistics
    $total_expenses = 0;
    $monthly_expenses = 0;
    $weekly_expenses = 0;
    $unpaid_bills = 0;

    $current_month = date('Y-m');
    $current_week_start = date('Y-m-d', strtotime('monday this week'));
    $current_week_end = date('Y-m-d', strtotime('sunday this week'));

    foreach ($expenses as $expense) {
        $total_expenses += $expense['amount'];

        $expense_month = date('Y-m', strtotime($expense['expense_date']));
        if ($expense_month === $current_month) {
            $monthly_expenses += $expense['amount'];
        }

        if ($expense['expense_date'] >= $current_week_start && $expense['expense_date'] <= $current_week_end) {
            $weekly_expenses += $expense['amount'];
        }

        if ($expense['status'] === 'pending' || $expense['status'] === 'overdue') {
            $unpaid_bills += $expense['amount'];
        }
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses Management - Arko Flavors</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            color: #333;
        }

        .main-content {
            margin-left: 220px;
            padding: 2rem;
            min-height: 100vh;
            width: calc(100% - 220px);
            transition: all 0.3s ease;
        }

        .page-header {
            margin-bottom: 2rem;
            animation: fadeIn 0.8s ease;
        }

        .page-header h1 {
            color: #222e3c;
            margin-bottom: 0.5rem;
            font-size: 2.2rem;
            font-weight: 700;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
            font-weight: 400;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            animation: slideUp 0.6s ease;
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

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }

        .trend {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .trend.up {
            color: #2ecc71;
        }

        .trend.down {
            color: #e74c3c;
        }

        .summary-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
        }

        .summary-card h3 {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .summary-card .value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #222e3c;
            margin-bottom: 0.5rem;
        }

        /* Dropdown Styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .add-expense-btn {
            margin-left: 78%;
            background: linear-gradient(135deg, #4361ee, #3a56e0);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0.85rem 1.8rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .add-expense-btn:hover {
            background: linear-gradient(135deg, #3a56e0, #3046c4);
            transform: translateY(-3px);
            box-shadow: 0 6px 18px rgba(67, 97, 238, 0.4);
        }

        .dropdown-content {
            margin-left: 78%;
            display: none;
            position: absolute;
            background: white;
            min-width: 220px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
            border-radius: 12px;
            z-index: 1000;
            top: 100%;
            left: 0;
            margin-top: 0.5rem;
            overflow: hidden;
            animation: fadeIn 0.3s ease;
            border: 1px solid #f0f0f0;
        }

        .dropdown-content.show {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-item {
            padding: 0.9rem 1.2rem;
            color: #333;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            transition: all 0.2s;
            border-bottom: 1px solid #f0f0f0;
            font-weight: 500;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
            color: #4361ee;
        }

        /* Force table to have lower z-index */
        .expenses-table-container {
            background: white;
            border-radius: 16px;
            padding: 1.8rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            animation: slideUp 0.8s ease;
            overflow: hidden;
            position: relative;
            z-index: 1 !important;
        }

        .expenses-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1.5rem;
            animation: slideUp 0.7s ease;
            position: relative;
            z-index: 9999 !important;
        }

        .expenses-table {
            width: 100%;
            border-collapse: collapse;
        }

        .expenses-table th {
            text-align: left;
            padding: 1.2rem 1rem;
            border-bottom: 2px solid #4361ee;
            color: #222e3c;
            font-weight: 600;
            font-size: 1rem;
            background: rgba(67, 97, 238, 0.05);
        }

        .expenses-table td {
            padding: 1.2rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .expenses-table tr:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .amount {
            font-weight: 600;
            color: #e74c3c;
            text-align: right;
        }

        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .status-paid {
            background: linear-gradient(135deg, #e8f8f0, #d1f2e0);
            color: #2ecc71;
        }

        .status-unpaid {
            background: linear-gradient(135deg, #ffeaea, #ffd6d6);
            color: #e74c3c;
        }

        .status-pending {
            background: linear-gradient(135deg, #fff4e6, #ffe8cc);
            color: #e67e22;
        }

        .status-overdue {
            background: linear-gradient(135deg, #ffcccc, #ff9999);
            color: #cc0000;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }

        .empty-state div:first-child {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.7;
        }

        .empty-state h3 {
            color: #222e3c;
            margin-bottom: 0.8rem;
            font-size: 1.5rem;
        }

        .empty-state p {
            margin-bottom: 2rem;
            font-size: 1rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.6rem;
        }

        .btn {
            padding: 0.6rem 1rem;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .btn-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .btn-info:hover {
            background: linear-gradient(135deg, #2980b9, #2471a3);
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #27ae60, #219653);
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #e67e22, #d35400);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
            transform: translateY(-2px);
        }

        /* Modal Styles - FIXED VERSION */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: #fff;
            padding: 2.5rem;
            width: 90%;
            max-width: 650px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.4s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin: auto;
            /* Ensures centering */
        }

        /* Add this to ensure the modal stays centered */
        .modal.show {
            display: flex !important;
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 28px;
            cursor: pointer;
            color: #888;
            background: none;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: #f5f5f5;
            color: #333;
            transform: rotate(90deg);
        }

        .modal h2 {
            color: #222e3c;
            margin-bottom: 1.8rem;
            font-size: 1.8rem;
            font-weight: 700;
            padding-bottom: 0.8rem;
            border-bottom: 2px solid #4361ee;
        }

        .form-group {
            margin-bottom: 1.8rem;
        }

        .modal-label {
            display: block;
            margin-bottom: 0.6rem;
            font-weight: 600;
            color: #222e3c;
            font-size: 0.95rem;
        }

        .modal-input,
        .modal-select,
        .modal-textarea {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            font-size: 16px;
            box-sizing: border-box;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .modal-input:focus,
        .modal-select:focus,
        .modal-textarea:focus {
            outline: none;
            border-color: #4361ee;
            background: white;
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 2.2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }

        .submit-btn {
            background: linear-gradient(135deg, #4361ee, #3a56e0);
            border: none;
            color: #fff;
            padding: 0.9rem 2.2rem;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .cancel-btn {
            background: #6c757d;
            border: none;
            color: #fff;
            padding: 0.9rem 2.2rem;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #3a56e0, #3046c4);
            transform: translateY(-3px);
            box-shadow: 0 6px 18px rgba(67, 97, 238, 0.4);
        }

        .cancel-btn:hover {
            background: #5a6268;
            transform: translateY(-3px);
        }

        .search-box {
            display: flex;
            align-items: center;
            background: white;
            padding: 0.85rem 1.2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            flex: 1;
            max-width: 450px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .search-box:focus-within {
            border-color: #4361ee;
            box-shadow: 0 4px 16px rgba(67, 97, 238, 0.15);
        }

        .search-box input {
            border: none;
            outline: none;
            font-size: 0.95rem;
            width: 100%;
            color: #333;
            background: transparent;
        }

        .search-box input::placeholder {
            color: #999;
        }

        .alert {
            position: fixed;
            top: 30px;
            right: 30px;
            z-index: 1001;
            padding: 1.2rem 1.8rem;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 0.8rem;
            max-width: 450px;
            animation: slideInRight 0.4s ease;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.95), rgba(39, 174, 96, 0.95));
            color: white;
            border-left: 4px solid #27ae60;
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.95), rgba(192, 57, 43, 0.95));
            color: white;
            border-left: 4px solid #c0392b;
        }

        .alert-close {
            background: none;
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
            margin-left: auto;
            padding: 0;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            color: white;
        }

        .alert-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .loading-spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .summary-cards {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1.5rem;
            }

            .expenses-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }

            .main-content {
                padding: 1rem;
            }

            .expenses-table {
                font-size: 0.85rem;
            }

            .expenses-table th,
            .expenses-table td {
                padding: 0.8rem 0.6rem;
            }

            .modal-content {
                padding: 1.8rem;
                width: 95%;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .submit-btn,
            .cancel-btn {
                width: 100%;
                justify-content: center;
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 1.8rem;
            }

            .btn {
                padding: 0.5rem 0.8rem;
            }
        }

        .view-details {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
        }

        .detail-value {
            color: #212529;
            text-align: right;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
    </style>
</head>

<body>
    <?php include '../../adminSidebar.php'; ?>

    <div class="main-content">
        <?php include '../admin_header.php'; ?>

        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <?php echo $_SESSION['success_message']; ?>
                <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <span>⚠</span>
                <?php echo $_SESSION['error_message']; ?>
                <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="summary-cards" data-aos="zoom-in" data-aos-duration="1000">
            <div class="summary-card">
                <h3>TOTAL EXPENSES</h3>
                <div class="value">₱<?php echo number_format($total_expenses, 2); ?></div>
                <div class="trend down"><i></i> All-time expenses</div>
            </div>
            <div class="summary-card">
                <h3>THIS MONTH</h3>
                <div class="value">₱<?php echo number_format($monthly_expenses, 2); ?></div>
                <div class="trend down">Current month</div>
            </div>
            <div class="summary-card">
                <h3>THIS WEEK</h3>
                <div class="value">₱<?php echo number_format($weekly_expenses, 2); ?></div>
                <div class="trend down">Weekly spending</div>
            </div>
            <div class="summary-card">
                <h3>UNPAID BILLS</h3>
                <div class="value">₱<?php echo number_format($unpaid_bills, 2); ?></div>
                <div class="trend down">Pending payments</div>
            </div>
        </div>

        <!-- Actions Bar -->
        <div class="expenses-actions" data-aos="fade-left" data-aos-duration="1000">
            <div class="dropdown">
                <button class="add-expense-btn" id="dropdown-btn">
                    <i class="fas fa-plus"></i> Add New Expense
                </button>
                <div class="dropdown-content" id="dropdown-content">
                    <a href="#" class="dropdown-item" onclick="openModal('bill-modal')">
                        <i class="fas fa-file-invoice-dollar"></i> Record Bill Payment
                    </a>
                    <a href="#" class="dropdown-item" onclick="openModal('expense-modal')">
                        <i class="fas fa-receipt"></i> Record Business Expense
                    </a>
                </div>
            </div>
        </div>

        <!-- Expenses Table -->
        <div class="expenses-table-container" data-aos="fade-up" data-aos-duration="1000">
            <div class="table-responsive">
                <table class="expenses-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <div>💸</div>
                                        <h3>No Expenses Found</h3>
                                        <p>Get started by adding your first expense or bill!</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $expense): ?>
                                <?php
                                $status_class = '';
                                $status_icon = '';
                                switch ($expense['status']) {
                                    case 'paid':
                                        $status_class = 'status-paid';
                                        $status_icon = 'fa-check-circle';
                                        break;
                                    case 'pending':
                                        $status_class = 'status-pending';
                                        $status_icon = 'fa-clock';
                                        break;
                                    case 'overdue':
                                        $status_class = 'status-overdue';
                                        $status_icon = 'fa-exclamation-triangle';
                                        break;
                                    default:
                                        $status_class = 'status-unpaid';
                                        $status_icon = 'fa-times-circle';
                                }
                                ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($expense['expense_date'])); ?></td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($expense['name']); ?></div>
                                        <div style="font-size: 0.85rem; color: #666;">
                                            <?php echo htmlspecialchars($expense['description']); ?>
                                        </div>
                                        <?php if (!empty($expense['no'])): ?>
                                            <div style="font-size: 0.8rem; color: #888;">Ref:
                                                <?php echo htmlspecialchars($expense['no']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $expense['category']))); ?>
                                    </td>
                                    <td class="amount">₱<?php echo number_format($expense['amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <i class="fas <?php echo $status_icon; ?>"></i>
                                            <?php echo ucfirst($expense['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info"
                                                onclick="viewExpense(<?php echo $expense['expense_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-warning"
                                                onclick="editExpense(<?php echo $expense['expense_id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($expense['status'] !== 'paid'): ?>
                                                <button class="btn btn-success"
                                                    onclick="payBill(<?php echo $expense['expense_id']; ?>)">
                                                    <i class="fas fa-money-bill"></i> Pay
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-danger"
                                                onclick="deleteExpense(<?php echo $expense['expense_id']; ?>, '<?php echo htmlspecialchars($expense['name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Record Bill Payment Modal -->
    <div id="bill-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal('bill-modal')">&times;</button>
            <h2>Record Bill Payment</h2>
            <form method="POST" id="bill-form">
                <input type="hidden" name="action" value="add_bill">

                <div class="form-row">
                    <div class="form-group">
                        <label class="modal-label" for="bill-name">Vendor/Bill Name</label>
                        <input type="text" class="modal-input" id="bill-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label class="modal-label" for="bill-no">Invoice/Reference No.</label>
                        <input type="text" class="modal-input" id="bill-no" name="no" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="modal-label" for="bill-date">Bill Date</label>
                        <input type="date" class="modal-input" id="bill-date" name="expense_date" required readonly>
                    </div>
                    <div class="form-group">
                        <label class="modal-label" for="bill-due">Due Date</label>
                        <input type="date" class="modal-input" id="bill-due" name="due_date" required min="">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="modal-label" for="bill-amount">Amount</label>
                        <input type="number" class="modal-input" id="bill-amount" name="amount" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="modal-label" for="bill-deposit">Payment Account</label>
                        <select class="modal-select" id="bill-deposit" name="deposit_to" required>
                            <option value="cash">Cash</option>
                            <option value="inventory">Inventory</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="modal-label" for="bill-description">Description</label>
                    <input type="text" class="modal-input" id="bill-description" name="description" required>
                </div>

                <div class="form-group">
                    <label class="modal-label" for="bill-category">Expense Category</label>
                    <select class="modal-select" id="bill-category" name="category" required>
                        <option value="account payable">Account Payable</option>
                        <option value="short-term">Short-Term Loans</option>
                        <option value="long-term">Long-Term Loans</option>
                        <option value="note payable">Notes Payable</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeModal('bill-modal')">Cancel</button>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> Record Bill
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Record Business Expense Modal -->
    <div id="expense-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal('expense-modal')">&times;</button>
            <h2>Record Business Expense</h2>
            <form method="POST" id="expense-form">
                <input type="hidden" name="action" value="add_expense">

                <div class="form-group">
                    <label class="modal-label" for="expense-name">Expense Name</label>
                    <input type="text" class="modal-input" id="expense-name" name="name" required>
                </div>

                <div class="form-group">
                    <label class="modal-label" for="expense-date">Expense Date</label>
                    <input type="date" class="modal-input" id="expense-date" name="expense_date" required readonly>
                </div>

                <div class="form-group">
                    <label class="modal-label" for="expense-description">Description</label>
                    <input type="text" class="modal-input" id="expense-description" name="description" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="modal-label" for="expense-category">Expense Category</label>
                        <select class="modal-select" id="expense-category" name="category" required>
                            <option value="rent expense">Rent Expense</option>
                            <option value="utilities expenses">Utilities Expenses</option>
                            <option value="supplies expenses">Supplies Expenses</option>
                            <!-- add more if you want -->
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="modal-label" for="expense-amount">Amount</label>
                    <input type="number" class="modal-input" id="expense-amount" name="amount" step="0.01" required>
                </div>
                
        <div class="form-actions">
            <button type="button" class="cancel-btn" onclick="closeModal('expense-modal')">Cancel</button>
            <button type="submit" class="submit-btn">
                <i class="fas fa-plus"></i> Record Expense
            </button>
        </div>
        </form>
        </div>

    </div>
    </div>

    <!-- Edit Expense Modal -->
    <div id="edit-expense-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal('edit-expense-modal')">&times;</button>
            <h2>Edit Expense</h2>
            <form method="POST" id="edit-expense-form">
                <input type="hidden" name="action" value="update_expense">
                <input type="hidden" id="edit-expense-id" name="expense_id">

                <div class="form-row">
                    <div class="form-group">
                        <label class="modal-label" for="edit-name">Name</label>
                        <input type="text" class="modal-input" id="edit-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label class="modal-label" for="edit-no">Reference No.</label>
                        <input type="text" class="modal-input" id="edit-no" name="no">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="modal-label" for="edit-date">Date</label>
                        <input type="date" class="modal-input" id="edit-date" name="expense_date" required>
                    </div>
                    <div class="form-group">
                        <label class="modal-label" for="edit-due">Due Date</label>
                        <input type="date" class="modal-input" id="edit-due" name="due_date">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="modal-label" for="edit-amount">Amount</label>
                        <input type="number" class="modal-input" id="edit-amount" name="amount" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="modal-label" for="edit-deposit">Deposit To</label>
                        <select class="modal-select" id="edit-deposit" name="deposit_to" required>
                            <option value="cash">Cash</option>
                            <option value="inventory">Inventory</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="modal-label" for="edit-description">Description</label>
                    <input type="text" class="modal-input" id="edit-description" name="description" required>
                </div>

                <div class="form-group">
                    <label class="modal-label" for="edit-category">Category</label>
                    <select class="modal-select" id="edit-category" name="category" required>
                        <option value="rent expense">Rent Expense </option>
                        <option value="utilities expenses">Utilities Expenses</option>
                        <option value="supplies expenses">Supplies Expenses</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeModal('edit-expense-modal')">Cancel</button>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> Update Expense
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Expense Modal -->
    <div id="view-expense-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal('view-expense-modal')">&times;</button>
            <h2>Expense Details</h2>

            <div class="view-details" id="view-details">
                <!-- Details will be populated by JavaScript -->
            </div>

            <div class="form-actions">
                <button type="button" class="cancel-btn" onclick="closeModal('view-expense-modal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Pay Bill Modal -->
    <div id="pay-bill-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal('pay-bill-modal')">&times;</button>
            <h2>Pay Bill</h2>
            <form method="POST" id="pay-bill-form">
                <input type="hidden" name="action" value="pay_bill">
                <input type="hidden" id="pay-bill-id" name="expense_id">

                <div class="form-group">
                    <label class="modal-label">Bill Details</label>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                        <p><strong>Name:</strong> <span id="pay-bill-name"></span></p>
                        <p><strong>Description:</strong> <span id="pay-bill-description"></span></p>
                        <p><strong>Amount:</strong> ₱<span id="pay-bill-amount"></span></p>
                        <p><strong>Due Date:</strong> <span id="pay-bill-due"></span></p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="modal-label" for="payment-date">Payment Date</label>
                    <input type="date" class="modal-input" id="payment-date" name="payment_date" required readonly>
                </div>

                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeModal('pay-bill-modal')">Cancel</button>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-money-bill"></i> Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
        // Dropdown functionality
        const dropdownBtn = document.getElementById('dropdown-btn');
        const dropdownContent = document.getElementById('dropdown-content');

        dropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownContent.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdownBtn.contains(e.target) && !dropdownContent.contains(e.target)) {
                dropdownContent.classList.remove('show');
            }
        });

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'flex';
            dropdownContent.classList.remove('show');

            // Set today's date for date inputs
            const today = new Date().toISOString().split('T')[0];
            if (modalId === 'bill-modal') {
                document.getElementById('bill-date').value = today;
                document.getElementById('bill-due').min = today;
            } else if (modalId === 'expense-modal') {
                document.getElementById('expense-date').value = today;
            } else if (modalId === 'pay-bill-modal') {
                document.getElementById('payment-date').value = today;
            }

            // Force reflow to ensure smooth animation
            modal.offsetHeight;
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'none';
        }

        // Close modal when clicking outside - UPDATED VERSION
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });
        // View Expense Function
        async function viewExpense(id) {
            try {
                const response = await fetch(`expenses.php?view_id=${id}`);
                const expense = await response.json();

                if (expense) {
                    const detailsContainer = document.getElementById('view-details');
                    const statusClass = getStatusClass(expense.status);
                    const statusIcon = getStatusIcon(expense.status);

                    detailsContainer.innerHTML = `
                    <div class="detail-row">
                        <span class="detail-label">Name:</span>
                        <span class="detail-value">${expense.name}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Reference No:</span>
                        <span class="detail-value">${expense.no || 'N/A'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Description:</span>
                        <span class="detail-value">${expense.description}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Amount:</span>
                        <span class="detail-value">₱${parseFloat(expense.amount).toFixed(2)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value">${new Date(expense.expense_date).toLocaleDateString()}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Due Date:</span>
                        <span class="detail-value">${expense.due_date ? new Date(expense.due_date).toLocaleDateString() : 'N/A'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Category:</span>
                        <span class="detail-value">${expense.category.replace('_', ' ').toUpperCase()}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Deposit To:</span>
                        <span class="detail-value">${expense.deposit_to.toUpperCase()}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="status-indicator ${statusClass}">
                                <i class="fas ${statusIcon}"></i>
                                ${expense.status.toUpperCase()}
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Created:</span>
                        <span class="detail-value">${new Date(expense.created_at).toLocaleString()}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Last Updated:</span>
                        <span class="detail-value">${new Date(expense.updated_at).toLocaleString()}</span>
                    </div>
                `;

                    openModal('view-expense-modal');
                }
            } catch (error) {
                console.error('Error fetching expense:', error);
                alert('Error loading expense details');
            }
        }

        // Edit Expense Function
        async function editExpense(id) {
            try {
                const response = await fetch(`expenses.php?edit_id=${id}`);
                const expense = await response.json();

                if (expense) {
                    // Populate form fields
                    document.getElementById('edit-expense-id').value = expense.expense_id;
                    document.getElementById('edit-name').value = expense.name;
                    document.getElementById('edit-no').value = expense.no || '';
                    document.getElementById('edit-date').value = expense.expense_date;
                    document.getElementById('edit-due').value = expense.due_date || '';
                    document.getElementById('edit-amount').value = expense.amount;
                    document.getElementById('edit-deposit').value = expense.deposit_to;
                    document.getElementById('edit-description').value = expense.description;
                    document.getElementById('edit-category').value = expense.category;

                    openModal('edit-expense-modal');
                }
            } catch (error) {
                console.error('Error fetching expense:', error);
                alert('Error loading expense for editing');
            }
        }

        // Pay Bill Function
        async function payBill(id) {
            try {
                const response = await fetch(`expenses.php?view_id=${id}`);
                const expense = await response.json();

                if (expense) {
                    document.getElementById('pay-bill-id').value = expense.expense_id;
                    document.getElementById('pay-bill-name').textContent = expense.name;
                    document.getElementById('pay-bill-description').textContent = expense.description;
                    document.getElementById('pay-bill-amount').textContent = parseFloat(expense.amount).toFixed(2);
                    document.getElementById('pay-bill-due').textContent = expense.due_date ?
                        new Date(expense.due_date).toLocaleDateString() : 'N/A';

                    openModal('pay-bill-modal');
                }
            } catch (error) {
                console.error('Error fetching expense:', error);
                alert('Error loading bill details');
            }
        }

        function deleteExpense(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"?`)) {
                window.location.href = `expenses.php?delete_id=${id}`;
            }
        }

        // Helper functions for status
        function getStatusClass(status) {
            switch (status) {
                case 'paid':
                    return 'status-paid';
                case 'pending':
                    return 'status-pending';
                case 'overdue':
                    return 'status-overdue';
                default:
                    return 'status-unpaid';
            }
        }

        function getStatusIcon(status) {
            switch (status) {
                case 'paid':
                    return 'fa-check-circle';
                case 'pending':
                    return 'fa-clock';
                case 'overdue':
                    return 'fa-exclamation-triangle';
                default:
                    return 'fa-times-circle';
            }
        }

        // Set today's date as default for date inputs on page load
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('bill-date').value = today;
            document.getElementById('bill-due').min = today;
            document.getElementById('expense-date').value = today;
            document.getElementById('payment-date').value = today;
        });

        // Form submission handlers
        document.getElementById('edit-expense-form')?.addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            if (button) {
                button.innerHTML = '<div class="loading-spinner"></div> Updating...';
                button.disabled = true;
            }
        });

        document.getElementById('pay-bill-form')?.addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            if (button) {
                button.innerHTML = '<div class="loading-spinner"></div> Processing...';
                button.disabled = true;
            }
        });
    </script>

</body>

</html>