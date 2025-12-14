<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    $book_id = intval($_GET['id']);
    
    $query = "SELECT b.*, 
                     COUNT(bc.id) as total_copies,
                     SUM(CASE WHEN bc.status = 'available' THEN 1 ELSE 0 END) as available_copies
              FROM books b
              LEFT JOIN book_copies bc ON b.id = bc.book_id
              WHERE b.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $book = $result->fetch_assoc();
        
        echo '<div class="book-info-card">';
        echo '<h5>' . htmlspecialchars($book['title']) . '</h5>';
        echo '<p class="text-muted mb-2">by ' . htmlspecialchars($book['author']) . '</p>';
        echo '<hr>';
        echo '<div class="row">';
        echo '<div class="col-6"><small>ISBN:</small><br><strong>' . $book['isbn'] . '</strong></div>';
        echo '<div class="col-6"><small>Category:</small><br><strong>' . $book['category'] . '</strong></div>';
        echo '</div>';
        echo '<div class="row mt-2">';
        echo '<div class="col-6"><small>Total Copies:</small><br><span class="badge bg-primary">' . $book['total_copies'] . '</span></div>';
        echo '<div class="col-6"><small>Available:</small><br><span class="badge bg-success">' . $book['available_copies'] . '</span></div>';
        echo '</div>';
        if (!empty($book['publisher'])) {
            echo '<div class="mt-2"><small>Publisher:</small><br>' . htmlspecialchars($book['publisher']) . '</div>';
        }
        if (!empty($book['publication_year'])) {
            echo '<div class="mt-1"><small>Year:</small> ' . $book['publication_year'] . '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="alert alert-warning">Book not found</div>';
    }
}
?>