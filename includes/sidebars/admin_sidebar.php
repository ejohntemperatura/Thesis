<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Admin Sidebar Component
$current_page = basename($_SERVER['PHP_SELF']);

// Compute HR pending count if HR/admin is logged in
$hrPendingCount = 0;
try {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        if (!isset($pdo)) {
            require_once __DIR__ . '/../../config/database.php';
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM leave_requests WHERE dept_head_approval = 'approved' AND (admin_approval IS NULL OR admin_approval = 'pending') AND (director_approval IS NULL OR director_approval = 'pending') AND status != 'rejected'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $hrPendingCount = (int)($row['cnt'] ?? 0);
    }
} catch (Exception $e) {
    $hrPendingCount = 0;
}
?>

<!-- Active Navigation Item (Dashboard) -->
<a href="admin_dashboard.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'admin_dashboard.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-tachometer-alt w-5"></i>
    <span>Dashboard</span>
</a>

<!-- Section Headers -->
<div class="space-y-1">
    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Management</h3>
    
    <!-- Navigation Items -->
    <a href="manage_user.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'manage_user.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-users-cog w-5"></i>
        <span>Manage Users</span>
    </a>
    
    <a href="leave_management.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'leave_management.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-calendar-check w-5"></i>
        <span>Leave Management</span>
        <?php if (($hrPendingCount ?? 0) > 0): ?>
            <span class="ml-2 inline-flex items-center justify-center bg-red-600 text-white text-[11px] leading-none px-2 py-1 rounded-full min-w-[1.25rem]" id="pendingLeaveBadge"><?php echo $hrPendingCount; ?></span>
        <?php endif; ?>
    </a>
    
    <a href="leave_management.php?status=pending" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-clock w-5"></i>
        <span>Pending Requests</span>
    </a>
    
    <a href="leave_management.php?status=approved" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo (isset($_GET['status']) && $_GET['status'] === 'approved') ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-check-circle w-5"></i>
        <span>Approved Requests</span>
    </a>
    
    <a href="leave_management.php?status=rejected" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo (isset($_GET['status']) && $_GET['status'] === 'rejected') ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-times-circle w-5"></i>
        <span>Rejected Requests</span>
    </a>
    
    <a href="leave_alerts.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'leave_alerts.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-bell w-5"></i>
        <span>Leave Alerts</span>
    </a>
    
    <a href="cto_management.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'cto_management.php' ? 'text-white bg-purple-500/20 rounded-lg border border-purple-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-clock w-5"></i>
        <span>CTO/SERVICE</span>
    </a>
    
    <a href="dtr_cto_processing.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'dtr_cto_processing.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-sync-alt w-5"></i>
        <span>DTR Processing</span>
    </a>
</div>

<div class="space-y-1">
    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
    
    <a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'view_chart.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-chart-line w-5"></i>
        <span>Leave Chart</span>
    </a>
    
    <a href="reports.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'reports.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-file-alt w-5"></i>
        <span>Reports</span>
    </a>
</div>
