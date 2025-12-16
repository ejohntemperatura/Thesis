<?php
header('Content-Type: application/json');
require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'director'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['userId'] ?? null;
    
    if (!$userId) {
        throw new Exception('User ID is required');
    }
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Remove user's e-signature
    $stmt = $pdo->prepare("UPDATE employees SET e_signature = NULL, signature_uploaded_at = NULL WHERE id = ?");
    $result = $stmt->execute([$userId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Signature removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove signature']);
    }
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>