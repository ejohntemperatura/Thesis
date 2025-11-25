<?php
// Director Sidebar Component
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Active Navigation Item (Dashboard) -->
<a href="../director_head_dashboard.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'director_head_dashboard.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-tachometer-alt w-5"></i>
    <span>Dashboard</span>
</a>

<!-- Section Headers -->
<div class="space-y-1">
    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Management</h3>
    
    <!-- Navigation Items -->
    <a href="../director_leave_management.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'director_leave_management.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-calendar-check w-5"></i>
        <span>Leave Management</span>
    </a>
    
    <a href="../director_manage_user.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'director_manage_user.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-users-cog w-5"></i>
        <span>Manage Users</span>
    </a>
</div>

<div class="space-y-1">
    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
    
    <a href="../view_chart.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'view_chart.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-chart-line w-5"></i>
        <span>Leave Chart</span>
    </a>
</div>