<?php
// dashboard.php
require_once 'config.php';
require_once 'auth.php';
require_once 'functions.php';
requireLogin();

$page_title = 'Dashboard - ' . SITE_NAME;
$page_css = 'dashboard.css';
$page_js = 'dashboard.js';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Initialize stats array
$stats = [];
$error = '';

try {
    if ($role == 'reader') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reading_history WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch();
    } elseif ($role == 'borrower') {
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total_borrowed,
            SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as current_borrowed
            FROM borrowings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch();
    } elseif ($role == 'lender') {
        // For lender, get books they own/lent
        // First check if books table has owner_id column
        $check_stmt = $pdo->query("SHOW COLUMNS FROM books LIKE 'owner_id'");
        if ($check_stmt->rowCount() > 0) {
            // Books table has owner_id column
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_books FROM books WHERE owner_id = ?");
            $stmt->execute([$user_id]);
            $stats['total_books'] = $stmt->fetch()['total_books'] ?? 0;
            
            // Get books currently lent out
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT b.id) as books_lent 
                                  FROM books b 
                                  JOIN borrowings br ON b.id = br.book_id 
                                  WHERE b.owner_id = ? AND br.status = 'borrowed'");
            $stmt->execute([$user_id]);
            $stats['books_lent'] = $stmt->fetch()['books_lent'] ?? 0;
            
            // Get pending requests for lender's books
            $stmt = $pdo->prepare("SELECT COUNT(*) as pending_requests 
                                  FROM borrowings br 
                                  JOIN books b ON br.book_id = b.id 
                                  WHERE b.owner_id = ? AND br.status = 'pending'");
            $stmt->execute([$user_id]);
            $stats['pending_requests'] = $stmt->fetch()['pending_requests'] ?? 0;
        } else {
            // Books table doesn't have owner_id - simplified stats
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_books FROM books");
            $stmt->execute();
            $stats['total_books'] = $stmt->fetch()['total_books'] ?? 0;
            
            // Get books currently lent out (simplified)
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT book_id) as books_lent 
                                  FROM borrowings WHERE status = 'borrowed'");
            $stmt->execute();
            $stats['books_lent'] = $stmt->fetch()['books_lent'] ?? 0;
            
            // Get pending requests (simplified)
            $stmt = $pdo->prepare("SELECT COUNT(*) as pending_requests 
                                  FROM borrowings WHERE status = 'pending'");
            $stmt->execute();
            $stats['pending_requests'] = $stmt->fetch()['pending_requests'] ?? 0;
        }
    }
} catch (PDOException $e) {
    $error = 'Failed to load stats: ' . $e->getMessage();
}
?>
<?php include 'header.php'; ?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h1 class="card-title">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                    <p class="card-text">You are logged in as a <strong><?php echo ucfirst($role); ?></strong></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (isset($error) && !empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php 
    // Display flash messages if function exists
    if (function_exists('getFlash')) {
        echo getFlash();
    }
    ?>
    
    <!-- Role-specific Dashboard -->
    <?php if ($role == 'reader'): ?>
        <!-- Reader Dashboard -->
        <div class="row">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h1 class="display-4"><?php echo $stats['count'] ?? 0; ?></h1>
                        <p class="card-text">Books Read</p>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Reading</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->prepare("SELECT b.title, rh.read_date 
                                              FROM reading_history rh 
                                              JOIN books b ON rh.book_id = b.id 
                                              WHERE rh.user_id = ? 
                                              ORDER BY rh.read_date DESC 
                                              LIMIT 5");
                        $stmt->execute([$user_id]);
                        $history = $stmt->fetchAll();
                        
                        if ($history): ?>
                            <ul class="list-group">
                                <?php foreach($history as $item): ?>
                                    <li class="list-group-item">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                        <span class="float-end text-muted">
                                            <?php echo formatDate($item['read_date'] ?? ''); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">No reading history yet.</p>
                            <a href="books.php" class="btn btn-primary">Browse Books</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif ($role == 'borrower'): ?>
        <!-- Borrower Dashboard -->
        <div class="row">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h1 class="display-4"><?php echo $stats['total_borrowed'] ?? 0; ?></h1>
                        <p class="card-text">Total Borrowed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h1 class="display-4"><?php echo $stats['current_borrowed'] ?? 0; ?></h1>
                        <p class="card-text">Currently Borrowed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Current Borrowings</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->prepare("SELECT b.title, br.due_date, br.status 
                                              FROM borrowings br 
                                              JOIN books b ON br.book_id = b.id
                                              WHERE br.user_id = ? AND br.status = 'borrowed'
                                              ORDER BY br.due_date ASC
                                              LIMIT 5");
                        $stmt->execute([$user_id]);
                        $borrowings = $stmt->fetchAll();
                        
                        if ($borrowings): ?>
                            <ul class="list-group">
                                <?php foreach($borrowings as $borrowing): ?>
                                    <li class="list-group-item">
                                        <strong><?php echo htmlspecialchars($borrowing['title']); ?></strong>
                                        <span class="float-end text-muted">
                                            Due: <?php echo formatDate($borrowing['due_date'] ?? ''); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">No current borrowings.</p>
                            <a href="books.php" class="btn btn-primary">Browse Books to Borrow</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif ($role == 'lender'): ?>
        <!-- Lender Dashboard -->
        <div class="row">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h1 class="display-4"><?php echo $stats['total_books'] ?? 0; ?></h1>
                        <p class="card-text">Total Books Listed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h1 class="display-4"><?php echo $stats['books_lent'] ?? 0; ?></h1>
                        <p class="card-text">Books Currently Lent</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h1 class="display-4"><?php echo $stats['pending_requests'] ?? 0; ?></h1>
                        <p class="card-text">Pending Requests</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lender Actions -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="add_book.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i>Add New Book
                            </a>
                            <a href="my_books.php" class="btn btn-outline-primary">
                                <i class="fas fa-book me-2"></i>Manage My Books
                            </a>
                            <a href="borrow_requests.php" class="btn btn-outline-success">
                                <i class="fas fa-inbox me-2"></i>View Borrow Requests
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get recent borrow requests
                        try {
                            // Check if books table has owner_id column
                            $check_stmt = $pdo->query("SHOW COLUMNS FROM books LIKE 'owner_id'");
                            if ($check_stmt->rowCount() > 0) {
                                $stmt = $pdo->prepare("SELECT b.title, u.full_name, br.request_date, br.status 
                                                      FROM borrowings br 
                                                      JOIN books b ON br.book_id = b.id 
                                                      JOIN users u ON br.user_id = u.id
                                                      WHERE b.owner_id = ? 
                                                      ORDER BY br.request_date DESC 
                                                      LIMIT 5");
                                $stmt->execute([$user_id]);
                            } else {
                                $stmt = $pdo->prepare("SELECT b.title, u.full_name, br.request_date, br.status 
                                                      FROM borrowings br 
                                                      JOIN books b ON br.book_id = b.id 
                                                      JOIN users u ON br.user_id = u.id
                                                      ORDER BY br.request_date DESC 
                                                      LIMIT 5");
                                $stmt->execute();
                            }
                            
                            $activities = $stmt->fetchAll();
                            
                            if ($activities): ?>
                                <ul class="list-group">
                                    <?php foreach($activities as $activity): ?>
                                        <li class="list-group-item">
                                            <small>
                                                <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                                requested <em><?php echo htmlspecialchars($activity['title']); ?></em>
                                                <span class="badge bg-<?php 
                                                    echo $activity['status'] == 'pending' ? 'warning' : 
                                                         ($activity['status'] == 'approved' ? 'success' : 'secondary');
                                                ?>">
                                                    <?php echo ucfirst($activity['status']); ?>
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo formatDate($activity['request_date'] ?? ''); ?>
                                                </small>
                                            </small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">No recent activity.</p>
                                <a href="add_book.php" class="btn btn-primary">Add your first book</a>
                            <?php endif;
                            
                        } catch (PDOException $e) {
                            echo '<p class="text-muted">Unable to load recent activity.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Default/Unknown Role -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <h4>Welcome to <?php echo SITE_NAME; ?></h4>
                        <p>Your account is being set up. Please contact administrator if you need assistance.</p>
                        <a href="index.php" class="btn btn-primary">Go to Home</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Common Actions for All Users -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="books.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-book-open me-2"></i>Browse Books
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="profile.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-user me-2"></i>My Profile
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="settings.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="logout.php" class="btn btn-outline-danger w-100">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>