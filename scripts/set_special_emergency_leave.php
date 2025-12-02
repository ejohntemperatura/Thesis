<?php
/**
 * Script to set Special Emergency Leave, Adoption Leave, and Mandatory Leave balances
 * Run this once to initialize the balances for existing employees
 */

require_once __DIR__ . '/../config/database.php';

echo "Setting leave balances for all employees...\n\n";

try {
    // 1. Special Emergency Leave (Calamity) - 5 days
    $stmt = $pdo->prepare("
        UPDATE employees 
        SET special_emergency_leave_balance = 5 
        WHERE special_emergency_leave_balance = 0 OR special_emergency_leave_balance IS NULL
    ");
    $stmt->execute();
    $affected = $stmt->rowCount();
    echo "Special Emergency Leave: Updated $affected employees with 5 days balance.\n";

    // 2. Adoption Leave - 60 days
    $stmt = $pdo->prepare("
        UPDATE employees 
        SET adoption_leave_balance = 60 
        WHERE adoption_leave_balance = 0 OR adoption_leave_balance IS NULL
    ");
    $stmt->execute();
    $affected = $stmt->rowCount();
    echo "Adoption Leave: Updated $affected employees with 60 days balance.\n";

    // 3. Mandatory/Force Leave - 5 days
    $stmt = $pdo->prepare("
        UPDATE employees 
        SET mandatory_leave_balance = 5 
        WHERE mandatory_leave_balance = 0 OR mandatory_leave_balance IS NULL
    ");
    $stmt->execute();
    $affected = $stmt->rowCount();
    echo "Mandatory/Force Leave: Updated $affected employees with 5 days balance.\n";

    // Verify the updates
    echo "\n--- Summary ---\n";
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN special_emergency_leave_balance > 0 THEN 1 ELSE 0 END) as with_emergency,
            SUM(CASE WHEN adoption_leave_balance > 0 THEN 1 ELSE 0 END) as with_adoption,
            SUM(CASE WHEN mandatory_leave_balance > 0 THEN 1 ELSE 0 END) as with_mandatory
        FROM employees
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total employees: {$result['total']}\n";
    echo "With Special Emergency Leave: {$result['with_emergency']}\n";
    echo "With Adoption Leave: {$result['with_adoption']}\n";
    echo "With Mandatory Leave: {$result['with_mandatory']}\n";
    
    echo "\nDone!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
