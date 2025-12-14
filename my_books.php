<?php
// my_books.php - For lenders to manage their books
require_once 'config.php';
require_once 'auth.php';
require_once 'functions.php';
requireLogin();

// Only lenders can access this page
if ($_SESSION['role'] !== 'lender') {
    $_SESSION['flash_error'] = 'Access denied. Only lenders can manage books.';
    header("Location: dashboard.php");
    exit();
}

$page_title = 'My Books - ' . SITE_NAME;
$page_css = 'my_books.css';
$page_js = 'my_books.js';

$user_id = $_SESSION['user_id'];
$owner_column = 'added_by'; // Use the correct column name for your database
$message = '';
$books = [];
$total_books = 0;
$available_books = 0;
$borrowed_books = 0;

// First, let's check what columns exist in the books table
try {
    $check_columns = $pdo->query("SHOW COLUMNS FROM books");
    $columns = $check_columns->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Check if we have a status column
    $has_status = in_array('status', $columns);
    
    // Check if we have available copies system
    $has_copies = in_array('available_copies', $columns) && in_array('total_copies', $columns);
    
} catch (PDOException $e) {
    $message = '<div class="alert alert-danger">Error checking database structure: ' . htmlspecialchars($e->getMessage()) . '</div>';
    $has_status = false;
    $has_copies = false;
}

