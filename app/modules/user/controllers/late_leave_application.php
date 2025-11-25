<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/LeaveCreditsManager.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
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
$late_justification = $_POST['late_justification'];

// Gender eligibility: VAWC and Special Leave Benefits for Women are female-only
$gstmt = $pdo->prepare("SELECT gender FROM employees WHERE id = ?");
$gstmt->execute([$employee_id]);
$gender = $gstmt->fetchColumn();
if (in_array($leave_type, ['vawc','special_women']) && $gender !== 'female') {
    $_SESSION['error'] = "This leave type is available only to female employees.";
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
        header('Location: dashboard.php');
        exit();
    }
}

// Get conditional fields based on leave type
$location_type = $_POST['location_type'] ?? null;
$location_specify = $_POST['location_specify'] ?? null;
$medical_condition = $_POST['medical_condition'] ?? null;
$illness_specify = $_POST['illness_specify'] ?? null;
$special_women_condition = $_POST['special_women_condition'] ?? null;
$study_type = $_POST['study_type'] ?? null;

// Handle supporting document upload
$medical_certificate_path = null;
// Require for maternity/paternity
if (in_array($leave_type, ['maternity','paternity'])) {
    if (!isset($_FILES['medical_certificate']) || $_FILES['medical_certificate']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Supporting document is required for " . ucfirst($leave_type) . " leave.";
        header('Location: dashboard.php');
        exit();
    }
}

// Select correct file input
$file = null;
if ($leave_type === 'sick' && isset($_FILES['sick_medical_certificate']) && $_FILES['sick_medical_certificate']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['sick_medical_certificate'];
} elseif (in_array($leave_type, ['maternity','paternity']) && isset($_FILES['medical_certificate']) && $_FILES['medical_certificate']['error'] === UPLOAD_ERR_OK) {
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
    } else {
        $_SESSION['error'] = "Failed to upload supporting document.";
        header('Location: dashboard.php');
        exit();
    }
}

// Calculate number of days (inclusive)
$start = new DateTime($start_date);
$end = new DateTime($end_date);
if ($end < $start) {
    $_SESSION['error'] = "End date cannot be before start date.";
    header('Location: dashboard.php');
    exit();
}
$interval = $start->diff($end);
$days = $interval->days + 1; // Include both start and end dates

// Check leave credits using the LeaveCreditsManager
$creditsManager = new LeaveCreditsManager($pdo);
$creditCheck = $creditsManager->checkLeaveCredits($employee_id, $leave_type, $start_date, $end_date);

if (!$creditCheck['has_sufficient_credits']) {
    $_SESSION['error'] = $creditCheck['message'];
    header('Location: dashboard.php');
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Insert late leave request with conditional fields (mark as late application)
    $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status, days_requested, is_late, late_justification, location_type, location_specify, medical_condition, illness_specify, special_women_condition, study_type, medical_certificate_path, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$employee_id, $leave_type, $start_date, $end_date, $reason, $days, $late_justification, $location_type, $location_specify, $medical_condition, $illness_specify, $special_women_condition, $study_type, $medical_certificate_path]);

    // Deduct leave credits immediately when applying
    $creditsManager->deductLeaveCredits($employee_id, $leave_type, $start_date, $end_date);

    $pdo->commit();
    $_SESSION['success'] = "Late leave application submitted successfully. Leave credits have been deducted.";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error submitting late leave application: " . $e->getMessage();
}

header('Location: dashboard.php');
exit();
?>
