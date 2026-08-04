<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARKO</title>
    <link rel="stylesheet" href="design/landing.css?v=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <nav class="navbar">
    <div class="nav-container">
        <div class="nav-logo">
            <img src="/Food_System/Log-in Form/design/images/arko.jpg" alt="ARKO LOGO">
            <span>ARKO</span>
        </div>
        
        <div class="nav-links">
            <a href="#home">HOME</a>
            <a href="#about">ABOUT US</a>
            <a href="#contact">CONTACT US</a>
        </div>
        
        <div class="nav-buttons">
            <a href="login.php" class="login-btn">Login</a>
            <a href="signup.php" class="signup-btn">Sign Up</a>
                </div>
             </div>
         </nav>

    <section id="home" class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <h2>WELCOME TO</h2>
                <h1>ARKO</h1>
                <h3>FLAVOURS</h3>
                <p class="hero-subtitle">Where every dish is a celebration of flavor!</p>
                <div class="hero-buttons">
                    <a href="signup.php" class="btn-primary">Get Started</a>
                    <a href="#features" class="btn-secondary">Learn More</a>
                </div>
            </div>
        </div>
    </section>

    <section id="about" class="about">
        <div class="container">
            <h2>About Us</h2>
            <h1>Founded with a passion for creating food that brings people together, Arko Flavours blends bold taste, fresh ingredients, and Filipino heart to serve meals that comfort, excite, and satisfy.</h1>
            
            <h2>Our Story</h2>
            <h1>It all started with a simple idea: good food doesn’t have to be complicated. We believe that real flavor comes from honest ingredients, thoughtful preparation, and a warm smile. Now, Arko Flavours invites you to join us in this journey of taste — whether you’re grabbing a quick lunch, meeting friends for dinner, or just craving something delicious.</h1>
            
            <h2>Our Mission</h2>
            <h1>Our mission is to delight your taste buds, bring joy to your table, and make each meal memorable. We aim to craft dishes that cater to your cravings, made with care and served with warmth.</h1>
            
            <h2>Why Choose Us?</h2>
            <div class="about-grid">
                <div class="about-card">
                    <h3>Authentic Taste</h3>
                    <p>We bring out the rich, real flavors of Filipino comfort food — made with love and seasoned to perfection.</p>
            </div>
            <div class="about-card">
                <h3>Fresh Ingredients</h3>
                <p>Every dish starts with fresh, quality ingredients to make sure every bite is flavorful and satisfying.</p>
            </div>

            <div class="about-card">
                <h3>Made with Passion</h3>
                <p>Our team cooks from the heart — combining experience, creativity, and care in every meal we serve.</p>
            </div>

            <div class="about-card">
                <h3>Friendly & Fast Service</h3>
                <p>We value your time and trust. Expect warm smiles, quick service, and food that always feels like home.</p>
            </div>
        </div>
    </section>

    <section id="contact" class="contact">
    <div class="contact-container">
        <div class="contact-content">
            <h2>Contact Us</h2>
            <p class="contact-subtitle">Get in touch with us for any inquiries or orders</p>
            
            <div class="contact-grid">
                <div class="contact-info">
                    <h3>Get In Touch</h3>
                    
                    <div class="contact-item">
                        <div class="contact-icon"> <img src="./design/images/address.png" alt="Location"></div>
                        <div class="contact-details">
                            <h4>Address</h4>
                            <p>Baranggay San Isidro, Pagsanjan, Philippines</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon"> <img src="./design/images/phone.png" alt="Location"></div>
                        <div class="contact-details">
                            <h4>Phone</h4>
                            <p>+63 912 345 6789</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon"> <img src="./design/images/mail.png" alt="Location"></div>
                        <div class="contact-details">
                            <h4>Email</h4>
                            <p>info@arko.com</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon"> <img src="./design/images/time.png" alt="Location"> </div>
                        <div class="contact-details">
                            <h4>Business Hours</h4>
                            <p>Monday - Sunday: 10:00 AM - 10:00 PM</p>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Form -->
                <div class="contact-form">
                    <form class="form">
                        <div class="form-group">
                            <input type="text" id="name" name="name" placeholder="Name" required>
                        </div>
                        
                        <div class="form-group">
                            <input type="email" id="email" name="email" placeholder="Email" required>
                        </div>
                        
                        <div class="form-group">
                            <input type="text" id="subject" name="subject" placeholder="Subject" required>
                        </div>
                        
                        <div class="form-group">
                            <textarea id="message" name="message" placeholder="Message" rows="5" required></textarea>
                        </div>
                        
                        <button type="submit" class="submit-btn">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
     
</body>
</html>