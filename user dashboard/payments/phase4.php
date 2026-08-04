<div class="card">
    <div class="card-header">
        <h5 class="card-title">Payment Received</h5>
    </div>
    <div class="card-body text-center">
        <div class="alert alert-success">
            <h4>Thank you for your payment!</h4>
            <p>We have received your payment receipt and will verify it shortly.</p>
        </div>
        
        <?php if (isset($_SESSION['receipt_filename'])): ?>
        <div class="mb-3">
            <p><strong>Receipt Uploaded:</strong> <?php echo htmlspecialchars($_SESSION['receipt_filename']); ?></p>
        </div>
        <?php endif; ?>
        
        <?php
        $total_amount = isset($_SESSION['order_data']['total_amount']) ? $_SESSION['order_data']['total_amount'] : 0;
        ?>
        <div class="mb-3 p-3 bg-light rounded">
            <h6>Order Summary</h6>
            <p><strong>Total Paid:</strong> ₱<?php echo number_format($total_amount, 2); ?></p>
            <p><strong>Status:</strong> Payment Verification Pending</p>
        </div>
        
        <form method="POST" action="process_phase.php">
            <button type="submit" class="btn btn-primary" name="final_submit">Continue to Order Tracking</button>
        </form>
    </div>
</div>