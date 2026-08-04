<?php
require_once '../../Log-in Form/includes/config_session.inc.php';
require_once '../../Log-in Form/includes/dbh.inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    // Get form data
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $bio = $_POST['bio'] ?? '';
    
    // Update user profile
    $query = "UPDATE users SET username = ?, email = ? WHERE id = ?";
    $stmt = $pdo->prepare($query);
    
    $username = $first_name . ' ' . $last_name;
    
    if ($stmt->execute([$username, $email, $user_id])) {
        $_SESSION['success'] = "Profile updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating profile.";
    }
    
    header("Location: settings.php");
    exit();
}
?>