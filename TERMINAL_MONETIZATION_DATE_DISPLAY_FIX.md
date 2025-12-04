# Terminal Leave & Monetization Date Display Fix

## Issue
For Terminal Leave and Monetization of Leave Credits requests (leave_type = 'other'), the system was displaying start dates, end dates, and calendar date ranges, which is incorrect. These are leave credit conversions, not calendar-based absences.

## Solution
Updated all leave request detail displays and modals to:
- **Hide** start date, end date, and calendar date ranges for "other" leave type
- **Show** only the number of working days (leave credits) to be converted
- Add explanatory text that these are leave credits being converted to cash

## Files Changed

### 1. User Module
#### app/modules/user/views/dashboard.php
- **Recent Leave Requests section** (line ~1188)
  - For "other" type: Shows "X working day(s) to convert to cash"
  - For regular leave: Shows date range as before

#### app/modules/user/views/leave_history.php
- **Leave history table** (line ~132)
  - For "other" type: Shows green badge with "X working day(s)" and "Leave credits to convert" label
  - For regular leave: Shows calendar date badges as before

### 2. Admin Module
#### app/modules/admin/views/dashboard.php
- **Leave request modal** (line ~819)
  - For "other" type: Shows "Leave Credits to Convert" with working days count
  - For regular leave: Shows start/end dates as before

#### app/modules/admin/views/leave_management.php
- **Leave details modal** (line ~1031)
  - For "other" type: Shows "Leave Credits to Convert" section with working days
  - For regular leave: Shows "Selected Leave Days" with calendar dates as before

### 3. Admin Module - Mobile View
#### app/modules/admin/views/dashboard.php
- **Mobile view section** (line ~451)
  - For "other" type: Shows "X working day(s)" and "Leave credits" label
  - For regular leave: Shows date range as before

### 4. Director Module
#### app/modules/director/views/dashboard.php
- **Leave request modal** (line ~524)
  - For "other" type: Shows "Leave Credits to Convert" with working days and explanatory text
  - For regular leave: Shows "Selected Leave Days" with calendar date badges as before

### 5. Department Module
#### app/modules/department/views/dashboard.php
- **Leave request modal** (line ~569)
  - For "other" type: Shows "Leave Credits to Convert" with working days
  - For regular leave: Shows start/end dates as before

## Display Logic

### For Terminal Leave / Monetization (leave_type = 'other'):
```php
// PHP Example - Table Display (Aligned with date badges)
<?php if ($request['leave_type'] === 'other'): ?>
    <div class="flex flex-wrap gap-1 items-center">
        <span class="bg-green-500/20 text-green-400 px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap border border-green-500/30">
            <?php echo $request['working_days_applied'] ?? $request['days_requested']; ?> working day(s)
        </span>
        <span class="text-xs text-slate-400">Leave credits</span>
    </div>
<?php endif; ?>
```

```javascript
// JavaScript Example
${request.leave_type === 'other' ? `
    <div>
        <label>Leave Credits to Convert</label>
        <p>${request.working_days_applied || request.days_requested} working day(s)</p>
        <p class="text-sm">This represents leave credits to be converted to cash, not calendar dates.</p>
    </div>
` : `
    <!-- Show calendar dates -->
`}
```

### For Regular Leave Types:
- Shows start date and end date
- Shows selected calendar dates as badges
- Shows duration in days

## What Users Will See

### Before (Incorrect):
```
Terminal Leave
Dec 4, 2025 - Dec 4, 2025
10 days requested
```

### After (Correct):
```
Terminal Leave
10 working day(s) to convert to cash
This represents leave credits to be converted to cash, not calendar dates.
```

## Key Points

1. **No Calendar Dates**: Terminal Leave and Monetization requests don't show start/end dates or calendar date badges
2. **Working Days Only**: Display shows the `working_days_applied` field value
3. **Clear Labeling**: Uses "Leave Credits to Convert" or "working day(s)" to make it clear
4. **Explanatory Text**: Includes helper text explaining these are credit conversions, not absences
5. **Consistent Across All Views**: Applied to dashboards, modals, history, and management pages
6. **Specific Leave Type Display**: Shows "Terminal Leave" or "Monetization of Leave Credits" instead of generic "OTHER"
7. **Aligned Display**: Working days badges align horizontally with regular date badges in tables

