<?php
require_once __DIR__ . '/../config/database.php';

$stmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'reason'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Column: " . $col['Field'] . "\n";
echo "Type: " . $col['Type'] . "\n";
echo "Null: " . $col['Null'] . "\n";
echo "Default: " . ($col['Default'] ?? 'NULL') . "\n";
?>
