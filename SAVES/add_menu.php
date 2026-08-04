
<?php
require_once '../../Log-in Form/includes/config_session.inc.php';
require_once '../../Log-in Form/includes/dbh.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Log-in Form/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $sizes = trim($_POST['sizes']);
    $prices = trim($_POST['prices']);
    $addons = trim($_POST['addons']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    // Handle image upload
    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
        $targetDir = "../uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir);
        $targetFile = $targetDir . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile);
        $image_path = $targetFile;
    }

    $stmt = $pdo->prepare("INSERT INTO menu_items (name, description, sizes, prices, addons, image_path, is_available) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $description, $sizes, $prices, $addons, $image_path, $is_available]);

    header("Location: menu.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Menu Item</title>

<style>
    form {
        margin-left: 220px;
        background: #fff;
        padding: 2rem;
        max-width: 600px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    input, textarea {
        width: 100%;
        margin-top: 1rem;
        padding: 0.5rem;
        border-radius: 6px;
        border: 1px solid #ccc;
    }
    button {
        margin-top: 1.5rem;
        background: #ff6b35;
        border: none;
        color: #fff;
        padding: 0.75rem 1.5rem;
        border-radius: 6px;
        cursor: pointer;
    }
</style>
</head>
<body>
    <?php include '../../adminSidebar.php'; ?>
    
<form method="POST" enctype="multipart/form-data">
    <h2>Add New Menu Item</h2>
    <label>Name</label>
    <input type="text" name="name" required>

    <label>Description</label>
    <textarea name="description"></textarea>

    <label>Sizes</label>
    <input type="text" name="sizes" placeholder="e.g., Small, Medium, Large">

    <label>Prices</label>
    <input type="number" name="prices" placeholder="e.g., 50, 75, 100">

    <label>Addons</label>
    <input type="text" name="addons" placeholder="e.g., Extra Cheese, Bacon">

    <label>Image</label>
    <input type="file" name="image" accept="image/*">

    <label><input type="checkbox" name="is_available" checked> Available</label>

    <button type="submit">Add Item</button>
</form>
</body>
</html>
