<?php
// User Header Component
// Include this file at the top of all user/employee pages

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

// Fetch user details if not already set
if (!isset($employee)) {
    require_once '../../../../config/database.php';
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $employee = $stmt->fetch();

    if (!$employee) {
        session_destroy();
        header('Location: ../../../../auth/views/login.php');
        exit();
    }
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'ELMS Employee'; ?></title>
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../../../assets/css/elms-dark-theme.css">
    <link rel="icon" type="image/png" href="/ELMS/elmsicon.png">
    <link rel="shortcut icon" href="/ELMS/elmsicon.png">
    <link rel="apple-touch-icon" href="/ELMS/elmsicon.png">
</head>
<body style="background-color: #0f172a; margin: 0;">
    <!-- Top Navbar -->
    <nav class="elms-navbar">
        <div class="elms-navbar-content">
            <div class="elms-logo">
                <span class="elms-logo-text">ELMS Employee</span>
            </div>
            
            <div style="display: flex; align-items: center; gap: 1rem; margin-left: auto;">
                <!-- Notifications Bell -->
                <div style="position: relative;">
                    <button onclick="toggleNotificationDropdown()" onmouseover="this.style.color='white'; this.style.backgroundColor='#334155'" onmouseout="this.style.color='#cbd5e1'; this.style.backgroundColor='transparent'" style="position: relative; padding: 0.5rem; color: #cbd5e1; background: transparent; border: none; cursor: pointer; border-radius: 0.5rem; transition: all 0.2s;">
                        <i class="fas fa-bell" style="font-size: 1.25rem;"></i>
                        <span id="navbarAlertBadge" style="display: none; position: absolute; top: -4px; right: -4px; background: #ef4444; color: white; font-size: 0.75rem; border-radius: 9999px; height: 20px; width: 20px; align-items: center; justify-content: center; font-weight: 600;">
                            0
                        </span>
                    </button>
                    
                    <!-- Notification Dropdown -->
                    <div id="notificationDropdown" style="display: none; position: absolute; right: 0; margin-top: 0.5rem; width: 24rem; background: #1e293b; border: 1px solid #334155; border-radius: 0.75rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); z-index: 100; max-height: 400px;">
                        <div style="padding: 1rem; border-bottom: 1px solid #334155;">
                            <h3 style="font-size: 1.125rem; font-weight: 600; color: white; display: flex; align-items: center;">
                                <i class="fas fa-bell" style="margin-right: 0.5rem;"></i>
                                Notifications
                            </h3>
                        </div>
                        <div id="navbarAlertsContainer" style="max-height: 16rem; overflow-y: auto;">
                            <div style="text-align: center; padding: 2rem;">
                                <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; color: #64748b; margin-bottom: 1rem;"></i>
                                <p style="color: #94a3b8;">Loading alerts...</p>
                            </div>
                        </div>
                        <div style="padding: 0.75rem; border-top: 1px solid #334155; display: flex; justify-content: center; align-items: center;">
                            <button onclick="markAllNavbarAlertsRead()" style="font-size: 0.75rem; color: #94a3b8; background: none; border: none; cursor: pointer; display: flex; align-items: center;">
                                <i class="fas fa-check-double" style="margin-right: 0.25rem;"></i>Clear All
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- User Dropdown -->
                <div style="position: relative;">
                    <button onclick="toggleUserDropdown()" style="display: flex; align-items: center; gap: 0.5rem; background: none; border: none; cursor: pointer; padding: 0;">
                        <?php if (!empty($employee['profile_picture'])): ?>
                            <img src="../../../../uploads/profile_pictures/<?php echo htmlspecialchars($employee['profile_picture']); ?>" 
                                 alt="Profile" 
                                 style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #10b981;">
                        <?php else: ?>
                            <div style="width: 32px; height: 32px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.875rem;">
                                <?php echo strtoupper(substr($employee['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div style="text-align: left;">
                            <div style="color: white; font-weight: 600; font-size: 0.875rem;">
                                <?php echo htmlspecialchars($employee['name']); ?>
                            </div>
                        </div>
                        <i class="fas fa-chevron-down" style="color: #cbd5e1; font-size: 0.625rem;"></i>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div id="userDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 0.5rem; width: 260px; background: #1e293b; border: 1px solid #334155; border-radius: 0.75rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); z-index: 100;">
                        <!-- Dropdown Header -->
                        <div style="padding: 1rem; border-bottom: 1px solid #334155;">
                            <div style="font-weight: 600; color: white; margin-bottom: 0.25rem; font-size: 0.9375rem;">
                                <?php echo htmlspecialchars($employee['name']); ?>
                            </div>
                            <div style="color: #94a3b8; font-size: 0.8125rem; margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($employee['email'] ?? 'employee@elms.com'); ?>
                            </div>
                            <span style="display: inline-block; background: #10b981; color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.6875rem; font-weight: 600;">
                                Employee
                            </span>
                        </div>
                        
                        <!-- Dropdown Items -->
                        <div style="padding: 0.5rem 0;">
                            <a href="profile.php" style="display: block; padding: 0.75rem 1rem; color: #cbd5e1; text-decoration: none; transition: all 0.2s; font-size: 0.875rem; font-weight: 500;">
                                <i class="fas fa-user" style="margin-right: 0.625rem; width: 18px; font-size: 0.875rem;"></i>
                                My Profile
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

    <!-- Sidebar -->
    <aside class="elms-sidebar">
        <nav>
            <!-- Leave Application Section -->
            <div class="elms-sidebar-section">
                <h3 class="elms-sidebar-header">Leave Application</h3>
                <a href="dashboard.php" class="elms-sidebar-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt elms-sidebar-icon"></i>
                    <span>Leave Application</span>
                </a>
            </div>
            
            <!-- Leave Management Section -->
            <div class="elms-sidebar-section">
                <h3 class="elms-sidebar-header">Leave Management</h3>
                <a href="leave_history.php" class="elms-sidebar-link <?php echo ($current_page == 'leave_history.php') ? 'active' : ''; ?>">
                    <i class="fas fa-history elms-sidebar-icon"></i>
                    <span>Leave History</span>
                </a>
                <a href="leave_credits.php" class="elms-sidebar-link <?php echo ($current_page == 'leave_credits.php') ? 'active' : ''; ?>">
                    <i class="fas fa-calculator elms-sidebar-icon"></i>
                    <span>Leave Credits</span>
                </a>
            </div>
            
            <!-- Reports Section -->
            <div class="elms-sidebar-section">
                <h3 class="elms-sidebar-header">Reports</h3>
                <a href="calendar.php" class="elms-sidebar-link <?php echo ($current_page == 'calendar.php') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar elms-sidebar-icon"></i>
                    <span>Leave Chart</span>
                </a>
            </div>
            
            <!-- Account Section -->
            <div class="elms-sidebar-section">
                <h3 class="elms-sidebar-header">Account</h3>
                <a href="profile.php" class="elms-sidebar-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle elms-sidebar-icon"></i>
                    <span>Profile</span>
                </a>
            </div>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="elms-main">
    
    <script>
        let navbarAlertPollingInterval = null;
        let lastNavbarAlertCount = 0;
        
        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            const userDropdown = document.getElementById('userDropdown');
            
            // Close user dropdown if open
            if (userDropdown) {
                userDropdown.style.display = 'none';
            }
            
            // Toggle notification dropdown
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            
            // Load notifications if opening
            if (dropdown.style.display === 'block') {
                loadNavbarAlerts();
            }
        }
        
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            // Close notification dropdown if open
            if (notificationDropdown) {
                notificationDropdown.style.display = 'none';
            }
            
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
        
        // Load alerts for navbar dropdown
        async function loadNavbarAlerts() {
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
                
                const data = await response.json();
                
                if (data.success) {
                    displayNavbarAlerts(data.alerts);
                    updateNavbarAlertBadge(data.unread_count);
                    lastNavbarAlertCount = data.alerts.length;
                } else {
                    showNavbarAlertError('Failed to load alerts: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error fetching alerts:', error);
                showNavbarAlertError('Network error loading alerts');
            }
        }
        
        // Display alerts in navbar dropdown
        function displayNavbarAlerts(alerts) {
            const container = document.getElementById('navbarAlertsContainer');
            
            if (alerts.length === 0) {
                container.innerHTML = `
                    <div style="padding: 2rem; text-align: center;">
                        <i class="fas fa-bell-slash" style="font-size: 2.5rem; color: #64748b; margin-bottom: 1rem;"></i>
                        <p style="color: #94a3b8;">No notifications</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = alerts.slice(0, 5).map(alert => `
                <div class="notification-item" style="padding: 0.75rem; border-bottom: 1px solid rgba(51, 65, 85, 0.5); cursor: pointer; transition: all 0.2s;"
                     onmouseover="this.style.backgroundColor='rgba(51, 65, 85, 0.3)'" 
                     onmouseout="this.style.backgroundColor='transparent'"
                     data-alert-id="${alert.id}"
                     data-alert-type="${alert.alert_type}"
                     data-message="${alert.message.replace(/"/g, '&quot;')}"
                     data-created-at="${alert.created_at}"
                     data-sent-by="${(alert.sent_by_name || 'System').replace(/"/g, '&quot;')}">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 2rem; height: 2rem; background: ${alert.alert_color.split(' ')[1]}; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="${alert.alert_icon} ${alert.alert_color.split(' ')[0]}" style="font-size: 0.75rem;"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.25rem;">
                                <h4 style="font-size: 0.875rem; font-weight: 600; color: white; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    ${getNavbarAlertTitle(alert.alert_type)}
                                </h4>
                                <span style="font-size: 0.75rem; color: #64748b; background: rgba(51, 65, 85, 0.5); padding: 0.125rem 0.5rem; border-radius: 9999px; flex-shrink: 0; margin-left: 0.5rem;">${alert.time_ago}</span>
                            </div>
                            <p style="font-size: 0.75rem; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 0.25rem;">${alert.message.length > 60 ? alert.message.substring(0, 60) + '...' : alert.message}</p>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="font-size: 0.75rem; color: #64748b;">${alert.sent_by_name || 'System'}</span>
                                <span style="font-size: 0.75rem; color: #60a5fa; font-weight: 500;">View →</span>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        // Get alert title
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
        
        // Format alert message with proper HTML structure
        function formatAlertMessage(message) {
            if (!message) return '';
            
            // Convert literal \n to actual newlines
            message = message.replace(/\\n/g, '\n');
            
            // Split into lines
            let lines = message.split('\n');
            let html = '';
            let inList = false;
            
            for (let line of lines) {
                line = line.trim();
                
                if (!line) {
                    // Empty line - close list if open and add spacing
                    if (inList) {
                        html += '</ul>';
                        inList = false;
                    }
                    continue;
                }
                
                // Check if line starts with bullet point
                if (line.startsWith('•')) {
                    if (!inList) {
                        html += '<ul class="list-none space-y-2 my-3 ml-4">';
                        inList = true;
                    }
                    html += '<li class="flex items-start"><span class="text-yellow-400 mr-2">•</span><span class="flex-1">' + line.substring(1).trim() + '</span></li>';
                } else if (line.startsWith('Subject:')) {
                    // Subject line - make it bold and larger
                    if (inList) {
                        html += '</ul>';
                        inList = false;
                    }
                    html += '<div class="font-bold text-base text-blue-300 mb-4">' + line + '</div>';
                } else {
                    // Regular paragraph
                    if (inList) {
                        html += '</ul>';
                        inList = false;
                    }
                    html += '<p class="mb-3">' + line + '</p>';
                }
            }
            
            // Close list if still open
            if (inList) {
                html += '</ul>';
            }
            
            return html;
        }
        
        // Update navbar alert badge
        function updateNavbarAlertBadge(count) {
            const badge = document.getElementById('navbarAlertBadge');
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
        
        // Show navbar alert error
        function showNavbarAlertError(message) {
            const container = document.getElementById('navbarAlertsContainer');
            container.innerHTML = `
                <div style="padding: 2rem; text-align: center;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2.5rem; color: #ef4444; margin-bottom: 1rem;"></i>
                    <p style="color: #f87171;">${message}</p>
                    <button onclick="loadNavbarAlerts()" style="margin-top: 1rem; padding: 0.5rem 1rem; background: #dc2626; color: white; border: none; border-radius: 0.5rem; cursor: pointer; font-size: 0.875rem;">
                        Try Again
                    </button>
                </div>
            `;
        }
        
        // Mark all navbar alerts as read
        async function markAllNavbarAlertsRead() {
            try {
                const response = await fetch('/ELMS/api/mark_all_alerts_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    loadNavbarAlerts(); // Refresh the alerts
                    updateNavbarAlertBadge(0);
                }
            } catch (error) {
                console.error('Error clearing alerts:', error);
            }
        }
        
        // Start polling for navbar alerts
        function startNavbarAlertPolling() {
            navbarAlertPollingInterval = setInterval(() => {
                checkForNewNavbarAlerts();
            }, 10000); // Poll every 10 seconds for faster updates
        }
        
        // Check for new navbar alerts
        async function checkForNewNavbarAlerts() {
            try {
                const response = await fetch('/ELMS/api/get_realtime_alerts.php');
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success && data.alerts) {
                    const newAlertCount = data.unread_count || 0;
                    const previousCount = parseInt(document.getElementById('navbarAlertBadge')?.textContent || '0');
                    
                    // Update the list and badge
                    if (data.alerts.length > lastNavbarAlertCount || newAlertCount > previousCount) {
                        loadNavbarAlerts();
                        updateNavbarAlertBadge(data.unread_count);
                        
                        // Show toast notification if there are new unread alerts
                        if (newAlertCount > previousCount && newAlertCount > 0) {
                            const newAlertsCount = newAlertCount - previousCount;
                            showNewAlertToast(newAlertsCount);
                        }
                        
                        lastNavbarAlertCount = data.alerts.length;
                    }
                }
            } catch (error) {
                console.error('Error checking for new navbar alerts:', error);
            }
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const userDropdown = document.getElementById('userDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const button = event.target.closest('button');
            
            if (!button) {
                if (userDropdown && !userDropdown.contains(event.target)) {
                    userDropdown.style.display = 'none';
                }
                if (notificationDropdown && !notificationDropdown.contains(event.target)) {
                    notificationDropdown.style.display = 'none';
                }
            }
        });
        
        // Function to open notification modal
        window.openNotificationModal = function(alertId, alertType, message, createdAt, sentBy) {
            try {
                // Create modal overlay
                const modalOverlay = document.createElement('div');
                modalOverlay.className = 'fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4';
                modalOverlay.style.position = 'fixed';
                modalOverlay.style.zIndex = '9999';
            
                // Create modal content
                const modalContent = document.createElement('div');
                modalContent.className = 'bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-lg max-h-[80vh] overflow-y-auto shadow-2xl transform scale-95 opacity-0 transition-all duration-300 ease-out';
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
                
                modalContent.innerHTML = `
                    <div class="p-6 border-b border-slate-700 bg-gradient-to-r from-slate-800 to-slate-750">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="w-14 h-14 ${iconClass.bg} rounded-full flex items-center justify-center shadow-lg">
                                    <i class="${iconClass.icon} text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-white mb-1">${title}</h3>
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
                                <div class="text-slate-200 leading-relaxed text-sm space-y-3">${formatAlertMessage(message)}</div>
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
                                    ${sentBy}
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
                
                // Mark notification as read when modal is opened
                markNotificationAsRead(alertId);
                
                // Animate modal in
                requestAnimationFrame(() => {
                    modalContent.classList.remove('scale-95', 'opacity-0');
                    modalContent.classList.add('scale-100', 'opacity-100');
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
        
        // Get alert icon class
        function getAlertIconClass(alertType) {
            const iconClasses = {
                'urgent_year_end': { icon: 'fas fa-exclamation-triangle text-red-300', bg: 'bg-red-500/20' },
                'csc_utilization_low': { icon: 'fas fa-chart-line text-orange-300', bg: 'bg-orange-500/20' },
                'critical_utilization': { icon: 'fas fa-exclamation-circle text-red-300', bg: 'bg-red-500/20' },
                'csc_limit_exceeded': { icon: 'fas fa-ban text-red-300', bg: 'bg-red-500/20' },
                'csc_limit_approaching': { icon: 'fas fa-exclamation-triangle text-orange-300', bg: 'bg-orange-500/20' },
                'year_end_critical': { icon: 'fas fa-fire text-red-300', bg: 'bg-red-500/20' },
                'year_end_warning': { icon: 'fas fa-exclamation-triangle text-yellow-300', bg: 'bg-yellow-500/20' },
                'moderate_reminder': { icon: 'fas fa-bell text-blue-300', bg: 'bg-blue-500/20' },
                'planning_reminder': { icon: 'fas fa-calendar-check text-blue-300', bg: 'bg-blue-500/20' },
                'csc_compliance': { icon: 'fas fa-file-alt text-purple-300', bg: 'bg-purple-500/20' },
                'wellness_focus': { icon: 'fas fa-heart text-green-300', bg: 'bg-green-500/20' },
                'custom': { icon: 'fas fa-comment text-blue-300', bg: 'bg-blue-500/20' }
            };
            return iconClasses[alertType] || { icon: 'fas fa-bell text-blue-300', bg: 'bg-blue-500/20' };
        }
        
        // Get alert title
        function getAlertTitle(alertType) {
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
        
        // Mark notification as read
        async function markNotificationAsRead(alertId) {
            try {
                const response = await fetch('/ELMS/api/mark_alert_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ alert_id: alertId })
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success) {
                        loadNavbarAlerts(); // Refresh the alerts
                    }
                }
            } catch (error) {
                console.error('Error marking alert as read:', error);
            }
        }
        
        // Event delegation for notification clicks
        document.addEventListener('click', function(event) {
            const notificationItem = event.target.closest('.notification-item');
            if (notificationItem) {
                const alertId = notificationItem.getAttribute('data-alert-id');
                const alertType = notificationItem.getAttribute('data-alert-type');
                const message = notificationItem.getAttribute('data-message');
                const createdAt = notificationItem.getAttribute('data-created-at');
                const sentBy = notificationItem.getAttribute('data-sent-by');
                
                if (typeof window.openNotificationModal === 'function') {
                    window.openNotificationModal(alertId, alertType, message, createdAt, sentBy);
                }
            }
        });
        
        // Show toast notification for new alerts
        function showNewAlertToast(alertCount) {
            // Remove any existing toast
            const existingToast = document.getElementById('alertToast');
            if (existingToast) {
                existingToast.remove();
            }
            
            const toast = document.createElement('div');
            toast.id = 'alertToast';
            toast.style.cssText = 'position: fixed; top: 80px; right: 20px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 1rem 1.5rem; border-radius: 12px; box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4); z-index: 10000; display: flex; align-items: center; gap: 12px; animation: slideInRight 0.5s ease-out; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;';
            
            toast.innerHTML = `
                <i class="fas fa-bell" style="font-size: 1.5rem; animation: ring 1s ease-in-out;"></i>
                <div>
                    <div style="font-weight: 600; font-size: 0.95rem;">New Leave Alert!</div>
                    <div style="font-size: 0.85rem; opacity: 0.9;">${alertCount} new notification${alertCount > 1 ? 's' : ''}</div>
                </div>
                <button onclick="this.parentElement.remove()" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 24px; height: 24px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; margin-left: 8px;">
                    <i class="fas fa-times" style="font-size: 0.75rem;"></i>
                </button>
            `;
            
            // Add animations
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(400px); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes ring {
                    0%, 100% { transform: rotate(0deg); }
                    10%, 30% { transform: rotate(-10deg); }
                    20%, 40% { transform: rotate(10deg); }
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.style.animation = 'slideInRight 0.5s ease-out reverse';
                    setTimeout(() => toast.remove(), 500);
                }
            }, 5000);
        }
        
        // Handle visibility change
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Page is hidden, stop polling to save resources
                if (navbarAlertPollingInterval) {
                    clearInterval(navbarAlertPollingInterval);
                    navbarAlertPollingInterval = null;
                }
            } else {
                // Page is visible again, restart polling and refresh
                if (!navbarAlertPollingInterval) {
                    startNavbarAlertPolling();
                }
                loadNavbarAlerts(); // Immediate refresh
            }
        });
        
        // Initialize notifications on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadNavbarAlerts();
            startNavbarAlertPolling();
        });
    </script>
