<?php
/**
 * Leave Accrual Service
 * Handles automatic monthly accrual of leave credits
 */

class LeaveAccrualService {
    private $pdo;
    
    // Monthly accrual rates (CSC standard)
    const MONTHLY_VACATION_ACCRUAL = 1.25;  // 15 days / 12 months
    const MONTHLY_SICK_ACCRUAL = 1.25;      // 15 days / 12 months
    const ANNUAL_SLP_ACCRUAL = 3;           // 3 days per year
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Process monthly accrual for all eligible employees
     * @return array Results summary
     */
    public function processMonthlyAccrual() {
        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        $currentDate = date('Y-m-d');
        $isJanuary = (date('m') == '01');
        
        try {
            $employees = $this->getEligibleEmployees();
            
            foreach ($employees as $employee) {
                try {
                    $accrualResult = $this->accrueCreditsForEmployee(
                        $employee, 
                        $currentDate, 
                        $isJanuary
                    );
                    
                    if ($accrualResult['success']) {
                        $results['processed']++;
                        $results['details'][] = [
                            'employee_id' => $employee['id'],
                            'name' => $employee['name'],
                            'status' => 'success',
                            'message' => $accrualResult['message']
                        ];
                    } else {
                        $results['skipped']++;
                        $results['details'][] = [
                            'employee_id' => $employee['id'],
                            'name' => $employee['name'],
                            'status' => 'skipped',
                            'message' => $accrualResult['message']
                        ];
                    }
                } catch (Exception $e) {
                    $results['errors']++;
                    $results['details'][] = [
                        'employee_id' => $employee['id'],
                        'name' => $employee['name'],
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }
        } catch (Exception $e) {
            $results['errors']++;
            $results['details'][] = [
                'status' => 'fatal_error',
                'message' => $e->getMessage()
            ];
        }
        
        return $results;
    }
    
    /**
     * Get all employees eligible for accrual
     */
    private function getEligibleEmployees() {
        $stmt = $this->pdo->prepare("
            SELECT 
                id, 
                name, 
                email,
                department,
                vacation_leave_balance,
                sick_leave_balance,
                special_privilege_leave_balance,
                last_leave_credit_update,
                service_start_date,
                created_at,
                account_status
            FROM employees 
            WHERE role = 'employee' 
            AND account_status = 'active'
            ORDER BY id ASC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Accrue credits for a single employee
     */
    private function accrueCreditsForEmployee($employee, $currentDate, $isJanuary) {
        $employeeId = $employee['id'];
        $lastUpdate = $employee['last_leave_credit_update'];
        
        // Check if employee should receive accrual
        $eligibilityCheck = $this->checkAccrualEligibility($employee, $currentDate);
        
        if (!$eligibilityCheck['eligible']) {
            return [
                'success' => false,
                'message' => $eligibilityCheck['reason']
            ];
        }
        
        // Begin transaction
        $this->pdo->beginTransaction();
        
        try {
            // Calculate new balances
            $newVacationBalance = $employee['vacation_leave_balance'] + self::MONTHLY_VACATION_ACCRUAL;
            $newSickBalance = $employee['sick_leave_balance'] + self::MONTHLY_SICK_ACCRUAL;
            $newSLPBalance = $employee['special_privilege_leave_balance'];
            
            // Reset SLP in January
            if ($isJanuary) {
                $newSLPBalance = self::ANNUAL_SLP_ACCRUAL;
            }
            
            // Update employee balances
            $updateStmt = $this->pdo->prepare("
                UPDATE employees 
                SET 
                    vacation_leave_balance = ?,
                    sick_leave_balance = ?,
                    special_privilege_leave_balance = ?,
                    last_leave_credit_update = ?
                WHERE id = ?
            ");
            
            $updateStmt->execute([
                $newVacationBalance,
                $newSickBalance,
                $newSLPBalance,
                $currentDate,
                $employeeId
            ]);
            
            // Log vacation leave accrual
            $this->logCreditHistory($employeeId, 'vacation', self::MONTHLY_VACATION_ACCRUAL, $currentDate);
            
            // Log sick leave accrual
            $this->logCreditHistory($employeeId, 'sick', self::MONTHLY_SICK_ACCRUAL, $currentDate);
            
            // Log SLP if January
            if ($isJanuary) {
                $this->logCreditHistory($employeeId, 'special_privilege', self::ANNUAL_SLP_ACCRUAL, $currentDate);
            }
            
            $this->pdo->commit();
            
            $slpNote = $isJanuary ? ", SLP: " . self::ANNUAL_SLP_ACCRUAL : "";
            $message = "Accrued VL: " . self::MONTHLY_VACATION_ACCRUAL . ", SL: " . self::MONTHLY_SICK_ACCRUAL . $slpNote;
            
            return [
                'success' => true,
                'message' => $message
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Check if employee is eligible for accrual this month
     */
    private function checkAccrualEligibility($employee, $currentDate) {
        $lastUpdate = $employee['last_leave_credit_update'];
        
        // First time accrual
        if (empty($lastUpdate)) {
            $serviceStart = $employee['service_start_date'] ?: $employee['created_at'];
            $serviceStartDate = new DateTime($serviceStart);
            $currentDateTime = new DateTime($currentDate);
            
            $interval = $serviceStartDate->diff($currentDateTime);
            $monthsInService = ($interval->y * 12) + $interval->m;
            
            if ($monthsInService < 1) {
                return [
                    'eligible' => false,
                    'reason' => 'Less than 1 month in service'
                ];
            }
            
            return [
                'eligible' => true,
                'reason' => "First accrual (in service for $monthsInService months)"
            ];
        }
        
        // Check if already accrued this month
        $lastUpdateDate = new DateTime($lastUpdate);
        $lastUpdateMonth = $lastUpdateDate->format('Y-m');
        $currentMonthStr = date('Y-m', strtotime($currentDate));
        
        if ($lastUpdateMonth >= $currentMonthStr) {
            return [
                'eligible' => false,
                'reason' => 'Already accrued this month'
            ];
        }
        
        return [
            'eligible' => true,
            'reason' => "Monthly accrual (last update: $lastUpdate)"
        ];
    }
    
    /**
     * Log credit history
     */
    private function logCreditHistory($employeeId, $creditType, $amount, $accrualDate) {
        $stmt = $this->pdo->prepare("
            INSERT INTO leave_credit_history 
            (employee_id, credit_type, credit_amount, accrual_date, service_days, created_at) 
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        
        $stmt->execute([$employeeId, $creditType, $amount, $accrualDate]);
    }
    
    /**
     * Get accrual history for an employee
     */
    public function getEmployeeAccrualHistory($employeeId, $limit = 12) {
        $stmt = $this->pdo->prepare("
            SELECT 
                credit_type,
                credit_amount,
                accrual_date,
                created_at
            FROM leave_credit_history
            WHERE employee_id = ?
            ORDER BY accrual_date DESC, created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$employeeId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Manually trigger accrual for a specific employee
     */
    public function manualAccrualForEmployee($employeeId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                id, 
                name, 
                email,
                department,
                vacation_leave_balance,
                sick_leave_balance,
                special_privilege_leave_balance,
                last_leave_credit_update,
                service_start_date,
                created_at,
                account_status
            FROM employees 
            WHERE id = ?
        ");
        
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            throw new Exception("Employee not found");
        }
        
        $currentDate = date('Y-m-d');
        $isJanuary = (date('m') == '01');
        
        return $this->accrueCreditsForEmployee($employee, $currentDate, $isJanuary);
    }
}
?>
