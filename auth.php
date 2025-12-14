
<?php
// auth.php - Authentication Functions

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin/lender
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'lender';
}

// Check if user is borrower
function isBorrower() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'borrower';
}

// Check if user is reader
function isReader() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'reader';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}
// In auth.php, add this function
function checkRememberMe() {
    if (isset($_COOKIE['remember_me']) && !isset($_SESSION['user_id'])) {
        list($user_id, $token) = explode(':', $_COOKIE['remember_me']);
        
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user && hash('sha256', $user['password']) === $token) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            return true;
        }
    }
    return false;
}

// Call it in your isLoggedIn() function or at the top of auth.php
checkRememberMe();
?>