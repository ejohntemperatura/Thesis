<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../config/leave_types.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

// Get leave types configuration
$leaveTypes = getLeaveTypes();

// Get leave history with employee service credit balance (for display hint)
$stmt = $pdo->prepare("
    SELECT lr.*, e.service_credit_balance AS sc_balance,
           lr.leave_type as display_leave_type,
           lr.is_late,
           lr.late_justification,
           lr.selected_dates
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id
    WHERE lr.employee_id = ? 
    ORDER BY lr.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process selected_dates for each request
foreach ($leave_requests as &$request) {
    if (!empty($request['selected_dates'])) {
        $request['selected_dates_array'] = explode(',', $request['selected_dates']);
    }
}
unset($request);

// Set page title
$page_title = "Leave History";

// Include user header
include '../../../../includes/user_header.php';
?>

<!-- Page Header -->
<h1 class="elms-h1" style="margin-bottom: 0.5rem; display: flex; align-items: center;">
    <i class="fas fa-history" style="color: #0891b2; margin-right: 0.75rem;"></i>Leave History
</h1>
<p class="elms-text-muted" style="margin-bottom: 2rem;">View all your leave requests and their status</p>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-check-circle mr-3"></i>
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Leave History Table -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-list text-primary mr-3"></i>
                            Your Leave Requests
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-slate-700/30">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Leave Type</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Leave Dates</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Reason</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Applied On</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-slate-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php 
                                $index = 0;
                                foreach ($leave_requests as $request): 
                                    $index++;
                                    $hidden_class = $index > 10 ? 'hidden leave-row-hidden' : '';
                                ?>
                                    <tr class="hover:bg-slate-700/30 transition-colors <?php echo $hidden_class; ?>">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-2">
                                                <span class="bg-primary/20 text-primary px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">
                                                    <?php 
                                                        $disp = getLeaveTypeDisplayName($request['leave_type'], $request['original_leave_type'] ?? null, $leaveTypes);
                                                        if (!isset($disp) || trim($disp) === '') {
                                                            $base = $request['original_leave_type'] ?? ($request['leave_type'] ?? '');
                                                            $disp = getLeaveTypeDisplayName($base, null, $leaveTypes);
                                                            if (!isset($disp) || trim($disp) === '') {
                                                                if (!empty($request['study_type'])) {
                                                                    $disp = 'Study Leave (Without Pay)';
                                                                } elseif (!empty($request['medical_condition']) || !empty($request['illness_specify'])) {
                                                                    $disp = 'Sick Leave (SL)';
                                                                } elseif (!empty($request['special_women_condition'])) {
                                                                    $disp = 'Special Leave Benefits for Women';
                                                                } elseif (!empty($request['location_type'])) {
                                                                    $disp = 'Vacation Leave (VL)';
                                                                } elseif (isset($request['sc_balance']) && (float)$request['sc_balance'] > 0) {
                                                                    $disp = 'Service Credits';
                                                                } elseif (($request['pay_status'] ?? '') === 'without_pay' || ($request['leave_type'] ?? '') === 'without_pay') {
                                                                    $disp = 'Without Pay Leave';
                                                                } else {
                                                                    $disp = 'Service Credits';
                                                                }
                                                            }
                                                        }
                                                        echo $disp;
                                                    ?>
                                                </span>
                                                <?php if ($request['is_late'] == 1): ?>
                                                    <span class="bg-orange-500/20 text-orange-400 px-2 py-1 rounded-full text-xs font-semibold flex items-center">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                                        Late Application
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-slate-300 text-sm">
                                            <div class="flex flex-wrap gap-1">
                                                <?php 
                                                if (!empty($request['selected_dates_array'])) {
                                                    // Show selected dates as badges
                                                    foreach ($request['selected_dates_array'] as $date): ?>
                                                        <span class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap border border-blue-500/30">
                                                            <?php echo date('M d, Y', strtotime($date)); ?>
                                                        </span>
                                                    <?php endforeach;
                                                } else {
                                                    // Generate date range as badges for older records
                                                    $start = new DateTime($request['start_date']);
                                                    $end = new DateTime($request['end_date']);
                                                    $current = clone $start;
                                                    while ($current <= $end) {
                                                        $dayOfWeek = (int)$current->format('N');
                                                        if ($dayOfWeek >= 1 && $dayOfWeek <= 5): ?>
                                                            <span class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap border border-blue-500/30">
                                                                <?php echo $current->format('M d, Y'); ?>
                                                            </span>
                                                        <?php endif;
                                                        $current->modify('+1 day');
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-slate-300 text-sm max-w-xs truncate" title="<?php echo htmlspecialchars($request['reason']); ?>">
                                            <?php echo htmlspecialchars($request['reason']); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide <?php 
                                                switch($request['status']) {
                                                    case 'approved':
                                                        echo 'bg-green-500/20 text-green-400';
                                                        break;
                                                    case 'rejected':
                                                        echo 'bg-red-500/20 text-red-400';
                                                        break;
                                                    case 'cancelled':
                                                        echo 'bg-gray-500/20 text-gray-400';
                                                        break;
                                                    case 'under_appeal':
                                                        echo 'bg-orange-500/20 text-orange-400';
                                                        break;
                                                    default:
                                                        echo 'bg-yellow-500/20 text-yellow-400';
                                                }
                                            ?>">
                                                <?php 
                                                $status_display = [
                                                    'under_appeal' => 'Under Appeal',
                                                    'cancelled' => 'Cancelled'
                                                ];
                                                echo $status_display[$request['status']] ?? ucfirst($request['status']); 
                                                ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-slate-300 text-sm"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick="viewLeaveDetails(<?php echo $request['id']; ?>)" 
                                                        class="bg-primary hover:bg-primary/90 text-white p-2 rounded-lg transition-colors" title="View Details">
                                                    <i class="fas fa-eye text-xs"></i>
                                                </button>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                <button onclick="cancelLeaveRequest(<?php echo $request['id']; ?>)" 
                                                        class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg transition-colors" title="Cancel Request">
                                                    <i class="fas fa-times text-xs"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($leave_requests)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-12">
                                            <i class="fas fa-inbox text-4xl text-slate-500 mb-4"></i>
                                            <p class="text-slate-400 text-lg">No leave requests found</p>
                                            <p class="text-slate-500 text-sm">Start by applying for your first leave request</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Show More / Show Less Button -->
                    <?php if (count($leave_requests) > 10): ?>
                        <div class="mt-6 text-center">
                            <button id="toggleRowsBtn" onclick="toggleLeaveRows()" 
                                    class="bg-primary hover:bg-primary/90 text-white font-semibold py-3 px-8 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl flex items-center mx-auto">
                                <i class="fas fa-chevron-down mr-2"></i>
                                <span id="toggleBtnText">Show More</span>
                                <span class="ml-2 bg-white/20 px-2 py-1 rounded-full text-xs"><?php echo count($leave_requests) - 10; ?> more</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Leave Details Modal -->
    <div id="leaveDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-8 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto border border-slate-700/50">
            <div class="flex items-center justify-between mb-6">
                <h5 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-eye text-primary mr-3"></i>Leave Request Details
                </h5>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeLeaveDetailsModal()" class="text-slate-400 hover:text-white transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div id="leaveDetailsContent" class="text-slate-300">
                <!-- Content will be loaded here -->
            </div>
            <div class="flex justify-end pt-6">
                <button type="button" onclick="closeLeaveDetailsModal()" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // Pass leave types data to JavaScript
        window.leaveTypes = <?php echo json_encode($leaveTypes); ?>;
        
        // Toggle Show More / Show Less
        function toggleLeaveRows() {
            const hiddenRows = document.querySelectorAll('.leave-row-hidden');
            const btn = document.getElementById('toggleRowsBtn');
            const btnText = document.getElementById('toggleBtnText');
            const icon = btn.querySelector('i');
            const badge = btn.querySelector('.bg-white\\/20');
            
            const isHidden = hiddenRows[0].classList.contains('hidden');
            
            hiddenRows.forEach(row => {
                if (isHidden) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });
            
            if (isHidden) {
                btnText.textContent = 'Show Less';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                badge.style.display = 'none';
            } else {
                btnText.textContent = 'Show More';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
                badge.style.display = 'inline-block';
            }
        }
        
        // Helper function to get leave type display name in JavaScript
        function getLeaveTypeDisplayNameJS(leaveType, originalLeaveType = null) {
            const leaveTypes = window.leaveTypes;
            if (!leaveTypes) return leaveType;
            
            // Check if leave is without pay
            let isWithoutPay = false;
            
            // If leave_type is explicitly 'without_pay', it's without pay
            if (leaveType === 'without_pay') {
                isWithoutPay = true;
            }
            // If original_leave_type exists and current type is 'without_pay' or empty, it was converted to without pay
            else if (originalLeaveType && (leaveType === 'without_pay' || !leaveType)) {
                isWithoutPay = true;
            }
            // Check if the current leave type is inherently without pay
            else if (leaveTypes[leaveType] && leaveTypes[leaveType].without_pay) {
                isWithoutPay = true;
            }
            // Check if the original leave type was inherently without pay
            else if (originalLeaveType && leaveTypes[originalLeaveType] && leaveTypes[originalLeaveType].without_pay) {
                isWithoutPay = true;
            }
            
            // Determine the base leave type to display
            let baseType = null;
            if (originalLeaveType && (leaveType === 'without_pay' || !leaveType)) {
                // Use original type if it was converted to without pay
                baseType = originalLeaveType;
            } else {
                // Use current type
                baseType = leaveType;
            }
            
            // Get the display name
            if (leaveTypes[baseType]) {
                const leaveTypeConfig = leaveTypes[baseType];
                
                // Use formal_name if available, otherwise fall back to name
                const displayName = leaveTypeConfig.formal_name || leaveTypeConfig.name;
                
                if (isWithoutPay) {
                    // Show name with without pay indicator
                    if (leaveTypeConfig.name_with_note) {
                        return leaveTypeConfig.name_with_note;
                    } else {
                        return displayName + ' (Without Pay)';
                    }
                } else {
                    // Show formal name
                    return displayName;
                }
            } else {
                // Fallback for unknown types
                const displayName = baseType.charAt(0).toUpperCase() + baseType.slice(1).replace(/_/g, ' ');
                return isWithoutPay ? displayName + ' (Without Pay)' : displayName;
            }
        }

        // Robust resolver for modal/details: prefers helper, then infers from fields and service credit balance
        function resolveLeaveTypeLabel(req) {
            const lbl = getLeaveTypeDisplayNameJS(req.leave_type, req.original_leave_type);
            if (lbl && String(lbl).trim() !== '') return lbl;
            if (req.study_type) return 'Study Leave (Without Pay)';
            if (req.medical_condition || req.illness_specify) return 'Sick Leave (SL)';
            if (req.special_women_condition) return 'Special Leave Benefits for Women';
            if (req.location_type) return 'Vacation Leave (VL)';
            if (typeof req.sc_balance !== 'undefined' && parseFloat(req.sc_balance) > 0) return 'Service Credits';
            if (req.pay_status === 'without_pay' || req.leave_type === 'without_pay') return 'Without Pay Leave';
            return 'Service Credits';
        }

        function openLeaveDetailsModal() {
            document.getElementById('leaveDetailsModal').classList.remove('hidden');
            document.getElementById('leaveDetailsModal').classList.add('flex');
        }

        function closeLeaveDetailsModal() {
            document.getElementById('leaveDetailsModal').classList.add('hidden');
            document.getElementById('leaveDetailsModal').classList.remove('flex');
        }

        let currentLeaveId = null;

        function viewLeaveDetails(leaveId) {
            currentLeaveId = leaveId;
            // Fetch leave details via AJAX
            fetch(`/ELMS/api/get_leave_details.php?id=${leaveId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const leave = data.leave;
                        const modalContent = document.getElementById('leaveDetailsContent');
                        
                        // Create comprehensive content with conditional fields
                        const isLate = leave.is_late == 1 || leave.is_late === true;
                        const requirements = leave.leave_requirements || {};
                        const statusInfo = leave.status_info || {};
                        
                        modalContent.innerHTML = `
                            ${isLate ? `
                                <div class="bg-orange-500/10 border border-orange-500/20 rounded-xl p-6 mb-6">
                                    <div class="flex items-center mb-4">
                                        <i class="fas fa-exclamation-triangle text-orange-400 text-2xl mr-3"></i>
                                        <h4 class="text-xl font-bold text-orange-400">Late Leave Application</h4>
                                    </div>
                                    <p class="text-orange-300 text-sm">This application was submitted after the required deadline and requires special consideration.</p>
                                </div>
                            ` : `
                                <div class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-6 mb-6">
                                    <div class="flex items-center mb-4">
                                        <i class="fas fa-calendar-check text-blue-400 text-2xl mr-3"></i>
                                        <h4 class="text-xl font-bold text-blue-400">Regular Leave Application</h4>
                                    </div>
                                    <p class="text-blue-300 text-sm">This is a standard leave application submitted within the required timeframe.</p>
                                </div>
                            `}
                            
                            <!-- Status Information -->
                            <div class="mb-6 ${statusInfo.bg_color} border ${statusInfo.border_color} rounded-xl p-4">
                                <div class="flex items-center">
                                    <i class="${statusInfo.icon} ${statusInfo.color} text-xl mr-3"></i>
                                    <div>
                                        <h6 class="${statusInfo.color} font-semibold mb-1">Application Status</h6>
                                        <p class="text-slate-300 text-sm">${statusInfo.message}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h6 class="text-slate-400 mb-2 font-semibold">Leave Type</h6>
                                    <p class="mb-3">
                                        <span class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">${resolveLeaveTypeLabel(leave)}</span>
                                    </p>
                                    
                                    ${leave.selected_dates_array && leave.selected_dates_array.length > 0 ? `
                                        <h6 class="text-slate-400 mb-2 font-semibold">Selected Leave Days</h6>
                                        <div class="mb-3 bg-slate-700/50 p-3 rounded-lg">
                                            <div class="flex flex-wrap gap-2">
                                                ${leave.selected_dates_array.map(date => `
                                                    <span class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full text-xs font-semibold">
                                                        ${new Date(date + 'T00:00:00').toLocaleDateString('en-US', { 
                                                            month: 'short', 
                                                            day: 'numeric',
                                                            year: 'numeric'
                                                        })}
                                                    </span>
                                                `).join('')}
                                            </div>
                                        </div>
                                    ` : `
                                        <h6 class="text-slate-400 mb-2 font-semibold">Start Date</h6>
                                        <p class="mb-3 text-white">${new Date(leave.start_date).toLocaleDateString('en-US', { 
                                            year: 'numeric', 
                                            month: 'long', 
                                            day: 'numeric' 
                                        })}</p>
                                        
                                        <h6 class="text-slate-400 mb-2 font-semibold">End Date</h6>
                                        <p class="mb-3 text-white">${new Date(leave.end_date).toLocaleDateString('en-US', { 
                                            year: 'numeric', 
                                            month: 'long', 
                                            day: 'numeric' 
                                        })}</p>
                                    `}
                                    
                                    <h6 class="text-slate-400 mb-2 font-semibold">Days Requested</h6>
                                    <p class="mb-3 text-white">${leave.days_requested || leave.days || 'N/A'} day(s)</p>
                                    
                                    ${leave.approved_days && leave.approved_days > 0 ? `
                                        <h6 class="text-slate-400 mb-2 font-semibold">Days Approved</h6>
                                        <p class="mb-3 text-green-400 font-semibold">
                                            ${leave.approved_days} day(s) 
                                            ${leave.pay_status ? `<span class="text-xs">(${leave.pay_status.replace('_', ' ')})</span>` : ''}
                                        </p>
                                        ${leave.approved_days != leave.days_requested ? `
                                            <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-3 text-sm text-yellow-400">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Director approved ${leave.approved_days} days instead of ${leave.days_requested} requested days
                                            </div>
                                        ` : ''}
                                    ` : ''}
                                </div>
                                <div>
                                    <h6 class="text-slate-400 mb-2 font-semibold">Status</h6>
                                    <p class="mb-3">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide ${getStatusClass(leave.status)}">
                                            ${getStatusDisplay(leave.status)}
                                        </span>
                                    </p>
                                    
                                    <h6 class="text-slate-400 mb-2 font-semibold">Applied On</h6>
                                    <p class="mb-3 text-white">${new Date(leave.created_at).toLocaleDateString('en-US', { 
                                        year: 'numeric', 
                                        month: 'long', 
                                        day: 'numeric' 
                                    })}</p>
                                    
                                    <h6 class="text-slate-400 mb-2 font-semibold">Employee</h6>
                                    <p class="mb-3 text-white">${leave.employee_name || 'N/A'}</p>
                                    
                                    <h6 class="text-slate-400 mb-2 font-semibold">Department</h6>
                                    <p class="mb-3 text-white">${leave.department || 'N/A'}</p>
                                </div>
                            </div>
                            
                            <!-- Leave Reason -->
                            <div class="mt-6">
                                <h6 class="text-slate-400 mb-2 font-semibold">Leave Reason</h6>
                                <p class="text-white bg-slate-700/50 p-4 rounded-lg">${leave.reason}</p>
                            </div>
                            
                            <!-- Location Details (for vacation leave) -->
                            ${leave.location_type ? `
                                <div class="mt-6">
                                    <h6 class="text-slate-400 mb-2 font-semibold flex items-center">
                                        <i class="fas fa-map-marker-alt text-blue-400 mr-2"></i>
                                        Location Details
                                    </h6>
                                    <div class="bg-slate-700/30 border border-slate-600/50 rounded-lg p-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="text-sm font-medium text-slate-400">Location Type</label>
                                                <p class="text-white">${leave.location_type ? leave.location_type.charAt(0).toUpperCase() + leave.location_type.slice(1).replace('_', ' ') : 'N/A'}</p>
                                            </div>
                                            ${leave.location_specify ? `
                                            <div>
                                                <label class="text-sm font-medium text-slate-400">Specific Location</label>
                                                <p class="text-white">${leave.location_specify}</p>
                                            </div>
                                            ` : ''}
                                        </div>
                                    </div>
                                </div>
                            ` : ''}
                            
                            <!-- Late Justification (if applicable) -->
                            ${isLate ? `
                                <div class="mt-6">
                                    <h6 class="text-slate-400 mb-2 font-semibold flex items-center">
                                        <i class="fas fa-exclamation-triangle text-orange-400 mr-2"></i>
                                        Late Justification
                                    </h6>
                                    <p class="text-white bg-orange-500/10 border border-orange-500/20 p-4 rounded-lg">${leave.late_justification || 'No justification provided'}</p>
                                </div>
                            ` : ''}
                            
                            <!-- Leave-Specific Requirements -->
                            ${requirements.description ? `
                                <div class="mt-6">
                                    <h6 class="text-slate-400 mb-2 font-semibold flex items-center">
                                        <i class="${requirements.icon} ${requirements.color} mr-2"></i>
                                        Leave Requirements
                                    </h6>
                                    <div class="bg-slate-700/30 border border-slate-600/50 rounded-lg p-4">
                                        <p class="text-slate-300 text-sm">${requirements.description}</p>
                                        ${requirements.medical_certificate ? `
                                            <div class="mt-3 flex items-center">
                                                <i class="fas fa-file-medical text-red-400 mr-2"></i>
                                                <span class="text-slate-300 text-sm">Medical Certificate: ${leave.medical_certificate_path ? 'Attached' : 'Not provided'}</span>
                                            </div>
                                        ` : ''}
                                        ${requirements.birth_certificate ? `
                                            <div class="mt-2 flex items-center">
                                                <i class="fas fa-certificate text-cyan-400 mr-2"></i>
                                                <span class="text-slate-300 text-sm">Birth Certificate: Required</span>
                                            </div>
                                        ` : ''}
                                        ${requirements.court_order ? `
                                            <div class="mt-2 flex items-center">
                                                <i class="fas fa-gavel text-red-600 mr-2"></i>
                                                <span class="text-slate-300 text-sm">Court Order/Police Report: Required</span>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            ` : ''}
                            
                            <!-- Special Conditions (if applicable) -->
                            ${leave.special_women_condition ? `
                                <div class="mt-6">
                                    <h6 class="text-slate-400 mb-2 font-semibold">Special Women Condition</h6>
                                    <p class="text-white bg-purple-500/10 border border-purple-500/20 p-4 rounded-lg">
                                        ${leave.special_women_condition.charAt(0).toUpperCase() + leave.special_women_condition.slice(1)}
                                    </p>
                                </div>
                            ` : ''}
                            
                            ${leave.medical_condition ? `
                                <div class="mt-6">
                                    <h6 class="text-slate-400 mb-2 font-semibold">Medical Condition</h6>
                                    <p class="text-white bg-red-500/10 border border-red-500/20 p-4 rounded-lg">
                                        ${leave.medical_condition.charAt(0).toUpperCase() + leave.medical_condition.slice(1)}
                                        ${leave.illness_specify ? ` - ${leave.illness_specify}` : ''}
                                    </p>
                                </div>
                            ` : ''}
                            
                            ${leave.study_type ? `
                                <div class="mt-6">
                                    <h6 class="text-slate-400 mb-2 font-semibold">Study Type</h6>
                                    <p class="text-white bg-indigo-500/10 border border-indigo-500/20 p-4 rounded-lg">
                                        ${leave.study_type.charAt(0).toUpperCase() + leave.study_type.slice(1)}
                                    </p>
                                </div>
                            ` : ''}
                            
                            <!-- Action Buttons -->
                            <div class="mt-6 flex flex-wrap gap-3">
                                ${leave.medical_certificate_path ? `
                                    <button onclick="viewMedicalCertificate('${leave.medical_certificate_path}')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                                        <i class="fas fa-file-medical mr-2"></i>View Medical Certificate
                                    </button>
                                ` : ''}
                            </div>
                            
                            <!-- Important Notices -->
                            <div class="mt-6 ${isLate ? 'bg-yellow-500/10 border border-yellow-500/20' : 'bg-green-500/10 border border-green-500/20'} rounded-lg p-4">
                                <div class="flex items-start">
                                    <i class="fas ${isLate ? 'fa-info-circle text-yellow-400' : 'fa-check-circle text-green-400'} mr-3 mt-1"></i>
                                    <div>
                                        <h6 class="${isLate ? 'text-yellow-400' : 'text-green-400'} font-semibold mb-1">
                                            ${isLate ? 'Important Notice' : 'Application Status'}
                                        </h6>
                                        <p class="${isLate ? 'text-yellow-300' : 'text-green-300'} text-sm">
                                            ${isLate ? 'Late applications may require additional approval and may be subject to different processing times. Please ensure your justification is clear and valid.' : 'This application was submitted on time and follows standard processing procedures.'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        openLeaveDetailsModal();
                    } else {
                        showStyledAlert('Error loading leave details: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    showStyledAlert('Error loading leave details: ' + error.message, 'error');
                });
        }

        // Action button functions

        function viewMedicalCertificate(certificatePath) {
            // Open medical certificate via secure API endpoint
            const timestamp = new Date().getTime();
            window.open(`/ELMS/app/modules/api/view_medical_certificate.php?file=${encodeURIComponent(certificatePath)}&v=${timestamp}`, '_blank');
        }

        // Helper functions for status display
        function getStatusClass(status) {
            switch(status) {
                case 'approved':
                    return 'bg-green-500/20 text-green-400';
                case 'rejected':
                    return 'bg-red-500/20 text-red-400';
                case 'cancelled':
                    return 'bg-gray-500/20 text-gray-400';
                case 'under_appeal':
                    return 'bg-orange-500/20 text-orange-400';
                default:
                    return 'bg-yellow-500/20 text-yellow-400';
            }
        }

        function getStatusDisplay(status) {
            const statusMap = {
                'under_appeal': 'Under Appeal',
                'cancelled': 'Cancelled'
            };
            return statusMap[status] || status.charAt(0).toUpperCase() + status.slice(1);
        }

        // Cancel leave request function
        function cancelLeaveRequest(leaveId) {
            showStyledConfirm(
                'Are you sure you want to cancel this leave request? This action cannot be undone.',
                function(confirmed) {
                    if (confirmed) {
                        // Show loading state
                        const cancelBtn = document.querySelector(`button[onclick="cancelLeaveRequest(${leaveId})"]`);
                        if (cancelBtn) {
                            cancelBtn.disabled = true;
                            cancelBtn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i>';
                        }
                        
                        fetch('/ELMS/app/modules/user/controllers/cancel_leave.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'leave_id=' + leaveId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Show success message and reload page
                                showStyledAlert('Leave request cancelled successfully!', 'success');
                                setTimeout(() => window.location.reload(), 1500);
                            } else {
                                showStyledAlert('Error: ' + data.message, 'error');
                                // Restore button
                                if (cancelBtn) {
                                    cancelBtn.disabled = false;
                                    cancelBtn.innerHTML = '<i class="fas fa-times text-xs"></i>';
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showStyledAlert('An error occurred while cancelling the leave request.', 'error');
                            // Restore button
                            if (cancelBtn) {
                                cancelBtn.disabled = false;
                                cancelBtn.innerHTML = '<i class="fas fa-times text-xs"></i>';
                            }
                        });
                    }
                },
                'danger',
                'Cancel Leave Request',
                'Yes, Cancel',
                'No, Keep It'
            );
        }

    </script>
    <script src="../../../../assets/js/modal-alert.js"></script>

<?php include '../../../../includes/user_footer.php'; ?> 