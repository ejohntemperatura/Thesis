<?php
// Admin Header Component
// Include this file at the top of all admin pages

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'director'])) {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

// Fetch user details
require_once '../../../../config/database.php';
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'ELMS Admin'; ?></title>
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../../../assets/css/elms-dark-theme.css">
    <link rel="icon" type="image/png" sizes="32x32" href="/ELMS/elmsicon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/ELMS/elmsicon.png">
    <link rel="shortcut icon" href="/ELMS/elmsicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/ELMS/elmsicon.png">
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

    <!-- Sidebar -->
    <aside class="elms-sidebar">
        <nav>
            <!-- Dashboard Section -->
            <div class="elms-sidebar-section">
                <h3 class="elms-sidebar-header">Dashboard</h3>
                <a href="dashboard.php" class="elms-sidebar-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt elms-sidebar-icon"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <!-- Management Section -->
            <div class="elms-sidebar-section">
                <h3 class="elms-sidebar-header">Management</h3>
                <a href="manage_user.php" class="elms-sidebar-link <?php echo ($current_page == 'manage_user.php') ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog elms-sidebar-icon"></i>
                    <span>Manage Users</span>
                </a>
                <a href="leave_management.php" class="elms-sidebar-link <?php echo ($current_page == 'leave_management.php') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check elms-sidebar-icon"></i>
                    <span>Leave Management</span>
                </a>
                <a href="leave_alerts.php" class="elms-sidebar-link <?php echo ($current_page == 'leave_alerts.php') ? 'active' : ''; ?>">
                    <i class="fas fa-bell elms-sidebar-icon"></i>
                    <span>Leave Alerts</span>
                </a>
                <a href="cto_management.php" class="elms-sidebar-link <?php echo ($current_page == 'cto_management.php') ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle elms-sidebar-icon"></i>
                    <span>Add Leave Credits</span>
                </a>
                <a href="#" onclick="openDTRKiosk(); return false;" class="elms-sidebar-link <?php echo ($current_page == 'dtr_kiosk.php') ? 'active' : ''; ?>">
                    <i class="fas fa-clock elms-sidebar-icon"></i>
                    <span>DTR</span>
                </a>
            </div>
            
            <!-- Reports Section -->
            <div class="elms-sidebar-section">
                <h3 class="elms-sidebar-header">Reports</h3>
                <a href="view_chart.php" class="elms-sidebar-link <?php echo ($current_page == 'view_chart.php') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar elms-sidebar-icon"></i>
                    <span>Leave Chart</span>
                </a>
                <a href="reports.php" class="elms-sidebar-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt elms-sidebar-icon"></i>
                    <span>Reports</span>
                </a>
            </div>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="elms-main">
    
    <script>
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
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
        
        // Open DTR Kiosk in fullscreen popup
        function openDTRKiosk() {
            // Get screen dimensions
            const width = screen.width;
            const height = screen.height;
            
            // Open popup window (fullscreen size, minimal UI)
            const dtrWindow = window.open(
                'dtr_kiosk.php',
                'DTR_Kiosk',
                `width=${width},height=${height},top=0,left=0,menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=no`
            );
            
            // Try to enter fullscreen mode after window opens
            if (dtrWindow) {
                dtrWindow.focus();
                
                // Wait for window to load, then request fullscreen
                dtrWindow.onload = function() {
                    try {
                        const elem = dtrWindow.document.documentElement;
                        if (elem.requestFullscreen) {
                            elem.requestFullscreen();
                        } else if (elem.webkitRequestFullscreen) {
                            elem.webkitRequestFullscreen();
                        } else if (elem.msRequestFullscreen) {
                            elem.msRequestFullscreen();
                        } else if (elem.mozRequestFullScreen) {
                            elem.mozRequestFullScreen();
                        }
                    } catch (e) {
                        console.log('Fullscreen request failed:', e);
                    }
                };
            }
        }
    </script>
