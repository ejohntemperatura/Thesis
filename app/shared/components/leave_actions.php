<?php
// Shared Leave Actions Component
// This file handles approve/reject leave functionality for all roles
// Used by: admin, director, department heads

session_start();
require_once '../../../../config/database.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'director'])) {
    header('Location: ../auth/index.php');
    exit();
}

$action = $_GET['action'] ?? '';
$request_id = $_GET['request_id'] ?? null;

if (!$request_id) {
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '../index.php');
    exit();
}

// Get leave request details
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name, e.email as employee_email 
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    WHERE lr.id = ?
");
$stmt->execute([$request_id]);
$leave_request = $stmt->fetch();

if (!$leave_request) {
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '../index.php');
    exit();
}

// Handle actions
$role = $_SESSION['role'];
$message = '';

if ($action === 'approve') {
    if ($role === 'manager') {
        // Department Head approval
        $stmt = $pdo->prepare("UPDATE leave_requests SET dept_head_approval = 'approved', dept_head_approved_by = ?, dept_head_approved_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $request_id]);
        $message = 'Department Head approval recorded.';
    } elseif ($role === 'admin') {
        // HR/Admin approval requires Dept Head approval first
        if (($leave_request['dept_head_approval'] ?? 'pending') !== 'approved') {
            $message = 'Department Head must approve first before HR.';
        } else {
            $stmt = $pdo->prepare("UPDATE leave_requests SET admin_approval = 'approved', admin_approved_by = ?, admin_approved_at = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $request_id]);
            $message = 'HR approval recorded.';
        }
    } elseif ($role === 'director') {
        // Director approval requires Dept Head and HR approvals
        if (($leave_request['dept_head_approval'] ?? 'pending') !== 'approved') {
            $message = 'Department Head must approve first before Director.';
        } elseif (($leave_request['admin_approval'] ?? 'pending') !== 'approved') {
            $message = 'HR must approve before Director.';
        } else {
            $stmt = $pdo->prepare("UPDATE leave_requests SET director_approval = 'approved', director_approved_by = ?, director_approved_at = NOW(), status = 'approved' WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $request_id]);
            $message = 'Leave request approved by Director.';
        }
    }
    
    // Send notification email
    require_once '../../../includes/EmailService.php';
    $emailService = new EmailService();
    $emailService->sendLeaveStatusNotification(
        $leave_request['employee_email'],
        $leave_request['employee_name'],
        ($role === 'director' ? 'approved' : 'pending'),
        $leave_request['start_date'],
        $leave_request['end_date'],
        $leave_request['leave_type'],
        null,
        null,
        null,
        $leave_request['original_leave_type'] ?? null,
        null,
        $leave_request['selected_dates'] ?? null,
        $leave_request['working_days_applied'] ?? null,
        $leave_request['other_purpose'] ?? null
    );
    
} elseif ($action === 'reject') {
    if ($role === 'manager') {
        $stmt = $pdo->prepare("UPDATE leave_requests SET dept_head_approval = 'rejected', dept_head_approved_by = ?, dept_head_approved_at = NOW(), status = 'rejected' WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $request_id]);
        $message = 'Leave request rejected by Department Head.';
    } elseif ($role === 'admin') {
        // HR can reject after Dept Head approval
        if (($leave_request['dept_head_approval'] ?? 'pending') !== 'approved') {
            $message = 'Department Head must approve first before HR can reject.';
        } else {
            $stmt = $pdo->prepare("UPDATE leave_requests SET admin_approval = 'rejected', admin_approved_by = ?, admin_approved_at = NOW(), status = 'rejected' WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $request_id]);
            $message = 'Leave request rejected by HR.';
        }
    } elseif ($role === 'director') {
        // Director can reject only after Dept Head and HR approvals
        if (($leave_request['dept_head_approval'] ?? 'pending') !== 'approved') {
            $message = 'Department Head must act first before Director can reject.';
        } elseif (($leave_request['admin_approval'] ?? 'pending') !== 'approved') {
            $message = 'HR must approve before Director can reject.';
        } else {
            $stmt = $pdo->prepare("UPDATE leave_requests SET director_approval = 'rejected', director_approved_by = ?, director_approved_at = NOW(), status = 'rejected' WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $request_id]);
            $message = 'Leave request rejected by Director.';
        }
    }
    
    // Send notification email
    require_once '../../../includes/EmailService.php';
    $emailService = new EmailService();
    $emailService->sendLeaveStatusNotification(
        $leave_request['employee_email'],
        $leave_request['employee_name'],
        'rejected',
        $leave_request['start_date'],
        $leave_request['end_date'],
        $leave_request['leave_type'],
        null,
        null,
        null,
        $leave_request['original_leave_type'] ?? null,
        null,
        $leave_request['selected_dates'] ?? null,
        $leave_request['working_days_applied'] ?? null,
        $leave_request['other_purpose'] ?? null
    );
}

// Redirect back with message
$redirect_url = $_SERVER['HTTP_REFERER'] ?? '../index.php';
$redirect_url .= (strpos($redirect_url, '?') !== false ? '&' : '?') . 'message=' . urlencode($message);
header('Location: ' . $redirect_url);
exit();
?>
