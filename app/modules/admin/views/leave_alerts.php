<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/EnhancedLeaveAlertService.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Get admin information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Create leave_alerts table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leave_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            alert_type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            sent_by INT NOT NULL,
            priority ENUM('low', 'moderate', 'critical', 'urgent') DEFAULT 'moderate',
            is_read TINYINT(1) DEFAULT 0,
            read_at TIMESTAMP NULL,
                alert_category ENUM('utilization', 'year_end', 'csc_compliance', 'wellness', 'custom') DEFAULT 'utilization',
            metadata JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id),
            FOREIGN KEY (sent_by) REFERENCES employees(id)
        )
    ");
} catch (Exception $e) {
    // Table might already exist, ignore error
}

// Initialize enhanced alert service
$alertService = new EnhancedLeaveAlertService($pdo);

// Handle alert sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_alert') {
        $employee_id = $_POST['employee_id'] ?? '';
        $alert_type = $_POST['alert_type'] ?? '';
        $message = $_POST['message'] ?? '';
        
        // Validate required fields to prevent empty submissions
        if (empty($employee_id) || empty($alert_type) || empty($message)) {
            $_SESSION['error'] = "Please fill in all required fields before sending the alert.";
            error_log("Alert validation failed: empty fields detected");
        } else {
        // Debug: Log the received data
        error_log("Alert data received: employee_id=$employee_id, alert_type=$alert_type, message=" . substr($message, 0, 50) . "...");
        
        try {
            // Insert alert into database with is_read = 0 to ensure it triggers floating notification
            $stmt = $pdo->prepare("
                INSERT INTO leave_alerts (employee_id, alert_type, message, sent_by, is_read, created_at) 
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$employee_id, $alert_type, $message, $_SESSION['user_id']]);
            
            $_SESSION['success'] = "Leave maximization alert sent successfully!";
            error_log("Alert sent successfully for employee ID: $employee_id");
        } catch (Exception $e) {
            $_SESSION['error'] = "Error sending alert: " . $e->getMessage();
            error_log("Error sending alert: " . $e->getMessage());
            }
        }
    }
}

// Function to generate concise warning message
function getShortWarning($alert) {
    $leaveName = isset($alert['leave_name']) ? $alert['leave_name'] : 'Leave';
    $remaining = isset($alert['remaining']) ? $alert['remaining'] : 0;
    $utilization = isset($alert['utilization']) ? $alert['utilization'] : 0;
    
    switch($alert['type']) {
        case 'low_utilization':
            return "⚠️ CSC Notice: {$leaveName} credits nearing maximum accumulation - {$utilization}% utilized, {$remaining} days remaining";
        case 'year_end_urgent':
        case 'year_end_critical':
        case 'year_end_warning':
            return "🔴 CSC Notice: {$remaining} {$leaveName} days subject to forfeiture on December 31st";
        case 'overall_low_utilization':
            return "⚠️ CSC Notice: Leave credits nearing maximum accumulation - {$utilization}% utilized, {$remaining} total days remaining";
        case 'csc_limit_exceeded':
            return "🚨 CSC Violation: {$leaveName} maximum allowable limit exceeded - Immediate HRMO coordination required";
        case 'csc_limit_approaching':
            return "⚠️ CSC Notice: {$leaveName} approaching maximum allowable limit - {$remaining} days remaining";
        case 'forfeiture_critical':
        case 'forfeiture_warning':
            return "🔴 CSC Notice: {$remaining} {$leaveName} days subject to forfeiture - Immediate action required";
        default:
            return "⚠️ CSC Notice: {$leaveName} requires administrative attention";
    }
}

// Get total user count first
$userCountStmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM employees WHERE role = 'employee'");
$userCountStmt->execute();
$totalUsers = $userCountStmt->fetchColumn();

// Get enhanced alert data using the new service
$alertData = $alertService->getUrgentAlerts(50);
$alertStats = $alertService->getAlertStatistics();

// Process alert data for display and group by department
$employees = [];
$departmentGroups = [];
foreach ($alertData as $employeeId => $data) {
    $employee = $data['employee'];
    $alerts = $data['alerts'];
    $priority = $data['priority'];
    $cscCompliance = $data['csc_compliance'];
    
    // Calculate overall statistics
    $totalAllocated = 0;
    $totalUsed = 0;
    $totalRemaining = 0;
    
    require_once '../../../../config/leave_types.php';
    $leaveTypesConfig = getLeaveTypes();
    foreach ($leaveTypesConfig as $type => $config) {
        if (!$config['requires_credits']) continue;
        
        $balanceField = $config['credit_field'];
        $usedField = $type . '_used';
        
        $allocated = $employee[$balanceField] ?? 0;
        $used = $employee[$usedField] ?? 0;
        $remaining = max(0, $allocated - $used);
        
        $totalAllocated += $allocated;
        $totalUsed += $used;
        $totalRemaining += $remaining;
        
        $employee[$type . '_remaining'] = $remaining;
        $employee[$type . '_utilization'] = $allocated > 0 ? round(($used / $allocated) * 100, 1) : 0;
    }
    
    $employee['total_allocated'] = $totalAllocated;
    $employee['total_used'] = $totalUsed;
    $employee['total_remaining'] = $totalRemaining;
    $employee['overall_utilization'] = $totalAllocated > 0 ? round(($totalUsed / $totalAllocated) * 100, 1) : 0;
    $employee['priority'] = $priority;
    $employee['alerts'] = $alerts;
    $employee['csc_compliance'] = $cscCompliance;
    
    // Calculate days remaining in year
    $currentDate = new DateTime();
    $yearEnd = new DateTime(date('Y') . '-12-31');
    $daysRemaining = $currentDate->diff($yearEnd)->days;
    $employee['days_remaining_in_year'] = $daysRemaining;
    
    // Generate urgency message based on priority
    switch ($priority) {
        case 'urgent':
            $employee['urgency_level'] = 'high';
            $employee['urgency_message'] = '🚨 URGENT: Immediate action required!';
            break;
        case 'critical':
            $employee['urgency_level'] = 'high';
            $employee['urgency_message'] = '⚠️ CRITICAL: High priority attention needed!';
            break;
        case 'moderate':
            $employee['urgency_level'] = 'medium';
            $employee['urgency_message'] = '📋 MODERATE: Planning and coordination needed.';
            break;
        default:
            $employee['urgency_level'] = 'low';
            $employee['urgency_message'] = '📅 PLANNING: Consider leave utilization.';
    }
    
    $employees[] = $employee;
    
    // Group by department
    $dept = $employee['department'] ?: 'Unassigned';
    if (!isset($departmentGroups[$dept])) {
        $departmentGroups[$dept] = [];
    }
    $departmentGroups[$dept][] = $employee;
}

