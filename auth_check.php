<?php
// auth_check.php - Authentication Check Script

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 */
function hasRole($requiredRole) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    // Admin has access to everything
    if ($_SESSION['role'] === 'admin') {
        return true;
    }
    
    // Check specific role
    return $_SESSION['role'] === $requiredRole;
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole($roles = []) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    // Admin has access to everything
    if ($_SESSION['role'] === 'admin') {
        return true;
    }
    
    // Check if user role is in the allowed roles array
    return in_array($_SESSION['role'], $roles);
}

/**
 * Redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Store the current page to redirect back after login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login page
        header('Location: login.php');
        exit();
    }
}

/**
 * Redirect if user doesn't have required role
 */
function requireRole($requiredRole) {
    requireLogin(); // First check if logged in
    
    if (!hasRole($requiredRole)) {
        // Log unauthorized access attempt
        error_log("Unauthorized access attempt by user ID: " . $_SESSION['user_id'] . " to: " . $_SERVER['REQUEST_URI']);
        
        // Redirect to unauthorized page or dashboard
        $_SESSION['error'] = "You don't have permission to access this page.";
        header('Location: dashboard.php');
        exit();
    }
}

/**
 * Redirect if user doesn't have any of the specified roles
 */
function requireAnyRole($roles = []) {
    requireLogin(); // First check if logged in
    
    if (!hasAnyRole($roles)) {
        // Log unauthorized access attempt
        error_log("Unauthorized access attempt by user ID: " . $_SESSION['user_id'] . " to: " . $_SERVER['REQUEST_URI']);
        
        // Redirect to unauthorized page or dashboard
        $_SESSION['error'] = "You don't have permission to access this page.";
        header('Location: dashboard.php');
        exit();
    }
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if current user is librarian
 */
function isLibrarian() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'librarian';
}

/**
 * Check if current user is student
 */
function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

/**
 * Check if current user is faculty/staff
 */
function isFaculty() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'faculty' || $_SESSION['role'] === 'staff');
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

/**
 * Get current user data from session
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'profile_pic' => $_SESSION['profile_pic'] ?? null
    ];
}

/**
 * Prevent access to login/register pages if already logged in
 */
function redirectIfLoggedIn($redirectTo = 'dashboard.php') {
    if (isLoggedIn()) {
        header("Location: $redirectTo");
        exit();
    }
}

/**
 * Check CSRF token for form submissions
 */
function verifyCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = "Invalid CSRF token. Please try again.";
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        }
    }
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Check session timeout (optional)
 */
function checkSessionTimeout($timeoutMinutes = 30) {
    if (isset($_SESSION['LAST_ACTIVITY'])) {
        $secondsInactive = time() - $_SESSION['LAST_ACTIVITY'];
        
        if ($secondsInactive > ($timeoutMinutes * 60)) {
            // Session expired
            session_unset();
            session_destroy();
            
            $_SESSION['error'] = "Your session has expired. Please login again.";
            header('Location: login.php');
            exit();
        }
    }
    
    // Update last activity time
    $_SESSION['LAST_ACTIVITY'] = time();
}

/**
 * Initialize security features
 */
function initSecurity() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set session security parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } elseif (time() - $_SESSION['CREATED'] > 1800) {
        // Regenerate ID every 30 minutes
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
    
    // Check session timeout (optional - enable if needed)
    // checkSessionTimeout(30);
}

/**
 * Log user activity (optional)
 */
function logActivity($action, $details = '') {
    if (!isLoggedIn()) return;
    
    global $conn;
    
    $userId = $_SESSION['user_id'];
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $pageUrl = $_SERVER['REQUEST_URI'];
    
    $query = "INSERT INTO user_activity_logs (user_id, action, details, ip_address, user_agent, page_url) 
              VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isssss", $userId, $action, $details, $ipAddress, $userAgent, $pageUrl);
    $stmt->execute();
}

// Initialize security features when this file is included
initSecurity();

// You need to create the user_activity_logs table for logging (optional):
/*
CREATE TABLE user_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    page_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
*/