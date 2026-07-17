# Changelog — 2026-07-17

Complete list of all changes made to implement employee status-based role control and leave priority management.

---

## Part 1: Employee Status Consolidation & Leave Entitlement Tiers

### Database Migrations

#### 1. Add employee_status to users table
- **File**: `database/migrations/2026_07_17_000001_add_employee_status_to_users_table.php`
- **Change**: Added enum column with values: probation, intern, permanent
- **Default**: permanent
- **Purpose**: Distinguish employment status for leave entitlements

#### 2. Add employee_status to leave_entitlement_tiers table
- **File**: `database/migrations/2026_07_17_000002_add_employee_status_to_leave_entitlement_tiers_table.php`
- **Change**: Added nullable employee_status column to tiers
- **Purpose**: Allow different leave quotas per employee status
- **Effect**: Probation/Intern can have 2 WFH/month (24/year), Permanent tiered by years of service

#### 3. Fix unique constraint on leave_entitlement_tiers
- **File**: `database/migrations/2026_07_17_000004_fix_leave_entitlement_tiers_unique_constraint.php`
- **Change**: Updated unique constraint from (leave_type_id, min_years) to (leave_type_id, min_years, employee_status)
- **Why**: Multiple status tiers can now exist for same leave type + years combo

#### 4. Consolidate is_active into employee_status
- **File**: `database/migrations/2026_07_17_000005_consolidate_employee_status_and_is_active.php`
- **Change**: Dropped is_active boolean column
- **New behavior**: NULL = inactive/disabled, non-null value = active with that status

#### 5. Make employee_status nullable
- **File**: `database/migrations/2026_07_17_000006_make_employee_status_nullable.php`
- **Change**: Added nullable() to employee_status enum column
- **Purpose**: Support disabled/fired users (employee_status = NULL)

### Model Updates

#### User Model
- **File**: `app/Models/User.php`
- **Changes**:
  - Added 'employee_status' to #[Fillable] attribute
  - Removed 'is_active' from Fillable
  - Removed 'is_active' from casts()
  - Added `isActive()` method: `return $this->employee_status !== null`
- **Purpose**: Single source of truth for active/inactive status

#### LeaveEntitlementTier Model
- **File**: `app/Models/LeaveEntitlementTier.php`
- **Change**: Added 'employee_status' to #[Fillable] array
- **Purpose**: Allow setting status when creating/updating tiers

### Service Layer

#### LeaveEntitlementService
- **File**: `app/Services/LeaveEntitlementService.php`
- **Changes**:
  - Updated `entitlementFor()` to filter tiers by employee_status
  - Added special logic: Probation/Intern always use 0+ tier (ignore years of service)
  - Added null handling with `whereNull()` for "All Employees" tiers
  - Returns `default_annual_quota` if no matching tier found
- **Behavior**: 
  - Probation/Intern: Always get 2 WFH days/month (24/year)
  - Permanent: Tiered by years of service (0-1: 24/year, 2+: 48/year, etc.)

### Authentication

#### AuthenticatedSessionController
- **File**: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- **Line 31**: Changed from `if (! Auth::user()->is_active)` to `if (! Auth::user()->isActive())`
- **Effect**: Disabled users (NULL employee_status) cannot login

---

## Part 2: User Management & Admin Controls

### Controllers

#### Admin UserController
- **File**: `app/Http/Controllers/Admin/UserController.php`
- **New methods**:
  - `disable(User $user)`: Sets employee_status to NULL, logs audit trail
  - `enable(User $user)`: Sets employee_status to 'permanent', logs audit trail
- **Purpose**: HOD/Admin can disable/enable users without deletion

#### Admin LeaveBalanceController
- **File**: `app/Http/Controllers/Admin/LeaveBalanceController.php`
- **New method**:
  - `recalculate(User $user)`: Deletes old balances and recreates with current tier configuration
  - Creates audit trail entries with field_changed='reset', reason='Reset via Recalculate'
  - Logs: "RECALCULATE: User {name} (id={id}), LeaveYear={year}, by {admin_name}"
- **Purpose**: Admins can re-provision balances when tiers change

### Routes

#### Added routes
- **File**: `routes/web.php`
- Routes added:
  - `POST /users/{user}/disable` → `users.disable`
  - `POST /users/{user}/enable` → `users.enable`
  - `POST /users/{user}/balances/recalculate` → `users.balances.recalculate`

### Form Validation

#### StoreLeaveEntitlementTierRequest
- **File**: `app/Http/Requests/Admin/StoreLeaveEntitlementTierRequest.php`
- **Changes**:
  - Added employee_status validation: `in:probation,intern,permanent`
  - Implemented unique rule with employee_status included
  - Added whereNull() for NULL handling to prevent duplicate NULLs
  - Added ignore rule for PUT requests to exclude current tier from uniqueness check
- **Effect**: Can't create duplicate tiers for same leave_type + min_years + status combo

