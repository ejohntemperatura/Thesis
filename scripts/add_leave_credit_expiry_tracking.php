<?php
/**
 * Add Leave Credit Expiry Tracking
 * Creates table to track when credits were added and their specific expiry dates
 */

require_once __DIR__ . '/../config/database.php';

try {
    // Create leave_credit_expiry_tracking table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leave_credit_expiry_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            leave_type ENUM('mandatory', 'cto', 'special_privilege', 'vacation', 'sick') NOT NULL,
            credit_amount DECIMAL(5,2) NOT NULL,
            date_added DATE NOT NULL,
            expiry_date DATE NOT NULL,
            added_by INT NOT NULL,
            reason TEXT,
            is_expired TINYINT(1) DEFAULT 0,
            is_used TINYINT(1) DEFAULT 0,
            used_amount DECIMAL(5,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (added_by) REFERENCES employees(id),
            INDEX idx_employee_leave_type (employee_id, leave_type),
            INDEX idx_expiry_date (expiry_date),
            INDEX idx_date_added (date_added)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ Created leave_credit_expiry_tracking table\n";
    
    // Add expiry tracking fields to employees table if they don't exist
    $columns_to_add = [
        'mandatory_leave_expiry_date' => 'DATE NULL',
        'cto_expiry_date' => 'DATE NULL', 
        'slp_expiry_date' => 'DATE NULL'
    ];
    
    foreach ($columns_to_add as $column => $definition) {
        try {
            $pdo->exec("ALTER TABLE employees ADD COLUMN $column $definition");
            echo "✓ Added $column column to employees table\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "- Column $column already exists\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Create function to calculate expiry date (1 year from date added)
    $pdo->exec("
        DROP FUNCTION IF EXISTS calculate_leave_expiry_date;
    ");
    
    $pdo->exec("
        CREATE FUNCTION calculate_leave_expiry_date(date_added DATE)
        RETURNS DATE
        READS SQL DATA
        DETERMINISTIC
        BEGIN
            RETURN DATE_ADD(date_added, INTERVAL 1 YEAR);
        END
    ");
    
    echo "✓ Created calculate_leave_expiry_date function\n";
    
    // Create trigger to automatically set expiry date when credits are added
    $pdo->exec("
        DROP TRIGGER IF EXISTS set_leave_expiry_date;
    ");
    
    $pdo->exec("
        CREATE TRIGGER set_leave_expiry_date
        BEFORE INSERT ON leave_credit_expiry_tracking
        FOR EACH ROW
        BEGIN
            SET NEW.expiry_date = calculate_leave_expiry_date(NEW.date_added);
        END
    ");
    
    echo "✓ Created set_leave_expiry_date trigger\n";
    
    // Create procedure to add leave credits with expiry tracking
    $pdo->exec("
        DROP PROCEDURE IF EXISTS add_leave_credit_with_expiry;
    ");
    
    $pdo->exec("
        CREATE PROCEDURE add_leave_credit_with_expiry(
            IN p_employee_id INT,
            IN p_leave_type VARCHAR(50),
            IN p_credit_amount DECIMAL(5,2),
            IN p_date_added DATE,
            IN p_added_by INT,
            IN p_reason TEXT
        )
        BEGIN
            DECLARE v_balance_field VARCHAR(50);
            DECLARE v_current_balance DECIMAL(5,2) DEFAULT 0;
            DECLARE v_new_balance DECIMAL(5,2);
            DECLARE v_expiry_date DATE;
            
            -- Calculate expiry date (1 year from date added)
            SET v_expiry_date = DATE_ADD(p_date_added, INTERVAL 1 YEAR);
            
            -- Determine balance field name
            CASE p_leave_type
                WHEN 'mandatory' THEN SET v_balance_field = 'mandatory_leave_balance';
                WHEN 'cto' THEN SET v_balance_field = 'cto_balance';
                WHEN 'special_privilege' THEN SET v_balance_field = 'special_leave_privilege_balance';
                WHEN 'vacation' THEN SET v_balance_field = 'vacation_leave_balance';
                WHEN 'sick' THEN SET v_balance_field = 'sick_leave_balance';
                ELSE SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid leave type';
            END CASE;
            
            -- Get current balance
            SET @sql = CONCAT('SELECT COALESCE(', v_balance_field, ', 0) INTO @current_balance FROM employees WHERE id = ', p_employee_id);
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
            SET v_current_balance = @current_balance;
            
            -- Calculate new balance
            SET v_new_balance = v_current_balance + p_credit_amount;
            
            -- Update employee balance
            SET @sql = CONCAT('UPDATE employees SET ', v_balance_field, ' = ', v_new_balance, ' WHERE id = ', p_employee_id);
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
            
            -- Update expiry date field for 1-year expiry types
            IF p_leave_type = 'mandatory' THEN
                UPDATE employees SET mandatory_leave_expiry_date = v_expiry_date WHERE id = p_employee_id;
            ELSEIF p_leave_type = 'cto' THEN
                UPDATE employees SET cto_expiry_date = v_expiry_date WHERE id = p_employee_id;
            ELSEIF p_leave_type = 'special_privilege' THEN
                UPDATE employees SET slp_expiry_date = v_expiry_date WHERE id = p_employee_id;
            END IF;
            
            -- Insert tracking record
            INSERT INTO leave_credit_expiry_tracking 
            (employee_id, leave_type, credit_amount, date_added, expiry_date, added_by, reason)
            VALUES 
            (p_employee_id, p_leave_type, p_credit_amount, p_date_added, v_expiry_date, p_added_by, p_reason);
            
        END
    ");
    
    echo "✓ Created add_leave_credit_with_expiry procedure\n";
    
    // Create view for easy expiry tracking
    $pdo->exec("
        CREATE OR REPLACE VIEW leave_expiry_summary AS
        SELECT 
            e.id as employee_id,
            e.name as employee_name,
            e.department,
            lct.leave_type,
            lct.credit_amount,
            lct.date_added,
            lct.expiry_date,
            DATEDIFF(lct.expiry_date, CURDATE()) as days_until_expiry,
            CASE 
                WHEN DATEDIFF(lct.expiry_date, CURDATE()) <= 15 THEN 'critical'
                WHEN DATEDIFF(lct.expiry_date, CURDATE()) <= 45 THEN 'warning'
                ELSE 'normal'
            END as alert_level,
            lct.is_expired,
            lct.is_used,
            (lct.credit_amount - lct.used_amount) as remaining_credits
        FROM employees e
        JOIN leave_credit_expiry_tracking lct ON e.id = lct.employee_id
        WHERE lct.is_expired = 0 AND lct.is_used = 0
        ORDER BY lct.expiry_date ASC, e.name ASC
    ");
    
    echo "✓ Created leave_expiry_summary view\n";
    
    echo "\n🎉 Leave credit expiry tracking system setup completed!\n";
    echo "\nNext steps:\n";
    echo "1. Update CTO management to use the new add_leave_credit_with_expiry procedure\n";
    echo "2. Modify EnhancedLeaveAlertService to use specific expiry dates\n";
    echo "3. Run migration script to set expiry dates for existing credits\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>