<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Phase 1: Place Order
    if (isset($_POST['phase1_submit'])) {
        $_SESSION['order_data'] = [
            'product' => $_POST['product'],
            'total_amount' => $_POST['total_amount']
        ];
        $_SESSION['current_phase'] = 2;
    }
    
    // Handle Phase 2: Confirm Order
    elseif (isset($_POST['edit_order'])) {
        $_SESSION['current_phase'] = 1;
    }
    elseif (isset($_POST['proceed_to_payment'])) {
        $_SESSION['current_phase'] = 3;
    }
    
    // Handle Phase 3: Payment Method
    elseif (isset($_POST['submit_receipt'])) {
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = 'receipt_' . time() . '_' . basename($_FILES['receipt']['name']);
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $target_file)) {
                $_SESSION['receipt_filename'] = $filename;
                $_SESSION['current_phase'] = 4;
            } else {
                echo "<script>alert('Failed to upload receipt.'); window.history.back();</script>";
                exit;
            }
        } else {
            echo "<script>alert('Please select a receipt file.'); window.history.back();</script>";
            exit;
        }
    }
    
    // Handle Phase 4: Send Receipt
    elseif (isset($_POST['final_submit'])) {
        $_SESSION['current_phase'] = 5;
    }
    
    // Handle Phase 5: Delivery
    elseif (isset($_POST['complete_order'])) {
        // Save order to database (you'll need to implement this)
        saveOrderToDatabase();
        
        // Reset session for new order
        session_destroy();
        session_start();
        $_SESSION['current_phase'] = 1;
    }
    elseif (isset($_POST['track_order'])) {
        // Stay on phase 5 for tracking
        $_SESSION['current_phase'] = 5;
    }
}

header('Location: index.php');
exit;

function saveOrderToDatabase() {
    // Implement your database saving logic here
    // This is a placeholder - you'll need to connect to your actual database
    
    /*
    global $conn;
    
    $product = $_SESSION['order_data']['product'];
    $total_amount = $_SESSION['order_data']['total_amount'];
    $receipt = $_SESSION['receipt_filename'];
    
    $sql = "INSERT INTO orders (product, total_amount, receipt, status, created_at) 
            VALUES (?, ?, ?, 'pending', NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sds", $product, $total_amount, $receipt);
    
    return $stmt->execute();
    */
    
    // For now, just log the order
    error_log("Order placed: " . $_SESSION['order_data']['product'] . " - " . $_SESSION['order_data']['total_amount']);
    return true;
}
?>