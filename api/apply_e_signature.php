<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../app/core/services/SignatureService.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $leaveRequestId = $input['leaveRequestId'];
    $role = $input['role'];
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $signatureService = new SignatureService($pdo);
    
    $result = $signatureService->applyESignature($leaveRequestId, $_SESSION['user_id'], $role);
    
    if ($result) {
        // Update leave request status
        $statusField = '';
        switch($role) {
            case 'department':
                $statusField = 'department_status = "approved"';
                break;
            case 'admin':
                $statusField = 'hr_status = "approved"';
                break;
        }
        
        if ($statusField) {
            $stmt = $pdo->prepare("UPDATE leave_requests SET {$statusField} WHERE id = ?");
            $stmt->execute([$leaveRequestId]);
        }
        
        echo json_encode(['success' => true, 'message' => 'E-signature applied successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to apply e-signature']);
    }
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>