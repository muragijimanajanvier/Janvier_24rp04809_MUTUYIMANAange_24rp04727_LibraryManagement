<?php
// add_book.php - For lenders to add books
require_once 'config.php';
require_once 'auth.php';
require_once 'functions.php';
requireLogin();

// Only lenders can access this page
if ($_SESSION['role'] !== 'lender') {
    $_SESSION['flash_error'] = 'Access denied. Only lenders can add books.';
    header("Location: dashboard.php");
    exit();
}

$page_title = 'Add New Book - ' . SITE_NAME;
$page_css = 'forms.css';
$page_js = 'forms.js';

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';
$form_data = [
    'title' => '',
    'author' => '',
    'isbn' => '',
    'description' => '',
    'category' => '',
    'publisher' => '',
    'year' => '',
    'pages' => '',
    'language' => 'English',
    'condition' => 'good',
    'status' => 'available'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_book'])) {
    // Get form data
    $form_data['title'] = sanitize($_POST['title'] ?? '');
    $form_data['author'] = sanitize($_POST['author'] ?? '');
    $form_data['isbn'] = sanitize($_POST['isbn'] ?? '');
    $form_data['description'] = sanitize($_POST['description'] ?? '');
    $form_data['category'] = sanitize($_POST['category'] ?? '');
    $form_data['publisher'] = sanitize($_POST['publisher'] ?? '');
    $form_data['year'] = sanitize($_POST['year'] ?? '');
    $form_data['pages'] = sanitize($_POST['pages'] ?? '');
    $form_data['language'] = sanitize($_POST['language'] ?? 'English');
    $form_data['condition'] = sanitize($_POST['condition'] ?? 'good');
    $form_data['status'] = sanitize($_POST['status'] ?? 'available');
    
    // Handle file upload
    $cover_image = '';
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = handleFileUpload('cover_image', 'covers/');
        if ($upload_result['success']) {
            $cover_image = $upload_result['filename'];
        } else {
            $errors[] = $upload_result['error'];
        }
    }
    
    // Validation
    if (empty($form_data['title'])) {
        $errors[] = 'Book title is required';
    }
    
    if (empty($form_data['author'])) {
        $errors[] = 'Author name is required';
    }
    
    if (!empty($form_data['year']) && !is_numeric($form_data['year'])) {
        $errors[] = 'Publication year must be a number';
    }
    
    if (!empty($form_data['pages']) && !is_numeric($form_data['pages'])) {
        $errors[] = 'Number of pages must be a number';
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // Check if books table has owner_id column
            $check_stmt = $pdo->query("SHOW COLUMNS FROM books LIKE 'owner_id'");
            $has_owner_id = ($check_stmt->rowCount() > 0);
            
            if ($has_owner_id) {
                $sql = "INSERT INTO books (owner_id, title, author, isbn, description, category, publisher, 
                        year, pages, language, condition, status, cover_image) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = [
                    $user_id,
                    $form_data['title'],
                    $form_data['author'],
                    $form_data['isbn'],
                    $form_data['description'],
                    $form_data['category'],
                    $form_data['publisher'],
                    $form_data['year'] ?: null,
                    $form_data['pages'] ?: null,
                    $form_data['language'],
                    $form_data['condition'],
                    $form_data['status'],
                    $cover_image
                ];
            } else {
                $sql = "INSERT INTO books (title, author, isbn, description, category, publisher, 
                        year, pages, language, condition, status, cover_image) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = [
                    $form_data['title'],
                    $form_data['author'],
                    $form_data['isbn'],
                    $form_data['description'],
                    $form_data['category'],
                    $form_data['publisher'],
                    $form_data['year'] ?: null,
                    $form_data['pages'] ?: null,
                    $form_data['language'],
                    $form_data['condition'],
                    $form_data['status'],
                    $cover_image
                ];
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $book_id = $pdo->lastInsertId();
            $success = "Book '{$form_data['title']}' added successfully!";
            
            // Clear form data
            $form_data = [
                'title' => '',
                'author' => '',
                'isbn' => '',
                'description' => '',
                'category' => '',
                'publisher' => '',
                'year' => '',
                'pages' => '',
                'language' => 'English',
                'condition' => 'good',
                'status' => 'available'
            ];
            
        } catch (PDOException $e) {
            $errors[] = 'Failed to add book: ' . $e->getMessage();
        }
    }
}
?>
<?php include 'header.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Book to Your Collection</h3>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h5>Please fix the following errors:</h5>
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success); ?>
                            <div class="mt-2">
                                <a href="add_book.php" class="btn btn-outline-success btn-sm me-2">
                                    <i class="fas fa-plus me-1"></i>Add Another Book
                                </a>
                                <a href="my_books.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-book me-1"></i>View My Books
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data" id="addBookForm" novalidate>
                        <!-- Book Information -->
                        <div class="mb-4">
                            <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-book me-2"></i>Book Information</h5>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="title" class="form-label">Book Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($form_data['title']); ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter the book title.</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="isbn" class="form-label">ISBN</label>
                                    <input type="text" class="form-control" id="isbn" name="isbn" 
                                           value="<?php echo htmlspecialchars($form_data['isbn']); ?>"
                                           placeholder="e.g., 978-3-16-148410-0">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="author" class="form-label">Author *</label>
                                    <input type="text" class="form-control" id="author" name="author" 
                                           value="<?php echo htmlspecialchars($form_data['author']); ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter the author name.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <select class="form-control" id="category" name="category">
                                        <option value="">Select Category</option>
                                        <option value="Fiction" <?php echo $form_data['category'] == 'Fiction' ? 'selected' : ''; ?>>Fiction</option>
                                        <option value="Non-Fiction" <?php echo $form_data['category'] == 'Non-Fiction' ? 'selected' : ''; ?>>Non-Fiction</option>
                                        <option value="Science Fiction" <?php echo $form_data['category'] == 'Science Fiction' ? 'selected' : ''; ?>>Science Fiction</option>
                                        <option value="Fantasy" <?php echo $form_data['category'] == 'Fantasy' ? 'selected' : ''; ?>>Fantasy</option>
                                        <option value="Mystery" <?php echo $form_data['category'] == 'Mystery' ? 'selected' : ''; ?>>Mystery</option>
                                        <option value="Romance" <?php echo $form_data['category'] == 'Romance' ? 'selected' : ''; ?>>Romance</option>
                                        <option value="Biography" <?php echo $form_data['category'] == 'Biography' ? 'selected' : ''; ?>>Biography</option>
                                        <option value="History" <?php echo $form_data['category'] == 'History' ? 'selected' : ''; ?>>History</option>
                                        <option value="Science" <?php echo $form_data['category'] == 'Science' ? 'selected' : ''; ?>>Science</option>
                                        <option value="Technology" <?php echo $form_data['category'] == 'Technology' ? 'selected' : ''; ?>>Technology</option>
                                        <option value="Children" <?php echo $form_data['category'] == 'Children' ? 'selected' : ''; ?>>Children</option>
                                        <option value="Self-Help" <?php echo $form_data['category'] == 'Self-Help' ? 'selected' : ''; ?>>Self-Help</option>
                                        <option value="Other" <?php echo $form_data['category'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3" placeholder="Brief description of the book..."><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Publication Details -->
                        <div class="mb-4">
                            <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-info-circle me-2"></i>Publication Details</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="publisher" class="form-label">Publisher</label>
                                    <input type="text" class="form-control" id="publisher" name="publisher" 
                                           value="<?php echo htmlspecialchars($form_data['publisher']); ?>"
                                           placeholder="e.g., Penguin Books">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="year" class="form-label">Year</label>
                                    <input type="number" class="form-control" id="year" name="year" 
                                           value="<?php echo htmlspecialchars($form_data['year']); ?>"
                                           min="1000" max="<?php echo date('Y'); ?>" 
                                           placeholder="e.g., 2020">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="pages" class="form-label">Pages</label>
                                    <input type="number" class="form-control" id="pages" name="pages" 
                                           value="<?php echo htmlspecialchars($form_data['pages']); ?>"
                                           min="1" placeholder="e.g., 320">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="language" class="form-label">Language</label>
                                    <select class="form-control" id="language" name="language">
                                        <option value="English" <?php echo $form_data['language'] == 'English' ? 'selected' : ''; ?>>English</option>
                                        <option value="Kinyarwanda" <?php echo $form_data['language'] == 'Kinyarwanda' ? 'selected' : ''; ?>>Kinyarwanda</option>
                                        <option value="French" <?php echo $form_data['language'] == 'French' ? 'selected' : ''; ?>>French</option>
                                        <option value="Spanish" <?php echo $form_data['language'] == 'Spanish' ? 'selected' : ''; ?>>Spanish</option>
                                        <option value="Other" <?php echo $form_data['language'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="condition" class="form-label">Book Condition</label>
                                    <select class="form-control" id="condition" name="condition">
                                        <option value="new" <?php echo $form_data['condition'] == 'new' ? 'selected' : ''; ?>>New</option>
                                        <option value="good" <?php echo $form_data['condition'] == 'good' ? 'selected' : ''; ?>>Good</option>
                                        <option value="fair" <?php echo $form_data['condition'] == 'fair' ? 'selected' : ''; ?>>Fair</option>
                                        <option value="poor" <?php echo $form_data['condition'] == 'poor' ? 'selected' : ''; ?>>Poor</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="status" class="form-label">Availability Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="available" <?php echo $form_data['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                        <option value="unavailable" <?php echo $form_data['status'] == 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cover Image -->
                        <div class="mb-4">
                            <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-image me-2"></i>Book Cover</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="cover_image" class="form-label">Cover Image</label>
                                    <input type="file" class="form-control" id="cover_image" name="cover_image" 
                                           accept="image/*">
                                    <div class="form-text">
                                        Upload a cover image (JPG, PNG, GIF). Max size: 2MB.
                                    </div>
                                    <div class="mt-2" id="imagePreview"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">Cover Guidelines:</h6>
                                            <ul class="small text-muted mb-0">
                                                <li>Clear image of the book cover</li>
                                                <li>Preferred dimensions: 300x450 pixels</li>
                                                <li>Max file size: 2MB</li>
                                                <li>Accepted formats: JPG, PNG, GIF</li>
                                                <li>If no cover available, a default image will be used</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="my_books.php" class="btn btn-outline-secondary me-md-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" name="add_book" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i>Add Book to Collection
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Quick Tips -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Tips for Lenders</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-success fa-lg me-3"></i>
                                </div>
                                <div>
                                    <h6>Accurate Information</h6>
                                    <p class="small text-muted mb-0">Provide correct book details to attract borrowers.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-success fa-lg me-3"></i>
                                </div>
                                <div>
                                    <h6>Clear Photos</h6>
                                    <p class="small text-muted mb-0">Good quality photos help borrowers decide.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-success fa-lg me-3"></i>
                                </div>
                                <div>
                                    <h6>Honest Condition</h6>
                                    <p class="small text-muted mb-0">Be honest about the book's condition to avoid issues.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>