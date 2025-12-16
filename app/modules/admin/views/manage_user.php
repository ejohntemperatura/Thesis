<?php
session_start();
require_once '../../../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager','director'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Get admin details
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle AJAX request for getting user details
if (isset($_GET['action']) && $_GET['action'] === 'get_user' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Validate required fields
                if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])) {
                    $_SESSION['error'] = "First name, last name, and email are required!";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }
                
                $firstName = trim($_POST['first_name']);
                $middleName = trim($_POST['middle_name'] ?? '');
                $lastName = trim($_POST['last_name']);
                $name = trim($firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName);
                $email = trim($_POST['email']);
                $position = trim($_POST['position'] ?? '');
                $department = trim($_POST['department'] ?? '');
                $contact = trim($_POST['contact'] ?? '');
                $role = $_POST['role'] ?? 'employee';
                $gender = isset($_POST['gender']) && in_array($_POST['gender'], ['male','female']) ? $_POST['gender'] : 'male';
                $address = trim($_POST['address'] ?? '');

                $maritalStatus = isset($_POST['marital_status']) && in_array($_POST['marital_status'], ['single','married','widowed','separated']) ? $_POST['marital_status'] : null;

                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['error'] = "Invalid email format!";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }

                // Check if email already exists (only check active/verified accounts)
                $stmt = $pdo->prepare("SELECT id, name, account_status, email_verified FROM employees WHERE email = ? AND (account_status = 'active' OR email_verified = 1)");
                $stmt->execute([$email]);
                $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existingUser) {
                    $status = $existingUser['account_status'] ?? 'active';
                    $userName = $existingUser['name'];
                    $_SESSION['error'] = "Email already exists! It is currently used by '{$userName}' (Status: {$status}). Please use a different email.";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }
                
                // If email exists but is pending/unverified, delete the old pending account
                $stmt = $pdo->prepare("SELECT id FROM employees WHERE email = ? AND account_status = 'pending' AND email_verified = 0");
                $stmt->execute([$email]);
                $pendingUser = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($pendingUser) {
                    // Delete the old pending account
                    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
                    $stmt->execute([$pendingUser['id']]);
                }

                try {
                    // Generate verification token
                    $verificationToken = bin2hex(random_bytes(32));
                    $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    // Create temporary password (will be changed after email verification)
                    $temporaryPassword = password_hash('temp_' . time(), PASSWORD_DEFAULT);
                    
                    // Gender handling
                    $gender = isset($_POST['gender']) && in_array($_POST['gender'], ['male','female']) ? $_POST['gender'] : 'male';
                    $isSoloParent = isset($_POST['is_solo_parent']) ? 1 : 0;
                    // Fused VL/SL eligibility: one checkbox controls both initial balances
                    // Monthly accrual system: Start with 1.25 days (1 month worth)
                    $eligibleCombined = isset($_POST['eligible_vl_sl']) ? 1 : 0;
                    $vacationInit = $eligibleCombined ? 1.25 : 0;
                    $sickInit = $eligibleCombined ? 1.25 : 0;
                    // Include Special Leave Privilege in fused eligibility
                    $slpInit = $eligibleCombined ? 3 : 0;
                    
                    // Enforce: Leave eligibility applies only to Employees
                    if ($role !== 'employee') {
                        $vacationInit = 0;
                        $sickInit = 0;
                        $slpInit = 0;
                    }
                    
                    // Only employees keep marital status; others set to NULL
                    if ($role !== 'employee') { $maritalStatus = null; }
                    $stmt = $pdo->prepare("
                        INSERT INTO employees (name, first_name, middle_name, last_name, email, password, position, department, contact, role, gender, address, marital_status, is_solo_parent,
                                               vacation_leave_balance, sick_leave_balance, special_leave_privilege_balance, email_verified, verification_token, verification_expires, account_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'pending')
                    ");
                    $stmt->execute([$name, $firstName, $middleName, $lastName, $email, $temporaryPassword, $position, $department, $contact, $role, $gender, $address, $maritalStatus, $isSoloParent,
                                  $vacationInit, $sickInit, $slpInit, $verificationToken, $verificationExpires]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    // Initialize leave balances based on gender
                    if ($gender === 'female') {
                        $initStmt = $pdo->prepare("UPDATE employees SET maternity_leave_balance = 105, paternity_leave_balance = 0, last_leave_credit_update = CURDATE() WHERE id = ?");
                        $initStmt->execute([$userId]);
                    } else {
                        $initStmt = $pdo->prepare("UPDATE employees SET paternity_leave_balance = 7, maternity_leave_balance = 0, last_leave_credit_update = CURDATE() WHERE id = ?");
                        $initStmt->execute([$userId]);
                    }
                    
                    // Log verification attempt
                    $stmt = $pdo->prepare("
                        INSERT INTO email_verification_logs (employee_id, email, verification_token, expires_at, ip_address, user_agent)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$userId, $email, $verificationToken, $verificationExpires, 
                                  $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown']);
                    
                    // Send verification email
                    require_once '../../../../app/core/services/RobustEmail.php';
                    $emailService = new RobustEmail($pdo);
                    
                    // Only show Employee ID in email for 'employee' role
                    if ($emailService->sendVerificationEmail($email, $name, $verificationToken, $userId, $role)) {
                        $_SESSION['success'] = "User added successfully! A verification email has been sent to {$email}";
                    } else {
                        $_SESSION['success'] = "User added successfully! However, there was an issue sending the verification email. Please contact the user directly.";
                    }
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                    
                } catch (PDOException $e) {
                    // Check if it's a duplicate entry error
                    if ($e->getCode() == 23000) {
                        $_SESSION['error'] = "Email already exists for another user!";
                    } else {
                        $_SESSION['error'] = "Error adding user: " . $e->getMessage();
                    }
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                } catch (Exception $e) {
                    $_SESSION['error'] = "Error sending verification email: " . $e->getMessage();
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }
                break;

            case 'edit':
                // Validate required fields
                if (empty($_POST['id']) || empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])) {
                    $_SESSION['error'] = "ID, first name, last name, and email are required!";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }
                
                $id = $_POST['id'];
                $firstName = trim($_POST['first_name']);
                $middleName = trim($_POST['middle_name'] ?? '');
                $lastName = trim($_POST['last_name']);
                $name = trim($firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName);
                $email = trim($_POST['email']);
                $position = trim($_POST['position'] ?? '');
                $department = trim($_POST['department'] ?? '');
                $contact = trim($_POST['contact'] ?? '');
                $role = $_POST['role'] ?? 'employee';
                $gender = isset($_POST['gender']) && in_array($_POST['gender'], ['male','female']) ? $_POST['gender'] : 'male';
                $isSoloParent = isset($_POST['is_solo_parent']) ? 1 : 0;
                // VL/SL/SLP eligibility checkbox
                $eligibleVLSL = isset($_POST['eligible_vl_sl']) ? 1 : 0;
                $address = trim($_POST['address'] ?? '');
                $maritalStatus = isset($_POST['marital_status']) && in_array($_POST['marital_status'], ['single','married','widowed','separated']) ? $_POST['marital_status'] : null;
                


                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['error'] = "Invalid email format!";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }

                // Check if email already exists for other users (only active/verified accounts)
                $stmt = $pdo->prepare("SELECT id, name, account_status, email_verified FROM employees WHERE email = ? AND id != ? AND (account_status = 'active' OR email_verified = 1)");
                $stmt->execute([$email, $id]);
                $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existingUser) {
                    $status = $existingUser['account_status'] ?? 'active';
                    $userName = $existingUser['name'];
                    $_SESSION['error'] = "Email already exists! It is currently used by '{$userName}' (Status: {$status}). Please use a different email.";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }
                
                // If email exists but is pending/unverified for another user, delete the old pending account
                $stmt = $pdo->prepare("SELECT id FROM employees WHERE email = ? AND id != ? AND account_status = 'pending' AND email_verified = 0");
                $stmt->execute([$email, $id]);
                $pendingUser = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($pendingUser) {
                    // Delete the old pending account
                    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
                    $stmt->execute([$pendingUser['id']]);
                }

                try {
                    // Only employees keep marital status; others set to NULL
                    if ($role !== 'employee') { $maritalStatus = null; }


                    // Get current employee data to check eligibility status
                    $currentStmt = $pdo->prepare("SELECT vacation_leave_balance, sick_leave_balance, special_leave_privilege_balance FROM employees WHERE id = ?");
                    $currentStmt->execute([$id]);
                    $currentData = $currentStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Determine leave balance updates based on eligibility checkbox
                    $vacationBal = null;
                    $sickBal = null;
                    $slpBal = null;
                    
                    if ($role === 'employee') {
                        $currentVac = floatval($currentData['vacation_leave_balance'] ?? 0);
                        $currentSick = floatval($currentData['sick_leave_balance'] ?? 0);
                        $currentSlp = floatval($currentData['special_leave_privilege_balance'] ?? 0);
                        $wasEligible = ($currentVac > 0 || $currentSick > 0 || $currentSlp > 0);
                        
                        if ($eligibleVLSL && !$wasEligible) {
                            // Enabling eligibility - set initial balances
                            $vacationBal = 1.25;
                            $sickBal = 1.25;
                            $slpBal = 3;
                        } elseif (!$eligibleVLSL && $wasEligible) {
                            // Disabling eligibility - set balances to 0
                            $vacationBal = 0;
                            $sickBal = 0;
                            $slpBal = 0;
                        }
                        // If eligibility status unchanged, leave balances as-is (managed by accrual system)
                    }

                    if ($role === 'employee') {
                        if ($vacationBal !== null) {
                            // Update with leave balances
                            $stmt = $pdo->prepare("
                                UPDATE employees 
                                SET name = ?, first_name = ?, middle_name = ?, last_name = ?, email = ?, position = ?, department = ?, contact = ?, role = ?, gender = ?, address = ?, marital_status = ?,
                                    is_solo_parent = ?, 
                                    vacation_leave_balance = ?, sick_leave_balance = ?, special_leave_privilege_balance = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $name, $firstName, $middleName, $lastName, $email, $position, $department, $contact, $role, $gender, $address, $maritalStatus, 
                                $isSoloParent, 
                                $vacationBal, $sickBal, $slpBal,
                                $id
                            ]);
                        } else {
                            // Update basic info only (leave balances unchanged)
                            $stmt = $pdo->prepare("
                                UPDATE employees 
                                SET name = ?, first_name = ?, middle_name = ?, last_name = ?, email = ?, position = ?, department = ?, contact = ?, role = ?, gender = ?, address = ?, marital_status = ?,
                                    is_solo_parent = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $name, $firstName, $middleName, $lastName, $email, $position, $department, $contact, $role, $gender, $address, $maritalStatus, 
                                $isSoloParent, 
                                $id
                            ]);
                        }
                    } else {
                        // Update basic info for non-employee roles (do not alter leave balances)
                        $stmt = $pdo->prepare("
                            UPDATE employees 
                            SET name = ?, first_name = ?, middle_name = ?, last_name = ?, email = ?, position = ?, department = ?, contact = ?, role = ?, gender = ?, address = ?, marital_status = NULL,
                                is_solo_parent = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $firstName, $middleName, $lastName, $email, $position, $department, $contact, $role, $gender, $address, $isSoloParent, $id]);
                    }
                    $_SESSION['success'] = "User updated successfully!";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                } catch (PDOException $e) {
                    // Check if it's a duplicate entry error
                    if ($e->getCode() == 23000) {
                        $_SESSION['error'] = "Email already exists for another user!";
                    } else {
                        $_SESSION['error'] = "Error updating user: " . $e->getMessage();
                    }
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }
                break;

            case 'delete':
                // Debug logging
                error_log("Delete request received for user ID: " . ($_POST['id'] ?? 'not set'));
                error_log("Session user ID: " . ($_SESSION['user_id'] ?? 'not set'));
                error_log("Session role: " . ($_SESSION['role'] ?? 'not set'));
                
                // Validate ID
                if (empty($_POST['id'])) {
                    $error_message = "User ID is required!";
                    break;
                }
                
                $id = $_POST['id'];
                
                // Check if user exists
                $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $error_message = "User not found!";
                    break;
                }
                
                // Prevent deleting the current user
                if ($id == $_SESSION['user_id']) {
                    $error_message = "You cannot delete your own account!";
                    break;
                }
                
                try {
                    // Start transaction to ensure data consistency
                    $pdo->beginTransaction();
                    error_log("Transaction started for user deletion: $id");
                    
                    // Check if user has related records
                    $tables_to_check = [
                        'leave_requests' => 'employee_id',
                        'dtr' => 'user_id',
                        'email_verification_logs' => 'employee_id',
                        'leave_alerts' => 'employee_id',
                        'leave_credit_earnings' => 'employee_id',
                        'leave_credit_history' => 'employee_id'
                    ];
                    
                    $total_records = 0;
                    foreach ($tables_to_check as $table => $column) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $column = ?");
                        $stmt->execute([$id]);
                        $count = $stmt->fetchColumn();
                        $total_records += $count;
                        error_log("$table count: $count");
                    }
                    
                    // Delete related records first (in correct order)
                    foreach ($tables_to_check as $table => $column) {
                        $stmt = $pdo->prepare("DELETE FROM $table WHERE $column = ?");
                        $result = $stmt->execute([$id]);
                        $deleted = $stmt->rowCount();
                        error_log("Deleted from $table: " . ($result ? "success" : "failed") . " ($deleted records)");
                    }
                    
                    // Also delete leave_alerts where sent_by = user_id
                    $stmt = $pdo->prepare("DELETE FROM leave_alerts WHERE sent_by = ?");
                    $result = $stmt->execute([$id]);
                    $deleted = $stmt->rowCount();
                    error_log("Deleted leave_alerts (sent_by): " . ($result ? "success" : "failed") . " ($deleted records)");
                    
                    // Also delete leave_requests where any approver = user_id
                    $approver_columns = ['admin_approved_by', 'approved_by', 'dept_head_approved_by', 'director_approved_by', 'rejected_by'];
                    foreach ($approver_columns as $column) {
                        $stmt = $pdo->prepare("UPDATE leave_requests SET $column = NULL WHERE $column = ?");
                        $result = $stmt->execute([$id]);
                        $updated = $stmt->rowCount();
                        error_log("Updated leave_requests ($column): " . ($result ? "success" : "failed") . " ($updated records)");
                    }
                    
                    // Now delete the user
                    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
                    $result = $stmt->execute([$id]);
                    $rowCount = $stmt->rowCount();
                    error_log("Delete user result: " . ($result ? "success" : "failed") . ", rows affected: $rowCount");
                    
                    if ($result && $rowCount > 0) {
                        // Commit transaction
                        $pdo->commit();
                        error_log("Transaction committed successfully");
                        $success_message = "User '{$user['name']}' and all related records deleted successfully!";
                    } else {
                        $pdo->rollBack();
                        error_log("Transaction rolled back - no rows affected");
                        $error_message = "Failed to delete user. User may not exist or already deleted.";
                    }
                } catch (PDOException $e) {
                    // Rollback transaction on error
                    $pdo->rollBack();
                    error_log("Database error during deletion: " . $e->getMessage());
                    $error_message = "Error deleting user: " . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch all users and group by department
$stmt = $pdo->query("SELECT * FROM employees ORDER BY department ASC, position ASC, name ASC");
$users = $stmt->fetchAll();

// Group users by department
$departmentGroups = [];
$departmentStats = [];
foreach ($users as $user) {
    $dept = $user['department'] ?: 'Unassigned';
    if (!isset($departmentGroups[$dept])) {
        $departmentGroups[$dept] = [];
        $departmentStats[$dept] = [
            'total' => 0,
            'admin' => 0,
            'manager' => 0,
            'director' => 0,
            'employee' => 0
        ];
    }
    $departmentGroups[$dept][] = $user;
    $departmentStats[$dept]['total']++;
    $role = $user['role'] ?? 'employee';
    if (isset($departmentStats[$dept][$role])) {
        $departmentStats[$dept][$role]++;
    }
}

// Sort departments alphabetically
ksort($departmentGroups);

// Set page title
$page_title = "Manage Users";

// Include admin header
include '../../../../includes/admin_header.php';
?>
<script>
    // Toggle all user checkboxes
    function toggleAllUsers(checkbox) {
        const checkboxes = document.querySelectorAll('.user-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
    }
    
    // View user details
    function viewUser(id) {
        // Fetch user details from server
        fetch('manage_user.php?action=get_user&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const user = data.user;
                    
                    // Store for edit function
                    currentViewUserId = id;
                    currentViewUserData = user;
                    
                    // Populate modal
                    document.getElementById('viewName').textContent = user.name || 'N/A';
                    document.getElementById('viewEmail').textContent = user.email || 'N/A';
                    document.getElementById('viewContact').textContent = user.contact || 'Not set';
                    document.getElementById('viewPosition').textContent = user.position || 'Not set';
                    document.getElementById('viewDepartment').textContent = user.department || 'Not set';
                    
                    // Format role display
                    let roleDisplay = user.role;
                    if (user.role === 'manager') roleDisplay = 'Department Head';
                    else if (user.role === 'director') roleDisplay = 'Director';
                    else if (user.role === 'admin') roleDisplay = 'Admin';
                    else roleDisplay = 'Employee';
                    document.getElementById('viewRole').textContent = roleDisplay;
                    
                    // Gender and Address always
                    const genderText = (user.gender === 'female') ? 'Female' : 'Male';
                    const viewGenderEl = document.getElementById('viewGender');
                    if (viewGenderEl) viewGenderEl.textContent = genderText;
                    const viewAddressEl = document.getElementById('viewAddress');
                    if (viewAddressEl) viewAddressEl.textContent = user.address && user.address.trim() !== '' ? user.address : 'Not set';
                    
                    // Employee-only fields visibility (Marital Status + Solo Parent)
                    const isEmployee = user.role === 'employee';
                    const maritalWrap = document.getElementById('viewMaritalContainer');
                    const soloWrap = document.getElementById('viewSoloContainer');
                    if (maritalWrap) maritalWrap.style.display = isEmployee ? 'block' : 'none';
                    if (soloWrap) soloWrap.style.display = isEmployee ? 'block' : 'none';
                    
                    // Populate employee-only values if visible
                    if (isEmployee) {
                        const maritalMap = { single: 'Single', married: 'Married', widowed: 'Widowed', separated: 'Separated' };
                        const maritalEl = document.getElementById('viewMarital');
                        if (maritalEl) maritalEl.textContent = maritalMap[user.marital_status] || 'Not set';
                        const soloEl = document.getElementById('viewSoloParent');
                        if (soloEl) soloEl.textContent = Number(user.is_solo_parent || 0) === 1 ? 'Yes' : 'No';
                    }
                    
                    // Format status
                    const statusText = user.account_status === 'active' ? 'Active' : 
                                     user.account_status === 'pending' ? 'Pending Verification' : 
                                     user.account_status === 'inactive' ? 'Inactive' : 'Unknown';
                    document.getElementById('viewStatus').textContent = statusText;
                    
                    openViewUserModal();
                } else {
                    showNotification('Error loading user details', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error loading user details', 'error');
            });
    }
</script>
<!-- Page Header -->
<div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 2rem;">
    <div>
        <h1 class="elms-h1" style="margin-bottom: 0.5rem; display: flex; align-items: center;">
            <i class="fas fa-users-cog" style="color: #0891b2; margin-right: 0.75rem;"></i>Manage Users
        </h1>
        <p class="elms-text-muted">Add, edit, and manage user accounts</p>
    </div>
    <button type="button" onclick="openAddUserModal()" class="elms-btn elms-btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; white-space: nowrap; padding: 0.625rem 1.25rem; font-weight: 600;">
        <i class="fas fa-plus"></i>Add New Employee
    </button>
</div>

                <!-- Search and Filter Bar -->
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-4 mb-6">
                    <div class="flex items-center justify-between gap-4">
                        <!-- Search -->
                        <div class="flex-1">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                                <input type="text" id="searchUsers" placeholder="Search User" 
                                       class="w-full pl-10 pr-4 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       onkeyup="filterUsers()">
                            </div>
                        </div>
                        
                        <!-- Role Filter -->
                        <div class="w-48">
                            <select id="filterRole" class="w-full px-4 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" onchange="filterUsers()">
                                <option value="">All</option>
                                <option value="admin">Admin</option>
                                <option value="director">Director</option>
                                <option value="manager">Department Head</option>
                                <option value="employee">Employee</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Filter Status -->
                    <div id="filterStatus" class="mt-3 text-sm text-slate-400" style="display: none;">
                        Showing <span id="filteredCount" class="text-primary font-semibold">0</span> of <span id="totalCount" class="font-semibold"><?php echo count($users); ?></span> users
                    </div>
                </div>

                <!-- Users Table -->
                <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-slate-700/50 border-b border-slate-700">
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-slate-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700">
                                <?php foreach ($users as $user): ?>
                                <tr id="user-row-<?php echo $user['id']; ?>" class="user-row hover:bg-slate-700/30 transition-colors" 
                                    data-name="<?php echo htmlspecialchars($user['name']); ?>" 
                                    data-email="<?php echo htmlspecialchars($user['email']); ?>" 
                                    data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($user['name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-slate-300"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php 
                                            echo $user['role'] === 'admin' ? 'bg-red-500/20 text-red-400' : 
                                                ($user['role'] === 'manager' ? 'bg-orange-500/20 text-orange-400' : 
                                                ($user['role'] === 'director' ? 'bg-purple-500/20 text-purple-400' : 'bg-green-500/20 text-green-400')); 
                                        ?>">
                                            <?php 
                                                echo $user['role'] === 'manager' ? 'Department Head' : 
                                                    ($user['role'] === 'director' ? 'Director' : ucfirst($user['role'])); 
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>', '<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>', '<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['contact'] ?? ''); ?>', '<?php echo htmlspecialchars($user['position'] ?? ''); ?>', '<?php echo htmlspecialchars($user['department'] ?? ''); ?>', '<?php echo htmlspecialchars($user['role']); ?>')" 
                                                    class="text-slate-400 hover:text-primary transition-colors" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="viewUser(<?php echo $user['id']; ?>)" 
                                                    class="text-slate-400 hover:text-blue-400 transition-colors" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="deleteUser(<?php echo $user['id']; ?>)" 
                                                    class="text-slate-400 hover:text-red-400 transition-colors" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-slate-800 rounded-2xl p-8 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto border border-slate-700">
            <div class="flex items-center justify-between mb-6">
                <h5 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-user-plus text-primary mr-3"></i>Add New Employee
                </h5>
                <button type="button" onclick="closeAddUserModal()" class="text-slate-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="addUserForm" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="addFirstName" class="block text-sm font-semibold text-slate-300 mb-2">First Name</label>
                        <input type="text" id="addFirstName" name="first_name" required autofocus autocomplete="off" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="addMiddleName" class="block text-sm font-semibold text-slate-300 mb-2">Middle Name</label>
                        <input type="text" id="addMiddleName" name="middle_name" autocomplete="off" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="addLastName" class="block text-sm font-semibold text-slate-300 mb-2">Last Name</label>
                        <input type="text" id="addLastName" name="last_name" required autocomplete="off" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label for="addEmail" class="block text-sm font-semibold text-slate-300 mb-2">Email</label>
                        <input type="email" id="addEmail" name="email" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                
                <div class="bg-blue-500/20 border border-blue-500/30 text-blue-400 p-4 rounded-xl">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle mr-3 mt-1"></i>
                        <div>
                            <strong>Note:</strong> A verification email will be sent to the user's email address. 
                            After verification, the user will create their own password to log in.
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="addContact" class="block text-sm font-semibold text-slate-300 mb-2">Contact</label>
                        <input type="text" id="addContact" name="contact" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="addPosition" class="block text-sm font-semibold text-slate-300 mb-2">Position</label>
                        <input type="text" id="addPosition" name="position" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div class="md:col-span-2">
                    <div id="eligibilitySection" class="bg-slate-700/40 border border-slate-600 rounded-xl p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-clipboard-check text-primary"></i>
                                <h6 class="font-semibold text-slate-200">Leave Eligibility <span class="text-slate-400 font-normal">(optional)</span></h6>
                            </div>
                        </div>
                        <p class="text-slate-400 text-sm mb-4">Enable initial leave credits. When enabled, this employee will receive initial leave credits based on your current settings.</p>
                        <div class="grid grid-cols-1 gap-3">
                            <label class="group flex items-center gap-3 px-4 py-3 rounded-lg border border-slate-600 bg-slate-800/40 hover:bg-slate-700/40 transition-colors cursor-pointer">
                                <input type="checkbox" id="addEligibleVLSL" name="eligible_vl_sl" class="h-5 w-5 text-primary bg-slate-700 border-slate-600 rounded focus:ring-primary">
                                <div class="flex flex-col">
                                    <span class="text-slate-200 font-medium">Vacation, Sick & Special Leave Privilege</span>
                                    <span class="text-slate-400 text-xs">Grants initial leave credits when enabled</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="addGender" class="block text-sm font-semibold text-slate-300 mb-2">Gender</label>
                        <select id="addGender" name="gender" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div>
                        <label for="addAddress" class="block text-sm font-semibold text-slate-300 mb-2">Address</label>
                        <input type="text" id="addAddress" name="address" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div id="addMaritalContainer">
                        <label for="addMaritalStatus" class="block text-sm font-semibold text-slate-300 mb-2">Marital Status</label>
                        <select id="addMaritalStatus" name="marital_status" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select</option>
                            <option value="single">Single</option>
                            <option value="married">Married</option>
                            <option value="widowed">Widowed</option>
                            <option value="separated">Separated</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-slate-300" id="addSoloParentContainer">
                            <input type="checkbox" id="addSoloParent" name="is_solo_parent" class="form-checkbox h-5 w-5 text-primary bg-slate-700 border-slate-600 rounded">
                            <span>Solo Parent (optional)</span>
                        </label>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="addRole" class="block text-sm font-semibold text-slate-300 mb-2">Role</label>
                        <select id="addRole" name="role" required onchange="toggleDepartmentField('add'); updateRoleDependentFields('add');" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="employee">Employee</option>
                            <option value="manager">Department Head</option>
                            <option value="director">Director Head</option>
                        </select>
                    </div>
                    <div>
                        <label for="addDepartment" class="block text-sm font-semibold text-slate-300 mb-2">Department</label>
                        <!-- Dropdown for non-manager roles -->
                        <select id="addDepartmentSelect" name="department" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Department</option>
                            <!-- Departments will be loaded dynamically -->
                        </select>
                        <!-- Text input for manager role (new department) -->
                        <input type="text" id="addDepartmentInput" name="department" placeholder="Enter new department name" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" style="display: none;">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 pt-6">
                    <button type="button" onclick="closeAddUserModal()" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02]">
                        <i class="fas fa-plus mr-2"></i>Add Employee
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View User Modal -->
    <div id="viewUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-slate-800 rounded-2xl p-8 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto border border-slate-700">
            <div class="flex items-center justify-between mb-6">
                <h5 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-user text-blue-400 mr-3"></i>User Details
                </h5>
                <button type="button" onclick="closeViewUserModal()" class="text-slate-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="space-y-6">
                <!-- User Avatar -->
                <div class="flex items-center justify-center mb-6">
                    <div class="w-24 h-24 bg-gradient-to-r from-primary to-accent rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-white text-4xl"></i>
                    </div>
                </div>
                
                <!-- User Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-slate-700/50 rounded-xl p-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Name</label>
                        <p id="viewName" class="text-white font-medium"></p>
                    </div>
                    <div class="bg-slate-700/50 rounded-xl p-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Email</label>
                        <p id="viewEmail" class="text-white font-medium"></p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-slate-700/50 rounded-xl p-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Contact</label>
                        <p id="viewContact" class="text-white font-medium"></p>
                    </div>
                    <div class="bg-slate-700/50 rounded-xl p-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Position</label>
                        <p id="viewPosition" class="text-white font-medium"></p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-slate-700/50 rounded-xl p-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Department</label>
                        <p id="viewDepartment" class="text-white font-medium"></p>
                    </div>
                    <div class="bg-slate-700/50 rounded-xl p-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Role</label>
                        <p id="viewRole" class="text-white font-medium"></p>
                    </div>
                </div>
                
                <!-- Gender + Address (always) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-slate-700/50 rounded-xl p-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Gender</label>
                        <p id="viewGender" class="text-white font-medium"></p>
                    </div>
                    <div class="bg-slate-700/50 rounded-xl p-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Address</label>
                        <p id="viewAddress" class="text-white font-medium"></p>
                    </div>
                </div>
                
                <!-- Employee-only: Marital Status + Solo Parent -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div id="viewMaritalContainer" class="bg-slate-700/50 rounded-xl p-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Marital Status</label>
                        <p id="viewMarital" class="text-white font-medium"></p>
                    </div>
                    <div id="viewSoloContainer" class="bg-slate-700/50 rounded-xl p-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Solo Parent</label>
                        <p id="viewSoloParent" class="text-white font-medium"></p>
                    </div>
                </div>
                
                <div class="bg-slate-700/50 rounded-xl p-4">
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Account Status</label>
                    <p id="viewStatus" class="text-white font-medium"></p>
                </div>
                
                <div class="flex justify-end space-x-4 pt-6">
                    <button type="button" onclick="closeViewUserModal()" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                        Close
                    </button>
                    <button type="button" onclick="editUserFromView()" class="bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02]">
                        <i class="fas fa-edit mr-2"></i>Edit User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-slate-800 rounded-2xl p-8 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto border border-slate-700">
            <div class="flex items-center justify-between mb-6">
                <h5 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-user-edit text-primary mr-3"></i>Edit User
                </h5>
                <button type="button" onclick="closeEditUserModal()" class="text-slate-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="editUserForm" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="editFirstName" class="block text-sm font-semibold text-slate-300 mb-2">First Name</label>
                        <input type="text" id="editFirstName" name="first_name" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="editMiddleName" class="block text-sm font-semibold text-slate-300 mb-2">Middle Name</label>
                        <input type="text" id="editMiddleName" name="middle_name" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="editLastName" class="block text-sm font-semibold text-slate-300 mb-2">Last Name</label>
                        <input type="text" id="editLastName" name="last_name" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label for="editEmail" class="block text-sm font-semibold text-slate-300 mb-2">Email</label>
                        <input type="email" id="editEmail" name="email" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="editContact" class="block text-sm font-semibold text-slate-300 mb-2">Contact</label>
                        <input type="text" id="editContact" name="contact" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="editPosition" class="block text-sm font-semibold text-slate-300 mb-2">Position</label>
                        <input type="text" id="editPosition" name="position" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div class="md:col-span-2">
                    <div id="editEligibilitySection" class="bg-slate-700/40 border border-slate-600 rounded-xl p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-clipboard-check text-primary"></i>
                                <h6 class="font-semibold text-slate-200">Leave Eligibility <span class="text-slate-400 font-normal">(optional)</span></h6>
                            </div>
                        </div>
                        <p class="text-slate-400 text-sm mb-4">Enable or disable initial eligibility. Turning off will set balances to 0.</p>
                        <div class="grid grid-cols-1 gap-3">
                            <label class="group flex items-center gap-3 px-4 py-3 rounded-lg border border-slate-600 bg-slate-800/40 hover:bg-slate-700/40 transition-colors cursor-pointer">
                                <input type="checkbox" id="editEligibleVLSL" name="eligible_vl_sl" class="h-5 w-5 text-primary bg-slate-700 border-slate-600 rounded focus:ring-primary">
                                <div class="flex flex-col">
                                    <span class="text-slate-200 font-medium">Vacation, Sick & Special Leave Privilege</span>
                                    <span class="text-slate-400 text-xs">Grants initial leave credits when enabled</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="editGender" class="block text-sm font-semibold text-slate-300 mb-2">Gender</label>
                        <select id="editGender" name="gender" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div>
                        <label for="editAddress" class="block text-sm font-semibold text-slate-300 mb-2">Address</label>
                        <input type="text" id="editAddress" name="address" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>



                <!-- E-Signature Section (for Department Heads and HR/Admin only) -->
                <div id="signatureSection" class="bg-slate-700/40 border border-slate-600 rounded-xl p-4" style="display: none;">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-signature text-primary"></i>
                            <h6 class="font-semibold text-slate-200">E-Signature Management</h6>
                        </div>
                    </div>
                    <p class="text-slate-400 text-sm mb-4">Upload and manage e-signature for approval processes. This signature will be used for leave approvals and official documents.</p>
                    
                    <!-- Current Signature Display -->
                    <div id="currentSignatureContainer" class="mb-4" style="display: none;">
                        <h6 class="text-sm font-semibold text-slate-300 mb-2">Current Signature:</h6>
                        <div class="bg-white p-3 rounded-lg border-2 border-dashed border-slate-400 inline-block">
                            <img id="currentSignatureImage" src="" alt="Current Signature" style="max-width: 200px; max-height: 80px;">
                        </div>
                        <div class="mt-2">
                            <button type="button" onclick="removeSignature()" class="text-red-400 hover:text-red-300 text-sm">
                                <i class="fas fa-trash mr-1"></i>Remove Signature
                            </button>
                        </div>
                    </div>
                    
                    <!-- Upload New Signature -->
                    <div id="uploadSignatureContainer">
                        <h6 class="text-sm font-semibold text-slate-300 mb-2">Upload New Signature:</h6>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="signatureFile" class="block text-sm text-slate-400 mb-2">
                                    Select signature image (PNG, JPG, JPEG)
                                </label>
                                <input type="file" id="signatureFile" accept="image/png,image/jpeg,image/jpg" 
                                       class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary/90">
                            </div>
                            <div class="flex gap-3">
                                <button type="button" onclick="uploadSignature()" class="bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                                    <i class="fas fa-upload mr-2"></i>Upload Signature
                                </button>
                                <button type="button" onclick="previewSignature()" class="bg-slate-600 hover:bg-slate-500 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                                    <i class="fas fa-eye mr-2"></i>Preview
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preview Area -->
                    <div id="signaturePreview" class="mt-4" style="display: none;">
                        <h6 class="text-sm font-semibold text-slate-300 mb-2">Preview:</h6>
                        <div class="bg-white p-3 rounded-lg border-2 border-dashed border-slate-400 inline-block">
                            <img id="previewSignatureImage" src="" alt="Signature Preview" style="max-width: 200px; max-height: 80px;">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div id="editMaritalContainer">
                        <label for="editMaritalStatus" class="block text-sm font-semibold text-slate-300 mb-2">Marital Status</label>
                        <select id="editMaritalStatus" name="marital_status" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select</option>
                            <option value="single">Single</option>
                            <option value="married">Married</option>
                            <option value="widowed">Widowed</option>
                            <option value="separated">Separated</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-slate-300" id="editSoloParentContainer">
                            <input type="checkbox" id="editSoloParent" name="is_solo_parent" class="form-checkbox h-5 w-5 text-primary bg-slate-700 border-slate-600 rounded">
                            <span>Solo Parent (optional)</span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2">Role</label>
                        <!-- Hidden input to preserve the role value -->
                        <input type="hidden" id="editRole" name="role" value="">
                        <!-- Read-only display of role -->
                        <div id="editRoleDisplay" class="w-full bg-slate-600 border border-slate-500 rounded-xl px-4 py-3 text-slate-300 cursor-not-allowed">
                            <span id="editRoleText">-</span>
                            <span class="text-xs text-slate-400 ml-2">(cannot be changed)</span>
                        </div>
                    </div>
                    <div>
                        <label for="editDepartment" class="block text-sm font-semibold text-slate-300 mb-2">Department</label>
                        <!-- Dropdown for non-manager roles -->
                        <select id="editDepartmentSelect" name="department" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Department</option>
                            <!-- Departments will be loaded dynamically -->
                        </select>
                        <!-- Text input for manager role (new department) -->
                        <input type="text" id="editDepartmentInput" name="department" placeholder="Enter new department name" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus-border-transparent" style="display: none;">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 pt-6">
                    <button type="button" onclick="closeEditUserModal()" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02]">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle department field between dropdown and text input based on role
        function toggleDepartmentField(modalType) {
            const roleElement = document.getElementById(modalType + 'Role');
            const deptSelect = document.getElementById(modalType + 'DepartmentSelect');
            const deptInput = document.getElementById(modalType + 'DepartmentInput');
            
            // Get role value - for edit modal it's a hidden input, for add modal it's a select
            const roleValue = roleElement ? roleElement.value : 'employee';
            
            if (roleValue === 'manager') {
                // Department Head - show text input for new department
                deptSelect.style.display = 'none';
                deptSelect.removeAttribute('name');
                deptInput.style.display = 'block';
                deptInput.setAttribute('name', 'department');
                // Don't clear value for edit modal
                if (modalType === 'add') {
                    deptInput.value = '';
                }
            } else {
                // Other roles - show dropdown
                deptSelect.style.display = 'block';
                deptSelect.setAttribute('name', 'department');
                deptInput.style.display = 'none';
                deptInput.removeAttribute('name');
                if (modalType === 'add') {
                    deptInput.value = '';
                }
            }
        }

        // Load departments into dropdown
        function loadDepartments() {
            // Add timestamp to prevent caching
            const timestamp = new Date().getTime();
            fetch('../api/get_departments.php?t=' + timestamp)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const addDeptSelect = document.getElementById('addDepartmentSelect');
                        const editDeptSelect = document.getElementById('editDepartmentSelect');
                        
                        // Clear existing options except the first one
                        addDeptSelect.innerHTML = '<option value="">Select Department</option>';
                        editDeptSelect.innerHTML = '<option value="">Select Department</option>';
                        
                        // Add departments to both dropdowns
                        data.departments.forEach(dept => {
                            const addOption = document.createElement('option');
                            addOption.value = dept;
                            addOption.textContent = dept;
                            addDeptSelect.appendChild(addOption);
                            
                            const editOption = document.createElement('option');
                            editOption.value = dept;
                            editOption.textContent = dept;
                            editDeptSelect.appendChild(editOption);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading departments:', error);
                });
        }

        // Modal functions
        function focusAddName(retries = 5) {
            const nameInput = document.getElementById('addName');
            if (nameInput) {
                try {
                    nameInput.blur();
                    nameInput.focus({ preventScroll: true });
                    return true;
                } catch (e) {}
            }
            if (retries > 0) {
                setTimeout(() => focusAddName(retries - 1), 100);
            }
            return false;
        }

        function openAddUserModal() {
            loadDepartments(); // Load departments when opening modal
            toggleDepartmentField('add'); // Set initial department field state
            document.getElementById('addUserModal').classList.remove('hidden');
            document.getElementById('addUserModal').classList.add('flex');
            updateRoleDependentFields('add');
            // Ensure modal starts at top and focus Name field for quick entry
            setTimeout(() => {
                const modalContent = document.querySelector('#addUserModal > div');
                if (modalContent) modalContent.scrollTop = 0;
                requestAnimationFrame(() => { focusAddName(); });
            }, 150);
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').classList.add('hidden');
            document.getElementById('addUserModal').classList.remove('flex');
        }

        function openEditUserModal() {
            loadDepartments(); // Load departments when opening modal
            document.getElementById('editUserModal').classList.remove('hidden');
            document.getElementById('editUserModal').classList.add('flex');
            updateRoleDependentFields('edit');
        }

        function closeEditUserModal() {
            document.getElementById('editUserModal').classList.add('hidden');
            document.getElementById('editUserModal').classList.remove('flex');
        }

        function openViewUserModal() {
            document.getElementById('viewUserModal').classList.remove('hidden');
            document.getElementById('viewUserModal').classList.add('flex');
        }

        function closeViewUserModal() {
            document.getElementById('viewUserModal').classList.add('hidden');
            document.getElementById('viewUserModal').classList.remove('flex');
        }

        // Store current user data for edit from view
        let currentViewUserId = null;
        let currentViewUserData = {};

        function editUserFromView() {
            closeViewUserModal();
            if (currentViewUserId && currentViewUserData) {
                editUser(
                    currentViewUserId,
                    currentViewUserData.first_name || '',
                    currentViewUserData.middle_name || '',
                    currentViewUserData.last_name || '',
                    currentViewUserData.email,
                    currentViewUserData.contact,
                    currentViewUserData.position,
                    currentViewUserData.department,
                    currentViewUserData.role
                );
            }
        }

        // Global functions
        function updateRoleDependentFields(modalType) {
            const role = document.getElementById(modalType + 'Role')?.value || 'employee';
            const isEmployee = role === 'employee';
            const elig = document.getElementById(modalType === 'add' ? 'eligibilitySection' : 'editEligibilitySection');
            // Correctly resolve the Marital Status container per modal
            const maritalContainer = document.getElementById(modalType === 'add' ? 'addMaritalContainer' : 'editMaritalContainer');
            const soloContainer = document.getElementById(modalType === 'add' ? 'addSoloParentContainer' : 'editSoloParentContainer');

            if (elig) elig.style.display = isEmployee ? 'block' : 'none';
            if (maritalContainer) maritalContainer.style.display = isEmployee ? 'block' : 'none';
            if (soloContainer) soloContainer.style.display = isEmployee ? 'inline-flex' : 'none';
        }
        function deleteUser(userId) {
            showStyledConfirm('Are you sure you want to delete this user? This action cannot be undone.', function(confirmed) {
                if (!confirmed) return;
                
                // Find and hide the user row immediately
                const userRow = document.getElementById(`user-row-${userId}`);
            if (userRow) {
                userRow.style.opacity = '0.5';
                userRow.style.pointerEvents = 'none';
            }
            
            showNotification('Deleting user...', 'info');
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', userId);
            
            // Add timeout to prevent hanging
            const timeoutId = setTimeout(() => {
                if (userRow) {
                    userRow.style.opacity = '1';
                    userRow.style.pointerEvents = 'auto';
                    window.location.reload(); // Reload to restore original state
                }
                showNotification('Delete request timed out. Please try again.', 'error');
            }, 10000); // 10 second timeout
            
            fetch('manage_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                clearTimeout(timeoutId);
                return response.text();
            })
            .then(data => {
                console.log('Response:', data); // Debug log
                
                // Check if the response contains an error message
                if (data.includes('error_message') || data.includes('Error') || data.includes('Failed')) {
                    // Restore the user row if deletion failed
                    if (userRow) {
                        userRow.style.opacity = '1';
                        userRow.style.pointerEvents = 'auto';
                        // Reload the page to restore the original content
                        window.location.reload();
                    }
                    
                    // Try to extract the specific error message
                    const errorMatch = data.match(/error_message.*?['"](.*?)['"]/);
                    const errorMsg = errorMatch ? errorMatch[1] : 'Error deleting user. Please try again.';
                    showNotification(errorMsg, 'error');
                } else {
                    showNotification('User deleted successfully!', 'success');
                    // Remove the user row from DOM immediately
                    if (userRow) {
                        userRow.remove();
                    }
                    // Force immediate page reload as backup
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error('Delete error:', error);
                // Restore the user row if deletion failed
                if (userRow) {
                    userRow.style.opacity = '1';
                    userRow.style.pointerEvents = 'auto';
                    window.location.reload(); // Reload to restore original state
                }
                showNotification('Error deleting user: ' + error.message, 'error');
            });
            }, 'danger', 'Delete User', 'Yes, Delete', 'Cancel');
        }

        function editUser(id, firstName, middleName, lastName, email, contact, position, department, role) {
            document.getElementById('editId').value = id;
            document.getElementById('editFirstName').value = firstName || '';
            document.getElementById('editMiddleName').value = middleName || '';
            document.getElementById('editLastName').value = lastName || '';
            document.getElementById('editEmail').value = email || '';
            document.getElementById('editContact').value = contact || '';
            document.getElementById('editPosition').value = position || '';
            document.getElementById('editRole').value = role || 'employee';
            
            // Set the role display text (read-only)
            const roleDisplayText = {
                'employee': 'Employee',
                'manager': 'Department Head',
                'director': 'Director Head',
                'admin': 'Admin'
            };
            document.getElementById('editRoleText').textContent = roleDisplayText[role] || role || 'Employee';
            
            // Open modal first, then set department after departments are loaded
            openEditUserModal();
            
            // Set department value after a short delay to ensure departments are loaded
            setTimeout(() => {
                toggleDepartmentField('edit'); // Toggle field based on role
                
                if (role === 'manager') {
                    // For Department Head, use text input
                    document.getElementById('editDepartmentInput').value = department || '';
                } else {
                    // For other roles, use dropdown
                    const deptSelect = document.getElementById('editDepartmentSelect');
                    if (department) {
                        // Check if the department exists in the dropdown
                        let optionExists = false;
                        for (let i = 0; i < deptSelect.options.length; i++) {
                            if (deptSelect.options[i].value === department) {
                                optionExists = true;
                                break;
                            }
                        }
                        
                        // If department doesn't exist in dropdown, add it
                        if (!optionExists && department !== '') {
                            const newOption = document.createElement('option');
                            newOption.value = department;
                            newOption.textContent = department;
                            deptSelect.appendChild(newOption);
                        }
                        
                        deptSelect.value = department;
                    } else {
                        deptSelect.value = '';
                    }
                }
            }, 100);

            // Prefill gender, solo parent, and eligibility using latest data
            fetch(`manage_user.php?action=get_user&id=${encodeURIComponent(id)}`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.success && data.user) {
                        const u = data.user;
                        const genderSel = document.getElementById('editGender');
                        if (genderSel && (u.gender === 'male' || u.gender === 'female')) {
                            genderSel.value = u.gender;
                        }
                        const solo = document.getElementById('editSoloParent');
                        if (solo) solo.checked = Number(u.is_solo_parent || 0) === 1;
                        // Prefill eligibility based on current balances
                        const fusedEl = document.getElementById('editEligibleVLSL');
                        const vac = parseFloat(u.vacation_leave_balance || 0);
                        const sick = parseFloat(u.sick_leave_balance || 0);
                        const slp = parseFloat(u.special_leave_privilege_balance || 0);
                        if (fusedEl) fusedEl.checked = (vac > 0 || sick > 0 || slp > 0);

                        // Toggle visibility by role
                        updateRoleDependentFields('edit');
                        // Show signature section for department heads and HR
                        showSignatureSection(role);
                        const addr = document.getElementById('editAddress');
                        const marital = document.getElementById('editMaritalStatus');
                        if (addr) addr.value = u.address || '';
                        if (marital && u.marital_status) marital.value = u.marital_status;
                    }
                })
                .catch(() => {});
        }

        function showNotification(message, type = 'info') {
            const bgColor = type === 'success' ? 'bg-green-500/20 border-green-500/30 text-green-400' : 
                           type === 'error' ? 'bg-red-500/20 border-red-500/30 text-red-400' : 
                           type === 'warning' ? 'bg-yellow-500/20 border-yellow-500/30 text-yellow-400' : 
                           'bg-blue-500/20 border-blue-500/30 text-blue-400';
            
            const icon = type === 'success' ? 'fa-check-circle' : 
                        type === 'error' ? 'fa-exclamation-circle' : 
                        type === 'warning' ? 'fa-exclamation-triangle' : 
                        'fa-info-circle';
            
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 ${bgColor} border rounded-xl p-4 max-w-sm shadow-xl`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${icon} mr-3"></i>
                    <div class="flex-1">${message}</div>
                    <button type="button" onclick="this.parentElement.parentElement.remove()" class="ml-3 text-current hover:opacity-75">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    const userItems = document.querySelectorAll('#usersRow > div');
                    
                    userItems.forEach(item => {
                        const userInfo = item.textContent.toLowerCase();
                        if (searchTerm === '' || userInfo.includes(searchTerm)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }

            // Initialize form submissions
            const addUserForm = document.getElementById('addUserForm');
            if (addUserForm) {
                addUserForm.addEventListener('submit', function(e) {
                    // Don't prevent default - let the form submit normally
                    // The server will handle redirect with session messages
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'add';
                    this.appendChild(actionInput);
                });
            }

            const editUserForm = document.getElementById('editUserForm');
            if (editUserForm) {
                editUserForm.addEventListener('submit', function(e) {
                    // Don't prevent default - let the form submit normally
                    // The server will handle redirect with session messages
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'edit';
                    this.appendChild(actionInput);
                });
            }

            
            // Filter Users Function
            window.filterUsers = function() {
                const searchTerm = document.getElementById('searchUsers').value.toLowerCase().trim();
                const roleFilter = document.getElementById('filterRole').value.toLowerCase().trim();
                
                console.log('Filtering - Search:', searchTerm, 'Role:', roleFilter);
                
                let visibleCount = 0;
                const totalCount = document.querySelectorAll('.user-row').length;
                
                document.querySelectorAll('.user-row').forEach(row => {
                    const name = row.dataset.name ? row.dataset.name.toLowerCase() : '';
                    const email = row.dataset.email ? row.dataset.email.toLowerCase() : '';
                    const role = row.dataset.role ? row.dataset.role.toLowerCase() : '';
                    
                    console.log('Row role:', role, 'Filter:', roleFilter, 'Match:', role === roleFilter);
                    
                    const matchesSearch = !searchTerm || name.includes(searchTerm) || email.includes(searchTerm);
                    const matchesRole = !roleFilter || role === roleFilter;
                    
                    if (matchesSearch && matchesRole) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                console.log('Visible count:', visibleCount, 'Total:', totalCount);
                
                // Update filter status
                const filterStatus = document.getElementById('filterStatus');
                const filteredCount = document.getElementById('filteredCount');
                
                if (filteredCount) {
                    filteredCount.textContent = visibleCount;
                }
                
                // Show/hide filter status based on whether filtering is active
                if (filterStatus) {
                    if (searchTerm || roleFilter) {
                        filterStatus.style.display = 'block';
                    } else {
                        filterStatus.style.display = 'none';
                    }
                }
            }
            

            // Function to fetch pending leave count
            function fetchPendingLeaveCount() {
                fetch('../../../../api/get_pending_leave_count.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const badge = document.getElementById('pendingLeaveBadge');
                            if (data.count > 0) {
                                badge.textContent = data.count;
                                badge.style.display = 'inline-block';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching pending leave count:', error);
                    });
            }

            // Fetch pending leave count on page load
            fetchPendingLeaveCount();

            // Update pending leave count every 30 seconds
            setInterval(fetchPendingLeaveCount, 30000);
            
            // Clean up any misplaced elements in dropdowns
            cleanupDropdowns();
        });
        
        // Signature Management Functions
        function showSignatureSection(role) {
            const signatureSection = document.getElementById('signatureSection');
            // Show signature section only for department heads (manager) and HR (admin)
            if (role === 'manager' || role === 'admin') {
                signatureSection.style.display = 'block';
                loadCurrentSignature();
            } else {
                signatureSection.style.display = 'none';
            }
        }
        
        function loadCurrentSignature() {
            const userId = document.getElementById('editId').value;
            if (!userId) return;
            
            fetch(`../../../../api/get_user_signature.php?user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.signature) {
                        document.getElementById('currentSignatureImage').src = data.signature;
                        document.getElementById('currentSignatureContainer').style.display = 'block';
                        document.getElementById('uploadSignatureContainer').querySelector('h6').textContent = 'Replace Signature:';
                    } else {
                        document.getElementById('currentSignatureContainer').style.display = 'none';
                        document.getElementById('uploadSignatureContainer').querySelector('h6').textContent = 'Upload New Signature:';
                    }
                })
                .catch(error => {
                    console.error('Error loading signature:', error);
                });
        }
        
        function previewSignature() {
            const fileInput = document.getElementById('signatureFile');
            const file = fileInput.files[0];
            
            if (!file) {
                showNotification('Please select a signature file first', 'warning');
                return;
            }
            
            // Validate file type
            if (!file.type.match(/^image\/(png|jpeg|jpg)$/)) {
                showNotification('Please select a PNG, JPG, or JPEG image', 'error');
                return;
            }
            
            // Validate file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                showNotification('File size must be less than 2MB', 'error');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewSignatureImage').src = e.target.result;
                document.getElementById('signaturePreview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
        
        function uploadSignature() {
            const fileInput = document.getElementById('signatureFile');
            const file = fileInput.files[0];
            const userId = document.getElementById('editId').value;
            
            if (!file) {
                showNotification('Please select a signature file first', 'warning');
                return;
            }
            
            if (!userId) {
                showNotification('User ID not found', 'error');
                return;
            }
            
            // Validate file type
            if (!file.type.match(/^image\/(png|jpeg|jpg)$/)) {
                showNotification('Please select a PNG, JPG, or JPEG image', 'error');
                return;
            }
            
            // Validate file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                showNotification('File size must be less than 2MB', 'error');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const signatureData = e.target.result;
                
                // Show loading state
                const uploadBtn = document.querySelector('button[onclick="uploadSignature()"]');
                const originalText = uploadBtn.innerHTML;
                uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';
                uploadBtn.disabled = true;
                
                fetch('../../../../api/upload_signature.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        userId: userId,
                        signatureData: signatureData
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Signature uploaded successfully!', 'success');
                        loadCurrentSignature(); // Reload to show new signature
                        // Clear file input and preview
                        fileInput.value = '';
                        document.getElementById('signaturePreview').style.display = 'none';
                    } else {
                        showNotification('Error uploading signature: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error uploading signature', 'error');
                })
                .finally(() => {
                    // Restore button state
                    uploadBtn.innerHTML = originalText;
                    uploadBtn.disabled = false;
                });
            };
            reader.readAsDataURL(file);
        }
        
        function removeSignature() {
            const userId = document.getElementById('editId').value;
            
            if (!userId) {
                showNotification('User ID not found', 'error');
                return;
            }
            
            if (!confirm('Are you sure you want to remove this signature?')) {
                return;
            }
            
            fetch('../../../../api/remove_signature.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    userId: userId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Signature removed successfully!', 'success');
                    document.getElementById('currentSignatureContainer').style.display = 'none';
                    document.getElementById('uploadSignatureContainer').querySelector('h6').textContent = 'Upload New Signature:';
                } else {
                    showNotification('Error removing signature: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error removing signature', 'error');
            });
        }
        
        // Function to clean up misplaced elements in dropdowns
        function cleanupDropdowns() {
            const userDropdown = document.getElementById('userDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            [userDropdown, notificationDropdown].forEach(dropdown => {
                if (dropdown) {
                    // Remove any input elements that shouldn't be in the dropdown
                    const misplacedInputs = dropdown.querySelectorAll('input');
                    misplacedInputs.forEach(input => {
                        input.remove();
                    });
                    
                    // Ensure proper styling
                    dropdown.style.position = 'absolute';
                    dropdown.style.zIndex = '1000';
                    dropdown.style.isolation = 'isolate';
                }
            });
        }
        
        // Show existing messages from session
        <?php if (isset($_SESSION['success'])): ?>
            showNotification('<?php echo addslashes($_SESSION['success']); ?>', 'success');
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            showNotification('<?php echo addslashes($_SESSION['error']); ?>', 'error');
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
    
    <style>
        /* Fix dropdown overlay issues */
        #userDropdown, #notificationDropdown {
            z-index: 1000 !important;
            position: absolute !important;
            isolation: isolate !important;
            overflow: hidden !important;
            contain: layout style paint !important;
        }
        
        /* Prevent search input from appearing in dropdown */
        #searchInput {
            position: relative;
            z-index: 1;
        }
        
        /* Ensure dropdown content is properly contained */
        #userDropdown .p-2 {
            position: relative;
            z-index: 1001;
            background: inherit;
        }
        
        /* Prevent any input elements from appearing in dropdown */
        #userDropdown input {
            display: none !important;
        }
        
        /* Additional isolation for the search section */
        .bg-slate-800.rounded-2xl.p-6.mb-8.border.border-slate-700 {
            position: relative;
            z-index: 1;
        }
    </style>
    <script src="../../../../assets/js/modal-alert.js"></script>
    
<?php include '../../../../includes/admin_footer.php'; ?> 