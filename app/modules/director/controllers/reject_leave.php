<?php
session_start();
require_once '../../../../config/database.php';

// Check if user is logged in and is a director
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

// Get request ID
$request_id = $_GET['id'] ?? '';

if (empty($request_id)) {
    $_SESSION['error'] = 'Invalid request ID';
    header('Location: ../views/dashboard.php');
    exit();
}

try {
    // Get leave request details for email notification
    $stmt = $pdo->prepare("SELECT lr.*, e.id as emp_id, e.name, e.email FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('Leave request not found');
    }
    
    // Get rejection reason
    $reason = $_POST['reason'] ?? 'No reason provided';
    
    // Director can reject regardless of previous approvals
    // Update director approval status to rejected (don't set final status yet)
    $stmt = $pdo->prepare("UPDATE leave_requests SET director_approval = 'rejected', director_approved_by = ?, director_approved_at = NOW(), director_rejection_reason = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $reason, $request_id]);
    
    // Check if all three levels rejected - then mark as finally rejected
    $dept_status = $request['dept_head_approval'] ?? 'pending';
    $admin_status = $request['admin_approval'] ?? 'pending';
    
    if ($dept_status === 'rejected' && $admin_status === 'rejected') {
        // All three rejected - mark as finally rejected
        $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$request_id]);
    }
    
    $_SESSION['success'] = 'Leave request rejected successfully!';
    
    // Send email notification for rejection
    try {
        require_once '../../../../app/core/services/EmailService.php';
        $emailService = new EmailService();
        
        // Get director name for email
        $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $director = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $emailService->sendLeaveStatusNotification(
            $request['email'],
            $request['name'],
            'rejected',
            $request['start_date'],
            $request['end_date'],
            $request['leave_type'],
            $director['name'] ?? 'Director',
            'director',
            null,
            $request['original_leave_type'] ?? null,
            $reason,
            $request['selected_dates'] ?? null,
            $request['working_days_applied'] ?? null,
            $request['other_purpose'] ?? null
        );
    } catch (Exception $e) {
        error_log("Email notification failed: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error rejecting leave request: ' . $e->getMessage();
}

header('Location: ../views/dashboard.php');
exit();
?>