// Sort departments alphabetically
ksort($departmentGroups);

// Set page title
$page_title = "Leave Alerts";

// Include admin header
include '../../../../includes/admin_header.php';
?>
    
    <style>
        /* Custom scrollbar styling for modals */
        .modal-scroll::-webkit-scrollbar {
            width: 6px;
        }
        
        .modal-scroll::-webkit-scrollbar-track {
            background: rgba(51, 65, 85, 0.3);
            border-radius: 3px;
        }
        
        .modal-scroll::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.5);
            border-radius: 3px;
        }
        
        .modal-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.7);
        }
        
        /* Smooth scrolling */
        .modal-scroll {
            scroll-behavior: smooth;
        }
    </style>
    <style>
        .alert-card {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .utilization-bar {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            overflow: hidden;
        }
        .utilization-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        .low-utilization {
            background: #dc3545;
        }
        .medium-utilization {
            background: #ffc107;
        }
        .high-utilization {
            background: #28a745;
        }
        .employee-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .employee-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .needs-alert {
            border-left: 4px solid #ffc107;
            background: #fff9e6;
        }
    </style>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex items-center gap-3">
        <i class="fas fa-bell text-3xl text-primary mr-2"></i>
        <div>
            <h1 class="text-3xl font-bold text-white mb-1">Leave Alerts</h1>
            <p class="text-slate-400">Send reminders to employees about their leave credits</p>
        </div>
    </div>
</div>


<!-- Alert Statistics -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8 mb-10">
    <!-- Total Alerts -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700/70 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-xs uppercase tracking-wide mb-1">Total Employees</p>
                <h2 class="text-3xl font-bold text-white"><?php echo $alertStats['total_employees_with_alerts']; ?></h2>
                <p class="text-slate-400 text-xs mt-1">Need attention</p>
            </div>
            <div class="w-14 h-14 bg-blue-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-users text-blue-400 text-2xl"></i>
            </div>
        </div>
    </div>
    
    <!-- Urgent Alerts -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700/70 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-xs uppercase tracking-wide mb-1">Urgent</p>
                <h2 class="text-3xl font-bold text-red-400"><?php echo $alertStats['urgent_alerts']; ?></h2>
                <p class="text-slate-400 text-xs mt-1">Immediate action</p>
            </div>
            <div class="w-14 h-14 bg-red-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-red-400 text-2xl"></i>
            </div>
        </div>
    </div>
    
    <!-- Year-End Risks -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700/70 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-xs uppercase tracking-wide mb-1">Year-End Risk</p>
                <h2 class="text-3xl font-bold text-yellow-400"><?php echo $alertStats['year_end_risks']; ?></h2>
                <p class="text-slate-400 text-xs mt-1">Credits expiring</p>
            </div>
            <div class="w-14 h-14 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-calendar-times text-yellow-400 text-2xl"></i>
            </div>
        </div>
    </div>
</div>



                <!-- Success Message -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-check-circle mr-3"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

<!-- Employee List -->
<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
        <div>
            <h2 class="text-lg font-semibold text-white">Employees Needing Alerts</h2>
            <p class="text-slate-400 text-sm">Organized by department - Click department headers to expand/collapse</p>
        </div>
        <div class="text-slate-400 text-sm">
            <span class="text-white font-semibold"><?php echo count($employees); ?></span> employees in <span class="text-white font-semibold"><?php echo count($departmentGroups); ?></span> departments
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="flex items-center gap-3 mb-4">
        <button onclick="expandAllDepartments()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm transition-colors">
            <i class="fas fa-chevron-down mr-2"></i>Expand All
        </button>
        <button onclick="collapseAllDepartments()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm transition-colors">
            <i class="fas fa-chevron-up mr-2"></i>Collapse All
        </button>
    </div>
    
    <!-- Filters Toolbar -->
    <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-xs text-slate-400 mb-1">Search employee</label>
                <input type="text" id="filterSearch" class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-400" placeholder="Type a name..." oninput="filterEmployees()">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Priority</label>
                <select id="filterPriority" class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white" onchange="filterEmployees()">
                    <option value="">All priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="critical">Critical</option>
                    <option value="moderate">Moderate</option>
                    <option value="low">Low</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Department</label>
                <select id="filterDepartment" class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white" onchange="filterEmployees()">
                    <option value="">All departments</option>
                    <?php foreach (array_keys($departmentGroups) as $deptOpt): ?>
                        <option value="<?php echo htmlspecialchars($deptOpt); ?>"><?php echo htmlspecialchars($deptOpt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex md:justify-end gap-2">
                <button onclick="clearFilters()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm">Clear</button>
            </div>
        </div>
    </div>
</div>

                <?php if (empty($employees)): ?>
                    <!-- No Alerts Card -->
                    <div class="flex flex-col items-center justify-center py-16">
                        <div class="bg-slate-800 rounded-2xl border border-slate-700 p-12 text-center max-w-md mx-auto">
                            <div class="w-20 h-20 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-check-circle text-green-400 text-3xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-white mb-4">Excellent Leave Utilization!</h3>
                            <p class="text-slate-400 mb-6">
                                All employees are effectively utilizing their leave credits. No alerts are needed at this time.
                            </p>
                            <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4 mb-6">
                                <div class="flex items-center justify-center mb-2">
                                    <i class="fas fa-chart-line text-green-400 mr-2"></i>
                                    <span class="text-green-400 font-semibold">Leave Maximization Status</span>
                                </div>
                                <p class="text-sm text-slate-300">
                                    Your organization is successfully maximizing leave utilization across all employees.
                                </p>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <button onclick="window.location.reload()" class="px-6 py-3 bg-primary hover:bg-primary/80 text-white rounded-xl transition-colors flex items-center justify-center">
                                    <i class="fas fa-sync-alt mr-2"></i>
                                    Refresh Data
                                </button>
                                <button onclick="window.location.href='leave_management.php'" class="px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white rounded-xl transition-colors flex items-center justify-center">
                                    <i class="fas fa-calendar-check mr-2"></i>
                                    View Leave Management
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Department Groups -->
                    <div class="space-y-6">
                        <?php foreach ($departmentGroups as $department => $deptEmployees): ?>
                            <div class="bg-slate-800/50 rounded-xl border border-slate-700 overflow-hidden">
                                <!-- Department Header -->
                                <button onclick="toggleDepartment('<?php echo md5($department); ?>')" 
                                        class="w-full flex items-center justify-between p-4 hover:bg-slate-700/50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-building text-primary"></i>
                                        </div>
                                        <div class="text-left">
                                            <h3 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($department); ?></h3>
                                            <p class="text-slate-400 text-sm"><?php echo count($deptEmployees); ?> employee<?php echo count($deptEmployees) > 1 ? 's' : ''; ?> needing attention</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <!-- Priority Summary -->
                                        <div class="flex items-center gap-2">
                                            <?php
                                            $urgentCount = count(array_filter($deptEmployees, fn($e) => $e['priority'] === 'urgent'));
                                            $criticalCount = count(array_filter($deptEmployees, fn($e) => $e['priority'] === 'critical'));
                                            if ($urgentCount > 0): ?>
                                                <span class="px-2 py-1 bg-red-500/20 text-red-400 rounded text-xs font-semibold">
                                                    <?php echo $urgentCount; ?> Urgent
                                                </span>
                                            <?php endif;
                                            if ($criticalCount > 0): ?>
                                                <span class="px-2 py-1 bg-orange-500/20 text-orange-400 rounded text-xs font-semibold">
                                                    <?php echo $criticalCount; ?> Critical
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <i class="fas fa-chevron-down text-slate-400 transition-transform dept-chevron-<?php echo md5($department); ?>"></i>
                                    </div>
                                </button>
                                
                                <!-- Department Employees -->
                                <div id="dept-<?php echo md5($department); ?>" class="dept-content">
                                    <div class="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-4 bg-slate-900/30">
                        <?php foreach ($deptEmployees as $index => $employee): ?>
                            <div class="employee-item bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden hover:border-slate-600/50 transition-all duration-300 <?php 
                                echo $employee['priority'] === 'urgent' ? 'ring-2 ring-red-500/50' : 
                                    ($employee['priority'] === 'critical' ? 'ring-2 ring-orange-500/50' : 
                                    ($employee['priority'] === 'moderate' ? 'ring-2 ring-yellow-500/50' : 'ring-2 ring-blue-500/50')); 
                            ?>" data-employee-id="<?php echo $employee['id']; ?>" data-priority="<?php echo htmlspecialchars($employee['priority']); ?>" data-department="<?php echo htmlspecialchars($employee['department']); ?>" data-name="<?php echo htmlspecialchars(strtolower($employee['name'])); ?>">
                            <div class="p-4">
                                    <!-- Header -->
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="text-lg font-semibold text-white mb-1"><?php echo htmlspecialchars($employee['name']); ?></h3>
                                            <p class="text-slate-400 text-sm"><?php echo htmlspecialchars($employee['position']); ?> - <?php echo htmlspecialchars($employee['department']); ?></p>
                                    </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php 
                                                echo $employee['priority'] === 'urgent' ? 'bg-red-500/20 text-red-400 border border-red-500/30' : 
                                                    ($employee['priority'] === 'critical' ? 'bg-orange-500/20 text-orange-400 border border-orange-500/30' :
                                                    ($employee['priority'] === 'moderate' ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30' : 'bg-blue-500/20 text-blue-400 border border-blue-500/30')); 
                                            ?>">
                                                <i class="fas <?php 
                                                    echo $employee['priority'] === 'urgent' ? 'fa-exclamation-triangle' : 
                                                        ($employee['priority'] === 'critical' ? 'fa-exclamation-circle' :
                                                        ($employee['priority'] === 'moderate' ? 'fa-clock' : 'fa-calendar')); 
                                                ?> mr-1"></i>
                                                <?php echo strtoupper($employee['priority']); ?>
                                        </span>
                                </div>
                                    </div>

                                    <!-- Urgency Message -->
                                    <div class="mb-4 p-3 rounded-lg <?php 
                                        echo $employee['urgency_level'] === 'high' ? 'bg-red-500/10 border border-red-500/30' : 
                                            ($employee['urgency_level'] === 'medium' ? 'bg-yellow-500/10 border border-yellow-500/30' : 'bg-blue-500/10 border border-blue-500/30'); 
                                    ?>">
                                        <p class="text-sm font-semibold <?php 
                                            echo $employee['urgency_level'] === 'high' ? 'text-red-400' : 
                                                ($employee['urgency_level'] === 'medium' ? 'text-yellow-400' : 'text-blue-400'); 
                                        ?>">
                                            <?php echo $employee['urgency_message']; ?>
                                    </p>
                                </div>

                                    <!-- Overall Statistics -->
                                    <div class="mb-4 p-4 bg-slate-700/30 rounded-lg">
                                        <div class="grid grid-cols-2 gap-4 text-center">
                                            <div>
                                                <div class="text-2xl font-bold text-white"><?php echo $employee['total_remaining']; ?></div>
                                                <div class="text-xs text-slate-400">Days Remaining</div>
                                    </div>
                                            <div>
                                                <div class="text-2xl font-bold <?php 
                                                    echo $employee['overall_utilization'] < 30 ? 'text-red-400' : 
                                                        ($employee['overall_utilization'] < 70 ? 'text-yellow-400' : 'text-green-400'); 
                                                ?>"><?php echo $employee['overall_utilization']; ?>%</div>
                                                <div class="text-xs text-slate-400">Utilization</div>
                                            </div>
                                        </div>
                                        <div class="w-full bg-slate-600 rounded-full h-2 mt-3">
                                        <div class="h-2 rounded-full <?php 
                                                echo $employee['overall_utilization'] < 30 ? 'bg-red-500' : 
                                                    ($employee['overall_utilization'] < 70 ? 'bg-yellow-500' : 'bg-green-500'); 
                                            ?>" style="width: <?php echo $employee['overall_utilization']; ?>%"></div>
                                    </div>
                                </div>

                                    <!-- Key Leave Types -->
                                    <div class="space-y-2 mb-4">
                                        <?php 
                                        $keyLeaveTypes = ['vacation', 'sick', 'special_privilege'];
                                        foreach ($keyLeaveTypes as $type): 
                                            $utilization = $employee[$type . '_utilization'];
                                            $remaining = $employee[$type . '_remaining'];
                                            // Get the correct field name from leave types config
                                            $creditField = $leaveTypesConfig[$type]['credit_field'];
                                            $allocated = $employee[$creditField] ?? 0;
                                            if ($allocated > 0):
                                        ?>
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-slate-300"><?php echo ucwords(str_replace('_', ' ', $type)); ?></span>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm <?php 
                                                    echo $utilization < 30 ? 'text-red-400' : 
                                                        ($utilization < 70 ? 'text-yellow-400' : 'text-green-400'); 
                                                ?>"><?php echo $utilization; ?>%</span>
                                                <span class="text-xs text-slate-400">(<?php echo $remaining; ?> left)</span>
                                    </div>
                                    </div>
                                        <?php endif; endforeach; ?>
                                </div>

                                    <!-- Compact Alert Details -->
                                    <?php if (!empty($employee['alerts'])): ?>
                                    <div class="mb-3">
                                        <div class="space-y-1" id="alerts-<?php echo $employee['id']; ?>">
                                            <?php foreach (array_slice($employee['alerts'], 0, 2) as $alert): ?>
                                            <div class="p-2 rounded <?php 
                                                echo $alert['severity'] === 'urgent' ? 'bg-red-500/10 border border-red-500/30' : 
                                                    ($alert['severity'] === 'critical' ? 'bg-orange-500/10 border border-orange-500/30' : 'bg-yellow-500/10 border border-yellow-500/30'); 
                                            ?>">
                                                <p class="text-xs font-medium <?php 
                                                    echo $alert['severity'] === 'urgent' ? 'text-red-300' : 
                                                        ($alert['severity'] === 'critical' ? 'text-orange-300' : 'text-yellow-300'); 
                                                ?>">
                                                    <?php echo htmlspecialchars(getShortWarning($alert)); ?>
                                                </p>
                                            </div>
                                            <?php endforeach; ?>
                                            
                                            <!-- Hidden alerts -->
                                            <div class="hidden-alerts-<?php echo $employee['id']; ?>" style="display: none;">
                                                <?php foreach (array_slice($employee['alerts'], 2) as $alert): ?>
                                                <div class="p-2 rounded mt-1 <?php 
                                                    echo $alert['severity'] === 'urgent' ? 'bg-red-500/10 border border-red-500/30' : 
                                                        ($alert['severity'] === 'critical' ? 'bg-orange-500/10 border border-orange-500/30' : 'bg-yellow-500/10 border border-yellow-500/30'); 
                                                ?>">
                                                    <p class="text-xs font-medium <?php 
                                                        echo $alert['severity'] === 'urgent' ? 'text-red-300' : 
                                                            ($alert['severity'] === 'critical' ? 'text-orange-300' : 'text-yellow-300'); 
                                                    ?>">
                                                        <?php echo htmlspecialchars(getShortWarning($alert)); ?>
                                                    </p>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <?php if (count($employee['alerts']) > 2): ?>
                                            <div class="text-center">
                                                <button type="button" 
                                                    onclick="toggleAlerts(<?php echo $employee['id']; ?>, <?php echo count($employee['alerts']) - 2; ?>)"
                                                    id="show-more-btn-<?php echo $employee['id']; ?>"
                                                    class="px-3 py-1 bg-slate-600/30 hover:bg-slate-600/50 text-slate-300 hover:text-white text-xs rounded-full transition-all cursor-pointer">
                                                    +<?php echo count($employee['alerts']) - 2; ?> more
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                <!-- Action Button -->
                                    <button class="w-full <?php 
                                        echo $employee['priority'] === 'urgent' ? 'bg-red-600 hover:bg-red-700' : 
                                            ($employee['priority'] === 'critical' ? 'bg-orange-600 hover:bg-orange-700' :
                                            ($employee['priority'] === 'moderate' ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-blue-600 hover:bg-blue-700')); 
                                    ?> text-white px-3 py-2 rounded-lg transition-colors flex items-center justify-center font-medium text-sm" 
                                            onclick="openAlertModal(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name']); ?>', '<?php echo $employee['priority']; ?>', <?php echo $employee['total_remaining']; ?>)">
                                        <i class="fas fa-bell mr-2"></i>
                                        Send Alert
                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

    <!-- Enhanced Leave Maximization Alert Modal -->
    <div id="alertModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4 hidden overflow-y-auto">
        <div class="bg-slate-800/95 backdrop-blur-sm rounded-2xl border border-slate-700 max-w-3xl w-full max-h-[95vh] shadow-2xl transform transition-all duration-300 scale-95 opacity-0 flex flex-col my-4" id="modalContent">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-700 bg-gradient-to-r from-slate-800/50 to-slate-700/30 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-bell text-white text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Send Leave Alert</h3>
                            <p class="text-slate-400 text-xs">Send personalized leave reminder to employee</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <!-- Priority Indicator -->
                        <div id="modal_priority_indicator" class="px-3 py-1 rounded-full text-xs font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30">
                            <i class="fas fa-flag mr-1"></i>
                            <span id="modal_priority_text">MODERATE PRIORITY</span>
                        </div>
                        <button type="button" class="w-8 h-8 bg-slate-700/50 hover:bg-slate-600/50 rounded-lg flex items-center justify-center text-slate-400 hover:text-white transition-all duration-200" onclick="closeAlertModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Body -->
            <form method="POST" action="leave_alerts.php" id="alertForm" class="flex flex-col flex-1 min-h-0">
                <div class="flex-1 overflow-y-auto modal-scroll">
                    <input type="hidden" name="action" value="send_alert">
                    <input type="hidden" name="employee_id" id="modal_employee_id">
                    
                    <!-- Employee Info Card -->
                    <div class="p-6 bg-slate-700/30 rounded-xl border border-slate-600/30">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gradient-to-r from-primary to-accent rounded-lg flex items-center justify-center">
                                <i class="fas fa-user text-white text-lg"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-semibold text-white" id="modal_employee_name">Employee Name</h4>
                                <p class="text-slate-400 text-sm" id="modal_employee_dept">Department</p>
                                <div class="flex items-center space-x-3 mt-1">
                                    <span class="px-2 py-1 bg-yellow-500/20 text-yellow-400 text-xs rounded-full font-medium" id="modal_urgency_level">LOW PRIORITY</span>
                                    <span class="text-slate-400 text-xs" id="modal_utilization">Utilization: 0%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Message Templates -->
                    <div class="px-6 py-4 border-b border-slate-700">
                        <label class="block text-sm font-medium text-slate-300 mb-3">Choose a Template</label>
                        <div class="space-y-2">
                            <button type="button" onclick="selectTemplate('low_utilization')" 
                                class="w-full p-3 bg-slate-700/30 hover:bg-orange-500/20 border border-slate-600 hover:border-orange-500 rounded-lg text-left transition-all">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-orange-500/20 rounded flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-chart-line text-orange-400 text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-white font-medium text-sm">Low Utilization Reminder</div>
                                        <div class="text-slate-400 text-xs">Encourage employee to use leave days</div>
                                    </div>
                                </div>
                            </button>
                            
                            <button type="button" onclick="selectTemplate('year_end')" 
                                class="w-full p-3 bg-slate-700/30 hover:bg-red-500/20 border border-slate-600 hover:border-red-500 rounded-lg text-left transition-all">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-red-500/20 rounded flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-red-400 text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-white font-medium text-sm">Year-End Warning</div>
                                        <div class="text-slate-400 text-xs">Urgent reminder about expiring credits</div>
                                    </div>
                                </div>
                            </button>
                            
                            <button type="button" onclick="selectTemplate('friendly_reminder')" 
                                class="w-full p-3 bg-slate-700/30 hover:bg-green-500/20 border border-slate-600 hover:border-green-500 rounded-lg text-left transition-all">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-green-500/20 rounded flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-smile text-green-400 text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-white font-medium text-sm">Friendly Reminder</div>
                                        <div class="text-slate-400 text-xs">Gentle reminder about leave balance</div>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Hidden fields for form submission -->
                    <input type="hidden" name="alert_type" id="alert_type" value="balance_reminder">
                    <input type="hidden" name="priority_level" id="priority_level" value="moderate">
                    <input type="hidden" name="alert_category" id="alert_category" value="utilization">

                    <!-- Message Editor -->
                    <div class="px-6 py-4">
                        <label for="message" class="block text-sm font-medium text-slate-300 mb-2">
                            Message Content
                        </label>
                        <div class="relative">
                            <textarea name="message" id="message" rows="6" 
                                placeholder="Click a template above or type your message..."
                                class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-4 py-3 text-white text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent resize-none transition-all duration-200"></textarea>
                        </div>
                        <p class="text-slate-400 text-xs mt-2">
                            <span id="charCount">0</span> characters
                        </p>
                    </div>


                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-slate-700 bg-slate-800 rounded-b-2xl flex-shrink-0">
                    <div class="flex justify-between items-center">
                        <!-- Character Count -->
                        <div class="text-sm text-slate-400">
                            <span id="footerCharCount">0 characters</span>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex items-center space-x-3">
                            <button type="button" onclick="closeAlertModal()" 
                                class="px-6 py-2 bg-slate-600 hover:bg-slate-500 text-white rounded-lg transition-colors text-sm font-medium">
                                Cancel
                            </button>
                            <button type="submit" id="sendButton" 
                                class="px-6 py-2 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white rounded-lg transition-all duration-200 flex items-center text-sm font-medium shadow-lg">
                                <i class="fas fa-paper-plane mr-2"></i>Send Alert
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <script>
        // Toggle alerts visibility
        function toggleAlerts(employeeId, count) {
            const hiddenAlerts = document.querySelector('.hidden-alerts-' + employeeId);
            const button = document.getElementById('show-more-btn-' + employeeId);
            
            if (hiddenAlerts.style.display === 'none') {
                hiddenAlerts.style.display = 'block';
                button.innerHTML = 'Show less';
            } else {
                hiddenAlerts.style.display = 'none';
                button.innerHTML = '+' + count + ' more';
            }
        }
        
        // Open alert modal with enhanced functionality
        function openAlertModal(employeeId, employeeName, priority = 'low', totalRemaining = 0) {
            console.log('Opening enhanced modal for employee:', employeeId, employeeName, priority, totalRemaining);
            
            // Validate input parameters
            if (!employeeId || !employeeName) {
                console.error('Invalid employee data provided');
                showStyledAlert('Error: Invalid employee data. Please try again.', 'error');
                return;
            }
            
            // Check if elements exist
            const modal = document.getElementById('alertModal');
            const modalContent = document.getElementById('modalContent');
            const employeeIdField = document.getElementById('modal_employee_id');
            const employeeNameField = document.getElementById('modal_employee_name');
            const employeeDeptField = document.getElementById('modal_employee_dept');
            const urgencyLevelField = document.getElementById('modal_urgency_level');
            const utilizationField = document.getElementById('modal_utilization');
            
            if (!modal || !employeeIdField || !employeeNameField) {
                console.error('Modal elements not found');
                showStyledAlert('Error: Modal elements not found. Please refresh the page.', 'error');
                return;
            }
            
            // Set employee data
            employeeIdField.value = employeeId;
            employeeNameField.textContent = employeeName;
            if (employeeDeptField) employeeDeptField.textContent = 'Department'; // You can enhance this with actual department data
            if (urgencyLevelField) urgencyLevelField.textContent = priority.toUpperCase() + ' PRIORITY';
            if (utilizationField) utilizationField.textContent = `Utilization: ${totalRemaining} days remaining`;
            
            // Update priority indicator in modal header
            const priorityIndicator = document.getElementById('modal_priority_indicator');
            const priorityText = document.getElementById('modal_priority_text');
            if (priorityIndicator && priorityText) {
                // Remove existing classes
                priorityIndicator.className = 'px-3 py-1 rounded-full text-xs font-medium border';
                
                // Set new classes based on priority
                switch(priority) {
                    case 'urgent':
                        priorityIndicator.classList.add('bg-red-500/20', 'text-red-400', 'border-red-500/30');
                        priorityText.textContent = 'URGENT PRIORITY';
                        break;
                    case 'critical':
                        priorityIndicator.classList.add('bg-orange-500/20', 'text-orange-400', 'border-orange-500/30');
                        priorityText.textContent = 'CRITICAL PRIORITY';
                        break;
                    case 'moderate':
                        priorityIndicator.classList.add('bg-yellow-500/20', 'text-yellow-400', 'border-yellow-500/30');
                        priorityText.textContent = 'MODERATE PRIORITY';
                        break;
                    default:
                        priorityIndicator.classList.add('bg-blue-500/20', 'text-blue-400', 'border-blue-500/30');
                        priorityText.textContent = 'LOW PRIORITY';
                }
            }
            
            // Auto-select alert type and priority based on employee priority and set default message
            const alertTypeField = document.getElementById('alert_type');
            const priorityField = document.getElementById('priority_level');
            const categoryField = document.getElementById('alert_category');
            const messageField = document.getElementById('message');
            
            if (alertTypeField && priorityField && categoryField && messageField) {
                let defaultMessage = '';
                
                switch(priority) {
                    case 'urgent':
                        alertTypeField.value = 'year_end_warning';
                        priorityField.value = 'urgent';
                        categoryField.value = 'year_end';
                        defaultMessage = 'Subject: Advisory on Year-End Leave Credit Forfeiture\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour leave credits will expire on December 31st and are subject to forfeiture.\n\nAccordingly, you are advised to schedule and utilize your leave credits within the prescribed period to avoid the following actions:\n\n• Forfeiture of unused leave days at year-end;\n• Non-crediting of excess leave balances; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office';
                        break;
                    case 'critical':
                        alertTypeField.value = 'low_utilization';
                        priorityField.value = 'critical';
                        categoryField.value = 'utilization';
                        defaultMessage = 'Subject: Advisory on Leave Credit Maximization\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour leave credits have reached or are nearing the maximum allowable accumulation.\n\nAccordingly, you are advised to schedule and utilize your excess leave credits within the prescribed period to avoid the following actions:\n\n• Application of Compulsory/Forced Leave, in accordance with CSC rules;\n• Non-crediting or forfeiture of leave days exceeding the allowable limit; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office';
                        break;
                    case 'moderate':
                        alertTypeField.value = 'balance_reminder';
                        priorityField.value = 'moderate';
                        categoryField.value = 'utilization';
                        defaultMessage = 'Subject: Advisory on Leave Credit Maximization\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour leave credits have reached or are nearing the maximum allowable accumulation.\n\nAccordingly, you are advised to schedule and utilize your excess leave credits within the prescribed period to avoid the following actions:\n\n• Application of Compulsory/Forced Leave, in accordance with CSC rules;\n• Non-crediting or forfeiture of leave days exceeding the allowable limit; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office';
                        break;
                    default:
                        alertTypeField.value = 'balance_reminder';
                        priorityField.value = 'low';
                        categoryField.value = 'utilization';
                        defaultMessage = 'Subject: Advisory on Leave Credit Maximization\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour leave credits have reached or are nearing the maximum allowable accumulation.\n\nAccordingly, you are advised to schedule and utilize your excess leave credits within the prescribed period to avoid the following actions:\n\n• Application of Compulsory/Forced Leave, in accordance with CSC rules;\n• Non-crediting or forfeiture of leave days exceeding the allowable limit; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office';
                }
                
                // Set the default message
                messageField.value = defaultMessage;
                updateCharCount();
            }
            
            // Show modal with animation
            modal.classList.remove('hidden');
            setTimeout(() => {
                if (modalContent) {
                    modalContent.style.transform = 'scale(1)';
                    modalContent.style.opacity = '1';
                }
            }, 10);
            
            console.log('Enhanced modal opened successfully');
        }

        // Close alert modal with animation
        function closeAlertModal() {
            const modal = document.getElementById('alertModal');
            const modalContent = document.getElementById('modalContent');
            
            if (modalContent) {
                modalContent.style.transform = 'scale(0.95)';
                modalContent.style.opacity = '0';
            }
            
            setTimeout(() => {
                modal.classList.add('hidden');
                // Reset form
                document.getElementById('alertForm').reset();
            }, 300);
        }


        // Select message template
        function selectTemplate(templateType) {
            const messageField = document.getElementById('message');
            const alertTypeField = document.getElementById('alert_type');
            
            let message = '';
            
            switch(templateType) {
                case 'low_utilization':
                    alertTypeField.value = 'low_utilization';
                    message = `Subject: Advisory on Leave Credit Maximization

In compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:

Your leave credits have reached or are nearing the maximum allowable accumulation.

Accordingly, you are advised to schedule and utilize your excess leave credits within the prescribed period to avoid the following actions:

• Application of Compulsory/Forced Leave, in accordance with CSC rules;
• Non-crediting or forfeiture of leave days exceeding the allowable limit; and
• Administrative adjustment of your leave balance based on government accounting requirements.

You are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.

For concerns or clarifications, you may contact the HRMO during regular office hours.

Thank you for your cooperation.

Human Resource Management Office`;
                    break;
                    
                case 'year_end':
                    alertTypeField.value = 'year_end_warning';
                    message = `Subject: Advisory on Year-End Leave Credit Forfeiture

In compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:

Your leave credits will expire on December 31st and are subject to forfeiture.

Accordingly, you are advised to schedule and utilize your leave credits within the prescribed period to avoid the following actions:

• Forfeiture of unused leave days at year-end;
• Non-crediting of excess leave balances; and
• Administrative adjustment of your leave balance based on government accounting requirements.

You are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.

For concerns or clarifications, you may contact the HRMO during regular office hours.

Thank you for your cooperation.

Human Resource Management Office`;
                    break;
                    
                case 'friendly_reminder':
                    alertTypeField.value = 'balance_reminder';
                    message = `Subject: Advisory on Leave Credit Maximization

In compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:

Your leave credits have reached or are nearing the maximum allowable accumulation.

Accordingly, you are advised to schedule and utilize your excess leave credits within the prescribed period to avoid the following actions:

• Application of Compulsory/Forced Leave, in accordance with CSC rules;
• Non-crediting or forfeiture of leave days exceeding the allowable limit; and
• Administrative adjustment of your leave balance based on government accounting requirements.

You are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.

For concerns or clarifications, you may contact the HRMO during regular office hours.

Thank you for your cooperation.

Human Resource Management Office`;
                    break;
            }
            
            messageField.value = message;
            updateCharCount();
        }

        // Preview message

        // Update character count
        function updateCharCount() {
            const message = document.getElementById('message');
            const charCount = document.getElementById('charCount');
            const footerCharCount = document.getElementById('footerCharCount');
            
            if (charCount) {
                charCount.textContent = message.value.length;
            }
            if (footerCharCount) {
                footerCharCount.textContent = message.value.length + ' characters';
            }
        }



        // Auto-fill message based on alert type
        document.addEventListener('DOMContentLoaded', function() {
            const alertTypeField = document.getElementById('alert_type');
            const messageField = document.getElementById('message');
            const priorityField = document.getElementById('priority_level');
            
            if (alertTypeField && messageField) {
                alertTypeField.addEventListener('change', function() {
                    const alertType = this.value;
                    let message = '';
                    
                    switch(alertType) {
                        case 'low_utilization':
                            message = 'Subject: Advisory on Leave Credit Maximization\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour leave credits have reached or are nearing the maximum allowable accumulation.\n\nAccordingly, you are advised to schedule and utilize your excess leave credits within the prescribed period to avoid the following actions:\n\n• Application of Compulsory/Forced Leave, in accordance with CSC rules;\n• Non-crediting or forfeiture of leave days exceeding the allowable limit; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office';
                            break;
                        case 'year_end_warning':
                            message = 'Subject: Advisory on Year-End Leave Credit Forfeiture\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour leave credits will expire on December 31st and are subject to forfeiture.\n\nAccordingly, you are advised to schedule and utilize your leave credits within the prescribed period to avoid the following actions:\n\n• Forfeiture of unused leave days at year-end;\n• Non-crediting of excess leave balances; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office';
                            break;
                        case 'balance_reminder':
                            message = 'Subject: Advisory on Leave Credit Maximization\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour leave credits have reached or are nearing the maximum allowable accumulation.\n\nAccordingly, you are advised to schedule and utilize your excess leave credits within the prescribed period to avoid the following actions:\n\n• Application of Compulsory/Forced Leave, in accordance with CSC rules;\n• Non-crediting or forfeiture of leave days exceeding the allowable limit; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office';
                            break;
                        case 'custom':
                            message = '';
                            break;
                    }
                    
                    messageField.value = message;
                    updateCharCount();
                });
                
                // Add character count listener
                messageField.addEventListener('input', updateCharCount);
            }
            
            // Update priority indicator when priority changes
            if (priorityField) {
                priorityField.addEventListener('change', function() {
                    updatePriorityIndicator(this.value);
                });
            }
        });
        
        // Function to update priority indicator
        function updatePriorityIndicator(priority) {
            const priorityIndicator = document.getElementById('modal_priority_indicator');
            const priorityText = document.getElementById('modal_priority_text');
            
            if (priorityIndicator && priorityText) {
                // Remove existing classes
                priorityIndicator.className = 'px-3 py-1 rounded-full text-xs font-medium border';
                
                // Set new classes based on priority
                switch(priority) {
                    case 'urgent':
                        priorityIndicator.classList.add('bg-red-500/20', 'text-red-400', 'border-red-500/30');
                        priorityText.textContent = 'URGENT PRIORITY';
                        break;
                    case 'critical':
                        priorityIndicator.classList.add('bg-orange-500/20', 'text-orange-400', 'border-orange-500/30');
                        priorityText.textContent = 'CRITICAL PRIORITY';
                        break;
                    case 'moderate':
                        priorityIndicator.classList.add('bg-yellow-500/20', 'text-yellow-400', 'border-yellow-500/30');
                        priorityText.textContent = 'MODERATE PRIORITY';
                        break;
                    default:
                        priorityIndicator.classList.add('bg-blue-500/20', 'text-blue-400', 'border-blue-500/30');
                        priorityText.textContent = 'LOW PRIORITY';
                }
            }
        }

        // AJAX form submission with real-time feedback
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[method="POST"]');
            if (form) {
                console.log('Form found, adding AJAX submit listener');
                form.addEventListener('submit', function(e) {
                    e.preventDefault(); // Prevent default form submission
                    console.log('AJAX form submitted');
                    
                    // Validate form fields before submission
                    const employeeId = this.querySelector('input[name="employee_id"]').value;
                    const alertType = document.getElementById('alert_type').value;
                    const message = this.querySelector('textarea[name="message"]').value;
                    
                    // Check if form is properly filled
                    if (!employeeId) {
                        showStyledAlert('Employee ID is missing. Please close and reopen the modal.', 'error');
                        console.log('Form validation failed: missing employee ID');
                        return false;
                    }
                    
                    if (!alertType) {
                        showStyledAlert('Alert type is missing. Please select a template.', 'warning');
                        console.log('Form validation failed: missing alert type');
                        return false;
                    }
                    
                    if (!message.trim()) {
                        showStyledAlert('Please enter a message or select a template.', 'warning');
                        console.log('Form validation failed: empty message');
                        return false;
                    }
                    
                    // Show loading state
                    console.log('Processing leave alert via AJAX...');
                    
                    // Show loading state on button
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
                        submitBtn.disabled = true;
                    }
                    
                    // Send AJAX request
                    sendAlertAjax(employeeId, alertType, message, submitBtn);
                });
            } else {
                console.error('Form not found!');
            }
        });

        // Send alert via AJAX
        async function sendAlertAjax(employeeId, alertType, message, submitBtn) {
            try {
                // Get additional form data
                const priority = document.getElementById('priority_level')?.value || 'moderate';
                const category = document.getElementById('alert_category')?.value || 'utilization';
                
                const response = await fetch('/ELMS/api/send_alert.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        employee_id: employeeId,
                        alert_type: alertType,
                        message: message,
                        priority: priority,
                        alert_category: category
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Show success message
                    showAlertSuccess(`Alert sent successfully to ${data.employee_name}!`);
                    
                    // Close modal
                    closeAlertModal();
                    
                    // Refresh page to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlertError('Error sending alert: ' + data.error);
                }
            } catch (error) {
                console.error('Error sending alert:', error);
                showAlertError('Network error sending alert. Please try again.');
            } finally {
                // Reset button state
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane mr-1 text-xs"></i>Send Alert';
                    submitBtn.disabled = false;
                }
            }
        }

        // Show success alert
        function showAlertSuccess(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'fixed top-20 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full bg-green-600 text-white';
            alertDiv.innerHTML = `
                <div class="flex items-center space-x-2">
                    <i class="fas fa-check-circle"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Animate in
            setTimeout(() => {
                alertDiv.classList.remove('translate-x-full');
            }, 100);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                alertDiv.classList.add('translate-x-full');
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 300);
            }, 3000);
        }

        // Show error alert
        function showAlertError(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'fixed top-20 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full bg-red-600 text-white';
            alertDiv.innerHTML = `
                <div class="flex items-center space-x-2">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Animate in
            setTimeout(() => {
                alertDiv.classList.remove('translate-x-full');
            }, 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                alertDiv.classList.add('translate-x-full');
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 300);
            }, 5000);
        }

        // Function to show alerts
        function showAlert(type, message) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert at top of main content
            const mainContent = document.querySelector('.main-content .container-fluid');
            mainContent.insertBefore(alertDiv, mainContent.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Function to update employee card after sending alert
        function updateEmployeeCard(employeeId) {
            const employeeCard = document.querySelector(`[data-employee-id="${employeeId}"]`).closest('.employee-card');
            if (employeeCard) {
                // Add a visual indicator that alert was sent
                const button = employeeCard.querySelector('button');
                button.innerHTML = '<i class="fas fa-check me-2"></i>Alert Sent';
                button.classList.remove('btn-warning');
                button.classList.add('btn-success');
                button.disabled = true;
                
                // Reset after 3 seconds
                setTimeout(() => {
                    button.innerHTML = '<i class="fas fa-bell me-2"></i>Send Alert';
                    button.classList.remove('btn-success');
                    button.classList.add('btn-warning');
                    button.disabled = false;
                }, 3000);
            }
        }


        // Department Toggle Functionality
        function toggleDepartment(deptId) {
            const content = document.getElementById('dept-' + deptId);
            const chevron = document.querySelector('.dept-chevron-' + deptId);
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                chevron.style.transform = 'rotate(180deg)';
            } else {
                content.style.display = 'none';
                chevron.style.transform = 'rotate(0deg)';
            }
        }
        
        function expandAllDepartments() {
            document.querySelectorAll('.dept-content').forEach(content => {
                content.style.display = 'block';
            });
            document.querySelectorAll('[class*="dept-chevron-"]').forEach(chevron => {
                chevron.style.transform = 'rotate(180deg)';
            });
        }
        
        function collapseAllDepartments() {
            document.querySelectorAll('.dept-content').forEach(content => {
                content.style.display = 'none';
            });
            document.querySelectorAll('[class*="dept-chevron-"]').forEach(chevron => {
                chevron.style.transform = 'rotate(0deg)';
            });
        }
        
        // Initialize - collapse all departments by default
        document.addEventListener('DOMContentLoaded', function() {
            collapseAllDepartments();
        });

        // Simple client-side filtering for employees
        function filterEmployees() {
            const q = (document.getElementById('filterSearch')?.value || '').trim().toLowerCase();
            const p = (document.getElementById('filterPriority')?.value || '').toLowerCase();
            const d = (document.getElementById('filterDepartment')?.value || '').toLowerCase();
            
            document.querySelectorAll('.employee-item').forEach(card => {
                const name = (card.getAttribute('data-name') || '').toLowerCase();
                const pri = (card.getAttribute('data-priority') || '').toLowerCase();
                const dept = (card.getAttribute('data-department') || '').toLowerCase();
                const match = (!q || name.includes(q)) && (!p || pri === p) && (!d || dept === d);
                card.style.display = match ? '' : 'none';
            });
        }
        function clearFilters() {
            const s = document.getElementById('filterSearch');
            const p = document.getElementById('filterPriority');
            const d = document.getElementById('filterDepartment');
            if (s) s.value = '';
            if (p) p.value = '';
            if (d) d.value = '';
            filterEmployees();
        }

        // Function to fetch pending leave count
        function fetchPendingLeaveCount() {
            fetch('api/get_pending_leave_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.getElementById('pendingLeaveBadge');
                        if (data.count > 0) {
                            badge.textContent = data.count;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching pending leave count:', error);
                });
        }

        // Fetch pending leave count on page load
        fetchPendingLeaveCount();

        // Update pending leave count every 30 seconds
        setInterval(fetchPendingLeaveCount, 30000);
    </script>
    <script src="../../../../assets/js/modal-alert.js"></script>
            </div>
<?php include '../../../../includes/admin_footer.php'; ?>

