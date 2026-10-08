# T-004 / T-005 Release: Warning Letters

New feature: admins upload warning letters (a PDF or image per letter) against an employee.
HODs and employees get a read-only list. This replaces the earlier "filtered Penalty list" idea:
it does **not** use the `Penalty` model or `penalty_type = 'warning'`.

**Audit log (admin-only).** `warning_letters.uploaded_by` stays the original issuer;
`warning_letters.updated_by` (nullable) records the last admin who edited a letter or replaced its
file. The `warning_letter_events` table logs every issue, edit, file replacement and delete by an
admin, and every download by anyone (admin, HOD or employee). Event details are short and never
contain the admin-only notes (edits log field names only). When a file is replaced, the old file is
kept: it is renamed to `storage/app/private/warning-letters/replaced/<YYYYMMDD-HHMMSS>-<old name>` and
that stored name is written into the event. The history is shown on the admin edit page only.

cPanel shared hosting, no SSH, no artisan on production. The database change is a plain SQL
script run in phpMyAdmin; the code goes up by FTP like every other release.

## Release order

1. **The `warning_letters` table must be created AFTER T-006 has shipped** (timezone switch and
   data shift). If you must release this before T-006, see "If this ships before T-006" below.
2. **Export the database** (phpMyAdmin > Export > SQL).
3. Run `docs/deploy/T-004_warning_letters.sql` once in phpMyAdmin (the script starts with
   `USE hotelsys_hrsystem;`). It creates the `warning_letters` and `warning_letter_events` tables
   (both InnoDB, utf8mb4) and records both migrations in the `migrations` table with the same
   `batch = MAX(batch) + 1`. Do not upload the SQL file. Expect both row counts to be 0 and two
   `migrations` rows. A second run fails harmlessly ("table already exists"). Tested on MySQL 5.7.44
   with `sql_mode = ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION`: no
   warnings, and the tables match what Laravel's migrations create.
4. **Create the folder** `/hrsystem/storage/app/private/warning-letters` in cPanel File Manager
   if it does not exist, and set its permissions to **775** (right-click > Change Permissions).
   Laravel can create it on the first upload, but doing it now avoids a permissions error on a
   host where the web user cannot create folders.
5. **Upload the new files** (list below), then **merge the three shared files into the live copies
   by hand** (section "Shared files: merge by hand"). Do the `routes/web.php` merge last: the menu
   and routes only go live once it is in place. Do NOT upload this folder's `routes/web.php`,
   `navbar.blade.php` or `User.php`.
6. **Cached routes / config.** In the repo checkout, `bootstrap/cache/` holds only `packages.php`
   and `services.php`: no `config.php` and no `routes-v7.php`, so nothing is cached locally.
   Production may differ, so check `/hrsystem/bootstrap/cache/` in File Manager: if `config.php`
   or `routes-v7.php` is there, delete it (a cached config or route list ignores new files and
   the new pages would 404). Leave `packages.php` and `services.php` alone. If OPcache is on,
   wait for it to expire or ask the host to reset it.
7. Run the checks below.

## Files to upload to /hrsystem (relative paths)

New:
- `app/Http/Controllers/WarningLetterController.php`
- `app/Http/Controllers/Admin/WarningLetterController.php`
- `app/Http/Requests/Admin/StoreWarningLetterRequest.php`
- `app/Http/Requests/Admin/UpdateWarningLetterRequest.php`
- `app/Models/WarningLetter.php`
- `app/Models/WarningLetterEvent.php`
- `app/Policies/WarningLetterPolicy.php`
- `config/warning_letters.php`
- `database/migrations/2026_10_09_000001_create_warning_letters_table.php` and
  `database/migrations/2026_10_09_000002_add_audit_to_warning_letters.php` (for parity; the SQL
  script already created the tables)
- `resources/views/admin/warning-letters/_form.blade.php`, `create.blade.php`, `edit.blade.php`,
  `index.blade.php`
- `resources/views/warning-letters/index.blade.php`

Shared files (do NOT upload; merge by hand, see the next section):
- `app/Models/User.php`
- `resources/views/partials/navbar.blade.php`
- `routes/web.php`

### Shared files: merge by hand

