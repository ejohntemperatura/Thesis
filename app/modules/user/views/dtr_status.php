<?php
session_start();
require_once '../../../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Define standard work hours for late detection
define('MORNING_START_TIME', '08:00:00'); // 8:00 AM
define('AFTERNOON_START_TIME', '13:00:00'); // 1:00 PM
define('LATE_GRACE_PERIOD_MINUTES', 15); // 15 minutes grace period

/**
 * Check if a time-in is late
 */
function checkIfLate($timeIn, $standardTime) {
    if (!$timeIn) {
        return ['is_late' => false, 'minutes_late' => 0];
    }
    
    $timeInObj = new DateTime($timeIn);
    $standardObj = new DateTime($timeInObj->format('Y-m-d') . ' ' . $standardTime);
    $standardObj->modify('+' . LATE_GRACE_PERIOD_MINUTES . ' minutes');
    
    if ($timeInObj > $standardObj) {
        $diff = $timeInObj->diff($standardObj);
        $minutesLate = ($diff->h * 60) + $diff->i;
        return ['is_late' => true, 'minutes_late' => $minutesLate];
    }
    
    return ['is_late' => false, 'minutes_late' => 0];
}

// Check if this is an AJAX request for attendance records
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Get last 30 days of attendance records
    $stmt = $pdo->prepare("
        SELECT 
            date,
            morning_time_in,
            morning_time_out,
            afternoon_time_in,
            afternoon_time_out
        FROM dtr 
        WHERE user_id = ? 
        ORDER BY date DESC 
        LIMIT 30
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted_records = [];
    foreach ($records as $record) {
        $total_hours = 0;
        
        // Calculate morning hours
        if ($record['morning_time_in'] && $record['morning_time_out']) {
            $morning_in = strtotime($record['morning_time_in']);
            $morning_out = strtotime($record['morning_time_out']);
            $total_hours += ($morning_out - $morning_in) / 3600;
        }
        
        // Calculate afternoon hours
        if ($record['afternoon_time_in'] && $record['afternoon_time_out']) {
            $afternoon_in = strtotime($record['afternoon_time_in']);
            $afternoon_out = strtotime($record['afternoon_time_out']);
            $total_hours += ($afternoon_out - $afternoon_in) / 3600;
        }
        
        // Check for late arrivals
        $morningLate = checkIfLate($record['morning_time_in'], MORNING_START_TIME);
        $afternoonLate = checkIfLate($record['afternoon_time_in'], AFTERNOON_START_TIME);
        
        $formatted_records[] = [
            'date' => date('M d, Y', strtotime($record['date'])),
            'morning_time_in' => $record['morning_time_in'] ? date('h:i A', strtotime($record['morning_time_in'])) : null,
            'morning_time_out' => $record['morning_time_out'] ? date('h:i A', strtotime($record['morning_time_out'])) : null,
            'afternoon_time_in' => $record['afternoon_time_in'] ? date('h:i A', strtotime($record['afternoon_time_in'])) : null,
            'afternoon_time_out' => $record['afternoon_time_out'] ? date('h:i A', strtotime($record['afternoon_time_out'])) : null,
            'total_hours' => round($total_hours, 2),
            'morning_late' => $morningLate['is_late'],
            'morning_minutes_late' => $morningLate['minutes_late'],
            'afternoon_late' => $afternoonLate['is_late'],
            'afternoon_minutes_late' => $afternoonLate['minutes_late'],
            'has_late' => $morningLate['is_late'] || $afternoonLate['is_late']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode(['records' => $formatted_records]);
    exit();
}

// Original code for today's record (for backward compatibility)
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM dtr WHERE user_id = ? AND date = ?");
$stmt->execute([$_SESSION['user_id'], $today]);
$today_record = $stmt->fetch();

// Prepare response data
$response = [
    'hasRecord' => false,
    'morning_time_in' => null,
    'morning_time_out' => null,
    'afternoon_time_in' => null,
    'afternoon_time_out' => null,
    'total_hours' => 0,
    'current_status' => 'no_record'
];

if ($today_record) {
    $response['hasRecord'] = true;
    
    if ($today_record['morning_time_in']) {
        $response['morning_time_in'] = date('h:i A', strtotime($today_record['morning_time_in']));
    }
    
    if ($today_record['morning_time_out']) {
        $response['morning_time_out'] = date('h:i A', strtotime($today_record['morning_time_out']));
    }
    
    if ($today_record['afternoon_time_in']) {
        $response['afternoon_time_in'] = date('h:i A', strtotime($today_record['afternoon_time_in']));
    }
    
    if ($today_record['afternoon_time_out']) {
        $response['afternoon_time_out'] = date('h:i A', strtotime($today_record['afternoon_time_out']));
    }
    
    // Calculate total hours worked
    $total_hours_worked = 0;
    if ($today_record['morning_time_in'] && $today_record['morning_time_out']) {
        $morning_in = strtotime($today_record['morning_time_in']);
        $morning_out = strtotime($today_record['morning_time_out']);
        $total_hours_worked += ($morning_out - $morning_in) / 3600;
    }
    if ($today_record['afternoon_time_in'] && $today_record['afternoon_time_out']) {
        $afternoon_in = strtotime($today_record['afternoon_time_in']);
        $afternoon_out = strtotime($today_record['afternoon_time_out']);
        $total_hours_worked += ($afternoon_out - $afternoon_in) / 3600;
    }
    
    $response['total_hours'] = round($total_hours_worked, 2);
    
    // Determine current status
    if (!$today_record['morning_time_in']) {
        $response['current_status'] = 'ready_to_time_in';
    } else if ($today_record['morning_time_in'] && !$today_record['morning_time_out']) {
        $response['current_status'] = 'timed_in_morning';
    } else if ($today_record['morning_time_in'] && $today_record['morning_time_out'] && !$today_record['afternoon_time_in']) {
        $response['current_status'] = 'ready_afternoon_time_in';
    } else if ($today_record['afternoon_time_in'] && !$today_record['afternoon_time_out']) {
        $response['current_status'] = 'timed_in_afternoon';
    } else if ($today_record['afternoon_time_out']) {
        $response['current_status'] = 'completed';
    }
}

// Set JSON header and return response
header('Content-Type: application/json');
echo json_encode($response);
?> 