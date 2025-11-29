<?php
/**
 * Quick Fix: Set All Employees to 1.25 VL/SL
 * This will update all active employees to have 1.25 days VL and SL
 */

require_once __DIR__ . '/../config/database.php';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     Set All Employees to 1.25 VL/SL (Monthly Accrual)     ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    // Get current employee balances
    $stmt = $pdo->query("
        SELECT 
            id,
            name,
            vacation_leave_balance,
            sick_leave_balance,
            role,
            account_status
        FROM employees 
        WHERE role = 'employee'
        ORDER BY name ASC
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($employees) . " employees\n\n";
    
    if (count($employees) == 0) {
        echo "No employees found. Exiting.\n";
        exit(0);
    }
    
    // Show current balances
    echo "Current Balances:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    foreach ($employees as $emp) {
        $status = $emp['account_status'] == 'active' ? '✓' : '✗';
        echo sprintf(
            "%s %-30s  VL: %6.2f  SL: %6.2f  [%s]\n",
            $status,
            substr($emp['name'], 0, 30),
            $emp['vacation_leave_balance'],
            $emp['sick_leave_balance'],
            $emp['account_status']
        );
    }
    
    echo "\n";
    echo "This will update ALL employees to:\n";
    echo "  • Vacation Leave: 1.25 days\n";
    echo "  • Sick Leave: 1.25 days\n";
    echo "  • Special Leave Privilege: 3.00 days\n";
    echo "  • Last Accrual Date: Today\n";
    echo "\n";
    echo "Do you want to proceed? (yes/no): ";
    
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) !== 'yes') {
        echo "\nCancelled. No changes made.\n";
        exit(0);
    }
    
    echo "\nUpdating employees...\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $pdo->beginTransaction();
    
    $updateStmt = $pdo->prepare("
        UPDATE employees 
        SET 
            vacation_leave_balance = 1.25,
            sick_leave_balance = 1.25,
            special_privilege_leave_balance = 3.00,
            last_leave_credit_update = CURDATE()
        WHERE id = ?
    ");
    
    $updated = 0;
    foreach ($employees as $emp) {
        $updateStmt->execute([$emp['id']]);
        $updated++;
        echo "✓ Updated: " . $emp['name'] . "\n";
    }
    
    $pdo->commit();
    
    echo "\n╔════════════════════════════════════════════════════════════╗\n";
    echo "║              Update Completed Successfully!               ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    echo "Summary:\n";
    echo "  • Updated: $updated employees\n";
    echo "  • All employees now have 1.25 VL and 1.25 SL\n";
    echo "  • Monthly accrual will add 1.25 days each month\n";
    echo "  • Next accrual: " . date('F 1, Y', strtotime('first day of next month')) . "\n";
    echo "\n";
    
    // Show updated balances
    echo "Verification - New Balances:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $verifyStmt = $pdo->query("
        SELECT 
            name,
            vacation_leave_balance,
            sick_leave_balance,
            special_privilege_leave_balance,
            last_leave_credit_update
        FROM employees 
        WHERE role = 'employee'
        ORDER BY name ASC
        LIMIT 10
    ");
    
    while ($row = $verifyStmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf(
            "%-30s  VL: %.2f  SL: %.2f  SLP: %.2f  Updated: %s\n",
            substr($row['name'], 0, 30),
            $row['vacation_leave_balance'],
            $row['sick_leave_balance'],
            $row['special_privilege_leave_balance'],
            $row['last_leave_credit_update']
        );
    }
    
    echo "\n✓ Done! Refresh your browser to see the changes.\n\n";
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
