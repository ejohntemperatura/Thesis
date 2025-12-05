<?php
/**
 * Migration Script: Split name field into first_name, middle_name, last_name
 * This script adds new columns and migrates existing data
 */

require_once __DIR__ . '/../config/database.php';

try {
    echo "Starting name field migration...\n";
    
    // Step 1: Add new columns
    echo "Adding new columns...\n";
    $pdo->exec("ALTER TABLE employees 
        ADD COLUMN first_name VARCHAR(100) DEFAULT NULL AFTER name,
        ADD COLUMN middle_name VARCHAR(100) DEFAULT NULL AFTER first_name,
        ADD COLUMN last_name VARCHAR(100) DEFAULT NULL AFTER middle_name");
    echo "✓ New columns added successfully\n";
    
    // Step 2: Migrate existing data
    echo "\nMigrating existing data...\n";
    $stmt = $pdo->query("SELECT id, name FROM employees WHERE name IS NOT NULL AND name != ''");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updateStmt = $pdo->prepare("UPDATE employees SET first_name = ?, middle_name = ?, last_name = ? WHERE id = ?");
    
    foreach ($employees as $employee) {
        $fullName = trim($employee['name']);
        $nameParts = explode(' ', $fullName);
        
        $firstName = '';
        $middleName = '';
        $lastName = '';
        
        if (count($nameParts) == 1) {
            // Only one name part - treat as first name
            $firstName = $nameParts[0];
        } elseif (count($nameParts) == 2) {
            // Two parts - first and last name
            $firstName = $nameParts[0];
            $lastName = $nameParts[1];
        } else {
            // Three or more parts - first, middle(s), last
            $firstName = $nameParts[0];
            $lastName = array_pop($nameParts);
            array_shift($nameParts); // Remove first name
            $middleName = implode(' ', $nameParts);
        }
        
        $updateStmt->execute([$firstName, $middleName, $lastName, $employee['id']]);
        echo "  Migrated: {$fullName} -> First: {$firstName}, Middle: {$middleName}, Last: {$lastName}\n";
    }
    
    echo "\n✓ Data migration completed successfully\n";
    echo "\nMigration completed! The 'name' column is kept for backward compatibility.\n";
    echo "You can manually drop it later if needed: ALTER TABLE employees DROP COLUMN name;\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
