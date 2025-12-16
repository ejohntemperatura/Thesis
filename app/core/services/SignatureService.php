<?php
class SignatureService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Upload e-signature for department heads and HR
     */
    public function uploadESignature($userId, $signatureData) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE employees 
                SET e_signature = ?, signature_uploaded_at = NOW() 
                WHERE id = ?
            ");
            return $stmt->execute([$signatureData, $userId]);
        } catch(Exception $e) {
            error_log("E-signature upload error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Apply e-signature to leave request (for dept head/HR)
     */
    public function applyESignature($leaveRequestId, $userId, $role) {
        try {
            // Get user's e-signature
            $stmt = $this->pdo->prepare("SELECT e_signature FROM employees WHERE id = ?");
            $stmt->execute([$userId]);
            $signature = $stmt->fetchColumn();
            
            if (!$signature) {
                throw new Exception("No e-signature found for user");
            }
            
            // Apply signature based on role
            $field = '';
            $typeField = '';
            $dateField = '';
            
            switch($role) {
                case 'department':
                    $field = 'dept_head_signature';
                    $typeField = 'dept_head_signature_type';
                    $dateField = 'dept_head_signed_at';
                    break;
                case 'admin': // HR
                    $field = 'hr_signature';
                    $typeField = 'hr_signature_type';
                    $dateField = 'hr_signed_at';
                    break;
            }
            
            if ($field) {
                $stmt = $this->pdo->prepare("
                    UPDATE leave_requests 
                    SET {$field} = ?, {$typeField} = 'e_signature', {$dateField} = NOW()
                    WHERE id = ?
                ");
                return $stmt->execute([$signature, $leaveRequestId]);
            }
            
            return false;
        } catch(Exception $e) {
            error_log("E-signature application error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process live signature (for directors)
     */
    public function processLiveSignature($leaveRequestId, $userId, $signatureData) {
        try {
            // Validate signature quality
            if (!$this->validateLiveSignature($signatureData)) {
                throw new Exception("Invalid signature quality");
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE leave_requests 
                SET director_signature = ?, 
                    director_signature_type = 'live_signature',
                    director_signed_at = NOW()
                WHERE id = ?
            ");
            
            return $stmt->execute([$signatureData, $leaveRequestId]);
        } catch(Exception $e) {
            error_log("Live signature error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate live signature quality
     */
    private function validateLiveSignature($signatureData) {
        // Basic validation - check if signature has content
        if (empty($signatureData)) return false;
        
        // Check if it's a valid base64 image
        if (strpos($signatureData, 'data:image/') !== 0) return false;
        
        // Additional validation can be added here
        return true;
    }
    
    /**
     * Get signature for display
     */
    public function getSignature($leaveRequestId, $signatureType) {
        try {
            $field = '';
            switch($signatureType) {
                case 'dept_head':
                    $field = 'dept_head_signature';
                    break;
                case 'hr':
                    $field = 'hr_signature';
                    break;
                case 'director':
                    $field = 'director_signature';
                    break;
            }
            
            if ($field) {
                $stmt = $this->pdo->prepare("SELECT {$field} FROM leave_requests WHERE id = ?");
                $stmt->execute([$leaveRequestId]);
                return $stmt->fetchColumn();
            }
            
            return null;
        } catch(Exception $e) {
            error_log("Get signature error: " . $e->getMessage());
            return null;
        }
    }
}
?>