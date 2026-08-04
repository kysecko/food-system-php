<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Phase 1: Place Order
    if (isset($_POST['phase1_submit'])) {
        $_SESSION['order_data'] = [
            'product' => $_POST['product'],
            'total_amount' => $_POST['total_amount']
        ];
        $_SESSION['current_phase'] = 2;
    }
    
    // Phase 2: Confirm Order
    elseif (isset($_POST['proceed_to_payment'])) {
        $_SESSION['current_phase'] = 3;
    }
    elseif (isset($_POST['edit_order'])) {
        // Redirect back to cart to edit order
        header('Location: ../cart/cart.php');
        exit;
    }
    
    // Phase 3: Payment Method
    elseif (isset($_POST['submit_receipt'])) {
        // Handle file upload
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = uniqid() . '_' . basename($_FILES['receipt']['name']);
            $uploadFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $uploadFile)) {
                $_SESSION['receipt_filename'] = $fileName;
                $_SESSION['current_phase'] = 4;
            } else {
                // Handle upload error
                $_SESSION['error'] = 'Failed to upload receipt.';
            }
        }
    }
    
    // Phase 4: Send Receipt
    elseif (isset($_POST['final_submit'])) {
        $_SESSION['current_phase'] = 5;
    }
    
    // Phase 5: Delivery
    elseif (isset($_POST['complete_order'])) {
        // Order completed - reset for new order
        $_SESSION['current_phase'] = 1;
        $_SESSION['order_data'] = [];
        unset($_SESSION['receipt_filename']);
    }
    
    // Redirect back to index
    header('Location: index.php');
    exit;
}
?>