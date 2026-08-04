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

// Handle payment verification - APPROVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_payment'])) {
    $order_id = intval($_POST['order_id']);
    
    $pdo->beginTransaction();
    
    try {
        // Get order from accepted_orders
        $stmt = $pdo->prepare('SELECT * FROM accepted_orders WHERE id = ?');
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // First, check if cooking_orders table exists, if not create it
            try {
                $stmt = $pdo->query("SELECT 1 FROM cooking_orders LIMIT 1");
            } catch (PDOException $e) {
                // Create cooking_orders table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE cooking_orders (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        customer_name VARCHAR(255) NOT NULL,
                        total_amount DECIMAL(10,2) NOT NULL,
                        order_date DATETIME NOT NULL,
                        order_details TEXT NOT NULL,
                        payment_screenshot VARCHAR(500),
                        status VARCHAR(50) DEFAULT 'cooking',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id)
                    )
                ");
            }

            // Insert into cooking_orders - CORRECTED: Move to cooking stage first
            $stmt = $pdo->prepare('
                INSERT INTO cooking_orders 
                (user_id, customer_name, total_amount, order_date, order_details, payment_screenshot, status) 
                VALUES (?, ?, ?, ?, ?, ?, "cooking")
            ');
            $stmt->execute([
                $order['user_id'],
                $order['customer_name'],
                $order['total_amount'],
                $order['order_date'],
                $order['order_details'],
                $order['payment_screenshot'] ?? null
            ]);
            
            $cooking_order_id = $pdo->lastInsertId();
            
            // Delete from accepted_orders
            $stmt = $pdo->prepare('DELETE FROM accepted_orders WHERE id = ?');
            $stmt->execute([$order_id]);
            
            // Update order_history if exists
            try {
                $stmt = $pdo->prepare('UPDATE order_history SET status = "cooking" WHERE user_id = ? AND order_date = ?');
                $stmt->execute([$order['user_id'], $order['order_date']]);
            } catch (PDOException $e) {
                // Continue if order_history doesn't exist
            }
            
            // Send email notification
            try {
                $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
                $stmt->execute([$order['user_id']]);
                $userEmail = $stmt->fetchColumn();
                
                if ($userEmail) {
                    require_once '../../Log-in Form/vendor/phpmailer/phpmailer/src/PHPMailer.php';
                    require_once '../../Log-in Form/vendor/phpmailer/phpmailer/src/SMTP.php';
                    require_once '../../Log-in Form/vendor/phpmailer/phpmailer/src/Exception.php';

                    $mail = new PHPMailer\PHPMailer\PHPMailer();
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->Port = 587;
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->SMTPAuth = true;
                    $mail->Username = 'luietan04@gmail.com';
                    $mail->Password = 'cqwv qwwg zacn odnz';
                    $mail->setFrom('luietan04@gmail.com', 'Arko Flavours');
                    $mail->addAddress($userEmail, $order['customer_name']);
                    $mail->Subject = 'Payment Verified - Order Now Cooking!';
                    $mail->isHTML(true);
                    $mail->Body = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <h2 style="color: #2ecc71;">✅ Payment Verified!</h2>
                        <p>Dear ' . htmlspecialchars($order['customer_name']) . ',</p>
                        <p>Your payment has been verified and your order is now being prepared!</p>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                            <strong>Order ID: #' . $cooking_order_id . '</strong><br>
                            <strong>Order Total: ₱' . number_format($order['total_amount'], 2) . '</strong><br>
                            <strong>Order Date: ' . date('F j, Y g:i A', strtotime($order['order_date'])) . '</strong>
                        </div>
                        <p><strong>Next Steps:</strong></p>
                        <ol>
                            <li>✅ Payment Verified</li>
                            <li>👨‍🍳 Order is now being prepared</li>
                            <li>📦 Order will be ready for pickup/delivery soon</li>
                            <li>🎉 Order completion notification</li>
                        </ol>
                        <p>We will notify you once your order is ready for pickup/delivery.</p>
                        <p>Thank you for choosing Arko Flavours!</p>
                    </div>
                    ';
                    
                    if (@$mail->send()) {
                        // You can add a notification flag if needed
                        error_log("Payment approval email sent to: " . $userEmail);
                    }
                }
            } catch (Exception $e) {
                // Continue even if email fails
                error_log("Email error: " . $e->getMessage());
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = "✅ Payment approved! Order #$cooking_order_id moved to cooking stage and customer notified.";
        } else {
            $pdo->rollBack();
            $_SESSION['error_message'] = "❌ Order not found!";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "❌ Error: " . $e->getMessage();
        error_log("Payment approval error: " . $e->getMessage());
    }
    
    header('Location: payment_invoices.php');
    exit;
}

