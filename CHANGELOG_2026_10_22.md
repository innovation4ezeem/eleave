# Changelog — 2026-10-22

October 2026 release: Malaysia timezone switch (T-006), Warning Letters (T-004 / T-005), and Subdivisions with penalty targeting (T-007).

---

## Release Order

The three tickets must go live in this order. Each has its own deploy guide in `docs/deploy/`.

1. **T-006** — timezone switch and one-time data shift (`T-006_README.md`)
2. **T-004** — Warning Letters (`T-004_README.md`)
3. **T-007** — Subdivisions + penalties (`T-007_subdivisions_penalties.sql`)

- **Why**: T-004 and T-007 create new tables. Releasing them after T-006 means those tables start out in Malaysia time and do not need shifting.
- **Production**: cPanel shared hosting, no SSH, no artisan. Every database change is a plain SQL script run once in phpMyAdmin. Export the database before each one.

---

## Part 1: Timezone Shift to Malaysia Time (T-006)

### Problem
- Production stored every datetime in UTC, so a leave submitted at 09:21 MYT showed as 01:21
- Laravel does not convert on read/write, so changing the config alone would leave all old rows 8 hours behind

### Configuration

#### config/app.php (server only)
- **File**: `/hrsystem/config/app.php` (edited in cPanel File Manager, not uploaded)
- **Change**: `'timezone' => 'UTC'` → `'timezone' => 'Asia/Kuala_Lumpur'`
- **Also**: Delete `bootstrap/cache/config.php` if present, otherwise the change is ignored
- **Note**: The repo copy of `config/app.php` still reads `UTC`. Only the live file changes, because it may differ from the repo.

### Data Migration

#### One-time timezone shift script
- **File**: `docs/deploy/T-006_timezone_shift.sql`
- **Change**: Shifts every datetime/timestamp column in the 22 app tables by +8 hours, once
- **Rule**: Applied per column, never per row: `col = IF(col < cutoff, col + 8 HOUR, col)`
  - A leave created before go-live and approved after it only has `created_at` shifted
  - `decided_at` / `updated_at` written after go-live are already Malaysia time and are left alone
- **Cutoff**: `UTC_TIMESTAMP() + INTERVAL 4 HOUR`, taken right after the config edit. The script rejects a cutoff that is not 1–7 hours ahead.
- **Safety**:
  - A marker table (`t006_timezone_shift_marker`) blocks a second run (a second run would shift rows by 16 hours)
  - Every datetime column is assigned explicitly so `ON UPDATE CURRENT_TIMESTAMP` cannot overwrite it
  - No transaction: 11 live tables are MyISAM and cannot roll back. If the script stops partway, restore from the export.
- **Ends with**: a list of every column touched and a coverage check of datetime columns it did not touch

#### leave_applications.applied_at special rule
- **Why**: `applied_at` has `ON UPDATE CURRENT_TIMESTAMP` and MySQL runs on Malaysia time, so rows updated after creation already hold Malaysia time
- **Buckets**:
  - A: `applied_at` within 5s of `created_at` → UTC → shifted +8h
  - B: `applied_at` = `updated_at` + 8h → already Malaysia → left alone
  - C: anything else (e.g. id 2212) → left alone
- **Backup**: `applied_at` is copied to `_t006_applied_at_backup` before the shift

#### Scope
- **Shifted**: all datetime/timestamp columns of the 22 Laravel/app tables, including the live-only `staff_achievements`
- **Not touched**: `_stg_leave_applications`, pure DATE columns (`start_date`, `end_date`, `joined_at`, `penalty_date`, ...), integer unix timestamps (`sessions.last_activity`, `cache.expiration`, `jobs.*_at`)
- **Known side effect**: historical leave records store a user-picked date in `decided_at` at 00:00:00. After the shift it reads 08:00:00 on the same date. The date does not change.

### Scheduler
- **File**: `routes/console.php` (unchanged)
- **Effect**: The leave-year rollover now fires at 00:05 Malaysia time instead of 08:05

### Rollback
- **File**: `docs/deploy/T-006_rollback.sql`
- Reads the cutoff from the marker table, reverses every shifted column, restores `applied_at` from the backup, then drops both helper tables

---

## Part 2: Warning Letters (T-004 / T-005)

