<?php
function getPendingOrders() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                po.id, 
                po.customer_name, 
                po.total_amount, 
                po.order_date,
                po.order_details,
                u.email as customer_email,
                TIMESTAMPDIFF(MINUTE, po.order_date, NOW()) as minutes_ago
            FROM pending_orders po 
            LEFT JOIN users u ON po.user_id = u.id 
            ORDER BY po.order_date DESC 
            LIMIT 10
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching pending orders: " . $e->getMessage());
        return [];
    }
}

// Function to get notification count
function getNotificationCount() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pending_orders");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error fetching notification count: " . $e->getMessage());
        return 0;
    }
}

$pendingOrders = getPendingOrders();
$notificationCount = getNotificationCount();

// Determine page title based on current file
$currentFile = basename($_SERVER['PHP_SELF']);
$currentPath = $_SERVER['PHP_SELF'];

$pageTitles = [
    'expenses.php' => ['title' => 'Expenses Management', 'desc' => 'Track and manage your business expenses.'],
    'sales.php' => ['title' => 'Sales Report', 'desc' => 'Comprehensive sales analytics and reports'],
    'manageOrders.php' => ['title' => 'Manage Orders', 'desc' => 'Review and manage customer orders across all stages'],
    'payment_invoices.php' => ['title' => 'Payment Proofs', 'desc' => 'View and verify customer payment screenshots'],
    'menu.php' => ['title' => 'Menu Management', 'desc' => 'Manage your restaurant menu items and availability'],
    'balance_sheet.php' => ['title' => 'Balance Sheet', 'desc' => 'Financial position overview of your company'],
    'cash_flow.php' => ['title' => 'Cash Flow Statement', 'desc' => 'Track your cash inflows, outflows, and net cash position'],
    'income_statement.php' => ['title' => 'Income Statement', 'desc' => 'Profit and Loss Summary'],
    'adminDashboard.php' => ['title' => 'Dashboard', 'desc' => 'Overview of your restaurant operations']
];

