<?php
/**
 * Migration Script: Add commutation column to leave_requests table
 * This script adds the commutation field to track whether employees request
 * commutation (monetization) of their leave credits
 */

require_once __DIR__ . '/../config/database.php';

try {
    echo "Starting migration: Adding commutation column to leave_requests table...\n\n";
    
    // Check if commutation column already exists
    $checkStmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'commutation'");
    $columnExists = $checkStmt->fetch();
    
    if ($columnExists) {
        echo "✓ Column 'commutation' already exists in leave_requests table.\n";
    } else {
        // Add commutation column
        $pdo->exec("ALTER TABLE leave_requests 
                    ADD COLUMN commutation ENUM('not_requested', 'requested') DEFAULT 'not_requested' 
                    AFTER study_type");
        echo "✓ Successfully added 'commutation' column to leave_requests table.\n";
    }
    
    echo "\n=== Migration completed successfully! ===\n";
    echo "The commutation field is now available for leave applications.\n";
    
} catch (PDOException $e) {
    echo "✗ Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>
