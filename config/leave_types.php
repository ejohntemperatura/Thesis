<?php
/**
 * Centralized Leave Types Configuration
 * Based on Civil Service Commission (CSC) Rules and Regulations
 * Updated to comply with official CSC leave entitlements
 */

function getLeaveTypes() {
    return [
    // Standard CSC Leave Types with Credits
    'vacation' => [
        'name' => 'Vacation Leave (VL)',
        'icon' => 'fas fa-umbrella-beach',
        'color' => 'bg-blue-500',
        'requires_credits' => true,
        'credit_field' => 'vacation_leave_balance',
        'description' => '15 days per year with full pay',
        'annual_credits' => 15,
        'cumulative' => true,
        'commutable' => true
    ],
    'sick' => [
        'name' => 'Sick Leave (SL)',
        'icon' => 'fas fa-thermometer-half',
        'color' => 'bg-red-500',
        'requires_credits' => true,
        'credit_field' => 'sick_leave_balance',
        'description' => '15 days per year with full pay',
        'annual_credits' => 15,
        'cumulative' => true,
        'commutable' => true,
        'requires_medical_certificate' => true
    ],
    'special_privilege' => [
        'name' => 'Special Leave Privilege (SLP)',
        'icon' => 'fas fa-star',
        'color' => 'bg-yellow-500',
        'requires_credits' => true,
        'credit_field' => 'special_leave_privilege_balance',
        'description' => '3 days per year, non-cumulative and non-commutable',
        'annual_credits' => 3,
        'cumulative' => false,
        'commutable' => false
    ],
    'maternity' => [
        'name' => 'Maternity Leave',
        'icon' => 'fas fa-baby',
        'color' => 'bg-pink-500',
        'requires_credits' => true,
        'credit_field' => 'maternity_leave_balance',
        'description' => '105 days with full pay, with option to extend for 30 days without pay',
        'annual_credits' => 105,
        'cumulative' => false,
        'commutable' => false,
        'gender_restricted' => 'female',
        'extension_available' => true,
        'extension_days' => 30
    ],
    'paternity' => [
        'name' => 'Paternity Leave',
        'icon' => 'fas fa-male',
        'color' => 'bg-cyan-500',
        'requires_credits' => true,
        'credit_field' => 'paternity_leave_balance',
        'description' => '7 working days for the first four deliveries of the legitimate spouse',
        'annual_credits' => 7,
        'cumulative' => false,
        'commutable' => false,
        'gender_restricted' => 'male',
        'delivery_limit' => 4
    ],
    'solo_parent' => [
        'name' => 'Solo Parent Leave',
        'icon' => 'fas fa-user-friends',
        'color' => 'bg-orange-500',
        'requires_credits' => true,
        'credit_field' => 'solo_parent_leave_balance',
        'description' => '7 working days per year',
        'annual_credits' => 7,
        'cumulative' => false,
        'commutable' => false
    ],
    'vawc' => [
        'name' => 'VAWC Leave',
        'icon' => 'fas fa-shield-alt',
        'color' => 'bg-red-600',
        'requires_credits' => true,
        'credit_field' => 'vawc_leave_balance',
        'description' => 'Violence Against Women and Their Children Leave - 10 days with full pay',
        'annual_credits' => 10,
        'cumulative' => false,
        'commutable' => false,
        'gender_restricted' => 'female'
    ],
    'special_women' => [
        'name' => 'Special Leave Benefits for Women',
        'icon' => 'fas fa-female',
        'color' => 'bg-rose-500',
        'requires_credits' => true,
        'credit_field' => 'special_women_leave_balance',
        'description' => 'Special Leave Benefits for Women (e.g., RA 9710) as per policy',
        'annual_credits' => 0,
        'cumulative' => false,
        'commutable' => false,
        'gender_restricted' => 'female'
    ],
    'rehabilitation' => [
        'name' => 'Rehabilitation Leave',
        'icon' => 'fas fa-heart',
        'color' => 'bg-green-500',
        'requires_credits' => true,
        'credit_field' => 'rehabilitation_leave_balance',
        'description' => 'Up to 6 months with pay, for job-related injuries or illnesses',
        'annual_credits' => 180, // 6 months
        'cumulative' => false,
        'commutable' => false,
        'requires_medical_certificate' => true
    ],
    'study' => [
        'name' => 'Study Leave',
        'name_with_note' => 'Study Leave (Without Pay)',
        'icon' => 'fas fa-graduation-cap',
        'color' => 'bg-indigo-500',
        'requires_credits' => false,
        'credit_field' => null,
        'description' => 'Up to 6 months for qualified government employees pursuing studies',
        'annual_credits' => 0,
        'cumulative' => false,
        'commutable' => false,
        'without_pay' => true
    ],
    'terminal' => [
        'name' => 'Terminal Leave',
        'icon' => 'fas fa-sign-out-alt',
        'color' => 'bg-gray-600',
        'requires_credits' => true,
        'credit_field' => 'terminal_leave_balance',
        'description' => 'Accumulated Vacation and Sick Leave credits convertible to cash upon separation',
        'annual_credits' => 0,
        'cumulative' => true,
        'commutable' => true,
        'cash_convertible' => true
    ],
    'cto' => [
        'name' => 'Compensatory Time Off (CTO)',
        'icon' => 'fas fa-clock',
        'color' => 'bg-purple-500',
        'requires_credits' => true,
        'credit_field' => 'cto_balance',
        'description' => 'Time off earned for overtime work, holidays, or special assignments',
        'annual_credits' => 0, // Earned through work, not annual allocation
        'cumulative' => true,
        'commutable' => true,
        'earned_through_work' => true,
        'expiration_months' => 6, // Must be used within 6 months
        'overtime_rate' => 1.0, // 1:1 ratio for regular overtime
        'holiday_rate' => 1.5, // 1.5:1 ratio for holiday work
        'weekend_rate' => 1.0, // 1:1 ratio for weekend work
        'max_accumulation' => 40, // Maximum 40 hours CTO can be accumulated
        'requires_approval' => true,
        'requires_supervisor_approval' => true
    ],
    'service_credit' => [
        'name' => 'Service Credits',
        'icon' => 'fas fa-hand-holding-heart',
        'color' => 'bg-emerald-500',
        'requires_credits' => true,
        'credit_field' => 'service_credit_balance',
        'description' => 'Credits earned for service that can be used as leave days',
        'annual_credits' => 0,
        'cumulative' => true,
        'commutable' => false
    ],
    'without_pay' => [
        'name' => 'Without Pay Leave',
        'name_with_note' => 'Without Pay Leave (No Salary)',
        'icon' => 'fas fa-exclamation-triangle',
        'color' => 'bg-gray-500',
        'requires_credits' => false,
        'credit_field' => null,
        'description' => 'Leave without pay when employee has insufficient leave credits',
        'annual_credits' => 0,
        'cumulative' => false,
        'commutable' => false,
        'without_pay' => true,
        'requires_approval' => true,
        'requires_supervisor_approval' => true
    ]
    ];
}

