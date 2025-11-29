<?php
/**
 * Setup Script for Leave Accrual System
 * Run this once to initialize the accrual system
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Leave Accrual System Setup ===\n\n";

try {
    // Check if last_leave_credit_update column exists
    echo "1. Checking database schema...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'last_leave_credit_update'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        echo "   Adding last_leave_credit_update column...\n";
        $pdo->exec("ALTER TABLE employees ADD COLUMN last_leave_credit_update DATE DEFAULT NULL AFTER service_start_date");
        echo "   ✓ Column added successfully\n";
    } else {
        echo "   ✓ Column already exists\n";
    }
    
    // Check if leave_credit_history table exists
    echo "\n2. Checking leave_credit_history table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'leave_credit_history'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "   Creating leave_credit_history table...\n";
        $pdo->exec("
            CREATE TABLE `leave_credit_history` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `employee_id` int(11) NOT NULL,
              `credit_type` enum('vacation','sick','special_privilege','maternity','paternity','solo_parent','vawc','rehabilitation','special_women','special_emergency','adoption','mandatory','cto','service_credit') NOT NULL,
              `credit_amount` decimal(5,2) NOT NULL,
              `accrual_date` date NOT NULL,
              `service_days` int(11) NOT NULL DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_leave_credit_history_employee` (`employee_id`,`accrual_date`),
              CONSTRAINT `fk_leave_credit_history_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        echo "   ✓ Table created successfully\n";
    } else {
        echo "   ✓ Table already exists\n";
    }
    
    // Create logs directory if it doesn't exist
    echo "\n3. Checking logs directory...\n";
    $logsDir = __DIR__ . '/../logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
        echo "   ✓ Logs directory created\n";
    } else {
        echo "   ✓ Logs directory exists\n";
    }
    
    // Set initial balances to 0 for new accrual system
    echo "\n4. Would you like to reset all employee leave balances to 0? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) === 'y') {
        echo "   Resetting leave balances...\n";
        $pdo->exec("
            UPDATE employees 
            SET 
                vacation_leave_balance = 0,
                sick_leave_balance = 0,
                special_privilege_leave_balance = 0,
                last_leave_credit_update = NULL
            WHERE role = 'employee'
        ");
        echo "   ✓ Leave balances reset to 0\n";
        echo "   Note: Employees will start accruing from next month\n";
    } else {
        echo "   Skipped balance reset\n";
    }
    
    echo "\n=== Setup Complete ===\n";
    echo "\nNext Steps:\n";
    echo "1. Set up cron job to run monthly:\n";
    echo "   0 0 1 * * /usr/bin/php " . __DIR__ . "/../cron/process_monthly_leave_accrual.php\n";
    echo "\n2. Access admin interface at:\n";
    echo "   /app/modules/admin/views/leave_accrual_management.php\n";
    echo "\n3. Review documentation at:\n";
    echo "   /docs/LEAVE_ACCRUAL_SYSTEM.md\n";
    echo "\n";
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
