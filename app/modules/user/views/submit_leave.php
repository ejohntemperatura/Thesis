<?php
// Start output buffering to prevent any rendering
ob_start();
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/LeaveCreditsManager.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); // Clear any output
    header('Location: ../../../auth/views/login.php');
    exit();
}

$employee_id = $_SESSION['user_id'];

// Verify employee exists in database
$stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
$stmt->execute([$employee_id]);
if (!$stmt->fetch()) {
    session_destroy();
    $_SESSION['error'] = "Your session has expired. Please log in again.";
    header('Location: ../../../auth/views/login.php');
    exit();
}

$leave_type = $_POST['leave_type'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$reason = $_POST['reason'];

// Get conditional fields based on leave type
$location_type = $_POST['location_type'] ?? null;
$location_specify = $_POST['location_specify'] ?? null;
$medical_condition = $_POST['medical_condition'] ?? null;
$illness_specify = $_POST['illness_specify'] ?? null;
$special_women_condition = $_POST['special_women_condition'] ?? null;
$study_type = $_POST['study_type'] ?? null;

// Handle medical certificate upload for sick leave
$medical_certificate_path = null;
if ($leave_type === 'sick' && isset($_FILES['medical_certificate']) && $_FILES['medical_certificate']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../../../../uploads/medical_certificates/' . date('Y') . '/' . date('m') . '/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    $file_extension = strtolower(pathinfo($_FILES['medical_certificate']['name'], PATHINFO_EXTENSION));
    $file_size = $_FILES['medical_certificate']['size'];
    
    if (!in_array($file_extension, $allowed_types)) {
        $_SESSION['error'] = "Invalid file type. Only PDF, JPG, JPEG, PNG, DOC, DOCX files are allowed.";
        header('Location: dashboard.php');
        exit();
    }
    
    if ($file_size > $max_size) {
        $_SESSION['error'] = "File size too large. Maximum size allowed is 10MB.";
        header('Location: dashboard.php');
        exit();
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['medical_certificate']['tmp_name'], $file_path)) {
        $medical_certificate_path = $file_path;
    } else {
        $_SESSION['error'] = "Failed to upload medical certificate.";
        header('Location: dashboard.php');
        exit();
    }
}

// Calculate number of days (inclusive) excluding weekends
$start = new DateTime($start_date);
$end = new DateTime($end_date);
if ($end < $start) {
    $_SESSION['error'] = "End date cannot be before start date.";
    header('Location: dashboard.php');
    exit();
}

// Calculate days excluding Saturdays and Sundays
$days = 0;
$current = clone $start;
while ($current <= $end) {
    $dayOfWeek = (int)$current->format('N'); // 1 (Monday) to 7 (Sunday)
    // Only count weekdays (Monday=1 to Friday=5)
    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
        $days++;
    }
    $current->modify('+1 day');
}

// Check if the leave application is for past dates (late application)
$today = new DateTime();
$today->setTime(0, 0, 0); // Reset time to start of day for accurate comparison

if ($start < $today) {
    // This is a late application - redirect to late application form
    $_SESSION['late_application_data'] = [
        'leave_type' => $leave_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'reason' => $reason,
        'location_type' => $location_type,
        'location_specify' => $location_specify,
        'medical_condition' => $medical_condition,
        'illness_specify' => $illness_specify,
        'special_women_condition' => $special_women_condition,
        'study_type' => $study_type,
        'medical_certificate_path' => $medical_certificate_path,
        'days' => $days
    ];
    
    $_SESSION['warning'] = "You are applying for leave with dates in the past. Please use the Late Leave Application form to provide justification for the late submission.";
    header('Location: dashboard.php');
    exit();
}

// Check leave credits using the LeaveCreditsManager
$creditsManager = new LeaveCreditsManager($pdo);
$creditCheck = $creditsManager->checkLeaveCredits($employee_id, $leave_type, $start_date, $end_date);

// Special case: Study leave should always show popup for without pay option
if ($leave_type === 'study') {
    $creditCheck['sufficient'] = false;
    $creditCheck['message'] = 'Study leave is typically without pay. Would you like to proceed with without pay leave?';
}