This folder is commit `9681899` (17 Jul 2026, branch `feature/employee-status-and-leave-priority`)
plus Warning Letters. **The live site is ahead of that commit**: a copy of the live files
(`BACKUP_LIVE`) has a `StaffAchievementController`, a `2026_08_05_000001_create_staff_achievements_table`
migration (recorded as applied in the live database) and about 25 other changed files that are not
in `9681899`. Uploading this folder's `routes/web.php` would drop the live staff-achievement routes
and break that feature, so the three shared files must be edited in place on the server. The
Warning Letters changes are small and are saved as `docs/deploy/T-004_shared_files.patch`. A dry run
of that patch against the live copies applies cleanly to `User.php` and `navbar.blade.php`, and to
3 of 4 hunks of `routes/web.php` (the `use` line hunk does not apply because the live `use` block
differs). Add exactly this, keeping everything else:

**`app/Models/User.php`** - add next to the other relations:
```php
public function warningLetters(): HasMany
{
    return $this->hasMany(WarningLetter::class);
}
```

**`routes/web.php`**
1. In the `use` block add:
   `use App\Http\Controllers\Admin\WarningLetterController as AdminWarningLetterController;` and
   `use App\Http\Controllers\WarningLetterController;`
2. Before the `// Personal Competence (peer voting)` comment, inside the `auth` group, add:
```php
Route::get('/warning-letters', [WarningLetterController::class, 'index'])->name('warning-letters.index');
Route::get('/warning-letters/{warningLetter}/download', [WarningLetterController::class, 'download'])->name('warning-letters.download');
```
3. Inside the admin-only group (`Route::middleware('role:admin')->group(...)`), before the
   `// Leave-year archive / locks` comment, add:
```php
Route::prefix('warning-letters')->name('warning-letters.')->group(function () {
    Route::get('/', [AdminWarningLetterController::class, 'index'])->name('index');
    Route::get('/create', [AdminWarningLetterController::class, 'create'])->name('create');
    Route::post('/', [AdminWarningLetterController::class, 'store'])->name('store');
    Route::get('/{warningLetter}/edit', [AdminWarningLetterController::class, 'edit'])->name('edit');
    Route::put('/{warningLetter}', [AdminWarningLetterController::class, 'update'])->name('update');
    Route::delete('/{warningLetter}', [AdminWarningLetterController::class, 'destroy'])->name('destroy');
});
```

**`resources/views/partials/navbar.blade.php`** (4 small edits; the diff is in the patch file)
1. Add `'warning-letters.*'` to the `request()->routeIs([...])` list of the E-Leave dropdown toggle.
2. After the "My Penalties" `<li>` add the `@unless (auth()->user()->isAdmin())` ... `@endunless`
   item linking to `warning-letters.index` (label "Warning Letters" for a HOD, "My Warning Letters"
   for an employee).
3. Add `'admin.warning-letters.*'` to the `routeIs([...])` list of the Administration toggle.
4. Inside the Administration `@if (auth()->user()->isAdmin())` block, add a "Warning Letters" `<li>`
   linking to `admin.warning-letters.index`, before "Departments".

Before editing, download the live copies and keep them as a backup.

**Never upload or overwrite `.env`, `storage/` or `vendor/`.** The live `.env` holds the
production settings, `storage/` holds the live uploads, sessions and logs, and `vendor/` is the
server's installed dependencies. Also do not upload `tests/` or `docs/`.

## Server settings to check

- **Upload limits.** The app allows 5 MB (`config/warning_letters.php`, or `WARNING_LETTER_MAX_KB`
  in `.env`). PHP must allow it too: in cPanel > MultiPHP INI Editor set `upload_max_filesize`
  to at least 5M and `post_max_size` to at least 6M. The shared-host default is often 2M, and
  then a 3 MB letter fails before Laravel sees it.
- **Storage.** Files go to `storage/app/private/warning-letters/` (the private `local` disk, the
  same disk the penalty documents use). Nothing goes to `public/` and `storage:link` is not used.
  `storage/app/private` must be writable by the web user (it already is for penalty documents).
- **Replaced files.** Replacing a letter's file moves the old one to
  `storage/app/private/warning-letters/replaced/` (created automatically, inherits the 775 of the
  parent). These files are kept on purpose and are never deleted by the app, so the folder only
  grows; clean it up by hand if ever needed. Include it in file backups.

## Checks after the release

