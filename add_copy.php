<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin/librarian
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'librarian')) {
    $_SESSION['error'] = "You don't have permission to access this page";
    header("Location: login.php");
    exit();
}

$pageTitle = "Add Book Copy";
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $book_id = trim($_POST['book_id']);
    $copy_number = trim($_POST['copy_number']);
    $condition = trim($_POST['condition']);
    $purchase_date = trim($_POST['purchase_date']);
    $location = trim($_POST['location']);
    $notes = trim($_POST['notes']);
    $status = 'available'; // Default status
    
    // Validate inputs
    if (empty($book_id) || empty($copy_number)) {
        $error = "Book ID and Copy Number are required";
    } else {
        // Check if book exists
        $check_book = $conn->prepare("SELECT id, title FROM books WHERE id = ?");
        $check_book->bind_param("i", $book_id);
        $check_book->execute();
        $book_result = $check_book->get_result();
        
        if ($book_result->num_rows == 0) {
            $error = "Book ID not found in database";
        } else {
            // Check if copy number already exists for this book
            $check_copy = $conn->prepare("SELECT id FROM book_copies WHERE book_id = ? AND copy_number = ?");
            $check_copy->bind_param("is", $book_id, $copy_number);
            $check_copy->execute();
            $copy_result = $check_copy->get_result();
            
            if ($copy_result->num_rows > 0) {
                $error = "Copy number already exists for this book";
            } else {
                // Insert new copy
                $stmt = $conn->prepare("INSERT INTO book_copies (book_id, copy_number, condition, purchase_date, location, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("issssss", $book_id, $copy_number, $condition, $purchase_date, $location, $notes, $status);
                
                if ($stmt->execute()) {
                    $success = "Book copy added successfully!";
                    
                    // Reset form or keep values as needed
                    if (isset($_POST['add_another']) && $_POST['add_another'] == '1') {
                        // Keep book_id for adding another copy
                        $book_id = $book_id;
                        $copy_number = '';
                    } else {
                        // Clear form
                        $book_id = $copy_number = $condition = $purchase_date = $location = $notes = '';
                    }
                } else {
                    $error = "Error adding book copy: " . $conn->error;
                }
            }
        }
    }
}

