<?php
/**
 * PDF Leave Requests Generator for ELMS
 * Generates PDF reports specifically for leave requests
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/leave_types.php';

class PDFLeaveRequestsGenerator {
    private $pdo;
    private $pdf;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate PDF report for leave requests
     */
    public function generateLeaveRequestsReport($startDate, $endDate, $department = null, $employeeId = null) {
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Get leave requests data
        $leaveData = $this->getLeaveRequestsData($startDate, $endDate, $department, $employeeId);
        
        // Create PDF
        $this->createPDF($leaveData, $startDate, $endDate);
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
                lr.dept_head_signature,
                lr.dept_head_signed_at,
                lr.hr_signature,
                lr.hr_signed_at,
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
        
        $sql .= " ORDER BY e.department ASC, lr.start_date ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create PDF document
     */
    private function createPDF($leaveData, $startDate, $endDate) {
        // Create new PDF document
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $this->pdf->SetCreator('ELMS - Employee Leave Management System');
        $this->pdf->SetAuthor('ELMS System');
        $this->pdf->SetTitle('Leave Requests Report');
        $this->pdf->SetSubject('Leave Requests Report');
        
        // Set default header data
        $this->pdf->SetHeaderData('', 0, 'ELMS Leave Requests Report', 'Generated on ' . date('Y-m-d H:i:s'));
        
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
        $this->pdf->Cell(0, 15, 'LEAVE REQUESTS REPORT', 0, 1, 'C');
        $this->pdf->Ln(5);
        
        // Report period
        $this->pdf->SetFont('helvetica', '', 12);
        $this->pdf->Cell(0, 10, 'Report Period: ' . date('F j, Y', strtotime($startDate)) . ' - ' . date('F j, Y', strtotime($endDate)), 0, 1, 'C');
        $this->pdf->Ln(10);
        
        // Summary statistics
        $this->addSummarySection($leaveData);
        
        // Leave requests organized by department
        $this->addLeaveRequestsSection($leaveData);
        
        // Output PDF
        $filename = "ELMS_Leave_Requests_" . date('Y-m-d_H-i-s') . ".pdf";
        $this->pdf->Output($filename, 'D');
        exit();
    }
    
    /**
     * Add summary section
     */
    private function addSummarySection($leaveData) {
        $totalRequests = count($leaveData);
        $approvedRequests = count(array_filter($leaveData, function($req) { return $req['status'] === 'approved'; }));
        $pendingRequests = count(array_filter($leaveData, function($req) { return $req['status'] === 'pending'; }));
        $rejectedRequests = count(array_filter($leaveData, function($req) { return $req['status'] === 'rejected'; }));
        
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetFillColor(59, 130, 246);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell(0, 10, 'SUMMARY', 0, 1, 'C', true);
        $this->pdf->Ln(5);
        
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('helvetica', '', 10);
        
        // Create table for summary
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->Cell(60, 8, 'Total Requests', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $totalRequests, 1, 0, 'C');
        $this->pdf->Ln();
        
        $this->pdf->Cell(60, 8, 'Approved Requests', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $approvedRequests, 1, 0, 'C');
        $this->pdf->Ln();
        
        $this->pdf->Cell(60, 8, 'Pending Requests', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $pendingRequests, 1, 0, 'C');
        $this->pdf->Ln();
        
        $this->pdf->Cell(60, 8, 'Rejected Requests', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $rejectedRequests, 1, 0, 'C');
        $this->pdf->Ln(15);
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
                foreach ($requests as $request) {
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
                    
                    $this->pdf->Cell(30, 6, substr(ucwords(str_replace('_', ' ', $request['leave_type'])), 0, 18), 1, 0, 'L');
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
