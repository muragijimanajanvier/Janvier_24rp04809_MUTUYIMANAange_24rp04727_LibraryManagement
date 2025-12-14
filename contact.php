<?php
// contact.php
require_once 'config.php';
require_once 'functions.php';

$page_title = 'Contact Us - ' . SITE_NAME;
$page_css = 'contact.css';
$page_js = 'contact.js';

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($subject)) {
        $errors[] = 'Subject is required';
    }
    
    if (empty($message)) {
        $errors[] = 'Message is required';
    } elseif (strlen($message) < 10) {
        $errors[] = 'Message must be at least 10 characters';
    }
    
    // If no errors, process form
    if (empty($errors)) {
        try {
            // Save to database
            $sql = "INSERT INTO contact_messages (name, email, subject, message, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt->execute([$name, $email, $subject, $message, $ip_address, $user_agent]);
            
            $success = 'Thank you! Your message has been sent. We\'ll respond within 24 hours.';
            
            // Clear form
            $_POST = [];
            
        } catch (PDOException $e) {
            $errors[] = 'Failed to send message: ' . $e->getMessage();
        }
    }
}

// Create contact_messages table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        subject VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    // Table creation failed, but we can still proceed
}
?>
<?php include 'header.php'; ?>

<div class="container">
    <!-- Hero Section -->
    <div class="hero-section text-center py-5 mb-5">
        <h1 class="display-4 fw-bold mb-3">Contact Us</h1>
        <p class="lead mb-4">Get in touch with our team. We're here to help!</p>
    </div>
    
    <div class="row">
        <!-- Contact Information -->
        <div class="col-lg-4 mb-4">
            <div class="contact-info-card p-4 rounded shadow-sm h-100">
                <h3 class="mb-4">Get In Touch</h3>
                
                <div class="contact-item mb-4">
                    <div class="contact-icon mb-2">
                        <i class="fas fa-map-marker-alt fa-2x text-primary"></i>
                    </div>
                    <h5>Our Location</h5>
                    <p>123 Library Street<br>Knowledge City, KC 10001</p>
                </div>
                
                <div class="contact-item mb-4">
                    <div class="contact-icon mb-2">
                        <i class="fas fa-phone fa-2x text-success"></i>
                    </div>
                    <h5>Phone Number</h5>
                    <p>+250 795 175 573<br>+250 792 454 072</p>
                </div>
                
                <div class="contact-item mb-4">
                    <div class="contact-icon mb-2">
                        <i class="fas fa-envelope fa-2x text-warning"></i>
                    </div>
                    <h5>Email Address</h5>
                    <p>info@<?php echo strtolower(SITE_NAME); ?>.com<br>support@<?php echo strtolower(SITE_NAME); ?>.com</p>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon mb-2">
                        <i class="fas fa-clock fa-2x text-info"></i>
                    </div>
                    <h5>Working Hours</h5>
                    <p>Monday - Friday: 8:00 AM - 8:00 PM<br>Saturday: 9:00 AM - 6:00 PM<br>Sunday: 10:00 AM - 4:00 PM</p>
                </div>
                
                <hr class="my-4">
                
                <h5 class="mb-3">Follow Us</h5>
                <div class="social-links">
                    <a href="#" class="btn btn-outline-primary me-2 mb-2">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="btn btn-outline-info me-2 mb-2">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="btn btn-outline-danger me-2 mb-2">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="btn btn-outline-primary me-2 mb-2">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Contact Form -->
        <div class="col-lg-8 mb-4">
            <div class="contact-form-card p-4 rounded shadow-sm">
                <h3 class="mb-4">Send Us a Message</h3>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5>Please fix the following errors:</h5>
                        <ul class="mb-0">
                            <?php foreach($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="contactForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Please enter your name.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Please enter a valid email.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject *</label>
                        <input type="text" class="form-control" id="subject" name="subject" 
                               value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                        <div class="invalid-feedback">Please enter a subject.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message *</label>
                        <textarea class="form-control" id="message" name="message" rows="6" 
                                  required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        <div class="invalid-feedback">Please enter your message (minimum 10 characters).</div>
                        <div class="mt-1">
                            <small class="text-muted" id="charCount">0/500 characters</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="newsletter" name="newsletter">
                            <label class="form-check-label" for="newsletter">
                                Subscribe to our newsletter for updates and announcements
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </button>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            By submitting this form, you agree to our 
                            <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Map Section -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="map-section rounded shadow-sm overflow-hidden">
                <div class="map-placeholder bg-light p-5 text-center">
                    <i class="fas fa-map fa-4x text-muted mb-3"></i>
                    <h4>Our Location</h4>
                    <p class="text-muted">Interactive map would be displayed here</p>
                    <div class="mt-3">
                        <a href="https://maps.google.com" target="_blank" class="btn btn-outline-primary">
                            <i class="fas fa-directions me-2"></i>Get Directions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- FAQ Section -->
    <div class="row mt-5">
        <div class="col-12">
            <h2 class="text-center mb-4">Frequently Asked Questions</h2>
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            How do I create an account?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Click on the "Sign Up" button in the navigation bar and fill out the registration form. 
                            You'll need to provide your name, email, and choose a username and password.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            How long can I borrow a book?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            The standard borrowing period is 14 days. You can renew books if there are no pending requests.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            How do I reset my password?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Click on "Forgot Password" on the login page and follow the instructions sent to your email.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            Can I suggest books for the library?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes! Use the contact form to suggest books. Our team reviews all suggestions regularly.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Privacy Policy</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>Data Collection</h6>
                <p>We collect your information solely to provide library services and improve user experience.</p>
                
                <h6>Data Usage</h6>
                <p>Your information is used to manage your account, process requests, and communicate with you.</p>
                
                <h6>Data Protection</h6>
                <p>We implement security measures to protect your personal information.</p>
                
                <h6>Contact Information</h6>
                <p>For privacy concerns, contact: privacy@<?php echo strtolower(SITE_NAME); ?>.com</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>