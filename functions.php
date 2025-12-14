<?php
// functions.php
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function showAlert($message, $type = 'info') {
    $types = [
        'success' => 'alert-success',
        'danger' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $class = $types[$type] ?? 'alert-info';
    
    return '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($message) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

function redirect($url, $message = null, $type = 'info') {
    if ($message) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header("Location: $url");
    exit();
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return showAlert($flash['message'], $flash['type']);
    }
    return '';
}

function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

function getDaysRemaining($due_date) {
    $now = new DateTime();
    $due = new DateTime($due_date);
    $interval = $now->diff($due);
    return $interval->days * ($interval->invert ? -1 : 1);
}

// Add to functions.php
function handleFileUpload($field_name, $upload_dir = 'uploads/') {
    $result = [
        'success' => false,
        'filename' => '',
        'error' => ''
    ];
    
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'No file uploaded or upload error.';
        return $result;
    }
    
    $file = $_FILES[$field_name];
    
    // Check file size (max 2MB)
    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $max_size) {
        $result['error'] = 'File size exceeds 2MB limit.';
        return $result;
    }
    
    // Check file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    if (!in_array($file['type'], $allowed_types)) {
        $result['error'] = 'Only JPG, PNG and GIF files are allowed.';
        return $result;
    }
    
    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('book_', true) . '.' . $file_ext;
    $destination = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $result['success'] = true;
        $result['filename'] = $filename;
    } else {
        $result['error'] = 'Failed to move uploaded file.';
    }
    
    return $result;

    // Add to functions.php
function getStatusColor($status) {
    switch($status) {
        case 'pending': return 'warning';
        case 'approved': return 'success';
        case 'borrowed': return 'info';
        case 'returned': return 'secondary';
        case 'rejected': return 'danger';
        case 'cancelled': return 'secondary';
        default: return 'secondary';
    }
}
function logAction($conn, $user_id, $action_type, $description) {
    $sql = "INSERT INTO activity_logs (user_id, action_type, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $user_id, $action_type, $description, 
                      $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    $stmt->execute();
}
}

// ... existing functions ...

function getRequestStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge badge-warning">Pending</span>',
        'approved' => '<span class="badge badge-success">Approved</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        'cancelled' => '<span class="badge badge-secondary">Cancelled</span>'
    ];
    return $badges[$status] ?? $badges['pending'];
}

function calculateDueDate($days = 14) {
    return date('Y-m-d', strtotime("+$days days"));
}




?>