$pageInfo = $pageTitles[$currentFile] ?? ['title' => '⚙️ Admin Panel', 'desc' => 'Manage your restaurant operations'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }

        * {
            box-sizing: border-box;
        }

        .admin-header {
            background: white;
            border-radius: 16px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            animation: fadeIn 0.5s ease;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
        }

        .header-text {
            flex: 1;
        }

        .header-text h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
            color: var(--dark);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header-text p {
            color: var(--gray);
            font-size: 1rem;
            margin: 0;
        }

        .data-source-badge {
            background: linear-gradient(135deg, var(--success), #27ae60);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(46, 204, 113, 0.3);
        }  
        /* Notification Styles */
        .notification-container {
            position: relative;
        }

        .notification-bell {
            position: relative;
            border: 2px solid transparent;
            border-radius: 50%;
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 22px;
        }

        .notification-bell:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .notification-bell.has-notifications {
            background: var(--warning);
            color: white;
            animation: bellRing 2s infinite;
        }

        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 12px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            animation: pulse 2s infinite;
        }

        .notification-dropdown {
            position: absolute;
            top: calc(100% + 15px);
            right: 0;
            width: 420px;
            max-width: 90vw;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: none;
            max-height: 500px;
            overflow: hidden;
        }

        .notification-dropdown.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .notification-dropdown::before {
            content: '';
            position: absolute;
            top: -10px;
            right: 20px;
            width: 20px;
            height: 20px;
            background: white;
            transform: rotate(45deg);
        }

        .notification-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 2px solid var(--light-gray);
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        .notification-header h3 {
            margin: 0 0 0.25rem 0;
            color: var(--dark);
            font-size: 1.1rem;
            font-weight: 700;
        }

        .notification-header small {
            color: var(--gray);
            font-size: 0.85rem;
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-list::-webkit-scrollbar {
            width: 6px;
        }

        .notification-list::-webkit-scrollbar-track {
            background: var(--light);
        }

        .notification-list::-webkit-scrollbar-thumb {
            background: var(--gray);
            border-radius: 3px;
        }

        .notification-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--light-gray);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .notification-item:hover {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            transform: translateX(-5px);
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }

        .notification-details {
            color: var(--gray);
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }

        .notification-time {
            color: var(--gray);
            font-size: 0.75rem;
            font-style: italic;
        }

        .notification-amount {
            font-weight: 700;
            color: var(--primary);
            font-size: 1rem;
            white-space: nowrap;
        }

        .notification-footer {
            padding: 1rem 1.5rem;
            border-top: 2px solid var(--light-gray);
            text-align: center;
            background: var(--light);
        }

        .view-all-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .view-all-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark), #2d47b8);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(67, 97, 238, 0.4);
        }

        .no-notifications {
            padding: 3rem 2rem;
            text-align: center;
            color: var(--gray);
        }

        .no-notifications i {
            font-size: 3rem;
            color: var(--success);
            margin-bottom: 1rem;
        }

        .no-notifications p {
            margin: 0.5rem 0;
            font-weight: 600;
            color: var(--dark);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        @keyframes bellRing {
            0%, 100% { transform: rotate(0deg); }
            10%, 30% { transform: rotate(-10deg); }
            20%, 40% { transform: rotate(10deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .admin-header {
                padding: 1rem 1.5rem;
                margin-bottom: 1.5rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .header-text h1 {
                font-size: 1.5rem;
            }

            .notification-dropdown {
                width: 350px;
                right: -50px;
            }

            .notification-bell {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            .notification-dropdown {
                width: calc(100vw - 40px);
                right: -100px;
            }
            
            .header-text h1 {
                font-size: 1.25rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Header Component -->
    <div class="admin-header">
        <div class="header-content">
            <div class="header-text">
                <h1>
                    <?php echo $pageInfo['title']; ?>
                </h1>
                <p><?php echo $pageInfo['desc']; ?></p>
            </div>

            <!-- Notification Bell -->
            <div class="notification-container">
                <button class="notification-bell <?php echo $notificationCount > 0 ? 'has-notifications' : ''; ?>" id="notificationBell" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-count"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
                    <?php endif; ?>
                </button>

                <!-- Notification Dropdown -->
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3>🔔 Pending Orders (<?php echo $notificationCount; ?>)</h3>
                        <small>New orders waiting for your approval</small>
                    </div>
                    
                    <div class="notification-list">
                        <?php if (count($pendingOrders) > 0): ?>
                            <?php foreach ($pendingOrders as $order): ?>
                                <div class="notification-item" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                    <div class="notification-icon">
                                        <i class="fas fa-shopping-cart"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title">
                                            <?php echo htmlspecialchars($order['customer_name']); ?>
                                        </div>
                                        <div class="notification-details">
                                            <?php echo htmlspecialchars($order['customer_email']); ?>
                                        </div>
                                        <div class="notification-time">
                                            <?php
                                            $minutesAgo = $order['minutes_ago'];
                                            if ($minutesAgo < 1) {
                                                echo '⏰ Just now';
                                            } elseif ($minutesAgo < 60) {
                                                echo '⏰ ' . $minutesAgo . ' min' . ($minutesAgo > 1 ? 's' : '') . ' ago';
                                            } else {
                                                $hours = floor($minutesAgo / 60);
                                                echo '⏰ ' . $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="notification-amount">
                                        ₱<?php echo number_format($order['total_amount'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-notifications">
                                <i class="fas fa-check-circle"></i>
                                <p>All caught up!</p>
                                <small>No pending orders at the moment</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($pendingOrders) > 0): ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            // Toggle dropdown
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!notificationDropdown.contains(e.target) && e.target !== notificationBell) {
                    notificationDropdown.classList.remove('show');
                }
            });
            
            // Prevent dropdown from closing when clicking inside
            notificationDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
        
        // Function to view specific order
        function viewOrder(orderId) {
            const currentPath = window.location.pathname;
            let basePath = '';
            
            // Determine the correct path based on current location
            if (currentPath.includes('/managing_orders/')) {
                basePath = './manageOrders.php?tab=pending';
            } else if (currentPath.includes('/admin_dashboard/')) {
                basePath = 'managing_orders/manageOrders.php?tab=pending';
            } else {
                basePath = '/Food_System/admin_dashboard/managing_orders/manageOrders.php?tab=pending';
            }
            
            window.location.href = basePath;
        }
        
        // Auto-refresh notification count every 30 seconds (optional)
        setInterval(function() {
            // Uncomment to enable auto-refresh
            // fetch(window.location.href)
            //     .then(response => response.text())
            //     .then(html => {
            //         // Update notification count
            //     });
        }, 30000);
    </script>
</body>
</html>