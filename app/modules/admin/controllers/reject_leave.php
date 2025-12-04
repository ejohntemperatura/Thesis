<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/EmailService.php';

// Ensure user is HR/Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

$request_id = $_GET['id'] ?? '';
$reason = $_POST['reason'] ?? 'No reason provided';

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

    // Update HR/Admin approval to rejected and final status rejected
    $stmt = $pdo->prepare("UPDATE leave_requests SET admin_approval = 'rejected', admin_approved_by = ?, admin_approved_at = NOW(), admin_approval_notes = ?, status = 'rejected', rejected_by = ?, rejected_at = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $reason, $_SESSION['user_id'], $request_id]);

    // Fetch employee details for email notification
    $empStmt = $pdo->prepare("SELECT e.name AS employee_name, e.email AS employee_email FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.id = ?");
    $empStmt->execute([$request_id]);
    $emp = $empStmt->fetch(PDO::FETCH_ASSOC);

    // Fetch rejector (HR) name
    $rejectorStmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
    $rejectorStmt->execute([$_SESSION['user_id']]);
    $rejectorName = $rejectorStmt->fetchColumn();

    $pdo->commit();

    // Send email notification to employee about rejection
    if ($emp && filter_var($emp['employee_email'], FILTER_VALIDATE_EMAIL)) {
        try {
            $emailService = new EmailService();
            $emailService->sendLeaveStatusNotification(
                $emp['employee_email'],
                $emp['employee_name'],
                'rejected',
                date('M d, Y', strtotime($request['start_date'])),
                date('M d, Y', strtotime($request['end_date'])),
                $request['leave_type'] ?? null,
                $rejectorName ?: 'HR',
                'admin',
                null,
                $request['original_leave_type'] ?? null,
                $reason,
                $request['selected_dates'] ?? null,
                $request['working_days_applied'] ?? null,
                $request['other_purpose'] ?? null
            );
        } catch (Exception $ex) {
            // Log but do not block flow
            error_log('HR rejection email failed: ' . $ex->getMessage());
        }
    }

    $_SESSION['success'] = 'Leave request rejected by HR.';
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'Error processing HR rejection: ' . $e->getMessage();
}

header('Location: ../views/leave_management.php');
exit();
