<?php
/**
 * Unified Navbar Component for ELMS
 * This ensures consistent navbar design across all dashboard pages
 */

// Get user information
$user_name = '';
$user_initials = '';
$user_role = $_SESSION['role'] ?? 'employee';

// Set role-specific information
switch($user_role) {
    case 'admin':
        $user_name = $_SESSION['name'] ?? 'Administrator';
        $user_initials = strtoupper(substr($user_name, 0, 2));
        $panel_title = 'ELMS HR';
        $logo_icon = 'fas fa-user-shield';
        $logo_color = 'from-slate-600 to-slate-700';
        break;
    case 'manager':
        $user_name = $_SESSION['name'] ?? 'Manager';
        $user_initials = strtoupper(substr($user_name, 0, 2));
        $panel_title = 'ELMS Department Head';
        $logo_icon = 'fas fa-user-tie';
        $logo_color = 'from-slate-600 to-slate-700';
        break;
    case 'director':
        $user_name = $_SESSION['name'] ?? 'Director';
        $user_initials = strtoupper(substr($user_name, 0, 2));
        $panel_title = 'ELMS Director Head';
        $logo_icon = 'fas fa-crown';
        $logo_color = 'from-slate-700 to-slate-800';
        break;
    default:
        $user_name = $_SESSION['name'] ?? 'Employee';
        $user_initials = strtoupper(substr($user_name, 0, 2));
        $panel_title = 'ELMS Employee';
        $logo_icon = 'fas fa-user';
        $logo_color = 'from-slate-500 to-slate-600';
        break;
}
?>

<!-- Top Navigation Bar - CLEAN DESIGN -->
<nav class="bg-slate-800 border-b border-slate-700 fixed top-0 left-0 right-0 z-50 h-16">
    <div class="px-4 md:px-6 py-4 h-full">
        <div class="flex items-center justify-between h-full">
            <!-- Mobile Menu Button -->
            <button class="md:hidden text-slate-400 hover:text-white transition-colors" onclick="toggleSidebar()">
                <i class="fas fa-bars text-xl"></i>
            </button>
            
            <!-- Logo and Title -->
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-gradient-to-r <?php echo $logo_color; ?> rounded-lg flex items-center justify-center">
                        <i class="<?php echo $logo_icon; ?> text-white text-sm"></i>
                    </div>
                    <span class="text-xl font-bold text-white"><?php echo $panel_title; ?></span>
                </div>
            </div>
            
            <!-- Right Side Menu -->
            <div class="flex items-center space-x-4">
                <!-- Notifications Bell - Only show for employees -->
                <?php if ($user_role === 'employee'): ?>
                <div class="relative">
                    <button onclick="toggleNotificationDropdown()" class="relative p-2 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-bell text-xl"></i>
                        <span id="navbarAlertBadge" class="notification-badge absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-semibold hidden">
                            0
                        </span>
                    </button>
                    
                    <!-- Notification Dropdown -->
                    <div id="notificationDropdown" class="absolute right-0 mt-2 w-80 md:w-96 bg-slate-800 border border-slate-700 rounded-lg shadow-xl hidden transform transition-all duration-200 ease-out">
                        <div class="p-4 border-b border-slate-700">
                            <h3 class="text-lg font-semibold text-white flex items-center">
                                <i class="fas fa-bell mr-2"></i>
                                Notifications
                                <?php if (isset($unread_alert_count) && $unread_alert_count > 0): ?>
                                <span class="ml-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-semibold">
                                    <?php echo $unread_alert_count > 99 ? '99+' : $unread_alert_count; ?>
                                </span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div id="navbarAlertsContainer" class="max-h-64 md:max-h-72 overflow-y-auto scrollbar-thin scrollbar-thumb-slate-600 scrollbar-track-slate-800 hover:scrollbar-thumb-slate-500 notification-scroll">
                            <div class="text-center py-8">
                                <i class="fas fa-spinner fa-spin text-2xl text-slate-500 mb-4"></i>
                                <p class="text-slate-400">Loading alerts...</p>
                            </div>
                        </div>
                        <div class="p-3 border-t border-slate-700 flex justify-between items-center">
                            <button onclick="markAllNavbarAlertsRead()" class="text-xs text-slate-400 hover:text-white transition-colors flex items-center">
                                <i class="fas fa-check-double mr-1"></i>Clear All
                            </button>
                            <a href="dashboard.php#alerts" class="text-xs text-blue-400 hover:text-blue-300 transition-colors">
                                View All Notifications
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- User Dropdown -->
                <div class="relative">
                    <button onclick="toggleUserDropdown()" class="flex items-center space-x-3 px-4 py-2 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <div class="w-8 h-8 bg-gradient-to-r <?php echo $logo_color; ?> rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-semibold"><?php echo $user_initials; ?></span>
                        </div>
                        <span class="font-medium hidden md:block"><?php echo htmlspecialchars($user_name); ?></span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    
                    <!-- User Dropdown Menu -->
                    <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-slate-800 border border-slate-700 rounded-lg shadow-xl hidden">
                        <div class="p-2">
                            <!-- User Info -->
                            <div class="px-3 py-2 text-sm text-slate-400 border-b border-slate-700">
                                <p class="font-medium text-white"><?php echo htmlspecialchars($user_name); ?></p>
                                <p class="text-xs text-slate-400"><?php 
                                    echo $user_role === 'manager' ? 'Department Head' : 
                                        ($user_role === 'director' ? 'Director Head' : ucfirst($user_role)); 
                                ?></p>
                            </div>
                            
                            <?php if ($user_role === 'admin'): ?>
                            <!-- Manage Users Link -->
                            <a href="manage_user.php" class="flex items-center space-x-3 px-3 py-2 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                                <i class="fas fa-users-cog w-4"></i>
                                <span>Manage Users</span>
                            </a>
                            <?php elseif (in_array($user_role, ['department_head', 'manager', 'director'])): ?>
                            <!-- No additional menu items for department heads and directors -->
                            <?php else: ?>
                            <!-- Profile Link for regular employees -->
                            <a href="profile.php" class="flex items-center space-x-3 px-3 py-2 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                                <i class="fas fa-user w-4"></i>
                                <span>Profile</span>
                            </a>
                            <?php endif; ?>
                            
                            <!-- Divider -->
                            <div class="border-t border-slate-700 my-1"></div>
                            
                            <!-- Logout Link -->
                            <a href="../../../../auth/controllers/logout.php" class="flex items-center space-x-3 px-3 py-2 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                                <i class="fas fa-sign-out-alt w-4"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>


