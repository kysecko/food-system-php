<?php
require_once '../../Log-in Form/includes/config_session.inc.php';
require_once '../../Log-in Form/includes/dbh.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../Log-in Form/login.php');
    exit;
}

// Handle Add/Edit form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            // Add new menu item
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            
            // Process variations and prices
            $variations_array = [];
            $prices_array = [];
            
            if (isset($_POST['variation_name']) && is_array($_POST['variation_name'])) {
                foreach ($_POST['variation_name'] as $index => $variation_name) {
                    if (!empty(trim($variation_name))) {
                        $variation_price = isset($_POST['variation_price'][$index]) ? floatval($_POST['variation_price'][$index]) : 0;
                        $variations_array[] = trim($variation_name);
                        $prices_array[] = $variation_price;
                    }
                }
            }
            
            $variations = implode(', ', $variations_array);
            $prices = implode(', ', $prices_array);
            
            // Process addons
            $addons_array = [];
            if (isset($_POST['addon_name']) && is_array($_POST['addon_name'])) {
                foreach ($_POST['addon_name'] as $addon_name) {
                    if (!empty(trim($addon_name))) {
                        $addons_array[] = trim($addon_name);
                    }
                }
            }
            $addons = implode(', ', $addons_array);
            
            $is_available = isset($_POST['is_available']) ? 1 : 0;

            // Handle image upload - FIXED PATH
            $image_path = null;
            if (!empty($_FILES['image']['name'])) {
                $targetDir = "../../assets/images/uploads/";
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                
                // Generate unique filename
                $fileExtension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
                $uniqueFilename = uniqid() . '_' . time() . '.' . $fileExtension;
                $targetFile = $targetDir . $uniqueFilename;
                
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
                    $image_path = "assets/images/uploads/" . $uniqueFilename;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO menu_items (name, description, sizes, prices, addons, image_path, is_available) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $variations, $prices, $addons, $image_path, $is_available]);

            // Return success response for AJAX
            echo json_encode(['success' => true, 'message' => 'Menu item added successfully']);
            exit;

        } elseif ($_POST['action'] === 'edit') {
            // Edit existing menu item
            $id = $_POST['id'];
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            
            // Process variations and prices
            $variations_array = [];
            $prices_array = [];
            
            if (isset($_POST['variation_name']) && is_array($_POST['variation_name'])) {
                foreach ($_POST['variation_name'] as $index => $variation_name) {
                    if (!empty(trim($variation_name))) {
                        $variation_price = isset($_POST['variation_price'][$index]) ? floatval($_POST['variation_price'][$index]) : 0;
                        $variations_array[] = trim($variation_name);
                        $prices_array[] = $variation_price;
                    }
                }
            }
            
            $variations = implode(', ', $variations_array);
            $prices = implode(', ', $prices_array);
            
            // Process addons
            $addons_array = [];
            if (isset($_POST['addon_name']) && is_array($_POST['addon_name'])) {
                foreach ($_POST['addon_name'] as $addon_name) {
                    if (!empty(trim($addon_name))) {
                        $addons_array[] = trim($addon_name);
                    }
                }
            }
            $addons = implode(', ', $addons_array);
            
            $is_available = isset($_POST['is_available']) ? 1 : 0;

            // Get current item to preserve image if not changed
            $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
            $stmt->execute([$id]);
            $currentItem = $stmt->fetch(PDO::FETCH_ASSOC);

            $image_path = $currentItem['image_path'];
            if (!empty($_FILES['image']['name'])) {
                $targetDir = "../../assets/images/uploads/";
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                
                // Generate unique filename
                $fileExtension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
                $uniqueFilename = uniqid() . '_' . time() . '.' . $fileExtension;
                $targetFile = $targetDir . $uniqueFilename;
                
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
                    // Delete old image if exists
                    if ($image_path && file_exists("../../" . $image_path)) {
                        unlink("../../" . $image_path);
                    }
                    $image_path = "assets/images/uploads/" . $uniqueFilename;
                }
            }

            $stmt = $pdo->prepare("UPDATE menu_items SET name=?, description=?, sizes=?, prices=?, addons=?, image_path=?, is_available=? WHERE id=?");
            $stmt->execute([$name, $description, $variations, $prices, $addons, $image_path, $is_available, $id]);

            // Return success response for AJAX
            echo json_encode(['success' => true, 'message' => 'Menu item updated successfully']);
            exit;
        }
    }
}

