<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../config/leave_types.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../auth/views/login.php');
    exit();
}

$leaveTypes = getLeaveTypes();

// Handle manual leave credit addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_leave_credit') {
    $employee_id = $_POST['employee_id'];
    $leave_type = $_POST['leave_type'];
    $credits_to_add = (float)$_POST['credits_to_add'];
    $reason = trim($_POST['reason'] ?? 'Manual adjustment by admin');
    
    try {
        // Validate leave type
        if (!isset($leaveTypes[$leave_type])) {
            throw new Exception('Invalid leave type');
        }
        
        $leaveConfig = $leaveTypes[$leave_type];
        
        // Check if leave type requires credits
        if (!$leaveConfig['requires_credits']) {
            throw new Exception('This leave type does not use credits');
        }
        
        $credit_field = $leaveConfig['credit_field'];
        
        // Get employee details
        $stmt = $pdo->prepare("SELECT id, name, gender, $credit_field FROM employees WHERE id = ? AND role = 'employee'");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            throw new Exception('Employee not found or not eligible');
        }
        
        // Check gender restrictions
        if (isset($leaveConfig['gender_restricted']) && $employee['gender'] !== $leaveConfig['gender_restricted']) {
            throw new Exception('This leave type is restricted to ' . $leaveConfig['gender_restricted'] . ' employees only');
        }
        
        // Validate credits amount
        if ($credits_to_add <= 0) {
            throw new Exception('Credits to add must be greater than 0');
        }
        
        $current_balance = (float)($employee[$credit_field] ?? 0);
        $new_balance = $current_balance + $credits_to_add;
        
        // Update employee balance
        $stmt = $pdo->prepare("UPDATE employees SET $credit_field = ? WHERE id = ?");
        $stmt->execute([$new_balance, $employee_id]);
        
        // Log the manual addition in leave_credit_history
        $stmt = $pdo->prepare("
            INSERT INTO leave_credit_history 
            (employee_id, credit_type, credit_amount, accrual_date, service_days, created_at) 
            VALUES (?, ?, ?, CURDATE(), 0, NOW())
        ");
        
        // Map leave type to credit_type enum
        $credit_type_map = [
            'vacation' => 'vacation',
            'sick' => 'sick',
            'special_privilege' => 'special_privilege',
            'maternity' => 'maternity',
            'paternity' => 'paternity',
            'solo_parent' => 'solo_parent',
            'vawc' => 'vawc',
            'rehabilitation' => 'rehabilitation',
            'special_women' => 'special_women',
            'special_emergency' => 'special_emergency',
            'adoption' => 'adoption',
            'mandatory' => 'mandatory'
        ];
        
        $credit_type = $credit_type_map[$leave_type] ?? 'vacation';
        $stmt->execute([$employee_id, $credit_type, $credits_to_add]);
        
        $_SESSION['success'] = "Successfully added {$credits_to_add} day(s) of {$leaveConfig['name']} to {$employee['name']}. New balance: {$new_balance} day(s)";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    // Redirect back to the referring page (leave_management or cto_management)
    $redirect = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'leave_management') !== false 
                ? 'leave_management.php' 
                : 'cto_management.php';
    header('Location: ' . $redirect);
    exit();
}