### Overview
- Admins upload warning letters (one PDF or image per letter) against an employee
- HODs and employees get a read-only list
- Replaces the earlier "filtered Penalty list" idea: does **not** use the `Penalty` model or `penalty_type = 'warning'`

### Database Migrations

#### 1. Create warning_letters table
- **File**: `database/migrations/2026_10_09_000001_create_warning_letters_table.php`
- **Columns**: `user_id`, `issued_date`, `level` (string), `notes` (admin-only), `original_filename`, `mime_type`, `file_size`, `uploaded_by`, timestamps, soft deletes
- **Production**: `docs/deploy/T-004_warning_letters.sql` (InnoDB, utf8mb4)

#### 2. Add audit log to warning letters
- **File**: `database/migrations/2026_10_09_000002_add_audit_to_warning_letters.php`
- **Change**: Added nullable `warning_letters.updated_by` and created the `warning_letter_events` table
- **Purpose**: `uploaded_by` stays the original issuer; `updated_by` is the last admin who edited the letter or replaced its file

### Models

#### WarningLetter (new)
- **File**: `app/Models/WarningLetter.php`
- **Change**: Letter record with soft deletes; `notes` hidden from serialisation

#### WarningLetterEvent (new)
- **File**: `app/Models/WarningLetterEvent.php`
- **Change**: Audit row per action: issued, edited, file_replaced, deleted (admins) and downloaded (anyone)
- **Rule**: Event details never contain the admin-only notes

#### User Model
- **File**: `app/Models/User.php`
- **Change**: Added `warningLetters()` HasMany relationship

### Authorization

#### WarningLetterPolicy (new)
- **File**: `app/Policies/WarningLetterPolicy.php`
- **Rules**:
  - Admin: view all, issue, edit, delete, see history
  - HOD: own letters (with download) + letters of employees in own department (date and level only; download URL returns 403)
  - Employee: own letters only
- **Null safety**: A user or HOD with no department never matches the department rule
- **History**: `viewHistory` is admin-only

### Controllers

#### Admin WarningLetterController (new)
- **File**: `app/Http/Controllers/Admin/WarningLetterController.php`
- **Methods**: `index`, `create`, `store`, `edit`, `update`, `destroy`
- **File replacement**: The old file is kept, renamed to `warning-letters/replaced/<YYYYMMDD-HHMMSS>-<old name>`, and its stored name is recorded in the event
- **Audit detail**: Edit events show old → new values per field (employee name resolved from `user_id`, dates formatted, notes truncated to 60 chars)
- **Delete**: Soft delete; the row and the file stay

#### WarningLetterController (new)
- **File**: `app/Http/Controllers/WarningLetterController.php`
- **Methods**:
  - `index()`: HOD/employee list, scoped in the query (not the view); `notes` is never selected
  - `download()`: policy-checked, original filename, logged
  - `preview()`: `Content-Disposition: inline` so PDFs open in a new tab
- **Purpose**: Read-only access for non-admins

### Form Validation

#### StoreWarningLetterRequest / UpdateWarningLetterRequest (new)
- **Files**: `app/Http/Requests/Admin/StoreWarningLetterRequest.php`, `UpdateWarningLetterRequest.php`
- **Rules**: PDF or image only, 5 MB max
- **Config**: `config/warning_letters.php` (override with `WARNING_LETTER_MAX_KB` in `.env`)

### Storage
- **Location**: `storage/app/private/warning-letters/` (private `local` disk, same as penalty documents)
- **Public access**: none; files are only served through the policy-checked routes
- **Replaced files**: `warning-letters/replaced/`, never deleted by the app

### Routes

#### Added routes
- **File**: `routes/web.php`
- Routes added:
  - `GET /warning-letters` → `warning-letters.index`
  - `GET /warning-letters/{warningLetter}/download` → `warning-letters.download`
  - `GET /warning-letters/{warningLetter}/preview` → `warning-letters.preview`
  - `GET|POST|PUT|DELETE /admin/warning-letters/...` → `admin.warning-letters.{index,create,store,edit,update,destroy}` (admin-only group)

### Views

#### Admin Warning Letters (new)
- **Files**: `resources/views/admin/warning-letters/_form.blade.php`, `create.blade.php`, `edit.blade.php`, `index.blade.php`
- **Changes**:
  - Level is a text input with a datalist of suggestions: Verbal, 1st Written, 2nd Written, Final, Other
  - Edit page shows the History panel (admin only)
  - File links open the preview in a new tab

