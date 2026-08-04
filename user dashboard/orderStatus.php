<?php
require_once '../Log-in Form/includes/config_session.inc.php';
require_once '../Log-in Form/includes/dbh.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Log-in Form/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$orders = [];

// Handle payment proof upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_proof'])) {
    $order_id = intval($_POST['order_id']);
    
    // Check if file was uploaded without errors
    if ($_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['payment_proof']['tmp_name'];
        $file_name = $_FILES['payment_proof']['name'];
        $file_size = $_FILES['payment_proof']['size'];
        $file_type = $_FILES['payment_proof']['type'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error_message'] = "❌ Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
        } 
        // Validate file size (max 5MB)
        elseif ($file_size > 5 * 1024 * 1024) {
            $_SESSION['error_message'] = "❌ File size must be less than 5MB.";
        } else {
            // Generate unique filename
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_filename = 'payment_' . $order_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = '../assets/images/payments/' . $new_filename;
            
            // Create directory if it doesn't exist
            if (!is_dir('../assets/images/payments/')) {
                mkdir('../assets/images/payments/', 0777, true);
            }
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp_name, $upload_path)) {
                // Update the order with payment screenshot
                $stmt = $pdo->prepare('UPDATE accepted_orders SET payment_screenshot = ?, payment_status = "pending" WHERE id = ? AND user_id = ?');
                $stmt->execute([$new_filename, $order_id, $user_id]);
                
                // Also update order_history if it exists
                try {
                    $stmt = $pdo->prepare('UPDATE order_history SET payment_screenshot = ? WHERE order_id = ? AND user_id = ?');
                    $stmt->execute([$new_filename, $order_id, $user_id]);
                } catch (PDOException $e) {
                    // Silently continue if order_history doesn't exist
                }
                
                $_SESSION['success_message'] = "✅ Payment proof uploaded successfully! Waiting for admin verification.";
                header('Location: orderStatus.php');
                exit;
            } else {
                $_SESSION['error_message'] = "❌ Error uploading file. Please try again.";
            }
        }
    } else {
        $_SESSION['error_message'] = "❌ Error uploading file. Please try again.";
    }
}

