<?php
/**
 * Test Script: Verify 1-Year Expiry Alert System
 * Tests the new date-based expiry system for Force Leave, CTO, and SLP
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/core/services/EnhancedLeaveAlertService.php';

try {
    echo "🧪 Testing 1-Year Expiry Alert System...\n\n";
    
    // Initialize the alert service
    $alertService = new EnhancedLeaveAlertService($pdo);
    
    // Generate alerts
    $alerts = $alertService->generateComprehensiveAlerts();
    
    echo "📊 Alert Generation Results:\n";
    echo "   • Total employees with alerts: " . count($alerts) . "\n\n";
    
    if (empty($alerts)) {
        echo "✅ No alerts generated - this means:\n";
        echo "   • No employees have 1-year expiry credits expiring soon, OR\n";
        echo "   • All expiry dates are more than 45 days away\n\n";
        
        // Show current expiry tracking data
        $stmt = $pdo->query("
            SELECT 
                e.name,
                lct.leave_type,
                lct.credit_amount,
                lct.expiry_date,
                DATEDIFF(lct.expiry_date, CURDATE()) as days_until_expiry
            FROM leave_credit_expiry_tracking lct
            JOIN employees e ON lct.employee_id = e.id
            WHERE lct.is_expired = 0 AND lct.is_used = 0
            ORDER BY lct.expiry_date ASC
        ");
        
        $trackingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($trackingData)) {
            echo "📋 Current Expiry Tracking Data:\n";
            foreach ($trackingData as $row) {
                $leaveType = ucwords(str_replace('_', ' ', $row['leave_type']));
                echo "   • {$row['name']}: {$row['credit_amount']} {$leaveType} expires on {$row['expiry_date']} ({$row['days_until_expiry']} days)\n";
            }
        }
    } else {
        echo "🚨 ALERTS GENERATED:\n\n";
        
        foreach ($alerts as $employeeId => $alertData) {
            $employee = $alertData['employee'];
            $employeeAlerts = $alertData['alerts'];
            $priority = $alertData['priority'];
            
            echo "👤 {$employee['name']} ({$employee['department']}) - Priority: " . strtoupper($priority) . "\n";
            
            foreach ($employeeAlerts as $alert) {
                $alertType = $alert['type'];
                $leaveName = $alert['leave_name'];
                $remaining = $alert['unused_days'] ?? $alert['remaining'];
                $daysUntil = $alert['days_until_forfeiture'];
                $expiryDate = $alert['expiry_date'] ?? 'N/A';
                $severity = strtoupper($alert['severity']);
                
                echo "   🔔 {$severity}: {$remaining} {$leaveName} expire in {$daysUntil} days (on {$expiryDate})\n";
            }
            echo "\n";
        }
    }
    
    // Test statistics
    $stats = $alertService->getAlertStatistics();
    echo "📈 Alert Statistics:\n";
    echo "   • Total employees with alerts: {$stats['total_employees_with_alerts']}\n";
    echo "   • Urgent alerts: {$stats['urgent_alerts']}\n";
    echo "   • Year-end risks: {$stats['year_end_risks']}\n\n";
    
    echo "✅ Test completed successfully!\n";
    echo "\n💡 Next Steps:\n";
    echo "   1. Visit the Leave Alerts page to see the new 1-year expiry system in action\n";
    echo "   2. Add new credits using CTO Management to test date-based expiry\n";
    echo "   3. Verify that expiry dates are calculated correctly (1 year from grant date)\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>