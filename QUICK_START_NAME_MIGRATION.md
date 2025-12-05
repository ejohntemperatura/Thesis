# Quick Start: Name Field Migration

## TL;DR - Just Run This

```bash
# Step 1: Test the name splitting logic (optional but recommended)
php scripts/test_name_split.php

# Step 2: Run the migration
php scripts/split_name_fields.php

# Step 3: Test in browser
# - Go to http://localhost/ELMS/app/modules/admin/views/manage_user.php
# - Try adding a new employee
# - Try editing an existing employee
# - Print a leave request to see the new name format
```

## What This Does

✅ Splits employee names into First, Middle, and Last name fields  
✅ Updates the Add/Edit employee forms  
✅ Updates the print leave request to show names properly  
✅ Keeps backward compatibility - nothing breaks  

## Before Migration
- Single "Name" field: `John Michael Doe`

## After Migration
- First Name: `John`
- Middle Name: `Michael`
- Last Name: `Doe`

## Print Leave Request Format
```
2. NAME:     (Last)    |    (First)    |    (Middle)
             Doe       |     John      |    Michael
```

## That's It!

The system will automatically:
- Split existing names intelligently
- Show three fields in forms
- Display names correctly on printed forms
- Keep everything working as before

For detailed information, see `NAME_FIELDS_MIGRATION_INSTRUCTIONS.md`
