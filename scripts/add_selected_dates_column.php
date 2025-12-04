<?php
/**
 * Migration script to add selected_dates column to leave_requests table
 * This column stores comma-separated list of selected dates for leave requests
 */

require_once dirname(__DIR__) . '/config/database.php';

try {
    echo "Adding selected_dates column to leave_requests table...\n";
    
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'selected_dates'");
    if ($stmt->rowCount() > 0) {
        echo "Column 'selected_dates' already exists. Skipping...\n";
        exit(0);
    }
    
    // Add the column
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN selected_dates TEXT NULL AFTER end_date");
    
    echo "Successfully added selected_dates column!\n";
    echo "Migration completed.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