// Get all employees (only regular employees)
$stmt = $pdo->query("SELECT id, name, department, gender FROM employees WHERE role = 'employee' ORDER BY name");
$allEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent credit additions from history
$stmt = $pdo->query("
    SELECT lch.*, e.name as employee_name, e.department
    FROM leave_credit_history lch
    JOIN employees e ON lch.employee_id = e.id
    ORDER BY lch.created_at DESC
    LIMIT 50
");
$creditHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set page title
$page_title = "Add Leave Credits";

// Include admin header
include '../../../../includes/admin_header.php';
?>
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-plus-circle text-3xl text-primary mr-2"></i>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-1">Add Leave Credits</h1>
                            <p class="text-slate-400">Manually add leave credits for employees</p>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 px-4 py-3 rounded-lg mb-6 flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg mb-6 flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Information Box -->
                <div class="bg-blue-500/20 border border-blue-500/30 rounded-2xl p-6 mb-8">
                    <div class="flex items-start gap-4">
                        <i class="fas fa-info-circle text-blue-400 text-2xl mt-1"></i>
                        <div>
                            <h3 class="text-xl font-semibold text-blue-400 mb-2">About Manual Leave Credits</h3>
                            <p class="text-slate-300 mb-4">Use this page to manually add leave credits for employees. This is useful for:</p>
                            <ul class="text-slate-300 space-y-1 text-sm">
                                <li>• <strong>Special Leave Types:</strong> Maternity, Paternity, Solo Parent, VAWC, etc.</li>
                                <li>• <strong>Administrative Adjustments:</strong> Corrections or special approvals</li>
                                <li>• <strong>CTO Credits:</strong> Compensatory Time Off for overtime work</li>
                                <li>• <strong>Service Credits:</strong> Credits earned through special service</li>
                                <li>• <strong>One-time Grants:</strong> Special circumstances or rewards</li>
                            </ul>
                            <p class="text-slate-400 text-sm mt-4">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <strong>Note:</strong> Vacation Leave and Sick Leave typically accumulate automatically. Use this only for manual adjustments.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Manual Leave Credit Addition Form -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6 mb-8">
                    <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                        <i class="fas fa-plus-circle text-primary mr-3"></i>
                        Add Leave Credits
                    </h3>
                    
                    <form method="POST" class="space-y-6" id="addCreditForm">
                        <input type="hidden" name="action" value="add_leave_credit">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Employee Selection -->
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">
                                    <i class="fas fa-user mr-1"></i>Employee
                                </label>
                                <select name="employee_id" id="employee_select" required 
                                        class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-xl text-white focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="">Select Employee</option>
                                    <?php foreach ($allEmployees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>" 
                                                data-gender="<?php echo $employee['gender']; ?>"
                                                data-name="<?php echo htmlspecialchars($employee['name']); ?>">
                                            <?php echo htmlspecialchars($employee['name']); ?> - <?php echo htmlspecialchars($employee['department']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Leave Type Selection -->
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">
                                    <i class="fas fa-calendar-alt mr-1"></i>Leave Type
                                </label>
                                <select name="leave_type" id="leave_type_select" required 
                                        class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-xl text-white focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="">Select Leave Type</option>
                                    <?php 
                                    // Exclude these leave types from the dropdown
                                    $excludedTypes = ['vacation', 'sick', 'special_privilege', 'cto', 'service_credit'];
                                    
                                    foreach ($leaveTypes as $key => $config): 
                                        // Skip excluded leave types
                                        if (in_array($key, $excludedTypes)) continue;
                                        
                                        if ($config['requires_credits']): 
                                    ?>
                                            <option value="<?php echo $key; ?>" 
                                                    data-name="<?php echo htmlspecialchars($config['name']); ?>"
                                                    data-gender="<?php echo $config['gender_restricted'] ?? ''; ?>"
                                                    data-icon="<?php echo $config['icon']; ?>"
                                                    data-description="<?php echo htmlspecialchars($config['description']); ?>">
                                                <?php echo htmlspecialchars($config['name']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <div id="leave_type_info" class="mt-2 text-sm text-slate-400 hidden">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <span id="leave_type_description"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Credits Amount -->
                        <div>
                            <label class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-calculator mr-1"></i>Credits to Add (days/hours)
                            </label>
                            <input type="number" name="credits_to_add" id="credits_input" 
                                   step="0.5" min="0.5" required 
                                   class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-xl text-white focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Enter amount to add">
                            <p class="mt-2 text-xs text-slate-400">
                                <i class="fas fa-arrow-up mr-1"></i>
                                This amount will be <strong class="text-green-400">added</strong> to the employee's current balance
                            </p>
                        </div>
                        
                        <!-- Reason -->
                        <div>
                            <label class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-comment-alt mr-1"></i>Reason for Addition
                            </label>
                            <textarea name="reason" rows="3" 
                                      class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-xl text-white focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="e.g., Special approval for maternity leave, Administrative correction, CTO earned from overtime work"></textarea>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="px-6 py-3 bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg">
                                <i class="fas fa-plus-circle mr-2"></i>
                                Add Leave Credits
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Recent Credit Additions -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
                    <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                        <i class="fas fa-history text-cyan-400 mr-3"></i>
                        Recent Credit Additions
                    </h3>
                    
                    <?php if (!empty($creditHistory)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-700/50">
                                        <th class="text-left py-3 px-4 text-slate-400 font-semibold">Date</th>
                                        <th class="text-left py-3 px-4 text-slate-400 font-semibold">Employee</th>
                                        <th class="text-left py-3 px-4 text-slate-400 font-semibold">Leave Type</th>
                                        <th class="text-right py-3 px-4 text-slate-400 font-semibold">Credits Added</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($creditHistory as $history): ?>
                                        <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition-colors">
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php echo date('M d, Y', strtotime($history['created_at'])); ?>
                                                <div class="text-xs text-slate-500"><?php echo date('h:i A', strtotime($history['created_at'])); ?></div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="text-white font-semibold"><?php echo htmlspecialchars($history['employee_name']); ?></div>
                                                <div class="text-xs text-slate-400"><?php echo htmlspecialchars($history['department']); ?></div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-primary/20 text-primary">
                                                    <?php 
                                                    $type_name = ucwords(str_replace('_', ' ', $history['credit_type']));
                                                    echo htmlspecialchars($type_name);
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-right">
                                                <span class="text-green-400 font-mono font-semibold text-base">
                                                    +<?php echo number_format($history['credit_amount'], 1); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-history text-5xl text-slate-600 mb-4"></i>
                            <p class="text-slate-400 text-lg">No credit additions recorded yet</p>
                            <p class="text-slate-500 text-sm mt-2">Manual leave credit additions will appear here</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Show leave type description when selected
        document.getElementById('leave_type_select').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const description = selectedOption.getAttribute('data-description');
            const genderRestriction = selectedOption.getAttribute('data-gender');
            const infoDiv = document.getElementById('leave_type_info');
            const descSpan = document.getElementById('leave_type_description');
            
            if (this.value && description) {
                let infoText = description;
                if (genderRestriction) {
                    infoText += ` (${genderRestriction.charAt(0).toUpperCase() + genderRestriction.slice(1)} only)`;
                }
                descSpan.textContent = infoText;
                infoDiv.classList.remove('hidden');
                
                // Validate gender restriction
                validateGenderRestriction();
            } else {
                infoDiv.classList.add('hidden');
            }
        });
        
        // Validate gender restriction when employee changes
        document.getElementById('employee_select').addEventListener('change', function() {
            validateGenderRestriction();
        });
        
        function validateGenderRestriction() {
            const employeeSelect = document.getElementById('employee_select');
            const leaveTypeSelect = document.getElementById('leave_type_select');
            
            if (!employeeSelect.value || !leaveTypeSelect.value) return;
            
            const selectedEmployee = employeeSelect.options[employeeSelect.selectedIndex];
            const selectedLeaveType = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
            
            const employeeGender = selectedEmployee.getAttribute('data-gender');
            const requiredGender = selectedLeaveType.getAttribute('data-gender');
            
            if (requiredGender && employeeGender !== requiredGender) {
                const employeeName = selectedEmployee.getAttribute('data-name');
                const leaveTypeName = selectedLeaveType.getAttribute('data-name');
                showStyledAlert(`Warning: ${leaveTypeName} is restricted to ${requiredGender} employees only. ${employeeName} is ${employeeGender}.`, 'warning');
            }
        }
        
        // Form validation
        document.getElementById('addCreditForm').addEventListener('submit', function(e) {
            const credits = parseFloat(document.getElementById('credits_input').value);
            if (credits <= 0) {
                e.preventDefault();
                showStyledAlert('Credits to add must be greater than 0', 'warning');
                return false;
            }
        });
    </script>
    <script src="../../../../assets/js/modal-alert.js"></script>
    
<?php include '../../../../includes/admin_footer.php'; ?>
