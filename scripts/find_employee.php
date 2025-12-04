<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query('SELECT id, name FROM employees LIMIT 1');
$emp = $stmt->fetch();
if ($emp) {
    echo "ID: {$emp['id']}, Name: {$emp['name']}\n";
} else {
    echo "No employees found\n";
}
?>