<style>
    /* Clean dropdown styles */
    #userDropdown, #notificationDropdown {
        z-index: 1000;
        box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.3);
        position: absolute !important;
        isolation: isolate;
    }
    
    /* Ensure proper stacking */
    nav {
        z-index: 50;
        position: relative;
    }
    
    .relative {
        z-index: 100;
        position: relative;
    }
    
    /* Prevent search input from appearing in dropdown */
    #searchInput {
        position: relative;
        z-index: 1;
    }
    
    /* Ensure dropdown content is properly contained */
    #userDropdown .p-2 {
        position: relative;
        z-index: 1001;
        background: inherit;
    }
    
    /* Additional dropdown isolation */
    #userDropdown {
        overflow: hidden;
        contain: layout style paint;
    }
    
    /* Ensure no elements bleed into dropdown */
    #userDropdown * {
        position: relative;
    }
    
    /* Prevent any input elements from appearing in dropdown */
    #userDropdown input {
        display: none !important;
    }
    
    /* Line clamp utility for notification text */
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    /* Custom scrollbar for notification dropdown */
    .scrollbar-thin {
        scrollbar-width: thin;
        scrollbar-color: rgb(71 85 105) rgb(30 41 59);
    }
    
    .scrollbar-thin::-webkit-scrollbar {
        width: 6px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-track {
        background: rgb(30 41 59);
        border-radius: 3px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: rgb(71 85 105);
        border-radius: 3px;
        transition: background 0.2s ease;
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background: rgb(94 105 121);
    }
    
    /* Smooth scrolling for notification dropdown */
    .notification-scroll {
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Extra thin scrollbar for notification messages */
    .notification-scroll::-webkit-scrollbar {
        width: 4px;
    }
    
    .notification-scroll::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .notification-scroll::-webkit-scrollbar-thumb {
        background: rgb(71 85 105);
        border-radius: 2px;
    }
    
    .notification-scroll::-webkit-scrollbar-thumb:hover {
        background: rgb(94 105 121);
    }
    
    /* Floating modal animations */
    @keyframes modalFloat {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-2px); }
    }
    
    .modal-float {
        animation: modalFloat 3s ease-in-out infinite;
    }
    
    /* Enhanced modal shadow */
    .modal-shadow {
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 
                    0 0 0 1px rgba(255, 255, 255, 0.05),
                    0 0 20px rgba(59, 130, 246, 0.1);
    }
</style>

<script>
// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('-translate-x-full');
        sidebar.classList.toggle('md:translate-x-0');
    }
}

// Toggle user dropdown
function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    const notificationDropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
        
        // Ensure dropdown is properly positioned and isolated
        if (!dropdown.classList.contains('hidden')) {
            dropdown.style.position = 'absolute';
            dropdown.style.zIndex = '1000';
            dropdown.style.isolation = 'isolate';
            
            // Remove any misplaced elements that might have appeared
            const misplacedInputs = dropdown.querySelectorAll('input');
            misplacedInputs.forEach(input => {
                input.remove();
            });
        }
        
        // Close notification dropdown when opening user dropdown
        if (notificationDropdown) {
            notificationDropdown.classList.add('hidden');
        }
    }
}

// Toggle notification dropdown
function toggleNotificationDropdown() {
    console.log('Toggling notification dropdown...');
    const dropdown = document.getElementById('notificationDropdown');
    const userDropdown = document.getElementById('userDropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
        console.log('Dropdown visibility:', dropdown.classList.contains('hidden') ? 'hidden' : 'visible');
        
        // Add smooth animation when opening
        if (!dropdown.classList.contains('hidden')) {
            dropdown.classList.add('animate-in');
            requestAnimationFrame(() => {
                dropdown.classList.add('show');
            });
        }
        
        // Close user dropdown when opening notification dropdown
        if (userDropdown) {
            userDropdown.classList.add('hidden');
        }
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const userDropdown = document.getElementById('userDropdown');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const userButton = event.target.closest('button[onclick="toggleUserDropdown()"]');
    const notificationButton = event.target.closest('button[onclick="toggleNotificationDropdown()"]');
    
    if (userDropdown && !userDropdown.contains(event.target) && !userButton) {
        userDropdown.classList.add('hidden');
    }
    
    if (notificationDropdown && !notificationDropdown.contains(event.target) && !notificationButton) {
        notificationDropdown.classList.add('hidden');
    }
});