#### Warning Letters list (new)
- **File**: `resources/views/warning-letters/index.blade.php`
- **Changes**:
  - HOD: "Warning Letters" — department members' letters show date and level only, no file name or link
  - Employee: "My Warning Letters"

#### Other views
- **`resources/views/partials/navbar.blade.php`**: "Warning Letters" in Administration (admin) and in E-Leave (HOD/employee)
- **`resources/views/admin/users/show.blade.php`**: Warning Letters button, filtered by `?user_id=`
- **`resources/views/profile/edit.blade.php`**: red-outline Warning Letters button, shown only when the employee has letters
- **`resources/views/dashboard.blade.php`**: Warning Letters card

### Server Settings
- PHP `upload_max_filesize` ≥ 5M and `post_max_size` ≥ 6M (cPanel > MultiPHP INI Editor)
- Create `/hrsystem/storage/app/private/warning-letters` with permissions 775

### Tests
- **File**: `tests/Feature/WarningLetterTest.php`
- Covers access rules per role, HOD restrictions on other people's letters, null-department handling, uploads and the audit log
- Builds its own tables on in-memory SQLite

---

## Part 3: Subdivisions + Penalties Update (T-007)

### Overview
- Employees can be tagged with one or more subdivisions (teams)
- A subdivision belongs to one department, or to none (cross-department)
- Penalties can now target a whole department, or a subdivision within it, as well as a single employee

### Database Migrations

#### 1. Create subdivisions table
- **File**: `database/migrations/2026_10_08_000001_create_subdivisions_table.php`
- **Columns**: `department_id` (nullable), `name` (unique), timestamps
- **Behavior**: `department_id` NULL = cross-department subdivision. Deleting a department turns its subdivisions into cross-department ones instead of deleting them (`ON DELETE SET NULL`).

#### 2. Create subdivision_user pivot
- **File**: `database/migrations/2026_10_08_000002_create_subdivision_user_table.php`
- **Change**: Primary key (`subdivision_id`, `user_id`); rows cascade on delete of either side

#### 3. Add subdivision_id to penalties
- **File**: `database/migrations/2026_10_08_000003_add_subdivision_id_to_penalties_table.php`
- **Change**: Nullable `subdivision_id` after `department_id`; existing rows get NULL

#### 4. Make subdivision department optional
- **File**: `database/migrations/2026_10_08_000004_alter_subdivisions_nullable_department.php`
- **Change**: Nullable `department_id`, unique on `name` only
- **Note**: Not run on production. 000001 already creates this end state, so the SQL script only records 000004 as run.

#### 5. Make penalties.user_id nullable
- **File**: `database/migrations/2026_10_08_000005_make_penalties_user_id_nullable.php`
- **Purpose**: A department- or subdivision-wide penalty has no single employee

#### Production script
- **File**: `docs/deploy/T-007_subdivisions_penalties.sql`
- **Change**: Creates the final structure directly and records all five migrations in one batch
- **Order**: Run the SQL **before** uploading the code; the new code reads `penalties.subdivision_id`
- **MyISAM note**: `penalties` is MyISAM on live, so `subdivision_id` gets an index only, no foreign key. Deleting a subdivision does **not** clear `penalties.subdivision_id` in the database.
- **Rollback**: `docs/deploy/T-007_subdivisions_penalties_rollback.sql` (deletes all subdivisions, their employee tags and the subdivision scope on penalties)

### Models

#### Subdivision (new)
- **File**: `app/Models/Subdivision.php`
- **Relationships**: `department()` BelongsTo, `users()` BelongsToMany

#### Department / User / Penalty Models
- **`app/Models/Department.php`**: added `subdivisions()` HasMany (ordered by name)
- **`app/Models/User.php`**: added `subdivisions()` BelongsToMany
- **`app/Models/Penalty.php`**: added `subdivision_id` to Fillable and `subdivision()` BelongsTo

### Controllers

#### Admin SubdivisionController (new)
- **File**: `app/Http/Controllers/Admin/SubdivisionController.php`
- **Methods**: `index`, `store`, `update`, `destroy`
- **Validation**: Name unique across all subdivisions; department optional

