# Leave Credits Accrual System

## Overview
The ELMS (Employee Leave Management System) implements an automatic monthly accrual system for leave credits, following Civil Service Commission (CSC) standards.

## Accrual Rates

### Monthly Accrual (Automatic)
- **Vacation Leave (VL)**: 1.25 days per month = 15 days annually
- **Sick Leave (SL)**: 1.25 days per month = 15 days annually

### Annual Accrual
- **Special Leave Privilege (SLP)**: 3 days per year (accrued every January 1st)

### Earned Leave Credits
- **CTO (Compensatory Time Off)**: Earned through overtime work
  - Regular overtime: 1:1 ratio
  - Holiday work: 1.5:1 ratio
  - Weekend work: 1:1 ratio
  - Maximum accumulation: 40 hours
  - Expiration: 6 months from earning date

- **Service Credits**: Earned for special service, manually added by admin

## How It Works

### Automatic Monthly Processing
1. The system runs a cron job on the 1st of each month
2. All active employees are evaluated for eligibility
3. Eligible employees receive their monthly accrual
4. Credits are added to employee balances
5. All transactions are logged in the `leave_credit_history` table

### Eligibility Criteria
An employee is eligible for monthly accrual if:
- Account status is "active"
- Role is "employee"
- Has been in service for at least 1 month
- Has not already received accrual for the current month

### Initial Credits for New Employees
- New employees start with **1.25 days VL and 1.25 days SL** (equivalent to 1 month)
- This gives them immediate leave credits upon hiring
- After 1 month of service, they receive their first monthly accrual
- The system calculates service duration from `service_start_date` or `created_at` date

### Monthly Accrual After Initial Credits
- Each month, employees receive an additional 1.25 days VL and 1.25 days SL
- Credits accumulate over time (cumulative)
- Example progression:
  - Month 1 (Start): 1.25 VL, 1.25 SL
  - Month 2: 2.50 VL, 2.50 SL
  - Month 3: 3.75 VL, 3.75 SL
  - Month 12: 15.00 VL, 15.00 SL (full year)

## Setup Instructions

### 1. Cron Job Setup

#### Linux/Unix
Add to crontab:
```bash
# Run on the 1st of every month at midnight
0 0 1 * * /usr/bin/php /path/to/elms/cron/process_monthly_leave_accrual.php
```

#### Windows Task Scheduler
1. Open Task Scheduler
2. Create Basic Task
3. Trigger: Monthly, Day 1, Time: 00:00
4. Action: Start a program
5. Program: `C:\php\php.exe`
6. Arguments: `C:\path\to\elms\cron\process_monthly_leave_accrual.php`

### 2. Manual Trigger (Admin Interface)
Admins can manually trigger accrual through:
- Navigate to: Admin Dashboard → Leave Accrual Management
- Click "Trigger Monthly Accrual Now"
- System will process all eligible employees

### 3. Database Requirements
Ensure these tables exist:
- `employees` (with `last_leave_credit_update` field)
- `leave_credit_history` (for audit trail)

## Admin Interface Features

### Leave Accrual Management Page
Location: `/app/modules/admin/views/leave_accrual_management.php`

Features:
1. **Accrual Information Cards**
   - Display current accrual rates
   - Visual representation of monthly/annual credits

2. **Manual Trigger**
   - Button to manually process monthly accrual
   - Confirmation dialog for safety
   - Real-time results display

3. **Employee Status Table**
   - View all employees' current balances
   - See last accrual date for each employee
   - Identify employees needing accrual

4. **Accrual History**
   - Recent accrual transactions
   - Filterable by employee, date, credit type
   - Complete audit trail

## API/Service Class

### LeaveAccrualService
Location: `/app/core/services/LeaveAccrualService.php`

#### Methods:

**processMonthlyAccrual()**
- Processes accrual for all eligible employees
- Returns: Array with processed, skipped, and error counts

**manualAccrualForEmployee($employeeId)**
- Manually trigger accrual for specific employee
- Returns: Success/failure status with message

**getEmployeeAccrualHistory($employeeId, $limit = 12)**
- Retrieve accrual history for an employee
- Returns: Array of accrual records

## Logging

### Log Files
Location: `/logs/leave_accrual_YYYY-MM.log`

Log entries include:
- Timestamp
- Employee ID and name
- Accrual amounts
- Success/skip/error status
- Reason for skip (if applicable)

### Database Logging
All accruals are recorded in `leave_credit_history` table:
- `employee_id`: Employee receiving credit
- `credit_type`: Type of leave (vacation, sick, special_privilege)
- `credit_amount`: Amount accrued
- `accrual_date`: Date of accrual
- `created_at`: Timestamp of record creation

## Important Notes

### Special Leave Privilege (SLP)
- Accrued only once per year in January
- Non-cumulative (resets to 3 days each year)
- Not carried over to next year

### Vacation and Sick Leave
- Cumulative (unused credits carry over)
- Accrued monthly at 1.25 days
- Can be converted to cash upon retirement (terminal leave)

### CTO Credits
- Not automatically accrued
- Must be earned through overtime/holiday work
- Processed separately via DTR system
- Expires after 6 months

## Troubleshooting

### Employees Not Receiving Accrual
Check:
1. Account status is "active"
2. Role is "employee" (not admin/manager/director)
3. Service duration is at least 1 month
4. `last_leave_credit_update` is not current month

### Duplicate Accrual
- System prevents duplicate accrual in same month
- Check `last_leave_credit_update` field
- Review `leave_credit_history` for duplicates

### Cron Job Not Running
1. Verify cron job is properly configured
2. Check PHP path is correct
3. Ensure file permissions allow execution
4. Review log files for errors

## Future Enhancements

Potential improvements:
- Email notifications after accrual
- Dashboard widget showing next accrual date
- Bulk adjustment tools for corrections
- Integration with payroll system
- Automatic year-end reports

## Support

For issues or questions:
1. Check log files in `/logs/`
2. Review `leave_credit_history` table
3. Contact system administrator
4. Refer to CSC guidelines for policy questions
