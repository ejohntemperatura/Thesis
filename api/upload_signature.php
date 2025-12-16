<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../app/core/services/SignatureService.php';

session_start();

// Debug logging
error_log("Upload signature - Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("Upload signature - Session role: " . ($_SESSION['role'] ?? 'not set'));

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated', 'debug' => [
        'session_id' => session_id(),
        'session_data' => $_SESSION
    ]]);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['userId'];
    $signatureData = $input['signatureData'];
    
    error_log("Upload signature - Target userId: " . $userId);
    error_log("Upload signature - Current session userId: " . $_SESSION['user_id']);
    error_log("Upload signature - Current session role: " . $_SESSION['role']);
    
    // Verify authorization:
    // - Users can upload their own signature
    // - Admins can upload signatures for department heads and HR users
    if ($userId != $_SESSION['user_id']) {
        error_log("Upload signature - Different user, checking admin permissions");
        
        // Check if current user is admin and can manage signatures
        if (!in_array($_SESSION['role'], ['admin', 'director'])) {
            error_log("Upload signature - Not admin/director, rejecting");
            throw new Exception('Unauthorized - You can only upload your own signature');
        }
        
        // Verify the target user is eligible for e-signature (department head or admin/HR)
        $stmt = $pdo->prepare("SELECT role FROM employees WHERE id = ?");
        $stmt->execute([$userId]);
        $targetUserRole = $stmt->fetchColumn();
        
        error_log("Upload signature - Target user role: " . $targetUserRole);
        
        if (!in_array($targetUserRole, ['manager', 'admin'])) {
            error_log("Upload signature - Target user not eligible for e-signature");
            throw new Exception('Unauthorized - E-signatures are only for Department Heads and HR');
        }
    }
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $signatureService = new SignatureService($pdo);
    
    $result = $signatureService->uploadESignature($userId, $signatureData);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Signature uploaded successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload signature']);
    }
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>