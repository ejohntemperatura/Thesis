<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../config/leave_types.php';
require_once '../../../../app/core/services/EnhancedLeaveAlertService.php';

// Clear insufficient credits session variables if requested
if (isset($_GET['clear_insufficient_credits']) && $_GET['clear_insufficient_credits'] == '1') {
    unset($_SESSION['insufficient_credits_data']);
    unset($_SESSION['show_insufficient_credits_popup']);
    
    // If user wants to proceed with without pay, submit the form
    if (isset($_GET['proceed_without_pay']) && $_GET['proceed_without_pay'] == '1') {
        // Restore the form data and submit it
        if (isset($_SESSION['temp_insufficient_credits_data'])) {
            $formData = $_SESSION['temp_insufficient_credits_data'];
            
            // Debug: Log what we're processing
            error_log("Processing without pay request with data: " . json_encode($formData));
            
            // Redirect to dashboard with processing flag to show popup
            $_SESSION['show_processing_popup'] = true;
            $_SESSION['temp_insufficient_credits_data'] = $formData; // Keep the data for processing
            header('Location: dashboard.php');
            exit();
        } else {
            // If no form data found, redirect to dashboard with error
            error_log("No temp_insufficient_credits_data found in session");
            $_SESSION['error'] = "Unable to process without pay request. Please try submitting again.";
            header('Location: dashboard.php');
            exit();
        }
    }
    
    // Redirect to remove the parameter from URL
    header('Location: dashboard.php');
    exit();
}

// Clear last submission tracking after 30 seconds to allow legitimate resubmissions
if (isset($_SESSION['last_submission_time']) && (time() - $_SESSION['last_submission_time']) > 30) {
    unset($_SESSION['last_submission']);
    unset($_SESSION['last_submission_time']);
}


$leaveTypes = getLeaveTypes();
$alertService = new EnhancedLeaveAlertService($pdo);

// Auto-process emails when internet is available
require_once '../../../../app/core/services/auto_email_processor.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

// Check if employee exists
if (!$employee) {
    // Clear invalid session and redirect to login
    session_destroy();
    $_SESSION['error'] = 'Your session has expired or is invalid. Please log in again.';
    header('Location: ../../../../auth/views/login.php');
    exit();
}

// Get today's DTR record
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM dtr WHERE user_id = ? AND date = ?");
$stmt->execute([$_SESSION['user_id'], $today]);
$today_record = $stmt->fetch();

// Get user's leave alerts
$userAlerts = [];
try {
    $currentYear = date('Y');
    $alerts = $alertService->getUrgentAlerts(50);
    if (isset($alerts[$_SESSION['user_id']])) {
        $userAlerts = $alerts[$_SESSION['user_id']];
    }
} catch (Exception $e) {
    error_log("Error fetching user alerts: " . $e->getMessage());
}

// Handle time out only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $formatted_time = $current_time->format('Y-m-d H:i:s');
    $current_hour = (int)$current_time->format('H');

    if ($_POST['action'] === 'time_out') {
        if ($today_record && $today_record['morning_time_in'] && !$today_record['morning_time_out']) {
            // Morning time out
            $stmt = $pdo->prepare("UPDATE dtr SET morning_time_out = ? WHERE user_id = ? AND date = CURDATE()");
            if ($stmt->execute([$formatted_time, $_SESSION['user_id']])) {
                $_SESSION['message'] = "Time Out recorded successfully at " . $current_time->format('h:i A');
                unset($_SESSION['logged_in_this_session']); // Clear session flag after time out
            }
        } else if ($today_record && $today_record['afternoon_time_in'] && !$today_record['afternoon_time_out']) {
            // Afternoon time out
            $stmt = $pdo->prepare("UPDATE dtr SET afternoon_time_out = ? WHERE user_id = ? AND date = CURDATE()");
            if ($stmt->execute([$formatted_time, $_SESSION['user_id']])) {
                $_SESSION['message'] = "Afternoon Time Out recorded successfully at " . $current_time->format('h:i A');
                unset($_SESSION['logged_in_this_session']); // Clear session flag after time out
            }
        } else {
            $_SESSION['error'] = "Invalid time out request. You need to time in first from the DTR page.";
        }
    }
    header('Location: dashboard.php'); // Redirect back to dashboard to refresh status
    exit();
}

// Get dashboard statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pending_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'approved'");
$stmt->execute([$_SESSION['user_id']]);
$approved_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'rejected'");
$stmt->execute([$_SESSION['user_id']]);
$rejected_count = $stmt->fetchColumn();

// Get total available leave credits from employees table
// Most systems store leave balance in the employees table
$total_credits = $employee['vacation_leave'] ?? 15; // Default to 15 if not set

