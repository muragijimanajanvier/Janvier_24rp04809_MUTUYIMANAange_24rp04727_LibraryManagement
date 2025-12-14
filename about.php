<?php
// about.php
require_once 'config.php';
require_once 'functions.php';

$page_title = 'About Us - ' . SITE_NAME;
$page_css = 'about.css';
?>
<?php include 'header.php'; ?>

<div class="container">
    <!-- Hero Section -->
    <div class="hero-section text-center py-5 mb-5">
        <h1 class="display-4 fw-bold mb-3">About Our Library</h1>
        <p class="lead mb-4">Transforming reading experiences through digital innovation</p>
    </div>
    
    <!-- Mission & Vision -->
    <div class="row mb-5">
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="icon-box mb-3">
                        <i class="fas fa-bullseye fa-3x text-primary"></i>
                    </div>
                    <h3 class="card-title mb-3">Our Mission</h3>
                    <p class="card-text">
                        To provide accessible, innovative, and comprehensive library services that 
                        foster lifelong learning, support academic excellence, and promote a love 
                        for reading in our community.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="icon-box mb-3">
                        <i class="fas fa-eye fa-3x text-success"></i>
                    </div>
                    <h3 class="card-title mb-3">Our Vision</h3>
                    <p class="card-text">
                        To be the leading digital library platform that connects readers with 
                        knowledge, empowers learning, and builds an informed and educated 
                        community through technology.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- History Section -->
    <div class="row mb-5 align-items-center">
        <div class="col-lg-6 mb-4">
            <img src="https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                 alt="Library History" class="img-fluid rounded shadow">
        </div>
        <div class="col-lg-6 mb-4">
            <h2 class="mb-4">Our Story</h2>
            <p>
                Founded in 2023, <?php echo SITE_NAME; ?> began as a small project with a big vision: 
                to make reading accessible to everyone, everywhere. What started as a simple 
                digital catalog has grown into a comprehensive library management system 
                serving thousands of users.
            </p>
            <p>
                Our journey has been guided by our commitment to innovation, user experience, 
                and community engagement. We continuously evolve to meet the changing needs 
                of our readers in the digital age.
            </p>
            <div class="mt-4">
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-check-circle text-success me-2"></i>
                    <span>Over 10,000 books in our collection</span>
                </div>
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-check-circle text-success me-2"></i>
                    <span>Serving 5,000+ active members</span>
                </div>
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-check-circle text-success me-2"></i>
                    <span>24/7 digital access to resources</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Team Section -->
    <div class="row mb-5">
        <div class="col-12">
            <h2 class="text-center mb-5">Meet Our Team</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="team-card text-center">
                        <div class="team-img mb-3">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" 
                                 class="rounded-circle" alt="Team Member" width="150" height="150">
                        </div>
                        <h4>Alex Johnson</h4>
                        <p class="text-muted">Library Director</p>
                        <p>10+ years of experience in library management</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="team-card text-center">
                        <div class="team-img mb-3">
                            <img src="https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" 
                                 class="rounded-circle" alt="Team Member" width="150" height="150">
                        </div>
                        <h4>Maria Garcia</h4>
                        <p class="text-muted">Digital Resources Manager</p>
                        <p>Expert in digital library systems and user experience</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="team-card text-center">
                        <div class="team-img mb-3">
                            <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" 
                                 class="rounded-circle" alt="Team Member" width="150" height="150">
                        </div>
                        <h4>David Kim</h4>
                        <p class="text-muted">Technical Support Lead</p>
                        <p>Ensuring our platform runs smoothly 24/7</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Values Section -->
    <div class="row mb-5">
        <div class="col-12">
            <h2 class="text-center mb-5">Our Values</h2>
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="value-card text-center p-4">
                        <div class="value-icon mb-3">
                            <i class="fas fa-users fa-3x text-primary"></i>
                        </div>
                        <h5>Community</h5>
                        <p>Building strong connections with our readers</p>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="value-card text-center p-4">
                        <div class="value-icon mb-3">
                            <i class="fas fa-lightbulb fa-3x text-warning"></i>
                        </div>
                        <h5>Innovation</h5>
                        <p>Embracing technology to enhance reading experiences</p>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="value-card text-center p-4">
                        <div class="value-icon mb-3">
                            <i class="fas fa-lock fa-3x text-success"></i>
                        </div>
                        <h5>Privacy</h5>
                        <p>Protecting user data and reading preferences</p>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="value-card text-center p-4">
                        <div class="value-icon mb-3">
                            <i class="fas fa-handshake fa-3x text-info"></i>
                        </div>
                        <h5>Accessibility</h5>
                        <p>Making reading available to everyone</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contact CTA -->
    <div class="row">
        <div class="col-12">
            <div class="cta-section bg-primary text-white rounded p-5 text-center">
                <h2 class="mb-3">Want to Know More?</h2>
                <p class="mb-4">Get in touch with us for any questions or partnership opportunities</p>
                <a href="contact.php" class="btn btn-light btn-lg">
                    <i class="fas fa-envelope me-2"></i>Contact Us
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>