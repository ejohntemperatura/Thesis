<?php
/**
 * Migration Script: Add other_purpose and working_days_applied fields
 * This script adds fields for Terminal Leave and Monetization as "Other Purpose"
 */

require_once __DIR__ . '/../config/database.php';

try {
    echo "Starting migration to add other_purpose fields...\n\n";
    
    // 1. Add other_purpose column to leave_requests
    echo "1. Adding other_purpose column to leave_requests table...\n";
    try {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN other_purpose ENUM('terminal_leave', 'monetization') DEFAULT NULL AFTER leave_type");
        echo "✓ Added other_purpose column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠ other_purpose column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // 2. Add working_days_applied column to leave_requests
    echo "\n2. Adding working_days_applied column to leave_requests table...\n";
    try {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN working_days_applied INT DEFAULT NULL AFTER other_purpose");
        echo "✓ Added working_days_applied column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠ working_days_applied column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // 3. Migrate existing terminal and monetization leave requests
    echo "\n3. Migrating existing terminal leave requests...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type = 'terminal'");
    $terminalCount = $stmt->fetchColumn();
    if ($terminalCount > 0) {
        $pdo->exec("UPDATE leave_requests SET other_purpose = 'terminal_leave' WHERE leave_type = 'terminal'");
        echo "✓ Migrated $terminalCount terminal leave requests\n";
    } else {
        echo "⚠ No terminal leave requests to migrate\n";
    }
    
    echo "\n4. Migrating existing monetization leave requests...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type = 'monetization'");
    $monetizationCount = $stmt->fetchColumn();
    if ($monetizationCount > 0) {
        $pdo->exec("UPDATE leave_requests SET other_purpose = 'monetization' WHERE leave_type = 'monetization'");
        echo "✓ Migrated $monetizationCount monetization leave requests\n";
    } else {
        echo "⚠ No monetization leave requests to migrate\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "\nSummary:\n";
    echo "- Added other_purpose column (terminal_leave, monetization)\n";
    echo "- Added working_days_applied column\n";
    echo "- Migrated existing terminal and monetization requests\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