#### Admin PenaltyController
- **File**: `app/Http/Controllers/Admin/PenaltyController.php`
- **Changes**:
  - New target mode: **Individual** (department taken from the employee) or **Department/Division** (department picked, optional subdivision)
  - Department-target documents are stored under `penalty-documents/dept-{id}`
  - Index eager-loads `subdivision`; department-target rows show department/subdivision
  - Fixed the "(Deleted)" label so it no longer appears on penalties that have no employee

#### Employee PenaltyController
- **File**: `app/Http/Controllers/Employee/PenaltyController.php`
- **Change**: "My Penalties" now shows three kinds:
  - Personal (targeted at the employee)
  - Dept-wide (same department, no subdivision)
  - Subdivision-scoped (same department, employee is in that subdivision)

#### Admin UserController
- **File**: `app/Http/Controllers/Admin/UserController.php`
- **Changes**:
  - Admin can assign subdivisions on the user edit page (HODs cannot)
  - On department change, subdivisions tied to the old department are detached automatically; cross-department ones are kept

### Authorization

#### PenaltyPolicy
- **File**: `app/Policies/PenaltyPolicy.php`
- **Change**: `view` now also allows dept-wide penalties for the employee's department, and subdivision-scoped penalties for members of that subdivision

#### User::canApproveFor
- **File**: `app/Models/User.php`
- **Change**: Restored the `(int)` cast from live on the HOD department comparison
- **Why**: A strict `===` fails when one id is an int and the other a string, which hid the Approve/Reject buttons for HODs

### Form Validation

#### StorePenaltyRequest / UpdatePenaltyRequest
- **Files**: `app/Http/Requests/Admin/StorePenaltyRequest.php`, `UpdatePenaltyRequest.php`
- **Changes**:
  - Added `target_type`: `in:individual,department`
  - `user_id` required only for individual target
  - `department_id` required only for department target
  - Added optional `subdivision_id`

#### UpdateUserRequest
- **File**: `app/Http/Requests/Admin/UpdateUserRequest.php`
- **Change**: Added subdivision IDs validation

### Routes

#### Added routes
- **File**: `routes/web.php`
- Routes added (admin):
  - `GET /admin/subdivisions` → `admin.subdivisions.index`
  - `POST /admin/subdivisions` → `admin.subdivisions.store`
  - `PUT /admin/subdivisions/{subdivision}` → `admin.subdivisions.update`
  - `DELETE /admin/subdivisions/{subdivision}` → `admin.subdivisions.destroy`

### Views

#### Subdivisions Index (new)
- **File**: `resources/views/admin/subdivisions/index.blade.php`
- **Changes**:
  - Subdivisions grouped by department, plus a "Standalone" group for cross-department ones
  - Department dropdown with "— none (cross-department) —" option

#### Penalties Form
- **File**: `resources/views/admin/penalties/_form.blade.php`
- **Changes**:
  - Individual / Department radio toggle
  - Subdivision dropdown filtered to the chosen department + cross-department subdivisions, with "— whole department —" as the default
  - Subdivision field hidden for individual target

#### Penalties Show / Index
- **Files**: `resources/views/admin/penalties/show.blade.php`, `index.blade.php`
- **Change**: Show target (employee, or department / subdivision) for department-target penalties

#### Employee Penalties
- **File**: `resources/views/employee/penalties/index.blade.php`
- **Change**: New Scope column with badges: Personal (gray), subdivision name (blue), Dept-wide (yellow)

#### Users Edit / Show
- **Files**: `resources/views/admin/users/edit.blade.php`, `show.blade.php`
- **Changes**:
  - Edit: subdivision checkboxes, limited to the user's department + cross-department ones, grouped by department
  - Show: lists the user's subdivisions

#### Navbar / Dashboard
- **Files**: `resources/views/partials/navbar.blade.php`, `resources/views/dashboard.blade.php`
- **Change**: "Subdivisions" link and card in Administration

---

## Summary of Changes by Category

### Database Schema
- ✅ One-time +8h shift of all app datetime columns (T-006, SQL only)
- ✅ Added warning_letters and warning_letter_events tables
- ✅ Added subdivisions and subdivision_user tables
- ✅ Added penalties.subdivision_id; made penalties.user_id nullable

### Configuration
- ✅ App timezone set to Asia/Kuala_Lumpur on the server
- ✅ Added config/warning_letters.php (5 MB upload limit)

