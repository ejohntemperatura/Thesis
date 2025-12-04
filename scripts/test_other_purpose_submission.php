<?php
/**
 * Test script to verify "other" purpose leave submission works correctly
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/leave_types.php';

echo "Testing Other Purpose Leave Submission\n";
echo "======================================\n\n";

// Test data
$testEmployee = 6; // Using existing employee ID

// Check if employee exists
$stmt = $pdo->prepare("SELECT id, name FROM employees WHERE id = ?");
$stmt->execute([$testEmployee]);
$employee = $stmt->fetch();

if (!$employee) {
    echo "❌ Test employee (ID: $testEmployee) not found. Please update the script with a valid employee ID.\n";
    exit(1);
}

echo "Testing with employee: {$employee['name']} (ID: {$employee['id']})\n\n";

// Test 1: Terminal Leave
echo "Test 1: Terminal Leave Submission\n";
echo "----------------------------------\n";

$terminalData = [
    'employee_id' => $testEmployee,
    'leave_type' => 'other',
    'other_purpose' => 'terminal_leave',
    'working_days_applied' => 10,
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d'),
    'selected_dates' => '',
    'reason' => 'Test terminal leave - converting 10 days of leave credits to cash',
    'status' => 'pending',
    'days_requested' => 10,
    'commutation' => 'requested'
];

try {
    $stmt = $pdo->prepare("
        INSERT INTO leave_requests 
        (employee_id, leave_type, other_purpose, working_days_applied, start_date, end_date, 
         selected_dates, reason, status, days_requested, commutation, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $terminalData['employee_id'],
        $terminalData['leave_type'],
        $terminalData['other_purpose'],
        $terminalData['working_days_applied'],
        $terminalData['start_date'],
        $terminalData['end_date'],
        $terminalData['selected_dates'],
        $terminalData['reason'],
        $terminalData['status'],
        $terminalData['days_requested'],
        $terminalData['commutation']
    ]);
    
    $terminalId = $pdo->lastInsertId();
    echo "✓ Terminal Leave request created (ID: $terminalId)\n";
    
    // Verify it was saved correctly
    $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ?");
    $stmt->execute([$terminalId]);
    $saved = $stmt->fetch();
    
    echo "  - Leave Type: {$saved['leave_type']}\n";
    echo "  - Other Purpose: {$saved['other_purpose']}\n";
    echo "  - Working Days Applied: {$saved['working_days_applied']}\n";
    echo "  - Commutation: {$saved['commutation']}\n";
    
    // Test display name
    $displayName = getLeaveTypeDisplayName(
        $saved['leave_type'], 
        $saved['original_leave_type'] ?? null, 
        getLeaveTypes(),
        $saved['other_purpose']
    );
    echo "  - Display Name: $displayName\n";
    
} catch (PDOException $e) {
    echo "✗ Failed to create Terminal Leave request: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Monetization
echo "Test 2: Monetization Submission\n";
echo "--------------------------------\n";

$monetizationData = [
    'employee_id' => $testEmployee,
    'leave_type' => 'other',
    'other_purpose' => 'monetization',
    'working_days_applied' => 5,
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d'),
    'selected_dates' => '',
    'reason' => 'Test monetization - converting 5 days of leave credits to cash',
    'status' => 'pending',
    'days_requested' => 5,
    'commutation' => 'not_requested'
];

try {
    $stmt = $pdo->prepare("
        INSERT INTO leave_requests 
        (employee_id, leave_type, other_purpose, working_days_applied, start_date, end_date, 
         selected_dates, reason, status, days_requested, commutation, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $monetizationData['employee_id'],
        $monetizationData['leave_type'],
        $monetizationData['other_purpose'],
        $monetizationData['working_days_applied'],
        $monetizationData['start_date'],
        $monetizationData['end_date'],
        $monetizationData['selected_dates'],
        $monetizationData['reason'],
        $monetizationData['status'],
        $monetizationData['days_requested'],
        $monetizationData['commutation']
    ]);
    
    $monetizationId = $pdo->lastInsertId();
    echo "✓ Monetization request created (ID: $monetizationId)\n";
    
    // Verify it was saved correctly
    $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ?");
    $stmt->execute([$monetizationId]);
    $saved = $stmt->fetch();
    
    echo "  - Leave Type: {$saved['leave_type']}\n";
    echo "  - Other Purpose: {$saved['other_purpose']}\n";
    echo "  - Working Days Applied: {$saved['working_days_applied']}\n";
    echo "  - Commutation: {$saved['commutation']}\n";
    
    // Test display name
    $displayName = getLeaveTypeDisplayName(
        $saved['leave_type'], 
        $saved['original_leave_type'] ?? null, 
        getLeaveTypes(),
        $saved['other_purpose']
    );
    echo "  - Display Name: $displayName\n";
    
} catch (PDOException $e) {
    echo "✗ Failed to create Monetization request: " . $e->getMessage() . "\n";
}

echo "\n";
echo "✅ Test complete!\n";
echo "\nYou can now view these requests in the admin panel.\n";
echo "To clean up test data, run:\n";
echo "DELETE FROM leave_requests WHERE id IN ($terminalId, $monetizationId);\n";
?>
