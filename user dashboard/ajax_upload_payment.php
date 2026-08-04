<?php
require_once '../Log-in Form/includes/config_session.inc.php';
require_once '../Log-in Form/includes/dbh.inc.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['payment_screenshot']) || !isset($_POST['order_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$order_id = intval($_POST['order_id']);
$file = $_FILES['payment_screenshot'];
$target_dir = '../uploads/';
if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
$filename = 'payment_' . $order_id . '_' . time() . '_' . basename($file['name']);
$target_file = $target_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $target_file)) {
    $stmt = $pdo->prepare('UPDATE order_history SET payment_screenshot = ? WHERE id = ?');
    $stmt->execute([$filename, $order_id]);
    // Also update accepted_orders if it exists
    try {
        $stmt = $pdo->prepare('UPDATE accepted_orders SET payment_screenshot = ? WHERE id = ?');
        $stmt->execute([$filename, $order_id]);
    } catch (PDOException $e) {}
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['success' => false, 'error' => 'Upload failed']);
}
