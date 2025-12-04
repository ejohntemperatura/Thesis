<?php
/**
 * API endpoint to get leave request details for Department Head modal
 */

// Disable error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

session_start();

// Simple path to database config
require_once '../../../../config/database.php';
require_once '../../../../config/leave_types.php';

// Check if user is logged in and has department head access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get department head's department for filtering (only for managers, admins can see all)
$dept_head_department = null;
if ($_SESSION['role'] === 'manager') {
    $stmt = $pdo->prepare("SELECT department FROM employees WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $dept_head = $stmt->fetch(PDO::FETCH_ASSOC);
    $dept_head_department = $dept_head['department'] ?? null;
}

// Check if request ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit();
}

$requestId = intval($_GET['id']);

try {
    // Get leave request details with employee information and conditional fields
    $sql = "
        SELECT 
            lr.*,
            e.name as employee_name,
            e.position,
            e.department,
            e.email as employee_email
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        WHERE lr.id = ?
        AND (lr.dept_head_approval IS NULL OR lr.dept_head_approval = 'pending')
        AND lr.status != 'rejected'
    ";
    
    $params = [$requestId];
    
    // Add department filtering for managers (admins can see all)
    if ($_SESSION['role'] === 'manager' && $dept_head_department) {
        $sql .= " AND e.department = ?";
        $params[] = $dept_head_department;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$leaveRequest) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Leave request not found or already processed']);
        exit();
    }
    
    // Use days_requested from database (already excludes weekends)
    // If not set, calculate excluding weekends
    if (!isset($leaveRequest['days_requested']) || $leaveRequest['days_requested'] == 0) {
        $startDate = new DateTime($leaveRequest['start_date']);
        $endDate = new DateTime($leaveRequest['end_date']);
        $daysRequested = 0;
        $current = clone $startDate;
        while ($current <= $endDate) {
            $dayOfWeek = (int)$current->format('N');
            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                $daysRequested++;
            }
            $current->modify('+1 day');
        }
        $leaveRequest['days_requested'] = $daysRequested;
    }
    
    // Format dates for display
    $startDate = new DateTime($leaveRequest['start_date']);
    $endDate = new DateTime($leaveRequest['end_date']);
    $leaveRequest['start_date'] = $startDate->format('F j, Y');
    $leaveRequest['end_date'] = $endDate->format('F j, Y');
    
    // Get leave types configuration
    $leaveTypes = getLeaveTypes();
    
    // Store raw leave type for conditional field matching (use original_leave_type if available, otherwise use leave_type)
    $leaveRequest['leave_type_raw'] = $leaveRequest['original_leave_type'] ?? $leaveRequest['leave_type'];
    
    // Format leave type display using helper function
    $leaveRequest['leave_type'] = getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null, $leaveTypes, $leaveRequest['other_purpose'] ?? null);
    
    // Format location type for display
    if ($leaveRequest['location_type']) {
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
    if ($leaveRequest['medical_condition']) {
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
    if ($leaveRequest['special_women_condition']) {
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
    if ($leaveRequest['study_type']) {
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
    
    // Get approval history
    $approvalHistory = [];
    
    // Department head approval status
    $approvalHistory[] = [
        'approver' => 'Department Head',
        'status' => $leaveRequest['dept_head_approval'] ?: 'pending',
        'date' => $leaveRequest['dept_head_approval_date'] ?: null,
        'is_current' => true
    ];
    
    // Director approval status
    $approvalHistory[] = [
        'approver' => 'Director',
        'status' => $leaveRequest['director_approval'] ?: 'waiting',
        'date' => $leaveRequest['director_approval_date'] ?: null,
        'is_current' => false
    ];
    
    $leaveRequest['approval_history'] = $approvalHistory;
    
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