// Real-time notification polling
let notificationCheckInterval;
let lastNotificationCount = null; // Will be set from first API call

function startNotificationPolling() {
    // Check for new notifications every 5 seconds
    console.log('Starting notification polling...');
    notificationCheckInterval = setInterval(checkForNewNotifications, 5000);
    // Also check immediately on start
    checkForNewNotifications();
}

function stopNotificationPolling() {
    if (notificationCheckInterval) {
        clearInterval(notificationCheckInterval);
    }
}

async function checkForNewNotifications() {
    try {
        // Get the current path to determine the correct API path
        const currentPath = window.location.pathname;
        const apiPath = currentPath.includes('/user/') ? '../api/get_user_alerts.php' : 
                       currentPath.includes('/admin/') ? '../api/get_user_alerts.php' :
                       currentPath.includes('/director/') ? '../api/get_user_alerts.php' :
                       currentPath.includes('/department/') ? '../api/get_user_alerts.php' :
                       'api/get_user_alerts.php';
        
        console.log('Checking notifications from:', apiPath);
        console.log('Current path:', currentPath);
        const response = await fetch(apiPath);
        const data = await response.json();
        
        console.log('Notification API response:', data);
        console.log('Response status:', response.status);
        
        if (data.success) {
            const currentCount = data.unread_count || 0;
            console.log('Current notification count:', currentCount, 'Previous count:', lastNotificationCount);
            
            // Update notification badge
            updateNotificationBadge(currentCount);
            
            // Only check for new notifications if we have a previous count to compare
            if (lastNotificationCount !== null && currentCount > lastNotificationCount) {
                console.log('New notifications detected! Updating dropdown...');
                updateNotificationDropdown(data.alerts || []);
                // Show a subtle notification that new alerts arrived
                showNewNotificationToast();
            } else if (lastNotificationCount === null) {
                // First time loading - just update the dropdown without showing toast
                console.log('Initial notification load - updating dropdown...');
                updateNotificationDropdown(data.alerts || []);
            }
            
            lastNotificationCount = currentCount;
        } else {
            console.log('API returned error:', data.error);
        }
    } catch (error) {
        console.log('Notification check failed:', error);
    }
}

function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

// Function to refresh notification dropdown from API
function refreshNotificationDropdown() {
    // Determine the correct API path based on current URL
    const currentPath = window.location.pathname;
    const apiPath = currentPath.includes('/user/') ? '../api/get_user_alerts.php' : 
                   currentPath.includes('/admin/') ? '../api/get_user_alerts.php' :
                   currentPath.includes('/director/') ? '../api/get_user_alerts.php' :
                   currentPath.includes('/department/') ? '../api/get_user_alerts.php' :
                   'api/get_user_alerts.php';
    
    fetch(apiPath)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationDropdown(data.alerts);
                updateNotificationBadge(data.unread_count);
            }
        })
        .catch(error => {
            console.error('Error refreshing notifications:', error);
        });
}

