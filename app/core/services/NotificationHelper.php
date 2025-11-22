<?php
/**
 * Notification Helper for Leave Management System
 * Handles finding appropriate approvers and sending notifications
 */

class NotificationHelper {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Notify HR when a new leave is submitted (informational)
     */
    public function notifyHRNewLeave($leaveRequestId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    lr.*,
                    e.name as employee_name,
                    e.email as employee_email,
                    e.department,
                    e.position
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.id
                WHERE lr.id = ?
            ");
            $stmt->execute([$leaveRequestId]);
            $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$leaveRequest) { return false; }

            $hr = $this->getHREmail();
            if (!$hr) { return false; }

            require_once __DIR__ . '/EmailService.php';
            $emailService = new EmailService();

            $subject = "New Leave Application Submitted - ELMS";
            $html = $this->generateHRNewLeaveHTML($leaveRequest, $hr);
            $plain = $this->generateHRNewLeavePlain($leaveRequest, $hr);

            return $emailService->sendCustomEmail(
                $hr['email'],
                $hr['name'],
                $subject,
                $html,
                $plain
            );
        } catch (Exception $e) {
            error_log("Error notifying HR (new leave): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notify HR after Department Head has approved (HR is next to act)
     */
    public function notifyHRDeptApproved($leaveRequestId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    lr.*,
                    e.name as employee_name,
                    e.email as employee_email,
                    e.department,
                    e.position,
                    dept_head.name as dept_head_name
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.id
                LEFT JOIN employees dept_head ON lr.dept_head_approved_by = dept_head.id
                WHERE lr.id = ?
            ");
            $stmt->execute([$leaveRequestId]);
            $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$leaveRequest) { return false; }

            if (($leaveRequest['dept_head_approval'] ?? 'pending') !== 'approved') {
                return true;
            }

            $hr = $this->getHREmail();
            if (!$hr) { return false; }

            require_once __DIR__ . '/EmailService.php';
            $emailService = new EmailService();

            $subject = "Leave Request Approved by Department Head - HR Review Needed - ELMS";
            $html = $this->generateHRAfterDeptApprovedHTML($leaveRequest, $hr);
            $plain = $this->generateHRAfterDeptApprovedPlain($leaveRequest, $hr);

            return $emailService->sendCustomEmail(
                $hr['email'],
                $hr['name'],
                $subject,
                $html,
                $plain
            );
        } catch (Exception $e) {
            error_log("Error notifying HR (after dept approval): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get HR/Admin email
     */
    public function getHREmail() {
        try {
            // Prefer explicit HR role if present, then fall back to admin
            $stmt = $this->pdo->prepare("
                SELECT email, name 
                FROM employees 
                WHERE role IN ('hr','admin') 
                  AND account_status = 'active'
                ORDER BY CASE role WHEN 'hr' THEN 0 ELSE 1 END, id ASC
                LIMIT 1
            ");
            $stmt->execute();
            $hr = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            // Validate email, else use configured fallback
            if ($hr && isset($hr['email']) && filter_var($hr['email'], FILTER_VALIDATE_EMAIL)) {
                // Ensure a name value
                if (empty($hr['name'])) { $hr['name'] = 'HR'; }
                return $hr;
            }

            // Fallback to provided HR email address
            return [
                'email' => '4dmin890@gmail.com',
                'name'  => 'HR'
            ];
        } catch (Exception $e) {
            error_log("Error getting HR email: " . $e->getMessage());
            // Safe fallback
            return [
                'email' => '4dmin890@gmail.com',
                'name'  => 'HR'
            ];
        }
    }
    
    /**
     * Get department head email for a given employee
     */
    public function getDepartmentHeadEmail($employeeId) {
        try {
            // Get employee's department
            $stmt = $this->pdo->prepare("SELECT department FROM employees WHERE id = ?");
            $stmt->execute([$employeeId]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$employee || !$employee['department']) {
                return null;
            }
            
            // Find department head (manager role) for the same department
            $stmt = $this->pdo->prepare("
                SELECT email, name 
                FROM employees 
                WHERE role = 'manager' 
                AND department = ? 
                AND account_status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$employee['department']]);
            $deptHead = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $deptHead ? $deptHead : null;
            
        } catch (Exception $e) {
            error_log("Error getting department head email: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get director email
     */
    public function getDirectorEmail() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT email, name 
                FROM employees 
                WHERE role = 'director' 
                AND account_status = 'active'
                LIMIT 1
            ");
            $stmt->execute();
            $director = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $director ? $director : null;
            
        } catch (Exception $e) {
            error_log("Error getting director email: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Send notification to department head when employee applies for leave
     */
    public function notifyDepartmentHeadNewLeave($leaveRequestId) {
        try {
            // Get leave request details with employee info
            $stmt = $this->pdo->prepare("
                SELECT 
                    lr.*,
                    e.name as employee_name,
                    e.email as employee_email,
                    e.department,
                    e.position
                FROM leave_requests lr 
                JOIN employees e ON lr.employee_id = e.id 
                WHERE lr.id = ?
            ");
            $stmt->execute([$leaveRequestId]);
            $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leaveRequest) {
                return false;
            }
            
            // Get department head info
            $deptHead = $this->getDepartmentHeadEmail($leaveRequest['employee_id']);
            if (!$deptHead) {
                error_log("No department head found for employee department: " . $leaveRequest['department']);
                return false;
            }
            
            // Send email notification
            require_once __DIR__ . '/EmailService.php';
            $emailService = new EmailService();
            
            $subject = "New Leave Request - Action Required - ELMS";
            $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
            $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
            
            $html = $this->generateDepartmentHeadNotificationHTML($leaveRequest, $deptHead);
            $plain = $this->generateDepartmentHeadNotificationPlain($leaveRequest, $deptHead);
            
            return $emailService->sendCustomEmail(
                $deptHead['email'],
                $deptHead['name'],
                $subject,
                $html,
                $plain
            );
            
        } catch (Exception $e) {
            error_log("Error notifying department head: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification to director when department head takes action
     */
    public function notifyDirectorDepartmentAction($leaveRequestId, $action) {
        try {
            // Get leave request details with employee and department head info
            $stmt = $this->pdo->prepare("
                SELECT 
                    lr.*,
                    e.name as employee_name,
                    e.email as employee_email,
                    e.department,
                    e.position,
                    dept_head.name as dept_head_name,
                    hr_user.name as hr_name
                FROM leave_requests lr 
                JOIN employees e ON lr.employee_id = e.id 
                LEFT JOIN employees dept_head ON lr.dept_head_approved_by = dept_head.id
                LEFT JOIN employees hr_user ON lr.admin_approved_by = hr_user.id
                WHERE lr.id = ?
            ");
            $stmt->execute([$leaveRequestId]);
            $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leaveRequest) {
                return false;
            }
            
            // Only notify director if department head approved (not rejected)
            if ($action !== 'approved') {
                return true; // No need to notify director for rejections
            }

            // Enforce sequential gating: require HR/Admin approval before notifying Director
            if (!isset($leaveRequest['admin_approval']) || $leaveRequest['admin_approval'] !== 'approved') {
                // Do not notify Director until HR has approved
                return true;
            }
            
            // Get director info
            $director = $this->getDirectorEmail();
            if (!$director) {
                error_log("No director found in system");
                return false;
            }
            
            // Send email notification
            require_once __DIR__ . '/EmailService.php';
            $emailService = new EmailService();
            
            $subject = "Leave Request Approved by HR - Action Required - ELMS";
            $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
            $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
            
            $html = $this->generateDirectorNotificationHTML($leaveRequest, $director);
            $plain = $this->generateDirectorNotificationPlain($leaveRequest, $director);
            
            return $emailService->sendCustomEmail(
                $director['email'],
                $director['name'],
                $subject,
                $html,
                $plain
            );
            
        } catch (Exception $e) {
            error_log("Error notifying director: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate HTML email for department head notification
     */
    private function generateDepartmentHeadNotificationHTML($leaveRequest, $deptHead) {
        $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
        // Use days_requested from database (excludes weekends)
        $days = $leaveRequest['days_requested'] ?? 0;
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8fafc; }
                .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .action-btn { 
                    display: inline-block; 
                    background: #2563eb; 
                    color: white !important; 
                    padding: 12px 24px; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    margin: 10px 5px;
                    font-weight: bold;
                    font-size: 16px;
                    border: none;
                    cursor: pointer;
                }
                .action-btn:hover {
                    background: #1d4ed8;
                    color: white !important;
                }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>New Leave Request - Action Required</h1>
                    <p>ELMS - Employee Leave Management System</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$deptHead['name']},</p>
                    
                    <p>A new leave request has been submitted and requires your approval:</p>
                    
                    <div class='details'>
                        <h3>Leave Request Details:</h3>
                        <p><strong>Employee:</strong> {$leaveRequest['employee_name']}</p>
                        <p><strong>Position:</strong> {$leaveRequest['position']}</p>
                        <p><strong>Department:</strong> {$leaveRequest['department']}</p>
                        <p><strong>Leave Type:</strong> " . $this->getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null) . "</p>
                        <p><strong>Start Date:</strong> {$startDate}</p>
                        <p><strong>End Date:</strong> {$endDate}</p>
                        <p><strong>Duration:</strong> {$days} day(s)</p>
                        <p><strong>Reason:</strong> {$leaveRequest['reason']}</p>
                    </div>
                    
                    <p><strong>Action Required:</strong> Please review and approve or reject this leave request.</p>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='" . $this->getBaseUrl() . "/app/modules/department/views/dashboard.php' class='action-btn' style='color: white !important; text-decoration: none;'>
                            📋 Review Leave Request
                        </a>
                    </div>
                    
                    <p>Please log in to the ELMS system to take action on this request.</p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated notification from the ELMS system.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generate plain text email for department head notification
     */
    private function generateDepartmentHeadNotificationPlain($leaveRequest, $deptHead) {
        $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
        // Use days_requested from database (excludes weekends)
        $days = $leaveRequest['days_requested'] ?? 0;
        
        return "
NEW LEAVE REQUEST - ACTION REQUIRED
ELMS - Employee Leave Management System

Dear {$deptHead['name']},

A new leave request has been submitted and requires your approval:

LEAVE REQUEST DETAILS:
Employee: {$leaveRequest['employee_name']}
Position: {$leaveRequest['position']}
Department: {$leaveRequest['department']}
Leave Type: " . $this->getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null) . "
Start Date: {$startDate}
End Date: {$endDate}
Duration: {$days} day(s)
Reason: {$leaveRequest['reason']}

ACTION REQUIRED: Please review and approve or reject this leave request.

To take action, please log in to the ELMS system at: " . $this->getBaseUrl() . "/app/modules/department/views/dashboard.php

This is an automated notification from the ELMS system.
Please do not reply to this email.
        ";
    }
    
    /**
     * Generate HTML for HR notification on new leave submission
     */
    private function generateHRNewLeaveHTML($leaveRequest, $hr) {
        $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
        $days = $leaveRequest['days_requested'] ?? 0;
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0ea5e9; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8fafc; }
                .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .action-btn { display: inline-block; background: #0ea5e9; color: white !important; padding: 12px 24px; text-decoration: none; border-radius: 8px; margin: 10px 5px; font-weight: bold; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>New Leave Application Submitted</h1>
                    <p>ELMS - Employee Leave Management System</p>
                </div>
                <div class='content'>
                    <p>Dear {$hr['name']},</p>
                    <p>A new leave application has been submitted. This is for your visibility and upcoming review after the Department Head's action.</p>
                    <div class='details'>
                        <h3>Leave Request Details:</h3>
                        <p><strong>Employee:</strong> {$leaveRequest['employee_name']}</p>
                        <p><strong>Position:</strong> {$leaveRequest['position']}</p>
                        <p><strong>Department:</strong> {$leaveRequest['department']}</p>
                        <p><strong>Leave Type:</strong> " . $this->getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null) . "</p>
                        <p><strong>Start Date:</strong> {$startDate}</p>
                        <p><strong>End Date:</strong> {$endDate}</p>
                        <p><strong>Duration:</strong> {$days} day(s)</p>
                        <p><strong>Reason:</strong> {$leaveRequest['reason']}</p>
                    </div>
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='" . $this->getBaseUrl() . "/app/modules/admin/views/leave_management.php' class='action-btn' style='color: white !important; text-decoration: none;'>
                            📋 Open HR Leave Queue
                        </a>
                    </div>
                </div>
                <div class='footer'>
                    <p>This is an automated notification from the ELMS system.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Generate plain text for HR notification on new leave submission
     */
    private function generateHRNewLeavePlain($leaveRequest, $hr) {
        $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
        $days = $leaveRequest['days_requested'] ?? 0;
        return "
NEW LEAVE APPLICATION SUBMITTED
ELMS - Employee Leave Management System

Dear {$hr['name']},

A new leave application has been submitted. This is for your visibility and upcoming review after the Department Head's action.

LEAVE REQUEST DETAILS:
Employee: {$leaveRequest['employee_name']}
Position: {$leaveRequest['position']}
Department: {$leaveRequest['department']}
Leave Type: " . $this->getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null) . "
Start Date: {$startDate}
End Date: {$endDate}
Duration: {$days} day(s)
Reason: {$leaveRequest['reason']}

To view the HR queue, visit: " . $this->getBaseUrl() . "/app/modules/admin/views/leave_management.php

This is an automated notification from the ELMS system.
Please do not reply to this email.
        ";
    }

    /**
     * Generate HTML for HR notification after dept head approval
     */
    private function generateHRAfterDeptApprovedHTML($leaveRequest, $hr) {
        $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
        $days = $leaveRequest['approved_days'] ?? $leaveRequest['days_requested'] ?? 0;
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f59e0b; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8fafc; }
                .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .action-btn { display: inline-block; background: #f59e0b; color: white !important; padding: 12px 24px; text-decoration: none; border-radius: 8px; margin: 10px 5px; font-weight: bold; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Department Head Approved - HR Review Needed</h1>
                    <p>ELMS - Employee Leave Management System</p>
                </div>
                <div class='content'>
                    <p>Dear {$hr['name']},</p>
                    <p>The following leave request has been approved by the Department Head and now requires your review.</p>
                    <div class='details'>
                        <h3>Leave Request Details:</h3>
                        <p><strong>Employee:</strong> {$leaveRequest['employee_name']}</p>
                        <p><strong>Position:</strong> {$leaveRequest['position']}</p>
                        <p><strong>Department:</strong> {$leaveRequest['department']}</p>
                        <p><strong>Department Head:</strong> {$leaveRequest['dept_head_name']}</p>
                        <p><strong>Leave Type:</strong> " . $this->getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null) . "</p>
                        <p><strong>Start Date:</strong> {$startDate}</p>
                        <p><strong>End Date:</strong> {$endDate}</p>
                        <p><strong>Duration:</strong> {$days} day(s)</p>
                        <p><strong>Reason:</strong> {$leaveRequest['reason']}</p>
                    </div>
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='" . $this->getBaseUrl() . "/app/modules/admin/views/leave_management.php' class='action-btn' style='color: white !important; text-decoration: none;'>
                            📋 Review as HR
                        </a>
                    </div>
                </div>
                <div class='footer'>
                    <p>This is an automated notification from the ELMS system.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Generate plain text for HR notification after dept head approval
     */
    private function generateHRAfterDeptApprovedPlain($leaveRequest, $hr) {
        $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
        $days = $leaveRequest['approved_days'] ?? $leaveRequest['days_requested'] ?? 0;
        return "
DEPARTMENT HEAD APPROVED - HR REVIEW NEEDED
ELMS - Employee Leave Management System

Dear {$hr['name']},

The following leave request has been approved by the Department Head and now requires your review.

LEAVE REQUEST DETAILS:
Employee: {$leaveRequest['employee_name']}
Position: {$leaveRequest['position']}
Department: {$leaveRequest['department']}
Department Head: {$leaveRequest['dept_head_name']}
Leave Type: " . $this->getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null) . "
Start Date: {$startDate}
End Date: {$endDate}
Duration: {$days} day(s)
Reason: {$leaveRequest['reason']}

To review, visit: " . $this->getBaseUrl() . "/app/modules/admin/views/leave_management.php

This is an automated notification from the ELMS system.
Please do not reply to this email.
        ";
    }
    
    /**
     * Generate HTML email for director notification
     */
    private function generateDirectorNotificationHTML($leaveRequest, $director) {
        $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
        // Use approved_days if available (from dept head), otherwise days_requested (excludes weekends)
        $days = $leaveRequest['approved_days'] ?? $leaveRequest['days_requested'] ?? 0;
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #059669; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8fafc; }
                .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .action-btn { 
                    display: inline-block; 
                    background: #059669; 
                    color: white; 
                    padding: 10px 20px; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    margin: 10px 5px;
                }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .approved { color: #059669; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Leave Request Approved by HR</h1>
                    <p>ELMS - Employee Leave Management System</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$director['name']},</p>
                    
                    <p>A leave request has been <span class='approved'>approved by HR</span> and now requires your final approval:</p>
                    
                    <div class='details'>
                        <h3>Leave Request Details:</h3>
                        <p><strong>Employee:</strong> {$leaveRequest['employee_name']}</p>
                        <p><strong>Position:</strong> {$leaveRequest['position']}</p>
                        <p><strong>HR Approved By:</strong> {$leaveRequest['hr_name']}</p>
                        <p><strong>Department Head:</strong> {$leaveRequest['dept_head_name']}</p>
                        <p><strong>HR Reviewed By:</strong> {$leaveRequest['hr_name']}</p>
                        <p><strong>Leave Type:</strong> " . $this->getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null) . "</p>
                        <p><strong>Start Date:</strong> {$startDate}</p>
                        <p><strong>End Date:</strong> {$endDate}</p>
                        <p><strong>Duration:</strong> {$days} day(s)</p>
                        <p><strong>Reason:</strong> {$leaveRequest['reason']}</p>
                    </div>
                    
                    <p><strong>Action Required:</strong> Please review and provide final approval for this leave request.</p>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='" . $this->getBaseUrl() . "/app/modules/director/views/dashboard.php' class='action-btn' style='color: white !important; text-decoration: none;'>
                            📋 Review Leave Request
                        </a>
                    </div>
                    
                    <p>Please log in to the ELMS system to take final action on this request.</p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated notification from the ELMS system.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generate plain text email for director notification
     */
    private function generateDirectorNotificationPlain($leaveRequest, $director) {
        $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
        // Use approved_days if available (from dept head), otherwise days_requested (excludes weekends)
        $days = $leaveRequest['approved_days'] ?? $leaveRequest['days_requested'] ?? 0;
        
        return "
LEAVE REQUEST APPROVED BY HR - ACTION REQUIRED
ELMS - Employee Leave Management System

Dear {$director['name']},

A leave request has been APPROVED by HR and now requires your final approval:

LEAVE REQUEST DETAILS:
Employee: {$leaveRequest['employee_name']}
Position: {$leaveRequest['position']}
Department: {$leaveRequest['department']}
Department Head: {$leaveRequest['dept_head_name']}
HR Reviewed By: {$leaveRequest['hr_name']}
Leave Type: " . $this->getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null) . "
Start Date: {$startDate}
End Date: {$endDate}
Duration: {$days} day(s)
Reason: {$leaveRequest['reason']}

ACTION REQUIRED: Please review and provide final approval for this leave request.

To take action, please log in to the ELMS system at: " . $this->getBaseUrl() . "/app/modules/director/views/dashboard.php

This is an automated notification from the ELMS system.
Please do not reply to this email.
        ";
    }
    
    /**
     * Get leave type display name using the proper formatting
     */
    private function getLeaveTypeDisplayName($leave_type, $original_leave_type = null) {
        require_once __DIR__ . '/../../../config/leave_types.php';
        return getLeaveTypeDisplayName($leave_type, $original_leave_type);
    }
    
    /**
     * Get base URL for the application
     */
    private function getBaseUrl() {
        // Prefer configured base URL if available
        try {
            $appConfig = require __DIR__ . '/../../config/app_config.php';
            $baseUrl = rtrim($appConfig['base_url'] ?? '', '/');
            if (!empty($baseUrl)) {
                return $baseUrl;
            }
        } catch (\Throwable $t) {
            // ignore and fallback
        }

        // Fallback to dynamic detection
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
        return $scheme . '://' . $host . $scriptDir;
    }
}
?>