/**
 * Helper function to determine if a leave request should display as "without pay"
 * @param string $leave_type The current leave type
 * @param string $original_leave_type The original leave type (if converted)
 * @param array $leaveTypes The leave types configuration
 * @return bool True if the leave should show as without pay
 */
function isLeaveWithoutPay($leave_type, $original_leave_type = null, $leaveTypes = null) {
    if (!$leaveTypes) {
        $leaveTypes = getLeaveTypes();
    }
    
    // If leave_type is explicitly 'without_pay', it's without pay
    if ($leave_type === 'without_pay') {
        return true;
    }
    
    // If original_leave_type exists and current type is 'without_pay' or empty, it was converted to without pay
    if (!empty($original_leave_type) && ($leave_type === 'without_pay' || empty($leave_type))) {
        return true;
    }
    
    // Check if the current leave type is inherently without pay
    if (isset($leaveTypes[$leave_type]) && isset($leaveTypes[$leave_type]['without_pay']) && $leaveTypes[$leave_type]['without_pay']) {
        return true;
    }
    
    // Check if the original leave type was inherently without pay
    if (!empty($original_leave_type) && isset($leaveTypes[$original_leave_type]) && isset($leaveTypes[$original_leave_type]['without_pay']) && $leaveTypes[$original_leave_type]['without_pay']) {
        return true;
    }
    
    return false;
}

