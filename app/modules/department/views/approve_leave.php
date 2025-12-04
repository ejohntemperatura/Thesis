<?php
session_start();
require_once '../../../../config/database.php';

// Check if user is logged in and is a department head
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

// Get department head's department for verification
$stmt = $pdo->prepare("SELECT department FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dept_head = $stmt->fetch(PDO::FETCH_ASSOC);
$dept_head_department = $dept_head['department'] ?? null;

// Get request ID from GET or POST
$request_id = $_GET['id'] ?? $_POST['request_id'] ?? '';
$action = $_POST['action'] ?? 'approve';

if (empty($request_id)) {
    $_SESSION['error'] = 'Invalid request ID. Please try again.';
    header('Location: dashboard.php');
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Get leave request details with department verification
    $stmt = $pdo->prepare("
        SELECT lr.*, e.id as emp_id, e.name, e.email, e.department 
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        WHERE lr.id = ? AND e.department = ?
    ");
    $stmt->execute([$request_id, $dept_head_department]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('Leave request not found or you do not have permission to approve requests from this department');
    }
    
    // Handle approval or rejection based on action
    if ($action === 'approve') {
        // Update department head approval status
        $stmt = $pdo->prepare("UPDATE leave_requests SET dept_head_approval = 'approved', dept_head_approved_by = ?, dept_head_approved_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $request_id]);
    
        // Deduct leave credits
        $start = new DateTime($request['start_date']);
        $end = new DateTime($request['end_date']);
        $days = $start->diff($end)->days + 1;
        $leave_type = strtolower(trim($request['leave_type']));
        $balance_field = $leave_type . '_leave_balance';
        
        // Don't deduct leave credits yet - wait for final approval from director
        $_SESSION['success'] = 'Leave request approved by Department Head! Now waiting for HR approval.';
        
        // Send email notification for department head approval
        try {
            require_once '../../../../app/core/services/EmailService.php';
            $emailService = new EmailService();
            
            // Get department head name for email
            $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $dept_head = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $emailService->sendLeaveStatusNotification(
                $request['email'],
                $request['name'],
                'dept_approved',
                $request['start_date'],
                $request['end_date'],
                $request['leave_type'],
                $dept_head['name'] ?? 'Department Head',
                'manager',
                null,
                $request['original_leave_type'] ?? null,
                null,
                $request['selected_dates'] ?? null,
                $request['working_days_applied'] ?? null,
                $request['other_purpose'] ?? null
            );
        } catch (Exception $e) {
            error_log("Email notification failed: " . $e->getMessage());
        }
        
        // Notify HR that Department Head approved (HR is next to review)
        try {
            require_once '../../../../app/core/services/NotificationHelper.php';
            $notificationHelper = new NotificationHelper($pdo);
            $notificationHelper->notifyHRDeptApproved($request_id);
        } catch (Exception $e) {
            error_log("HR notification after dept approval failed: " . $e->getMessage());
        }
        
        // Send notification to director
        try {
            require_once '../../../../app/core/services/NotificationHelper.php';
            $notificationHelper = new NotificationHelper($pdo);
            $notificationHelper->notifyDirectorDepartmentAction($request_id, 'approved');
        } catch (Exception $e) {
            error_log("Director notification failed: " . $e->getMessage());
            // Don't fail the approval if notification fails
        }
        
    } else {
        // Handle rejection
        $reason = $_POST['reason'] ?? 'No reason provided';
        
        // Update department head approval status to rejected
        $stmt = $pdo->prepare("UPDATE leave_requests SET dept_head_approval = 'rejected', dept_head_approved_by = ?, dept_head_approved_at = NOW(), dept_head_rejection_reason = ?, status = 'rejected' WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $reason, $request_id]);
        
        $_SESSION['success'] = 'Leave request rejected successfully!';
        
        // Send email notification for rejection
        try {
            require_once '../../../../app/core/services/EmailService.php';
            $emailService = new EmailService();
            
            // Get department head name for email
            $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $dept_head = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $emailService->sendLeaveStatusNotification(
                $request['email'],
                $request['name'],
                'rejected',
                $request['start_date'],
                $request['end_date'],
                $request['leave_type'],
                $dept_head['name'] ?? 'Department Head',
                'manager',
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
    }
    
    $pdo->commit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'Error processing leave request: ' . $e->getMessage();
}

header('Location: dashboard.php');
exit();
?>
