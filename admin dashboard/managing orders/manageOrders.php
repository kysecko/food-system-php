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

// Get current tab
$current_tab = $_GET['tab'] ?? 'pending';

// ========== ORDER ACTIONS ==========

// Accept pending order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_order'])) {
    $order_id = intval($_POST['order_id']);

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Get order info from pending_orders
        $stmt = $pdo->prepare('SELECT * FROM pending_orders WHERE id = ?');
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Move to accepted_orders
            $stmt = $pdo->prepare('INSERT INTO accepted_orders (user_id, customer_name, total_amount, order_date, order_details, payment_screenshot) 
                                VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $order['user_id'],
                $order['customer_name'],
                $order['total_amount'],
                $order['order_date'],
                $order['order_details'],
                $order['payment_proof'] ?? null
            ]);

            $accepted_order_id = $pdo->lastInsertId();

            // Update or create order_history
            try {
                $stmt = $pdo->prepare('UPDATE order_history SET status = "accepted", order_id = ? WHERE user_id = ? AND order_date = ?');
                $stmt->execute([$accepted_order_id, $order['user_id'], $order['order_date']]);

                if ($stmt->rowCount() === 0) {
                    $stmt = $pdo->prepare('INSERT INTO order_history (user_id, customer_name, total_amount, order_date, order_details, status, order_id) 
                                        VALUES (?, ?, ?, ?, ?, "accepted", ?)');
                    $stmt->execute([
                        $order['user_id'],
                        $order['customer_name'],
                        $order['total_amount'],
                        $order['order_date'],
                        $order['order_details'],
                        $accepted_order_id
                    ]);
                }
            } catch (PDOException $e) {
                // If order_history table doesn't exist, continue without it
                error_log("Order history update failed: " . $e->getMessage());
            }

            // Delete from pending_orders
            $stmt = $pdo->prepare('DELETE FROM pending_orders WHERE id = ?');
            $stmt->execute([$order_id]);

            // Send acceptance email notification
            try {
                $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
                $stmt->execute([$order['user_id']]);
                $userEmail = $stmt->fetchColumn();

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
                $mail->Subject = 'Order Accepted - Arko Flavours';
                $mail->isHTML(true);
                $mail->Body = '
                    <div style="font-family: Poppins, sans-serif; max-width: 600px; margin: 0 auto;">
                        <h2 style="color: #2ecc71;">🎉 Order Accepted!</h2>
                        <p>Dear ' . htmlspecialchars($order['customer_name']) . ',</p>
                        <p>Great news! Your order has been accepted and is now being processed.</p>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                            <strong>Order Total: ₱' . number_format($order['total_amount'], 2) . '</strong><br>
                            <strong>Order Date: ' . date('F j, Y g:i A', strtotime($order['order_date'])) . '</strong><br>
                            <strong>Order ID: #' . $accepted_order_id . '</strong><br>
                            <strong>You can pay us using GCash at 09368128322 </strong>
                        </div>
                        <p>We will notify you once your order is ready for pickup/delivery.</p>
                        <p style="color: red;">Dont forget to take an screenshot for paying your order.</p>
                        <p>Thank you for choosing Arko Flavours!</p>
                    </div>
                ';

                if (@$mail->send()) {
                    $_SESSION['success_message'] = "✅ Order successfully accepted! Customer has been notified via email.";
                } else {
                    $_SESSION['success_message'] = "✅ Order accepted! (Email notification failed)";
                }
            } catch (Exception $e) {
                $_SESSION['success_message'] = "✅ Order accepted! (Email notification failed)";
            }

            $pdo->commit();
            header('Location: manageOrders.php?tab=pending');
            exit;
        } else {
            $_SESSION['error_message'] = "❌ Order not found!";
            header('Location: manageOrders.php?tab=pending');
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "❌ Error accepting order: " . $e->getMessage();
        header('Location: manageOrders.php?tab=pending');
        exit;
    }
}

