<?php
/**
 * Enhanced Leave Alert Service
 * Provides advanced leave maximization alerts with CSC compliance tracking
 * Based on Civil Service Commission rules and Philippine labor standards
 */

class EnhancedLeaveAlertService {
    private $pdo;
    private $leaveTypesConfig;
    
    // CSC Leave Policy Thresholds (Based on Civil Service Commission Guidelines)
    const URGENT_THRESHOLD_DAYS = 30;      // Days remaining in year
    const MODERATE_THRESHOLD_DAYS = 60;    // Days remaining in year
    const LOW_UTILIZATION_THRESHOLD = 50;  // Percentage of leave used
    const CRITICAL_UTILIZATION_THRESHOLD = 25; // Critical low utilization
    
    // CSC Leave Limits (Per CSC Memorandum Circular)
    const VACATION_LEAVE_MAX = 15;         // Maximum vacation leave per year
    const SICK_LEAVE_MAX = 15;             // Maximum sick leave per year
    const SPECIAL_PRIVILEGE_MAX = 3;       // Maximum special privilege leave per year
    const MANDATORY_LEAVE_MAX = 5;         // Maximum mandatory leave per year
    
    // CSC Forfeiture Rules
    const LEAVE_FORFEITURE_WARNING_DAYS = 45; // Warning before forfeiture
    const LEAVE_FORFEITURE_CRITICAL_DAYS = 15; // Critical warning before forfeiture
    const LEAVE_FORFEITURE_DEADLINE = 31;     // December 31 deadline
    
