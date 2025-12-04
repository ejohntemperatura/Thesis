<?php
session_start();
require_once '../../../../config/database.php';

// Check if user is logged in and is a director
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

// Get request ID and approval parameters
$request_id = $_GET['id'] ?? '';
$approved_days = $_GET['days'] ?? '';
$pay_status = $_GET['pay_status'] ?? 'with_pay';

if (empty($request_id)) {
    $_SESSION['error'] = 'Invalid request ID';
    header('Location: ../views/dashboard.php');
    exit();
}

if (empty($approved_days) || !is_numeric($approved_days) || $approved_days < 1) {
    $_SESSION['error'] = 'Invalid number of days specified';
    header('Location: ../views/dashboard.php');
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Get leave request details
    $stmt = $pdo->prepare("SELECT lr.*, e.id as emp_id, e.name, e.email FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('Leave request not found');
    }
    
    // Check if department head has already approved
    if (($request['dept_head_approval'] ?? 'pending') !== 'approved') {
        throw new Exception('Department Head must approve first before Director can approve.');
    }

    // Require HR/Admin approval after Department Head approval
    if (($request['admin_approval'] ?? 'pending') !== 'approved') {
        throw new Exception('HR must review and approve before Director can approve.');
    }
    
    // Update director approval status and final status with pay status
    // Set approved_days and specific pay status fields
    if ($pay_status === 'with_pay') {
        $stmt = $pdo->prepare("UPDATE leave_requests SET director_approval = 'approved', director_approved_by = ?, director_approved_at = NOW(), status = 'approved', approved_days = ?, approved_days_with_pay = ?, approved_days_without_pay = 0, pay_status = ? WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $approved_days, $approved_days, $pay_status, $request_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE leave_requests SET director_approval = 'approved', director_approved_by = ?, director_approved_at = NOW(), status = 'approved', approved_days = ?, approved_days_with_pay = 0, approved_days_without_pay = ?, pay_status = ? WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $approved_days, $approved_days, $pay_status, $request_id]);
    }
    
    // Deduct leave credits based on approved days (only for with pay)
    if ($pay_status === 'with_pay') {
        $leave_type = strtolower(trim($request['leave_type']));
        
        // Special handling for "other" type (Terminal Leave / Monetization)
        if ($leave_type === 'other') {
            $other_purpose = strtolower(trim($request['other_purpose'] ?? ''));
            
            if ($other_purpose === 'monetization') {
                // Monetization: Deduct from Vacation Leave only
                $stmt = $pdo->prepare("UPDATE employees SET vacation_leave_balance = vacation_leave_balance - ? WHERE id = ?");
                $stmt->execute([$approved_days, $request['emp_id']]);
            } elseif ($other_purpose === 'terminal') {
                // Terminal Leave: Deduct from BOTH Vacation Leave and Sick Leave
                // Split the days between VL and SL (or deduct from both proportionally)
                // For simplicity, we'll deduct half from each, or all from VL first then SL
                
                // Get current balances
                $stmt = $pdo->prepare("SELECT vacation_leave_balance, sick_leave_balance FROM employees WHERE id = ?");
                $stmt->execute([$request['emp_id']]);
                $balances = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $vl_balance = floatval($balances['vacation_leave_balance']);
                $sl_balance = floatval($balances['sick_leave_balance']);
                $days_to_deduct = floatval($approved_days);
                
                // Deduct from VL first, then SL if needed
                if ($vl_balance >= $days_to_deduct) {
                    // All days can be deducted from VL
                    $stmt = $pdo->prepare("UPDATE employees SET vacation_leave_balance = vacation_leave_balance - ? WHERE id = ?");
                    $stmt->execute([$days_to_deduct, $request['emp_id']]);
                } else {
                    // Deduct all VL, then remaining from SL
                    $remaining = $days_to_deduct - $vl_balance;
                    $stmt = $pdo->prepare("UPDATE employees SET vacation_leave_balance = 0, sick_leave_balance = sick_leave_balance - ? WHERE id = ?");
                    $stmt->execute([$remaining, $request['emp_id']]);
                }
            }
        } else {
            // Regular leave types
            // Map leave types to correct balance fields
            $leave_type_mapping = [
                'annual' => 'vacation_leave_balance',
                'sick' => 'sick_leave_balance',
                'vacation' => 'vacation_leave_balance',
                'special_privilege' => 'special_privilege_leave_balance',
                'solo_parent' => 'solo_parent_leave_balance',
                'vawc' => 'vawc_leave_balance',
                'rehabilitation' => 'rehabilitation_leave_balance',
                'terminal' => 'terminal_leave_balance',
                'maternity' => 'maternity_leave_balance',
                'paternity' => 'paternity_leave_balance',
                'study' => 'study_leave_balance',
                'without_pay' => null  // without_pay doesn't have a balance field
            ];
            
            $balance_field = $leave_type_mapping[$leave_type] ?? null;
            
            // Only deduct if we have a valid balance field and it's not without_pay
            if ($balance_field && $leave_type !== 'without_pay') {
                // Deduct the approved days from leave balance (not the requested days)
                $stmt = $pdo->prepare("UPDATE employees SET $balance_field = $balance_field - ? WHERE id = ?");
                $stmt->execute([$approved_days, $request['emp_id']]);
            }
        }
    }
    
    $pdo->commit();
    $pay_text = $pay_status === 'with_pay' ? 'with pay' : 'without pay';
    $_SESSION['success'] = "Leave request approved by Director! Final approval completed - {$approved_days} day(s) {$pay_text}.";
    
    // Send email notification for approval
    try {
        require_once '../../../../app/core/services/EmailService.php';
        $emailService = new EmailService();
        
        // Get director name for email
        $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $director = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Create custom status message for email
        $custom_status = $pay_status === 'with_pay' ? 'approved_with_pay' : 'approved_without_pay';
        
        $emailService->sendLeaveStatusNotification(
            $request['email'],
            $request['name'],
            $custom_status,
            $request['start_date'],
            $request['end_date'],
            $request['leave_type'],
            $director['name'] ?? 'Director',
            'director',
            $approved_days,
            $request['original_leave_type'] ?? null,
            null,
            $request['selected_dates'] ?? null,
            $request['working_days_applied'] ?? null,
            $request['other_purpose'] ?? null
        );
    } catch (Exception $e) {
        error_log("Email notification failed: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'Error approving leave request: ' . $e->getMessage();
}

header('Location: ../views/dashboard.php');
exit();
?>