// Get all books for dropdown
$books_query = "SELECT id, title, author, isbn FROM books ORDER BY title";
$books_result = $conn->query($books_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Library Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border: none;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .btn-outline-secondary {
            color: var(--primary-color);
            border-color: var(--border-color);
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .nav-tabs .nav-link.active {
            background-color: white;
            border-color: var(--border-color) var(--border-color) white;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .quick-add-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .book-info-card {
            background-color: white;
            border-left: 4px solid var(--secondary-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-top: 4px solid var(--secondary-color);
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .copy-badge {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .condition-excellent { background-color: #d4edda; color: #155724; }
        .condition-good { background-color: #d1ecf1; color: #0c5460; }
        .condition-fair { background-color: #fff3cd; color: #856404; }
        .condition-poor { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-copy me-2"></i><?php echo $pageTitle; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="manage_copies.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-list"></i> Manage Copies
                        </a>
                        <a href="books.php" class="btn btn-outline-secondary">
                            <i class="fas fa-book"></i> View All Books
                        </a>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Left Column: Add Copy Form -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Book Copy</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" id="addCopyForm">
                                    <div class="row">
                                        <!-- Book Selection -->
                                        <div class="col-md-6 mb-3">
                                            <label for="book_id" class="form-label">Select Book <span class="text-danger">*</span></label>
                                            <select class="form-select" id="book_id" name="book_id" required>
                                                <option value="">-- Select Book --</option>
                                                <?php while($book = $books_result->fetch_assoc()): ?>
                                                    <option value="<?php echo $book['id']; ?>" 
                                                        <?php echo (isset($book_id) && $book_id == $book['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($book['title']); ?> 
                                                        (ISBN: <?php echo $book['isbn']; ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <div class="form-text">Can't find the book? <a href="add_book.php">Add new book first</a></div>
                                        </div>
                                        
                                        <!-- Copy Number -->
                                        <div class="col-md-6 mb-3">
                                            <label for="copy_number" class="form-label">Copy Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="copy_number" name="copy_number" 
                                                   value="<?php echo isset($copy_number) ? htmlspecialchars($copy_number) : ''; ?>" 
                                                   placeholder="e.g., C001, B2-001" required>
                                            <div class="form-text">Unique identifier for this copy</div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <!-- Condition -->
                                        <div class="col-md-6 mb-3">
                                            <label for="condition" class="form-label">Physical Condition</label>
                                            <select class="form-select" id="condition" name="condition">
                                                <option value="excellent" <?php echo (isset($condition) && $condition == 'excellent') ? 'selected' : ''; ?>>Excellent</option>
                                                <option value="good" <?php echo (isset($condition) && $condition == 'good') ? 'selected' : ''; ?>>Good</option>
                                                <option value="fair" <?php echo (isset($condition) && $condition == 'fair') ? 'selected' : ''; ?>>Fair</option>
                                                <option value="poor" <?php echo (isset($condition) && $condition == 'poor') ? 'selected' : ''; ?>>Poor</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Purchase Date -->
                                        <div class="col-md-6 mb-3">
                                            <label for="purchase_date" class="form-label">Purchase Date</label>
                                            <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                                   value="<?php echo isset($purchase_date) ? $purchase_date : date('Y-m-d'); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <!-- Location -->
                                        <div class="col-md-6 mb-3">
                                            <label for="location" class="form-label">Location/Shelf</label>
                                            <input type="text" class="form-control" id="location" name="location" 
                                                   value="<?php echo isset($location) ? htmlspecialchars($location) : ''; ?>" 
                                                   placeholder="e.g., Shelf A5, Row 3">
                                            <div class="form-text">Where this copy is stored</div>
                                        </div>
                                        
                                        <!-- Status (readonly) -->
                                        <div class="col-md-6 mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <input type="text" class="form-control" id="status" value="Available" readonly style="background-color: #e9ecef;">
                                            <div class="form-text">New copies are automatically set as Available</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Notes -->
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                                  placeholder="Any special notes about this copy..."><?php echo isset($notes) ? htmlspecialchars($notes) : ''; ?></textarea>
                                        <div class="form-text">Optional: damage notes, special markings, etc.</div>
                                    </div>
                                    
                                    <!-- Form Actions -->
                                    <div class="d-flex justify-content-between align-items-center mt-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="add_another" name="add_another" value="1" checked>
                                            <label class="form-check-label" for="add_another">
                                                Add another copy after this one
                                            </label>
                                        </div>
                                        
                                        <div>
                                            <button type="reset" class="btn btn-outline-secondary me-2">
                                                <i class="fas fa-redo"></i> Reset
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Add Book Copy
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-number" id="totalBooks">0</div>
                                    <div class="stat-label">Total Books</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-number" id="totalCopies">0</div>
                                    <div class="stat-label">Total Copies</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-number" id="availableCopies">0</div>
                                    <div class="stat-label">Available Copies</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Book Details & Recent Copies -->
                    <div class="col-lg-4">
                        <!-- Selected Book Details -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Book Details</h5>
                            </div>
                            <div class="card-body" id="bookDetails">
                                <div class="text-center text-muted">
                                    <i class="fas fa-book fa-3x mb-3"></i>
                                    <p>Select a book to view details</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Copies Added -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Copies Added</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush" id="recentCopies">
                                    <?php
                                    // Fetch recent copies
                                    $recent_query = "SELECT bc.copy_number, b.title, bc.created_at, bc.status 
                                                    FROM book_copies bc 
                                                    JOIN books b ON bc.book_id = b.id 
                                                    ORDER BY bc.created_at DESC LIMIT 5";
                                    $recent_result = $conn->query($recent_query);
                                    
                                    if ($recent_result->num_rows > 0) {
                                        while($recent = $recent_result->fetch_assoc()) {
                                            $status_class = $recent['status'] == 'available' ? 'bg-success' : 'bg-warning';
                                            echo '<div class="list-group-item d-flex justify-content-between align-items-center">';
                                            echo '<div>';
                                            echo '<h6 class="mb-1">' . htmlspecialchars($recent['title']) . '</h6>';
                                            echo '<small class="text-muted">Copy: ' . $recent['copy_number'] . '</small>';
                                            echo '</div>';
                                            echo '<span class="badge ' . $status_class . '">' . ucfirst($recent['status']) . '</span>';
                                            echo '</div>';
                                        }
                                    } else {
                                        echo '<p class="text-muted text-center">No copies added yet</p>';
                                    }
                                    ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="manage_copies.php" class="btn btn-sm btn-outline-primary">View All Copies</a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="mt-4">
                            <h6 class="text-muted mb-3">Quick Actions</h6>
                            <div class="d-grid gap-2">
                                <a href="bulk_add_copies.php" class="btn btn-outline-primary text-start">
                                    <i class="fas fa-file-import me-2"></i> Bulk Add Copies
                                </a>
                                <a href="scan_isbn.php" class="btn btn-outline-primary text-start">
                                    <i class="fas fa-barcode me-2"></i> Scan ISBN to Add
                                </a>
                                <a href="print_barcode.php" class="btn btn-outline-primary text-start">
                                    <i class="fas fa-print me-2"></i> Print Barcode Label
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Load book details when book is selected
        $('#book_id').change(function() {
            var bookId = $(this).val();
            if (bookId) {
                $.ajax({
                    url: 'ajax/get_book_details.php',
                    type: 'GET',
                    data: {id: bookId},
                    success: function(response) {
                        $('#bookDetails').html(response);
                        loadBookStats(bookId);
                    }
                });
            } else {
                $('#bookDetails').html('<div class="text-center text-muted"><i class="fas fa-book fa-3x mb-3"></i><p>Select a book to view details</p></div>');
                resetStats();
            }
        });
        
        // Auto-generate copy number if empty
        $('#copy_number').blur(function() {
            if (!$(this).val()) {
            var bookId = $('#book_id').val();
            if (bookId) {
                    // Generate copy number like B001, B002, etc.
                    $.ajax({
                    url: 'ajax/generate_copy_number.php',
                    type: 'GET',
                    data: {book_id: bookId},
                        success: function(response) {
                        $('#copy_number').val(response);
                    }
                });
            }
            }
        });
        
        // Load initial stats
        loadOverallStats();
        
        // Form validation
        $('#addCopyForm').submit(function(e) {
            var copyNumber = $('#copy_number').val();
            if (!copyNumber.match(/^[A-Za-z0-9\-_]+$/)) {
                alert('Copy number can only contain letters, numbers, hyphens and underscores');
                e.preventDefault();
                return false;
            }
            return true;
        });
    });
    
    function loadBookStats(bookId) {
        $.ajax({
            url: 'ajax/get_book_stats.php',
            type: 'GET',
            data: {book_id: bookId},
            success: function(stats) {
                $('#totalCopies').text(stats.total_copies || 0);
                $('#availableCopies').text(stats.available_copies || 0);
            }
        });
    }
    
    function loadOverallStats() {
        $.ajax({
            url: 'ajax/get_overall_stats.php',
            type: 'GET',
            success: function(stats) {
                $('#totalBooks').text(stats.total_books || 0);
                if (!$('#totalCopies').text() || $('#totalCopies').text() == '0') {
                    $('#totalCopies').text(stats.total_copies || 0);
                }
                if (!$('#availableCopies').text() || $('#availableCopies').text() == '0') {
                    $('#availableCopies').text(stats.available_copies || 0);
                }
            }
        });
    }
    
    function resetStats() {
        loadOverallStats();
    }
    </script>
</body>
</html>