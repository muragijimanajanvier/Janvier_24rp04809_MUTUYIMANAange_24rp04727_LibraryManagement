<?php
// index.php
require_once 'config.php';
require_once 'auth.php';
require_once 'functions.php';

$page_title = 'Home - ' . SITE_NAME;
$page_css = 'home.css';
?>
<?php include 'header.php'; ?>

<div class="container">
    <!-- Hero Section -->
    <div class="hero-section bg-primary text-white rounded p-5 mb-5">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="display-4 fw-bold">Welcome to Digital Library</h1>
                <p class="lead">Discover, borrow, and manage books with our comprehensive library system.</p>
                <div class="mt-4">
                    <?php if (isLoggedIn()): ?>
                        <a href="dashboard.php" class="btn btn-light btn-lg me-3">Dashboard</a>
                        <a href="books.php" class="btn btn-outline-light btn-lg">Browse Books</a>
                    <?php else: ?>
                        <a href="signup.php" class="btn btn-light btn-lg me-3">Get Started</a>
                        <a href="login.php" class="btn btn-outline-light btn-lg">Login</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6 text-center">
                <img src="https://images.unsplash.com/photo-1481627834876-b7833e8f5570?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                     alt="Library" class="img-fluid rounded" style="max-height: 300px;">
            </div>
        </div>
    </div>
    
    <!-- Features -->
    <div class="row mb-5">
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-book-reader fa-3x text-primary mb-3"></i>
                    <h4>Reader</h4>
                    <p>Read books online, track reading history, and explore digital collection.</p>
                    <?php if (!isLoggedIn()): ?>
                        <a href="signup.php?role=reader" class="btn btn-outline-primary mt-2">Join as Reader</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-exchange-alt fa-3x text-success mb-3"></i>
                    <h4>Borrower</h4>
                    <p>Borrow physical books, request books, and manage borrowings.</p>
                    <?php if (!isLoggedIn()): ?>
                        <a href="signup.php?role=borrower" class="btn btn-outline-success mt-2">Join as Borrower</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-user-shield fa-3x text-warning mb-3"></i>
                    <h4>Lender</h4>
                    <p>Manage library, approve requests, and oversee the system.</p>
                    <?php if (!isLoggedIn()): ?>
                        <a href="signup.php?role=lender" class="btn btn-outline-warning mt-2">Request Access</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Library Statistics</h4>
                    <div class="row text-center">
                        <?php
                        try {
                            $books_count = $pdo->query("SELECT COUNT(*) as count FROM books")->fetch()['count'];
                            $users_count = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
                            $borrowings_count = $pdo->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'borrowed'")->fetch()['count'];
                        } catch (Exception $e) {
                            $books_count = $users_count = $borrowings_count = 0;
                        }
                        ?>
                        <div class="col-md-4">
                            <h2 class="text-primary"><?php echo $books_count; ?></h2>
                            <p>Books Available</p>
                        </div>
                        <div class="col-md-4">
                            <h2 class="text-success"><?php echo $users_count; ?></h2>
                            <p>Active Members</p>
                        </div>
                        <div class="col-md-4">
                            <h2 class="text-warning"><?php echo $borrowings_count; ?></h2>
                            <p>Active Borrowings</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>