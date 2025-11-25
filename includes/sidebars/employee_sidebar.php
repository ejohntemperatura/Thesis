<?php
// Employee Sidebar Component
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Active Navigation Item (Dashboard) -->
<a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'dashboard.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-tachometer-alt w-5"></i>
    <span>Dashboard</span>
</a>

<!-- Section Headers -->
<div class="space-y-1">
    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Leave Management</h3>
    
    <!-- Navigation Items -->
    <a href="leave_history.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'leave_history.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-history w-5"></i>
        <span>Leave History</span>
    </a>
    
    <a href="leave_credits.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'leave_credits.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-calculator w-5"></i>
        <span>Leave Credits</span>
    </a>
</div>


<div class="space-y-1">
    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Account</h3>
    
    <a href="profile.php" class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $current_page === 'profile.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-user w-5"></i>
        <span>Profile</span>
    </a>
</div>
