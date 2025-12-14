<?php
// login.php - Login Page
require_once 'config.php';
require_once 'auth.php';
require_once 'functions.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Prevent form resubmission warning
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$page_title = 'Login - ' . SITE_NAME;
$page_css = 'login.css';
$page_js = 'login.js';

$errors = [];
$username_value = isset($_SESSION['login_username']) ? $_SESSION['login_username'] : '';

// Clear session data after retrieving
unset($_SESSION['login_username']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Store for repopulation
    $_SESSION['login_username'] = $username;
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username or email is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    if (empty($errors)) {
        try {
            // FIXED: Use different parameter names
            $sql = "SELECT * FROM users WHERE username = :username OR email = :email";
            // OR if you have status column: AND status = 'active'
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $username); // Same value
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Clear stored data
                    unset($_SESSION['login_username']);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    // Update last login
                    $update_sql = "UPDATE users SET last_login = NOW() WHERE id = :id";
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->bindParam(':id', $user['id']);
                    $update_stmt->execute();
                    
                    // DEBUG: Check what role was retrieved
                    // echo "Debug: User role is: " . $user['role'];
                    // exit();
                    
                    // Redirect based on role (optional)
                    /*
                    switch ($user['role']) {
                        case 'lender':
                            header("Location: lender_dashboard.php");
                            break;
                        case 'borrower':
                            header("Location: borrower_dashboard.php");
                            break;
                        case 'reader':
                        default:
                            header("Location: dashboard.php");
                    }
                    exit();
                    */
                    
                    // Original redirect
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $errors[] = 'Invalid username or password';
                }
            } else {
                $errors[] = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            $errors[] = 'Login failed: ' . $e->getMessage();
            
            // DEBUG: Add more info
            if (defined('DEBUG') && DEBUG) {
                $errors[] = 'SQL Error Code: ' . $e->getCode();
                $errors[] = 'SQL Query: ' . $sql;
                $errors[] = 'Parameters: username=' . $username;
            }
        }
    }
    
    // Store errors in session and redirect
    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        header("Location: login.php");
        exit();
    }
}

// Get errors from session
if (isset($_SESSION['login_errors'])) {
    $errors = $_SESSION['login_errors'];
    unset($_SESSION['login_errors']);
}
?>
<?php include 'header.php'; ?>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h3 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>Login</h3>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php
                    // Display flash messages
                    if (isset($_SESSION['flash_message'])) {
                        echo '<div class="alert alert-success">' . $_SESSION['flash_message'] . '</div>';
                        unset($_SESSION['flash_message']);
                    }
                    ?>
                    
                    <form method="POST" action="" id="loginForm">
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="fas fa-user me-2"></i>Username or Email
                            </label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($username_value ?: ($_POST['username'] ?? '')); ?>" 
                                   required autofocus>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-2"></i>Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                            <a href="forgot_password.php" class="float-end text-decoration-none">Forgot Password?</a>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </div>
                        
                        <div class="text-center mb-3">
                            <p class="text-muted">Or login with</p>
                            <div class="d-flex justify-content-center gap-3">
                                <button type="button" class="btn btn-outline-danger">
                                    <i class="fab fa-google me-2"></i>Google
                                </button>
                                <button type="button" class="btn btn-outline-primary">
                                    <i class="fab fa-facebook me-2"></i>Facebook
                                </button>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p>Don't have an account? <a href="signup.php" class="fw-bold">Sign up here</a></p>
                            <p><a href="index.php"><i class="fas fa-home me-2"></i>Back to Home</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>