<?php
// Disable output buffering to prevent issues
ob_start();
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/app/core/services/RobustEmail.php';

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $error_message = "Invalid verification link. Please contact your administrator.";
    include 'verification_result.php';
    exit();
}

$token = trim($_GET['token']);

try {
    // First check if user exists with this token in email_verification_logs
    $stmt = $pdo->prepare("
        SELECT e.*, evl.verification_token as log_token, evl.expires_at 
        FROM email_verification_logs evl
        INNER JOIN employees e ON e.id = evl.employee_id
        WHERE evl.verification_token = ?
        ORDER BY evl.sent_at DESC
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not found in logs, try employees table directly
    if (!$user) {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE verification_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$user) {
        $error_message = "Invalid or expired verification token. Please contact your administrator.";
        include 'verification_result.php';
        exit();
    }
    
    // Check if already verified
    if ($user['email_verified'] == 1) {
        $success_message = "Your email has already been verified! You can now log in with your credentials.";
        $user_name = $user['name'];
        $user_email = $user['email'];
        include 'verification_result.php';
        exit();
    }
    
    // Check if token has expired (from the JOIN query or employees table)
    $expiresAt = $user['expires_at'] ?? $user['verification_expires'] ?? null;
    
    if ($expiresAt && strtotime($expiresAt) < time()) {
        $error_message = "Verification token has expired. Please contact your administrator for a new verification link.";
        include 'verification_result.php';
        exit();
    }
    
    // Check if password is provided
    if (isset($_POST['password']) && !empty($_POST['password'])) {
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate password
        if (strlen($password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
            include 'verification_result.php';
            exit();
        }
        
        if ($password !== $confirmPassword) {
            $error_message = "Passwords do not match.";
            include 'verification_result.php';
            exit();
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Update user status
        $stmt = $pdo->prepare("
            UPDATE employees 
            SET email_verified = 1, 
                password = ?, 
                account_status = 'active',
                verification_token = NULL,
                verification_expires = NULL
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $user['id']]);
        
        // Mark verification as used
        $stmt = $pdo->prepare("
            UPDATE email_verification_logs 
            SET verified_at = NOW(), status = 'verified' 
            WHERE employee_id = ? AND verification_token = ?
        ");
        $stmt->execute([$user['id'], $token]);
        
        // Commit transaction
        $pdo->commit();
        
        // Send welcome email (only show Employee ID for 'employee' role)
        $emailService = new RobustEmail($pdo);
        $userRole = $user['role'] ?? 'employee';
        $emailService->sendWelcomeEmail($user['email'], $user['name'], '', $user['id'], $userRole);
        
        $success_message = "Email verified successfully! Your account is now active. You can now log in with your email and password.";
        $user_name = $user['name'];
        $user_email = $user['email'];
        
        include 'verification_result.php';
    } else {
        // Show password creation form
        $user_name = $user['name'];
        $user_email = $user['email'];
        $verification_token = $token;
        include 'verification_result.php';
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Verification error: " . $e->getMessage() . " | Token: " . $token);
    $error_message = "An error occurred during verification: " . $e->getMessage();
    include 'verification_result.php';
}

// Flush output buffer
ob_end_flush();
?>