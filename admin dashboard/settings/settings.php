<?php
// Start session and include database connection at the VERY TOP
require_once '../../Log-in Form/includes/config_session.inc.php';
require_once '../../Log-in Form/includes/dbh.inc.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../Log-in Form/login.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    // Handle Profile Image Upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
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
    }
    
    // Handle Profile Information Update
    if (isset($_POST['first_name']) && isset($_POST['last_name']) && isset($_POST['email']) && isset($_POST['username'])) {
        // Get form data
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        $bio = $_POST['bio'] ?? '';
        
        // Update user profile
        $query = "UPDATE users SET username = ?, email = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        
        if ($stmt->execute([$username, $email, $user_id])) {
            // If image was also uploaded, show combined success message
            if (!isset($_SESSION['success']) || strpos($_SESSION['success'], 'image') === false) {
                $_SESSION['success'] = "Profile updated successfully!";
            } else {
                $_SESSION['success'] = "Profile and image updated successfully!";
            }
        } else {
            $_SESSION['error'] = "Error updating profile.";
        }
    }
    
    header("Location: settings.php");
    exit();
}

// Get user data from database
$user_id = $_SESSION['user_id'];
$query = "SELECT username, email, profile_image FROM users WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$username = $user['username'] ?? 'Admin User';
$email = $user['email'] ?? 'admin@arko-flavors.com';
$profile_image = $user['profile_image'] ?? null;

