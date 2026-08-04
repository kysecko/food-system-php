<?php
require_once '../Log-in Form/includes/config_session.inc.php';
require_once '../Log-in Form/includes/dbh.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Log-in Form/login.php');
    exit;
}

$order_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $user_id = $_SESSION['user_id'];
    $customer_name = $_SESSION['user_username'];
    $order_details = $_POST['order_details'];
    $total_amount = floatval($_POST['total_amount']);
    $order_date = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO order_history (user_id, customer_name, total_amount, order_date, order_details, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$user_id, $customer_name, $total_amount, $order_date, $order_details]);

    $stmt = $pdo->prepare("INSERT INTO pending_orders (user_id, customer_name, total_amount, order_date, order_details) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $customer_name, $total_amount, $order_date, $order_details]);

    $order_message = "Order submitted successfully!";
}

$stmt = $pdo->prepare('SELECT is_verified, email, role FROM users WHERE id = :id');
$stmt->bindParam(':id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && $user['is_verified'] == 0) {
    $_SESSION['verify_email'] = $user['email'];
    header('Location: ../Log-in Form/verify.php');
    exit;
}

if ($user && $user['role'] === 'admin') {
    header('Location: ../admin dashboard/adminDashboard.php');
    exit;
}

// Handle payment upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_payment']) && isset($_FILES['payment_screenshot'])) {
    $order_id = intval($_POST['order_id']);
    $file = $_FILES['payment_screenshot'];
    $target_dir = '../uploads/';
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $filename = 'payment_' . $order_id . '_' . time() . '_' . basename($file['name']);
    $target_file = $target_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $stmt = $pdo->prepare('UPDATE order_history SET payment_screenshot = ? WHERE id = ?');
        $stmt->execute([$filename, $order_id]);
        
        // Also update accepted_orders if it exists
        try {
            $stmt = $pdo->prepare('UPDATE accepted_orders SET payment_screenshot = ? WHERE id = ?');
            $stmt->execute([$filename, $order_id]);
        } catch (PDOException $e) {
            // Table might not exist, ignore error
        }
        
        echo '<script>alert("Payment screenshot uploaded successfully!"); window.location ="userDashboard.php";</script>';
    } else {
        echo '<script>alert("Upload failed. Please try again.");</script>';
    }
}

