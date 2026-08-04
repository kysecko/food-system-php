<?php
require_once '../Log-in Form/includes/config_session.inc.php';
require_once '../Log-in Form/includes/dbh.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Log-in Form/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = :user_id');
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Load user's cart items
$cart_items = [];
$stmt = $pdo->prepare('SELECT cart_data FROM user_carts WHERE user_id = :user_id');
$stmt->execute([':user_id' => $user_id]);
$cart_data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($cart_data && !empty($cart_data['cart_data'])) {
    $cart_items = json_decode($cart_data['cart_data'], true);
    
    // Get menu item images for cart items
    foreach ($cart_items as &$item) {
        $menu_stmt = $pdo->prepare('SELECT image_path FROM menu_items WHERE id = :menu_id');
        $menu_stmt->execute([':menu_id' => $item['menuId']]);
        $menu_item = $menu_stmt->fetch(PDO::FETCH_ASSOC);
        
        $item['image_path'] = $menu_item['image_path'] ?? '/Food_System/assets/images/default-food.jpg';
        // Fix image path if it's relative
        if (!empty($item['image_path']) && strpos($item['image_path'], '/Food_System/') !== 0) {
            $item['image_path'] = '/Food_System/' . $item['image_path'];
        }
    }
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    if (empty($cart_items)) {
        $_SESSION['error_message'] = 'Your cart is empty. Please add items before submitting.';
        header('Location: cart.php');
        exit;
    }
    
    // Calculate total amount
    $total_amount = 0;
    foreach ($cart_items as $item) {
        $total_amount += ($item['price'] * $item['quantity']);
    }
    
    try {
        // Insert into pending_orders
        $stmt = $pdo->prepare('
            INSERT INTO pending_orders (user_id, customer_name, total_amount, order_date, order_details) 
            VALUES (:user_id, :customer_name, :total_amount, NOW(), :order_details)
        ');
        
        $stmt->execute([
            ':user_id' => $user_id,
            ':customer_name' => $user['username'],
            ':total_amount' => $total_amount,
            ':order_details' => json_encode($cart_items)
        ]);
        
        // Clear the cart after successful order submission
        $stmt = $pdo->prepare('UPDATE user_carts SET cart_data = "[]" WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $user_id]);
        
        $_SESSION['success_message'] = 'Order submitted successfully! Waiting for admin approval.';
        header('Location: orderStatus.php');
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error submitting order. Please try again.';
        header('Location: cart.php');
        exit;
    }
}

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $cart_data = $_POST['cart_data'] ?? '[]';
    $cart_items = json_decode($cart_data, true);
    
    $stmt = $pdo->prepare('REPLACE INTO user_carts (user_id, cart_data) VALUES (:user_id, :cart_data)');
    $stmt->execute([
        ':user_id' => $user_id,
        ':cart_data' => $cart_data
    ]);
    
    echo json_encode(['success' => true]);
    exit;
}

// Handle item removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    $index = $_POST['item_index'];
    
    if (isset($cart_items[$index])) {
        array_splice($cart_items, $index, 1);
        
        $stmt = $pdo->prepare('REPLACE INTO user_carts (user_id, cart_data) VALUES (:user_id, :cart_data)');
        $stmt->execute([
            ':user_id' => $user_id,
            ':cart_data' => json_encode($cart_items)
        ]);
        
        $_SESSION['success_message'] = 'Item removed from cart.';
        header('Location: cart.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Arko Flavours</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/Food_System/user dashboard/design/userSidebar.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
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
            color: #333;
        }

        .main-content {
            margin-left: 220px;
            padding: 2rem;
            min-height: 100vh;
            background-color: #f5f7fa;
            width: calc(100% - 220px);
            transition: all 0.3s ease;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: #222e3c;
            margin-bottom: 0.5rem;
            font-size: 2rem;
            font-weight: 700;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .cart-container {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }

        .empty-cart img {
            width: 150px;
            height: 150px;
            margin-bottom: 1.5rem;
            opacity: 0.7;
            filter: grayscale(0.3);
            border-radius: 16px;
        }

        .empty-cart h3 {
            margin-bottom: 1rem;
            font-weight: 600;
            color: #222e3c;
            font-size: 1.5rem;
        }

        .empty-cart p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            color: #666;
        }

        .cart-items {
            margin-bottom: 2rem;
        }

        .cart-item {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-left: 4px solid #ff6b35;
            display: flex;
            gap: 1.5rem;
        }

        .cart-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .item-image {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .item-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #222e3c;
            margin-bottom: 0.5rem;
        }

        .item-variation {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .item-addons {
            color: #888;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }

        .item-notes {
            color: #666;
            font-size: 0.85rem;
            font-style: italic;
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 8px;
            margin-top: 0.5rem;
        }

        .item-controls {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f8f9fa;
            border-radius: 25px;
            padding: 0.3rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .quantity-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 50%;
            background: #ff6b35;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .quantity-btn:hover {
            background: #e65c2a;
            transform: scale(1.1);
        }

        .quantity-display {
            min-width: 40px;
            text-align: center;
            font-weight: 600;
            color: #222e3c;
        }

        .item-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: #ff6b35;
            min-width: 100px;
            text-align: right;
        }

        .item-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-edit {
            background: #3498db;
            color: white;
        }

        .btn-remove {
            background: #e74c3c;
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .lucide-icon {
            width: 16px;
            height: 16px;
            stroke-width: 2px;
        }

        .cart-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .summary-total {
            font-size: 1.5rem;
            font-weight: 700;
            border-top: 2px solid rgba(255,255,255,0.3);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .cart-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: flex-end;
        }

        .cart-btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-continue {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #ddd;
        }

        .btn-continue:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .btn-submit {
            background: #ff6b35;
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
        }

        .btn-submit:hover {
            background: #e65c2a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.4);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 4000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: #2ecc71;
        }

        .notification.error {
            background: #e74c3c;
        }

        .floating-menu-btn {
            position: fixed;
            bottom: 32px;
            right: 32px;
            background: #ff6b35;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(255, 107, 53, 0.3);
            z-index: 1000;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .floating-menu-btn:hover {
            background: #e65c2a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.4);
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
            
            .cart-item {
                flex-direction: column;
                gap: 1rem;
            }
            
            .item-image {
                width: 100%;
                height: 200px;
            }
            
            .item-header {
                flex-direction: column;
                gap: 1rem;
            }

            .item-controls {
                width: 100%;
                justify-content: space-between;
            }

            .cart-actions {
                flex-direction: column;
            }

            .cart-btn {
                width: 100%;
                justify-content: center;
            }

            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .floating-menu-btn {
                bottom: 20px;
                right: 20px;
                padding: 0.8rem 1.5rem;
                font-size: 1rem;
            }
        }

        /* Animation styles */
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

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>
<body>
    <?php include '../userSidebar.php'; ?>

    <div class="main-content">
        <div class="page-header fade-in-up">
            <h1>Shopping Cart</h1>
            <p>Review your order before submission</p>
        </div>

        <div class="cart-container fade-in-up">
            <?php if (empty($cart_items)): ?>
                <div class="empty-cart">
                    <img src="/Food_System/assets/icons/emptycart.jpg" alt="Empty Cart">
                    <h3>Your Cart is Empty</h3>
                    <p>Looks like you haven't added any items to your cart yet.</p>
                    <a href="menu.php" class="cart-btn btn-continue">
                        <i data-lucide="shopping-bag" class="lucide-icon"></i>
                        Continue Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="cart-items">
                    <?php 
                    $total_amount = 0;
                    foreach ($cart_items as $index => $item): 
                        $item_total = $item['price'] * $item['quantity'];
                        $total_amount += $item_total;
                    ?>
                        <div class="cart-item fade-in-up" style="animation-delay: <?= $index * 0.1 ?>s">
                            <img src="<?= htmlspecialchars($item['image_path']) ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                 class="item-image"
                                 onerror="this.src='/Food_System/assets/images/default-food.jpg'">
                            
                            <div class="item-content">
                                <div class="item-header">
                                    <div class="item-info">
                                        <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="item-variation">
                                            <strong>Variation:</strong> <?= htmlspecialchars($item['variation']) ?>
                                        </div>
                                        <?php if (!empty($item['addons'])): ?>
                                            <div class="item-addons">
                                                <strong>Add-ons:</strong> <?= htmlspecialchars(implode(', ', $item['addons'])) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['notes'])): ?>
                                            <div class="item-notes">
                                                <strong>Notes:</strong> <?= htmlspecialchars($item['notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="item-controls">
                                        <div class="quantity-controls">
                                            <button class="quantity-btn minus" data-index="<?= $index ?>">
                                                <i data-lucide="minus" class="lucide-icon"></i>
                                            </button>
                                            <span class="quantity-display"><?= $item['quantity'] ?></span>
                                            <button class="quantity-btn plus" data-index="<?= $index ?>">
                                                <i data-lucide="plus" class="lucide-icon"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="item-price">
                                            ₱<?= number_format($item_total, 2) ?>
                                        </div>
                                        
                                        <div class="item-actions">
                                            <button class="action-btn btn-edit" onclick="editItem(<?= $index ?>)">
                                                <i data-lucide="edit" class="lucide-icon"></i>
                                                Edit
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="item_index" value="<?= $index ?>">
                                                <button type="submit" name="remove_item" class="action-btn btn-remove" 
                                                        onclick="return confirm('Are you sure you want to remove this item?')">
                                                    <i data-lucide="trash-2" class="lucide-icon"></i>
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>₱<?= number_format($total_amount, 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Service Fee:</span>
                        <span>₱0.00</span>
                    </div>
                    <div class="summary-row summary-total">
                        <span>Total Amount:</span>
                        <span>₱<?= number_format($total_amount, 2) ?></span>
                    </div>
                </div>

                <div class="cart-actions">
                    <a href="menu.php" class="cart-btn btn-continue">
                        <i data-lucide="arrow-left" class="lucide-icon"></i>
                        Continue Shopping
                    </a>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="submit_order" class="cart-btn btn-submit">
                            <i data-lucide="clipboard-check" class="lucide-icon"></i>
                            Request this orders
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Floating Menu Button -->
    <a href="menu.php" class="floating-menu-btn">
        <i data-lucide="utensils" class="lucide-icon"></i>
        <span>Back to Menu</span>
    </a>

    <!-- Notification -->
    <div class="notification" id="notification"></div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Handle quantity updates
        document.querySelectorAll('.quantity-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = this.dataset.index;
                const isPlus = this.classList.contains('plus');
                const quantityDisplay = this.parentElement.querySelector('.quantity-display');
                let quantity = parseInt(quantityDisplay.textContent);
                
                if (isPlus) {
                    quantity++;
                } else {
                    if (quantity > 1) quantity--;
                }
                
                quantityDisplay.textContent = quantity;
                updateCartItemQuantity(index, quantity);
            });
        });

        function updateCartItemQuantity(index, quantity) {
            const cartItems = <?php echo json_encode($cart_items); ?>;
            
            if (cartItems[index]) {
                cartItems[index].quantity = quantity;
                
                // Send update to server
                $.ajax({
                    url: 'cart.php',
                    type: 'POST',
                    data: {
                        update_cart: 1,
                        cart_data: JSON.stringify(cartItems)
                    },
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            // Reload to update prices
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        }
                    }
                });
            }
        }

        function editItem(index) {
            const cartItems = <?php echo json_encode($cart_items); ?>;
            const item = cartItems[index];
            
            // Store editing info in session and redirect to menu
            $.ajax({
                url: 'menu.php',
                type: 'POST',
                data: {
                    set_editing_item: 1,
                    editing_index: index,
                    editing_item: JSON.stringify(item)
                },
                success: function() {
                    window.location.href = 'menu.php';
                }
            });
        }

        // Show notifications
        <?php if (isset($_SESSION['success_message'])): ?>
            showNotification('<?php echo $_SESSION['success_message']; ?>', 'success');
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            showNotification('<?php echo $_SESSION['error_message']; ?>', 'error');
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type} show`;
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        // Add hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const cartItems = document.querySelectorAll('.cart-item');
            cartItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>