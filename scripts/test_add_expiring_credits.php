<?php
/**
 * Test Script: Add Credits That Will Expire Soon
 * Creates test credits with expiry dates in the near future to demonstrate the alert system
 */

require_once __DIR__ . '/../config/database.php';

try {
    echo "🧪 Adding test credits that will expire soon...\n\n";
    
    // Get a test employee
    $stmt = $pdo->query("SELECT id, name FROM employees WHERE role = 'employee' LIMIT 1");
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        echo "❌ No employee found for testing\n";
        exit(1);
    }
    
    echo "👤 Using test employee: {$employee['name']}\n\n";
    
    // Get admin ID for tracking
    $adminStmt = $pdo->query("SELECT id FROM employees WHERE role = 'admin' LIMIT 1");
    $adminId = $adminStmt->fetchColumn() ?: $employee['id'];
    
    // Test scenarios:
    // 1. Force Leave expiring in 10 days (critical)
    // 2. CTO expiring in 30 days (warning)
    // 3. SLP expiring in 5 days (critical)
    
    $testCredits = [
        [
            'leave_type' => 'mandatory',
            'amount' => 5,
            'days_from_now' => 10,
            'description' => 'Force Leave expiring in 10 days (CRITICAL)'
        ],
        [
            'leave_type' => 'cto',
            'amount' => 16,
            'days_from_now' => 30,
            'description' => 'CTO expiring in 30 days (WARNING)'
        ],
        [
            'leave_type' => 'special_privilege',
            'amount' => 3,
            'days_from_now' => 5,
            'description' => 'SLP expiring in 5 days (CRITICAL)'
        ]
    ];
    
    foreach ($testCredits as $credit) {
        $grantDate = date('Y-m-d', strtotime("-1 year +{$credit['days_from_now']} days"));
        $expiryDate = date('Y-m-d', strtotime("+{$credit['days_from_now']} days"));
        
        echo "📅 Adding {$credit['description']}\n";
        echo "   Grant Date: {$grantDate}\n";
        echo "   Expiry Date: {$expiryDate}\n";
        
        // Use the stored procedure to add credits with expiry tracking
        $stmt = $pdo->prepare("CALL add_leave_credit_with_expiry(?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $employee['id'],
            $credit['leave_type'],
            $credit['amount'],
            $grantDate,
            $adminId,
            "Test credit for alert system demonstration - {$credit['description']}"
        ]);
        
        echo "   ✅ Added {$credit['amount']} {$credit['leave_type']} credits\n\n";
    }
    
    echo "🎉 Test credits added successfully!\n\n";
    echo "📋 Summary:\n";
    echo "   • Employee: {$employee['name']}\n";
    echo "   • Force Leave: 5 days expiring in 10 days\n";
    echo "   • CTO: 16 hours expiring in 30 days\n";
    echo "   • SLP: 3 days expiring in 5 days\n\n";
    
    echo "🔔 Expected Alert Behavior:\n";
    echo "   • SLP (5 days): CRITICAL alert (≤15 days)\n";
    echo "   • Force Leave (10 days): CRITICAL alert (≤15 days)\n";
    echo "   • CTO (30 days): WARNING alert (≤45 days)\n\n";
    
    echo "💡 Next Steps:\n";
    echo "   1. Run: php scripts/test_expiry_alerts.php\n";
    echo "   2. Visit the Leave Alerts page in the admin panel\n";
    echo "   3. You should see the new alerts with specific expiry dates\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>