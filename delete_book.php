<?php
// delete_book.php
require_once 'config.php';
require_once 'auth_check.php';

// Check if user is admin or librarian
requireAnyRole(['admin', 'librarian']);

// Generate CSRF token for confirmation form
$csrf_token = generateCsrfToken();

// Initialize variables
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$book = null;
$error = '';
$success = '';
$has_active_borrows = false;

// Fetch book details
if ($book_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        $error = "Book not found!";
    } else {
        // Check if book has active borrows
        $stmt = $pdo->prepare("SELECT COUNT(*) as active_borrows FROM borrow_records WHERE book_id = ? AND return_date IS NULL");
        $stmt->execute([$book_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $has_active_borrows = $result['active_borrows'] > 0;
    }
}

// Handle book deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_book'])) {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid security token!";
    } else {
        $book_id = intval($_POST['book_id']);
        
        // Double-check authorization
        if (!isAdmin() && !isLibrarian()) {
            $error = "You don't have permission to delete books!";
        } else {
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Check again if book exists
                $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
                $stmt->execute([$book_id]);
                $book_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$book_to_delete) {
                    $error = "Book not found!";
                    $pdo->rollBack();
                } else {
                    // Check for active borrows
                    $stmt = $pdo->prepare("SELECT COUNT(*) as active_borrows FROM borrow_records WHERE book_id = ? AND return_date IS NULL");
                    $stmt->execute([$book_id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result['active_borrows'] > 0) {
                        $error = "Cannot delete book with active borrows! Please wait until all copies are returned.";
                        $pdo->rollBack();
                    } else {
                        // Archive option: If you want to keep history
                        if (isset($_POST['archive']) && $_POST['archive'] === 'yes') {
                            // Move to archive table instead of deleting
                            $stmt = $pdo->prepare("INSERT INTO books_archive SELECT *, NOW() as archived_at FROM books WHERE id = ?");
                            $stmt->execute([$book_id]);
                            
                            // Also archive borrow records
                            $stmt = $pdo->prepare("INSERT INTO borrow_records_archive SELECT *, NOW() as archived_at FROM borrow_records WHERE book_id = ?");
                            $stmt->execute([$book_id]);
                            
                            // Delete from main tables
                            $stmt = $pdo->prepare("DELETE FROM borrow_records WHERE book_id = ?");
                            $stmt->execute([$book_id]);
                        }
                        
                        // Delete book
                        $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
                        $stmt->execute([$book_id]);
                        
                        // Log the deletion
                        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
                        $log_stmt->execute([
                            getCurrentUserId(),
                            'DELETE_BOOK',
                            "Deleted book: {$book_to_delete['title']} (ID: {$book_id})",
                            $_SERVER['REMOTE_ADDR']
                        ]);
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        // Set success message and redirect
                        $_SESSION['success'] = "Book '{$book_to_delete['title']}' has been successfully deleted!";
                        
                        // Redirect based on source
                        if (isset($_POST['redirect_to']) && !empty($_POST['redirect_to'])) {
                            header('Location: ' . $_POST['redirect_to']);
                        } else {
                            header('Location: books.php');
                        }
                        exit();
                    }
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error deleting book: " . $e->getMessage();
                error_log("Delete book error: " . $e->getMessage());
            }
        }
    }
}

