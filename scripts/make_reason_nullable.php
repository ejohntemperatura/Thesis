<?php
/**
 * Migration Script: Make reason column nullable
 * 
 * This script modifies the leave_requests table to make the 'reason' column nullable
 * since the reason field is no longer required in leave applications.
 * Only late_justification is now used for late leave applications.
 */

require_once __DIR__ . '/../config/database.php';

try {
    echo "Starting migration: Making 'reason' column nullable...\n";
    
    // Alter the reason column to allow NULL values
    $sql = "ALTER TABLE leave_requests MODIFY COLUMN reason TEXT NULL";
    $pdo->exec($sql);
    
    echo "✓ Successfully modified 'reason' column to allow NULL values\n";
    
    // Update any existing records with empty reason to NULL (optional cleanup)
    $updateSql = "UPDATE leave_requests SET reason = NULL WHERE reason = '' OR reason = 'Late leave application'";
    $affected = $pdo->exec($updateSql);
    
    echo "✓ Updated $affected existing records with empty/default reason to NULL\n";
    
    echo "\nMigration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "✗ Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>
