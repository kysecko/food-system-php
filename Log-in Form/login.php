<?php
require_once 'includes/config_session.inc.php';
require_once 'includes/signup_view.inc.php';
require_once 'includes/login_view.inc.php';
// require_once 'includes/google-config.php';
// require_once 'includes/facebook-config.php';

// Build Google OAuth2 authorization URL
$googleAuthParams = [
    // 'client_id' => GOOGLE_CLIENT_ID,
    // 'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'offline',
    'prompt' => 'select_account'
];
$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($googleAuthParams);

// Build Facebook OAuth2 authorization URL
$facebookAuthParams = [
    // 'client_id' => FACEBOOK_APP_ID,
    // 'redirect_uri' => FACEBOOK_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email public_profile'
];
$facebookAuthUrl = 'https://www.facebook.com/v12.0/dialog/oauth?' . http_build_query($facebookAuthParams);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login to Your Account</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" href="./design/reset.css" />
    
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    
    <link rel="stylesheet" href="./design/login.css"/>

    <link rel="stylesheet" href="./design/button.css"/>

</head>

<body>
    <?php if (!isset($_SESSION["user_id"])): ?>
        <div class="login-main-wrapper">
            <!-- Form Section -->
            <form action="includes/login.inc.php" method="post" class="form_container">
                <div class="logo_container">
                    <img src="./design/images/arko.jpg" alt="ARKO Logo" />
                </div>

                <div class="title_container">
                    <p class="title">Login to your Account</p>
                    <p class="subtitle">Get started with our app by logging in to continue.</p>
                </div>

                <?php
                // Display error messages
                if (isset($_SESSION["errors_login"])) {
                    echo '<div class="error-container">';
                    foreach ($_SESSION["errors_login"] as $error) {
                        echo "<p class='error-message'>$error</p>";
                    }
                    echo '</div>';
                    unset($_SESSION["errors_login"]);
                }

                // Display success messages (like after signup)
                if (isset($_SESSION["success_message"])) {
                    echo '<div class="success-container">';
                    echo "<p class='success-message'>{$_SESSION['success_message']}</p>";
                    echo '</div>';
                    unset($_SESSION["success_message"]);
                }
                ?>

                <div class="input_container">
                    <label class="input_label" for="username_field">Username</label>
                    <input 
                        placeholder="Enter your username" 
                        name="username" 
                        type="text" 
                        class="input_field" 
                        id="username_field" 
                        value="<?php echo isset($_SESSION['login_username']) ? htmlspecialchars($_SESSION['login_username']) : ''; ?>"
                        required 
                    />
                </div>

                <div class="input_container">
                    <label class="input_label" for="password_field">Password</label>
                    <input 
                        placeholder="Enter your password" 
                        name="pwd" 
                        type="password" 
                        class="input_field" 
                        id="password_field" 
                        required 
                    />
                    <div class="forgot-password">
                        <a href="forgot-password.php">Forgot Password?</a>
                    </div>
                </div>

                <?php require './design/button.php'; ?>

                <div class="separator">Or continue with</div>

                <div class="oauth-buttons">
                    <a href="<?php echo htmlspecialchars($googleAuthUrl); ?>" class="oauth-btn">
                        <img src="./design/images/googlelogo.png" alt="Google Logo" />
                        Sign-in with Google
                    </a>
                    
                    <a href="<?php echo htmlspecialchars($facebookAuthUrl); ?>" class="oauth-btn">
                        <img src="./design/images/fblogo.png" alt="Facebook Logo" />
                        Sign-in with Facebook
                    </a>
                </div>

                <div class="signup-link">
                    Don't have an account? <a href="signup.php"> Sign up here</a>
                </div>
            </form>

            <!-- Carousel Section -->
            <div class="carousel-container">
                <div class="carousel">
                    <div class="carousel-inner">
                        <div class="carousel-item">
                            <img src="./design/images/arko.jpg" alt="Welcome to ARKO">
                            <div class="carousel-caption">
                                <h3>Welcome to ARKO!</h3>
                                <p>A soon to be one of Pagsanjeños new food delicacy.</p>
                            </div>
                        </div>

                        <div class="carousel-item">
                            <img src="./design/images/arko1.jpg" alt="Longanisa with ingredients">
                        </div>

                        <div class="carousel-item">
                            <img src="./design/images/arko2.jpg" alt="Longganisa">
                        </div>

                        <div class="carousel-item">
                            <img src="./design/images/arko3.jpg" alt="Store">
                        </div>
                    </div>
                    
                    <!-- Carousel Indicators -->
                    <div class="carousel-indicators">
                        <button class="indicator active" data-slide="0"></button>
                        <button class="indicator" data-slide="1"></button>
                        <button class="indicator" data-slide="2"></button>
                        <button class="indicator" data-slide="3"></button>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- User is already logged in -->
        <div class="already-logged-in">
            <div class="form_container">
                <div class="logo_container">
                    <img src="./design/images/arko.jpg" alt="ARKO Logo" />
                </div>
                <div class="title_container">
                    <p class="title">Welcome Back!</p>
                    <p class="subtitle">You are already logged in.</p>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="../admin dashboard/adminDashboard.php" class="sign-in_btn" style="text-decoration: none; display: inline-block;">Go to Dashboard</a>
                    <br><br>
                    <a href="includes/logout.inc.php" style="color: #115DFC; text-decoration: none;">Logout</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Enhanced Carousel with Cross-Browser Compatibility
        document.addEventListener('DOMContentLoaded', function() {
            const indicators = document.querySelectorAll('.indicator');
            const carouselInner = document.querySelector('.carousel-inner');
            let currentSlide = 0;
            const totalSlides = 4;
            let carouselInterval;

            function updateCarousel() {
                if (carouselInner) {
                    // Use transform for smooth sliding (works in all browsers)
                    carouselInner.style.transform = 'translateX(-' + (currentSlide * 25) + '%)';
                    
                    // Update indicators
                    indicators.forEach((indicator, index) => {
                        if (index === currentSlide) {
                            indicator.classList.add('active');
                        } else {
                            indicator.classList.remove('active');
                        }
                    });
                }
            }

            function goToSlide(slideIndex) {
                currentSlide = slideIndex;
                updateCarousel();
                resetCarouselInterval();
            }

            function resetCarouselInterval() {
                if (carouselInterval) {
                    clearInterval(carouselInterval);
                }
                carouselInterval = setInterval(nextSlide, 5000);
            }

            function nextSlide() {
                currentSlide = (currentSlide + 1) % totalSlides;
                updateCarousel();
            }

            // Add click events to indicators
            if (indicators.length > 0) {
                indicators.forEach((indicator, index) => {
                    indicator.addEventListener('click', () => {
                        goToSlide(index);
                    });
                });

                // Start auto-advance
                resetCarouselInterval();

                // Pause on hover
                const carouselContainer = document.querySelector('.carousel-container');
                if (carouselContainer) {
                    carouselContainer.addEventListener('mouseenter', () => {
                        clearInterval(carouselInterval);
                    });
                    
                    carouselContainer.addEventListener('mouseleave', () => {
                        resetCarouselInterval();
                    });
                }
            }

            // Form enhancement
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.textContent = 'Logging in...';
                        submitBtn.disabled = true;
                    }
                });
            }
        });
    </script>
</body>
</html>