// Fetch user's leave requests with approved days calculation
$stmt = $pdo->prepare("
    SELECT 
        lr.*, lr.late_justification, e.service_credit_balance AS sc_balance,
        CASE 
            WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 
            THEN lr.approved_days
            ELSE lr.days_requested
        END as actual_days_approved
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id
    WHERE lr.employee_id = ? 
    ORDER BY lr.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$leave_requests = $stmt->fetchAll();

// Debug: Log leave requests fetch
error_log("Dashboard - Fetched " . count($leave_requests) . " leave requests for user " . $_SESSION['user_id']);

// Set page title
$page_title = "Leave Application";

// Include user header
include '../../../../includes/user_header.php';
?>
<link href='../../../../assets/libs/fullcalendar/css/main.min.css' rel='stylesheet' />
<style>
/* Leave Calendar Picker Styles - Floating Popup */
.calendar-picker-wrapper {
    position: relative;
}
.calendar-trigger-btn {
    width: 100%;
    background: rgba(51, 65, 85, 0.8);
    border: 1px solid rgba(71, 85, 105, 0.5);
    border-radius: 12px;
    padding: 12px 16px;
    color: #e2e8f0;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.2s;
}
.calendar-trigger-btn:hover {
    background: rgba(51, 65, 85, 1);
    border-color: rgba(59, 130, 246, 0.5);
}
.calendar-trigger-btn .trigger-text {
    display: flex;
    align-items: center;
    gap: 8px;
}
.calendar-trigger-btn .trigger-icon {
    color: #3b82f6;
}
.leave-calendar-picker {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 100;
    background: rgba(30, 41, 59, 0.98);
    border: 1px solid rgba(71, 85, 105, 0.5);
    border-radius: 12px;
    padding: 16px;
    user-select: none;
    margin-top: 4px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
    display: none;
}
.leave-calendar-picker.show {
    display: block;
    animation: fadeInDown 0.2s ease-out;
}
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.leave-calendar-picker .calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(71, 85, 105, 0.3);
}
.leave-calendar-picker .calendar-header button {
    background: rgba(59, 130, 246, 0.2);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #93c5fd;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}
.leave-calendar-picker .calendar-header button:hover {
    background: rgba(59, 130, 246, 0.4);
}
.leave-calendar-picker .calendar-header .month-year {
    color: white;
    font-weight: 600;
    font-size: 1rem;
}
.leave-calendar-picker .calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}
.leave-calendar-picker .day-header {
    text-align: center;
    color: #94a3b8;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 6px 0;
}
.leave-calendar-picker .day-cell {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
    color: #e2e8f0;
    transition: all 0.15s;
    min-height: 32px;
}
.leave-calendar-picker .day-cell:hover:not(.disabled):not(.weekend) {
    background: rgba(59, 130, 246, 0.3);
}
.leave-calendar-picker .day-cell.selected {
    background: #3b82f6;
    color: white;
    font-weight: 600;
}
.leave-calendar-picker .day-cell.in-range {
    background: rgba(59, 130, 246, 0.2);
}
.leave-calendar-picker .day-cell.disabled {
    color: #475569;
    cursor: not-allowed;
}
.leave-calendar-picker .day-cell.weekend {
    color: #64748b;
    cursor: not-allowed;
    background: rgba(100, 116, 139, 0.1);
}
.leave-calendar-picker .day-cell.today {
    border: 2px solid #3b82f6;
}
.leave-calendar-picker .day-cell.other-month {
    color: #475569;
}
.leave-calendar-picker .day-cell.past-date {
    color: #64748b;
    text-decoration: line-through;
}
/* Late leave calendar allows ALL dates (past, present, future) - no special styling needed */
.leave-calendar-picker.late-mode .day-cell {
    color: #e2e8f0;
    cursor: pointer;
}
}
.leave-calendar-picker .calendar-footer {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid rgba(71, 85, 105, 0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.leave-calendar-picker .calendar-footer button {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
}
.leave-calendar-picker .calendar-footer .btn-clear {
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}
.leave-calendar-picker .calendar-footer .btn-clear:hover {
    background: rgba(239, 68, 68, 0.4);
}
.leave-calendar-picker .calendar-footer .btn-done {
    background: rgba(34, 197, 94, 0.2);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}
.leave-calendar-picker .calendar-footer .btn-done:hover {
    background: rgba(34, 197, 94, 0.4);
}
.selected-dates-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}
.selected-date-chip {
    background: rgba(59, 130, 246, 0.2);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #93c5fd;
    padding: 4px 10px;
    border-radius: 16px;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.selected-date-chip .remove-date {
    cursor: pointer;
    opacity: 0.7;
}
.selected-date-chip .remove-date:hover {
    opacity: 1;
}
</style>

    <!-- Apply Leave Modal -->
    <div id="applyLeaveModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 elms-modal-overlay">
        <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 w-full max-w-2xl max-h-[90vh] overflow-y-auto elms-modal">
            <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-white flex items-center">
                        <i class="fas fa-calendar-plus mr-3 text-blue-500"></i>
                        Apply for Leave
                    </h3>
                    <button onclick="closeApplyLeaveModal()" class="text-slate-400 hover:text-white transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form id="applyLeaveForm" method="POST" action="submit_leave.php" enctype="multipart/form-data" class="space-y-6" onsubmit="showProcessingModal(event)">
                    <!-- Employee Information -->
                    <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                        <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-user-circle text-blue-500 mr-3"></i>
                            Employee Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Employee Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['name']); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Position</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['position']); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Department</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Date of Filing</label>
                                <input type="text" value="<?php echo date('F j, Y'); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                        </div>
                    </div>

                    
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="modal_leave_type" class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-calendar-check mr-2"></i>Leave Type
                            </label>
                            <select id="modal_leave_type" name="leave_type" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" onchange="toggleModalConditionalFields()">
                                <option value="">Select Leave Type</option>
                                <?php foreach ($leaveTypes as $type => $config): 
                                    // Gender restrictions
                                    if (isset($config['gender_restricted'])) {
                                        if ($config['gender_restricted'] === 'female' && ($employee['gender'] ?? 'male') !== 'female') continue;
                                        if ($config['gender_restricted'] === 'male' && ($employee['gender'] ?? 'male') !== 'male') continue;
                                    }
                                    // Solo parent visibility
                                    if ($type === 'solo_parent' && (int)($employee['is_solo_parent'] ?? 0) !== 1) continue;
                                    // Credit availability: if requires credits, only show when employee has > 0 balance
                                    // Exception: always_show flag bypasses credit check (for emergency leaves)
                                    $show = true;
                                    if (!empty($config['requires_credits']) && empty($config['always_show'])) {
                                        $creditField = $config['credit_field'] ?? null;
                                        if ($creditField && isset($employee[$creditField])) {
                                            $show = ((float)$employee[$creditField]) > 0;
                                        }
                                    }
                                    if (!$show) continue; 
                                    $formalName = $config['formal_name'] ?? $config['name'];
                                ?>
                                    <option value="<?php echo $type; ?>"><?php echo htmlspecialchars($formalName); ?></option>
                                <?php endforeach; ?>
                                <option value="other">Other (Terminal Leave / Monetization)</option>
                            </select>
                        </div>

                        <!-- Other Purpose Dropdown -->
                        <div id="modalOtherPurposeField" class="hidden">
                            <label for="modal_other_purpose" class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-list-alt mr-2"></i>Other Purpose
                            </label>
                            <select id="modal_other_purpose" name="other_purpose" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select Purpose</option>
                                <?php 
                                $otherPurposes = getOtherPurposeOptions();
                                foreach ($otherPurposes as $purposeKey => $purposeConfig): 
                                ?>
                                    <option value="<?php echo $purposeKey; ?>"><?php echo htmlspecialchars($purposeConfig['formal_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Calendar Date Picker (Hidden for Other Purpose) -->
                    <div id="modalCalendarPicker">
                        <label class="block text-sm font-semibold text-slate-300 mb-2">
                            <i class="fas fa-calendar-alt mr-2"></i>Select Leave Days
                        </label>
                        <div class="calendar-picker-wrapper">
                            <button type="button" class="calendar-trigger-btn" onclick="leaveCalendar.toggle()">
                                <span class="trigger-text">
                                    <i class="fas fa-calendar-alt trigger-icon"></i>
                                    <span id="selectedDatesDisplay">Click to select dates</span>
                                </span>
                                <span id="modal_total_days" class="text-blue-400 font-semibold">0 days</span>
                            </button>
                            <div id="leaveCalendarPicker" class="leave-calendar-picker"></div>
                        </div>
                        <input type="hidden" id="modal_start_date" name="start_date">
                        <input type="hidden" id="modal_end_date" name="end_date">
                        <input type="hidden" id="modal_selected_dates" name="selected_dates">
                        <input type="hidden" id="modal_days_count" name="days_count">
                    </div>

                    <!-- Working Days Input (For Other Purpose Only) -->
                    <div id="modalWorkingDaysField" class="hidden">
                        <label for="modal_working_days" class="block text-sm font-semibold text-slate-300 mb-2">
                            <i class="fas fa-calculator mr-2"></i>Number of Working Days Applied For
                        </label>
                        <input type="number" id="modal_working_days" name="working_days_applied" min="1" step="1" placeholder="Enter number of working days" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-slate-400 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Enter the total number of working days you are applying for
                        </p>
                    </div>
                    
                    <!-- Maternity/Paternity Supporting Document (Required) - Regular Application -->
                    <div id="modalMatPatFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                        <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-file-shield text-blue-500 mr-3"></i>
                            Supporting Document (Required)
                        </h4>
                        <div class="space-y-2">
                            <input type="file" id="modal_matpat_file" name="medical_certificate" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-500 file:text-white hover:file:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <p class="text-xs text-slate-400">
                                <i class="fas fa-info-circle mr-1"></i>
                                Accepted: PDF, JPG, JPEG, PNG, DOC, DOCX (Max 10MB)
                            </p>
                        </div>
                    </div>
                    
                    <!-- Conditional Fields for Apply Leave Modal -->
                    <div id="modalConditionalFields" class="hidden">
                        <!-- Vacation Leave Fields -->
                        <div id="modalVacationFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-map-marker-alt text-blue-500 mr-3"></i>
                                Vacation Location Details
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_vacation_location" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-globe mr-2"></i>Location Type
                                    </label>
                                    <select id="modal_vacation_location" name="location_type" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select location type</option>
                                        <option value="within_philippines">Within Philippines</option>
                                        <option value="outside_philippines">Outside Philippines</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="modal_vacation_address" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-map-pin mr-2"></i>Specific Address
                                    </label>
                                    <input type="text" id="modal_vacation_address" name="location_specify" placeholder="Enter the specific address where you will spend your vacation..." class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Sick Leave Fields -->
                        <div id="modalSickFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-user-md text-blue-500 mr-3"></i>
                                Medical Information
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_medical_certificate" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-file-medical mr-2"></i>Medical Condition
                                    </label>
                                    <select id="modal_medical_certificate" name="medical_condition" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select condition</option>
                                        <option value="in_hospital">In hospital (specify illness)</option>
                                        <option value="out_patient">Out patient (Specify illness)</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="modal_illness_description" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-stethoscope mr-2"></i>Specify Illness
                                    </label>
                                    <input type="text" id="modal_illness_description" name="illness_specify" placeholder="Specify your illness or medical condition..." class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label for="modal_sick_medical_cert_file" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-file-upload mr-2"></i>Medical Certificate (Optional)
                                    </label>
                                    <input type="file" id="modal_sick_medical_cert_file" name="sick_medical_certificate" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-500 file:text-white hover:file:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <p class="text-xs text-slate-400 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Supported formats: PDF, JPG, JPEG, PNG, DOC, DOCX (Max 10MB)
                                    </p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Note: If 3 days or more should required medical certificate
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Special Leave Benefits for Women Fields -->
                        <div id="modalSpecialWomenFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-female text-blue-500 mr-3"></i>
                                Special Leave Benefits for Women
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_special_women_condition" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-stethoscope mr-2"></i>Specify Illness
                                    </label>
                                    <input type="text" id="modal_special_women_condition" name="special_women_condition" placeholder="Specify your illness..." class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Study Leave Fields -->
                        <div id="modalStudyFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-graduation-cap text-blue-500 mr-3"></i>
                                Study Information
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_course_program" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-book mr-2"></i>Course/Program Type
                                    </label>
                                    <select id="modal_course_program" name="study_type" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select study type</option>
                                        <option value="masters_degree">Master's degree</option>
                                        <option value="bar_board">BAR/Board Examination Review</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Commutation Field -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2">
                            <i class="fas fa-money-bill-wave mr-2"></i>Commutation
                        </label>
                        <div class="bg-slate-700/30 rounded-xl p-4 border border-slate-600/50">
                            <div class="space-y-3">
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="radio" name="commutation" value="not_requested" checked class="w-4 h-4 text-blue-600 bg-slate-700 border-slate-600 focus:ring-blue-500 focus:ring-2">
                                    <span class="text-white">Not Requested</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="radio" name="commutation" value="requested" class="w-4 h-4 text-blue-600 bg-slate-700 border-slate-600 focus:ring-blue-500 focus:ring-2">
                                    <span class="text-white">Requested</span>
                                </label>
                            </div>
                            <p class="text-xs text-slate-400 mt-3">
                                <i class="fas fa-info-circle mr-1"></i>
                                Commutation refers to the monetization of leave credits
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-4 justify-end pt-6">
                        <button type="button" onclick="closeApplyLeaveModal()" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Leave Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Late Application Modal -->
    <div id="lateApplicationModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 elms-modal-overlay">
        <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 w-full max-w-2xl max-h-[90vh] overflow-y-auto elms-modal">
            <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-white flex items-center">
                        <i class="fas fa-exclamation-triangle mr-3 text-gray-500"></i>
                        Late Leave Application
                    </h3>
                    <button onclick="closeLateApplicationModal()" class="text-slate-400 hover:text-white transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form id="lateApplicationForm" method="POST" action="late_leave_application.php" enctype="multipart/form-data" class="space-y-6" onsubmit="return showLateProcessingModal(document.getElementById('modal_late_leave_type').value)">
                    <!-- Employee Information -->
                    <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                        <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-user-circle text-gray-500 mr-3"></i>
                            Employee Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Employee Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['name']); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Position</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['position']); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Department</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Date of Filing</label>
                                <input type="text" value="<?php echo date('F j, Y'); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="modal_late_leave_type" class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-calendar-check mr-2"></i>Leave Type
                            </label>
                            <select id="modal_late_leave_type" name="leave_type" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent" onchange="toggleModalLateConditionalFields()">
                                <option value="">Select Leave Type</option>
                                <?php foreach ($leaveTypes as $type => $config): 
                                    // Gender restrictions
                                    if (isset($config['gender_restricted'])) {
                                        if ($config['gender_restricted'] === 'female' && ($employee['gender'] ?? 'male') !== 'female') continue;
                                        if ($config['gender_restricted'] === 'male' && ($employee['gender'] ?? 'male') !== 'male') continue;
                                    }
                                    // Solo parent visibility
                                    if ($type === 'solo_parent' && (int)($employee['is_solo_parent'] ?? 0) !== 1) continue;
                                    // Credit availability: if requires credits, only show when employee has > 0 balance
                                    // Exception: always_show flag bypasses credit check (for emergency leaves)
                                    $showLate = true;
                                    if (!empty($config['requires_credits']) && empty($config['always_show'])) {
                                        $creditField = $config['credit_field'] ?? null;
                                        if ($creditField && isset($employee[$creditField])) {
                                            $showLate = ((float)$employee[$creditField]) > 0;
                                        }
                                    }
                                    if (!$showLate) continue; 
                                    $formalName = $config['formal_name'] ?? $config['name'];
                                ?>
                                    <option value="<?php echo $type; ?>"><?php echo htmlspecialchars($formalName); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Calendar Date Picker for Late Leave -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2">
                            <i class="fas fa-calendar-alt mr-2"></i>Select Leave Days
                        </label>
                        <div class="calendar-picker-wrapper">
                            <button type="button" class="calendar-trigger-btn" onclick="lateLeaveCalendar.toggle()">
                                <span class="trigger-text">
                                    <i class="fas fa-calendar-alt trigger-icon"></i>
                                    <span id="lateSelectedDatesDisplay">Click to select dates</span>
                                </span>
                                <span id="modal_late_total_days" class="text-gray-400 font-semibold">0 days</span>
                            </button>
                            <div id="lateLeaveCalendarPicker" class="leave-calendar-picker"></div>
                        </div>
                        <input type="hidden" id="modal_late_start_date" name="start_date" required>
                        <input type="hidden" id="modal_late_end_date" name="end_date" required>
                        <input type="hidden" id="modal_late_selected_dates" name="selected_dates">
                        <input type="hidden" id="modal_late_days_count" name="days_count">
                    </div>

                    <!-- Maternity/Paternity Supporting Document (Required) for Late Application -->
                    <div id="modalLateMatPatFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                        <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-file-shield text-gray-500 mr-3"></i>
                            Supporting Document (Required)
                        </h4>
                        <div class="space-y-2">
                            <input type="file" id="modal_late_matpat_file" name="medical_certificate" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-500 file:text-white hover:file:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                            <p class="text-xs text-slate-400">
                                <i class="fas fa-info-circle mr-1"></i>
                                Accepted: PDF, JPG, JPEG, PNG, DOC, DOCX (Max 10MB)
                            </p>
                        </div>
                    </div>
                    
                    <!-- Conditional Fields for Late Application Modal -->
                    <div id="modalLateConditionalFields" class="hidden">
                        <!-- Vacation Leave Fields -->
                        <div id="modalLateVacationFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-map-marker-alt text-gray-500 mr-3"></i>
                                Vacation Location Details
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_late_vacation_location" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-globe mr-2"></i>Location Type
                                    </label>
                                    <select id="modal_late_vacation_location" name="location_type" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                                        <option value="">Select location type</option>
                                        <option value="within_philippines">Within Philippines</option>
                                        <option value="outside_philippines">Outside Philippines</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="modal_late_vacation_address" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-map-pin mr-2"></i>Specific Address
                                    </label>
                                    <input type="text" id="modal_late_vacation_address" name="location_specify" placeholder="Enter the specific address where you will spend your vacation..." class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Sick Leave Fields -->
                        <div id="modalLateSickFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-user-md text-gray-500 mr-3"></i>
                                Medical Information
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_late_medical_certificate" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-file-medical mr-2"></i>Medical Condition
                                    </label>
                                    <select id="modal_late_medical_certificate" name="medical_condition" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                                        <option value="">Select condition</option>
                                        <option value="in_hospital">In hospital (specify illness)</option>
                                        <option value="out_patient">Out patient (Specify illness)</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="modal_late_illness_description" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-stethoscope mr-2"></i>Specify Illness
                                    </label>
                                    <input type="text" id="modal_late_illness_description" name="illness_specify" placeholder="Specify your illness or medical condition..." class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label for="modal_late_medical_cert_file" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-file-upload mr-2"></i>Medical Certificate (Optional)
                                    </label>
                                    <input type="file" id="modal_late_medical_cert_file" name="medical_certificate" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-500 file:text-white hover:file:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                                    <p class="text-xs text-slate-400 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Supported formats: PDF, JPG, JPEG, PNG, DOC, DOCX (Max 10MB)
                                    </p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Note: If 3 days or more should required medical certificate
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Special Leave Benefits for Women Fields -->
                        <div id="modalLateSpecialWomenFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-female text-gray-500 mr-3"></i>
                                Special Leave Benefits for Women
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_late_special_women_condition" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-stethoscope mr-2"></i>Specify Illness
                                    </label>
                                    <input type="text" id="modal_late_special_women_condition" name="special_women_condition" placeholder="Specify your illness..." class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Study Leave Fields -->
                        <div id="modalLateStudyFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-graduation-cap text-gray-500 mr-3"></i>
                                Study Information
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_late_course_program" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-book mr-2"></i>Course/Program Type
                                    </label>
                                    <select id="modal_late_course_program" name="study_type" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                                        <option value="">Select study type</option>
                                        <option value="masters_degree">Master's degree</option>
                                        <option value="bar_board">BAR/Board Examination Review</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Commutation Field -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2">
                            <i class="fas fa-money-bill-wave mr-2"></i>Commutation
                        </label>
                        <div class="bg-slate-700/30 rounded-xl p-4 border border-slate-600/50">
                            <div class="space-y-3">
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="radio" name="commutation" value="not_requested" checked class="w-4 h-4 text-blue-600 bg-slate-700 border-slate-600 focus:ring-blue-500 focus:ring-2">
                                    <span class="text-white">Not Requested</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="radio" name="commutation" value="requested" class="w-4 h-4 text-blue-600 bg-slate-700 border-slate-600 focus:ring-blue-500 focus:ring-2">
                                    <span class="text-white">Requested</span>
                                </label>
                            </div>
                            <p class="text-xs text-slate-400 mt-3">
                                <i class="fas fa-info-circle mr-1"></i>
                                Commutation refers to the monetization of leave credits
                            </p>
                        </div>
                    </div>

                    <div>
                        <label for="modal_late_justification" class="block text-sm font-semibold text-slate-300 mb-2">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Late Justification
                        </label>
                        <textarea id="modal_late_justification" name="late_justification" rows="4" placeholder="Please explain why you are submitting this leave application late..." required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent" style="pointer-events: auto;"></textarea>
                    </div>
                    
                    <div class="flex gap-4 justify-end pt-6">
                        <button type="button" onclick="closeLateApplicationModal()" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Late Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Insufficient Credits Popup -->
    <div id="insufficientCreditsModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 w-full max-w-md">
            <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-white flex items-center">
                        <i class="fas fa-exclamation-triangle mr-3 text-orange-500"></i>
                        Insufficient Leave Credits
                    </h3>
                    <button onclick="closeInsufficientCreditsModal()" class="text-slate-400 hover:text-white transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="mb-6">
                    <div class="bg-orange-500/20 border border-orange-500/30 rounded-lg p-4 mb-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-orange-400 mr-3"></i>
                            <span class="text-orange-400 font-medium">You don't have enough leave credits for this request.</span>
                        </div>
                    </div>
                    <div id="creditMessage" class="text-slate-300 text-sm mb-4"></div>
                    <div class="bg-blue-500/20 border border-blue-500/30 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle text-blue-400 mr-3"></i>
                            <span class="text-blue-400">This leave will be processed as <strong>Without Pay Leave</strong>.</span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-4 justify-end">
                    <button type="button" onclick="closeInsufficientCreditsModal()" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                        Cancel
                    </button>
                    <button type="button" onclick="proceedWithWithoutPay()" class="bg-orange-600 hover:bg-orange-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl">
                        <i class="fas fa-check mr-2"></i>Proceed Without Pay
                    </button>
                </div>
            </div>
        </div>
    </div>

