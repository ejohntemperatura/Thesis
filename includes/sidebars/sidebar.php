<?php
// Reusable Sidebar Component for ELMS
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'employee';

// Determine dashboard link based on role
$dashboardLink = $role === 'director' ? '../director_head_dashboard.php' : 
                 ($role === 'manager' ? '../department_head_dashboard.php' : 
                 ($role === 'admin' ? 'admin_dashboard.php' : 'dashboard.php'));

// Define navigation sections based on role
$navSections = [];

if ($role === 'admin') {
    $navSections = [
        'dashboard' => [
            'title' => 'Dashboard',
            'items' => [
                ['href' => 'admin_dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard']
            ]
        ],
        'management' => [
            'title' => 'Management',
            'items' => [
                ['href' => 'manage_user.php', 'icon' => 'fas fa-users-cog', 'text' => 'Manage Users'],
                ['href' => 'leave_management.php', 'icon' => 'fas fa-calendar-check', 'text' => 'Leave Management', 'badge' => 'pendingLeaveBadge'],
                ['href' => 'leave_alerts.php', 'icon' => 'fas fa-bell', 'text' => 'Leave Alerts']
            ]
        ],
        'reports' => [
            'title' => 'Reports',
            'items' => [
                ['href' => 'view_chart.php', 'icon' => 'fas fa-chart-line', 'text' => 'Leave Chart'],
                ['href' => 'reports.php', 'icon' => 'fas fa-file-alt', 'text' => 'Reports']
            ]
        ]
    ];
} elseif ($role === 'manager') {
    $navSections = [
        'dashboard' => [
            'title' => 'Dashboard',
            'items' => [
                ['href' => '../department_head_dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard']
            ]
        ],
        'management' => [
            'title' => 'Management',
            'items' => [
                ['href' => '../approve_leave.php', 'icon' => 'fas fa-calendar-check', 'text' => 'Approve Leave']
            ]
        ],
        'reports' => [
            'title' => 'Reports',
            'items' => [
                ['href' => '../view_chart.php', 'icon' => 'fas fa-chart-line', 'text' => 'Leave Chart']
            ]
        ]
    ];
} elseif ($role === 'director') {
    $navSections = [
        'dashboard' => [
            'title' => 'Dashboard',
            'items' => [
                ['href' => '../director_head_dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard']
            ]
        ],
        'management' => [
            'title' => 'Management',
            'items' => [
                ['href' => '../director_leave_management.php', 'icon' => 'fas fa-calendar-check', 'text' => 'Leave Management'],
                ['href' => '../director_manage_user.php', 'icon' => 'fas fa-users-cog', 'text' => 'Manage Users']
            ]
        ],
        'reports' => [
            'title' => 'Reports',
            'items' => [
                ['href' => '../view_chart.php', 'icon' => 'fas fa-chart-line', 'text' => 'Leave Chart']
            ]
        ]
    ];
} else {
    // Employee navigation
    $navSections = [
        'dashboard' => [
            'title' => 'Dashboard',
            'items' => [
                ['href' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard']
            ]
        ],
        'leave' => [
            'title' => 'Leave Management',
            'items' => [
                ['href' => 'leave_history.php', 'icon' => 'fas fa-history', 'text' => 'Leave History'],
                ['href' => 'leave_credits.php', 'icon' => 'fas fa-calculator', 'text' => 'Leave Credits']
            ]
        ],
        'reports' => [
            'title' => 'Reports',
            'items' => [
                ['href' => 'view_chart.php', 'icon' => 'fas fa-chart-line', 'text' => 'Leave Chart']
            ]
        ],
        'account' => [
            'title' => 'Account',
            'items' => [
                ['href' => 'profile.php', 'icon' => 'fas fa-user', 'text' => 'Profile']
            ]
        ]
    ];
}
?>

<!-- Left Sidebar -->
<aside class="fixed left-0 top-16 h-screen w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40 elms-sidebar">
    <nav class="p-4 space-y-2">
        <?php foreach ($navSections as $sectionKey => $section): ?>
            <?php if ($sectionKey === 'dashboard'): ?>
                <!-- Active Navigation Item (Dashboard) -->
                <?php 
                $dashboardItem = $section['items'][0];
                $isActive = $current_page === $dashboardItem['href'] || 
                           (isset($dashboardItem['href']) && strpos($current_page, $dashboardItem['href']) !== false);
                $activeClass = $isActive ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 
                                          'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors';
                ?>
                <a href="<?php echo $dashboardItem['href']; ?>" 
                   class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $activeClass; ?>">
                    <i class="<?php echo $dashboardItem['icon']; ?> w-5"></i>
                    <span><?php echo $dashboardItem['text']; ?></span>
                </a>
            <?php else: ?>
                <!-- Section Headers -->
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2"><?php echo $section['title']; ?></h3>
                    
                    <!-- Navigation Items -->
                    <?php foreach ($section['items'] as $item): ?>
                        <?php 
                        $isActive = $current_page === $item['href'] || 
                                   (isset($item['href']) && strpos($current_page, $item['href']) !== false);
                        $activeClass = $isActive ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 
                                                  'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors';
                        ?>
                        <a href="<?php echo $item['href']; ?>" 
                           class="flex items-center space-x-3 px-4 py-3 elms-nav-item <?php echo $activeClass; ?>">
                            <i class="<?php echo $item['icon']; ?> w-5"></i>
                            <span><?php echo $item['text']; ?></span>
                            <?php if (isset($item['badge'])): ?>
                                <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full" id="<?php echo $item['badge']; ?>" style="display: none;">0</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <!-- Logout Section -->
        <div class="pt-4 border-t border-slate-700">
            <a href="../auth/logout.php" 
               class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors elms-nav-item">
                <i class="fas fa-sign-out-alt w-5"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>
</aside>