// Handle delete
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    // Get item to delete image file
    $stmt = $pdo->prepare("SELECT image_path FROM menu_items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Delete image file if exists
    if ($item && $item['image_path'] && file_exists("../../" . $item['image_path'])) {
        unlink("../../" . $item['image_path']);
    }
    
    $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: menu.php");
    exit;
}

// Fetch all menu items
$stmt = $pdo->query("SELECT * FROM menu_items ORDER BY created_at DESC");
$menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate menu statistics - FIXED: Replaced arrow function with regular function
$total_items = count($menuItems);
$available_items = 0;
$unavailable_items = 0;

foreach ($menuItems as $item) {
    if ($item['is_available']) {
        $available_items++;
    } else {
        $unavailable_items++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - Arko Flavors</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
     <link rel="stylesheet" href="/Food_System/admin dashboard/menu/menu.css">
    <style>
        .dynamic-field-group {
            margin-bottom: 1rem;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }
        
        .field-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .field-row input {
            flex: 1;
        }
        
        .remove-btn {
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
        }
        
        .remove-btn:hover {
            background: #c0392b;
        }
        
        .add-more-btn {
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 15px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .add-more-btn:hover {
            background: #2980b9;
        }
        
        .section-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #222e3c;
            border-bottom: 2px solid #4361ee;
            padding-bottom: 5px;
        }
        
        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
    </style>
</head>

<body>
    <?php include '../../adminSidebar.php'; ?>

    <div class="main-content">
                <?php include '../admin_header.php'; ?>


        <div id="alertMessage" class="alert" style="display: none;"></div>

        <!-- Summary Cards -->
        <div class="summary-cards"data-aos="zoom-in" data-aos-duration="1000">
            <div class="summary-card">
                <h3>Total Items</h3>
                <div class="value"><?php echo $total_items; ?></div>
                <div class="description">📊 All menu items</div>
            </div>
            <div class="summary-card">
                <h3>Available</h3>
                <div class="value"><?php echo $available_items; ?></div>
                <div class="description">✅ Ready to order</div>
            </div>
            <div class="summary-card">
                <h3>Unavailable</h3>
                <div class="value"><?php echo $unavailable_items; ?></div>
                <div class="description">⏳ Out of stock</div>
            </div>
        </div>

        <!-- Search and Add Button -->
        <div class="menu-actions" data-aos="fade-left" data-aos-duration="1000">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search menu items...">
                <img src="/Food_System/assets/icons/search.png" alt="search"
                    style="width: 20px; height: 20px; margin-left: 10px;">
            </div>
            <button class="btn btn-primary" id="openAddModalBtn">
                <span>+</span> Add New Item
            </button>
        </div>

        <!-- Menu Table -->
        <div class="menu-table-container" data-aos="fade-up" data-aos-duration="1000">
            <div class="table-responsive">
                <table class="menu-table" id="menuTable">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Image</th>
                            <th>Details</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($menuItems): ?>
                            <?php foreach ($menuItems as $item): ?>
                                <tr class="menu-item">
                                    <td>
                                        <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="item-description"><?= htmlspecialchars($item['description']) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($item['image_path']): ?>
                                            <img src="/Food_System/<?= htmlspecialchars($item['image_path']) ?>"
                                                alt="<?= htmlspecialchars($item['name']) ?>" class="item-image">
                                        <?php else: ?>
                                            <div class="no-image">No Image</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><strong>Variations:</strong> <?= htmlspecialchars($item['sizes']) ?></div>
                                        <div><strong>Add-ons:</strong> <?= htmlspecialchars($item['addons']) ?></div>
                                    </td>
                                    <td>
                                        <span class="price-tag">₱<?= htmlspecialchars($item['prices']) ?></span>
                                    </td>
                                    <td>
                                        <span
                                            class="availability-badge <?= $item['is_available'] ? 'available' : 'unavailable' ?>">
                                            <?= $item['is_available'] ? 'Available' : 'Unavailable' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-success btn-sm edit-btn" data-id="<?= $item['id'] ?>"
                                                data-name="<?= htmlspecialchars($item['name']) ?>"
                                                data-description="<?= htmlspecialchars($item['description']) ?>"
                                                data-sizes="<?= htmlspecialchars($item['sizes']) ?>"
                                                data-prices="<?= htmlspecialchars($item['prices']) ?>"
                                                data-addons="<?= htmlspecialchars($item['addons']) ?>"
                                                data-image="<?= htmlspecialchars($item['image_path']) ?>"
                                                data-available="<?= $item['is_available'] ?>">
                                                Edit
                                            </button>
                                            <a href="menu.php?delete_id=<?= $item['id'] ?>" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars($item['name']) ?>?')">
                                                Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <img src="/Food_System/assets/icons/no-order.jpg" alt="No Orders" style="width:150px; height: 120px;">
                                        <h3>No Menu Items Found</h3>
                                        <p>Get started by adding your first menu item!</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div id="addItemModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="closeAddModalBtn">&times;</button>
            <h2>Add New Menu Item</h2>
            <form method="POST" enctype="multipart/form-data" id="addItemForm">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label class="modal-label" for="name">Name</label>
                    <input type="text" class="modal-input" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label class="modal-label" for="description">Description</label>
                    <textarea class="modal-textarea" id="description" name="description"></textarea>
                </div>

                <!-- Variations and Prices Section -->
                <div class="form-group">
                    <div class="section-title">Variations & Prices</div>
                    <div class="help-text">Add different variations of this item (e.g., Spicy, Mild, With Rice, Without Rice, etc.)</div>
                    <div id="variations-container">
                        <div class="dynamic-field-group">
                            <div class="field-row">
                                <input type="text" class="modal-input" name="variation_name[]" placeholder="Variation (e.g., Spicy)" required>
                                <input type="number" class="modal-input" name="variation_price[]" placeholder="Price" step="0.01" min="0" required>
                                <button type="button" class="remove-btn" onclick="removeField(this)">Remove</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="add-more-btn" onclick="addVariationField()">+ Add Another Variation</button>
                </div>

                <!-- Addons Section -->
                <div class="form-group">
                    <div class="section-title">Add-ons</div>
                    <div class="help-text">Additional items that can be added to the order</div>
                    <div id="addons-container">
                        <div class="dynamic-field-group">
                            <div class="field-row">
                                <input type="text" class="modal-input" name="addon_name[]" placeholder="Add-on (e.g., Extra Cheese)">
                                <button type="button" class="remove-btn" onclick="removeField(this)">Remove</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="add-more-btn" onclick="addAddonField()">+ Add Another Add-on</button>
                </div>

                <div class="form-group">
                    <label class="modal-label" for="image">Image</label>
                    <input type="file" class="modal-file" id="image" name="image" accept="image/*" placeholder="Select">
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_available" name="is_available" checked>
                        <label class="modal-label" for="is_available">Available</label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="cancel-btn" id="cancelAddBtn">Cancel</button>
                    <button type="submit" class="submit-btn">Add Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div id="editItemModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="closeEditModalBtn">&times;</button>
            <h2>Edit Menu Item</h2>
            <form method="POST" enctype="multipart/form-data" id="editItemForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-group">
                    <label class="modal-label" for="edit_name">Name</label>
                    <input type="text" class="modal-input" id="edit_name" name="name" required>
                </div>

                <div class="form-group">
                    <label class="modal-label" for="edit_description">Description</label>
                    <textarea class="modal-textarea" id="edit_description" name="description"></textarea>
                </div>

                <!-- Variations and Prices Section -->
                <div class="form-group">
                    <div class="section-title">Variations & Prices</div>
                    <div class="help-text">Add different variations of this item (e.g., Spicy, Mild, With Rice, Without Rice, etc.)</div>
                    <div id="edit-variations-container">
                        <!-- Will be populated dynamically -->
                    </div>
                    <button type="button" class="add-more-btn" onclick="addEditVariationField()">+ Add Another Variation</button>
                </div>

                <!-- Addons Section -->
                <div class="form-group">
                    <div class="section-title">Add-ons</div>
                    <div class="help-text">Additional items that can be added to the order</div>
                    <div id="edit-addons-container">
                        <!-- Will be populated dynamically -->
                    </div>
                    <button type="button" class="add-more-btn" onclick="addEditAddonField()">+ Add Another Add-on</button>
                </div>

                <div class="form-group">
                    <label class="modal-label" for="edit_image">Image</label>
                    <div id="currentImageContainer"></div>
                    <input type="file" class="modal-file" id="edit_image" name="image" accept="image/*">
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="edit_is_available" name="is_available">
                        <label class="modal-label" for="edit_is_available">Available</label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="cancel-btn" id="cancelEditBtn">Cancel</button>
                    <button type="submit" class="submit-btn">Update Item</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal elements
        const addModal = document.getElementById('addItemModal');
        const editModal = document.getElementById('editItemModal');
        const openAddModalBtn = document.getElementById('openAddModalBtn');
        const closeAddModalBtn = document.getElementById('closeAddModalBtn');
        const closeEditModalBtn = document.getElementById('closeEditModalBtn');
        const cancelAddBtn = document.getElementById('cancelAddBtn');
        const cancelEditBtn = document.getElementById('cancelEditBtn');
        const addItemForm = document.getElementById('addItemForm');
        const editItemForm = document.getElementById('editItemForm');
        const alertMessage = document.getElementById('alertMessage');

        // Dynamic Field Functions
        function addVariationField() {
            const container = document.getElementById('variations-container');
            const newField = document.createElement('div');
            newField.className = 'dynamic-field-group';
            newField.innerHTML = `
                <div class="field-row">
                    <input type="text" class="modal-input" name="variation_name[]" placeholder="Variation (e.g., Mild)" required>
                    <input type="number" class="modal-input" name="variation_price[]" placeholder="Price" step="0.01" min="0" required>
                    <button type="button" class="remove-btn" onclick="removeField(this)">Remove</button>
                </div>
            `;
            container.appendChild(newField);
        }

        function addAddonField() {
            const container = document.getElementById('addons-container');
            const newField = document.createElement('div');
            newField.className = 'dynamic-field-group';
            newField.innerHTML = `
                <div class="field-row">
                    <input type="text" class="modal-input" name="addon_name[]" placeholder="Add-on (e.g., Extra Bacon)">
                    <button type="button" class="remove-btn" onclick="removeField(this)">Remove</button>
                </div>
            `;
            container.appendChild(newField);
        }

        function addEditVariationField() {
            const container = document.getElementById('edit-variations-container');
            const newField = document.createElement('div');
            newField.className = 'dynamic-field-group';
            newField.innerHTML = `
                <div class="field-row">
                    <input type="text" class="modal-input" name="variation_name[]" placeholder="Variation (e.g., Mild)" required>
                    <input type="number" class="modal-input" name="variation_price[]" placeholder="Price" step="0.01" min="0" required>
                    <button type="button" class="remove-btn" onclick="removeField(this)">Remove</button>
                </div>
            `;
            container.appendChild(newField);
        }

        function addEditAddonField() {
            const container = document.getElementById('edit-addons-container');
            const newField = document.createElement('div');
            newField.className = 'dynamic-field-group';
            newField.innerHTML = `
                <div class="field-row">
                    <input type="text" class="modal-input" name="addon_name[]" placeholder="Add-on (e.g., Extra Bacon)">
                    <button type="button" class="remove-btn" onclick="removeField(this)">Remove</button>
                </div>
            `;
            container.appendChild(newField);
        }

        function removeField(button) {
            const fieldGroup = button.closest('.dynamic-field-group');
            // Don't remove if it's the last field in its container
            const container = fieldGroup.parentElement;
            if (container.children.length > 1) {
                fieldGroup.remove();
            }
        }

        // Open Add Modal
        openAddModalBtn.addEventListener('click', openAddModal);

        function openAddModal() {
            addModal.style.display = 'flex';
            addItemForm.reset();
            
            // Reset dynamic fields to one each
            document.getElementById('variations-container').innerHTML = `
                <div class="dynamic-field-group">
                    <div class="field-row">
                        <input type="text" class="modal-input" name="variation_name[]" placeholder="Variation (e.g., Spicy)" required>
                        <input type="number" class="modal-input" name="variation_price[]" placeholder="Price" step="0.01" min="0" required>
                        <button type="button" class="remove-btn" onclick="removeField(this)">Remove</button>
                    </div>
                </div>
            `;
            
            document.getElementById('addons-container').innerHTML = `
                <div class="dynamic-field-group">
                    <div class="field-row">
                        <input type="text" class="modal-input" name="addon_name[]" placeholder="Add-on (e.g., Extra Cheese)">
                        <button type="button" class="remove-btn" onclick="removeField(this)">Remove</button>
                    </div>
                </div>
            `;
        }

        // Close Add Modal
        function closeAddModal() {
            addModal.style.display = 'none';
        }

        closeAddModalBtn.addEventListener('click', closeAddModal);
        cancelAddBtn.addEventListener('click', closeAddModal);

        // Close Edit Modal
        function closeEditModal() {
            editModal.style.display = 'none';
        }

        closeEditModalBtn.addEventListener('click', closeEditModal);
        cancelEditBtn.addEventListener('click', closeEditModal);

        // Close modals when clicking outside
        window.addEventListener('click', function (event) {
            if (event.target === addModal) {
                closeAddModal();
            }
            if (event.target === editModal) {
                closeEditModal();
            }
        });

        // Edit button click handlers
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const description = this.getAttribute('data-description');
                const variations = this.getAttribute('data-sizes'); // Still using 'sizes' attribute for variations
                const prices = this.getAttribute('data-prices');
                const addons = this.getAttribute('data-addons');
                const image = this.getAttribute('data-image');
                const available = this.getAttribute('data-available') === '1';

                // Populate edit form
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_is_available').checked = available;

                // Populate variations and prices
                const variationsContainer = document.getElementById('edit-variations-container');
                variationsContainer.innerHTML = '';
                
                if (variations && prices) {
                    const variationArray = variations.split(', ');
                    const priceArray = prices.split(', ');
                    
                    variationArray.forEach((variation, index) => {
                        if (variation.trim()) {
                            const price = priceArray[index] || '0';
                            const newField = document.createElement('div');
                            newField.className = 'dynamic-field-group';
                            newField.innerHTML = `
                                <div class="field-row">
                                    <input type="text" class="modal-input" name="variation_name[]" value="${variation.trim()}" required>
                                    <input type="number" class="modal-input" name="variation_price[]" value="${price.trim()}" step="0.01" min="0" required>
                                    <button type="button" class="remove-btn" onclick="removeField(this)">Remove</button>
                                </div>
                            `;
                            variationsContainer.appendChild(newField);
                        }
                    });
                } else {
                    // Add one empty field if no variations exist
                    addEditVariationField();
                }

                // Populate addons
                const addonsContainer = document.getElementById('edit-addons-container');
                addonsContainer.innerHTML = '';
                
                if (addons) {
                    const addonArray = addons.split(', ');
                    addonArray.forEach(addon => {
                        if (addon.trim()) {
                            const newField = document.createElement('div');
                            newField.className = 'dynamic-field-group';
                            newField.innerHTML = `
                                <div class="field-row">
                                    <input type="text" class="modal-input" name="addon_name[]" value="${addon.trim()}">
                                    <button type="button" class="remove-btn" onclick="removeField(this)">Remove</button>
                                </div>
                            `;
                            addonsContainer.appendChild(newField);
                        }
                    });
                } else {
                    // Add one empty field if no addons exist
                    addEditAddonField();
                }

                // Display current image
                const imageContainer = document.getElementById('currentImageContainer');
                if (image) {
                    imageContainer.innerHTML = `
                        <p><strong>Current Image:</strong></p>
                        <img src="/Food_System/${image}" alt="Current Image" class="current-image">
                    `;
                } else {
                    imageContainer.innerHTML = '<p class="no-image">No current image</p>';
                }

                // Show edit modal
                editModal.style.display = 'flex';
            });
        });

        // Show alert message
        function showAlert(message, type = 'success') {
            alertMessage.textContent = message;
            alertMessage.className = `alert alert-${type}`;
            alertMessage.style.display = 'block';

            setTimeout(() => {
                alertMessage.style.display = 'none';
            }, 5000);
        }

        // Form submission handlers
        addItemForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('menu.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        closeAddModal();
                        // Reload page to show new item
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('An error occurred while adding the item', 'error');
                });
        });

        editItemForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('menu.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        closeEditModal();
                        // Reload page to show updated item
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('An error occurred while updating the item', 'error');
                });
        });

        // Search functionality
        function searchMenu() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('menuTable');
            const tr = table.getElementsByClassName('menu-item');

            for (let i = 0; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td')[0];
                if (td) {
                    const txtValue = td.textContent || td.innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        tr[i].style.display = '';
                    } else {
                        tr[i].style.display = 'none';
                    }
                }
            }
        }

        // Add search event listener
        document.getElementById('searchInput').addEventListener('input', searchMenu);

        // Add hover effects to table rows
        document.addEventListener('DOMContentLoaded', function () {
            const rows = document.querySelectorAll('.menu-item');
            rows.forEach(row => {
                row.addEventListener('mouseenter', function () {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
                });
                row.addEventListener('mouseleave', function () {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });
        });
    </script>
</body>

</html>