<?php
header('Content-Type: application/json');
require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'director'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

try {
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('User ID is required');
    }
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get user's e-signature
    $stmt = $pdo->prepare("SELECT e_signature, signature_uploaded_at FROM employees WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['e_signature']) {
        echo json_encode([
            'success' => true, 
            'signature' => $result['e_signature'],
            'uploaded_at' => $result['signature_uploaded_at']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No signature found']);
    }
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>