// Get user info
$stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = :user_id');
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Pending Orders with details
$stmt = $pdo->prepare("
    SELECT id, total_amount, order_date, order_details, 
           'pending' AS status_type, 
           'Pending Approval' AS status 
    FROM pending_orders 
    WHERE user_id = :user_id
    ORDER BY order_date DESC
");
$stmt->execute([':user_id' => $user_id]);
$pending_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($pending_orders as &$order) {
    $order['status_icon'] = 'clock';
    $order['status_class'] = 'status-pending';
    $order['step_number'] = 1;
}
$orders = array_merge($orders, $pending_orders);

// Accepted Orders with details
$stmt = $pdo->prepare("
    SELECT id, total_amount, order_date, order_details, 
           payment_screenshot, payment_status,
           'accepted' AS status_type,
           CASE 
               WHEN payment_status = 'verified' THEN 'Payment Verified'
               WHEN payment_screenshot IS NOT NULL THEN 'Payment Under Review'
               ELSE 'Awaiting Payment'
           END AS status
    FROM accepted_orders 
    WHERE user_id = :user_id
    ORDER BY order_date DESC
");
$stmt->execute([':user_id' => $user_id]);
$accepted_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($accepted_orders as &$order) {
    if ($order['payment_status'] === 'verified') {
        $order['status_icon'] = 'check-circle';
        $order['status_class'] = 'status-verified';
        $order['step_number'] = 3;
    } elseif (!empty($order['payment_screenshot'])) {
        $order['status_icon'] = 'search';
        $order['status_class'] = 'status-under-review';
        $order['step_number'] = 2;
    } else {
        $order['status_icon'] = 'credit-card';
        $order['status_class'] = 'status-awaiting-payment';
        $order['step_number'] = 2;
    }
}
$orders = array_merge($orders, $accepted_orders);

// Completed Orders
$stmt = $pdo->prepare("
    SELECT id, total_amount, order_date, order_details, delivered, 
           'completed' AS status_type, 
           CASE 
               WHEN delivered = 1 THEN 'Delivered' 
               ELSE 'Completed - Ready for Pickup' 
           END AS status 
    FROM completed_orders 
    WHERE user_id = :user_id
    ORDER BY order_date DESC
");
$stmt->execute([':user_id' => $user_id]);
$completed_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($completed_orders as &$order) {
    $order['status_icon'] = $order['delivered'] ? 'party-popper' : 'package-check';
    $order['status_class'] = $order['delivered'] ? 'status-delivered' : 'status-completed';
    $order['step_number'] = $order['delivered'] ? 5 : 4;
}
$orders = array_merge($orders, $completed_orders);

// Sort orders by date (newest first)
usort($orders, fn($a, $b) => strtotime($b['order_date']) - strtotime($a['order_date']));

// Get current active order for quick status
$active_order = null;
if (!empty($orders)) {
    foreach ($orders as $order) {
        if (in_array($order['status_type'], ['pending', 'accepted'])) {
            $active_order = $order;
            break;
        }
    }
    if (!$active_order) {
        $active_order = $orders[0];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status - Food Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/lucide@latest/dist/umd/lucide.js">
    <link rel="stylesheet" href="/Food_System/user dashboard/design/userSidebar.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --border: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            color: var(--dark);
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 2rem;
            text-align: left;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary), var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-header p {
            font-size: 1.1rem;
            color: var(--gray);
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .alert-warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border-left: 4px solid var(--warning);
        }

        .current-status-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .current-status-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--info));
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            margin: 0.5rem 0;
            backdrop-filter: blur(10px);
        }

        .status-pending { 
            background: linear-gradient(135deg, #fffbeb, #fed7aa);
            color: #92400e;
            border: 1px solid #fdba74;
        }
        .status-awaiting-payment { 
            background: linear-gradient(135deg, #fffbeb, #fed7aa);
            color: #92400e;
            border: 1px solid #fdba74;
        }
        .status-under-review { 
            background: linear-gradient(135deg, #dbeafe, #93c5fd);
            color: #1e40af;
            border: 1px solid #60a5fa;
        }
        .status-verified { 
            background: linear-gradient(135deg, #d1fae5, #6ee7b7);
            color: #065f46;
            border: 1px solid #10b981;
        }
        .status-completed { 
            background: linear-gradient(135deg, #d1fae5, #6ee7b7);
            color: #065f46;
            border: 1px solid #10b981;
        }
        .status-delivered { 
            background: linear-gradient(135deg, #ecfdf5, #a7f3d0);
            color: #047857;
            border: 1px solid #34d399;
        }

        .orders-container {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
        }

        .section-title {
            font-weight: 700;
            margin-bottom: 2rem;
            font-size: 1.75rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .order-card {
            background: var(--light);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid var(--border);
        }

        .order-card:hover {
            background: white;
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .order-id {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-status {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 1px solid var(--border);
        }

        .detail-label {
            font-size: 0.8rem;
            color: var(--gray);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-weight: 600;
            color: var(--dark);
            font-size: 1.1rem;
        }

        .order-items {
            margin-top: 1.5rem;
            border-top: 2px solid var(--border);
            padding-top: 1.5rem;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            margin-bottom: 0.75rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }

        .order-item:hover {
            transform: translateX(8px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .item-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 1rem;
        }

        .item-meta {
            color: var(--gray);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .item-price {
            font-weight: 700;
            color: var(--success);
            font-size: 1.1rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }

        .empty-state img {
            width: 200px;
            height: 200px;
            margin-bottom: 2rem;
            opacity: 0.7;
        }

        .empty-state h3 {
            margin-bottom: 1rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 1.75rem;
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            color: var(--gray);
        }

        .progress-tracker {
            display: flex;
            justify-content: space-between;
            margin-top: 2.5rem;
            position: relative;
            padding: 0 1rem;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 2;
            flex: 1;
            text-align: center;
        }

        .step-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            background: white;
            border: 3px solid var(--border);
            transition: all 0.3s ease;
            position: relative;
        }

        .step-active .step-icon {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .step-completed .step-icon {
            background: var(--success);
            color: white;
            border-color: var(--success);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .step-label {
            font-size: 0.85rem;
            color: var(--gray);
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .step-active .step-label {
            color: var(--primary);
            font-weight: 600;
        }

        .step-completed .step-label {
            color: var(--success);
            font-weight: 600;
        }

        .progress-tracker::before {
            content: '';
            position: absolute;
            top: 30px;
            left: 10%;
            right: 10%;
            height: 4px;
            background: var(--border);
            z-index: 1;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .progress-completed::before {
            background: var(--success);
        }

        /* Button Styles */
        .upload-payment-btn {
            background: linear-gradient(135deg, var(--primary), var(--info));
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1rem;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .upload-payment-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--info));
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: var(--primary);
            border-color: var(--primary);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }

        .modal-content {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            border: 1px solid var(--border);
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
            background: var(--light);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: var(--danger);
            color: white;
            transform: rotate(90deg);
        }

        .upload-form {
            margin-top: 1.5rem;
        }

        .file-input {
            width: 100%;
            padding: 2rem;
            border: 2px dashed var(--border);
            border-radius: 16px;
            margin-bottom: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--light);
        }

        .file-input:hover {
            border-color: var(--primary);
            background: #f0f4ff;
            transform: scale(1.02);
        }

        .file-input input {
            display: none;
        }

        .file-label {
            display: block;
            cursor: pointer;
            color: var(--gray);
        }

        .file-label i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
            color: var(--primary);
        }

        .submit-btn {
            width: 100%;
            padding: 1.25rem;
            background: linear-gradient(135deg, var(--primary), var(--info));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .status-highlight {
            background: linear-gradient(135deg, #fffbeb, #fed7aa);
            padding: 1.25rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            border-left: 4px solid var(--warning);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .order-details {
                grid-template-columns: 1fr;
            }

            .progress-tracker {
                flex-direction: column;
                gap: 2rem;
                align-items: flex-start;
            }

            .progress-tracker::before {
                display: none;
            }
            
            .progress-step {
                flex-direction: row;
                gap: 1rem;
                text-align: left;
                width: 100%;
            }
            
            .step-label {
                text-align: left;
                margin-top: 0;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .current-status-card,
            .orders-container {
                padding: 2rem;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* Lucide Icons */
        .lucide {
            width: 1.25em;
            height: 1.25em;
        }
    </style>
</head>
<body>
    <?php include '../userSidebar.php'; ?>

    <div class="main-content">
        <div class="page-header fade-in-up">
            <h1>Order Status</h1>
            <p>Track your orders and stay updated with real-time status</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i data-lucide="check-circle" class="lucide"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i data-lucide="alert-circle" class="lucide"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Check for pending payment orders -->
        <?php 
        $has_pending_payment = false;
        foreach ($accepted_orders as $order) {
            if (empty($order['payment_screenshot'])) {
                $has_pending_payment = true;
                break;
            }
        }
        ?>

        <?php if ($has_pending_payment): ?>
            <div class="alert alert-warning">
                <i data-lucide="alert-triangle" class="lucide"></i>
                You have orders waiting for payment proof upload
            </div>
        <?php endif; ?>

        <?php if ($active_order): ?>
        <div class="current-status-card fade-in-up">
            <h2 style="font-size: 1.5rem; margin-bottom: 1rem; color: var(--dark); display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="package" class="lucide"></i>
                Current Order Status
            </h2>
            <div class="status-badge <?= $active_order['status_class'] ?> pulse">
                <i data-lucide="<?= $active_order['status_icon'] ?>" class="lucide"></i>
                <?= $active_order['status'] ?>
            </div>
            <p style="margin-top: 1rem; color: var(--gray); font-size: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="hash" class="lucide"></i>
                Order #<?= $active_order['id'] ?> • 
                <i data-lucide="calendar" class="lucide"></i>
                <?= date('M j, Y g:i A', strtotime($active_order['order_date'])) ?>
            </p>
            
            <?php if ($active_order['status_type'] === 'accepted' && empty($active_order['payment_screenshot'])): ?>
                <div class="status-highlight">
                    <i data-lucide="lightbulb" class="lucide"></i>
                    <div>
                        <p style="margin: 0; font-weight: 600; color: #92400e;">
                            Please upload your payment proof to continue order processing
                        </p>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.9rem; color: #92400e;">
                            Upload a clear screenshot of your payment transaction
                        </p>
                    </div>
                </div>
                <button type="button" class="upload-payment-btn" onclick="openPaymentModal(<?= $active_order['id'] ?>)">
                    <i data-lucide="upload" class="lucide"></i>
                    Upload Payment Proof
                </button>
            <?php endif; ?>
            
            <!-- Progress Tracker -->
            <div class="progress-tracker <?= $active_order['step_number'] >= 4 ? 'progress-completed' : '' ?>">
                <?php
                $steps = [
                    ['icon' => 'clock', 'label' => 'Pending', 'status' => 'pending'],
                    ['icon' => 'credit-card', 'label' => 'Payment', 'status' => 'accepted'],
                    ['icon' => 'check-circle', 'label' => 'Verified', 'status' => 'verified'],
                    ['icon' => 'package-check', 'label' => 'Ready', 'status' => 'completed'],
                    ['icon' => 'party-popper', 'label' => 'Delivered', 'status' => 'delivered']
                ];
                
                $current_step_index = $active_order['step_number'] - 1;
                ?>
                <?php foreach ($steps as $index => $step): ?>
                    <div class="progress-step 
                        <?= $index <= $current_step_index ? 'step-completed' : '' ?>
                        <?= $index == $current_step_index ? 'step-active' : '' ?>">
                        <div class="step-icon">
                            <i data-lucide="<?= $step['icon'] ?>" class="lucide"></i>
                        </div>
                        <div class="step-label"><?= $step['label'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="action-buttons">
                <a href="menu.php" class="action-btn btn-primary">
                    <i data-lucide="shopping-cart" class="lucide"></i>
                    Order Again
                </a>
                <a href="orderStatus.php" class="action-btn btn-secondary">
                    <i data-lucide="list" class="lucide"></i>
                    View Details
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="orders-container fade-in-up">
            <h2 class="section-title">
                <i data-lucide="history" class="lucide"></i>
                Order History
            </h2>
            
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <img src="/Food_System/assets/icons/emptycart.jpg" alt="No orders">
                    <h3>No Orders Yet</h3>
                    <p>You haven't placed any orders yet. Start shopping to see your order history here!</p>
                    <a href="menu.php" class="upload-payment-btn" style="text-decoration: none;">
                        <i data-lucide="shopping-bag" class="lucide"></i>
                        Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="orders-list">
                    <?php foreach ($orders as $index => $order): ?>
                        <div class="order-card fade-in-up" style="animation-delay: <?= $index * 0.1 ?>s">
                            <div class="order-header">
                                <div class="order-id">
                                    <i data-lucide="package" class="lucide"></i>
                                    Order #<?= htmlspecialchars($order['id']) ?>
                                </div>
                                <div class="order-status">
                                    <span class="status-badge <?= $order['status_class'] ?>">
                                        <i data-lucide="<?= $order['status_icon'] ?>" class="lucide"></i>
                                        <?= htmlspecialchars($order['status']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="order-details">
                                <div class="detail-item">
                                    <span class="detail-label">Order Date</span>
                                    <span class="detail-value" style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i data-lucide="calendar" class="lucide"></i>
                                        <?= date('M j, Y g:i A', strtotime($order['order_date'])) ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Total Amount</span>
                                    <span class="detail-value" style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i data-lucide="dollar-sign" class="lucide"></i>
                                        ₱<?= number_format($order['total_amount'], 2) ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Status</span>
                                    <span class="detail-value"><?= htmlspecialchars($order['status']) ?></span>
                                </div>
                            </div>

                            <?php if (!empty($order['order_details'])): ?>
                                <div class="order-items">
                                    <strong style="font-size: 1rem; color: var(--dark); display: flex; align-items: center; gap: 0.5rem;">
                                        <i data-lucide="utensils" class="lucide"></i>
                                        Order Items:
                                    </strong>
                                    <?php
                                    $orderDetails = json_decode($order['order_details'], true);
                                    if (is_array($orderDetails)):
                                        foreach ($orderDetails as $item):
                                            $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                                    ?>
                                        <div class="order-item">
                                            <div style="flex: 1;">
                                                <div class="item-name"><?= htmlspecialchars($item['name'] ?? 'Unknown Item') ?></div>
                                                <div class="item-meta">
                                                    <?php if (!empty($item['size'])): ?>
                                                        <span>Size: <?= htmlspecialchars($item['size']) ?></span> • 
                                                    <?php endif; ?>
                                                    Qty: <?= $item['quantity'] ?? 1 ?>
                                                    <?php if (!empty($item['addons'])): ?>
                                                        <br>Add-ons: <?= htmlspecialchars(implode(', ', $item['addons'])) ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['notes'])): ?>
                                                        <br>Notes: <?= htmlspecialchars($item['notes']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="item-price">
                                                ₱<?= number_format($itemTotal, 2) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($order['status_type'] === 'accepted' && empty($order['payment_screenshot'])): ?>
                                <div style="margin-top: 1.5rem; text-align: center;">
                                    <button type="button" class="upload-payment-btn" onclick="openPaymentModal(<?= $order['id'] ?>)">
                                        <i data-lucide="upload" class="lucide"></i>
                                        Upload Payment Proof
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Upload Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" onclick="closePaymentModal()">
                <i data-lucide="x" class="lucide"></i>
            </button>
            <h2 style="margin-bottom: 1rem; color: var(--dark); display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="upload" class="lucide"></i>
                Upload Payment Proof
            </h2>
            <p style="color: var(--gray); margin-bottom: 1.5rem;">
                Please upload a clear screenshot or photo of your payment transaction for verification.
            </p>
            
            <form class="upload-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="order_id" id="modalOrderId">
                
                <div class="file-input">
                    <label class="file-label">
                        <i data-lucide="file-image" class="lucide" style="width: 3rem; height: 3rem;"></i>
                        <span style="font-size: 1.1rem; font-weight: 600;">Click to select payment proof image</span>
                        <br>
                        <small style="color: var(--gray);">Supported formats: JPG, PNG, GIF, WEBP (Max 5MB)</small>
                        <input type="file" name="payment_proof" accept="image/*" required onchange="previewImage(this)">
                    </label>
                </div>
                
                <div id="imagePreview" style="display: none; margin-bottom: 1.5rem; text-align: center;">
                    <img id="previewImg" style="max-width: 250px; max-height: 250px; border-radius: 12px; border: 2px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                </div>
                
                <button type="submit" class="submit-btn">
                    <i data-lucide="upload" class="lucide"></i>
                    Upload Payment Proof
                </button>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Real-time order status updates
        let lastUpdateTime = <?= time() ?>;
        
        function checkForUpdates() {
            fetch('orderStatus.php?check_updates=' + lastUpdateTime)
                .then(response => response.json())
                .then(data => {
                    if (data.updated) {
                        // Show update notification
                        showUpdateNotification();
                        // Reload page after a short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }
                    lastUpdateTime = data.current_time;
                })
                .catch(error => console.error('Error checking updates:', error));
        }

        function showUpdateNotification() {
            const notification = document.createElement('div');
            notification.className = 'alert alert-success';
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '1001';
            notification.style.maxWidth = '300px';
            notification.innerHTML = `
                <i data-lucide="refresh-cw" class="lucide"></i>
                Order status updated! Refreshing...
            `;
            document.body.appendChild(notification);
            lucide.createIcons();
            
            setTimeout(() => {
                notification.remove();
            }, 2000);
        }

        // Check for updates every 10 seconds
        setInterval(checkForUpdates, 10000);

        // Auto-refresh page every 60 seconds as fallback
        setTimeout(() => {
            window.location.reload();
        }, 60000);

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const orderCards = document.querySelectorAll('.order-card');
            orderCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Payment Modal Functions
        function openPaymentModal(orderId) {
            document.getElementById('modalOrderId').value = orderId;
            document.getElementById('paymentModal').style.display = 'flex';
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
            document.getElementById('imagePreview').style.display = 'none';
            document.querySelector('input[name="payment_proof"]').value = '';
        }

        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const img = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Close modal when clicking outside
        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePaymentModal();
            }
        });

        // Add hover effects
        const statusBadges = document.querySelectorAll('.status-badge');
        statusBadges.forEach(badge => {
            badge.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
            });
            badge.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Add loading state to forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitButton = this.querySelector('button[type="submit"]');
                if (submitButton) {
                    const originalText = submitButton.innerHTML;
                    submitButton.innerHTML = `
                        <i data-lucide="loader-2" class="lucide spin"></i>
                        Uploading...
                    `;
                    submitButton.disabled = true;
                    
                    // Re-enable after 10 seconds in case of error
                    setTimeout(() => {
                        submitButton.innerHTML = originalText;
                        submitButton.disabled = false;
                        lucide.createIcons();
                    }, 10000);
                }
            });
        });

        // Add spinning animation for loader
        const style = document.createElement('style');
        style.textContent = `
            .spin {
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>