// Handle multiple book deletion (bulk delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid security token!";
    } else if (!isset($_POST['book_ids']) || empty($_POST['book_ids'])) {
        $error = "No books selected for deletion!";
    } else {
        $book_ids = $_POST['book_ids'];
        $deleted_count = 0;
        $failed_count = 0;
        $deleted_titles = [];
        
        try {
            $pdo->beginTransaction();
            
            foreach ($book_ids as $book_id_str) {
                $current_book_id = intval($book_id_str);
                
                // Check if book exists
                $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
                $stmt->execute([$current_book_id]);
                $book_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($book_to_delete) {
                    // Check for active borrows
                    $stmt = $pdo->prepare("SELECT COUNT(*) as active_borrows FROM borrow_records WHERE book_id = ? AND return_date IS NULL");
                    $stmt->execute([$current_book_id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result['active_borrows'] > 0) {
                        $failed_count++;
                        continue; // Skip books with active borrows
                    }
                    
                    // Archive option
                    if (isset($_POST['archive']) && $_POST['archive'] === 'yes') {
                        $stmt = $pdo->prepare("INSERT INTO books_archive SELECT *, NOW() as archived_at FROM books WHERE id = ?");
                        $stmt->execute([$current_book_id]);
                        
                        $stmt = $pdo->prepare("INSERT INTO borrow_records_archive SELECT *, NOW() as archived_at FROM borrow_records WHERE book_id = ?");
                        $stmt->execute([$current_book_id]);
                        
                        $stmt = $pdo->prepare("DELETE FROM borrow_records WHERE book_id = ?");
                        $stmt->execute([$current_book_id]);
                    }
                    
                    // Delete book
                    $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
                    if ($stmt->execute([$current_book_id])) {
                        $deleted_count++;
                        $deleted_titles[] = $book_to_delete['title'];
                        
                        // Log deletion
                        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
                        $log_stmt->execute([
                            getCurrentUserId(),
                            'DELETE_BOOK',
                            "Deleted book: {$book_to_delete['title']} (ID: {$current_book_id})",
                            $_SERVER['REMOTE_ADDR']
                        ]);
                    } else {
                        $failed_count++;
                    }
                } else {
                    $failed_count++;
                }
            }
            
            $pdo->commit();
            
            if ($deleted_count > 0) {
                $_SESSION['success'] = "Successfully deleted {$deleted_count} book(s)";
                if ($failed_count > 0) {
                    $_SESSION['warning'] = "{$failed_count} book(s) could not be deleted (may have active borrows)";
                }
                
                header('Location: books.php');
                exit();
            } else {
                $error = "No books were deleted. They may have active borrows.";
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error during bulk deletion: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Book - Library Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
            margin-top: 0;
        }
        
        .book-info {
            background-color: #f8f9fa;
            border-left: 4px solid #4CAF50;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .book-info h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        
        .book-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .detail-item {
            margin-bottom: 8px;
        }
        
        .detail-label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 2px;
        }
        
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .danger-box {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        
        .btn-back {
            background-color: #17a2b8;
            color: white;
            margin-right: 10px;
        }
        
        .btn-back:hover {
            background-color: #138496;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .hidden {
            display: none;
        }
        
        .info-text {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Delete Book</h1>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (empty($book_id) || !$book): ?>
            <div class="alert alert-error">
                Invalid book ID or book not found.
            </div>
            <div class="button-group">
                <a href="books.php" class="btn btn-back">Back to Books</a>
            </div>
        <?php else: ?>
            <div class="book-info">
                <h3>Book Information</h3>
                <div class="book-details">
                    <div class="detail-item">
                        <span class="detail-label">Title:</span>
                        <?php echo htmlspecialchars($book['title']); ?>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Author:</span>
                        <?php echo htmlspecialchars($book['author']); ?>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">ISBN:</span>
                        <?php echo htmlspecialchars($book['isbn']); ?>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Publisher:</span>
                        <?php echo htmlspecialchars($book['publisher'] ?? 'N/A'); ?>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Total Copies:</span>
                        <?php echo htmlspecialchars($book['total_copies']); ?>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Available Copies:</span>
                        <?php echo htmlspecialchars($book['available_copies']); ?>
                    </div>
                </div>
            </div>
            
            <?php if ($has_active_borrows): ?>
                <div class="danger-box">
                    <strong>⚠️ Cannot Delete!</strong>
                    <p>This book currently has active borrows. Please wait until all copies are returned before deleting.</p>
                </div>
                <div class="button-group">
                    <a href="books.php" class="btn btn-back">Back to Books</a>
                    <a href="book_details.php?id=<?php echo $book_id; ?>" class="btn">View Book Details</a>
                </div>
            <?php else: ?>
                <div class="warning-box">
                    <strong>⚠️ Warning!</strong>
                    <p>You are about to delete this book permanently. This action cannot be undone.</p>
                    <?php if ($book['total_copies'] > 0): ?>
                        <p><strong>Note:</strong> This book has <?php echo $book['total_copies']; ?> copies that will be removed from the system.</p>
                    <?php endif; ?>
                </div>
                
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo isset($_GET['return']) ? htmlspecialchars($_GET['return']) : 'books.php'; ?>">
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="archive" name="archive" value="yes" checked>
                        <label for="archive">
                            <strong>Archive book data</strong>
                            <div class="info-text">Keep a copy of this book's data in the archive for record keeping</div>
                        </label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="confirm_delete" name="confirm_delete" required>
                        <label for="confirm_delete">
                            <strong>I understand this action cannot be undone</strong>
                        </label>
                    </div>
                    
                    <div class="button-group">
                        <a href="books.php" class="btn btn-cancel">Cancel</a>
                        <button type="submit" name="delete_book" class="btn btn-delete" onclick="return confirmFinalDelete()">
                            Delete Book Permanently
                        </button>
                    </div>
                </form>
                
                <script>
                    function confirmFinalDelete() {
                        if (!document.getElementById('confirm_delete').checked) {
                            alert('Please confirm that you understand this action cannot be undone.');
                            return false;
                        }
                        
                        return confirm('Are you absolutely sure you want to delete this book? This action is permanent and cannot be reversed.');
                    }
                    
                    // Form validation
                    document.getElementById('deleteForm').addEventListener('submit', function(e) {
                        if (!document.getElementById('confirm_delete').checked) {
                            e.preventDefault();
                            alert('Please confirm that you understand this action cannot be undone.');
                        }
                    });
                </script>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>