#### UpdateUserRequest
- **File**: `app/Http/Requests/Admin/UpdateUserRequest.php`
- **Change**: Added employee_status field validation as required enum
- **Purpose**: Enforce valid status selection when editing users

### Views

#### Users Index
- **File**: `resources/views/admin/users/index.blade.php`
- **Changes**:
  - Added employee_status column with badge display
  - Added filter dropdown: Probation/Intern/Permanent/Inactive
  - Badges: Probation/Intern/Permanent (blue) or "Inactive" (red)
  - Filter includes "inactive" option for whereNull('employee_status')

#### Users Create/Edit
- **Files**: `resources/views/admin/users/create.blade.php`, `edit.blade.php`
- **Changes**:
  - Added employee_status dropdown with Probation/Intern/Permanent options
  - Placed after designation field
  - Removed is_active checkbox
  - Edit form shows red alert banner for disabled users
  - Alert includes green "Enable User" button

#### Entitlement Tiers Index
- **File**: `resources/views/admin/entitlement-tiers/index.blade.php`
- **Changes**:
  - Added employee_status filter dropdown with "inactive" option
  - Simplified form from Bootstrap grid to mb-3 spacing
  - Added badge display: "All Employees" (gray) for NULL or status name (blue)
  - Updated dropdown label from "Employee Status (optional)" to "Applies To"

#### Entitlement Tiers Edit
- **File**: `resources/views/admin/entitlement-tiers/edit.blade.php`
- **Changes**:
  - Created edit form with disabled leave_type_id and min_years fields (read-only)
  - Included hidden inputs for disabled fields to ensure POST capture
  - Made employee_status the only editable field

#### Leave Balances Edit
- **File**: `resources/views/admin/balances/edit.blade.php`
- **Changes**:
  - Added "Recalculate" button (yellow/warning style) next to "Save Balances" button
  - Recalculate form separate from update form to avoid form nesting
  - Confirmation dialog: "This will delete all current balances and recalculate based on current tiers. Continue?"
  - Success message: "Leave balances recalculated successfully."

---

## Part 3: Leave Priority & Validation Rules

### Leave Application Validation

#### StoreLeaveApplicationRequest
- **File**: `app/Http/Requests/Employee/StoreLeaveApplicationRequest.php`

##### Removed old logic:
- Removed `checkRewardLeave()` method (only checked Annual → Reward)

##### Implemented new priority hierarchy:
- **Method**: `checkLeavePriority()`
- **Priority order**: Annual → Reward → Additional → Unpaid
- **Carry Forward**: No restrictions (can use anytime)
- **Validation**:
  - Validates all higher-priority leaves are exhausted before current leave type
  - Applied to both regular and split leave validation
  - Error message: "{leaveType} can only be used after {higherType} is exhausted. You still have {remaining} day(s) remaining."

---

## Part 4: Tentative Leave Balance Deduction

### Balance Calculation Enhancement

#### LeaveApplicationController
- **File**: `app/Http/Controllers/Employee/LeaveApplicationController.php`
- **Method**: `create(Request $request)`
- **Changes**:
  - Added calculation of pending days for each leave type
  - Queries all pending + approved leave applications within leave year
  - Passes `$pendingDays` array to view
- **Formula**: `Entitlement - Used - Pending`

#### StoreLeaveApplicationRequest
- **File**: `app/Http/Requests/Employee/StoreLeaveApplicationRequest.php`
- **Methods**: `checkBalance()` and `checkLeavePriority()`
- **Changes**:
  - Both methods now include pending days in balance calculation
  - Query: Sum total_days from leave_applications where status IN ('pending', 'approved')
  - Pending days are counted before approval to prevent overbooking
- **Effect**: 
  - Available = Entitlement - Used - Pending
  - Employees see accurate balance accounting for pending applications

### Form Display Enhancement

#### Leave Application Create View
- **File**: `resources/views/employee/leave/create.blade.php`
- **Dropdown labels** now show:
  - Format: "Annual Leave (remaining: 20.0 [15.0 available] day(s))"
  - 20.0 = original entitlement/database balance
  - 15.0 = true available after pending deductions
- **Sidebar balances** now show:
  - Format: "Annual Leave | 20.0 (15.0)"
  - Clear visual separation with helper text: "Showing: remaining (available after pending)"
- **JavaScript preview** uses tentative balance for accurate warnings
- **Data attributes** updated to pass tentative available balance instead of database balance

---

## Part 5: Reward Leave Priority Fix

### Updated Priority Hierarchy

#### StoreLeaveApplicationRequest
- **File**: `app/Http/Requests/Employee/StoreLeaveApplicationRequest.php`
- **Method**: `checkLeavePriority()`
- **Change**: Updated priority order to include Carry Forward
- **Old order**: Annual (1) → Reward (2) → Additional (3) → Unpaid (4)
- **New order**: Annual (1) → Carry Forward (1.5) → Reward (2) → Additional (3) → Unpaid (4)
- **Effect**:
  - Reward Leave now requires BOTH Annual AND Carry Forward to be exhausted
  - Carry Forward can still be used anytime (applies flexibly)
  - But when Reward checks priority, Carry Forward must also be exhausted

