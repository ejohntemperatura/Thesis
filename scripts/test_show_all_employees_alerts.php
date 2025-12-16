<?php
/**
 * Test script to verify that all employees are now shown in leave alerts
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/core/services/EnhancedLeaveAlertService.php';

echo "=== Testing Show All Employees in Leave Alerts ===\n\n";

// Initialize the alert service
$alertService = new EnhancedLeaveAlertService($pdo);

// Test 1: Get all employees data
echo "1. Testing getAllEmployeesWithOneYearExpiryData method...\n";
$allEmployees = $alertService->generateComprehensiveAlerts();

echo "   Total employees returned: " . count($allEmployees) . "\n";

// Test 2: Check if employees without alerts are included
$employeesWithAlerts = 0;
$employeesWithoutAlerts = 0;

foreach ($allEmployees as $employeeId => $data) {
    if (!empty($data['alerts'])) {
        $employeesWithAlerts++;
    } else {
        $employeesWithoutAlerts++;
    }
}

echo "   Employees with alerts: $employeesWithAlerts\n";
echo "   Employees without alerts: $employeesWithoutAlerts\n";

// Test 3: Show sample employee data
echo "\n2. Sample employee data:\n";
$count = 0;
foreach ($allEmployees as $employeeId => $data) {
    if ($count >= 3) break; // Show only first 3 employees
    
    $employee = $data['employee'];
    $alerts = $data['alerts'];
    $priority = $data['priority'];
    
    echo "   Employee: {$employee['name']}\n";
    echo "   Department: {$employee['department']}\n";
    echo "   Priority: $priority\n";
    echo "   Alerts: " . count($alerts) . "\n";
    echo "   Force Leave Balance: {$employee['mandatory_leave_balance']}\n";
    echo "   CTO Balance: {$employee['cto_balance']}\n";
    echo "   SLP Balance: {$employee['special_leave_privilege_balance']}\n";
    echo "   ---\n";
    
    $count++;
}

// Test 4: Check database query
echo "\n3. Testing database query for all employees...\n";
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as total_employees
        FROM employees 
        WHERE role = 'employee'
    ");
    $result = $stmt->fetch();
    $totalEmployeesInDB = $result['total_employees'];
    
    echo "   Total employees in database: $totalEmployeesInDB\n";
    echo "   Total employees in alert system: " . count($allEmployees) . "\n";
    
    if (count($allEmployees) == $totalEmployeesInDB) {
        echo "   ✓ SUCCESS: All employees are included in the alert system\n";
    } else {
        echo "   ⚠️ WARNING: Some employees might be missing from the alert system\n";
    }
    
} catch (Exception $e) {
    echo "   ✗ ERROR: " . $e->getMessage() . "\n";
}

// Test 5: Check priority distribution
echo "\n4. Priority distribution:\n";
$priorityCount = [
    'critical' => 0,
    'urgent' => 0,
    'moderate' => 0,
    'none' => 0
];

foreach ($allEmployees as $data) {
    $priority = $data['priority'];
    if (isset($priorityCount[$priority])) {
        $priorityCount[$priority]++;
    }
}

foreach ($priorityCount as $priority => $count) {
    echo "   $priority: $count employees\n";
}

echo "\n=== Test Complete ===\n";
echo "Status: All employees should now be visible in the leave alerts page\n";
echo "\nChanges made:\n";
echo "1. Modified EnhancedLeaveAlertService to include ALL employees\n";
echo "2. Updated leave_alerts.php to handle employees without alerts\n";
echo "3. Added visual indicators for employees with no expiring credits\n";
echo "4. Updated UI to show 'All Employees' instead of 'Employees with alerts'\n";
?>