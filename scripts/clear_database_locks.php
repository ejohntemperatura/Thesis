<?php
/**
 * Clear Database Locks Script
 * This script helps resolve database lock issues by showing and killing blocking transactions
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Database Lock Checker and Cleaner ===\n\n";

try {
    // Check for running transactions
    echo "1. Checking for running transactions...\n";
    $stmt = $pdo->query("SELECT * FROM information_schema.INNODB_TRX");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        echo "   ✓ No running transactions found\n\n";
    } else {
        echo "   Found " . count($transactions) . " running transaction(s):\n";
        foreach ($transactions as $trx) {
            echo "   - Transaction ID: {$trx['trx_id']}, State: {$trx['trx_state']}, Started: {$trx['trx_started']}\n";
        }
        echo "\n";
    }
    
    // Check for locked tables
    echo "2. Checking for locked tables...\n";
    $stmt = $pdo->query("SHOW OPEN TABLES WHERE In_use > 0");
    $lockedTables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($lockedTables)) {
        echo "   ✓ No locked tables found\n\n";
    } else {
        echo "   Found " . count($lockedTables) . " locked table(s):\n";
        foreach ($lockedTables as $table) {
            echo "   - Database: {$table['Database']}, Table: {$table['Table']}, In_use: {$table['In_use']}\n";
        }
        echo "\n";
    }
    
    // Check for blocking processes
    echo "3. Checking for blocking processes...\n";
    $stmt = $pdo->query("
        SELECT 
            r.trx_id waiting_trx_id,
            r.trx_mysql_thread_id waiting_thread,
            r.trx_query waiting_query,
            b.trx_id blocking_trx_id,
            b.trx_mysql_thread_id blocking_thread,
            b.trx_query blocking_query
        FROM information_schema.INNODB_LOCK_WAITS w
        INNER JOIN information_schema.INNODB_TRX b ON b.trx_id = w.blocking_trx_id
        INNER JOIN information_schema.INNODB_TRX r ON r.trx_id = w.requesting_trx_id
    ");
    $blockingProcesses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($blockingProcesses)) {
        echo "   ✓ No blocking processes found\n\n";
    } else {
        echo "   Found " . count($blockingProcesses) . " blocking process(es):\n";
        foreach ($blockingProcesses as $process) {
            echo "   - Blocking Thread: {$process['blocking_thread']}, Waiting Thread: {$process['waiting_thread']}\n";
            echo "     Blocking Query: " . substr($process['blocking_query'] ?? 'NULL', 0, 100) . "\n";
        }
        echo "\n";
    }
    
    // Show current processes
    echo "4. Current MySQL processes:\n";
    $stmt = $pdo->query("SHOW PROCESSLIST");
    $processes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $sleepingCount = 0;
    $activeCount = 0;
    
    foreach ($processes as $process) {
        if ($process['Command'] === 'Sleep') {
            $sleepingCount++;
        } else {
            $activeCount++;
            if ($process['Time'] > 10) { // Show processes running longer than 10 seconds
                echo "   - ID: {$process['Id']}, User: {$process['User']}, Time: {$process['Time']}s, State: {$process['State']}\n";
                if (!empty($process['Info'])) {
                    echo "     Query: " . substr($process['Info'], 0, 100) . "\n";
                }
            }
        }
    }
    
    echo "   Total: " . count($processes) . " processes (Active: $activeCount, Sleeping: $sleepingCount)\n\n";
    
    // Attempt to clear locks
    echo "5. Attempting to clear locks...\n";
    
    // Kill long-running queries (older than 60 seconds)
    $killedCount = 0;
    foreach ($processes as $process) {
        if ($process['Command'] !== 'Sleep' && 
            $process['Time'] > 60 && 
            $process['User'] !== 'system user' &&
            $process['Id'] != $pdo->query("SELECT CONNECTION_ID()")->fetchColumn()) {
            
            try {
                $pdo->exec("KILL {$process['Id']}");
                echo "   ✓ Killed process ID: {$process['Id']} (running for {$process['Time']}s)\n";
                $killedCount++;
            } catch (Exception $e) {
                echo "   ✗ Failed to kill process ID: {$process['Id']} - {$e->getMessage()}\n";
            }
        }
    }
    
    if ($killedCount === 0) {
        echo "   ✓ No long-running processes to kill\n";
    }
    
    echo "\n";
    
    // Optimize tables
    echo "6. Optimizing key tables...\n";
    $tables = ['leave_requests', 'employees', 'leave_credits'];
    foreach ($tables as $table) {
        try {
            $pdo->exec("OPTIMIZE TABLE $table");
            echo "   ✓ Optimized table: $table\n";
        } catch (Exception $e) {
            echo "   ✗ Failed to optimize $table: {$e->getMessage()}\n";
        }
    }
    
    echo "\n=== Database Lock Check Complete ===\n";
    echo "You can now try submitting your leave request again.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
