<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/LeaveCreditsCalculator.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../auth/views/login.php');
    exit();
}

$calculator = new LeaveCreditsCalculator($pdo);

// Handle CTO earning submission and Service Credit addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'manual_add_cto') {
        // Handle manual CTO addition
        $employee_id = $_POST['employee_id'];
        $hours_to_add = $_POST['hours_to_add'];
        $reason = $_POST['reason'] ?? 'Manual adjustment by admin';
        
        try {
            // Get current balance
            $stmt = $pdo->prepare("SELECT cto_balance, name FROM employees WHERE id = ?");
            $stmt->execute([$employee_id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$employee) {
                throw new Exception('Employee not found');
            }
            
            $current_balance = $employee['cto_balance'] ?? 0;
            $new_balance = $current_balance + $hours_to_add;
            
            // Check maximum accumulation limit (40 hours)
            if ($new_balance > 40) {
                $max_to_add = 40 - $current_balance;
                if ($max_to_add <= 0) {
                    throw new Exception("Employee already has maximum CTO balance (40 hours)");
                }
                $hours_to_add = $max_to_add;
                $new_balance = 40;
            }
            
            // Update employee balance
            $stmt = $pdo->prepare("UPDATE employees SET cto_balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $employee_id]);
            
            // Get the approver ID (admin who is making this adjustment)
            $approver_id = null;
            if (isset($_SESSION['user_id'])) {
                // Verify that the session user_id exists in employees table
                $stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                if ($stmt->fetchColumn()) {
                    $approver_id = $_SESSION['user_id'];
                }
            }
            
            // Record in cto_earnings for history
            // Note: work_type must be one of: 'overtime', 'holiday', 'weekend', 'special_assignment'
            // Since this is a manual adjustment, we'll use 'special_assignment' as the closest match
            // OR we can create a separate audit log table, but for now, we'll insert with NULL approved_by if no valid approver
            $stmt = $pdo->prepare("
                INSERT INTO cto_earnings 
                (employee_id, earned_date, hours_worked, cto_earned, work_type, rate_applied, description, approved_by, status) 
                VALUES (?, CURDATE(), ?, ?, 'special_assignment', 1.0, ?, ?, 'approved')
            ");
            $stmt->execute([$employee_id, $hours_to_add, $hours_to_add, $reason, $approver_id]);
            
            $_SESSION['success'] = "Successfully added {$hours_to_add} hours CTO to {$employee['name']}. New balance: {$new_balance} hours";
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        header('Location: cto_management.php');
        exit();
    } elseif ($_POST['action'] === 'manual_add_service_credit') {
        $employee_id = $_POST['employee_id'];
        $days_to_add = (float)($_POST['days_to_add'] ?? 0);
        $reason = $_POST['reason'] ?? 'Manual service credit by admin';
        try {
            // Ensure column exists
            $pdo->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS service_credit_balance DECIMAL(5,2) DEFAULT 0.00");
            // Ensure history table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS service_credit_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                days_added DECIMAL(5,2) NOT NULL,
                reason TEXT NULL,
                approved_by INT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (employee_id),
                CONSTRAINT fk_svc_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                CONSTRAINT fk_svc_appr FOREIGN KEY (approved_by) REFERENCES employees(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

            // Get current balance
            $stmt = $pdo->prepare("SELECT service_credit_balance, name FROM employees WHERE id = ?");
            $stmt->execute([$employee_id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$employee) { throw new Exception('Employee not found'); }

            $current_balance = (float)($employee['service_credit_balance'] ?? 0);
            $add = max(0, $days_to_add);
            if ($add <= 0) { throw new Exception('Days to add must be greater than 0'); }
            $new_balance = $current_balance + $add;

            // Approver ID (admin)
            $approver_id = null;
            if (isset($_SESSION['user_id'])) {
                $stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                if ($stmt->fetchColumn()) { $approver_id = $_SESSION['user_id']; }
            }

            // Apply update + history atomically
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE employees SET service_credit_balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $employee_id]);

            $stmt = $pdo->prepare("INSERT INTO service_credit_history (employee_id, days_added, reason, approved_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$employee_id, $add, $reason, $approver_id]);
            $pdo->commit();

            $_SESSION['success'] = "Successfully added {$add} day(s) Service Credit to {$employee['name']}. New balance: {$new_balance} day(s)";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        header('Location: cto_management.php');
        exit();
    }
}

// Get CTO earnings history
$stmt = $pdo->query("
    SELECT ce.*, e.name as employee_name, e.department, 
           approver.name as approved_by_name
    FROM cto_earnings ce
    JOIN employees e ON ce.employee_id = e.id
    LEFT JOIN employees approver ON ce.approved_by = approver.id
    ORDER BY ce.created_at DESC
    LIMIT 50
");
$ctoEarnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get CTO usage history
$stmt = $pdo->query("
    SELECT cu.*, e.name as employee_name, e.department,
           lr.start_date, lr.end_date, lr.leave_type
    FROM cto_usage cu
    JOIN employees e ON cu.employee_id = e.id
    LEFT JOIN leave_requests lr ON cu.leave_request_id = lr.id
    ORDER BY cu.created_at DESC
    LIMIT 50
");
$ctoUsage = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees with CTO balances
$stmt = $pdo->query("
    SELECT e.id, e.name, e.department, e.cto_balance
    FROM employees e
    WHERE e.cto_balance > 0
    ORDER BY e.cto_balance DESC
");
$employeesWithCTO = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set page title
$page_title = "CTO/SERVICE";

// Include admin header
include '../../../../includes/admin_header.php';
?>
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-clock text-3xl text-primary mr-2"></i>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-1">CTO/SERVICE</h1>
                            <p class="text-slate-400">Manage Compensatory Time Off (CTO) earnings and usage</p>
                        </div>

                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- CTO Information -->
                <div class="bg-purple-500/20 border border-purple-500/30 rounded-2xl p-6 mb-8">
                    <div class="flex items-start gap-4">
                        <i class="fas fa-info-circle text-purple-400 text-2xl mt-1"></i>
                        <div>
                            <h3 class="text-xl font-semibold text-purple-400 mb-2">Compensatory Time Off (CTO)</h3>
                            <p class="text-slate-300 mb-4">CTO is earned through overtime work, holiday work, and special assignments:</p>
                            <ul class="text-slate-300 space-y-1 text-sm">
                                <li>• <strong>Overtime:</strong> 1:1 ratio (1 hour worked = 1 hour CTO)</li>
                                <li>• <strong>Holiday Work:</strong> 1.5:1 ratio (1 hour worked = 1.5 hours CTO)</li>
                                <li>• <strong>Weekend Work:</strong> 1:1 ratio (1 hour worked = 1 hour CTO)</li>
                                <li>• <strong>Special Assignments:</strong> 1:1 ratio (1 hour worked = 1 hour CTO)</li>
                                <li>• <strong>Maximum Accumulation:</strong> 40 hours</li>
                                <li>• <strong>Expiration:</strong> 6 months from earning date</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Manual CTO Adjustment Form -->
                <div class="bg-blue-500/10 border border-blue-500/30 rounded-2xl p-6 mb-8">
                    <div class="flex items-start gap-4 mb-6">
                        <i class="fas fa-info-circle text-blue-400 text-2xl mt-1"></i>
                        <div>
                            <h3 class="text-xl font-semibold text-blue-400 mb-2">Manual CTO Credit Addition</h3>
                            <p class="text-slate-300">This feature allows you to manually add CTO credits to employees. Use this for special cases, corrections, or administrative adjustments. Maximum accumulation is 40 hours per employee.</p>
                        </div>
                    </div>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="manual_add_cto">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Employee</label>
                                <select name="employee_id" id="employee_select" required class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select Employee</option>
                                    <?php 
                                    // Get all regular employees only (exclude admin, manager, and director)
                                    $stmt = $pdo->query("SELECT id, name, department, cto_balance FROM employees WHERE role NOT IN ('admin', 'manager', 'director') ORDER BY name");
                                    $allEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($allEmployees as $employee): 
                                    ?>
                                        <option value="<?php echo $employee['id']; ?>" data-balance="<?php echo $employee['cto_balance'] ?? 0; ?>">
                                            <?php echo htmlspecialchars($employee['name']); ?> - <?php echo htmlspecialchars($employee['department']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="current_balance" class="mt-2 text-sm text-slate-400 hidden">
                                    <i class="fas fa-wallet mr-1"></i>Current CTO Balance: <span class="font-semibold text-blue-400" id="balance_value">0</span> hours
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Hours to Add</label>
                                <input type="number" name="hours_to_add" id="hours_input" step="0.5" min="0.5" max="40" required 
                                       class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Enter hours to add (max 40)">
                                <div id="max_hours" class="mt-2 text-xs text-slate-500 hidden">
                                    Maximum: 40 hours total (will cap at limit)
                                </div>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-300 mb-2">Reason for Manual Addition</label>
                                <input type="text" name="reason" 
                                       class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="e.g., Special project completion bonus, Administrative adjustment, Overtime correction">
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors shadow-lg shadow-blue-500/20">
                                <i class="fas fa-plus-circle mr-2"></i>
                                Add CTO Credits
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Manual Service Credit Addition Form (placed below CTO form) -->
                <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-6 mb-8">
                    <div class="flex items-start gap-4 mb-6">
                        <i class="fas fa-info-circle text-emerald-400 text-2xl mt-1"></i>
                        <div>
                            <h3 class="text-xl font-semibold text-emerald-400 mb-2">Manual Service Credit Addition</h3>
                            <p class="text-slate-300">Add Service Credit days directly to an employee's balance. This will also log a history record.</p>
                        </div>
                    </div>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="manual_add_service_credit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Employee</label>
                                <select name="employee_id" required class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                                    <option value="">Select Employee</option>
                                    <?php 
                                    if (!isset($allEmployees)) {
                                        $stmt = $pdo->query("SELECT id, name, department FROM employees WHERE role NOT IN ('admin', 'manager', 'director') ORDER BY name");
                                        $allEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    }
                                    foreach ($allEmployees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['name']); ?> - <?php echo htmlspecialchars($employee['department']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Days to Add</label>
                                <input type="number" name="days_to_add" step="0.5" min="0.5" required 
                                       class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                       placeholder="Enter days to add">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-300 mb-2">Reason</label>
                                <input type="text" name="reason" 
                                       class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                       placeholder="e.g., Service rendered on weekend, Administrative adjustment">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg transition-colors shadow-lg shadow-emerald-500/20">
                                <i class="fas fa-plus-circle mr-2"></i>
                                Add Service Credit
                            </button>
                        </div>
                    </form>
                </div>

                <!-- CTO Balances -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6 mb-8">
                    <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                        <i class="fas fa-wallet text-green-400 mr-3"></i>
                        Current CTO Balances
                    </h3>
                    
                    <?php if (!empty($employeesWithCTO)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($employeesWithCTO as $employee): ?>
                                <div class="bg-slate-700/30 rounded-xl p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-semibold text-white"><?php echo htmlspecialchars($employee['name']); ?></h4>
                                        <span class="text-green-400 font-bold"><?php echo number_format($employee['cto_balance'], 1); ?>h</span>
                                    </div>
                                    <p class="text-slate-400 text-sm"><?php echo htmlspecialchars($employee['department']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-wallet text-4xl text-slate-500 mb-4"></i>
                            <p class="text-slate-400">No employees currently have CTO balances</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- CTO Usage History -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
                    <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                        <i class="fas fa-calendar-check text-orange-400 mr-3"></i>
                        CTO Usage History
                    </h3>
                    
                    <?php if (!empty($ctoUsage)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-700/50">
                                        <th class="text-left py-3 px-4 text-slate-400">Employee</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Hours Used</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Date</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Leave Period</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ctoUsage as $usage): ?>
                                        <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition-colors">
                                            <td class="py-3 px-4 text-white font-semibold">
                                                <?php echo htmlspecialchars($usage['employee_name']); ?>
                                                <div class="text-xs text-slate-400"><?php echo htmlspecialchars($usage['department']); ?></div>
                                            </td>
                                            <td class="py-3 px-4 text-red-400 font-mono font-semibold">
                                                -<?php echo number_format($usage['hours_used'], 1); ?>h
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php echo date('M d, Y', strtotime($usage['used_date'])); ?>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php if ($usage['start_date'] && $usage['end_date']): ?>
                                                    <?php echo date('M d', strtotime($usage['start_date'])); ?> - <?php echo date('M d, Y', strtotime($usage['end_date'])); ?>
                                                    <div class="text-xs text-slate-400"><?php echo ucwords(str_replace('_', ' ', $usage['leave_type'])); ?></div>
                                                <?php else: ?>
                                                    <span class="text-slate-500">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php echo htmlspecialchars($usage['description']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-check text-4xl text-slate-500 mb-4"></i>
                            <p class="text-slate-400">No CTO usage recorded yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Handle employee selection and show current CTO balance
        document.getElementById('employee_select').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const currentBalance = parseFloat(selectedOption.getAttribute('data-balance')) || 0;
            const balanceDisplay = document.getElementById('current_balance');
            const balanceValue = document.getElementById('balance_value');
            const maxHoursDiv = document.getElementById('max_hours');
            
            if (this.value) {
                balanceDisplay.classList.remove('hidden');
                balanceValue.textContent = currentBalance.toFixed(1);
                
                // Update max hours input dynamically
                if (currentBalance >= 40) {
                    document.getElementById('hours_input').max = 0;
                    maxHoursDiv.textContent = 'Employee already has maximum CTO balance (40 hours)';
                    maxHoursDiv.classList.remove('hidden');
                    maxHoursDiv.classList.add('text-red-400');
                } else {
                    const maxToAdd = 40 - currentBalance;
                    document.getElementById('hours_input').max = maxToAdd;
                    maxHoursDiv.textContent = `Maximum hours that can be added: ${maxToAdd.toFixed(1)} (Total will be 40)`;
                    maxHoursDiv.classList.remove('text-red-400');
                    if (maxToAdd < 5) {
                        maxHoursDiv.classList.remove('hidden');
                    } else {
                        maxHoursDiv.classList.add('hidden');
                    }
                }
            } else {
                balanceDisplay.classList.add('hidden');
                document.getElementById('hours_input').max = 40;
                maxHoursDiv.classList.add('hidden');
            }
        });
        
        // Auto-refresh every 60 seconds
        setTimeout(function() {
            window.location.reload();
        }, 60000);
    </script>
    
<?php include '../../../../includes/admin_footer.php'; ?>
