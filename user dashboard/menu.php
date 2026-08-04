<?php
require_once '../Log-in Form/includes/config_session.inc.php';
require_once '../Log-in Form/includes/dbh.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Log-in Form/login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = :id');
$stmt->bindParam(':id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo '<p>User not found.</p>';
    exit;
}

// --- ORDER STATUS CHECKS (Updated to allow multiple orders) ---

// Check if user has any pending orders (should allow multiple)
$has_pending_order = false;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) as pending_count FROM pending_orders WHERE user_id = :user_id');
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $pending_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_pending_order = $pending_result['pending_count'] > 0;
} catch (PDOException $e) {
    $has_pending_order = false;
}

// Check if user has any accepted orders waiting for payment (should allow multiple)
$has_accepted_order_no_payment = false;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) as accepted_count FROM accepted_orders WHERE user_id = :user_id AND (payment_screenshot IS NULL OR payment_screenshot = "")');
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $accepted_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_accepted_order_no_payment = $accepted_result['accepted_count'] > 0;
} catch (PDOException $e) {
    $has_accepted_order_no_payment = false;
}

// Check if user has any cooking orders (should allow multiple)
$has_cooking_order = false;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) as cooking_count FROM cooking_orders WHERE user_id = :user_id');
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $cooking_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_cooking_order = $cooking_result['cooking_count'] > 0;
} catch (PDOException $e) {
    $has_cooking_order = false;
}

// Check if user has any completed orders that are not delivered (should allow multiple)
$has_unfinished_completed_order = false;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) as completed_count FROM completed_orders WHERE user_id = :user_id AND delivered = 0');
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $completed_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_unfinished_completed_order = $completed_result['completed_count'] > 0;
} catch (PDOException $e) {
    $has_unfinished_completed_order = false;
}

// NEW: Only restrict ordering if user has TOO MANY pending orders (e.g., more than 3)
$has_too_many_pending = false;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) as total_pending FROM (
        SELECT id FROM pending_orders WHERE user_id = :user_id
        UNION ALL
        SELECT id FROM accepted_orders WHERE user_id = :user_id AND (payment_screenshot IS NULL OR payment_screenshot = "")
        UNION ALL
        SELECT id FROM cooking_orders WHERE user_id = :user_id
        UNION ALL
        SELECT id FROM completed_orders WHERE user_id = :user_id AND delivered = 0
    ) AS active_orders');
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $total_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_too_many_pending = $total_result['total_pending'] >= 3; // Allow up to 3 active orders
} catch (PDOException $e) {
    $has_too_many_pending = false;
}

// Determine if user can place new order
// Only block if user has too many active orders
$can_place_order = !$has_too_many_pending;

// Load user's existing cart items
$cart_items = [];
$stmt = $pdo->prepare('SELECT cart_data FROM user_carts WHERE user_id = :user_id');
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$cart_data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($cart_data && !empty($cart_data['cart_data'])) {
    $cart_items = json_decode($cart_data['cart_data'], true);
}

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    if (!$can_place_order && !isset($_POST['set_editing_item'])) {
        echo json_encode(['success' => false, 'message' => 'Cannot modify cart while you have an active order.']);
        exit;
    }
    
    $cart_data = $_POST['cart_data'] ?? '[]';
    
    $stmt = $pdo->prepare('REPLACE INTO user_carts (user_id, cart_data) VALUES (:user_id, :cart_data)');
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':cart_data', $cart_data);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

// Handle editing session setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_editing_item'])) {
    $_SESSION['editing_cart_item'] = json_decode($_POST['editing_item'], true);
    $_SESSION['editing_cart_index'] = $_POST['editing_index'];
    echo json_encode(['success' => true]);
    exit;
}

