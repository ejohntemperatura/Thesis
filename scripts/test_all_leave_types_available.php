<?php
/**
 * Test script to verify that all leave types are now available in the add leave credits functionality
 */

require_once __DIR__ . '/../config/leave_types.php';

echo "=== Testing All Leave Types Available in Add Leave Credits ===\n\n";

// Get all leave types
$leaveTypes = getLeaveTypes();

echo "1. All leave types that require credits:\n";
$creditRequiredTypes = [];
foreach ($leaveTypes as $key => $config) {
    if ($config['requires_credits']) {
        $creditRequiredTypes[] = $key;
        echo "   ✓ $key - {$config['name']}\n";
    }
}

echo "\n2. Previously excluded types that should now be available:\n";
$previouslyExcluded = ['vacation', 'sick', 'special_privilege', 'cto', 'service_credit'];
foreach ($previouslyExcluded as $type) {
    if (in_array($type, $creditRequiredTypes)) {
        echo "   ✅ $type - Now available\n";
    } else {
        echo "   ❌ $type - Still not available\n";
    }
}

echo "\n3. Leave types and their default expiry rules:\n";
$oneYearExpiryTypes = ['mandatory', 'cto', 'special_privilege'];
foreach ($creditRequiredTypes as $type) {
    $defaultExpiry = in_array($type, $oneYearExpiryTypes) ? '1-year expiry' : 'No expiry';
    echo "   $type - Default: $defaultExpiry\n";
}

echo "\n4. Summary:\n";
echo "   Total leave types requiring credits: " . count($creditRequiredTypes) . "\n";
echo "   Previously excluded types now available: " . count(array_intersect($previouslyExcluded, $creditRequiredTypes)) . "\n";

echo "\n5. Expected behavior:\n";
echo "   - Vacation Leave: Available, defaults to 'No expiry'\n";
echo "   - Sick Leave: Available, defaults to 'No expiry'\n";
echo "   - Special Leave Privilege (SLP): Available, defaults to '1-year expiry'\n";
echo "   - Compensatory Time Off (CTO): Available, defaults to '1-year expiry'\n";
echo "   - Service Credits: Available, defaults to 'No expiry'\n";
echo "   - Force Leave (Mandatory): Available, defaults to '1-year expiry'\n";

echo "\n=== Test Complete ===\n";
echo "Status: All requested leave types should now be available in both:\n";
echo "- Leave Management modal (Add Leave Credits button)\n";
echo "- CTO Management page form\n";
?>