// Complete accepted order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order'])) {
    $order_id = intval($_POST['order_id']);

    $pdo->beginTransaction();

    try {
        // Get order from accepted_orders
        $stmt = $pdo->prepare('SELECT * FROM accepted_orders WHERE id = ?');
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Move to completed_orders
            $stmt = $pdo->prepare('INSERT INTO completed_orders (user_id, customer_name, total_amount, order_date, order_details, payment_proof, delivered) 
                                VALUES (?, ?, ?, ?, ?, ?, 0)');
            $stmt->execute([
                $order['user_id'],
                $order['customer_name'],
                $order['total_amount'],
                $order['order_date'],
                $order['order_details'],
                $order['payment_proof'] ?? null
            ]);

            $completed_order_id = $pdo->lastInsertId();

            // Update order_history
            try {
                $stmt = $pdo->prepare('UPDATE order_history SET status = "completed", order_id = ? WHERE order_id = ?');
                $stmt->execute([$completed_order_id, $order_id]);
            } catch (PDOException $e) {
                // If update fails, try insert
                $stmt = $pdo->prepare('INSERT INTO order_history (user_id, customer_name, total_amount, order_date, order_details, status, order_id) 
                                    VALUES (?, ?, ?, ?, ?, "completed", ?)');
                $stmt->execute([
                    $order['user_id'],
                    $order['customer_name'],
                    $order['total_amount'],
                    $order['order_date'],
                    $order['order_details'],
                    $completed_order_id
                ]);
            }

            // Delete from accepted_orders
            $stmt = $pdo->prepare('DELETE FROM accepted_orders WHERE id = ?');
            $stmt->execute([$order_id]);

            // Send completion email
            try {
                $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
                $stmt->execute([$order['user_id']]);
                $userEmail = $stmt->fetchColumn();

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
                $mail->Subject = 'Order Completed - Arko Flavours';
                $mail->isHTML(true);
                $mail->Body = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <h2 style="color: #2ecc71;">✅ Order Completed!</h2>
                        <p>Dear ' . htmlspecialchars($order['customer_name']) . ',</p>
                        <p>Your order has been completed and is ready for pickup/delivery!</p>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                            <strong>Order Total: ₱' . number_format($order['total_amount'], 2) . '</strong><br>
                            <strong>Order Date: ' . date('F j, Y g:i A', strtotime($order['order_date'])) . '</strong><br>
                            <strong>Order ID: #' . $completed_order_id . '</strong>
                        </div>
                        <p>Please proceed to pickup your order or wait for delivery.</p>
                        <p>Thank you for choosing Arko Flavours!</p>
                    </div>
                ';

                if (@$mail->send()) {
                    $_SESSION['success_message'] = "✅ Order marked as completed! Customer has been notified via email.";
                } else {
                    $_SESSION['success_message'] = "✅ Order completed! (Email notification failed)";
                }
            } catch (Exception $e) {
                $_SESSION['success_message'] = "✅ Order completed! (Email notification failed)";
            }

            $pdo->commit();
            header('Location: manageOrders.php?tab=accepted');
            exit;
        } else {
            $_SESSION['error_message'] = "❌ Order not found!";
            header('Location: manageOrders.php?tab=accepted');
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "❌ Error completing order: " . $e->getMessage();
        header('Location: manageOrders.php?tab=accepted');
        exit;
    }
}

// Mark as delivered
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_delivered'])) {
    $order_id = intval($_POST['order_id']);

    $pdo->beginTransaction();

    try {
        // Update completed_orders
        $stmt = $pdo->prepare('UPDATE completed_orders SET delivered = 1 WHERE id = ?');
        $stmt->execute([$order_id]);

        // Update order_history
        try {
            $stmt = $pdo->prepare('UPDATE order_history SET delivered = 1, status = "delivered", delivery_date = NOW() WHERE order_id = ?');
            $stmt->execute([$order_id]);
        } catch (PDOException $e) {
            // If update fails, continue without order_history
        }

        // Get user info for notification
        $stmt = $pdo->prepare('SELECT user_id, customer_name FROM completed_orders WHERE id = ?');
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Send delivery email
            try {
                $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
                $stmt->execute([$order['user_id']]);
                $userEmail = $stmt->fetchColumn();

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
                $mail->Subject = 'Order Delivered - Arko Flavours';
                $mail->isHTML(true);
                $mail->Body = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <h2 style="color: #2ecc71;">🎉 Order Delivered!</h2>
                        <p>Dear ' . htmlspecialchars($order['customer_name']) . ',</p>
                        <p>Your order has been successfully delivered!</p>
                        <p>Thank you for choosing Arko Flavours. We hope to serve you again soon!</p>
                    </div>
                ';

                if (@$mail->send()) {
                    $_SESSION['success_message'] = "✅ Order marked as delivered! Customer has been notified via email.";
                } else {
                    $_SESSION['success_message'] = "✅ Order delivered! (Email notification failed)";
                }
            } catch (Exception $e) {
                $_SESSION['success_message'] = "✅ Order delivered! (Email notification failed)";
            }
        } else {
            $_SESSION['success_message'] = "✅ Order marked as delivered!";
        }

        $pdo->commit();
        header('Location: manageOrders.php?tab=completed');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "❌ Error marking order as delivered: " . $e->getMessage();
        header('Location: manageOrders.php?tab=completed');
        exit;
    }
}