1. As **admin**: Administration > Warning Letters opens. Upload a test letter (a small PDF) for a
   test employee; it appears in the list and downloads under its original filename. The file
   exists under `storage/app/private/warning-letters/` with a random name.
2. As the **employee**: E-Leave > My Warning Letters shows only that letter. Download works. The
   admin notes are not visible anywhere.
3. As the employee's **HOD**: E-Leave > Warning Letters shows the letter. As a HOD of another
   department it does not appear, and its download URL returns 403.
4. As a **non-admin**, open `/admin/warning-letters/create`: it must return 403.
5. Edit the test letter (change the level, replace the file), then delete it. Delete is a soft
   delete: the row stays in the table with `deleted_at` set, and the file stays on disk.
6. Try a `.exe` or `.txt` file and a file over 5 MB: both must be rejected with a message.
7. **Audit log.** Open the test letter's edit page as admin: the History panel lists "Issued",
   "Edited", "File replaced" (with the kept file's stored name), and a "Downloaded" row for each
   download you made as admin, HOD and employee. After a file replacement the letter still shows the
   original uploader, and the old file exists under `warning-letters/replaced/`. Open the same edit
   URL as the HOD or the employee: 403. None of their pages show any history or notes.

## Access rules (enforced by `WarningLetterPolicy`, not by hiding buttons)

| Role | List | Download | Issue / edit / delete |
|---|---|---|---|
| Admin | all letters | any | yes |
| HOD | own letters + letters of employees in own department | same set | no (403) |
| Employee | own letters | own | no (403) |

A user or HOD with no department never matches the department rule. The HOD list is filtered in
the database query, not only in the view. The admin-only `notes` field is never loaded for the
HOD/employee list and is hidden from serialisation. The audit history is admin-only
(`viewHistory` in the policy, plus the admin-only route); downloads by HODs and employees are
logged but they cannot see the log.

## If this ships before T-006

`warning_letters.created_at`, `updated_at`, `deleted_at` and `warning_letter_events.created_at`
would hold UTC values at go-live, so add both tables to the T-006 scripts. In
`T-006_timezone_shift.sql`, add these blocks next to the other UPDATEs and add the four rows to the
`t006_touched` list (`warning_letters` x3, `warning_letter_events` x1):

```sql
UPDATE warning_letters SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at),
    deleted_at = IF(deleted_at < @cutoff, DATE_ADD(deleted_at, INTERVAL 8 HOUR), deleted_at);

UPDATE warning_letter_events SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at);
```

In `T-006_rollback.sql` add the same blocks with `DATE_SUB`. (`issued_date` is a DATE and is not
shifted.) Both tables are InnoDB, so they are not part of the MyISAM list in the T-006 notes. The
coverage check at the end of the T-006 script lists the tables as untouched if you forget.

## Rollback

1. Export both tables first (they hold the letter records and the audit history).
2. Run `docs/deploy/T-004_warning_letters_rollback.sql` in phpMyAdmin (starts with
   `USE hotelsys_hrsystem;`). It drops `warning_letter_events` and `warning_letters` (in that order,
   because of the foreign key) and removes both `migrations` rows.
3. Remove the Warning Letters additions from the live `routes/web.php`, `navbar.blade.php` and
   `User.php` (restore the copies you backed up before merging). Uploaded files remain on disk
   under `storage/app/private/warning-letters/`.

## Local note

The local `.env` (not committed, git-ignored) points at the local Laragon MySQL database
`eleave_local` on `127.0.0.1`. Never point it at `eleave_test` or at production.
After running the SQL script on production both migrations are recorded there, so a later
`php artisan migrate` does not try to create the tables twice. On a local database that already ran
`2026_10_09_000001` (like `eleave_local`), `php artisan migrate` applies the new
`2026_10_09_000002_add_audit_to_warning_letters` migration (adds `updated_by`, creates the events
table). It has not been run on `eleave_local` yet.
Tests (the Warning Letters tests build their own tables and run on in-memory SQLite, so they never
touch a real database): `DB_CONNECTION=sqlite DB_DATABASE=:memory: php vendor/bin/phpunit`.
The 4 `LeaveYearRolloverServiceTest` failures are a known SQLite-only problem (the old migrations
use MySQL-only `ALTER TABLE ... MODIFY COLUMN`), not caused by Warning Letters.
