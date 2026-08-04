<!DOCTYPE html>
<html lang="en">
<!-- kupal -->
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Arko Flavors - User Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet" />
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

  <style>
    * {
      box-sizing: border-box;
      font-family: "Poppins", sans-serif;
      margin: 0;
      padding: 0;
    }

    body {
      margin: 0;
    }

    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: 220px;
      height: 100vh;
      background-color: #222e3c;
      color: white;
      display: flex;
      flex-direction: column;
      box-shadow: 2px 0 12px rgba(0, 0, 0, 0.1);
      z-index: 1000;
    }

    .sidebar-logo {
      text-align: center;
      padding: 1.8rem 0;
      font-size: 1.2rem;
      font-weight: bold;
      color: #ff6b35;
      letter-spacing: 2px;
    }

    .sidebar-separator {
      border: none;
      border-bottom: 1px solid #444d5c;
      margin: 0 1.7rem 1rem 1.7rem;
      width: calc(100% - 3.4rem);
    }

    nav {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .sidebar-menu {
      list-style: none;
      width: 100%;
    }

    .sidebar-menu li {
      width: 100%;
    }

    .sidebar-menu a {
      display: flex;
      align-items: center;
      padding: 0.85rem 1.7rem;
      color: white;
      text-decoration: none;
      font-size: 12px;
      font-weight: 500;
      transition: 0.2s ease;
      border-radius: 8px;
      cursor: pointer;
    }

    .sidebar-menu a:hover {
      color: #ff6b35;
      transform: translateX(2px) scale(1.03);
    }

    .sidebar-menu a.active {
      color: #ff6b35;
      font-weight: 600;
      transform: scale(1.05);
    }

    .icon {
      width: 16px;
      height: 16px;
      margin-right: 10px;
      stroke-width: 1.5px;
    }

    /* Logout button styles */
    .logout-container {
      padding: 1rem 0;
      margin-top: auto;
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
      margin-bottom: 10px;
      border-radius: 8px;
    }

    .logout-btn:hover {
      color: red;
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
      z-index: 2000;
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
      .logout-btn {
        justify-content: center;
        font-size: 0;
        padding: 0.5rem;
      }

      .icon {
        margin: 0;
      }

      .sidebar-menu span,
      .logout-btn span {
        display: none;
      }
    }
  </style>
</head>

<body>
  <div class="sidebar">
    <div class="sidebar-logo"><span style="color: white;">Arko</span> Flavors</div>
    <hr class="sidebar-separator" />

    <nav>
      <ul class="sidebar-menu">
        <li>
          <a href="/food-system/food-system-php/user%20dashboard/userDashboard.php">
            <i data-lucide="layout-dashboard" class="icon"></i>
            <span>Dashboard</span>
          </a>
        </li>

        <li>
          <a href="/food-system/food-system-php/user%20dashboard/menu.php">
            <i data-lucide="utensils" class="icon"></i>
            <span>Menu</span>
          </a>
        </li>

        <li>
          <a href="/food-system/food-system-php/user%20dashboard/cart.php">
            <i data-lucide="shopping-cart" class="icon"></i>
            <span>Cart</span>
          </a>
        </li>

        <li>
          <a href="/food-system/food-system-php/user%20dashboard/orderStatus.php">
            <i data-lucide="box" class="icon"></i>
            <span>My Orders</span>
          </a>
        </li>

        <li>
          <a href="/food-system/food-system-php/user%20dashboard/profile.php">
            <i data-lucide="user" class="icon"></i>
            <span>Profile</span>
          </a>
        </li>
      </ul>
    </nav>

    <div class="logout-container">
      <button class="logout-btn" id="logoutBtn">
        <i data-lucide="log-out" class="icon"></i>
        <span>Logout</span>
      </button>
    </div>
  </div>

  <!-- Logout Confirmation Modal -->
  <div class="modal-overlay" id="logoutModal">
    <div class="modal">
      <div class="modal-header">
        <i data-lucide="alert-triangle" class="modal-icon"></i>
        <h3>Confirm Logout</h3>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to log out of your account?</p>
      </div>
      <div class="modal-footer">
        <button class="modal-btn modal-btn-cancel" id="cancelLogout">Cancel</button>
        <button class="modal-btn modal-btn-confirm" id="confirmLogout">Logout</button>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      // Lucide Icons
      lucide.createIcons();

      // Highlight active link based on current URL path
      const currentPath = window.location.pathname;
      document.querySelectorAll('.sidebar-menu a').forEach(link => {
        if (link.getAttribute('href') === currentPath) {
          link.classList.add('active');
        }
      });

      // Logout Modal Functionality
      const logoutBtn = document.getElementById('logoutBtn');
      const logoutModal = document.getElementById('logoutModal');
      const cancelLogout = document.getElementById('cancelLogout');
      const confirmLogout = document.getElementById('confirmLogout');

      logoutBtn.addEventListener('click', () => {
        logoutModal.classList.add('active');
      });

      cancelLogout.addEventListener('click', () => {
        logoutModal.classList.remove('active');
      });

      confirmLogout.addEventListener('click', () => {
        window.location.href = '/Food_System/user%20dashboard/logout.php';
      });

      // Close modal when clicking outside
      logoutModal.addEventListener('click', (e) => {
        if (e.target === logoutModal) {
          logoutModal.classList.remove('active');
        }
      });
    });
  </script>
</body>

</html>