function updateNotificationDropdown(alertsOrCount) {
    // If called with just a count, refresh from API
    if (typeof alertsOrCount === 'number') {
        refreshNotificationDropdown();
        return;
    }
    
    const dropdown = document.getElementById('notificationDropdown');
    if (!dropdown) return;
    
    const alertsContainer = dropdown.querySelector('.max-h-64');
    if (!alertsContainer) return;
    
    if (alertsOrCount.length === 0) {
        alertsContainer.innerHTML = `
            <div class="p-8 text-center">
                <i class="fas fa-bell-slash text-4xl text-slate-500 mb-4"></i>
                <p class="text-slate-400">No notifications</p>
            </div>
        `;
        return;
    }
    
    let alertsHTML = '';
    alertsOrCount.forEach((alert, index) => {
        const iconClass = getAlertIconClass(alert.alert_type);
        const title = getAlertTitle(alert.alert_type);
        const message = alert.message.length > 150 ? alert.message.substring(0, 150) + '...' : alert.message;
        const date = new Date(alert.created_at).toLocaleDateString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
        });
        const timeAgo = getTimeAgo(new Date(alert.created_at));
        
        // Safely escape all content for JavaScript
        const safeAlertType = alert.alert_type.replace(/'/g, "\\'");
        const safeMessage = alert.message.replace(/'/g, "\\'").replace(/"/g, '\\"');
        const safeSentBy = (alert.sent_by_name || 'System').replace(/'/g, "\\'");
        const safeTitle = title.replace(/'/g, "\\'");
        
        alertsHTML += `
            <div class="notification-item p-3 border-b border-slate-700/50 hover:bg-slate-700/30 hover:border-blue-500/30 transition-all duration-200 cursor-pointer group" 
                 onclick="console.log('🖱️ Notification clicked!'); openNotificationModal(${alert.id}, '${safeAlertType}', '${safeMessage}', '${alert.created_at}', '${safeSentBy}')"
                 data-alert-id="${alert.id}"
                 title="Click to view full message">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 ${iconClass.bg} rounded-full flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform duration-200">
                        <i class="${iconClass.icon} text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <h4 class="text-sm font-semibold text-white group-hover:text-blue-300 transition-colors truncate">${safeTitle}</h4>
                            <span class="text-xs text-slate-500 bg-slate-700/50 px-2 py-0.5 rounded-full flex-shrink-0 ml-2">${timeAgo}</span>
                        </div>
                        <p class="text-xs text-slate-400 truncate mb-1">${alert.message.length > 60 ? alert.message.substring(0, 60) + '...' : alert.message}</p>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">
                                ${safeSentBy}
                            </span>
                            <span class="text-xs text-blue-400 group-hover:text-blue-300 transition-colors flex items-center font-medium">
                                View <i class="fas fa-arrow-right ml-1 text-xs group-hover:translate-x-1 transition-transform"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    alertsContainer.innerHTML = alertsHTML;
}

function getAlertIconClass(alertType) {
    switch(alertType) {
        case 'civil_service_compliance':
            return { bg: 'bg-purple-500/20', icon: 'fas fa-balance-scale text-purple-400' };
        case 'low_utilization':
            return { bg: 'bg-orange-500/20', icon: 'fas fa-chart-line text-orange-400' };
        case 'year_end_reminder':
            return { bg: 'bg-red-500/20', icon: 'fas fa-calendar-times text-red-400' };
        case 'custom':
            return { bg: 'bg-green-500/20', icon: 'fas fa-bell text-green-400' };
        default:
            return { bg: 'bg-yellow-500/20', icon: 'fas fa-exclamation-triangle text-yellow-400' };
    }
}

function getAlertTitle(alertType) {
    switch(alertType) {
        case 'civil_service_compliance':
            return 'Civil Service Compliance Alert';
        case 'low_utilization':
            return 'Leave Utilization Reminder';
        case 'year_end_reminder':
            return 'Year-End Leave Reminder';
        case 'custom':
            return 'Custom Alert';
        default:
            return 'Leave Alert';
    }
}

function showNewNotificationToast() {
    // Check if there's already a toast notification showing
    const existingToast = document.querySelector('.fixed.top-20.right-4.bg-blue-600');
    if (existingToast) {
        return; // Don't show duplicate toasts
    }
    
    // Create a subtle toast notification
    const toast = document.createElement('div');
    toast.className = 'fixed top-20 right-4 bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300 cursor-pointer';
    toast.innerHTML = '<i class="fas fa-bell mr-2"></i>New notification received!';
    
    // Make toast clickable to open notifications
    toast.onclick = function() {
        toggleNotificationDropdown();
        document.body.removeChild(toast);
    };
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
    }, 100);
    
    // Remove after 5 seconds
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 300);
    }, 5000);
}

// Helper function to get time ago
function getTimeAgo(date) {
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) {
        return 'Just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes}m ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours}h ago`;
    } else {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days}d ago`;
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Function to mark notification as read
function markNotificationAsRead(alertId) {
    // Determine the correct API path based on current URL
    const currentPath = window.location.pathname;
    const apiPath = currentPath.includes('/user/') ? '../api/mark_notification_read.php' : 
                   currentPath.includes('/admin/') ? '../api/mark_notification_read.php' :
                   currentPath.includes('/director/') ? '../api/mark_notification_read.php' :
                   currentPath.includes('/department/') ? '../api/mark_notification_read.php' :
                   'api/mark_notification_read.php';
    
    fetch(apiPath, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'alert_id=' + encodeURIComponent(alertId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Notification marked as read:', alertId);
            // Update the notification badge count
            updateNotificationBadge(data.unread_count);
            // Update the dropdown to reflect the change
            updateNotificationDropdown(data.unread_count);
        } else {
            console.error('Failed to mark notification as read:', data.error);
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

// Function to open notification modal
window.openNotificationModal = function(alertId, alertType, message, createdAt, sentBy) {
    try {
        console.log('🔔 Opening notification modal:', { alertId, alertType, message, createdAt, sentBy });
        console.log('📍 Current URL:', window.location.pathname);
        console.log('🎯 Function called successfully!');
        
        // Create modal overlay
        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 elms-modal-overlay';
        modalOverlay.style.position = 'fixed';
        modalOverlay.style.zIndex = '9999';
        console.log('Created modal overlay:', modalOverlay);
    
    // Create modal content
    const modalContent = document.createElement('div');
    modalContent.className = 'bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-lg max-h-[80vh] overflow-y-auto shadow-2xl elms-modal';
    modalContent.style.zIndex = '10000';
    
    const iconClass = getAlertIconClass(alertType);
    const title = getAlertTitle(alertType);
    const formattedDate = new Date(createdAt).toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
    
    // Safely escape content to prevent template literal injection
    const safeMessage = message.replace(/`/g, '\\`').replace(/\$/g, '\\$').replace(/\\/g, '\\\\');
    const safeTitle = title.replace(/`/g, '\\`').replace(/\$/g, '\\$').replace(/\\/g, '\\\\');
    const safeSentBy = sentBy.replace(/`/g, '\\`').replace(/\$/g, '\\$').replace(/\\/g, '\\\\');
    
    modalContent.innerHTML = `
        <div class="p-6 border-b border-slate-700 bg-gradient-to-r from-slate-800 to-slate-750">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="w-14 h-14 ${iconClass.bg} rounded-full flex items-center justify-center shadow-lg">
                        <i class="${iconClass.icon} text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-white mb-1">${safeTitle}</h3>
                        <p class="text-sm text-slate-400 flex items-center">
                            <i class="fas fa-hashtag mr-1"></i>
                            Alert ID: ${alertId}
                        </p>
                    </div>
                </div>
                <button onclick="closeNotificationModal()" class="text-slate-400 hover:text-white hover:bg-slate-700 p-2 rounded-full transition-all duration-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6 bg-slate-800/50">
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-slate-300 mb-3 flex items-center">
                    <i class="fas fa-message mr-2"></i>
                    Message Details
                </h4>
                <div class="bg-slate-700/30 border border-slate-600/50 rounded-xl p-5 max-h-64 overflow-y-auto shadow-inner">
                    <p class="text-slate-200 leading-relaxed whitespace-pre-line text-sm">${safeMessage}</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
                <div class="bg-slate-700/20 rounded-lg p-4 border border-slate-600/30">
                    <h4 class="font-semibold text-slate-300 mb-2 flex items-center">
                        <i class="fas fa-user-circle mr-2 text-blue-400"></i>
                        Sent By
                    </h4>
                    <p class="text-slate-400 flex items-center">
                        <i class="fas fa-user mr-2"></i>
                        ${safeSentBy}
                    </p>
                </div>
                <div class="bg-slate-700/20 rounded-lg p-4 border border-slate-600/30">
                    <h4 class="font-semibold text-slate-300 mb-2 flex items-center">
                        <i class="fas fa-calendar-alt mr-2 text-green-400"></i>
                        Date & Time
                    </h4>
                    <p class="text-slate-400 flex items-center">
                        <i class="fas fa-clock mr-2"></i>
                        ${formattedDate}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="p-6 border-t border-slate-700 bg-slate-800/30 flex justify-end">
            <button onclick="closeNotificationModal()" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-8 rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105">
                <i class="fas fa-check mr-2"></i>
                Mark as Read
            </button>
        </div>
    `;
    
    modalOverlay.appendChild(modalContent);
    document.body.appendChild(modalOverlay);
    console.log('Modal appended to DOM:', document.body.contains(modalOverlay));
    
    // Mark notification as read when modal is opened
    markNotificationAsRead(alertId);
    
    // Animate modal in
    requestAnimationFrame(() => {
        console.log('Animating modal in...');
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
        console.log('Modal classes after animation:', modalContent.className);
    });
    
    // Close modal when clicking outside
    modalOverlay.onclick = function(e) {
        if (e.target === modalOverlay) {
            closeNotificationModal();
        }
    };
    
    // Store modal reference for closing
    window.currentNotificationModal = modalOverlay;
    } catch (error) {
        console.error('Error opening notification modal:', error);
        alert('Error opening notification: ' + error.message);
    }
}

// Function to close notification modal
window.closeNotificationModal = function() {
    if (window.currentNotificationModal) {
        const modalContent = window.currentNotificationModal.querySelector('div');
        if (modalContent) {
            // Animate out
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            
            // Remove after animation
            setTimeout(() => {
                if (document.body.contains(window.currentNotificationModal)) {
                    document.body.removeChild(window.currentNotificationModal);
                }
                window.currentNotificationModal = null;
            }, 300);
        } else {
            document.body.removeChild(window.currentNotificationModal);
            window.currentNotificationModal = null;
        }
    }
}


// Start polling when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Clear any existing toast notifications from previous sessions
    const existingToasts = document.querySelectorAll('.fixed.top-20.right-4.bg-blue-600');
    existingToasts.forEach(toast => {
        if (document.body.contains(toast)) {
            document.body.removeChild(toast);
        }
    });
    
    // Clean up any misplaced elements in dropdowns
    cleanupDropdowns();
    
    startNotificationPolling();
});