<!-- Page Content -->
<div class="max-w-7xl mx-auto">
                <!-- Error Messages Only (Success messages are shown in modal) -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

<!-- Welcome Section -->
<div style="margin-bottom: 2rem;">
    <div style="display: flex; align-items: flex-start; justify-content: space-between;">
        <div>
            <h1 class="elms-h1" style="margin-bottom: 0.5rem;">Welcome back, <?php echo htmlspecialchars($employee['name']); ?>!</h1>
            <p class="elms-text-muted">Here's what's happening with your leave requests today.</p>
        </div>
        <div style="text-align: right;">
            <div style="display: inline-flex; align-items: baseline; gap: 0.5rem; justify-content: flex-end; margin-bottom: 0.25rem;">
                <span id="clockHM" style="color: white; font-size: 1.75rem; font-weight: 700; font-family: 'Courier New', monospace;">--:--</span>
                <span id="clockSec" style="color: #cbd5e1; font-size: 1rem; font-family: 'Courier New', monospace;">--</span>
                <span id="clockAmPm" style="color: #cbd5e1; font-size: 0.875rem; font-family: 'Courier New', monospace;">--</span>
            </div>
            <div style="color: #94a3b8; font-size: 0.75rem;">Today is</div>
            <div id="clockDateChip" style="margin-top: 0.25rem; display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; border: 1px solid rgba(51,65,85,0.6); background: rgba(51,65,85,0.4); color: #e5e7eb; font-size: 0.875rem;">Loading...</div>
        </div>
    </div>
    
    <script>
        // Update employee dashboard time (Split Badge clock)
        function updateEmployeeDashboardTime() {
            const now = new Date();
            let h = now.getHours();
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12; h = h ? h : 12;
            const hm = `${String(h).padStart(2, '0')}:${m}`;
            const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            const dateString = `${days[now.getDay()]}, ${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
            const elHM = document.getElementById('clockHM');
            const elS = document.getElementById('clockSec');
            const elAP = document.getElementById('clockAmPm');
            const elDate = document.getElementById('clockDateChip');
            if (elHM) elHM.textContent = hm;
            if (elS) elS.textContent = s;
            if (elAP) elAP.textContent = ampm;
            if (elDate) elDate.textContent = dateString;
        }
        
        // Update time immediately and then every second
        updateEmployeeDashboardTime();
        setInterval(updateEmployeeDashboardTime, 1000);
    </script>
</div>

                <!-- Messages -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="mb-6 p-4 bg-green-500/20 border border-green-500/30 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-3"></i>
                            <span class="text-green-400"><?php echo $_SESSION['message']; ?></span>
                        </div>
                    </div>
                    <?php
                    unset($_SESSION['message']);
                    ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['warning'])): ?>
                    <div class="mb-6 p-4 bg-orange-500/20 border border-orange-500/30 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-orange-400 mr-3"></i>
                                <span class="text-orange-400"><?php echo $_SESSION['warning']; ?></span>
                            </div>
                            <?php if (isset($_SESSION['late_application_data'])): ?>
                            <button onclick="openLateApplicationModal(); populateLateApplicationForm();" class="ml-4 bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                <i class="fas fa-clock mr-2"></i>Use Late Application
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    unset($_SESSION['warning']);
                    ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['info'])): ?>
                    <div class="mb-6 p-4 bg-blue-500/20 border border-blue-500/30 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-400 mr-3"></i>
                                <span class="text-blue-400"><?php echo $_SESSION['info']; ?></span>
                            </div>
                            <?php if (isset($_SESSION['regular_application_data'])): ?>
                            <button onclick="openApplyLeaveModal(); populateRegularApplicationForm();" class="ml-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                <i class="fas fa-calendar-plus mr-2"></i>Use Regular Application
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    unset($_SESSION['info']);
                    ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="mb-6 p-4 bg-red-500/20 border border-red-500/30 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-400 mr-3"></i>
                            <span class="text-red-400"><?php echo $_SESSION['error']; ?></span>
                        </div>
                    </div>
                    <?php
                    unset($_SESSION['error']);
                    ?>
                <?php endif; ?>

<!-- Quick Action Cards -->
<div class="elms-grid elms-grid-1 elms-grid-md-2 elms-grid-lg-4" style="margin-bottom: 2rem;">
    <!-- Apply for Leave Card -->
    <button onclick="openApplyLeaveModal()" class="elms-stat-card" style="border: none; cursor: pointer; text-align: left;">
        <div>
            <p class="elms-stat-label">Apply for Leave</p>
            <p class="elms-stat-value" style="font-size: 1rem; margin-top: 0.5rem;">📝</p>
            <p style="color: #60a5fa; font-size: 0.875rem; margin-top: 0.5rem;">
                <i class="fas fa-arrow-right"></i> Submit new request
            </p>
        </div>
        <div class="elms-stat-icon-container" style="background-color: #1e3a8a;">
            <i class="fas fa-calendar-plus elms-stat-icon" style="color: #60a5fa;"></i>
        </div>
    </button>
            
    <!-- Late Application Card -->
    <button onclick="openLateApplicationModal()" class="elms-stat-card" style="border: none; cursor: pointer; text-align: left;">
        <div>
            <p class="elms-stat-label">Late Application</p>
            <p class="elms-stat-value" style="font-size: 1rem; margin-top: 0.5rem;">⏰</p>
            <p style="color: #fb923c; font-size: 0.875rem; margin-top: 0.5rem;">
                <i class="fas fa-arrow-right"></i> Submit late request
            </p>
        </div>
        <div class="elms-stat-icon-container" style="background-color: #7c2d12;">
            <i class="fas fa-exclamation-triangle elms-stat-icon" style="color: #fb923c;"></i>
        </div>
    </button>
            
    <!-- Leave History Card -->
    <a href="leave_history.php" class="elms-stat-card" style="text-decoration: none;">
        <div>
            <p class="elms-stat-label">Leave History</p>
            <p class="elms-stat-value" style="font-size: 1rem; margin-top: 0.5rem;">📋</p>
            <p style="color: #60a5fa; font-size: 0.875rem; margin-top: 0.5rem;">
                <i class="fas fa-arrow-right"></i> View all requests
            </p>
        </div>
        <div class="elms-stat-icon-container" style="background-color: #1e3a8a;">
            <i class="fas fa-history elms-stat-icon" style="color: #60a5fa;"></i>
        </div>
    </a>
    
    <!-- Attendance Log Card -->
    <button onclick="openAttendanceModal()" class="elms-stat-card" style="border: none; cursor: pointer; text-align: left;">
        <div>
            <p class="elms-stat-label">Attendance Log</p>
            <p class="elms-stat-value" style="font-size: 1rem; margin-top: 0.5rem;">📊</p>
            <p style="color: #a78bfa; font-size: 0.875rem; margin-top: 0.5rem;">
                <i class="fas fa-arrow-right"></i> View attendance
            </p>
        </div>
        <div class="elms-stat-icon-container" style="background-color: #4c1d95;">
            <i class="fas fa-clipboard-list elms-stat-icon" style="color: #a78bfa;"></i>
        </div>
    </button>
</div>

                <!-- Recent Leave Requests -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-8 mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-calendar-alt text-blue-500 mr-3"></i>
                            Recent Leave Requests
                        </h2>
                        <a href="leave_history.php" class="text-blue-400 hover:text-blue-300 text-sm font-medium flex items-center">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <?php if (empty($leave_requests)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-times text-4xl text-slate-500 mb-4"></i>
                            <p class="text-slate-400 text-lg">No leave requests yet</p>
                            <p class="text-slate-500 text-sm">Click "Apply for Leave" to submit your first request</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach (array_slice($leave_requests, 0, 5) as $request): ?>
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-12 h-12 bg-slate-600 rounded-full flex items-center justify-center">
                                                <i class="fas fa-calendar text-slate-300"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-lg font-semibold text-white">
                                                    <?php 
                                                    // Use the getLeaveTypeDisplayName function for consistent display with fallback
                                                    $__label = getLeaveTypeDisplayName($request['leave_type'] ?? '', $request['original_leave_type'] ?? null, $leaveTypes, $request['other_purpose'] ?? null);
                                                    if (!isset($__label) || trim($__label) === '') {
                                                        $base = $request['original_leave_type'] ?? ($request['leave_type'] ?? '');
                                                        $__label = getLeaveTypeDisplayName($base, null, $leaveTypes, $request['other_purpose'] ?? null);
                                                        if (!isset($__label) || trim($__label) === '') {
                                                            if (!empty($request['study_type'])) {
                                                                $__label = 'Study Leave (Without Pay)';
                                                            } elseif (!empty($request['medical_condition']) || !empty($request['illness_specify'])) {
                                                                $__label = 'Sick Leave (SL)';
                                                            } elseif (!empty($request['special_women_condition'])) {
                                                                $__label = 'Special Leave Benefits for Women';
                                                            } elseif (!empty($request['location_type'])) {
                                                                $__label = 'Vacation Leave (VL)';
                                                            } elseif (isset($request['sc_balance']) && (float)$request['sc_balance'] > 0) {
                                                                $__label = 'Service Credits';
                                                            } elseif (($request['pay_status'] ?? '') === 'without_pay' || ($request['leave_type'] ?? '') === 'without_pay') {
                                                                $__label = 'Without Pay Leave';
                                                            } else {
                                                                $__label = 'Service Credits';
                                                            }
                                                        }
                                                    }
                                                    echo $__label;
                                                    ?>
                                                </h3>
                                                <?php if ($request['leave_type'] === 'other'): ?>
                                                    <!-- For Terminal Leave / Monetization: Show only working days -->
                                                    <p class="text-slate-400 text-sm">
                                                        <span class="text-blue-400 font-semibold"><?php echo $request['working_days_applied'] ?? $request['days_requested']; ?> working day(s)</span> to convert to cash
                                                    </p>
                                                <?php else: ?>
                                                    <!-- For Regular Leave: Show date range -->
                                                    <p class="text-slate-400 text-sm">
                                                        <?php echo date('M j, Y', strtotime($request['start_date'])); ?> - 
                                                        <?php 
                                                        // Calculate correct end date based on approved days (excluding weekends)
                                                        if ($request['status'] === 'approved' && $request['actual_days_approved'] > 0) {
                                                            $start = new DateTime($request['start_date']);
                                                            $daysToCount = $request['actual_days_approved'];
                                                            $weekdaysCounted = 0;
                                                            $current = clone $start;
                                                            
                                                            $dayOfWeek = (int)$current->format('N');
                                                            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                                                                $weekdaysCounted++;
                                                            }
                                                            
                                                            while ($weekdaysCounted < $daysToCount) {
                                                                $current->modify('+1 day');
                                                                $dayOfWeek = (int)$current->format('N');
                                                                if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                                                                    $weekdaysCounted++;
                                                                }
                                                            }
                                                            
                                                            echo date('M j, Y', $current->getTimestamp());
                                                        } else {
                                                            echo date('M j, Y', strtotime($request['end_date']));
                                                        }
                                                        ?>
                                                        (<?php echo $request['days_requested']; ?> days requested)
                                                    </p>
                                                <?php endif; ?>
                                                <?php if ($request['status'] === 'approved' && $request['actual_days_approved'] != $request['days_requested']): ?>
                                                <p class="text-green-400 text-sm font-medium">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    <?php echo $request['actual_days_approved']; ?> days approved
                                                </p>
                                                <?php elseif ($request['status'] === 'approved'): ?>
                                                <p class="text-green-400 text-sm font-medium">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    Full approval
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                                <?php
                                                switch($request['status']) {
                                                    case 'approved':
                                                        echo 'bg-green-500/20 text-green-400 border border-green-500/30';
                                                        break;
                                                    case 'rejected':
                                                        echo 'bg-red-500/20 text-red-400 border border-red-500/30';
                                                        break;
                                                    case 'pending':
                                                        echo 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30';
                                                        break;
                                                    default:
                                                        echo 'bg-slate-500/20 text-slate-400 border border-slate-500/30';
                                                }
                                                ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                            <p class="text-slate-500 text-xs mt-1">
                                                <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>


</div>

<script>
        // Modal Functions
        function openApplyLeaveModal() {
            const modal = document.getElementById('applyLeaveModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            // Scroll modal to top to ensure it's visible
            modal.scrollTop = 0;
            // Don't auto-focus on any element to prevent scrolling
        }

        function closeApplyLeaveModal() {
            const modal = document.getElementById('applyLeaveModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            // Reset form
            document.getElementById('applyLeaveForm').reset();
        }

        function openLateApplicationModal() {
            const modal = document.getElementById('lateApplicationModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeLateApplicationModal() {
            const modal = document.getElementById('lateApplicationModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            // Reset form
            document.getElementById('lateApplicationForm').reset();
        }

        function openAttendanceModal() {
            const modal = document.getElementById('attendanceModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            loadAttendanceData();
        }

        function closeAttendanceModal() {
            const modal = document.getElementById('attendanceModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function loadAttendanceData() {
            const tbody = document.getElementById('attendanceTableBody');
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-sm"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>';
            
            fetch('dtr_status.php?ajax=1')
                .then(response => response.json())
                .then(data => {
                    if (data.records && data.records.length > 0) {
                        tbody.innerHTML = data.records.map(record => {
                            // Determine status badges
                            let statusBadges = [];
                            const hasOvertime = record.total_hours > 8;
                            const overtimeHours = hasOvertime ? (record.total_hours - 8).toFixed(1) : 0;
                            
                            if (record.has_late) {
                                statusBadges.push('<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/30"><i class="fas fa-clock mr-1"></i>Late</span>');
                            }
                            if (hasOvertime) {
                                statusBadges.push('<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30"><i class="fas fa-star mr-1"></i>OT +' + overtimeHours + 'h</span>');
                            }
                            if (!record.has_late && !hasOvertime) {
                                statusBadges.push('<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-500/20 text-green-400 border border-green-500/30"><i class="fas fa-check mr-1"></i>OK</span>');
                            }
                            
                            // Format time cells with late indicator
                            const morningInClass = record.morning_late ? 'text-red-400' : 'text-slate-300';
                            const afternoonInClass = record.afternoon_late ? 'text-red-400' : 'text-slate-300';
                            const morningInIcon = record.morning_late ? ' <i class="fas fa-exclamation-circle text-red-400" title="Late"></i>' : '';
                            const afternoonInIcon = record.afternoon_late ? ' <i class="fas fa-exclamation-circle text-red-400" title="Late"></i>' : '';
                            
                            return `
                            <tr class="hover:bg-slate-700/30 transition-colors ${record.has_late ? 'bg-red-500/5' : ''}">
                                <td class="px-3 py-2 text-xs text-slate-300">${record.date}</td>
                                <td class="px-3 py-2 text-xs ${morningInClass}">${record.morning_time_in || '-'}${morningInIcon}</td>
                                <td class="px-3 py-2 text-xs text-slate-300">${record.morning_time_out || '-'}</td>
                                <td class="px-3 py-2 text-xs ${afternoonInClass}">${record.afternoon_time_in || '-'}${afternoonInIcon}</td>
                                <td class="px-3 py-2 text-xs text-slate-300">${record.afternoon_time_out || '-'}</td>
                                <td class="px-3 py-2 text-xs font-semibold ${record.total_hours >= 8 ? 'text-green-400' : 'text-yellow-400'}">${record.total_hours}h</td>
                                <td class="px-3 py-2 text-xs">${statusBadges.join(' ')}</td>
                            </tr>
                        `}).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-6 text-slate-400 text-sm"><i class="fas fa-calendar-times text-xl mb-2"></i><br>No records found</td></tr>';
                    }
                })
                .catch(error => {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-red-400 text-sm"><i class="fas fa-exclamation-triangle mr-2"></i>Error loading data</td></tr>';
                });
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const applyModal = document.getElementById('applyLeaveModal');
            const lateModal = document.getElementById('lateApplicationModal');
            
            if (event.target === applyModal) {
                closeApplyLeaveModal();
            }
            if (event.target === lateModal) {
                closeLateApplicationModal();
            }
        });

        // Calculate days between dates excluding weekends
        function calculateDays() {
            const startDate = document.getElementById('modal_start_date').value;
            const endDate = document.getElementById('modal_end_date').value;
            const totalDaysInput = document.getElementById('modal_total_days');
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                // Count only weekdays (Monday to Friday)
                let weekdayCount = 0;
                let current = new Date(start);
                
                while (current <= end) {
                    const dayOfWeek = current.getDay(); // 0 (Sunday) to 6 (Saturday)
                    // Only count Monday (1) to Friday (5)
                    if (dayOfWeek >= 1 && dayOfWeek <= 5) {
                        weekdayCount++;
                    }
                    current.setDate(current.getDate() + 1);
                }
                
                totalDaysInput.value = weekdayCount + ' day' + (weekdayCount !== 1 ? 's' : '');
            }
        }

        function calculateLateDays() {
            const startDate = document.getElementById('modal_late_start_date').value;
            const endDate = document.getElementById('modal_late_end_date').value;
            const totalDaysInput = document.getElementById('modal_late_total_days');
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                // Count only weekdays (Monday to Friday)
                let weekdayCount = 0;
                let current = new Date(start);
                
                while (current <= end) {
                    const dayOfWeek = current.getDay(); // 0 (Sunday) to 6 (Saturday)
                    // Only count Monday (1) to Friday (5)
                    if (dayOfWeek >= 1 && dayOfWeek <= 5) {
                        weekdayCount++;
                    }
                    current.setDate(current.getDate() + 1);
                }
                
                totalDaysInput.value = weekdayCount + ' day' + (weekdayCount !== 1 ? 's' : '');
            }
        }

        // Show/hide conditional fields for Apply Leave Modal
        function toggleModalConditionalFields() {
            const leaveType = document.getElementById('modal_leave_type').value;
            const conditionalFields = document.getElementById('modalConditionalFields');
            const vacationFields = document.getElementById('modalVacationFields');
            const sickFields = document.getElementById('modalSickFields');
            const specialWomenFields = document.getElementById('modalSpecialWomenFields');
            const studyFields = document.getElementById('modalStudyFields');
            const matPatFields = document.getElementById('modalMatPatFields');
            const matPatInput = document.getElementById('modal_matpat_file');
            const otherPurposeField = document.getElementById('modalOtherPurposeField');
            const calendarPicker = document.getElementById('modalCalendarPicker');
            const workingDaysField = document.getElementById('modalWorkingDaysField');
            const startDateInput = document.getElementById('modal_start_date');
            const endDateInput = document.getElementById('modal_end_date');
            const workingDaysInput = document.getElementById('modal_working_days');
            
            // Hide all conditional fields first using opacity instead of hidden
            if (vacationFields) {
                vacationFields.classList.add('hidden');
                vacationFields.style.display = 'none';
            }
            if (sickFields) {
                sickFields.classList.add('hidden');
                sickFields.style.display = 'none';
            }
            if (specialWomenFields) {
                specialWomenFields.classList.add('hidden');
                specialWomenFields.style.display = 'none';
            }
            if (studyFields) {
                studyFields.classList.add('hidden');
                studyFields.style.display = 'none';
            }
            if (conditionalFields) {
                conditionalFields.classList.add('hidden');
                conditionalFields.style.display = 'none';
            }
            if (matPatFields) {
                matPatFields.classList.add('hidden');
                matPatFields.style.display = 'none';
            }
            if (matPatInput) matPatInput.required = false;
            
            // Handle "other" leave type (Terminal Leave / Monetization)
            if (leaveType === 'other') {
                // Show other purpose dropdown
                if (otherPurposeField) {
                    otherPurposeField.classList.remove('hidden');
                    document.getElementById('modal_other_purpose').required = true;
                }
                // Hide calendar picker, show working days input
                if (calendarPicker) calendarPicker.classList.add('hidden');
                if (workingDaysField) {
                    workingDaysField.classList.remove('hidden');
                    if (workingDaysInput) workingDaysInput.required = true;
                }
                // Make date fields not required for "other"
                if (startDateInput) startDateInput.required = false;
                if (endDateInput) endDateInput.required = false;
            } else {
                // Hide other purpose field for regular leave types
                if (otherPurposeField) {
                    otherPurposeField.classList.add('hidden');
                    document.getElementById('modal_other_purpose').required = false;
                }
                // Show calendar picker, hide working days input
                if (calendarPicker) calendarPicker.classList.remove('hidden');
                if (workingDaysField) {
                    workingDaysField.classList.add('hidden');
                    if (workingDaysInput) workingDaysInput.required = false;
                }
                // Make date fields required for regular leave types
                if (startDateInput) startDateInput.required = true;
                if (endDateInput) endDateInput.required = true;
            }
            
            // Show relevant fields based on leave type
            if (leaveType === 'vacation' || leaveType === 'special_privilege') {
                if (vacationFields) {
                    vacationFields.classList.remove('hidden');
                    vacationFields.style.display = 'block';
                }
                if (conditionalFields) {
                    conditionalFields.classList.remove('hidden');
                    conditionalFields.style.display = 'block';
                }
            } else if (leaveType === 'sick') {
                if (sickFields) {
                    sickFields.classList.remove('hidden');
                    sickFields.style.display = 'block';
                }
                if (conditionalFields) {
                    conditionalFields.classList.remove('hidden');
                    conditionalFields.style.display = 'block';
                }
            } else if (leaveType === 'special_women') {
                if (specialWomenFields) {
                    specialWomenFields.classList.remove('hidden');
                    specialWomenFields.style.display = 'block';
                }
                if (conditionalFields) {
                    conditionalFields.classList.remove('hidden');
                    conditionalFields.style.display = 'block';
                }
            } else if (leaveType === 'study') {
                if (studyFields) {
                    studyFields.classList.remove('hidden');
                    studyFields.style.display = 'block';
                }
                if (conditionalFields) {
                    conditionalFields.classList.remove('hidden');
                    conditionalFields.style.display = 'block';
                }
            } else if (leaveType === 'maternity' || leaveType === 'paternity') {
                if (matPatFields) {
                    matPatFields.classList.remove('hidden');
                    matPatFields.style.display = 'block';
                }
                if (matPatInput) matPatInput.required = true;
            }
        }

        // Show/hide conditional fields for Late Application Modal
        function toggleModalLateConditionalFields() {
            const leaveType = document.getElementById('modal_late_leave_type').value;
            const conditionalFields = document.getElementById('modalLateConditionalFields');
            const vacationFields = document.getElementById('modalLateVacationFields');
            const sickFields = document.getElementById('modalLateSickFields');
            const specialWomenFields = document.getElementById('modalLateSpecialWomenFields');
            const studyFields = document.getElementById('modalLateStudyFields');
            const lateMatPatFields = document.getElementById('modalLateMatPatFields');
            const lateMatPatInput = document.getElementById('modal_late_matpat_file');
            
            // Hide all conditional fields first
            if (vacationFields) vacationFields.classList.add('hidden');
            if (sickFields) sickFields.classList.add('hidden');
            if (specialWomenFields) specialWomenFields.classList.add('hidden');
            if (studyFields) studyFields.classList.add('hidden');
            if (conditionalFields) conditionalFields.classList.add('hidden');
            if (lateMatPatFields) lateMatPatFields.classList.add('hidden');
            if (lateMatPatFields) lateMatPatFields.style.display = 'none';
            if (lateMatPatInput) lateMatPatInput.required = false;
            
            // Show relevant fields based on leave type
            if (leaveType === 'vacation' || leaveType === 'special_privilege') {
                if (vacationFields) vacationFields.classList.remove('hidden');
                if (conditionalFields) conditionalFields.classList.remove('hidden');
            } else if (leaveType === 'sick') {
                if (sickFields) sickFields.classList.remove('hidden');
                if (conditionalFields) conditionalFields.classList.remove('hidden');
            } else if (leaveType === 'special_women') {
                if (specialWomenFields) specialWomenFields.classList.remove('hidden');
                if (conditionalFields) conditionalFields.classList.remove('hidden');
            } else if (leaveType === 'study') {
                if (studyFields) studyFields.classList.remove('hidden');
                if (conditionalFields) conditionalFields.classList.remove('hidden');
            } else if (leaveType === 'maternity' || leaveType === 'paternity') {
                if (lateMatPatFields) {
                    lateMatPatFields.classList.remove('hidden');
                    lateMatPatFields.style.display = 'block';
                }
                if (lateMatPatInput) lateMatPatInput.required = true;
            }
        }


        // Clear conditional fields when modal is closed
        function clearModalConditionalFields() {
            const conditionalFields = document.getElementById('modalConditionalFields');
            const vacationFields = document.getElementById('modalVacationFields');
            const sickFields = document.getElementById('modalSickFields');
            const specialWomenFields = document.getElementById('modalSpecialWomenFields');
            const studyFields = document.getElementById('modalStudyFields');
            
            if (vacationFields) vacationFields.classList.add('hidden');
            if (sickFields) sickFields.classList.add('hidden');
            if (specialWomenFields) specialWomenFields.classList.add('hidden');
            if (studyFields) studyFields.classList.add('hidden');
            if (conditionalFields) conditionalFields.classList.add('hidden');
        }

        function clearModalLateConditionalFields() {
            const conditionalFields = document.getElementById('modalLateConditionalFields');
            const vacationFields = document.getElementById('modalLateVacationFields');
            const sickFields = document.getElementById('modalLateSickFields');
            const specialWomenFields = document.getElementById('modalLateSpecialWomenFields');
            const studyFields = document.getElementById('modalLateStudyFields');
            
            if (vacationFields) vacationFields.classList.add('hidden');
            if (sickFields) sickFields.classList.add('hidden');
            if (specialWomenFields) specialWomenFields.classList.add('hidden');
            if (studyFields) studyFields.classList.add('hidden');
            if (conditionalFields) conditionalFields.classList.add('hidden');
        }

        // Update close functions to clear conditional fields
        function closeApplyLeaveModal() {
            const modal = document.getElementById('applyLeaveModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            // Reset form
            document.getElementById('applyLeaveForm').reset();
            clearModalConditionalFields();
            // Reset calendar picker
            if (typeof leaveCalendar !== 'undefined') {
                leaveCalendar.reset();
            }
        }

        function closeLateApplicationModal() {
            const modal = document.getElementById('lateApplicationModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            // Reset form
            document.getElementById('lateApplicationForm').reset();
            clearModalLateConditionalFields();
            // Reset calendar picker
            if (typeof lateLeaveCalendar !== 'undefined') {
                lateLeaveCalendar.reset();
            }
        }

        function openInsufficientCreditsModal() {
            const modal = document.getElementById('insufficientCreditsModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }

        function closeInsufficientCreditsModal() {
            const modal = document.getElementById('insufficientCreditsModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            // Clear the stored data via AJAX or redirect
            window.location.href = 'dashboard.php?clear_insufficient_credits=1';
        }

        function proceedWithWithoutPay() {
            // Clear any previous success modal state for new submission
            sessionStorage.removeItem('successModalShown');
            
            // Get the stored form data
            const formData = <?php echo json_encode($_SESSION['temp_insufficient_credits_data'] ?? []); ?>;
            
            // Debug: Log the form data
            console.log('proceedWithWithoutPay - formData:', formData);
            console.log('medical_certificate_path:', formData.medical_certificate_path);
            
            if (formData && Object.keys(formData).length > 0) {
                // Show processing modal first
                const processingModal = document.getElementById('processingModal');
                if (processingModal) {
                    // Update processing message for without pay
                    const processingMessage = document.querySelector('#processingModal p.text-slate-300');
                    if (processingMessage) {
                        const leaveTypeDisplay = formData.leave_type ? formData.leave_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'leave';
                        processingMessage.textContent = `Please wait while we submit your ${leaveTypeDisplay} leave request (Without Pay)...`;
                    }
                    
                    // Show processing modal
                    processingModal.classList.remove('hidden');
                }
                
                // Hide the insufficient credits modal
                const insufficientModal = document.getElementById('insufficientCreditsModal');
                if (insufficientModal) {
                    insufficientModal.classList.add('hidden');
                }
                
                // Create a form and submit it after a short delay to show processing
                setTimeout(() => {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = formData.is_late ? 'late_leave_application.php' : 'submit_leave.php';
                    
                    // Add all form data as hidden inputs
                    Object.keys(formData).forEach(key => {
                        if (key !== 'is_late' && key !== 'credit_message') {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = formData[key] || '';
                            form.appendChild(input);
                        }
                    });
                    
                    // Add proceed_without_pay flag
                    const proceedInput = document.createElement('input');
                    proceedInput.type = 'hidden';
                    proceedInput.name = 'proceed_without_pay';
                    proceedInput.value = 'yes';
                    form.appendChild(proceedInput);
                    
                    // Add original_leave_type to preserve the original leave type
                    if (formData.leave_type) {
                        const originalInput = document.createElement('input');
                        originalInput.type = 'hidden';
                        originalInput.name = 'original_leave_type';
                        originalInput.value = formData.leave_type;
                        form.appendChild(originalInput);
                    }
                    
                    // Submit the form
                    document.body.appendChild(form);
                    form.submit();
                }, 1500); // 1.5 second delay to show processing modal
            } else {
                showStyledAlert('Unable to process request. Please try again.', 'error');
                window.location.href = 'dashboard.php';
            }
        }

        // Leave Calendar Picker Class
        class LeaveCalendarPicker {
            constructor(containerId, options = {}) {
                this.container = document.getElementById(containerId);
                this.isLateMode = options.isLateMode || false;
                this.startDateInput = document.getElementById(options.startDateId);
                this.endDateInput = document.getElementById(options.endDateId);
                this.totalDaysEl = document.getElementById(options.totalDaysId);
                this.displayContainer = document.getElementById(options.displayId);
                this.selectedDatesId = options.selectedDatesId;
                this.daysCountId = options.daysCountId;
                this.selectedDatesInput = document.getElementById(options.selectedDatesId);
                this.daysCountInput = document.getElementById(options.daysCountId);
                
                // Debug: Log if elements are found
                console.log('Calendar init - selectedDatesInput:', this.selectedDatesInput ? 'found' : 'NOT FOUND', options.selectedDatesId);
                console.log('Calendar init - daysCountInput:', this.daysCountInput ? 'found' : 'NOT FOUND', options.daysCountId);
                this.selectedDates = [];
                this.currentMonth = new Date();
                this.today = new Date();
                this.today.setHours(0, 0, 0, 0);
                this.isOpen = false;
                
                if (this.isLateMode) {
                    this.container.classList.add('late-mode');
                }
                
                // Calendar only closes when clicking Done button
                // No outside click close - let employee decide when done
                
                this.render();
            }
            
            toggle() {
                if (this.isOpen) {
                    this.close();
                } else {
                    this.open();
                }
            }
            
            open() {
                this.container.classList.add('show');
                this.isOpen = true;
            }
            
            close() {
                this.container.classList.remove('show');
                this.isOpen = false;
            }
            
            render() {
                const year = this.currentMonth.getFullYear();
                const month = this.currentMonth.getMonth();
                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                const startDayOfWeek = firstDay.getDay();
                
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                   'July', 'August', 'September', 'October', 'November', 'December'];
                
                let html = `
                    <div class="calendar-header">
                        <button type="button" onclick="event.preventDefault(); ${this.getInstanceName()}.prevMonth()">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="month-year">${monthNames[month]} ${year}</span>
                        <button type="button" onclick="event.preventDefault(); ${this.getInstanceName()}.nextMonth()">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <div class="calendar-grid">
                        <div class="day-header">Sun</div>
                        <div class="day-header">Mon</div>
                        <div class="day-header">Tue</div>
                        <div class="day-header">Wed</div>
                        <div class="day-header">Thu</div>
                        <div class="day-header">Fri</div>
                        <div class="day-header">Sat</div>
                `;
                
                // Previous month days
                const prevMonth = new Date(year, month, 0);
                for (let i = startDayOfWeek - 1; i >= 0; i--) {
                    const day = prevMonth.getDate() - i;
                    html += `<div class="day-cell other-month disabled">${day}</div>`;
                }
                
                // Current month days
                for (let day = 1; day <= lastDay.getDate(); day++) {
                    const date = new Date(year, month, day);
                    const dateStr = this.formatDate(date);
                    const dayOfWeek = date.getDay();
                    const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
                    const isPast = date < this.today;
                    const isToday = date.getTime() === this.today.getTime();
                    const isSelected = this.selectedDates.includes(dateStr);
                    const isInRange = this.isDateInRange(date);
                    
                    let classes = ['day-cell'];
                    if (isWeekend) classes.push('weekend');
                    if (isToday) classes.push('today');
                    if (isSelected) classes.push('selected');
                    if (isInRange && !isSelected) classes.push('in-range');
                    
                    // In late mode, all dates are selectable (past, present, future)
                    // In regular mode, only today and future dates are selectable
                    if (!this.isLateMode && isPast) {
                        classes.push('disabled', 'past-date');
                    }
                    
                    const clickable = !isWeekend && (this.isLateMode || !isPast);
                    const onclick = clickable ? `onclick="event.preventDefault(); ${this.getInstanceName()}.toggleDate('${dateStr}')"` : '';
                    
                    html += `<div class="${classes.join(' ')}" ${onclick}>${day}</div>`;
                }
                
                // Next month days
                const remainingCells = 42 - (startDayOfWeek + lastDay.getDate());
                for (let day = 1; day <= remainingCells; day++) {
                    html += `<div class="day-cell other-month disabled">${day}</div>`;
                }
                
                html += '</div>';
                
                // Footer with buttons
                html += `
                    <div class="calendar-footer">
                        <button type="button" class="btn-clear" onclick="event.preventDefault(); ${this.getInstanceName()}.clearAll()">
                            <i class="fas fa-trash-alt mr-1"></i> Clear
                        </button>
                        <span class="text-slate-400 text-sm">${this.selectedDates.length} selected</span>
                        <button type="button" class="btn-done" onclick="event.preventDefault(); ${this.getInstanceName()}.close()">
                            <i class="fas fa-check mr-1"></i> Done
                        </button>
                    </div>
                `;
                
                this.container.innerHTML = html;
            }
            
            clearAll() {
                this.selectedDates = [];
                this.updateInputs();
                this.updateDisplay();
                this.render();
            }
            
            getInstanceName() {
                return this.isLateMode ? 'lateLeaveCalendar' : 'leaveCalendar';
            }
            
            formatDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
            
            formatDisplayDate(dateStr) {
                const date = new Date(dateStr + 'T00:00:00');
                const options = { month: 'short', day: 'numeric' };
                return date.toLocaleDateString('en-US', options);
            }
            
            isDateInRange(date) {
                if (this.selectedDates.length < 2) return false;
                const sortedDates = [...this.selectedDates].sort();
                const start = new Date(sortedDates[0] + 'T00:00:00');
                const end = new Date(sortedDates[sortedDates.length - 1] + 'T00:00:00');
                return date > start && date < end;
            }
            
            toggleDate(dateStr) {
                const index = this.selectedDates.indexOf(dateStr);
                if (index > -1) {
                    this.selectedDates.splice(index, 1);
                } else {
                    this.selectedDates.push(dateStr);
                }
                this.selectedDates.sort();
                this.updateInputs();
                this.updateDisplay();
                this.render();
            }
            
            removeDate(dateStr) {
                const index = this.selectedDates.indexOf(dateStr);
                if (index > -1) {
                    this.selectedDates.splice(index, 1);
                    this.updateInputs();
                    this.updateDisplay();
                    this.render();
                }
            }
            
            updateInputs() {
                // Count only weekdays from selected dates
                const weekdayDates = this.selectedDates.filter(dateStr => {
                    const date = new Date(dateStr + 'T00:00:00');
                    const day = date.getDay();
                    return day !== 0 && day !== 6;
                }).sort();
                
                if (weekdayDates.length > 0) {
                    this.startDateInput.value = weekdayDates[0];
                    this.endDateInput.value = weekdayDates[weekdayDates.length - 1];
                } else {
                    this.startDateInput.value = '';
                    this.endDateInput.value = '';
                }
                
                // Re-fetch elements if not found (in case they weren't available during init)
                if (!this.selectedDatesInput && this.selectedDatesId) {
                    this.selectedDatesInput = document.getElementById(this.selectedDatesId);
                }
                if (!this.daysCountInput && this.daysCountId) {
                    this.daysCountInput = document.getElementById(this.daysCountId);
                }
                
                // Store selected dates as comma-separated string
                if (this.selectedDatesInput) {
                    this.selectedDatesInput.value = weekdayDates.join(',');
                    console.log('Set selected_dates to:', weekdayDates.join(','));
                }
                
                // Store the actual count
                if (this.daysCountInput) {
                    this.daysCountInput.value = weekdayDates.length;
                    console.log('Set days_count to:', weekdayDates.length);
                }
                
                if (this.totalDaysEl) {
                    this.totalDaysEl.textContent = weekdayDates.length + ' day' + (weekdayDates.length !== 1 ? 's' : '');
                }
            }
            
            updateDisplay() {
                if (this.selectedDates.length === 0) {
                    this.displayContainer.textContent = 'Click to select dates';
                    return;
                }
                
                const sortedDates = [...this.selectedDates].sort();
                if (sortedDates.length === 1) {
                    this.displayContainer.textContent = this.formatDisplayDate(sortedDates[0]);
                } else if (sortedDates.length === 2) {
                    this.displayContainer.textContent = `${this.formatDisplayDate(sortedDates[0])} - ${this.formatDisplayDate(sortedDates[1])}`;
                } else {
                    this.displayContainer.textContent = `${this.formatDisplayDate(sortedDates[0])} - ${this.formatDisplayDate(sortedDates[sortedDates.length - 1])} (${sortedDates.length} dates)`;
                }
            }
            
            prevMonth() {
                this.currentMonth.setMonth(this.currentMonth.getMonth() - 1);
                this.render();
            }
            
            nextMonth() {
                this.currentMonth.setMonth(this.currentMonth.getMonth() + 1);
                this.render();
            }
            
            reset() {
                this.selectedDates = [];
                this.currentMonth = new Date();
                this.isOpen = false;
                this.container.classList.remove('show');
                this.updateInputs();
                this.updateDisplay();
                this.render();
            }
        }
        
        // Initialize calendar pickers
        let leaveCalendar, lateLeaveCalendar;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Regular leave calendar
            leaveCalendar = new LeaveCalendarPicker('leaveCalendarPicker', {
                isLateMode: false,
                startDateId: 'modal_start_date',
                endDateId: 'modal_end_date',
                totalDaysId: 'modal_total_days',
                displayId: 'selectedDatesDisplay',
                selectedDatesId: 'modal_selected_dates',
                daysCountId: 'modal_days_count'
            });
            
            // Late leave calendar
            lateLeaveCalendar = new LeaveCalendarPicker('lateLeaveCalendarPicker', {
                isLateMode: true,
                startDateId: 'modal_late_start_date',
                endDateId: 'modal_late_end_date',
                totalDaysId: 'modal_late_total_days',
                displayId: 'lateSelectedDatesDisplay',
                selectedDatesId: 'modal_late_selected_dates',
                daysCountId: 'modal_late_days_count'
            });
            
            // Ensure hidden fields are populated before form submission
            const applyLeaveForm = document.getElementById('applyLeaveForm');
            if (applyLeaveForm) {
                applyLeaveForm.addEventListener('submit', function(e) {
                    // Force update inputs before submission
                    if (leaveCalendar) {
                        leaveCalendar.updateInputs();
                    }
                    console.log('Form submitting with days_count:', document.getElementById('modal_days_count').value);
                });
            }
            
            const lateApplicationForm = document.getElementById('lateApplicationForm');
            if (lateApplicationForm) {
                lateApplicationForm.addEventListener('submit', function(e) {
                    // Force update inputs before submission
                    if (lateLeaveCalendar) {
                        lateLeaveCalendar.updateInputs();
                    }
                });
            }
        });
        
        // Legacy functions for compatibility
        function calculateDays() {
            // Now handled by calendar picker
        }
        
        function calculateLateDays() {
            // Now handled by calendar picker
        }
        
        // Reason field removed from regular leave application


        
        // Auto-populate forms if there's stored data
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['late_application_data'])): ?>
            // Auto-open late application modal and populate with stored data
            setTimeout(function() {
                openLateApplicationModal();
                populateLateApplicationForm();
            }, 1000); // Delay to ensure modal is ready
            <?php endif; ?>
            
            <?php if (isset($_SESSION['regular_application_data'])): ?>
            // Auto-open regular application modal and populate with stored data
            setTimeout(function() {
                openApplyLeaveModal();
                populateRegularApplicationForm();
            }, 1000); // Delay to ensure modal is ready
            <?php endif; ?>
            
            <?php if (isset($_SESSION['show_insufficient_credits_popup']) && isset($_SESSION['insufficient_credits_data']) && !isset($_SESSION['show_success_modal'])): ?>
            // Auto-open insufficient credits popup (only if not showing success modal)
            setTimeout(function() {
                openInsufficientCreditsModal();
                // Display the credit message
                const creditMessage = document.getElementById('creditMessage');
                if (creditMessage) {
                    creditMessage.textContent = '<?php echo addslashes($_SESSION['insufficient_credits_data']['credit_message']); ?>';
                }
            }, 1000); // Delay to ensure modal is ready
            <?php endif; ?>
            
            // Ensure all form fields are submitted regardless of visibility
            const applyLeaveForm = document.getElementById('applyLeaveForm');
            if (applyLeaveForm) {
                applyLeaveForm.addEventListener('submit', function(e) {
                    // Ensure conditional fields are visible when submitting
                    const leaveType = document.getElementById('modal_leave_type').value;
                    const vacationFields = document.getElementById('modalVacationFields');
                    const sickFields = document.getElementById('modalSickFields');
                    const specialWomenFields = document.getElementById('modalSpecialWomenFields');
                    const studyFields = document.getElementById('modalStudyFields');
                    
                    // Temporarily show all relevant fields
                    if (leaveType === 'vacation' || leaveType === 'special_privilege') {
                        if (vacationFields) {
                            vacationFields.style.display = 'block';
                            vacationFields.classList.remove('hidden');
                        }
                    } else if (leaveType === 'sick') {
                        if (sickFields) {
                            sickFields.style.display = 'block';
                            sickFields.classList.remove('hidden');
                        }
                    } else if (leaveType === 'special_women') {
                        if (specialWomenFields) {
                            specialWomenFields.style.display = 'block';
                            specialWomenFields.classList.remove('hidden');
                        }
                    } else if (leaveType === 'study') {
                        if (studyFields) {
                            studyFields.style.display = 'block';
                            studyFields.classList.remove('hidden');
                        }
                    }
                });
            }
            
            // Same fix for late application form
            const lateApplicationForm = document.getElementById('lateApplicationForm');
            if (lateApplicationForm) {
                lateApplicationForm.addEventListener('submit', function(e) {
                    // Ensure conditional fields are visible when submitting
                    const leaveType = document.getElementById('modal_late_leave_type').value;
                    const vacationFields = document.getElementById('modalLateVacationFields');
                    const sickFields = document.getElementById('modalLateSickFields');
                    const specialWomenFields = document.getElementById('modalLateSpecialWomenFields');
                    const studyFields = document.getElementById('modalLateStudyFields');
                    
                    // Temporarily show all relevant fields
                    if (leaveType === 'vacation' || leaveType === 'special_privilege') {
                        if (vacationFields) {
                            vacationFields.style.display = 'block';
                            vacationFields.classList.remove('hidden');
                        }
                    } else if (leaveType === 'sick') {
                        if (sickFields) {
                            sickFields.style.display = 'block';
                            sickFields.classList.remove('hidden');
                        }
                    } else if (leaveType === 'special_women') {
                        if (specialWomenFields) {
                            specialWomenFields.style.display = 'block';
                            specialWomenFields.classList.remove('hidden');
                        }
                    } else if (leaveType === 'study') {
                        if (studyFields) {
                            studyFields.style.display = 'block';
                            studyFields.classList.remove('hidden');
                        }
                    }
                    
                    // Show processing modal for late applications
                    showLateProcessingModal(leaveType);
                });
            }
        });

        function populateLateApplicationForm() {
            <?php if (isset($_SESSION['late_application_data'])): ?>
            const data = <?php echo json_encode($_SESSION['late_application_data']); ?>;
            
            // Populate form fields
            const leaveTypeSelect = document.querySelector('#lateApplicationModal select[name="leave_type"]');
            if (leaveTypeSelect) leaveTypeSelect.value = data.leave_type;
            
            const startDateInput = document.querySelector('#lateApplicationModal input[name="start_date"]');
            if (startDateInput) startDateInput.value = data.start_date;
            
            const endDateInput = document.querySelector('#lateApplicationModal input[name="end_date"]');
            if (endDateInput) endDateInput.value = data.end_date;
            
            // Reason field removed from late leave application
            
            // Populate conditional fields
            if (data.location_type) {
                const locationTypeSelect = document.querySelector('#lateApplicationModal select[name="location_type"]');
                if (locationTypeSelect) locationTypeSelect.value = data.location_type;
            }
            
            if (data.location_specify) {
                const locationSpecifyInput = document.querySelector('#lateApplicationModal input[name="location_specify"]');
                if (locationSpecifyInput) locationSpecifyInput.value = data.location_specify;
            }
            
            if (data.medical_condition) {
                const medicalConditionSelect = document.querySelector('#lateApplicationModal select[name="medical_condition"]');
                if (medicalConditionSelect) medicalConditionSelect.value = data.medical_condition;
            }
            
            if (data.illness_specify) {
                const illnessSpecifyInput = document.querySelector('#lateApplicationModal input[name="illness_specify"]');
                if (illnessSpecifyInput) illnessSpecifyInput.value = data.illness_specify;
            }
            
            if (data.special_women_condition) {
                const specialWomenSelect = document.querySelector('#lateApplicationModal select[name="special_women_condition"]');
                if (specialWomenSelect) specialWomenSelect.value = data.special_women_condition;
            }
            
            if (data.study_type) {
                const studyTypeSelect = document.querySelector('#lateApplicationModal select[name="study_type"]');
                if (studyTypeSelect) studyTypeSelect.value = data.study_type;
            }
            
            // Clear the stored data after populating
            <?php unset($_SESSION['late_application_data']); ?>
            <?php endif; ?>
        }

        function populateRegularApplicationForm() {
            <?php if (isset($_SESSION['regular_application_data'])): ?>
            const data = <?php echo json_encode($_SESSION['regular_application_data']); ?>;
            
            // Populate form fields
            const leaveTypeSelect = document.querySelector('#applyLeaveModal select[name="leave_type"]');
            if (leaveTypeSelect) leaveTypeSelect.value = data.leave_type;
            
            const startDateInput = document.querySelector('#applyLeaveModal input[name="start_date"]');
            if (startDateInput) startDateInput.value = data.start_date;
            
            const endDateInput = document.querySelector('#applyLeaveModal input[name="end_date"]');
            if (endDateInput) endDateInput.value = data.end_date;
            
            // Reason field removed from regular leave application
            
            // Populate conditional fields
            if (data.location_type) {
                const locationTypeSelect = document.querySelector('#applyLeaveModal select[name="location_type"]');
                if (locationTypeSelect) locationTypeSelect.value = data.location_type;
            }
            
            if (data.location_specify) {
                const locationSpecifyInput = document.querySelector('#applyLeaveModal input[name="location_specify"]');
                if (locationSpecifyInput) locationSpecifyInput.value = data.location_specify;
            }
            
            if (data.medical_condition) {
                const medicalConditionSelect = document.querySelector('#applyLeaveModal select[name="medical_condition"]');
                if (medicalConditionSelect) medicalConditionSelect.value = data.medical_condition;
            }
            
            if (data.illness_specify) {
                const illnessSpecifyInput = document.querySelector('#applyLeaveModal input[name="illness_specify"]');
                if (illnessSpecifyInput) illnessSpecifyInput.value = data.illness_specify;
            }
            
            if (data.special_women_condition) {
                const specialWomenSelect = document.querySelector('#applyLeaveModal select[name="special_women_condition"]');
                if (specialWomenSelect) specialWomenSelect.value = data.special_women_condition;
            }
            
            if (data.study_type) {
                const studyTypeSelect = document.querySelector('#applyLeaveModal select[name="study_type"]');
                if (studyTypeSelect) studyTypeSelect.value = data.study_type;
            }
            
            // Trigger the conditional fields display
            if (leaveTypeSelect) {
                toggleModalConditionalFields();
            }
            
            // Clear the stored data after populating
            <?php unset($_SESSION['regular_application_data']); ?>
            <?php endif; ?>
        }

    </script>
    
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
        // Initialize FullCalendar
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            if (!calendarEl) {
                // Calendar container not present on this view; skip initialization
                return;
            }
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
                    <?php 
                    // Get user's leave requests for calendar (exclude rejected leaves)
                    // EXCLUDE 'other' type (Terminal Leave/Monetization) as they don't represent actual absence days
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
                        AND lr.status != 'rejected'
                        AND lr.leave_type != 'other'
                        ORDER BY lr.start_date ASC
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user_leave_requests = $stmt->fetchAll();
                    
                    foreach ($user_leave_requests as $request): 
                        // Use the getLeaveTypeDisplayName function for consistent display
                        $leaveDisplayName = getLeaveTypeDisplayName($request['leave_type'], $request['original_leave_type'] ?? null, $leaveTypes, $request['other_purpose'] ?? null);
                        
                        // For without pay leaves, use original leave type color if available
                        $colorClass = 'leave-' . $request['leave_type'];
                        if ($request['leave_type'] === 'without_pay' && !empty($request['original_leave_type'])) {
                            $colorClass = 'leave-' . $request['original_leave_type'];
                        }
                        
                        // Check if specific dates are selected
                        $weekdayGroups = [];
                        
                        if (!empty($request['selected_dates'])) {
                            // Use selected dates - create individual events for each date
                            $selectedDates = explode(',', $request['selected_dates']);
                            foreach ($selectedDates as $date) {
                                $date = trim($date);
                                if (!empty($date)) {
                                    $weekdayGroups[] = ['start' => $date, 'end' => $date, 'days' => 1];
                                }
                            }
                        } else {
                            // Fallback: Collect all weekday dates and group consecutive weekdays
                            $start = new DateTime($request['start_date']);
                            $daysToCount = $request['actual_days_approved'];
                            $weekdaysCounted = 0;
                            $current = clone $start;
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
                        }
                        
                        // Create separate events for each weekday group
                        foreach ($weekdayGroups as $index => $group):
                            $groupEnd = new DateTime($group['end']);
                            $groupEnd->modify('+1 day');
                            // Use individual day count for title display
                            $displayDays = isset($group['days']) ? $group['days'] : $request['actual_days_approved'];
                    ?>
                    {
                        id: '<?php echo $request['id'] . '_' . $index; ?>',
                        title: '<?php echo addslashes($leaveDisplayName); ?> (<?php echo $displayDays; ?> day<?php echo $displayDays != 1 ? 's' : ''; ?>)',
                        start: '<?php echo $group['start']; ?>',
                        end: '<?php echo $groupEnd->format('Y-m-d'); ?>',
                        className: '<?php echo $colorClass; ?>',
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
                ],
                eventClick: function(info) {
                    const props = info.event.extendedProps;
                    const message = `Leave Details:\nType: ${props.display_name}\nStatus: ${props.status}\nDays Approved: ${props.days_approved}\nDays Requested: ${props.days_requested}\nReason: ${props.reason}\nDate: ${info.event.start.toLocaleDateString()}`;
                    showStyledAlert(message, 'info', 'Leave Details');
                }
            });
            
            calendar.render();
        });
    </script>

    <!-- Processing Popup Modal -->
    <div id="processingModal" class="hidden fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6 text-center">
                <div class="mb-4">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-700 rounded-full mb-4">
                        <i class="fas fa-spinner fa-spin text-blue-400 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Processing Your Request</h3>
                    <p class="text-slate-300 mb-4">
                        <?php 
                        $processingLeaveType = $_SESSION['processing_leave_type'] ?? 'leave';
                        $processingIsLate = $_SESSION['is_late_application'] ?? false;
                        $processingTypeDisplay = ucfirst(str_replace('_', ' ', $processingLeaveType));
                        
                        if ($processingIsLate) {
                            echo "Please wait while we submit your late {$processingTypeDisplay} leave request...";
                        } else {
                            echo "Please wait while we submit your {$processingTypeDisplay} leave request...";
                        }
                        ?>
                    </p>
                </div>
                
                <div class="flex items-center justify-center space-x-2 text-sm text-slate-400">
                    <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce"></div>
                    <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                    <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Popup Modal -->
    <div id="successModal" class="hidden fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6 text-center">
                <div class="mb-4">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-green-600 rounded-full mb-4">
                        <i class="fas fa-check text-white text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Request Submitted Successfully!</h3>
                    <p class="text-slate-300 mb-4">
                        <?php 
                        $leaveType = $_SESSION['success_leave_type'] ?? 'leave';
                        $isLate = $_SESSION['is_late_application'] ?? false;
                        
                        // Use the leave types configuration for proper display
                        $leaveTypes = getLeaveTypes();
                        $leaveTypeDisplay = isset($leaveTypes[$leaveType]) ? $leaveTypes[$leaveType]['name'] : ucfirst(str_replace('_', ' ', $leaveType));
                        
                        if ($isLate) {
                            echo "Your late {$leaveTypeDisplay} leave request has been submitted and is now pending approval. Please note that late applications may require additional justification.";
                        } else {
                            echo "Your {$leaveTypeDisplay} leave request has been submitted and is now pending approval.";
                        }
                        ?>
                    </p>
                </div>
                
                <button onclick="closeSuccessModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                    Continue
                </button>
            </div>
        </div>
    </div>


    <script>
        // Show processing popup if needed
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['show_success_modal']) && $_SESSION['show_success_modal']): ?>
                // Show success modal (for proceed without pay, sessionStorage is cleared before submission)
                document.getElementById('successModal').classList.remove('hidden');
                
                // Mark as shown in session storage
                sessionStorage.setItem('successModalShown', 'true');
                
                // Clear session variables immediately
                <?php 
                unset($_SESSION['show_success_modal']);
                unset($_SESSION['success_leave_type']);
                unset($_SESSION['is_late_application']);
                ?>
            <?php endif; ?>
        });
        
        // Function to show processing modal immediately on form submission
        function showProcessingModal(event) {
            // Clear any previous success modal state for new submission
            sessionStorage.removeItem('successModalShown');
            
            // Get the leave type from the form
            const leaveTypeSelect = document.querySelector('select[name="leave_type"]');
            const leaveType = leaveTypeSelect ? leaveTypeSelect.value : 'leave';
            
            // Format leave type for display
            const leaveTypeDisplay = leaveType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            
            // Update processing modal message
            const processingMessage = document.querySelector('#processingModal p.text-slate-300');
            if (processingMessage) {
                processingMessage.textContent = `Please wait while we submit your ${leaveTypeDisplay} leave request...`;
            }
            
            // Hide the leave application modal
            const applyLeaveModal = document.getElementById('applyLeaveModal');
            if (applyLeaveModal) {
                applyLeaveModal.classList.add('hidden');
            }
            
            // Show processing modal immediately
            const processingModal = document.getElementById('processingModal');
            if (processingModal) {
                processingModal.classList.remove('hidden');
            }
            
            // Allow form to continue submitting
            return true;
        }
        
        // Function to show processing modal for late applications
        function showLateProcessingModal(leaveType) {
            // Clear any previous success modal state for new submission
            sessionStorage.removeItem('successModalShown');
            
            // Format leave type for display
            const leaveTypeDisplay = leaveType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            
            // Update processing modal message
            const processingMessage = document.querySelector('#processingModal p.text-slate-300');
            if (processingMessage) {
                processingMessage.textContent = `Please wait while we submit your late ${leaveTypeDisplay} leave request...`;
            }
            
            // Hide the late application modal
            const lateModal = document.getElementById('lateApplicationModal');
            if (lateModal) {
                lateModal.classList.add('hidden');
                lateModal.classList.remove('flex');
            }
            
            // Show processing modal immediately
            const processingModal = document.getElementById('processingModal');
            if (processingModal) {
                processingModal.classList.remove('hidden');
            }
            
            // Allow form to continue submitting
            return true;
        }
        
        // Function to close success modal
        function closeSuccessModal() {
            document.getElementById('successModal').classList.add('hidden');
            // Mark that the modal has been closed to prevent re-display
            sessionStorage.setItem('successModalShown', 'true');
        }
    </script>
    <script src="../../../../assets/js/modal-alert.js"></script>

<?php include '../../../../includes/user_footer.php'; ?>


<!-- Attendance Log Modal -->
<div id="attendanceModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-800/95 backdrop-blur-sm rounded-2xl border border-slate-700/50 w-full max-w-3xl max-h-[85vh] overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-700/50 bg-slate-700/30">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white flex items-center">
                    <i class="fas fa-clipboard-list mr-2 text-purple-500"></i>
                    My Attendance Log
                </h3>
                <button onclick="closeAttendanceModal()" class="text-slate-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
        </div>
        <div class="p-4 overflow-y-auto" style="max-height: calc(85vh - 60px);">
            <div class="bg-slate-700/30 rounded-xl border border-slate-600/50 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-300 uppercase">Date</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-300 uppercase">AM In</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-300 uppercase">AM Out</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-300 uppercase">PM In</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-300 uppercase">PM Out</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-300 uppercase">Hours</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-300 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceTableBody" class="divide-y divide-slate-700">
                        <tr>
                            <td colspan="6" class="text-center py-6 text-slate-400">
                                <i class="fas fa-spinner fa-spin text-xl mb-2"></i>
                                <br>Loading...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
