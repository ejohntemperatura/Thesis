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
$late_justification = $_POST['late_justification'] ?? '';
// Reason field is no longer required - only late_justification is used
$reason = null;

// Get selected dates and days count from form (same as regular leave application)
$selected_dates = $_POST['selected_dates'] ?? '';
$days_count = isset($_POST['days_count']) ? (int)$_POST['days_count'] : 0;

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

// Calculate number of days using selected_dates (same logic as regular leave application)
// Debug: Log received values
error_log("Late leave submission - selected_dates: '$selected_dates', days_count: $days_count, start: $start_date, end: $end_date");

// If selected_dates is provided, use ONLY those dates
if (!empty($selected_dates)) {
    $dates_array = array_filter(explode(',', $selected_dates)); // Remove empty values
    $days_count = count($dates_array);
    error_log("Calculated days from selected_dates: $days_count dates: " . implode(', ', $dates_array));
    
    // Update start_date and end_date to match the actual selected dates
    if ($days_count > 0) {
        sort($dates_array); // Sort dates chronologically
        $start_date = $dates_array[0]; // First selected date
        $end_date = $dates_array[$days_count - 1]; // Last selected date
        error_log("Updated date range based on selected dates: $start_date to $end_date");
    }
}

// Calculate number of days from selected dates or fallback to range calculation
$start = new DateTime($start_date);
$end = new DateTime($end_date);

if ($end < $start) {
    $_SESSION['error'] = "End date cannot be before start date.";
    header('Location: dashboard.php');
    exit();
}

// Use the days_count from form if provided (from calendar picker with selected dates)
// Otherwise fallback to calculating from date range
if ($days_count > 0 && !empty($selected_dates)) {
    // Use the count from selected dates (specific dates chosen by user)
    $days = $days_count;
} else {
    // Fallback: Calculate days excluding Saturdays and Sundays from the full range
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
}

// Late leave application now allows ANY dates (past, present, or future)
// The "late" designation simply means it requires justification, not that dates must be in the past

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
$submission_key = $employee_id . '_' . $leave_type . '_' . $start_date . '_' . $end_date . '_late';
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
        'selected_dates' => $selected_dates,
        'days_count' => $days_count,
        'reason' => $reason,
        'location_type' => $location_type,
        'location_specify' => $location_specify,
        'medical_condition' => $medical_condition,
        'illness_specify' => $illness_specify,
        'special_women_condition' => $special_women_condition,
        'study_type' => $study_type,
        'medical_certificate_path' => $medical_certificate_path,
        'days' => $days,
        'credit_message' => $creditCheck['message'],
        'is_late' => true,
        'late_justification' => $_POST['late_justification'] ?? ''
    ];
    
    // Store form data temporarily for auto-submission
    $_SESSION['temp_insufficient_credits_data'] = [
        'leave_type' => $leave_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'selected_dates' => $selected_dates,
        'days_count' => $days_count,
        'reason' => $reason,
        'location_type' => $location_type,
        'location_specify' => $location_specify,
        'medical_condition' => $medical_condition,
        'illness_specify' => $illness_specify,
        'special_women_condition' => $special_women_condition,
        'study_type' => $study_type,
        'medical_certificate_path' => $medical_certificate_path,
        'days' => $days,
        'credit_message' => $creditCheck['message'],
        'is_late' => true,
        'late_justification' => $_POST['late_justification'] ?? ''
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

    // Insert late leave request with conditional fields, selected_dates, and late justification
    // Check if original_leave_type column exists
    try {
        $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, original_leave_type, start_date, end_date, selected_dates, reason, status, days_requested, location_type, location_specify, medical_condition, illness_specify, special_women_condition, study_type, medical_certificate_path, late_justification, is_late, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$employee_id, $leave_type, $original_leave_type, $start_date, $end_date, $selected_dates, $reason, $days, $location_type, $location_specify, $medical_condition, $illness_specify, $special_women_condition, $study_type, $medical_certificate_path, $late_justification]);
    } catch (PDOException $e) {
        // If original_leave_type or selected_dates column doesn't exist, use fallback query
        if (strpos($e->getMessage(), 'original_leave_type') !== false || strpos($e->getMessage(), 'selected_dates') !== false) {
            try {
                $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, original_leave_type, start_date, end_date, reason, status, days_requested, location_type, location_specify, medical_condition, illness_specify, special_women_condition, study_type, medical_certificate_path, late_justification, is_late, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$employee_id, $leave_type, $original_leave_type, $start_date, $end_date, $reason, $days, $location_type, $location_specify, $medical_condition, $illness_specify, $special_women_condition, $study_type, $medical_certificate_path, $late_justification]);
            } catch (PDOException $e2) {
                // Final fallback without original_leave_type
                $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status, days_requested, location_type, location_specify, medical_condition, illness_specify, special_women_condition, study_type, medical_certificate_path, late_justification, is_late, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$employee_id, $leave_type, $start_date, $end_date, $reason, $days, $location_type, $location_specify, $medical_condition, $illness_specify, $special_women_condition, $study_type, $medical_certificate_path, $late_justification]);
            }
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
            error_log("CTO credits deducted immediately on late submission - Request ID: $leaveRequestId");
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
        error_log("HR notification (new late leave) failed: " . $e->getMessage());
        // Non-blocking
    }
    
    // Track successful submission to prevent duplicates
    $_SESSION['last_submission'] = $submission_key;
    $_SESSION['last_submission_time'] = time();
    
    // Set flags to show success modal only (no redundant banner message)
    $_SESSION['show_success_modal'] = true;
    $_SESSION['success_leave_type'] = $original_leave_type ?: $leave_type;
    $_SESSION['is_late_application'] = true;
    
    // Clear insufficient credits modal session variables to prevent re-display
    unset($_SESSION['show_insufficient_credits_popup']);
    unset($_SESSION['insufficient_credits_data']);
    unset($_SESSION['temp_insufficient_credits_data']);
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error submitting late leave application: " . $e->getMessage();
}

// Clear any output and redirect
ob_end_clean();
header('Location: dashboard.php');
exit();
?>
