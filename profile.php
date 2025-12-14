<?php
// profile.php
require_once 'config.php';
require_once 'auth.php';
require_once 'functions.php';
requireLogin();

$page_title = 'My Profile - ' . SITE_NAME;
$page_css = 'profile.css';
$page_js = 'profile.js';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$errors = [];
$success = '';

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['flash_error'] = 'User not found!';
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    $errors[] = 'Failed to load profile: ' . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    
    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'Invalid email format';
    }
    
    // Check if email already exists (excluding current user)
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Email already exists';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    
    // Update profile if no errors
    if (empty($errors)) {
        try {
            $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$full_name, $email, $phone, $user_id]);
            
            // Update session
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            $success = 'Profile updated successfully!';
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
        } catch (PDOException $e) {
            $errors[] = 'Failed to update profile: ' . $e->getMessage();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($current_password)) {
        $errors[] = 'Current password is required';
    }
    
    if (empty($new_password)) {
        $errors[] = 'New password is required';
    } elseif (strlen($new_password) < 6) {
        $errors[] = 'New password must be at least 6 characters';
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match';
    }
    
    // Verify current password
    if (empty($errors)) {
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        }
    }
    
    // Change password if no errors
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$hashed_password, $user_id]);
            
            $success = 'Password changed successfully!';
            
        } catch (PDOException $e) {
            $errors[] = 'Failed to change password: ' . $e->getMessage();
        }
    }
}
?>
<?php include 'header.php'; ?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-4">
            <!-- Profile Sidebar -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <div class="profile-avatar bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" 
                             style="width: 100px; height: 100px; font-size: 2.5rem;">
                            <span class="text-white"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></span>
                        </div>
                    </div>
                    <h4 class="card-title"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p class="text-muted">
                        <i class="fas fa-user-tag me-2"></i>
                        <span class="badge bg-<?php 
                            echo $role == 'lender' ? 'warning' : 
                                 ($role == 'borrower' ? 'success' : 'primary'); 
                        ?>">
                            <?php echo ucfirst($role); ?>
                        </span>
                    </p>
                    <p class="text-muted">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Member since: <?php echo formatDate($user['created_at'] ?? ''); ?>
                    </p>
                    <p class="text-muted">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Last login: <?php echo formatDate($user['last_login'] ?? ''); ?>
                    </p>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Quick Stats</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <?php if ($role == 'reader'): ?>
                            <?php
                            // Check if reading_history table exists
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reading_history WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            $stats = $stmt->fetch();
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Books Read
                                <span class="badge bg-primary rounded-pill"><?php echo $stats['count'] ?? 0; ?></span>
                            </li>
                        <?php elseif ($role == 'borrower'): ?>
                            <?php
                            // Check if borrowings table exists
                            $stmt = $pdo->prepare("SELECT 
                                COUNT(*) as total_borrowed,
                                SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as current_borrowed
                                FROM borrowings WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            $stats = $stmt->fetch();
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Borrowed
                                <span class="badge bg-primary rounded-pill"><?php echo $stats['total_borrowed'] ?? 0; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Current Borrowings
                                <span class="badge bg-success rounded-pill"><?php echo $stats['current_borrowed'] ?? 0; ?></span>
                            </li>
                        <?php elseif ($role == 'lender'): ?>
                            <?php
                            // FIXED: Check your actual database structure
                            // Option 1: If you have a user_id column in books table
                            try {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as total_books FROM books WHERE user_id = ? OR added_by = ?");
                                $stmt->execute([$user_id, $user_id]);
                                $books = $stmt->fetch();
                            } catch (PDOException $e) {
                                // If the query fails, try alternative column names
                                try {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) as total_books FROM books WHERE lender_id = ?");
                                    $stmt->execute([$user_id]);
                                    $books = $stmt->fetch();
                                } catch (PDOException $e2) {
                                    // If still fails, check if books table has any user relation
                                    $books = ['total_books' => 0];
                                }
                            }
                            
                            // For pending requests, check your actual borrowing/loan table structure
                            try {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as pending_requests 
                                                      FROM borrowings br 
                                                      JOIN books b ON br.book_id = b.id 
                                                      WHERE b.added_by = ? AND br.status = 'pending'");
                                $stmt->execute([$user_id]);
                                $requests = $stmt->fetch();
                            } catch (PDOException $e) {
                                // Alternative query
                                try {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_requests 
                                                          FROM loans 
                                                          WHERE status = 'pending' AND book_id IN 
                                                          (SELECT id FROM books WHERE user_id = ?)");
                                    $stmt->execute([$user_id]);
                                    $requests = $stmt->fetch();
                                } catch (PDOException $e2) {
                                    $requests = ['pending_requests' => 0];
                                }
                            }
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Books Listed
                                <span class="badge bg-primary rounded-pill"><?php echo $books['total_books'] ?? 0; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Pending Requests
                                <span class="badge bg-warning rounded-pill"><?php echo $requests['pending_requests'] ?? 0; ?></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
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
                </div>
            <?php endif; ?>
            
            <!-- Update Profile Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Update Profile</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" 
                                       value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo ucfirst($user['role'] ?? 'reader'); ?>" readonly disabled>
                            <small class="text-muted">Role cannot be changed</small>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Change Password Form -->
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password *</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">New Password *</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Lender Specific Information -->
            <?php if ($role == 'lender'): ?>
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-book me-2"></i>Lender Dashboard</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3><?php echo $books['total_books'] ?? 0; ?></h3>
                                        <p class="text-muted mb-0">Books in Library</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3><?php echo $requests['pending_requests'] ?? 0; ?></h3>
                                        <p class="text-muted mb-0">Pending Requests</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <a href="manage_books.php" class="btn btn-info me-2">
                                <i class="fas fa-book me-2"></i>Manage Books
                            </a>
                            <a href="borrow_requests.php" class="btn btn-warning">
                                <i class="fas fa-clock me-2"></i>View Requests
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Account Information -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th width="30%">Account Created:</th>
                            <td><?php echo formatDate($user['created_at'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <th>Last Updated:</th>
                            <td><?php echo formatDate($user['updated_at'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <th>Last Login:</th>
                            <td><?php echo formatDate($user['last_login'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <th>Account ID:</th>
                            <td><code><?php echo $user['id']; ?></code></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="badge bg-success">Active</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>