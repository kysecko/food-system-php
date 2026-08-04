<?php
require_once '../../Log-in Form/includes/config_session.inc.php';
require_once '../../Log-in Form/includes/dbh.inc.php';

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

if (!isset($_SESSION['user_id'])) {
	header('Location: ../../Log-in Form/login.php');
	exit;
}

$stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id');
$stmt->bindParam(':id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
	header('Location: ../../user dashboard/userDashboard.php');
	exit;
}

// Mark as delivered
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_delivered'])) {
	$order_id = intval($_POST['order_id']);
	$payment_screenshot = '';

	if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['tmp_name']) {
		$target_dir = '../../assets/images/uploads/';
		if (!is_dir($target_dir))
			mkdir($target_dir, 0777, true);
		$filename = 'payment_' . $order_id . '_' . time() . '_' . basename($_FILES['payment_screenshot']['name']);
		$target_file = $target_dir . $filename;
		if (move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $target_file)) {
			$payment_screenshot = 'assets/images/uploads/' . $filename;
		}
	}

	$stmt = $pdo->prepare('UPDATE completed_orders SET delivered = 1, payment_screenshot = ? WHERE id = ?');
	$stmt->execute([$payment_screenshot, $order_id]);

	// Update order_history
	$stmt2 = $pdo->prepare('UPDATE order_history SET delivered = 1, status = "delivered" WHERE id = ?');
	$stmt2->execute([$order_id]);

	// Get user email for notification
	$stmt3 = $pdo->prepare('SELECT user_id, customer_name FROM completed_orders WHERE id = ?');
	$stmt3->execute([$order_id]);
	$order = $stmt3->fetch(PDO::FETCH_ASSOC);

	if ($order) {
		$stmt4 = $pdo->prepare('SELECT email FROM users WHERE id = ?');
		$stmt4->execute([$order['user_id']]);
		$userEmail = $stmt4->fetchColumn();

		// Send Gmail notification
		require_once '../../Log-in Form/vendor/phpmailer/phpmailer/src/PHPMailer.php';
		require_once '../../Log-in Form/vendor/phpmailer/phpmailer/src/SMTP.php';
		require_once '../../Log-in Form/vendor/phpmailer/phpmailer/src/Exception.php';

		$mail = new PHPMailer\PHPMailer\PHPMailer();
		$mail->isSMTP();
		$mail->Host = 'smtp.gmail.com';
		$mail->Port = 587;
		$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
		$mail->SMTPAuth = true;
		$mail->Username = 'luietan04@gmail.com';
		$mail->Password = 'cqwv qwwg zacn odnz';
		$mail->setFrom('luietan04@gmail.com', 'Food Shop');
		$mail->addAddress($userEmail, $order['customer_name']);
		$mail->Subject = 'Order Delivered - Food Shop';
		$mail->isHTML(true);
		$mail->Body = '<p>Your order has been marked as delivered! Thank you for ordering at Food Shop.</p>';
		@$mail->send();

		$_SESSION['success_message'] = 'Order marked as delivered and user notified!';
	} else {
		$_SESSION['success_message'] = 'Order marked as delivered!';
	}

	header('Location: completedOrders.php');
	exit;
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$delivery_status = $_GET['delivery_status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$min_amount = $_GET['min_amount'] ?? '';
$max_amount = $_GET['max_amount'] ?? '';

// Build query with filters
$query = 'SELECT * FROM completed_orders WHERE 1=1';
$params = [];

if (!empty($search)) {
	$query .= ' AND (customer_name LIKE ? OR order_details LIKE ?)';
	$params[] = "%$search%";
	$params[] = "%$search%";
}

if ($delivery_status === 'delivered') {
	$query .= ' AND delivered = 1';
} elseif ($delivery_status === 'pending') {
	$query .= ' AND delivered = 0';
}

if (!empty($date_from)) {
	$query .= ' AND DATE(order_date) >= ?';
	$params[] = $date_from;
}

if (!empty($date_to)) {
	$query .= ' AND DATE(order_date) <= ?';
	$params[] = $date_to;
}

if (!empty($min_amount)) {
	$query .= ' AND total_amount >= ?';
	$params[] = $min_amount;
}

if (!empty($max_amount)) {
	$query .= ' AND total_amount <= ?';
	$params[] = $max_amount;
}

$query .= ' ORDER BY order_date DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_orders = count($orders);
$delivered_orders = count(array_filter($orders, fn($order) => $order['delivered']));
$pending_delivery = $total_orders - $delivered_orders;
$total_revenue = array_sum(array_column($orders, 'total_amount'));
$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Managing Order - Completed Orders</title>
	<link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
	<style>
		* {
			box-sizing: border-box;
			font-family: "Poppins", sans-serif;
			margin: 0;
			padding: 0;
		}

		body {
			display: flex;
			min-height: 100vh;
			background-color: #f5f7fa;
		}

		.main-content {
			margin-left: 220px;
			padding: 2rem;
			min-height: 100vh;
			background-color: #f5f7fa;
			width: calc(100% - 220px);
		}

		.page-header {
			margin-bottom: 2rem;
		}

		.page-header h1 {
			color: #222e3c;
			margin-bottom: 0.5rem;
			font-size: 2rem;
		}

		.page-header p {
			color: #666;
			font-size: 1.1rem;
		}

		.summary-cards {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
			gap: 1.5rem;
			margin-bottom: 2rem;
		}

		.summary-card {
			background: white;
			border-radius: 12px;
			padding: 1.5rem;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
			display: flex;
			flex-direction: column;
			transition: all 0.3s ease
		}

		.summary-card h3 {
			color: #666;
			font-size: 0.9rem;
			font-weight: 500;
			margin-bottom: 0.5rem;
		}

		.summary-card .value {
			font-size: 1.8rem;
			font-weight: 700;
			color: #222e3c;
			margin-bottom: 0.5rem;
		}

		.summary-card .description {
			font-size: 0.85rem;
			color: #888;
		}

		.summary-card:hover {
			transform: translateY(-8px);
			box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
		}

		.filters-container {
			background: white;
			border-radius: 12px;
			padding: 1.5rem;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
			margin-bottom: 2rem;
		}

		.filter-row {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 1rem;
			margin-bottom: 1rem;
		}

		.filter-group {
			display: flex;
			flex-direction: column;
		}

		.filter-group label {
			font-weight: 600;
			margin-bottom: 0.5rem;
			color: #222e3c;
		}

		.filter-input {
			padding: 0.75rem;
			border-radius: 6px;
			border: 1px solid #ddd;
			font-size: 0.9rem;
			background: #f8f9fa;
			transition: border-color 0.3s;
		}

		.filter-input:focus {
			outline: none;
			border-color: #4361ee;
		}

		.filter-actions {
			display: flex;
			gap: 1rem;
			justify-content: flex-end;
		}

		.btn {
			padding: 0.75rem 1.5rem;
			border: none;
			border-radius: 8px;
			font-weight: 500;
			cursor: pointer;
			display: flex;
			align-items: center;
			gap: 0.5rem;
			transition: all 0.3s;
			font-size: 0.9rem;
		}

		.btn-primary {
			background: #4361ee;
			color: white;
		}

		.btn-primary:hover {
			background: #3a56e0;
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
		}

		.btn-secondary {
			background: #6c757d;
			color: white;
		}

		.btn-secondary:hover {
			background: #5a6268;
		}

		.btn-success {
			background: #2ecc71;
			color: white;
		}

		.btn-success:hover {
			background: #27ae60;
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
		}

		.btn-warning {
			background: #ffc107;
			color: #212529;
		}

		.btn-warning:hover {
			background: #e0a800;
		}

		.order-card {
			background: white;
			border-radius: 12px;
			padding: 1.5rem;
			margin-bottom: 1rem;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
			border-left: 4px solid #2ecc71;
			transition: transform 0.2s, box-shadow 0.2s;
		}

		.order-card:hover {
			transform: translateY(-2px);
			box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
		}

		.order-header {
			display: flex;
			justify-content: space-between;
			align-items: flex-start;
			margin-bottom: 1rem;
			flex-wrap: wrap;
			gap: 1rem;
		}

		.order-id {
			font-weight: 600;
			color: #222e3c;
			font-size: 1.1rem;
		}

		.order-customer {
			color: #666;
			font-size: 0.9rem;
			margin-top: 0.25rem;
		}

		.order-amount {
			font-weight: 600;
			color: #2ecc71;
			font-size: 1.2rem;
		}

		.order-date {
			color: #888;
			font-size: 0.85rem;
			text-align: right;
		}

		.order-items {
			margin: 1rem 0;
		}

		.order-item {
			display: flex;
			align-items: center;
			padding: 0.75rem;
			background: #f8f9fa;
			border-radius: 8px;
			margin-bottom: 0.5rem;
		}

		.item-image {
			width: 50px;
			height: 50px;
			border-radius: 8px;
			object-fit: cover;
			margin-right: 1rem;
		}

		.item-details {
			flex: 1;
		}

		.item-name {
			font-weight: 600;
			color: #222e3c;
		}

		.item-meta {
			color: #666;
			font-size: 0.85rem;
			margin-top: 0.25rem;
		}

		.order-actions {
			display: flex;
			gap: 1rem;
			justify-content: flex-end;
			margin-top: 1rem;
		}

		.empty-state {
			text-align: center;
			padding: 3rem 2rem;
			color: #666;
			background: white;
			border-radius: 12px;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
		}

		.empty-state div:first-child {
			font-size: 3rem;
			margin-bottom: 1rem;
		}

		.empty-state h3 {
			color: #222e3c;
			margin-bottom: 0.5rem;
		}

		.alert {
			padding: 12px 16px;
			border-radius: 6px;
			margin-bottom: 1rem;
		}

		.alert-success {
			background-color: #e8f8f0;
			color: #2ecc71;
			border: 1px solid #d4edda;
		}

		.status-badge {
			padding: 0.25rem 0.75rem;
			border-radius: 20px;
			font-size: 0.8rem;
			font-weight: 500;
		}

		.status-delivered {
			background: #e8f8f0;
			color: #2ecc71;
		}

		.status-pending {
			background: #fff4e6;
			color: #e67e22;
		}

		.payment-modal {
			display: none;
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background-color: rgba(0, 0, 0, 0.8);
			z-index: 1000;
			justify-content: center;
			align-items: center;
		}

		.payment-modal-content {
			background: white;
			padding: 2rem;
			border-radius: 12px;
			max-width: 90%;
			max-height: 90%;
			position: relative;
		}

		.payment-modal img {
			max-width: 100%;
			max-height: 70vh;
			border-radius: 8px;
		}

		.close-modal {
			position: absolute;
			top: 15px;
			right: 20px;
			font-size: 24px;
			cursor: pointer;
			color: #888;
			background: none;
			border: none;
		}

		.delivery-modal {
			display: none;
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background-color: rgba(0, 0, 0, 0.5);
			z-index: 1000;
			justify-content: center;
			align-items: center;
		}

		.delivery-modal-content {
			background: white;
			padding: 2rem;
			border-radius: 12px;
			width: 90%;
			max-width: 500px;
			position: relative;
		}

		.upload-preview {
			max-width: 100%;
			max-height: 200px;
			margin: 1rem 0;
			border-radius: 8px;
			display: none;
			border: 2px solid #e9ecef;
		}

		.search-box {
			display: flex;
			align-items: center;
			background: white;
			padding: 0.75rem 1rem;
			border-radius: 8px;
			box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
			flex: 1;
			max-width: 400px;
		}

		.search-box input {
			border: none;
			outline: none;
			font-size: 0.9rem;
			width: 100%;
			color: #333;
			background: transparent;
		}

		.search-box input::placeholder {
			color: #999;
		}

		@media (max-width: 1024px) {
			.summary-cards {
				grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
			}
		}

		@media (max-width: 900px) {
			.main-content {
				margin-left: 60px;
				width: calc(100% - 60px);
				padding: 1rem;
			}

			.filter-row {
				grid-template-columns: 1fr;
			}

			.filter-actions {
				justify-content: stretch;
			}

			.btn {
				flex: 1;
				text-align: center;
			}
		}

		@media (max-width: 768px) {
			body {
				flex-direction: column;
			}

			.main-content {
				margin-left: 0;
				width: 100%;
				padding: 1rem;
			}

			.order-header {
				flex-direction: column;
				align-items: flex-start;
			}

			.order-actions {
				justify-content: stretch;
			}

			.btn {
				flex: 1;
				text-align: center;
			}
		}
	</style>
</head>

<body>
	<?php include '../../adminSidebar.php'; ?>

	<div class="main-content">
		<div class="page-header" data-aos="fade-down" data-aos-duration="1000">
			<h1>Completed Orders</h1>
			<p>Manage delivered and pending delivery orders.</p>
		</div>

		<?php if (isset($_SESSION['success_message'])): ?>
			<div class="alert alert-success">
				✅ <?php echo $_SESSION['success_message'];
				unset($_SESSION['success_message']); ?>
			</div>
		<?php endif; ?>

		<!-- Summary Cards -->
		<div class="summary-cards" data-aos="zoom-in" data-aos-duration="1000">
			<div class="summary-card">
				<h3>Completed Orders</h3>
				<div class="value"><?php echo $total_orders; ?></div>
				<div class="description">📦 Total processed</div>
			</div>
			<div class="summary-card">
				<h3>Delivered</h3>
				<div class="value"><?php echo $delivered_orders; ?></div>
				<div class="description">✅ Successfully delivered</div>
			</div>
			<div class="summary-card">
				<h3>Pending Delivery</h3>
				<div class="value"><?php echo $pending_delivery; ?></div>
				<div class="description">⏳ Awaiting delivery</div>
			</div>
			<div class="summary-card">
				<h3>Total Revenue</h3>
				<div class="value">₱<?php echo number_format($total_revenue, 2); ?></div>
				<div class="description">💰 From completed orders</div>
			</div>
		</div>

		<!-- Filters -->
		<div class="filters-container" data-aos="fade-up" data-aos-duration="1000">
			<form method="GET" action="">
				<div class="filter-row">
					<div class="filter-group">
						<label for="search">Search Orders</label>
						<input type="text" id="search" name="search" class="filter-input"
							placeholder="Search by customer or items..."
							value="<?php echo htmlspecialchars($search); ?>">
					</div>
					<div class="filter-group">
						<label for="delivery_status">Delivery Status</label>
						<select id="delivery_status" name="delivery_status" class="filter-input">
							<option value="all" <?php echo $delivery_status === 'all' ? 'selected' : ''; ?>>All Status
							</option>
							<option value="delivered" <?php echo $delivery_status === 'delivered' ? 'selected' : ''; ?>>
								Delivered</option>
							<option value="pending" <?php echo $delivery_status === 'pending' ? 'selected' : ''; ?>>
								Pending Delivery</option>
						</select>
					</div>
					<div class="filter-group">
						<label for="date_from">Date From</label>
						<input type="date" id="date_from" name="date_from" class="filter-input"
							value="<?php echo htmlspecialchars($date_from); ?>">
					</div>
					<div class="filter-group">
						<label for="date_to">Date To</label>
						<input type="date" id="date_to" name="date_to" class="filter-input"
							value="<?php echo htmlspecialchars($date_to); ?>">
					</div>
					<div class="filter-group">
						<label for="min_amount">Min Amount</label>
						<input type="number" id="min_amount" name="min_amount" class="filter-input" placeholder="0.00"
							step="0.01" value="<?php echo htmlspecialchars($min_amount); ?>">
					</div>
					<div class="filter-group">
						<label for="max_amount">Max Amount</label>
						<input type="number" id="max_amount" name="max_amount" class="filter-input"
							placeholder="1000.00" step="0.01" value="<?php echo htmlspecialchars($max_amount); ?>">
					</div>
				</div>
				<div class="filter-actions">
					<button type="submit" class="btn btn-primary"> <svg xmlns="http://www.w3.org/2000/svg" width="20"
							height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
							stroke-linecap="round" stroke-linejoin="round"
							class="lucide lucide-list-filter-plus-icon lucide-list-filter-plus">
							<path d="M12 5H2" />
							<path d="M6 12h12" />
							<path d="M9 19h6" />
							<path d="M16 5h6" />
							<path d="M19 8V2" />
						</svg>
						Apply Filters</button>
					<a href="acceptedOrders.php" class="btn btn-secondary" style="text-decoration: none;"><svg
							xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
							class="lucide lucide-brush-cleaning-icon lucide-brush-cleaning">
							<path d="m16 22-1-4" />
							<path
								d="M19 13.99a1 1 0 0 0 1-1V12a2 2 0 0 0-2-2h-3a1 1 0 0 1-1-1V4a2 2 0 0 0-4 0v5a1 1 0 0 1-1 1H6a2 2 0 0 0-2 2v.99a1 1 0 0 0 1 1" />
							<path d="M5 14h14l1.973 6.767A1 1 0 0 1 20 22H4a1 1 0 0 1-.973-1.233z" />
							<path d="m8 22 1-4" />
						</svg>Clear Filters</a>
				</div>
			</form>
		</div>

		<?php if (empty($orders)): ?>
			<div class="empty-state">
				<img src="/Food_System/assets/icons/empty.jpg" alt="empty" style="width: 100px; height: 100px;">
				<h3>No Completed Orders</h3>
				<p>All orders are being processed! Check accepted orders for new completions.</p>
			</div>
		<?php else: ?>
			<div class="orders-list">
				<?php foreach ($orders as $row):
					$order_age = time() - strtotime($row['order_date']);
					$is_old = $order_age > 86400; // More than 24 hours old
					?>
					<div class="order-card">
						<div class="order-header">
							<div>
								<div class="order-id">
									Order #<?php echo $row['id']; ?>
									<span
										class="status-badge <?php echo $row['delivered'] ? 'status-delivered' : 'status-pending'; ?>">
										<?php echo $row['delivered'] ? '✅ DELIVERED' : '⏳ PENDING DELIVERY'; ?>
									</span>
									<?php if (!$row['delivered'] && $is_old): ?>
										<span class="status-badge" style="background: #ffeaea; color: #e74c3c;">
											⚠️ OVERDUE
										</span>
									<?php endif; ?>
								</div>
								<div class="order-customer">👤 <?php echo htmlspecialchars($row['customer_name']); ?></div>
								<div class="order-date" style="text-align: left; margin-top: 0.5rem;">
									📅 <?php echo date('M j, Y g:i A', strtotime($row['order_date'])); ?>
									<?php if (!$row['delivered']): ?>
										<br><small style="color: #e67e22;">⏰ Completed <?php echo round($order_age / 3600, 1); ?>
											hours ago</small>
									<?php endif; ?>
								</div>
							</div>
							<div style="text-align: right;">
								<div class="order-amount">₱<?php echo number_format($row['total_amount'], 2); ?></div>
							</div>
						</div>

						<div class="order-items">
							<?php
							$orderDetails = json_decode($row['order_details'], true);
							if (is_array($orderDetails)):
								foreach ($orderDetails as $item):
									$img = '';
									if (!empty($item['menuId'])) {
										$imgStmt = $pdo->prepare('SELECT image_path FROM menu_items WHERE id = ?');
										$imgStmt->execute([$item['menuId']]);
										$imgPath = $imgStmt->fetchColumn();
										if ($imgPath) {
											$img = '<img src="../../assets/images/uploads/' . htmlspecialchars($imgPath) . '" class="item-image">';
										}
									}
									?>
									<div class="order-item">
										<?php echo $img; ?>
										<div class="item-details">
											<div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
											<div class="item-meta">
												Size: <?php echo htmlspecialchars($item['size']); ?> •
												Qty: <?php echo htmlspecialchars($item['qty']); ?>
												<?php if (!empty($item['addons'])): ?>
													<br>Add-ons: <?php echo htmlspecialchars(implode(', ', $item['addons'])); ?>
												<?php endif; ?>
												<?php if (!empty($item['notes'])): ?>
													<br>Notes: <?php echo htmlspecialchars($item['notes']); ?>
												<?php endif; ?>
											</div>
										</div>
									</div>
								<?php endforeach; endif; ?>
						</div>

						<div class="order-actions">
							<?php
							$paymentScreenshot = $row['payment_screenshot'];
							if (empty($paymentScreenshot)) {
								$stmtPay = $pdo->prepare('SELECT payment_screenshot FROM order_history WHERE id = ?');
								$stmtPay->execute([$row['id']]);
								$paymentScreenshot = $stmtPay->fetchColumn();
							}

							if (!empty($paymentScreenshot)): ?>
								<button type="button" class="btn btn-secondary view-payment-btn"
									data-payment="<?php echo htmlspecialchars($paymentScreenshot); ?>">
									👁️ View Payment
								</button>
							<?php else: ?>
								<span class="btn btn-warning" style="opacity: 0.7;">📸 No Payment</span>
							<?php endif; ?>

							<?php if (!$row['delivered']): ?>
								<button type="button" class="btn btn-success"
									onclick="openDeliveryModal(<?php echo $row['id']; ?>)">
									🚚 Mark Delivered
								</button>
							<?php else: ?>
								<span class="btn btn-success" style="opacity: 0.6; cursor: default;">✅ Delivered</span>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<!-- Payment Modal -->
	<div id="paymentModal" class="payment-modal">
		<div class="payment-modal-content">
			<button class="close-modal" id="closePaymentModal">&times;</button>
			<h3>Payment Screenshot</h3>
			<img id="paymentImage" src="" alt="Payment Screenshot">
		</div>
	</div>

	<!-- Delivery Modal -->
	<div id="deliveryModal" class="delivery-modal">
		<div class="delivery-modal-content">
			<button class="close-modal" id="closeDeliveryModal">&times;</button>
			<h3>🚚 Mark Order as Delivered</h3>
			<form method="post" action="completedOrders.php" enctype="multipart/form-data" id="deliveryForm">
				<input type="hidden" name="order_id" id="modalOrderId">

				<div class="filter-group">
					<label for="payment_screenshot">📸 Upload Delivery Proof (Optional)</label>
					<input type="file" id="payment_screenshot" name="payment_screenshot" accept="image/*"
						class="filter-input" onchange="previewImage(this)">
					<img id="imagePreview" class="upload-preview" alt="Image preview">
				</div>

				<div class="filter-actions" style="margin-top: 1.5rem;">
					<button type="button" class="btn btn-secondary" onclick="closeDeliveryModal()">❌ Cancel</button>
					<button type="submit" name="mark_delivered" class="btn btn-success">✅ Confirm Delivery</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		// Payment modal functionality
		const paymentModal = document.getElementById('paymentModal');
		const paymentImage = document.getElementById('paymentImage');
		const closePaymentModal = document.getElementById('closePaymentModal');

		document.querySelectorAll('.view-payment-btn').forEach(button => {
			button.addEventListener('click', function () {
				const paymentFile = this.getAttribute('data-payment');
				paymentImage.src = '../../uploads/' + paymentFile;
				paymentModal.style.display = 'flex';
			});
		});

		closePaymentModal.addEventListener('click', function () {
			paymentModal.style.display = 'none';
		});

		paymentModal.addEventListener('click', function (e) {
			if (e.target === paymentModal) {
				paymentModal.style.display = 'none';
			}
		});

		// Delivery modal functionality
		const deliveryModal = document.getElementById('deliveryModal');
		const closeDeliveryModal = document.getElementById('closeDeliveryModal');

		function openDeliveryModal(orderId) {
			document.getElementById('modalOrderId').value = orderId;
			deliveryModal.style.display = 'flex';
		}

		function closeDeliveryModal() {
			deliveryModal.style.display = 'none';
			document.getElementById('imagePreview').style.display = 'none';
			document.getElementById('payment_screenshot').value = '';
		}

		closeDeliveryModal.addEventListener('click', closeDeliveryModal);

		deliveryModal.addEventListener('click', function (e) {
			if (e.target === deliveryModal) {
				closeDeliveryModal();
			}
		});

		function previewImage(input) {
			const preview = document.getElementById('imagePreview');
			if (input.files && input.files[0]) {
				const reader = new FileReader();
				reader.onload = function (e) {
					preview.src = e.target.result;
					preview.style.display = 'block';
				}
				reader.readAsDataURL(input.files[0]);
			}
		}

		// Auto-submit form when filters change
		document.getElementById('delivery_status')?.addEventListener('change', function () {
			this.form.submit();
		});

		document.getElementById('date_from')?.addEventListener('change', function () {
			this.form.submit();
		});

		document.getElementById('date_to')?.addEventListener('change', function () {
			this.form.submit();
		});

		// Add loading state to forms
		document.querySelectorAll('form').forEach(form => {
			form.addEventListener('submit', function () {
				const button = this.querySelector('button[type="submit"]');
				if (button) {
					button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hourglass-icon lucide-hourglass"><path d="M5 22h14" /><path d="M5 2h14" /><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22" /><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2" /></svg> Processing...';
					button.disabled = true;
				}
			});
		});

		// Auto-refresh page every 60 seconds
		setTimeout(function () {
			window.location.reload();
		}, 60000);

		// Add hover effects to order cards
		document.addEventListener('DOMContentLoaded', function () {
			const cards = document.querySelectorAll('.order-card');
			cards.forEach(card => {
				card.addEventListener('mouseenter', function () {
					this.style.transform = 'translateY(-2px)';
					this.style.boxShadow = '0 8px 20px rgba(0,0,0,0.1)';
				});
				card.addEventListener('mouseleave', function () {
					this.style.transform = 'translateY(0)';
					this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.05)';
				});
			});
		});

		// Quick search functionality
		document.getElementById('search')?.addEventListener('input', function () {
			const searchTerm = this.value.toLowerCase();
			const orderCards = document.querySelectorAll('.order-card');

			orderCards.forEach(card => {
				const text = card.textContent.toLowerCase();
				if (text.includes(searchTerm)) {
					card.style.display = 'block';
				} else {
					card.style.display = 'none';
				}
			});
		});
	</script>
</body>

</html>