// Handle payment verification - REJECT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_payment'])) {
    $order_id = intval($_POST['order_id']);
    $rejection_reason = $_POST['rejection_reason'] ?? 'Payment verification failed';
    
    try {
        // Get order and user info
        $stmt = $pdo->prepare('SELECT ao.*, u.email FROM accepted_orders ao LEFT JOIN users u ON ao.user_id = u.id WHERE ao.id = ?');
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // Update payment status to rejected instead of deleting
            $stmt = $pdo->prepare('UPDATE accepted_orders SET payment_status = "rejected", rejection_reason = ? WHERE id = ?');
            $stmt->execute([$rejection_reason, $order_id]);
            
            // Send rejection email
            if ($order['email']) {
                try {
                    require_once '../../Log-in Form/vendor/phpmailer/phpmailer/src/PHPMailer.php';
                    require_once '../../Log-in Form/vendor/phpmailer/phpmailer/src/SMTP.php';
                    require_once '../../Log-in Form/vendor/phpmailer/phpmailer/src/Exception.php';

                    $mail = new PHPMailer\PHPMailer\PHPMailer();
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->Port = 587;
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->SMTPAuth = true;
                    $mail->Username = 'luietan04@gmail.com';
                    $mail->Password = 'cqwv qwwg zacn odnz';
                    $mail->setFrom('luietan04@gmail.com', 'Arko Flavours');
                    $mail->addAddress($order['email'], $order['customer_name']);
                    $mail->Subject = 'Payment Rejected - Arko Flavours';
                    $mail->isHTML(true);
                    $mail->Body = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <h2 style="color: #e74c3c;">❌ Payment Rejected</h2>
                        <p>Dear ' . htmlspecialchars($order['customer_name']) . ',</p>
                        <p>Unfortunately, your payment proof could not be verified.</p>
                        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ffc107;">
                            <strong>Reason:</strong> ' . htmlspecialchars($rejection_reason) . '
                        </div>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                            <strong>Order Details:</strong><br>
                            Order Total: ₱' . number_format($order['total_amount'], 2) . '<br>
                            Order Date: ' . date('F j, Y g:i A', strtotime($order['order_date'])) . '
                        </div>
                        <p><strong>What to do next:</strong></p>
                        <ol>
                            <li>Please re-upload a clear payment proof</li>
                            <li>Ensure the image shows the complete transaction details</li>
                            <li>Make sure the amount matches your order total</li>
                            <li>Contact us if you need assistance</li>
                        </ol>
                        <p>Thank you for your understanding.</p>
                    </div>
                    ';
                    @$mail->send();
                } catch (Exception $e) {
                    error_log("Email error: " . $e->getMessage());
                }
            }
            
            $_SESSION['success_message'] = "✅ Payment rejected and customer notified via email!";
        } else {
            $_SESSION['error_message'] = "❌ Order not found!";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "❌ Error: " . $e->getMessage();
        error_log("Payment rejection error: " . $e->getMessage());
    }
    
    header('Location: payment_invoices.php');
    exit;
}

