<?php
session_start();
require_once '../../../../config/database.php';

// Auto-process emails when internet is available
require_once '../../../../app/core/services/auto_email_processor.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

// Fetch admin details
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Fetch statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM employees");
$total_employees = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*) FROM leave_requests 
    WHERE NOT (dept_head_approval = 'approved' AND director_approval = 'approved')
    AND NOT (dept_head_approval = 'rejected' OR director_approval = 'rejected')
");
$pending_requests = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*) FROM leave_requests 
    WHERE dept_head_approval = 'approved' AND director_approval = 'approved'
");
$approved_requests = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*) FROM leave_requests 
    WHERE dept_head_approval = 'rejected' OR director_approval = 'rejected'
");
$rejected_requests = $stmt->fetchColumn();

// Get enhanced alert statistics
require_once '../../../../app/core/services/EnhancedLeaveAlertService.php';
$alertService = new EnhancedLeaveAlertService($pdo);
$alertStats = $alertService->getAlertStatistics();
$low_utilization_count = $alertStats['total_employees_with_alerts'];

// Get leave types configuration for proper display names
require_once '../../../../config/leave_types.php';
$leaveTypes = getLeaveTypes();

// Fetch recent leave requests
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name, e.service_credit_balance AS sc_balance,
           CASE 
               WHEN lr.dept_head_approval = 'rejected' OR lr.director_approval = 'rejected' THEN 'rejected'
               WHEN lr.dept_head_approval = 'approved' AND lr.director_approval = 'approved' THEN 'approved'
               ELSE 'pending'
           END as final_approval_status
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    ORDER BY lr.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_requests = $stmt->fetchAll();

// Fetch APPROVED leave requests for calendar only
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name,
           CASE 
               WHEN lr.dept_head_approval = 'rejected' OR lr.director_approval = 'rejected' THEN 'rejected'
               WHEN lr.dept_head_approval = 'approved' AND lr.director_approval = 'approved' THEN 'approved'
               ELSE 'pending'
           END as final_approval_status
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    WHERE lr.status = 'approved'
    ORDER BY lr.start_date ASC
");
$stmt->execute();
$leave_requests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Admin Dashboard</title>
        <!-- OFFLINE Tailwind CSS - No internet required! -->
        <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
        <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
        <!-- Font Awesome Local - No internet required! -->
        
        <link rel="stylesheet" href="../../../../assets/css/elms-dark-theme.css">
        <script src="../../../../assets/libs/chartjs/chart.umd.min.js"></script>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/ELMS/elmsicon.png">
    <link rel="shortcut icon" href="/ELMS/elmsicon.png">
    <link rel="apple-touch-icon" href="/ELMS/elmsicon.png">
