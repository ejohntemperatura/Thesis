<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/LeaveCreditsCalculator.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Initialize leave credits calculator and manager
$calculator = new LeaveCreditsCalculator($pdo);
require_once '../../../../app/core/services/LeaveCreditsManager.php';
$creditsManager = new LeaveCreditsManager($pdo);

// Use current year
$selectedYear = date('Y');

// Get leave credit summary using the manager (without automatic recalculation)
$leaveSummary = $creditsManager->getLeaveCreditsSummary($_SESSION['user_id']);

// Only recalculate if ALL credits are missing (not just some are zero)
$needsRecalculation = false;
$requiredFields = [
    'vacation_leave_balance', 'sick_leave_balance', 'special_privilege_leave_balance',
    'maternity_leave_balance', 'paternity_leave_balance', 'solo_parent_leave_balance',
    'vawc_leave_balance', 'rehabilitation_leave_balance', 'terminal_leave_balance'
];

$allFieldsMissing = true;
foreach ($requiredFields as $field) {
    if (isset($leaveSummary[$field]) && $leaveSummary[$field] > 0) {
        $allFieldsMissing = false;
        break;
    }
}

// Only recalculate if ALL fields are missing or zero (indicating a completely new account)
if ($allFieldsMissing) {
    $gstmt = $pdo->prepare("SELECT gender FROM employees WHERE id = ?");
    $gstmt->execute([$_SESSION['user_id']]);
    $gender = $gstmt->fetchColumn();
    $isFemale = ($gender === 'female');
    $maternityInit = $isFemale ? 105 : 0;
    $paternityInit = $isFemale ? 0 : 7;
    $stmt = $pdo->prepare("
        UPDATE employees SET 
            vacation_leave_balance = 1.25,
            sick_leave_balance = 1.25,
            special_leave_privilege_balance = 3,
            maternity_leave_balance = ?,
            paternity_leave_balance = ?,
            solo_parent_leave_balance = 7,
            vawc_leave_balance = 10,
            last_leave_credit_update = CURDATE()
        WHERE id = ? AND (
            vacation_leave_balance = 0 AND 
            sick_leave_balance = 0 AND 
            special_leave_privilege_balance = 0
        )
    ");
    $stmt->execute([$maternityInit, $paternityInit, $_SESSION['user_id']]);
    
    // Refresh the summary after initialization
    $leaveSummary = $creditsManager->getLeaveCreditsSummary($_SESSION['user_id']);
}

// Get employee information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

// Get leave usage for selected year with detailed information
$stmt = $pdo->prepare("
    SELECT 
        leave_type,
        original_leave_type,
        start_date,
        end_date,
        CASE 
            WHEN status = 'approved' AND approved_days IS NOT NULL AND approved_days > 0 
            THEN approved_days
            ELSE days_requested
        END as days_used,
        COALESCE(days_requested, 0) as days_requested,
        approved_days,
        pay_status,
        status,
        reason,
        is_late,
        late_justification,
        created_at,
        approved_at,
        rejection_reason
    FROM leave_requests 
    WHERE employee_id = ? 
    AND YEAR(start_date) = ? 
    ORDER BY start_date DESC
");
$stmt->execute([$_SESSION['user_id'], $selectedYear]);
$leaveHistory = $stmt->fetchAll();

// Calculate total leave days used this year
$totalUsed = array_sum(array_column($leaveHistory, 'days_used'));

// Calculate leave usage by type (use original_leave_type if available, otherwise leave_type)
$leaveUsageByType = [];
foreach ($leaveHistory as $leave) {
    // Use original_leave_type if available (for proceed without pay), otherwise use leave_type
    $type = !empty($leave['original_leave_type']) ? $leave['original_leave_type'] : $leave['leave_type'];
    
    if (!isset($leaveUsageByType[$type])) {
        $leaveUsageByType[$type] = [
            'total_days' => 0,
            'approved_days' => 0,
            'pending_days' => 0,
            'rejected_days' => 0,
            'count' => 0
        ];
    }
    
    $leaveUsageByType[$type]['total_days'] += $leave['days_used'];
    $leaveUsageByType[$type]['count']++;
    
    if ($leave['status'] === 'approved') {
        $leaveUsageByType[$type]['approved_days'] += $leave['days_used'];
    } elseif ($leave['status'] === 'pending') {
        $leaveUsageByType[$type]['pending_days'] += $leave['days_used'];
    } elseif ($leave['status'] === 'rejected') {
        $leaveUsageByType[$type]['rejected_days'] += $leave['days_used'];
    }
}

// Get leave type mapping for display
$leaveTypeMapping = [
    'vacation' => 'Vacation Leave',
    'special_privilege' => 'Special Leave Privilege',
    'sick' => 'Sick Leave',
    'study' => 'Study Leave',
    'solo_parent' => 'Solo Parent Leave',
    'vawc' => '10-Day VAWC Leave',
    'rehabilitation' => 'Rehabilitation Privilege',
    'special_women' => 'Special Leave Benefits for Women',
    'special_emergency' => 'Special Emergency Leave (Calamity)',
    'adoption' => 'Adoption Leave',
    'maternity' => 'Maternity Leave',
    'paternity' => 'Paternity Leave',
    'mandatory' => 'Mandatory/Forced Leave',
    'other' => 'Other Purpose'
];

// Set page title
$page_title = "Leave Credits";

// Include user header
include '../../../../includes/user_header.php';
?>

<!-- Page Header -->
<h1 class="elms-h1" style="margin-bottom: 0.5rem; display: flex; align-items: center;">
    <i class="fas fa-calculator" style="color: #0891b2; margin-right: 0.75rem;"></i>Leave Credits
</h1>
<p class="elms-text-muted" style="margin-bottom: 2rem;">Civil Service compliant leave credit management</p>

                <!-- Civil Service Information -->
                <div class="bg-blue-500/20 border border-blue-500/30 rounded-2xl p-6 mb-8">
                    <div class="flex items-start gap-4">
                        <i class="fas fa-info-circle text-blue-400 text-2xl mt-1"></i>
                        <div>
                            <h3 class="text-xl font-semibold text-blue-400 mb-2">Civil Service Leave Credits</h3>
                            <p class="text-slate-300 mb-4">Your leave credits are calculated according to Civil Service Commission rules:</p>
                            <ul class="text-slate-300 space-y-1 text-sm">
                                <li>• <strong>Vacation Leave:</strong> 1.25 days per month (15 days annually)</li>
                                <li>• <strong>Sick Leave:</strong> 1.25 days per month (15 days annually)</li>
                                <li>• <strong>Special Privilege:</strong> 3 days annually</li>
                                <li>• <strong>CTO:</strong> Earned through overtime work (1:1 ratio), holiday work (1.5:1 ratio)</li>
                                <li>• <strong>Special Leaves:</strong> As per Civil Service rules and agency policies</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Leave Credits Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <?php
                    require_once '../../../../config/leave_types.php';
                    $leaveTypes = getLeaveTypes();
                    
                    foreach ($leaveTypes as $type => $info):
                        // Skip "Without Pay Leave" - it should not appear in leave credits display
                        if ($type === 'without_pay') {
                            continue;
                        }
                        
                        // Hide female-only leave types for male employees
                        if (!empty($employee) && isset($employee['gender']) && $employee['gender'] !== 'female') {
                            if (isset($info['gender_restricted']) && $info['gender_restricted'] === 'female') {
                                continue;
                            }
                        }
                        // Hide paternity for female employees
                        if (!empty($employee) && isset($employee['gender']) && $employee['gender'] === 'female' && $type === 'paternity') {
                            continue;
                        }
                        // Hide Solo Parent leave if employee is not a solo parent
                        if ($type === 'solo_parent') {
                            $isSoloParent = isset($employee['is_solo_parent']) ? (int)$employee['is_solo_parent'] : 0;
                            if ($isSoloParent !== 1) {
                                continue;
                            }
                        }
                        // Get current balance from the manager
                        // Use credit_field from config if available, otherwise construct field name
                        $fieldName = isset($info['credit_field']) ? $info['credit_field'] : ($type . '_leave_balance');
                        $currentBalance = isset($leaveSummary[$fieldName]) ? $leaveSummary[$fieldName] : 0;
                        
                        // Hide VL/SL/SLP cards if not eligible (no credits)
                        if (in_array($type, ['vacation','sick','special_privilege']) && (float)$currentBalance <= 0) {
                            continue;
                        }

                        // Get usage statistics for this leave type
                        $usage = $leaveUsageByType[$type] ?? [
                            'total_days' => 0,
                            'approved_days' => 0,
                            'pending_days' => 0,
                            'rejected_days' => 0,
                            'count' => 0
                        ];
                        
                        // For CTO, convert days to hours (1 day = 8 hours)
                        $isCTO = ($type === 'cto');
                        $totalAllocatedDays = $usage['approved_days'];
                        $pendingDays = $usage['pending_days'];
                        $rejectedDays = $usage['rejected_days'];
                        
                        if ($isCTO) {
                            $totalAllocatedHours = $totalAllocatedDays * 8;
                            $pendingHours = $pendingDays * 8;
                            $rejectedHours = $rejectedDays * 8;
                        }
                        
                        // Calculate total allocated (current balance + used)
                        $totalAllocated = $currentBalance + ($isCTO ? $totalAllocatedHours : $totalAllocatedDays);
                        $usedAmount = $isCTO ? $totalAllocatedHours : $totalAllocatedDays;
                        $usedPercentage = $totalAllocated > 0 ? ($usedAmount / $totalAllocated) * 100 : 0;
                        $remainingPercentage = 100 - $usedPercentage;
                    ?>
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6 hover:border-slate-600/50 transition-all duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 <?php echo $info['color']; ?> rounded-xl flex items-center justify-center">
                                    <i class="<?php echo $info['icon']; ?> text-white text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-white"><?php echo $info['name']; ?></h3>
                                    <p class="text-slate-400 text-sm">Civil Service</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-white"><?php echo number_format($currentBalance, 2); ?></div>
                                <div class="text-slate-400 text-sm"><?php echo $type === 'cto' ? 'hours remaining' : 'days remaining'; ?></div>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">Total Allocated:</span>
                                <span class="text-white font-semibold"><?php echo number_format($totalAllocated, 2); ?> <?php echo $type === 'cto' ? 'hours' : 'days'; ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">Used (Approved):</span>
                                <span class="text-white font-semibold"><?php echo number_format($usedAmount, 2); ?> <?php echo $type === 'cto' ? 'hours' : 'days'; ?></span>
                            </div>
                            <?php if ($isCTO ? $pendingHours > 0 : $pendingDays > 0): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">Pending:</span>
                                <span class="text-yellow-400 font-semibold"><?php echo number_format($isCTO ? $pendingHours : $pendingDays, 2); ?> <?php echo $type === 'cto' ? 'hours' : 'days'; ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($isCTO ? $rejectedHours > 0 : $rejectedDays > 0): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">Rejected:</span>
                                <span class="text-red-400 font-semibold"><?php echo number_format($isCTO ? $rejectedHours : $rejectedDays, 2); ?> <?php echo $type === 'cto' ? 'hours' : 'days'; ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="w-full bg-slate-700 rounded-full h-2">
                                <div class="<?php echo $info['color']; ?> h-2 rounded-full transition-all duration-300" 
                                     style="width: <?php echo $remainingPercentage; ?>%"></div>
                            </div>
                            <div class="text-xs text-slate-400 text-center">
                                <?php echo number_format($remainingPercentage, 2); ?>% available
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                                    </div>

                <!-- Leave History for Selected Year -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-history text-primary mr-3"></i>
                            Leave Usage for <?php echo $selectedYear; ?>
                        </h3>
                                </div>
                    <div class="p-6">
                        <?php if (!empty($leaveHistory)): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-700/50">
                                            <th class="text-left py-3 px-4 text-slate-400">Leave Type</th>
                                            <th class="text-left py-3 px-4 text-slate-400">Start Date</th>
                                            <th class="text-left py-3 px-4 text-slate-400">End Date</th>
                                            <th class="text-left py-3 px-4 text-slate-400">Days</th>
                                            <th class="text-left py-3 px-4 text-slate-400">Status</th>
                                            <th class="text-left py-3 px-4 text-slate-400">Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leaveHistory as $leave): ?>
                                        <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition-colors">
                                            <td class="py-3 px-4 text-white font-semibold">
                                                <div class="flex items-center gap-2">
                                                    <?php 
                                                    // Use original_leave_type for display if available (for proceed without pay)
                                                    $displayType = !empty($leave['original_leave_type']) ? $leave['original_leave_type'] : $leave['leave_type'];
                                                    $typeInfo = $leaveTypes[$displayType] ?? ['name' => ucwords(str_replace('_', ' ', $displayType)), 'icon' => 'fas fa-calendar', 'color' => 'from-gray-500 to-slate-500'];
                                                    ?>
                                                    <i class="<?php echo $typeInfo['icon']; ?> text-primary"></i>
                                                    <?php echo $typeInfo['name']; ?>
                                                    <?php if ($leave['leave_type'] === 'without_pay' && !empty($leave['original_leave_type'])): ?>
                                                        <span class="px-2 py-1 bg-orange-500/20 text-orange-400 text-xs rounded-full">Without Pay</span>
                                                    <?php endif; ?>
                                                    <?php if ($leave['is_late']): ?>
                                                        <span class="px-2 py-1 bg-orange-500/20 text-orange-400 text-xs rounded-full">Late</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php echo date('M d, Y', strtotime($leave['start_date'])); ?>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php echo date('M d, Y', strtotime($leave['end_date'])); ?>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300 font-mono">
                                                <div class="flex items-center gap-1">
                                                    <?php echo $leave['days_used']; ?>
                                                    <?php if ($leave['days_requested'] != $leave['days_used']): ?>
                                                        <span class="text-xs text-slate-500">(<?php echo $leave['days_requested']; ?> requested)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide <?php 
                                                    echo $leave['status'] === 'approved' ? 'bg-green-500/20 text-green-400' : 
                                                        ($leave['status'] === 'rejected' ? 'bg-red-500/20 text-red-400' : 
                                                        ($leave['status'] === 'cancelled' ? 'bg-gray-500/20 text-gray-400' : 'bg-yellow-500/20 text-yellow-400')); 
                                                ?>">
                                                    <?php echo ucfirst($leave['status']); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <div class="max-w-xs">
                                                    <?php 
                                                    // For late leave applications, show late_justification instead of reason
                                                    $displayText = $leave['is_late'] == 1 ? ($leave['late_justification'] ?? '') : ($leave['reason'] ?? '');
                                                    ?>
                                                    <div class="truncate" title="<?php echo htmlspecialchars($displayText); ?>">
                                                        <?php echo htmlspecialchars(substr($displayText, 0, 50)) . (strlen($displayText) > 50 ? '...' : ''); ?>
                                                    </div>
                                                    <?php if ($leave['is_late'] && !empty($leave['late_justification'])): ?>
                                                        <div class="text-xs text-orange-400 mt-1">
                                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                                            Late Application
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="p-4 bg-slate-700/30 rounded-xl">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-300">Total Days Used:</span>
                                        <span class="text-2xl font-bold text-white"><?php echo $totalUsed; ?></span>
                                    </div>
                                    <div class="text-sm text-slate-400 mt-1">in <?php echo $selectedYear; ?></div>
                                </div>
                                
                                <div class="p-4 bg-slate-700/30 rounded-xl">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-300">Pending Requests:</span>
                                        <span class="text-2xl font-bold text-yellow-400">
                                            <?php echo count(array_filter($leaveHistory, function($leave) { return $leave['status'] === 'pending'; })); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-slate-400 mt-1">awaiting approval</div>
                                </div>
                                
                                <div class="p-4 bg-slate-700/30 rounded-xl">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-300">Approved Requests:</span>
                                        <span class="text-2xl font-bold text-green-400">
                                            <?php echo count(array_filter($leaveHistory, function($leave) { return $leave['status'] === 'approved'; })); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-slate-400 mt-1">successfully processed</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-calendar-times text-4xl text-slate-500 mb-4"></i>
                                <h3 class="text-xl font-semibold text-white mb-2">No Leave Records</h3>
                                <p class="text-slate-400">You haven't used any leave credits in <?php echo $selectedYear; ?>.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh every 30 seconds if there are pending requests
        <?php if (count(array_filter($leaveHistory, function($leave) { return $leave['status'] === 'pending'; })) > 0): ?>
        setTimeout(function() {
            if (confirm('You have pending leave requests. Would you like to refresh to see the latest updates?')) {
                window.location.reload();
            }
        }, 30000);
        <?php endif; ?>
    </script>

<?php include '../../../../includes/user_footer.php'; ?> 