// ========== DATA FETCHING ==========

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$min_amount = $_GET['min_amount'] ?? '';
$max_amount = $_GET['max_amount'] ?? '';
$delivery_status = $_GET['delivery_status'] ?? 'all';

// Fetch orders based on current tab
$orders = [];
$total_orders = 0;
$total_revenue = 0;
$avg_order_value = 0;
$additional_stats = [];

switch ($current_tab) {
    case 'pending':
        $query = 'SELECT * FROM pending_orders WHERE 1=1';
        $params = [];

        if (!empty($search)) {
            $query .= ' AND (customer_name LIKE ? OR order_details LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($date_from)) {
            $query .= ' AND DATE(order_date) >= ?';
            $params[] = $date_from;
        }

        if (!empty($date_to)) {
            $query .= ' AND DATE(order_date) <= ?';
            $params[] = $date_to;
        }

        if (!empty($min_amount)) {
            $query .= ' AND total_amount >= ?';
            $params[] = $min_amount;
        }

        if (!empty($max_amount)) {
            $query .= ' AND total_amount <= ?';
            $params[] = $max_amount;
        }

        $query .= ' ORDER BY order_date DESC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_orders = count($orders);
        $total_revenue = array_sum(array_column($orders, 'total_amount'));
        $avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
        break;

    case 'accepted':
        $query = 'SELECT * FROM accepted_orders WHERE 1=1';
        $params = [];

        if (!empty($search)) {
            $query .= ' AND (customer_name LIKE ? OR order_details LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($date_from)) {
            $query .= ' AND DATE(order_date) >= ?';
            $params[] = $date_from;
        }

        if (!empty($date_to)) {
            $query .= ' AND DATE(order_date) <= ?';
            $params[] = $date_to;
        }

        if (!empty($min_amount)) {
            $query .= ' AND total_amount >= ?';
            $params[] = $min_amount;
        }

        if (!empty($max_amount)) {
            $query .= ' AND total_amount <= ?';
            $params[] = $max_amount;
        }

        $query .= ' ORDER BY order_date DESC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_orders = count($orders);
        $total_revenue = array_sum(array_column($orders, 'total_amount'));
        $avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
        $orders_with_payment = count(array_filter($orders, fn($order) => !empty($order['payment_proof'])));
        $additional_stats['with_payment'] = $orders_with_payment;
        break;

    case 'completed':
        $query = 'SELECT * FROM completed_orders WHERE 1=1';
        $params = [];

        if (!empty($search)) {
            $query .= ' AND (customer_name LIKE ? OR order_details LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($delivery_status === 'delivered') {
            $query .= ' AND delivered = 1';
        } elseif ($delivery_status === 'pending') {
            $query .= ' AND delivered = 0';
        }

        if (!empty($date_from)) {
            $query .= ' AND DATE(order_date) >= ?';
            $params[] = $date_from;
        }

        if (!empty($date_to)) {
            $query .= ' AND DATE(order_date) <= ?';
            $params[] = $date_to;
        }

        if (!empty($min_amount)) {
            $query .= ' AND total_amount >= ?';
            $params[] = $min_amount;
        }

        if (!empty($max_amount)) {
            $query .= ' AND total_amount <= ?';
            $params[] = $max_amount;
        }

        $query .= ' ORDER BY order_date DESC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_orders = count($orders);
        $delivered_orders = count(array_filter($orders, fn($order) => $order['delivered']));
        $pending_delivery = $total_orders - $delivered_orders;
        $total_revenue = array_sum(array_column($orders, 'total_amount'));
        $avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
        $additional_stats['delivered'] = $delivered_orders;
        $additional_stats['pending_delivery'] = $pending_delivery;
        break;
}

