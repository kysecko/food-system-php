<?php
require_once '../Log-in Form/includes/config_session.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Log-in Form/login.php');
    exit;
}

require_once '../Log-in Form/includes/dbh.inc.php';
$stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$username = $user['username'] ?? ($_SESSION['user_name'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Dashboard - Landing</title>
    <style>
        body { font-family: 'Poppins', sans-serif; margin:0; }
        .content { margin-left:220px; padding:2rem; }
        .card { background:#fff; border-radius:8px; padding:1.2rem; box-shadow:0 6px 18px rgba(0,0,0,0.06); margin-bottom:1rem; }
        .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; }
        .welcome { font-size:1.6rem; margin-bottom:0.5rem; }
        .quick-link { display:flex; align-items:center; justify-content:space-between; padding:0.8rem 1rem; border-radius:6px; background:linear-gradient(90deg,#fff,#fff); border:1px solid #eee; text-decoration:none; color:#222; }
        .quick-link:hover { box-shadow:0 8px 24px rgba(0,0,0,0.06); }
    </style>
</head>
<body>

<?php include __DIR__ . '/../userSidebar.php'; ?>

<div class="content">
    <div class="card">
        <div class="welcome">Welcome back, <?php echo htmlspecialchars($username); ?> 👋</div>
        <div>Glad to see you. Use the quick links below to navigate the dashboard.</div>
    </div>

    <div class="grid">
        <!-- <a class="quick-link card" href="userDashboard.php">
            <div>
                <strong>My Orders</strong>
                <div style="font-size:0.9rem; color:#666;">View and manage your orders</div>
            </div>
            <div style="font-size:1.2rem; color:#ff6b35;">→</div>
        </a> -->

        <a class="quick-link card" href="menu.php">
            <div>
                <strong>Menu</strong>
                <div style="font-size:0.9rem; color:#666;">Browse available items</div>
            </div>
            <div style="font-size:1.2rem; color:#ff6b35;">→</div>
        </a>

        <a class="quick-link card" href="profile.php">
            <div>
                <strong>Profile</strong>
                <div style="font-size:0.9rem; color:#666;">View or edit your profile</div>
            </div>
            <div style="font-size:1.2rem; color:#ff6b35;">→</div>
        </a>

        <a class="quick-link card" href="cart.php">
            <div>
                <strong>Cart</strong>
                <div style="font-size:0.9rem; color:#666;">View items in your cart</div>
            </div>
            <div style="font-size:1.2rem; color:#ff6b35;">→</div>
        </a>
    </div>

</div>

</body>
</html>