<?php
/**
 * PDF Attendance Generator for ELMS
 * Generates PDF reports specifically for attendance data
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

class PDFAttendanceGenerator {
    private $pdo;
    private $pdf;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate PDF report for attendance
     */
    public function generateAttendanceReport($startDate, $endDate, $department = null, $employeeId = null) {
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Get attendance data
        $attendanceData = $this->getAttendanceData($startDate, $endDate, $department, $employeeId);
        
        // Create PDF
        $this->createPDF($attendanceData, $startDate, $endDate);
    }
    
    /**
     * Get attendance data with detailed information
     */
    private function getAttendanceData($startDate, $endDate, $department = null, $employeeId = null) {
        $sql = "
            SELECT 
                d.id,
                d.user_id as employee_id,
                d.date,
                d.morning_time_in,
                d.morning_time_out,
                d.afternoon_time_in,
                d.afternoon_time_out,
                d.created_at,
                e.name as employee_name,
                e.position,
                e.department,
                e.email,
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
                CASE 
                    WHEN d.morning_time_in IS NOT NULL AND d.morning_time_out IS NOT NULL 
                    AND d.afternoon_time_in IS NOT NULL AND d.afternoon_time_out IS NOT NULL 
                    THEN (TIMESTAMPDIFF(MINUTE, d.morning_time_in, d.morning_time_out) + 
                          TIMESTAMPDIFF(MINUTE, d.afternoon_time_in, d.afternoon_time_out)) / 60.0
                    ELSE 0 
                END as total_hours,
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
                END as status
            FROM dtr d 
            JOIN employees e ON d.user_id = e.id 
            WHERE d.date >= ? AND d.date <= ?
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
            $sql .= " AND d.user_id = ?";
            $params[] = $employeeId;
        }
        
        $sql .= " ORDER BY e.department ASC, d.date ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create PDF document
     */
    private function createPDF($attendanceData, $startDate, $endDate) {
        // Create new PDF document
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $this->pdf->SetCreator('ELMS - Employee Leave Management System');
        $this->pdf->SetAuthor('ELMS System');
        $this->pdf->SetTitle('Attendance Report');
        $this->pdf->SetSubject('Attendance Report');
        
        // Set default header data
        $this->pdf->SetHeaderData('', 0, 'ELMS Attendance Report', 'Generated on ' . date('Y-m-d H:i:s'));
        
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
        $this->pdf->Cell(0, 15, 'ATTENDANCE REPORT', 0, 1, 'C');
        $this->pdf->Ln(5);
        
        // Report period
        $this->pdf->SetFont('helvetica', '', 12);
        $this->pdf->Cell(0, 10, 'Report Period: ' . date('F j, Y', strtotime($startDate)) . ' - ' . date('F j, Y', strtotime($endDate)), 0, 1, 'C');
        $this->pdf->Ln(10);
        
        // Summary statistics
        $this->addSummarySection($attendanceData);
        
        // Attendance data organized by department
        $this->addAttendanceSection($attendanceData);
        
        // Output PDF
        $filename = "ELMS_Attendance_" . date('Y-m-d_H-i-s') . ".pdf";
        $this->pdf->Output($filename, 'D');
        exit();
    }
    
    /**
     * Add summary section
     */
    private function addSummarySection($attendanceData) {
        $totalRecords = count($attendanceData);
        $totalHours = array_sum(array_column($attendanceData, 'total_hours'));
        $avgHoursPerDay = $totalRecords > 0 ? $totalHours / $totalRecords : 0;
        
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetFillColor(16, 185, 129);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell(0, 10, 'SUMMARY', 0, 1, 'C', true);
        $this->pdf->Ln(5);
        
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('helvetica', '', 10);
        
        // Create table for summary
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->Cell(60, 8, 'Total Records', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $totalRecords, 1, 0, 'C');
        $this->pdf->Ln();
        
        $this->pdf->Cell(60, 8, 'Total Hours Worked', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, number_format($totalHours, 2), 1, 0, 'C');
        $this->pdf->Ln();
        
        $this->pdf->Cell(60, 8, 'Average Hours per Day', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, number_format($avgHoursPerDay, 2), 1, 0, 'C');
        $this->pdf->Ln(15);
    }
    
    /**
     * Add attendance details section organized by department
     */
    private function addAttendanceSection($attendanceData) {
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetFillColor(245, 101, 101);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell(0, 10, 'ATTENDANCE BY DEPARTMENT', 0, 1, 'C', true);
        $this->pdf->Ln(5);
        
        // Group attendance by department
        $groupedData = [];
        foreach ($attendanceData as $record) {
            $groupedData[$record['department']][] = $record;
        }
        
        // Sort departments alphabetically
        ksort($groupedData);
        
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('helvetica', '', 8);
        
        foreach ($groupedData as $department => $records) {
            // Department header
            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->SetFillColor(16, 185, 129);
            $this->pdf->SetTextColor(255, 255, 255);
            $this->pdf->Cell(0, 8, $department . ' (' . count($records) . ' records)', 0, 1, 'L', true);
            $this->pdf->Ln(2);
            
            // Table header
            $this->pdf->SetFont('helvetica', 'B', 7);
            $this->pdf->SetFillColor(200, 200, 200);
            $this->pdf->SetTextColor(0, 0, 0);
            $this->pdf->Cell(30, 8, 'Employee', 1, 0, 'C', true);
            $this->pdf->Cell(18, 8, 'Date', 1, 0, 'C', true);
            $this->pdf->Cell(18, 8, 'Morning In', 1, 0, 'C', true);
            $this->pdf->Cell(18, 8, 'Morning Out', 1, 0, 'C', true);
            $this->pdf->Cell(18, 8, 'Afternoon In', 1, 0, 'C', true);
            $this->pdf->Cell(18, 8, 'Afternoon Out', 1, 0, 'C', true);
            $this->pdf->Cell(12, 8, 'Total Hrs', 1, 0, 'C', true);
            $this->pdf->Cell(28, 8, 'Status', 1, 0, 'C', true);
            $this->pdf->Ln();
            
            // Table data for this department
            $this->pdf->SetFont('helvetica', '', 7);
            foreach ($records as $record) {
                // Format times in 12-hour format with AM/PM
                $morningIn = $record['morning_time_in'] ? date('g:i A', strtotime($record['morning_time_in'])) : '-';
                $morningOut = $record['morning_time_out'] ? date('g:i A', strtotime($record['morning_time_out'])) : '-';
                $afternoonIn = $record['afternoon_time_in'] ? date('g:i A', strtotime($record['afternoon_time_in'])) : '-';
                $afternoonOut = $record['afternoon_time_out'] ? date('g:i A', strtotime($record['afternoon_time_out'])) : '-';
                
                // Shorten status text to fit
                $status = $record['status'];
                if ($status == 'Half Day (Morning)') {
                    $status = 'Half Day (AM)';
                } elseif ($status == 'Half Day (Afternoon)') {
                    $status = 'Half Day (PM)';
                }
                
                $this->pdf->Cell(30, 8, substr($record['employee_name'], 0, 15), 1, 0, 'L');
                $this->pdf->Cell(18, 8, date('m/d/Y', strtotime($record['date'])), 1, 0, 'C');
                $this->pdf->Cell(18, 8, $morningIn, 1, 0, 'C');
                $this->pdf->Cell(18, 8, $morningOut, 1, 0, 'C');
                $this->pdf->Cell(18, 8, $afternoonIn, 1, 0, 'C');
                $this->pdf->Cell(18, 8, $afternoonOut, 1, 0, 'C');
                $this->pdf->Cell(12, 8, number_format($record['total_hours'], 1), 1, 0, 'C');
                $this->pdf->Cell(28, 8, $status, 1, 0, 'C');
                $this->pdf->Ln();
            }
            
            $this->pdf->Ln(5);
        }
    }
}
?>