// Function to find image path
function findImagePath($image_path)
{
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

// Function to get product image
function getProductImage($menuId, $pdo)
{
    if (empty($menuId)) return null;
    
    try {
        $stmt = $pdo->prepare('SELECT image_path FROM menu_items WHERE id = ?');
        $stmt->execute([$menuId]);
        $imagePath = $stmt->fetchColumn();
        
        if ($imagePath) {
            return findImagePath($imagePath);
        }
    } catch (PDOException $e) {
        error_log("Error fetching product image: " . $e->getMessage());
    }
    
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Food Shop</title>
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

        /* Add alert styles */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 1rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background-color: #e8f8f0;
            color: #2ecc71;
            border-color: #d4edda;
        }

        .alert-danger {
            background-color: #ffeaea;
            color: #e74c3c;
            border-color: #f5c6cb;
        }

        /* Tabs */
        .tabs-container {
            background: white;
            border-radius: 12px;
            padding: 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid #e9ecef;
        }

        .tab {
            padding: 1rem 2rem;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            transition: all 0.3s;
            position: relative;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .tab.active {
            color: #4361ee;
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 3px;
            background: #4361ee;
        }

        .tab:hover {
            background: #f8f9fa;
            color: #4361ee;
        }

        .tab-badge {
            background: #e74c3c;
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }

        /* Summary Cards */
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

        /* Order Cards */
        .order-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .order-card.pending {
            border-left: 4px solid #4361ee;
        }

        .order-card.accepted {
            border-left: 4px solid #2ecc71;
        }

        .order-card.completed {
            border-left: 4px solid #ffc107;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .order-id {
            font-weight: 600;
            color: #222e3c;
            font-size: 1.1rem;
        }

        .order-customer {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .order-amount {
            font-weight: 600;
            font-size: 1.2rem;
        }

        .order-card.pending .order-amount {
            color: #4361ee;
        }

        .order-card.accepted .order-amount {
            color: #2ecc71;
        }

        .order-card.completed .order-amount {
            color: #ffc107;
        }

        .order-date {
            color: #888;
            font-size: 0.85rem;
            text-align: right;
        }

        .order-items {
            margin: 1rem 0;
        }

        .order-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .item-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 1rem;
            border: 2px solid #e9ecef;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: #222e3c;
            margin-bottom: 0.25rem;
        }

        .item-meta {
            color: #666;
            font-size: 0.85rem;
        }

        .order-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .btn-success {
            background: #2ecc71;
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .status-pending {
            background: #fff4e6;
            color: #e67e22;
        }

        .status-urgent {
            background: #ffeaea;
            color: #e74c3c;
        }

        .status-accepted {
            background: #e8f8f0;
            color: #2ecc71;
        }

        .status-payment-pending {
            background: #fff4e6;
            color: #e67e22;
        }

        .status-delivered {
            background: #e8f8f0;
            color: #2ecc71;
        }

        .status-pending-delivery {
            background: #fff4e6;
            color: #e67e22;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #666;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .empty-state img {
            width: 150px;
            height: 150px;
            margin-bottom: 1rem;
            opacity: 0.7;
        }

        .empty-state h3 {
            color: #222e3c;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        /* Payment Section */
        .payment-section {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #4361ee;
        }

        .payment-image {
            max-width: 300px;
            max-height: 300px;
            border-radius: 8px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .payment-image:hover {
            transform: scale(1.05);
        }

        .image-not-found {
            padding: 1rem;
            background: #ffeaa7;
            border-radius: 6px;
            color: #856404;
            text-align: center;
        }

        .payment-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-actions {
                justify-content: stretch;
            }

            .btn {
                flex: 1;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <?php include '../../adminSidebar.php'; ?>

    <div class="main-content">
                <?php include '../admin_header.php'; ?>


        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs-container">
            <div class="tabs">
                <a href="?tab=pending" class="tab <?= $current_tab === 'pending' ? 'active' : '' ?>">
                    ⏳ Pending Orders
                    <?php if ($current_tab === 'pending' && $total_orders > 0): ?>
                        <span class="tab-badge"><?= $total_orders ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=accepted" class="tab <?= $current_tab === 'accepted' ? 'active' : '' ?>">
                    ✅ Accepted Orders
                    <?php if ($current_tab === 'accepted' && $total_orders > 0): ?>
                        <span class="tab-badge"><?= $total_orders ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=completed" class="tab <?= $current_tab === 'completed' ? 'active' : '' ?>">
                    📦 Completed Orders
                    <?php if ($current_tab === 'completed' && $total_orders > 0): ?>
                        <span class="tab-badge"><?= $total_orders ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <img src="/Food_System/assets/icons/emptycart.jpg" alt="No orders" style="width: 150px; height: 150px; margin-bottom: 1rem;">
                <h3>No <?= $current_tab ?> Orders</h3>
                <p>
                    <?php
                    switch ($current_tab) {
                        case 'pending':
                            echo 'All orders have been processed! Check back later for new orders.';
                            break;
                        case 'accepted':
                            echo 'All orders have been processed! Check pending orders for new requests.';
                            break;
                        case 'completed':
                            echo 'All orders are being processed! Check accepted orders for new completions.';
                            break;
                    }
                    ?>
                </p>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $row):
                    $order_age = time() - strtotime($row['order_date']);
                    $is_urgent = $order_age > 3600;
                    $is_old = $order_age > 86400;
                ?>
                    <div class="order-card <?= $current_tab ?>" id="order-<?= $row['id'] ?>">
                        <div class="order-header">
                            <div>
                                <div class="order-id">
                                    Order #<?= $row['id'] ?>
                                    <?php if ($current_tab === 'pending'): ?>
                                        <span class="status-badge <?= $is_urgent ? 'status-urgent' : 'status-pending' ?>">
                                            <?= $is_urgent ? '⚠️ URGENT' : '⏳ PENDING' ?>
                                        </span>
                                    <?php elseif ($current_tab === 'accepted'): ?>
                                        <span class="status-badge status-accepted">✅ ACCEPTED</span>
                                        <?php if ($is_urgent): ?>
                                            <span class="status-badge status-payment-pending">⏰ WAITING</span>
                                        <?php endif; ?>
                                    <?php elseif ($current_tab === 'completed'): ?>
                                        <span class="status-badge <?= $row['delivered'] ? 'status-delivered' : 'status-pending-delivery' ?>">
                                            <?= $row['delivered'] ? '✅ DELIVERED' : '⏳ PENDING DELIVERY' ?>
                                        </span>
                                        <?php if (!$row['delivered'] && $is_old): ?>
                                            <span class="status-badge status-urgent">⚠️ OVERDUE</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="order-customer">👤 <?= htmlspecialchars($row['customer_name']) ?></div>
                                <?php if ($current_tab === 'accepted'): ?>
                                    <div class="payment-status">
                                        <?php if (!empty($row['payment_proof'])): ?>
                                            <span style="color: #2ecc71;">💳 Payment Uploaded</span>
                                        <?php else: ?>
                                            <span style="color: #e74c3c;">⏳ Payment Pending</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: right;">
                                <div class="order-amount">₱<?= number_format($row['total_amount'], 2) ?></div>
                                <div class="order-date">
                                    📅 <?= date('M j, Y g:i A', strtotime($row['order_date'])) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items with Images -->
                        <div class="order-items">
                            <?php
                            $orderDetails = json_decode($row['order_details'], true);
                            if (is_array($orderDetails)):
                                foreach ($orderDetails as $item):
                                    $productImage = getProductImage($item['menuId'] ?? null, $pdo);
                            ?>
                                    <div class="order-item">
                                        <?php if ($productImage): ?>
                                            <img src="<?= $productImage ?>" alt="<?= htmlspecialchars($item['name'] ?? 'Product') ?>" class="item-image">
                                        <?php else: ?>
                                            <div class="item-image" style="background: #e9ecef; display: flex; align-items: center; justify-content: center; color: #666; font-size: 0.8rem;">
                                                No Image
                                            </div>
                                        <?php endif; ?>
                                        <div class="item-details">
                                            <div class="item-name"><?= htmlspecialchars($item['name'] ?? 'Unknown Item') ?></div>
                                            <div class="item-meta">
                                                <?php if (!empty($item['variations'])): ?>
                                                    <strong>Variation:</strong> <?= htmlspecialchars($item['variations']) ?> •
                                                <?php endif; ?>
                                                <strong>Qty:</strong> <?= htmlspecialchars($item['quantity'] ?? 1) ?>
                                                <?php if (!empty($item['addons'])): ?>
                                                    <br><strong>Add-ons: </strong> <?= htmlspecialchars(implode(', ', $item['addons'])) ?>
                                                <?php endif; ?>
                                                <?php if (!empty($item['notes'])): ?>
                                                    <br><strong>Notes: </strong><?= htmlspecialchars($item['notes']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                            <?php endforeach;
                            endif; ?>
                        </div>

                        <div class="order-actions">
                            <?php if ($current_tab === 'pending'): ?>
                                <form method="post" action="" style="display: inline;">
                                    <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="accept_order" class="btn btn-success" onclick="return confirm('Are you sure you want to accept this order?')">
                                        ✅ Accept Order
                                    </button>
                                </form>
                                <button type="button" class="btn btn-danger" onclick="showDeclineModal(<?= $row['id'] ?>)">
                                    ❌ Decline Order
                                </button>

                            <?php elseif ($current_tab === 'accepted'): ?>
                                <form method="post" action="" style="display: inline;">
                                    <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="complete_order" class="btn btn-success" onclick="return confirm('Are you sure you want to mark this order as completed?')">
                                        ✅ Mark as Completed
                                    </button>
                                </form>

                            <?php elseif ($current_tab === 'completed'): ?>
                                <?php if (!$row['delivered']): ?>
                                    <form method="post" action="" style="display: inline;">
                                        <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="mark_delivered" class="btn btn-success" onclick="return confirm('Are you sure you want to mark this order as delivered?')">
                                            🚚 Mark Delivered
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="btn btn-success" style="opacity: 0.6; cursor: default;">✅ Delivered</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Decline Modal -->
    <div id="declineModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="modal-content" style="background: white; padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%;">
            <h3>Decline Order</h3>
            <p>Please provide a reason for declining this order:</p>
            <form method="POST" action="" id="declineForm">
                <input type="hidden" name="order_id" id="declineOrderId">
                <input type="hidden" name="decline_order" value="1">
                <textarea name="decline_reason" class="decline-reason" placeholder="Enter reason for declining this order..." required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; margin: 1rem 0; font-family: inherit; resize: vertical; min-height: 100px;"></textarea>
                <div class="modal-actions" style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeDeclineModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Decline Order</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showDeclineModal(orderId) {
            document.getElementById('declineOrderId').value = orderId;
            document.getElementById('declineModal').style.display = 'flex';
        }

        function closeDeclineModal() {
            document.getElementById('declineModal').style.display = 'none';
            document.getElementById('declineForm').reset();
        }

        // Close modal when clicking outside
        document.getElementById('declineModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeclineModal();
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>

</html>