// Handle book deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_book'])) {
    $book_id = intval($_POST['book_id'] ?? 0);
    
    if ($book_id > 0) {
        try {
            // Check if book belongs to this lender
            $check_stmt = $pdo->prepare("SELECT id FROM books WHERE id = ? AND $owner_column = ?");
            $check_stmt->execute([$book_id, $user_id]);
            
            if ($check_stmt->rowCount() > 0) {
                // Check if book is currently borrowed
                $borrow_stmt = $pdo->prepare("SELECT id FROM borrowings WHERE book_id = ? AND status IN ('borrowed', 'pending')");
                $borrow_stmt->execute([$book_id]);
                
                if ($borrow_stmt->rowCount() > 0) {
                    $message = '<div class="alert alert-warning">Cannot delete book that is currently borrowed or has pending requests.</div>';
                } else {
                    // Delete the book
                    $delete_stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
                    $delete_stmt->execute([$book_id]);
                    
                    $message = '<div class="alert alert-success">Book deleted successfully!</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">Book not found or you don\'t have permission to delete it.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error deleting book: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Handle availability update (only if status column exists)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_availability']) && $has_status) {
    $book_id = intval($_POST['book_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    
    if ($book_id > 0 && in_array($new_status, ['available', 'unavailable'])) {
        try {
            // Check if book belongs to this lender
            $check_stmt = $pdo->prepare("SELECT id FROM books WHERE id = ? AND $owner_column = ?");
            $check_stmt->execute([$book_id, $user_id]);
            
            if ($check_stmt->rowCount() > 0) {
                // Update status
                $update_stmt = $pdo->prepare("UPDATE books SET status = ?, updated_at = NOW() WHERE id = ?");
                $update_stmt->execute([$new_status, $book_id]);
                
                $message = '<div class="alert alert-success">Book availability updated successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Book not found or you don\'t have permission to update it.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error updating book: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Get lender's books with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get filter parameters
$availability_filter = $_GET['availability'] ?? '';
$search_query = $_GET['search'] ?? '';

try {
    // Base query
    $sql = "SELECT b.*, 
            (SELECT COUNT(*) FROM borrowings WHERE book_id = b.id AND status = 'borrowed') as borrowed_count,
            (SELECT COUNT(*) FROM borrowings WHERE book_id = b.id AND status = 'pending') as pending_requests";
    
    // If we have copies system, include it
    if ($has_copies) {
        $sql .= ", b.available_copies, b.total_copies";
    }
    
    $sql .= " FROM books b ";
    
    $count_sql = "SELECT COUNT(*) as total FROM books b ";
    
    // Add WHERE clause
    $where = [];
    $params = [];
    
    $where[] = "b.$owner_column = ?";
    $params[] = $user_id;
    
    // Apply availability filter
    if ($availability_filter && in_array($availability_filter, ['available', 'unavailable'])) {
        if ($has_status) {
            $where[] = "b.status = ?";
            $params[] = $availability_filter;
        } elseif ($has_copies) {
            if ($availability_filter == 'available') {
                $where[] = "b.available_copies > 0";
            } else {
                $where[] = "b.available_copies = 0";
            }
        }
    }
    
    if ($search_query) {
        $where[] = "(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
        $search_param = "%$search_query%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
        $count_sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Execute main query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll();
    
    // Execute count query (remove limit/offset from params)
    $count_params = array_slice($params, 0, -2);
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_count = $count_stmt->fetch()['total'];
    
    // Calculate pagination
    $total_pages = ceil($total_count / $limit);
    
    // Get statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_books";
    
    // Add status/copies based statistics
    if ($has_status) {
        $stats_sql .= ",
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_books,
            SUM(CASE WHEN status = 'unavailable' THEN 1 ELSE 0 END) as unavailable_books";
    } elseif ($has_copies) {
        $stats_sql .= ",
            SUM(CASE WHEN available_copies > 0 THEN 1 ELSE 0 END) as available_books,
            SUM(CASE WHEN available_copies = 0 THEN 1 ELSE 0 END) as unavailable_books";
    } else {
        // If no status or copies, count all as available
        $stats_sql .= ",
            COUNT(*) as available_books,
            0 as unavailable_books";
    }
    
    $stats_sql .= " FROM books WHERE $owner_column = ?";
    
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch();
    
    $total_books = $stats['total_books'] ?? 0;
    $available_books = $stats['available_books'] ?? 0;
    $unavailable_books = $stats['unavailable_books'] ?? 0;
    
    // Get borrowed books count from borrowings table
    $borrowed_stmt = $pdo->prepare("SELECT COUNT(DISTINCT b.id) as borrowed_books 
                                   FROM books b 
                                   JOIN borrowings br ON b.id = br.book_id 
                                   WHERE b.$owner_column = ? AND br.status = 'borrowed'");
    $borrowed_stmt->execute([$user_id]);
    $borrowed_stats = $borrowed_stmt->fetch();
    $borrowed_books = $borrowed_stats['borrowed_books'] ?? 0;
    
} catch (PDOException $e) {
    $message = '<div class="alert alert-danger">Error loading books: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
<?php include 'header.php'; ?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0"><i class="fas fa-book me-2"></i>My Books Collection</h1>
                    <p class="text-muted mb-0">Manage the books you're lending to others</p>
                </div>
                <div>
                    <a href="add_book.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i>Add New Book
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php echo $message; ?>
    
    <!-- Database structure info (remove in production) -->
    <?php if (!$has_status): ?>
    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Note:</strong> 
        <?php if ($has_copies): ?>
            Using available copies system instead.
        <?php else: ?>
            Showing all books as available.
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Total Books</h5>
                            <h2 class="mb-0"><?php echo $total_books; ?></h2>
                        </div>
                        <i class="fas fa-book fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Available</h5>
                            <h2 class="mb-0"><?php echo $available_books; ?></h2>
                        </div>
                        <i class="fas fa-check-circle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Currently Borrowed</h5>
                            <h2 class="mb-0"><?php echo $borrowed_books; ?></h2>
                        </div>
                        <i class="fas fa-exchange-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Pending Requests</h5>
                            <?php
                            $pending_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM borrowings br 
                                                          JOIN books b ON br.book_id = b.id 
                                                          WHERE b.$owner_column = ? AND br.status = 'pending'");
                            $pending_stmt->execute([$user_id]);
                            $pending_count = $pending_stmt->fetch()['count'] ?? 0;
                            ?>
                            <h2 class="mb-0"><?php echo $pending_count; ?></h2>
                        </div>
                        <i class="fas fa-inbox fa-3x opacity-50"></i>
                    </div>
                    <?php if ($pending_count > 0): ?>
                        <a href="borrow_requests.php" class="btn btn-light btn-sm mt-2 w-100">
                            <i class="fas fa-eye me-1"></i>View Requests
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters and Search -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search by title, author, or ISBN..."
                                       value="<?php echo htmlspecialchars($search_query); ?>">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <?php if ($search_query): ?>
                                    <a href="my_books.php" class="btn btn-outline-secondary">Clear</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <select class="form-control" name="availability" onchange="this.form.submit()">
                                <option value="">All Availability</option>
                                <option value="available" <?php echo $availability_filter == 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="unavailable" <?php echo $availability_filter == 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 text-end">
                            <a href="add_book.php" class="btn btn-success w-100">
                                <i class="fas fa-plus me-1"></i>Add Book
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Books Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">My Books (<?php echo $total_count; ?> books)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($books)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-book fa-4x text-muted mb-3"></i>
                            <h4>No books found</h4>
                            <p class="text-muted">You haven't added any books to your collection yet.</p>
                            <a href="add_book.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i>Add Your First Book
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">Cover</th>
                                        <th>Book Details</th>
                                        <th>Availability</th>
                                        <th>Requests</th>
                                        <th>Added On</th>
                                        <th width="200">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($books as $book): 
                                        // Determine availability status
                                        $availability = 'available';
                                        $availability_badge = 'success';
                                        $availability_text = 'Available';
                                        
                                        if ($has_status && isset($book['status'])) {
                                            $availability = $book['status'];
                                            $availability_text = ucfirst($book['status']);
                                            $availability_badge = $book['status'] == 'available' ? 'success' : 'secondary';
                                        } elseif ($has_copies) {
                                            if (isset($book['available_copies']) && $book['available_copies'] > 0) {
                                                $availability = 'available';
                                                $availability_text = $book['available_copies'] . ' copies available';
                                                $availability_badge = 'success';
                                            } else {
                                                $availability = 'unavailable';
                                                $availability_text = 'No copies available';
                                                $availability_badge = 'secondary';
                                            }
                                        } elseif ($book['borrowed_count'] > 0) {
                                            $availability = 'borrowed';
                                            $availability_text = 'Currently Borrowed';
                                            $availability_badge = 'warning';
                                        }
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($book['cover_image'])): ?>
                                                    <img src="covers/<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                                         alt="Cover" class="img-thumbnail" style="width: 50px; height: 70px;">
                                                <?php else: ?>
                                                    <div class="bg-light d-flex align-items-center justify-content-center" 
                                                         style="width: 50px; height: 70px;">
                                                        <i class="fas fa-book text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($book['title']); ?></h6>
                                                <p class="text-muted mb-1">
                                                    <small><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($book['author']); ?></small>
                                                </p>
                                                <?php if (!empty($book['isbn'])): ?>
                                                    <p class="text-muted mb-0">
                                                        <small><i class="fas fa-barcode me-1"></i><?php echo htmlspecialchars($book['isbn']); ?></small>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if ($has_copies && isset($book['total_copies'])): ?>
                                                    <p class="text-muted mb-0">
                                                        <small><i class="fas fa-copy me-1"></i>
                                                            <?php echo ($book['available_copies'] ?? 0) . '/' . $book['total_copies']; ?> copies
                                                        </small>
                                                    </p>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $availability_badge; ?>">
                                                    <?php echo $availability_text; ?>
                                                </span>
                                                
                                                <?php if ($has_status): ?>
                                                <form method="POST" action="" class="mt-1" onsubmit="return confirm('Change availability?')">
                                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                        <option value="available" <?php echo $availability == 'available' ? 'selected' : ''; ?>>Available</option>
                                                        <option value="unavailable" <?php echo $availability == 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                                                    </select>
                                                    <input type="hidden" name="update_availability" value="1">
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($book['borrowed_count'] > 0): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-exchange-alt me-1"></i>Currently Borrowed
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($book['pending_requests'] > 0): ?>
                                                    <div class="mt-1">
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-clock me-1"></i><?php echo $book['pending_requests']; ?> pending
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo formatDate($book['created_at']); ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="book_details.php?id=<?php echo $book['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit_book.php?id=<?php echo $book['id']; ?>" 
                                                       class="btn btn-sm btn-outline-success" title="Edit Book">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                                            data-bs-toggle="modal" data-bs-target="#requestsModal<?php echo $book['id']; ?>"
                                                            title="View Requests">
                                                        <i class="fas fa-inbox"></i>
                                                    </button>
                                                    <form method="POST" action="" class="d-inline" 
                                                          onsubmit="return confirm('Are you sure you want to delete this book?')">
                                                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                        <button type="submit" name="delete_book" 
                                                                class="btn btn-sm btn-outline-danger" title="Delete Book">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- Requests Modal for this book -->
                                        <div class="modal fade" id="requestsModal<?php echo $book['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Requests for: <?php echo htmlspecialchars($book['title']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <?php
                                                        $requests_stmt = $pdo->prepare("SELECT br.*, u.full_name, u.email 
                                                                                      FROM borrowings br 
                                                                                      JOIN users u ON br.user_id = u.id 
                                                                                      WHERE br.book_id = ? 
                                                                                      ORDER BY br.request_date DESC");
                                                        $requests_stmt->execute([$book['id']]);
                                                        $requests = $requests_stmt->fetchAll();
                                                        
                                                        if ($requests): ?>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Borrower</th>
                                                                            <th>Request Date</th>
                                                                            <th>Status</th>
                                                                            <th>Actions</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach($requests as $request): ?>
                                                                            <tr>
                                                                                <td>
                                                                                    <?php echo htmlspecialchars($request['full_name']); ?><br>
                                                                                    <small class="text-muted"><?php echo htmlspecialchars($request['email']); ?></small>
                                                                                </td>
                                                                                <td><?php echo formatDate($request['request_date']); ?></td>
                                                                                <td>
                                                                                    <span class="badge bg-<?php 
                                                                                        echo $request['status'] == 'pending' ? 'warning' : 
                                                                                             ($request['status'] == 'approved' ? 'success' : 'secondary');
                                                                                    ?>">
                                                                                        <?php echo ucfirst($request['status']); ?>
                                                                                    </span>
                                                                                </td>
                                                                                <td>
                                                                                    <a href="borrow_request.php?id=<?php echo $request['id']; ?>" 
                                                                                       class="btn btn-sm btn-outline-primary">
                                                                                        <i class="fas fa-eye"></i> View
                                                                                    </a>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php else: ?>
                                                            <p class="text-muted">No requests for this book.</p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&availability=<?php echo $availability_filter; ?>&search=<?php echo urlencode($search_query); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&availability=<?php echo $availability_filter; ?>&search=<?php echo urlencode($search_query); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&availability=<?php echo $availability_filter; ?>&search=<?php echo urlencode($search_query); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="add_book.php" class="btn btn-primary w-100">
                                <i class="fas fa-plus-circle me-2"></i>Add New Book
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="borrow_requests.php" class="btn btn-warning w-100">
                                <i class="fas fa-inbox me-2"></i>View All Requests
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="export_books.php" class="btn btn-success w-100">
                                <i class="fas fa-download me-2"></i>Export Books
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="dashboard.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<a href="export_books.php" class="btn btn-export">Export Books</a>
<?php include 'footer.php'; ?>