// Function to clean up misplaced elements in dropdowns
function cleanupDropdowns() {
    const userDropdown = document.getElementById('userDropdown');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    [userDropdown, notificationDropdown].forEach(dropdown => {
        if (dropdown) {
            // Remove any input elements that shouldn't be in the dropdown
            const misplacedInputs = dropdown.querySelectorAll('input');
            misplacedInputs.forEach(input => {
                input.remove();
            });
            
            // Ensure proper styling
            dropdown.style.position = 'absolute';
            dropdown.style.zIndex = '1000';
            dropdown.style.isolation = 'isolate';
        }
    });
}

// Stop polling when page unloads
window.addEventListener('beforeunload', function() {
    stopNotificationPolling();
});

// Real-time Alerts System for Navbar
let navbarAlertPollingInterval;
let lastNavbarAlertCount = 0;

// Initialize navbar alerts (only for regular users)
function initNavbarAlerts() {
    // Check if user role is admin, manager, or director - skip navbar alerts for these roles
    const userRole = document.body.getAttribute('data-user-role') || 
                    document.querySelector('[data-user-role]')?.getAttribute('data-user-role') ||
                    'user';
    
    console.log('initNavbarAlerts called - detected role:', userRole);
    
    if (userRole === 'admin' || userRole === 'manager' || userRole === 'director') {
        console.log('Navbar alerts disabled for role:', userRole);
        return;
    }
    
    console.log('Navbar alerts enabled for role:', userRole);
    loadNavbarAlerts();
    startNavbarAlertPolling();
}

