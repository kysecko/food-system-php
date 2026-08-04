<?php
require_once '../../Log-in Form/includes/config_session.inc.php';
require_once '../../Log-in Form/includes/dbh.inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $user_id = $_SESSION['user_id'];
    $image = $_FILES['profile_image'];
    
    // Check for errors
    if ($image['error'] === UPLOAD_ERR_OK) {
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($image['tmp_name']);
        
        if (in_array($file_type, $allowed_types)) {
            // Validate file size (max 2MB)
            if ($image['size'] <= 2 * 1024 * 1024) {
                // Create uploads directory if it doesn't exist
                $upload_dir = '../../uploads/profiles/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $file_extension = pathinfo($image['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($image['tmp_name'], $file_path)) {
                    // Update database with file path
                    $query = "UPDATE users SET profile_image = ? WHERE id = ?";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$filename, $user_id]);
                    
                    $_SESSION['success'] = "Profile image updated successfully!";
                } else {
                    $_SESSION['error'] = "Error saving file.";
                }
            } else {
                $_SESSION['error'] = "File too large. Maximum size is 2MB.";
            }
        } else {
            $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        }
    } else {
        $_SESSION['error'] = "Error uploading file.";
    }
    
    header("Location: settings.php");
    exit();
}
?>