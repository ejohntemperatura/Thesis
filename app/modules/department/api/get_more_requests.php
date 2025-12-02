<?php
session_start();
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/leave_types.php';

// Check if user is logged in and is manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get department head's department for filtering
$stmt = $pdo->prepare("SELECT department FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dept_head = $stmt->fetch(PDO::FETCH_ASSOC);
$dept_head_department = $dept_head['department'] ?? null;

if (!isset($_GET['offset']) || !is_numeric($_GET['offset'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid offset parameter']);
    exit();
}

$offset = (int)$_GET['offset'];
$limit = 10; // Load 10 more requests at a time

try {
    // Get additional pending leave requests - filtered by department
    $stmt = $pdo->prepare("
        SELECT lr.*, e.name as employee_name, e.position, e.department 
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        WHERE (lr.dept_head_approval IS NULL OR lr.dept_head_approval = 'pending')
        AND lr.status NOT IN ('rejected', 'cancelled')
        AND e.department = ?
        ORDER BY lr.is_late DESC, lr.created_at DESC 
        LIMIT " . intval($limit) . " OFFSET " . intval($offset)
    );
    $stmt->execute([$dept_head_department]);
    $additional_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get leave types configuration
    $leaveTypes = getLeaveTypes();
    
    // Generate HTML for additional requests
    $html = '';
    foreach ($additional_requests as $request) {
        $start = new DateTime($request['start_date']);
        $end = new DateTime($request['end_date']);
        $days = $start->diff($end)->days + 1;
        
        $html .= '
        <tr class="hover:bg-slate-700/30 transition-colors">
            <td class="px-6 py-4">
                <div>
                    <div class="font-semibold text-white">' . htmlspecialchars($request['employee_name']) . '</div>
                    <div class="text-sm text-slate-400">' . htmlspecialchars($request['position']) . '</div>
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="flex flex-col gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-primary/20 text-primary border border-primary/30">
                        ' . getLeaveTypeDisplayName($request['leave_type'], $request['original_leave_type'] ?? null, $leaveTypes) . '
                    </span>';
        
        if ($request['is_late'] == 1) {
            $html .= '
                    <span class="bg-orange-500/20 text-orange-400 px-2 py-1 rounded-full text-xs font-semibold flex items-center">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Late Application
                    </span>';
        }
        
        $html .= '
                </div>
            </td>
            <td class="px-6 py-4 text-slate-300 text-sm">' . date('M d, Y', strtotime($request['start_date'])) . '</td>
            <td class="px-6 py-4 text-slate-300 text-sm">' . date('M d, Y', strtotime($request['end_date'])) . '</td>
            <td class="px-6 py-4 text-slate-300 text-sm">' . $days . '</td>
            <td class="px-6 py-4 text-slate-300 text-sm max-w-xs truncate" title="' . htmlspecialchars($request['reason']) . '">
                ' . (strlen($request['reason']) > 30 ? substr(htmlspecialchars($request['reason']), 0, 30) . '...' : htmlspecialchars($request['reason'])) . '
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                    <span class="bg-yellow-500/20 text-yellow-400 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">Pending</span>
                    <button type="button" onclick="showStatusInfo(' . $request['id'] . ')" title="View Status Details" class="text-slate-400 hover:text-white transition-colors">
                        <i class="fas fa-info-circle"></i>
                    </button>
                </div>
            </td>
            <td class="px-6 py-4 text-center">
                <div class="flex justify-center">
                    <button onclick="openDepartmentApprovalModal(' . $request['id'] . ')" class="inline-flex items-center px-4 py-2 bg-primary hover:bg-primary/80 text-white text-sm font-medium rounded-lg transition-colors">
                        <i class="fas fa-gavel mr-2"></i> Process Request
                    </button>
                </div>
            </td>
        </tr>';
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'count' => count($additional_requests),
        'hasMore' => count($additional_requests) >= $limit
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error fetching requests: ' . $e->getMessage()]);
}
?>