// Load alerts for navbar dropdown
async function loadNavbarAlerts() {
    // Check role before loading alerts
    const userRole = document.body.getAttribute('data-user-role') || 
                    document.querySelector('[data-user-role]')?.getAttribute('data-user-role') ||
                    'user';
    
    if (userRole === 'admin' || userRole === 'manager' || userRole === 'director') {
        console.log('loadNavbarAlerts blocked for role:', userRole);
        return;
    }
    
    const apiUrl = '/ELMS/api/get_realtime_alerts.php';
    
    try {
        const response = await fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            throw new Error('Invalid JSON response from server');
        }
        
        if (data.success) {
            displayNavbarAlerts(data.alerts);
            updateNavbarAlertBadge(data.unread_count);
            lastNavbarAlertCount = data.alerts.length;
            
            // ALWAYS show floating notification for unread alerts
            if (data.unread_count > 0) {
                const unreadAlerts = data.alerts.filter(alert => alert.is_read == 0);
                if (unreadAlerts.length > 0) {
                    const latestUnreadAlert = unreadAlerts[0];
                    
                    // Check if we've already shown this alert
                    const alertKey = 'shown_alert_' + latestUnreadAlert.id;
                    if (!localStorage.getItem(alertKey)) {
                        setTimeout(() => {
                            showNavbarNotification(
                                `📢 ${getNavbarAlertTitle(latestUnreadAlert.alert_type)}: ${latestUnreadAlert.message.substring(0, 50)}${latestUnreadAlert.message.length > 50 ? '...' : ''}`,
                                'alert',
                                5000
                            );
                            // Mark this alert as shown
                            localStorage.setItem(alertKey, 'true');
                        }, 1000);
                    }
                }
            }
        } else {
            console.error('Navbar: API returned error:', data.error);
            showNavbarAlertError('Failed to load alerts: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Navbar: Error fetching alerts:', error);
        showNavbarAlertError('Network error loading alerts: ' + error.message);
    }
}

// Display alerts in navbar dropdown
function displayNavbarAlerts(alerts) {
    const container = document.getElementById('navbarAlertsContainer');
    
    if (alerts.length === 0) {
        container.innerHTML = `
            <div class="p-8 text-center">
                <i class="fas fa-bell-slash text-4xl text-slate-500 mb-4"></i>
                <p class="text-slate-400">No notifications</p>
            </div>
        `;
        return;
    }

    container.innerHTML = alerts.slice(0, 5).map(alert => `
        <div class="notification-item p-3 border-b border-slate-700/50 hover:bg-slate-700/30 hover:border-blue-500/30 transition-all duration-200 cursor-pointer group" 
             data-alert-id="${alert.id}"
             data-alert-type="${alert.alert_type}"
             data-message="${alert.message.replace(/"/g, '&quot;')}"
             data-created-at="${alert.created_at}"
             data-sent-by="${(alert.sent_by_name || 'System').replace(/"/g, '&quot;')}"
             title="Click to view full message">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 ${alert.alert_color.split(' ')[1]} rounded-full flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform duration-200">
                    <i class="${alert.alert_icon} ${alert.alert_color.split(' ')[0]} text-xs"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                        <h4 class="text-sm font-semibold text-white group-hover:text-blue-300 transition-colors truncate">
                            ${getNavbarAlertTitle(alert.alert_type)}
                        </h4>
                        <span class="text-xs text-slate-500 bg-slate-700/50 px-2 py-0.5 rounded-full flex-shrink-0 ml-2">${alert.time_ago}</span>
                    </div>
                    <p class="text-xs text-slate-400 truncate mb-1">${alert.message.length > 60 ? alert.message.substring(0, 60) + '...' : alert.message}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">${alert.sent_by_name || 'System'}</span>
                        <span class="text-xs text-blue-400 group-hover:text-blue-300 transition-colors flex items-center font-medium">
                            View <i class="fas fa-arrow-right ml-1 text-xs group-hover:translate-x-1 transition-transform"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

// Get alert title for navbar
function getNavbarAlertTitle(alertType) {
    const titles = {
        'urgent_year_end': '🚨 Urgent Year-End Alert',
        'csc_utilization_low': '📊 CSC Utilization Low',
        'critical_utilization': '⚠️ Critical Low Utilization',
        'csc_limit_exceeded': '🚫 CSC Limit Exceeded',
        'csc_limit_approaching': '⚠️ CSC Limit Approaching',
        'year_end_critical': '🔥 Year-End Critical',
        'year_end_warning': '⚠️ Year-End Warning',
        'moderate_reminder': '📋 Moderate Reminder',
        'planning_reminder': '📅 Planning Reminder',
        'csc_compliance': '📜 CSC Compliance Notice',
        'wellness_focus': '💚 Wellness Focus',
        'custom': '✏️ Custom Message'
    };
    return titles[alertType] || '📢 Leave Alert';
}

// Update navbar alert badge
function updateNavbarAlertBadge(count) {
    const badge = document.getElementById('navbarAlertBadge');
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

// Show navbar alert error
function showNavbarAlertError(message) {
    const container = document.getElementById('navbarAlertsContainer');
    container.innerHTML = `
        <div class="p-8 text-center">
            <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-4"></i>
            <p class="text-red-400">${message}</p>
            <button onclick="loadNavbarAlerts()" class="mt-4 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors text-sm">
                Try Again
            </button>
        </div>
    `;
}

// Start polling for navbar alerts
function startNavbarAlertPolling() {
    // Check role before starting polling
    const userRole = document.body.getAttribute('data-user-role') || 
                    document.querySelector('[data-user-role]')?.getAttribute('data-user-role') ||
                    'user';
    
    if (userRole === 'admin' || userRole === 'manager' || userRole === 'director') {
        console.log('startNavbarAlertPolling blocked for role:', userRole);
        return;
    }
    
    console.log('Starting navbar alert polling for role:', userRole);
    navbarAlertPollingInterval = setInterval(() => {
        checkForNewNavbarAlerts();
    }, 5000); // Poll every 5 seconds for faster response
}

// Check for new navbar alerts
async function checkForNewNavbarAlerts() {
    // Double-check role before proceeding
    const userRole = document.body.getAttribute('data-user-role') || 
                    document.querySelector('[data-user-role]')?.getAttribute('data-user-role') ||
                    'user';
    
    if (userRole === 'admin' || userRole === 'manager' || userRole === 'director') {
        console.log('checkForNewNavbarAlerts blocked for role:', userRole);
        return;
    }
    
    try {
        const response = await fetch('/ELMS/api/get_realtime_alerts.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.alerts) {
            // Show floating notification for unread alerts
            const unreadAlerts = data.alerts.filter(alert => alert.is_read == 0);
            
            if (unreadAlerts.length > 0) {
                const latestUnreadAlert = unreadAlerts[0];
                
                // Check if we've already shown this specific alert
                const alertKey = 'shown_alert_' + latestUnreadAlert.id;
                if (!localStorage.getItem(alertKey)) {
                    const alertTitle = getNavbarAlertTitle(latestUnreadAlert.alert_type);
                    const alertMessage = latestUnreadAlert.message ? 
                        latestUnreadAlert.message.substring(0, 50) + (latestUnreadAlert.message.length > 50 ? '...' : '') : 
                        'New alert received';
                    
                    showNavbarNotification(
                        `📢 ${alertTitle}: ${alertMessage}`,
                        'alert',
                        5000
                    );
                    // Mark this alert as shown
                    localStorage.setItem(alertKey, 'true');
                }
            }
            
            lastNavbarAlertCount = data.alerts.length;
            loadNavbarAlerts();
        } else {
            console.warn('Failed to fetch alerts:', data.error || 'Unknown error');
        }
    } catch (error) {
        console.error('Error checking for new navbar alerts:', error);
    }
}

// Show navbar notification
function showNavbarNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `fixed top-20 right-4 z-50 px-6 py-4 rounded-xl shadow-lg transition-all duration-500 transform translate-x-full ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-green-600 text-white' :
        type === 'info' ? 'bg-green-500 text-white' :
        type === 'alert' ? 'bg-green-600 text-white' :
        'bg-green-500 text-white'
    }`;
    
    notification.innerHTML = `
        <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
                <i class="fas ${
                    type === 'success' ? 'fa-check-circle text-xl' :
                    type === 'error' ? 'fa-exclamation-triangle text-xl' :
                    type === 'info' ? 'fa-info-circle text-xl' :
                    type === 'alert' ? 'fa-bell text-xl' :
                    'fa-bell text-xl'
                }"></i>
            </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold">${message}</p>
                        <div class="w-full bg-white/30 rounded-full h-1 mt-2">
                            <div class="bg-white h-1 rounded-full transition-all duration-100 ease-linear" style="width: 100%"></div>
                        </div>
                    </div>
            <button onclick="closeNavbarNotification(this)" class="flex-shrink-0 text-white/80 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
        notification.classList.add('translate-x-0');
    }, 100);
    
    // Progress bar animation
    const progressBar = notification.querySelector('.bg-white');
    if (progressBar) {
        setTimeout(() => {
            progressBar.style.width = '0%';
        }, 100);
    }
    
    // Auto remove after specified duration
    setTimeout(() => {
        closeNavbarNotification(notification.querySelector('button'));
    }, duration);
}

