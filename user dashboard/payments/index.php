<?php
session_start();

// Initialize session variables if not set
if (!isset($_SESSION['current_phase'])) {
    $_SESSION['current_phase'] = 1;
}
if (!isset($_SESSION['order_data'])) {
    $_SESSION['order_data'] = [];
}

// If coming from cart, start at phase 2
if (isset($_SESSION['order_data']['cart_items']) && !empty($_SESSION['order_data']['cart_items'])) {
    $_SESSION['current_phase'] = 2;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Process System - Arko Flavours</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .step-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        .step-container::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #e0e0e0;
            z-index: 1;
        }
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: white;
            font-weight: bold;
        }
        .step.active .step-circle {
            background-color: #ff6b35;
        }
        .step.completed .step-circle {
            background-color: #28a745;
        }
        .step.completed .step-circle::after {
            content: '✓';
        }
        .step-label {
            font-size: 14px;
            color: #666;
        }
        .step.active .step-label {
            color: #ff6b35;
            font-weight: bold;
        }
        .upload-preview {
            max-width: 200px;
            max-height: 200px;
            display: none;
            margin: 10px auto;
        }
        .cart-item-summary {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Order Checkout - Arko Flavours</h1>
        
        <!-- Progress Steps -->
        <div class="step-container">
            <?php
            $steps = [
                1 => 'Place Order',
                2 => 'Confirm Order', 
                3 => 'Payment Method',
                4 => 'Send Receipt',
                5 => 'Delivery'
            ];
            
            foreach ($steps as $num => $label) {
                $class = '';
                if ($num < $_SESSION['current_phase']) {
                    $class = 'completed';
                } elseif ($num == $_SESSION['current_phase']) {
                    $class = 'active';
                }
                echo "
                <div class='step $class' id='step$num'>
                    <div class='step-circle'>$num</div>
                    <div class='step-label'>$label</div>
                </div>";
            }
            ?>
        </div>

        <!-- Current Phase Content -->
        <div class="text-center mt-4">
            <?php
            if ($_SESSION['current_phase'] <= 5) {
                $phase_file = "phase" . $_SESSION['current_phase'] . ".php";
                if (file_exists($phase_file)) {
                    include $phase_file;
                } else {
                    echo '<div class="alert alert-danger">Phase file not found: ' . $phase_file . '</div>';
                }
            } else {
                echo '<div class="alert alert-success"><h4>Order Completed!</h4><p>Your order has been successfully processed.</p><a href="../cart/menu.php" class="btn btn-primary">Back to Menu</a></div>';
            }
            ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload preview for receipt
        $(document).ready(function() {
            $('#receipt').change(function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#receiptPreview').attr('src', e.target.result).show();
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
</body>
</html>