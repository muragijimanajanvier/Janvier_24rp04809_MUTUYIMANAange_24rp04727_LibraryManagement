<?php
// manage_books.php - Library Books Management
session_start();
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in and is admin/librarian
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'librarian') {
    header('Location: login.php');
    exit();
}

// Handle form submissions
$message = '';
$message_type = '';

// Add Book
if (isset($_POST['add_book'])) {
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $isbn = $_POST['isbn'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $copies = $_POST['copies'] ?? 1;
    $publisher = $_POST['publisher'] ?? '';
    $publication_year = $_POST['publication_year'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (!empty($title) && !empty($author)) {
        $sql = "INSERT INTO books (title, author, isbn, category_id, total_copies, available_copies, 
                publisher, publication_year, description, added_by, added_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiissssi", $title, $author, $isbn, $category_id, $copies, $copies, 
                         $publisher, $publication_year, $description, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $message = "Book added successfully!";
            $message_type = "success";
        } else {
            $message = "Error adding book: " . $stmt->error;
            $message_type = "error";
        }
    } else {
        $message = "Title and Author are required!";
        $message_type = "error";
    }
}

// Update Book
if (isset($_POST['update_book'])) {
    $book_id = $_POST['book_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $isbn = $_POST['isbn'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $publisher = $_POST['publisher'] ?? '';
    $publication_year = $_POST['publication_year'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (!empty($book_id) && !empty($title) && !empty($author)) {
        $sql = "UPDATE books SET title = ?, author = ?, isbn = ?, category_id = ?, 
                publisher = ?, publication_year = ?, description = ?, 
                updated_by = ?, updated_date = NOW() WHERE book_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssissssi", $title, $author, $isbn, $category_id, 
                         $publisher, $publication_year, $description, $_SESSION['user_id'], $book_id);
        
        if ($stmt->execute()) {
            $message = "Book updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating book: " . $stmt->error;
            $message_type = "error";
        }
    }
}

// Delete Book
if (isset($_GET['delete'])) {
    $book_id = $_GET['delete'];
    
    // Check if book has active borrowings
    $check_sql = "SELECT COUNT(*) as active_borrowings FROM borrowings 
                  WHERE book_id = ? AND return_date IS NULL";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $book_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_data = $check_result->fetch_assoc();
    
    if ($check_data['active_borrowings'] == 0) {
        $sql = "DELETE FROM books WHERE book_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $book_id);
        
        if ($stmt->execute()) {
            $message = "Book deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting book: " . $stmt->error;
            $message_type = "error";
        }
    } else {
        $message = "Cannot delete book with active borrowings!";
        $message_type = "error";
    }
}

// Search functionality
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query for books with filters
$query = "SELECT b.*, c.category_name, 
          (SELECT COUNT(*) FROM borrowings WHERE book_id = b.book_id AND return_date IS NULL) as borrowed_count
          FROM books b 
          LEFT JOIN categories c ON b.category_id = c.category_id 
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if (!empty($category_filter)) {
    $query .= " AND b.category_id = ?";
    $params[] = $category_filter;
    $types .= "i";
}

if (!empty($status_filter)) {
    if ($status_filter == 'available') {
        $query .= " AND b.available_copies > 0";
    } elseif ($status_filter == 'unavailable') {
        $query .= " AND b.available_copies = 0";
    }
}

// Count total records for pagination
$count_query = str_replace("b.*, c.category_name", "COUNT(*) as total", $query);
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Get categories for filter dropdown
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// Get books with pagination
$query .= " ORDER BY b.title LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - Library Lending System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .nav {
            background: var(--dark);
            padding: 15px;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .nav a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 50px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav a:hover, .nav a.active {
            background: var(--secondary);
            transform: translateY(-2px);
        }
        
        .content {
            padding: 30px;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .card h2 {
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--secondary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--secondary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #219653;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 14px;
        }
        
        .search-box {
            background: var(--light);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background: var(--primary);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-unavailable {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .page-link {
            padding: 8px 16px;
            background: var(--light);
            border-radius: 5px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
        }
        
        .page-link:hover, .page-link.active {
            background: var(--secondary);
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--dark);
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 10px;
            }
            
            .content {
                padding: 15px;
            }
            
            .nav {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-book"></i> Library Lending System</h1>
            <p>Manage Books - Add, Update, Delete, and Search Books</p>
        </div>
        
        <div class="nav">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="manage_books.php" class="active"><i class="fas fa-book"></i> Manage Books</a>
            <a href="add_book.php"><i class="fas fa-plus-circle"></i> Add Book</a>
            <a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
            <a href="manage_requests.php"><i class="fas fa-exchange-alt"></i> Manage Requests</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Search and Filter Section -->
            <div class="card search-box">
                <h2><i class="fas fa-search"></i> Search Books</h2>
                <form method="GET" class="search-form">
                    <div class="form-group">
                        <label>Search:</label>
                        <input type="text" name="search" class="form-control" placeholder="Title, Author, or ISBN" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Category:</label>
                        <select name="category" class="form-control">
                            <option value="">All Categories</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo $cat['category_id']; ?>" <?php echo ($category_filter == $cat['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status:</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="available" <?php echo ($status_filter == 'available') ? 'selected' : ''; ?>>Available</option>
                            <option value="unavailable" <?php echo ($status_filter == 'unavailable') ? 'selected' : ''; ?>>Unavailable</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        <a href="manage_books.php" class="btn btn-danger"><i class="fas fa-times"></i> Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- Add New Book Form -->
            <div class="card">
                <h2><i class="fas fa-plus-circle"></i> Add New Book</h2>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Author *</label>
                            <input type="text" name="author" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>ISBN</label>
                            <input type="text" name="isbn" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" class="form-control">
                                <option value="">Select Category</option>
                                <?php 
                                $cats = $conn->query("SELECT * FROM categories ORDER BY category_name");
                                while ($cat = $cats->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['category_id']; ?>">
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Number of Copies</label>
                            <input type="number" name="copies" class="form-control" value="1" min="1">
                        </div>
                        
                        <div class="form-group">
                            <label>Publisher</label>
                            <input type="text" name="publisher" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>Publication Year</label>
                            <input type="number" name="publication_year" class="form-control" min="1000" max="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" name="add_book" class="btn btn-success">
                        <i class="fas fa-save"></i> Add Book
                    </button>
                </form>
            </div>
            
            <!-- Books List -->
            <div class="card">
                <h2><i class="fas fa-list"></i> Books List (<?php echo $total_rows; ?> books found)</h2>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>ISBN</th>
                                <th>Category</th>
                                <th>Copies</th>
                                <th>Available</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($book = $result->fetch_assoc()): ?>
                                    <?php 
                                    $available = $book['available_copies'] > 0;
                                    $borrowed_count = $book['borrowed_count'] ?? 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $book['book_id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($book['title']); ?></strong>
                                            <?php if ($book['publisher']): ?>
                                                <br><small>Publisher: <?php echo htmlspecialchars($book['publisher']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($book['author']); ?></td>
                                        <td><?php echo $book['isbn'] ?: 'N/A'; ?></td>
                                        <td><?php echo $book['category_name'] ?: 'Uncategorized'; ?></td>
                                        <td><?php echo $book['total_copies']; ?></td>
                                        <td><?php echo $book['available_copies']; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $available ? 'status-available' : 'status-unavailable'; ?>">
                                                <?php echo $available ? 'Available' : 'Unavailable'; ?>
                                            </span>
                                            <?php if ($borrowed_count > 0): ?>
                                                <br><small><?php echo $borrowed_count; ?> borrowed</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick="editBook(<?php echo $book['book_id']; ?>)" 
                                                        class="btn btn-primary btn-sm">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="?delete=<?php echo $book['book_id']; ?>" 
                                                   class="btn btn-danger btn-sm"
                                                   onclick="return confirm('Are you sure you want to delete this book?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                                <a href="book_details.php?id=<?php echo $book['book_id']; ?>" 
                                                   class="btn btn-success btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-book-open" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
                                        <h3>No books found</h3>
                                        <p>Try adjusting your search criteria or add new books.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>"
                               class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Edit Book Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Book</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="editFormContainer">
                <!-- Edit form will be loaded here via AJAX -->
            </div>
        </div>
    </div>
    
    <script>
        // Edit Book Modal
        function editBook(bookId) {
            // Show loading
            document.getElementById('editFormContainer').innerHTML = 
                '<div style="text-align: center; padding: 40px;">Loading...</div>';
            
            // Show modal
            document.getElementById('editModal').style.display = 'block';
            
            // Load edit form via AJAX
            fetch('ajax/get_book.php?id=' + bookId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('editFormContainer').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('editFormContainer').innerHTML = 
                        '<div class="message error">Error loading book details</div>';
                });
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Form submission for edit modal
        function submitEditForm(event) {
            event.preventDefault();
            
            var form = event.target;
            var formData = new FormData(form);
            
            fetch('ajax/update_book.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating book');
            });
        }
    </script>
</body>
</html>