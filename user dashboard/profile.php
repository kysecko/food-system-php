<?php
require_once '../Log-in Form/includes/config_session.inc.php';
require_once '../Log-in Form/includes/dbh.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Log-in Form/login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = :id');
$stmt->bindParam(':id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo '<p>User not found.</p>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/Food_System/user dashboard/design/userProfile.css">
    <style>
       
    </style>
</head>
<body>
    <?php include '../userSidebar.php'; ?>

    <div class="main-container">
        <div class="profile-box">
            <h2>Edit Profile</h2>
            <form method="post" action="profile.php">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?php echo htmlspecialchars($user['username']); ?>" required>

                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?php echo htmlspecialchars($user['email']); ?>" required>

                <button type="submit" class="save-btn">Save Changes</button>
            </form>

            <div class="profile-footer">
                <a href="../user dashboard/userDashboard.php">← Back to Menu</a>
            </div>
        </div>
    </div>
</body>
</html>
