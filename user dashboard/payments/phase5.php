<div class="card">
    <div class="card-header">
        <h5 class="card-title">Order Tracking</h5>
    </div>
    <div class="card-body">
        <div class="progress mb-4" style="height: 25px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                 role="progressbar" 
                 style="width: 25%;" 
                 aria-valuenow="25" 
                 aria-valuemin="0" 
                 aria-valuemax="100">
                Order Confirmed
            </div>
        </div>
        
        <div class="delivery-timeline mb-4">
            <div class="d-flex justify-content-between text-center mb-3">
                <div>
                    <div class="badge bg-success rounded-circle p-2">✓</div>
                    <div>Order Placed</div>
                </div>
                <div>
                    <div class="badge bg-success rounded-circle p-2">✓</div>
                    <div>Payment Verified</div>
                </div>
                <div>
                    <div class="badge bg-warning rounded-circle p-2">●</div>
                    <div>Preparing</div>
                </div>
                <div>
                    <div class="badge bg-secondary rounded-circle p-2">○</div>
                    <div>Out for Delivery</div>
                </div>
                <div>
                    <div class="badge bg-secondary rounded-circle p-2">○</div>
                    <div>Delivered</div>
                </div>
            </div>
        </div>
        
        <div class="mb-3 p-3 bg-light rounded">
            <p><strong>Estimated Delivery:</strong> 20-30 minutes</p>
            <p><strong>Order Status:</strong> Being prepared in the kitchen</p>
            <p><strong>Delivery Address:</strong> [User's address would be here]</p>
        </div>
        
        <form method="POST" action="process_phase.php">
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-outline-primary" name="track_order">Refresh Status</button>
                <button type="submit" class="btn btn-primary" name="complete_order">Order Received</button>
            </div>
        </form>
    </div>
</div>