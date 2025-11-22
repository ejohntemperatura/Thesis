<?php
/**
 * PDF Pay Summary Generator for ELMS
 * Generates PDF with department-wise counts of approved leaves: with pay vs without pay
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/ReportService.php';

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

        $reportService = new ReportService($this->pdo);
        $summary = $reportService->getDepartmentPayStatusSummary($startDate, $endDate, $filters);

        $this->createPDF($summary, $startDate, $endDate);
    }

    private function createPDF($summary, $startDate, $endDate) {
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $this->pdf->SetCreator('ELMS - Employee Leave Management System');
        $this->pdf->SetAuthor('ELMS System');
        $this->pdf->SetTitle('Pay Summary Report');
        $this->pdf->SetSubject('Department With-Pay vs Without-Pay Summary');

        $this->pdf->SetHeaderData('', 0, 'ELMS Pay Summary Report', 'Generated on ' . date('Y-m-d H:i:s'));
        $this->pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
        $this->pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);
        $this->pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $this->pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $this->pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $this->pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->Cell(0, 12, 'PAY SUMMARY (WITH PAY VS WITHOUT PAY)', 0, 1, 'C');
        $this->pdf->Ln(2);

        $this->pdf->SetFont('helvetica', '', 11);
        $this->pdf->Cell(0, 8, 'Report Period: ' . date('F j, Y', strtotime($startDate)) . ' - ' . date('F j, Y', strtotime($endDate)), 0, 1, 'C');
        $this->pdf->Ln(6);

        $this->renderTable($summary);

        $filename = 'ELMS_Pay_Summary_' . date('Y-m-d_H-i-s') . '.pdf';
        $this->pdf->Output($filename, 'D');
        exit();
    }

    private function renderTable($summary) {
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetFillColor(230, 230, 230);

        $this->pdf->Cell(90, 8, 'Department', 1, 0, 'L', true);
        $this->pdf->Cell(35, 8, 'With Pay', 1, 0, 'C', true);
        $this->pdf->Cell(35, 8, 'Without Pay', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Total', 1, 1, 'C', true);

        $this->pdf->SetFont('helvetica', '', 10);

        $totalWith = 0; $totalWithout = 0;
        if (!empty($summary)) {
            foreach ($summary as $row) {
                $dept = $row['department'] ?? '';
                $with = (int)($row['with_pay_count'] ?? 0);
                $without = (int)($row['without_pay_count'] ?? 0);
                $total = $with + $without;

                $totalWith += $with; $totalWithout += $without;

                $this->pdf->Cell(90, 8, $dept, 1, 0, 'L');
                $this->pdf->Cell(35, 8, (string)$with, 1, 0, 'C');
                $this->pdf->Cell(35, 8, (string)$without, 1, 0, 'C');
                $this->pdf->Cell(30, 8, (string)$total, 1, 1, 'C');
            }
        } else {
            $this->pdf->Cell(190, 10, 'No approved leave data found for the selected period/filters.', 1, 1, 'C');
        }

        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Cell(90, 8, 'Grand Total', 1, 0, 'R', true);
        $this->pdf->Cell(35, 8, (string)$totalWith, 1, 0, 'C', true);
        $this->pdf->Cell(35, 8, (string)$totalWithout, 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, (string)($totalWith + $totalWithout), 1, 1, 'C', true);
    }
}
?>
