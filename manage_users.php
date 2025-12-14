<?php
// manage_users.php - Library Users Management for Lender (Admin/Librarian)
session_start();
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in and is admin/librarian (lender)
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'librarian')) {
    header('Location: login.php');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];

// Handle form submissions
$message = '';
$message_type = '';

// Add New Member (User)
if (isset($_POST['add_member'])) {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $membership_type = $_POST['membership_type'] ?? 'regular';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Set max books based on membership type
    $max_books_map = [
        'regular' => 5,
        'premium' => 10,
        'student' => 3,
        'faculty' => 15
    ];
    $max_books = $max_books_map[$membership_type] ?? 5;
    
    if (!empty($first_name) && !empty($last_name) && !empty($email) && !empty($username) && !empty($password)) {
        // Check if email or username already exists
        $check_sql = "SELECT user_id FROM users WHERE (email = ? OR username = ?) AND status != 'deleted'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $email, $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = "Email or username already exists!";
            $message_type = "error";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (first_name, last_name, email, phone, address, 
                    username, password, role, membership_type, max_books_allowed, 
                    status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'member', ?, ?, 'active', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssii", $first_name, $last_name, $email, $phone, $address,
                             $username, $hashed_password, $membership_type, $max_books, $current_user_id);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                
                // Generate library card number
                $card_number = 'LIB' . str_pad($user_id, 6, '0', STR_PAD_LEFT) . date('y');
                $update_sql = "UPDATE users SET library_card_number = ? WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $card_number, $user_id);
                $update_stmt->execute();
                
                $message = "Member added successfully! Library Card: " . $card_number;
                $message_type = "success";
                
                // Log the action
                logAction($conn, $current_user_id, 'ADD_MEMBER', "Added new member: $first_name $last_name ($card_number)");
            } else {
                $message = "Error adding member: " . $stmt->error;
                $message_type = "error";
            }
        }
    } else {
        $message = "All required fields must be filled!";
        $message_type = "error";
    }
}

// Update Member
if (isset($_POST['update_member'])) {
    $user_id = $_POST['user_id'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $membership_type = $_POST['membership_type'] ?? 'regular';
    $status = $_POST['status'] ?? 'active';
    
    // Set max books based on membership type
    $max_books_map = [
        'regular' => 5,
        'premium' => 10,
        'student' => 3,
        'faculty' => 15
    ];
    $max_books = $max_books_map[$membership_type] ?? 5;
    
    if (!empty($user_id) && !empty($first_name) && !empty($last_name) && !empty($email)) {
        $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                address = ?, membership_type = ?, max_books_allowed = ?, status = ?, 
                updated_by = ? WHERE user_id = ? AND role = 'member'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssisii", $first_name, $last_name, $email, $phone, $address,
                         $membership_type, $max_books, $status, $current_user_id, $user_id);
        
        if ($stmt->execute()) {
            $message = "Member updated successfully!";
            $message_type = "success";
            
            // Log the action
            logAction($conn, $current_user_id, 'UPDATE_MEMBER', "Updated member ID: $user_id");
        } else {
            $message = "Error updating member: " . $stmt->error;
            $message_type = "error";
        }
    }
}

// Delete/Suspend Member
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    $action = $_GET['action'];
    
    // Check if user exists and is a member (not admin/librarian)
    $check_sql = "SELECT user_id, first_name, last_name, role FROM users WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $user_data = $check_result->fetch_assoc();
    
    if ($user_data && $user_data['role'] == 'member') {
        if ($action == 'delete') {
            // Check if member has active borrowings
            $borrow_sql = "SELECT COUNT(*) as active_borrowings FROM borrowings 
                          WHERE user_id = ? AND return_date IS NULL";
            $borrow_stmt = $conn->prepare($borrow_sql);
            $borrow_stmt->bind_param("i", $user_id);
            $borrow_stmt->execute();
            $borrow_result = $borrow_stmt->get_result();
            $borrow_data = $borrow_result->fetch_assoc();
            
            if ($borrow_data['active_borrowings'] == 0) {
                $sql = "UPDATE users SET status = 'deleted', deleted_by = ?, deleted_at = NOW() 
                        WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $current_user_id, $user_id);
                
                if ($stmt->execute()) {
                    $message = "Member deleted successfully!";
                    $message_type = "success";
                    logAction($conn, $current_user_id, 'DELETE_MEMBER', "Deleted member ID: $user_id");
                }
            } else {
                $message = "Cannot delete member with active borrowings!";
                $message_type = "error";
            }
        } elseif ($action == 'suspend') {
            $sql = "UPDATE users SET status = 'suspended', updated_by = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $current_user_id, $user_id);
            
            if ($stmt->execute()) {
                $message = "Member suspended successfully!";
                $message_type = "success";
                logAction($conn, $current_user_id, 'SUSPEND_MEMBER', "Suspended member ID: $user_id");
            }
        } elseif ($action == 'activate') {
            $sql = "UPDATE users SET status = 'active', updated_by = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $current_user_id, $user_id);
            
            if ($stmt->execute()) {
                $message = "Member activated successfully!";
                $message_type = "success";
                logAction($conn, $current_user_id, 'ACTIVATE_MEMBER', "Activated member ID: $user_id");
            }
        }
    } else {
        $message = "Invalid member or action!";
        $message_type = "error";
    }
}

