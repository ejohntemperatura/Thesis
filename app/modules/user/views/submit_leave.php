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

// Check if user wants to proceed with without pay leave (MUST BE EARLY)
$proceed_without_pay = isset($_POST['proceed_without_pay']) && $_POST['proceed_without_pay'] === 'yes';

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
// Normalize leave type variants to canonical keys (e.g., service/service_credits -> service_credit)
$normalizeLeaveType = function($type) {
    $t = strtolower(trim((string)$type));
    $t = str_replace(['-', ' '], '_', $t);
    $t = preg_replace('/[^a-z0-9_]/', '', $t);
    if ($t === 'service_credits' || $t === 'service' || $t === 'servicecredit' || $t === 'svc_credit' || $t === 'svc' || (strpos($t,'service') !== false && strpos($t,'credit') !== false)) {
        $t = 'service_credit';
    }
    // Singularize simple trailing s if known type missing
    $known = ['vacation','sick','special_privilege','maternity','paternity','solo_parent','vawc','special_women','rehabilitation','study','terminal','cto','service_credit','without_pay','special_emergency','adoption','mandatory','monetization','other'];
    if (!in_array($t,$known,true) && substr($t,-1) === 's') {
        $s = rtrim($t,'s');
        if (in_array($s,$known,true)) { $t = $s; }
    }
    return $t;
};
$leave_type = $normalizeLeaveType($leave_type);
// Ensure DB enum supports new leave types to avoid blank labels
try {
    $colStmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'leave_type'");
    $col = $colStmt->fetch(PDO::FETCH_ASSOC);
    if ($col && isset($col['Type'])) {
        $typeDef = $col['Type']; // e.g., enum('vacation','sick',...)
        $needsUpdate = false;
        $newTypes = [];
        
        // List of leave types that may need to be added to the enum
        $requiredTypes = ['service_credit', 'special_emergency', 'adoption', 'mandatory', 'monetization', 'other'];
        
        foreach ($requiredTypes as $reqType) {
            if (stripos($typeDef, "'" . $reqType . "'") === false) {
                $newTypes[] = $reqType;
                $needsUpdate = true;
            }
        }
        
        if ($needsUpdate && preg_match("/enum\((.*)\)/i", $typeDef, $m)) {
            $vals = $m[1];
            foreach ($newTypes as $newType) {
                $vals .= ",'" . $newType . "'";
            }
            $alterSql = "ALTER TABLE leave_requests MODIFY leave_type enum(" . $vals . ") NOT NULL";
            $pdo->exec($alterSql);
        }
    }
} catch (Exception $e) {
    // Non-fatal; continue
}
// Gender eligibility: VAWC and Special Leave Benefits for Women are female-only
$gstmt = $pdo->prepare("SELECT gender FROM employees WHERE id = ?");
$gstmt->execute([$employee_id]);
$gender = $gstmt->fetchColumn();
if (in_array($leave_type, ['vawc','special_women']) && $gender !== 'female') {
    $_SESSION['error'] = "This leave type is available only to female employees.";
    ob_end_clean();
    header('Location: dashboard.php');
    exit();
}
// Solo Parent eligibility
if ($leave_type === 'solo_parent') {
    $spstmt = $pdo->prepare("SELECT is_solo_parent FROM employees WHERE id = ?");
    $spstmt->execute([$employee_id]);
    $isSolo = (int)$spstmt->fetchColumn();
    if ($isSolo !== 1) {
        $_SESSION['error'] = "Solo Parent Leave is only available to employees flagged as Solo Parent.";
        ob_end_clean();
        header('Location: dashboard.php');
        exit();
    }
}
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
$commutation = $_POST['commutation'] ?? 'not_requested';
$other_purpose = $_POST['other_purpose'] ?? null;
$working_days_applied = isset($_POST['working_days_applied']) ? (int)$_POST['working_days_applied'] : null;

// Handle medical certificate upload for sick leave
$medical_certificate_path = null;

// Determine the actual leave type to check (use original_leave_type if proceeding without pay)
$check_leave_type = $leave_type;
if ($proceed_without_pay && isset($_POST['original_leave_type'])) {
    $check_leave_type = $_POST['original_leave_type'];
}

// NEW APPROACH: Make documents optional for maternity/paternity when without pay
// Only enforce document requirement when NOT proceeding without pay
if (!$proceed_without_pay) {
    // Enforce required upload for maternity/paternity; optional for sick (but processed if provided)
    if (in_array($check_leave_type, ['maternity','paternity'])) {
        if (!isset($_FILES['medical_certificate']) || $_FILES['medical_certificate']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Supporting document is required for " . ucfirst($check_leave_type) . " leave.";
            header('Location: dashboard.php');
            exit();
        }
    }
}

// Process file upload if available (for both first submission and resubmission)
$file = null;
if ($check_leave_type === 'sick' && isset($_FILES['sick_medical_certificate']) && $_FILES['sick_medical_certificate']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['sick_medical_certificate'];
} elseif (in_array($check_leave_type, ['maternity','paternity']) && isset($_FILES['medical_certificate']) && $_FILES['medical_certificate']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['medical_certificate'];
}

