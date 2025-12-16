<?php
/**
 * Enhanced Leave Alert Service - Focus on 1-Year Expiry Rules
 * Specialized for Force Leave, CTO, and SLP with mandatory 1-year expiry
 * Based on Civil Service Commission rules and Philippine labor standards
 */

class EnhancedLeaveAlertService {
    private $pdo;
    
    // 1-Year Expiry Thresholds
    const EXPIRY_CRITICAL_DAYS = 15;  // Critical warning - 15 days before expiry
    const EXPIRY_WARNING_DAYS = 45;   // Warning - 45 days before expiry
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate alerts focused on 1-year expiry leave types only - Show ALL employees
     */
    public function generateComprehensiveAlerts() {
        $currentYear = date('Y');
        $currentDate = new DateTime();
        $yearEnd = new DateTime($currentYear . '-12-31');
        $daysRemaining = $currentDate->diff($yearEnd)->days;
        
        $alerts = [];
        
        // Get ALL employees with Force Leave, CTO, and SLP data
        $employees = $this->getAllEmployeesWithOneYearExpiryData($currentYear);
        
        foreach ($employees as $employee) {
            $employeeAlerts = $this->analyzeOneYearExpiryLeaves($employee, $currentYear, $daysRemaining);
            
            // Include ALL employees, even those without alerts
            $alerts[$employee['id']] = [
                'employee' => $employee,
                'alerts' => $employeeAlerts, // Can be empty array
                'priority' => !empty($employeeAlerts) ? $this->calculateAlertPriority($employeeAlerts, $daysRemaining) : 'none'
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Analyze only Force Leave, CTO, and SLP (1-year expiry types) using specific expiry dates
     */
    private function analyzeOneYearExpiryLeaves($employee, $year, $daysRemaining) {
        $alerts = [];
        $currentDate = new DateTime();
        
        // Force Leave (Mandatory Leave) - Check specific expiry date
        $forceLeaveBalance = $employee['mandatory_leave_balance'] ?? 0;
        $forceLeaveUsed = $employee['mandatory_used'] ?? 0;
        $forceLeaveRemaining = max(0, $forceLeaveBalance - $forceLeaveUsed);
        $forceLeaveExpiryDate = $employee['mandatory_leave_expiry_date'] ?? null;
        
        if ($forceLeaveRemaining > 0 && $forceLeaveExpiryDate) {
            $expiryDate = new DateTime($forceLeaveExpiryDate);
            $daysUntilExpiry = $currentDate->diff($expiryDate)->days;
            $isExpiringSoon = $expiryDate > $currentDate; // Only alert if not yet expired
            
            if ($isExpiringSoon) {
                if ($daysUntilExpiry <= self::EXPIRY_CRITICAL_DAYS) {
                    $utilization = $forceLeaveBalance > 0 ? round(($forceLeaveUsed / $forceLeaveBalance) * 100, 1) : 0;
                    $alerts[] = [
                        'type' => 'force_leave_1_year_expiry_critical',
                        'leave_type' => 'mandatory',
                        'leave_name' => 'Force Leave (Mandatory)',
                        'unused_days' => $forceLeaveRemaining,
                        'remaining' => $forceLeaveRemaining,
                        'days_until_forfeiture' => $daysUntilExpiry,
                        'expiry_date' => $forceLeaveExpiryDate,
                        'utilization' => $utilization,
                        'allocated' => $forceLeaveBalance,
                        'used' => $forceLeaveUsed,
                        'severity' => 'critical',
                        'expiry_rule' => '1_year_from_grant_date',
                        'message' => $this->generateForceLeaveExpiryMessage($forceLeaveRemaining, $daysUntilExpiry, 'critical', $forceLeaveExpiryDate)
                    ];
                } elseif ($daysUntilExpiry <= self::EXPIRY_WARNING_DAYS) {
                    $utilization = $forceLeaveBalance > 0 ? round(($forceLeaveUsed / $forceLeaveBalance) * 100, 1) : 0;
                    $alerts[] = [
                        'type' => 'force_leave_1_year_expiry_warning',
                        'leave_type' => 'mandatory',
                        'leave_name' => 'Force Leave (Mandatory)',
                        'unused_days' => $forceLeaveRemaining,
                        'remaining' => $forceLeaveRemaining,
                        'days_until_forfeiture' => $daysUntilExpiry,
                        'expiry_date' => $forceLeaveExpiryDate,
                        'utilization' => $utilization,
                        'allocated' => $forceLeaveBalance,
                        'used' => $forceLeaveUsed,
                        'severity' => 'urgent',
                        'expiry_rule' => '1_year_from_grant_date',
                        'message' => $this->generateForceLeaveExpiryMessage($forceLeaveRemaining, $daysUntilExpiry, 'warning', $forceLeaveExpiryDate)
                    ];
                }
            }
        }
        
        // CTO (Compensatory Time Off) - Check specific expiry date
        $ctoBalance = $employee['cto_balance'] ?? 0;
        $ctoUsed = $employee['cto_used'] ?? 0;
        $ctoRemaining = max(0, $ctoBalance - $ctoUsed);
        $ctoExpiryDate = $employee['cto_expiry_date'] ?? null;
        
        if ($ctoRemaining > 0 && $ctoExpiryDate) {
            $expiryDate = new DateTime($ctoExpiryDate);
            $daysUntilExpiry = $currentDate->diff($expiryDate)->days;
            $isExpiringSoon = $expiryDate > $currentDate;
            
            if ($isExpiringSoon) {
                if ($daysUntilExpiry <= self::EXPIRY_CRITICAL_DAYS) {
                    $utilization = $ctoBalance > 0 ? round(($ctoUsed / $ctoBalance) * 100, 1) : 0;
                    $alerts[] = [
                        'type' => 'cto_1_year_expiry_critical',
                        'leave_type' => 'cto',
                        'leave_name' => 'Compensatory Time Off (CTO)',
                        'unused_days' => $ctoRemaining,
                        'remaining' => $ctoRemaining,
                        'days_until_forfeiture' => $daysUntilExpiry,
                        'expiry_date' => $ctoExpiryDate,
                        'utilization' => $utilization,
                        'allocated' => $ctoBalance,
                        'used' => $ctoUsed,
                        'severity' => 'critical',
                        'expiry_rule' => '1_year_from_grant_date',
                        'message' => $this->generateCTOExpiryMessage($ctoRemaining, $daysUntilExpiry, 'critical', $ctoExpiryDate)
                    ];
                } elseif ($daysUntilExpiry <= self::EXPIRY_WARNING_DAYS) {
                    $utilization = $ctoBalance > 0 ? round(($ctoUsed / $ctoBalance) * 100, 1) : 0;
                    $alerts[] = [
                        'type' => 'cto_1_year_expiry_warning',
                        'leave_type' => 'cto',
                        'leave_name' => 'Compensatory Time Off (CTO)',
                        'unused_days' => $ctoRemaining,
                        'remaining' => $ctoRemaining,
                        'days_until_forfeiture' => $daysUntilExpiry,
                        'expiry_date' => $ctoExpiryDate,
                        'utilization' => $utilization,
                        'allocated' => $ctoBalance,
                        'used' => $ctoUsed,
                        'severity' => 'urgent',
                        'expiry_rule' => '1_year_from_grant_date',
                        'message' => $this->generateCTOExpiryMessage($ctoRemaining, $daysUntilExpiry, 'warning', $ctoExpiryDate)
                    ];
                }
            }
        }
        
        // SLP (Special Leave Privilege) - Check specific expiry date
        $slpBalance = $employee['special_leave_privilege_balance'] ?? 0;
        $slpUsed = $employee['special_privilege_used'] ?? 0;
        $slpRemaining = max(0, $slpBalance - $slpUsed);
        $slpExpiryDate = $employee['slp_expiry_date'] ?? null;
        
        if ($slpRemaining > 0 && $slpExpiryDate) {
            $expiryDate = new DateTime($slpExpiryDate);
            $daysUntilExpiry = $currentDate->diff($expiryDate)->days;
            $isExpiringSoon = $expiryDate > $currentDate;
            
            if ($isExpiringSoon) {
                if ($daysUntilExpiry <= self::EXPIRY_CRITICAL_DAYS) {
                    $utilization = $slpBalance > 0 ? round(($slpUsed / $slpBalance) * 100, 1) : 0;
                    $alerts[] = [
                        'type' => 'slp_1_year_expiry_critical',
                        'leave_type' => 'special_privilege',
                        'leave_name' => 'Special Leave Privilege (SLP)',
                        'unused_days' => $slpRemaining,
                        'remaining' => $slpRemaining,
                        'days_until_forfeiture' => $daysUntilExpiry,
                        'expiry_date' => $slpExpiryDate,
                        'utilization' => $utilization,
                        'allocated' => $slpBalance,
                        'used' => $slpUsed,
                        'severity' => 'critical',
                        'expiry_rule' => '1_year_from_grant_date',
                        'message' => $this->generateSLPExpiryMessage($slpRemaining, $daysUntilExpiry, 'critical', $slpExpiryDate)
                    ];
                } elseif ($daysUntilExpiry <= self::EXPIRY_WARNING_DAYS) {
                    $utilization = $slpBalance > 0 ? round(($slpUsed / $slpBalance) * 100, 1) : 0;
                    $alerts[] = [
                        'type' => 'slp_1_year_expiry_warning',
                        'leave_type' => 'special_privilege',
                        'leave_name' => 'Special Leave Privilege (SLP)',
                        'unused_days' => $slpRemaining,
                        'remaining' => $slpRemaining,
                        'days_until_forfeiture' => $daysUntilExpiry,
                        'expiry_date' => $slpExpiryDate,
                        'utilization' => $utilization,
                        'allocated' => $slpBalance,
                        'used' => $slpUsed,
                        'severity' => 'urgent',
                        'expiry_rule' => '1_year_from_grant_date',
                        'message' => $this->generateSLPExpiryMessage($slpRemaining, $daysUntilExpiry, 'warning', $slpExpiryDate)
                    ];
                }
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get ALL employees with Force Leave, CTO, and SLP data (including those without expiring credits)
     */
    private function getAllEmployeesWithOneYearExpiryData($year) {
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id, e.name, e.email, e.department, e.position,
                COALESCE(e.mandatory_leave_balance, 0) as mandatory_leave_balance,
                COALESCE(e.cto_balance, 0) as cto_balance,
                COALESCE(e.special_leave_privilege_balance, 0) as special_leave_privilege_balance,
                e.mandatory_leave_expiry_date,
                e.cto_expiry_date,
                e.slp_expiry_date,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'mandatory' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as mandatory_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'cto' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as cto_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'special_privilege' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as special_privilege_used
            FROM employees e
            LEFT JOIN leave_requests lr ON e.id = lr.employee_id
            WHERE e.role = 'employee'
            GROUP BY e.id, e.name, e.email, e.department, e.position,
                     e.mandatory_leave_balance, e.cto_balance, e.special_leave_privilege_balance,
                     e.mandatory_leave_expiry_date, e.cto_expiry_date, e.slp_expiry_date
            ORDER BY e.name
        ");
        
        $stmt->execute([$year, $year, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get employees with only 1-year expiry leave data including specific expiry dates (original method for compatibility)
     */
    private function getEmployeesWithOneYearExpiryData($year) {
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id, e.name, e.email, e.department, e.position,
                COALESCE(e.mandatory_leave_balance, 0) as mandatory_leave_balance,
                COALESCE(e.cto_balance, 0) as cto_balance,
                COALESCE(e.special_leave_privilege_balance, 0) as special_leave_privilege_balance,
                e.mandatory_leave_expiry_date,
                e.cto_expiry_date,
                e.slp_expiry_date,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'mandatory' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as mandatory_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'cto' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as cto_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'special_privilege' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as special_privilege_used
            FROM employees e
            LEFT JOIN leave_requests lr ON e.id = lr.employee_id
            WHERE e.role = 'employee'
            AND (
                (COALESCE(e.mandatory_leave_balance, 0) > 0 AND e.mandatory_leave_expiry_date IS NOT NULL) OR
                (COALESCE(e.cto_balance, 0) > 0 AND e.cto_expiry_date IS NOT NULL) OR
                (COALESCE(e.special_leave_privilege_balance, 0) > 0 AND e.slp_expiry_date IS NOT NULL)
            )
            GROUP BY e.id, e.name, e.email, e.department, e.position,
                     e.mandatory_leave_balance, e.cto_balance, e.special_leave_privilege_balance,
                     e.mandatory_leave_expiry_date, e.cto_expiry_date, e.slp_expiry_date
            ORDER BY e.name
        ");
        
        $stmt->execute([$year, $year, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calculate alert priority based on 1-year expiry urgency
     */
    private function calculateAlertPriority($alerts, $daysRemaining) {
        $hasUrgent = false;
        $hasCritical = false;
        
        foreach ($alerts as $alert) {
            if ($alert['severity'] === 'critical') $hasCritical = true;
            if ($alert['severity'] === 'urgent') $hasUrgent = true;
        }
        
        if ($hasCritical || $daysRemaining <= self::EXPIRY_CRITICAL_DAYS) {
            return 'critical';
        } elseif ($hasUrgent || $daysRemaining <= self::EXPIRY_WARNING_DAYS) {
            return 'urgent';
        } else {
            return 'moderate';
        }
    }
    
    /**
     * Generate Force Leave expiry message with specific expiry date
     */
    private function generateForceLeaveExpiryMessage($remaining, $daysUntil, $severity, $expiryDate = null) {
        $expiryDateText = $expiryDate ? date('F j, Y', strtotime($expiryDate)) : 'the expiry date';
        
        if ($severity === 'critical') {
            return "Subject: CRITICAL - Force Leave 1-Year Expiry Notice\n\nIn compliance with Civil Service Commission (CSC) Omnibus Rules on Leave and the mandatory 1-year expiry policy for Force Leave credits:\n\nYour {$remaining} Force Leave (Mandatory) days will EXPIRE and be FORFEITED in {$daysUntil} days on {$expiryDateText}.\n\nIMPORTANT: Force Leave credits are subject to MANDATORY 1-YEAR EXPIRY from the date they were granted and cannot be carried over.\n\nIMMEDIATE ACTION REQUIRED:\n• Schedule and utilize your Force Leave credits IMMEDIATELY\n• Coordinate with your immediate supervisor for leave scheduling\n• Submit leave applications within the next {$daysUntil} days\n• Failure to use these credits will result in AUTOMATIC FORFEITURE\n\nConsequences of non-utilization:\n• Complete forfeiture of unused Force Leave credits on {$expiryDateText}\n• No monetary compensation for expired credits\n• Administrative adjustment of leave balance\n• Potential assignment of forced leave dates by HRMO\n\nExpiry Date: {$expiryDateText} (1 year from grant date)\n\nFor urgent assistance, contact the HRMO immediately.\n\nHuman Resource Management Office";
        } else {
            return "Subject: URGENT - Force Leave 1-Year Expiry Advisory\n\nIn compliance with Civil Service Commission (CSC) Omnibus Rules on Leave and the mandatory 1-year expiry policy:\n\nYour {$remaining} Force Leave (Mandatory) days will expire in {$daysUntil} days on {$expiryDateText}.\n\nREMINDER: Force Leave credits are subject to MANDATORY 1-YEAR EXPIRY from the date they were granted and must be utilized before the expiry date.\n\nACTION REQUIRED:\n• Plan and schedule your Force Leave utilization immediately\n• Coordinate with your supervisor for optimal leave scheduling\n• Submit leave applications well in advance\n• Ensure all credits are used before {$expiryDateText}\n\nExpiry Date: {$expiryDateText} (1 year from grant date)\n\nNote: Unused Force Leave credits will be automatically forfeited on the expiry date with no compensation.\n\nFor assistance with leave planning, contact the HRMO.\n\nHuman Resource Management Office";
        }
    }
    
    /**
     * Generate CTO expiry message with specific expiry date
     */
    private function generateCTOExpiryMessage($remaining, $daysUntil, $severity, $expiryDate = null) {
        $expiryDateText = $expiryDate ? date('F j, Y', strtotime($expiryDate)) : 'the expiry date';
        
        if ($severity === 'critical') {
            return "Subject: CRITICAL - CTO 1-Year Expiry Notice\n\nIn compliance with government compensation policies and the mandatory 1-year expiry rule for Compensatory Time Off:\n\nYour {$remaining} CTO (Compensatory Time Off) hours/days will EXPIRE and be FORFEITED in {$daysUntil} days on {$expiryDateText}.\n\nIMPORTANT: CTO credits earned through overtime work are subject to MANDATORY 1-YEAR EXPIRY from the date they were granted and cannot be carried over.\n\nIMMEDIATE ACTION REQUIRED:\n• Schedule and utilize your CTO credits IMMEDIATELY\n• Coordinate with your supervisor for time-off scheduling\n• Submit CTO applications within the next {$daysUntil} days\n• Failure to use these credits will result in AUTOMATIC FORFEITURE\n\nConsequences of non-utilization:\n• Complete loss of earned overtime compensation on {$expiryDateText}\n• No monetary equivalent for expired CTO\n• Administrative adjustment of CTO balance\n\nExpiry Date: {$expiryDateText} (1 year from grant date)\n\nYour overtime work deserves compensation - don't let it expire!\n\nFor urgent assistance, contact the HRMO immediately.\n\nHuman Resource Management Office";
        } else {
            return "Subject: URGENT - CTO 1-Year Expiry Advisory\n\nIn compliance with government compensation policies and the mandatory 1-year expiry rule:\n\nYour {$remaining} CTO (Compensatory Time Off) hours/days will expire in {$daysUntil} days on {$expiryDateText}.\n\nREMINDER: CTO credits earned through overtime work are subject to MANDATORY 1-YEAR EXPIRY from the date they were granted and must be utilized before the expiry date.\n\nACTION REQUIRED:\n• Plan and schedule your CTO utilization immediately\n• Coordinate with your supervisor for optimal scheduling\n• Submit CTO applications well in advance\n• Ensure all earned overtime compensation is used before {$expiryDateText}\n\nExpiry Date: {$expiryDateText} (1 year from grant date)\n\nNote: Unused CTO will be automatically forfeited on the expiry date with no monetary compensation.\n\nDon't lose the compensation you earned through overtime work!\n\nFor assistance, contact the HRMO.\n\nHuman Resource Management Office";
        }
    }
    
    /**
     * Generate SLP expiry message with specific expiry date
     */
    private function generateSLPExpiryMessage($remaining, $daysUntil, $severity, $expiryDate = null) {
        $expiryDateText = $expiryDate ? date('F j, Y', strtotime($expiryDate)) : 'the expiry date';
        
        if ($severity === 'critical') {
            return "Subject: CRITICAL - SLP 1-Year Expiry Notice\n\nIn compliance with Civil Service Commission (CSC) MC No. 6, s. 1996 and the mandatory 1-year expiry policy for Special Leave Privilege:\n\nYour {$remaining} SLP (Special Leave Privilege) days will EXPIRE and be FORFEITED in {$daysUntil} days on {$expiryDateText}.\n\nIMPORTANT: SLP credits are NON-CUMULATIVE and subject to MANDATORY 1-YEAR EXPIRY from the date they were granted. They cannot be carried over or converted to cash.\n\nIMMEDIATE ACTION REQUIRED:\n• Schedule and utilize your SLP credits IMMEDIATELY\n• Coordinate with your supervisor for leave scheduling\n• Submit SLP applications within the next {$daysUntil} days\n• Failure to use these credits will result in AUTOMATIC FORFEITURE\n\nConsequences of non-utilization:\n• Complete forfeiture of unused SLP credits on {$expiryDateText}\n• No monetary compensation (non-commutable)\n• No carry-over (non-cumulative)\n• Administrative adjustment of leave balance\n\nExpiry Date: {$expiryDateText} (1 year from grant date)\n\nSLP is a special privilege - use it before you lose it!\n\nFor urgent assistance, contact the HRMO immediately.\n\nHuman Resource Management Office";
        } else {
            return "Subject: URGENT - SLP 1-Year Expiry Advisory\n\nIn compliance with Civil Service Commission (CSC) MC No. 6, s. 1996 and the mandatory 1-year expiry policy:\n\nYour {$remaining} SLP (Special Leave Privilege) days will expire in {$daysUntil} days on {$expiryDateText}.\n\nREMINDER: SLP credits are NON-CUMULATIVE and subject to MANDATORY 1-YEAR EXPIRY from the date they were granted and must be utilized before the expiry date.\n\nACTION REQUIRED:\n• Plan and schedule your SLP utilization immediately\n• Coordinate with your supervisor for optimal leave scheduling\n• Submit SLP applications well in advance\n• Ensure all credits are used before {$expiryDateText}\n\nExpiry Date: {$expiryDateText} (1 year from grant date)\n\nNote: Unused SLP will be automatically forfeited on the expiry date (non-cumulative, non-commutable).\n\nTake advantage of this special privilege before it expires!\n\nFor assistance with leave planning, contact the HRMO.\n\nHuman Resource Management Office";
        }
    }
    
    /**
     * Get urgent alerts (for compatibility with existing code)
     */
    public function getUrgentAlerts($limit = 50) {
        return $this->generateComprehensiveAlerts();
    }
    
    /**
     * Get alert statistics (for compatibility with existing code)
     */
    public function getAlertStatistics() {
        $alerts = $this->generateComprehensiveAlerts();
        
        $stats = [
            'total_employees_with_alerts' => count($alerts),
            'urgent_alerts' => 0,
            'year_end_risks' => 0
        ];
        
        foreach ($alerts as $employeeAlert) {
            if ($employeeAlert['priority'] === 'urgent' || $employeeAlert['priority'] === 'critical') {
                $stats['urgent_alerts']++;
            }
            $stats['year_end_risks']++;
        }
        
        return $stats;
    }
}
?>