<?php
/**
 * Comprehensive verification of all leave_requests columns
 */

require_once __DIR__ . '/../config/database.php';

echo "Comprehensive Column Verification\n";
echo "==================================\n\n";

$requiredColumns = [
    'other_purpose' => 'ENUM for terminal_leave/monetization',
    'working_days_applied' => 'INT for leave credits',
    'commutation' => 'ENUM for requested/not_requested',
    'selected_dates' => 'TEXT for calendar dates',
    'original_leave_type' => 'VARCHAR for without pay tracking'
];

$allGood = true;

foreach ($requiredColumns as $column => $description) {
    $stmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE '$column'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "✓ $column: EXISTS ($description)\n";
    } else {
        echo "✗ $column: MISSING ($description)\n";
        $allGood = false;
    }
}

echo "\n";

// Check leave_type enum
$stmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'leave_type'");
$result = $stmt->fetch();
if ($result) {
    $hasOther = strpos($result['Type'], "'other'") !== false;
    echo ($hasOther ? "✓" : "✗") . " 'other' in leave_type enum\n";
    if (!$hasOther) $allGood = false;
}

echo "\n";
echo ($allGood ? "✅ All columns verified!" : "❌ Some columns are missing!") . "\n";

exit($allGood ? 0 : 1);
?>
