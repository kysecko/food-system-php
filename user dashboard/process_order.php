<?php
require_once '../Log-in Form/includes/config_session.inc.php';
require_once '../Log-in Form/includes/dbh.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Log-in Form/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $user_id = $_SESSION['user_id'];
    $order_details = json_decode($_POST['order_details'], true);
    $total_amount = $_POST['total_amount'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert order
        $stmt = $pdo->prepare('INSERT INTO orders (user_id, total_amount, status) VALUES (:user_id, :total_amount, "pending")');
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':total_amount', $total_amount);
        $stmt->execute();
        
        $order_id = $pdo->lastInsertId();
        
        // Insert order items
        $stmt = $pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, item_name, size, quantity, price, addons, special_instructions) VALUES (:order_id, :menu_item_id, :item_name, :size, :quantity, :price, :addons, :special_instructions)');
        
        foreach ($order_details as $item) {
            $addons_json = !empty($item['addons']) ? json_encode($item['addons']) : null;
            
            $stmt->bindParam(':order_id', $order_id);
            $stmt->bindParam(':menu_item_id', $item['menuId']);
            $stmt->bindParam(':item_name', $item['name']);
            $stmt->bindParam(':size', $item['size']);
            $stmt->bindParam(':quantity', $item['quantity']);
            $stmt->bindParam(':price', $item['price']);
            $stmt->bindParam(':addons', $addons_json);
            $stmt->bindParam(':special_instructions', $item['notes']);
            $stmt->execute();
        }
        
        // Clear user's cart
        $stmt = $pdo->prepare('DELETE FROM user_carts WHERE user_id = :user_id');
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $pdo->commit();
        
        header('Location: order_success.php?order_id=' . $order_id);
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Order processing error: " . $e->getMessage());
        header('Location: cart.php?error=order_failed');
        exit;
    }
} else {
    header('Location: cart.php');
    exit;
}
?>