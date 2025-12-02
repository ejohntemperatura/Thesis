<?php
$dtrData = $reportData['dtr_data'] ?? [];
$attendanceSummary = $reportData['attendance_summary'] ?? [];

// Define standard work hours for late detection
define('MORNING_START_TIME', '08:00:00'); // 8:00 AM
define('AFTERNOON_START_TIME', '13:00:00'); // 1:00 PM
define('LATE_GRACE_PERIOD_MINUTES', 15); // 15 minutes grace period
define('STANDARD_WORK_HOURS', 8); // 8 hours standard

/**
 * Check if a time-in is late
 */
function checkIfLateReport($timeIn, $standardTime) {
    if (!$timeIn) return ['is_late' => false, 'minutes_late' => 0];
    
    $timeInObj = new DateTime($timeIn);
    $standardObj = new DateTime($timeInObj->format('Y-m-d') . ' ' . $standardTime);
    $standardObj->modify('+' . LATE_GRACE_PERIOD_MINUTES . ' minutes');
    
    if ($timeInObj > $standardObj) {
        $diff = $timeInObj->diff($standardObj);
        $minutesLate = ($diff->h * 60) + $diff->i;
        return ['is_late' => true, 'minutes_late' => $minutesLate];
    }
    return ['is_late' => false, 'minutes_late' => 0];
}

/**
 * Format late text
 */
function formatLateTextReport($minutes) {
    if ($minutes >= 60) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return $hours . 'h ' . $mins . 'm';
    }
    return $minutes . 'm';
}

// Calculate late and overtime counts
$lateCount = 0;
$overtimeCount = 0;
$totalOvertimeHours = 0;

foreach ($dtrData as $dtr) {
    $morningLate = checkIfLateReport($dtr['morning_time_in'], MORNING_START_TIME);
    $afternoonLate = checkIfLateReport($dtr['afternoon_time_in'], AFTERNOON_START_TIME);
    if ($morningLate['is_late'] || $afternoonLate['is_late']) {
        $lateCount++;
    }
    if ($dtr['total_hours'] > STANDARD_WORK_HOURS) {
        $overtimeCount++;
        $totalOvertimeHours += ($dtr['total_hours'] - STANDARD_WORK_HOURS);
    }
}
?>

<!-- Attendance Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Total Days</p>
                <p class="text-3xl font-bold text-white"><?php echo $attendanceSummary['total_days'] ?? 0; ?></p>
                <p class="text-slate-400 text-xs mt-1">in period</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-calendar text-blue-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Complete Days</p>
                <p class="text-3xl font-bold text-white"><?php echo $attendanceSummary['complete_days'] ?? 0; ?></p>
                <p class="text-green-400 text-xs mt-1"><?php echo $attendanceSummary['total_days'] > 0 ? round(($attendanceSummary['complete_days'] / $attendanceSummary['total_days']) * 100, 1) : 0; ?>%</p>
            </div>
            <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-check-circle text-green-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Total Hours</p>
                <p class="text-3xl font-bold text-white"><?php echo number_format($attendanceSummary['total_hours'] ?? 0, 1); ?></p>
                <p class="text-slate-400 text-xs mt-1">hours worked</p>
            </div>
            <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-clock text-purple-400 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Late & Overtime Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-red-500/10 border border-red-500/30 rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-red-300 text-sm font-medium">Late Arrivals</p>
                <p class="text-3xl font-bold text-red-400"><?php echo $lateCount; ?></p>
                <p class="text-red-300/70 text-xs mt-1">days with late time-in</p>
            </div>
            <div class="w-12 h-12 bg-red-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-clock text-red-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-blue-500/10 border border-blue-500/30 rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-blue-300 text-sm font-medium">Overtime Days</p>
                <p class="text-3xl font-bold text-blue-400"><?php echo $overtimeCount; ?></p>
                <p class="text-blue-300/70 text-xs mt-1">days with overtime</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-star text-blue-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-cyan-500/10 border border-cyan-500/30 rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-cyan-300 text-sm font-medium">Total Overtime</p>
                <p class="text-3xl font-bold text-cyan-400"><?php echo number_format($totalOvertimeHours, 1); ?></p>
                <p class="text-cyan-300/70 text-xs mt-1">hours (CTO eligible)</p>
            </div>
            <div class="w-12 h-12 bg-cyan-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-hourglass-half text-cyan-400 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Breakdown -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-check-circle text-green-400 mr-2"></i>Complete Days
            </h3>
        </div>
        <div class="p-6">
            <div class="text-center">
                <div class="text-4xl font-bold text-green-400 mb-2"><?php echo $attendanceSummary['complete_days'] ?? 0; ?></div>
                <p class="text-slate-400">Full day attendance</p>
            </div>
        </div>
    </div>
    
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-clock text-yellow-400 mr-2"></i>Half Days
            </h3>
        </div>
        <div class="p-6">
            <div class="text-center">
                <div class="text-4xl font-bold text-yellow-400 mb-2"><?php echo $attendanceSummary['half_days'] ?? 0; ?></div>
                <p class="text-slate-400">Partial attendance</p>
            </div>
        </div>
    </div>
    
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-times-circle text-red-400 mr-2"></i>Absent Days
            </h3>
        </div>
        <div class="p-6">
            <div class="text-center">
                <div class="text-4xl font-bold text-red-400 mb-2"><?php echo $attendanceSummary['absent_days'] ?? 0; ?></div>
                <p class="text-slate-400">No attendance</p>
            </div>
        </div>
    </div>
