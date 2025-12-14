<?php
// edit_book.php - For editing books
require_once 'config.php';
require_once 'auth.php';
require_once 'functions.php';
requireLogin();

// Only lenders can access this page
if ($_SESSION['role'] !== 'lender') {
    $_SESSION['flash_error'] = 'Access denied. Only lenders can edit books.';
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = 'Edit Book - ' . SITE_NAME;
$page_css = 'forms.css';
$errors = [];
$success = '';

// Get book ID from URL
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($book_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid book ID.';
    header("Location: my_books.php");
    exit();
}

// Check if book belongs to this lender
try {
    $check_stmt = $pdo->prepare("SELECT * FROM books WHERE id = ? AND owner_id = ?");
    $check_stmt->execute([$book_id, $user_id]);
    $book = $check_stmt->fetch();
    
    if (!$book) {
        $_SESSION['flash_error'] = 'Book not found or you don\'t have permission to edit it.';
        header("Location: my_books.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Error loading book: ' . $e->getMessage();
    header("Location: my_books.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_book'])) {
    // Get form data
    $title = sanitize($_POST['title'] ?? '');
    $author = sanitize($_POST['author'] ?? '');
    $isbn = sanitize($_POST['isbn'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $publisher = sanitize($_POST['publisher'] ?? '');
    $year = sanitize($_POST['year'] ?? '');
    $pages = sanitize($_POST['pages'] ?? '');
    $language = sanitize($_POST['language'] ?? 'English');
    $condition = sanitize($_POST['condition'] ?? 'good');
    $status = sanitize($_POST['status'] ?? 'available');
    
    // Validation
    if (empty($title)) {
        $errors[] = 'Book title is required';
    }
    
    if (empty($author)) {
        $errors[] = 'Author name is required';
    }
    
    // Handle file upload if new image is provided
    $cover_image = $book['cover_image'];
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = handleFileUpload('cover_image', 'covers/');
        if ($upload_result['success']) {
            // Delete old cover image if exists
            if ($cover_image && file_exists('covers/' . $cover_image)) {
                unlink('covers/' . $cover_image);
            }
            $cover_image = $upload_result['filename'];
        } else {
            $errors[] = $upload_result['error'];
        }
    }
    
    // Update if no errors
    if (empty($errors)) {
        try {
            $sql = "UPDATE books SET 
                    title = ?, author = ?, isbn = ?, description = ?, 
                    category = ?, publisher = ?, year = ?, pages = ?, 
                    language = ?, condition = ?, status = ?, cover_image = ?, 
                    updated_at = NOW() 
                    WHERE id = ? AND owner_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $title, $author, $isbn, $description,
                $category, $publisher, $year ?: null, $pages ?: null,
                $language, $condition, $status, $cover_image,
                $book_id, $user_id
            ]);
            
            $success = 'Book updated successfully!';
            header("Location: my_books.php");
            exit();
            
        } catch (PDOException $e) {
            $errors[] = 'Failed to update book: ' . $e->getMessage();
        }
    }
} else {
    // Pre-fill form with existing data
    $title = $book['title'];
    $author = $book['author'];
    $isbn = $book['isbn'];
    $description = $book['description'];
    $category = $book['category'];
    $publisher = $book['publisher'];
    $year = $book['year'];
    $pages = $book['pages'];
    $language = $book['language'];
    $condition = $book['condition'];
    $status = $book['status'];
}

// Rest of the form is similar to add_book.php with pre-filled values
?>
<?php include 'header.php'; ?>
<!-- Similar form to add_book.php but with pre-filled values -->
<?php include 'footer.php'; ?>