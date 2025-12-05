<?php
/**
 * PDF Report Generator for ELMS
 * Generates comprehensive PDF reports with leave details and analytics
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/leave_types.php';

class PDFReportGenerator {
    private $pdo;
    private $pdf;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate comprehensive PDF report with leave and analytics details
     */
    public function generateComprehensiveReport($startDate, $endDate, $department = null, $employeeId = null) {
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Get report data
        $leaveData = $this->getLeaveRequestsData($startDate, $endDate, $department, $employeeId);
        $systemStats = $this->getSystemStats($startDate, $endDate, $department, $employeeId);
        $departmentStats = $this->getDepartmentStats($startDate, $endDate, $department, $employeeId);
        $leaveTypeStats = $this->getLeaveTypeStats($startDate, $endDate, $department, $employeeId);
        
        // Create PDF
        $this->createPDF($leaveData, $systemStats, $departmentStats, $leaveTypeStats, $startDate, $endDate);
    }
    
    /**
     * Get leave requests data with detailed information
     */
    private function getLeaveRequestsData($startDate, $endDate, $department = null, $employeeId = null) {
        $sql = "
            SELECT 
                lr.*,
                e.name as employee_name,
                e.position,
                e.department,
                e.email,
                CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 
                    THEN lr.approved_days
                    ELSE DATEDIFF(lr.end_date, lr.start_date) + 1 
                END as actual_days_approved
            FROM leave_requests lr 
            JOIN employees e ON lr.employee_id = e.id 
            WHERE lr.start_date >= ? AND lr.end_date <= ?
            AND e.role = 'employee' 
            AND e.department NOT IN ('Executive', 'Operations')
            AND e.position NOT LIKE '%Department Head%'
            AND e.position NOT LIKE '%Director Head%'
            AND e.role = 'employee'
        ";
        
        $params = [$startDate, $endDate];
        
        if ($department) {
            $sql .= " AND e.department = ?";
            $params[] = $department;
        }
        
        if ($employeeId) {
            $sql .= " AND lr.employee_id = ?";
            $params[] = $employeeId;
        }
        
        $sql .= " ORDER BY lr.start_date ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get system statistics
     */
    private function getSystemStats($startDate, $endDate, $department = null, $employeeId = null) {
        $sql = "
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
                AVG(CASE 
                    WHEN approved_days IS NOT NULL AND approved_days > 0 
                    THEN approved_days
                    ELSE DATEDIFF(end_date, start_date) + 1 
                END) as avg_days_per_request
            FROM leave_requests lr 
            JOIN employees e ON lr.employee_id = e.id 
            WHERE lr.start_date >= ? AND lr.end_date <= ?
            AND e.role = 'employee' 
            AND e.department NOT IN ('Executive', 'Operations')
            AND e.position NOT LIKE '%Department Head%'
            AND e.position NOT LIKE '%Director Head%'
            AND e.role = 'employee' 
            AND e.department NOT IN ('Executive', 'Operations')
            AND e.position NOT LIKE '%Department Head%'
            AND e.position NOT LIKE '%Director Head%'
        ";
        
        $params = [$startDate, $endDate];
        
        if ($department) {
            $sql .= " AND e.department = ?";
            $params[] = $department;
        }
        
        if ($employeeId) {
            $sql .= " AND lr.employee_id = ?";
            $params[] = $employeeId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get department statistics
     */
    private function getDepartmentStats($startDate, $endDate, $department = null, $employeeId = null) {
        $sql = "
            SELECT 
                e.department,
                COUNT(*) as total_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                AVG(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 
                    THEN lr.approved_days
                    ELSE DATEDIFF(lr.end_date, lr.start_date) + 1 
                END) as avg_days
            FROM leave_requests lr 
            JOIN employees e ON lr.employee_id = e.id 
            WHERE lr.start_date >= ? AND lr.end_date <= ?
            AND e.role = 'employee' 
            AND e.department NOT IN ('Executive', 'Operations')
            AND e.position NOT LIKE '%Department Head%'
            AND e.position NOT LIKE '%Director Head%'
        ";
        
        $params = [$startDate, $endDate];
        
        if ($department) {
            $sql .= " AND e.department = ?";
            $params[] = $department;
        }
        
        if ($employeeId) {
            $sql .= " AND lr.employee_id = ?";
            $params[] = $employeeId;
        }
        
        $sql .= " GROUP BY e.department ORDER BY total_requests DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get leave type statistics
     */
    private function getLeaveTypeStats($startDate, $endDate, $department = null, $employeeId = null) {
        $sql = "
            SELECT 
                lr.leave_type,
                COUNT(*) as total_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                AVG(CASE 
                    WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 
                    THEN lr.approved_days
                    ELSE DATEDIFF(lr.end_date, lr.start_date) + 1 
                END) as avg_days
            FROM leave_requests lr 
            JOIN employees e ON lr.employee_id = e.id 
            WHERE lr.start_date >= ? AND lr.end_date <= ?
            AND e.role = 'employee' 
            AND e.department NOT IN ('Executive', 'Operations')
            AND e.position NOT LIKE '%Department Head%'
            AND e.position NOT LIKE '%Director Head%'
        ";
        
        $params = [$startDate, $endDate];
        
        if ($department) {
            $sql .= " AND e.department = ?";
            $params[] = $department;
        }
        
        if ($employeeId) {
            $sql .= " AND lr.employee_id = ?";
            $params[] = $employeeId;
        }
        
        $sql .= " GROUP BY lr.leave_type ORDER BY total_requests DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create PDF document
     */
    private function createPDF($leaveData, $systemStats, $departmentStats, $leaveTypeStats, $startDate, $endDate) {
        // Create new PDF document
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $this->pdf->SetCreator('ELMS - Employee Leave Management System');
        $this->pdf->SetAuthor('ELMS System');
        $this->pdf->SetTitle('Leave Management Report');
        $this->pdf->SetSubject('Comprehensive Leave Report');
        
        // Set default header data
        $this->pdf->SetHeaderData('', 0, 'ELMS Leave Management Report', 'Generated on ' . date('Y-m-d H:i:s'));
        
        // Set header and footer fonts
        $this->pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $this->pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        
        // Set default monospaced font
        $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        
        // Set margins
        $this->pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $this->pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $this->pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        
        // Set auto page breaks
        $this->pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        
        // Set image scale factor
        $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // Add a page
        $this->pdf->AddPage();
        
        // Set font
        $this->pdf->SetFont('helvetica', 'B', 16);
        
        // Report title
        $this->pdf->Cell(0, 15, 'LEAVE MANAGEMENT SYSTEM REPORT', 0, 1, 'C');
        $this->pdf->Ln(5);
        
        // Report period
        $this->pdf->SetFont('helvetica', '', 12);
        $this->pdf->Cell(0, 10, 'Report Period: ' . date('F j, Y', strtotime($startDate)) . ' - ' . date('F j, Y', strtotime($endDate)), 0, 1, 'C');
        $this->pdf->Ln(10);
        
        // System Statistics Section
        $this->addSystemStatsSection($systemStats);
        
        // Department Statistics Section
        $this->addDepartmentStatsSection($departmentStats);
        
        // Leave Type Statistics Section
        $this->addLeaveTypeStatsSection($leaveTypeStats);
        
        // Leave Requests Details Section
        $this->addLeaveRequestsSection($leaveData);
        
        // Output PDF
        $filename = "ELMS_Report_" . date('Y-m-d_H-i-s') . ".pdf";
        $this->pdf->Output($filename, 'D');
        exit();
    }
    
    /**
     * Add system statistics section
     */
    private function addSystemStatsSection($systemStats) {
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetFillColor(59, 130, 246);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell(0, 10, 'SYSTEM STATISTICS', 0, 1, 'C', true);
        $this->pdf->Ln(5);
        
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('helvetica', '', 10);
        
        // Create table for system stats
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->Cell(60, 8, 'Total Requests', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $systemStats['total_requests'], 1, 0, 'C');
        $this->pdf->Ln();
        
        $this->pdf->Cell(60, 8, 'Approved Requests', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $systemStats['approved_requests'], 1, 0, 'C');
        $this->pdf->Ln();
        
        $this->pdf->Cell(60, 8, 'Pending Requests', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $systemStats['pending_requests'], 1, 0, 'C');
        $this->pdf->Ln();
        
        $this->pdf->Cell(60, 8, 'Rejected Requests', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $systemStats['rejected_requests'], 1, 0, 'C');
        $this->pdf->Ln();
        
        $this->pdf->Cell(60, 8, 'Average Days per Request', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, number_format($systemStats['avg_days_per_request'], 2), 1, 0, 'C');
        $this->pdf->Ln(15);
    }
    
    /**
     * Add department statistics section
     */
    private function addDepartmentStatsSection($departmentStats) {
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetFillColor(16, 185, 129);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell(0, 10, 'DEPARTMENT STATISTICS', 0, 1, 'C', true);
        $this->pdf->Ln(5);
        
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('helvetica', '', 9);
        
        // Table header
        $this->pdf->SetFillColor(200, 200, 200);
        $this->pdf->Cell(60, 8, 'Department', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Total Requests', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Approved', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Avg Days', 1, 0, 'C', true);
        $this->pdf->Ln();
        
        // Table data
        foreach ($departmentStats as $dept) {
            $this->pdf->Cell(60, 8, $dept['department'], 1, 0, 'L');
            $this->pdf->Cell(30, 8, $dept['total_requests'], 1, 0, 'C');
            $this->pdf->Cell(30, 8, $dept['approved_requests'], 1, 0, 'C');
            $this->pdf->Cell(30, 8, number_format($dept['avg_days'], 1), 1, 0, 'C');
            $this->pdf->Ln();
        }
        $this->pdf->Ln(10);
    }
    
    /**
     * Add leave type statistics section
     */
    private function addLeaveTypeStatsSection($leaveTypeStats) {
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetFillColor(245, 101, 101);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell(0, 10, 'LEAVE TYPE STATISTICS', 0, 1, 'C', true);
        $this->pdf->Ln(5);
        
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('helvetica', '', 9);
        
        // Table header
        $this->pdf->SetFillColor(200, 200, 200);
        $this->pdf->Cell(50, 8, 'Leave Type', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Total Requests', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Approved', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Avg Days', 1, 0, 'C', true);
        $this->pdf->Ln();
        
        // Table data
        foreach ($leaveTypeStats as $leaveType) {
            $displayName = ucwords(str_replace('_', ' ', $leaveType['leave_type']));
            $this->pdf->Cell(50, 8, $displayName, 1, 0, 'L');
            $this->pdf->Cell(30, 8, $leaveType['total_requests'], 1, 0, 'C');
            $this->pdf->Cell(30, 8, $leaveType['approved_requests'], 1, 0, 'C');
            $this->pdf->Cell(30, 8, number_format($leaveType['avg_days'], 1), 1, 0, 'C');
            $this->pdf->Ln();
        }
        $this->pdf->Ln(10);
    }
    
    /**
     * Add leave requests details section with organized layout
     */
    private function addLeaveRequestsSection($leaveData) {
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetFillColor(139, 69, 19);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell(0, 10, 'LEAVE REQUESTS BY DEPARTMENT', 0, 1, 'C', true);
        $this->pdf->Ln(5);
        
        // Group leave requests by department, then by employee
        $groupedData = [];
        foreach ($leaveData as $request) {
            $groupedData[$request['department']][$request['employee_name']][] = $request;
        }
        
        // Sort departments alphabetically
        ksort($groupedData);
        
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('helvetica', '', 8);
        
        foreach ($groupedData as $department => $employees) {
            // Department header
            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->SetFillColor(59, 130, 246);
            $this->pdf->SetTextColor(255, 255, 255);
            $totalRequests = array_sum(array_map('count', $employees));
            $this->pdf->Cell(0, 8, $department . ' (' . $totalRequests . ' requests)', 0, 1, 'L', true);
            $this->pdf->Ln(2);
            
            // Sort employees alphabetically within department
            ksort($employees);
            
            foreach ($employees as $employeeName => $requests) {
                // Employee header
                $this->pdf->SetFont('helvetica', 'B', 10);
                $this->pdf->SetFillColor(16, 185, 129);
                $this->pdf->SetTextColor(255, 255, 255);
                $this->pdf->Cell(0, 6, $employeeName . ' (' . count($requests) . ' requests)', 0, 1, 'L', true);
                $this->pdf->Ln(1);
                
                // Table header
                $this->pdf->SetFont('helvetica', 'B', 8);
                $this->pdf->SetFillColor(200, 200, 200);
                $this->pdf->SetTextColor(0, 0, 0);
                $this->pdf->Cell(30, 6, 'Leave Type', 1, 0, 'C', true);
                $this->pdf->Cell(50, 6, 'Selected Dates', 1, 0, 'C', true);
                $this->pdf->Cell(12, 6, 'Days', 1, 0, 'C', true);
                $this->pdf->Cell(15, 6, 'Status', 1, 0, 'C', true);
                $this->pdf->Cell(20, 6, 'Applied Date', 1, 0, 'C', true);
                $this->pdf->Ln();
                
                // Table data for this employee
                $this->pdf->SetFont('helvetica', '', 7);
                $leaveTypes = getLeaveTypes();
                
                foreach ($requests as $request) {
                    // Get proper leave type display name
                    $leaveType = $request['leave_type'];
                    $originalLeaveType = $request['original_leave_type'] ?? null;
                    
                    // Handle empty or null leave types
                    if (empty($leaveType)) {
                        $leaveType = $originalLeaveType ?: 'vacation';
                    }
                    
                    // Use getLeaveTypeDisplayName if available, otherwise format manually
                    if (function_exists('getLeaveTypeDisplayName')) {
                        $displayName = getLeaveTypeDisplayName($leaveType, $originalLeaveType, $leaveTypes);
                    } else {
                        $displayName = isset($leaveTypes[$leaveType]) ? $leaveTypes[$leaveType]['name'] : ucwords(str_replace('_', ' ', $leaveType));
                    }
                    
                    // Final fallback if still empty
                    if (empty($displayName) || trim($displayName) === '') {
                        $displayName = 'Leave';
                    }
                    
                    // Shorten long names
                    if (strlen($displayName) > 20) {
                        $displayName = substr($displayName, 0, 18) . '..';
                    }
                    
                    // Format selected dates
                    $selectedDatesText = '';
                    if (!empty($request['selected_dates'])) {
                        $dates = explode(',', $request['selected_dates']);
                        $formattedDates = array_map(function($date) {
                            return date('m/d', strtotime(trim($date)));
                        }, $dates);
                        $selectedDatesText = implode(', ', $formattedDates);
                        // Truncate if too long
                        if (strlen($selectedDatesText) > 45) {
                            $selectedDatesText = substr($selectedDatesText, 0, 42) . '...';
                        }
                    } else {
                        // Fallback to date range if no selected dates
                        $selectedDatesText = date('m/d', strtotime($request['start_date'])) . ' - ' . date('m/d', strtotime($request['end_date']));
                    }
                    
                    $this->pdf->Cell(30, 6, $displayName, 1, 0, 'L');
                    $this->pdf->Cell(50, 6, $selectedDatesText, 1, 0, 'L');
                    $this->pdf->Cell(12, 6, $request['actual_days_approved'], 1, 0, 'C');
                    $this->pdf->Cell(15, 6, ucfirst($request['status']), 1, 0, 'C');
                    $this->pdf->Cell(20, 6, date('m/d/Y', strtotime($request['created_at'])), 1, 0, 'C');
                    $this->pdf->Ln();
                }
                
                $this->pdf->Ln(3);
            }
            
            $this->pdf->Ln(5);
        }
    }
}
?>