// Split username into first and last name for the form
$name_parts = explode(' ', $username, 2);
$first_name = $name_parts[0] ?? 'Admin';
$last_name = $name_parts[1] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Arko Flavors</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e74c3c;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .profile-avatar {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #e74c3c;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            margin-right: 20px;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e74c3c;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }

        .change-avatar-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .profile-info h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .profile-info p {
            color: #7f8c8d;
            margin-bottom: 10px;
        }

        .stats {
            display: flex;
            margin-top: 15px;
        }

        .stat {
            margin-right: 30px;
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        /* Settings Tabs */
        .settings-tabs {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }

        .tab {
            padding: 12px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            border-bottom: 3px solid #e74c3c;
            color: #e74c3c;
            font-weight: 500;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: border 0.3s;
        }

        .form-control:focus {
            border-color: #e74c3c;
            outline: none;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #c0392b;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }

        .btn-outline:hover {
            background: #e74c3c;
            color: white;
        }

        /* Top Image Upload Styles */
        .image-upload-container {
            margin-bottom: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px dashed #e0e0e0;
        }

        .image-upload-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .image-upload-header h3 {
            color: #2c3e50;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .image-upload-wrapper {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .image-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            background: white;
            border: 3px solid #e0e0e0;
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-preview .placeholder {
            color: #999;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .file-input {
            display: none;
        }

        .upload-controls {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
        }

        .upload-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
        }

        .upload-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .upload-info {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        /* Notifications */
        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .notification-info h4 {
            font-size: 1rem;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .notification-info p {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #e74c3c;
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        /* Security */
        .security-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .security-info h4 {
            font-size: 1rem;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .security-info p {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }

            .stats {
                justify-content: center;
            }

            .image-upload-wrapper {
                flex-direction: column;
                text-align: center;
            }

            .upload-controls {
                align-items: center;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .image-preview {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include("../../adminSidebar.php"); ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Profile Settings</h1>
                <div class="user-info">
                    <?php if ($profile_image): ?>
                        <img src="../../uploads/profiles/<?php echo htmlspecialchars($profile_image); ?>" 
                             alt="User Avatar"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                    <?php endif; ?>
                    <div class="user-avatar" style="<?php echo $profile_image ? 'display: none' : 'display: flex'; ?>">
                        <?php echo strtoupper(substr($username, 0, 2)); ?>
                    </div>
                    <span><?php echo htmlspecialchars($username); ?></span>
                </div>
            </div>

            <!-- Profile Card -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php if ($profile_image): ?>
                            <img src="../../uploads/profiles/<?php echo htmlspecialchars($profile_image); ?>" 
                                 alt="Profile Image"
                                 onerror="this.style.display='none'; document.getElementById('avatar-placeholder').style.display='flex'">
                        <?php endif; ?>
                        <div id="avatar-placeholder" class="avatar-placeholder" style="<?php echo $profile_image ? 'display: none' : 'display: flex'; ?>">
                            <?php echo strtoupper(substr($username, 0, 2)); ?>
                        </div>
                        <button class="change-avatar-btn" onclick="document.getElementById('profile_image').click()">
                            <i data-lucide="camera"></i>
                        </button>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($username); ?></h2>
                        <p><?php echo htmlspecialchars($email); ?></p>
                        <p>Pagsanjan, Laguna</p>
                        <div class="stats">
                            <div class="stat">
                                <div class="stat-value">47</div>
                                <div class="stat-label">Orders</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value">₱12,450</div>
                                <div class="stat-label">Revenue</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value">92%</div>
                                <div class="stat-label">Satisfaction</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <div class="tab active" data-tab="profile">Profile</div>
                    <div class="tab" data-tab="notifications">Notifications</div>
                    <div class="tab" data-tab="security">Security</div>
                </div>

                <!-- Profile Tab -->
                <div class="tab-content active" id="profile">
                    <?php
                    // Display success/error messages
                    if (isset($_SESSION['success'])) {
                        echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
                        unset($_SESSION['success']);
                    }
                    if (isset($_SESSION['error'])) {
                        echo '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
                        unset($_SESSION['error']);
                    }
                    ?>

                    <!-- Combined Form for both image and profile data -->
                    <form action="settings.php" method="POST" enctype="multipart/form-data">
                        
                        <!-- Top Image Upload Section -->
                        <div class="image-upload-container">
                            <div class="image-upload-header">
                                <h3>Profile Image</h3>
                            </div>
                            <div class="image-upload-wrapper">
                                <div class="image-preview" id="imagePreview">
                                    <?php if ($profile_image): ?>
                                        <img src="../../uploads/profiles/<?php echo htmlspecialchars($profile_image); ?>" 
                                             alt="Current Profile Image"
                                             onerror="this.style.display='none'; document.getElementById('preview-placeholder').style.display='flex'">
                                    <?php endif; ?>
                                    <div id="preview-placeholder" class="placeholder" style="<?php echo $profile_image ? 'display: none' : 'display: flex'; ?>">
                                        <i data-lucide="user" style="width: 40px; height: 40px; color: #ccc;"></i>
                                        <div style="font-size: 0.8rem; color: #999;">No image</div>
                                    </div>
                                </div>
                                <div class="upload-controls">
                                    <input type="file" id="profile_image" name="profile_image" class="file-input" accept="image/*">
                                    <button type="button" class="upload-btn" onclick="document.getElementById('profile_image').click()">
                                        <i data-lucide="upload" style="width: 16px; height: 16px;"></i>
                                        Choose Image
                                    </button>
                                    <div class="upload-info">
                                        <small style="color: #666;">
                                            <strong>Supported formats:</strong> JPG, PNG, GIF<br>
                                            <strong>Max file size:</strong> 2MB<br>
                                            <strong>Recommended:</strong> Square image, 500x500px
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Information Section -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName">First Name</label>
                                <input type="text" id="firstName" name="first_name" class="form-control" value="<?php echo htmlspecialchars($first_name); ?>">
                            </div>
                            <div class="form-group">
                                <label for="lastName">Last Name</label>
                                <input type="text" id="lastName" name="last_name" class="form-control" value="<?php echo htmlspecialchars($last_name); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder="+63 912 345 6789">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" class="form-control" value="Pagsanjan, Laguna">
                        </div>
                        
                        <div class="form-group">
                            <label for="bio">Bio</label>
                            <textarea id="bio" name="bio" class="form-control" rows="4">Restaurant manager at Arko Flavors, specializing in traditional Filipino cuisine from Pagsanjan, Laguna.</textarea>
                        </div>
                        
                        <button type="submit" class="btn" style="width: 100%; padding: 15px; font-size: 1.1rem;">
                            <i data-lucide="save" style="width: 18px; height: 18px; margin-right: 8px;"></i>
                            Save All Changes
                        </button>
                    </form>
                </div>

                <!-- Notifications Tab -->
                <div class="tab-content" id="notifications">
                    <div class="notification-item">
                        <div class="notification-info">
                            <h4>Order Notifications</h4>
                            <p>Get notified when new orders are placed</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="notification-item">
                        <div class="notification-info">
                            <h4>Payment Notifications</h4>
                            <p>Receive alerts for successful payments</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="notification-item">
                        <div class="notification-info">
                            <h4>Inventory Alerts</h4>
                            <p>Get notified when items are running low</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="notification-item">
                        <div class="notification-info">
                            <h4>Marketing Emails</h4>
                            <p>Receive updates about promotions and new items</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="notification-item">
                        <div class="notification-info">
                            <h4>Weekly Reports</h4>
                            <p>Get weekly sales and performance reports</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Security Tab -->
                <div class="tab-content" id="security">
                    <div class="security-item">
                        <div class="security-info">
                            <h4>Two-Factor Authentication</h4>
                            <p>Add an extra layer of security to your account</p>
                        </div>
                        <button class="btn btn-outline">Enable</button>
                    </div>
                    <div class="security-item">
                        <div class="security-info">
                            <h4>Change Password</h4>
                            <p>Update your password regularly to keep your account secure</p>
                        </div>
                        <button class="btn btn-outline" id="change-password-btn">Change</button>
                    </div>
                    <div class="security-item">
                        <div class="security-info">
                            <h4>Login Activity</h4>
                            <p>Review recent login activity on your account</p>
                        </div>
                        <button class="btn btn-outline">View</button>
                    </div>
                    <div class="security-item">
                        <div class="security-info">
                            <h4>Connected Devices</h4>
                            <p>Manage devices that have access to your account</p>
                        </div>
                        <button class="btn btn-outline">Manage</button>
                    </div>
                    
                    <!-- Password Change Form (Hidden by default) -->
                    <form id="password-form" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                        <div class="form-group">
                            <label for="current-password">Current Password</label>
                            <input type="password" id="current-password" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="new-password">New Password</label>
                            <input type="password" id="new-password" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="confirm-password">Confirm New Password</label>
                            <input type="password" id="confirm-password" class="form-control">
                        </div>
                        <button type="submit" class="btn">Update Password</button>
                        <button type="button" class="btn btn-outline" id="cancel-password">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Image preview functionality
        document.getElementById('profile_image').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const preview = document.getElementById('imagePreview');
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    // Hide the placeholder
                    document.getElementById('preview-placeholder').style.display = 'none';
                }
                reader.readAsDataURL(file);
            }
        });

        // Tab functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                tab.classList.add('active');
                const tabId = tab.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Password change form toggle
        document.getElementById('change-password-btn').addEventListener('click', () => {
            const form = document.getElementById('password-form');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });

        document.getElementById('cancel-password').addEventListener('click', () => {
            document.getElementById('password-form').style.display = 'none';
        });

        // Form submission for password form
        document.getElementById('password-form').addEventListener('submit', (e) => {
            e.preventDefault();
            alert('Password updated successfully!');
            document.getElementById('password-form').style.display = 'none';
        });
    </script>
</body>
</html>