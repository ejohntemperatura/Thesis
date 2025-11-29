<?php
/**
 * PDF Leave Summary Generator for ELMS
 * Generates PDF with employee list grouped by department showing VL and SL balances
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

class PDFPaySummaryGenerator {
    private $pdo;
    private $pdf;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function generatePaySummaryReport($startDate, $endDate, $filters = []) {
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Get employees grouped by department with their leave balances
        $employeesByDepartment = $this->getEmployeeLeaveBalances($filters);

        $this->createPDF($employeesByDepartment, $startDate, $endDate);
    }

    private function getEmployeeLeaveBalances($filters = []) {
        $whereConditions = ["e.role = 'employee'"];
        $params = [];

        if (!empty($filters['department'])) {
            $whereConditions[] = "e.department = ?";
            $params[] = $filters['department'];
        }

        if (!empty($filters['employee_id'])) {
            $whereConditions[] = "e.id = ?";
            $params[] = $filters['employee_id'];
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get all employees first
        $sql = "
            SELECT 
                e.id,
                e.name,
                e.department,
                e.position
            FROM employees e
            WHERE $whereClause
            ORDER BY e.department ASC, e.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each employee, get their leave usage grouped by leave type
        foreach ($employees as &$emp) {
            $leaveUsageSql = "
                SELECT 
                    lr.leave_type,
                    lr.pay_status,
                    SUM(COALESCE(lr.approved_days, lr.days_requested)) as total_days
                FROM leave_requests lr
                WHERE lr.employee_id = ? AND lr.status = 'approved'
                GROUP BY lr.leave_type, lr.pay_status
            ";
            
            $leaveStmt = $this->pdo->prepare($leaveUsageSql);
            $leaveStmt->execute([$emp['id']]);
            $leaveUsage = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $emp['leave_usage'] = $leaveUsage;
        }

        // Group by department
        $grouped = [];
        foreach ($employees as $emp) {
            $dept = $emp['department'] ?: 'Unassigned';
            if (!isset($grouped[$dept])) {
                $grouped[$dept] = [];
            }
            $grouped[$dept][] = $emp;
        }

        return $grouped;
    }

    private function createPDF($employeesByDepartment, $startDate, $endDate) {
        // Use landscape orientation for more columns
        $this->pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $this->pdf->SetCreator('ELMS - Employee Leave Management System');
        $this->pdf->SetAuthor('ELMS System');
        $this->pdf->SetTitle('Leave Summary Report');
        $this->pdf->SetSubject('Employee Leave Balances by Department');

        $this->pdf->SetHeaderData('', 0, 'ELMS Leave Summary Report', 'Generated on ' . date('Y-m-d H:i:s'));
        $this->pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
        $this->pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);
        $this->pdf->SetMargins(10, PDF_MARGIN_TOP, 10);
        $this->pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $this->pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $this->pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->Cell(0, 12, 'LEAVE SUMMARY', 0, 1, 'C');
        $this->pdf->Ln(2);

        $this->pdf->SetFont('helvetica', '', 11);
        $this->pdf->Cell(0, 8, 'Employee Leave Usage by Department', 0, 1, 'C');
        $this->pdf->Cell(0, 8, 'Report Period: ' . date('F j, Y', strtotime($startDate)) . ' - ' . date('F j, Y', strtotime($endDate)), 0, 1, 'C');
        $this->pdf->Ln(6);

        $this->renderEmployeeTables($employeesByDepartment);

        $filename = 'ELMS_Leave_Summary_' . date('Y-m-d_H-i-s') . '.pdf';
        $this->pdf->Output($filename, 'D');
        exit();
    }

    private function renderEmployeeTables($employeesByDepartment) {
        if (empty($employeesByDepartment)) {
            $this->pdf->SetFont('helvetica', '', 11);
            $this->pdf->Cell(0, 10, 'No employees found for the selected filters.', 0, 1, 'C');
            return;
        }

        require_once __DIR__ . '/../../../config/leave_types.php';
        $leaveTypes = getLeaveTypes();

        // Build leave columns from all leave types in config
        $leaveColumns = [];
        foreach ($leaveTypes as $key => $config) {
            // Shorten names for column headers
            $label = $config['name'];
            $label = str_replace(' Leave', '', $label);
            $label = str_replace('Vacation (VL)', 'VL', $label);
            $label = str_replace('Sick (SL)', 'SL', $label);
            $label = str_replace('Special Leave Privilege (SLP)', 'SLP', $label);
            $label = str_replace('Compensatory Time Off (CTO)', 'CTO', $label);
            $label = str_replace('Special Benefits for Women', 'Women', $label);
            $label = str_replace('Leave Without Pay', 'W/O Pay', $label);
            
            // Limit length for display
            if (strlen($label) > 12) {
                $label = substr($label, 0, 11) . '.';
            }
            
            $leaveColumns[$key] = $label;
        }

        $totalEmployees = 0;
        $grandTotals = [];
        foreach ($leaveColumns as $key => $label) {
            $grandTotals[$key] = 0;
        }

        foreach ($employeesByDepartment as $department => $employees) {
            // Department Header
            $this->pdf->SetFont('helvetica', 'B', 10);
            $this->pdf->SetFillColor(59, 130, 246);
            $this->pdf->SetTextColor(255, 255, 255);
            $this->pdf->Cell(0, 8, $department . ' (' . count($employees) . ' employees)', 1, 1, 'L', true);
            $this->pdf->SetTextColor(0, 0, 0);

            // Table Header
            $this->pdf->SetFont('helvetica', 'B', 6);
            $this->pdf->SetFillColor(230, 230, 230);
            $this->pdf->Cell(5, 6, '#', 1, 0, 'C', true);
            $this->pdf->Cell(40, 6, 'Employee Name', 1, 0, 'L', true);
            $this->pdf->Cell(30, 6, 'Position', 1, 0, 'L', true);
            
            // Calculate column width based on number of leave types
            $numLeaveTypes = count($leaveColumns);
            $availableWidth = 277 - 75; // Landscape width minus employee info columns
            $leaveColWidth = floor($availableWidth / $numLeaveTypes);
            if ($leaveColWidth < 10) $leaveColWidth = 10;
            if ($leaveColWidth > 15) $leaveColWidth = 15;
            
            // Leave type columns
            foreach ($leaveColumns as $key => $label) {
                $this->pdf->Cell($leaveColWidth, 6, $label, 1, 0, 'C', true);
            }
            $this->pdf->Ln();

            // Employee Rows
            $this->pdf->SetFont('helvetica', '', 5);
            $counter = 1;
            $deptTotals = [];
            foreach ($leaveColumns as $key => $label) {
                $deptTotals[$key] = 0;
            }

            foreach ($employees as $emp) {
                // Build leave data array for this employee
                $empLeaveData = [];
                foreach ($leaveColumns as $key => $label) {
                    $empLeaveData[$key] = 0;
                }

                // Process leave usage
                if (!empty($emp['leave_usage'])) {
                    foreach ($emp['leave_usage'] as $usage) {
                        $leaveType = $usage['leave_type'];
                        $payStatus = $usage['pay_status'];
                        $days = (float)$usage['total_days'];

                        // Map to column
                        if ($payStatus === 'without_pay' || $leaveType === 'without_pay') {
                            $empLeaveData['without_pay'] += $days;
                        } elseif (isset($empLeaveData[$leaveType])) {
                            $empLeaveData[$leaveType] += $days;
                        }
                    }
                }

                // Add to department totals
                foreach ($leaveColumns as $key => $label) {
                    $deptTotals[$key] += $empLeaveData[$key];
                }

                // Render row
                $this->pdf->Cell(5, 4.5, (string)$counter, 1, 0, 'C');
                $this->pdf->Cell(40, 4.5, substr($emp['name'], 0, 32), 1, 0, 'L');
                $this->pdf->Cell(30, 4.5, substr($emp['position'] ?: 'N/A', 0, 24), 1, 0, 'L');
                
                foreach ($leaveColumns as $key => $label) {
                    $value = $empLeaveData[$key] > 0 ? number_format($empLeaveData[$key], 1) : '-';
                    $this->pdf->Cell($leaveColWidth, 4.5, $value, 1, 0, 'C');
                }
                $this->pdf->Ln();

                $counter++;
            }

            // Department Subtotal
            $this->pdf->SetFont('helvetica', 'B', 5);
            $this->pdf->SetFillColor(245, 245, 245);
            $this->pdf->Cell(75, 4.5, 'Department Total', 1, 0, 'R', true);
            
            foreach ($leaveColumns as $key => $label) {
                $value = $deptTotals[$key] > 0 ? number_format($deptTotals[$key], 1) : '-';
                $this->pdf->Cell($leaveColWidth, 4.5, $value, 1, 0, 'C', true);
                
                // Add to grand totals
                $grandTotals[$key] += $deptTotals[$key];
            }
            $this->pdf->Ln();

            $totalEmployees += count($employees);
            $this->pdf->Ln(3);
        }

        // Grand Total
        $this->pdf->SetFont('helvetica', 'B', 6);
        $this->pdf->SetFillColor(59, 130, 246);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell(75, 5, 'GRAND TOTAL (' . $totalEmployees . ' employees)', 1, 0, 'R', true);
        
        // Calculate column width for grand total (same as above)
        $numLeaveTypes = count($leaveColumns);
        $availableWidth = 277 - 75;
        $leaveColWidth = floor($availableWidth / $numLeaveTypes);
        if ($leaveColWidth < 10) $leaveColWidth = 10;
        if ($leaveColWidth > 15) $leaveColWidth = 15;
        
        foreach ($leaveColumns as $key => $label) {
            $value = $grandTotals[$key] > 0 ? number_format($grandTotals[$key], 1) : '-';
            $this->pdf->Cell($leaveColWidth, 5, $value, 1, 0, 'C', true);
        }
        $this->pdf->Ln();
        $this->pdf->SetTextColor(0, 0, 0);
    }
}
?>
