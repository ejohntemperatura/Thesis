<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../../vendor/autoload.php';

class EmailService {
    private $mailer;
    private $config;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../../../config/email_config.php';
        $this->initializeMailer();
    }
    
    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp_host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['smtp_username'];
            $this->mailer->Password = $this->config['smtp_password'];
            $this->mailer->SMTPSecure = $this->config['smtp_encryption'];
            $this->mailer->Port = $this->config['smtp_port'];
            
            // Default settings
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addReplyTo($this->config['reply_to'], $this->config['from_name']);
            $this->mailer->isHTML(true);
            
        } catch (Exception $e) {
            error_log("Email initialization failed: " . $e->getMessage());
        }
    }
    
    /**
     * Send email verification to new user
     */
    public function sendVerificationEmail($userEmail, $userName, $verificationToken) {
        try {
            // Ensure clean recipient list per send
            $this->mailer->clearAllRecipients();

            $this->mailer->addAddress($userEmail, $userName);
            // Temporarily BCC sender for deliverability debugging
            if (!empty($this->config['smtp_username'])) {
                $this->mailer->addBCC($this->config['smtp_username']);
            }
            $this->mailer->Subject = 'Verify Your Email - ELMS System';
            
            // Build verification link using fixed BASE URL
            $appConfig = require __DIR__ . '/../../config/app_config.php';
            $baseUrl = rtrim($appConfig['base_url'] ?? '', '/');
            if ($baseUrl === '') {
                // Fallback to dynamic detection if not configured
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
                $projectRoot = preg_replace('#/(admin|user)$#', '', $scriptDir);
                $baseUrl = $scheme . '://' . $host . $projectRoot;
            }
            $verificationLink = $baseUrl . '/auth/views/verify_email.php?token=' . $verificationToken;
            
            $emailBody = $this->getVerificationEmailTemplate($userName, $verificationLink);
            $this->mailer->Body = $emailBody;
            $this->mailer->AltBody = $this->getPlainTextVerificationEmail($userName, $verificationLink);
            
            $this->mailer->send();
            // Cleanup recipients after send
            $this->mailer->clearAllRecipients();
            return true;
            
        } catch (Exception $e) {
            error_log("Verification email failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send welcome email after verification
     */
    public function sendWelcomeEmail($userEmail, $userName, $temporaryPassword) {
        try {
            // Ensure clean recipient list per send
            $this->mailer->clearAllRecipients();

            $this->mailer->addAddress($userEmail, $userName);
            // No BCC in dynamic mode
            $this->mailer->Subject = 'Welcome to ELMS System - Account Verified';
            
            $emailBody = $this->getWelcomeEmailTemplate($userName, $temporaryPassword);
            $this->mailer->Body = $emailBody;
            $this->mailer->AltBody = $this->getPlainTextWelcomeEmail($userName, $temporaryPassword);
            
            $this->mailer->send();
            // Cleanup recipients after send
            $this->mailer->clearAllRecipients();
            return true;
            
        } catch (Exception $e) {
            error_log("Welcome email failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send leave status notification (approved/rejected)
     */
    public function sendLeaveStatusNotification(
        string $userEmail,
        string $userName,
        string $status,
        string $startDate,
        string $endDate,
        ?string $leaveType = null,
        ?string $approverName = null,
        ?string $approverRole = null,
        ?int $approvedDays = null,
        ?string $originalLeaveType = null,
        ?string $rejectionReason = null,
        ?string $selectedDates = null,
        ?int $workingDaysApplied = null,
        ?string $otherPurpose = null
    ): bool {
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($userEmail, $userName);
            
            // Determine email content based on status
            $emailContent = $this->generateEmailContent($status, $userName, $startDate, $endDate, $leaveType, $approverName, $approverRole, $approvedDays, $originalLeaveType, $rejectionReason, $selectedDates, $workingDaysApplied, $otherPurpose);
            
            $this->mailer->Subject = $emailContent['subject'];
            $this->mailer->Body = $emailContent['html'];
            $this->mailer->AltBody = $emailContent['plain'];

            $this->mailer->send();
            $this->mailer->clearAllRecipients();
            // Log success
            $this->logDeliver("leave_status", $userEmail, $this->mailer->Subject, true);
            return true;
        } catch (Exception $e) {
            error_log('Leave status email failed: ' . $e->getMessage());
            $this->logDeliver("leave_status", $userEmail, 'Leave Request - ELMS', false, $e->getMessage());
            return false;
        }
    }
    
    private function generateEmailContent(
        string $status,
        string $userName,
        string $startDate,
        string $endDate,
        ?string $leaveType,
        ?string $approverName,
        ?string $approverRole,
        ?int $approvedDays = null,
        ?string $originalLeaveType = null,
        ?string $rejectionReason = null,
        ?string $selectedDates = null,
        ?int $workingDaysApplied = null,
        ?string $otherPurpose = null
    ): array {
        $statusColor = $this->getStatusColor($status);
        $statusText = $this->getStatusText($status);
        $approverInfo = $this->getApproverInfo($approverName, $approverRole);
        
        // Resolve Leave Type display name robustly so it's always shown
        require_once __DIR__ . '/../../../config/leave_types.php';
        $leaveTypes = getLeaveTypes();
        $displayType = trim((string)getLeaveTypeDisplayName($leaveType ?? '', $originalLeaveType ?? null, $leaveTypes, $otherPurpose));
        if ($displayType === '') {
            if (!empty($originalLeaveType)) {
                $displayType = trim((string)getLeaveTypeDisplayName($originalLeaveType, null, $leaveTypes, $otherPurpose));
            }
        }
        if ($displayType === '') {
            $displayType = 'Service Credits';
        }
        $typeText = "<p><strong>Leave Type:</strong> {$displayType}</p>";
        $approverText = $approverInfo ? "<p><strong>Approved by:</strong> {$approverInfo}</p>" : '';
        
        // Determine date display based on leave type
        $dateText = '';
        $dateTextPlain = '';
        if ($leaveType === 'other') {
            // For Terminal Leave/Monetization: Show working days only
            $days = $workingDaysApplied ?? $approvedDays ?? 0;
            $dateText = "<p><strong>Leave Credits to Convert:</strong> <span style='color: #10b981; font-weight: bold;'>{$days} working day(s)</span></p>";
            $dateText .= "<p style='color: #6b7280; font-size: 14px;'><em>This represents leave credits to be converted to cash, not calendar dates.</em></p>";
            $dateTextPlain = "Leave Credits to Convert: {$days} working day(s)\n(This represents leave credits to be converted to cash, not calendar dates.)";
        } else {
            // For Regular Leave: Show selected dates
            if (!empty($selectedDates)) {
                $datesArray = explode(',', $selectedDates);
                $dateText = "<p><strong>Selected Leave Days:</strong></p><div style='display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0;'>";
                $dateTextPlain = "Selected Leave Days:\n";
                foreach ($datesArray as $date) {
                    $formattedDate = date('M d, Y', strtotime($date));
                    $dateText .= "<span style='background: #dbeafe; color: #1e40af; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-block; margin: 4px;'>{$formattedDate}</span>";
                    $dateTextPlain .= "  • {$formattedDate}\n";
                }
                $dateText .= "</div>";
                $daysCount = count($datesArray);
                $dateText .= "<p><strong>Total Days:</strong> {$daysCount} day(s)</p>";
                $dateTextPlain .= "Total Days: {$daysCount} day(s)";
            } else {
                // Fallback to date range for older records
                $dateText = "<p><strong>Start Date:</strong> {$startDate}</p>";
                $dateText .= "<p><strong>End Date:</strong> {$endDate}</p>";
                if ($approvedDays) {
                    $dateText .= "<p><strong>Days Approved:</strong> {$approvedDays} day(s)</p>";
                }
                $dateTextPlain = "Start Date: {$startDate}\nEnd Date: {$endDate}";
                if ($approvedDays) {
                    $dateTextPlain .= "\nDays Approved: {$approvedDays} day(s)";
                }
            }
        }
        
        $subject = $this->getEmailSubject($status);
        
        $html = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$subject}</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f8fafc;
                }
                .container { 
                    max-width: 600px; 
                    margin: 20px auto; 
                    background: #ffffff;
                    border-radius: 12px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    overflow: hidden;
                }
                .header { 
                    background: linear-gradient(135deg, {$statusColor}, " . $this->darkenColor($statusColor, 20) . "); 
                    color: white; 
                    padding: 32px 24px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 28px; 
                    font-weight: 700; 
                }
                /* removed emoji icon */
                .content { 
                    padding: 32px 24px; 
                    background: #ffffff; 
                }
                .greeting {
                    font-size: 18px;
                    margin-bottom: 24px;
                    color: #1f2937;
                }
                .status-message {
                    background: " . $this->lightenColor($statusColor, 85) . ";
                    border-left: 4px solid {$statusColor};
                    padding: 20px;
                    margin: 24px 0;
                    border-radius: 0 8px 8px 0;
                }
                .status-message strong {
                    color: {$statusColor};
                    font-size: 18px;
                }
                .details { 
                    background: #f8fafc; 
                    padding: 24px; 
                    border-radius: 8px; 
                    border: 1px solid #e2e8f0; 
                    margin: 24px 0; 
                }
                .details h3 {
                    margin: 0 0 16px 0;
                    color: #1f2937;
                    font-size: 18px;
                    font-weight: 600;
                }
                .details p {
                    margin: 8px 0;
                    color: #4b5563;
                }
                .next-steps {
                    background: #fef3c7;
                    border: 1px solid #f59e0b;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 24px 0;
                }
                .next-steps h4 {
                    margin: 0 0 12px 0;
                    color: #92400e;
                    font-size: 16px;
                    font-weight: 600;
                }
                .next-steps p {
                    margin: 0;
                    color: #92400e;
                }
                .footer { 
                    text-align: center; 
                    color: #6b7280; 
                    font-size: 14px; 
                    padding: 24px;
                    background: #f8fafc;
                    border-top: 1px solid #e2e8f0;
                }
                .footer .company {
                    font-weight: 600;
                    color: #1f2937;
                    margin-bottom: 8px;
                }
                .footer .system {
                    color: #6b7280;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$statusText}</h1>
                </div>
                <div class='content'>
                    <div class='greeting'>
                        Dear <strong>{$userName}</strong>,
                    </div>
                    
                    <div class='status-message'>
                        Your leave request has been <strong>{$statusText}</strong>.
                        " . ($status === 'rejected' && $rejectionReason ? "<p style='margin-top: 12px; color: #991b1b;'><strong>Reason:</strong> {$rejectionReason}</p>" : '') . "
                    </div>
                    
                    <div class='details'>
                        <h3>Leave Request Details</h3>
                        {$typeText}
                        {$dateText}
                        {$approverText}
                        <p><strong>Date Processed:</strong> " . date('F j, Y \a\t g:i A') . "</p>
                    </div>
                    
                    <p style='margin-top: 32px; color: #4b5563;'>
                        If you have any questions about this decision, please contact your supervisor or the HR department.
                    </p>
                </div>
                <div class='footer'>
                    <div class='company'>Employee Leave Management System (ELMS)</div>
                    <div class='system'>This is an automated notification. Please do not reply to this email.</div>
                </div>
            </div>
        </body>
        </html>";

        $plain = "{$subject}\n\n"
            . "Dear {$userName},\n\n"
            . "Your leave request has been {$statusText}.\n"
            . ($status === 'rejected' && $rejectionReason ? "\nReason: {$rejectionReason}\n" : '') . "\n"
            . "LEAVE REQUEST DETAILS:\n"
            . ("Leave Type: " . $displayType . "\n")
            . $dateTextPlain . "\n"
            . ($approverInfo ? "Approved by: {$approverInfo}\n" : '')
            . "Date Processed: " . date('F j, Y \a\t g:i A') . "\n\n"
            . "If you have any questions, please contact your supervisor or HR.\n\n"
            . "---\n"
            . "Employee Leave Management System (ELMS)\n"
            . "This is an automated notification.";

        return [
            'subject' => $subject,
            'html' => $html,
            'plain' => $plain
        ];
    }
    
    private function getStatusColor(string $status): string {
        switch ($status) {
            case 'approved':
            case 'approved_with_pay':
                return '#10b981';
            case 'approved_without_pay':
                return '#f59e0b';
            case 'dept_approved':
                return '#3b82f6';
            case 'hr_approved':
                return '#2563eb';
            case 'rejected':
                return '#ef4444';
            case 'pending':
                return '#6b7280';
            default:
                return '#6b7280';
        }
    }
    
    private function getStatusText(string $status): string {
        switch ($status) {
            case 'approved':
                return 'Approved';
            case 'approved_with_pay':
                return 'Approved with Pay';
            case 'approved_without_pay':
                return 'Approved without Pay';
            case 'dept_approved':
                return 'Approved by Department Head';
            case 'hr_approved':
                return 'Approved by HR';
            case 'rejected':
                return 'Rejected';
            case 'pending':
                return 'Pending';
            default:
                return ucfirst($status);
        }
    }
    
    private function getStatusIcon(string $status): string {
        switch ($status) {
            case 'approved':
            case 'approved_with_pay':
                return '✅';
            case 'approved_without_pay':
                return '⚠️';
            case 'dept_approved':
                return '📋';
            case 'hr_approved':
                return '🧑‍💼';
            case 'rejected':
                return '❌';
            case 'pending':
                return '⏳';
            default:
                return '📄';
        }
    }
    
    private function getApproverInfo(?string $approverName, ?string $approverRole): string {
        if (!$approverName) return '';
        
        $roleText = '';
        switch ($approverRole) {
            case 'manager':
                $roleText = 'Department Head';
                break;
            case 'director':
                $roleText = 'Director';
                break;
            case 'admin':
                $roleText = 'Administrator';
                break;
            default:
                $roleText = ucfirst($approverRole ?? 'Supervisor');
        }
        
        return "{$approverName} ({$roleText})";
    }
    
    private function getEmailSubject(string $status): string {
        switch ($status) {
            case 'approved':
                return 'Leave Request Approved - ELMS';
            case 'dept_approved':
                return 'Leave Request Approved by Department Head - ELMS';
            case 'hr_approved':
                return 'Leave Request Approved by HR - ELMS';
            case 'rejected':
                return 'Leave Request - ELMS';
            case 'pending':
                return 'Leave Request Submitted - ELMS';
            default:
                return 'Leave Request - ELMS';
        }
    }
    
    private function getNextStepsContent(string $status): string {
        switch ($status) {
            case 'approved':
                return "
                <div class='next-steps'>
                    <h4>🎯 Next Steps</h4>
                    <p>Your leave request has been fully approved! You may proceed with your planned leave. Make sure to inform your team and complete any necessary handover tasks.</p>
                </div>";
            case 'approved_with_pay':
                return "
                <div class='next-steps'>
                    <h4>🎯 Next Steps</h4>
                    <p>Your leave request has been approved with pay! You may proceed with your planned leave and will receive your regular salary for the approved days. Make sure to inform your team and complete any necessary handover tasks.</p>
                </div>";
            case 'approved_without_pay':
                return "
                <div class='next-steps'>
                    <h4>⚠️ Next Steps</h4>
                    <p>Your leave request has been approved without pay. You may proceed with your planned leave, but note that you will not receive salary for the approved days. Make sure to inform your team and complete any necessary handover tasks.</p>
                </div>";
            case 'dept_approved':
                return "
                <div class='next-steps'>
                    <h4>📋 Next Steps</h4>
                    <p>Your leave request has been approved by your Department Head and is now being reviewed by HR. You will receive another notification after HR has reviewed your request.</p>
                </div>";
            case 'hr_approved':
                return "
                <div class='next-steps'>
                    <h4>🧑‍💼 Next Steps</h4>
                    <p>Your leave request has been approved by HR and will now move to the Director for final approval. You will receive another notification once the Director makes a decision.</p>
                </div>";
            case 'rejected':
                return "
                <div class='next-steps'>
                    <h4>💬 Next Steps</h4>
                    <p>If you have questions about this decision or would like to discuss alternatives, please contact your supervisor or HR department.</p>
                </div>";
            default:
                return "";
        }
    }
    
    private function getNextStepsPlain(string $status): string {
        switch ($status) {
            case 'approved':
                return "NEXT STEPS: Your leave request has been fully approved! You may proceed with your planned leave. Make sure to inform your team and complete any necessary handover tasks.";
            case 'approved_with_pay':
                return "NEXT STEPS: Your leave request has been approved with pay! You may proceed with your planned leave and will receive your regular salary for the approved days. Make sure to inform your team and complete any necessary handover tasks.";
            case 'approved_without_pay':
                return "NEXT STEPS: Your leave request has been approved without pay. You may proceed with your planned leave, but note that you will not receive salary for the approved days. Make sure to inform your team and complete any necessary handover tasks.";
            case 'dept_approved':
                return "NEXT STEPS: Your leave request has been approved by your Department Head and is now being reviewed by HR. You will receive another notification after HR review.";
            case 'hr_approved':
                return "NEXT STEPS: Your leave request has been approved by HR and will now move to the Director for final approval. You will receive another notification after the Director's decision.";
            case 'rejected':
                return "NEXT STEPS: If you have questions about this decision or would like to discuss alternatives, please contact your supervisor or HR department.";
            default:
                return "";
        }
    }
    
    private function darkenColor(string $hex, int $percent): string {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, $r - ($r * $percent / 100));
        $g = max(0, $g - ($g * $percent / 100));
        $b = max(0, $b - ($b * $percent / 100));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    private function lightenColor(string $hex, int $percent): string {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = min(255, $r + ((255 - $r) * $percent / 100));
        $g = min(255, $g + ((255 - $g) * $percent / 100));
        $b = min(255, $b + ((255 - $b) * $percent / 100));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    /**
     * Get leave type display name using the proper formatting
     */
    private function getLeaveTypeDisplayName($leave_type, $original_leave_type = null) {
        require_once __DIR__ . '/../../../config/leave_types.php';
        return getLeaveTypeDisplayName($leave_type, $original_leave_type);
    }

    private function logDeliver(string $type, string $to, string $subject, bool $ok, ?string $err = null): void {
        try {
            $dir = __DIR__ . '/../logs';
            if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
            $line = sprintf("[%s] [%s] [%s] to=%s subject=\"%s\"%s\n",
                date('Y-m-d H:i:s'), $type, $ok ? 'OK' : 'FAIL', $to, $subject, $err ? ' error=' . str_replace("\n", ' ', $err) : ''
            );
            @file_put_contents($dir . '/email.log', $line, FILE_APPEND);
        } catch (\Throwable $t) {
            // ignore
        }
    }
    
    /**
     * Get HTML verification email template
     */
    private function getVerificationEmailTemplate($userName, $verificationLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Verify Your Email</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .btn { display: inline-block; padding: 15px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Email Verification Required</h1>
                    <p>ELMS System - Employee Leave Management</p>
                </div>
                <div class='content'>
                    <h2>Hello {$userName}!</h2>
                    <p>Welcome to the ELMS System! Your account has been created by an administrator.</p>
                    <p>To complete your account setup and start using the system, please verify your email address by clicking the button below:</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$verificationLink}' class='btn'>✅ Verify Email Address</a>
                    </div>
                    
                    <p><strong>Important:</strong> This verification link will expire in 24 hours for security reasons.</p>
                    
                    <p>If the button above doesn't work, you can copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; background: #f0f0f0; padding: 10px; border-radius: 5px;'>{$verificationLink}</p>
                    
                    <p>If you didn't expect this email, please contact your system administrator.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from the ELMS System. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " ELMS System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get plain text verification email
     */
    private function getPlainTextVerificationEmail($userName, $verificationLink) {
        return "Hello {$userName}!

Welcome to the ELMS System! Your account has been created by an administrator.

To complete your account setup and start using the system, please verify your email address by visiting this link:

{$verificationLink}

Important: This verification link will expire in 24 hours for security reasons.

If you didn't expect this email, please contact your system administrator.

This is an automated message from the ELMS System. Please do not reply to this email.";
    }
    
    /**
     * Get HTML welcome email template
     */
    private function getWelcomeEmailTemplate($userName, $temporaryPassword) {
        $passwordSection = '';
        if (!empty($temporaryPassword)) {
            $passwordSection = "
                    <div class='password-box'>
                        <h3>🔑 Your Password</h3>
                        <p><strong>Password:</strong> {$temporaryPassword}</p>
                        <p><em>Please keep this password secure.</em></p>
                    </div>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Welcome to ELMS System</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .password-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Welcome to ELMS System!</h1>
                    <p>Your Email Has Been Verified Successfully</p>
                </div>
                <div class='content'>
                    <h2>Hello {$userName}!</h2>
                    <p>Congratulations! Your email address has been verified successfully.</p>
                    <p>Your account is now active and you can start using the ELMS System.</p>
                    {$passwordSection}
                    
                    <p><strong>Next Steps:</strong></p>
                    <ol>
                        <li>Visit the ELMS System login page</li>
                        <li>Use your email address and your password</li>
                        <li>You can update your password anytime from your profile</li>
                        <li>Complete your profile information</li>
                    </ol>
                    
                    <p>If you have any questions or need assistance, please contact your system administrator.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from the ELMS System. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " ELMS System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get plain text welcome email
     */
    private function getPlainTextWelcomeEmail($userName, $temporaryPassword) {
        $passwordText = '';
        if (!empty($temporaryPassword)) {
            $passwordText = "\nYour Password: {$temporaryPassword}\nPlease keep this password secure.";
        }

        return "Hello {$userName}!

Congratulations! Your email address has been verified successfully.

Your account is now active and you can start using the ELMS System.
{$passwordText}

Next Steps:
1. Visit the ELMS System login page
2. Use your email address and your password
3. You can update your password anytime from your profile
4. Complete your profile information

If you have any questions or need assistance, please contact your system administrator.

This is an automated message from the ELMS System. Please do not reply to this email.";
    }
    
    /**
     * Send custom email with HTML and plain text content
     */
    public function sendCustomEmail($userEmail, $userName, $subject, $htmlBody, $plainBody) {
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($userEmail, $userName);
            
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = $plainBody;

            $this->mailer->send();
            $this->mailer->clearAllRecipients();
            
            // Log success
            $this->logDeliver("custom_notification", $userEmail, $subject, true);
            return true;
            
        } catch (Exception $e) {
            error_log('Custom email failed: ' . $e->getMessage());
            $this->logDeliver("custom_notification", $userEmail, $subject, false, $e->getMessage());
            return false;
        }
    }

    /**
     * Test email configuration
     */
    public function testConnection() {
        try {
            // Ensure clean recipient list per send
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($this->config['smtp_username']);
            $this->mailer->Subject = 'Test Email - ELMS System';
            $this->mailer->Body = '<h1>Test Email</h1><p>If you receive this email, your email configuration is working correctly.</p>';
            $this->mailer->AltBody = 'Test Email - If you receive this email, your email configuration is working correctly.';
            
            $this->mailer->send();
            // Cleanup recipients after send
            $this->mailer->clearAllRecipients();
            return true;
            
        } catch (Exception $e) {
            error_log("Test email failed: " . $e->getMessage());
            return false;
        }
    }
}
?>