if ($file !== null) {
    $upload_dir = '../../../../uploads/medical_certificates/' . date('Y') . '/' . date('m') . '/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_size = $file['size'];
    
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
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        $medical_certificate_path = $file_path;
        // Store in session immediately for without pay resubmission
        $_SESSION['uploaded_medical_cert'] = $file_path;
    } else {
        $_SESSION['error'] = "Failed to upload supporting document.";
        header('Location: dashboard.php');
        exit();
    }
}

// If proceeding without pay, try to get path from POST or session
if ($proceed_without_pay) {
    if (!empty($_POST['medical_certificate_path'])) {
        $medical_certificate_path = $_POST['medical_certificate_path'];
    } elseif (isset($_SESSION['uploaded_medical_cert'])) {
        $medical_certificate_path = $_SESSION['uploaded_medical_cert'];
    }
    // If still no path, that's OK for without pay leave
}

// Handle "other" leave type (Terminal Leave / Monetization)
if ($leave_type === 'other') {
    // Validate other_purpose is provided
    if (empty($other_purpose)) {
        $_SESSION['error'] = "Please select a purpose (Terminal Leave or Monetization).";
        header('Location: dashboard.php');
        exit();
    }
    
    // Validate working_days_applied is provided
    if (empty($working_days_applied) || $working_days_applied < 1) {
        $_SESSION['error'] = "Please enter the number of working days applied for.";
        header('Location: dashboard.php');
        exit();
    }
    
    // For "other" purpose, we don't use calendar dates
    // Set dummy dates (today) since they're not applicable
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
    $selected_dates = '';
    $days = $working_days_applied;
} else {
    // Regular leave types - use calendar selection
    // Get selected dates and days count from form
    $selected_dates = $_POST['selected_dates'] ?? '';
    $days_count = isset($_POST['days_count']) ? (int)$_POST['days_count'] : 0;

    // Debug: Log received values
    error_log("Leave submission - selected_dates: '$selected_dates', days_count: $days_count, start: $start_date, end: $end_date");

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
}

// Check if the leave application is for past dates (late application)
// Skip this check for "other" type (Terminal Leave/Monetization) since they're about leave credits, not calendar dates
if ($leave_type !== 'other') {
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
}

// Check leave credits using the LeaveCreditsManager
// SKIP credit check for "other" type (Terminal Leave/Monetization)
// These are conversions of leave credits to cash, not requests to use credits for time off
if ($leave_type === 'other') {
    // No credit check needed - they're converting existing credits to cash
    $creditCheck = ['sufficient' => true, 'message' => ''];
} else {
    $creditsManager = new LeaveCreditsManager($pdo);
    $creditCheck = $creditsManager->checkLeaveCredits($employee_id, $leave_type, $start_date, $end_date);
}

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
    // Debug: Log the medical certificate path being stored
    error_log("Storing session data - medical_certificate_path: " . ($medical_certificate_path ?? 'NULL'));
    error_log("Storing session data - leave_type: $leave_type");
    
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
        'credit_message' => $creditCheck['message']
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
        'credit_message' => $creditCheck['message']
    ];
    
    $_SESSION['show_insufficient_credits_popup'] = true;
    header('Location: dashboard.php');
    exit();
}

// If proceeding without pay, change leave type to without_pay and store original type
$original_leave_type = null;
if ($proceed_without_pay) {
    // Use the normalized original type for correct downstream labels
    $orig = isset($_POST['original_leave_type']) ? $_POST['original_leave_type'] : $leave_type;
    $original_leave_type = $normalizeLeaveType($orig);
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

    // Insert leave request with conditional fields including selected_dates, commutation, other_purpose, and working_days_applied
    try {
        $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, original_leave_type, other_purpose, working_days_applied, start_date, end_date, selected_dates, reason, status, days_requested, location_type, location_specify, medical_condition, illness_specify, special_women_condition, study_type, commutation, medical_certificate_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$employee_id, $leave_type, $original_leave_type, $other_purpose, $working_days_applied, $start_date, $end_date, $selected_dates, $reason, $days, $location_type, $location_specify, $medical_condition, $illness_specify, $special_women_condition, $study_type, $commutation, $medical_certificate_path]);
    } catch (PDOException $e) {
        // Fallback for older database schemas
        if (strpos($e->getMessage(), 'other_purpose') !== false || strpos($e->getMessage(), 'working_days_applied') !== false) {
            try {
                $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, original_leave_type, start_date, end_date, selected_dates, reason, status, days_requested, location_type, location_specify, medical_condition, illness_specify, special_women_condition, study_type, commutation, medical_certificate_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$employee_id, $leave_type, $original_leave_type, $start_date, $end_date, $selected_dates, $reason, $days, $location_type, $location_specify, $medical_condition, $illness_specify, $special_women_condition, $study_type, $commutation, $medical_certificate_path]);
            } catch (PDOException $e2) {
                // If commutation column still doesn't exist, use the old query without it
                $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, selected_dates, reason, status, days_requested, location_type, location_specify, medical_condition, illness_specify, special_women_condition, study_type, medical_certificate_path, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$employee_id, $leave_type, $start_date, $end_date, $selected_dates, $reason, $days, $location_type, $location_specify, $medical_condition, $illness_specify, $special_women_condition, $study_type, $medical_certificate_path]);
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
    unset($_SESSION['uploaded_medical_cert']);
    
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