// Reset Password
if (isset($_POST['reset_password'])) {
    $user_id = $_POST['user_id'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if (!empty($user_id) && !empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = ?, updated_by = ? WHERE user_id = ? AND role = 'member'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $hashed_password, $current_user_id, $user_id);
        
        if ($stmt->execute()) {
            $message = "Password reset successfully!";
            $message_type = "success";
            logAction($conn, $current_user_id, 'RESET_PASSWORD', "Reset password for member ID: $user_id");
        } else {
            $message = "Error resetting password: " . $stmt->error;
            $message_type = "error";
        }
    }
}

// Search functionality
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$membership_filter = $_GET['membership'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query for members (only role = 'member')
$query = "SELECT u.*, 
          CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name,
          (SELECT COUNT(*) FROM borrowings WHERE user_id = u.user_id AND return_date IS NULL) as active_borrowings,
          (SELECT COUNT(*) FROM borrowings WHERE user_id = u.user_id) as total_borrowings,
          (SELECT SUM(fine_amount) FROM borrowings WHERE user_id = u.user_id AND fine_amount > 0 AND return_date IS NOT NULL) as total_fines
          FROM users u 
          LEFT JOIN users creator ON u.created_by = creator.user_id
          WHERE u.role = 'member' AND u.status != 'deleted'";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? 
               OR u.phone LIKE ? OR u.library_card_number LIKE ? OR u.username LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssssss";
}

if (!empty($status_filter)) {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($membership_filter)) {
    $query .= " AND u.membership_type = ?";
    $params[] = $membership_filter;
    $types .= "s";
}

