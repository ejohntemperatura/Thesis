<?php
/**
 * Test script to demonstrate querying leave requests with other_purpose
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/leave_types.php';

echo "Testing Other Purpose Leave Queries\n";
echo "====================================\n\n";

// Show the other purpose options
echo "Available Other Purpose Options:\n";
$otherPurposes = getOtherPurposeOptions();
foreach ($otherPurposes as $key => $config) {
    echo "  - $key: {$config['formal_name']}\n";
}

echo "\n";

// Query for any 'other' leave type requests
$stmt = $pdo->query("
    SELECT 
        lr.id,
        lr.leave_type,
        lr.other_purpose,
        lr.working_days_applied,
        lr.days_requested,
        lr.status,
        e.name as employee_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    WHERE lr.leave_type = 'other'
    ORDER BY lr.created_at DESC
    LIMIT 5
");

$results = $stmt->fetchAll();

if (count($results) > 0) {
    echo "Recent 'Other' Purpose Leave Requests:\n";
    echo "--------------------------------------\n";
    foreach ($results as $row) {
        $purposeName = isset($otherPurposes[$row['other_purpose']]) 
            ? $otherPurposes[$row['other_purpose']]['name'] 
            : $row['other_purpose'];
        
        echo "ID: {$row['id']}\n";
        echo "  Employee: {$row['employee_name']}\n";
        echo "  Purpose: $purposeName\n";
        echo "  Working Days: {$row['working_days_applied']}\n";
        echo "  Status: {$row['status']}\n";
        echo "\n";
    }
} else {
    echo "No 'other' purpose leave requests found yet.\n";
    echo "This is normal if no one has submitted Terminal Leave or Monetization requests.\n";
}

echo "\n✅ Test complete!\n";
?>
