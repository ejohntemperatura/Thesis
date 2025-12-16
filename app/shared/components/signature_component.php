<?php
/**
 * Signature Component - Handles both e-signature and live signature
 */
class SignatureComponent {
    
    /**
     * Render e-signature upload form (for dept heads/HR)
     */
    public static function renderESignatureUpload($userId) {
        ?>
        <div class="e-signature-upload">
            <h4>Upload Your E-Signature</h4>
            <p class="text-muted">Upload your signature image once. It will be used for all your approvals.</p>
            
            <form id="eSignatureForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="signatureFile">Signature Image (PNG, JPG)</label>
                    <input type="file" class="form-control" id="signatureFile" 
                           accept="image/png,image/jpeg,image/jpg" required>
                </div>
                <button type="submit" class="btn btn-primary">Upload Signature</button>
            </form>
            
            <div id="signaturePreview" class="mt-3" style="display: none;">
                <h5>Current Signature:</h5>
                <img id="currentSignature" style="max-width: 200px; border: 1px solid #ddd; padding: 5px;">
            </div>
        </div>
        
        <script>
        document.getElementById('eSignatureForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('signatureFile');
            const file = fileInput.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const signatureData = e.target.result;
                    
                    // Upload signature
                    fetch('../../api/upload_signature.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            userId: <?php echo $userId; ?>,
                            signatureData: signatureData
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('currentSignature').src = signatureData;
                            document.getElementById('signaturePreview').style.display = 'block';
                            alert('Signature uploaded successfully!');
                        } else {
                            alert('Error uploading signature: ' + data.message);
                        }
                    });
                };
                reader.readAsDataURL(file);
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render e-signature confirmation (for approvals)
     */
    public static function renderESignatureConfirm($leaveRequestId, $userRole) {
        ?>
        <div class="e-signature-confirm">
            <h5>Confirm Approval with E-Signature</h5>
            <p>Click to apply your e-signature to this approval.</p>
            
            <button type="button" class="btn btn-success" onclick="applyESignature(<?php echo $leaveRequestId; ?>, '<?php echo $userRole; ?>')">
                <i class="fas fa-signature"></i> Apply E-Signature & Approve
            </button>
        </div>
        
        <script>
        function applyESignature(leaveRequestId, role) {
            if (confirm('Apply your e-signature to approve this leave request?')) {
                fetch('../../api/apply_e_signature.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        leaveRequestId: leaveRequestId,
                        role: role
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Leave request approved with e-signature!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
        </script>
        <?php
    }
    
    /**
     * Render live signature canvas (for directors)
     */
    public static function renderLiveSignature($leaveRequestId) {
        ?>
        <div class="live-signature">
            <h5>Director Live Signature Required</h5>
            <p>Please sign below to approve this leave request.</p>
            
            <div class="signature-pad-container">
                <canvas id="signaturePad" width="400" height="200" style="border: 2px solid #000;"></canvas>
                <div class="signature-controls mt-2">
                    <button type="button" class="btn btn-secondary" onclick="clearSignature()">Clear</button>
                    <button type="button" class="btn btn-success" onclick="submitLiveSignature(<?php echo $leaveRequestId; ?>)">
                        <i class="fas fa-check"></i> Sign & Approve
                    </button>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
        <script>
        const canvas = document.getElementById('signaturePad');
        const signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255, 255, 255, 0)',
            penColor: 'rgb(0, 0, 0)'
        });
        
        function clearSignature() {
            signaturePad.clear();
        }
        
        function submitLiveSignature(leaveRequestId) {
            if (signaturePad.isEmpty()) {
                alert('Please provide a signature first.');
                return;
            }
            
            const signatureData = signaturePad.toDataURL();
            
            fetch('../../api/process_live_signature.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    leaveRequestId: leaveRequestId,
                    signatureData: signatureData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Leave request approved with live signature!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        </script>
        <?php
    }
}
?>