<?php
require_once 'dbh.inc.php';
$year = (int)date('Y');
$stmt = $pdo->prepare('SELECT month, total_sales FROM monthly_sales WHERE year = ? ORDER BY month');
$stmt->execute([$year]);
$sales = array_fill(1, 12, 0.0);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sales[(int)$row['month']] = (float)$row['total_sales'];
}
$labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
echo json_encode([
    'labels' => $labels,
    'sales' => array_values($sales)
]);
