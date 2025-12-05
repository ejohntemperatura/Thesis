<?php
/**
 * Fix Cascading Rejection Status
 * 
 * This script fixes leave requests that were rejected under the old logic
 * where any single rejection set status='rejected'.
 * 
 * Under the new cascading rejection logic:
 * - status should only be 'rejected' if ALL THREE levels rejected
 * - Otherwise status should remain 'pending' to allow cascading review
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Fixing Cascading Rejection Status ===\n\n";

try {
    $pdo->beginTransaction();
    
    // Find requests where status='rejected' but not all three levels rejected
    $stmt = $pdo->query("
        SELECT 
            id,
            employee_id,
            dept_head_approval,
            admin_approval,
            director_approval,
            status
        FROM leave_requests
        WHERE status = 'rejected'
        AND NOT (
            COALESCE(dept_head_approval, 'pending') = 'rejected' 
            AND COALESCE(admin_approval, 'pending') = 'rejected' 
            AND COALESCE(director_approval, 'pending') = 'rejected'
        )
    ");
    
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($requests) . " requests to fix\n\n";
    
    if (count($requests) > 0) {
        foreach ($requests as $request) {
            echo "Request ID: {$request['id']}\n";
            echo "  Dept Head: " . ($request['dept_head_approval'] ?? 'pending') . "\n";
            echo "  HR/Admin: " . ($request['admin_approval'] ?? 'pending') . "\n";
            echo "  Director: " . ($request['director_approval'] ?? 'pending') . "\n";
            echo "  Current Status: {$request['status']}\n";
            
            // Update status to 'pending' so it can be reviewed by remaining approvers
            $updateStmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = 'pending'
                WHERE id = ?
            ");
            $updateStmt->execute([$request['id']]);
            
            echo "  ✓ Updated status to 'pending'\n\n";
        }
        
        $pdo->commit();
        echo "\n✓ Successfully fixed " . count($requests) . " requests\n";
        echo "These requests can now be reviewed by the remaining approvers.\n";
    } else {
        $pdo->commit();
        echo "No requests need fixing. All rejection statuses are correct.\n";
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Done ===\n";
?>