// Count total records for pagination
$count_query = str_replace("u.*, CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name,", "COUNT(*) as total", $query);
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Get members with pagination
$query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
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
    <title>Manage Members - Library Lending System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* (CSS remains mostly the same as previous, just updating for lender context) */
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #17a2b8;
            --light: #ecf0f1;
            --dark: #2c3e50;
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
        
        .lender-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-top: 10px;
            display: inline-block;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--secondary);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: #666;
            font-size: 0.9rem;
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        
        .form-group label.required::after {
            content: " *";
            color: var(--danger);
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
        
        .btn-primary { background: var(--secondary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-info { background: var(--info); color: white; }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary:hover { background: #2980b9; }
        .btn-success:hover { background: #219653; }
        .btn-danger:hover { background: #c0392b; }
        .btn-warning:hover { background: #e67e22; }
        .btn-info:hover { background: #148f9c; }
        
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
            display: inline-block;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-suspended { background: #fff3cd; color: #856404; }
        
        .membership-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
        }
        
        .membership-regular { background: #d1ecf1; color: #0c5460; }
        .membership-premium { background: #d4edda; color: #155724; }
        .membership-student { background: #fff3cd; color: #856404; }
        .membership-faculty { background: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
        }
        
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        
        @media (max-width: 768px) {
            .container { margin: 10px; border-radius: 10px; }
            .content { padding: 15px; }
            .nav { flex-direction: column; gap: 10px; }
            .form-row { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-user-tie"></i> Library Lender System</h1>
            <p>Manage Library Members as Lender</p>
            <div class="lender-badge">
                <i class="fas fa-user-shield"></i> Logged in as: <?php echo $_SESSION['user_name'] ?? 'Librarian'; ?> 
                (<?php echo ucfirst($current_user_role); ?>)
            </div>
        </div>
        
        <div class="nav">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="manage_books.php"><i class="fas fa-book"></i> Manage Books</a>
            <a href="manage_users.php" class="active"><i class="fas fa-users"></i> Manage Members</a>
            <a href="issue_books.php"><i class="fas fa-hand-holding"></i> Issue Books</a>
            <a href="return_books.php"><i class="fas fa-undo"></i> Return Books</a>
            <a href="manage_fines.php"><i class="fas fa-money-bill-wave"></i> Manage Fines</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Member Statistics -->
            <?php
            // Get member statistics
            $stats_sql = "SELECT 
                COUNT(*) as total_members,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_members,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_members,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_members,
                SUM(CASE WHEN membership_type = 'premium' THEN 1 ELSE 0 END) as premium_members,
                (SELECT COUNT(*) FROM borrowings WHERE return_date IS NULL) as books_borrowed
                FROM users WHERE role = 'member' AND status != 'deleted'";
            $stats_result = $conn->query($stats_sql);
            $stats = $stats_result->fetch_assoc();
            ?>
            
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['total_members']; ?></div>
                    <div class="stats-label">Total Members</div>
                </div>
                
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['active_members']; ?></div>
                    <div class="stats-label">Active Members</div>
                </div>
                
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-book-reader"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['books_borrowed']; ?></div>
                    <div class="stats-label">Books Borrowed</div>
                </div>
                
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['premium_members']; ?></div>
                    <div class="stats-label">Premium Members</div>
                </div>
            </div>
            
            <!-- Search Section -->
            <div class="card search-box">
                <h2><i class="fas fa-search"></i> Search Members</h2>
                <form method="GET" class="search-form">
                    <div class="form-group">
                        <label>Search:</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Name, Email, Phone, or Library Card" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Status:</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo ($status_filter == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Membership Type:</label>
                        <select name="membership" class="form-control">
                            <option value="">All Types</option>
                            <option value="regular" <?php echo ($membership_filter == 'regular') ? 'selected' : ''; ?>>Regular</option>
                            <option value="premium" <?php echo ($membership_filter == 'premium') ? 'selected' : ''; ?>>Premium</option>
                            <option value="student" <?php echo ($membership_filter == 'student') ? 'selected' : ''; ?>>Student</option>
                            <option value="faculty" <?php echo ($membership_filter == 'faculty') ? 'selected' : ''; ?>>Faculty</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        <a href="manage_users.php" class="btn btn-danger"><i class="fas fa-times"></i> Clear</a>
                        <button type="button" class="btn btn-success" onclick="showAddForm()">
                            <i class="fas fa-user-plus"></i> Add Member
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Add Member Form (Initially Hidden) -->
            <div class="card" id="addForm" style="display: none;">
                <h2><i class="fas fa-user-plus"></i> Add New Member</h2>
                <form method="POST" action="" onsubmit="return validateMemberForm()">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">First Name</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Last Name</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Username</label>
                            <input type="text" name="username" class="form-control" required>
                            <small class="text-muted">For member login</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Password</label>
                            <input type="password" name="password" id="password" class="form-control" 
                                   required minlength="8">
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Confirm Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" 
                                   class="form-control" required>
                            <div id="passwordMatch" class="text-muted"></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Membership Type</label>
                            <select name="membership_type" class="form-control" id="membershipType" 
                                    onchange="updateMaxBooks()">
                                <option value="regular">Regular (5 books max)</option>
                                <option value="premium">Premium (10 books max)</option>
                                <option value="student">Student (3 books max)</option>
                                <option value="faculty">Faculty (15 books max)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Max Books Allowed</label>
                            <input type="number" id="maxBooks" class="form-control" readonly value="5">
                            <small class="text-muted">Auto-calculated based on membership</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="add_member" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Member
                        </button>
                        <button type="button" class="btn btn-danger" onclick="hideAddForm()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Members List -->
            <div class="card">
                <h2><i class="fas fa-list"></i> Members List (<?php echo $total_rows; ?> members)</h2>
                
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Card No.</th>
                                <th>Member Name</th>
                                <th>Contact Info</th>
                                <th>Membership</th>
                                <th>Status</th>
                                <th>Borrowing Info</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($member = $result->fetch_assoc()): ?>
                                    <?php 
                                    $full_name = $member['first_name'] . ' ' . $member['last_name'];
                                    $membership_class = 'membership-' . $member['membership_type'];
                                    $status_class = 'status-' . $member['status'];
                                    $has_active_borrowings = $member['active_borrowings'] > 0;
                                    $has_fines = $member['total_fines'] > 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $member['library_card_number']; ?></strong>
                                            <br><small>ID: <?php echo $member['user_id']; ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($full_name); ?></strong>
                                            <br><small>@<?php echo htmlspecialchars($member['username']); ?></small>
                                            <?php if ($member['address']): ?>
                                                <br><small><i class="fas fa-map-marker-alt"></i> 
                                                    <?php echo substr($member['address'], 0, 30) . '...'; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <i class="fas fa-envelope"></i> <?php echo $member['email']; ?><br>
                                            <?php if ($member['phone']): ?>
                                                <i class="fas fa-phone"></i> <?php echo $member['phone']; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="membership-badge <?php echo $membership_class; ?>">
                                                <?php echo ucfirst($member['membership_type']); ?>
                                            </span>
                                            <br><small>Max: <?php echo $member['max_books_allowed']; ?> books</small>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst($member['status']); ?>
                                            </span>
                                            <?php if ($has_fines): ?>
                                                <br><small class="text-danger">
                                                    <i class="fas fa-exclamation-circle"></i> 
                                                    Fine: $<?php echo number_format($member['total_fines'], 2); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong>Active:</strong> <?php echo $member['active_borrowings']; ?><br>
                                            <strong>Total:</strong> <?php echo $member['total_borrowings']; ?>
                                            <?php if ($has_active_borrowings): ?>
                                                <br><small class="text-warning">
                                                    <i class="fas fa-book"></i> Has active borrowings
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $member['created_by_name'] ?: 'System'; ?><br>
                                            <small><?php echo date('M d, Y', strtotime($member['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick="editMember(<?php echo $member['user_id']; ?>)" 
                                                        class="btn btn-primary btn-sm">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                
                                                <?php if ($member['status'] == 'active'): ?>
                                                    <a href="?action=suspend&id=<?php echo $member['user_id']; ?>" 
                                                       class="btn btn-warning btn-sm"
                                                       onclick="return confirm('Suspend this member?')">
                                                        <i class="fas fa-ban"></i> Suspend
                                                    </a>
                                                <?php elseif ($member['status'] == 'suspended'): ?>
                                                    <a href="?action=activate&id=<?php echo $member['user_id']; ?>" 
                                                       class="btn btn-success btn-sm"
                                                       onclick="return confirm('Activate this member?')">
                                                        <i class="fas fa-check"></i> Activate
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="?action=delete&id=<?php echo $member['user_id']; ?>" 
                                                   class="btn btn-danger btn-sm"
                                                   onclick="return confirm('Are you sure? This cannot be undone!')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                                
                                                <button onclick="resetMemberPassword(<?php echo $member['user_id']; ?>)" 
                                                        class="btn btn-info btn-sm">
                                                    <i class="fas fa-key"></i> Reset PW
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-user-slash" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
                                        <h3>No members found</h3>
                                        <p>Click "Add Member" to register new library members.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div style="display: flex; justify-content: center; gap: 10px; margin-top: 30px;">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&membership=<?php echo $membership_filter; ?>"
                               style="padding: 8px 16px; background: var(--light); border-radius: 5px; 
                                      text-decoration: none; color: var(--dark); 
                                      <?php echo ($i == $page) ? 'background: var(--secondary); color: white;' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        // Show/hide add form
        function showAddForm() {
            document.getElementById('addForm').style.display = 'block';
            window.scrollTo({ top: document.getElementById('addForm').offsetTop - 100, behavior: 'smooth' });
        }
        
        function hideAddForm() {
            document.getElementById('addForm').style.display = 'none';
        }
        
        // Update max books based on membership type
        function updateMaxBooks() {
            const membership = document.getElementById('membershipType').value;
            const maxBooksMap = {
                'regular': 5,
                'premium': 10,
                'student': 3,
                'faculty': 15
            };
            document.getElementById('maxBooks').value = maxBooksMap[membership] || 5;
        }
        
        // Password confirmation
        document.getElementById('confirm_password').addEventListener('keyup', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (password === confirm) {
                matchDiv.innerHTML = '<span style="color: green;">✓ Passwords match</span>';
                matchDiv.style.color = 'green';
            } else {
                matchDiv.innerHTML = '<span style="color: red;">✗ Passwords do not match</span>';
                matchDiv.style.color = 'red';
            }
        });
        
        // Form validation
        function validateMemberForm() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters long');
                return false;
            }
            
            if (password !== confirm) {
                alert('Passwords do not match');
                return false;
            }
            
            return true;
        }
        
        // Edit member (AJAX would go here)
        function editMember(userId) {
            // This would typically load a modal with AJAX
            window.location.href = 'edit_member.php?id=' + userId;
        }
        
        // Reset password (AJAX would go here)
        function resetMemberPassword(userId) {
            const newPassword = prompt('Enter new password for member (min 8 chars):');
            if (newPassword && newPassword.length >= 8) {
                // AJAX call to reset password
                fetch('ajax/reset_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'user_id=' + userId + '&new_password=' + encodeURIComponent(newPassword)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Password reset successfully!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error resetting password');
                });
            } else if (newPassword) {
                alert('Password must be at least 8 characters');
            }
        }
    </script>
</body>
</html>