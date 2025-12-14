<?php
// request_borrow.php - COMBINED SOLUTION
require_once 'config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a borrower
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') != 'borrower') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// ========== HANDLE POST REQUEST (AJAX) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // This is an AJAX request to process borrowing
    header('Content-Type: application/json');
    
    // Check if it's evening (5 PM or later)
    $current_hour = (int)date('H');
    if ($current_hour < 17) {
        echo json_encode([
            'success' => false,
            'message' => 'Borrowing requests can only be made in the evening (after 5:00 PM). Current time: ' . date('h:i A')
        ]);
        exit();
    }
    
    // Get book ID from POST or JSON
    $book_id = $_POST['book_id'] ?? 0;
    
    // Also check for JSON input
    $input = file_get_contents('php://input');
    if ($input && $book_id == 0) {
        $data = json_decode($input, true);
        $book_id = $data['book_id'] ?? 0;
    }
    
    if ($book_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid book ID']);
        exit();
    }
    
    try {
        // Check book availability
        $book_sql = "SELECT id, title, author, available FROM books WHERE id = ?";
        $book_stmt = $pdo->prepare($book_sql);
        $book_stmt->execute([$book_id]);
        $book = $book_stmt->fetch();
        
        if (!$book) {
            echo json_encode(['success' => false, 'message' => 'Book not found']);
            exit();
        }
        
        if ($book['available'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'Book is not available']);
            exit();
        }
        
        // Check for existing requests
        $check_sql = "SELECT id FROM borrowings 
                     WHERE user_id = ? AND book_id = ? 
                     AND status IN ('pending', 'approved', 'borrowed')";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$user_id, $book_id]);
        
        if ($check_stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'You already have a request for this book']);
            exit();
        }
        
        // Calculate dates
        $borrow_date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+14 days'));
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Create borrow request
        $insert_sql = "INSERT INTO borrowings 
                      (user_id, book_id, borrow_date, due_date, status, request_time) 
                      VALUES (?, ?, ?, ?, 'pending', NOW())";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([$user_id, $book_id, $borrow_date, $due_date]);
        
        // Update book availability
        $update_sql = "UPDATE books SET available = available - 1 WHERE id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$book_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Borrow request submitted successfully!',
            'due_date' => $due_date,
            'book_title' => $book['title']
        ]);
        exit();
        
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit();
    }
}

// ========== HANDLE GET REQUEST (DISPLAY PAGE) ==========
// This is the HTML page display
try {
    $sql = "SELECT id, title, author, isbn, available FROM books WHERE available > 0 ORDER BY title";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $books = $stmt->fetchAll();
} catch (PDOException $e) {
    $books = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Books - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .time-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .book-card {
            border: none;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
        }
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .btn-borrow {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-borrow:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-borrow:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-book me-2"></i>Library System
            </a>
            <div class="d-flex align-items-center">
                <span class="text-light me-3">Welcome, <?php echo htmlspecialchars($username); ?></span>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Time Banner -->
        <div class="time-banner">
            <h2><i class="bi bi-clock"></i> Evening Borrowing Only</h2>
            <p class="lead mb-3">Borrowing requests are accepted only between <strong>5:00 PM and 11:59 PM</strong></p>
            <div class="d-flex justify-content-center align-items-center">
                <div class="me-4">
                    <i class="bi bi-calendar-event me-2"></i>
                    <strong id="currentDate"><?php echo date('F j, Y'); ?></strong>
                </div>
                <div>
                    <i class="bi bi-clock me-2"></i>
                    <strong id="currentTime"><?php echo date('h:i:s A'); ?></strong>
                </div>
            </div>
        </div>

        <!-- Available Books -->
        <h2 class="mb-4">Available Books</h2>
        
        <?php if (empty($books)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No books available for borrowing at the moment.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($books as $book): ?>
                    <div class="col-md-4 col-lg-3 mb-4">
                        <div class="book-card">
                            <h5><?php echo htmlspecialchars($book['title']); ?></h5>
                            <p class="text-muted mb-2">
                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($book['author']); ?>
                            </p>
                            <p class="mb-2">
                                <i class="bi bi-upc-scan"></i> <?php echo htmlspecialchars($book['isbn']); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="badge bg-success">
                                    <?php echo $book['available']; ?> available
                                </span>
                                <button class="btn btn-borrow" 
                                        data-book-id="<?php echo $book['id']; ?>"
                                        data-book-title="<?php echo htmlspecialchars($book['title']); ?>">
                                    <i class="bi bi-book-plus"></i> Borrow
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript -->
    <script>
        // Update current time
        function updateCurrentTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = 
                now.toLocaleTimeString('en-US', {hour12: true});
        }
        setInterval(updateCurrentTime, 1000);

        // Handle borrow button clicks
        document.addEventListener('DOMContentLoaded', function() {
            // Check if it's evening
            const now = new Date();
            const currentHour = now.getHours();
            
            document.querySelectorAll('.btn-borrow').forEach(button => {
                // Disable if before 5 PM
                if (currentHour < 17) {
                    button.disabled = true;
                    button.innerHTML = '<i class="bi bi-clock"></i> After 5 PM';
                }
                
                button.addEventListener('click', function() {
                    const bookId = this.getAttribute('data-book-id');
                    const bookTitle = this.getAttribute('data-book-title');
                    
                    // Double-check time
                    const currentHour = new Date().getHours();
                    if (currentHour < 17) {
                        alert('Borrowing is only allowed after 5:00 PM.\nCurrent time: ' + 
                              new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
                        return;
                    }
                    
                    // Confirm request
                    if (confirm(`Request to borrow "${bookTitle}"?\n\nDue date: 14 days from today.`)) {
                        sendBorrowRequest(bookId, bookTitle, this);
                    }
                });
            });
        });

        // Send AJAX request to THE SAME FILE (request_borrow.php)
        async function sendBorrowRequest(bookId, bookTitle, buttonElement) {
            // Show loading
            if (buttonElement) {
                buttonElement.disabled = true;
                buttonElement.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
            }
            
            try {
                // Send POST request to THE SAME FILE
                const response = await fetch('request_borrow.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ book_id: bookId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(`Success! ${data.message}\nBook: ${bookTitle}\nDue Date: ${data.due_date}`);
                    
                    // Update button
                    if (buttonElement) {
                        buttonElement.disabled = true;
                        buttonElement.innerHTML = '<i class="bi bi-check"></i> Requested';
                        buttonElement.classList.remove('btn-borrow');
                        buttonElement.classList.add('btn-success');
                    }
                    
                    // Update availability count
                    const badge = buttonElement?.closest('.book-card')?.querySelector('.badge');
                    if (badge) {
                        const current = parseInt(badge.textContent);
                        const newCount = Math.max(0, current - 1);
                        badge.textContent = newCount + ' available';
                        if (newCount === 0) {
                            badge.className = 'badge bg-danger';
                        }
                    }
                    
                } else {
                    alert(`Error: ${data.message}`);
                    if (buttonElement) {
                        buttonElement.disabled = false;
                        buttonElement.innerHTML = '<i class="bi bi-book-plus"></i> Borrow';
                    }
                }
                
            } catch (error) {
                alert('Network error. Please try again.');
                console.error('Error:', error);
                if (buttonElement) {
                    buttonElement.disabled = false;
                    buttonElement.innerHTML = '<i class="bi bi-book-plus"></i> Borrow';
                }
            }
        }
    </script>
</body>
</html>