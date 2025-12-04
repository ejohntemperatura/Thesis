<?php
require_once __DIR__ . '/../config/database.php';

try {
    echo "Adding 'other' to leave_type enum...\n";
    
    // Get current enum values
    $stmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'leave_type'");
    $result = $stmt->fetch();
    
    if ($result && isset($result['Type'])) {
        $typeDef = $result['Type'];
        
        // Check if 'other' already exists
        if (strpos($typeDef, "'other'") !== false) {
            echo "⚠ 'other' already exists in enum\n";
        } else {
            // Extract enum values and add 'other'
            if (preg_match("/enum\((.*)\)/i", $typeDef, $matches)) {
                $values = $matches[1] . ",'other'";
                $sql = "ALTER TABLE leave_requests MODIFY leave_type ENUM($values) NOT NULL";
                $pdo->exec($sql);
                echo "✓ Successfully added 'other' to leave_type enum\n";
            } else {
                echo "✗ Could not parse enum definition\n";
            }
        }
    } else {
        echo "✗ Could not find leave_type column\n";
    }
    
    echo "\n✅ Complete!\n";
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