// Close navbar notification
function closeNavbarNotification(button) {
    const notification = button.closest('.fixed');
    if (notification) {
        notification.classList.add('translate-x-full');
        notification.classList.remove('translate-x-0');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 500);
    }
}

// View alert message in modal
function viewAlertMessage(alertId, alertType, message, createdAt, sentBy) {
    // Close notification dropdown first
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        dropdown.classList.add('hidden');
    }
    
    // Create modal for viewing alert
    const modal = document.createElement('div');
    modal.id = 'alertViewModal';
    modal.className = 'fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="bg-slate-800/95 backdrop-blur-sm rounded-2xl border border-slate-700 max-w-2xl w-full max-h-[80vh] shadow-2xl transform transition-all duration-300 scale-95 opacity-0 flex flex-col">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-700 bg-gradient-to-r from-slate-800/50 to-slate-700/30 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-bell text-white text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">${getNavbarAlertTitle(alertType)}</h3>
                            <p class="text-slate-400 text-xs">Alert Details</p>
                        </div>
                    </div>
                    <button type="button" class="w-8 h-8 bg-slate-700/50 hover:bg-slate-600/50 rounded-lg flex items-center justify-center text-slate-400 hover:text-white transition-all duration-200" onclick="closeAlertViewModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="px-6 py-4 flex-1 overflow-y-auto">
                <div class="space-y-4">
                    <!-- Alert Info -->
                    <div class="bg-slate-700/30 rounded-xl p-4 border border-slate-600/30">
                        <div class="flex items-center space-x-3 mb-3">
                            <div class="w-8 h-8 bg-slate-600 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-slate-300 text-sm"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-white">From: ${sentBy}</h4>
                                <p class="text-xs text-slate-400">${new Date(createdAt).toLocaleString()}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Message Content -->
                    <div class="bg-slate-700/30 rounded-xl p-4 border border-slate-600/30">
                        <h4 class="text-sm font-semibold text-white mb-2">Message:</h4>
                        <div class="text-slate-300 text-sm leading-relaxed whitespace-pre-line">${message}</div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-slate-700 bg-slate-700/20 rounded-b-2xl flex justify-between items-center">
                <button onclick="markAlertAsRead(${alertId})" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm flex items-center">
                    <i class="fas fa-check mr-2"></i>Mark as Read
                </button>
                <button onclick="closeAlertViewModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors text-sm">
                    Close
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Animate in
    setTimeout(() => {
        const modalContent = modal.querySelector('.bg-slate-800\\/95');
        if (modalContent) {
            modalContent.style.transform = 'scale(1)';
            modalContent.style.opacity = '1';
        }
    }, 10);
}