// Check if returning from cart editing
$editingItem = $_SESSION['editing_cart_item'] ?? null;
$editingIndex = $_SESSION['editing_cart_index'] ?? null;
unset($_SESSION['editing_cart_item'], $_SESSION['editing_cart_index']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - Arko Flavours</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/food-system/food-system-php/user dashboard/design/userMenu.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .order-restriction-banner {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #856404;
            text-align: center;
        }
        
        .order-restriction-banner.warning {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .order-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .restriction-info {
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        
        .menu-card.disabled {
            opacity: 0.7;
            position: relative;
        }
        
        .menu-card.disabled::after {
            content: "Order Restricted";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.9rem;
            z-index: 10;
        }
        
        .menu-card-variations {
            font-size: 0.85rem;
            color: #666;
            margin: 5px 0;
        }
    </style>
</head>

<body>
    <?php include '../userSidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>Our Delicious Menu</h1>
            <p>Choose from our selection of freshly prepared dishes</p>
        </div>

        <!-- Order Restriction Banner -->
        <?php if (!$can_place_order): ?>
        <div class="order-restriction-banner warning">
            <strong>⚠️ Order Restriction Active</strong>
            <p>You currently have an active order that needs to be completed before you can place a new one.</p>
            <div class="restriction-info">
                <?php
                $active_orders = [];
                if ($has_pending_order) $active_orders[] = "Pending Approval";
                if ($has_accepted_order_no_payment) $active_orders[] = "Accepted";
                if ($has_cooking_order) $active_orders[] = "Cooking";
                if ($has_unfinished_completed_order) $active_orders[] = "Completed - Awaiting Delivery";
                
                echo "Current order status: <strong>" . implode(" → ", $active_orders) . "</strong>";
                ?>
            </div>
            <p style="margin-top: 0.5rem;">
                <a href="/Food_System/user dashboard/orderStatus.php" style="color: #721c24; text-decoration: underline;">
                    Check your order status here
                </a>
            </p>
        </div>
        <?php endif; ?>

        <div class="menu-filters">
            <input type="text" class="search-box" placeholder="Search menu items..." id="search-input">
            <button class="filter-btn active" data-category="all">All Items</button>
            <button class="filter-btn" data-category="popular">Popular</button>
            <button class="filter-btn" data-category="new">New</button>
        </div>

        <div class="menu-grid" id="menu-list">
            <?php
            $stmt = $pdo->query('SELECT * FROM menu_items WHERE is_available = 1 ORDER BY id DESC');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // FIXED: Use absolute path for images

                $imagePath = !empty($row['image_path'])
                    ? '/food-system/food-system-php/' . htmlspecialchars($row['image_path'])
                    : '/food-system/food-system-php/assets/images/default-food.jpg';
                
                $variations = array_map('trim', explode(',', $row['sizes']));
                $prices = array_map('trim', explode(',', $row['prices']));
                
                $isDisabled = !$can_place_order ? 'disabled' : '';
                $cardClass = !$can_place_order ? 'menu-card disabled' : 'menu-card';
                
                echo '
                <div class="' . $cardClass . '" data-category="all">
                    <img src="' . $imagePath . '" alt="' . htmlspecialchars($row['name']) . '" class="menu-card-image">
                    <div class="menu-card-content">
                        <div class="menu-card-header">
                            <h3 class="menu-card-title">' . htmlspecialchars($row['name']) . '</h3>
                            <p class="menu-card-description">' . htmlspecialchars($row['description']) . '</p>
                        </div>
                        <div class="menu-card-details">
                            <div class="menu-card-price">₱' . htmlspecialchars($prices[0]) . '</div>
                            <div class="menu-card-variations">Variations: ' . htmlspecialchars($row['sizes']) . '</div>
                            <button class="order-btn" ' . $isDisabled . '
                                data-id="' . $row['id'] . '"
                                data-name="' . htmlspecialchars($row['name']) . '"
                                data-variations="' . htmlspecialchars($row['sizes']) . '"
                                data-prices="' . htmlspecialchars($row['prices']) . '"
                                data-addons="' . htmlspecialchars($row['addons']) . '">
                                ' . ($can_place_order ? 'Add to Order' : 'Order Restricted') . '
                            </button>
                        </div>
                    </div>
                </div>';
            }
            ?>
        </div>
    </div>

    <!-- Floating Cart Button -->
    <a href="cart.php" class="floating-cart-btn" id="cart-button">
        <span>🛒</span>
        <span>View Cart</span>
        <span class="cart-badge" id="cart-count"><?php echo count($cart_items); ?></span>
    </a>

    <!-- Order Modal -->
    <div class="modal-overlay" id="order-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="order-modal-title">Customize Your Order</h2>
                <button class="close-btn" id="close-order-modal">&times;</button>
            </div>
            <form id="order-form">
                <div class="form-group">
                    <label class="form-label">Select Variation</label>
                    <select class="form-select" id="order-variation" required>
                        <option value="">Choose a variation</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Quantity</label>
                    <input type="number" class="form-input" id="order-qty" min="1" value="1" required>
                </div>

                <div class="form-group" id="addons-section">
                    <label class="form-label">Add-ons</label>
                    <div class="addons-grid" id="order-addons"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Special Instructions</label>
                    <textarea class="form-textarea" id="order-notes" rows="3" placeholder="Any special requests or dietary restrictions..."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="cancel-order-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submit-order-btn">Add to Cart</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notification -->
    <div class="notification" id="notification"></div>

    <script>
        // Global variables
        let currentOrders = <?php echo json_encode($cart_items); ?>;
        let editingOrderIndex = <?php echo $editingIndex !== null ? $editingIndex : 'null'; ?>;
        let canPlaceOrder = <?php echo $can_place_order ? 'true' : 'false'; ?>;

        // DOM Elements
        const orderModal = document.getElementById('order-modal');
        const orderForm = document.getElementById('order-form');
        const cartCount = document.getElementById('cart-count');
        const searchInput = document.getElementById('search-input');
        const filterButtons = document.querySelectorAll('.filter-btn');
        const orderButtons = document.querySelectorAll('.order-btn');

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            initializeEventListeners();
            
            // Check if we're returning from cart editing
            <?php if ($editingItem !== null): ?>
            const editingItem = <?php echo json_encode($editingItem); ?>;
            openOrderModalForEditing(editingItem);
            <?php endif; ?>
        });

        function initializeEventListeners() {
            // Order button click handlers
            orderButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    if (!canPlaceOrder) {
                        showNotification('You cannot add items to cart while you have an active order. Please wait for your current order to be completed.', 'error');
                        return;
                    }
                    
                    openOrderModal(
                        this.dataset.id,
                        this.dataset.name,
                        this.dataset.variations,
                        this.dataset.prices,
                        this.dataset.addons
                    );
                });
            });

            // Filter buttons
            filterButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    filterButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    filterMenuItems(this.dataset.category);
                });
            });

            // Search functionality
            searchInput.addEventListener('input', function(e) {
                filterMenuItemsBySearch(e.target.value);
            });

            // Modal close buttons
            document.getElementById('close-order-modal').addEventListener('click', closeOrderModal);
            document.getElementById('cancel-order-btn').addEventListener('click', closeOrderModal);

            // Order form submission
            orderForm.addEventListener('submit', handleOrderFormSubmit);
        }

        function openOrderModal(menuId, name, variations, prices, addons) {
            console.log('Opening order modal for:', name);
            
            // Reset form
            orderForm.reset();
            document.getElementById('order-qty').value = 1;
            editingOrderIndex = null;
            
            // Set modal title
            document.getElementById('order-modal-title').textContent = name;

            // Populate variations
            const variationSelect = document.getElementById('order-variation');
            variationSelect.innerHTML = '<option value="">Choose a variation</option>';
            const variationArr = variations.split(',');
            const priceArr = prices.split(',');
            
            variationArr.forEach((variation, i) => {
                if (variation.trim()) {
                    const option = document.createElement('option');
                    option.value = variation.trim();
                    option.textContent = `${variation.trim()} - ₱${priceArr[i] ? priceArr[i].trim() : '0'}`;
                    option.dataset.price = priceArr[i] ? priceArr[i].trim() : '0';
                    variationSelect.appendChild(option);
                }
            });

            // Populate add-ons
            const addonsSection = document.getElementById('addons-section');
            const addonsDiv = document.getElementById('order-addons');
            addonsDiv.innerHTML = '';
            
            const addonsArr = addons ? addons.split(',') : [];
            if (addonsArr.length && addonsArr[0].trim()) {
                addonsSection.style.display = 'block';
                addonsArr.forEach(addon => {
                    if (addon.trim()) {
                        const addonOption = document.createElement('div');
                        addonOption.className = 'addon-option';
                        addonOption.innerHTML = `
                            <input type="checkbox" name="addons[]" value="${addon.trim()}" id="addon-${addon.trim().replace(/\s+/g, '-')}">
                            <label for="addon-${addon.trim().replace(/\s+/g, '-')}">${addon.trim()}</label>
                        `;
                        addonsDiv.appendChild(addonOption);
                        
                        // Add click handler for addon options
                        addonOption.addEventListener('click', function() {
                            const checkbox = this.querySelector('input[type="checkbox"]');
                            checkbox.checked = !checkbox.checked;
                            this.classList.toggle('selected', checkbox.checked);
                        });
                    }
                });
            } else {
                addonsSection.style.display = 'none';
            }

            // Store current menu item data
            orderForm.dataset.menuId = menuId;
            orderForm.dataset.name = name;
            orderForm.dataset.prices = prices;
            
            // Show modal
            orderModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function openOrderModalForEditing(item) {
            // Find the menu item to get full details
            const menuBtn = document.querySelector(`.order-btn[data-name="${item.name}"]`);
            if (menuBtn) {
                openOrderModal(
                    item.menuId,
                    item.name,
                    menuBtn.dataset.variations,
                    menuBtn.dataset.prices,
                    menuBtn.dataset.addons
                );
                
                // Pre-fill the form with existing values
                setTimeout(() => {
                    document.getElementById('order-variation').value = item.variation;
                    document.getElementById('order-qty').value = item.quantity;
                    document.getElementById('order-notes').value = item.notes || '';
                    
                    // Pre-select addons
                    if (item.addons && item.addons.length > 0) {
                        item.addons.forEach(addon => {
                            const checkbox = document.querySelector(`input[value="${addon}"]`);
                            if (checkbox) {
                                checkbox.checked = true;
                                checkbox.closest('.addon-option').classList.add('selected');
                            }
                        });
                    }
                }, 100);
            }
        }

        function closeOrderModal() {
            orderModal.classList.remove('active');
            document.body.style.overflow = 'auto';
            editingOrderIndex = null;
        }

        function handleOrderFormSubmit(e) {
            e.preventDefault();
            
            if (!canPlaceOrder) {
                showNotification('You cannot add items to cart while you have an active order. Please wait for your current order to be completed.', 'error');
                closeOrderModal();
                return;
            }
            
            const variationSelect = document.getElementById('order-variation');
            const selectedOption = variationSelect.options[variationSelect.selectedIndex];
            
            if (!selectedOption.value) {
                showNotification('Please select a variation', 'error');
                return;
            }
            
            const order = {
                menuId: orderForm.dataset.menuId,
                name: orderForm.dataset.name,
                variation: selectedOption.value,
                price: parseFloat(selectedOption.dataset.price),
                quantity: parseInt(document.getElementById('order-qty').value),
                notes: document.getElementById('order-notes').value,
                addons: Array.from(document.querySelectorAll('#order-addons input:checked')).map(a => a.value)
            };
            
            // Save to cart
            saveOrderToCart(order);
        }

        function saveOrderToCart(order) {
            if (editingOrderIndex !== null) {
                currentOrders[editingOrderIndex] = order;
            } else {
                currentOrders.push(order);
            }
            
            // Save to server
            $.ajax({
                url: 'menu.php',
                type: 'POST',
                data: {
                    update_cart: 1,
                    cart_data: JSON.stringify(currentOrders)
                },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        closeOrderModal();
                        updateCartBadge();
                        showNotification(
                            editingOrderIndex !== null ? 'Item updated successfully!' : 'Item added to cart!', 
                            'success'
                        );
                        
                        // Redirect to cart if editing
                        if (editingOrderIndex !== null) {
                            setTimeout(() => {
                                window.location.href = 'cart.php';
                            }, 1000);
                        }
                    } else {
                        showNotification(result.message || 'Error saving to cart', 'error');
                    }
                },
                error: function() {
                    showNotification('Error saving to cart', 'error');
                }
            });
        }

        function updateCartBadge() {
            cartCount.textContent = currentOrders.length;
        }

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type} show`;
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        function filterMenuItems(category) {
            // This is a simplified filter - you can expand this based on your data
            const menuCards = document.querySelectorAll('.menu-card');
            
            menuCards.forEach(card => {
                if (category === 'all') {
                    card.style.display = 'block';
                } else {
                    // Add your filtering logic here based on data attributes
                    card.style.display = 'block';
                }
            });
        }

        function filterMenuItemsBySearch(searchTerm) {
            const term = searchTerm.toLowerCase();
            const menuCards = document.querySelectorAll('.menu-card');
            
            menuCards.forEach(card => {
                const title = card.querySelector('.menu-card-title').textContent.toLowerCase();
                const description = card.querySelector('.menu-card-description').textContent.toLowerCase();
                
                if (title.includes(term) || description.includes(term)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Make functions available globally
        window.editCartItem = function(index) {
            // Store editing info in session and redirect to menu
            const order = currentOrders[index];
            $.ajax({
                url: 'menu.php',
                type: 'POST',
                data: {
                    set_editing_item: 1,
                    editing_index: index,
                    editing_item: JSON.stringify(order)
                },
                success: function() {
                    window.location.href = 'menu.php';
                }
            });
        };
    </script>
</body>
</html>