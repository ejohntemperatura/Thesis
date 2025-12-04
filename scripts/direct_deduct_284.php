<?php
/**
 * Direct deduction for request #284
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Direct Deduction for Request #284 ===\n\n";

try {
    // Get current balance
    $stmt = $pdo->prepare("SELECT vacation_leave_balance, sick_leave_balance FROM employees WHERE id = 101");
    $stmt->execute();
    $balance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Current VL Balance: {$balance['vacation_leave_balance']}\n";
    echo "Current SL Balance: {$balance['sick_leave_balance']}\n\n";
    
    // Deduct 1 day from VL
    echo "Deducting 1 day from Vacation Leave...\n";
    $stmt = $pdo->prepare("UPDATE employees SET vacation_leave_balance = vacation_leave_balance - 1 WHERE id = 101");
    $stmt->execute();
    
    // Verify
    $stmt = $pdo->prepare("SELECT vacation_leave_balance, sick_leave_balance FROM employees WHERE id = 101");
    $stmt->execute();
    $newBalance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\nNew VL Balance: {$newBalance['vacation_leave_balance']}\n";
    echo "New SL Balance: {$newBalance['sick_leave_balance']}\n";
    
    echo "\n✓ Deduction complete!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
