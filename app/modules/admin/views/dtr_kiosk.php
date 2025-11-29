<?php
session_start();
require_once '../../../../config/database.php';

date_default_timezone_set('Asia/Manila');

// Restrict access to admin (or HR if you later add that role)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin')) {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

$message = '';
$error = '';
$employee = null;
$displayName = '';
$inputId = '';
$showTimeIn = false;
$showTimeOut = false;
$actionHint = '';

// Retrieve flash message if present
if (!empty($_SESSION['kiosk_message'])) {
    $message = $_SESSION['kiosk_message'];
    unset($_SESSION['kiosk_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputId = trim($_POST['employee_id'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($inputId === '') {
        $error = 'Please enter a valid Employee ID number.';
    } else {
        // Fetch employee by ID
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$inputId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {
            $error = 'Employee not found.';
        } else {
            $displayName = $employee['name'];

            // If no action yet, compute which action is valid and show only that
            if ($action === '') {
                $today = date('Y-m-d');
                $stmt = $pdo->prepare("SELECT * FROM dtr WHERE user_id = ? AND date = ?");
                $stmt->execute([$employee['id'], $today]);
                $today_record = $stmt->fetch(PDO::FETCH_ASSOC);

                $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
                $current_hour = (int)$current_time->format('H');

                if ($current_hour < 12) {
                    if (!$today_record || empty($today_record['morning_time_in'])) {
                        $showTimeIn = true; // Morning Time In
                        $actionHint = 'Ready for Morning Time In';
                    } elseif (!empty($today_record['morning_time_in']) && empty($today_record['morning_time_out'])) {
                        $showTimeOut = true; // Morning Time Out
                        $actionHint = 'Ready for Morning Time Out';
                    } else {
                        $actionHint = 'Morning session completed. Try after 12:00 PM for afternoon session.';
                    }
                } else {
                    if (!$today_record || empty($today_record['afternoon_time_in'])) {
                        $showTimeIn = true; // Afternoon Time In
                        $actionHint = 'Ready for Afternoon Time In';
                    } elseif (!empty($today_record['afternoon_time_in']) && empty($today_record['afternoon_time_out'])) {
                        $showTimeOut = true; // Afternoon Time Out
                        $actionHint = 'Ready for Afternoon Time Out';
                    } else {
                        $actionHint = 'Afternoon session completed for today.';
                    }
                }
            } else {
                // Perform the requested action with the same logic
                $today = date('Y-m-d');
                $stmt = $pdo->prepare("SELECT * FROM dtr WHERE user_id = ? AND date = ?");
                $stmt->execute([$employee['id'], $today]);
                $today_record = $stmt->fetch(PDO::FETCH_ASSOC);

                $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
                $formatted_time = $current_time->format('Y-m-d H:i:s');
                $current_hour = (int)$current_time->format('H');

                try {
                    if ($action === 'time_in') {
                        if ($current_hour < 12) {
                            // Morning time in
                            if (!$today_record) {
                                $stmt = $pdo->prepare("INSERT INTO dtr (user_id, date, morning_time_in) VALUES (?, ?, ?)");
                                $stmt->execute([$employee['id'], $today, $formatted_time]);
                            } else {
                                $stmt = $pdo->prepare("UPDATE dtr SET morning_time_in = ? WHERE user_id = ? AND date = ?");
                                $stmt->execute([$formatted_time, $employee['id'], $today]);
                            }
                            $message = "Morning Time In recorded for {$displayName} at " . $current_time->format('h:i A');
                            $_SESSION['kiosk_message'] = $message;
                            header('Location: dtr_kiosk.php');
                            exit();
                        } else {
                            // Afternoon time in
                            if (!$today_record) {
                                $stmt = $pdo->prepare("INSERT INTO dtr (user_id, date, afternoon_time_in) VALUES (?, ?, ?)");
                                $stmt->execute([$employee['id'], $today, $formatted_time]);
                            } else {
                                $stmt = $pdo->prepare("UPDATE dtr SET afternoon_time_in = ? WHERE user_id = ? AND date = ?");
                                $stmt->execute([$formatted_time, $employee['id'], $today]);
                            }
                            $message = "Afternoon Time In recorded for {$displayName} at " . $current_time->format('h:i A');
                            $_SESSION['kiosk_message'] = $message;
                            header('Location: dtr_kiosk.php');
                            exit();
                        }
                    } elseif ($action === 'time_out') {
                        if ($today_record && !empty($today_record['morning_time_in']) && empty($today_record['morning_time_out'])) {
                            // Morning time out
                            $stmt = $pdo->prepare("UPDATE dtr SET morning_time_out = ? WHERE user_id = ? AND date = ?");
                            $stmt->execute([$formatted_time, $employee['id'], $today]);
                            $message = "Morning Time Out recorded for {$displayName} at " . $current_time->format('h:i A');
                            $_SESSION['kiosk_message'] = $message;
                            header('Location: dtr_kiosk.php');
                            exit();
                        } elseif ($today_record && !empty($today_record['afternoon_time_in']) && empty($today_record['afternoon_time_out'])) {
                            // Afternoon time out
                            $stmt = $pdo->prepare("UPDATE dtr SET afternoon_time_out = ? WHERE user_id = ? AND date = ?");
                            $stmt->execute([$formatted_time, $employee['id'], $today]);
                            $message = "Afternoon Time Out recorded for {$displayName} at " . $current_time->format('h:i A');
                            $_SESSION['kiosk_message'] = $message;
                            header('Location: dtr_kiosk.php');
                            exit();
                        } else {
                            $error = 'Invalid time out: no active session found.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'An error occurred while recording time. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - DTR</title>
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../../../assets/css/elms-dark-theme.css">
</head>
<body class="bg-slate-900 text-white min-h-screen">
    <!-- No Sidebar Kiosk UI -->
    <div class="min-h-screen flex flex-col items-center justify-center px-4">
        <div class="w-full max-w-xl">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold">ELMS DTR</h1>
                <p class="text-slate-400">Enter Employee ID number to Time In/Out</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="bg-green-500/20 border border-green-500/30 text-green-400 px-4 py-3 rounded-xl mb-4">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-red-500/20 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl mb-4">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($displayName) && empty($message) && empty($error)): ?>
                <div class="bg-slate-800/60 rounded-xl border border-slate-700 p-4 mb-4">
                    <div class="text-slate-300">Employee</div>
                    <div class="text-xl font-semibold text-white"><?php echo htmlspecialchars($displayName); ?></div>
                    <?php if (!empty($actionHint)): ?>
                        <div class="text-xs text-slate-400 mt-1"><?php echo htmlspecialchars($actionHint); ?></div>
                    <?php endif; ?>
                </div>
                <form method="POST" class="flex items-center gap-3 mb-6">
                    <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($inputId); ?>" />
                    <?php if ($showTimeIn): ?>
                        <button type="submit" name="action" value="time_in" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-colors">
                            <i class="fas fa-sign-in-alt mr-2"></i> Time In
                        </button>
                    <?php endif; ?>
                    <?php if ($showTimeOut): ?>
                        <button type="submit" name="action" value="time_out" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors">
                            <i class="fas fa-sign-out-alt mr-2"></i> Time Out
                        </button>
                    <?php endif; ?>
                </form>
                <?php if (!$showTimeIn && !$showTimeOut): ?>
                    <div class="text-slate-400 text-sm mb-4">No valid action available at this time.</div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="POST" class="space-y-4" autocomplete="off">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Employee ID Number</label>
                    <input autofocus type="text" name="employee_id" value="<?php echo htmlspecialchars($inputId); ?>" placeholder="Enter ID and press Enter" required class="w-full px-4 py-3 bg-slate-800 border border-slate-700 rounded-xl text-white focus:ring-2 focus:ring-blue-500" />
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors">
                        Submit
                    </button>
                </div>
            </form>

            <div class="text-center text-slate-400 mt-8">
                <div id="clock" class="text-5xl font-bold text-white"></div>
                <div id="date" class="text-slate-400 mt-1"></div>
            </div>
        </div>
    </div>

    <script>
        // Simple clock
        function updateClock() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            document.getElementById('clock').textContent = `${h}:${m}:${s}`;
            document.getElementById('date').textContent = `${days[now.getDay()]}, ${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
        }
        updateClock();
        setInterval(updateClock, 1000);
    </script>
</body>
</html>