    // CSC Leave Utilization Thresholds
    const LEAVE_UTILIZATION_WARNING = 60;   // Warning when utilization is below 60%
    const LEAVE_UTILIZATION_CRITICAL = 30;  // Critical when utilization is below 30%
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        require_once dirname(__DIR__, 3) . '/config/leave_types.php';
        $this->leaveTypesConfig = getLeaveTypes();
    }
    
    /**
     * Generate comprehensive leave alerts for all employees
     */
    public function generateComprehensiveAlerts() {
        $currentYear = date('Y');
        $currentDate = new DateTime();
        $yearEnd = new DateTime($currentYear . '-12-31');
        $daysRemaining = $currentDate->diff($yearEnd)->days;
        
        $alerts = [];
        
        // Get all employees with their leave data
        $employees = $this->getEmployeesWithLeaveData($currentYear);
        
        foreach ($employees as $employee) {
            $employeeAlerts = $this->analyzeEmployeeLeaveUtilization($employee, $currentYear, $daysRemaining);
            if (!empty($employeeAlerts)) {
                $alerts[$employee['id']] = [
                    'employee' => $employee,
                    'alerts' => $employeeAlerts,
                    'priority' => $this->calculateAlertPriority($employeeAlerts, $daysRemaining),
                    'csc_compliance' => $this->checkCSCUtilizationCompliance($employee, $currentYear)
                ];
            }
        }
        
        return $alerts;
    }
    
    /**
     * Analyze individual employee leave utilization
     */
    private function analyzeEmployeeLeaveUtilization($employee, $year, $daysRemaining) {
        $alerts = [];
        $totalAllocated = 0;
        $totalUsed = 0;
        $criticalTypes = [];
        
        foreach ($this->leaveTypesConfig as $type => $config) {
            if (!$config['requires_credits']) continue;
            
            $balanceField = $config['credit_field'];
            $usedField = $type . '_used';
            
            $allocated = $employee[$balanceField] ?? 0;
            $used = $employee[$usedField] ?? 0;
            $remaining = max(0, $allocated - $used);
            $utilization = $allocated > 0 ? round(($used / $allocated) * 100, 1) : 0;
            
            $totalAllocated += $allocated;
            $totalUsed += $used;
            
            // Check for low utilization
            if ($allocated > 0 && $utilization < self::LOW_UTILIZATION_THRESHOLD) {
                $severity = $utilization < self::CRITICAL_UTILIZATION_THRESHOLD ? 'critical' : 'warning';
                
                $alerts[] = [
                    'type' => 'low_utilization',
                    'leave_type' => $type,
                    'leave_name' => $config['name'],
                    'allocated' => $allocated,
                    'used' => $used,
                    'remaining' => $remaining,
                    'utilization' => $utilization,
                    'severity' => $severity,
                    'message' => $this->generateUtilizationMessage($type, $config['name'], $utilization, $remaining, $daysRemaining)
                ];
                
                if ($severity === 'critical') {
                    $criticalTypes[] = $type;
                }
            }
            
            // Check for year-end urgency
            if ($daysRemaining <= self::URGENT_THRESHOLD_DAYS && $remaining > 0) {
                $alerts[] = [
                    'type' => 'year_end_urgent',
                    'leave_type' => $type,
                    'leave_name' => $config['name'],
                    'remaining' => $remaining,
                    'days_remaining' => $daysRemaining,
                    'severity' => 'urgent',
                    'message' => $this->generateYearEndMessage($type, $config['name'], $remaining, $daysRemaining)
                ];
            }
        }
        
        // Add CSC compliance checks
        $cscViolations = $this->checkCSCLeaveLimits($employee, $year);
        foreach ($cscViolations as $violation) {
            $alerts[] = $violation;
        }
        
        // Add CSC utilization compliance checks
        $utilizationAlerts = $this->checkCSCUtilizationCompliance($employee, $year);
        foreach ($utilizationAlerts as $alert) {
            $alerts[] = $alert;
        }
        
        // Add year-end forfeiture risk checks
        $forfeitureRisks = $this->checkYearEndForfeitureRisk($employee, $year);
        foreach ($forfeitureRisks as $risk) {
            $alerts[] = $risk;
        }
        
        // Overall utilization alert
        $overallUtilization = $totalAllocated > 0 ? round(($totalUsed / $totalAllocated) * 100, 1) : 0;
        if ($overallUtilization < self::LOW_UTILIZATION_THRESHOLD) {
            $alerts[] = [
                'type' => 'overall_low_utilization',
                'total_allocated' => $totalAllocated,
                'total_used' => $totalUsed,
                'total_remaining' => $totalAllocated - $totalUsed,
                'utilization' => $overallUtilization,
                'severity' => $overallUtilization < self::CRITICAL_UTILIZATION_THRESHOLD ? 'critical' : 'warning',
                'message' => $this->generateOverallUtilizationMessage($overallUtilization, $totalAllocated - $totalUsed, $daysRemaining)
            ];
        }
        
        // CSC utilization compliance check
        $utilizationAlerts = $this->checkCSCUtilizationCompliance($employee, $year);
        foreach ($utilizationAlerts as $alert) {
            $alerts[] = $alert;
        }
        
        return $alerts;
    }
    
    /**
     * Check CSC leave utilization compliance
     */
    private function checkCSCUtilizationCompliance($employee, $year) {
        $totalAllocated = 0;
        $totalUsed = 0;
        $utilizationAlerts = [];
        
        // Calculate overall leave utilization
        foreach ($this->leaveTypesConfig as $type => $config) {
            if (!$config['requires_credits']) continue;
            
            $balanceField = $config['credit_field'];
            $usedField = $type . '_used';
            
            $allocated = $employee[$balanceField] ?? 0;
            $used = $employee[$usedField] ?? 0;
            
            $totalAllocated += $allocated;
            $totalUsed += $used;
        }
        
        $overallUtilization = $totalAllocated > 0 ? round(($totalUsed / $totalAllocated) * 100, 1) : 0;
        
        // Check for low utilization
        if ($overallUtilization < self::LEAVE_UTILIZATION_WARNING) {
            $severity = $overallUtilization < self::LEAVE_UTILIZATION_CRITICAL ? 'critical' : 'urgent';
            
            $utilizationAlerts[] = [
                'type' => 'csc_utilization_low',
                'leave_type' => 'overall',
                'leave_name' => 'Overall Leave Utilization',
                'allocated' => $totalAllocated,
                'used' => $totalUsed,
                'remaining' => $totalAllocated - $totalUsed,
                'utilization' => $overallUtilization,
                'severity' => $severity,
                'message' => $this->generateCSCUtilizationMessage($overallUtilization, $totalAllocated - $totalUsed)
            ];
        }
        
        return $utilizationAlerts;
    }
    
    /**
     * Check for CSC leave limit violations
     */
    private function checkCSCLeaveLimits($employee, $currentYear) {
        $violations = [];
        
        try {
            // Define CSC leave limits
            $cscLimits = [
                'vacation' => ['max' => self::VACATION_LEAVE_MAX, 'used' => $employee['vacation_used'] ?? 0],
                'sick' => ['max' => self::SICK_LEAVE_MAX, 'used' => $employee['sick_used'] ?? 0],
                'special_privilege' => ['max' => self::SPECIAL_PRIVILEGE_MAX, 'used' => $employee['special_privilege_used'] ?? 0],
                'mandatory' => ['max' => self::MANDATORY_LEAVE_MAX, 'used' => $employee['mandatory_used'] ?? 0]
            ];
            
            foreach ($cscLimits as $leaveType => $data) {
                $usedDays = $data['used'];
                $maxDays = $data['max'];
                $remainingDays = $maxDays - $usedDays;
                
                if ($usedDays > $maxDays) {
                    $violations[] = [
                        'type' => 'csc_limit_exceeded',
                        'leave_type' => $leaveType,
                        'used_days' => $usedDays,
                        'max_days' => $maxDays,
                        'excess_days' => $usedDays - $maxDays,
                        'severity' => 'critical',
                        'message' => "Subject: URGENT Notice on CSC Leave Limit Violation\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour {$leaveType} usage has exceeded the maximum allowable limit (Used: {$usedDays}/{$maxDays} days).\n\nYou are required to contact the Human Resource Management Office IMMEDIATELY for administrative adjustments and compliance measures.\n\nThis violation may result in:\n\n• Non-crediting of excess leave days;\n• Administrative sanctions as per CSC guidelines; and\n• Mandatory adjustment of your leave balance.\n\nPlease report to the HRMO at your earliest convenience.\n\nThank you for your immediate attention to this matter.\n\nHuman Resource Management Office"
                    ];
                } elseif ($remainingDays <= 2 && $remainingDays > 0) {
                    $violations[] = [
                        'type' => 'csc_limit_approaching',
                        'leave_type' => $leaveType,
                        'used_days' => $usedDays,
                        'max_days' => $maxDays,
                        'remaining_days' => $remainingDays,
                        'severity' => 'urgent',
                        'message' => "Subject: Advisory on CSC Leave Limit\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour {$leaveType} usage is approaching the maximum allowable limit ({$remainingDays} days remaining before limit).\n\nYou are advised to coordinate with your immediate supervisor regarding the scheduling of your leave to avoid exceeding the CSC-mandated limits.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office"
                    ];
                }
            }
            
            return $violations;
            
        } catch (Exception $e) {
            error_log("Error checking CSC leave limits: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check for year-end forfeiture risks
     */
    private function checkYearEndForfeitureRisk($employee, $currentYear) {
        $risks = [];
        
        try {
            $currentDate = new DateTime();
            $yearEnd = new DateTime($currentYear . '-12-31');
            $daysUntilYearEnd = $currentDate->diff($yearEnd)->days;
            
            // Define leave types and their limits
            $leaveTypes = [
                'vacation' => ['max' => self::VACATION_LEAVE_MAX, 'used' => $employee['vacation_used'] ?? 0],
                'sick' => ['max' => self::SICK_LEAVE_MAX, 'used' => $employee['sick_used'] ?? 0],
                'special_privilege' => ['max' => self::SPECIAL_PRIVILEGE_MAX, 'used' => $employee['special_privilege_used'] ?? 0],
                'mandatory' => ['max' => self::MANDATORY_LEAVE_MAX, 'used' => $employee['mandatory_used'] ?? 0]
            ];
            
            foreach ($leaveTypes as $leaveType => $data) {
                $usedDays = $data['used'];
                $maxDays = $data['max'];
                $unusedDays = $maxDays - $usedDays;
                
                if ($unusedDays > 0) {
                    if ($daysUntilYearEnd <= self::LEAVE_FORFEITURE_CRITICAL_DAYS) {
                        $risks[] = [
                            'type' => 'year_end_critical',
                            'leave_type' => $leaveType,
                            'unused_days' => $unusedDays,
                            'days_until_forfeiture' => $daysUntilYearEnd,
                            'severity' => 'critical',
                            'message' => "Subject: URGENT Advisory on Leave Credit Forfeiture\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour {$unusedDays} {$leaveType} days will be forfeited in {$daysUntilYearEnd} days.\n\nAccordingly, you are advised to schedule and utilize your leave credits IMMEDIATELY to avoid the following actions:\n\n• Forfeiture of unused leave days at year-end;\n• Non-crediting of excess leave balances; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office"
                        ];
                    } elseif ($daysUntilYearEnd <= self::LEAVE_FORFEITURE_WARNING_DAYS) {
                        $risks[] = [
                            'type' => 'year_end_warning',
                            'leave_type' => $leaveType,
                            'unused_days' => $unusedDays,
                            'days_until_forfeiture' => $daysUntilYearEnd,
                            'severity' => 'urgent',
                            'message' => "Subject: Advisory on Leave Credit Forfeiture\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour {$unusedDays} {$leaveType} days will be forfeited in {$daysUntilYearEnd} days.\n\nAccordingly, you are advised to schedule and utilize your leave credits within the prescribed period to avoid the following actions:\n\n• Forfeiture of unused leave days at year-end;\n• Non-crediting of excess leave balances; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office"
                        ];
                    }
                }
            }
            
        return $risks;
        
    } catch (Exception $e) {
        error_log("Error checking year-end forfeiture risk: " . $e->getMessage());
        return [];
    }
}

/**
 * Generate CSC utilization message
 */
private function generateCSCUtilizationMessage($utilization, $remainingDays) {
    if ($utilization < self::LEAVE_UTILIZATION_CRITICAL) {
        return "Subject: Advisory on Leave Credit Maximization\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour leave credits have reached or are nearing the maximum allowable accumulation ({$remainingDays} days remaining).\n\nAccordingly, you are advised to schedule and utilize your excess leave credits within the prescribed period to avoid the following actions:\n\n• Application of Compulsory/Forced Leave, in accordance with CSC rules;\n• Non-crediting or forfeiture of leave days exceeding the allowable limit; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office";
    } else {
        return "Subject: Reminder on Leave Credit Utilization\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYou have {$remainingDays} days remaining for utilization.\n\nYou are advised to schedule and utilize your leave credits within the prescribed period. Please coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office";
    }
}
    
    /**
     * Get employees with comprehensive leave data
     */
    private function getEmployeesWithLeaveData($year) {
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id, e.name, e.email, e.department, e.position, e.service_start_date, e.created_at,
                e.vacation_leave_balance, e.sick_leave_balance, e.special_leave_privilege_balance,
                e.maternity_leave_balance, e.paternity_leave_balance, e.solo_parent_leave_balance,
                e.vawc_leave_balance, e.rehabilitation_leave_balance, e.special_women_leave_balance,
                e.special_emergency_leave_balance, e.adoption_leave_balance, e.mandatory_leave_balance,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'vacation' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as vacation_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'sick' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as sick_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'special_privilege' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as special_privilege_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'maternity' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as maternity_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'paternity' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as paternity_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'solo_parent' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as solo_parent_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'vawc' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as vawc_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'rehabilitation' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as rehabilitation_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'special_women' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as special_women_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'special_emergency' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as special_emergency_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'adoption' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as adoption_used,
                COALESCE(SUM(CASE WHEN lr.leave_type = 'mandatory' AND YEAR(lr.start_date) = ? AND lr.status = 'approved' 
                    THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) as mandatory_used
            FROM employees e
            LEFT JOIN leave_requests lr ON e.id = lr.employee_id
            WHERE e.role = 'employee'
            GROUP BY e.id, e.name, e.email, e.department, e.position, e.service_start_date, e.created_at,
                     e.vacation_leave_balance, e.sick_leave_balance, e.special_leave_privilege_balance,
                     e.maternity_leave_balance, e.paternity_leave_balance, e.solo_parent_leave_balance,
                     e.vawc_leave_balance, e.rehabilitation_leave_balance, e.special_women_leave_balance,
                     e.special_emergency_leave_balance, e.adoption_leave_balance, e.mandatory_leave_balance
            ORDER BY e.name
        ");
        
        $stmt->execute([$year, $year, $year, $year, $year, $year, $year, $year, $year, $year, $year, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calculate alert priority based on severity and urgency
     */
    private function calculateAlertPriority($alerts, $daysRemaining) {
        $hasUrgent = false;
        $hasCritical = false;
        $hasWarning = false;
        
        foreach ($alerts as $alert) {
            if ($alert['severity'] === 'urgent') $hasUrgent = true;
            if ($alert['severity'] === 'critical') $hasCritical = true;
            if ($alert['severity'] === 'warning') $hasWarning = true;
        }
        
        if ($hasUrgent || $daysRemaining <= self::URGENT_THRESHOLD_DAYS) {
            return 'urgent';
        } elseif ($hasCritical) {
            return 'critical';
        } elseif ($hasWarning) {
            return 'moderate';
        } else {
            return 'low';
        }
    }
    
    /**
     * Generate utilization message
     */
    private function generateUtilizationMessage($type, $name, $utilization, $remaining, $daysRemaining) {
        if ($utilization < self::CRITICAL_UTILIZATION_THRESHOLD) {
            return "Subject: Advisory on Leave Credit Maximization\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour {$name} credits have reached or are nearing the maximum allowable accumulation ({$remaining} days remaining).\n\nAccordingly, you are advised to schedule and utilize your excess leave credits within the prescribed period to avoid the following actions:\n\n• Application of Compulsory/Forced Leave, in accordance with CSC rules;\n• Non-crediting or forfeiture of leave days exceeding the allowable limit; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office";
        } else {
            return "Subject: Reminder on Leave Credit Utilization\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYou have {$remaining} {$name} days available for utilization.\n\nYou are advised to schedule and utilize your leave credits within the prescribed period. Please coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office";
        }
    }
    
    /**
     * Generate year-end urgent message
     */
    private function generateYearEndMessage($type, $name, $remaining, $daysRemaining) {
        return "Subject: Advisory on Year-End Leave Credit Forfeiture\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour {$remaining} {$name} days will expire on December 31st and are subject to forfeiture.\n\nAccordingly, you are advised to schedule and utilize your leave credits within the prescribed period to avoid the following actions:\n\n• Forfeiture of unused leave days at year-end;\n• Non-crediting of excess leave balances; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office";
    }
    
    /**
     * Generate overall utilization message
     */
    private function generateOverallUtilizationMessage($utilization, $totalRemaining, $daysRemaining) {
        if ($daysRemaining <= self::URGENT_THRESHOLD_DAYS) {
            return "Subject: Advisory on Year-End Leave Credit Forfeiture\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour {$totalRemaining} total leave days will expire on December 31st and are subject to forfeiture.\n\nAccordingly, you are advised to schedule and utilize your leave credits within the prescribed period to avoid the following actions:\n\n• Forfeiture of unused leave days at year-end;\n• Non-crediting of excess leave balances; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office";
        } else {
            return "Subject: Advisory on Leave Credit Maximization\n\nIn compliance with existing Government Leave Administration Policies and pursuant to the guidelines outlined in the Civil Service Commission (CSC) Omnibus Rules on Leave, this is to formally notify you that:\n\nYour leave credits have reached or are nearing the maximum allowable accumulation ({$totalRemaining} days remaining).\n\nAccordingly, you are advised to schedule and utilize your excess leave credits within the prescribed period to avoid the following actions:\n\n• Application of Compulsory/Forced Leave, in accordance with CSC rules;\n• Non-crediting or forfeiture of leave days exceeding the allowable limit; and\n• Administrative adjustment of your leave balance based on government accounting requirements.\n\nYou are further requested to coordinate with your immediate supervisor regarding the scheduling of your leave to ensure that operations remain uninterrupted. Failure to submit preferred dates may result in this Office assigning forced leave dates on your behalf.\n\nFor concerns or clarifications, you may contact the HRMO during regular office hours.\n\nThank you for your cooperation.\n\nHuman Resource Management Office";
        }
    }
    
    
    /**
     * Send automated alert to employee
     */
    public function sendAutomatedAlert($employeeId, $alertType, $message, $priority = 'medium') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO leave_alerts (employee_id, alert_type, message, sent_by, priority, is_read, created_at) 
                VALUES (?, ?, ?, 0, ?, 0, NOW())
            ");
            $stmt->execute([$employeeId, $alertType, $message, $priority]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Error sending automated alert: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get alert statistics for dashboard
     */
    public function getAlertStatistics() {
        $currentYear = date('Y');
        $alerts = $this->generateComprehensiveAlerts();
        
        $stats = [
            'total_employees_with_alerts' => count($alerts),
            'urgent_alerts' => 0,
            'critical_alerts' => 0,
            'moderate_alerts' => 0,
            'low_alerts' => 0,
            'csc_compliance_issues' => 0,
            'year_end_risks' => 0
        ];
        
        foreach ($alerts as $employeeAlerts) {
            $priority = $employeeAlerts['priority'];
            $stats[$priority . '_alerts']++;
            
            if ($employeeAlerts['csc_compliance']) {
                $stats['csc_compliance_issues']++;
            }
            
            foreach ($employeeAlerts['alerts'] as $alert) {
                if ($alert['type'] === 'year_end_urgent' || $alert['type'] === 'year_end_critical' || $alert['type'] === 'year_end_warning') {
                    $stats['year_end_risks']++;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Get employees requiring immediate attention
     */
    public function getUrgentAlerts($limit = 20) {
        $alerts = $this->generateComprehensiveAlerts();
        
        // Sort by priority
        uasort($alerts, function($a, $b) {
            $priorityOrder = ['urgent' => 4, 'critical' => 3, 'moderate' => 2, 'low' => 1];
            return $priorityOrder[$b['priority']] - $priorityOrder[$a['priority']];
        });
        
        return array_slice($alerts, 0, $limit, true);
    }
}
?>
