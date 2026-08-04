<?php
require_once 'includes/config_session.inc.php';
require_once 'includes/signup_view.inc.php';
require_once 'includes/login_view.inc.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGN UP - ARKO</title>
    <link rel="stylesheet" href="./design/reset.css">
    <link rel="stylesheet" href="./design/signup.css"> <!-- Link to the new signup CSS -->
    <link rel="stylesheet" href="./design/button.css">
    <link rel="stylesheet" href="./design/loading.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <form action="includes/signup.inc.php" method="post" class="form_container">
        <div class="logo_container">
            <img src="/Food_System/Log-in Form/design/images/arko.jpg" alt="ARKO Logo" />
        </div>
        
        <div class="title_container">
            <p class="title">Create an Account</p>
            <h1><span class="subtitle">Join us and start using our system.</span></h1>
        </div>

        <?php
        // Display signup errors
        if (isset($_SESSION["errors_signup"])) {
            echo '<div class="error-container">';
            foreach ($_SESSION["errors_signup"] as $error) {
                echo "<p class='error-message'>$error</p>";
            }
            echo '</div>';
            unset($_SESSION["errors_signup"]);
        }
        ?>

        <!-- Signup Inputs -->
        <div class="input_container">
            <label class="input_label" for="username_field">Username</label>
                <path stroke-linejoin="round" stroke-linecap="round" stroke-width="1.5" stroke="#141B34"
                    d="M12 12c2.21 0 4-1.79 4-4S14.21 4 12 4 8 5.79 8 8s1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z">
                </path>
            </svg>
            <input placeholder="Enter your username" name="username" type="text" class="input_field" id="username_field" required>
        </div>

        <div class="input_container">
            <label class="input_label" for="email_field">Email</label>
                <path stroke-linejoin="round" stroke-linecap="round" stroke-width="1.5" stroke="#141B34"
                    d="M7 8.5L9.94202 10.2394C11.6572 11.2535 12.3428 11.2535 14.058 10.2394L17 8.5"></path>
                <path stroke-linejoin="round" stroke-width="1.5" stroke="#141B34"
                    d="M2.01577 13.4756C2.08114 16.5412 2.11383 18.0739 3.24496 19.2094C4.37608 20.3448 5.95033 20.3843 9.09883 20.4634C11.0393 20.5122 12.9607 20.5122 14.9012 20.4634C18.0497 20.3843 19.6239 20.3448 20.7551 19.2094C21.8862 18.0739 21.9189 16.5412 21.9842 13.4756C22.0053 12.4899 22.0053 11.5101 21.9842 10.5244C21.9189 7.45886 21.8862 5.92609 20.7551 4.79066C19.6239 3.65523 18.0497 3.61568 14.9012 3.53657C12.9607 3.48781 11.0393 3.48781 9.09882 3.53656C5.95033 3.61566 4.37608 3.65521 3.24495 4.79065C2.11382 5.92608 2.08114 7.45885 2.01576 10.5244C1.99474 11.5101 1.99475 12.4899 2.01577 13.4756Z">
                </path>
            </svg>
            <input placeholder="Enter your email" name="email" type="email" class="input_field" id="email_field" required>
        </div>

        <div class="input_container">
            <label class="input_label" for="password_field">Password</label>
                <path stroke-linecap="round" stroke-width="1.5" stroke="#141B34"
                    d="M18 11.0041C17.4166 9.91704 16.273 9.15775 14.9519 9.0993C13.477 9.03404 11.9788 9 10.329 9C8.67911 9 7.18091 9.03404 5.70604 9.0993C3.95328 9.17685 2.51295 10.4881 2.27882 12.1618C2.12602 13.2541 2 14.3734 2 15.5134C2 16.6534 2.12602 17.7727 2.27882 18.865C2.51295 20.5387 3.95328 21.8499 5.70604 21.9275C6.42013 21.9591 7.26041 21.9834 8 22">
                </path>
                <path stroke-linejoin="round" stroke-linecap="round" stroke-width="1.5" stroke="#141B34"
                    d="M6 9V6.5C6 4.01472 8.01472 2 10.5 2C12.9853 2 15 4.01472 15 6.5V9"></path>
            </svg>
            <input placeholder="Enter your password" name="pwd" type="password" class="input_field" id="password_field" required>
        </div>

        <div class="input_container">
            <label class="input_label" for="confirm_field">Confirm Password</label>
                <path stroke-linecap="round" stroke-width="1.5" stroke="#141B34"
                    d="M18 11.0041C17.4166 9.91704 16.273 9.15775 14.9519 9.0993C13.477 9.03404 11.9788 9 10.329 9C8.67911 9 7.18091 9.03404 5.70604 9.0993C3.95328 9.17685 2.51295 10.4881 2.27882 12.1618C2.12602 13.2541 2 14.3734 2 15.5134C2 16.6534 2.12602 17.7727 2.27882 18.865C2.51295 20.5387 3.95328 21.8499 5.70604 21.9275C6.42013 21.9591 7.26041 21.9834 8 22">
                </path>
                <path stroke-linejoin="round" stroke-linecap="round" stroke-width="1.5" stroke="#141B34"
                    d="M6 9V6.5C6 4.01472 8.01472 2 10.5 2C12.9853 2 15 4.01472 15 6.5V9"></path>
            </svg>
            <input placeholder="Confirm your password" name="pwdrepeat" type="password" class="input_field" id="confirm_field" required>
        </div>

        <?php require './design/button.php'; ?>

        <div class="signup-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>

        <?php
        check_signup_errors();
        ?>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputFields = document.querySelectorAll('.input_field');
            
            inputFields.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });

                input.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        this.style.borderColor = '#4CAF50';
                    } else {
                        this.style.borderColor = '#e5e5e5';
                    }
                });
            });

            const form = document.querySelector('form');
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = 'Creating Account...';
                    submitBtn.disabled = true;
                }
            });
        });
    </script>

</body>
</html>