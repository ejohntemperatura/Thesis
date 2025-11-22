<?php
/**
 * Comprehensive Report Service for ELMS
 * Handles all report generation, filtering, and export functionality
 */

require_once __DIR__ . '/../../../config/leave_types.php';

class ReportService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get comprehensive system statistics
     */
    public function getSystemStats($startDate, $endDate, $filters = []) {
        $whereConditions = ["lr.start_date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        // Apply filters
        if (!empty($filters['employee_id'])) {
            $whereConditions[] = "lr.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['department'])) {
            $whereConditions[] = "e.department = ?";
            $params[] = $filters['department'];
        }
        
        if (!empty($filters['leave_type'])) {
            $whereConditions[] = "lr.leave_type = ?";
            $params[] = $filters['leave_type'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Basic statistics
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
                SUM(CASE WHEN lr.status = 'under_appeal' THEN 1 ELSE 0 END) as appeal_requests,
                SUM(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 AND lr.status = 'approved'
                    THEN lr.approved_days
                    ELSE lr.days_requested
                END) as total_days_requested,
                AVG(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 AND lr.status = 'approved'
                    THEN lr.approved_days
                    ELSE lr.days_requested
                END) as avg_days_per_request
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            WHERE $whereClause
            AND e.role = 'employee' 
            AND e.department NOT IN ('Executive', 'Operations')
            AND e.position NOT LIKE '%Department Head%'
            AND e.position NOT LIKE '%Director Head%'
        ");
        $stmt->execute($params);
        $stats = $stmt->fetch();
        
        // Calculate additional metrics
        $stats['approval_rate'] = $stats['total_requests'] > 0 ? 
            round(($stats['approved_requests'] / $stats['total_requests']) * 100, 2) : 0;
        $stats['rejection_rate'] = $stats['total_requests'] > 0 ? 
            round(($stats['rejected_requests'] / $stats['total_requests']) * 100, 2) : 0;
        $stats['pending_rate'] = $stats['total_requests'] > 0 ? 
            round(($stats['pending_requests'] / $stats['total_requests']) * 100, 2) : 0;
            
        return $stats;
    }
    
    /**
     * Get department-wise statistics
     */
    public function getDepartmentStats($startDate, $endDate, $filters = []) {
        $whereConditions = ["lr.start_date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if (!empty($filters['employee_id'])) {
            $whereConditions[] = "lr.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['leave_type'])) {
            $whereConditions[] = "lr.leave_type = ?";
            $params[] = $filters['leave_type'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                e.department,
                COUNT(DISTINCT e.id) as total_employees,
                COUNT(lr.id) as total_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
                SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 AND lr.status = 'approved'
                    THEN lr.approved_days
                    ELSE lr.days_requested
                END) as total_days_requested,
                AVG(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 AND lr.status = 'approved'
                    THEN lr.approved_days
                    ELSE lr.days_requested
                END) as avg_days_per_request
            FROM employees e
            LEFT JOIN leave_requests lr ON e.id = lr.employee_id AND $whereClause
            WHERE e.role = 'employee' 
            AND e.department NOT IN ('Executive', 'Operations')
            AND e.position NOT LIKE '%Department Head%'
            AND e.position NOT LIKE '%Director Head%'
            GROUP BY e.department
            ORDER BY total_requests DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get department-wise counts of approved leave requests with pay vs without pay
     */
    public function getDepartmentPayStatusSummary($startDate, $endDate, $filters = []) {
        // Only constrain by date; ignore employee/department filters for global summary
        $whereConditions = ["lr.start_date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        $whereClause = implode(' AND ', $whereConditions);

        $sql = "
            SELECT 
                e.department,
                SUM(CASE WHEN lr.status = 'approved' AND lr.pay_status = 'with_pay' THEN 1 ELSE 0 END) AS with_pay_count,
                SUM(CASE WHEN lr.status = 'approved' AND (lr.pay_status = 'without_pay' OR lr.leave_type = 'without_pay') THEN 1 ELSE 0 END) AS without_pay_count
            FROM employees e
            LEFT JOIN leave_requests lr ON e.id = lr.employee_id AND $whereClause
            WHERE e.role = 'employee' 
            AND e.department NOT IN ('Executive', 'Operations')
            AND e.position NOT LIKE '%Department Head%'
            AND e.position NOT LIKE '%Director Head%'
            GROUP BY e.department
            ORDER BY e.department ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get leave type statistics
     */
    public function getLeaveTypeStats($startDate, $endDate, $filters = []) {
        $whereConditions = ["lr.start_date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if (!empty($filters['employee_id'])) {
            $whereConditions[] = "lr.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['department'])) {
            $whereConditions[] = "e.department = ?";
            $params[] = $filters['department'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                lr.leave_type,
                COUNT(*) as total_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
                SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 AND lr.status = 'approved'
                    THEN lr.approved_days
                    ELSE lr.days_requested
                END) as total_days_requested,
                AVG(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 AND lr.status = 'approved'
                    THEN lr.approved_days
                    ELSE lr.days_requested
                END) as avg_days_per_request
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            WHERE $whereClause
            GROUP BY lr.leave_type
            ORDER BY total_requests DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get monthly trends
     */
    public function getMonthlyTrends($startDate, $endDate, $filters = []) {
        $whereConditions = ["lr.start_date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if (!empty($filters['employee_id'])) {
            $whereConditions[] = "lr.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['department'])) {
            $whereConditions[] = "e.department = ?";
            $params[] = $filters['department'];
        }
        
        if (!empty($filters['leave_type'])) {
            $whereConditions[] = "lr.leave_type = ?";
            $params[] = $filters['leave_type'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE_FORMAT(lr.start_date, '%Y-%m') as month,
                COUNT(*) as total_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
                SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 AND lr.status = 'approved'
                    THEN lr.approved_days
                    ELSE lr.days_requested
                END) as total_days_requested
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            WHERE $whereClause
            GROUP BY DATE_FORMAT(lr.start_date, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get employee performance data
     */
    public function getEmployeePerformance($startDate, $endDate, $filters = []) {
        $whereConditions = ["lr.start_date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if (!empty($filters['employee_id'])) {
            $whereConditions[] = "lr.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['department'])) {
            $whereConditions[] = "e.department = ?";
            $params[] = $filters['department'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id,
                e.name,
                e.department,
                e.position,
                COUNT(lr.id) as total_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
                SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 AND lr.status = 'approved'
                    THEN lr.approved_days
                    ELSE lr.days_requested
                END) as total_days_requested,
                e.vacation_leave_balance,
                e.sick_leave_balance,
                e.special_leave_privilege_balance,
                e.maternity_leave_balance,
                e.paternity_leave_balance,
                e.solo_parent_leave_balance,
                e.vawc_leave_balance,
                e.rehabilitation_leave_balance,
                e.terminal_leave_balance
            FROM employees e
            LEFT JOIN leave_requests lr ON e.id = lr.employee_id AND $whereClause
            GROUP BY e.id, e.name, e.department, e.position
            HAVING total_requests > 0
            ORDER BY total_requests DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get DTR (Daily Time Record) data
     */
    public function getDTRData($startDate, $endDate, $filters = []) {
        $whereConditions = ["d.date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if (!empty($filters['employee_id'])) {
            $whereConditions[] = "d.user_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['department'])) {
            $whereConditions[] = "e.department = ?";
            $params[] = $filters['department'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id as employee_id,
                e.name as employee_name,
                e.department,
                d.date,
                d.morning_time_in,
                d.morning_time_out,
                d.afternoon_time_in,
                d.afternoon_time_out,
                CASE 
                    WHEN d.morning_time_in IS NOT NULL AND d.morning_time_out IS NOT NULL 
                         AND d.afternoon_time_in IS NOT NULL AND d.afternoon_time_out IS NOT NULL 
                    THEN 'Complete'
                    WHEN d.morning_time_in IS NOT NULL AND d.morning_time_out IS NOT NULL 
                    THEN 'Half Day (Morning)'
                    WHEN d.afternoon_time_in IS NOT NULL AND d.afternoon_time_out IS NOT NULL 
                    THEN 'Half Day (Afternoon)'
                    WHEN d.morning_time_in IS NOT NULL OR d.afternoon_time_in IS NOT NULL 
                    THEN 'Incomplete'
                    ELSE 'Absent'
                END as attendance_status,
                CASE 
                    WHEN d.morning_time_in IS NOT NULL AND d.morning_time_out IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, d.morning_time_in, d.morning_time_out) / 60.0
                    ELSE 0
                END as morning_hours,
                CASE 
                    WHEN d.afternoon_time_in IS NOT NULL AND d.afternoon_time_out IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, d.afternoon_time_in, d.afternoon_time_out) / 60.0
                    ELSE 0
                END as afternoon_hours,
                (CASE 
                    WHEN d.morning_time_in IS NOT NULL AND d.morning_time_out IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, d.morning_time_in, d.morning_time_out) / 60.0
                    ELSE 0
                END + 
                CASE 
                    WHEN d.afternoon_time_in IS NOT NULL AND d.afternoon_time_out IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, d.afternoon_time_in, d.afternoon_time_out) / 60.0
                    ELSE 0
                END) as total_hours
            FROM dtr d
            JOIN employees e ON d.user_id = e.id
            WHERE $whereClause
            ORDER BY d.date, e.name
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get attendance summary
     */
    public function getAttendanceSummary($startDate, $endDate, $filters = []) {
        $dtrData = $this->getDTRData($startDate, $endDate, $filters);
        
        $summary = [
            'total_days' => count($dtrData),
            'complete_days' => count(array_filter($dtrData, function($dtr) { 
                return $dtr['attendance_status'] === 'Complete'; 
            })),
            'half_days' => count(array_filter($dtrData, function($dtr) { 
                return strpos($dtr['attendance_status'], 'Half Day') !== false; 
            })),
            'incomplete_days' => count(array_filter($dtrData, function($dtr) { 
                return $dtr['attendance_status'] === 'Incomplete'; 
            })),
            'absent_days' => count(array_filter($dtrData, function($dtr) { 
                return $dtr['attendance_status'] === 'Absent'; 
            })),
            'total_hours' => array_sum(array_column($dtrData, 'total_hours')),
            'avg_hours_per_day' => count($dtrData) > 0 ? 
                array_sum(array_column($dtrData, 'total_hours')) / count($dtrData) : 0
        ];
        
        return $summary;
    }
    
    /**
     * Get all employees for dropdown (excluding management roles)
     */
    public function getEmployees() {
        $stmt = $this->pdo->prepare("
            SELECT id, name, department, position, email 
            FROM employees 
            WHERE account_status = 'active' 
            AND role = 'employee'
            AND department NOT IN ('Executive', 'Operations')
            AND position NOT LIKE '%Department Head%'
            AND position NOT LIKE '%Director Head%'
            ORDER BY department, name
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get all departments (excluding Executive, Operations, and management roles)
     */
    public function getDepartments() {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT department 
            FROM employees 
            WHERE department IS NOT NULL AND department != '' 
            AND department NOT IN ('Executive', 'Operations')
            AND position NOT LIKE '%Department Head%'
            AND position NOT LIKE '%Director Head%'
            ORDER BY department
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get leave types from configuration
     */
    public function getLeaveTypes() {
        return getLeaveTypes();
    }
    
    /**
     * Export data to CSV
     */
    public function exportToCSV($data, $filename, $headers = []) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        if (!empty($headers)) {
            fputcsv($output, $headers);
        }
        
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    }
    
    /**
     * Get leave requests for export
     */
    public function getLeaveRequestsForExport($startDate, $endDate, $filters = []) {
        $whereConditions = ["lr.start_date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if (!empty($filters['employee_id'])) {
            $whereConditions[] = "lr.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['department'])) {
            $whereConditions[] = "e.department = ?";
            $params[] = $filters['department'];
        }
        
        if (!empty($filters['leave_type'])) {
            $whereConditions[] = "lr.leave_type = ?";
            $params[] = $filters['leave_type'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                e.name as employee_name,
                e.department,
                e.position,
                lr.leave_type,
                lr.start_date,
                lr.end_date,
                CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 AND lr.status = 'approved'
                    THEN lr.approved_days
                    ELSE lr.days_requested
                END as days_requested,
                lr.reason,
                lr.status,
                lr.location_type,
                lr.medical_condition,
                lr.is_late,
                lr.created_at
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            WHERE $whereClause
            ORDER BY lr.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get comprehensive leave credits report (excluding management roles)
     */
    public function getLeaveCreditsReport($filters = []) {
        $whereConditions = [
            "role = 'employee'",
            "department NOT IN ('Executive', 'Operations')",
            "position NOT LIKE '%Department Head%'",
            "position NOT LIKE '%Director Head%'"
        ];
        $params = [];
        
        if (!empty($filters['employee_id'])) {
            $whereConditions[] = "id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['department'])) {
            $whereConditions[] = "department = ?";
            $params[] = $filters['department'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                name,
                email,
                department,
                position,
                vacation_leave_balance,
                sick_leave_balance,
                special_leave_privilege_balance,
                maternity_leave_balance,
                paternity_leave_balance,
                solo_parent_leave_balance,
                vawc_leave_balance,
                rehabilitation_leave_balance,
                terminal_leave_balance,
                last_leave_credit_update,
                created_at
            FROM employees 
            WHERE $whereClause
            ORDER BY department, name
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get system utilization metrics
     */
    public function getSystemUtilizationMetrics($startDate, $endDate, $filters = []) {
        $whereConditions = ["lr.start_date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if (!empty($filters['employee_id'])) {
            $whereConditions[] = "lr.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['department'])) {
            $whereConditions[] = "e.department = ?";
            $params[] = $filters['department'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total employees
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total_employees 
            FROM employees 
            WHERE account_status = 'active'
        ");
        $stmt->execute();
        $totalEmployees = $stmt->fetch()['total_employees'];
        
        // Get employees who submitted leave requests
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT lr.employee_id) as active_employees
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            WHERE $whereClause
        ");
        $stmt->execute($params);
        $activeEmployees = $stmt->fetch()['active_employees'];
        
        // Get system usage statistics
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_requests,
                COUNT(DISTINCT lr.employee_id) as unique_employees,
                AVG(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 AND lr.status = 'approved'
                    THEN lr.approved_days
                    ELSE lr.days_requested
                END) as avg_days_per_request,
                SUM(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 AND lr.status = 'approved'
                    THEN lr.approved_days
                    ELSE lr.days_requested
                END) as total_days_used
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            WHERE $whereClause
        ");
        $stmt->execute($params);
        $usageStats = $stmt->fetch();
        
        return [
            'total_employees' => $totalEmployees,
            'active_employees' => $activeEmployees,
            'employee_participation_rate' => $totalEmployees > 0 ? 
                round(($activeEmployees / $totalEmployees) * 100, 2) : 0,
            'total_requests' => $usageStats['total_requests'],
            'unique_employees' => $usageStats['unique_employees'],
            'avg_days_per_request' => round($usageStats['avg_days_per_request'], 2),
            'total_days_used' => $usageStats['total_days_used']
        ];
    }
    
    /**
     * Get compliance and policy adherence metrics
     */
    public function getComplianceMetrics($startDate, $endDate, $filters = []) {
        $whereConditions = ["lr.start_date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if (!empty($filters['employee_id'])) {
            $whereConditions[] = "lr.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['department'])) {
            $whereConditions[] = "e.department = ?";
            $params[] = $filters['department'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN lr.is_late = 1 THEN 1 ELSE 0 END) as late_submissions,
                SUM(CASE WHEN lr.medical_certificate_path IS NOT NULL THEN 1 ELSE 0 END) as with_medical_cert,
                SUM(CASE WHEN lr.status = 'under_appeal' THEN 1 ELSE 0 END) as appeals,
                AVG(CASE 
                    WHEN lr.created_at IS NOT NULL AND lr.start_date IS NOT NULL
                    THEN DATEDIFF(lr.start_date, lr.created_at)
                    ELSE NULL
                END) as avg_advance_notice_days
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            WHERE $whereClause
        ");
        $stmt->execute($params);
        $compliance = $stmt->fetch();
        
        return [
            'total_requests' => $compliance['total_requests'],
            'late_submissions' => $compliance['late_submissions'],
            'late_submission_rate' => $compliance['total_requests'] > 0 ? 
                round(($compliance['late_submissions'] / $compliance['total_requests']) * 100, 2) : 0,
            'with_medical_cert' => $compliance['with_medical_cert'],
            'medical_cert_rate' => $compliance['total_requests'] > 0 ? 
                round(($compliance['with_medical_cert'] / $compliance['total_requests']) * 100, 2) : 0,
            'appeals' => $compliance['appeals'],
            'appeal_rate' => $compliance['total_requests'] > 0 ? 
                round(($compliance['appeals'] / $compliance['total_requests']) * 100, 2) : 0,
            'avg_advance_notice_days' => round($compliance['avg_advance_notice_days'], 1)
        ];
    }
    
    /**
     * Get financial impact analysis
     */
    public function getFinancialImpactAnalysis($startDate, $endDate, $filters = []) {
        $whereConditions = ["lr.start_date BETWEEN ? AND ? AND lr.status = 'approved'"];
        $params = [$startDate, $endDate];
        
        if (!empty($filters['employee_id'])) {
            $whereConditions[] = "lr.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['department'])) {
            $whereConditions[] = "e.department = ?";
            $params[] = $filters['department'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                lr.leave_type,
                COUNT(*) as approved_requests,
                SUM(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0
                    THEN lr.approved_days
                    ELSE lr.days_requested
                END) as total_days,
                AVG(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0
                    THEN lr.approved_days
                    ELSE lr.days_requested
                END) as avg_days_per_request
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            WHERE $whereClause
            GROUP BY lr.leave_type
            ORDER BY total_days DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Generate comprehensive report data
     */
    public function generateComprehensiveReport($startDate, $endDate, $filters = []) {
        return [
            'system_stats' => $this->getSystemStats($startDate, $endDate, $filters),
            'department_stats' => $this->getDepartmentStats($startDate, $endDate, $filters),
            'leave_type_stats' => $this->getLeaveTypeStats($startDate, $endDate, $filters),
            'monthly_trends' => $this->getMonthlyTrends($startDate, $endDate, $filters),
            'employee_performance' => $this->getEmployeePerformance($startDate, $endDate, $filters),
            'dtr_data' => $this->getDTRData($startDate, $endDate, $filters),
            'attendance_summary' => $this->getAttendanceSummary($startDate, $endDate, $filters),
            'utilization_metrics' => $this->getSystemUtilizationMetrics($startDate, $endDate, $filters),
            'compliance_metrics' => $this->getComplianceMetrics($startDate, $endDate, $filters),
            'financial_impact' => $this->getFinancialImpactAnalysis($startDate, $endDate, $filters)
        ];
    }
}
?>