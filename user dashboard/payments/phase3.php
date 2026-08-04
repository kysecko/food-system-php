<div class="card">
    <div class="card-header">
        <h5 class="card-title">Payment Method</h5>
    </div>
    <div class="card-body">
        <?php
        $total_amount = isset($_SESSION['order_data']['total_amount']) ? $_SESSION['order_data']['total_amount'] : 0;
        ?>
        
        <div class="mb-4 p-3 bg-light rounded">
            <h6>Order Total: ₱<?php echo number_format($total_amount, 2); ?></h6>
        </div>
        
        <div class="mb-4">
            <h6>GCash Payment</h6>
            <div class="text-center mb-3">
                <!-- Placeholder for GCash QR Code -->
                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZmZmIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzAwMCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkdDQVNIIFFSIENvZGU8L3RleHQ+PHRleHQgeD0iNTAlIiB5PSI2MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxMiIgZmlsbD0iIzY2NiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPlBheTogwqM8P3BocCBlY2hvIG51bWJlcl9mb3JtYXQoJHRvdGFsX2Ftb3VudCwgMik7ID8+PC90ZXh0Pjwvc3ZnPg==" 
                     alt="GCash QR Code" class="img-fluid border p-2" style="max-width: 200px;">
            </div>
            <p class="text-center"><strong>Send to GCash Number: 09XX XXX XXXX</strong></p>
            <p class="text-center text-muted">Amount: ₱<?php echo number_format($total_amount, 2); ?></p>
            <p class="text-center">After payment, upload your receipt as proof of payment.</p>
        </div>
        
        <form method="POST" action="process_phase.php" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="receipt" class="form-label">Upload Payment Receipt</label>
                <input class="form-control" type="file" id="receipt" name="receipt" accept="image/*,.pdf" required>
                <div class="form-text">Upload a screenshot or photo of your GCash payment confirmation</div>
                <img id="receiptPreview" class="upload-preview img-thumbnail" alt="Receipt preview">
            </div>
            <button type="submit" class="btn btn-primary w-100" name="submit_receipt">Submit Receipt & Continue</button>
        </form>
    </div>
</div>