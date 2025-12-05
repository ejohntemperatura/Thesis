# Cascading Rejection Implementation

## Overview
Modified the leave request rejection logic to allow cascading reviews through the approval chain. Previously, when any approver rejected a request, it was immediately marked as "rejected". Now, rejections cascade through the chain, allowing subsequent approvers to still review and potentially override the rejection.

## Changes Made

### 1. Rejection Logic Updates

#### Department Head Rejection
- **Before**: Rejection immediately set `status = 'rejected'`
- **After**: Only sets `dept_head_approval = 'rejected'`, HR can still review

#### HR/Admin Rejection
- **Before**: Required Dept Head approval first, then immediately set `status = 'rejected'`
- **After**: Can reject regardless of Dept Head status, Director can still review

#### Director Rejection
- **Before**: Required both Dept Head and HR approval first, then set `status = 'rejected'`
- **After**: Can reject regardless of previous approvals, only sets final `status = 'rejected'` if all three levels rejected

### 2. Files Modified

#### app/modules/admin/views/leave_management.php
- Updated `$hr_can_act` logic to allow HR to process requests even if Dept Head rejected
- Changed from requiring `$dept_approved` to only checking if HR hasn't acted yet
- Updated status display logic to show each approver's actual status (not cascaded)
- Fixed JavaScript modal to show independent status for each approval level
- Changed "Rejected" badge to "Finally Rejected" when status is actually 'rejected'

#### app/shared/components/leave_actions.php
- Updated rejection logic for all three roles (manager, admin, director)
- Department Head rejection no longer sets final status
- HR rejection no longer requires Dept Head approval
- Director rejection checks if all three rejected before setting final status

#### app/modules/admin/controllers/reject_leave.php
- Removed requirement for Dept Head approval before HR can reject
- Added check: only set final `status = 'rejected'` if all three levels rejected

#### app/modules/director/controllers/reject_leave.php
- Removed requirements for Dept Head and HR approval before Director can reject
- Added check: only set final `status = 'rejected'` if all three levels rejected

#### app/modules/director/views/dashboard.php
- Updated SQL query to show requests even if Dept Head or HR rejected them
- Changed from requiring `dept_head_approval = 'approved' AND admin_approval = 'approved'`
- To only checking `director_approval IS NULL OR director_approval = 'pending'`

#### app/modules/director/api/get_more_requests.php
- Updated "load more" API endpoint with same query changes as dashboard

### 3. New Approval Flow

#### Scenario 1: Department Head Rejects
1. Dept Head rejects → `dept_head_approval = 'rejected'`
2. Request still visible to HR (status remains 'pending')
3. HR can approve or reject
4. If HR approves, request goes to Director
5. If HR rejects, request still goes to Director
6. Only if Director also rejects (and Dept Head rejected), then `status = 'rejected'`

#### Scenario 2: HR Rejects
1. Dept Head approves → `dept_head_approval = 'approved'`
2. HR rejects → `admin_approval = 'rejected'`
3. Request still visible to Director (status remains 'pending')
4. Director can approve or reject
5. Only if Director also rejects (and Dept Head rejected), then `status = 'rejected'`

#### Scenario 3: All Three Reject
1. Dept Head rejects → `dept_head_approval = 'rejected'`
2. HR rejects → `admin_approval = 'rejected'`
3. Director rejects → `director_approval = 'rejected'` AND `status = 'rejected'`
4. Request is finally rejected

### 4. Query Changes

#### Director Dashboard Query
**Before:**
```sql
WHERE lr.dept_head_approval = 'approved'
AND lr.admin_approval = 'approved'
AND (lr.director_approval IS NULL OR lr.director_approval = 'pending')
```

**After:**
```sql
WHERE (lr.director_approval IS NULL OR lr.director_approval = 'pending')
AND lr.status NOT IN ('rejected', 'cancelled')
```

This allows Director to see all requests pending their review, regardless of Dept Head or HR rejection status.

## Benefits

1. **More Flexibility**: Higher-level approvers can override lower-level rejections
2. **Better Review Process**: Each level gets to review independently
3. **Transparency**: All rejection statuses are visible in the approval chain
4. **Fair Process**: Employees get multiple chances for their request to be reviewed

## Data Migration

### Fix Existing Rejected Requests
A migration script was created to fix requests that were rejected under the old logic:

**Script**: `scripts/fix_cascading_rejection_status.php`

This script:
- Finds requests where `status='rejected'` but not all three levels rejected
- Updates their status to `'pending'` so remaining approvers can review them
- Fixed 6 requests in the initial run

**To run:**
```bash
php scripts/fix_cascading_rejection_status.php
```

## Testing Recommendations

1. Test Dept Head rejection → verify HR can still see and act on it
2. Test HR rejection → verify Director can still see and act on it
3. Test all three rejections → verify final status becomes 'rejected'
4. Test mixed approvals/rejections → verify proper flow
5. Verify email notifications still work correctly
6. Check leave management view displays rejection statuses properly
7. Verify old rejected requests now show "Process Request" button for pending approvers
