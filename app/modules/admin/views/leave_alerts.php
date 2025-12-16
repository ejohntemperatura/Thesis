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
        
        if (empty($employee_id) || empty($alert_type) || empty($message)) {
            $_SESSION['error'] = "Please fill in all required fields before sending the alert.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO leave_alerts (employee_id, alert_type, message, sent_by, is_read, created_at) 
                    VALUES (?, ?, ?, ?, 0, NOW())
                ");
                $stmt->execute([$employee_id, $alert_type, $message, $_SESSION['user_id']]);
                
                $_SESSION['success'] = "1-Year Expiry alert sent successfully!";
            } catch (Exception $e) {
                $_SESSION['error'] = "Error sending alert: " . $e->getMessage();
            }
        }
    }
}

// Function to generate concise warning message for 1-year expiry types only
function getShortWarning($alert) {
    $leaveName = isset($alert['leave_name']) ? $alert['leave_name'] : 'Leave';
    $remaining = isset($alert['remaining']) ? $alert['remaining'] : (isset($alert['unused_days']) ? $alert['unused_days'] : 0);
    $daysUntilExpiry = isset($alert['days_until_forfeiture']) ? $alert['days_until_forfeiture'] : 0;
    
    switch($alert['type']) {
        // Force Leave 1-Year Expiry Alerts
        case 'force_leave_1_year_expiry_critical':
            $expiryDateText = isset($alert['expiry_date']) ? ' on ' . date('M j, Y', strtotime($alert['expiry_date'])) : '';
            $utilizationText = isset($alert['utilization']) ? " ({$alert['utilization']}% used)" : '';
            return "🚨 CRITICAL: {$remaining} Force Leave days expire in {$daysUntilExpiry} days{$expiryDateText}{$utilizationText} - 1-YEAR EXPIRY RULE - Use immediately!";
        case 'force_leave_1_year_expiry_warning':
            $expiryDateText = isset($alert['expiry_date']) ? ' on ' . date('M j, Y', strtotime($alert['expiry_date'])) : '';
            $utilizationText = isset($alert['utilization']) ? " ({$alert['utilization']}% used)" : '';
            return "⚠️ URGENT: {$remaining} Force Leave days expire in {$daysUntilExpiry} days{$expiryDateText}{$utilizationText} - 1-YEAR EXPIRY RULE - Plan usage now!";
            
        // CTO 1-Year Expiry Alerts
        case 'cto_1_year_expiry_critical':
            $expiryDateText = isset($alert['expiry_date']) ? ' on ' . date('M j, Y', strtotime($alert['expiry_date'])) : '';
            $utilizationText = isset($alert['utilization']) ? " ({$alert['utilization']}% used)" : '';
            return "🚨 CRITICAL: {$remaining} CTO hours expire in {$daysUntilExpiry} days{$expiryDateText}{$utilizationText} - 1-YEAR EXPIRY RULE - Don't lose overtime compensation!";
        case 'cto_1_year_expiry_warning':
            $expiryDateText = isset($alert['expiry_date']) ? ' on ' . date('M j, Y', strtotime($alert['expiry_date'])) : '';
            $utilizationText = isset($alert['utilization']) ? " ({$alert['utilization']}% used)" : '';
            return "⚠️ URGENT: {$remaining} CTO hours expire in {$daysUntilExpiry} days{$expiryDateText}{$utilizationText} - 1-YEAR EXPIRY RULE - Schedule time off now!";
            
        // SLP 1-Year Expiry Alerts
        case 'slp_1_year_expiry_critical':
            $expiryDateText = isset($alert['expiry_date']) ? ' on ' . date('M j, Y', strtotime($alert['expiry_date'])) : '';
            $utilizationText = isset($alert['utilization']) ? " ({$alert['utilization']}% used)" : '';
            return "🚨 CRITICAL: {$remaining} SLP days expire in {$daysUntilExpiry} days{$expiryDateText}{$utilizationText} - 1-YEAR EXPIRY RULE - Non-cumulative, use now!";
        case 'slp_1_year_expiry_warning':
            $expiryDateText = isset($alert['expiry_date']) ? ' on ' . date('M j, Y', strtotime($alert['expiry_date'])) : '';
            $utilizationText = isset($alert['utilization']) ? " ({$alert['utilization']}% used)" : '';
            return "⚠️ URGENT: {$remaining} SLP days expire in {$daysUntilExpiry} days{$expiryDateText}{$utilizationText} - 1-YEAR EXPIRY RULE - Special privilege expires!";
            
        default:
            return "⚠️ 1-Year Expiry Notice: {$leaveName} requires immediate attention";
    }
}