### Models
- ✅ New WarningLetter, WarningLetterEvent, Subdivision models
- ✅ User: warningLetters() and subdivisions() relationships, restored (int) cast in canApproveFor()
- ✅ Department: subdivisions(); Penalty: subdivision()

### Authorization
- ✅ New WarningLetterPolicy (admin / HOD / employee rules, admin-only history)
- ✅ PenaltyPolicy covers dept-wide and subdivision-scoped penalties

### Controllers
- ✅ New admin and user-facing Warning Letters controllers (incl. preview)
- ✅ New SubdivisionController
- ✅ Penalties: Individual vs Department/Division target mode
- ✅ Employee penalties show personal, dept-wide and subdivision-scoped penalties
- ✅ User edit: subdivision assignment, auto-detach on department change

### Views
- ✅ Warning Letters admin pages, read-only list, profile and user-page buttons
- ✅ Subdivisions admin page
- ✅ Penalty form target toggle and subdivision picker; Scope badge for employees
- ✅ Dashboard cards and navbar links for Warning Letters and Subdivisions

### Routes
- ✅ Warning Letters routes (user + admin)
- ✅ Subdivisions routes (admin)

---

## Testing Checklist

### T-006
- [ ] The two pending leaves from 8 Oct morning that showed 01:21 / 01:22 now read 09:21 / 09:22
- [ ] applied_at consistency query returns exactly 1 (id 2212, still `2026-09-10 10:00:28`)
- [ ] New test leave shows correct Malaysia time (not +8h again)
- [ ] `rows_in_future` shows 0 for every row
- [ ] Coverage check lists only `_stg_leave_applications` columns as untouched
- [ ] cPanel cron runs `php artisan schedule:run` every minute
- [ ] Helper tables dropped after sign-off

### T-004
- [ ] Admin uploads a PDF letter → appears in list, downloads under original filename
- [ ] Employee sees only own letters; admin notes not visible anywhere
- [ ] HOD sees department letters as date + level only; download URL returns 403
- [ ] HOD can download a letter issued to themselves
- [ ] HOD of another department does not see the letter
- [ ] Non-admin opening `/admin/warning-letters/create` → 403
- [ ] `.exe` / `.txt` and files over 5 MB rejected
- [ ] Preview opens PDF inline in a new tab
- [ ] History panel shows Issued / Edited (old → new) / File replaced / Downloaded
- [ ] Replaced file kept under `warning-letters/replaced/`
- [ ] Delete is soft: row keeps `deleted_at`, file stays on disk

### T-007
- [ ] Create a department subdivision and a cross-department subdivision
- [ ] Assign subdivisions on user edit → only user's dept + cross-dept options shown
- [ ] Change a user's department → old-department subdivisions detached, cross-dept kept
- [ ] Individual penalty → shows as "Personal" to that employee only
- [ ] Dept-wide penalty → visible to every employee in the department ("Dept-wide" badge)
- [ ] Subdivision penalty → visible only to members of that subdivision (subdivision badge)
- [ ] HOD can only create/view penalties in own department
- [ ] HOD still sees Approve/Reject on department leave applications (int cast fix)

---

## Files Modified Summary

**Database**: 7 migrations (2 Warning Letters, 5 Subdivisions/Penalties) + 5 deploy SQL scripts (T-006 shift/rollback, T-004, T-007 + rollback)  
**Config**: 1 file (warning_letters.php) + live config/app.php edit  
**Models**: 6 files (WarningLetter, WarningLetterEvent, Subdivision new; User, Department, Penalty)  
**Policies**: 2 files (WarningLetterPolicy new; PenaltyPolicy)  
**Controllers**: 6 files (Admin/WarningLetterController, WarningLetterController, SubdivisionController new; Admin/PenaltyController, Employee/PenaltyController, UserController)  
**Requests**: 5 files (StoreWarningLetterRequest, UpdateWarningLetterRequest new; StorePenaltyRequest, UpdatePenaltyRequest, UpdateUserRequest)  
**Views**: 15 files (warning-letters admin ×4 + list, subdivisions index, penalties _form/show/index, employee penalties, users edit/show, profile edit, navbar, dashboard)  
**Routes**: 1 file (web.php)  
**Tests**: 1 file (WarningLetterTest)  

**Total: 50+ files modified/created**