</div>

<!-- DTR Data Table -->
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
        <h3 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-table text-blue-400 mr-2"></i>Daily Time Record Details
        </h3>
        <p class="text-slate-400 text-sm mt-1">
            <span class="text-red-400"><i class="fas fa-clock mr-1"></i>Late</span> = After 8:15 AM (morning) / 1:15 PM (afternoon) |
            <span class="text-blue-400"><i class="fas fa-star mr-1"></i>OT</span> = More than 8 hours worked
        </p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-600">
            <thead class="bg-slate-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Employee</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Morning In</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Morning Out</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Afternoon In</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Afternoon Out</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Total Hours</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Remarks</th>
                </tr>
            </thead>
            <tbody class="bg-slate-800 divide-y divide-slate-600">
                <?php foreach ($dtrData as $dtr): 
                    // Check for late arrivals
                    $morningLate = checkIfLateReport($dtr['morning_time_in'], MORNING_START_TIME);
                    $afternoonLate = checkIfLateReport($dtr['afternoon_time_in'], AFTERNOON_START_TIME);
                    $hasLate = $morningLate['is_late'] || $afternoonLate['is_late'];
                    
                    // Check for overtime
                    $hasOvertime = $dtr['total_hours'] > STANDARD_WORK_HOURS;
                    $overtimeHours = $hasOvertime ? round($dtr['total_hours'] - STANDARD_WORK_HOURS, 2) : 0;
                    
                    // Row background
                    $rowClass = '';
                    if ($hasLate) $rowClass = 'bg-red-500/5';
                    elseif ($hasOvertime) $rowClass = 'bg-blue-500/5';
                ?>
                <tr class="hover:bg-slate-700/50 <?php echo $rowClass; ?>">
                    <td class="px-4 py-3 text-sm font-medium text-white"><?php echo htmlspecialchars($dtr['employee_name']); ?></td>
                    <td class="px-4 py-3 text-sm text-slate-300"><?php echo date('M d, Y', strtotime($dtr['date'])); ?></td>
                    <td class="px-4 py-3 text-sm <?php echo $morningLate['is_late'] ? 'text-red-400 font-semibold' : 'text-slate-300'; ?>">
                        <?php echo $dtr['morning_time_in'] ? date('H:i', strtotime($dtr['morning_time_in'])) : '-'; ?>
                        <?php if ($morningLate['is_late']): ?>
                            <i class="fas fa-exclamation-circle text-red-400 ml-1" title="Late by <?php echo formatLateTextReport($morningLate['minutes_late']); ?>"></i>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-300">
                        <?php echo $dtr['morning_time_out'] ? date('H:i', strtotime($dtr['morning_time_out'])) : '-'; ?>
                    </td>
                    <td class="px-4 py-3 text-sm <?php echo $afternoonLate['is_late'] ? 'text-red-400 font-semibold' : 'text-slate-300'; ?>">
                        <?php echo $dtr['afternoon_time_in'] ? date('H:i', strtotime($dtr['afternoon_time_in'])) : '-'; ?>
                        <?php if ($afternoonLate['is_late']): ?>
                            <i class="fas fa-exclamation-circle text-red-400 ml-1" title="Late by <?php echo formatLateTextReport($afternoonLate['minutes_late']); ?>"></i>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-300">
                        <?php echo $dtr['afternoon_time_out'] ? date('H:i', strtotime($dtr['afternoon_time_out'])) : '-'; ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php
                        $status = $dtr['attendance_status'];
                        $statusClass = '';
                        switch ($status) {
                            case 'Complete':
                                $statusClass = 'bg-green-500/20 text-green-400';
                                break;
                            case 'Half Day (Morning)':
                            case 'Half Day (Afternoon)':
                                $statusClass = 'bg-yellow-500/20 text-yellow-400';
                                break;
                            case 'Incomplete':
                                $statusClass = 'bg-orange-500/20 text-orange-400';
                                break;
                            case 'Absent':
                                $statusClass = 'bg-red-500/20 text-red-400';
                                break;
                        }
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                            <?php echo $status; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm <?php echo $hasOvertime ? 'text-blue-400 font-semibold' : 'text-slate-300'; ?>">
                        <?php echo number_format($dtr['total_hours'], 2); ?> hrs
                        <?php if ($hasOvertime): ?>
                            <i class="fas fa-star text-blue-400 ml-1" title="Overtime: +<?php echo $overtimeHours; ?> hrs"></i>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php if ($hasLate): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/30 mr-1">
                                <i class="fas fa-clock mr-1"></i>Late
                            </span>
                        <?php endif; ?>
                        <?php if ($hasOvertime): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30">
                                <i class="fas fa-star mr-1"></i>OT +<?php echo $overtimeHours; ?>h
                            </span>
                        <?php endif; ?>
                        <?php if (!$hasLate && !$hasOvertime && $status === 'Complete'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-500/20 text-green-400 border border-green-500/30">
                                <i class="fas fa-check mr-1"></i>OK
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (empty($dtrData)): ?>
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
    <div class="p-12 text-center">
        <i class="fas fa-clock text-6xl text-slate-500 mb-4"></i>
        <h3 class="text-xl font-semibold text-slate-400 mb-2">No Attendance Data</h3>
        <p class="text-slate-500">No DTR records found for the selected period and filters.</p>
    </div>
</div>
<?php endif; ?>

