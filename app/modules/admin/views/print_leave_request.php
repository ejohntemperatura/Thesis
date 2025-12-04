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
        $leaveTypes
    );
    
    // Determine which leave type checkbox to mark
    $selectedLeaveType = strtolower($leaveRequest['original_leave_type'] ?? $leaveRequest['leave_type'] ?? '');
    
} catch (Exception $e) {
    die('Error fetching leave request: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Application for Leave - <?php echo htmlspecialchars($leaveRequest['employee_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
            background: white;
        }
        
        .page {
            width: 8.5in;
            min-height: 11in;
            margin: 0 auto;
            padding: 0.5in;
            background: white;
        }
        
        /* Header Section */
        .header {
            text-align: center;
            margin-bottom: 15px;
            position: relative;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .form-number {
            text-align: left;
            font-size: 9pt;
        }
        
        .annex {
            text-align: right;
            font-size: 9pt;
            font-weight: bold;
        }
        
        .university-info {
            text-align: center;
            margin: 10px 0;
        }
        
        .university-name {
            font-weight: bold;
            font-size: 11pt;
        }
        
        .campus-name {
            font-size: 10pt;
        }
        
        .contact-info {
            font-size: 8pt;
            color: #333;
        }
        
        .office-name {
            font-weight: bold;
            font-size: 10pt;
            margin: 8px 0;
        }
        
        .form-title {
            font-size: 14pt;
            font-weight: bold;
            margin: 10px 0;
            letter-spacing: 2px;
        }
        
        /* Form Sections */
        .form-section {
            border: 1px solid #000;
            margin-bottom: 2px;
            page-break-inside: avoid;
        }
        
        .section-header {
            background: #f0f0f0;
            padding: 4px 8px;
            font-weight: bold;
            font-size: 9pt;
            border-bottom: 1px solid #000;
        }
        
        .section-content {
            padding: 8px;
        }
        
        /* Two Column Layout */
        .two-columns {
            display: flex;
            gap: 2px;
        }
        
        .column {
            flex: 1;
            border: 1px solid #000;
        }
        
        .column-left {
            flex: 0 0 48%;
        }
        
        .column-right {
            flex: 0 0 52%;
        }
        
        /* Field Styles */
        .field-row {
            display: flex;
            padding: 3px 8px;
            border-bottom: 1px solid #ddd;
        }
        
        .field-row:last-child {
            border-bottom: none;
        }
        
        .field-label {
            font-weight: bold;
            min-width: 120px;
            font-size: 9pt;
        }
        
        .field-value {
            flex: 1;
            border-bottom: 1px solid #000;
            min-height: 18px;
            padding: 0 4px;
        }
        
        /* Checkbox Styles */
        .checkbox-item {
            display: flex;
            align-items: flex-start;
            margin: 3px 0;
            font-size: 8.5pt;
            line-height: 1.4;
        }
        
        .checkbox {
            width: 12px;
            height: 12px;
            border: 1px solid #000;
            margin-right: 6px;
            flex-shrink: 0;
            position: relative;
            margin-top: 2px;
        }
        
        .checkbox.checked::after {
            content: "✓";
            position: absolute;
            top: -3px;
            left: 1px;
            font-size: 14px;
            font-weight: bold;
        }
        
        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table td, table th {
            border: 1px solid #000;
            padding: 4px;
            font-size: 9pt;
        }
        
        table th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        /* Signature Section */
        .signature-line {
            border-bottom: 1px solid #000;
            min-height: 40px;
            margin-top: 30px;
        }
        
        .signature-label {
            text-align: center;
            font-size: 8pt;
            margin-top: 2px;
        }
        
        /* Print Styles */
        @media print {
            body { background: white; }
            .page { margin: 0; padding: 0.5in; }
            .no-print { display: none !important; }
        }
        
        @page {
            size: letter;
            margin: 0.5in;
        }
        
        .text-small {
            font-size: 8pt;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-2 {
            margin-top: 8px;
        }
        
        .mb-2 {
            margin-bottom: 8px;
        }
        
        .approval-text {
            color: #22c55e;
            font-weight: bold;
            font-size: 10pt;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header -->
        <div class="header">
            <div class="header-top">
                <div class="form-number">
                    Civil Service Form No. 6<br>
                    Revised 2020
                </div>
                <div class="annex">ANNEX A</div>
            </div>
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin: 15px 0;">
                <!-- CTU Logo -->
                <div style="flex: 0 0 100px; margin-left: 20px;">
                    <img src="../../../../ctulogo.png" alt="CTU Logo" style="width: 100px; height: 100px; object-fit: contain;">
                </div>
                
                <!-- University Info -->
                <div class="university-info" style="flex: 1; padding: 0 30px;">
                    <div class="university-name">CEBU TECHNOLOGICAL UNIVERSITY</div>
                    <div class="campus-name">TUBURAN CAMPUS</div>
                    <div class="contact-info">
                        Brgy. 8, Poblacion, Tuburan, Cebu, Philippines<br>
                        Website: http://www.ctu.edu.ph E-mail: tuburan.campus@ctu.edu.ph<br>
                        Phone: +6332 463 9313 loc. 1523
                    </div>
                    <div class="office-name">HUMAN RESOURCES MANAGEMENT OFFICE</div>
                </div>
                
                <!-- Bagong Pilipinas Logo -->
                <div style="flex: 0 0 100px; margin-right: 20px;">
                    <img src="../../../../pilipinas.png" alt="Bagong Pilipinas" style="width: 100px; height: 100px; object-fit: contain;">
                </div>
            </div>
            
            <div class="form-title">APPLICATION FOR LEAVE</div>
        </div>

        <!-- Section 1: Office/Department -->
        <div class="form-section">
            <table style="margin: 0;">
                <tr>
                    <td style="width: 15%; font-weight: bold;">1. OFFICE/DEPARTMENT</td>
                    <td style="width: 35%;"><?php echo htmlspecialchars($leaveRequest['department'] ?? 'N/A'); ?></td>
                    <td style="width: 15%; font-weight: bold;">2. NAME</td>
                    <td style="width: 35%;"><?php echo htmlspecialchars($leaveRequest['employee_name']); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Section 2: Date of Filing & Position -->
        <div class="form-section">
            <table style="margin: 0;">
                <tr>
                    <td style="width: 15%; font-weight: bold;">3. DATE OF FILING</td>
                    <td style="width: 35%;"><?php echo date('F j, Y', strtotime($leaveRequest['created_at'])); ?></td>
                    <td style="width: 15%; font-weight: bold;">4. POSITION</td>
                    <td style="width: 35%;"><?php echo htmlspecialchars($leaveRequest['position'] ?? 'N/A'); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Section 3: Two Column Layout -->
        <div class="two-columns" style="margin-top: 2px;">
            <!-- Left Column: Type of Leave -->
            <div class="column column-left">
                <div class="section-header">5.A TYPE OF LEAVE TO BE AVAILED OF</div>
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
                <div class="section-header">5.B DETAILS OF LEAVE</div>
                <div class="section-content">
                    <div style="margin-bottom: 8px;">
                        <strong style="font-size: 9pt;">In case of Vacation/Special Privilege Leave:</strong>
                    </div>
                    <?php if (in_array($selectedLeaveType, ['vacation', 'special_privilege'])): ?>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo ($leaveRequest['location_type'] ?? '') === 'within_philippines' ? 'checked' : ''; ?>"></div>
                        <span>Within the Philippines: <?php echo htmlspecialchars($leaveRequest['location_specify'] ?? '_______________'); ?></span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo ($leaveRequest['location_type'] ?? '') === 'outside_philippines' ? 'checked' : ''; ?>"></div>
                        <span>Abroad (Specify): <?php echo htmlspecialchars($leaveRequest['location_specify'] ?? '_______________'); ?></span>
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
                    
                    <div style="margin: 12px 0 8px 0;">
                        <strong style="font-size: 9pt;">In case of Sick Leave:</strong>
                    </div>
                    <?php if ($selectedLeaveType === 'sick'): ?>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo ($leaveRequest['medical_condition'] ?? '') === 'in_hospital' ? 'checked' : ''; ?>"></div>
                        <span>In Hospital (Specify Illness): <?php echo htmlspecialchars($leaveRequest['illness_specify'] ?? '_______________'); ?></span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo ($leaveRequest['medical_condition'] ?? '') === 'out_patient' ? 'checked' : ''; ?>"></div>
                        <span>Out Patient (Specify Illness): <?php echo htmlspecialchars($leaveRequest['illness_specify'] ?? '_______________'); ?></span>
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
                    
                    <div style="margin: 12px 0 8px 0;">
                        <strong style="font-size: 9pt;">In case of Special Leave Benefits for Women:</strong>
                    </div>
                    <?php if ($selectedLeaveType === 'special_women'): ?>
                    <div style="font-size: 8.5pt; padding-left: 18px;">
                        (Specify Illness): <?php echo htmlspecialchars($leaveRequest['special_women_condition'] ?? '_______________'); ?>
                    </div>
                    <?php else: ?>
                    <div style="font-size: 8.5pt; padding-left: 18px;">
                        (Specify Illness): _______________
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin: 12px 0 8px 0;">
                        <strong style="font-size: 9pt;">In case of Study Leave:</strong>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo ($leaveRequest['study_type'] ?? '') === 'completion' ? 'checked' : ''; ?>"></div>
                        <span>Completion of Master's Degree</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox <?php echo ($leaveRequest['study_type'] ?? '') === 'bar_board' ? 'checked' : ''; ?>"></div>
                        <span>BAR/Board Examination Review</span>
                    </div>
                    
                    <div style="margin: 12px 0 8px 0;">
                        <strong style="font-size: 9pt;">Other Purpose:</strong>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Monetization of Leave Credits</span>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Terminal Leave</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 6: Number of Working Days & Dates -->
        <div class="form-section" style="margin-top: 2px;">
            <table style="margin: 0;">
                <tr>
                    <td style="width: 50%; font-weight: bold; padding: 8px;">
                        6.C NUMBER OF WORKING DAYS APPLIED FOR
                        <div style="font-size: 14pt; font-weight: bold; text-align: center; margin-top: 8px; border: 1px solid #000; padding: 8px;">
                            <?php echo $leaveRequest['days_requested'] ?? 'N/A'; ?>
                        </div>
                        <div style="font-weight: bold; margin-top: 12px; font-size: 9pt;">INCLUSIVE DATES:</div>
                        <div style="margin-top: 4px; padding: 8px; border: 1px solid #000; min-height: 40px; font-size: 9pt;">
                            <?php 
                            // Display inclusive dates
                            if (!empty($leaveRequest['selected_dates'])) {
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
                    <td style="width: 50%; padding: 8px;">
                        <div style="font-weight: bold; margin-bottom: 8px;">6.D COMMUTATION</div>
                        <div class="checkbox-item">
                            <div class="checkbox"></div>
                            <span>Not Requested</span>
                        </div>
                        <div class="checkbox-item">
                            <div class="checkbox"></div>
                            <span>Requested</span>
                        </div>
                        <div style="margin-top: 20px; text-align: center;">
                            <div class="signature-line" style="margin-top: 10px;"></div>
                            <div class="signature-label">(Signature of Applicant)</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Section 7: Details of Action on Application -->
        <div class="form-section" style="margin-top: 2px;">
            <div class="section-header">7. DETAILS OF ACTION ON APPLICATION</div>
            <table style="margin: 0;">
                <tr>
                    <td style="width: 50%; vertical-align: top; padding: 8px;">
                        <div style="font-weight: bold; margin-bottom: 8px;">7.A CERTIFICATION OF LEAVE CREDITS</div>
                        <table style="width: 100%; margin-top: 8px;">
                            <tr>
                                <th></th>
                                <th>VACATION LEAVE</th>
                                <th>SICK LEAVE</th>
                            </tr>
                            <tr>
                                <td style="font-weight: bold;">Total Earned</td>
                                <td style="text-align: center;"><?php echo number_format($leaveRequest['vacation_leave_balance'] ?? 0, 2); ?></td>
                                <td style="text-align: center;"><?php echo number_format($leaveRequest['sick_leave_balance'] ?? 0, 2); ?></td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold;">Less Application</td>
                                <td style="text-align: center;">
                                    <?php 
                                    if (in_array($selectedLeaveType, ['vacation', 'special_privilege'])) {
                                        echo number_format($leaveRequest['days_requested'] ?? 0, 2);
                                    } else {
                                        echo '0.00';
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center;">
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
                                <td style="font-weight: bold;">Balance</td>
                                <td style="text-align: center;">
                                    <?php 
                                    $vlBalance = $leaveRequest['vacation_leave_balance'] ?? 0;
                                    if (in_array($selectedLeaveType, ['vacation', 'special_privilege'])) {
                                        $vlBalance -= ($leaveRequest['days_requested'] ?? 0);
                                    }
                                    echo number_format($vlBalance, 2);
                                    ?>
                                </td>
                                <td style="text-align: center;">
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
                        <div style="margin-top: 20px; text-align: center;">
                            <div style="font-weight: bold; font-size: 8pt;"><?php echo strtoupper($hrInfo['name'] ?? 'N/A'); ?></div>
                            <div style="font-size: 8pt;"><?php echo htmlspecialchars($hrInfo['position'] ?? 'Administrative Officer'); ?></div>
                            <?php if ($leaveRequest['status'] === 'approved'): ?>
                                <div class="approval-text">APPROVED</div>
                            <?php endif; ?>
                            <div style="margin-top: 30px;">
                                <div class="signature-line" style="margin-top: 10px;"></div>
                                <div class="signature-label">(Authorized Officer)</div>
                            </div>
                        </div>
                    </td>
                    <td style="width: 50%; vertical-align: top; padding: 8px;">
                        <div style="font-weight: bold; margin-bottom: 8px;">7.B RECOMMENDATION</div>
                        <div class="checkbox-item">
                            <div class="checkbox <?php echo $leaveRequest['status'] === 'approved' ? 'checked' : ''; ?>"></div>
                            <span>For approval</span>
                        </div>
                        <div class="checkbox-item">
                            <div class="checkbox <?php echo $leaveRequest['status'] === 'rejected' ? 'checked' : ''; ?>"></div>
                            <span>For disapproval due to:</span>
                        </div>
                        <div style="margin: 8px 0; padding-left: 18px; min-height: 40px; border-bottom: 1px solid #000;">
                            <?php 
                            if ($leaveRequest['status'] === 'rejected') {
                                echo htmlspecialchars($leaveRequest['director_rejection_reason'] ?? 
                                     $leaveRequest['admin_rejection_reason'] ?? 
                                     $leaveRequest['dept_head_rejection_reason'] ?? '');
                            }
                            ?>
                        </div>
                        <div style="margin-top: 30px; text-align: center;">
                            <?php if ($leaveRequest['dept_head_approval'] === 'approved'): ?>
                                <div class="approval-text">APPROVED</div>
                            <?php endif; ?>
                            <div class="signature-line" style="margin-top: 10px;"></div>
                            <div class="signature-label">(Immediate Head)</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Section 8: Approved For -->
        <div class="form-section" style="margin-top: 2px;">
            <table style="margin: 0;">
                <tr>
                    <td style="width: 50%; padding: 8px;">
                        <div style="font-weight: bold; margin-bottom: 8px;">7.C APPROVED FOR:</div>
                        <div style="display: flex; gap: 20px; margin: 8px 0;">
                            <div style="flex: 1;">
                                <div class="field-value" style="text-align: center; font-weight: bold;">
                                    <?php 
                                    if ($leaveRequest['status'] === 'approved') {
                                        echo $leaveRequest['approved_days'] ?? $leaveRequest['days_requested'] ?? '';
                                    }
                                    ?>
                                </div>
                                <div style="text-align: center; font-size: 8pt; margin-top: 2px;">days with pay</div>
                            </div>
                            <div style="flex: 1;">
                                <div class="field-value" style="text-align: center; font-weight: bold;"></div>
                                <div style="text-align: center; font-size: 8pt; margin-top: 2px;">days without pay</div>
                            </div>
                            <div style="flex: 1;">
                                <div class="field-value" style="text-align: center; font-weight: bold;"></div>
                                <div style="text-align: center; font-size: 8pt; margin-top: 2px;">others (specify)</div>
                            </div>
                        </div>
                    </td>
                    <td style="width: 50%; padding: 8px;">
                        <div style="font-weight: bold; margin-bottom: 8px;">7.D DISAPPROVED DUE TO:</div>
                        <div style="min-height: 40px; border-bottom: 1px solid #000; margin: 8px 0;">
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
            </table>
        </div>
        
        <!-- Final Signature -->
        <div style="margin-top: 30px; text-align: center;">
            <div style="font-weight: bold; font-size: 10pt;"><?php echo strtoupper($directorInfo['name'] ?? 'N/A'); ?></div>
            <div style="font-size: 9pt;"><?php echo htmlspecialchars($directorInfo['position'] ?? 'Campus Director'); ?></div>
            <?php if ($leaveRequest['director_approval'] === 'approved'): ?>
                <div class="approval-text">APPROVED</div>
            <?php endif; ?>
            <div class="signature-line" style="margin-top: 20px; width: 300px; margin-left: auto; margin-right: auto;"></div>
            <div class="signature-label">(Authorized Official)</div>
        </div>
        
    </div>
    
    <!-- Print Button -->
    <button onclick="window.print()" class="no-print" style="position: fixed; top: 20px; right: 20px; background: #3b82f6; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000;">
        <i class="fas fa-print"></i> Print Form
    </button>
</body>
</html>
