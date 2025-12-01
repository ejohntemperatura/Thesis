<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/ReportService.php';

// Check if user is logged in and is an admin or manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager','director'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Initialize report service
$reportService = new ReportService($pdo);

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$selected_employee = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
$selected_department = isset($_GET['department']) ? $_GET['department'] : '';
$selected_leave_type = isset($_GET['leave_type']) ? $_GET['leave_type'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';

// Build filters array
$filters = [
    'employee_id' => $selected_employee,
    'department' => $selected_department,
    'leave_type' => $selected_leave_type
];

// Get data based on report type
$reportData = [];
switch ($report_type) {
    case 'overview':
        $reportData = $reportService->generateComprehensiveReport($start_date, $end_date, $filters);
        break;
    case 'attendance':
        $reportData = [
            'dtr_data' => $reportService->getDTRData($start_date, $end_date, $filters),
            'attendance_summary' => $reportService->getAttendanceSummary($start_date, $end_date, $filters)
        ];
        break;
    case 'performance':
        $reportData = [
            'employee_performance' => $reportService->getEmployeePerformance($start_date, $end_date, $filters),
            'system_stats' => $reportService->getSystemStats($start_date, $end_date, $filters),
            'utilization_metrics' => $reportService->getSystemUtilizationMetrics($start_date, $end_date, $filters)
        ];
        break;
    case 'leave_analysis':
        $reportData = [
            'leave_type_stats' => $reportService->getLeaveTypeStats($start_date, $end_date, $filters),
            'monthly_trends' => $reportService->getMonthlyTrends($start_date, $end_date, $filters),
            'system_stats' => $reportService->getSystemStats($start_date, $end_date, $filters),
            'financial_impact' => $reportService->getFinancialImpactAnalysis($start_date, $end_date, $filters)
        ];
        break;
    case 'compliance':
        $reportData = [
            'compliance_metrics' => $reportService->getComplianceMetrics($start_date, $end_date, $filters),
            'system_stats' => $reportService->getSystemStats($start_date, $end_date, $filters)
        ];
        break;
    case 'leave_credits':
        $reportData = [
            'leave_credits' => $reportService->getLeaveCreditsReport($filters),
            'system_stats' => $reportService->getSystemStats($start_date, $end_date, $filters)
        ];
        break;
    case 'utilization':
        $reportData = [
            'utilization_metrics' => $reportService->getSystemUtilizationMetrics($start_date, $end_date, $filters),
            'department_stats' => $reportService->getDepartmentStats($start_date, $end_date, $filters),
            'system_stats' => $reportService->getSystemStats($start_date, $end_date, $filters)
        ];
        break;
    case 'pay_summary':
        $reportData = [
            'pay_summary' => $reportService->getDepartmentPayStatusSummary($start_date, $end_date, $filters)
        ];
        break;
}

// Get filter options
$employees = $reportService->getEmployees();
$departments = $reportService->getDepartments();
$leaveTypes = $reportService->getLeaveTypes();

// Handle PDF exports
if (isset($_POST['export'])) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $export_type = $_POST['export_type'];
    $export_filters = [
        'employee_id' => $_POST['employee_id'] ?? '',
        'department' => $_POST['department'] ?? '',
        'leave_type' => $_POST['leave_type'] ?? ''
    ];
    
    switch ($export_type) {
        case 'leave_requests':
            require_once '../../../../app/core/services/PDFLeaveRequestsGenerator.php';
            $pdfGenerator = new PDFLeaveRequestsGenerator($pdo);
            $pdfGenerator->generateLeaveRequestsReport($start_date, $end_date, $export_filters['department'], $export_filters['employee_id']);
            break;
            
        case 'attendance':
            require_once '../../../../app/core/services/PDFAttendanceGenerator.php';
            $pdfGenerator = new PDFAttendanceGenerator($pdo);
            $pdfGenerator->generateAttendanceReport($start_date, $end_date, $export_filters['department'], $export_filters['employee_id']);
            break;
            
        case 'leave_credits':
            require_once '../../../../app/core/services/PDFLeaveCreditsGenerator.php';
            $pdfGenerator = new PDFLeaveCreditsGenerator($pdo);
            $pdfGenerator->generateLeaveCreditsReport($export_filters['department'], $export_filters['employee_id']);
            break;
        case 'pay_summary':
            require_once '../../../../app/core/services/PDFPaySummaryGenerator.php';
            $pdfGenerator = new PDFPaySummaryGenerator($pdo);
            $pdfGenerator->generatePaySummaryReport($start_date, $end_date, $export_filters);
            break;
    }
}