// Special case: CTO leave requires sufficient credits - cannot proceed without pay
// DOUBLE CHECK: Direct database query to ensure CTO balance is available
if ($leave_type === 'cto') {
    $stmt = $pdo->prepare("SELECT cto_balance FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $cto_balance = $stmt->fetchColumn() ?: 0;
    
    $hours_requested = (int)$days * 8; // Calculate hours needed
    
    if ($cto_balance <= 0) {
        $_SESSION['error'] = "You have no CTO credits available. Current balance: {$cto_balance} hours. Cannot submit CTO leave request.";
        header('Location: dashboard.php');
        exit();
    }
    
    if ($hours_requested > $cto_balance) {
        $_SESSION['error'] = "Insufficient CTO balance. Available: {$cto_balance} hours, Requested: {$hours_requested} hours. Cannot submit CTO leave request.";
        header('Location: dashboard.php');
        exit();
    }
    
    // If creditCheck says insufficient but balance check passes, use the balance check
    if (!$creditCheck['sufficient']) {
        $_SESSION['error'] = $creditCheck['message'] . " CTO leave cannot be taken without pay.";
        header('Location: dashboard.php');
        exit();
    }
}

// Check if user wants to proceed with without pay leave
$proceed_without_pay = isset($_POST['proceed_without_pay']) && $_POST['proceed_without_pay'] === 'yes';

// Additional check: CTO cannot proceed without pay even if proceed_without_pay is set
if ($leave_type === 'cto' && !$creditCheck['sufficient'] && $proceed_without_pay) {
    $_SESSION['error'] = "CTO leave requires sufficient credits and cannot be taken without pay.";
    header('Location: dashboard.php');
    exit();
}

// Prevent duplicate submissions
$submission_key = $employee_id . '_' . $leave_type . '_' . $start_date . '_' . $end_date;
if (isset($_SESSION['last_submission']) && $_SESSION['last_submission'] === $submission_key) {
    $_SESSION['error'] = "Duplicate submission detected. Please wait a moment before submitting again.";
    header('Location: dashboard.php');
    exit();
}

if (!$creditCheck['sufficient'] && !$proceed_without_pay) {
    // Store the form data and show popup for insufficient credits
    $_SESSION['insufficient_credits_data'] = [
        'leave_type' => $leave_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'reason' => $reason,
        'location_type' => $location_type,
        'location_specify' => $location_specify,
        'medical_condition' => $medical_condition,
        'illness_specify' => $illness_specify,
        'special_women_condition' => $special_women_condition,
        'study_type' => $study_type,
        'medical_certificate_path' => $medical_certificate_path,
        'days' => $days,
        'credit_message' => $creditCheck['message']
    ];
    
    // Store form data temporarily for auto-submission
    $_SESSION['temp_insufficient_credits_data'] = [
        'leave_type' => $leave_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'reason' => $reason,
        'location_type' => $location_type,
        'location_specify' => $location_specify,
        'medical_condition' => $medical_condition,
        'illness_specify' => $illness_specify,
        'special_women_condition' => $special_women_condition,
        'study_type' => $study_type,
        'medical_certificate_path' => $medical_certificate_path,
        'days' => $days,
        'credit_message' => $creditCheck['message']
    ];
    
    $_SESSION['show_insufficient_credits_popup'] = true;
    header('Location: dashboard.php');
    exit();
}

// If proceeding without pay, change leave type to without_pay and store original type
$original_leave_type = null;
if ($proceed_without_pay) {
    // Use the original_leave_type from form if provided, otherwise use current leave_type
    $original_leave_type = isset($_POST['original_leave_type']) ? $_POST['original_leave_type'] : $leave_type;
    $leave_type = 'without_pay';
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // FINAL CHECK for CTO balance before inserting
    if ($leave_type === 'cto') {
        $stmt = $pdo->prepare("SELECT cto_balance FROM employees WHERE id = ? FOR UPDATE");
        $stmt->execute([$employee_id]);
        $final_cto_balance = $stmt->fetchColumn() ?: 0;
        $final_hours_requested = (int)$days * 8;
        
        if ($final_cto_balance <= 0) {
            $pdo->rollBack();
            $_SESSION['error'] = "Cannot submit CTO leave request. You have no CTO credits available (Current balance: {$final_cto_balance} hours).";
            header('Location: dashboard.php');
            exit();
        }
        
        if ($final_hours_requested > $final_cto_balance) {
            $pdo->rollBack();
            $_SESSION['error'] = "Cannot submit CTO leave request. Insufficient CTO balance. Available: {$final_cto_balance} hours, Requested: {$final_hours_requested} hours.";
            header('Location: dashboard.php');
            exit();
        }
    }

    // Insert leave request with conditional fields
    // Check if original_leave_type column exists
    try {
        $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, original_leave_type, start_date, end_date, reason, status, days_requested, location_type, location_specify, medical_condition, illness_specify, special_women_condition, study_type, medical_certificate_path, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$employee_id, $leave_type, $original_leave_type, $start_date, $end_date, $reason, $days, $location_type, $location_specify, $medical_condition, $illness_specify, $special_women_condition, $study_type, $medical_certificate_path]);
    } catch (PDOException $e) {
        // If original_leave_type column doesn't exist, use the old query
        if (strpos($e->getMessage(), 'original_leave_type') !== false) {
            $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status, days_requested, location_type, location_specify, medical_condition, illness_specify, special_women_condition, study_type, medical_certificate_path, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$employee_id, $leave_type, $start_date, $end_date, $reason, $days, $location_type, $location_specify, $medical_condition, $illness_specify, $special_women_condition, $study_type, $medical_certificate_path]);
        } else {
            throw $e; // Re-throw if it's a different error
        }
    }

    // Get the insert ID before committing the transaction
    $leaveRequestId = $pdo->lastInsertId();
    
    // For CTO leave type, deduct credits immediately when submitted (not on approval)
    if ($leave_type === 'cto') {
        try {
            $creditsManager->deductLeaveCredits($employee_id, 'cto', $start_date, $end_date);
            error_log("CTO credits deducted immediately on submission - Request ID: $leaveRequestId");
        } catch (Exception $e) {
            // If deduction fails, rollback the transaction
            $pdo->rollBack();
            $_SESSION['error'] = "Failed to deduct CTO credits: " . $e->getMessage();
            error_log("CTO credit deduction failed: " . $e->getMessage());
            header('Location: dashboard.php');
            exit();
        }
    }
    
    // Note: For other leave types, credits will be deducted when the leave is approved by Director
    // This ensures we deduct only the approved days, not the requested days

    $pdo->commit();
    
    // Send notification to department head
    try {
        require_once '../../../../app/core/services/NotificationHelper.php';
        $notificationHelper = new NotificationHelper($pdo);
        $notificationHelper->notifyDepartmentHeadNewLeave($leaveRequestId);
    } catch (Exception $e) {
        error_log("Department head notification failed: " . $e->getMessage());
        // Don't fail the submission if notification fails
    }
    
    // Notify HR of new leave submission (FYI)
    try {
        if (!isset($notificationHelper)) {
            require_once '../../../../app/core/services/NotificationHelper.php';
            $notificationHelper = new NotificationHelper($pdo);
        }
        $notificationHelper->notifyHRNewLeave($leaveRequestId);
    } catch (Exception $e) {
        error_log("HR notification (new leave) failed: " . $e->getMessage());
        // Non-blocking
    }
    
    // Track successful submission to prevent duplicates
    $_SESSION['last_submission'] = $submission_key;
    $_SESSION['last_submission_time'] = time();
    
    // Set flags to show success modal only (no redundant banner message)
    $_SESSION['show_success_modal'] = true;
    $_SESSION['success_leave_type'] = $original_leave_type ?: $leave_type;
    
    // Clear insufficient credits modal session variables to prevent re-display
    unset($_SESSION['show_insufficient_credits_popup']);
    unset($_SESSION['insufficient_credits_data']);
    unset($_SESSION['temp_insufficient_credits_data']);
    
    // Debug: Log successful submission
    error_log("Leave request submitted successfully - ID: $leaveRequestId, Employee: $employee_id, Leave Type: $leave_type, Original: $original_leave_type");
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error submitting leave request: " . $e->getMessage();
    error_log("Leave submission error: " . $e->getMessage());
}

// Clear any output and redirect
ob_end_clean();
header('Location: dashboard.php');
exit();
?> 