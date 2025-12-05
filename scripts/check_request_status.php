<?php
require_once __DIR__ . '/../config/database.php';

$request_id = 317; // Change this to the request ID you want to check

$stmt = $pdo->prepare("
    SELECT 
        id,
        employee_id,
        dept_head_approval,
        admin_approval,
        director_approval,
        status,
        created_at
    FROM leave_requests
    WHERE id = ?
");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if ($request) {
    echo "Request ID: {$request['id']}\n";
    echo "Created: {$request['created_at']}\n";
    echo "Dept Head: " . ($request['dept_head_approval'] ?? 'NULL') . "\n";
    echo "HR/Admin: " . ($request['admin_approval'] ?? 'NULL') . "\n";
    echo "Director: " . ($request['director_approval'] ?? 'NULL') . "\n";
    echo "Status: {$request['status']}\n";
} else {
    echo "Request not found\n";
}
?>
