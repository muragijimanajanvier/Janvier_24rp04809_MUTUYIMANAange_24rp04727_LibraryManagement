<?php
// books.php
require_once 'config.php';
require_once 'auth.php';
require_once 'functions.php';

$page_title = 'Books - ' . SITE_NAME;
$page_css = 'books.css';
$page_js = 'books.js';

// Get books from database
try {
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    
    $sql = "SELECT * FROM books WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (title LIKE ? OR author LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY title ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll();
    
    // Get categories for filter
    $categories = $pdo->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL ORDER BY category")->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error loading books: " . $e->getMessage();
    $books = [];
    $categories = [];
}
?>
<?php include 'header.php'; ?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-4">Books Library</h1>
            
            <!-- Search and Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search books..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" name="category" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                        <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <a href="books.php" class="btn btn-secondary w-100">Clear</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Books Grid -->
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
                <?php foreach($books as $book): ?>
                <div class="col">
                    <div class="card h-100">
                        <img src="https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" 
                             class="card-img-top" alt="<?php echo htmlspecialchars($book['title']); ?>"
                             style="height: 200px; object-fit: cover;">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                            <p class="card-text text-muted">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($book['author']); ?>
                            </p>
                            <p class="card-text">
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($book['category']); ?></span>
                                <span class="badge bg-info ms-1">
                                    <?php echo $book['available']; ?> available
                                </span>
                            </p>
                        </div>
                        <div class="card-footer">
                            <div class="d-grid gap-2">
                                <a href="view_book.php?id=<?php echo $book['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                
                                <?php if (isLoggedIn()): ?>
                                    <?php if (isReader()): ?>
                                        <a href="mark_as_read.php?book_id=<?php echo $book['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-bookmark"></i> Mark as Read
                                        </a>
                                    <?php elseif (isBorrower() && $book['available'] > 0): ?>
                                        <a href="request_borrow.php?book_id=<?php echo $book['id']; ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="fas fa-hand-paper"></i> Request Book
                                        </a>
                                    <?php elseif (isAdmin()): ?>
                                        <div class="btn-group w-100">
                                            <a href="edit_book.php?id=<?php echo $book['id']; ?>" 
                                               class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete_book.php?id=<?php echo $book['id']; ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Delete this book?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-sign-in-alt"></i> Login to Access
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($books)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book fa-4x text-muted mb-3"></i>
                    <h4>No books found</h4>
                    <p class="text-muted">Try a different search or check back later.</p>
                    <a href="books.php" class="btn btn-primary">View All Books</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>