// Function to find image path
function findImagePath($image_path) {
    if (empty($image_path)) return null;
    
    $possible_paths = [
        '../../' . $image_path,
        '../' . $image_path,
        $image_path,
        '../../assets/images/uploads/' . basename($image_path),
        '../../assets/images/payments/' . basename($image_path),
        '../assets/images/uploads/' . basename($image_path),
        '../assets/images/payments/' . basename($image_path)
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return null;
}

// Get payment invoices (orders with payment screenshots) - Only show pending ones
$stmt = $pdo->prepare("
    SELECT 
        ao.*,
        u.email as customer_email,
        u.phone as customer_phone,
        TIMESTAMPDIFF(MINUTE, ao.order_date, NOW()) as minutes_waiting
    FROM accepted_orders ao
    LEFT JOIN users u ON ao.user_id = u.id
    WHERE ao.payment_screenshot IS NOT NULL 
    AND ao.payment_screenshot != ''
    AND (ao.payment_status IS NULL OR ao.payment_status = 'pending')
    ORDER BY ao.order_date DESC
");
$stmt->execute();
$payment_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count statistics
$total_pending = count($payment_invoices);
$total_amount = array_sum(array_column($payment_invoices, 'total_amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Proofs - Arko Flavours</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/lucide@latest/dist/umd/lucide.js">
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
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
            margin: 0;
            padding: 0;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            margin-left: 220px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 2rem;
            animation: fadeIn 0.8s ease;
        }

        .page-header h1 {
            color: #222e3c;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #4361ee;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 2rem;
            animation: slideIn 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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

        .payment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
        }

        .payment-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            animation: slideUp 0.5s ease;
            border: 1px solid var(--border);
        }

        .payment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--border);
        }

        .customer-info h3 {
            color: #222e3c;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .customer-info p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--success);
        }

        .waiting-time {
            font-size: 0.85rem;
            color: var(--warning);
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .payment-image-container {
            text-align: center;
            margin: 1.5rem 0;
        }

        .payment-image {
            max-width: 100%;
            max-height: 280px;
            border-radius: 12px;
            border: 2px solid #ddd;
            cursor: zoom-in;
            transition: transform 0.3s ease;
            object-fit: contain;
        }

        .payment-image:hover {
            transform: scale(1.03);
        }

        .payment-details {
            background: var(--light);
            padding: 1.25rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            border: 1px solid var(--border);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        .detail-row:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-value {
            color: var(--gray);
            font-weight: 500;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.85rem 1.5rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .btn-approve {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }

        .btn-reject {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-reject:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
        }

        .btn-view {
            background: linear-gradient(135deg, var(--primary), var(--info));
            color: white;
            grid-column: span 2;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .btn-view:hover {
            background: linear-gradient(135deg, var(--info), #1d4ed8);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(67, 97, 238, 0.4);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: linear-gradient(135deg, #fff4e6, #ffe8cc);
            color: #92400e;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 0.5rem;
        }

        .image-not-found {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            color: #856404;
            margin: 1rem 0;
            border: 1px solid #fbbf24;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            grid-column: 1 / -1;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
        }

        .empty-state h3 {
            color: #222e3c;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .empty-state p {
            color: #666;
            font-size: 1rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .modal-content {
            max-width: 90%;
            max-height: 90%;
            position: relative;
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            border: 1px solid var(--border);
        }

        .rejection-modal .modal-content {
            max-width: 500px;
        }

        .modal-image {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            color: #666;
            font-size: 28px;
            cursor: pointer;
            background: var(--light);
            border: none;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: var(--danger);
            color: white;
            transform: rotate(90deg);
        }

        .rejection-form textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            margin: 1rem 0;
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .rejection-form textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .payment-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .btn-view {
                grid-column: 1;
            }
        }

        /* Lucide Icons */
        .lucide {
            width: 1.25em;
            height: 1.25em;
        }
    </style>
</head>
<body>
    <?php include '../../adminSidebar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h1>Payment Proofs</h1>
                <p>Review and verify customer payment screenshots</p>
            </div>

            <div class="stats-bar">
                <div class="stat-card">
                    <h3><i data-lucide="clock" class="lucide"></i> PENDING VERIFICATION</h3>
                    <div class="value"><?= $total_pending ?></div>
                </div>
                <div class="stat-card">
                    <h3><i data-lucide="dollar-sign" class="lucide"></i> TOTAL AMOUNT</h3>
                    <div class="value">₱<?= number_format($total_amount, 2) ?></div>
                </div>
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

            <div class="payment-grid">
                <?php if (empty($payment_invoices)): ?>
                    <div class="empty-state">
                        <h3><i data-lucide="inbox" class="lucide"></i> No Payment Proofs</h3>
                        <p>No customers have uploaded payment proofs yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($payment_invoices as $invoice): ?>
                        <div class="payment-card">
                            <div class="payment-header">
                                <div class="customer-info">
                                    <h3>
                                        <i data-lucide="user" class="lucide"></i>
                                        <?= htmlspecialchars($invoice['customer_name']) ?>
                                        <span class="status-badge">
                                            <i data-lucide="clock" class="lucide"></i>
                                            Pending
                                        </span>
                                    </h3>
                                    <p><i data-lucide="mail" class="lucide"></i> <?= htmlspecialchars($invoice['customer_email']) ?></p>
                                    <?php if ($invoice['minutes_waiting'] > 0): ?>
                                        <p class="waiting-time">
                                            <i data-lucide="clock" class="lucide"></i>
                                            Waiting <?= $invoice['minutes_waiting'] < 60 
                                                ? $invoice['minutes_waiting'] . ' min' 
                                                : floor($invoice['minutes_waiting'] / 60) . ' hour' . (floor($invoice['minutes_waiting'] / 60) > 1 ? 's' : '') ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="order-amount">
                                    ₱<?= number_format($invoice['total_amount'], 2) ?>
                                </div>
                            </div>

                            <div class="payment-details">
                                <div class="detail-row">
                                    <span class="detail-label">
                                        <i data-lucide="hash" class="lucide"></i>
                                        Order ID:
                                    </span>
                                    <span class="detail-value">#<?= $invoice['id'] ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">
                                        <i data-lucide="calendar" class="lucide"></i>
                                        Order Date:
                                    </span>
                                    <span class="detail-value"><?= date('M j, Y g:i A', strtotime($invoice['order_date'])) ?></span>
                                </div>
                            </div>

                            <div class="payment-image-container">
                                <p style="font-weight: 600; margin-bottom: 1rem; color: #222e3c; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                    <i data-lucide="image" class="lucide"></i>
                                    Payment Proof:
                                </p>
                                <?php
                                $image_path = findImagePath($invoice['payment_screenshot']);
                                $image_exists = $image_path && file_exists($image_path);
                                ?>
                                
                                <?php if ($image_exists): ?>
                                    <img src="<?= $image_path ?>" 
                                         alt="Payment Proof" 
                                         class="payment-image"
                                         onclick="showImage('<?= $image_path ?>')">
                                <?php else: ?>
                                    <div class="image-not-found">
                                        <p><i data-lucide="alert-triangle" class="lucide"></i> Image not found</p>
                                        <small>Path: <?= htmlspecialchars($invoice['payment_screenshot']) ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="action-buttons">
                                <?php if ($image_exists): ?>
                                    <form method="POST" style="display: contents;">
                                        <input type="hidden" name="order_id" value="<?= $invoice['id'] ?>">
                                        <button type="submit" name="approve_payment" class="btn btn-approve"
                                            onclick="return confirm('Approve this payment? Order will be moved to cooking stage.')">
                                            <i data-lucide="check" class="lucide"></i>
                                            Approve
                                        </button>
                                    </form>

                                    <button type="button" class="btn btn-reject"
                                        onclick="openRejectModal(<?= $invoice['id'] ?>)">
                                        <i data-lucide="x" class="lucide"></i>
                                        Reject
                                    </button>

                                    <button class="btn btn-view" onclick="showImage('<?= $image_path ?>')">
                                        <i data-lucide="search" class="lucide"></i>
                                        View Full Size
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-view" disabled style="grid-column: span 2;">
                                        <i data-lucide="alert-triangle" class="lucide"></i>
                                        Image Missing
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="imageModal" class="modal">
        <button class="close-modal" onclick="closeImageModal()">
            <i data-lucide="x" class="lucide"></i>
        </button>
        <div class="modal-content">
            <img id="modalImage" class="modal-image">
        </div>
    </div>

    <div id="rejectionModal" class="modal rejection-modal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeRejectionModal()">
                <i data-lucide="x" class="lucide"></i>
            </button>
            <h3 style="margin-bottom: 1rem; color: var(--danger); display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="x-circle" class="lucide"></i>
                Reject Payment
            </h3>
            <p style="color: var(--gray); margin-bottom: 1rem;">Please provide a reason for rejecting this payment:</p>
            <form method="POST" id="rejectionForm" class="rejection-form">
                <input type="hidden" name="order_id" id="rejectOrderId">
                <textarea name="rejection_reason" 
                    placeholder="Enter reason for rejection (e.g., blurry image, wrong amount, missing transaction details, unclear reference number...)" 
                    required></textarea>
                <div class="form-actions">
                    <button type="button" class="btn" style="background: var(--gray); color: white;" onclick="closeRejectionModal()">
                        <i data-lucide="x" class="lucide"></i>
                        Cancel
                    </button>
                    <button type="submit" name="reject_payment" class="btn btn-reject">
                        <i data-lucide="x" class="lucide"></i>
                        Reject Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Image modal functionality
        function showImage(imageSrc) {
            if (!imageSrc) {
                alert('Image not found!');
                return;
            }
            
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('modalImage').style.transform = 'scale(1)';
            document.getElementById('imageModal').style.display = 'flex';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // Rejection modal functionality
        function openRejectModal(orderId) {
            document.getElementById('rejectOrderId').value = orderId;
            document.getElementById('rejectionModal').style.display = 'flex';
        }

        function closeRejectionModal() {
            document.getElementById('rejectionModal').style.display = 'none';
            document.getElementById('rejectionForm').reset();
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });

        // Close with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
                closeRejectionModal();
            }
        });

        // Zoom functionality for images
        document.getElementById('modalImage').addEventListener('click', function(e) {
            e.stopPropagation();
            this.style.transform = this.style.transform === 'scale(1.5)' ? 'scale(1)' : 'scale(1.5)';
            this.style.transition = 'transform 0.3s ease';
        });

        // Add loading state to forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitButton = this.querySelector('button[type="submit"]');
                if (submitButton) {
                    const originalText = submitButton.innerHTML;
                    submitButton.innerHTML = `
                        <i data-lucide="loader-2" class="lucide spin"></i>
                        Processing...
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

        // Auto-close success messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            });

            // Add smooth animations to cards
            const cards = document.querySelectorAll('.payment-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Confirm rejection before submitting
        document.getElementById('rejectionForm').addEventListener('submit', function(e) {
            const reason = this.querySelector('textarea[name="rejection_reason"]').value.trim();
            if (!reason) {
                e.preventDefault();
                alert('Please provide a rejection reason.');
                return;
            }
            
            if (!confirm('Are you sure you want to reject this payment? The customer will be notified via email.')) {
                e.preventDefault();
            }
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