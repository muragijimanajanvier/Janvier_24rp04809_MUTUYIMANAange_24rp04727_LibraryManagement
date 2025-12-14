<?php
// ajax/mark_as_read.php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'reader') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $book_id = $data['book_id'] ?? 0;
    
    try {
        // Check if already marked as read
        $check_sql = "SELECT id FROM reading_history WHERE user_id = ? AND book_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$_SESSION['user_id'], $book_id]);
        
        if ($check_stmt->rowCount() == 0) {
            $insert_sql = "INSERT INTO reading_history (user_id, book_id) VALUES (?, ?)";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([$_SESSION['user_id'], $book_id]);
            
            echo json_encode(['success' => true, 'message' => 'Book marked as read']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Already marked as read']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>