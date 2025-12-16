<?php
// Add signature fields to database
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Add signature fields to employees table
    $sql1 = "ALTER TABLE employees 
             ADD COLUMN IF NOT EXISTS e_signature LONGTEXT COMMENT 'Pre-uploaded signature for dept heads/HR'";
    
    $sql2 = "ALTER TABLE employees 
             ADD COLUMN IF NOT EXISTS signature_uploaded_at DATETIME COMMENT 'When signature was uploaded'";
    
    // Add signature fields to leave_requests table
    $sql3 = "ALTER TABLE leave_requests 
             ADD COLUMN IF NOT EXISTS dept_head_signature LONGTEXT COMMENT 'Department head signature'";
    
    $sql4 = "ALTER TABLE leave_requests 
             ADD COLUMN IF NOT EXISTS dept_head_signature_type ENUM('e_signature', 'live_signature') DEFAULT 'e_signature'";
    
    $sql5 = "ALTER TABLE leave_requests 
             ADD COLUMN IF NOT EXISTS dept_head_signed_at DATETIME";
    
    $sql6 = "ALTER TABLE leave_requests 
             ADD COLUMN IF NOT EXISTS hr_signature LONGTEXT COMMENT 'HR signature'";
    
    $sql7 = "ALTER TABLE leave_requests 
             ADD COLUMN IF NOT EXISTS hr_signature_type ENUM('e_signature', 'live_signature') DEFAULT 'e_signature'";
    
    $sql8 = "ALTER TABLE leave_requests 
             ADD COLUMN IF NOT EXISTS hr_signed_at DATETIME";
    
    $sql9 = "ALTER TABLE leave_requests 
             ADD COLUMN IF NOT EXISTS director_signature LONGTEXT COMMENT 'Director live signature'";
    
    $sql10 = "ALTER TABLE leave_requests 
              ADD COLUMN IF NOT EXISTS director_signature_type ENUM('e_signature', 'live_signature') DEFAULT 'live_signature'";
    
    $sql11 = "ALTER TABLE leave_requests 
              ADD COLUMN IF NOT EXISTS director_signed_at DATETIME";
    
    $pdo->exec($sql1);
    $pdo->exec($sql2);
    $pdo->exec($sql3);
    $pdo->exec($sql4);
    $pdo->exec($sql5);
    $pdo->exec($sql6);
    $pdo->exec($sql7);
    $pdo->exec($sql8);
    $pdo->exec($sql9);
    $pdo->exec($sql10);
    $pdo->exec($sql11);
    
    echo "Signature fields added successfully!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>