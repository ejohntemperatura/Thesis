<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/leave_types.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid leave request ID']);
    exit();
}

$leave_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // Fetch leave request details - only for the logged-in user's own requests
    $stmt = $pdo->prepare("
        SELECT 
            lr.*,
            e.name as employee_name,
            e.email as employee_email,
            e.department,
            e.position,
            e.vacation_leave_balance,
            e.sick_leave_balance,
            e.service_credit_balance as sc_balance
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        WHERE lr.id = ? AND lr.employee_id = ?
    ");
    $stmt->execute([$leave_id, $user_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Leave request not found or access denied']);
        exit();
    }
    
    // Calculate number of days
    $start = new DateTime($request['start_date']);
    $end = new DateTime($request['end_date']);
    $request['days'] = $start->diff($end)->days + 1;
    
    // Compute raw vs display leave type
    $leaveTypes = getLeaveTypes();
    $request['leave_type_raw'] = $request['original_leave_type'] ?? $request['leave_type'];
    $request['leave_type_display'] = getLeaveTypeDisplayName($request['leave_type'], $request['original_leave_type'] ?? null, $leaveTypes);

    // Add leave-specific information
    $request['leave_requirements'] = getLeaveRequirements($request['leave_type_raw']);
    $request['status_info'] = getStatusInformation($request['status'], $request['leave_type_raw']);
    $request['can_appeal'] = canAppealLeave($request['status'], $request['created_at']);
    
    // Return the leave request data
    echo json_encode([
        'success' => true,
        'leave' => $request
    ]);
    
} catch (PDOException $e) {
    error_log("Database error fetching leave details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}

/**
 * Get leave-specific requirements based on leave type
 */
function getLeaveRequirements($leaveType) {
    $requirements = [
        'sick' => [
            'medical_certificate' => true,
            'description' => 'Medical certificate required for sick leave',
            'icon' => 'fas fa-file-medical',
            'color' => 'text-red-400'
        ],
        'maternity' => [
            'medical_certificate' => true,
            'description' => 'Medical certificate and birth certificate required',
            'icon' => 'fas fa-baby',
            'color' => 'text-pink-400'
        ],
        'paternity' => [
            'birth_certificate' => true,
            'description' => 'Birth certificate of child required',
            'icon' => 'fas fa-male',
            'color' => 'text-cyan-400'
        ],
        'vawc' => [
            'description' => 'VAWC Leave - 10 days with full pay',
            'icon' => 'fas fa-shield-alt',
            'color' => 'text-red-600'
        ],
        'special_women' => [
            'medical_certificate' => true,
            'description' => 'Medical certificate for special women conditions',
            'icon' => 'fas fa-venus',
            'color' => 'text-purple-400'
        ],
        'rehabilitation' => [
            'medical_certificate' => true,
            'description' => 'Medical certificate and rehabilitation plan required',
            'icon' => 'fas fa-heart',
            'color' => 'text-green-400'
        ],
        'adoption' => [
            'adoption_papers' => true,
            'description' => 'Adoption papers and court order required',
            'icon' => 'fas fa-hands-helping',
            'color' => 'text-emerald-400'
        ]
    ];
    
    return $requirements[$leaveType] ?? [
        'description' => 'Standard leave application',
        'icon' => 'fas fa-calendar-check',
        'color' => 'text-blue-400'
    ];
}

/**
 * Get status-specific information
 */
function getStatusInformation($status, $leaveType) {
    $statusInfo = [
        'pending' => [
            'message' => 'Your leave request is currently under review',
            'icon' => 'fas fa-clock',
            'color' => 'text-yellow-400',
            'bg_color' => 'bg-yellow-500/10',
            'border_color' => 'border-yellow-500/20'
        ],
        'approved' => [
            'message' => 'Your leave request has been approved',
            'icon' => 'fas fa-check-circle',
            'color' => 'text-green-400',
            'bg_color' => 'bg-green-500/10',
            'border_color' => 'border-green-500/20'
        ],
        'rejected' => [
            'message' => 'Your leave request has been rejected',
            'icon' => 'fas fa-times-circle',
            'color' => 'text-red-400',
            'bg_color' => 'bg-red-500/10',
            'border_color' => 'border-red-500/20'
        ],
        'under_appeal' => [
            'message' => 'Your leave request is under appeal',
            'icon' => 'fas fa-gavel',
            'color' => 'text-orange-400',
            'bg_color' => 'bg-orange-500/10',
            'border_color' => 'border-orange-500/20'
        ]
    ];
    
    return $statusInfo[$status] ?? $statusInfo['pending'];
}

/**
 * Check if leave can be appealed
 */
function canAppealLeave($status, $createdAt) {
    if ($status !== 'rejected') {
        return false;
    }
    
    // Can appeal within 7 days of rejection
    $created = new DateTime($createdAt);
    $now = new DateTime();
    $diff = $now->diff($created);
    
    return $diff->days < 7;
}
?>
