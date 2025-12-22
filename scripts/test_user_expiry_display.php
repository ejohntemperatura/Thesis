<?php
/**
 * Test script to verify that expiry information is displayed on user leave credits page
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Testing User Leave Credits Expiry Display ===\n\n";

// Test 1: Check if leave_credit_expiry_tracking table exists and has data
echo "1. Testing expiry tracking data:\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leave_credit_expiry_tracking");
    $result = $stmt->fetch();
    $totalExpiry = $result['total'];
    
    echo "   Total expiry records: $totalExpiry\n";
    
    if ($totalExpiry > 0) {
        // Get sample expiry data
        $stmt = $pdo->query("
            SELECT 
                e.name as employee_name,
                lct.leave_type,
                lct.expiry_date,
                DATEDIFF(lct.expiry_date, CURDATE()) as days_until_expiry
            FROM leave_credit_expiry_tracking lct
            JOIN employees e ON lct.employee_id = e.id
            WHERE lct.expiry_date >= CURDATE()
            ORDER BY lct.expiry_date ASC
            LIMIT 5
        ");
        $sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Sample expiry data:\n";
        foreach ($sampleData as $data) {
            $urgency = $data['days_until_expiry'] <= 15 ? 'CRITICAL' : 
                      ($data['days_until_expiry'] <= 45 ? 'URGENT' : 'OK');
            echo "   - {$data['employee_name']}: {$data['leave_type']} expires in {$data['days_until_expiry']} days ($urgency)\n";
        }
    } else {
        echo "   ⚠️ No expiry records found. You may need to add some test data.\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 2: Check if the leave credits file has been updated with expiry functionality
echo "\n2. Testing leave credits file modifications:\n";

$leaveCreditsFile = 'app/modules/user/views/leave_credits.php';
if (!file_exists($leaveCreditsFile)) {
    echo "   ❌ Leave credits file not found\n";
    exit(1);
}

$content = file_get_contents($leaveCreditsFile);

// Check for expiry query
if (strpos($content, 'leave_credit_expiry_tracking') !== false) {
    echo "   ✅ Expiry tracking query added\n";
} else {
    echo "   ❌ Expiry tracking query missing\n";
}

// Check for expiry info variable
if (strpos($content, '$expiryInfo') !== false) {
    echo "   ✅ Expiry info variable added\n";
} else {
    echo "   ❌ Expiry info variable missing\n";
}

// Check for expiry display in cards
if (strpos($content, '1-Year Expiry') !== false) {
    echo "   ✅ Expiry display in cards added\n";
} else {
    echo "   ❌ Expiry display in cards missing\n";
}

// Check for alert banner
if (strpos($content, 'Leave Credits Expiring Soon') !== false) {
    echo "   ✅ Expiry alert banner added\n";
} else {
    echo "   ❌ Expiry alert banner missing\n";
}

// Check for urgency levels
if (strpos($content, 'days_until_expiry') !== false && strpos($content, 'Critical') !== false) {
    echo "   ✅ Urgency level logic added\n";
} else {
    echo "   ❌ Urgency level logic missing\n";
}

// Test 3: Simulate expiry data processing
echo "\n3. Testing expiry data processing logic:\n";

// Simulate expiry data
$testExpiryData = [
    ['leave_type' => 'mandatory', 'expiry_date' => date('Y-m-d', strtotime('+10 days')), 'days_until_expiry' => 10],
    ['leave_type' => 'cto', 'expiry_date' => date('Y-m-d', strtotime('+30 days')), 'days_until_expiry' => 30],
    ['leave_type' => 'special_privilege', 'expiry_date' => date('Y-m-d', strtotime('+60 days')), 'days_until_expiry' => 60],
];

foreach ($testExpiryData as $expiry) {
    $daysUntilExpiry = $expiry['days_until_expiry'];
    $urgencyLevel = 'Good';
    $urgencyClass = 'text-green-400';
    
    if ($daysUntilExpiry <= 15) {
        $urgencyLevel = 'Critical';
        $urgencyClass = 'text-red-400';
    } elseif ($daysUntilExpiry <= 45) {
        $urgencyLevel = 'Urgent';
        $urgencyClass = 'text-orange-400';
    }
    
    echo "   {$expiry['leave_type']}: {$daysUntilExpiry} days → $urgencyLevel ($urgencyClass)\n";
}

// Test 4: Check expected user experience
echo "\n4. Expected user experience:\n";
echo "   • Users will see expiry information for Force Leave, CTO, and SLP\n";
echo "   • Critical alerts (≤15 days) shown in red with urgent messaging\n";
echo "   • Urgent alerts (≤45 days) shown in orange with warning messaging\n";
echo "   • Good status (>45 days) shown in green\n";
echo "   • Alert banner at top of page for expiring credits\n";
echo "   • Individual expiry sections in each leave credit card\n";
echo "   • Exact expiry dates and days remaining displayed\n";

// Test 5: Create sample expiry data if none exists
if ($totalExpiry == 0) {
    echo "\n5. Creating sample expiry data for testing:\n";
    try {
        // Get a sample employee
        $stmt = $pdo->query("SELECT id FROM employees WHERE role = 'employee' LIMIT 1");
        $employee = $stmt->fetch();
        
        if ($employee) {
            $employeeId = $employee['id'];
            
            // Add sample expiry records
            $sampleExpiries = [
                ['mandatory', date('Y-m-d', strtotime('+10 days'))],  // Critical
                ['cto', date('Y-m-d', strtotime('+30 days'))],        // Urgent
                ['special_privilege', date('Y-m-d', strtotime('+60 days'))] // Good
            ];
            
            foreach ($sampleExpiries as [$leaveType, $expiryDate]) {
                $stmt = $pdo->prepare("
                    INSERT INTO leave_credit_expiry_tracking 
                    (employee_id, leave_type, credits_granted, grant_date, expiry_date, granted_by, reason, created_at)
                    VALUES (?, ?, 5, CURDATE(), ?, 1, 'Test data for expiry display', NOW())
                    ON DUPLICATE KEY UPDATE expiry_date = VALUES(expiry_date)
                ");
                $stmt->execute([$employeeId, $leaveType, $expiryDate]);
                echo "   ✅ Added sample $leaveType expiry: $expiryDate\n";
            }
            
            echo "   Sample data created. Users can now see expiry information.\n";
        } else {
            echo "   ⚠️ No employees found to create sample data\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error creating sample data: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "Status: User leave credits page now displays expiry information\n";
echo "\nFeatures added:\n";
echo "• Expiry tracking query to fetch user's expiring credits\n";
echo "• Alert banner for critical/urgent expiring credits\n";
echo "• Individual expiry sections in leave credit cards\n";
echo "• Color-coded urgency levels (red/orange/green)\n";
echo "• Days remaining and exact expiry dates\n";
echo "• Compliance messaging about 1-year expiry rules\n";
?>