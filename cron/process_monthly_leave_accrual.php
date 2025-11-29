<?php
/**
 * Monthly Leave Credits Accrual System
 * 
 * This script automatically accrues leave credits on a monthly basis:
 * - Vacation Leave: 1.25 days per month (15 days annually)
 * - Sick Leave: 1.25 days per month (15 days annually)
 * - Special Leave Privilege: 3 days annually (accrued on January 1st)
 * 
 * Run this script monthly via cron job:
 * 0 0 1 * * /usr/bin/php /path/to/cron/process_monthly_leave_accrual.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/core/services/LeaveAccrualService.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Log file
$logFile = __DIR__ . '/../logs/leave_accrual_' . date('Y-m') . '.log';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
}

try {
    logMessage("=== Starting Monthly Leave Accrual Process ===");
    
    $accrualService = new LeaveAccrualService($pdo);
    $results = $accrualService->processMonthlyAccrual();
    
    logMessage("Total Processed: " . $results['processed']);
    logMessage("Total Skipped: " . $results['skipped']);
    logMessage("Total Errors: " . $results['errors']);
    logMessage("");
    
    // Log details
    foreach ($results['details'] as $detail) {
        $status = strtoupper($detail['status']);
        $name = $detail['name'] ?? 'N/A';
        $message = $detail['message'];
        logMessage("  [$status] $name: $message");
    }
    
    logMessage("\n=== Accrual Process Complete ===");
    logMessage("=====================================\n");
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    exit(1);
}
?>
