<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/LeaveAccrualService.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../auth/views/login.php');
    exit();
}

$accrualService = new LeaveAccrualService($pdo);
$message = '';
$messageType = '';

// Handle manual accrual trigger
if (isset($_POST['trigger_accrual'])) {
    try {
        $results = $accrualService->processMonthlyAccrual();
        $message = "Accrual processed successfully! Processed: {$results['processed']}, Skipped: {$results['skipped']}, Errors: {$results['errors']}";
        $messageType = 'success';
    } catch (Exception $e) {
        $message = "Error processing accrual: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get recent accrual history
$historyStmt = $pdo->prepare("
    SELECT 
        lch.id,
        lch.employee_id,
        e.name as employee_name,
        e.department,
        lch.credit_type,
        lch.credit_amount,
        lch.accrual_date,
        lch.created_at
    FROM leave_credit_history lch
    JOIN employees e ON lch.employee_id = e.id
    ORDER BY lch.accrual_date DESC, lch.created_at DESC
    LIMIT 100
");
$historyStmt->execute();
$accrualHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees with their last accrual date
$employeesStmt = $pdo->prepare("
    SELECT 
        id,
        name,
        department,
        vacation_leave_balance,
        sick_leave_balance,
        special_privilege_leave_balance,
        last_leave_credit_update,
        service_start_date,
        created_at
    FROM employees
    WHERE role = 'employee' AND account_status = 'active'
    ORDER BY last_leave_credit_update ASC, name ASC
");
$employeesStmt->execute();
$employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Leave Accrual Management";
include '../../../../includes/admin_header.php';
?>

<style>
    .accrual-card {
        transition: all 0.3s ease;
    }
    .accrual-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    }
</style>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <i class="fas fa-calendar-plus text-3xl text-primary mr-2"></i>
            <div>
                <h1 class="text-3xl font-bold text-white mb-1">Leave Accrual Management</h1>
                <p class="text-slate-400">Automatic monthly leave credits accrual system</p>
            </div>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-xl <?php echo $messageType === 'success' ? 'bg-green-500/20 border border-green-500' : 'bg-red-500/20 border border-red-500'; ?>">
    <p class="<?php echo $messageType === 'success' ? 'text-green-400' : 'text-red-400'; ?>">
        <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
        <?php echo htmlspecialchars($message); ?>
    </p>
</div>
<?php endif; ?>

<!-- Accrual Information -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="accrual-card bg-slate-800 rounded-2xl border border-slate-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-blue-500/20 p-3 rounded-xl">
                <i class="fas fa-umbrella-beach text-2xl text-blue-400"></i>
            </div>
            <span class="text-3xl font-bold text-blue-400">1.25</span>
        </div>
        <h3 class="text-lg font-semibold text-white mb-1">Vacation Leave</h3>
        <p class="text-slate-400 text-sm">Days per month</p>
        <p class="text-slate-500 text-xs mt-2">15 days annually</p>
    </div>

    <div class="accrual-card bg-slate-800 rounded-2xl border border-slate-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-red-500/20 p-3 rounded-xl">
                <i class="fas fa-thermometer-half text-2xl text-red-400"></i>
            </div>
            <span class="text-3xl font-bold text-red-400">1.25</span>
        </div>
        <h3 class="text-lg font-semibold text-white mb-1">Sick Leave</h3>
        <p class="text-slate-400 text-sm">Days per month</p>
        <p class="text-slate-500 text-xs mt-2">15 days annually</p>
    </div>

    <div class="accrual-card bg-slate-800 rounded-2xl border border-slate-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-yellow-500/20 p-3 rounded-xl">
                <i class="fas fa-star text-2xl text-yellow-400"></i>
            </div>
            <span class="text-3xl font-bold text-yellow-400">3</span>
        </div>
        <h3 class="text-lg font-semibold text-white mb-1">Special Privilege</h3>
        <p class="text-slate-400 text-sm">Days per year</p>
        <p class="text-slate-500 text-xs mt-2">Accrued every January</p>
    </div>
</div>

<!-- Manual Trigger -->
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
        <h3 class="text-xl font-semibold text-white flex items-center">
            <i class="fas fa-play-circle text-green-400 mr-3"></i>Manual Accrual Trigger
        </h3>
    </div>
    <div class="p-6">
        <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4 mb-4">
            <p class="text-yellow-400 text-sm">
                <i class="fas fa-info-circle mr-2"></i>
                <strong>Note:</strong> This will process monthly accrual for all eligible employees. Employees who have already received accrual this month will be skipped automatically.
            </p>
        </div>
        <form method="POST" onsubmit="return confirm('Are you sure you want to trigger monthly accrual for all eligible employees?');">
            <button type="submit" name="trigger_accrual" class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl flex items-center">
                <i class="fas fa-play mr-2"></i>Trigger Monthly Accrual Now
            </button>
        </form>
    </div>
</div>

<!-- Employee Status -->
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
        <h3 class="text-xl font-semibold text-white flex items-center">
            <i class="fas fa-users text-blue-400 mr-3"></i>Employee Accrual Status
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-700/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Department</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-slate-300 uppercase tracking-wider">VL Balance</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-slate-300 uppercase tracking-wider">SL Balance</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-slate-300 uppercase tracking-wider">SLP Balance</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-slate-300 uppercase tracking-wider">Last Accrual</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                <?php foreach ($employees as $emp): ?>
                <tr class="hover:bg-slate-700/30 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($emp['name']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-slate-400"><?php echo htmlspecialchars($emp['department']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="text-sm font-semibold text-blue-400"><?php echo number_format($emp['vacation_leave_balance'], 2); ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="text-sm font-semibold text-red-400"><?php echo number_format($emp['sick_leave_balance'], 2); ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="text-sm font-semibold text-yellow-400"><?php echo number_format($emp['special_privilege_leave_balance'], 2); ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <?php if ($emp['last_leave_credit_update']): ?>
                            <span class="text-sm text-slate-400"><?php echo date('M d, Y', strtotime($emp['last_leave_credit_update'])); ?></span>
                        <?php else: ?>
                            <span class="text-sm text-slate-500 italic">Never</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Accrual History -->
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
        <h3 class="text-xl font-semibold text-white flex items-center">
            <i class="fas fa-history text-purple-400 mr-3"></i>Recent Accrual History
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-700/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Credit Type</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-slate-300 uppercase tracking-wider">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                <?php foreach ($accrualHistory as $history): ?>
                <tr class="hover:bg-slate-700/30 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-slate-400"><?php echo date('M d, Y', strtotime($history['accrual_date'])); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($history['employee_name']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-slate-400"><?php echo htmlspecialchars($history['department']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        $typeColors = [
                            'vacation' => 'bg-blue-500/20 text-blue-400',
                            'sick' => 'bg-red-500/20 text-red-400',
                            'special_privilege' => 'bg-yellow-500/20 text-yellow-400'
                        ];
                        $typeLabels = [
                            'vacation' => 'Vacation Leave',
                            'sick' => 'Sick Leave',
                            'special_privilege' => 'Special Privilege'
                        ];
                        $colorClass = $typeColors[$history['credit_type']] ?? 'bg-gray-500/20 text-gray-400';
                        $label = $typeLabels[$history['credit_type']] ?? ucwords(str_replace('_', ' ', $history['credit_type']));
                        ?>
                        <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $colorClass; ?>">
                            <?php echo $label; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="text-sm font-semibold text-green-400">+<?php echo number_format($history['credit_amount'], 2); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../../../includes/admin_footer.php'; ?>
