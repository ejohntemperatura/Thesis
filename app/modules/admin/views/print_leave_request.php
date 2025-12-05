<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../config/leave_types.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Load leave types
$leaveTypes = getLeaveTypes();

// Get leave request ID
$leave_id = $_GET['id'] ?? null;

if (!$leave_id || !is_numeric($leave_id)) {
    die('Invalid leave request ID');
}

try {
    // Get leave request details with employee information
    $stmt = $pdo->prepare("
        SELECT 
            lr.*,
            e.name as employee_name,
            e.first_name,
            e.middle_name,
            e.last_name,
            e.position,
            e.department,
            e.vacation_leave_balance,
            e.sick_leave_balance,
            e.id as emp_id,
            e.service_credit_balance AS sc_balance
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        WHERE lr.id = ?
    ");
    $stmt->execute([$leave_id]);
    $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$leaveRequest) {
        die('Leave request not found');
    }
    
    // Get HR/Admin name (Administrative Officer)
    $stmtHR = $pdo->prepare("SELECT name, position FROM employees WHERE role = 'admin' LIMIT 1");
    $stmtHR->execute();
    $hrInfo = $stmtHR->fetch(PDO::FETCH_ASSOC);
    
    // Get Director name and position
    $stmtDirector = $pdo->prepare("SELECT name, position FROM employees WHERE role = 'director' LIMIT 1");
    $stmtDirector->execute();
    $directorInfo = $stmtDirector->fetch(PDO::FETCH_ASSOC);
    
    // Get leave type display name
    $leaveTypeDisplay = getLeaveTypeDisplayName(
        $leaveRequest['leave_type'] ?? '', 
        $leaveRequest['original_leave_type'] ?? null, 
        $leaveTypes,
        $leaveRequest['other_purpose'] ?? null
    );
    
    // Determine which leave type checkbox to mark
    // For "other" type, use the other_purpose value
    if (($leaveRequest['leave_type'] ?? '') === 'other' && !empty($leaveRequest['other_purpose'])) {
        $selectedLeaveType = $leaveRequest['other_purpose']; // 'terminal_leave' or 'monetization'
    } else {
        $selectedLeaveType = strtolower($leaveRequest['original_leave_type'] ?? $leaveRequest['leave_type'] ?? '');
    }
    
} catch (Exception $e) {
    die('Error fetching leave request: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../../../../elmsicon.png">
    <title>Application for Leave - <?php echo htmlspecialchars($leaveRequest['employee_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.2;
            color: #000;
            background: white;
        }
        
        .page {
            width: 8.5in;
            height: 11in;
            margin: 0 auto;
            padding: 0.3in 0.4in;
            background: white;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        /* Header Section */
        .header {
            text-align: center;
            margin-bottom: 8px;
            position: relative;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .form-number {
            text-align: left;
            font-size: 8pt;
            line-height: 1.3;
        }
        
        .annex {
            text-align: right;
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 2px;
        }
        
        .republic-text {
            font-size: 8pt;
            margin-bottom: 2px;
        }
        
        .university-info {
            text-align: center;
            margin: 5px 0;
        }
        
        .university-name {
            font-weight: bold;
            font-size: 10pt;
            letter-spacing: 0.5px;
        }
        
        .campus-name {
            font-size: 9pt;
            font-weight: bold;
        }
        
        .contact-info {
            font-size: 6.5pt;
            color: #000;
            line-height: 1.4;
            margin-top: 2px;
        }
        
        .office-name {
            font-weight: bold;
            font-size: 8.5pt;
            margin: 5px 0 0 0;
            letter-spacing: 0.5px;
        }
        
        .form-title {
            font-size: 13pt;
            font-weight: bold;
            margin: 8px 0 10px 0;
            letter-spacing: 3px;
        }
        
        /* Form Sections */
        .form-section {
            border: 1px solid #000;
            margin-bottom: 1px;
            page-break-inside: avoid;
        }
        
        .section-header {
            background: #f0f0f0;
            padding: 2px 6px;
            font-weight: bold;
            font-size: 7pt;
            border-bottom: 1px solid #000;
        }
        
        .section-content {
            padding: 4px;
        }
        
        /* Two Column Layout */
        .two-columns {
            display: flex;
            gap: 0;
            border: 1px solid #000;
        }
        
        .column {
            flex: 1;
        }
        
        .column-left {
            flex: 0 0 48%;
            border-right: 1px solid #000;
        }
        
        .column-right {
            flex: 0 0 52%;
        }
        
        /* Field Styles */
        .field-row {
            display: flex;
            padding: 2px 6px;
            border-bottom: 1px solid #ddd;
        }
        
        .field-row:last-child {
            border-bottom: none;
        }
        
        .field-label {
            font-weight: bold;
            min-width: 100px;
            font-size: 7pt;
        }
        
        .field-value {
            flex: 1;
            border-bottom: 1px solid #000;
            min-height: 14px;
            padding: 0 3px;
        }
        
        /* Checkbox Styles */
        .checkbox-item {
            display: flex;
            align-items: flex-start;
            margin: 1px 0;
            font-size: 6.5pt;
            line-height: 1.3;
        }
        
        .checkbox {
            width: 10px;
            height: 10px;
            border: 1px solid #000;
            margin-right: 4px;
            flex-shrink: 0;
            position: relative;
            margin-top: 1px;
        }
        
        .checkbox.checked::after {
            content: "✓";
            position: absolute;
            top: -3px;
            left: 0px;
            font-size: 11px;
            font-weight: bold;
        }
        
        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table td, table th {
            border: 1px solid #000;
            padding: 3px;
            font-size: 7pt;
        }
        
        table th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        /* Signature Section */
        .signature-line {
            border-bottom: 1px solid #000;
            min-height: 25px;
            margin-top: 15px;
        }
        
        .signature-label {
            text-align: center;
            font-size: 6pt;
            margin-top: 1px;
        }
        
        /* Footer */
        .footer {
            margin-top: auto;
            text-align: center;
            padding-top: 10px;
        }
        
        .footer img {
            max-width: 100%;
            height: auto;
            max-height: 60px;
        }
        
        /* Print Styles */
        @media print {
            body { background: white; }
            .page { margin: 0; padding: 0.3in 0.4in; }
            .no-print { display: none !important; }
        }
        
        @page {
            size: letter;
            margin: 0.3in 0.4in;
        }
        
        .text-small {
            font-size: 6pt;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-2 {
            margin-top: 4px;
        }
        
        .mb-2 {
            margin-bottom: 4px;
        }
        
        .approval-text {
            color: #22c55e;
            font-weight: bold;
            font-size: 8pt;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header -->
        <div class="header">
            <div class="header-top">
                <div class="form-number">
                    <strong>Civil Service Form No. 6</strong><br>
                    <em>Revised 2020</em>
                </div>
                <div class="annex">ANNEX A</div>
            </div>
            
            <div style="display: flex; align-items: center; justify-content: center; margin: 0; gap: 15px;">
                <!-- CTU Logo -->
                <div style="flex: 0 0 80px; text-align: center;">
                    <img src="../../../../ctulogo.png" alt="CTU Logo" style="width: 80px; height: 80px; object-fit: contain;">
                </div>
                
                <!-- University Info -->
                <div class="university-info" style="flex: 0 1 auto; padding: 0 10px;">
                    <div class="republic-text">Republic of the Philippines</div>
                    <div class="university-name">CEBU TECHNOLOGICAL UNIVERSITY</div>
                    <div class="campus-name">TUBURAN CAMPUS</div>
                    <div class="contact-info">
                        Brgy. 8, Poblacion, Tuburan, Cebu, Philippines<br>
                        Website: http://www.ctu.edu.ph E-mail: tuburan.campus@ctu.edu.ph<br>
                        Phone: +6332 463 9313 loc. 1523
                    </div>
                    <div class="office-name">HUMAN RESOURCE MANAGEMENT OFFICE</div>
                </div>
                
                <!-- Bagong Pilipinas Logo -->
                <div style="flex: 0 0 100px; text-align: center;">
                    <img src="../../../../pilipinas.png" alt="Bagong Pilipinas" style="width: 100px; height: 100px; object-fit: contain;">
                </div>
            </div>
            
            <div class="form-title">APPLICATION FOR LEAVE</div>
        </div>

        <!-- Section 1 & 2: Combined Table -->
        <div class="form-section">
            <table style="margin: 0; border-collapse: collapse;">
                <tr>
                    <td rowspan="2" style="width: 20%; font-weight: bold; padding: 4px; vertical-align: top;">1. OFFICE/DEPARTMENT</td>
                    <td rowspan="2" style="width: 30%; padding: 4px; vertical-align: top;"><?php echo htmlspecialchars($leaveRequest['department'] ?? 'N/A'); ?></td>
                    <td style="width: 10%; font-weight: bold; padding: 4px; border-bottom: 1px solid #000;">2. NAME :</td>
                    <td style="width: 13.33%; text-align: center; font-size: 6pt; padding: 2px; border-bottom: 1px solid #000;">(Last)</td>
                    <td style="width: 13.33%; text-align: center; font-size: 6pt; padding: 2px; border-bottom: 1px solid #000;">(First)</td>
                    <td style="width: 13.33%; text-align: center; font-size: 6pt; padding: 2px; border-bottom: 1px solid #000;">(Middle)</td>
                </tr>
                <tr>
                    <td style="padding: 2px; border-right: 1px solid #000;"></td>
                    <td style="text-align: center; padding: 4px; border-right: 1px solid #000;"><?php echo htmlspecialchars($leaveRequest['last_name'] ?? ''); ?></td>
                    <td style="text-align: center; padding: 4px; border-right: 1px solid #000;"><?php echo htmlspecialchars($leaveRequest['first_name'] ?? ''); ?></td>
                    <td style="text-align: center; padding: 4px;"><?php echo htmlspecialchars($leaveRequest['middle_name'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td style="font-weight: bold; padding: 4px; border-top: 1px solid #000;">3. DATE OF FILING</td>
                    <td style="padding: 4px; border-top: 1px solid #000;"><?php echo date('F j, Y', strtotime($leaveRequest['created_at'])); ?></td>
                    <td colspan="3" style="font-weight: bold; padding: 4px; border-top: 1px solid #000;">4. POSITION <span style="font-weight: normal; margin-left: 10px;"><?php echo htmlspecialchars($leaveRequest['position'] ?? 'N/A'); ?></span></td>
                    <td style="font-weight: bold; padding: 4px; border-top: 1px solid #000;">5. SALARY</td>
                </tr>
            </table>
        </div>
        
        <!-- Section 6: Details of Application -->
        <div class="form-section" style="margin-top: 1px;">
            <div class="section-header" style="text-align: center;">6. DETAILS OF APPLICATION</div>
        </div>
        
        <div class="two-columns" style="margin-top: 0;">
            <!-- Left Column: Type of Leave -->
            <div class="column column-left">
                <div class="section-header" style="border-right: none;">6.A TYPE OF LEAVE TO BE AVAILED OF</div>
                <div class="section-content">
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'vacation' ? 'checked' : ''; ?>"></div>
                        <span>Vacation Leave (Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'mandatory' ? 'checked' : ''; ?>"></div>
                        <span>Mandatory/Forced Leave (Sec. 53, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'sick' ? 'checked' : ''; ?>"></div>
                        <span>Sick Leave (Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'maternity' ? 'checked' : ''; ?>"></div>
                        <span>Maternity Leave (R.A. No. 11210 / RR issued by CSC, DOLE and SSS)</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'paternity' ? 'checked' : ''; ?>"></div>
                        <span>Paternity Leave (R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended)</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'special_privilege' ? 'checked' : ''; ?>"></div>
                        <span>Special Privilege Leave (Rule VI, CSC MC No. 6, s. 1996, as amended/Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'solo_parent' ? 'checked' : ''; ?>"></div>
                        <span>Solo Parent Leave (SPL) (R.A. No. 8972 / CSC MC No. 8, s. 2004)</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'study' ? 'checked' : ''; ?>"></div>
                        <span>Study Leave (Sec. 58, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'vawc' ? 'checked' : ''; ?>"></div>
                        <span>10-Day VAWC Leave (R.A. No. 9262 / CSC MC No. 15, s. 2005)</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'rehabilitation' ? 'checked' : ''; ?>"></div>
                        <span>Rehabilitation Privilege (Sec. 59, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'special_women' ? 'checked' : ''; ?>"></div>
                        <span>Special Leave Benefits for Women (R.A. No. 9710 / CSC MC No. 25, s. 2010)</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'special_emergency' ? 'checked' : ''; ?>"></div>
                        <span>Special Emergency (Calamity) Leave (CSC MC No. 2, s. 2012, as amended)</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'adoption' ? 'checked' : ''; ?>"></div>
                        <span>Adoption Leave (R.A. No. 8552)</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Others: _______________________</span>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Details of Leave -->
            <div class="column column-right">
                <div class="section-header">6.B DETAILS OF LEAVE</div>
                <div class="section-content">
                    <div style="margin-bottom: 4px;">
                        <strong style="font-size: 7pt;">In case of Vacation/Special Privilege Leave:</strong>
                    </div>
                    <?php if (in_array($selectedLeaveType, ['vacation', 'special_privilege'])): ?>
                        <?php if (($leaveRequest['location_type'] ?? '') === 'within_philippines'): ?>
                    <div class="checkbox-item">
                        <div class="checkbox checked"></div>
                        <span>Within the Philippines: <?php echo htmlspecialchars($leaveRequest['location_specify'] ?? ''); ?></span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Abroad (Specify): _______________</span>
                    </div>
                        <?php elseif (($leaveRequest['location_type'] ?? '') === 'outside_philippines'): ?>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Within the Philippines: _______________</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox checked"></div>
                        <span>Abroad (Specify): <?php echo htmlspecialchars($leaveRequest['location_specify'] ?? ''); ?></span>
                    </div>
                        <?php else: ?>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Within the Philippines: _______________</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Abroad (Specify): _______________</span>
                    </div>
                        <?php endif; ?>
                    <?php else: ?>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Within the Philippines: _______________</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Abroad (Specify): _______________</span>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin: 6px 0 4px 0;">
                        <strong style="font-size: 7pt;">In case of Sick Leave:</strong>
                    </div>
                    <?php if ($selectedLeaveType === 'sick'): ?>
                        <?php if (($leaveRequest['medical_condition'] ?? '') === 'in_hospital'): ?>
                    <div class="checkbox-item">
                        <div class="checkbox checked"></div>
                        <span>In Hospital (Specify Illness): <?php echo htmlspecialchars($leaveRequest['illness_specify'] ?? ''); ?></span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Out Patient (Specify Illness): _______________</span>
                    </div>
                        <?php elseif (($leaveRequest['medical_condition'] ?? '') === 'out_patient'): ?>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>In Hospital (Specify Illness): _______________</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox checked"></div>
                        <span>Out Patient (Specify Illness): <?php echo htmlspecialchars($leaveRequest['illness_specify'] ?? ''); ?></span>
                    </div>
                        <?php else: ?>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>In Hospital (Specify Illness): _______________</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Out Patient (Specify Illness): _______________</span>
                    </div>
                        <?php endif; ?>
                    <?php else: ?>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>In Hospital (Specify Illness): _______________</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Out Patient (Specify Illness): _______________</span>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin: 6px 0 4px 0;">
                        <strong style="font-size: 7pt;">In case of Special Leave Benefits for Women:</strong>
                    </div>
                    <?php if ($selectedLeaveType === 'special_women'): ?>
                    <div style="font-size: 6.5pt; padding-left: 14px;">
                        (Specify Illness): <?php echo htmlspecialchars($leaveRequest['special_women_condition'] ?? '_______________'); ?>
                    </div>
                    <?php else: ?>
                    <div style="font-size: 6.5pt; padding-left: 14px;">
                        (Specify Illness): _______________
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin: 6px 0 4px 0;">
                        <strong style="font-size: 7pt;">In case of Study Leave:</strong>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo ($leaveRequest['study_type'] ?? '') === 'completion' ? 'checked' : ''; ?>"></div>
                        <span>Completion of Master's Degree</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo ($leaveRequest['study_type'] ?? '') === 'bar_board' ? 'checked' : ''; ?>"></div>
                        <span>BAR/Board Examination Review</span>
                    </div>
                    
                    <div style="margin: 6px 0 4px 0;">
                        <strong style="font-size: 7pt;">Other Purpose:</strong>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'monetization' || $selectedLeaveType === 'monetization' ? 'checked' : ''; ?>"></div>
                        <span>Monetization of Leave Credits</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo $selectedLeaveType === 'terminal' || $selectedLeaveType === 'terminal_leave' ? 'checked' : ''; ?>"></div>
                        <span>Terminal Leave</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 6: Number of Working Days & Dates -->
        <div class="form-section" style="margin-top: 1px;">
            <table style="margin: 0;">
                <tr>
                    <td style="width: 50%; font-weight: bold; padding: 4px;">
                        6.C NUMBER OF WORKING DAYS APPLIED FOR
                        <div style="font-size: 11pt; font-weight: bold; text-align: center; margin-top: 4px; border: 1px solid #000; padding: 4px;">
                            <?php 
                            // For Terminal Leave and Monetization, use working_days_applied if available
                            if (($leaveRequest['leave_type'] ?? '') === 'other' && !empty($leaveRequest['working_days_applied'])) {
                                echo $leaveRequest['working_days_applied'];
                            } else {
                                echo $leaveRequest['days_requested'] ?? 'N/A';
                            }
                            ?>
                        </div>
                        <div style="font-weight: bold; margin-top: 6px; font-size: 7pt;">INCLUSIVE DATES:</div>
                        <div style="margin-top: 2px; padding: 4px; border: 1px solid #000; min-height: 25px; font-size: 7pt;">
                            <?php 
                            // For Terminal Leave and Monetization, do not display dates
                            if (in_array($selectedLeaveType, ['terminal', 'terminal_leave', 'monetization']) || 
                                ($leaveRequest['leave_type'] ?? '') === 'other') {
                                echo 'N/A (Leave credits conversion)';
                            } elseif (!empty($leaveRequest['selected_dates'])) {
                                // If specific dates are selected, display them
                                $dates = explode(',', $leaveRequest['selected_dates']);
                                $formatted_dates = array_map(function($date) {
                                    return date('M d, Y', strtotime($date));
                                }, $dates);
                                echo implode(', ', $formatted_dates);
                            } else {
                                // Otherwise show the date range
                                $start = date('M d, Y', strtotime($leaveRequest['start_date']));
                                $end = date('M d, Y', strtotime($leaveRequest['end_date']));
                                if ($start === $end) {
                                    echo $start;
                                } else {
                                    echo $start . ' to ' . $end;
                                }
                            }
                            ?>
                        </div>
                    </td>
                    <td style="width: 50%; padding: 4px;">
                        <div style="font-weight: bold; margin-bottom: 4px;">6.D COMMUTATION</div>
                        <div class="checkbox-item">
                            <div class="checkbox <?php echo ($leaveRequest['commutation'] ?? 'not_requested') === 'not_requested' ? 'checked' : ''; ?>"></div>
                            <span>Not Requested</span>
                        </div>
                        <div class="checkbox-item">
                            <div class="checkbox <?php echo ($leaveRequest['commutation'] ?? '') === 'requested' ? 'checked' : ''; ?>"></div>
                            <span>Requested</span>
                        </div>
                        <div style="margin-top: 10px; text-align: center;">
                            <div class="signature-line" style="margin-top: 5px;"></div>
                            <div class="signature-label">(Signature of Applicant)</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Section 7: Details of Action on Application -->
        <div class="form-section" style="margin-top: 1px;">
            <div class="section-header" style="text-align: center;">7. DETAILS OF ACTION ON APPLICATION</div>
            <table style="margin: 0;">
                <tr>
                    <td style="width: 50%; vertical-align: top; padding: 2px;">
                        <div style="font-weight: bold; margin-bottom: 2px;">7.A CERTIFICATION OF LEAVE CREDITS</div>
                        <div style="text-align: center; margin-bottom: 4px; font-size: 7pt;">As of __________________</div>
                        <table style="width: 100%;">
                            <tr>
                                <th style="text-align: left; font-style: italic; padding: 2px;"></th>
                                <th style="text-align: center; padding: 2px;">Vacation Leave</th>
                                <th style="text-align: center; padding: 2px;">Sick Leave</th>
                            </tr>
                            <tr>
                                <td style="font-style: italic; padding: 2px;">Total Earned Less</td>
                                <td style="text-align: center; padding: 2px;"><?php echo number_format($leaveRequest['vacation_leave_balance'] ?? 0, 2); ?></td>
                                <td style="text-align: center; padding: 2px;"><?php echo number_format($leaveRequest['sick_leave_balance'] ?? 0, 2); ?></td>
                            </tr>
                            <tr>
                                <td style="font-style: italic; padding: 2px;">this application</td>
                                <td style="text-align: center; padding: 2px;">
                                    <?php 
                                    if (in_array($selectedLeaveType, ['vacation', 'special_privilege'])) {
                                        echo number_format($leaveRequest['days_requested'] ?? 0, 2);
                                    } else {
                                        echo '0.00';
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center; padding: 2px;">
                                    <?php 
                                    if ($selectedLeaveType === 'sick') {
                                        echo number_format($leaveRequest['days_requested'] ?? 0, 2);
                                    } else {
                                        echo '0.00';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-style: italic; padding: 2px;">Balance</td>
                                <td style="text-align: center; padding: 2px;">
                                    <?php 
                                    $vlBalance = $leaveRequest['vacation_leave_balance'] ?? 0;
                                    if (in_array($selectedLeaveType, ['vacation', 'special_privilege'])) {
                                        $vlBalance -= ($leaveRequest['days_requested'] ?? 0);
                                    }
                                    echo number_format($vlBalance, 2);
                                    ?>
                                </td>
                                <td style="text-align: center; padding: 2px;">
                                    <?php 
                                    $slBalance = $leaveRequest['sick_leave_balance'] ?? 0;
                                    if ($selectedLeaveType === 'sick') {
                                        $slBalance -= ($leaveRequest['days_requested'] ?? 0);
                                    }
                                    echo number_format($slBalance, 2);
                                    ?>
                                </td>
                            </tr>
                        </table>
                        <div style="margin-top: 15px; text-align: center; border-top: 1px solid #000; padding-top: 5px;">
                            <?php if ($leaveRequest['admin_approval'] === 'approved' || ($leaveRequest['status'] === 'approved' && empty($leaveRequest['admin_approval']))): ?>
                                <div class="approval-text">APPROVED</div>
                            <?php endif; ?>
                            <div class="signature-line" style="margin-top: 3px;"></div>
                            <div style="font-weight: bold; font-size: 9pt; margin-top: 1px;">CRISTY XILDE R. AMANCIO, RPm</div>
                            <div style="font-size: 7pt;">AO-IV/HRMO II</div>
                        </div>
                    </td>
                    <td style="width: 50%; vertical-align: top; padding: 2px;">
                        <div style="font-weight: bold; margin-bottom: 2px;">7.B RECOMMENDATION</div>
                        <div class="checkbox-item">
                            <div class="checkbox <?php echo ($leaveRequest['status'] === 'approved' || $leaveRequest['admin_approval'] === 'approved') ? 'checked' : ''; ?>"></div>
                            <span>For approval</span>
                        </div>
                        <div class="checkbox-item">
                            <div class="checkbox <?php echo ($leaveRequest['status'] === 'rejected' || $leaveRequest['admin_approval'] === 'rejected') ? 'checked' : ''; ?>"></div>
                            <span>For disapproval due to:</span>
                        </div>
                        <div style="margin: 2px 0; padding-left: 14px; min-height: 20px; border-bottom: 1px solid #000;">
                            <?php 
                            if ($leaveRequest['status'] === 'rejected' || $leaveRequest['admin_approval'] === 'rejected') {
                                echo htmlspecialchars($leaveRequest['admin_rejection_reason'] ?? 
                                     $leaveRequest['dept_head_rejection_reason'] ?? '');
                            }
                            ?>
                        </div>
                        <div style="margin-top: 8px; text-align: center;">
                            <?php if ($leaveRequest['admin_approval'] === 'approved' || ($leaveRequest['status'] === 'approved' && empty($leaveRequest['admin_approval']))): ?>
                                <div class="approval-text">APPROVED</div>
                            <?php endif; ?>
                            <div class="signature-line" style="margin-top: 3px;"></div>
                            <div class="signature-label">(Immediate Head)</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Section 8: Approved For -->
        <div class="form-section" style="margin-top: 1px;">
            <table style="margin: 0;">
                <tr>
                    <td style="width: 50%; padding: 2px; vertical-align: top;">
                        <div style="font-weight: bold; margin-bottom: 2px;">7.C APPROVED FOR:</div>
                        <div style="display: flex; gap: 10px; margin: 2px 0;">
                            <div style="flex: 1;">
                                <div class="field-value" style="text-align: center; font-weight: bold;">
                                    <?php 
                                    // Show days with pay only if NOT without_pay leave type
                                    if ($leaveRequest['status'] === 'approved' && 
                                        ($leaveRequest['leave_type'] ?? '') !== 'without_pay' &&
                                        ($leaveRequest['pay_status'] ?? '') !== 'without_pay') {
                                        echo $leaveRequest['approved_days'] ?? $leaveRequest['days_requested'] ?? '';
                                    }
                                    ?>
                                </div>
                                <div style="text-align: center; font-size: 6pt; margin-top: 1px;">days with pay</div>
                            </div>
                            <div style="flex: 1;">
                                <div class="field-value" style="text-align: center; font-weight: bold;">
                                    <?php 
                                    // Show days without pay if leave_type is without_pay OR pay_status is without_pay
                                    if ($leaveRequest['status'] === 'approved' && 
                                        (($leaveRequest['leave_type'] ?? '') === 'without_pay' ||
                                         ($leaveRequest['pay_status'] ?? '') === 'without_pay')) {
                                        echo $leaveRequest['approved_days'] ?? $leaveRequest['days_requested'] ?? '';
                                    }
                                    ?>
                                </div>
                                <div style="text-align: center; font-size: 6pt; margin-top: 1px;">days without pay</div>
                            </div>
                            <div style="flex: 1;">
                                <div class="field-value" style="text-align: center; font-weight: bold;"></div>
                                <div style="text-align: center; font-size: 6pt; margin-top: 1px;">others (specify)</div>
                            </div>
                        </div>
                    </td>
                    <td style="width: 50%; padding: 2px; vertical-align: top;">
                        <div style="font-weight: bold; margin-bottom: 2px;">7.D DISAPPROVED DUE TO:</div>
                        <div style="min-height: 20px; border-bottom: 1px solid #000; margin: 2px 0;">
                            <?php 
                            if ($leaveRequest['status'] === 'rejected') {
                                echo htmlspecialchars($leaveRequest['director_rejection_reason'] ?? 
                                     $leaveRequest['admin_rejection_reason'] ?? 
                                     $leaveRequest['dept_head_rejection_reason'] ?? '');
                            }
                            ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 5px 2px; text-align: center; border-top: 1px solid #000;">
                        <?php if ($leaveRequest['director_approval'] === 'approved' || ($leaveRequest['status'] === 'approved' && empty($leaveRequest['director_approval']))): ?>
                            <div class="approval-text">APPROVED</div>
                        <?php endif; ?>
                        <div class="signature-line" style="margin-top: 5px; width: 400px; margin-left: auto; margin-right: auto;"></div>
                        <div style="font-weight: bold; font-size: 9pt; margin-top: 1px;">MA. CARLA Y. ABAQUITA, Dev.Ed. D., RChE</div>
                        <div style="font-size: 7pt;">Campus Director</div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <img src="../../../../footer.png" alt="Footer">
        </div>
        
    </div>
    
    <!-- Print Button -->
    <button onclick="window.print()" class="no-print" style="position: fixed; top: 20px; right: 20px; background: #3b82f6; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000;">
        <i class="fas fa-print"></i> Print Form
    </button>
</body>
</html>