// Handle PDF report generation
if (isset($_POST['generate_pdf_report'])) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    require_once '../../../../app/core/services/PDFReportGenerator.php';
    $reportGenerator = new PDFReportGenerator($pdo);
    $reportGenerator->generateComprehensiveReport($start_date, $end_date, $selected_department, $selected_employee);
    exit();
}

// Set page title
$page_title = "Reports & Analytics";

// Include admin header
include '../../../../includes/admin_header.php';
?>
<script src="../../../../assets/libs/chartjs/chart.umd.min.js"></script>
    
    <style>
        .report-card {
            transition: all 0.3s ease;
        }
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .metric-card {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(147, 51, 234, 0.1) 100%);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        .chart-container {
            position: relative;
            height: 400px;
        }
        .filter-section {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(51, 65, 85, 0.8) 100%);
            backdrop-filter: blur(10px);
        }
    </style>
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-file-alt text-3xl text-primary mr-2"></i>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-1">Reports & Analytics</h1>
                            <p class="text-slate-400">Comprehensive leave management insights and analytics</p>
                        </div>
                    </div>
                </div>

                <!-- Advanced Filters -->
                <div class="filter-section rounded-2xl border border-slate-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-filter text-blue-400 mr-3"></i>Advanced Filters
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="GET" class="space-y-6">
                            <!-- Date Range -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="start_date" class="block text-sm font-semibold text-slate-300 mb-2">Start Date</label>
                                    <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" 
                                           class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label for="end_date" class="block text-sm font-semibold text-slate-300 mb-2">End Date</label>
                                    <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" 
                                           class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                            
                            <!-- Filters Row -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="employee_id" class="block text-sm font-semibold text-slate-300 mb-2">Employee</label>
                                    <select name="employee_id" id="employee_id" 
                                            class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">All Employees</option>
                                        <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>" <?php echo $selected_employee == $emp['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($emp['name'] . ' - ' . $emp['department']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="department" class="block text-sm font-semibold text-slate-300 mb-2">Department</label>
                                    <select name="department" id="department" 
                                            class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['department']; ?>" <?php echo $selected_department == $dept['department'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['department']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="leave_type" class="block text-sm font-semibold text-slate-300 mb-2">Leave Type</label>
                                    <select name="leave_type" id="leave_type" 
                                            class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">All Leave Types</option>
                                        <?php foreach ($leaveTypes as $type => $config): ?>
                                        <option value="<?php echo $type; ?>" <?php echo $selected_leave_type == $type ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($config['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Report Type -->
                            <div>
                                <label for="report_type" class="block text-sm font-semibold text-slate-300 mb-2">Report Type</label>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <label class="flex items-center space-x-3 cursor-pointer p-3 rounded-lg border border-slate-600 hover:border-blue-500 <?php echo $report_type == 'overview' ? 'bg-blue-500/10 border-blue-500' : ''; ?>">
                                        <input type="radio" name="report_type" value="overview" <?php echo $report_type == 'overview' ? 'checked' : ''; ?> 
                                               class="w-4 h-4 text-blue-500 bg-slate-700 border-slate-600 focus:ring-blue-500">
                                        <span class="text-slate-300">
                                            <i class="fas fa-chart-pie mr-2"></i>Overview
                                        </span>
                                    </label>
                                    <label class="flex items-center space-x-3 cursor-pointer p-3 rounded-lg border border-slate-600 hover:border-blue-500 <?php echo $report_type == 'attendance' ? 'bg-blue-500/10 border-blue-500' : ''; ?>">
                                        <input type="radio" name="report_type" value="attendance" <?php echo $report_type == 'attendance' ? 'checked' : ''; ?> 
                                               class="w-4 h-4 text-blue-500 bg-slate-700 border-slate-600 focus:ring-blue-500">
                                        <span class="text-slate-300">
                                            <i class="fas fa-clock mr-2"></i>Attendance
                                        </span>
                                    </label>
                                    <label class="flex items-center space-x-3 cursor-pointer p-3 rounded-lg border border-slate-600 hover:border-blue-500 <?php echo $report_type == 'leave_analysis' ? 'bg-blue-500/10 border-blue-500' : ''; ?>">
                                        <input type="radio" name="report_type" value="leave_analysis" <?php echo $report_type == 'leave_analysis' ? 'checked' : ''; ?> 
                                               class="w-4 h-4 text-blue-500 bg-slate-700 border-slate-600 focus:ring-blue-500">
                                        <span class="text-slate-300">
                                            <i class="fas fa-calendar-alt mr-2"></i>Leave Analysis
                                        </span>
                                    </label>
                                    <label class="flex items-center space-x-3 cursor-pointer p-3 rounded-lg border border-slate-600 hover:border-blue-500 <?php echo $report_type == 'leave_credits' ? 'bg-blue-500/10 border-blue-500' : ''; ?>">
                                        <input type="radio" name="report_type" value="leave_credits" <?php echo $report_type == 'leave_credits' ? 'checked' : ''; ?> 
                                               class="w-4 h-4 text-blue-500 bg-slate-700 border-slate-600 focus:ring-blue-500">
                                        <span class="text-slate-300">
                                            <i class="fas fa-coins mr-2"></i>Leave Credits
                                        </span>
                                    </label>
                                    <label class="flex items-center space-x-3 cursor-pointer p-3 rounded-lg border border-slate-600 hover:border-blue-500 <?php echo $report_type == 'pay_summary' ? 'bg-blue-500/10 border-blue-500' : ''; ?>">
                                        <input type="radio" name="report_type" value="pay_summary" <?php echo $report_type == 'pay_summary' ? 'checked' : ''; ?> 
                                               class="w-4 h-4 text-blue-500 bg-slate-700 border-slate-600 focus:ring-blue-500">
                                        <span class="text-slate-300">
                                            <i class="fas fa-file-invoice mr-2"></i>Leave Summary
                                        </span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Apply Button -->
                            <div class="flex justify-end">
                                <button type="submit" class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-semibold py-3 px-8 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl flex items-center">
                                    <i class="fas fa-search mr-2"></i>Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>


                <!-- Report Content -->
                <?php if ($report_type == 'overview'): ?>
                    <?php include 'report_sections/overview.php'; ?>
                <?php elseif ($report_type == 'attendance'): ?>
                    <?php include 'report_sections/attendance.php'; ?>
                <?php elseif ($report_type == 'leave_analysis'): ?>
                    <?php include 'report_sections/leave_analysis.php'; ?>
                <?php elseif ($report_type == 'leave_credits'): ?>
                    <?php include 'report_sections/leave_credits.php'; ?>
                <?php elseif ($report_type == 'pay_summary'): ?>
                    <?php include 'report_sections/pay_summary.php'; ?>
                <?php endif; ?>

                <!-- Export Section -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-download text-green-400 mr-3"></i>Export Reports
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <form method="POST" class="bg-slate-700/50 rounded-xl p-4 border border-slate-600 hover:border-red-500 transition-all duration-300">
                                <input type="hidden" name="employee_id" value="<?php echo $selected_employee; ?>">
                                <input type="hidden" name="department" value="<?php echo $selected_department; ?>">
                                <input type="hidden" name="leave_type" value="<?php echo $selected_leave_type; ?>">
                                <input type="hidden" name="export_type" value="leave_requests">
                                <button type="submit" name="export" class="w-full text-left">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-file-pdf text-red-400 text-xl mr-3"></i>
                                        <span class="font-semibold text-white">Leave Requests</span>
                                    </div>
                                    <p class="text-slate-400 text-sm">Export all leave requests as PDF</p>
                                </button>
                            </form>
                            
                            <form method="POST" class="bg-slate-700/50 rounded-xl p-4 border border-slate-600 hover:border-red-500 transition-all duration-300">
                                <input type="hidden" name="employee_id" value="<?php echo $selected_employee; ?>">
                                <input type="hidden" name="department" value="<?php echo $selected_department; ?>">
                                <input type="hidden" name="leave_type" value="<?php echo $selected_leave_type; ?>">
                                <input type="hidden" name="export_type" value="attendance">
                                <button type="submit" name="export" class="w-full text-left">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-file-pdf text-red-400 text-xl mr-3"></i>
                                        <span class="font-semibold text-white">Attendance</span>
                                    </div>
                                    <p class="text-slate-400 text-sm">Export attendance data as PDF</p>
                                </button>
                            </form>
                            
                            <form method="POST" class="bg-slate-700/50 rounded-xl p-4 border border-slate-600 hover:border-red-500 transition-all duration-300">
                                <input type="hidden" name="employee_id" value="<?php echo $selected_employee; ?>">
                                <input type="hidden" name="department" value="<?php echo $selected_department; ?>">
                                <input type="hidden" name="leave_type" value="<?php echo $selected_leave_type; ?>">
                                <input type="hidden" name="export_type" value="leave_credits">
                                <button type="submit" name="export" class="w-full text-left">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-file-pdf text-red-400 text-xl mr-3"></i>
                                        <span class="font-semibold text-white">Leave Credits</span>
                                    </div>
                                    <p class="text-slate-400 text-sm">Export leave balances as PDF</p>
                                </button>
                            </form>
                            <form method="POST" class="bg-slate-700/50 rounded-xl p-4 border border-slate-600 hover:border-red-500 transition-all duration-300">
                                <input type="hidden" name="employee_id" value="<?php echo $selected_employee; ?>">
                                <input type="hidden" name="department" value="<?php echo $selected_department; ?>">
                                <input type="hidden" name="leave_type" value="<?php echo $selected_leave_type; ?>">
                                <input type="hidden" name="export_type" value="pay_summary">
                                <button type="submit" name="export" class="w-full text-left">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-file-pdf text-red-400 text-xl mr-3"></i>
                                        <span class="font-semibold text-white">Leave Summary</span>
                                    </div>
                                    <p class="text-slate-400 text-sm">Export employee VL/SL usage (with pay and without pay)</p>
                                </button>
                            </form>
                        </div>
                        
                        <!-- PDF Export -->
                        <div class="mt-6 pt-6 border-t border-slate-600">
                            <form method="POST" class="flex items-center justify-between p-4 bg-slate-700/50 rounded-xl">
                                <div>
                                    <h5 class="font-semibold text-white mb-1">Complete PDF Report</h5>
                                    <p class="text-slate-400 text-sm">All data in one professional PDF file</p>
                                </div>
                                <button type="submit" name="generate_pdf_report" 
                                        class="bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-300 flex items-center">
                                    <i class="fas fa-file-pdf mr-2"></i>Download PDF
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

    <script>
        // Function to fetch pending leave count
        function fetchPendingLeaveCount() {
            fetch('api/get_pending_leave_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.getElementById('pendingLeaveBadge');
                        if (badge) {
                            if (data.count > 0) {
                                badge.textContent = data.count;
                                badge.style.display = 'inline-block';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching pending leave count:', error);
                });
        }

        // Fetch pending leave count on page load
        document.addEventListener('DOMContentLoaded', function() {
            fetchPendingLeaveCount();
            setInterval(fetchPendingLeaveCount, 30000);
        });
    </script>
    
<?php include '../../../../includes/admin_footer.php'; ?>