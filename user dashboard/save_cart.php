<?php
require_once '../Log-in Form/includes/config_session.inc.php';
require_once '../Log-in Form/includes/dbh.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $cart_data = $_POST['cart_data'] ?? '[]';
    
    try {
        // Check if user already has a cart
        $stmt = $pdo->prepare('SELECT id FROM user_carts WHERE user_id = :user_id');
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if ($stmt->fetch()) {
            // Update existing cart
            $stmt = $pdo->prepare('UPDATE user_carts SET cart_data = :cart_data, updated_at = NOW() WHERE user_id = :user_id');
        } else {
            // Insert new cart
            $stmt = $pdo->prepare('INSERT INTO user_carts (user_id, cart_data) VALUES (:user_id, :cart_data)');
        }
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':cart_data', $cart_data);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Cart save error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to save cart']);
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>