# Name Fields Migration Instructions

## Overview
This migration splits the single `name` field in the `employees` table into three separate fields:
- `first_name`
- `middle_name`
- `last_name`

## Changes Made

### 1. Database Changes
- Added three new columns: `first_name`, `middle_name`, `last_name`
- The original `name` column is kept for backward compatibility
- Migration script automatically splits existing names

### 2. Form Changes (manage_user.php)
- **Add User Form**: Now has three separate fields for First Name, Middle Name, and Last Name
- **Edit User Form**: Updated to use three separate name fields
- First Name and Last Name are required fields
- Middle Name is optional

### 3. Print Leave Request (print_leave_request.php)
- Updated to display name in proper format: Last Name, First Name, Middle Name
- Each name component appears in its own column as per the official form format

## Migration Steps

### Step 1: Run the Database Migration
```bash
php scripts/split_name_fields.php
```

This script will:
1. Add the three new columns to the `employees` table
2. Automatically migrate existing data by splitting names:
   - Single name → First Name only
   - Two names → First Name + Last Name
   - Three+ names → First Name + Middle Name(s) + Last Name
3. Keep the original `name` column for backward compatibility

### Step 2: Verify the Migration
After running the migration, check:
1. All employees have their names properly split
2. The manage user page loads correctly
3. You can add new employees with separate name fields
4. You can edit existing employees
5. Print leave request shows names in the correct format

### Step 3: Test the System
1. **Add a new employee**:
   - Go to Manage Users
   - Click "Add User"
   - Fill in First Name, Middle Name (optional), Last Name
   - Submit and verify

2. **Edit an existing employee**:
   - Click edit on any employee
   - Verify the three name fields are populated correctly
   - Make changes and save

3. **Print a leave request**:
   - Go to any leave request
   - Click print
   - Verify the name appears in three separate columns: (Last), (First), (Middle)

## Rollback (if needed)
If you need to rollback, you can drop the new columns:
```sql
ALTER TABLE employees 
DROP COLUMN first_name,
DROP COLUMN middle_name,
DROP COLUMN last_name;
```

## Notes
- The original `name` column is still updated with the full name for backward compatibility
- Middle name is optional and can be left blank
- The system will continue to work with existing code that references the `name` column
- You can safely drop the `name` column later once you've verified all code uses the new fields

## Files Modified
1. `scripts/split_name_fields.php` - Migration script (NEW)
2. `app/modules/admin/views/manage_user.php` - Updated forms and backend logic
3. `app/modules/admin/views/print_leave_request.php` - Updated name display format
