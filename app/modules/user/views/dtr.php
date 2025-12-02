<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../../../config/database.php';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Get today's DTR record
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM dtr WHERE user_id = ? AND date = ?");
$stmt->execute([$_SESSION['user_id'], $today]);
$today_record = $stmt->fetch();

// Handle time in/out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $formatted_time = $current_time->format('Y-m-d H:i:s');
    $current_hour = (int)$current_time->format('H');
    
    if (isset($_POST['action'])) {
    if ($_POST['action'] === 'time_in') {
            // Check if it's morning or afternoon
            if ($current_hour < 12) {
                // Morning time in
        if (!$today_record) {
                    // Create new record
                    $stmt = $pdo->prepare("INSERT INTO dtr (user_id, date, morning_time_in) VALUES (?, ?, ?)");
                    if ($stmt->execute([$_SESSION['user_id'], $today, $formatted_time])) {
                        $_SESSION['message'] = "Morning Time In recorded successfully at " . $current_time->format('h:i A');
                    }
                } else {
                    // Update existing record
                    $stmt = $pdo->prepare("UPDATE dtr SET morning_time_in = ? WHERE user_id = ? AND date = ?");
                    if ($stmt->execute([$formatted_time, $_SESSION['user_id'], $today])) {
                        $_SESSION['message'] = "Morning Time In recorded successfully at " . $current_time->format('h:i A');
                    }
                }
            } else {
                // Afternoon time in
        if (!$today_record) {
                    // Create new record
                    $stmt = $pdo->prepare("INSERT INTO dtr (user_id, date, afternoon_time_in) VALUES (?, ?, ?)");
                    if ($stmt->execute([$_SESSION['user_id'], $today, $formatted_time])) {
                        $_SESSION['message'] = "Afternoon Time In recorded successfully at " . $current_time->format('h:i A');
                    }
                } else {
                    // Update existing record
                    $stmt = $pdo->prepare("UPDATE dtr SET afternoon_time_in = ? WHERE user_id = ? AND date = ?");
                    if ($stmt->execute([$formatted_time, $_SESSION['user_id'], $today])) {
                        $_SESSION['message'] = "Afternoon Time In recorded successfully at " . $current_time->format('h:i A');
                    }
                }
            }
        } elseif ($_POST['action'] === 'time_out') {
            if ($today_record && $today_record['morning_time_in'] && !$today_record['morning_time_out']) {
                // Morning time out
                $stmt = $pdo->prepare("UPDATE dtr SET morning_time_out = ? WHERE user_id = ? AND date = ?");
                if ($stmt->execute([$formatted_time, $_SESSION['user_id'], $today])) {
                    $_SESSION['message'] = "Morning Time Out recorded successfully at " . $current_time->format('h:i A');
                }
            } else if ($today_record && $today_record['afternoon_time_in'] && !$today_record['afternoon_time_out']) {
                // Afternoon time out
                $stmt = $pdo->prepare("UPDATE dtr SET afternoon_time_out = ? WHERE user_id = ? AND date = ?");
                if ($stmt->execute([$formatted_time, $_SESSION['user_id'], $today])) {
                    $_SESSION['message'] = "Afternoon Time Out recorded successfully at " . $current_time->format('h:i A');
                }
            }
        }
    }
    header('Location: dtr.php');
    exit();
}