// Close alert view modal
function closeAlertViewModal() {
    const modal = document.getElementById('alertViewModal');
    if (modal) {
        const modalContent = modal.querySelector('.bg-slate-800\\/95');
        if (modalContent) {
            modalContent.style.transform = 'scale(0.95)';
            modalContent.style.opacity = '0';
        }
        
        setTimeout(() => {
            if (modal.parentNode) {
                modal.parentNode.removeChild(modal);
            }
        }, 300);
    }
}

// Mark alert as read
async function markAlertAsRead(alertId) {
    try {
        const response = await fetch('/ELMS/api/mark_alert_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ alert_id: alertId })
        });
        
        const data = await response.json();
        if (data.success) {
            showNavbarNotification('Alert marked as read', 'success', 3000);
            closeAlertViewModal();
            loadNavbarAlerts(); // Refresh the alerts
        } else {
            showNavbarNotification('Error marking alert as read', 'error', 3000);
        }
    } catch (error) {
        console.error('Error marking alert as read:', error);
        showNavbarNotification('Network error', 'error', 3000);
    }
}

// Mark all navbar alerts as read
async function markAllNavbarAlertsRead() {
    try {
        console.log('Attempting to clear all alerts...');
        const response = await fetch('/ELMS/api/mark_all_alerts_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        });
        
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        console.log('Raw response:', text);
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            throw new Error('Invalid JSON response from server');
        }
        
        console.log('Parsed data:', data);
        
        if (data.success) {
            showNavbarNotification(`All alerts cleared (${data.updated_count} alerts)`, 'success', 3000);
            loadNavbarAlerts(); // Refresh the alerts
        } else {
            console.error('API returned error:', data.error);
            showNavbarNotification(`Error: ${data.error}`, 'error', 5000);
        }
    } catch (error) {
        console.error('Error clearing alerts:', error);
        showNavbarNotification(`Error: ${error.message}`, 'error', 5000);
    }
}




// Event delegation for notification clicks
document.addEventListener('click', function(event) {
    const notificationItem = event.target.closest('.notification-item');
    if (notificationItem) {
        console.log('🖱️ Notification clicked via event delegation!');
        
        const alertId = notificationItem.getAttribute('data-alert-id');
        const alertType = notificationItem.getAttribute('data-alert-type');
        const message = notificationItem.getAttribute('data-message');
        const createdAt = notificationItem.getAttribute('data-created-at');
        const sentBy = notificationItem.getAttribute('data-sent-by');
        
        console.log('Alert data:', { alertId, alertType, message, createdAt, sentBy });
        
        if (typeof window.openNotificationModal === 'function') {
            window.openNotificationModal(alertId, alertType, message, createdAt, sentBy);
        } else {
            console.error('❌ openNotificationModal function not available!');
        }
    }
});

// Initialize navbar alerts when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize for all users
    initNavbarAlerts();
    console.log('🚀 Navbar initialized. Test notification modal with: testNotificationModal()');
    console.log('🎯 Event delegation set up for notification clicks');
});

// Clean up navbar polling when page is hidden
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        if (navbarAlertPollingInterval) {
            clearInterval(navbarAlertPollingInterval);
        }
    } else {
        if (!navbarAlertPollingInterval) {
            startNavbarAlertPolling();
        }
        // Refresh alerts when page becomes visible
        loadNavbarAlerts();
    }
});

// ===== Global Loading Overlay Helpers (shared across pages) =====
window.showGlobalLoading = function(message = 'Processing...') {
    if (document.getElementById('elmsLoadingOverlay')) return;
    const overlay = document.createElement('div');
    overlay.id = 'elmsLoadingOverlay';
    overlay.className = 'elms-loading-overlay';
    overlay.innerHTML = `
      <div class="elms-loading-box">
        <span class="elms-spinner" aria-hidden="true"></span>
        <span>${message}</span>
      </div>
    `;
    document.body.appendChild(overlay);
};

window.hideGlobalLoading = function() {
    const overlay = document.getElementById('elmsLoadingOverlay');
    if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
};

// Auto-bind overlay to elements with data-loading and forms with .elms-action-form
document.addEventListener('DOMContentLoaded', function() {
    document.body.addEventListener('click', function(e) {
        const el = e.target.closest('[data-loading]');
        if (el) {
            const msg = el.getAttribute('data-loading') || 'Processing...';
            showGlobalLoading(msg);
        }
    });
    document.body.addEventListener('submit', function(e) {
        const form = e.target.closest('form.elms-action-form');
        if (form) {
            const msg = form.getAttribute('data-loading') || 'Processing...';
            showGlobalLoading(msg);
        }
    });
});
</script>
