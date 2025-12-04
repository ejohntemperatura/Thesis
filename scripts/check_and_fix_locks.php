<?php
/**
 * Check and fix database locks
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Checking Database Locks ===\n\n";

try {
    // Check for locked tables
    $stmt = $pdo->query("SHOW OPEN TABLES WHERE In_use > 0");
    $locked = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($locked) > 0) {
        echo "Found " . count($locked) . " locked tables:\n";
        foreach ($locked as $table) {
            echo "- {$table['Database']}.{$table['Table']} (In_use: {$table['In_use']})\n";
        }
    } else {
        echo "No locked tables found.\n";
    }
    
    echo "\n=== Checking Active Processes ===\n\n";
    
    // Check for active processes
    $stmt = $pdo->query("SHOW PROCESSLIST");
    $processes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Active processes:\n";
    foreach ($processes as $proc) {
        if ($proc['Command'] != 'Sleep') {
            echo "ID: {$proc['Id']} | User: {$proc['User']} | Command: {$proc['Command']} | Time: {$proc['Time']}s | State: {$proc['State']}\n";
        }
    }
    
    echo "\n✓ Check complete\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
