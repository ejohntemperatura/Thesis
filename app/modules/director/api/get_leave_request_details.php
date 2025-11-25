<?php
/**
 * API endpoint to get leave request details for Director modal
 */
session_start();
header('Content-Type: application/json');
require_once '../../../../config/database.php';
require_once '../../../../config/leave_types.php';

// Check if user is logged in and is a director or admin (match page access)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['director','admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get request ID
$request_id = $_GET['id'] ?? '';

if (empty($request_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit();
}

try {
    // Get leave request details with employee and approver information
    $stmt = $pdo->prepare("
        SELECT 
            lr.*,
            e.name as employee_name,
            e.position,
            e.department,
            e.email as employee_email,
            e.service_credit_balance AS sc_balance
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        WHERE lr.id = ?
    ");
    $stmt->execute([$request_id]);
    $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$leaveRequest) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Leave request not found']);
        exit();
    }
    
    // Use days_requested from database (already excludes weekends)
    // If not set, calculate excluding weekends
    if (!isset($leaveRequest['days_requested']) || $leaveRequest['days_requested'] == 0) {
        $start_date = new DateTime($leaveRequest['start_date']);
        $end_date = new DateTime($leaveRequest['end_date']);
        $days_requested = 0;
        $current = clone $start_date;
        while ($current <= $end_date) {
            $dayOfWeek = (int)$current->format('N');
            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                $days_requested++;
            }
            $current->modify('+1 day');
        }
        $leaveRequest['days_requested'] = $days_requested;
    }
    
    // Format dates
    $leaveRequest['start_date'] = date('M d, Y', strtotime($leaveRequest['start_date']));
    $leaveRequest['end_date'] = date('M d, Y', strtotime($leaveRequest['end_date']));
    
    // Get leave types configuration
    $leaveTypes = getLeaveTypes();
    
    // Store raw leave type for conditional field matching (use original_leave_type if available, otherwise use leave_type)
    $leaveRequest['leave_type_raw'] = $leaveRequest['original_leave_type'] ?? $leaveRequest['leave_type'];
    
    // Format leave type display using helper function
    $mapped = getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null, $leaveTypes);
    $display = trim((string)$mapped);
    if ($display === '') {
        $base = $leaveRequest['leave_type_raw'] ?? '';
        $display = trim((string)getLeaveTypeDisplayName($base, null, $leaveTypes));
        if ($display === '') {
            if (!empty($leaveRequest['study_type'])) {
                $display = 'Study Leave (Without Pay)';
            } elseif (!empty($leaveRequest['medical_condition']) || !empty($leaveRequest['illness_specify'])) {
                $display = 'Sick Leave (SL)';
            } elseif (!empty($leaveRequest['special_women_condition'])) {
                $display = 'Special Leave Benefits for Women';
            } elseif (!empty($leaveRequest['location_type'])) {
                $display = 'Vacation Leave (VL)';
            } elseif (isset($leaveRequest['sc_balance']) && (float)$leaveRequest['sc_balance'] > 0) {
                $display = 'Service Credits';
            } elseif (($leaveRequest['pay_status'] ?? '') === 'without_pay' || ($leaveRequest['leave_type_raw'] ?? '') === 'without_pay') {
                $display = 'Without Pay Leave';
            } else {
                $display = 'Service Credits';
            }
        }
    }
    $leaveRequest['leave_type_display'] = $display;
    // Override leave_type so consumers reading this field see the display label
    $leaveRequest['leave_type'] = $display;
    
    // Format location type for display
    if (!empty($leaveRequest['location_type'])) {
        switch ($leaveRequest['location_type']) {
            case 'within_philippines':
                $leaveRequest['location_type'] = 'Within Philippines';
                break;
            case 'outside_philippines':
                $leaveRequest['location_type'] = 'Outside Philippines';
                break;
            default:
                $leaveRequest['location_type'] = ucfirst(str_replace('_', ' ', $leaveRequest['location_type']));
                break;
        }
    }
    
    // Format medical condition for display
    if (!empty($leaveRequest['medical_condition'])) {
        switch ($leaveRequest['medical_condition']) {
            case 'minor':
                $leaveRequest['medical_condition'] = 'Minor';
                break;
            case 'serious':
                $leaveRequest['medical_condition'] = 'Serious';
                break;
            case 'chronic':
                $leaveRequest['medical_condition'] = 'Chronic';
                break;
            default:
                $leaveRequest['medical_condition'] = ucfirst(str_replace('_', ' ', $leaveRequest['medical_condition']));
                break;
        }
    }
    
    // Format special women condition for display
    if (!empty($leaveRequest['special_women_condition'])) {
        switch ($leaveRequest['special_women_condition']) {
            case 'pregnancy':
                $leaveRequest['special_women_condition'] = 'Pregnancy';
                break;
            case 'menstruation':
                $leaveRequest['special_women_condition'] = 'Menstruation';
                break;
            case 'miscarriage':
                $leaveRequest['special_women_condition'] = 'Miscarriage';
                break;
            case 'other':
                $leaveRequest['special_women_condition'] = 'Other';
                break;
            default:
                $leaveRequest['special_women_condition'] = ucfirst(str_replace('_', ' ', $leaveRequest['special_women_condition']));
                break;
        }
    }
    
    // Format study type for display
    if (!empty($leaveRequest['study_type'])) {
        switch ($leaveRequest['study_type']) {
            case 'conference':
                $leaveRequest['study_type'] = 'Conference';
                break;
            case 'training':
                $leaveRequest['study_type'] = 'Training';
                break;
            case 'seminar':
                $leaveRequest['study_type'] = 'Seminar';
                break;
            case 'course':
                $leaveRequest['study_type'] = 'Course';
                break;
            case 'exam':
                $leaveRequest['study_type'] = 'Exam';
                break;
            default:
                $leaveRequest['study_type'] = ucfirst(str_replace('_', ' ', $leaveRequest['study_type']));
                break;
        }
    }
    
    // Format approval timestamps
    if (!empty($leaveRequest['dept_head_approved_at'])) {
        $leaveRequest['dept_head_approved_at'] = date('M d, Y \a\t g:i A', strtotime($leaveRequest['dept_head_approved_at']));
    }
    
    echo json_encode([
        'success' => true,
        'leave' => $leaveRequest
    ]);
    
} catch (Exception $e) {
    error_log('Error fetching leave request details: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>

