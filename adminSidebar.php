<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Arko Flavours - Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="/Food_System/content%20design/sidebar.css" />

  <!-- AOS Animations -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

  <!-- Lucide Icoms -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

  <style>
    /* Core styles */
    * {
      box-sizing: border-box;
      font-family: "Poppins", sans-serif;
      margin: 0;
      padding: 0;
    }

    body {
      margin: 0;
      background: #f5f7fa;
      min-height: 100vh;
    }

    /* Sidebar styles */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: 220px;
      height: 100vh;
      background: #222e3c;
      color: white;
      display: flex;
      flex-direction: column;
      box-shadow: 2px 0 12px rgba(0, 0, 0, 0.1);
      z-index: 1000;
    }

    /* Logo styles - Fixed at the very top */
    .sidebar-logo {
      font-size: 1.2rem;
      font-weight: 600;
      padding: 1rem .2rem;
      text-align: center;
      color: #ff6b35;
      background: linear-gradient(180deg, rgba(255, 107, 53, 0.1) 0%, transparent 100%);
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      margin-bottom: 0;
    }

    .sidebar-logo span {
      color: #fff;
    }

    /* Fix image display in dropdowns */
    .sidebar img {
      width: 18px;
      height: 18px;
      margin-right: 12px;
      object-fit: contain;
    }

    /* Ensure icons align properly */
    .icon {
      width: 18px;
      height: 18px;
      margin-right: 12px;
      stroke-width: 2px;
      flex-shrink: 0;
    }

    /* Menu styles */
    .sidebar-menu {
      list-style: none;
      width: 100%;
      padding: 0;
      margin: 0;
    }

    .sidebar-menu li {
      width: 100%;
      margin-bottom: 0.3rem;
    }

    .sidebar-menu a,
    .dropdown-toggle {
      display: flex;
      align-items: center;
      padding: 0.85rem 1.5rem;
      color: white;
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 500;
      transition: all 0.2s ease;
      border-radius: 8px;
      margin: 0 0.5rem;
      cursor: pointer;
    }

    .dropdown-toggle {
      justify-content: space-between;
    }

    .dropdown-toggle>div {
      display: flex;
      align-items: center;
    }

    .sidebar-menu a:hover,
    .dropdown-toggle:hover {
      background: rgba(255, 107, 53, 0.1);
      color: #ff6b35;
    }

    .sidebar-menu a.active {
      background: #ff6b35;
      color: white;
      font-weight: 600;
    }

    /* Dropdown styles */
    .dropdown-menu {
      display: none;
      padding: 0.5rem 0 0.5rem 1.5rem;
    }

    .dropdown.open .dropdown-menu {
      display: flex;
      flex-direction: column;
    }

    .dropdown-menu a,
    .nested-dropdown-toggle {
      font-size: 0.85rem;
      padding: 0.7rem 1.5rem;
      opacity: 0.9;
    }

    /* Nested dropdown styles */
    .nested-dropdown-menu {
      display: none;
      padding-left: 1.5rem;
    }

    .nested-dropdown.open .nested-dropdown-menu {
      display: flex;
      flex-direction: column;
    }

    .arrow {
      font-size: 10px;
      transition: transform 0.2s ease;
      margin-left: 8px;
    }

    .dropdown.open>.dropdown-toggle .arrow,
    .nested-dropdown.open>.nested-dropdown-toggle .arrow {
      transform: rotate(180deg);
    }

    nav {
      width: 100%;
      flex: 1;
      overflow-y: auto;
      padding-top: 0.5rem;
    }

    .sidebar-menu {
      list-style: none;
      padding: 0;
      margin: 0;
      width: 100%;
    }

    .sidebar-menu li {
      width: 100%;
      margin-bottom: 0.5rem;
    }

    .sidebar-menu a,
    .dropdown-toggle {
      display: flex;
      align-items: center;
      padding: 0.85rem 1.7rem;
      color: white;
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 500;
      transition: all 0.2s ease;
      border-radius: 8px;
      margin: 0 0.5rem;
    }

    .sidebar-menu a:hover,
    .dropdown-toggle:hover {
      background: rgba(255, 107, 53, 0.1);
      color: #ff6b35;
      transform: translateX(4px);
    }

    .sidebar-menu a.active {
      background: #ff6b35;
      color: white;
      font-weight: 600;
      box-shadow: 0 2px 8px rgba(255, 107, 53, 0.2);
    }

    .icon {
      width: 18px;
      height: 18px;
      margin-right: 12px;
      stroke-width: 2px;
    }

    /* Dropdown menu styles */
    .dropdown-toggle {
      display: flex;
      align-items: center;
      justify-content: space-between;
      cursor: pointer;
    }

    .dropdown-menu {
      display: none;
      padding-left: 1.5rem;
      margin-top: 0.2rem;
    }

    .dropdown.open .dropdown-menu {
      display: flex;
      flex-direction: column;
    }

    .dropdown-menu a {
      font-size: 0.85rem;
      padding: 0.7rem 1.7rem;
    }

    .arrow {
      font-size: 10px;
      transition: transform 0.2s ease;
      margin-left: 8px;
    }

    .dropdown.open .arrow {
      transform: rotate(180deg);
    }

    /* Nested dropdown styles */
    .nested-dropdown {
      position: relative;
    }

    .nested-dropdown-toggle {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.7rem 1.7rem;
      font-size: 0.85rem;
      font-weight: 500;
      color: white;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .nested-dropdown-toggle:hover {
      color: #ff6b35;
    }

    .nested-dropdown.open .nested-dropdown-toggle {
      color: #ff6b35;
    }

    .nested-dropdown-menu {
      display: none;
      padding-left: 1.5rem;
    }

    .nested-dropdown.open .nested-dropdown-menu {
      display: flex;
      flex-direction: column;
    }

    /* Logout button styles */
    .logout-container {
      margin-top: auto;
      padding: 1rem;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .logout-btn {
      width: 100%;
      padding: 0.85rem 1.7rem;
      display: flex;
      align-items: center;
      justify-content: center;
      background: transparent;
      border: none;
      color: #ff4b4b;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      border-radius: 8px;
      margin: 0 0.5rem;
    }

    .logout-btn:hover {
      background: rgba(255, 75, 75, 0.1);
      color: #ff3333;
    }

    /* Section label styles */
    .section-label {
      padding: 0.5rem 1.7rem;
      color: #94a3b8;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-top: 1rem;
    }

    /* Modal Styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      /* Higher z-index to ensure it's on top of everything */
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }

    .modal-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .modal {
      background-color: white;
      border-radius: 8px;
      width: 90%;
      max-width: 400px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
      transform: translateY(-20px);
      transition: transform 0.3s ease;
    }

    .modal-overlay.active .modal {
      transform: translateY(0);
    }

    .modal-header {
      padding: 1.5rem;
      border-bottom: 1px solid #eee;
      display: flex;
      align-items: center;
    }

    .modal-header h3 {
      margin: 0;
      color: #222e3c;
      font-size: 1.2rem;
    }

    .modal-icon {
      width: 24px;
      height: 24px;
      margin-right: 10px;
      color: #ff6b35;
    }

    .modal-body {
      padding: 1.5rem;
      color: #555;
    }

    .modal-footer {
      padding: 1rem 1.5rem;
      border-top: 1px solid #eee;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }

    .modal-btn {
      padding: 0.5rem 1.5rem;
      border: none;
      border-radius: 4px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .modal-btn-cancel {
      background-color: #f0f0f0;
      color: #333;
    }

    .modal-btn-cancel:hover {
      background-color: #e0e0e0;
    }

    .modal-btn-confirm {
      background-color: #ff6b35;
      color: white;
    }

    .modal-btn-confirm:hover {
      background-color: #e55a2b;
    }

    /* Responsive styles */
    @media (max-width: 900px) {
      .main-content {
        margin-left: 60px;
      }

      .sidebar {
        width: 60px;
      }

      .sidebar-logo {
        font-size: 0;
        padding: 1rem;
        margin: 0;
      }

      .sidebar-menu a,
      .dropdown-toggle {
        padding: 0.85rem;
        justify-content: center;
      }

      .sidebar-menu span,
      .dropdown-toggle span,
      .arrow {
        display: none;
      }

      .icon {
        margin: 0;
      }

      /* Hide section labels on mobile */
      .section-label {
        display: none;
      }

      .dropdown-menu,
      .nested-dropdown-menu {
        position: absolute;
        left: 100%;
        top: 0;
        min-width: 200px;
        background: #222e3c;
        border-radius: 0 8px 8px 0;
        box-shadow: 2px 0 12px rgba(0, 0, 0, 0.15);
        padding: 0.5rem;
        margin: 0;
      }

      .logout-btn {
        padding: 0.85rem;
      }

      .logout-btn span {
        display: none;
      }
    }

    .sidebar-menu {
      list-style: none;
      width: 100%;
    }

    .sidebar-menu li {
      width: 100%;
    }

    .sidebar-menu a,
    .dropdown-toggle {
      display: flex;
      align-items: center;
      padding: 0.85rem 1.7rem;
      color: white;
      text-decoration: none;
      font-size: 12px;
      font-weight: 500;
      transition: 0.2s ease;
      border-radius: 8px;
    }

    .sidebar-menu a:hover,
    .dropdown-toggle:hover {
      color: #ff6b35;
    }

    .sidebar-menu a.active {
      color: #ff6b35;
      font-weight: 600;
    }

    .sidebar-menu li.active {
      border-left: 3px solid #ff6b35;
    }

    .icon {
      width: 16px;
      height: 16px;
      margin-right: 10px;
      stroke-width: 1.5px;
    }

    .dropdown {
      width: 100%;
    }

    .dropdown-toggle {
      justify-content: space-between;
      cursor: pointer;
    }

    .arrow {
      font-size: 10px;
      margin-left: 8px;
      transition: transform 0.2s ease;
    }

    .dropdown.open .arrow {
      transform: rotate(180deg);
    }

    .dropdown-menu {
      display: none;
      flex-direction: column;
      padding-left: 1.5rem;
      margin-top: 0.5rem;
    }

    .dropdown.open .dropdown-menu {
      display: flex;
    }

    .dropdown-menu a {
      padding: 0.5rem 1.7rem;
      font-size: 11px;
      font-weight: 400;
      color: white;
    }

    .dropdown-menu a:hover,
    .dropdown-menu a.active {
      color: #ff6b35;
      font-weight: 600;
    }

    /* Nested dropdown styles */
    .nested-dropdown {
      position: relative;
    }

    .nested-dropdown-toggle {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.5rem 1.7rem;
      font-size: 11px;
      font-weight: 400;
      color: white;
      cursor: pointer;
      transition: color 0.2s ease;
    }

    .nested-dropdown-toggle:hover {
      color: #ff6b35;
    }

    .nested-dropdown.open .nested-dropdown-toggle {
      color: #ff6b35;
      font-weight: 600;
    }

    .nested-dropdown-menu {
      display: none;
      flex-direction: column;
      padding-left: 1rem;
      margin-top: 0.25rem;
    }

    .nested-dropdown.open .nested-dropdown-menu {
      display: flex;
    }

    .nested-dropdown-menu a {
      padding: 0.4rem 1.7rem;
      font-size: 10px;
    }

    .logout-container {
      padding: 1rem 0;
    }

    .logout-btn {
      font-size: 11px;
      font-weight: 600;
      width: 100%;
      border: none;
      background: transparent;
      color: white;
      padding: 0.5rem 1.7rem;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: color 0.2s ease;
    }

    .main-content {
      margin-left: 220px;
      padding: 2rem;
      min-height: 100vh;
      background-color: #f5f7fa;
    }

    @media (max-width: 900px) {
      .main-content {
        margin-left: 60px;
      }
    }

    @media (max-width: 900px) {
      .sidebar {
        width: 60px;
        align-items: center;
      }

      .sidebar-logo {
        font-size: 0;
        padding: 1rem 0;
      }

      .sidebar-menu a,
      .dropdown-toggle,
      .dropdown-menu a,
      .logout-btn {
        justify-content: center;
        font-size: 0;
        padding: 0.5rem;
      }

      .icon {
        margin: 0;
      }

      .arrow,
      .sidebar-menu span {
        display: none;
      }

      /* Hide nested dropdown text on mobile */
      .nested-dropdown-toggle span {
        display: none;
      }
    }

    /* Active link color fix */
    .sidebar-menu a.active,
    .dropdown-menu a.active,
    .nested-dropdown-menu a.active {
      background: rgba(255, 107, 53, 0.1);
      color: #ff6b35;
      font-weight: 600;
    }
  </style>
</head>

<body>
  <div class="sidebar">
    <div class="sidebar-logo"><span>Arko</span>Flavours <p style="margin-top: 20px; font-size: small; ">Administrator
      </p>
    </div>

    <nav>
      <ul class="sidebar-menu">
        <li>
          <a href="/food-system/food-system-php/admin dashboard/adminDashboard.php">
            <i data-lucide="layout-dashboard" class="icon"></i>
            <span>Dashboard</span>
          </a>
        </li>
        <li>
          <a href="/food-system/food-system-php/admin dashboard/menu/inventory.php">
            <i data-lucide="box" class="icon"></i>
            <span>Inventory</span>
          </a>
        </li>

        <li>
          <a href="/food-system/food-system-php/admin dashboard/menu/menu.php">
            <i data-lucide="utensils" class="icon"></i>
            <span>Products</span>
          </a>
        </li>

        <!-- Orders Dropdown -->
        <li class="dropdown">
          <div class="dropdown-toggle">
            <div style="display: flex; align-items: center;">
              <i data-lucide="shopping-bag" class="icon"></i>
              <span>Manage Orders</span>
            </div>
            <span class="arrow">&#9662;</span>
          </div>
          <div class="dropdown-menu">
            <!-- Unified Orders Management -->
            <a href="/food-system/food-system-php/admin dashboard/managing orders/manageOrders.php?tab=pending">
              <i data-lucide="clock" class="icon"></i> All Orders
            </a>
            <!-- Legacy links for backward compatibility -->
            <a href="/food-system/food-system-php/admin dashboard/managing orders/manageOrders.php?tab=pending">
              <i data-lucide="clock" class="icon"></i> Pending
            </a>
            <a href="/food-system/food-system-php/admin dashboard/managing orders/manageOrders.php?tab=accepted">
              <i data-lucide="check-circle" class="icon"></i> Accepted
            </a>
            <a href="/food-system/food-system-php/admin dashboard/managing orders/manageOrders.php?tab=completed">
              <i data-lucide="check-square" class="icon"></i> Completed
            </a>
          </div>
        </li>

        <li>
          <a href="/food-system/food-system-php/admin dashboard/managing orders/payment_invoices.php" class="sidebar-link">
            Payment Proofs [
            <?php
            // Show count of pending payment verifications
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM accepted_orders WHERE payment_screenshot IS NOT NULL AND payment_screenshot != ''");
            $stmt->execute();
            $payment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            if ($payment_count > 0): ?>
              <span class="sidebar-badge"><?= $payment_count ?></span>
              <?php endif; ?>]
          </a>
        </li>

        <!-- Accounting Section Label -->
        <div class="section-label">Accounting</div>

        <li>
          <a href="/food-system/food-system-php/admin dashboard/analytics/sales.php">
            <i data-lucide="trending-up" class="icon"></i>
            <span>Sales</span>
          </a>
        </li>

        <li>
          <a href="/food-system/food-system-php/admin dashboard/analytics/expenses.php">
            <i data-lucide="trending-down" class="icon"></i>
            <span>Expenses</span>
          </a>
        </li>

        <!-- Reports Dropdown -->
        <li class="dropdown">
          <div class="dropdown-toggle">
            <div style="display: flex; align-items: center;">
              <i data-lucide="bar-chart-3" class="icon"></i>
              <span>Reports</span>
            </div>
            <span class="arrow">&#9662;</span>
          </div>
          <div class="dropdown-menu">
            <a href="/food-system/food-system-php/admin dashboard/reports/balance_sheet.php">
              <i data-lucide="credit-card" class="icon"></i> Balance Sheet
            </a>

            <a href="/food-system/food-system-php/admin dashboard/reports/cashflow.php">
              <i data-lucide="banknote" class="icon"></i> Cash Flow
            </a>

            <a href="/food-system/food-system-php/admin dashboard/reports/income_statement.php">
              <i data-lucide="file-text" class="icon"></i> Income Statement
            </a>

          </div>
        </li>

        <li>
          <a href="/food-system/food-system-php/admin dashboard/combined_reports.php">
            <i data-lucide="trending-up" class="icon"></i>
            <span>Summary</span>
          </a>
        </li>

        <!-- Settings Tab -->
        <li>
          <a href="/food-system/food-system-php/admin dashboard/settings/settings.php">
            <i data-lucide="settings" class="icon"></i>
            <span>Settings</span>
          </a>
        </li>
      </ul>
    </nav>

    <!-- Logout Button at Bottom -->
    <div class="logout-container">
      <button type="button" class="logout-btn" id="logoutBtn">
        <i data-lucide="log-out" class="icon"></i>
        <span>Logout</span>
      </button>
    </div>
  </div>

  <!-- Logout Confirmation Modal - Now placed outside sidebar -->
  <div class="modal-overlay" id="logoutModal">
    <div class="modal">
      <div class="modal-header">
        <i data-lucide="alert-triangle" class="modal-icon"></i>
        <h3>Confirm Logout</h3>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to log out of your admin account?</p>
      </div>
      <div class="modal-footer">
        <button class="modal-btn modal-btn-cancel" id="cancelLogout">Cancel</button>
        <form action="/Food_System/user dashboard/logout.php" method="post" style="display: inline;">
          <button type="submit" class="modal-btn modal-btn-confirm" id="confirmLogout">Logout</button>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Initialize AOS animations
    AOS.init();

    // Handle dropdown toggle clicks (no auto-closing of others)
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
      toggle.addEventListener('click', function(event) {
        event.stopPropagation();
        this.closest('.dropdown').classList.toggle('open');
      });
    });

    // Function to activate a link and open its parents
    function activateLink(link) {
      // Clear existing active states
      document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));

      // Activate clicked link
      link.classList.add('active');

      // Open parent dropdowns if necessary
      const dropdown = link.closest('.dropdown');
      if (dropdown) dropdown.classList.add('open');

      const nested = link.closest('.nested-dropdown');
      if (nested) nested.classList.add('open');
    }

    // Handle manual clicking on sidebar links
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
      link.addEventListener('click', function() {
        activateLink(this);
        localStorage.setItem('activeLink', this.getAttribute('href'));
      });
    });

    // On page load, restore the active link from localStorage or based on current URL
    document.addEventListener('DOMContentLoaded', function() {
      const savedPath = localStorage.getItem('activeLink');
      const currentPath = window.location.pathname;

      let activeLink = null;

      // Try to match by localStorage first, fallback to current URL
      document.querySelectorAll('.sidebar-menu a').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPath || href === savedPath) {
          activeLink = link;
        }
      });

      if (activeLink) {
        activateLink(activeLink);
      } else {
        // If none matched exactly, check for partial matches (folders)
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
          const href = link.getAttribute('href');
          if (!activeLink && href && currentPath.includes(href.split('/').pop())) {
            activeLink = link;
          }
        });
        if (activeLink) activateLink(activeLink);
      }
    });

    // Keep dropdown open if it contains an active link (useful on reload)
    document.querySelectorAll('.dropdown').forEach(dropdown => {
      if (dropdown.querySelector('a.active')) {
        dropdown.classList.add('open');
      }
    });

    // Close all dropdowns when clicking outside sidebar
    document.addEventListener('click', function(event) {
      if (!event.target.closest('.sidebar')) {
        document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('open'));
      }
    });

    // Logout Modal Functionality
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutModal = document.getElementById('logoutModal');
    const cancelLogout = document.getElementById('cancelLogout');

    logoutBtn.addEventListener('click', () => {
      logoutModal.classList.add('active');
    });

    cancelLogout.addEventListener('click', () => {
      logoutModal.classList.remove('active');
    });

    // Close modal when clicking outside
    logoutModal.addEventListener('click', (e) => {
      if (e.target === logoutModal) {
        logoutModal.classList.remove('active');
      }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && logoutModal.classList.contains('active')) {
        logoutModal.classList.remove('active');
      }
    });
  </script>
</body>

</html>