<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/DTRToCTOProcessor.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../auth/views/login.php');
    exit();
}

$processor = new DTRToCTOProcessor($pdo);
$message = '';
$error = '';

// Handle manual processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'process_dtr') {
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $employeeId = $_POST['employee_id'] ?: null;
        
        $result = $processor->processDTRForCTO($startDate, $endDate, $employeeId);
        
        if ($result['success']) {
            $message = "Successfully processed {$result['processed']} DTR records";
            if (!empty($result['errors'])) {
                $message .= " with " . count($result['errors']) . " warnings";
            }
        } else {
            $error = "Processing failed: " . $result['error'];
        }
    }
}

// Get processing summary for current month
$currentMonth = date('Y-m');
$summary = $processor->getProcessingSummary($currentMonth . '-01', date('Y-m-t'));

// Get recent CTO earnings from DTR processing
$stmt = $pdo->query("
    SELECT ce.*, e.name as employee_name, e.department
    FROM cto_earnings ce
    JOIN employees e ON ce.employee_id = e.id
    WHERE ce.status = 'approved'
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$recentEarnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees for dropdown
$stmt = $pdo->query("SELECT id, name, department FROM employees WHERE role != 'admin' ORDER BY name");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - DTR to CTO Processing</title>
    <link rel="stylesheet" href="../../../../assets/css/elms-dark-theme.css">
</head>
<body class="bg-slate-900 text-white min-h-screen">
    <?php include '../../../../includes/unified_navbar.php'; ?>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside class="fixed left-0 top-16 h-[calc(100vh-4rem)] w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40 elms-sidebar">
            <nav class="p-4 space-y-2">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors elms-sidebar-link">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Management</h3>
                    
                    <a href="leave_management.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors elms-sidebar-link">
                        <i class="fas fa-calendar-check w-5"></i>
                        <span>Leave Management</span>
                    </a>
                    
                    <a href="cto_management.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors elms-sidebar-link">
                        <i class="fas fa-clock w-5"></i>
                        <span>CTO/SERVICE</span>
                    </a>
                    
                    <a href="dtr_cto_processing.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30 elms-sidebar-link">
                        <i class="fas fa-sync-alt w-5"></i>
                        <span>DTR Processing</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="pt-24 flex-1 ml-64 px-6 pb-6 elms-main">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-2xl flex items-center justify-center">
                            <i class="fas fa-sync-alt text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">DTR to CTO Processing</h1>
                            <p class="text-slate-400">Automatically process DTR data to generate CTO earnings</p>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Processing Information -->
                <div class="bg-blue-500/20 border border-blue-500/30 rounded-2xl p-6 mb-8">
                    <div class="flex items-start gap-4">
                        <i class="fas fa-info-circle text-blue-400 text-2xl mt-1"></i>
                        <div>
                            <h3 class="text-xl font-semibold text-blue-400 mb-2">Automatic DTR to CTO Processing</h3>
                            <p class="text-slate-300 mb-4">The system automatically processes DTR data to generate CTO earnings based on:</p>
                            <ul class="text-slate-300 space-y-1 text-sm">
                                <li>• <strong>Overtime Work:</strong> More than 8 hours in a day (1:1 ratio)</li>
                                <li>• <strong>Holiday Work:</strong> Any work on holidays (1.5:1 ratio)</li>
                                <li>• <strong>Weekend Work:</strong> Work on Saturdays/Sundays (1:1 ratio)</li>
                                <li>• <strong>Maximum Overtime:</strong> 4 hours per day (to prevent abuse)</li>
                                <li>• <strong>Auto-Approval:</strong> DTR-based CTO is automatically approved</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Manual Processing Form -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6 mb-8">
                    <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                        <i class="fas fa-play-circle text-green-400 mr-3"></i>
                        Manual DTR Processing
                    </h3>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="process_dtr">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Start Date</label>
                                <input type="date" name="start_date" required 
                                       value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>"
                                       class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">End Date</label>
                                <input type="date" name="end_date" required 
                                       value="<?php echo date('Y-m-d'); ?>"
                                       class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Employee (Optional)</label>
                                <select name="employee_id" class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Employees</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['name']); ?> - <?php echo htmlspecialchars($employee['department']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors">
                                <i class="fas fa-play mr-2"></i>
                                Process DTR Data
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Current Month Summary -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6 mb-8">
                    <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                        <i class="fas fa-chart-bar text-purple-400 mr-3"></i>
                        Current Month Summary (<?php echo date('F Y'); ?>)
                    </h3>
                    
                    <?php if (!empty($summary)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <?php foreach ($summary as $item): ?>
                                <div class="bg-slate-700/30 rounded-xl p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-lg font-semibold text-white">
                                            <?php echo ucwords(str_replace('_', ' ', $item['work_type'])); ?>
                                        </h4>
                                        <span class="text-2xl font-bold text-green-400">
                                            <?php echo number_format($item['total_cto_earned'], 1); ?>h
                                        </span>
                                    </div>
                                    <div class="text-sm text-slate-400">
                                        <div><?php echo $item['count']; ?> records</div>
                                        <div><?php echo number_format($item['total_hours_worked'], 1); ?> hours worked</div>
                                        <div>Rate: <?php echo number_format($item['avg_rate'], 1); ?>:1</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-chart-bar text-4xl text-slate-500 mb-4"></i>
                            <p class="text-slate-400">No DTR processing data for this month</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent CTO Earnings -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
                    <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                        <i class="fas fa-history text-orange-400 mr-3"></i>
                        Recent CTO Earnings from DTR
                    </h3>
                    
                    <?php if (!empty($recentEarnings)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-700/50">
                                        <th class="text-left py-3 px-4 text-slate-400">Employee</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Work Type</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Hours Worked</th>
                                        <th class="text-left py-3 px-4 text-slate-400">CTO Earned</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Date</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentEarnings as $earning): ?>
                                        <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition-colors">
                                            <td class="py-3 px-4 text-white font-semibold">
                                                <?php echo htmlspecialchars($earning['employee_name']); ?>
                                                <div class="text-xs text-slate-400"><?php echo htmlspecialchars($earning['department']); ?></div>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <span class="px-2 py-1 bg-purple-500/20 text-purple-400 text-xs rounded-full">
                                                    <?php echo ucwords(str_replace('_', ' ', $earning['work_type'])); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300 font-mono">
                                                <?php echo number_format($earning['hours_worked'], 1); ?>h
                                            </td>
                                            <td class="py-3 px-4 text-green-400 font-mono font-semibold">
                                                +<?php echo number_format($earning['cto_earned'], 1); ?>h
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php echo date('M d, Y', strtotime($earning['earned_date'])); ?>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="px-2 py-1 rounded-full text-xs font-semibold bg-green-500/20 text-green-400">
                                                    <?php echo ucfirst($earning['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-history text-4xl text-slate-500 mb-4"></i>
                            <p class="text-slate-400">No recent CTO earnings from DTR processing</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>