// Get enhanced alert data using the new service
$alertData = $alertService->getUrgentAlerts(50);
$alertStats = $alertService->getAlertStatistics();

// Process alert data for display and group by department - Show ALL employees
$employees = [];
$departmentGroups = [];
foreach ($alertData as $employeeId => $data) {
    $employee = $data['employee'];
    $alerts = $data['alerts'];
    $priority = $data['priority'];
    
    // Calculate statistics for 1-year expiry types only
    $forceLeaveBalance = $employee['mandatory_leave_balance'] ?? 0;
    $forceLeaveUsed = $employee['mandatory_used'] ?? 0;
    $forceLeaveRemaining = max(0, $forceLeaveBalance - $forceLeaveUsed);
    
    $ctoBalance = $employee['cto_balance'] ?? 0;
    $ctoUsed = $employee['cto_used'] ?? 0;
    $ctoRemaining = max(0, $ctoBalance - $ctoUsed);
    
    $slpBalance = $employee['special_leave_privilege_balance'] ?? 0;
    $slpUsed = $employee['special_privilege_used'] ?? 0;
    $slpRemaining = max(0, $slpBalance - $slpUsed);
    
    $totalRemaining = $forceLeaveRemaining + $ctoRemaining + $slpRemaining;
    $totalAllocated = $forceLeaveBalance + $ctoBalance + $slpBalance;
    $totalUsed = $forceLeaveUsed + $ctoUsed + $slpUsed;
    
    $employee['force_leave_remaining'] = $forceLeaveRemaining;
    $employee['cto_remaining'] = $ctoRemaining;
    $employee['slp_remaining'] = $slpRemaining;
    $employee['total_allocated'] = $totalAllocated;
    $employee['total_used'] = $totalUsed;
    $employee['total_remaining'] = $totalRemaining;
    $employee['overall_utilization'] = $totalAllocated > 0 ? round(($totalUsed / $totalAllocated) * 100, 1) : 0;
    
    // Calculate individual utilization percentages
    $employee['force_leave_utilization'] = $forceLeaveBalance > 0 ? round(($forceLeaveUsed / $forceLeaveBalance) * 100, 1) : 0;
    $employee['cto_utilization'] = $ctoBalance > 0 ? round(($ctoUsed / $ctoBalance) * 100, 1) : 0;
    $employee['slp_utilization'] = $slpBalance > 0 ? round(($slpUsed / $slpBalance) * 100, 1) : 0;
    
    $employee['priority'] = $priority;
    $employee['alerts'] = $alerts;
    
    // Calculate days remaining in year
    $currentDate = new DateTime();
    $yearEnd = new DateTime(date('Y') . '-12-31');
    $daysRemaining = $currentDate->diff($yearEnd)->days;
    $employee['days_remaining_in_year'] = $daysRemaining;
    
    // Generate urgency message based on priority - Include employees without alerts
    switch ($priority) {
        case 'critical':
            $employee['urgency_level'] = 'high';
            $employee['urgency_message'] = '🚨 CRITICAL: 1-Year expiry - Immediate action required!';
            break;
        case 'urgent':
            $employee['urgency_level'] = 'high';
            $employee['urgency_message'] = '⚠️ URGENT: 1-Year expiry - High priority attention needed!';
            break;
        case 'none':
            $employee['urgency_level'] = 'low';
            if ($totalAllocated > 0) {
                $employee['urgency_message'] = '✅ GOOD: All 1-year expiry credits are being utilized well.';
            } else {
                $employee['urgency_message'] = '📋 INFO: No 1-year expiry credits allocated.';
            }
            break;
        default:
            $employee['urgency_level'] = 'medium';
            $employee['urgency_message'] = '📋 MODERATE: 1-Year expiry - Planning needed.';
    }
    
    // Include ALL employees, not just those with alerts
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
$page_title = "1-Year Expiry Alerts";

// Include admin header
include '../../../../includes/admin_header.php';
?>
    
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
        <i class="fas fa-hourglass-end text-3xl text-red-400 mr-2"></i>
        <div>
            <h1 class="text-3xl font-bold text-white mb-1">1-Year Expiry Alerts</h1>
            <p class="text-slate-400">Force Leave, CTO, and SLP credits expiring on December 31st</p>
        </div>
    </div>
</div>

<!-- 1-Year Expiry Alert Banner -->
<?php
// Count specific 1-year expiry alerts
$forceLeaveExpiry = 0;
$ctoExpiry = 0;
$slpExpiry = 0;
$totalOneYearExpiry = 0;

foreach ($employees as $employee) {
    foreach ($employee['alerts'] as $alert) {
        if (strpos($alert['type'], 'force_leave_1_year_expiry') !== false) {
            $forceLeaveExpiry++;
            $totalOneYearExpiry++;
        } elseif (strpos($alert['type'], 'cto_1_year_expiry') !== false) {
            $ctoExpiry++;
            $totalOneYearExpiry++;
        } elseif (strpos($alert['type'], 'slp_1_year_expiry') !== false) {
            $slpExpiry++;
            $totalOneYearExpiry++;
        }
    }
}

if ($totalOneYearExpiry > 0):
?>
<div class="bg-gradient-to-r from-red-900/50 to-orange-900/50 border-2 border-red-500/50 rounded-2xl p-6 mb-8">
    <div class="flex items-center gap-4">
        <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center">
            <i class="fas fa-exclamation-triangle text-red-400 text-2xl animate-pulse"></i>
        </div>
        <div class="flex-1">
            <h2 class="text-2xl font-bold text-red-400 mb-2">🚨 1-YEAR EXPIRY RULE ALERTS</h2>
            <p class="text-slate-300 mb-3">
                <strong><?php echo $totalOneYearExpiry; ?></strong> employees have leave credits subject to mandatory 1-year expiry from their grant date that will be forfeited if not used.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php if ($forceLeaveExpiry > 0): ?>
                <div class="bg-slate-800/50 rounded-lg p-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-calendar-xmark text-red-400"></i>
                        <span class="text-white font-semibold"><?php echo $forceLeaveExpiry; ?> Force Leave</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Mandatory leave expiring</p>
                </div>
                <?php endif; ?>
                
                <?php if ($ctoExpiry > 0): ?>
                <div class="bg-slate-800/50 rounded-lg p-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-clock text-purple-400"></i>
                        <span class="text-white font-semibold"><?php echo $ctoExpiry; ?> CTO Credits</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Overtime compensation expiring</p>
                </div>
                <?php endif; ?>
                
                <?php if ($slpExpiry > 0): ?>
                <div class="bg-slate-800/50 rounded-lg p-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-star text-yellow-400"></i>
                        <span class="text-white font-semibold"><?php echo $slpExpiry; ?> SLP Credits</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Special privilege expiring</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Alert Statistics -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8 mb-10">
    <!-- Total Employees -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700/70 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-xs uppercase tracking-wide mb-1">Total Employees</p>
                <h2 class="text-3xl font-bold text-white"><?php echo count($employees); ?></h2>
                <p class="text-slate-400 text-xs mt-1">All employees shown</p>
            </div>
            <div class="w-14 h-14 bg-blue-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-users text-blue-400 text-2xl"></i>
            </div>
        </div>
    </div>
    
    <!-- Critical Alerts -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700/70 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-xs uppercase tracking-wide mb-1">Critical</p>
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
                <p class="text-slate-400 text-xs uppercase tracking-wide mb-1">1-Year Expiry</p>
                <h2 class="text-3xl font-bold text-yellow-400"><?php echo $totalOneYearExpiry; ?></h2>
                <p class="text-slate-400 text-xs mt-1">Credits expiring</p>
            </div>
            <div class="w-14 h-14 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-hourglass-end text-yellow-400 text-2xl"></i>
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
            <h2 class="text-lg font-semibold text-white">All Employees - 1-Year Expiry Status</h2>
            <p class="text-slate-400 text-sm">Force Leave, CTO, and SLP credit utilization and expiry tracking</p>
        </div>
        <div class="text-slate-400 text-sm">
            <span class="text-white font-semibold"><?php echo count($employees); ?></span> employees in <span class="text-white font-semibold"><?php echo count($departmentGroups); ?></span> departments
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
            <h3 class="text-2xl font-bold text-white mb-4">No 1-Year Expiry Alerts!</h3>
            <p class="text-slate-400 mb-6">
                All employees have either used their Force Leave, CTO, and SLP credits or have no balances to expire.
            </p>
            <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4 mb-6">
                <div class="flex items-center justify-center mb-2">
                    <i class="fas fa-hourglass-end text-green-400 mr-2"></i>
                    <span class="text-green-400 font-semibold">1-Year Expiry Status</span>
                </div>
                <p class="text-sm text-slate-300">
                    No Force Leave, CTO, or SLP credits are at risk of forfeiture.
                </p>
            </div>
            <button onclick="window.location.reload()" class="px-6 py-3 bg-primary hover:bg-primary/80 text-white rounded-xl transition-colors flex items-center justify-center mx-auto">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh Data
            </button>
        </div>
    </div>
<?php else: ?>
    <!-- Department Groups -->
    <div class="space-y-6">
        <?php foreach ($departmentGroups as $department => $deptEmployees): ?>
            <div class="bg-slate-800/50 rounded-xl border border-slate-700 overflow-hidden">
                <!-- Department Header -->
                <div class="p-4 border-b border-slate-700">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-red-500/20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-building text-red-400"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($department); ?></h3>
                            <p class="text-slate-400 text-sm"><?php echo count($deptEmployees); ?> employee<?php echo count($deptEmployees) > 1 ? 's' : ''; ?> - 1-year expiry status</p>
                        </div>
                    </div>
                </div>
                
                <!-- Department Employees -->
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4 p-4">
                    <?php foreach ($deptEmployees as $employee): ?>
                        <div class="bg-slate-800 rounded-2xl border-2 <?php 
                            echo $employee['priority'] === 'critical' ? 'border-red-500/50' : 
                                ($employee['priority'] === 'urgent' ? 'border-orange-500/50' : 
                                ($employee['priority'] === 'none' ? 'border-green-500/50' : 'border-slate-600/50')); 
                        ?> overflow-hidden hover:border-slate-600/50 transition-all duration-300">
                            <div class="p-4">
                                <!-- Header -->
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="text-lg font-semibold text-white mb-1"><?php echo htmlspecialchars($employee['name']); ?></h3>
                                        <p class="text-slate-400 text-sm"><?php echo htmlspecialchars($employee['position']); ?></p>
                                    </div>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php 
                                        echo $employee['priority'] === 'critical' ? 'bg-red-500/20 text-red-400 border border-red-500/30' : 
                                            ($employee['priority'] === 'urgent' ? 'bg-orange-500/20 text-orange-400 border border-orange-500/30' : 
                                            ($employee['priority'] === 'none' ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-slate-500/20 text-slate-400 border border-slate-500/30')); 
                                    ?>">
                                        <i class="fas fa-<?php echo $employee['priority'] === 'none' ? 'check-circle' : 'hourglass-end'; ?> mr-1"></i>
                                        <?php echo $employee['priority'] === 'none' ? 'NO ALERTS' : '1-YEAR EXPIRY'; ?>
                                    </span>
                                </div>

                                <!-- 1-Year Expiry Credits -->
                                <div class="mb-4 p-3 rounded-lg <?php 
                                    echo $employee['priority'] === 'none' ? 'bg-green-900/30 border border-green-500/50' : 'bg-red-900/30 border border-red-500/50'; 
                                ?>">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="fas fa-<?php echo $employee['priority'] === 'none' ? 'check-circle' : 'hourglass-end'; ?> <?php 
                                            echo $employee['priority'] === 'none' ? 'text-green-400' : 'text-red-400'; 
                                        ?>"></i>
                                        <span class="<?php echo $employee['priority'] === 'none' ? 'text-green-400' : 'text-red-400'; ?> font-bold text-sm">
                                            <?php echo $employee['priority'] === 'none' ? 'CREDIT STATUS' : 'EXPIRING CREDITS'; ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($employee['force_leave_remaining'] > 0): ?>
                                    <div class="text-xs text-red-300 mb-2">
                                        <div class="flex justify-between items-center mb-1">
                                            <strong>Force Leave:</strong> 
                                            <span><?php echo $employee['force_leave_remaining']; ?> days remaining</span>
                                        </div>
                                        <div class="flex justify-between items-center text-xs">
                                            <span>Utilization:</span>
                                            <span class="<?php echo $employee['force_leave_utilization'] < 30 ? 'text-red-400' : ($employee['force_leave_utilization'] < 70 ? 'text-yellow-400' : 'text-green-400'); ?> font-semibold">
                                                <?php echo $employee['force_leave_utilization']; ?>%
                                            </span>
                                        </div>
                                        <div class="w-full bg-slate-600 rounded-full h-1 mt-1">
                                            <div class="h-1 rounded-full <?php echo $employee['force_leave_utilization'] < 30 ? 'bg-red-500' : ($employee['force_leave_utilization'] < 70 ? 'bg-yellow-500' : 'bg-green-500'); ?>" 
                                                 style="width: <?php echo $employee['force_leave_utilization']; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($employee['cto_remaining'] > 0): ?>
                                    <div class="text-xs text-purple-300 mb-2">
                                        <div class="flex justify-between items-center mb-1">
                                            <strong>CTO:</strong> 
                                            <span><?php echo $employee['cto_remaining']; ?> hours remaining</span>
                                        </div>
                                        <div class="flex justify-between items-center text-xs">
                                            <span>Utilization:</span>
                                            <span class="<?php echo $employee['cto_utilization'] < 30 ? 'text-red-400' : ($employee['cto_utilization'] < 70 ? 'text-yellow-400' : 'text-green-400'); ?> font-semibold">
                                                <?php echo $employee['cto_utilization']; ?>%
                                            </span>
                                        </div>
                                        <div class="w-full bg-slate-600 rounded-full h-1 mt-1">
                                            <div class="h-1 rounded-full <?php echo $employee['cto_utilization'] < 30 ? 'bg-red-500' : ($employee['cto_utilization'] < 70 ? 'bg-yellow-500' : 'bg-green-500'); ?>" 
                                                 style="width: <?php echo $employee['cto_utilization']; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($employee['slp_remaining'] > 0): ?>
                                    <div class="text-xs text-yellow-300 mb-2">
                                        <div class="flex justify-between items-center mb-1">
                                            <strong>SLP:</strong> 
                                            <span><?php echo $employee['slp_remaining']; ?> days remaining</span>
                                        </div>
                                        <div class="flex justify-between items-center text-xs">
                                            <span>Utilization:</span>
                                            <span class="<?php echo $employee['slp_utilization'] < 30 ? 'text-red-400' : ($employee['slp_utilization'] < 70 ? 'text-yellow-400' : 'text-green-400'); ?> font-semibold">
                                                <?php echo $employee['slp_utilization']; ?>%
                                            </span>
                                        </div>
                                        <div class="w-full bg-slate-600 rounded-full h-1 mt-1">
                                            <div class="h-1 rounded-full <?php echo $employee['slp_utilization'] < 30 ? 'bg-red-500' : ($employee['slp_utilization'] < 70 ? 'bg-yellow-500' : 'bg-green-500'); ?>" 
                                                 style="width: <?php echo $employee['slp_utilization']; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($employee['force_leave_remaining'] == 0 && $employee['cto_remaining'] == 0 && $employee['slp_remaining'] == 0): ?>
                                        <div class="text-xs text-green-300">
                                            <div class="flex items-center gap-2 mb-2">
                                                <i class="fas fa-check-circle text-green-400"></i>
                                                <span class="font-semibold">No expiring credits</span>
                                            </div>
                                            <p class="text-slate-400">
                                                <?php if ($employee['total_allocated'] > 0): ?>
                                                    All 1-year expiry credits have been utilized or no remaining balance.
                                                <?php else: ?>
                                                    No Force Leave, CTO, or SLP credits allocated.
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-xs text-red-400 font-semibold mt-2">
                                            ⚠️ Expires in <?php echo $employee['days_remaining_in_year']; ?> days - No carry-over!
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <!-- Overall Utilization Summary -->
                                <div class="mb-4 p-3 bg-slate-700/30 rounded-lg">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="fas fa-chart-pie text-blue-400"></i>
                                        <span class="text-blue-400 font-bold text-sm">OVERALL UTILIZATION</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 text-center">
                                        <div>
                                            <div class="text-lg font-bold text-white"><?php echo $employee['total_remaining']; ?></div>
                                            <div class="text-xs text-slate-400">Days Remaining</div>
                                        </div>
                                        <div>
                                            <div class="text-lg font-bold <?php 
                                                echo $employee['overall_utilization'] < 30 ? 'text-red-400' : 
                                                    ($employee['overall_utilization'] < 70 ? 'text-yellow-400' : 'text-green-400'); 
                                            ?>"><?php echo $employee['overall_utilization']; ?>%</div>
                                            <div class="text-xs text-slate-400">Utilized</div>
                                        </div>
                                    </div>
                                    <div class="w-full bg-slate-600 rounded-full h-2 mt-3">
                                        <div class="h-2 rounded-full <?php 
                                            echo $employee['overall_utilization'] < 30 ? 'bg-red-500' : 
                                                ($employee['overall_utilization'] < 70 ? 'bg-yellow-500' : 'bg-green-500'); 
                                        ?>" style="width: <?php echo $employee['overall_utilization']; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-slate-400 mt-1">
                                        <span>Used: <?php echo $employee['total_used']; ?></span>
                                        <span>Total: <?php echo $employee['total_allocated']; ?></span>
                                    </div>
                                </div>

                                <!-- Urgency Message -->
                                <div class="mb-4 p-3 rounded-lg <?php 
                                    echo $employee['priority'] === 'critical' ? 'bg-red-500/10 border border-red-500/30' : 
                                        ($employee['priority'] === 'urgent' ? 'bg-orange-500/10 border border-orange-500/30' : 
                                        ($employee['priority'] === 'none' ? 'bg-green-500/10 border border-green-500/30' : 'bg-slate-500/10 border border-slate-500/30')); 
                                ?>">
                                    <p class="text-sm font-semibold <?php 
                                        echo $employee['priority'] === 'critical' ? 'text-red-400' : 
                                            ($employee['priority'] === 'urgent' ? 'text-orange-400' : 
                                            ($employee['priority'] === 'none' ? 'text-green-400' : 'text-slate-400')); 
                                    ?>">
                                        <?php echo $employee['urgency_message']; ?>
                                    </p>
                                </div>

                                <!-- Alert Details -->
                                <?php if (!empty($employee['alerts'])): ?>
                                <div class="space-y-2 mb-4">
                                    <?php foreach (array_slice($employee['alerts'], 0, 3) as $alert): ?>
                                        <div class="text-xs p-2 bg-slate-700/50 rounded border-l-2 <?php 
                                            echo $alert['severity'] === 'critical' ? 'border-red-500' : 'border-orange-500'; 
                                        ?>">
                                            <?php echo getShortWarning($alert); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <!-- Send Alert Button -->
                                <?php if ($employee['priority'] !== 'none'): ?>
                                <button class="w-full px-3 py-2 <?php 
                                    echo $employee['priority'] === 'critical' ? 'bg-red-600 hover:bg-red-700' : 'bg-orange-600 hover:bg-orange-700'; 
                                ?> text-white rounded-lg transition-colors flex items-center justify-center font-medium text-sm" 
                                        onclick="openAlertModal(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name']); ?>', '<?php echo $employee['priority']; ?>', <?php echo $employee['total_remaining']; ?>)">
                                    <i class="fas fa-bell mr-2"></i>
                                    Send 1-Year Expiry Alert
                                </button>
                                <?php else: ?>
                                <div class="w-full px-3 py-2 bg-green-600/20 text-green-400 rounded-lg flex items-center justify-center font-medium text-sm border border-green-500/30">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    No Action Required
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Enhanced 1-Year Expiry Alert Modal -->
<div id="alertModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4 hidden overflow-y-auto">
    <div class="bg-slate-800/95 backdrop-blur-sm rounded-2xl border border-slate-700 max-w-3xl w-full max-h-[95vh] shadow-2xl transform transition-all duration-300 scale-95 opacity-0 flex flex-col my-4" id="modalContent">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-700 bg-gradient-to-r from-red-900/50 to-orange-900/50 rounded-t-2xl flex-shrink-0">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-red-500/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-hourglass-end text-red-400 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white" id="modal_employee_name">Send 1-Year Expiry Alert</h3>
                        <p class="text-slate-300 text-sm">Force Leave, CTO & SLP Expiry Notice</p>
                    </div>
                </div>
                <button type="button" class="w-8 h-8 rounded-lg bg-slate-700 hover:bg-slate-600 flex items-center justify-center text-slate-400 hover:text-white transition-all duration-200" onclick="closeAlertModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <form method="POST" class="flex-1 flex flex-col overflow-hidden">
            <input type="hidden" name="action" value="send_alert">
            <input type="hidden" name="employee_id" id="modal_employee_id">

            <!-- Template Selection -->
            <div class="px-6 py-4 border-b border-slate-700 flex-shrink-0">
                <h4 class="text-sm font-semibold text-slate-300 mb-3">Choose a Template</h4>
                <div class="grid grid-cols-1 gap-2">
                    <button type="button" onclick="selectTemplate('force_leave_expiry')" 
                        class="w-full p-3 bg-red-900/30 hover:bg-red-500/20 border border-red-600 hover:border-red-500 rounded-lg text-left transition-all">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-red-500/20 rounded flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-calendar-xmark text-red-400 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-white font-medium text-sm">Force Leave Expiry</div>
                                <div class="text-slate-400 text-xs">Mandatory leave 1-year expiry notice</div>
                            </div>
                        </div>
                    </button>
                    
                    <button type="button" onclick="selectTemplate('cto_expiry')" 
                        class="w-full p-3 bg-purple-900/30 hover:bg-purple-500/20 border border-purple-600 hover:border-purple-500 rounded-lg text-left transition-all">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-purple-500/20 rounded flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-clock text-purple-400 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-white font-medium text-sm">CTO Expiry</div>
                                <div class="text-slate-400 text-xs">Compensatory Time Off 1-year expiry</div>
                            </div>
                        </div>
                    </button>
                    
                    <button type="button" onclick="selectTemplate('slp_expiry')" 
                        class="w-full p-3 bg-yellow-900/30 hover:bg-yellow-500/20 border border-yellow-600 hover:border-yellow-500 rounded-lg text-left transition-all">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-yellow-500/20 rounded flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-star text-yellow-400 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-white font-medium text-sm">SLP Expiry</div>
                                <div class="text-slate-400 text-xs">Special Leave Privilege 1-year expiry</div>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
            
            <!-- Hidden fields for form submission -->
            <input type="hidden" name="alert_type" id="alert_type" value="1_year_expiry">

            <!-- Message Editor -->
            <div class="px-6 py-4 flex-1 overflow-y-auto">
                <label for="message" class="block text-sm font-medium text-slate-300 mb-2">
                    Message Content
                </label>
                <div class="relative">
                    <textarea name="message" id="message" rows="12" 
                        placeholder="Click a template above to load the 1-year expiry message..."
                        class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-4 py-3 text-white text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none transition-all duration-200"></textarea>
                </div>
                <p class="text-slate-400 text-xs mt-2">
                    <span id="charCount">0</span> characters
                </p>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-slate-700 bg-slate-800 rounded-b-2xl flex-shrink-0">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-slate-400">
                        <span id="footerCharCount">0 characters</span>
                    </div>
                    
                    <div class="flex items-center space-x-3">
                        <button type="button" onclick="closeAlertModal()" 
                            class="px-6 py-2 bg-slate-600 hover:bg-slate-500 text-white rounded-lg transition-colors text-sm font-medium">
                            Cancel
                        </button>
                        <button type="submit" id="sendButton" 
                            class="px-6 py-2 bg-gradient-to-r from-red-500 to-orange-500 hover:from-red-600 hover:to-orange-600 text-white rounded-lg transition-all duration-200 flex items-center text-sm font-medium shadow-lg">
                            <i class="fas fa-paper-plane mr-2"></i>Send 1-Year Expiry Alert
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Open alert modal
    function openAlertModal(employeeId, employeeName, priority = 'critical', totalRemaining = 0) {
        const modal = document.getElementById('alertModal');
        const modalContent = document.getElementById('modalContent');
        const employeeIdField = document.getElementById('modal_employee_id');
        const employeeNameField = document.getElementById('modal_employee_name');
        
        if (!modal || !modalContent || !employeeIdField || !employeeNameField) {
            console.error('Modal elements not found');
            return;
        }
        
        // Set employee data
        employeeIdField.value = employeeId;
        employeeNameField.textContent = `Send 1-Year Expiry Alert to ${employeeName}`;
        
        // Show modal with animation
        modal.classList.remove('hidden');
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }
    
    // Close alert modal
    function closeAlertModal() {
        const modal = document.getElementById('alertModal');
        const modalContent = document.getElementById('modalContent');
        
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
    
    // Select message template
    function selectTemplate(templateType) {
        const messageField = document.getElementById('message');
        const alertTypeField = document.getElementById('alert_type');
        
        let message = '';
        
        switch(templateType) {
            case 'force_leave_expiry':
                alertTypeField.value = 'force_leave_1_year_expiry';
                message = `Subject: CRITICAL - Force Leave 1-Year Expiry Notice

In compliance with Civil Service Commission (CSC) Omnibus Rules on Leave and the mandatory 1-year expiry policy for Force Leave credits:

Your Force Leave (Mandatory) days will EXPIRE and be FORFEITED on December 31st.

IMPORTANT: Force Leave credits are subject to MANDATORY 1-YEAR EXPIRY and cannot be carried over to the next year.

IMMEDIATE ACTION REQUIRED:
• Schedule and utilize your Force Leave credits IMMEDIATELY
• Coordinate with your immediate supervisor for leave scheduling
• Submit leave applications within the remaining days of the year
• Failure to use these credits will result in AUTOMATIC FORFEITURE

Consequences of non-utilization:
• Complete forfeiture of unused Force Leave credits
• No monetary compensation for expired credits
• Administrative adjustment of leave balance
• Potential assignment of forced leave dates by HRMO

For urgent assistance, contact the HRMO immediately.

Human Resource Management Office`;
                break;
                
            case 'cto_expiry':
                alertTypeField.value = 'cto_1_year_expiry';
                message = `Subject: CRITICAL - CTO 1-Year Expiry Notice

In compliance with government compensation policies and the mandatory 1-year expiry rule for Compensatory Time Off:

Your CTO (Compensatory Time Off) hours/days will EXPIRE and be FORFEITED on December 31st.

IMPORTANT: CTO credits earned through overtime work are subject to MANDATORY 1-YEAR EXPIRY and cannot be carried over.

IMMEDIATE ACTION REQUIRED:
• Schedule and utilize your CTO credits IMMEDIATELY
• Coordinate with your supervisor for time-off scheduling
• Submit CTO applications within the remaining days of the year
• Failure to use these credits will result in AUTOMATIC FORFEITURE

Consequences of non-utilization:
• Complete loss of earned overtime compensation
• No monetary equivalent for expired CTO
• Administrative adjustment of CTO balance

Your overtime work deserves compensation - don't let it expire!

For urgent assistance, contact the HRMO immediately.

Human Resource Management Office`;
                break;
                
            case 'slp_expiry':
                alertTypeField.value = 'slp_1_year_expiry';
                message = `Subject: CRITICAL - SLP 1-Year Expiry Notice

In compliance with Civil Service Commission (CSC) MC No. 6, s. 1996 and the mandatory 1-year expiry policy for Special Leave Privilege:

Your SLP (Special Leave Privilege) days will EXPIRE and be FORFEITED on December 31st.

IMPORTANT: SLP credits are NON-CUMULATIVE and subject to MANDATORY 1-YEAR EXPIRY. They cannot be carried over to the next year or converted to cash.

IMMEDIATE ACTION REQUIRED:
• Schedule and utilize your SLP credits IMMEDIATELY
• Coordinate with your supervisor for leave scheduling
• Submit SLP applications within the remaining days of the year
• Failure to use these credits will result in AUTOMATIC FORFEITURE

Consequences of non-utilization:
• Complete forfeiture of unused SLP credits
• No monetary compensation (non-commutable)
• No carry-over to next year (non-cumulative)
• Administrative adjustment of leave balance

SLP is a special privilege - use it before you lose it!

For urgent assistance, contact the HRMO immediately.

Human Resource Management Office`;
                break;
        }
        
        messageField.value = message;
        updateCharCount();
    }
    
    // Update character count
    function updateCharCount() {
        const message = document.getElementById('message');
        const charCount = document.getElementById('charCount');
        const footerCharCount = document.getElementById('footerCharCount');
        
        if (charCount && message) {
            const count = message.value.length;
            charCount.textContent = count;
            if (footerCharCount) {
                footerCharCount.textContent = count + ' characters';
            }
        }
    }
    
    // Add event listener for character count
    document.addEventListener('DOMContentLoaded', function() {
        const messageField = document.getElementById('message');
        if (messageField) {
            messageField.addEventListener('input', updateCharCount);
        }
    });
</script>

<?php include '../../../../includes/admin_footer.php'; ?>