## Database Fields Used

For "other" leave type:
- `working_days_applied` - Primary field for number of leave credits
- `days_requested` - Fallback if working_days_applied is not set
- `other_purpose` - Specifies 'terminal_leave' or 'monetization'
- `leave_type` - Set to 'other'

Note: `start_date` and `end_date` are still stored in the database but not displayed for "other" type.

## JavaScript Function Updates

Updated `getLeaveTypeDisplayNameJS()` function in all dashboard files to handle the `other_purpose` parameter:

```javascript
function getLeaveTypeDisplayNameJS(leaveType, originalLeaveType = null, otherPurpose = null) {
    // Handle "other" leave type (Terminal Leave / Monetization)
    if (leaveType === 'other' && otherPurpose) {
        if (otherPurpose === 'terminal_leave') {
            return 'Terminal Leave';
        } else if (otherPurpose === 'monetization') {
            return 'Monetization of Leave Credits';
        }
    }
    // ... rest of function
}
```

Files updated:
- `app/modules/admin/views/dashboard.php` - Updated function and `resolveLeaveTypeLabel()` call
- `app/modules/admin/views/leave_management.php` - Updated function and `resolveLeaveTypeLabel()` call
- `app/modules/director/views/dashboard.php` - Updated function and modal call
- `app/modules/department/views/dashboard.php` - Updated function and modal call

## Additional Updates (Tables)

### 6. Admin Module - Table Display
#### app/modules/admin/views/dashboard.php
- **Leave requests table** (line ~495)
  - For "other" type: Shows green badge with working days and "Leave credits" label
  - For regular leave: Shows calendar date badges as before

#### app/modules/admin/views/leave_management.php
- **Leave requests table** (line ~444)
  - For "other" type: Shows green badge with working days and "Leave credits" label
  - For regular leave: Shows calendar date badges as before

### 7. Director Module - Table Display
#### app/modules/director/views/dashboard.php
- **Leave requests table** (line ~199)
  - For "other" type: Shows green badge with working days and "Leave credits" label
  - For regular leave: Shows calendar date badges as before

### 8. Department Module - Table Display
#### app/modules/department/views/dashboard.php
- **Leave requests table** (line ~200)
  - For "other" type: Shows green badge with working days and "Leave credits" label
  - For regular leave: Shows calendar date badges as before

### 9. User Module - Modal Display
#### app/modules/user/views/leave_history.php
- **Leave request details modal** (line ~447)
  - For "other" type: Shows large green box with working days and explanatory text
  - For regular leave: Shows calendar dates as before

## Testing Checklist

- [x] User dashboard shows working days for Terminal Leave/Monetization
- [x] Leave history table shows working days badge for Terminal Leave/Monetization
- [x] Leave history table shows specific leave type (Terminal Leave or Monetization) instead of "OTHER"
- [x] Leave history modal shows working days for Terminal Leave/Monetization
- [x] Admin dashboard table shows working days for Terminal Leave/Monetization
- [x] Admin dashboard modal shows working days for Terminal Leave/Monetization
- [x] Admin dashboard modal shows specific leave type (Terminal Leave or Monetization) instead of "OTHER"
- [x] Admin dashboard mobile view shows working days for Terminal Leave/Monetization
- [x] Admin leave management table shows working days for Terminal Leave/Monetization
- [x] Admin leave management modal shows working days for Terminal Leave/Monetization
- [x] Admin leave management modal shows specific leave type (Terminal Leave or Monetization) instead of "OTHER"
- [x] Director dashboard table shows working days for Terminal Leave/Monetization
- [x] Director dashboard modal shows working days for Terminal Leave/Monetization
- [x] Director dashboard modal shows specific leave type (Terminal Leave or Monetization) instead of "OTHER"
- [x] Department dashboard table shows working days for Terminal Leave/Monetization
- [x] Department dashboard modal shows working days for Terminal Leave/Monetization
- [x] Department dashboard modal shows specific leave type (Terminal Leave or Monetization) instead of "OTHER"
- [x] Regular leave types still show calendar dates correctly
- [x] No start/end dates visible for Terminal Leave/Monetization
