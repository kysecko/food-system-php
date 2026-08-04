<?php
require_once '../../Log-in Form/includes/config_session.inc.php';
require_once '../../Log-in Form/includes/dbh.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Log-in Form/login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: menu.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo "Item not found.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $sizes = trim($_POST['sizes']);
    $prices = trim($_POST['prices']);
    $addons = trim($_POST['addons']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    $image_path = $item['image_path'];
    if (!empty($_FILES['image']['name'])) {
        $targetDir = "../uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir);
        $targetFile = $targetDir . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile);
        $image_path = $targetFile;
    }

    $stmt = $pdo->prepare("UPDATE menu_items SET name=?, description=?, sizes=?, prices=?, addons=?, image_path=?, is_available=? WHERE id=?");
    $stmt->execute([$name, $description, $sizes, $prices, $addons, $image_path, $is_available, $id]);

    header("Location: menu.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php include '../../adminSidebar.php'; ?>  
<title>Edit Menu Item</title>
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
    img {
        width: 100px;
        margin-top: 1rem;
        border-radius: 8px;
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
<form method="POST" enctype="multipart/form-data">
    <h2>Edit Menu Item</h2>
    <label>Name</label>
    <input type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required>

    <label>Description</label>
    <textarea name="description"><?= htmlspecialchars($item['description']) ?></textarea>

    <label>Sizes</label>
    <input type="text" name="sizes" value="<?= htmlspecialchars($item['sizes']) ?>">

    <label>Prices</label>
    <input type="text" name="prices" value="<?= htmlspecialchars($item['prices']) ?>">

    <label>Addons</label>
    <input type="text" name="addons" value="<?= htmlspecialchars($item['addons']) ?>">

    <label>Image</label><br>
    <?php if ($item['image_path']): ?>
        <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="Image">
    <?php endif; ?>
    <input type="file" name="image" accept="image/*">

    <label><input type="checkbox" name="is_available" <?= $item['is_available'] ? 'checked' : '' ?>> Available</label>

    <button type="submit">Update Item</button>
</form>
</body>
</html>