/**
 * Helper function to get the display name for a leave type with appropriate without pay indicator
 * @param string $leave_type The current leave type
 * @param string $original_leave_type The original leave type (if converted)
 * @param array $leaveTypes The leave types configuration
 * @return string The display name with or without pay indicator
 */
function getLeaveTypeDisplayName($leave_type, $original_leave_type = null, $leaveTypes = null) {
    if (!$leaveTypes) {
        $leaveTypes = getLeaveTypes();
    }
    
    $isWithoutPay = isLeaveWithoutPay($leave_type, $original_leave_type, $leaveTypes);
    
    // Determine the base leave type to display
    $baseType = null;
    if (!empty($original_leave_type) && ($leave_type === 'without_pay' || empty($leave_type))) {
        // Use original type if it was converted to without pay
        $baseType = $original_leave_type;
    } else {
        // Use current type
        $baseType = $leave_type;
    }
    
    // Get the display name
    // Normalize common variants (hyphens, spaces, non-alphanumerics, plurals, aliases)
    $normalizedBase = strtolower(trim((string)$baseType));
    $normalizedBase = str_replace(['-', ' '], '_', $normalizedBase);
    $normalizedBase = preg_replace('/[^a-z0-9_]/', '', $normalizedBase);
    // Normalize common Service Credit variants
    if ($normalizedBase === 'service_credits') { $normalizedBase = 'service_credit'; }
    if ($normalizedBase === 'service' || $normalizedBase === 'servicecredit' || $normalizedBase === 'svc_credit' || $normalizedBase === 'svc') {
        $normalizedBase = 'service_credit';
    }
    if (strpos($normalizedBase, 'service') !== false && strpos($normalizedBase, 'credit') !== false) {
        $normalizedBase = 'service_credit';
    }
    if (!isset($leaveTypes[$normalizedBase]) && substr($normalizedBase, -1) === 's') {
        $singular = rtrim($normalizedBase, 's');
        if (isset($leaveTypes[$singular])) { $normalizedBase = $singular; }
    }

    // If still unknown, try original_leave_type as a fallback mapping
    if (!isset($leaveTypes[$normalizedBase]) && !empty($original_leave_type)) {
        $tmp = strtolower(trim((string)$original_leave_type));
        $tmp = str_replace(['-', ' '], '_', $tmp);
        $tmp = preg_replace('/[^a-z0-9_]/', '', $tmp);
        if ($tmp === 'service_credits' || $tmp === 'service' || $tmp === 'servicecredit' || $tmp === 'svc_credit' || $tmp === 'svc' || (strpos($tmp, 'service') !== false && strpos($tmp, 'credit') !== false)) {
            $tmp = 'service_credit';
        }
        if (!isset($leaveTypes[$tmp]) && substr($tmp, -1) === 's') {
            $sg = rtrim($tmp, 's');
            if (isset($leaveTypes[$sg])) { $tmp = $sg; }
        }
        if (isset($leaveTypes[$tmp])) {
            $normalizedBase = $tmp;
        }
    }

    if (isset($leaveTypes[$normalizedBase])) {
        $leaveTypeConfig = $leaveTypes[$normalizedBase];
        
        if ($isWithoutPay) {
            // Show name with without pay indicator
            if (isset($leaveTypeConfig['name_with_note'])) {
                return $leaveTypeConfig['name_with_note'];
            } else {
                return $leaveTypeConfig['name'] . ' (Without Pay)';
            }
        } else {
            // Show regular name
            return $leaveTypeConfig['name'];
        }
    } else {
        // Fallback for unknown types
        $displayName = ucfirst(str_replace('_', ' ', $normalizedBase));
        return $isWithoutPay ? $displayName . ' (Without Pay)' : $displayName;
    }
}
?>