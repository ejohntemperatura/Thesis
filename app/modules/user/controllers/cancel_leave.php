<?php
session_start();
require_once '../../../../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if leave_id is provided
if (!isset($_POST['leave_id']) || empty($_POST['leave_id'])) {
    echo json_encode(['success' => false, 'message' => 'Leave request ID is required']);
    exit();
}

$leave_id = (int)$_POST['leave_id'];
$employee_id = $_SESSION['user_id'];

try {
    // Verify the leave request belongs to the current user and is still pending
    $stmt = $pdo->prepare("
        SELECT lr.*, e.name as employee_name 
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        WHERE lr.id = ? AND lr.employee_id = ?
    ");
    $stmt->execute([$leave_id, $employee_id]);
    $leave_request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$leave_request) {
        echo json_encode(['success' => false, 'message' => 'Leave request not found or you do not have permission to cancel it']);
        exit();
    }
    
    // Check if the leave request is still pending
    if ($leave_request['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending leave requests can be cancelled. Current status: ' . ucfirst($leave_request['status'])]);
        exit();
    }
    
    // Check if any approval has already been given
    $dept_head_approved = ($leave_request['dept_head_approval'] ?? 'pending') === 'approved';
    $admin_approved = ($leave_request['admin_approval'] ?? 'pending') === 'approved';
    $director_approved = ($leave_request['director_approval'] ?? 'pending') === 'approved';
    
    if ($dept_head_approved || $admin_approved || $director_approved) {
        echo json_encode(['success' => false, 'message' => 'Cannot cancel this leave request as it has already been partially approved. Please contact HR for assistance.']);
        exit();
    }
    
    // Update the leave request status to cancelled
    $stmt = $pdo->prepare("
        UPDATE leave_requests 
        SET status = 'cancelled', 
            rejected_at = NOW(),
            rejection_reason = 'Cancelled by employee'
        WHERE id = ? AND employee_id = ?
    ");
    $stmt->execute([$leave_id, $employee_id]);
    
    if ($stmt->rowCount() > 0) {
        // If it was a CTO leave, refund the CTO hours
        if ($leave_request['leave_type'] === 'cto') {
            // Calculate hours to refund
            $start = new DateTime($leave_request['start_date']);
            $end = new DateTime($leave_request['end_date']);
            $days = 0;
            $current = clone $start;
            while ($current <= $end) {
                $dayOfWeek = (int)$current->format('N');
                if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                    $days++;
                }
                $current->modify('+1 day');
            }
            $hours_to_refund = $days * 8;
            
            // Refund CTO hours
            $stmt = $pdo->prepare("UPDATE employees SET cto_balance = cto_balance + ? WHERE id = ?");
            $stmt->execute([$hours_to_refund, $employee_id]);
            
            error_log("CTO hours refunded: $hours_to_refund hours for employee $employee_id (leave request $leave_id cancelled)");
        }
        
        echo json_encode(['success' => true, 'message' => 'Leave request cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel leave request']);
    }
    
} catch (Exception $e) {
    error_log("Error cancelling leave request: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
