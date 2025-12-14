<?php
// book_details.php - View book details
require_once 'config.php';
require_once 'auth.php';
require_once 'functions.php';
requireLogin();

$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($book_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid book ID.';
    header("Location: index.php");
    exit();
}

// Get book details
try {
    $stmt = $pdo->prepare("SELECT b.*, u.full_name as owner_name 
                          FROM books b 
                          JOIN users u ON b.owner_id = u.id 
                          WHERE b.id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch();
    
    if (!$book) {
        $_SESSION['flash_error'] = 'Book not found.';
        header("Location: index.php");
        exit();
    }
    
    $page_title = $book['title'] . ' - ' . SITE_NAME;
    
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Error loading book: ' . $e->getMessage();
    header("Location: index.php");
    exit();
}
?>
<?php include 'header.php'; ?>
<!-- Display book details -->
<?php include 'footer.php'; ?>