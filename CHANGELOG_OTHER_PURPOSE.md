# Changelog - Other Purpose Leave Implementation

## [1.0.0] - 2024-12-04

### Added
- **New Leave Type**: "Other (Terminal Leave / Monetization)" option in leave type dropdown
- **Other Purpose Dropdown**: Secondary dropdown to select specific purpose (Terminal Leave or Monetization)
- **Working Days Input**: Numeric input field for entering working days (replaces calendar for "other" type)
- **Database Columns**:
  - `other_purpose` (ENUM: 'terminal_leave', 'monetization')
  - `working_days_applied` (INT)
- **Configuration Function**: `getOtherPurposeOptions()` in `config/leave_types.php`
- **Migration Scripts**:
  - `scripts/add_other_purpose_fields.php` - Main migration
  - `scripts/add_other_to_enum.php` - Enum update
  - `scripts/verify_changes.php` - Verification
  - `scripts/test_other_purpose_query.php` - Query testing
- **Documentation**:
  - `IMPLEMENTATION_SUMMARY.md` - Complete implementation overview
  - `UI_CHANGES_GUIDE.md` - Visual UI flow guide
  - `DEVELOPER_QUICK_REFERENCE.md` - Developer reference

### Changed
- **Leave Type Dropdown**: Removed "Terminal Leave" and "Monetization of Leave Credits" as standalone options
- **Calendar Picker**: Now hidden when "Other" leave type is selected
- **Form Validation**: Updated to handle "other" type with different required fields
- **JavaScript Function**: `toggleModalConditionalFields()` enhanced to show/hide conditional fields
- **Submit Handler**: `submit_leave.php` updated to process other_purpose and working_days_applied
- **Database INSERT**: Updated to include new fields with fallback for backward compatibility

### Removed
- Terminal Leave from main leave types array in `config/leave_types.php`
- Monetization of Leave Credits from main leave types array in `config/leave_types.php`

### Technical Details

#### Database Schema Changes
```sql
-- Added columns
ALTER TABLE leave_requests ADD COLUMN other_purpose ENUM('terminal_leave', 'monetization') DEFAULT NULL;
ALTER TABLE leave_requests ADD COLUMN working_days_applied INT DEFAULT NULL;

-- Updated enum
ALTER TABLE leave_requests MODIFY leave_type ENUM(..., 'other') NOT NULL;
```

#### Files Modified
1. `config/leave_types.php`
   - Removed monetization and terminal from main array
   - Added getOtherPurposeOptions() function

2. `app/modules/user/views/dashboard.php`
   - Added "Other" option to dropdown
   - Added other_purpose dropdown (conditional)
   - Added working_days_applied input (conditional)
   - Updated toggleModalConditionalFields() function

3. `app/modules/user/views/submit_leave.php`
   - Added other_purpose and working_days_applied handling
   - Added validation for "other" type
   - Updated INSERT statement
   - Added 'other' to known types

#### Backward Compatibility
- Existing leave types unaffected
- Fallback queries for older database schemas
- Graceful handling of missing columns

### Migration Status
✅ All migrations completed successfully
✅ Database changes verified
✅ No data loss
✅ Backward compatible

### Testing Status
✅ Database schema verified
✅ Configuration functions tested
✅ Query functionality tested
✅ No syntax errors in modified files

### Known Issues
None

### Future Enhancements
- [ ] Add more "other purpose" options if needed
- [ ] Create admin report for other purpose leaves
- [ ] Add email notification templates for other purposes
- [ ] Consider adding purpose-specific validation rules

### Breaking Changes
None - All changes are backward compatible

### Upgrade Notes
1. Run migration scripts in order:
   ```bash
   php scripts/add_other_purpose_fields.php
   php scripts/add_other_to_enum.php
   php scripts/verify_changes.php
   ```

2. Clear any application cache if applicable

3. Test leave submission with "Other" type

4. Verify existing leave requests still display correctly

### Rollback Instructions
If rollback is needed:
```sql
ALTER TABLE leave_requests DROP COLUMN other_purpose;
ALTER TABLE leave_requests DROP COLUMN working_days_applied;
-- Remove 'other' from enum (restore original enum values)
```

### Contributors
- Implementation Date: December 4, 2024
- Tested: ✅ Database, ✅ Configuration, ✅ Queries

### References
- See `IMPLEMENTATION_SUMMARY.md` for complete overview
- See `UI_CHANGES_GUIDE.md` for UI flow
- See `DEVELOPER_QUICK_REFERENCE.md` for code examples
