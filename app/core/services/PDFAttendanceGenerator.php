<?php
/**
 * PDF Attendance Generator for ELMS
 * Generates PDF reports specifically for attendance data
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

class PDFAttendanceGenerator {
    private $pdo;
    private $pdf;
    
    // Standard work hours for late detection
    const MORNING_START_TIME = '08:00:00'; // 8:00 AM
    const AFTERNOON_START_TIME = '13:00:00'; // 1:00 PM
    const LATE_GRACE_PERIOD_MINUTES = 15; // 15 minutes grace period
    const STANDARD_WORK_HOURS = 8; // 8 hours standard
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Check if a time-in is late
     */
    private function checkIfLate($timeIn, $standardTime) {
        if (!$timeIn) return ['is_late' => false, 'minutes_late' => 0];
        
        $timeInObj = new DateTime($timeIn);
        $standardObj = new DateTime($timeInObj->format('Y-m-d') . ' ' . $standardTime);
        $standardObj->modify('+' . self::LATE_GRACE_PERIOD_MINUTES . ' minutes');
        
        if ($timeInObj > $standardObj) {
            $diff = $timeInObj->diff($standardObj);
            $minutesLate = ($diff->h * 60) + $diff->i;
            return ['is_late' => true, 'minutes_late' => $minutesLate];
        }
        return ['is_late' => false, 'minutes_late' => 0];
    }
    
    /**
     * Format late text
     */
    private function formatLateText($minutes) {
        if ($minutes >= 60) {
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            return $hours . 'h ' . $mins . 'm';
        }
        return $minutes . 'm';
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
        
        // Calculate late and overtime counts
        $lateCount = 0;
        $overtimeCount = 0;
        $totalOvertimeHours = 0;
        
        foreach ($attendanceData as $record) {
            $morningLate = $this->checkIfLate($record['morning_time_in'], self::MORNING_START_TIME);
            $afternoonLate = $this->checkIfLate($record['afternoon_time_in'], self::AFTERNOON_START_TIME);
            if ($morningLate['is_late'] || $afternoonLate['is_late']) {
                $lateCount++;
            }
            if ($record['total_hours'] > self::STANDARD_WORK_HOURS) {
                $overtimeCount++;
                $totalOvertimeHours += ($record['total_hours'] - self::STANDARD_WORK_HOURS);
            }
        }
        
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
        $this->pdf->Ln();
        
        // Late arrivals
        $this->pdf->SetFillColor(254, 226, 226); // Light red
        $this->pdf->Cell(60, 8, 'Late Arrivals', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $lateCount . ' days', 1, 0, 'C');
        $this->pdf->Ln();
        
        // Overtime days
        $this->pdf->SetFillColor(219, 234, 254); // Light blue
        $this->pdf->Cell(60, 8, 'Overtime Days', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $overtimeCount . ' days', 1, 0, 'C');
        $this->pdf->Ln();
        
        // Total overtime hours
        $this->pdf->SetFillColor(207, 250, 254); // Light cyan
        $this->pdf->Cell(60, 8, 'Total Overtime Hours (CTO Eligible)', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, number_format($totalOvertimeHours, 2) . ' hrs', 1, 0, 'C');
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
            $this->pdf->SetFont('helvetica', 'B', 6);
            $this->pdf->SetFillColor(200, 200, 200);
            $this->pdf->SetTextColor(0, 0, 0);
            $this->pdf->Cell(25, 8, 'Employee', 1, 0, 'C', true);
            $this->pdf->Cell(16, 8, 'Date', 1, 0, 'C', true);
            $this->pdf->Cell(16, 8, 'AM In', 1, 0, 'C', true);
            $this->pdf->Cell(16, 8, 'AM Out', 1, 0, 'C', true);
            $this->pdf->Cell(16, 8, 'PM In', 1, 0, 'C', true);
            $this->pdf->Cell(16, 8, 'PM Out', 1, 0, 'C', true);
            $this->pdf->Cell(12, 8, 'Hours', 1, 0, 'C', true);
            $this->pdf->Cell(22, 8, 'Status', 1, 0, 'C', true);
            $this->pdf->Cell(21, 8, 'Remarks', 1, 0, 'C', true);
            $this->pdf->Ln();
            
            // Table data for this department
            $this->pdf->SetFont('helvetica', '', 6);
            foreach ($records as $record) {
                // Check for late arrivals
                $morningLate = $this->checkIfLate($record['morning_time_in'], self::MORNING_START_TIME);
                $afternoonLate = $this->checkIfLate($record['afternoon_time_in'], self::AFTERNOON_START_TIME);
                $hasLate = $morningLate['is_late'] || $afternoonLate['is_late'];
                
                // Check for overtime
                $hasOvertime = $record['total_hours'] > self::STANDARD_WORK_HOURS;
                $overtimeHours = $hasOvertime ? round($record['total_hours'] - self::STANDARD_WORK_HOURS, 1) : 0;
                
                // Build remarks
                $remarks = [];
                if ($hasLate) {
                    $remarks[] = 'LATE';
                }
                if ($hasOvertime) {
                    $remarks[] = 'OT +' . $overtimeHours . 'h';
                }
                if (empty($remarks) && $record['status'] === 'Complete') {
                    $remarks[] = 'OK';
                }
                $remarksText = implode(', ', $remarks);
                
                // Format times in 12-hour format with AM/PM
                $morningIn = $record['morning_time_in'] ? date('g:i A', strtotime($record['morning_time_in'])) : '-';
                $morningOut = $record['morning_time_out'] ? date('g:i A', strtotime($record['morning_time_out'])) : '-';
                $afternoonIn = $record['afternoon_time_in'] ? date('g:i A', strtotime($record['afternoon_time_in'])) : '-';
                $afternoonOut = $record['afternoon_time_out'] ? date('g:i A', strtotime($record['afternoon_time_out'])) : '-';
                
                // Shorten status text to fit
                $status = $record['status'];
                if ($status == 'Half Day (Morning)') {
                    $status = 'Half (AM)';
                } elseif ($status == 'Half Day (Afternoon)') {
                    $status = 'Half (PM)';
                }
                
                // Set row background color based on remarks
                if ($hasLate) {
                    $this->pdf->SetFillColor(254, 226, 226); // Light red for late
                } elseif ($hasOvertime) {
                    $this->pdf->SetFillColor(219, 234, 254); // Light blue for overtime
                } else {
                    $this->pdf->SetFillColor(255, 255, 255); // White for normal
                }
                
                $this->pdf->Cell(25, 7, substr($record['employee_name'], 0, 12), 1, 0, 'L', true);
                $this->pdf->Cell(16, 7, date('m/d/y', strtotime($record['date'])), 1, 0, 'C', true);
                $this->pdf->Cell(16, 7, $morningIn, 1, 0, 'C', true);
                $this->pdf->Cell(16, 7, $morningOut, 1, 0, 'C', true);
                $this->pdf->Cell(16, 7, $afternoonIn, 1, 0, 'C', true);
                $this->pdf->Cell(16, 7, $afternoonOut, 1, 0, 'C', true);
                $this->pdf->Cell(12, 7, number_format($record['total_hours'], 1), 1, 0, 'C', true);
                $this->pdf->Cell(22, 7, $status, 1, 0, 'C', true);
                $this->pdf->Cell(21, 7, $remarksText, 1, 0, 'C', true);
                $this->pdf->Ln();
            }
            
            $this->pdf->Ln(5);
        }
    }
}
?>
