<?php
/**
 * Migration Script: Set Expiry Dates for Existing Credits
 * Sets 1-year expiry dates for existing Force Leave, CTO, and SLP credits
 */

require_once __DIR__ . '/../config/database.php';

try {
    echo "🔄 Starting migration of existing leave credits to expiry tracking system...\n\n";
    
    // Get all employees with Force Leave, CTO, or SLP balances
    $stmt = $pdo->query("
        SELECT 
            id, name, 
            COALESCE(mandatory_leave_balance, 0) as force_leave,
            COALESCE(cto_balance, 0) as cto,
            COALESCE(special_leave_privilege_balance, 0) as slp,
            created_at
        FROM employees 
        WHERE role = 'employee' 
        AND (
            COALESCE(mandatory_leave_balance, 0) > 0 OR
            COALESCE(cto_balance, 0) > 0 OR
            COALESCE(special_leave_privilege_balance, 0) > 0
        )
        ORDER BY name
    ");
    
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalEmployees = count($employees);
    
    echo "📊 Found {$totalEmployees} employees with 1-year expiry leave credits\n\n";
    
    $migratedCount = 0;
    $defaultGrantDate = date('Y-01-01'); // Default to January 1st of current year
    
    // Get first admin user ID for tracking records
    $adminStmt = $pdo->query("SELECT id FROM employees WHERE role = 'admin' LIMIT 1");
    $adminId = $adminStmt->fetchColumn();
    if (!$adminId) {
        echo "⚠️  No admin user found, using employee IDs as fallback\n";
    }
    
    foreach ($employees as $employee) {
        echo "👤 Processing: {$employee['name']}\n";
        
        // For existing credits, we'll set a default grant date and calculate expiry
        // In a real scenario, you might want to use employee creation date or a specific date
        $grantDate = $employee['created_at'] ? date('Y-m-d', strtotime($employee['created_at'])) : $defaultGrantDate;
        $expiryDate = date('Y-m-d', strtotime($grantDate . ' +1 year'));
        
        // Migrate Force Leave
        if ($employee['force_leave'] > 0) {
            // Set expiry date in employees table
            $stmt = $pdo->prepare("UPDATE employees SET mandatory_leave_expiry_date = ? WHERE id = ?");
            $stmt->execute([$expiryDate, $employee['id']]);
            
            // Add tracking record
            
            $stmt = $pdo->prepare("
                INSERT INTO leave_credit_expiry_tracking 
                (employee_id, leave_type, credit_amount, date_added, expiry_date, added_by, reason)
                VALUES (?, 'mandatory', ?, ?, ?, ?, 'Migration of existing credits')
            ");
            $stmt->execute([$employee['id'], $employee['force_leave'], $grantDate, $expiryDate, $adminId ?: $employee['id']]);
            
            echo "   ✓ Force Leave: {$employee['force_leave']} days (expires: {$expiryDate})\n";
        }
        
        // Migrate CTO
        if ($employee['cto'] > 0) {
            // Set expiry date in employees table
            $stmt = $pdo->prepare("UPDATE employees SET cto_expiry_date = ? WHERE id = ?");
            $stmt->execute([$expiryDate, $employee['id']]);
            
            // Add tracking record
            $stmt = $pdo->prepare("
                INSERT INTO leave_credit_expiry_tracking 
                (employee_id, leave_type, credit_amount, date_added, expiry_date, added_by, reason)
                VALUES (?, 'cto', ?, ?, ?, ?, 'Migration of existing credits')
            ");
            $stmt->execute([$employee['id'], $employee['cto'], $grantDate, $expiryDate, $adminId ?: $employee['id']]);
            
            echo "   ✓ CTO: {$employee['cto']} hours (expires: {$expiryDate})\n";
        }
        
        // Migrate SLP
        if ($employee['slp'] > 0) {
            // Set expiry date in employees table
            $stmt = $pdo->prepare("UPDATE employees SET slp_expiry_date = ? WHERE id = ?");
            $stmt->execute([$expiryDate, $employee['id']]);
            
            // Add tracking record
            $stmt = $pdo->prepare("
                INSERT INTO leave_credit_expiry_tracking 
                (employee_id, leave_type, credit_amount, date_added, expiry_date, added_by, reason)
                VALUES (?, 'special_privilege', ?, ?, ?, ?, 'Migration of existing credits')
            ");
            $stmt->execute([$employee['id'], $employee['slp'], $grantDate, $expiryDate, $adminId ?: $employee['id']]);
            
            echo "   ✓ SLP: {$employee['slp']} days (expires: {$expiryDate})\n";
        }
        
        $migratedCount++;
        echo "\n";
    }
    
    echo "🎉 Migration completed successfully!\n";
    echo "📈 Statistics:\n";
    echo "   • Total employees processed: {$totalEmployees}\n";
    echo "   • Successfully migrated: {$migratedCount}\n";
    echo "   • Default grant date used: {$defaultGrantDate}\n";
    echo "   • Expiry date calculated: {$expiryDate}\n\n";
    
    echo "⚠️  IMPORTANT NOTES:\n";
    echo "   • All existing credits have been set to expire on {$expiryDate}\n";
    echo "   • This is based on a default grant date of {$defaultGrantDate}\n";
    echo "   • You may need to adjust individual expiry dates based on actual grant dates\n";
    echo "   • Future credits will use the exact grant date for expiry calculation\n\n";
    
    // Show summary of expiring credits
    $stmt = $pdo->query("
        SELECT 
            leave_type,
            COUNT(*) as employee_count,
            SUM(credit_amount) as total_credits,
            expiry_date
        FROM leave_credit_expiry_tracking 
        WHERE is_expired = 0 AND is_used = 0
        GROUP BY leave_type, expiry_date
        ORDER BY expiry_date, leave_type
    ");
    
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($summary)) {
        echo "📋 EXPIRY SUMMARY:\n";
        foreach ($summary as $row) {
            $leaveTypeName = ucwords(str_replace('_', ' ', $row['leave_type']));
            echo "   • {$leaveTypeName}: {$row['employee_count']} employees, {$row['total_credits']} total credits expire on {$row['expiry_date']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>