<?php
// Navigation helper file
// Include this file in your pages to get consistent navigation

function getNavigation($user_role = 'user', $pending_count = 0) {
    if ($user_role === 'admin') {
        return [
            'DASHBOARD' => [
                ['url' => 'dashboard.php', 'title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'badge' => '', 'badge_class' => 'nav-badge-green']
            ],
            'MANAGEMENT' => [
                ['url' => 'manage_user.php', 'title' => 'Manage Users', 'icon' => 'fas fa-users-cog', 'badge' => ''],
                ['url' => 'leave_management.php', 'title' => 'Leave Management', 'icon' => 'fas fa-calendar-check', 'badge' => $pending_count > 0 ? $pending_count : ''],
                ['url' => 'leave_alerts.php', 'title' => 'Leave Alerts', 'icon' => 'fas fa-bell', 'badge' => ''],
                ['url' => 'cto_management.php', 'title' => 'CTO/SERVICE', 'icon' => 'fas fa-clock', 'badge' => '']
            ],
            'REPORTS' => [
                ['url' => 'calendar.php', 'title' => 'Leave Chart', 'icon' => 'fas fa-calendar', 'badge' => ''],
                ['url' => 'reports.php', 'title' => 'Reports & Analytics', 'icon' => 'fas fa-chart-line', 'badge' => 'Admin', 'badge_type' => 'blue']
            ],
            'ACCOUNT' => [
                ['url' => '../../app/modules/user/views/profile.php', 'title' => 'Profile Settings', 'icon' => 'fas fa-user', 'badge' => ''],
                ['url' => '../../../../auth/controllers/logout.php', 'title' => 'Sign Out', 'icon' => 'fas fa-sign-out-alt', 'badge' => '']
            ]
        ];
    } elseif ($user_role === 'director') {
        return [
            'DASHBOARD' => [
                ['url' => 'dashboard.php', 'title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'badge' => '']
            ],
            'SCHEDULE' => [
                ['url' => 'calendar.php', 'title' => 'Calendar', 'icon' => 'fas fa-calendar', 'badge' => '']
            ],
            'ACCOUNT' => [
                ['url' => '../../app/modules/user/views/profile.php', 'title' => 'Profile Settings', 'icon' => 'fas fa-user', 'badge' => ''],
                ['url' => '../../../../auth/controllers/logout.php', 'title' => 'Sign Out', 'icon' => 'fas fa-sign-out-alt', 'badge' => '']
            ]
        ];
    } elseif ($user_role === 'department_head') {
        return [
            'DASHBOARD' => [
                ['url' => 'dashboard.php', 'title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'badge' => '']
            ],
            'SCHEDULE' => [
                ['url' => 'calendar.php', 'title' => 'Calendar', 'icon' => 'fas fa-calendar', 'badge' => '']
            ],
            'ACCOUNT' => [
                ['url' => '../../app/modules/user/views/profile.php', 'title' => 'Profile Settings', 'icon' => 'fas fa-user', 'badge' => ''],
                ['url' => '../../../../auth/controllers/logout.php', 'title' => 'Sign Out', 'icon' => 'fas fa-sign-out-alt', 'badge' => '']
            ]
        ];
    } else {
        return [
            'DASHBOARD' => [
                ['url' => 'dashboard.php', 'title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'badge' => '']
            ],
            'LEAVE MANAGEMENT' => [
                ['url' => 'leave_history.php', 'title' => 'Leave History', 'icon' => 'fas fa-history', 'badge' => ''],
                ['url' => 'submit_leave.php', 'title' => 'Submit Leave', 'icon' => 'fas fa-plus', 'badge' => ''],
                ['url' => 'leave_credits.php', 'title' => 'Leave Credits', 'icon' => 'fas fa-calendar', 'badge' => '']
            ],
            'TIME TRACKING' => [
                ['url' => 'dtr.php', 'title' => 'Daily Time Record', 'icon' => 'fas fa-clock', 'badge' => '']
            ],
            'ACCOUNT' => [
                ['url' => 'profile.php', 'title' => 'Profile Settings', 'icon' => 'fas fa-user', 'badge' => ''],
                ['url' => '../../../../auth/controllers/logout.php', 'title' => 'Sign Out', 'icon' => 'fas fa-sign-out-alt', 'badge' => '']
            ]
        ];
    }
}

function renderNavigation($user_role = 'user', $pending_count = 0) {
    $nav_sections = getNavigation($user_role, $pending_count);
    $current_page = basename($_SERVER['PHP_SELF']);
    
    echo '<div class="sidebar">';
    echo '<div class="sidebar-header">';
    
    // Role-based header
    $header_icon = 'fas fa-user-shield';
    $header_title = 'EMPLOYEE PANEL';
    if ($user_role === 'admin') {
        $header_icon = 'fas fa-shield-alt';
        $header_title = 'ADMIN PANEL';
    } elseif ($user_role === 'director') {
        $header_icon = 'fas fa-briefcase';
        $header_title = 'DIRECTOR PANEL';
    } elseif ($user_role === 'department_head') {
        $header_icon = 'fas fa-sitemap';
        $header_title = 'DEPARTMENT HEAD';
    }
    
    echo '<h4><i class="' . $header_icon . '"></i> ' . $header_title . '</h4>';
    echo '</div>';
    echo '<div class="sidebar-menu">';
    
    // Render sections with headers
    foreach ($nav_sections as $section_title => $items) {
        echo '<div class="sidebar-section-header">' . htmlspecialchars($section_title) . '</div>';
        
        foreach ($items as $item) {
            $is_active = ($current_page === $item['url'] || $current_page === basename($item['url'])) ? 'active' : '';
            echo '<a href="' . htmlspecialchars($item['url']) . '" class="' . $is_active . '">';
            echo '<i class="' . htmlspecialchars($item['icon']) . '"></i>';
            echo '<span>' . htmlspecialchars($item['title']) . '</span>';
            
            // Add badge if present
            if (!empty($item['badge'])) {
                $badge_class = isset($item['badge_type']) && $item['badge_type'] === 'blue' ? 'nav-badge-blue' : 'nav-badge';
                echo '<span class="' . $badge_class . '">' . htmlspecialchars($item['badge']) . '</span>';
            }
            
            echo '</a>';
        }
    }
    
    echo '</div>';
    echo '</div>';
}

// Function to render user profile dropdown
function renderUserDropdown($user_name = 'User', $user_email = '', $user_role = 'Employee') {
    ?>
    <div class="user-profile-dropdown">
        <div class="user-profile-button" onclick="toggleUserDropdown()">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <?php if (!empty($user_email)): ?>
                    <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                <?php endif; ?>
            </div>
            <i class="fas fa-chevron-down"></i>
        </div>
        
        <div class="dropdown-menu-custom" id="userDropdown">
            <div class="dropdown-header-custom">
                <div class="dropdown-header-title"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="dropdown-header-subtitle"><?php echo htmlspecialchars($user_email); ?></div>
                <span class="dropdown-header-role"><?php echo htmlspecialchars($user_role); ?></span>
            </div>
            <a href="profile.php">Profile Settings</a>
            <a href="logout.php" class="danger">Sign Out</a>
        </div>
    </div>
    
    <script>
    function toggleUserDropdown() {
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('show');
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('userDropdown');
        const button = document.querySelector('.user-profile-button');
        if (dropdown && !dropdown.contains(event.target) && !button.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });
    </script>
    <?php
}
?>
