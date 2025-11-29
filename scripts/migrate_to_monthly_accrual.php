<?php
/**
 * Migration Script: Convert from Annual to Monthly Accrual System
 * 
 * This script helps transition from giving 15 days upfront to monthly accrual of 1.25 days
 */

require_once __DIR__ . '/../config/database.php';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   Migration: Annual to Monthly Leave Accrual System       ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    // Get current employee statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_employees,
            AVG(vacation_leave_balance) as avg_vl,
            AVG(sick_leave_balance) as avg_sl,
            MAX(vacation_leave_balance) as max_vl,
            MAX(sick_leave_balance) as max_sl
        FROM employees 
        WHERE role = 'employee' AND account_status = 'active'
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Current System Status:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Total Active Employees: " . $stats['total_employees'] . "\n";
    echo "Average VL Balance: " . number_format($stats['avg_vl'], 2) . " days\n";
    echo "Average SL Balance: " . number_format($stats['avg_sl'], 2) . " days\n";
    echo "Max VL Balance: " . number_format($stats['max_vl'], 2) . " days\n";
    echo "Max SL Balance: " . number_format($stats['max_sl'], 2) . " days\n";
    echo "\n";
    
    echo "Migration Options:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "1. Reset all to 1.25 days (fresh start with monthly accrual)\n";
    echo "2. Keep existing balances (continue from current, add monthly)\n";
    echo "3. Pro-rate based on service months (calculate fair starting point)\n";
    echo "4. Cancel (no changes)\n";
    echo "\n";
    echo "Enter your choice (1-4): ";
    
    $handle = fopen("php://stdin", "r");
    $choice = trim(fgets($handle));
    fclose($handle);
    
    $pdo->beginTransaction();
    
    switch ($choice) {
        case '1':
            echo "\n[Option 1] Resetting all employees to 1.25 days...\n";
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
            echo "✓ Reset " . $stmt->rowCount() . " employees to 1.25 VL/SL\n";
            echo "✓ Set last_leave_credit_update to today\n";
            echo "✓ Employees will accrue next month\n";
            break;
            
        case '2':
            echo "\n[Option 2] Keeping existing balances...\n";
            $stmt = $pdo->prepare("
                UPDATE employees 
                SET last_leave_credit_update = CURDATE()
                WHERE role = 'employee' 
                AND account_status = 'active'
                AND last_leave_credit_update IS NULL
            ");
            $stmt->execute();
            echo "✓ Updated " . $stmt->rowCount() . " employees\n";
            echo "✓ Existing balances preserved\n";
            echo "✓ Monthly accrual will start next month\n";
            break;
            
        case '3':
            echo "\n[Option 3] Pro-rating based on service months...\n";
            
            // Calculate pro-rated balances based on months in service
            $employees = $pdo->query("
                SELECT 
                    id, 
                    name,
                    COALESCE(service_start_date, DATE(created_at)) as start_date,
                    vacation_leave_balance,
                    sick_leave_balance
                FROM employees 
                WHERE role = 'employee' AND account_status = 'active'
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            $updateStmt = $pdo->prepare("
                UPDATE employees 
                SET 
                    vacation_leave_balance = ?,
                    sick_leave_balance = ?,
                    last_leave_credit_update = CURDATE()
                WHERE id = ?
            ");
            
            $updated = 0;
            foreach ($employees as $emp) {
                $startDate = new DateTime($emp['start_date']);
                $now = new DateTime();
                $interval = $startDate->diff($now);
                $monthsInService = ($interval->y * 12) + $interval->m;
                
                // Calculate fair balance: 1.25 per month, max 15
                $fairVL = min($monthsInService * 1.25, 15);
                $fairSL = min($monthsInService * 1.25, 15);
                
                $updateStmt->execute([$fairVL, $fairSL, $emp['id']]);
                $updated++;
                
                echo "  • " . $emp['name'] . ": $monthsInService months → VL: " . number_format($fairVL, 2) . ", SL: " . number_format($fairSL, 2) . "\n";
            }
            
            echo "✓ Pro-rated $updated employees based on service duration\n";
            break;
            
        case '4':
            echo "\nCancelled. No changes made.\n";
            $pdo->rollBack();
            exit(0);
            
        default:
            echo "\nInvalid choice. No changes made.\n";
            $pdo->rollBack();
            exit(1);
    }
    
    // Update database schema defaults
    echo "\nUpdating database schema...\n";
    $pdo->exec("ALTER TABLE employees MODIFY COLUMN vacation_leave_balance DECIMAL(5,2) DEFAULT 1.25");
    $pdo->exec("ALTER TABLE employees MODIFY COLUMN sick_leave_balance DECIMAL(5,2) DEFAULT 1.25");
    echo "✓ Updated column defaults to 1.25\n";
    
    $pdo->commit();
    
    echo "\n╔════════════════════════════════════════════════════════════╗\n";
    echo "║              Migration Completed Successfully!            ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    echo "Next Steps:\n";
    echo "1. New employees will start with 1.25 VL/SL\n";
    echo "2. Monthly accrual will add 1.25 days each month\n";
    echo "3. Set up cron job for automatic monthly processing\n";
    echo "4. Monitor accrual via admin dashboard\n\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