// Handle mark as delivered
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_delivered'])) {
    $order_id = intval($_POST['order_id']);
    $stmt = $pdo->prepare('UPDATE order_history SET status = "delivered" WHERE id = ?');
    $stmt->execute([$order_id]);
    
    // Also update completed_orders if it exists
    try {
        $stmt = $pdo->prepare('UPDATE completed_orders SET delivered = 1 WHERE id = ?');
        $stmt->execute([$order_id]);
    } catch (PDOException $e) {
        // Table might not exist, ignore error
    }
    
  echo '<script>alert("Order marked as delivered!"); window.location = "userDashboard.php";</script>';

}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Arko Flavors</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/food-system/food-system-php/user dashboard/design/userDashboard.css">
    <style>
        .order-details {
            max-width: 300px;
        }
        .order-item {
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .order-item:last-child {
            margin-bottom: 0;
        }
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status.pending { background: #fff4e6; color: #e67e22; }
        .status.accepted { background: #e8f8f0; color: #2ecc71; }
        .status.completed { background: #e7f3ff; color: #3498db; }
        .status.delivered { background: #e8f8f0; color: #27ae60; }
        .status.declined { background: #ffeaea; color: #e74c3c; }
        .status.awaiting-payment { background: #fff4e6; color: #e67e22; }
        .action-btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .action-btn.secondary {
            background: #6c757d;
            color: white;
        }
        .action-btn:hover {
            opacity: 0.8;
        }
        .no-data {
            text-align: center;
            color: #666;
            padding: 2rem;
        }
    </style>
</head>

<body>
    <?php include '../userSidebar.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_username']); ?>!</h1>
            <p>Manage your orders and track your food journey</p>
        </div>

        <?php if ($order_message): ?>
            <div style="background: #e8f8f0; color: #2ecc71; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <?php echo $order_message; ?>
            </div>
        <?php endif; ?>

        <!-- Order Statistics -->
        <div class="summary-cards">
            <?php
            // Total Orders
            $total_orders_query = "SELECT COUNT(*) as total_orders FROM order_history WHERE user_id = ?";
            $total_orders_stmt = $pdo->prepare($total_orders_query);
            $total_orders_stmt->execute([$_SESSION['user_id']]);
            $total_orders = $total_orders_stmt->fetch(PDO::FETCH_ASSOC)['total_orders'];

            // Pending Orders
            $pending_orders_query = "SELECT COUNT(*) as pending_orders FROM order_history WHERE user_id = ? AND status = 'pending'";
            $pending_orders_stmt = $pdo->prepare($pending_orders_query);
            $pending_orders_stmt->execute([$_SESSION['user_id']]);
            $pending_orders = $pending_orders_stmt->fetch(PDO::FETCH_ASSOC)['pending_orders'];

            // Completed Orders
            $completed_orders_query = "SELECT COUNT(*) as completed_orders FROM order_history WHERE user_id = ? AND status = 'completed'";
            $completed_orders_stmt = $pdo->prepare($completed_orders_query);
            $completed_orders_stmt->execute([$_SESSION['user_id']]);
            $completed_orders = $completed_orders_stmt->fetch(PDO::FETCH_ASSOC)['completed_orders'];

            // Total Spent
            $total_spent_query = "SELECT COALESCE(SUM(total_amount), 0) as total_spent FROM order_history WHERE user_id = ? AND status = 'completed'";
            $total_spent_stmt = $pdo->prepare($total_spent_query);
            $total_spent_stmt->execute([$_SESSION['user_id']]);
            $total_spent = $total_spent_stmt->fetch(PDO::FETCH_ASSOC)['total_spent'];
            ?>
            
            <div class="summary-card">
                <h3>TOTAL ORDERS</h3>
                <div class="value"><?php echo $total_orders; ?></div>
                <div class="trend up">↑ All time orders</div>
            </div>
            <div class="summary-card">
                <h3>PENDING ORDERS</h3>
                <div class="value"><?php echo $pending_orders; ?></div>
                <div class="trend down">↓ Being processed</div>
            </div>
            <div class="summary-card">
                <h3>COMPLETED ORDERS</h3>
                <div class="value"><?php echo $completed_orders; ?></div>
                <div class="trend up">↑ Successfully delivered</div>
            </div>
            <div class="summary-card">
                <h3>TOTAL SPENT</h3>
                <div class="value">₱<?php echo number_format($total_spent, 2); ?></div>
                <div class="trend up">↑ All time spending</div>
            </div>
        </div>

        <!-- Order History -->
        <div class="orders-container">
            <h2>Your Order History</h2>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Details</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Delivery</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->prepare('SELECT * FROM order_history WHERE user_id = ? ORDER BY order_date DESC');
                    $stmt->execute([$_SESSION['user_id']]);
                    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($orders)) {
                        echo '<tr><td colspan="8" class="no-data">No orders found</td></tr>';
                    } else {
                        foreach ($orders as $row) {
                            echo '<tr>';
                            echo '<td>#' . $row['id'] . '</td>';
                            echo '<td>';
                            
                            // Safely decode order details
                            $orderDetails = [];
                            if (!empty($row['order_details'])) {
                                $orderDetails = json_decode($row['order_details'], true);
                            }
                            
                            if (is_array($orderDetails) && !empty($orderDetails)) {
                                echo '<div class="order-details">';
                                foreach ($orderDetails as $item) {
                                    if (is_array($item)) {
                                        echo '<div class="order-item">';
                                        echo '<strong>' . htmlspecialchars($item['name'] ?? 'Unknown Item') . '</strong>';
                                        echo ' (' . htmlspecialchars($item['size'] ?? 'Regular') . ') x ' . htmlspecialchars($item['quantity'] ?? $item['qty'] ?? '1');
                                        if (!empty($item['addons'])) {
                                            echo '<br><small>Add-ons: ' . htmlspecialchars(implode(', ', $item['addons'])) . '</small>';
                                        }
                                        if (!empty($item['notes'])) {
                                            echo '<br><small>Notes: ' . htmlspecialchars($item['notes']) . '</small>';
                                        }
                                        echo '</div>';
                                    }
                                }
                                echo '</div>';
                            } else {
                                echo '<div class="order-details">';
                                echo '<div class="order-item">';
                                echo 'Order details not available';
                                echo '</div>';
                                echo '</div>';
                            }
                            echo '</td>';
                            
                            echo '<td><strong>₱' . number_format($row['total_amount'], 2) . '</strong></td>';
                            echo '<td>' . date('M j, g:i A', strtotime($row['order_date'])) . '</td>';
                            
                            // Status with colored badges
                            $status = $row['status'] ?? 'pending';
                            $status_class = str_replace(' ', '-', strtolower($status));
                            echo '<td><span class="status ' . $status_class . '">' . htmlspecialchars($status) . '</span></td>';

                            // Payment column - Check if payment_screenshot column exists
                            $has_payment = false;
                            try {
                                $check_payment_stmt = $pdo->prepare("SHOW COLUMNS FROM order_history LIKE 'payment_screenshot'");
                                $check_payment_stmt->execute();
                                $has_payment = $check_payment_stmt->rowCount() > 0;
                            } catch (PDOException $e) {
                                $has_payment = false;
                            }

                            if ($has_payment && !empty($row['payment_screenshot'])) {
                                echo '<td><a href="../uploads/' . htmlspecialchars($row['payment_screenshot']) . '" target="_blank" class="action-btn secondary">View Receipt</a></td>';
                            } else {
                                echo '<td>
                                    <form method="post" enctype="multipart/form-data" style="display:inline;">
                                        <input type="hidden" name="order_id" value="' . $row['id'] . '">
                                        <input type="file" name="payment_screenshot" accept="image/*" required style="margin-bottom: 0.5rem;">
                                        <button type="submit" name="upload_payment" class="action-btn">Upload</button>
                                    </form>
                                </td>';
                            }

                            // Delivery column - Check if delivered column exists
                            $has_delivered = false;
                            try {
                                $check_delivered_stmt = $pdo->prepare("SHOW COLUMNS FROM order_history LIKE 'delivered'");
                                $check_delivered_stmt->execute();
                                $has_delivered = $check_delivered_stmt->rowCount() > 0;
                            } catch (PDOException $e) {
                                $has_delivered = false;
                            }

                            $is_delivered = $has_delivered && isset($row['delivered']) && $row['delivered'] == 1;
                            
                            if ($row['status'] === 'completed' && !$is_delivered) {
                                echo '<td><span class="status awaiting-payment">To be delivered</span></td>';
                                echo '<td>
                                    <form method="post" action="userDashboard.php">
                                        <input type="hidden" name="order_id" value="' . $row['id'] . '">
                                        <button type="submit" name="mark_delivered" class="action-btn">Mark Delivered</button>
                                    </form>
                                </td>';
                            } elseif ($is_delivered) {
                                echo '<td><span class="status delivered">Delivered</span></td><td>-</td>';
                            } else {
                                echo '<td>-</td><td>-</td>';
                            }
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Orders Awaiting Payment -->
        <div class="orders-container">
            <h2>Orders Awaiting Payment</h2>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Details</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Upload Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Check if payment_screenshot column exists
                    $has_payment_column = false;
                    try {
                        $check_column_stmt = $pdo->prepare("SHOW COLUMNS FROM order_history LIKE 'payment_screenshot'");
                        $check_column_stmt->execute();
                        $has_payment_column = $check_column_stmt->rowCount() > 0;
                    } catch (PDOException $e) {
                        $has_payment_column = false;
                    }

                    if ($has_payment_column) {
                        $stmt = $pdo->prepare('SELECT * FROM order_history WHERE user_id = ? AND (payment_screenshot IS NULL OR payment_screenshot = "") AND status IN ("pending", "accepted") ORDER BY order_date DESC');
                        $stmt->execute([$_SESSION['user_id']]);
                        $pending_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $pending_payments = [];
                    }

                    if (empty($pending_payments)) {
                        echo '<tr><td colspan="6" class="no-data">No orders awaiting payment</td></tr>';
                    } else {
                        foreach ($pending_payments as $row) {
                            echo '<tr>';
                            echo '<td>#' . $row['id'] . '</td>';
                            
                            // Display order details safely
                            $orderDetails = [];
                            if (!empty($row['order_details'])) {
                                $orderDetails = json_decode($row['order_details'], true);
                            }
                            
                            if (is_array($orderDetails) && !empty($orderDetails)) {
                                echo '<td>';
                                echo '<div class="order-details">';
                                $first_item = $orderDetails[0];
                                if (is_array($first_item)) {
                                    echo '<strong>' . htmlspecialchars($first_item['name'] ?? 'Unknown Item') . '</strong>';
                                    if (count($orderDetails) > 1) {
                                        echo ' + ' . (count($orderDetails) - 1) . ' more items';
                                    }
                                }
                                echo '</div>';
                                echo '</td>';
                            } else {
                                echo '<td>Order details not available</td>';
                            }
                            
                            echo '<td><strong>₱' . number_format($row['total_amount'], 2) . '</strong></td>';
                            echo '<td>' . date('M j, g:i A', strtotime($row['order_date'])) . '</td>';
                            echo '<td><span class="status awaiting-payment">Awaiting Payment</span></td>';
                            echo '<td>
                                <form method="post" enctype="multipart/form-data" style="display:inline;">
                                    <input type="hidden" name="order_id" value="' . $row['id'] . '">
                                    <input type="file" name="payment_screenshot" accept="image/*" required style="margin-bottom: 0.5rem;">
                                    <button type="submit" name="upload_payment" class="action-btn">Upload Payment</button>
                                </form>
                            </td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Floating Order Button -->
    <button id="show-orders-btn" class="floating-order-btn">View Current Orders</button>

    <!-- Hidden Form for Order Submission -->
    <form id="submit-order-form" method="post" style="display:none;">
        <input type="hidden" name="order_details" id="order_details_input">
        <input type="hidden" name="total_amount" id="total_amount_input">
        <input type="hidden" name="submit_order" value="1">
    </form>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Initialize the floating button
            const showOrdersBtn = document.getElementById('show-orders-btn');
            if (showOrdersBtn) {
                showOrdersBtn.addEventListener('click', function() {
                    window.location.href = 'orderStatus.php';
                });
            }
        });
    </script>
</body>
</html>