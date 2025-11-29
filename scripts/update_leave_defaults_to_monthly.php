<?php
/**
 * Update Leave Balance Defaults to Monthly Accrual System
 * Changes default VL and SL from 15/10 to 1.25 (monthly accrual rate)
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Updating Leave Balance Defaults ===\n\n";

try {
    $pdo->beginTransaction();
    
    // 1. Update table defaults for new employees
    echo "1. Updating table column defaults...\n";
    
    $pdo->exec("ALTER TABLE employees MODIFY COLUMN vacation_leave_balance DECIMAL(5,2) DEFAULT 1.25");
    echo "   ✓ Vacation Leave default changed to 1.25\n";
    
    $pdo->exec("ALTER TABLE employees MODIFY COLUMN sick_leave_balance DECIMAL(5,2) DEFAULT 1.25");
    echo "   ✓ Sick Leave default changed to 1.25\n";
    
    // 2. Ask if user wants to reset existing employees
    echo "\n2. Do you want to reset existing employees to 1.25 days? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) === 'y') {
        echo "   Resetting existing employee balances...\n";
        
        $stmt = $pdo->prepare("
            UPDATE employees 
            SET 
                vacation_leave_balance = 1.25,
                sick_leave_balance = 1.25,
                special_privilege_leave_balance = 3.0,
                last_leave_credit_update = CURDATE()
            WHERE role = 'employee' AND account_status = 'active'
        ");
        $stmt->execute();
        
        $affected = $stmt->rowCount();
        echo "   ✓ Updated $affected employees\n";
        echo "   Note: Set last_leave_credit_update to today so they accrue next month\n";
    } else {
        echo "   Skipped existing employee reset\n";
    }
    
    $pdo->commit();
    
    echo "\n=== Update Complete ===\n";
    echo "\nNew employees will now start with:\n";
    echo "- Vacation Leave: 1.25 days\n";
    echo "- Sick Leave: 1.25 days\n";
    echo "- Special Leave Privilege: 3.0 days\n";
    echo "\nThey will accrue 1.25 days VL and SL each month automatically.\n\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
