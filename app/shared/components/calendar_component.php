<?php
// Shared Calendar Component
// This file provides calendar functionality for all roles
// Used by: admin, director, department heads, employees

// Session already started by including file
require_once '../../../../config/database.php';
require_once '../../../../config/leave_types.php';
require_once '../../../../config/holidays.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit();
}

$role = $_SESSION['role'] ?? 'employee';

// Get department for filtering (for managers/department heads)
$user_department = null;
if (in_array($role, ['manager'])) {
    $stmt = $pdo->prepare("SELECT department FROM employees WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_department = $user_data['department'] ?? null;
}

// Get APPROVED leave requests only - with proper approved days
$sql = "
    SELECT 
        lr.*, 
        e.name as employee_name, 
        e.position, 
        e.department,
        e.service_credit_balance AS sc_balance,
        CASE 
            WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 
            THEN lr.approved_days
            ELSE DATEDIFF(lr.end_date, lr.start_date) + 1 
        END as actual_days_approved
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    WHERE lr.status = 'approved'
";

$params = [];

// Add department filtering for managers
if ($role === 'manager' && $user_department) {
    $sql .= " AND e.department = ?";
    $params[] = $user_department;
} elseif ($role === 'employee') {
    $sql .= " AND lr.employee_id = ?";
    $params[] = $_SESSION['user_id'];
}

$sql .= " ORDER BY lr.start_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$leave_requests = $stmt->fetchAll();

// Get leave types configuration
$leaveTypes = getLeaveTypes();
?>

<!-- Calendar Container -->
<div class="bg-slate-800 rounded-2xl border border-slate-700/50 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
        <h3 class="text-xl font-semibold text-white flex items-center">
            <i class="fas fa-calendar text-primary mr-3"></i>Leave Chart
        </h3>
    </div>
    <div class="p-6">
        <!-- Leave Type Legend -->
        <div class="mb-6">
            <h4 class="text-lg font-semibold text-white mb-4">Leave Type Legend</h4>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-vacation"></div>
                    <span class="text-sm text-slate-300">Vacation</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-service_credits"></div>
                    <span class="text-sm text-slate-300">Service Credits</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-special_privilege"></div>
                    <span class="text-sm text-slate-300">Special Privilege</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-sick"></div>
                    <span class="text-sm text-slate-300">Sick Leave</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-maternity"></div>
                    <span class="text-sm text-slate-300">Maternity</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-paternity"></div>
                    <span class="text-sm text-slate-300">Paternity</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-study"></div>
                    <span class="text-sm text-slate-300">Study Leave</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-solo_parent"></div>
                    <span class="text-sm text-slate-300">Solo Parent</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-vawc"></div>
                    <span class="text-sm text-slate-300">VAWC Leave</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-rehabilitation"></div>
                    <span class="text-sm text-slate-300">Rehabilitation</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-special_women"></div>
                    <span class="text-sm text-slate-300">Special Women</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-special_emergency"></div>
                    <span class="text-sm text-slate-300">Special Emergency</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-adoption"></div>
                    <span class="text-sm text-slate-300">Adoption</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-mandatory"></div>
                    <span class="text-sm text-slate-300">Mandatory Leave</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded leave-without_pay"></div>
                    <span class="text-sm text-slate-300">Without Pay</span>
                </div>
            </div>
        </div>
        
        <div id="calendar"></div>
    </div>
</div>

<style>
/* FullCalendar Custom Styling */
.fc {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.fc-header-toolbar {
    margin-bottom: 1.5rem !important;
    padding: 1rem;
    background: #1e293b !important;
    border-radius: 8px;
    border: 1px solid #334155 !important;
}

.fc-toolbar-title {
    font-size: 1.5rem !important;
    font-weight: 600 !important;
    color: #f8fafc !important;
}

.fc-button {
    background: #0891b2 !important;
    border: 1px solid #0891b2 !important;
    border-radius: 6px !important;
    font-weight: 500 !important;
    padding: 0.5rem 1rem !important;
    color: white !important;
}

.fc-button:hover {
    background: #0e7490 !important;
    border-color: #0e7490 !important;
}

.fc-button:focus {
    box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.3) !important;
}

.fc-button-primary:not(:disabled):active {
    background: #0e7490 !important;
    border-color: #0e7490 !important;
}

.fc-button-group {
    background: #1e293b !important;
}

.fc-button-group .fc-button {
    background: #334155 !important;
    border-color: #475569 !important;
    color: #f8fafc !important;
}

.fc-button-group .fc-button:hover {
    background: #475569 !important;
    border-color: #64748b !important;
}

.fc-button-group .fc-button:focus {
    box-shadow: 0 0 0 3px rgba(71, 85, 105, 0.3) !important;
}

.fc-event {
    border-radius: 4px !important;
    border: none !important;
    padding: 2px 6px !important;
    font-size: 0.85rem !important;
    font-weight: 500 !important;
}

.fc-event-title {
    font-weight: 600 !important;
}

/* Leave Type Colors - Solid Colors (matching leave credits) */
.leave-vacation { background: #3b82f6 !important; color: white !important; }
.leave-sick { background: #ef4444 !important; color: white !important; }
.leave-mandatory { background: #6b7280 !important; color: white !important; }
.leave-special_privilege { background: #eab308 !important; color: white !important; }
.leave-maternity { background: #ec4899 !important; color: white !important; }
.leave-paternity { background: #06b6d4 !important; color: white !important; }
.leave-solo_parent { background: #f97316 !important; color: white !important; }
.leave-vawc { background: #dc2626 !important; color: white !important; }
.leave-rehabilitation { background: #22c55e !important; color: white !important; }
.leave-special_women { background: #a855f7 !important; color: white !important; }
.leave-special_emergency { background: #ea580c !important; color: white !important; }
.leave-adoption { background: #10b981 !important; color: white !important; }
.leave-study { background: #6366f1 !important; color: white !important; }
.leave-without_pay { background: #6b7280 !important; color: white !important; }
.leave-service_credits { background: #14b8a6 !important; color: white !important; }
.leave-service_credit { background: #14b8a6 !important; color: white !important; }

/* Holiday Events - Option B: transparent background with cool tones (strong specificity) */
.holiday-regular, .holiday-special,
.holiday-regular.fc-h-event, .holiday-special.fc-h-event {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}
.holiday-regular .fc-event-main, .holiday-special .fc-event-main { background: transparent !important; }
.holiday-regular .fc-event-title, .holiday-special .fc-event-title { font-weight: 800 !important; }
/* Use white text for both types */
.holiday-regular, .holiday-regular .fc-event-title { color: #ffffff !important; }
.holiday-special, .holiday-special .fc-event-title { color: #ffffff !important; }

/* FullCalendar Dark Theme */
.fc {
    background: #1e293b !important;
    color: #f8fafc !important;
}

.fc-theme-standard td, .fc-theme-standard th {
    border-color: #334155 !important;
}

.fc-theme-standard .fc-scrollgrid {
    border-color: #334155 !important;
}

.fc-daygrid-day {
    background: #1e293b !important;
}

.fc-daygrid-day:hover {
    background: #334155 !important;
}

.fc-daygrid-day-number {
    color: #f8fafc !important;
}

.fc-daygrid-day.fc-day-today {
    background: #0f172a !important;
}

.fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
    color: #06b6d4 !important;
    font-weight: 600 !important;
}

.fc-col-header-cell {
    background: #334155 !important;
    color: #f8fafc !important;
    font-weight: 600 !important;
}

.fc-daygrid-day-events {
    margin-top: 2px !important;
}

.fc-daygrid-event {
    margin: 1px 2px !important;
}

.fc-list {
    background: #1e293b !important;
}

.fc-list-day-cushion {
    background: #334155 !important;
    color: #f8fafc !important;
}

.fc-list-event {
    background: #1e293b !important;
    border-color: #334155 !important;
}

.fc-list-event:hover {
    background: #334155 !important;
}

.fc-list-event-time {
    color: #94a3b8 !important;
}

.fc-list-event-title {
    color: #f8fafc !important;
}

/* More Link Styling */
.fc-more-link {
    background: #0891b2 !important;
    color: white !important;
    border-radius: 4px !important;
    padding: 2px 6px !important;
    font-size: 0.75rem !important;
    font-weight: 500 !important;
    text-decoration: none !important;
    display: inline-block !important;
    margin-top: 2px !important;
    transition: all 0.2s ease !important;
}

.fc-more-link:hover {
    background: #0e7490 !important;
    color: white !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 2px 4px rgba(8, 145, 178, 0.3) !important;
}

/* Popover Styling */
.fc-popover {
    background: #1e293b !important;
    border: 1px solid #334155 !important;
    border-radius: 8px !important;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3) !important;
}

.fc-popover-header {
    background: #334155 !important;
    color: #f8fafc !important;
    border-bottom: 1px solid #475569 !important;
    padding: 0.75rem 1rem !important;
    font-weight: 600 !important;
}

.fc-popover-body {
    background: #1e293b !important;
    color: #f8fafc !important;
    padding: 0.5rem !important;
}

.fc-popover-close {
    color: #94a3b8 !important;
    font-size: 1.25rem !important;
}

.fc-popover-close:hover {
    color: #f8fafc !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listWeek'
        },
        height: 'auto',
        dayMaxEvents: 3, // Limit to 3 events per day
        moreLinkClick: 'popover', // Show popover for additional events
        moreLinkText: function(num) {
            return '+ ' + num + ' more';
        },
        events: [
            <?php foreach ($leave_requests as $request): 
                // Get proper display name using the function
                $leaveDisplayName = getLeaveTypeDisplayName($request['leave_type'] ?? '', $request['original_leave_type'] ?? null, $leaveTypes);

                // If still blank, infer from fields (same logic used in dashboards)
                if (!isset($leaveDisplayName) || trim((string)$leaveDisplayName) === '') {
                    if (!empty($request['study_type'])) {
                        $leaveDisplayName = 'Study Leave (Without Pay)';
                    } elseif (!empty($request['medical_condition']) || !empty($request['illness_specify'])) {
                        $leaveDisplayName = 'Sick Leave (SL)';
                    } elseif (!empty($request['special_women_condition'])) {
                        $leaveDisplayName = 'Special Leave Benefits for Women';
                    } elseif (!empty($request['location_type'])) {
                        $leaveDisplayName = 'Vacation Leave (VL)';
                    } elseif (isset($request['sc_balance']) && (float)$request['sc_balance'] > 0) {
                        $leaveDisplayName = 'Service Credits';
                    } elseif (($request['pay_status'] ?? '') === 'without_pay' || ($request['leave_type'] ?? '') === 'without_pay') {
                        $leaveDisplayName = 'Without Pay Leave';
                    } else {
                        $leaveDisplayName = 'Service Credits';
                    }
                }

                // Determine color class robustly
                $typeForColor = $request['leave_type'] ?? '';
                if ($typeForColor === 'without_pay' && !empty($request['original_leave_type'])) {
                    $typeForColor = $request['original_leave_type'];
                }
                if (empty($typeForColor)) {
                    if (isset($request['sc_balance']) && (float)$request['sc_balance'] > 0) {
                        $typeForColor = 'service_credit';
                    } elseif (!empty($request['location_type'])) {
                        $typeForColor = 'vacation';
                    }
                }
                $typeForColor = strtolower(preg_replace('/[^a-z0-9_]/', '_', str_replace([' ', '-'], '_', (string)$typeForColor)));
                if ($typeForColor === 'service_credits' || ($typeForColor && strpos($typeForColor, 'service') !== false && strpos($typeForColor, 'credit') !== false)) {
                    $typeForColor = 'service_credit';
                }
                $colorClass = 'leave-' . ($typeForColor ?: 'vacation');
                
                // Collect all weekday dates and group consecutive weekdays
                $start = new DateTime($request['start_date']);
                $daysToCount = $request['actual_days_approved'];
                $weekdaysCounted = 0;
                $current = clone $start;
                $weekdayGroups = [];
                $currentGroup = null;
                
                // Collect all weekday dates
                while ($weekdaysCounted < $daysToCount) {
                    $dayOfWeek = (int)$current->format('N');
                    
                    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Weekday
                        if ($currentGroup === null) {
                            $currentGroup = ['start' => $current->format('Y-m-d'), 'end' => $current->format('Y-m-d')];
                        } else {
                            $currentGroup['end'] = $current->format('Y-m-d');
                        }
                        $weekdaysCounted++;
                    } else { // Weekend
                        if ($currentGroup !== null) {
                            $weekdayGroups[] = $currentGroup;
                            $currentGroup = null;
                        }
                    }
                    
                    if ($weekdaysCounted < $daysToCount) {
                        $current->modify('+1 day');
                    }
                }
                
                // Add the last group
                if ($currentGroup !== null) {
                    $weekdayGroups[] = $currentGroup;
                }
                
                // Create separate events for each weekday group
                foreach ($weekdayGroups as $index => $group):
                    $groupEnd = new DateTime($group['end']);
                    $groupEnd->modify('+1 day');
            ?>
            {
                id: '<?php echo $request['id'] . '_' . $index; ?>',
                title: '<?php echo addslashes($request['employee_name']); ?> - <?php echo addslashes($leaveDisplayName); ?> (<?php echo $request['actual_days_approved']; ?> day<?php echo $request['actual_days_approved'] != 1 ? 's' : ''; ?>)',
                start: '<?php echo $group['start']; ?>',
                end: '<?php echo $groupEnd->format('Y-m-d'); ?>',
                allDay: true,
                className: '<?php echo $colorClass; ?>',
                display: 'block',
                extendedProps: {
                    leave_type: '<?php echo $request['leave_type']; ?>',
                    employee_name: '<?php echo addslashes($request['employee_name']); ?>',
                    department: '<?php echo addslashes($request['department']); ?>',
                    position: '<?php echo addslashes($request['position']); ?>',
                    days_approved: <?php echo $request['actual_days_approved']; ?>,
                    pay_status: '<?php echo $request['pay_status'] ?? 'N/A'; ?>',
                    display_name: '<?php echo addslashes($leaveDisplayName); ?>'
                }
            },
            <?php endforeach; ?>
            <?php endforeach; ?>
            <?php 
                // Inject holiday events for current, previous, and next year so adjacent months also render
                $years = [date('Y') - 1, date('Y'), date('Y') + 1];
                foreach ($years as $y):
                    $hols = getHolidays((int)$y);
                    foreach ($hols as $hDate => $hInfo):
                        $hTitle = is_array($hInfo) ? ($hInfo['title'] ?? 'Holiday') : $hInfo;
                        $hType = is_array($hInfo) ? ($hInfo['type'] ?? 'regular') : 'regular';
                        $hEnd = (new DateTime($hDate))->modify('+1 day')->format('Y-m-d');
                        $hClass = $hType === 'special' ? 'holiday-special' : 'holiday-regular';
                        $hLabel = ($hType === 'special' ? '⭐ ' : '') . $hTitle;
            ?>
            // Background highlight (Option D)
            {
                id: 'holidaybg_<?php echo $hDate; ?>',
                start: '<?php echo $hDate; ?>',
                end: '<?php echo $hEnd; ?>',
                allDay: true,
                display: 'background',
                className: 'holiday-bg-<?php echo $hType === 'special' ? 'special' : 'regular'; ?>',
                backgroundColor: '<?php echo $hType === 'special' ? 'rgba(253,164,175,0.12)' : 'rgba(134,239,172,0.12)'; ?>',
                borderColor: 'transparent',
                extendedProps: { isHoliday: true, isBackground: true, holidayType: '<?php echo $hType; ?>' }
            },
            // Foreground label
            {
                id: 'holiday_<?php echo $hDate; ?>',
                title: '<?php echo addslashes($hLabel); ?>',
                start: '<?php echo $hDate; ?>',
                end: '<?php echo $hEnd; ?>',
                allDay: true,
                className: '<?php echo $hClass; ?>',
                display: 'block',
                extendedProps: { isHoliday: true, holidayType: '<?php echo $hType; ?>' }
            },
            <?php 
                    endforeach; 
                endforeach; 
            ?>
        ],
        eventClick: function(info) {
            const props = info.event.extendedProps;
            if (props && props.isHoliday) {
                const typeLabel = props.holidayType === 'special' ? 'Special (Non-Working) Holiday' : 'Regular Holiday';
                const isSpecial = props.holidayType === 'special';
                const holidayHtml = `
                    <div class="space-y-4">
                        <div class="text-center pb-4 border-b border-slate-600/50">
                            <div class="w-14 h-14 ${isSpecial ? 'bg-gradient-to-br from-pink-500/30 to-rose-500/20' : 'bg-gradient-to-br from-green-500/30 to-emerald-500/20'} rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas ${isSpecial ? 'fa-star' : 'fa-flag'} text-2xl ${isSpecial ? 'text-pink-400' : 'text-green-400'}"></i>
                            </div>
                            <div class="text-lg font-semibold text-white">${info.event.title.replace(/^⭐\s*/, '')}</div>
                            <div class="text-sm ${isSpecial ? 'text-pink-400' : 'text-green-400'}">${typeLabel}</div>
                        </div>
                        <div class="bg-slate-700/30 rounded-lg p-4 text-center">
                            <div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Date</div>
                            <div class="text-base font-medium text-white">${info.event.start.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}</div>
                        </div>
                    </div>
                `;
                showStyledAlert(holidayHtml, 'info', 'Holiday', true);
                return;
            }
            
            // Format pay status nicely
            let payStatusDisplay = props.pay_status || 'N/A';
            if (payStatusDisplay === 'with_pay') payStatusDisplay = 'With Pay';
            else if (payStatusDisplay === 'without_pay') payStatusDisplay = 'Without Pay';
            
            const leaveHtml = `
                <div class="space-y-4">
                    <div class="text-center pb-4 border-b border-slate-600/50">
                        <div class="w-14 h-14 bg-gradient-to-br from-primary/30 to-cyan-500/20 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-user text-2xl text-primary"></i>
                        </div>
                        <div class="text-lg font-semibold text-white">${props.employee_name}</div>
                        <div class="text-sm text-slate-400">${props.position} • ${props.department}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-slate-700/30 rounded-lg p-3 text-center">
                            <div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Leave Type</div>
                            <div class="text-sm font-medium text-primary">${props.display_name}</div>
                        </div>
                        <div class="bg-slate-700/30 rounded-lg p-3 text-center">
                            <div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Duration</div>
                            <div class="text-sm font-semibold text-white">${props.days_approved} day${props.days_approved !== 1 ? 's' : ''}</div>
                        </div>
                        <div class="bg-slate-700/30 rounded-lg p-3 text-center">
                            <div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Pay Status</div>
                            <div class="text-sm font-medium ${payStatusDisplay === 'With Pay' ? 'text-green-400' : 'text-yellow-400'}">${payStatusDisplay}</div>
                        </div>
                        <div class="bg-slate-700/30 rounded-lg p-3 text-center">
                            <div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Date</div>
                            <div class="text-sm font-medium text-white">${info.event.start.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>
                        </div>
                    </div>
                </div>
            `;
            showStyledAlert(leaveHtml, 'info', 'Leave Details', true);
        }
    });
    
    calendar.render();
});
</script>
<script src="../../../../assets/js/modal-alert.js"></script>
