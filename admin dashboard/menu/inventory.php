<?php
require_once '../../Log-in Form/includes/config_session.inc.php';
require_once '../../Log-in Form/includes/dbh.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../Log-in Form/login.php');
    exit;
}

// Handle stock updates via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $id = intval($_POST['id']);
    $stock = intval($_POST['stock']);
    
    if ($stock < 0) {
        echo json_encode(['success' => false, 'message' => 'Stock cannot be negative']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE menu_items SET stock = ? WHERE id = ?");
        $stmt->execute([$stock, $id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Stock update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update stock']);
    }
    exit;
}

// Fetch all menu items with their current stock
$stmt = $pdo->query("SELECT id, name, description, sizes, prices, stock, is_available FROM menu_items ORDER BY name");
$menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate inventory statistics
$total_items = count($menuItems);
$out_of_stock = count(array_filter($menuItems, fn($item) => $item['stock'] <= 0));
$low_stock = count(array_filter($menuItems, fn($item) => $item['stock'] > 0 && $item['stock'] <= 10));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Arko Flavours</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        .main-content {
            margin-left: 220px;
            padding: 2rem;
            background-color: #f5f7fa;
            min-height: 100vh;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 600;
            color: #222e3c;
        }

        .inventory-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .search-box {
            flex: 1;
            max-width: 300px;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .inventory-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
            width: 100%;
            margin-bottom: 2rem;
        }

        .inventory-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .inventory-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #222e3c;
            border-bottom: 2px solid #dee2e6;
        }

        .inventory-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .stock-input {
            width: 80px;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }

        .stock-input:focus {
            outline: none;
            border-color: #4361ee;
        }

        .stock-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .stock-normal {
            background: #e8f8f0;
            color: #2ecc71;
        }

        .stock-low {
            background: #fff3cd;
            color: #ffc107;
        }

        .stock-out {
            background: #ffeaea;
            color: #dc3545;
        }

        .btn-update {
            padding: 0.5rem 1rem;
            background: #4361ee;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-update:hover {
            background: #3a56e0;
        }

        .btn-update:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            transform: translateY(-100%);
            opacity: 0;
            transition: all 0.3s;
        }

        .notification.success {
            background: #2ecc71;
        }

        .notification.error {
            background: #e74c3c;
        }

        .notification.show {
            transform: translateY(0);
            opacity: 1;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .inventory-actions {
                flex-direction: column;
                gap: 1rem;
            }

            .search-box {
                max-width: none;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include '../../adminSidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>Inventory Management</h1>
            <p>Monitor and update your menu items stock levels</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Items</h3>
                <div class="value"><?php echo $total_items; ?></div>
            </div>
            <div class="stat-card">
                <h3>Out of Stock</h3>
                <div class="value"><?php echo $out_of_stock; ?></div>
            </div>
            <div class="stat-card">
                <h3>Low Stock</h3>
                <div class="value"><?php echo $low_stock; ?></div>
            </div>
        </div>

        <!-- Search and Actions -->
        <div class="inventory-actions">
            <input type="text" id="searchInput" class="search-box" placeholder="Search menu items...">
        </div>

        <!-- Inventory Table -->
        <div class="inventory-table">
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Description</th>
                        <th>Sizes & Prices</th>
                        <th>Current Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($menuItems as $item): ?>
                        <tr class="inventory-row">
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                            <td>
                                <?php
                                $sizes = explode(',', $item['sizes']);
                                $prices = explode(',', $item['prices']);
                                foreach ($sizes as $i => $size) {
                                    if (trim($size)) {
                                        echo htmlspecialchars(trim($size)) . ': ₱' . 
                                             htmlspecialchars(isset($prices[$i]) ? trim($prices[$i]) : '0') . '<br>';
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <input type="number" class="stock-input" 
                                       value="<?php echo intval($item['stock']); ?>" 
                                       min="0" data-id="<?php echo $item['id']; ?>"
                                       data-original="<?php echo intval($item['stock']); ?>">
                            </td>
                            <td>
                                <?php
                                $stock = intval($item['stock']);
                                if ($stock <= 0) {
                                    echo '<span class="stock-badge stock-out">Out of Stock</span>';
                                } elseif ($stock <= 10) {
                                    echo '<span class="stock-badge stock-low">Low Stock</span>';
                                } else {
                                    echo '<span class="stock-badge stock-normal">In Stock</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <button class="btn-update" disabled>Update</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Notification -->
        <div id="notification" class="notification"></div>
    </div>

    <script>
        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', function() {
            initStockInputs();
            initSearchFilter();
        });

        function initStockInputs() {
            const stockInputs = document.querySelectorAll('.stock-input');
            
            stockInputs.forEach(input => {
                // Store original value
                const originalValue = input.value;
                
                // Handle input changes
                input.addEventListener('input', function() {
                    const row = this.closest('tr');
                    const updateBtn = row.querySelector('.btn-update');
                    const newValue = parseInt(this.value) || 0;
                    
                    // Enable/disable update button
                    updateBtn.disabled = newValue === parseInt(originalValue) || newValue < 0;
                    
                    // Update status badge
                    updateStockBadge(row, newValue);
                });
                
                // Handle update button
                const updateBtn = input.closest('tr').querySelector('.btn-update');
                updateBtn.addEventListener('click', function() {
                    const id = input.dataset.id;
                    const newStock = parseInt(input.value) || 0;
                    
                    if (newStock < 0) {
                        showNotification('Stock cannot be negative', 'error');
                        return;
                    }
                    
                    updateStock(id, newStock, input, this);
                });
            });
        }

        function updateStock(id, stock, input, button) {
            button.disabled = true;
            
            $.ajax({
                url: 'inventory.php',
                type: 'POST',
                data: {
                    update_stock: 1,
                    id: id,
                    stock: stock
                },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        input.dataset.original = stock;
                        showNotification('Stock updated successfully', 'success');
                    } else {
                        showNotification(result.message || 'Failed to update stock', 'error');
                        input.value = input.dataset.original;
                    }
                },
                error: function() {
                    showNotification('Error updating stock', 'error');
                    input.value = input.dataset.original;
                }
            });
        }

        function updateStockBadge(row, stock) {
            const badge = row.querySelector('.stock-badge');
            badge.textContent = stock <= 0 ? 'Out of Stock' : 
                              stock <= 10 ? 'Low Stock' : 'In Stock';
            badge.className = 'stock-badge ' + 
                            (stock <= 0 ? 'stock-out' : 
                             stock <= 10 ? 'stock-low' : 'stock-normal');
        }

        function initSearchFilter() {
            const searchInput = document.getElementById('searchInput');
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.inventory-row');
                
                rows.forEach(row => {
                    const name = row.querySelector('td').textContent.toLowerCase();
                    const description = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    
                    if (name.includes(searchTerm) || description.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type} show`;
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>
