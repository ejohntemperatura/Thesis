<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../config/leave_types.php';
require_once '../../../../config/holidays.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

// Set page title
$page_title = "Leave Calendar";

// Include user header
include '../../../../includes/user_header.php';
?>
<link href='../../../../assets/libs/fullcalendar/css/main.min.css' rel='stylesheet' />

<!-- Page Header -->
<h1 class="elms-h1" style="margin-bottom: 0.5rem; display: flex; align-items: center;">
    <i class="fas fa-calendar" style="color: #0891b2; margin-right: 0.75rem;"></i>Leave Calendar
</h1>
<p class="elms-text-muted" style="margin-bottom: 2rem;">View all your leave requests in calendar format</p>

<!-- Use shared calendar component -->
                <?php 
                // Create a custom calendar component for users (only approved leaves)
                $stmt = $pdo->prepare("
                    SELECT 
                        lr.*,
                        CASE 
                            WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 
                            THEN lr.approved_days
                            ELSE lr.days_requested
                        END as actual_days_approved
                    FROM leave_requests lr 
                    WHERE lr.employee_id = ? 
                    AND lr.status = 'approved'
                    ORDER BY lr.start_date ASC
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $leave_requests = $stmt->fetchAll();
                
                // Get leave types configuration
                $leaveTypes = getLeaveTypes();
                ?>

                <!-- Calendar Container -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-calendar text-primary mr-3"></i>Your Leave Calendar
                        </h3>
                        <p class="text-slate-400 text-sm mt-1">Your approved leave requests displayed in calendar format</p>
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
    
    <script src="../../../../assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src='../../../../assets/libs/fullcalendar/js/main.min.js'></script>
    
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

    /* Holiday Events - Option C: transparent, high contrast (strong specificity) */
    .holiday-regular, .holiday-special,
    .holiday-regular.fc-h-event, .holiday-special.fc-h-event {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
    }
    .holiday-regular .fc-event-main, .holiday-special .fc-event-main { background: transparent !important; }
    .holiday-regular { color: #ffffff !important; font-weight: 800 !important; text-decoration: underline !important; text-decoration-color: rgba(255,255,255,0.6) !important; text-underline-offset: 2px !important; }
    .holiday-special { color: #ffffff !important; font-weight: 800 !important; }

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
                    $leaveDisplayName = getLeaveTypeDisplayName($request['leave_type'], $request['original_leave_type'] ?? null, $leaveTypes);
                    
                    // For without pay leaves, use original leave type color if available
                    $colorClass = 'leave-' . $request['leave_type'];
                    if ($request['leave_type'] === 'without_pay' && !empty($request['original_leave_type'])) {
                        $colorClass = 'leave-' . $request['original_leave_type'];
                    }
                    
                    // Collect all weekday dates and group consecutive weekdays
                    $start = new DateTime($request['start_date']);
                    $daysToCount = $request['actual_days_approved'];
                    $weekdaysCounted = 0;
                    $current = clone $start;
                    $weekdayGroups = [];
                    $currentGroup = null;
                    
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
                    title: '<?php echo addslashes($leaveDisplayName); ?> (<?php echo $request['actual_days_approved']; ?> day<?php echo $request['actual_days_approved'] != 1 ? 's' : ''; ?>)',
                    start: '<?php echo $group['start']; ?>',
                    end: '<?php echo $groupEnd->format('Y-m-d'); ?>',
                    allDay: true,
                    className: '<?php echo $colorClass; ?>',
                    display: 'block',
                    extendedProps: {
                        leave_type: '<?php echo $request['leave_type']; ?>',
                        status: '<?php echo $request['status']; ?>',
                        days_approved: <?php echo $request['actual_days_approved']; ?>,
                        days_requested: <?php echo $request['days_requested']; ?>,
                        reason: '<?php echo addslashes($request['reason']); ?>',
                        display_name: '<?php echo addslashes($leaveDisplayName); ?>'
                    }
                },
                <?php endforeach; ?>
                <?php endforeach; ?>
                <?php 
                    // Holidays for previous, current, next year
                    $years = [date('Y') - 1, date('Y'), date('Y') + 1];
                    foreach ($years as $y):
                        $hols = getHolidays((int)$y);
                        foreach ($hols as $hDate => $hInfo):
                            $hTitle = is_array($hInfo) ? ($hInfo['title'] ?? 'Holiday') : $hInfo;
                            $hType = is_array($hInfo) ? ($hInfo['type'] ?? 'regular') : 'regular';
                            $hEnd = (new DateTime($hDate))->modify('+1 day')->format('Y-m-d');
                            $hClass = $hType === 'special' ? 'holiday-special' : 'holiday-regular';
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
                    title: '<?php echo addslashes(($hType === 'special' ? '⭐ ' : '') . $hTitle); ?>',
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
                    alert(`Holiday: ${info.event.title}\nType: ${typeLabel}\nDate: ${info.event.start.toLocaleDateString()}`);
                    return;
                }
                const message = `
Leave Details:
Type: ${props.display_name}
Status: ${props.status}
Days Approved: ${props.days_approved}
Days Requested: ${props.days_requested}
Reason: ${props.reason}
Date: ${info.event.start.toLocaleDateString()}
                `;
                alert(message);
            }
        });
        
        calendar.render();
    });
    </script>
</body>
</html>