</head>
<body style="background-color: #0f172a; margin: 0;">
    <!-- Top Navbar -->
    <nav class="elms-navbar">
        <div class="elms-navbar-content">
            <div class="elms-logo">
                <span class="elms-logo-text">ELMS HR</span>
            </div>
            
            <div style="display: flex; align-items: center; gap: 1rem; margin-left: auto;">
                <!-- User Dropdown -->
                <div style="position: relative;">
                    <button onclick="toggleUserDropdown()" style="display: flex; align-items: center; gap: 0.5rem; background: none; border: none; cursor: pointer; padding: 0;">
                        <div style="width: 32px; height: 32px; background: #06b6d4; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.875rem;">
                            <?php echo strtoupper(substr($admin['name'], 0, 1)); ?>
                        </div>
                        <div style="text-align: left;">
                            <div style="color: white; font-weight: 600; font-size: 0.875rem;">
                                <?php echo htmlspecialchars($admin['name']); ?>
                            </div>
                        </div>
                        <i class="fas fa-chevron-down" style="color: #cbd5e1; font-size: 0.625rem;"></i>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div id="userDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 0.5rem; width: 260px; background: #1e293b; border: 1px solid #334155; border-radius: 0.75rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); z-index: 100;">
                        <!-- Dropdown Header -->
                        <div style="padding: 1rem; border-bottom: 1px solid #334155;">
                            <div style="font-weight: 600; color: white; margin-bottom: 0.25rem; font-size: 0.9375rem;">
                                <?php echo htmlspecialchars($admin['name']); ?>
                            </div>
                            <div style="color: #94a3b8; font-size: 0.8125rem; margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($admin['email'] ?? 'admin@elms.com'); ?>
                            </div>
                            <span style="display: inline-block; background: #06b6d4; color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.6875rem; font-weight: 600;">
                                Administrator
                            </span>
                        </div>
                        
                        <!-- Dropdown Items -->
                        <div style="padding: 0.5rem 0;">
                            <a href="leave_management.php" style="display: block; padding: 0.75rem 1rem; color: #cbd5e1; text-decoration: none; transition: all 0.2s; font-size: 0.875rem; font-weight: 500;">
                                <i class="fas fa-calendar-check" style="margin-right: 0.625rem; width: 18px; font-size: 0.875rem;"></i>
                                Leave Management
                            </a>
                            <a href="../../../../auth/controllers/logout.php" style="display: block; padding: 0.75rem 1rem; color: #ef4444; text-decoration: none; transition: all 0.2s; border-top: 1px solid #334155; font-size: 0.875rem; font-weight: 500;">
                                <i class="fas fa-sign-out-alt" style="margin-right: 0.625rem; width: 18px; font-size: 0.875rem;"></i>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <script>
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }

        // Robust resolver for modal/details: prefers helper, then infers from fields and service credit balance if available
        function resolveLeaveTypeLabel(req) {
            const lbl = getLeaveTypeDisplayNameJS(req.leave_type, req.original_leave_type);
            if (lbl && String(lbl).trim() !== '') return lbl;
            if (req.study_type) return 'Study Leave (Without Pay)';
            if (req.medical_condition || req.illness_specify) return 'Sick Leave (SL)';
            if (req.special_women_condition) return 'Special Leave Benefits for Women';
            if (req.location_type) return 'Vacation Leave (VL)';
            if (typeof req.sc_balance !== 'undefined' && parseFloat(req.sc_balance) > 0) return 'Service Credits';
            if (req.pay_status === 'without_pay' || req.leave_type === 'without_pay') return 'Without Pay Leave';
            return 'Service Credits';
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const button = event.target.closest('button');
            if (!button || button.getAttribute('onclick') !== 'toggleUserDropdown()') {
                if (dropdown && !dropdown.contains(event.target)) {
                    dropdown.style.display = 'none';
                }
            }
        });
    </script>

    <!-- Sidebar -->
    <aside class="elms-sidebar">
        <nav>
            <!-- Dashboard Section -->
            <div class="elms-sidebar-section">
                <h3 class="elms-sidebar-header">Dashboard</h3>
                <a href="dashboard.php" class="elms-sidebar-link active">
                    <i class="fas fa-tachometer-alt elms-sidebar-icon"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <!-- Management Section -->
            <div class="elms-sidebar-section">
                <h3 class="elms-sidebar-header">Management</h3>
                <a href="manage_user.php" class="elms-sidebar-link">
                    <i class="fas fa-users-cog elms-sidebar-icon"></i>
                    <span>Manage Users</span>
                </a>
                <a href="leave_management.php" class="elms-sidebar-link">
                    <i class="fas fa-calendar-check elms-sidebar-icon"></i>
                    <span>Leave Management</span>
                </a>
                <a href="leave_alerts.php" class="elms-sidebar-link">
                    <i class="fas fa-bell elms-sidebar-icon"></i>
                    <span>Leave Alerts</span>
                </a>
                <a href="cto_management.php" class="elms-sidebar-link">
                    <i class="fas fa-plus-circle elms-sidebar-icon"></i>
                    <span>Add Leave Credits</span>
                </a>
                <a href="dtr_kiosk.php" class="elms-sidebar-link">
                    <i class="fas fa-id-card elms-sidebar-icon"></i>
                    <span>DTR</span>
                </a>
            </div>
            
            <!-- Reports Section -->
            <div class="elms-sidebar-section">
                <h3 class="elms-sidebar-header">Reports</h3>
                <a href="calendar.php" class="elms-sidebar-link">
                    <i class="fas fa-calendar elms-sidebar-icon"></i>
                    <span>Leave Chart</span>
                </a>
                <a href="reports.php" class="elms-sidebar-link">
                    <i class="fas fa-file-alt elms-sidebar-icon"></i>
                    <span>Reports</span>
                </a>
            </div>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="elms-main">
        <!-- Welcome Section -->
        <div style="margin-bottom: 2rem;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                <div>
                    <h1 class="elms-h1" style="margin-bottom: 0.5rem;">Welcome, <?php echo htmlspecialchars($admin['name']); ?>!</h1>
                    <p class="elms-text-muted">Manage your system from this dashboard.</p>
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
                // Update admin dashboard time (Split Badge clock)
                function updateAdminDashboardTime() {
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
                updateAdminDashboardTime();
                setInterval(updateAdminDashboardTime, 1000);
            </script>
        </div>

        <!-- Statistics Cards -->
        <div class="elms-grid elms-grid-1 elms-grid-md-2 elms-grid-lg-5" style="margin-bottom: 2rem;">
            <!-- Total Users Card -->
            <a href="manage_user.php" class="elms-stat-card" style="text-decoration: none;">
                <div>
                    <p class="elms-stat-label">Total Users</p>
                    <p class="elms-stat-value"><?php echo $total_employees; ?></p>
                    <p style="color: #34d399; font-size: 0.875rem; margin-top: 0.5rem;">
                        <i class="fas fa-arrow-up"></i> Active users
                    </p>
                </div>
                <div class="elms-stat-icon-container" style="background-color: #1e3a8a;">
                    <i class="fas fa-users elms-stat-icon" style="color: #60a5fa;"></i>
                </div>
            </a>
                    
            <!-- Pending Requests Card -->
            <a href="leave_management.php?status=pending" class="elms-stat-card" style="text-decoration: none;">
                <div>
                    <p class="elms-stat-label">Pending Requests</p>
                    <p class="elms-stat-value"><?php echo $pending_requests; ?></p>
                    <p style="color: #fb923c; font-size: 0.875rem; margin-top: 0.5rem;">
                        <i class="fas fa-hourglass-half"></i> Awaiting review
                    </p>
                </div>
                <div class="elms-stat-icon-container" style="background-color: #7c2d12;">
                    <i class="fas fa-clock elms-stat-icon" style="color: #fb923c;"></i>
                </div>
            </a>
                    
            <!-- Approved Requests Card -->
            <a href="leave_management.php?status=approved" class="elms-stat-card" style="text-decoration: none;">
                <div>
                    <p class="elms-stat-label">Approved Requests</p>
                    <p class="elms-stat-value"><?php echo $approved_requests; ?></p>
                    <p style="color: #34d399; font-size: 0.875rem; margin-top: 0.5rem;">
                        <i class="fas fa-arrow-up"></i> This month
                    </p>
                </div>
                <div class="elms-stat-icon-container" style="background-color: #064e3b;">
                    <i class="fas fa-check-circle elms-stat-icon" style="color: #34d399;"></i>
                </div>
            </a>
                    
            <!-- Rejected Requests Card -->
            <a href="leave_management.php?status=rejected" class="elms-stat-card" style="text-decoration: none;">
                <div>
                    <p class="elms-stat-label">Rejected Requests</p>
                    <p class="elms-stat-value"><?php echo $rejected_requests; ?></p>
                    <p style="color: #94a3b8; font-size: 0.875rem; margin-top: 0.5rem;">
                        <i class="fas fa-arrow-down"></i> This month
                    </p>
                </div>
                <div class="elms-stat-icon-container" style="background-color: #334155;">
                    <i class="fas fa-times-circle elms-stat-icon" style="color: #94a3b8;"></i>
                </div>
            </a>
                    
            <!-- Total Alerts Card -->
            <a href="leave_alerts.php" class="elms-stat-card" style="text-decoration: none;">
                <div>
                    <p class="elms-stat-label">Total Alerts</p>
                    <p class="elms-stat-value"><?php echo $alertStats['total_employees_with_alerts']; ?></p>
                    <p style="color: #60a5fa; font-size: 0.875rem; margin-top: 0.5rem;">
                        <i class="fas fa-users"></i> Need attention
                    </p>
                </div>
                <div class="elms-stat-icon-container" style="background-color: #1e3a8a;">
                    <i class="fas fa-bell elms-stat-icon" style="color: #60a5fa;"></i>
                </div>
            </a>
                    
                </div>

        <!-- Recent Leave Requests Table -->
        <div class="elms-table">
            <div class="elms-table-header">
                <h3 class="elms-h3" style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-list" style="color: #0891b2;"></i>
                    Recent Leave Requests
                </h3>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; min-width: 800px;">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th class="hidden sm:table-cell">Type</th>
                            <th class="hidden md:table-cell">Start Date</th>
                            <th class="hidden md:table-cell">End Date</th>
                            <th>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span>Status</span>
                                    <button type="button" 
                                            onclick="showStatusInfoHelp()"
                                            title="View Status Information"
                                            style="color: #60a5fa; background: none; border: none; cursor: pointer;">
                                        <i class="fas fa-info-circle" style="font-size: 0.75rem;"></i>
                                    </button>
                                </div>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                                <?php foreach ($recent_requests as $request): ?>
                                <tr class="hover:bg-slate-700/50 transition-colors">
                                    <td class="px-3 md:px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 md:w-10 md:h-10 bg-primary rounded-full flex items-center justify-center text-white">
                                                <i class="fas fa-user text-xs md:text-sm"></i>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="font-medium text-white text-sm truncate"><?php echo htmlspecialchars($request['employee_name']); ?></div>
                                                <small class="text-slate-400 text-xs">ID: #<?php echo $request['employee_id']; ?></small>
                                                <!-- Mobile: Show additional info -->
                                                <div class="sm:hidden mt-1">
                                                    <span class="bg-blue-500/20 text-blue-400 px-2 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">
                                                        <?php 
                                                            $disp = getLeaveTypeDisplayName($request['leave_type'], $request['original_leave_type'] ?? null, $leaveTypes);
                                                            if (!isset($disp) || trim($disp) === '') {
                                                                $base = $request['original_leave_type'] ?? ($request['leave_type'] ?? '');
                                                                $disp = getLeaveTypeDisplayName($base, null, $leaveTypes);
                                                                if (!isset($disp) || trim($disp) === '') { 
                                                                    // Try to infer from other fields
                                                                    if (!empty($request['study_type'])) {
                                                                        $disp = 'Study Leave (Without Pay)';
                                                                    } elseif (!empty($request['medical_condition']) || !empty($request['illness_specify'])) {
                                                                        $disp = 'Sick Leave (SL)';
                                                                    } elseif (!empty($request['special_women_condition'])) {
                                                                        $disp = 'Special Leave Benefits for Women';
                                                                    } elseif (!empty($request['location_type'])) {
                                                                        $disp = 'Vacation Leave (VL)';
                                                                    } elseif (isset($request['sc_balance']) && (float)$request['sc_balance'] > 0) {
                                                                        $disp = 'Service Credits';
                                                                    } elseif (($request['pay_status'] ?? '') === 'without_pay' || ($request['leave_type'] ?? '') === 'without_pay') {
                                                                        $disp = 'Without Pay Leave';
                                                                    } else {
                                                                        // Final fallback: assume Service Credits
                                                                        $disp = 'Service Credits';
                                                                    }
                                                                }
                                                            }
                                                            echo $disp;
                                                        ?>
                                                    </span>
                                                    <div class="text-slate-400 text-xs mt-1">
                                                        <?php 
                                                        // Calculate correct end date based on approved days (excluding weekends)
                                                        if ($request['status'] === 'approved' && $request['approved_days'] && $request['approved_days'] > 0) {
                                                            $start = new DateTime($request['start_date']);
                                                            $daysToCount = $request['approved_days'];
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
                                                            
                                                            echo date('M d', strtotime($request['start_date'])) . ' - ' . date('M d', $current->getTimestamp());
                                                        } else {
                                                            echo date('M d', strtotime($request['start_date'])) . ' - ' . date('M d', strtotime($request['end_date']));
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 md:px-6 py-4 hidden sm:table-cell">
                                        <span class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">
                                            <?php 
                                                $disp = getLeaveTypeDisplayName($request['leave_type'], $request['original_leave_type'] ?? null, $leaveTypes);
                                                if (!isset($disp) || trim($disp) === '') {
                                                    $base = $request['original_leave_type'] ?? ($request['leave_type'] ?? '');
                                                    $disp = getLeaveTypeDisplayName($base, null, $leaveTypes);
                                                    if (!isset($disp) || trim($disp) === '') {
                                                        if (!empty($request['study_type'])) {
                                                            $disp = 'Study Leave (Without Pay)';
                                                        } elseif (!empty($request['medical_condition']) || !empty($request['illness_specify'])) {
                                                            $disp = 'Sick Leave (SL)';
                                                        } elseif (!empty($request['special_women_condition'])) {
                                                            $disp = 'Special Leave Benefits for Women';
                                                        } elseif (!empty($request['location_type'])) {
                                                            $disp = 'Vacation Leave (VL)';
                                                        } elseif (isset($request['sc_balance']) && (float)$request['sc_balance'] > 0) {
                                                            $disp = 'Service Credits';
                                                        } elseif (($request['pay_status'] ?? '') === 'without_pay' || ($request['leave_type'] ?? '') === 'without_pay') {
                                                            $disp = 'Without Pay Leave';
                                                        } else {
                                                            $disp = 'Service Credits';
                                                        }
                                                    }
                                                }
                                                echo $disp;
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-3 md:px-6 py-4 text-slate-300 text-sm hidden md:table-cell"><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                    <td class="px-3 md:px-6 py-4 text-slate-300 text-sm hidden md:table-cell">
                                        <?php 
                                        // Calculate correct end date based on approved days (excluding weekends)
                                        if ($request['status'] === 'approved' && $request['approved_days'] && $request['approved_days'] > 0) {
                                            $start = new DateTime($request['start_date']);
                                            $daysToCount = $request['approved_days'];
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
                                            
                                            echo date('M d, Y', $current->getTimestamp());
                                        } else {
                                            echo date('M d, Y', strtotime($request['end_date']));
                                        }
                                        ?>
                                    </td>
                                    <td class="px-3 md:px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide <?php 
                                            $final_status = $request['final_approval_status'] ?? $request['status'];
                                            echo $final_status == 'approved' ? 'bg-green-500/20 text-green-400' : 
                                                ($final_status == 'pending' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400'); 
                                        ?>">
                                            <?php echo ucfirst($final_status); ?>
                                        </span>
                                    </td>
                                    <td class="px-3 md:px-6 py-4">
                                        <div class="flex gap-1 md:gap-2">
                                            <button onclick="viewRequestDetails(<?php echo $request['id']; ?>)" 
                                                    title="View Details"
                                                    class="bg-primary hover:bg-primary/90 text-white p-1.5 md:p-2 rounded-lg transition-colors">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            <a href="leave_management.php" 
                                               title="Manage Leave Requests"
                                               class="bg-slate-600 hover:bg-slate-500 text-white p-1.5 md:p-2 rounded-lg transition-colors inline-flex items-center">
                                                <i class="fas fa-cog text-xs"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Leave Calendar Section -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700/50">
                        <h3 class="text-xl font-semibold text-white flex items-center gap-3">
                            <i class="fas fa-calendar-alt text-primary"></i>
                            Leave Calendar
                        </h3>
                        <p class="text-slate-400 text-sm mt-1">View all leave requests in calendar format</p>
                    </div>
                    <div class="p-6">
                        <div id="calendar" class="h-96 bg-slate-700/30 rounded-lg flex items-center justify-center">
                            <div class="text-center">
                                <i class="fas fa-calendar-alt text-4xl text-slate-500 mb-4"></i>
                                <p class="text-slate-400 mb-4">Calendar view will be available here</p>
                                <a href="calendar.php" class="inline-flex items-center gap-2 bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-lg transition-colors">
                                    <i class="fas fa-external-link-alt"></i>
                                    View Full Calendar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Search Overlay -->
    <div id="mobileSearchOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="bg-slate-800 p-4">
            <div class="flex items-center space-x-4">
                <div class="flex-1 relative">
                    <input type="text" 
                           placeholder="Search..." 
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-3 pl-10 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                </div>
                <button onclick="toggleSearch()" class="text-slate-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Pass leave types data to JavaScript
        window.leaveTypes = <?php echo json_encode($leaveTypes); ?>;
        
        // Helper function to get leave type display name in JavaScript (with normalization like PHP helper)
        function getLeaveTypeDisplayNameJS(leaveType, originalLeaveType = null) {
            const leaveTypes = window.leaveTypes;
            if (!leaveTypes) return leaveType || '';

            // Determine if this is without pay
            let isWithoutPay = false;
            if (leaveType === 'without_pay') {
                isWithoutPay = true;
            } else if (originalLeaveType && (leaveType === 'without_pay' || !leaveType)) {
                isWithoutPay = true;
            } else if (leaveType && leaveTypes[leaveType] && leaveTypes[leaveType].without_pay) {
                isWithoutPay = true;
            } else if (originalLeaveType && leaveTypes[originalLeaveType] && leaveTypes[originalLeaveType].without_pay) {
                isWithoutPay = true;
            }

            // Choose base type
            let baseType = originalLeaveType && (leaveType === 'without_pay' || !leaveType) ? originalLeaveType : leaveType;

            // Normalization similar to PHP
            const normalize = (val) => {
                if (!val) return '';
                let v = String(val).trim().toLowerCase();
                v = v.replace(/[-\s]+/g, '_');
                v = v.replace(/[^a-z0-9_]/g, '');
                // Map common service credit variants
                if (v === 'service_credits' || v === 'service' || v === 'servicecredit' || v === 'svc_credit' || v === 'svc' || (v.includes('service') && v.includes('credit'))) {
                    v = 'service_credit';
                }
                if (!leaveTypes[v] && v.endsWith('s')) {
                    const sg = v.replace(/s$/, '');
                    if (leaveTypes[sg]) v = sg;
                }
                return v;
            };

            let key = normalize(baseType);
            if (!leaveTypes[key] && originalLeaveType) {
                const alt = normalize(originalLeaveType);
                if (leaveTypes[alt]) key = alt;
            }

            if (leaveTypes[key]) {
                const cfg = leaveTypes[key];
                if (isWithoutPay) {
                    return cfg.name_with_note ? cfg.name_with_note : cfg.name + ' (Without Pay)';
                }
                return cfg.name;
            }

            // Fallback
            const fallback = (key || '').replace(/_/g, ' ');
            const titled = fallback ? (fallback.charAt(0).toUpperCase() + fallback.slice(1)) : '';
            return isWithoutPay && titled ? titled + ' (Without Pay)' : (titled || '');
        }
        
        // Toggle functions for navigation
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        function toggleSearch() {
            const searchOverlay = document.getElementById('mobileSearchOverlay');
            searchOverlay.classList.toggle('hidden');
        }



        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const sidebarButton = event.target.closest('[onclick="toggleSidebar()"]');
            
            if (window.innerWidth < 768 && !sidebar.contains(event.target) && !sidebarButton) {
                sidebar.classList.add('-translate-x-full');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('-translate-x-full');
            } else {
                sidebar.classList.add('-translate-x-full');
            }
        });

        // Helper function for status badge styling
        function getStatusBadgeClass(status) {
            const colorMap = {
                'approved': 'bg-green-500/20 text-green-400 border-green-500/30',
                'rejected': 'bg-red-500/20 text-red-400 border-red-500/30',
                'pending': 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30'
            };
            return colorMap[status] || 'bg-slate-500/20 text-slate-400 border-slate-500/30';
        }

        function viewRequestDetails(leaveId) {
            // Create modal to show detailed information
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 elms-modal-overlay';
            modal.id = 'requestModal';
            modal.innerHTML = `
                <div class="bg-slate-800 rounded-lg p-6 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto elms-modal">
                    <div class="flex items-center justify-between mb-4">
                        <h5 class="text-xl font-semibold text-white">Leave Request Details</h5>
                        <button type="button" class="text-slate-400 hover:text-white" onclick="closeModal()">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                        <p class="mt-2 text-slate-400">Loading request details...</p>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Fetch request details
            fetch('../api/get_leave_request_details.php?id=' + leaveId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.leave_request) {
                        const leave = data.leave_request;
                        modal.querySelector('.text-center').innerHTML = `
                            <div class="space-y-6">
                                <!-- Employee Information -->
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                                    <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                        <i class="fas fa-user text-primary mr-3"></i>Employee Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Name</label>
                                            <p class="text-white font-semibold">${leave.employee_name || 'N/A'}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Position</label>
                                            <p class="text-white">${leave.position || 'N/A'}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Department</label>
                                            <p class="text-white">${leave.department || 'N/A'}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Email</label>
                                            <p class="text-white">${leave.employee_email || 'N/A'}</p>
                                        </div>
                                        ${leave.employee_contact ? `
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Contact</label>
                                            <p class="text-white">${leave.employee_contact}</p>
                                        </div>
                                        ` : ''}
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Request ID</label>
                                            <p class="text-white font-mono">#${leave.id || 'N/A'}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Leave Details -->
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                                    <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                        <i class="fas fa-calendar-alt text-primary mr-3"></i>Leave Details
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Leave Type</label>
                                            <p class="text-white">${resolveLeaveTypeLabel(leave)}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Days Requested</label>
                                            <p class="text-white">${leave.days_requested || 'N/A'} day${leave.days_requested != 1 ? 's' : ''}</p>
                                        </div>
                                        ${leave.approved_days && leave.approved_days > 0 && leave.status === 'approved' ? `
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Days Approved</label>
                                            <p class="text-green-400 font-semibold">
                                                ${leave.approved_days} day${leave.approved_days != 1 ? 's' : ''}
                                                ${leave.pay_status ? `<span class="text-xs ml-1">(${leave.pay_status.replace('_', ' ')})</span>` : ''}
                                            </p>
                                        </div>
                                        ` : ''}
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Start Date</label>
                                            <p class="text-white">${leave.start_date ? new Date(leave.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">End Date</label>
                                            <p class="text-white">${(() => {
                                                // Calculate correct end date based on approved days (excluding weekends)
                                                if (leave.status === 'approved' && leave.approved_days && leave.approved_days > 0) {
                                                    const startDate = new Date(leave.start_date);
                                                    let current = new Date(startDate);
                                                    let weekdaysCounted = 0;
                                                    
                                                    // Count the first day if it's a weekday
                                                    let dayOfWeek = current.getDay(); // 0=Sunday, 6=Saturday
                                                    if (dayOfWeek >= 1 && dayOfWeek <= 5) {
                                                        weekdaysCounted++;
                                                    }
                                                    
                                                    // Continue counting until we reach approved days
                                                    while (weekdaysCounted < leave.approved_days) {
                                                        current.setDate(current.getDate() + 1);
                                                        dayOfWeek = current.getDay();
                                                        if (dayOfWeek >= 1 && dayOfWeek <= 5) {
                                                            weekdaysCounted++;
                                                        }
                                                    }
                                                    
                                                    return current.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                                                } else {
                                                    return leave.end_date ? new Date(leave.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
                                                }
                                            })()}</p>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="text-sm font-medium text-slate-400">Reason</label>
                                            <p class="text-white bg-slate-800/50 rounded-lg p-3 mt-1">${leave.reason || 'No reason provided'}</p>
                                        </div>
                                    </div>
                                </div>

                                ${(leave.is_late == 1 || leave.is_late === '1' || leave.is_late === true) && leave.late_justification ? `
                                <!-- Late Application Details -->
                                <div class="bg-yellow-500/20 border border-yellow-500/30 rounded-xl p-6">
                                    <h4 class="text-lg font-semibold text-yellow-400 mb-4 flex items-center">
                                        <i class="fas fa-exclamation-triangle mr-3"></i>Late Application Details
                                    </h4>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="text-sm font-medium text-yellow-300">Late Justification</label>
                                            <p class="text-white bg-slate-800/50 rounded-lg p-3 mt-1">${leave.late_justification}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-yellow-300">Application Type</label>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">
                                                Late Application
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                ` : ''}

                                ${leave.location_type ? `
                                <!-- Location Details -->
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                                    <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                        <i class="fas fa-map-marker-alt text-primary mr-3"></i>Location Details
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Location Type</label>
                                            <p class="text-white">${leave.location_type ? leave.location_type.charAt(0).toUpperCase() + leave.location_type.slice(1) : 'N/A'}</p>
                                        </div>
                                        ${leave.location_specify ? `
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Specific Location</label>
                                            <p class="text-white">${leave.location_specify}</p>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                ` : ''}

                                ${leave.medical_condition || leave.medical_certificate_path ? `
                                <!-- Medical Details -->
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                                    <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                        <i class="fas fa-medkit text-primary mr-3"></i>Medical Details
                                    </h4>
                                    <div class="space-y-3">
                                        ${leave.medical_condition ? `
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Medical Condition</label>
                                            <p class="text-white">${leave.medical_condition}</p>
                                        </div>
                                        ` : ''}
                                        ${leave.illness_specify ? `
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Illness Specification</label>
                                            <p class="text-white">${leave.illness_specify}</p>
                                        </div>
                                        ` : ''}
                                        ${leave.medical_certificate_path ? `
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Medical Certificate</label>
                                            <div class="flex items-center space-x-3 mt-2">
                                                <i class="fas fa-file-medical text-green-400"></i>
                                                <a href="../../api/view_medical_certificate.php?file=${encodeURIComponent(leave.medical_certificate_path)}" 
                                                   target="_blank" 
                                                   class="text-blue-400 hover:text-blue-300 underline">
                                                    View Medical Certificate
                                                </a>
                                            </div>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                ` : ''}

                                ${leave.special_women_condition ? `
                                <!-- Special Women Condition -->
                                <div class="bg-pink-500/20 border border-pink-500/30 rounded-xl p-6">
                                    <h4 class="text-lg font-semibold text-pink-400 mb-4 flex items-center">
                                        <i class="fas fa-female mr-3"></i>Special Women Condition
                                    </h4>
                                    <div>
                                        <label class="text-sm font-medium text-pink-300">Condition</label>
                                        <p class="text-white">${leave.special_women_condition}</p>
                                    </div>
                                </div>
                                ` : ''}

                                ${leave.study_type ? `
                                <!-- Study Details -->
                                <div class="bg-blue-500/20 border border-blue-500/30 rounded-xl p-6">
                                    <h4 class="text-lg font-semibold text-blue-400 mb-4 flex items-center">
                                        <i class="fas fa-graduation-cap mr-3"></i>Study Details
                                    </h4>
                                    <div>
                                        <label class="text-sm font-medium text-blue-300">Study Type</label>
                                        <p class="text-white">${leave.study_type}</p>
                                    </div>
                                </div>
                                ` : ''}

                                <!-- Approval Status -->
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                                    <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                        <i class="fas fa-clipboard-check text-primary mr-3"></i>Approval Status
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <!-- Dept. Head -->
                                        <div class="text-center">
                                            <label class="text-sm font-medium text-slate-400 mb-2 block">Department Head</label>
                                            <div class="mb-2">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getStatusBadgeClass(leave.dept_head_approval || 'pending')} border">
                                                    ${(leave.dept_head_approval || 'pending').charAt(0).toUpperCase() + (leave.dept_head_approval || 'pending').slice(1)}
                                                </span>
                                            </div>
                                            ${leave.dept_head_name ? `<p class="text-xs text-slate-400">by ${leave.dept_head_name}</p>` : ''}
                                            ${leave.dept_head_approved_at ? `<p class="text-xs text-slate-400">${new Date(leave.dept_head_approved_at).toLocaleDateString()}</p>` : ''}
                                            ${leave.dept_head_rejection_reason ? `<p class="text-xs text-red-400 mt-1">${leave.dept_head_rejection_reason}</p>` : ''}
                                        </div>
                                        <!-- HR -->
                                        <div class="text-center">
                                            <label class="text-sm font-medium text-slate-400 mb-2 block">HR</label>
                                            <div class="mb-2">
                                                ${(() => {
                                                    const hrStatus = (leave.dept_head_approval === 'rejected') ? 'rejected' : (leave.admin_approval || 'pending');
                                                    const cls = getStatusBadgeClass(hrStatus);
                                                    const txt = hrStatus.charAt(0).toUpperCase() + hrStatus.slice(1);
                                                    return `<span class=\"inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${cls} border\">${txt}</span>`;
                                                })()}
                                            </div>
                                            ${leave.admin_name ? `<p class="text-xs text-slate-400">by ${leave.admin_name}</p>` : ''}
                                            ${leave.admin_approved_at ? `<p class="text-xs text-slate-400">${new Date(leave.admin_approved_at).toLocaleDateString()}</p>` : ''}
                                            ${leave.admin_rejection_reason ? `<p class="text-xs text-red-400 mt-1">${leave.admin_rejection_reason}</p>` : ''}
                                        </div>
                                        <!-- Director Head -->
                                        <div class="text-center">
                                            <label class="text-sm font-medium text-slate-400 mb-2 block">Director Head</label>
                                            <div class="mb-2">
                                                ${(() => {
                                                    let dirStatus = leave.director_approval || 'pending';
                                                    if (leave.dept_head_approval === 'rejected') dirStatus = 'rejected';
                                                    else if (leave.admin_approval !== 'approved') dirStatus = 'pending';
                                                    const cls = getStatusBadgeClass(dirStatus);
                                                    const txt = dirStatus.charAt(0).toUpperCase() + dirStatus.slice(1);
                                                    return `<span class=\"inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${cls} border\">${txt}</span>`;
                                                })()}
                                            </div>
                                            ${leave.director_name ? `<p class="text-xs text-slate-400">by ${leave.director_name}</p>` : ''}
                                            ${leave.director_approved_at ? `<p class="text-xs text-slate-400">${new Date(leave.director_approved_at).toLocaleDateString()}</p>` : ''}
                                            ${leave.director_rejection_reason ? `<p class="text-xs text-red-400 mt-1">${leave.director_rejection_reason}</p>` : ''}
                                        </div>
                                    </div>
                                    <div class="mt-6 pt-4 border-t border-slate-600/30">
                                        <div class="text-center">
                                            <label class="text-sm font-medium text-slate-400 mb-2 block">Final Status</label>
                                            <div class="mb-2">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getStatusBadgeClass(leave.status || 'pending')} border">
                                                    ${(leave.status || 'pending').charAt(0).toUpperCase() + (leave.status || 'pending').slice(1)}
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-400">Based on Department Head, HR, and Director approvals</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Request Information -->
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                                    <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                        <i class="fas fa-info-circle text-primary mr-3"></i>Request Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Submitted On</label>
                                            <p class="text-white">${leave.created_at ? new Date(leave.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Final Status</label>
                                            <p class="text-white">${leave.status ? leave.status.charAt(0).toUpperCase() + leave.status.slice(1) : 'Pending'}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex justify-end space-x-3">
                                    <button onclick="closeModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors">
                                        Close
                                    </button>
                                    ${(leave.dept_head_approval === 'approved' && leave.director_approval === 'approved') ? 
                                        `<a href="print_leave_request.php?id=${leave.id}" target="_blank" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                            <i class="fas fa-print mr-2"></i>Print
                                        </a>` : ''
                                    }
                                </div>
                            </div>
                        `;
                    } else {
                        throw new Error(data.error || 'Failed to load leave details');
                    }
                })
                .catch(error => {
                    modal.querySelector('.text-center').innerHTML = `
                        <div class="bg-red-500/20 border border-red-500/30 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                                <span class="text-red-400">Error loading request details: ${error}</span>
                            </div>
                        </div>
                    `;
                });
        }

        function closeModal() {
            const modal = document.getElementById('requestModal');
            if (modal) {
                modal.remove();
            }
        }

        function showNotification(message, type) {
            const bgColor = type === 'success' ? 'bg-green-500/20 border-green-500/30' : 'bg-red-500/20 border-red-500/30';
            const textColor = type === 'success' ? 'text-green-400' : 'text-red-400';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
            
            const notification = document.createElement('div');
            notification.className = `fixed top-5 right-5 z-50 min-w-80 max-w-md ${bgColor} border rounded-lg p-4 shadow-lg`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${icon} ${textColor} mr-3"></i>
                    <span class="${textColor} flex-1">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-slate-400 hover:text-white ml-2">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Show status information help modal
        function showStatusInfoHelp() {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.id = 'statusInfoHelpModal';
            modal.innerHTML = `
                <div class=\"bg-slate-800 rounded-xl w-full max-w-xl mx-4 max-h-[80vh] overflow-y-auto border border-slate-700/60 shadow-xl\">
                    <div class=\"px-5 py-4 border-b border-slate-700/60 flex items-center justify-between\">
                        <h5 class=\"text-base md:text-lg font-semibold text-white flex items-center\">
                            <i class=\"fas fa-info-circle text-primary mr-2\"></i>Status Information Guide
                        </h5>
                        <button type=\"button\" class=\"text-slate-400 hover:text-white\" onclick=\"closeStatusModal()\">
                            <i class=\"fas fa-times text-sm\"></i>
                        </button>
                    </div>
                    <div class=\"px-5 py-4\">
                        <p class=\"text-slate-300 text-sm mb-4\">Workflow: <span class=\"text-white font-medium\">Department Head → HR → Director Head</span>. Director remains Pending until HR approves.</p>

                        <div class=\"divide-y divide-slate-700/60 rounded-lg border border-slate-700/60 overflow-hidden\">
                            <div class=\"p-4 bg-slate-800/60\">
                                <div class=\"flex items-center mb-1\">
                                    <i class=\"fas fa-clock text-yellow-400 mr-2\"></i>
                                    <h6 class=\"text-slate-200 font-semibold\">Pending</h6>
                                </div>
                                <p class=\"text-slate-400 text-sm\">Awaiting action from the next approver in the sequence.</p>
                            </div>
                            <div class=\"p-4 bg-slate-800/60\">
                                <div class=\"flex items-center mb-1\">
                                    <i class=\"fas fa-check-circle text-green-400 mr-2\"></i>
                                    <h6 class=\"text-slate-200 font-semibold\">Approved</h6>
                                </div>
                                <p class=\"text-slate-400 text-sm\">Step approved. Final approval is achieved when the Director approves.</p>
                            </div>
                            <div class=\"p-4 bg-slate-800/60\">
                                <div class=\"flex items-center mb-1\">
                                    <i class=\"fas fa-times-circle text-red-400 mr-2\"></i>
                                    <h6 class=\"text-slate-200 font-semibold\">Rejected</h6>
                                </div>
                                <p class=\"text-slate-400 text-sm\">If the Department Head rejects, downstream statuses reflect Rejected.</p>
                            </div>
                        </div>

                        <div class=\"mt-4 text-slate-400 text-xs\">
                            <p>Use <span class=\"text-white\">Leave Management</span> to view and act on requests.</p>
                        </div>
                    </div>
                    <div class=\"px-5 py-3 border-t border-slate-700/60 flex justify-end\">
                        <button onclick=\"closeStatusModal()\" class=\"bg-slate-600 hover:bg-slate-500 text-white px-4 py-2 rounded-lg transition-colors text-sm\">Close</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        function closeStatusModal() {
            const modal = document.getElementById('statusInfoHelpModal');
            if (modal) {
                modal.remove();
            }
        }

        // Add click handlers for sidebar navigation
        document.addEventListener('DOMContentLoaded', function() {
            // Add active class to current page
            const currentPage = window.location.pathname.split('/').pop();
            const sidebarLinks = document.querySelectorAll('aside nav a');
            sidebarLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.remove('text-slate-300', 'hover:text-white', 'hover:bg-slate-700');
                    link.classList.add('text-white', 'bg-primary/20', 'border', 'border-primary/30');
                }
            });
            
            // Admin dashboard loaded
        });

        // Function to fetch pending leave count
        function fetchPendingLeaveCount() {
            fetch('../api/get_pending_leave_count.php')
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
        fetchPendingLeaveCount();

        // Update pending leave count every 30 seconds
        setInterval(fetchPendingLeaveCount, 30000);
    </script>
</body>
</html> 