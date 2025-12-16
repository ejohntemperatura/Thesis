<?php
/**
 * Test script to verify the expiry field implementation
 * This script tests the conditional expiry field functionality
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/leave_types.php';

echo "=== Testing Expiry Field Implementation ===\n\n";

// Test 1: Check if leave types configuration is accessible
echo "1. Testing leave types configuration...\n";
$leaveTypes = getLeaveTypes();
$oneYearExpiryTypes = ['mandatory', 'cto', 'special_privilege'];

foreach ($oneYearExpiryTypes as $type) {
    if (isset($leaveTypes[$type])) {
        echo "   ✓ {$type} ({$leaveTypes[$type]['name']}) - Found\n";
    } else {
        echo "   ✗ {$type} - Not found\n";
    }
}

// Test 2: Check if database has the necessary tables
echo "\n2. Testing database tables...\n";
try {
    // Check if leave_credit_expiry_tracking table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'leave_credit_expiry_tracking'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ leave_credit_expiry_tracking table exists\n";
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE leave_credit_expiry_tracking");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredColumns = ['employee_id', 'leave_type', 'credits_granted', 'grant_date', 'expiry_date'];
        
        foreach ($requiredColumns as $col) {
            if (in_array($col, $columns)) {
                echo "   ✓ Column '{$col}' exists\n";
            } else {
                echo "   ✗ Column '{$col}' missing\n";
            }
        }
    } else {
        echo "   ✗ leave_credit_expiry_tracking table does not exist\n";
    }
    
    // Check if stored procedure exists
    $stmt = $pdo->query("SHOW PROCEDURE STATUS WHERE Name = 'add_leave_credit_with_expiry'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ add_leave_credit_with_expiry stored procedure exists\n";
    } else {
        echo "   ✗ add_leave_credit_with_expiry stored procedure does not exist\n";
    }
    
} catch (PDOException $e) {
    echo "   ✗ Database error: " . $e->getMessage() . "\n";
}

// Test 3: Simulate form processing logic
echo "\n3. Testing form processing logic...\n";

// Simulate POST data for 1-year expiry
$testData1 = [
    'employee_id' => 1,
    'leave_type' => 'mandatory',
    'credits_to_add' => 5.0,
    'reason' => 'Test addition',
    'expiry_rule' => 'one_year_expiry'
];

echo "   Testing 1-year expiry rule:\n";
echo "   - Leave Type: {$testData1['leave_type']}\n";
echo "   - Expiry Rule: {$testData1['expiry_rule']}\n";
$useExpiryTracking1 = ($testData1['expiry_rule'] === 'one_year_expiry');
echo "   - Use Expiry Tracking: " . ($useExpiryTracking1 ? 'Yes' : 'No') . "\n";

// Simulate POST data for no expiry
$testData2 = [
    'employee_id' => 1,
    'leave_type' => 'maternity',
    'credits_to_add' => 105.0,
    'reason' => 'Test addition',
    'expiry_rule' => 'no_expiry'
];

echo "\n   Testing no expiry rule:\n";
echo "   - Leave Type: {$testData2['leave_type']}\n";
echo "   - Expiry Rule: {$testData2['expiry_rule']}\n";
$useExpiryTracking2 = ($testData2['expiry_rule'] === 'one_year_expiry');
echo "   - Use Expiry Tracking: " . ($useExpiryTracking2 ? 'Yes' : 'No') . "\n";

// Test 4: Check file modifications
echo "\n4. Testing file modifications...\n";

$filesToCheck = [
    'app/modules/admin/views/leave_management.php',
    'app/modules/admin/views/cto_management.php'
];

foreach ($filesToCheck as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Check for expiry field
        if (strpos($content, 'expiry_rule') !== false) {
            echo "   ✓ {$file} - Contains expiry_rule field\n";
        } else {
            echo "   ✗ {$file} - Missing expiry_rule field\n";
        }
        
        // Check for JavaScript handling
        if (strpos($content, 'expiryOptionsDiv') !== false || strpos($content, 'cto_expiry_options_div') !== false) {
            echo "   ✓ {$file} - Contains expiry options JavaScript\n";
        } else {
            echo "   ✗ {$file} - Missing expiry options JavaScript\n";
        }
    } else {
        echo "   ✗ {$file} - File not found\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "Implementation Status: Ready for testing\n";
echo "\nNext Steps:\n";
echo "1. Access the leave management page to test the modal\n";
echo "2. Access the CTO management page to test the form\n";
echo "3. Try adding leave credits with both expiry options\n";
echo "4. Verify that the expiry field appears/disappears based on leave type selection\n";
echo "5. Check that form validation works for the expiry rule requirement\n";
?>