// Get recent DTR records
$stmt = $pdo->prepare("SELECT * FROM dtr WHERE user_id = ? ORDER BY date DESC, morning_time_in DESC LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$recent_records = $stmt->fetchAll();

// Define standard work hours for late detection
define('MORNING_START_TIME', '08:00:00'); // 8:00 AM
define('AFTERNOON_START_TIME', '13:00:00'); // 1:00 PM
define('LATE_GRACE_PERIOD_MINUTES', 15); // 15 minutes grace period

/**
 * Check if a time-in is late
 * @param string $timeIn The actual time-in (datetime string)
 * @param string $standardTime The standard start time (H:i:s format)
 * @return array ['is_late' => bool, 'minutes_late' => int]
 */
function checkIfLate($timeIn, $standardTime) {
    if (!$timeIn) {
        return ['is_late' => false, 'minutes_late' => 0];
    }
    
    $timeInObj = new DateTime($timeIn);
    $standardObj = new DateTime($timeInObj->format('Y-m-d') . ' ' . $standardTime);
    
    // Add grace period
    $standardObj->modify('+' . LATE_GRACE_PERIOD_MINUTES . ' minutes');
    
    if ($timeInObj > $standardObj) {
        $diff = $timeInObj->diff($standardObj);
        $minutesLate = ($diff->h * 60) + $diff->i;
        return ['is_late' => true, 'minutes_late' => $minutesLate];
    }
    
    return ['is_late' => false, 'minutes_late' => 0];
}

/**
 * Format late indicator HTML
 */
function getLateIndicator($lateInfo) {
    if (!$lateInfo['is_late']) {
        return '';
    }
    
    $minutes = $lateInfo['minutes_late'];
    if ($minutes >= 60) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        $lateText = $hours . 'h ' . $mins . 'm late';
    } else {
        $lateText = $minutes . 'm late';
    }
    
    return '<span class="ml-2 px-2 py-0.5 text-xs font-semibold bg-red-500/20 text-red-400 border border-red-500/30 rounded-full">' . $lateText . '</span>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Time In/Out</title>
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../../../assets/css/elms-dark-theme.css">
    <link rel="stylesheet" href="../../../../assets/css/style.css">
</head>
<body class="no-sidebar" style="background-color: #0f172a; margin: 0; min-height: 100vh;">
    <!-- Simple Navbar (No Sidebar) -->
    <nav class="elms-navbar">
        <div class="elms-navbar-content">
            <a href="dashboard.php" class="elms-logo" style="text-decoration: none; cursor: pointer;">
                <span class="elms-logo-text">ELMS Employee</span>
            </a>
            
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
                        <div style="padding: 0.75rem; border-top: 1px solid #334155; display: flex; justify-content: space-between; align-items: center;">
                            <button onclick="markAllNavbarAlertsRead()" style="font-size: 0.75rem; color: #94a3b8; background: none; border: none; cursor: pointer; display: flex; align-items: center;">
                                <i class="fas fa-check-double" style="margin-right: 0.25rem;"></i>Clear All
                            </button>
                            <a href="dashboard.php#alerts" style="font-size: 0.75rem; color: #60a5fa; text-decoration: none;">
                                View All Notifications
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- User Dropdown -->
                <div style="position: relative;">
                    <button onclick="toggleUserDropdown()" style="display: flex; align-items: center; gap: 0.5rem; background: none; border: none; cursor: pointer; padding: 0;">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <img src="../../../../uploads/profile_pictures/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                 alt="Profile" 
                                 style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #10b981;">
                        <?php else: ?>
                            <div style="width: 32px; height: 32px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.875rem;">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div style="text-align: left;">
                            <div style="color: white; font-weight: 600; font-size: 0.875rem;">
                                <?php echo htmlspecialchars($user['name']); ?>
                            </div>
                        </div>
                        <i class="fas fa-chevron-down" style="color: #cbd5e1; font-size: 0.75rem;"></i>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div id="userDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 0.5rem; width: 260px; background: #1e293b; border: 1px solid #334155; border-radius: 0.75rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); z-index: 100;">
                        <!-- Dropdown Header -->
                        <div style="padding: 1rem; border-bottom: 1px solid #334155;">
                            <div style="font-weight: 600; color: white; margin-bottom: 0.25rem; font-size: 0.9375rem;">
                                <?php echo htmlspecialchars($user['name']); ?>
                            </div>
                            <div style="color: #94a3b8; font-size: 0.8125rem; margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($user['email'] ?? 'employee@elms.com'); ?>
                            </div>
                            <span style="display: inline-block; background: #10b981; color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.6875rem; font-weight: 600;">
                                Employee
                            </span>
                        </div>
                        
                        <!-- Dropdown Items -->
                        <div style="padding: 0.5rem 0;">
                            <a href="profile.php" style="display: block; padding: 0.75rem 1rem; color: #cbd5e1; text-decoration: none; transition: all 0.2s; font-size: 0.875rem; font-weight: 500;" onmouseover="this.style.backgroundColor='#334155'; this.style.color='white'" onmouseout="this.style.backgroundColor='transparent'; this.style.color='#cbd5e1'">
                                <i class="fas fa-user" style="margin-right: 0.625rem; width: 18px; font-size: 0.875rem;"></i>
                                My Profile
                            </a>
                            <a href="../../../../auth/controllers/logout.php" style="display: block; padding: 0.75rem 1rem; color: #ef4444; text-decoration: none; transition: all 0.2s; border-top: 1px solid #334155; font-size: 0.875rem; font-weight: 500;" onmouseover="this.style.backgroundColor='#334155'" onmouseout="this.style.backgroundColor='transparent'">
                                <i class="fas fa-sign-out-alt" style="margin-right: 0.625rem; width: 18px; font-size: 0.875rem;"></i>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content (No Sidebar) -->
    <main class="elms-main" style="padding: 6rem 2rem 2rem 2rem;">
        <div style="max-width: 1000px; margin: 0 auto;">
            <!-- Welcome Section -->
            <div style="text-align: center; margin-bottom: 3rem;">
                <h1 style="font-size: 2.5rem; font-weight: 700; color: white; margin-bottom: 0.5rem;">
                    Welcome, <?php echo htmlspecialchars($user['name']); ?>!
                </h1>
                <p style="color: #94a3b8; font-size: 1.125rem;">Please record your attendance for today</p>
            </div>

            <!-- Current Time Display -->
            <div class="bg-slate-800 rounded-2xl border border-slate-700 p-8 mb-8 text-center animate-slide-up" style="max-width: 760px; margin: 0 auto;">
                <div class="mb-6">
                    <i class="fas fa-clock text-4xl text-primary mb-4 animate-pulse-slow"></i>
                    <h2 id="current-time" class="text-6xl font-bold text-white mb-2 tracking-wider">00:00:00</h2>
                    <p id="current-date" class="text-xl text-slate-400">Loading...</p>
                </div>
                
                <!-- Status Indicator -->
                <div class="inline-block">
                    <span id="session-status" class="bg-slate-700 text-slate-300 px-4 py-2 rounded-full text-sm font-semibold">
                        Checking status...
                    </span>
                </div>
            </div>

            <!-- Alert Messages -->
            <div id="alert-container" class="mb-8" style="max-width: 760px; margin: 0 auto;">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-4 flex items-center animate-slide-up">
                        <i class="fas fa-check-circle mr-3"></i>
                        <?php 
                        echo $_SESSION['message'];
                        unset($_SESSION['message']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-4 flex items-center animate-slide-up">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- DTR Buttons -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8" style="max-width: 760px; margin: 0 auto;">
                <!-- Dashboard Button -->
                <a href="dashboard.php" class="bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl text-center flex items-center justify-center">
                    <i class="fas fa-tachometer-alt mr-2"></i>
                    Go to Dashboard
                </a>

                <?php 
                // Determine which buttons to show
                $can_time_in = false;
                $can_time_out = false;
                $time_in_text = 'Time In';
                $time_out_text = 'Time Out';
                
                // State 1: No record exists (first time of day)
                if (!$today_record) {
                    $can_time_in = true;
                }
                // State 2: Morning time in exists but no time out
                else if ($today_record['morning_time_in'] && !$today_record['morning_time_out']) {
                    $can_time_out = true;
                    $time_out_text = 'Morning Time Out';
                }
                // State 3: Morning completed, ready for afternoon
                else if ($today_record['morning_time_in'] && $today_record['morning_time_out'] && !$today_record['afternoon_time_in']) {
                    $can_time_in = true;
                    $time_in_text = 'Afternoon Time In';
                }
                // State 4: Afternoon time in exists but no time out
                else if ($today_record['afternoon_time_in'] && !$today_record['afternoon_time_out']) {
                    $can_time_out = true;
                    $time_out_text = 'Afternoon Time Out';
                }
                
                // Show Time In button if allowed
                if ($can_time_in):
                ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="time_in">
                    <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-400 hover:to-emerald-400 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl flex items-center justify-center">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        <?php echo $time_in_text; ?>
                    </button>
                </form>
                <?php endif; ?>
                
                <?php if ($can_time_out): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="time_out">
                    <button type="submit" class="w-full bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-400 hover:to-red-400 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl flex items-center justify-center">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        <?php echo $time_out_text; ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Today's Status -->
            <div class="bg-slate-800 rounded-2xl border border-slate-700 p-6 mb-8" style="max-width: 760px; margin: 0 auto;">
                <h3 class="text-xl font-semibold text-white mb-4 flex items-center">
                    <i class="fas fa-calendar-day text-primary mr-2"></i>
                    Today's Status
                </h3>
                
                <?php if ($today_record): 
                    $morningLate = checkIfLate($today_record['morning_time_in'], MORNING_START_TIME);
                    $afternoonLate = checkIfLate($today_record['afternoon_time_in'], AFTERNOON_START_TIME);
                ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Morning Session -->
                        <div class="bg-slate-700/30 rounded-xl p-4 <?php echo $morningLate['is_late'] ? 'border border-red-500/30' : ''; ?>">
                            <h4 class="text-lg font-semibold text-white mb-3 flex items-center">
                                <i class="fas fa-sun text-yellow-400 mr-2"></i>
                                Morning Session
                                <?php if ($morningLate['is_late']): ?>
                                    <span class="ml-auto px-2 py-1 text-xs font-semibold bg-red-500/20 text-red-400 border border-red-500/30 rounded-full">
                                        <i class="fas fa-clock mr-1"></i>Late
                                    </span>
                                <?php endif; ?>
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-400">Time In:</span>
                                    <span class="<?php echo $morningLate['is_late'] ? 'text-red-400' : 'text-white'; ?> font-mono flex items-center">
                                        <?php echo $today_record['morning_time_in'] ? date('h:i A', strtotime($today_record['morning_time_in'])) : 'Not recorded'; ?>
                                        <?php echo getLateIndicator($morningLate); ?>
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-400">Time Out:</span>
                                    <span class="text-white font-mono">
                                        <?php echo $today_record['morning_time_out'] ? date('h:i A', strtotime($today_record['morning_time_out'])) : 'Not recorded'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Afternoon Session -->
                        <div class="bg-slate-700/30 rounded-xl p-4 <?php echo $afternoonLate['is_late'] ? 'border border-red-500/30' : ''; ?>">
                            <h4 class="text-lg font-semibold text-white mb-3 flex items-center">
                                <i class="fas fa-moon text-blue-400 mr-2"></i>
                                Afternoon Session
                                <?php if ($afternoonLate['is_late']): ?>
                                    <span class="ml-auto px-2 py-1 text-xs font-semibold bg-red-500/20 text-red-400 border border-red-500/30 rounded-full">
                                        <i class="fas fa-clock mr-1"></i>Late
                                    </span>
                                <?php endif; ?>
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-400">Time In:</span>
                                    <span class="<?php echo $afternoonLate['is_late'] ? 'text-red-400' : 'text-white'; ?> font-mono flex items-center">
                                        <?php echo $today_record['afternoon_time_in'] ? date('h:i A', strtotime($today_record['afternoon_time_in'])) : 'Not recorded'; ?>
                                        <?php echo getLateIndicator($afternoonLate); ?>
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-400">Time Out:</span>
                                    <span class="text-white font-mono">
                                        <?php echo $today_record['afternoon_time_out'] ? date('h:i A', strtotime($today_record['afternoon_time_out'])) : 'Not recorded'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-times text-4xl text-slate-500 mb-4"></i>
                        <p class="text-slate-400">No attendance recorded for today yet.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Records -->
            <?php if (!empty($recent_records)): ?>
            <div class="bg-slate-800 rounded-2xl border border-slate-700 p-6" style="max-width: 760px; margin: 0 auto;">
                <h3 class="text-xl font-semibold text-white mb-4 flex items-center">
                    <i class="fas fa-history text-primary mr-2"></i>
                    Recent Attendance Records
                </h3>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-700">
                                <th class="text-left py-3 px-4 text-slate-400">Date</th>
                                <th class="text-left py-3 px-4 text-slate-400">Morning In</th>
                                <th class="text-left py-3 px-4 text-slate-400">Morning Out</th>
                                <th class="text-left py-3 px-4 text-slate-400">Afternoon In</th>
                                <th class="text-left py-3 px-4 text-slate-400">Afternoon Out</th>
                                <th class="text-left py-3 px-4 text-slate-400">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_records as $record): 
                                $recMorningLate = checkIfLate($record['morning_time_in'], MORNING_START_TIME);
                                $recAfternoonLate = checkIfLate($record['afternoon_time_in'], AFTERNOON_START_TIME);
                                $hasLate = $recMorningLate['is_late'] || $recAfternoonLate['is_late'];
                            ?>
                            <tr class="border-b border-slate-700 <?php echo $hasLate ? 'bg-red-500/5' : ''; ?>">
                                <td class="py-3 px-4 text-white font-mono"><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                <td class="py-3 px-4 font-mono <?php echo $recMorningLate['is_late'] ? 'text-red-400' : 'text-slate-300'; ?>">
                                    <?php echo $record['morning_time_in'] ? date('h:i A', strtotime($record['morning_time_in'])) : '-'; ?>
                                    <?php if ($recMorningLate['is_late']): ?>
                                        <i class="fas fa-exclamation-circle text-red-400 ml-1" title="Late"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-slate-300 font-mono"><?php echo $record['morning_time_out'] ? date('h:i A', strtotime($record['morning_time_out'])) : '-'; ?></td>
                                <td class="py-3 px-4 font-mono <?php echo $recAfternoonLate['is_late'] ? 'text-red-400' : 'text-slate-300'; ?>">
                                    <?php echo $record['afternoon_time_in'] ? date('h:i A', strtotime($record['afternoon_time_in'])) : '-'; ?>
                                    <?php if ($recAfternoonLate['is_late']): ?>
                                        <i class="fas fa-exclamation-circle text-red-400 ml-1" title="Late"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-slate-300 font-mono"><?php echo $record['afternoon_time_out'] ? date('h:i A', strtotime($record['afternoon_time_out'])) : '-'; ?></td>
                                <td class="py-3 px-4">
                                    <?php if ($hasLate): ?>
                                        <span class="px-2 py-1 text-xs font-semibold bg-red-500/20 text-red-400 border border-red-500/30 rounded-full">
                                            <i class="fas fa-clock mr-1"></i>Late
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-semibold bg-green-500/20 text-green-400 border border-green-500/30 rounded-full">
                                            <i class="fas fa-check mr-1"></i>On Time
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        let navbarAlertPollingInterval = null;
        let lastNavbarAlertCount = 0;
        
        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            const userDropdown = document.getElementById('userDropdown');
            
            if (userDropdown) {
                userDropdown.style.display = 'none';
            }
            
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            
            if (dropdown.style.display === 'block') {
                loadNavbarAlerts();
            }
        }
        
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            if (notificationDropdown) {
                notificationDropdown.style.display = 'none';
            }
            
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
        
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
                } else {
                    showNavbarAlertError('Failed to load alerts');
                }
            } catch (error) {
                console.error('Error fetching alerts:', error);
                showNavbarAlertError('Network error loading alerts');
            }
        }
        
        function displayNavbarAlerts(alerts) {
            const container = document.getElementById('navbarAlertsContainer');
            
            if (alerts.length === 0) {
                container.innerHTML = '<div style="padding: 2rem; text-align: center;"><i class="fas fa-bell-slash" style="font-size: 2.5rem; color: #64748b; margin-bottom: 1rem;"></i><p style="color: #94a3b8;">No notifications</p></div>';
                return;
            }

            container.innerHTML = alerts.slice(0, 5).map(alert => {
                const iconClass = alert.alert_icon || 'fas fa-bell';
                const colorClass = alert.alert_color || 'text-cyan-400 bg-cyan-500/20';
                const iconColor = colorClass.split(' ')[0].replace('text-', '');
                const bgColor = colorClass.split(' ')[1].replace('bg-', '');
                
                return `
                <div class="notification-item" style="padding: 0.75rem; border-bottom: 1px solid rgba(51, 65, 85, 0.5); cursor: pointer;" 
                     onmouseover="this.style.backgroundColor='rgba(51, 65, 85, 0.3)'" 
                     onmouseout="this.style.backgroundColor='transparent'"
                     data-alert-id="${alert.id}"
                     data-alert-type="${alert.alert_type}"
                     data-message="${alert.message.replace(/"/g, '&quot;')}"
                     data-created-at="${alert.created_at}"
                     data-sent-by="${(alert.sent_by_name || 'System').replace(/"/g, '&quot;')}">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 2rem; height: 2rem; ${bgColor ? 'background: ' + bgColor.replace('/', '') : 'background: rgba(6, 182, 212, 0.2)'}; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="${iconClass}" style="font-size: 0.75rem;"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.25rem;">
                                <h4 style="font-size: 0.875rem; font-weight: 600; color: white; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    ${getAlertTitle(alert.alert_type)}
                                </h4>
                                <span style="font-size: 0.75rem; color: #64748b;">${alert.time_ago}</span>
                            </div>
                            <p style="font-size: 0.75rem; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${alert.message.substring(0, 60)}...</p>
                            <span style="font-size: 0.75rem; color: #60a5fa; font-weight: 500;">Click to view →</span>
                        </div>
                    </div>
                </div>
            `}).join('');
        }
        
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
        
        function updateNavbarAlertBadge(count) {
            const badge = document.getElementById('navbarAlertBadge');
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
        
        function showNavbarAlertError(message) {
            const container = document.getElementById('navbarAlertsContainer');
            container.innerHTML = `<div style="padding: 2rem; text-align: center;"><i class="fas fa-exclamation-triangle" style="font-size: 2.5rem; color: #ef4444; margin-bottom: 1rem;"></i><p style="color: #f87171;">${message}</p></div>`;
        }
        
        async function markAllNavbarAlertsRead() {
            try {
                const response = await fetch('/ELMS/api/mark_all_alerts_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin'
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success) {
                        loadNavbarAlerts();
                        updateNavbarAlertBadge(0);
                    }
                }
            } catch (error) {
                console.error('Error clearing alerts:', error);
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
        
        // Open notification modal
        window.openNotificationModal = function(alertId, alertType, message, createdAt, sentBy) {
            try {
                const modalOverlay = document.createElement('div');
                modalOverlay.className = 'fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4';
                modalOverlay.style.position = 'fixed';
                modalOverlay.style.zIndex = '9999';
                modalOverlay.style.top = '0';
                modalOverlay.style.left = '0';
                modalOverlay.style.right = '0';
                modalOverlay.style.bottom = '0';
                modalOverlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
                modalOverlay.style.backdropFilter = 'blur(4px)';
                modalOverlay.style.display = 'flex';
                modalOverlay.style.alignItems = 'center';
                modalOverlay.style.justifyContent = 'center';
                modalOverlay.style.padding = '1rem';
            
                const modalContent = document.createElement('div');
                modalContent.className = 'bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-lg max-h-[80vh] overflow-y-auto shadow-2xl transform scale-95 opacity-0 transition-all duration-300 ease-out';
                modalContent.style.zIndex = '10000';
                modalContent.style.backgroundColor = '#1e293b';
                modalContent.style.border = '1px solid #334155';
                modalContent.style.borderRadius = '1rem';
                modalContent.style.maxWidth = '32rem';
                modalContent.style.maxHeight = '80vh';
                modalContent.style.overflowY = 'auto';
                
                const formattedDate = new Date(createdAt).toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit'
                });
                
                modalContent.innerHTML = `
                    <div style="padding: 1.5rem; border-bottom: 1px solid #334155; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 3.5rem; height: 3.5rem; background: rgba(16, 185, 129, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-bell" style="font-size: 1.5rem; color: #34d399;"></i>
                                </div>
                                <div>
                                    <h3 style="font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 0.25rem;">Leave Alert</h3>
                                    <p style="font-size: 0.875rem; color: #94a3b8; display: flex; align-items: center;">
                                        <i class="fas fa-hashtag" style="margin-right: 0.25rem;"></i>
                                        Alert ID: ${alertId}
                                    </p>
                                </div>
                            </div>
                            <button onclick="closeNotificationModal()" style="color: #94a3b8; background: none; border: none; cursor: pointer; padding: 0.5rem; border-radius: 50%; transition: all 0.2s;" onmouseover="this.style.color='white'; this.style.backgroundColor='#334155'" onmouseout="this.style.color='#94a3b8'; this.style.backgroundColor='transparent'">
                                <i class="fas fa-times" style="font-size: 1.25rem;"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div style="padding: 1.5rem; background: rgba(15, 23, 42, 0.5);">
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="font-size: 0.875rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.75rem; display: flex; align-items: center;">
                                <i class="fas fa-message" style="margin-right: 0.5rem;"></i>
                                Message Details
                            </h4>
                            <div style="background: rgba(51, 65, 85, 0.3); border: 1px solid rgba(71, 85, 105, 0.5); border-radius: 0.75rem; padding: 1.25rem; max-height: 16rem; overflow-y: auto;">
                                <p style="color: #e2e8f0; line-height: 1.75; white-space: pre-line; font-size: 0.875rem;">${message}</p>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; font-size: 0.875rem;">
                            <div style="background: rgba(51, 65, 85, 0.2); border-radius: 0.5rem; padding: 1rem; border: 1px solid rgba(71, 85, 105, 0.3);">
                                <h4 style="font-weight: 600; color: #cbd5e1; margin-bottom: 0.5rem; display: flex; align-items: center;">
                                    <i class="fas fa-user-circle" style="margin-right: 0.5rem; color: #60a5fa;"></i>
                                    Sent By
                                </h4>
                                <p style="color: #94a3b8; display: flex; align-items: center;">
                                    <i class="fas fa-user" style="margin-right: 0.5rem;"></i>
                                    ${sentBy}
                                </p>
                            </div>
                            <div style="background: rgba(51, 65, 85, 0.2); border-radius: 0.5rem; padding: 1rem; border: 1px solid rgba(71, 85, 105, 0.3);">
                                <h4 style="font-weight: 600; color: #cbd5e1; margin-bottom: 0.5rem; display: flex; align-items: center;">
                                    <i class="fas fa-calendar-alt" style="margin-right: 0.5rem; color: #34d399;"></i>
                                    Date & Time
                                </h4>
                                <p style="color: #94a3b8; display: flex; align-items: center;">
                                    <i class="fas fa-clock" style="margin-right: 0.5rem;"></i>
                                    ${formattedDate}
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="padding: 1.5rem; border-top: 1px solid #334155; background: rgba(30, 41, 59, 0.3); display: flex; justify-content: flex-end;">
                        <button onclick="closeNotificationModal()" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; font-weight: 600; padding: 0.75rem 2rem; border-radius: 0.75rem; border: none; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3);" onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 6px 12px rgba(37, 99, 235, 0.4)'" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 4px 6px rgba(37, 99, 235, 0.3)'">
                            <i class="fas fa-check" style="margin-right: 0.5rem;"></i>
                            Mark as Read
                        </button>
                    </div>
                `;
                
                modalOverlay.appendChild(modalContent);
                document.body.appendChild(modalOverlay);
                
                // Mark notification as read
                markNotificationAsRead(alertId);
                
                // Animate modal in
                requestAnimationFrame(() => {
                    modalContent.style.transform = 'scale(1)';
                    modalContent.style.opacity = '1';
                });
                
                // Close modal when clicking outside
                modalOverlay.onclick = function(e) {
                    if (e.target === modalOverlay) {
                        closeNotificationModal();
                    }
                };
                
                window.currentNotificationModal = modalOverlay;
            } catch (error) {
                console.error('Error opening notification modal:', error);
            }
        }
        
        window.closeNotificationModal = function() {
            if (window.currentNotificationModal) {
                const modalContent = window.currentNotificationModal.querySelector('div');
                if (modalContent) {
                    modalContent.style.transform = 'scale(0.95)';
                    modalContent.style.opacity = '0';
                    
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
                        loadNavbarAlerts();
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
                    // Close notification dropdown
                    document.getElementById('notificationDropdown').style.display = 'none';
                }
            }
        });
        
        // Load notifications on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadNavbarAlerts();
        });

        // Update time display
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour12: true,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const dateString = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            document.getElementById('current-time').textContent = timeString;
            document.getElementById('current-date').textContent = dateString;
        }

        // Update status indicator
        function updateStatus() {
            const now = new Date();
            const hour = now.getHours();
            const statusElement = document.getElementById('session-status');
            
            if (hour < 12) {
                statusElement.textContent = 'Morning Session';
                statusElement.className = 'bg-yellow-500/20 text-yellow-400 px-4 py-2 rounded-full text-sm font-semibold';
            } else if (hour < 17) {
                statusElement.textContent = 'Afternoon Session';
                statusElement.className = 'bg-blue-500/20 text-blue-400 px-4 py-2 rounded-full text-sm font-semibold';
            } else {
                statusElement.textContent = 'After Hours';
                statusElement.className = 'bg-slate-700 text-slate-300 px-4 py-2 rounded-full text-sm font-semibold';
            }
        }

        // Initialize
            updateTime();
        updateStatus();
            setInterval(updateTime, 1000);
        setInterval(updateStatus, 60000); // Update status every minute

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('#alert-container > div');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html> 