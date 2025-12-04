<?php
require_once __DIR__ . '/../config/database.php';

echo "Verifying database changes...\n\n";

// Check other_purpose column
$stmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'other_purpose'");
$result = $stmt->fetch();
echo "1. other_purpose column: " . ($result ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";

// Check working_days_applied column
$stmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'working_days_applied'");
$result = $stmt->fetch();
echo "2. working_days_applied column: " . ($result ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";

// Check if 'other' is in leave_type enum
$stmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'leave_type'");
$result = $stmt->fetch();
if ($result) {
    $hasOther = strpos($result['Type'], "'other'") !== false;
    echo "3. 'other' in leave_type enum: " . ($hasOther ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";
} else {
    echo "3. leave_type column: ✗ NOT FOUND\n";
}

echo "\n✅ Verification complete!\n";
?>
