<?php
// Retrieve order data from session
$cart_items = isset($_SESSION['order_data']['cart_items']) ? $_SESSION['order_data']['cart_items'] : [];
$total_amount = isset($_SESSION['order_data']['total_amount']) ? $_SESSION['order_data']['total_amount'] : 0;
$product = isset($_SESSION['order_data']['product']) ? $_SESSION['order_data']['product'] : 'Custom Order';
?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Confirm Your Order</h5>
    </div>
    <div class="card-body">
        <div class="order-summary mb-4 p-3 bg-light rounded">
            <h6>Order Summary</h6>
            <?php if (!empty($cart_items)): ?>
                <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item-summary">
                        <strong><?php echo htmlspecialchars($item['name']); ?></strong><br>
                        Size: <?php echo htmlspecialchars($item['size']); ?> | 
                        Quantity: <?php echo $item['quantity']; ?> | 
                        Price: ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                        <?php if (!empty($item['addons'])): ?>
                            <br>Addons: <?php echo htmlspecialchars(implode(', ', $item['addons'])); ?>
                        <?php endif; ?>
                        <?php if (!empty($item['notes'])): ?>
                            <br>Notes: <?php echo htmlspecialchars($item['notes']); ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <p><strong>Total Amount:</strong> ₱<?php echo number_format($total_amount, 2); ?></p>
        </div>
        
        <div class="delivery-info mb-4 p-3 bg-light rounded">
            <h6>Delivery Information</h6>
            <p>Puntahan mo SELF SERBIS DITO BAI</p>
            
            
        
        <form method="POST" action="process_phase.php">
            <div class="d-flex justify-content-between">
                <button type="submit" class="btn btn-secondary" name="edit_order">Edit Order</button>
                <button type="submit" class="btn btn-primary" name="proceed_to_payment">Proceed to Payment</button>
            </div>
        </form>
    </div>
</div>