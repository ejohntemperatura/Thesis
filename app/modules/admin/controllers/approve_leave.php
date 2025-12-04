<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/EmailService.php';
require_once '../../../../app/core/services/NotificationHelper.php';

// Ensure user is HR/Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

$request_id = $_GET['id'] ?? '';
if (empty($request_id)) {
    $_SESSION['error'] = 'Invalid request ID';
    header('Location: ../views/leave_management.php');
    exit();
}

try {
    $pdo->beginTransaction();

    // Fetch leave request
    $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception('Leave request not found');
    }

    // Verify sequence: Department Head must approve first
    if (($request['dept_head_approval'] ?? 'pending') !== 'approved') {
        throw new Exception('Department Head must approve first before HR can review.');
    }

    // If already rejected at any stage, stop
    if (in_array(($request['dept_head_approval'] ?? 'pending'), ['rejected']) || in_array(($request['director_approval'] ?? 'pending'), ['rejected'])) {
        throw new Exception('This request has already been rejected.');
    }

    // Update HR/Admin approval
    $stmt = $pdo->prepare("UPDATE leave_requests SET admin_approval = 'approved', admin_approved_by = ?, admin_approved_at = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $request_id]);

    // Fetch employee details for email notification
    $empStmt = $pdo->prepare("SELECT e.name AS employee_name, e.email AS employee_email FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.id = ?");
    $empStmt->execute([$request_id]);
    $emp = $empStmt->fetch(PDO::FETCH_ASSOC);

    // Fetch approver (HR) name
    $approverStmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
    $approverStmt->execute([$_SESSION['user_id']]);
    $approverName = $approverStmt->fetchColumn();

    $pdo->commit();

    // Send notification to employee that HR approved (sequence step)
    if ($emp && filter_var($emp['employee_email'], FILTER_VALIDATE_EMAIL)) {
        try {
            $emailService = new EmailService();
            $emailService->sendLeaveStatusNotification(
                $emp['employee_email'],
                $emp['employee_name'],
                'hr_approved',
                date('M d, Y', strtotime($request['start_date'])),
                date('M d, Y', strtotime($request['end_date'])),
                $request['leave_type'] ?? null,
                $approverName ?: 'HR',
                'admin',
                $request['approved_days'] ?? null,
                $request['original_leave_type'] ?? null,
                null,
                $request['selected_dates'] ?? null,
                $request['working_days_applied'] ?? null,
                $request['other_purpose'] ?? null
            );
        } catch (Exception $ex) {
            // Log but do not block flow
            error_log('HR approval email failed: ' . $ex->getMessage());
        }
    }

    // Notify Director now that HR has approved (final approver)
    try {
        $notifier = new NotificationHelper($pdo);
        // Reuse method; it now gates on HR approval and will send to Director
        $notifier->notifyDirectorDepartmentAction($request_id, 'approved');
    } catch (Exception $ex) {
        error_log('Director notify after HR approval failed: ' . $ex->getMessage());
    }

    $_SESSION['success'] = 'Leave request approved by HR.';
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'Error processing HR approval: ' . $e->getMessage();
}

header('Location: ../views/leave_management.php');
exit();