---

## Part 6: Database Column Migration Fixes

### is_active → employee_status Migration

Fixed 5 files still referencing old is_active column:

#### CompetenceController
- **File**: `app/Http/Controllers/Employee/CompetenceController.php`
- **Line 38**: Changed `where('is_active', true)` to `whereNotNull('employee_status')`
- **Purpose**: Fetch active colleagues for competence voting

#### LeaveYearRolloverService
- **File**: `app/Services/LeaveYearRolloverService.php`
- **Line 66**: Changed `where('is_active', true)` to `whereNotNull('employee_status')`
- **Purpose**: Process leave year rollover for all active users

#### LeaveApplicationApprovalService
- **File**: `app/Services/LeaveApplicationApprovalService.php`
- **Lines 247, 251**: Changed both `where('is_active', true)` to `whereNotNull('employee_status')`
- **Purpose**: Route change request and decision emails to active HOD/admin users

#### PenaltyController
- **File**: `app/Http/Controllers/Admin/PenaltyController.php`
- **Line 228**: Changed `where('is_active', true)` to `whereNotNull('employee_status')`
- **Purpose**: Send cash penalty collection emails to active admins

#### LeaveApplicationController
- **File**: `app/Http/Controllers/Employee/LeaveApplicationController.php`
- **Lines 235, 239**: Changed both `where('is_active', true)` to `whereNotNull('employee_status')`
- **Purpose**: Route leave application submission emails to active HOD/admin users

### Column Name Fix

#### StoreLeaveApplicationRequest
- **File**: `app/Http/Requests/Employee/StoreLeaveApplicationRequest.php`
- **Methods**: `checkBalance()` and `checkLeavePriority()`
- **Change**: Changed `sum('days_applied')` to `sum('total_days')`
- **Why**: Correct column name in leave_applications table

---

## Summary of Changes by Category

### Database Schema
- ✅ Added employee_status enum to users
- ✅ Made employee_status nullable for disabled users
- ✅ Added employee_status to leave_entitlement_tiers
- ✅ Fixed unique constraint on tiers

### Models
- ✅ Updated User model with isActive() method
- ✅ Updated LeaveEntitlementTier with employee_status in Fillable

### Services
- ✅ Enhanced LeaveEntitlementService with status-based tier matching
- ✅ Special logic for Probation/Intern (ignore years of service)

### Controllers
- ✅ Added disable/enable user functionality
- ✅ Added recalculate leave balances functionality
- ✅ Fixed 5 controllers using old is_active column

### Validation
- ✅ Implemented full leave priority hierarchy (Annual → CF → Reward → Additional → Unpaid)
- ✅ Added tentative leave deduction (pending + approved counted before approval)
- ✅ Medical Leave document requirement (already existed, verified working)

### Views
- ✅ Enhanced users list with employee_status filter and badges
- ✅ Updated create/edit forms for employee_status
- ✅ Added disable/enable UI with alert banner
- ✅ Enhanced leave application form to show tentative balances
- ✅ Added recalculate button with confirmation

### Routes
- ✅ Added disable/enable user routes
- ✅ Added recalculate balances route

---

## Testing Checklist

- [ ] Create Probation user → gets 2 WFH/month (24/year)
- [ ] Create Intern user → gets 2 WFH/month (24/year)
- [ ] Create Permanent user → gets 48 WFH/year (for 0+ years of service)
- [ ] Disable user → cannot login
- [ ] Enable user → can login again
- [ ] Recalculate balances → re-provisions with current tier config
- [ ] Apply Annual Leave (10 days) pending → balance shows (20-10=10 available)
- [ ] Try Reward while Annual pending → blocked with correct message
- [ ] Apply Carry Forward pending → add to pending count
- [ ] Try Reward with both AL + CF pending → blocked
- [ ] Form shows correct balance format: "20.0 (10.0)" in sidebar
- [ ] Medical Leave requires document upload
- [ ] Leave application submission emails send to active HOD/admin
- [ ] Competence voting page loads (test is_active fix)
- [ ] Leave year rollover processes active users only

---

## Files Modified Summary

**Database**: 5 migrations  
**Models**: 2 files (User, LeaveEntitlementTier)  
**Services**: 3 files (LeaveEntitlementService, LeaveYearRolloverService, LeaveApplicationApprovalService)  
**Controllers**: 6 files (UserController, LeaveBalanceController, CompetenceController, PenaltyController, LeaveApplicationController, LeaveBalanceController)  
**Requests**: 1 file (StoreLeaveApplicationRequest, UpdateUserRequest, StoreLeaveEntitlementTierRequest)  
**Views**: 6 files (users index/create/edit, entitlement-tiers index/edit, balances edit)  
**Routes**: 1 file (web.php)  

**Total: 24+ files modified/created**
