# Monthly Leave Accrual - Quick Start Guide

## What Changed?

### Before (Old System)
- New employees got **15 days VL** and **10 days SL** immediately
- All credits given upfront

### After (New System)
- New employees start with **1.25 days VL** and **1.25 days SL**
- Every month, they get **+1.25 VL** and **+1.25 SL**
- After 12 months = 15 days total (same annual amount, just spread out)

## Quick Setup (3 Steps)

### Step 1: Run Migration Script
```bash
php scripts/migrate_to_monthly_accrual.php
```

Choose your migration option:
- **Option 1**: Reset everyone to 1.25 (fresh start)
- **Option 2**: Keep current balances, start accruing monthly
- **Option 3**: Calculate fair balance based on service months

### Step 2: Set Up Cron Job
Add to your crontab (runs 1st of every month):
```bash
0 0 1 * * /usr/bin/php /path/to/elms/cron/process_monthly_leave_accrual.php
```

### Step 3: Test It
- Go to Admin Dashboard → Leave Accrual Management
- Click "Trigger Monthly Accrual Now"
- Verify employees received 1.25 days

## How It Works

### New Employee Example
```
Month 1 (Hired):     1.25 VL, 1.25 SL
Month 2 (Accrual):   2.50 VL, 2.50 SL  (+1.25 each)
Month 3 (Accrual):   3.75 VL, 3.75 SL  (+1.25 each)
...
Month 12 (Accrual): 15.00 VL, 15.00 SL (+1.25 each)
```

### Special Leave Privilege (SLP)
- Still 3 days per year
- Given all at once every January 1st
- Non-cumulative (resets each year)

## Admin Interface

Access: `/app/modules/admin/views/leave_accrual_management.php`

Features:
- ✅ View all employee balances
- ✅ See last accrual date
- ✅ Manually trigger accrual
- ✅ View accrual history

## FAQs

**Q: What if I already gave employees 15 days?**
A: Run migration script Option 2 to keep existing balances and start monthly accrual from there.

**Q: Can I manually add credits?**
A: Yes! Use "Add Leave Credits" page (formerly CTO Management) to add any leave type.

**Q: What happens if cron job fails?**
A: You can manually trigger accrual from admin dashboard. System prevents duplicate accrual.

**Q: How do I check if accrual ran?**
A: Check logs at `/logs/leave_accrual_YYYY-MM.log` or view history in admin dashboard.

**Q: Can employees see their accrual history?**
A: Currently admin-only. Can be added to employee dashboard if needed.

## Troubleshooting

### Employees not getting accrual?
Check:
1. Account status is "active"
2. Role is "employee"
3. Been in service for 1+ month
4. `last_leave_credit_update` is not current month

### Cron job not running?
1. Verify cron is configured: `crontab -l`
2. Check PHP path: `which php`
3. Test manually: `php cron/process_monthly_leave_accrual.php`
4. Check logs: `tail -f logs/leave_accrual_*.log`

## Support

Need help? Check:
- Full documentation: `/docs/LEAVE_ACCRUAL_SYSTEM.md`
- Log files: `/logs/leave_accrual_*.log`
- Database table: `leave_credit_history`
